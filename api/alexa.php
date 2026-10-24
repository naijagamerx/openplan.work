<?php
/**
 * Alexa Custom Skill Endpoint
 *
 * Receives POST requests from Alexa cloud, verifies signature + timestamp,
 * routes to AlexaHandler for intent dispatch, returns JSON response.
 *
 * Endpoint URL to set in Alexa Developer Console:
 *   https://openplan.work/api/alexa.php
 *
 * Required environment variables (set in Hostinger panel / .env):
 *   ALEXA_OWNER_USER_ID    — UUID of the user whose data Alexa exposes
 *   LAZYMAN_MASTER_PASSWORD — master password for that user's encryption
 *
 * Optional:
 *   ALEXA_DEV_BYPASS_SIGNATURE=1 — skip signature verification (DEV ONLY)
 */

require_once __DIR__ . '/../config.php';
require_once INCLUDES_PATH . '/AlexaSignatureVerifier.php';
require_once INCLUDES_PATH . '/AlexaHandler.php';

// Alexa sends application/json. Reject anything else early.
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    header('Content-Type: text/plain');
    echo 'Alexa endpoint accepts POST only';
    exit;
}

// Flush any inherited output buffer so the JSON response is clean.
while (ob_get_level() > 0) {
    ob_end_clean();
}

$rawBody = file_get_contents('php://input');
if ($rawBody === false || $rawBody === '') {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Empty request body']);
    exit;
}

// Header lookup must be case-insensitive. PHP normalizes incoming headers
// inconsistently across SAPIs; build our own map.
$headers = [];
foreach ($_SERVER as $k => $v) {
    if (strpos($k, 'HTTP_') === 0) {
        $name = strtolower(str_replace('_', '', substr($k, 5)));
        $headers[$name] = $v;
    }
}

// Signature verification — can be bypassed during local testing only.
$bypass = (bool)((int)getenv('ALEXA_DEV_BYPASS_SIGNATURE'));
if (!$bypass) {
    $check = AlexaSignatureVerifier::verify($rawBody, $headers);
    if (empty($check['valid'])) {
        error_log('Alexa signature rejected: ' . ($check['error'] ?? 'unknown'));
        http_response_code(400);
        header('Content-Type: text/plain');
        echo 'Signature verification failed';
        exit;
    }
}

$event = json_decode($rawBody, true);
if (!is_array($event)) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Malformed JSON']);
    exit;
}

// Extract Alexa account-linking access token (multi-user mode).
// If absent, AlexaHandler falls back to env-var owner mode.
$accessToken = $event['session']['user']['accessToken'] ?? null;

$ownerConfigured = (getenv('ALEXA_OWNER_USER_ID') ?: '') !== '' && (getenv('LAZYMAN_MASTER_PASSWORD') ?: '') !== '';

if ($accessToken === null && !$ownerConfigured) {
    // No access token AND no owner env vars → user must link account.
    $response = AlexaHandler::linkAccountCardResponse();
} else {
    try {
        $handler = new AlexaHandler($accessToken);
        $response = $handler->handle($event);
    } catch (AlexaUnauthorizedException $e) {
        error_log('Alexa unauthorized: ' . $e->getMessage());
        $response = AlexaHandler::linkAccountCardResponse('Your account link has expired. Please re-link in the Alexa app.');
    } catch (Throwable $e) {
        error_log('AlexaHandler failure: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());

        $msg = $e->getMessage();
        if (stripos($msg, 'Alexa skill not configured') !== false) {
            $speech = 'The skill is not configured. Set environment variables or enable account linking.';
        } elseif (stripos($msg, 'Decryption failed') !== false || stripos($msg, 'master password') !== false) {
            $speech = 'The master password does not match. If you recently changed it, please re-link your Amazon account.';
        } else {
            $speech = 'Sorry, something went wrong on the server. Check the PHP error log for details.';
        }

        $response = [
            'version' => '1.0',
            'response' => [
                'outputSpeech' => [
                    'type' => 'PlainText',
                    'text' => $speech
                ],
                'shouldEndSession' => true
            ]
        ];
    }
}

header('Content-Type: application/json');
echo json_encode($response);
exit;
