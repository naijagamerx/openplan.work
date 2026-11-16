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
 *
 * Card payments (provider "payfast") have no proof file. Checkout creates a
 * record with status "awaiting_gateway"; a verified PayFast notification turns
 * it into "approved" and activates the plan. Each later recurring charge is a
 * new approved record pointing at the first one (parentPaymentId).
 *
 * Every load-modify-save of the payments file runs under lockPayments(), taken
 * before the subscriptions lock (never the other way round).
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

    /** Exclusive lock for a load-modify-save of the payments file. Release with unlockPayments(). */
    private function lockPayments() {
        $handle = @fopen(DATA_PATH . '/payments.lock', 'c');
        if ($handle !== false) {
            flock($handle, LOCK_EX);
        }
        return $handle;
    }

    private function unlockPayments($handle): void {
        if ($handle !== false) {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    public function submit(string $userId, string $planId, string $methodId, array $proofFile, array $termsAccepted = []): array {
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
            'termsAccepted' => $termsAccepted ?: null,
            'submittedAt' => date('c'),
            'reviewedAt' => null,
            'reviewedBy' => null,
            'adminNote' => null,
            'rejectionReason' => null
        ];

        $lock = $this->lockPayments();
        try {
            $payments = $this->db->load('payments', true);
            $payments[] = $record;
            $this->db->save('payments', $payments);
        } finally {
            $this->unlockPayments($lock);
        }

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
        // The payments lock also stops two simultaneous approve clicks from each
        // passing the status check and producing duplicate subscriptions.
        $lock = $this->lockPayments();
        try {
            return $this->approveLocked($paymentId, $adminId, $note);
        } finally {
            $this->unlockPayments($lock);
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

        // Platform AI keys and the "payment approved" notice now follow from the
        // global subscription record (see userUsesSharedKeys / consumeSubscriptionNotice).
        // Nothing here may write to the buyer's own encrypted files: this runs in the
        // admin's session, whose key cannot open them.

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

    public function reject(string $paymentId, string $adminId, string $reason): array {
        $lock = $this->lockPayments();
        try {
            $rejected = $this->rejectLocked($paymentId, $adminId, $reason);
        } finally {
            $this->unlockPayments($lock);
        }
        $this->sendEmailToUser($rejected['userId'] ?? '', 'sendPaymentRejectedEmail', $rejected);
        return $rejected;
    }

    private function rejectLocked(string $paymentId, string $adminId, string $reason): array {
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
            // Admin approving: use the admin's own mail settings. No session (a PayFast
            // notification): Mailer falls back to the SMTP settings in .env.
            $config = [];
            $senderId = class_exists('Auth') ? Auth::userId() : null;
            if ($senderId) {
                $masterPassword = getMasterPassword();
                if ($masterPassword !== '') {
                    $loaded = (new Database($masterPassword, $senderId))->load('config', false);
                    $config = is_array($loaded) ? $loaded : [];
                }
            }
            $mailer = new Mailer($config);
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

    /**
     * Pending record for a PayFast card checkout. Its id goes to PayFast as
     * m_payment_id and comes back on every notification for this purchase.
     * Reuses the user's own unpaid checkout for the same plan and amount from
     * the last 24 hours, so repeated clicks do not pile up records.
     */
    public function createCardCheckout(string $userId, array $plan, float $amount, ?array $frequency, array $termsAccepted = []): array {
        require_once __DIR__ . '/PayFast.php';
        if ($userId === '' || empty($plan['id'])) {
            throw new Exception('Missing user or plan');
        }
        $amount = round($amount, 2);
        $lock = $this->lockPayments();
        try {
            $payments = $this->db->load('payments', true);
            $cutoff = time() - 86400;
            foreach ($payments as $p) {
                if (($p['userId'] ?? '') === $userId
                    && ($p['provider'] ?? '') === 'payfast'
                    && ($p['status'] ?? '') === 'awaiting_gateway'
                    && ($p['planId'] ?? '') === (string)$plan['id']
                    && abs((float)($p['amount'] ?? 0) - $amount) < 0.005
                    && (bool)($p['recurring'] ?? false) === ($frequency !== null)
                    && (strtotime((string)($p['submittedAt'] ?? '')) ?: 0) >= $cutoff) {
                    return $p;
                }
            }
            $record = [
                'id' => $this->db->generateId(),
                'userId' => $userId,
                'planId' => (string)$plan['id'],
                'planSnapshot' => [
                    'name' => $plan['name'] ?? '',
                    'price' => (float)($plan['price'] ?? 0),
                    'currency' => $plan['currency'] ?? 'USD',
                    'durationDays' => (int)($plan['durationDays'] ?? 30),
                    'providers' => $plan['providers'] ?? [],
                    'monthlyRequestCap' => (int)($plan['monthlyRequestCap'] ?? 0)
                ],
                'amount' => $amount,
                'currency' => 'ZAR',
                'provider' => 'payfast',
                'methodId' => null,
                'methodSnapshot' => ['type' => 'card', 'label' => 'Card (PayFast)'],
                'recurring' => $frequency !== null,
                'billingPeriod' => $frequency[1] ?? '',
                'graceDays' => $frequency !== null ? PayFast::GRACE_DAYS : 0,
                'proof' => null,
                'status' => 'awaiting_gateway',
                'termsAccepted' => $termsAccepted ?: null,
                'submittedAt' => date('c'),
                'reviewedAt' => null,
                'reviewedBy' => null,
                'adminNote' => null,
                'rejectionReason' => null
            ];
            $payments[] = $record;
            $this->db->save('payments', $payments);
            return $record;
        } finally {
            $this->unlockPayments($lock);
        }
    }

    /**
     * Apply one PayFast notification that the caller has ALREADY verified
     * (signature, source address, PayFast's own VALID reply). Safe to repeat:
     * each charge (pf_payment_id) is applied at most once.
     * Returns ['result' => activated|renewed|duplicate|cancelled|failed|ignored|unknown|amount_mismatch, ...].
     */
    public function applyPayFastNotification(array $itn): array {
        $lock = $this->lockPayments();
        try {
            $outcome = $this->applyPayFastNotificationLocked($itn);
        } finally {
            $this->unlockPayments($lock);
        }
        if (in_array($outcome['result'], ['activated', 'renewed'], true) && !empty($outcome['payment'])) {
            $this->sendEmailToUser($outcome['payment']['userId'] ?? '', 'sendPaymentApprovedEmail', $outcome['payment']);
        }
        return $outcome;
    }

    private function applyPayFastNotificationLocked(array $itn): array {
        require_once __DIR__ . '/PayFast.php';
        $mId = trim((string)($itn['m_payment_id'] ?? ''));
        $pfId = trim((string)($itn['pf_payment_id'] ?? ''));
        $status = strtoupper(trim((string)($itn['payment_status'] ?? '')));
        $token = trim((string)($itn['token'] ?? ''));

        $payments = $this->db->load('payments', true);
        $idx = -1;
        foreach ($payments as $i => $p) {
            if (($p['provider'] ?? '') !== 'payfast' || !empty($p['parentPaymentId'])) {
                continue;
            }
            $matches = $mId !== ''
                ? ($p['id'] ?? '') === $mId
                : ($token !== '' && ($p['gatewayToken'] ?? '') === $token);
            if ($matches) {
                $idx = $i;
                break;
            }
        }
        if ($idx < 0) {
            return ['result' => 'unknown'];
        }
        $original = $payments[$idx];

        if ($status === 'CANCELLED') {
            if (($original['status'] ?? '') === 'awaiting_gateway') {
                $payments[$idx]['status'] = 'cancelled';
                $payments[$idx]['reviewedAt'] = date('c');
                $this->db->save('payments', $payments);
            }
            $subscription = $this->cancelAutoRenew((string)($original['subscriptionId'] ?? ''), $token !== '' ? $token : (string)($original['gatewayToken'] ?? ''));
            return ['result' => 'cancelled', 'subscriptionId' => $subscription['id'] ?? null];
        }

        if ($status === 'FAILED') {
            if (($original['status'] ?? '') === 'awaiting_gateway') {
                $payments[$idx]['status'] = 'failed';
                $payments[$idx]['reviewedAt'] = date('c');
                $this->db->save('payments', $payments);
            }
            return ['result' => 'failed'];
        }

        if ($status !== 'COMPLETE') {
            return ['result' => 'ignored'];
        }
        if ($pfId === '') {
            return ['result' => 'ignored'];
        }
        foreach ($payments as $p) {
            if (($p['gatewayPaymentId'] ?? '') === $pfId) {
                return ['result' => 'duplicate', 'paymentId' => $p['id'] ?? null];
            }
        }
        if (!PayFast::amountMatches((float)($original['amount'] ?? 0), $itn['amount_gross'] ?? null)) {
            error_log('PayFast ITN amount mismatch for payment ' . $original['id'] . ': expected '
                . number_format((float)($original['amount'] ?? 0), 2, '.', '') . ', got ' . (string)($itn['amount_gross'] ?? ''));
            return ['result' => 'amount_mismatch'];
        }

        $nowIso = date('c');
        $isFirst = ($original['status'] ?? '') !== 'approved';
        $charge = $original;
        if (!$isFirst) {
            // A later recurring charge: its own record, linked to the first one.
            $charge['id'] = $this->db->generateId();
            $charge['parentPaymentId'] = $original['id'];
            $charge['submittedAt'] = $nowIso;
            unset($charge['subscriptionId']);
        }
        $charge['status'] = 'approved';
        $charge['gatewayPaymentId'] = $pfId;
        if ($token !== '') {
            $charge['gatewayToken'] = $token;
        }
        $charge['amount'] = round((float)$itn['amount_gross'], 2);
        $charge['reviewedAt'] = $nowIso;
        $charge['reviewedBy'] = 'payfast';

        // Record the charge BEFORE touching the subscription: if anything fails after this,
        // a repeated notification is a duplicate and can never extend access twice.
        if ($isFirst) {
            $payments[$idx] = $charge;
            $chargeIdx = $idx;
        } else {
            $payments[] = $charge;
            $chargeIdx = array_key_last($payments);
        }
        $this->db->save('payments', $payments);

        $subscription = $this->upsertSubscription($charge);
        $payments[$chargeIdx]['subscriptionId'] = $subscription['id'];
        $this->db->save('payments', $payments);

        return [
            'result' => $isFirst ? 'activated' : 'renewed',
            'payment' => $payments[$chargeIdx],
            'subscriptionId' => $subscription['id'],
        ];
    }

    /** The user's live card subscription that will charge again, or null. */
    public function findActiveCardSubscription(string $userId): ?array {
        $subs = $this->db->load('subscriptions', true);
        $now = time();
        foreach ($subs as $s) {
            if (($s['userId'] ?? '') === $userId
                && ($s['status'] ?? '') === 'active'
                && ($s['provider'] ?? '') === 'payfast'
                && !empty($s['autoRenew'])
                && !empty($s['gatewayToken'])
                && (strtotime((string)($s['expiresAt'] ?? '')) ?: 0) > $now) {
                return $s;
            }
        }
        return null;
    }

    /**
     * Stop future charges on a subscription (matched by id, else by card token).
     * Access continues until expiresAt. Returns the updated subscription or null.
     */
    public function cancelAutoRenew(string $subscriptionId, string $token = ''): ?array {
        if ($subscriptionId === '' && $token === '') {
            return null;
        }
        require_once __DIR__ . '/SubscriptionHelper.php';
        $lock = saas_lockSubscriptions();
        try {
            $subs = $this->db->load('subscriptions', true);
            foreach ($subs as $i => $s) {
                $hit = ($subscriptionId !== '' && ($s['id'] ?? '') === $subscriptionId)
                    || ($subscriptionId === '' && $token !== '' && ($s['gatewayToken'] ?? '') === $token);
                if (!$hit) {
                    continue;
                }
                // A late cancel for an OLD card token must not stop the card that replaced it.
                if ($token !== '' && !empty($s['gatewayToken']) && $s['gatewayToken'] !== $token) {
                    return $s;
                }
                if (!empty($subs[$i]['autoRenew']) || empty($subs[$i]['cancelledAt'])) {
                    $subs[$i]['autoRenew'] = false;
                    $subs[$i]['cancelledAt'] = $subs[$i]['cancelledAt'] ?? date('c');
                    $subs[$i]['updatedAt'] = date('c');
                    $this->db->save('subscriptions', $subs);
                }
                return $subs[$i];
            }
        } finally {
            saas_unlockSubscriptions($lock);
        }
        return null;
    }

    private function upsertSubscription(array $payment): array {
        require_once __DIR__ . '/SubscriptionHelper.php';
        $lock = saas_lockSubscriptions();
        try {
            return $this->upsertSubscriptionLocked($payment);
        } finally {
            saas_unlockSubscriptions($lock);
        }
    }

    private function upsertSubscriptionLocked(array $payment): array {
        $userId = $payment['userId'];
        $snapshot = $payment['planSnapshot'] ?? [];
        $durationDays = max(1, (int)($snapshot['durationDays'] ?? 30));

        // Card subscriptions renew on the same day each month/year, so they extend by a
        // calendar period ("+1 month") instead of a fixed number of days, and a new one
        // gets a few grace days so a renewal that lands late does not cut access.
        $period = (string)($payment['billingPeriod'] ?? '');
        $extend = static function (int $from) use ($period, $durationDays): int {
            if ($period !== '') {
                $next = strtotime($period, $from);
                if ($next !== false && $next > $from) {
                    return $next;
                }
            }
            return $from + $durationDays * 86400;
        };
        $graceSeconds = max(0, (int)($payment['graceDays'] ?? 0)) * 86400;
        $gatewayFields = [];
        $provider = (string)($payment['provider'] ?? '');
        if ($provider !== '' && $provider !== 'manual') {
            $gatewayFields['provider'] = $provider;
            if (!empty($payment['gatewayToken'])) {
                $gatewayFields['gatewayToken'] = (string)$payment['gatewayToken'];
                $gatewayFields['autoRenew'] = true;
                $gatewayFields['cancelledAt'] = null;
            }
        }

        $subs = $this->db->load('subscriptions', true);
        $now = time();
        $nowIso = date('c', $now);

        // Look for an existing active sub to EXTEND. A free trial is never extended:
        // buying ends it and starts a fresh paid subscription, so no trial flag,
        // trial start date or trial request count carries over into the paid plan.
        $existingIdx = -1;
        foreach ($subs as $i => $s) {
            if (($s['userId'] ?? '') === $userId
                && ($s['status'] ?? '') === 'active'
                && strtotime((string)($s['expiresAt'] ?? '')) > $now) {
                if (!empty($s['trial'])) {
                    $subs[$i]['status'] = 'converted';
                    $subs[$i]['expiresAt'] = $nowIso;
                    $subs[$i]['convertedAt'] = $nowIso;
                    $subs[$i]['updatedAt'] = $nowIso;
                    continue;
                }
                $existingIdx = $i;
                break;
            }
        }

        if ($existingIdx >= 0) {
            $current = $subs[$existingIdx];
            $currentExpiry = strtotime((string)$current['expiresAt']);
            $newExpiry = $extend($currentExpiry);
            $subs[$existingIdx] = array_merge($subs[$existingIdx], $gatewayFields);
            $subs[$existingIdx]['expiresAt'] = date('c', $newExpiry);
            // A new period gets its own "expiring soon" email.
            $subs[$existingIdx]['expiringEmailSentAt'] = null;
            $subs[$existingIdx]['planId'] = $payment['planId'];
            $subs[$existingIdx]['planSnapshot'] = $snapshot;
            $subs[$existingIdx]['providers'] = $snapshot['providers'] ?? [];
            $subs[$existingIdx]['monthlyRequestCap'] = (int)($snapshot['monthlyRequestCap'] ?? 0);
            $subs[$existingIdx]['paymentId'] = $payment['id'];
            $subs[$existingIdx]['updatedAt'] = $nowIso;
            $subs[$existingIdx]['noticePending'] = true;
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
            'expiresAt' => date('c', $extend($now) + $graceSeconds),
            'cancelledAt' => null,
            'providers' => $snapshot['providers'] ?? [],
            'monthlyRequestCap' => (int)($snapshot['monthlyRequestCap'] ?? 0),
            'noticePending' => true,
            'createdAt' => $nowIso,
            'updatedAt' => $nowIso
        ];
        $sub = array_merge($sub, $gatewayFields);
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
