<?php
/**
 * Payment QR Code Upload — admin only.
 *
 * POST multipart: { file: <png/jpg/webp> }
 * Returns: { url: "uploads/payment-qr/<random>.<ext>" }
 *
 * QR codes are publicly viewable (any user can see them at checkout), so no
 * Deny-all .htaccess. Random filename prevents enumeration but URLs are
 * intentionally direct-loadable for <img src=...> rendering.
 */

require_once __DIR__ . '/../config.php';
require_once INCLUDES_PATH . '/Auth.php';
require_once INCLUDES_PATH . '/Helpers.php';

if (!Auth::check()) {
    errorResponse('Unauthorized', 401);
    exit;
}
if (!Auth::isAdmin()) {
    errorResponse('Administrator access required', 403, 'FORBIDDEN');
    exit;
}
if (requestMethod() !== 'POST') {
    errorResponse('Method not allowed', 405);
    exit;
}

$csrf = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!Auth::isTokenAuth() && !Auth::validateCsrf($csrf)) {
    errorResponse('Invalid CSRF token', 403);
    exit;
}
if (empty($_FILES['file'])) {
    errorResponse('No file provided', 400, 'VALIDATION');
    exit;
}

$file = $_FILES['file'];
if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    errorResponse('Upload error code ' . (int)($file['error'] ?? -1), 400, 'UPLOAD_ERROR');
    exit;
}
$maxBytes = 5 * 1024 * 1024; // 5 MB — QR images are tiny.
if (($file['size'] ?? 0) > $maxBytes) {
    errorResponse('File too large (max 5 MB)', 400, 'TOO_LARGE');
    exit;
}

$allowed = [
    'image/png' => 'png',
    'image/jpeg' => 'jpg',
    'image/webp' => 'webp'
];
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = $finfo ? finfo_file($finfo, $file['tmp_name']) : null;
if ($finfo) {
    finfo_close($finfo);
}
if (!$mime || !isset($allowed[$mime])) {
    errorResponse('Only PNG / JPG / WEBP allowed for QR codes', 400, 'BAD_TYPE');
    exit;
}

$ext = $allowed[$mime];
$dir = ROOT_PATH . '/uploads/payment-qr';
if (!is_dir($dir)) {
    @mkdir($dir, 0755, true);
}

$filename = bin2hex(random_bytes(12)) . '.' . $ext;
$targetPath = $dir . '/' . $filename;
if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
    errorResponse('Failed to store QR file', 500, 'SERVER');
    exit;
}

@chmod($targetPath, 0644);

$publicUrl = 'uploads/payment-qr/' . $filename;

if (class_exists('Audit')) {
    try {
        Audit::logData('create', 'payment_qr', $filename, ['mime' => $mime, 'size' => $file['size'] ?? 0]);
    } catch (Throwable $e) {
        error_log('qr upload audit failed: ' . $e->getMessage());
    }
}

successResponse([
    'url' => $publicUrl,
    'filename' => $filename,
    'mimeType' => $mime,
    'size' => (int)($file['size'] ?? 0)
], 'QR uploaded');
