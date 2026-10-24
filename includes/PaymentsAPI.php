<?php
/**
 * PaymentsAPI — payment submission, admin approval, subscription provisioning.
 *
 * Storage:
 *   - payments collection in Database(APP_NAME) (global)
 *   - subscriptions collection in Database(APP_NAME) (global)
 *   - proof files at ROOT_PATH/uploads/payment-proofs/<userId>/<randomName>.<ext>
 *
 * Proof files are NEVER directly web-readable (.htaccess Deny + random
 * filenames). All reads go through api/payment-proof.php which checks
 * ownership or admin role.
 */

require_once __DIR__ . '/Database.php';

class PaymentsAPI {
    private const PROOF_MAX_BYTES = 10 * 1024 * 1024; // 10MB
    private const ALLOWED_MIME = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'application/pdf' => 'pdf'
    ];

    private Database $db;

    public function __construct() {
        $this->db = new Database(APP_NAME);
        $proofRoot = self::proofRoot();
        if (!is_dir($proofRoot)) {
            @mkdir($proofRoot, 0755, true);
        }
        $htaccess = $proofRoot . DIRECTORY_SEPARATOR . '.htaccess';
        if (!file_exists($htaccess)) {
            @file_put_contents($htaccess, "Deny from all\n<IfModule mod_authz_core.c>\n  Require all denied\n</IfModule>\n");
        }
    }

    public static function proofRoot(): string {
        return ROOT_PATH . '/uploads/payment-proofs';
    }

    public function submit(string $userId, string $planId, string $methodId, array $proofFile): array {
        if ($userId === '') {
            throw new Exception('Missing user');
        }
        if ($planId === '' || $methodId === '') {
            throw new Exception('Plan and payment method are required');
        }

        require_once __DIR__ . '/PlansAPI.php';
        require_once __DIR__ . '/PaymentMethodsAPI.php';
        $plansApi = new PlansAPI($this->db, 'plans');
        $methodsApi = new PaymentMethodsAPI($this->db, 'payment_methods');

        $plan = $plansApi->find($planId);
        if (!$plan || empty($plan['active'])) {
            throw new Exception('Plan unavailable');
        }
        $method = $methodsApi->find($methodId);
        if (!$method || empty($method['active'])) {
            throw new Exception('Payment method unavailable');
        }

        $stored = $this->storeProof($userId, $proofFile);

        $paymentId = $this->db->generateId();
        $record = [
            'id' => $paymentId,
            'userId' => $userId,
            'planId' => $planId,
            'planSnapshot' => [
                'name' => $plan['name'] ?? '',
                'price' => (float)($plan['price'] ?? 0),
                'currency' => $plan['currency'] ?? 'USD',
                'durationDays' => (int)($plan['durationDays'] ?? 30),
                'providers' => $plan['providers'] ?? [],
                'monthlyRequestCap' => (int)($plan['monthlyRequestCap'] ?? 0)
            ],
            'amount' => (float)($plan['price'] ?? 0),
            'currency' => $plan['currency'] ?? 'USD',
            'methodId' => $methodId,
            'methodSnapshot' => [
                'type' => $method['type'] ?? 'bank',
                'label' => $method['label'] ?? ''
            ],
            'proof' => [
                'filename' => $stored['filename'],
                'originalName' => $stored['originalName'],
                'mimeType' => $stored['mimeType'],
                'size' => $stored['size']
            ],
            'status' => 'pending',
            'submittedAt' => date('c'),
            'reviewedAt' => null,
            'reviewedBy' => null,
            'adminNote' => null,
            'rejectionReason' => null
        ];

        $payments = $this->db->load('payments', true);
        $payments[] = $record;
        $this->db->save('payments', $payments);

        if (class_exists('Audit')) {
            try {
                Audit::logData('create', 'payment', $paymentId, ['planId' => $planId, 'amount' => $record['amount']]);
            } catch (Throwable $e) {
                error_log('payment audit failed: ' . $e->getMessage());
            }
        }

        return $record;
    }

    public function find(string $id): ?array {
        $payments = $this->db->load('payments', true);
        foreach ($payments as $p) {
            if (($p['id'] ?? '') === $id) {
                return $p;
            }
        }
        return null;
    }

    public function getByUser(string $userId): array {
        $payments = $this->db->load('payments', true);
        $rows = array_values(array_filter($payments, fn($p) => ($p['userId'] ?? '') === $userId));
        usort($rows, fn($a, $b) => strcmp((string)($b['submittedAt'] ?? ''), (string)($a['submittedAt'] ?? '')));
        return $rows;
    }

    public function getAll(?string $status = null): array {
        $payments = $this->db->load('payments', true);
        if ($status !== null) {
            $payments = array_values(array_filter($payments, fn($p) => ($p['status'] ?? '') === $status));
        }
        usort($payments, fn($a, $b) => strcmp((string)($b['submittedAt'] ?? ''), (string)($a['submittedAt'] ?? '')));
        return $payments;
    }

    public function approve(string $paymentId, string $adminId, string $note = ''): array {
        // Acquire per-payment lock so two simultaneous approve clicks cannot
        // each pass the status check and produce duplicate subscriptions.
        $lockPath = sys_get_temp_dir() . '/lazyman_payment_' . md5($paymentId) . '.lock';
        $lockHandle = @fopen($lockPath, 'c');
        $haveLock = false;
        if ($lockHandle !== false) {
            $haveLock = flock($lockHandle, LOCK_EX);
        }
        try {
            return $this->approveLocked($paymentId, $adminId, $note);
        } finally {
            if ($lockHandle !== false) {
                if ($haveLock) {
                    flock($lockHandle, LOCK_UN);
                }
                fclose($lockHandle);
            }
        }
    }

    private function approveLocked(string $paymentId, string $adminId, string $note): array {
        $payments = $this->db->load('payments', true);
        $target = null;
        $idx = -1;
        foreach ($payments as $i => $p) {
            if (($p['id'] ?? '') === $paymentId) {
                $target = $p;
                $idx = $i;
                break;
            }
        }
        if ($target === null) {
            throw new Exception('Payment not found');
        }
        if (($target['status'] ?? '') === 'approved') {
            // Idempotent: payment already approved by a previous (concurrent)
            // request. Return the existing subscription rather than creating
            // another.
            $existingSub = null;
            if (!empty($target['subscriptionId'])) {
                $subs = $this->db->load('subscriptions', true);
                foreach ($subs as $s) {
                    if (($s['id'] ?? '') === $target['subscriptionId']) {
                        $existingSub = $s;
                        break;
                    }
                }
            }
            return ['payment' => $target, 'subscription' => $existingSub];
        }
        if (($target['status'] ?? '') !== 'pending' && ($target['status'] ?? '') !== 'under_review') {
            throw new Exception('Payment is not pending approval');
        }

        $subscription = $this->upsertSubscription($target);

        $payments[$idx]['status'] = 'approved';
        $payments[$idx]['reviewedAt'] = date('c');
        $payments[$idx]['reviewedBy'] = $adminId;
        $payments[$idx]['adminNote'] = $note;
        $payments[$idx]['subscriptionId'] = $subscription['id'];
        $this->db->save('payments', $payments);

        $this->enableSharedKeysForUser(
            $payments[$idx]['userId'] ?? '',
            $subscription
        );

        if (class_exists('Audit')) {
            try {
                Audit::logData('update', 'payment', $paymentId, ['status' => 'approved']);
                Audit::logData('create', 'subscription', $subscription['id'], ['userId' => $subscription['userId']]);
            } catch (Throwable $e) {
                error_log('approve audit failed: ' . $e->getMessage());
            }
        }

        $this->sendEmailToUser($payments[$idx]['userId'] ?? '', 'sendPaymentApprovedEmail', $payments[$idx]);

        return ['payment' => $payments[$idx], 'subscription' => $subscription];
    }

    private function enableSharedKeysForUser(string $userId, array $subscription): void {
        if ($userId === '') {
            return;
        }
        $masterPassword = getMasterPassword();
        if ($masterPassword === '') {
            return;
        }
        try {
            $userDb = new Database($masterPassword, $userId);
            $config = $userDb->load('config', false);
            if (!is_array($config)) {
                $config = [];
            }
            $config['useAdminSharedAiKeys'] = true;
            $config['paymentApprovalFlash'] = [
                'planName'  => (string)($subscription['planSnapshot']['name'] ?? 'Your plan'),
                'expiresAt' => (string)($subscription['expiresAt'] ?? ''),
                'providers' => is_array($subscription['providers'] ?? null) ? $subscription['providers'] : [],
                'at'        => date('c')
            ];
            $userDb->save('config', $config);
        } catch (Throwable $e) {
            error_log('enableSharedKeysForUser failed: ' . $e->getMessage());
        }
    }

    public function reject(string $paymentId, string $adminId, string $reason): array {
        $payments = $this->db->load('payments', true);
        $idx = -1;
        foreach ($payments as $i => $p) {
            if (($p['id'] ?? '') === $paymentId) {
                $idx = $i;
                break;
            }
        }
        if ($idx === -1) {
            throw new Exception('Payment not found');
        }
        if (!in_array($payments[$idx]['status'] ?? '', ['pending', 'under_review'], true)) {
            throw new Exception('Payment is not pending approval');
        }
        $payments[$idx]['status'] = 'rejected';
        $payments[$idx]['reviewedAt'] = date('c');
        $payments[$idx]['reviewedBy'] = $adminId;
        $payments[$idx]['rejectionReason'] = $reason;
        $this->db->save('payments', $payments);

        if (class_exists('Audit')) {
            try {
                Audit::logData('update', 'payment', $paymentId, ['status' => 'rejected']);
            } catch (Throwable $e) {
                error_log('reject audit failed: ' . $e->getMessage());
            }
        }
        $this->sendEmailToUser($payments[$idx]['userId'] ?? '', 'sendPaymentRejectedEmail', $payments[$idx]);
        return $payments[$idx];
    }

    private function sendEmailToUser(string $userId, string $method, ...$args): void {
        if ($userId === '' || !class_exists('Mailer')) {
            return;
        }
        try {
            $users = $this->db->load('users', true);
            $recipient = null;
            foreach ($users as $u) {
                if (($u['id'] ?? '') === $userId) {
                    $recipient = $u;
                    break;
                }
            }
            if (!$recipient || empty($recipient['email'])) {
                return;
            }
            $masterPassword = getMasterPassword();
            $senderId = class_exists('Auth') ? Auth::userId() : null;
            if (!$senderId || $masterPassword === '') {
                return;
            }
            $senderDb = new Database($masterPassword, $senderId);
            $config = $senderDb->load('config', false);
            $mailer = new Mailer(is_array($config) ? $config : []);
            if (method_exists($mailer, $method)) {
                $mailer->{$method}($recipient, ...$args);
            }
        } catch (Throwable $e) {
            error_log("PaymentsAPI email {$method} failed: " . $e->getMessage());
        }
    }

    public function getProofAbsolutePath(array $payment): ?string {
        $userId = $payment['userId'] ?? '';
        $filename = $payment['proof']['filename'] ?? '';
        if ($userId === '' || $filename === '') {
            return null;
        }
        if (!preg_match('/^[A-Za-z0-9_\\-]+$/', $userId) || !preg_match('/^[A-Za-z0-9_\\-.]+$/', $filename)) {
            return null;
        }
        $path = self::proofRoot() . '/' . $userId . '/' . $filename;
        if (!is_file($path)) {
            return null;
        }
        $real = realpath($path);
        $root = realpath(self::proofRoot());
        if ($real === false || $root === false || strpos($real, $root) !== 0) {
            return null;
        }
        return $real;
    }

    private function upsertSubscription(array $payment): array {
        $userId = $payment['userId'];
        $snapshot = $payment['planSnapshot'] ?? [];
        $durationDays = max(1, (int)($snapshot['durationDays'] ?? 30));

        $subs = $this->db->load('subscriptions', true);
        $now = time();
        $nowIso = date('c', $now);

        // Look for an existing active sub to EXTEND.
        $existingIdx = -1;
        foreach ($subs as $i => $s) {
            if (($s['userId'] ?? '') === $userId
                && ($s['status'] ?? '') === 'active'
                && strtotime((string)($s['expiresAt'] ?? '')) > $now) {
                $existingIdx = $i;
                break;
            }
        }

        if ($existingIdx >= 0) {
            $current = $subs[$existingIdx];
            $currentExpiry = strtotime((string)$current['expiresAt']);
            $newExpiry = $currentExpiry + ($durationDays * 86400);
            $subs[$existingIdx]['expiresAt'] = date('c', $newExpiry);
            $subs[$existingIdx]['planId'] = $payment['planId'];
            $subs[$existingIdx]['planSnapshot'] = $snapshot;
            $subs[$existingIdx]['providers'] = $snapshot['providers'] ?? [];
            $subs[$existingIdx]['monthlyRequestCap'] = (int)($snapshot['monthlyRequestCap'] ?? 0);
            $subs[$existingIdx]['paymentId'] = $payment['id'];
            $subs[$existingIdx]['updatedAt'] = $nowIso;
            $this->db->save('subscriptions', $subs);
            return $subs[$existingIdx];
        }

        $sub = [
            'id' => $this->db->generateId(),
            'userId' => $userId,
            'planId' => $payment['planId'],
            'planSnapshot' => $snapshot,
            'paymentId' => $payment['id'],
            'status' => 'active',
            'startsAt' => $nowIso,
            'expiresAt' => date('c', $now + $durationDays * 86400),
            'cancelledAt' => null,
            'providers' => $snapshot['providers'] ?? [],
            'monthlyRequestCap' => (int)($snapshot['monthlyRequestCap'] ?? 0),
            'createdAt' => $nowIso,
            'updatedAt' => $nowIso
        ];
        $subs[] = $sub;
        $this->db->save('subscriptions', $subs);
        return $sub;
    }

    private function storeProof(string $userId, array $file): array {
        if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('Proof file upload error');
        }
        if (($file['size'] ?? 0) > self::PROOF_MAX_BYTES) {
            throw new Exception('Proof file too large (max 10MB)');
        }
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = $finfo ? finfo_file($finfo, $file['tmp_name']) : null;
        if ($finfo) {
            finfo_close($finfo);
        }
        if (!$mime || !isset(self::ALLOWED_MIME[$mime])) {
            throw new Exception('Proof file type not allowed (PNG, JPG, WEBP, PDF only)');
        }
        $ext = self::ALLOWED_MIME[$mime];
        $userDir = self::proofRoot() . '/' . $userId;
        if (!is_dir($userDir)) {
            @mkdir($userDir, 0755, true);
        }
        $filename = bin2hex(random_bytes(12)) . '.' . $ext;
        $target = $userDir . '/' . $filename;
        if (!move_uploaded_file($file['tmp_name'], $target)) {
            throw new Exception('Failed to store proof file');
        }
        return [
            'filename' => $filename,
            'originalName' => basename((string)$file['name']),
            'mimeType' => $mime,
            'size' => filesize($target) ?: 0
        ];
    }
}
