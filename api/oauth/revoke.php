<?php
/**
 * OAuth Revoke Endpoint — user-initiated unlink from Settings page.
 *
 * Authenticated route. Marks all alexa_links rows for the current user as revoked.
 * Future skill requests with their access tokens → LinkAccount card.
 */

require_once __DIR__ . '/../../config.php';
require_once INCLUDES_PATH . '/Auth.php';
require_once INCLUDES_PATH . '/AlexaOAuth.php';

header('Content-Type: application/json');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'POST only']);
    exit;
}

if (!Auth::check()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$body = file_get_contents('php://input');
$payload = json_decode($body, true);
if (!is_array($payload)) $payload = $_POST;

$csrf = (string)($payload['csrf_token'] ?? '');
if (!Auth::validateCsrf($csrf)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit;
}

$userId = Auth::userId() ?? '';
if ($userId === '') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No user ID']);
    exit;
}

$count = AlexaOAuth::revokeByOpenplanUser($userId);
echo json_encode([
    'success' => true,
    'revokedCount' => $count,
    'message' => $count > 0 ? "Unlinked $count Alexa link(s)." : 'No active Alexa links found.'
]);
