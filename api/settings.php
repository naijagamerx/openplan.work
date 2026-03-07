<?php
/**
 * Settings API Endpoint
 */

require_once __DIR__ . '/../config.php';

if (!Auth::check()) {
    errorResponse('Unauthorized', 401);
}

$db = new Database(getMasterPassword());
$action = $_GET['action'] ?? 'get';

switch ($action) {
    case 'get':
        $config = $db->load('config');
        // Mask API keys for security
        if (!empty($config['groqApiKey'])) {
            $config['groqApiKey'] = substr($config['groqApiKey'], 0, 4) . '...' . substr($config['groqApiKey'], -4);
        }
        if (!empty($config['openrouterApiKey'])) {
            $config['openrouterApiKey'] = substr($config['openrouterApiKey'], 0, 4) . '...' . substr($config['openrouterApiKey'], -4);
        }
        successResponse($config);
        break;

    case 'save':
        if (requestMethod() !== 'POST') {
            errorResponse('Method not allowed', 405);
        }

        $body = getJsonBody();
        $section = $body['section'] ?? '';
        $config = $db->load('config');

        if ($section === 'business') {
            $config['businessName'] = trim($body['businessName'] ?? $config['businessName'] ?? '');
            $config['businessEmail'] = trim($body['businessEmail'] ?? $config['businessEmail'] ?? '');
            $config['businessPhone'] = trim($body['businessPhone'] ?? $config['businessPhone'] ?? '');
            $config['businessAddress'] = trim($body['businessAddress'] ?? $config['businessAddress'] ?? '');
            $config['currency'] = $body['currency'] ?? $config['currency'] ?? 'USD';
            $config['taxRate'] = floatval($body['taxRate'] ?? $config['taxRate'] ?? 0);
        } elseif ($section === 'api') {
            // Only update if provided (to avoid overwriting masked values from 'get' if not careful)
            if (isset($body['groqApiKey']) && !str_contains($body['groqApiKey'], '...')) {
                $config['groqApiKey'] = trim($body['groqApiKey']);
            }
            if (isset($body['openrouterApiKey']) && !str_contains($body['openrouterApiKey'], '...')) {
                $config['openrouterApiKey'] = trim($body['openrouterApiKey']);
            }
        } else {
            errorResponse('Invalid section');
        }

        if ($db->save('config', $config)) {
            successResponse(null, 'Settings saved successfully');
        } else {
            errorResponse('Failed to save settings');
        }
        break;

    default:
        errorResponse('Invalid action', 400);
}
