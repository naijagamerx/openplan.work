<?php
/**
 * OAuth Authorize Endpoint — front-channel for Alexa account linking.
 *
 * GET  /api/oauth/authorize?client_id=...&redirect_uri=...&response_type=code&state=...&scope=...
 *      → if user not logged in, redirect to login
 *      → if logged in, show consent page (view)
 *      → ?error=access_denied&state=... back to redirect_uri if user denies
 *
 * POST /api/oauth/authorize (consent form submit with CSRF)
 *      → mint auth code (10 min TTL), redirect to redirect_uri?code=...&state=...
 */

require_once __DIR__ . '/../../config.php';
require_once INCLUDES_PATH . '/Auth.php';
require_once INCLUDES_PATH . '/AlexaOAuth.php';

// Persist Amazon's query string across login redirect.
$incoming = $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : $_GET;
$clientId    = trim((string)($incoming['client_id'] ?? ''));
$redirectUri = trim((string)($incoming['redirect_uri'] ?? ''));
$state       = (string)($incoming['state'] ?? '');
$scope       = (string)($incoming['scope'] ?? '');
$responseType= strtolower((string)($incoming['response_type'] ?? 'code'));
$codeChallenge = (string)($incoming['code_challenge'] ?? '');
$codeMethod  = (string)($incoming['code_challenge_method'] ?? '');

if ($responseType !== 'code') {
    http_response_code(400);
    echo 'Only response_type=code is supported.';
    exit;
}
if ($clientId !== AlexaOAuth::expectedClientId()) {
    http_response_code(400);
    echo 'Unknown client_id.';
    exit;
}
if (!AlexaOAuth::isValidRedirectUri($redirectUri)) {
    http_response_code(400);
    echo 'Invalid redirect_uri. Must be Amazon Alexa endpoint.';
    exit;
}

// Login gate. APP_URL is broken when accessed via /api/oauth/* because config.php
// only strips /api or /mobile suffix, not /api/oauth. Build site root manually.
if (!Auth::check()) {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'openplan.work';
    $siteRoot = $scheme . '://' . $host;
    $returnTo = '/api/oauth/authorize.php?' . http_build_query($incoming);
    header('Location: ' . $siteRoot . '/?page=login&redirect=' . urlencode($returnTo));
    exit;
}

$userId = Auth::userId() ?? '';
$user = Auth::user() ?? [];

// On POST: process consent
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = (string)($_POST['csrf_token'] ?? '');
    if (!Auth::validateCsrf($csrf)) {
        http_response_code(403);
        echo 'Invalid CSRF token. Please reload and try again.';
        exit;
    }
    $action = (string)($_POST['consent'] ?? '');

    if ($action === 'deny') {
        $sep = strpos($redirectUri, '?') === false ? '?' : '&';
        header('Location: ' . $redirectUri . $sep . 'error=access_denied&state=' . urlencode($state));
        exit;
    }

    if ($action === 'approve') {
        // Wrap user's data key on the fly.
        try {
            $masterPassword = function_exists('getMasterPassword') ? getMasterPassword() : '';
            if ($masterPassword === '') {
                throw new Exception('Master password not available in session. Please log out and back in.');
            }
            $wrapped = AlexaOAuth::wrapMasterPassword($masterPassword);
        } catch (Throwable $e) {
            http_response_code(500);
            echo 'Could not wrap data key: ' . htmlspecialchars($e->getMessage());
            exit;
        }

        $code = AlexaOAuth::mintAuthCode(
            $userId,
            $redirectUri,
            $codeChallenge !== '' ? $codeChallenge : null,
            $codeMethod !== '' ? $codeMethod : null,
            $wrapped
        );

        $sep = strpos($redirectUri, '?') === false ? '?' : '&';
        header('Location: ' . $redirectUri . $sep . 'code=' . urlencode($code) . '&state=' . urlencode($state));
        exit;
    }

    http_response_code(400);
    echo 'Unknown consent action.';
    exit;
}

// On GET: render consent page
$csrfToken = Auth::csrfToken();
$displayName = htmlspecialchars($user['name'] ?? $user['email'] ?? 'your OpenPlan account');
$siteName = function_exists('getSiteName') ? getSiteName() : 'OpenPlan';
$redirectHost = parse_url($redirectUri, PHP_URL_HOST) ?: 'amazon.com';

$contextVars = [
    'client_id' => $clientId,
    'redirect_uri' => $redirectUri,
    'state' => $state,
    'scope' => $scope,
    'response_type' => $responseType,
    'code_challenge' => $codeChallenge,
    'code_challenge_method' => $codeMethod
];

require __DIR__ . '/../../views/oauth-authorize.php';
