<?php
/**
 * Helper Functions
 */

/**
 * Escape HTML output
 */
function e(?string $string): string {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * JSON response
 */
function jsonResponse(array $data, int $statusCode = 200): void {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

/**
 * Success response
 */
function successResponse(mixed $data = null, string $message = 'Success'): void {
    jsonResponse([
        'success' => true,
        'message' => $message,
        'data' => $data,
        'timestamp' => date('c')
    ]);
}

/**
 * Error response
 */
function errorResponse(string $error, int $statusCode = 400): void {
    jsonResponse([
        'success' => false,
        'error' => $error,
        'timestamp' => date('c')
    ], $statusCode);
}

/**
 * Get JSON request body
 */
function getJsonBody(): array {
    $body = file_get_contents('php://input');
    return json_decode($body, true) ?? [];
}

/**
 * Format date for display
 */
function formatDate(string $date, string $format = 'M d, Y'): string {
    return date($format, strtotime($date));
}

/**
 * Get currency symbol
 */
function getCurrencySymbol(string $currency = 'USD'): string {
    $symbols = [
        'USD' => '$',
        'EUR' => '€',
        'GBP' => '£',
        'ZAR' => 'R'
    ];
    return $symbols[$currency] ?? $currency . ' ';
}

/**
 * Format currency
 */
function formatCurrency(float $amount, string $currency = 'USD'): string {
    return getCurrencySymbol($currency) . number_format($amount, 2);
}

/**
 * Get application configuration
 */
function getAppConfig(): array {
    static $config = null;
    if ($config === null) {
        $db = new Database(getMasterPassword());
        $config = $db->load('config');
    }
    return $config;
}

/**
 * Get master password from session or environment
 */
function getMasterPassword(): string {
    return $_SESSION[SESSION_MASTER_KEY] ?? getenv('LAZYMAN_MASTER_PASSWORD') ?? '';
}

/**
 * Time ago format
 */
function timeAgo(string $datetime): string {
    $time = strtotime($datetime);
    $diff = time() - $time;
    
    if ($diff < 60) return 'just now';
    if ($diff < 3600) return floor($diff / 60) . ' min ago';
    if ($diff < 86400) return floor($diff / 3600) . ' hours ago';
    if ($diff < 604800) return floor($diff / 86400) . ' days ago';
    
    return formatDate($datetime);
}

/**
 * Generate a slug from string
 */
function slugify(string $text): string {
    $text = preg_replace('/[^a-zA-Z0-9]+/', '-', strtolower(trim($text)));
    return trim($text, '-');
}

/**
 * Get priority class
 */
function priorityClass(string $priority): string {
    return match($priority) {
        'urgent' => 'bg-red-100 text-red-800',
        'high' => 'bg-orange-100 text-orange-800',
        'medium' => 'bg-yellow-100 text-yellow-800',
        'low' => 'bg-green-100 text-green-800',
        default => 'bg-gray-100 text-gray-800'
    };
}

/**
 * Get status class
 */
function statusClass(string $status): string {
    return match($status) {
        'done', 'completed', 'paid' => 'bg-green-100 text-green-800',
        'in_progress', 'sent' => 'bg-blue-100 text-blue-800',
        'review' => 'bg-purple-100 text-purple-800',
        'todo', 'draft' => 'bg-gray-100 text-gray-800',
        'overdue', 'cancelled' => 'bg-red-100 text-red-800',
        default => 'bg-gray-100 text-gray-800'
    };
}

/**
 * Check if request is AJAX
 */
function isAjax(): bool {
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
}

/**
 * Get request method
 */
function requestMethod(): string {
    return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
}

/**
 * Validate email
 */
function isValidEmail(string $email): bool {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}
