<?php
/**
 * Release Artifact Download — auth-gated streaming endpoint.
 *
 * The release-artifacts/ directory has a .htaccess "Deny all" rule to prevent
 * anyone from guessing filenames and grabbing ZIPs that contain source code +
 * .env snapshots. This endpoint is the only legitimate path: admin-only, strict
 * filename validation, sends ZIP with attachment Content-Disposition.
 *
 * Usage:  GET /api/release-download.php?file=openplan.work-hosted-clean-...zip
 */

require_once __DIR__ . '/../config.php';
require_once INCLUDES_PATH . '/Auth.php';

// Auth gate — admin only.
if (!Auth::check()) {
    http_response_code(401);
    header('Content-Type: text/plain');
    echo 'Login required.';
    exit;
}
if (!Auth::isAdmin()) {
    http_response_code(403);
    header('Content-Type: text/plain');
    echo 'Admin only.';
    exit;
}

$requested = (string)($_GET['file'] ?? '');
if ($requested === '') {
    http_response_code(400);
    header('Content-Type: text/plain');
    echo 'Missing file parameter.';
    exit;
}

// Strict filename validation:
//  - only basename (no path separators or relative traversal)
//  - must end in .zip
//  - allowed chars: letters, digits, dot, hyphen, underscore
if (basename($requested) !== $requested) {
    http_response_code(400);
    header('Content-Type: text/plain');
    echo 'Invalid filename (path components rejected).';
    exit;
}
if (!preg_match('/^[A-Za-z0-9._-]+\.zip$/', $requested)) {
    http_response_code(400);
    header('Content-Type: text/plain');
    echo 'Invalid filename format.';
    exit;
}

$artifactDir = ROOT_PATH . '/release-artifacts';
$filepath = $artifactDir . '/' . $requested;

// Resolve symlinks etc and confirm result is still inside the artifact dir.
$realArtifactDir = realpath($artifactDir);
$realFile = realpath($filepath);
if ($realFile === false || $realArtifactDir === false || strpos($realFile, $realArtifactDir . DIRECTORY_SEPARATOR) !== 0) {
    http_response_code(404);
    header('Content-Type: text/plain');
    echo 'File not found.';
    exit;
}

if (!is_file($realFile)) {
    http_response_code(404);
    header('Content-Type: text/plain');
    echo 'File not found.';
    exit;
}

// Defense: clear any inherited output buffer before streaming binary.
while (ob_get_level() > 0) {
    ob_end_clean();
}

$filesize = filesize($realFile);

header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . basename($realFile) . '"');
header('Content-Length: ' . $filesize);
header('Cache-Control: private, no-store, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
header('X-Content-Type-Options: nosniff');

// Stream in chunks so memory stays low for large ZIPs.
$fp = @fopen($realFile, 'rb');
if ($fp === false) {
    http_response_code(500);
    header('Content-Type: text/plain');
    echo 'Cannot open file.';
    exit;
}
while (!feof($fp)) {
    echo fread($fp, 8192);
    flush();
}
fclose($fp);
exit;
