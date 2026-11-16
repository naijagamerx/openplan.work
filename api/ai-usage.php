<?php
/**
 * AI Usage Counter API
 *
 * GET /api/ai-usage.php — current month usage for caller.
 * GET /api/ai-usage.php?month=YYYY-MM — specific month usage.
 */

require_once __DIR__ . '/../config.php';
require_once INCLUDES_PATH . '/Database.php';
require_once INCLUDES_PATH . '/Auth.php';
require_once INCLUDES_PATH . '/Helpers.php';
require_once INCLUDES_PATH . '/SubscriptionHelper.php';

if (!Auth::check()) {
    errorResponse('Unauthorized', 401);
    exit;
}

if (requestMethod() !== 'GET') {
    errorResponse('Method not allowed', 405);
    exit;
}

$userId = Auth::userId();
$month = $_GET['month'] ?? date('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
    errorResponse('Invalid month format (expected YYYY-MM)', 400, 'VALIDATION');
    exit;
}

$usage = getMonthlyUsage($userId, $month);
$sub = getActiveSubscription($userId);
$cap = $sub ? (int)($sub['monthlyRequestCap'] ?? 0) : 0;
// A free trial's cap covers the whole trial, not one calendar month.
$planUsage = $sub ? subscriptionUsage($userId, $sub) : ['used' => (int)($usage['totalRequests'] ?? 0), 'perTrial' => false];
$remaining = $cap > 0 ? max(0, $cap - (int)$planUsage['used']) : null;

successResponse([
    'month' => $month,
    'totalRequests' => (int)($usage['totalRequests'] ?? 0),
    'byProvider' => $usage['byProvider'] ?? [],
    'cap' => $cap > 0 ? $cap : null,
    'remaining' => $remaining,
    'hasActiveSubscription' => $sub !== null,
    'trial' => $sub !== null && !empty($sub['trial']),
    'capUsed' => (int)$planUsage['used'],
]);
