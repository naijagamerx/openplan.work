<?php
/**
 * Settings API Endpoint
 */

require_once __DIR__ . '/../config.php';

if (!Auth::check()) {
    errorResponse('Unauthorized', 401);
}

$db = new Database(getMasterPassword(), Auth::userId());

// Verify database connection works
try {
    $db->load('config', true);
} catch (Exception $e) {
    errorResponse('Database connection failed: Wrong master password', 401, ERROR_UNAUTHORIZED);
}

$action = $_GET['action'] ?? 'get';

switch ($action) {
    case 'get':
        $config = $db->load('config', true);
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
        $csrfToken = $body['csrf_token'] ?? '';

        // CSRF validation
        if (!Auth::validateCsrf($csrfToken)) {
            errorResponse('Invalid CSRF token', 403);
        }

        $section = $body['section'] ?? '';
        $config = $db->load('config', true);

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
        } elseif ($section === 'notifications') {
            $config['notificationsEnabled'] = filter_var($body['notificationsEnabled'] ?? $config['notificationsEnabled'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $config['waterReminderInterval'] = intval($body['waterReminderInterval'] ?? $config['waterReminderInterval'] ?? 60);
            $config['waterReminderGoal'] = intval($body['waterReminderGoal'] ?? $config['waterReminderGoal'] ?? 8);
        } else {
            errorResponse('Invalid section');
        }

        if ($db->save('config', $config)) {
            successResponse(null, 'Settings saved successfully');
        } else {
            errorResponse('Failed to save settings');
        }
        break;

    case 'get_notification_settings':
        $config = $db->load('config', true);
        $notificationSettings = [
            'notificationsEnabled' => $config['notificationsEnabled'] ?? false,
            'waterReminderInterval' => $config['waterReminderInterval'] ?? 60,
            'waterReminderGoal' => $config['waterReminderGoal'] ?? 8
        ];
        successResponse($notificationSettings);
        break;

    case 'save_notification_settings':
        if (requestMethod() !== 'POST') {
            errorResponse('Method not allowed', 405);
        }

        $body = getJsonBody();
        $csrfToken = $body['csrf_token'] ?? '';

        // CSRF validation
        if (!Auth::validateCsrf($csrfToken)) {
            errorResponse('Invalid CSRF token', 403);
        }

        $config = $db->load('config', true);
        $config['notificationsEnabled'] = filter_var($body['notificationsEnabled'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $config['waterReminderInterval'] = intval($body['waterReminderInterval'] ?? 60);
        $config['waterReminderGoal'] = intval($body['waterReminderGoal'] ?? 8);

        if ($db->save('config', $config)) {
            successResponse(null, 'Notification settings saved successfully');
        } else {
            errorResponse('Failed to save notification settings');
        }
        break;

    default:
        errorResponse('Invalid action', 400);
}

