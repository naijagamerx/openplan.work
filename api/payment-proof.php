<?php
/**
 * Payment Proof Streaming Endpoint
 *
 * GET /api/payment-proof.php?id=<paymentId>
 *
 * Streams the proof file ONLY if the caller is the payment owner OR an admin.
 * Files live under /uploads/payment-proofs/<userId>/ with random filenames
 * and a Deny-all .htaccess; this endpoint is the sole access path.
 */

require_once __DIR__ . '/../config.php';
require_once INCLUDES_PATH . '/Database.php';
require_once INCLUDES_PATH . '/Auth.php';
require_once INCLUDES_PATH . '/Helpers.php';
require_once INCLUDES_PATH . '/PaymentsAPI.php';

if (!Auth::check()) {
    http_response_code(401);
    echo 'Unauthorized';
    exit;
}

// Flush any inherited output buffer so readfile() can stream cleanly.
while (ob_get_level() > 0) {
    ob_end_clean();
}

$paymentId = (string)($_GET['id'] ?? '');
if ($paymentId === '') {
    http_response_code(400);
    echo 'Payment ID required';
    exit;
}

$api = new PaymentsAPI();
$payment = $api->find($paymentId);
if (!$payment) {
    http_response_code(404);
    echo 'Not found';
    exit;
}

if (($payment['userId'] ?? '') !== Auth::userId() && !Auth::isAdmin()) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

$path = $api->getProofAbsolutePath($payment);
if ($path === null) {
    http_response_code(404);
    echo 'Proof file missing';
    exit;
}

$mime = $payment['proof']['mimeType'] ?? 'application/octet-stream';
$originalName = $payment['proof']['originalName'] ?? 'proof';
$disposition = isset($_GET['download']) ? 'attachment' : 'inline';
$size = @filesize($path);

header('Content-Type: ' . $mime);
if ($size !== false) {
    header('Content-Length: ' . $size);
}
header('Content-Disposition: ' . $disposition . '; filename="' . str_replace('"', '', $originalName) . '"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');

readfile($path);
exit;
