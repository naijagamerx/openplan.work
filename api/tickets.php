<?php
/**
 * Tickets API
 *
 * GET    /api/tickets.php — caller's tickets (admin: ?all=1 lists all).
 * GET    /api/tickets.php?id=X — single ticket (owner or admin).
 * POST   /api/tickets.php — create new ticket. body: { subject, body, priority? }
 * POST   /api/tickets.php?id=X&action=reply — add reply. body: { body }
 * POST   /api/tickets.php?id=X&action=close — close ticket (owner or admin).
 */

require_once __DIR__ . '/../config.php';
require_once INCLUDES_PATH . '/Database.php';
require_once INCLUDES_PATH . '/Auth.php';
require_once INCLUDES_PATH . '/Helpers.php';
require_once INCLUDES_PATH . '/TicketsAPI.php';

if (!Auth::check()) {
    errorResponse('Unauthorized', 401);
    exit;
}

$api = new TicketsAPI();
$method = requestMethod();
$id = $_GET['id'] ?? null;
$action = $_GET['action'] ?? null;

if ($method === 'GET') {
    if ($id) {
        $ticket = $api->find($id);
        if (!$ticket) {
            errorResponse('Ticket not found', 404, 'NOT_FOUND');
            exit;
        }
        if (($ticket['userId'] ?? '') !== Auth::userId() && !Auth::isAdmin()) {
            errorResponse('Forbidden', 403, 'FORBIDDEN');
            exit;
        }
        successResponse($ticket);
        exit;
    }
    if (!empty($_GET['all']) && Auth::isAdmin()) {
        successResponse($api->getAll());
    } else {
        successResponse($api->getByUser(Auth::userId()));
    }
    exit;
}

if ($method !== 'POST') {
    errorResponse('Method not allowed', 405);
    exit;
}

$body = getJsonBody();
$csrf = $body['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!Auth::isTokenAuth() && !Auth::validateCsrf($csrf)) {
    errorResponse('Invalid CSRF token', 403);
    exit;
}

try {
    if ($action === 'reply' && $id) {
        $ticket = $api->find($id);
        if (!$ticket) {
            errorResponse('Ticket not found', 404, 'NOT_FOUND');
        }
        if (($ticket['userId'] ?? '') !== Auth::userId() && !Auth::isAdmin()) {
            errorResponse('Forbidden', 403, 'FORBIDDEN');
        }
        $role = Auth::isAdmin() ? 'admin' : 'user';
        $updated = $api->addReply($id, Auth::userId(), $role, (string)($body['body'] ?? ''));
        successResponse($updated, 'Reply added');
        exit;
    }

    if ($action === 'close' && $id) {
        $ticket = $api->find($id);
        if (!$ticket) {
            errorResponse('Ticket not found', 404, 'NOT_FOUND');
        }
        if (($ticket['userId'] ?? '') !== Auth::userId() && !Auth::isAdmin()) {
            errorResponse('Forbidden', 403, 'FORBIDDEN');
        }
        $updated = $api->setStatus($id, 'closed');
        successResponse($updated, 'Ticket closed');
        exit;
    }

    // Create new ticket
    $ticket = $api->create(
        Auth::userId(),
        (string)($body['subject'] ?? ''),
        (string)($body['body'] ?? ''),
        (string)($body['priority'] ?? 'normal')
    );
    successResponse($ticket, 'Ticket created');
} catch (Throwable $e) {
    errorResponse($e->getMessage(), 400, 'ERROR');
}
