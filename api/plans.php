<?php
/**
 * Plans API — global SaaS plan catalog.
 *
 * GET  → any logged-in user can list / read.
 * POST / PUT / DELETE → admin only.
 */

require_once __DIR__ . '/../config.php';
require_once INCLUDES_PATH . '/Database.php';
require_once INCLUDES_PATH . '/Auth.php';
require_once INCLUDES_PATH . '/Helpers.php';
require_once INCLUDES_PATH . '/PlansAPI.php';

if (!Auth::check()) {
    errorResponse('Unauthorized', 401);
    exit;
}

$db = new Database(APP_NAME);
$plansAPI = new PlansAPI($db, 'plans');
$method = requestMethod();
$id = $_GET['id'] ?? null;
$action = $_GET['action'] ?? null;

if ($method === 'GET') {
    if ($id) {
        $plan = $plansAPI->find($id);
        if ($plan) {
            successResponse($plan);
        }
        errorResponse('Plan not found', 404, 'NOT_FOUND');
        exit;
    }
    $filters = [];
    if (!empty($_GET['activeOnly'])) {
        $filters['activeOnly'] = true;
    }
    successResponse($plansAPI->findAll($filters));
    exit;
}

// All mutations require admin
if (!Auth::isAdmin()) {
    errorResponse('Administrator access required', 403, 'FORBIDDEN');
    exit;
}

$body = getJsonBody();
$csrf = $body['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_GET['csrf_token'] ?? '';
if (!Auth::isTokenAuth() && !Auth::validateCsrf($csrf)) {
    errorResponse('Invalid CSRF token', 403);
    exit;
}

switch ($method) {
    case 'POST':
        if ($action === 'update' && $id) {
            $plan = $plansAPI->update($id, $body);
            if ($plan) {
                successResponse($plan, 'Plan updated');
            }
            errorResponse('Plan not found', 404, 'NOT_FOUND');
            exit;
        }
        $err = $plansAPI->validateRequired($body, ['name', 'price', 'durationDays']);
        if ($err) {
            errorResponse($err, 400, 'VALIDATION');
        }
        $plan = $plansAPI->create($body);
        if ($plan) {
            if (class_exists('Audit')) {
                Audit::logData('create', 'plan', $plan['id'], ['name' => $plan['name']]);
            }
            successResponse($plan, 'Plan created');
        }
        errorResponse('Failed to create plan', 500, 'SERVER');
        break;

    case 'PUT':
    case 'PATCH':
        if (!$id) {
            errorResponse('Plan ID required', 400, 'VALIDATION');
        }
        $plan = $plansAPI->update($id, $body);
        if ($plan) {
            if (class_exists('Audit')) {
                Audit::logData('update', 'plan', $id, array_keys($body));
            }
            successResponse($plan, 'Plan updated');
        }
        errorResponse('Plan not found', 404, 'NOT_FOUND');
        break;

    case 'DELETE':
        if (!$id) {
            errorResponse('Plan ID required', 400, 'VALIDATION');
        }
        if ($plansAPI->delete($id)) {
            if (class_exists('Audit')) {
                Audit::logData('delete', 'plan', $id);
            }
            successResponse(null, 'Plan deleted');
        }
        errorResponse('Plan not found', 404, 'NOT_FOUND');
        break;

    default:
        errorResponse('Method not allowed', 405);
}
