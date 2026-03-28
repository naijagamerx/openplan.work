<?php
/**
 * MCP API Token Management Endpoint
 *
 * Provides endpoints for managing MCP (Model Context Protocol) API tokens.
 * Each user can have their own unique token for MCP authentication.
 */

require_once __DIR__ . '/../config.php';

if (!Auth::check()) {
    errorResponse('Unauthorized', 401);
}

// Need master database for user operations (user-level operations)
$masterDb = new Database(getMasterPassword());
$auth = new Auth($masterDb);

$action = $_GET['action'] ?? 'get_token';
$userId = Auth::userId();

if ($userId === null) {
    errorResponse('User not authenticated', 401);
}

switch ($action) {
    case 'get_token':
        // Get current user's MCP API token (or null if not set)
        $token = $auth->getMcpApiToken($userId);

        if ($token === null) {
            successResponse([
                'token' => null,
                'hasToken' => false,
                'message' => 'No MCP API token generated yet'
            ]);
        }

        successResponse([
            'token' => $token,
            'hasToken' => true,
            'generatedAt' => null // Could be enhanced to return generation date
        ]);
        break;

    case 'generate_token':
        if (requestMethod() !== 'POST') {
            errorResponse('Method not allowed', 405);
        }

        $body = getJsonBody();
        $csrfToken = $body['csrf_token'] ?? '';

        // CSRF validation
        if (!Auth::validateCsrf($csrfToken)) {
            errorResponse('Invalid CSRF token', 403);
        }

        $result = $auth->generateMcpApiToken($userId);

        if (!$result['success']) {
            errorResponse($result['error'] ?? 'Failed to generate token');
        }

        successResponse([
            'token' => $result['token'],
            'message' => 'MCP API token generated successfully'
        ], 'Token generated');
        break;

    case 'revoke_token':
        if (requestMethod() !== 'POST') {
            errorResponse('Method not allowed', 405);
        }

        $body = getJsonBody();
        $csrfToken = $body['csrf_token'] ?? '';

        // CSRF validation
        if (!Auth::validateCsrf($csrfToken)) {
            errorResponse('Invalid CSRF token', 403);
        }

        $result = $auth->revokeMcpApiToken($userId);

        if (!$result['success']) {
            errorResponse($result['error'] ?? 'Failed to revoke token');
        }

        successResponse(null, 'Token revoked successfully');
        break;

    default:
        errorResponse('Invalid action', 400);
}
