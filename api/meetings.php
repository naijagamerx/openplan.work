<?php
/**
 * Meetings API Endpoint
 * CRUD operations for calendar meetings (separate from tasks).
 */

require_once __DIR__ . '/../config.php';
require_once INCLUDES_PATH . '/Database.php';
require_once INCLUDES_PATH . '/Auth.php';
require_once INCLUDES_PATH . '/Helpers.php';
require_once INCLUDES_PATH . '/MeetingsAPI.php';

if (!Auth::check()) {
    errorResponse('Unauthorized', 401);
    exit;
}

$db = new Database(getMasterPassword(), Auth::userId());
$meetingsAPI = new MeetingsAPI($db, 'meetings');

$action = $_GET['action'] ?? null;
$id = $_GET['id'] ?? null;
$isMCP = Auth::isMcp();

switch (requestMethod()) {
    case 'GET':
        if ($id) {
            $meeting = $meetingsAPI->find($id);
            if ($meeting) {
                successResponse($meeting);
            }
            errorResponse('Meeting not found', 404, ERROR_NOT_FOUND);
        }

        $filters = [];
        if (!empty($_GET['month']) && !empty($_GET['year'])) {
            $filters['month'] = (int)$_GET['month'];
            $filters['year'] = (int)$_GET['year'];
        }
        if (!empty($_GET['date'])) {
            $filters['date'] = $_GET['date'];
        }
        if (!empty($_GET['projectId'])) {
            $filters['projectId'] = $_GET['projectId'];
        }
        if (!empty($_GET['clientId'])) {
            $filters['clientId'] = $_GET['clientId'];
        }

        successResponse($meetingsAPI->findAll($filters));
        break;

    case 'POST':
        $body = getJsonBody();
        $csrfToken = $body['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!$isMCP && !Auth::validateCsrf($csrfToken)) {
            errorResponse('Invalid CSRF token', 403);
        }

        if ($action === 'update' && $id) {
            $meeting = $meetingsAPI->update($id, $body);
            if ($meeting) {
                successResponse($meeting, 'Meeting updated');
            }
            errorResponse('Meeting not found', 404, ERROR_NOT_FOUND);
        }

        $validationError = $meetingsAPI->validateRequired($body, ['title', 'date']);
        if ($validationError) {
            errorResponse($validationError, 400, ERROR_VALIDATION);
        }

        $meeting = $meetingsAPI->create($body);
        if ($meeting) {
            successResponse($meeting, 'Meeting created');
        }

        errorResponse('Failed to create meeting', 500, ERROR_SERVER);
        break;

    case 'PUT':
    case 'PATCH':
        if (!$id) {
            errorResponse('Meeting ID required', 400, ERROR_VALIDATION);
        }
        $body = getJsonBody();
        $csrfToken = $body['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!$isMCP && !Auth::validateCsrf($csrfToken)) {
            errorResponse('Invalid CSRF token', 403);
        }
        $meeting = $meetingsAPI->update($id, $body);
        if ($meeting) {
            successResponse($meeting, 'Meeting updated');
        }
        errorResponse('Meeting not found', 404, ERROR_NOT_FOUND);
        break;

    case 'DELETE':
        if (!$id) {
            errorResponse('Meeting ID required', 400, ERROR_VALIDATION);
        }
        $csrfToken = $_GET['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!$isMCP && !Auth::validateCsrf($csrfToken)) {
            errorResponse('Invalid CSRF token', 403);
        }
        if ($meetingsAPI->delete($id)) {
            successResponse(null, 'Meeting deleted');
        }
        errorResponse('Meeting not found', 404, ERROR_NOT_FOUND);
        break;
}
