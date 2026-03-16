<?php
/**
 * Water Plan API Endpoint
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/BaseAPI.php';

if (!Auth::check()) {
    errorResponse('Unauthorized', 401);
}

// CSRF validation for non-GET requests (bypass for MCP)
if (requestMethod() !== 'GET' && !Auth::isMcp()) {
    $body = getJsonBody();
    $token = $body['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!Auth::validateCsrf($token)) {
        errorResponse('Invalid CSRF token', 403);
    }
}

$db = new Database(getMasterPassword(), Auth::userId());

// Helper to get today's entry
function getWaterEntry(array $tracker, string $date): ?array {
    foreach ($tracker as $entry) {
        if (isset($entry['date']) && $entry['date'] === $date) {
            return $entry;
        }
    }
    return null;
}

switch (requestMethod()) {
    case 'GET':
        $tracker = $db->load('water_tracker', true);
        $date = $_GET['date'] ?? date('Y-m-d');
        
        $entry = getWaterEntry($tracker, $date);
        
        // If requesting today and it doesn't exist, provide a default structure (but don't save yet)
        if (!$entry && $date === date('Y-m-d')) {
            $entry = [
                'date' => $date,
                'glasses' => 0,
                'goal' => 8, // Default
                'reminderInterval' => 60
            ];
        }

        $response = [
            'date' => $date,
            'entry' => $entry,
            'history' => array_slice($tracker, -7) // Last 7 entries
        ];
        
        successResponse($response);
        break;

    case 'POST':
        // Log glasses or set goal
        $body = getJsonBody();
        $action = $_GET['action'] ?? 'log'; // 'log' or 'set_goal'
        $date = $body['date'] ?? date('Y-m-d');
        
        $tracker = $db->load('water_tracker', true);
        $foundIndex = -1;
        
        foreach ($tracker as $index => $entry) {
            if ($entry['date'] === $date) {
                $foundIndex = $index;
                break;
            }
        }
        
        if ($foundIndex === -1) {
            // Create new entry
            $newEntry = [
                'id' => $db->generateId(),
                'date' => $date,
                'glasses' => 0,
                'goal' => 8,
                'reminderInterval' => 60,
                'createdAt' => date('c'),
                'updatedAt' => date('c')
            ];
            $tracker[] = $newEntry;
            $foundIndex = count($tracker) - 1;
        }
        
        $current = &$tracker[$foundIndex];
        
        if ($action === 'log') {
            $glasses = $body['glasses'] ?? 1; // Amount to add (can be negative)
            // If explicit 'total' is provided, set it. Otherwise add.
            if (isset($body['total'])) {
                $current['glasses'] = (int)$body['total'];
            } else {
                $current['glasses'] += (int)$glasses;
            }
            if ($current['glasses'] < 0) $current['glasses'] = 0;
        } elseif ($action === 'set_goal') {
            if (isset($body['goal'])) {
                $current['goal'] = (int)$body['goal'];
            }
            if (isset($body['reminderInterval'])) {
                $current['reminderInterval'] = (int)$body['reminderInterval'];
            }
        }
        
        $current['updatedAt'] = date('c');
        
        if ($db->save('water_tracker', $tracker)) {
            successResponse($current, 'Water plan updated');
        }
        
        errorResponse('Failed to save water plan', 500, ERROR_SERVER);
        break;

    default:
        errorResponse('Method not allowed', 405);
}

