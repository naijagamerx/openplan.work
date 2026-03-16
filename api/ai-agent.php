<?php
/**
 * AI Agent API Endpoint
 *
 * Provides REST API for the AI Agent system with function calling capabilities.
 * All endpoints require authentication and valid CSRF tokens.
 */

require_once __DIR__ . '/../config.php';

// Set JSON response header
header('Content-Type: application/json');

// Allow long execution time for AI loops
set_time_limit(300);

// Check authentication
if (!Auth::check()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// Unlock session file to allow concurrent polling requests
session_write_close();

// Get master password and initialize database
$masterPassword = getMasterPassword();
$db = new Database($masterPassword, Auth::userId());
$config = $db->load('config', true);

// Get action from query parameter
$action = $_GET['action'] ?? 'chat';

try {
    // Initialize agent
    $provider = $_GET['provider'] ?? $config['aiProvider'] ?? 'groq';
    $agent = new AIAgent($db, $config, $provider);

    switch ($action) {
        case 'chat':
            // Handle chat message
            $body = getJsonBody();

            // Debug logging (Disabled for production)
            // error_log('AI Agent Chat Request: ' . json_encode([ ... ]));

            // Validate required fields
            if (empty($body['message'])) {
                errorResponse('Message is required', 400);
            }

            // Validate CSRF token (always required for chat)
            $csrfToken = $body['csrf_token'] ?? '';
            if (!Auth::validateCsrf($csrfToken)) {
                error_log('AI Agent Error: Invalid CSRF token.');
                errorResponse('Invalid CSRF token', 403);
            }

            // Convert string "null" to actual null
            $conversationId = $body['conversationId'] ?? null;
            if ($conversationId === 'null' || $conversationId === '') {
                $conversationId = null;
            }

            try {
                $result = $agent->chat(
                    $body['message'] ?? '',
                    $conversationId,
                    $body['autoConfirm'] ?? false,
                    $body['provider'] ?? null,
                    $body['model'] ?? null,
                    $body['kbFolderId'] ?? null
                );
                successResponse($result);
            } catch (Exception $e) {
                error_log('AI Agent: Chat method threw exception: ' . $e->getMessage());
                throw $e;
            }
            break;

        case 'confirm_action':
            // Handle action confirmation
            $body = getJsonBody();

            if (empty($body['conversationId']) || empty($body['actionId'])) {
                errorResponse('conversationId and actionId are required', 400);
            }

            if (!Auth::validateCsrf($body['csrf_token'] ?? '')) {
                errorResponse('Invalid CSRF token', 403);
            }

            $result = $agent->handleConfirmation(
                $body['conversationId'],
                $body['actionId'],
                $body['approved'] ?? false
            );

            successResponse($result);
            break;

        case 'list_conversations':
            // List all conversations for current user
            $limit = (int)($_GET['limit'] ?? 50);
            $conversations = $agent->listConversations($limit);
            successResponse(['conversations' => $conversations]);
            break;

        case 'get_conversation':
            // Get single conversation details
            $conversationId = $_GET['id'] ?? '';
            if (empty($conversationId)) {
                errorResponse('Conversation ID is required', 400);
            }

            $memory = new ConversationMemory($db, $conversationId);
            $conversation = $memory->loadConversation($conversationId);

            if (!$conversation) {
                errorResponse('Conversation not found', 404);
            }

            $stats = $memory->getStats();
            successResponse([
                'conversation' => $conversation,
                'stats' => $stats
            ]);
            break;

        case 'delete_conversation':
            // Delete a conversation
            $body = getJsonBody();
            $conversationId = $body['id'] ?? $_GET['id'] ?? '';

            if (empty($conversationId)) {
                errorResponse('Conversation ID is required', 400);
            }

            if (!Auth::validateCsrf($body['csrf_token'] ?? '')) {
                errorResponse('Invalid CSRF token', 403);
            }

            $deleted = $agent->deleteConversation($conversationId);
            successResponse([
                'deleted' => $deleted,
                'message' => $deleted ? 'Conversation deleted' : 'Conversation not found'
            ]);
            break;

        case 'export_conversation':
            // Export conversation as markdown
            $conversationId = $_GET['id'] ?? '';
            if (empty($conversationId)) {
                errorResponse('Conversation ID is required', 400);
            }

            $markdown = $agent->exportConversation($conversationId);

            if (empty($markdown)) {
                errorResponse('Conversation not found', 404);
            }

            // Return as markdown file download
            header('Content-Type: text/markdown');
            header('Content-Disposition: attachment; filename="conversation-' . $conversationId . '.md"');
            echo $markdown;
            exit;

        case 'clear_history':
            // Clear all messages from a conversation
            $body = getJsonBody();
            $conversationId = $body['conversationId'] ?? $body['id'] ?? '';

            if (empty($conversationId)) {
                errorResponse('Conversation ID is required', 400);
            }

            if (!Auth::validateCsrf($body['csrf_token'] ?? '')) {
                errorResponse('Invalid CSRF token', 403);
            }

            $memory = new ConversationMemory($db, $conversationId);
            $memory->clearMessages();

            successResponse(['message' => 'History cleared']);
            break;

        case 'clear_all_conversations':
            // Delete ALL conversations for the current user
            $body = getJsonBody();

            if (!Auth::validateCsrf($body['csrf_token'] ?? '')) {
                errorResponse('Invalid CSRF token', 403);
            }

            $conversations = $db->load('conversations', true) ?? [];
            $userId = Auth::userId();

            // Filter out all conversations for current user
            $filtered = array_filter($conversations, function($conv) use ($userId) {
                return $conv['userId'] !== $userId;
            });

            // Save the filtered list (removing all user's conversations)
            $db->save('conversations', array_values($filtered));

            successResponse(['message' => 'All conversations cleared']);
            break;

        case 'update_title':
            // Update conversation title
            $body = getJsonBody();
            $conversationId = $body['id'] ?? '';
            $title = $body['title'] ?? '';

            if (empty($conversationId) || empty($title)) {
                errorResponse('Conversation ID and title are required', 400);
            }

            if (!Auth::validateCsrf($body['csrf_token'] ?? '')) {
                errorResponse('Invalid CSRF token', 403);
            }

            $memory = new ConversationMemory($db, $conversationId);
            $updated = $memory->updateTitle($conversationId, $title);

            successResponse([
                'updated' => $updated,
                'message' => $updated ? 'Title updated' : 'Conversation not found'
            ]);
            break;

        case 'cancel':
            $body = getJsonBody();
            $conversationId = $body['conversationId'] ?? '';
            if (empty($conversationId)) {
                errorResponse('Conversation ID is required', 400);
            }

            if (!Auth::validateCsrf($body['csrf_token'] ?? '')) {
                errorResponse('Invalid CSRF token', 403);
            }

            $memory = new ConversationMemory($db, $conversationId);
            $updated = $memory->setCancelled($conversationId, true);
            successResponse([
                'cancelled' => $updated,
                'message' => $updated ? 'Cancellation requested' : 'Conversation not found'
            ]);
            break;

        default:
            errorResponse('Invalid action', 400);
    }

} catch (APIException $e) {
    http_response_code($e->getStatusCode());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'code' => $e->getErrorCode(),
        'context' => $e->getContext()
    ]);
    exit;

} catch (ValidationException $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'code' => 'VALIDATION_ERROR'
    ]);
    exit;

} catch (Exception $e) {
    error_log('AI Agent unhandled exception: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'An unexpected error occurred'
    ]);
    exit;
}

