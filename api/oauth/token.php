<?php
/**
 * OAuth Token Endpoint — back-channel for Alexa account linking.
 *
 * Amazon POSTs here with:
 *   grant_type=authorization_code   (first exchange)
 *   grant_type=refresh_token        (token refresh)
 * Client credentials are sent either in body (client_id + client_secret) or
 * via HTTP Basic auth header.
 */

require_once __DIR__ . '/../../config.php';
require_once INCLUDES_PATH . '/AlexaOAuth.php';

header('Cache-Control: no-store');
header('Pragma: no-cache');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    header('Content-Type: application/json');
    echo json_encode(['error' => 'invalid_request', 'error_description' => 'POST only']);
    exit;
}

// Read client credentials from body or HTTP Basic
$clientId = (string)($_POST['client_id'] ?? '');
$clientSecret = (string)($_POST['client_secret'] ?? '');

if ($clientId === '' || $clientSecret === '') {
    $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (stripos($auth, 'Basic ') === 0) {
        $decoded = base64_decode(substr($auth, 6), true);
        if ($decoded !== false && strpos($decoded, ':') !== false) {
            [$clientId, $clientSecret] = explode(':', $decoded, 2);
        }
    }
}

if (!AlexaOAuth::validateClientCredentials($clientId, $clientSecret)) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'invalid_client', 'error_description' => 'Bad client credentials']);
    exit;
}

$grantType = (string)($_POST['grant_type'] ?? '');

if ($grantType === 'authorization_code') {
    $code = (string)($_POST['code'] ?? '');
    $redirectUri = (string)($_POST['redirect_uri'] ?? '');
    $codeVerifier = (string)($_POST['code_verifier'] ?? '');

    if ($code === '' || $redirectUri === '') {
        http_response_code(400);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'invalid_request', 'error_description' => 'Missing code or redirect_uri']);
        exit;
    }

    $consumed = AlexaOAuth::consumeAuthCode($code, $redirectUri, $codeVerifier !== '' ? $codeVerifier : null);
    if ($consumed === null) {
        http_response_code(400);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'invalid_grant', 'error_description' => 'Auth code invalid, expired, or PKCE check failed']);
        exit;
    }

    // Amazon doesn't pass its own userId on this exchange — it's revealed later
    // via the access token-resolved request from the skill endpoint. So we leave
    // amazonUserId empty here; the first incoming skill request will fill it.
    $tokens = AlexaOAuth::issueTokensForCode($consumed, null);

    header('Content-Type: application/json');
    echo json_encode($tokens);
    exit;
}

if ($grantType === 'refresh_token') {
    $refresh = (string)($_POST['refresh_token'] ?? '');
    if ($refresh === '') {
        http_response_code(400);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'invalid_request', 'error_description' => 'Missing refresh_token']);
        exit;
    }
    $tokens = AlexaOAuth::refreshAccessToken($refresh);
    if ($tokens === null) {
        http_response_code(400);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'invalid_grant', 'error_description' => 'Unknown or revoked refresh_token']);
        exit;
    }
    header('Content-Type: application/json');
    echo json_encode($tokens);
    exit;
}

http_response_code(400);
header('Content-Type: application/json');
echo json_encode(['error' => 'unsupported_grant_type', 'error_description' => $grantType]);
exit;
