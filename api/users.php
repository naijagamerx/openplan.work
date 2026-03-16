<?php
require_once __DIR__ . '/../config.php';

if (!Auth::check()) {
    errorResponse('Unauthorized', 401, ERROR_UNAUTHORIZED);
}

Auth::requireAdmin();

$db = new Database(getMasterPassword());
$authService = new Auth($db);
$action = $_GET['action'] ?? 'list';

try {
    $users = Auth::allUsers();

    if ($action === 'list') {
        $payload = array_map(static function(array $user): array {
            $normalizedRole = Auth::normalizeRole($user['role'] ?? null);
            return [
                'id' => $user['id'] ?? '',
                'email' => $user['email'] ?? '',
                'name' => $user['name'] ?? '',
                'role' => $normalizedRole,
                'canDelete' => $normalizedRole === Auth::ROLE_USER,
                'isBanned' => !empty($user['banned']),
                'emailVerifiedAt' => $user['emailVerifiedAt'] ?? null,
                'createdAt' => $user['createdAt'] ?? null,
                'lastLogin' => $user['lastLogin'] ?? null
            ];
        }, $users);

        successResponse(['users' => $payload]);
    }

    if ($action === 'toggle_ban') {
        if (requestMethod() !== 'POST') {
            errorResponse('Method not allowed', 405);
        }

        $body = getJsonBody();
        $csrfToken = $body['csrf_token'] ?? '';
        if (!Auth::validateCsrf($csrfToken)) {
            errorResponse('Invalid CSRF token', 403);
        }

        $userId = trim((string)($body['user_id'] ?? ''));
        if ($userId === '') {
            errorResponse('User id is required');
        }

        $result = $authService->toggleBan($userId);
        if (empty($result['success'])) {
            errorResponse($result['error'] ?? 'Failed to update ban status', 500, ERROR_SERVER);
        }

        successResponse([
            'user_id' => $userId, 
            'isBanned' => $result['banned']
        ], 'User status updated');
    }

    if ($action === 'bulk_ban_spam') {
        if (requestMethod() !== 'POST') {
            errorResponse('Method not allowed', 405);
        }

        $body = getJsonBody();
        $csrfToken = $body['csrf_token'] ?? '';
        if (!Auth::validateCsrf($csrfToken)) {
            errorResponse('Invalid CSRF token', 403);
        }

        $result = $authService->bulkBanSpamUsers();
        if (empty($result['success'])) {
            errorResponse($result['error'] ?? 'Failed to bulk ban users', 500, ERROR_SERVER);
        }

        successResponse(['count' => $result['count']], "Successfully banned {$result['count']} spam accounts.");
    }

    if ($action === 'update_role') {
        if (requestMethod() !== 'POST') {
            errorResponse('Method not allowed', 405);
        }

        $body = getJsonBody();
        $csrfToken = $body['csrf_token'] ?? '';
        if (!Auth::validateCsrf($csrfToken)) {
            errorResponse('Invalid CSRF token', 403);
        }

        $userId = trim((string)($body['user_id'] ?? ''));
        $role = Auth::normalizeRole($body['role'] ?? null);

        if ($userId === '') {
            errorResponse('User id is required');
        }

        $found = false;
        foreach ($users as $index => $user) {
            if (($user['id'] ?? '') !== $userId) {
                continue;
            }

            if (Auth::normalizeRole($user['role'] ?? null) === Auth::ROLE_ADMIN && $role !== Auth::ROLE_ADMIN && Auth::isLastAdmin($userId)) {
                errorResponse('You cannot demote the last administrator', 400);
            }

            $users[$index]['role'] = $role;
            $users[$index]['updatedAt'] = date('c');
            $found = true;
            break;
        }

        if (!$found) {
            errorResponse('User not found', 404);
        }

        if (!$db->save('users', $users)) {
            errorResponse('Failed to update role', 500, ERROR_SERVER);
        }

        successResponse(['user_id' => $userId, 'role' => $role], 'Role updated');
    }

    if ($action === 'delete_user') {
        if (requestMethod() !== 'POST') {
            errorResponse('Method not allowed', 405);
        }

        $body = getJsonBody();
        $csrfToken = $body['csrf_token'] ?? '';
        if (!Auth::validateCsrf($csrfToken)) {
            errorResponse('Invalid CSRF token', 403);
        }

        $userId = trim((string)($body['user_id'] ?? ''));
        if ($userId === '') {
            errorResponse('User id is required');
        }

        $targetUser = null;
        foreach ($users as $user) {
            if (($user['id'] ?? '') === $userId) {
                $targetUser = $user;
                break;
            }
        }

        if ($targetUser === null) {
            errorResponse('User not found', 404);
        }

        if (Auth::normalizeRole($targetUser['role'] ?? null) === Auth::ROLE_ADMIN) {
            errorResponse('Administrator accounts cannot be deleted from this page', 400);
        }

        $result = $authService->deleteAccount($userId);
        if (empty($result['success'])) {
            errorResponse($result['error'] ?? 'Failed to delete user', 500, ERROR_SERVER);
        }

        successResponse(['user_id' => $userId], 'User deleted');
    }

    errorResponse('Invalid action', 400);
} catch (Exception $e) {
    errorResponse($e->getMessage(), 500, ERROR_SERVER);
}
