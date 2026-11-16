<?php
/**
 * PayFast payment notifications (ITN).
 *
 * Called by PayFast's servers, never by a browser, so there is no session and no
 * CSRF token. A notification can change nothing until it passes every check:
 *   1. its signature matches our passphrase
 *   2. it came from a PayFast address
 *   3. it is for our merchant ID
 *   4. PayFast itself confirms the data (answers VALID)
 *   5. (in PaymentsAPI) it matches a card checkout we created, for the same amount,
 *      and its charge ID has not been applied before
 *
 * Replies: 200 once handled (including duplicates and ignored statuses), 4xx when
 * it fails a check, 5xx on a temporary failure so PayFast sends it again; repeats
 * are safe because each charge is applied at most once.
 */

require_once __DIR__ . '/../config.php';
require_once INCLUDES_PATH . '/PayFast.php';
require_once INCLUDES_PATH . '/PaymentsAPI.php';

header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store');

function payfastItnStop(int $status, string $logReason = ''): void {
    if ($logReason !== '') {
        error_log('PayFast ITN rejected: ' . $logReason);
    }
    http_response_code($status);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    payfastItnStop(405);
}

$config = PayFast::config();
if (!PayFast::isConfigured($config)) {
    payfastItnStop(503, 'PayFast is not configured on this site');
}

// $_POST keeps the order PayFast sent, which the signature depends on.
$post = $_POST;
if ($post === [] || count($post) > 100) {
    payfastItnStop(400, 'empty or oversized notification');
}
foreach ($post as $key => $value) {
    if (!is_string($value) || !preg_match('/^[a-z0-9_]{1,64}$/', (string)$key)) {
        payfastItnStop(400, 'malformed field');
    }
}

$ref = 'm_payment_id=' . substr(preg_replace('/[^A-Za-z0-9-]/', '', (string)($post['m_payment_id'] ?? '')), 0, 64)
    . ' pf_payment_id=' . substr(preg_replace('/[^A-Za-z0-9-]/', '', (string)($post['pf_payment_id'] ?? '')), 0, 64);

if (!PayFast::itnSignatureValid($post, $config['passphrase'])) {
    payfastItnStop(400, 'bad signature (' . $ref . ')');
}
if (!PayFast::sourceIpTrusted($_SERVER)) {
    $seen = trim((string)($_SERVER['REMOTE_ADDR'] ?? '')) . ' / xff-last ' . trim((string)(array_reverse(explode(',', (string)($_SERVER['HTTP_X_FORWARDED_FOR'] ?? '')))[0] ?? ''));
    payfastItnStop(403, 'source address not PayFast: ' . $seen . ' (' . $ref . ')');
}
if (!hash_equals($config['merchantId'], (string)($post['merchant_id'] ?? ''))) {
    payfastItnStop(400, 'merchant id mismatch (' . $ref . ')');
}
if (!PayFast::confirmWithPayFast(PayFast::itnParamString($post), $config['sandbox'])) {
    // Also covers a network failure; PayFast retries on a non-200 reply.
    payfastItnStop(503, 'PayFast did not confirm the notification (' . $ref . ')');
}

try {
    $outcome = (new PaymentsAPI())->applyPayFastNotification($post);
} catch (Throwable $e) {
    error_log('PayFast ITN processing failed (' . $ref . '): ' . $e->getMessage());
    payfastItnStop(500);
}

error_log('PayFast ITN ' . $outcome['result'] . ' (' . $ref . ')');
if (in_array($outcome['result'], ['unknown', 'amount_mismatch'], true)) {
    payfastItnStop(400);
}
http_response_code(200);
echo 'OK';
