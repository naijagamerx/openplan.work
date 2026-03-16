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
    // Flush any output buffer to ensure clean JSON response
    if (ob_get_level() > 0) {
        ob_end_clean();
    }

    if (!headers_sent()) {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        // Security headers for API responses
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('X-XSS-Protection: 1; mode=block');
        header('Referrer-Policy: strict-origin-when-cross-origin');
    }
    echo json_encode($data);
    if (!defined('PHPUNIT_TEST')) {
        exit;
    }
}

/**
 * Success response
 */
function successResponse(mixed $data = null, string $message = 'Success'): void {
    jsonResponse([
        'success' => true,
        'data' => $data,
        'message' => $message,
        'timestamp' => date('c')
    ]);
}

/**
 * Error response with error code
 */
function errorResponse(string $error, int $statusCode = 400, string $errorCode = 'ERROR'): void {
    $response = [
        'success' => false,
        'error' => [
            'code' => $errorCode,
            'message' => $error
        ],
        'timestamp' => date('c')
    ];

    // Add redirect for 401 Unauthorized responses
    if ($statusCode === 401) {
        $logoutReason = strtolower(trim((string)($_SERVER['AUTH_LOGOUT_REASON'] ?? '')));
        $queryReason = match ($logoutReason) {
            'session_timeout' => 'session_timeout',
            'token_restore_failed' => 'token_restore_failed',
            'session_missing' => 'session_missing',
            default => 'session_expired'
        };
        $response['redirect'] = '?page=login&reason=' . $queryReason;
    }

    jsonResponse($response, $statusCode);
}

/**
 * Handle exception and send error response
 */
function handleException(Exception $e): void {
    $statusCode = $e instanceof APIException ? $e->getStatusCode() : 500;
    $errorArray = $e instanceof APIException ? $e->toArray() : [
        'code' => ERROR_SERVER,
        'message' => $e->getMessage()
    ];

    $response = [
        'success' => false,
        'error' => $errorArray,
        'timestamp' => date('c')
    ];

    // Add redirect for 401 Unauthorized responses
    if ($statusCode === 401) {
        $logoutReason = strtolower(trim((string)($_SERVER['AUTH_LOGOUT_REASON'] ?? '')));
        $queryReason = match ($logoutReason) {
            'session_timeout' => 'session_timeout',
            'token_restore_failed' => 'token_restore_failed',
            'session_missing' => 'session_missing',
            default => 'session_expired'
        };
        $response['redirect'] = '?page=login&reason=' . $queryReason;
    }

    jsonResponse($response, $statusCode);
}

/**
 * Get JSON request body
 */
function getJsonBody(): array {
    $body = file_get_contents('php://input');
    return json_decode($body, true) ?? [];
}

/**
 * Format minutes to human-readable time (e.g., "1h 30m")
 */
function formatMinutes(int $minutes): string {
    if ($minutes < 60) {
        return $minutes . 'm';
    }
    $hours = floor($minutes / 60);
    $mins = $minutes % 60;
    if ($mins === 0) {
        return $hours . 'h';
    }
    return $hours . 'h ' . $mins . 'm';
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
        'EUR' => 'EUR ',
        'GBP' => 'GBP ',
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
    static $cache = [];
    $cacheKey = Auth::userId() ?? 'global';

    if (!array_key_exists($cacheKey, $cache)) {
        $masterPassword = getMasterPassword();
        if (empty($masterPassword)) {
            return [];
        }
        try {
            $db = new Database($masterPassword, Auth::userId());
            $cache[$cacheKey] = $db->load('config', false);
        } catch (Exception $e) {
            $cache[$cacheKey] = [];
        }
    }
    return $cache[$cacheKey] ?? [];
}

/**
 * Get public config from unencrypted JSON file
 */
function getPublicConfig(): array {
    $configFile = DATA_PATH . '/public_config.json';
    if (file_exists($configFile)) {
        $content = file_get_contents($configFile);
        if ($content !== false) {
            $config = json_decode($content, true);
            if (is_array($config)) {
                return $config;
            }
        }
    }
    return [];
}

function savePublicConfig(array $updates): bool {
    $configFile = DATA_PATH . '/public_config.json';
    $current = getPublicConfig();
    $merged = array_merge($current, $updates);
    $payload = json_encode($merged, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if ($payload === false) {
        return false;
    }

    return file_put_contents($configFile, $payload, LOCK_EX) !== false;
}

function userRegistryExists(): bool {
    return file_exists(DATA_PATH . '/users.json') || file_exists(DATA_PATH . '/users.json.enc');
}

function authFeatureEnabled(string $feature): bool {
    return match ($feature) {
        'email_verification' => EMAIL_VERIFICATION_ENABLED,
        'password_reset' => PASSWORD_RESET_ENABLED,
        'image_service' => IMAGE_SERVICE_ENABLED,
        default => false
    };
}

function isEmailVerificationEnabled(): bool {
    return authFeatureEnabled('email_verification');
}

function isPasswordResetEnabled(): bool {
    return authFeatureEnabled('password_reset');
}

function isImageServiceEnabled(): bool {
    return authFeatureEnabled('image_service');
}

function getInstallMode(): string {
    $mode = strtolower(trim((string)(getPublicConfig()['installMode'] ?? 'multi_user')));
    return $mode === 'single_user' ? 'single_user' : 'multi_user';
}

function isSingleUserMode(): bool {
    return getInstallMode() === 'single_user';
}

function isMultiUserMode(): bool {
    return !isSingleUserMode();
}

function isRegistrationEnabled(): bool {
    return isMultiUserMode();
}

/**
 * Get the public-facing application name used before user-specific branding is known.
 */
function getPublicAppName(): string {
    return APP_DISPLAY_NAME !== '' ? APP_DISPLAY_NAME : APP_NAME;
}

/**
 * Get the public-facing tagline used on auth pages.
 */
function getPublicAppTagline(): string {
    return APP_TAGLINE;
}

/**
 * Get the authenticated user's workspace/app display name.
 */
function getUserSiteName(): string {
    if (!Auth::check()) {
        return getPublicAppName();
    }

    $masterPassword = getMasterPassword();
    if ($masterPassword === '') {
        return getPublicAppName();
    }

    try {
        $config = getAppConfig();
        $name = trim((string)($config['siteName'] ?? ''));
        if ($name !== '') {
            return $name;
        }
    } catch (Exception $e) {
        // Fall through to the public app name.
    }

    return getPublicAppName();
}

/**
 * Get site/app display name for the current request context.
 */
function getSiteName(): string {
    return getUserSiteName();
}

function isAdminUser(): bool {
    return Auth::isAdmin();
}

function setAuthFlash(array $payload): void {
    $_SESSION['auth_flash'] = $payload;
}

function pullAuthFlash(): ?array {
    if (!isset($_SESSION['auth_flash']) || !is_array($_SESSION['auth_flash'])) {
        return null;
    }

    $flash = $_SESSION['auth_flash'];
    unset($_SESSION['auth_flash']);

    return $flash;
}

/**
 * Get master password from session or environment
 */
function getMasterPassword(): string {
    // Check session first
    if (isset($_SESSION[SESSION_MASTER_KEY]) && !empty($_SESSION[SESSION_MASTER_KEY])) {
        return $_SESSION[SESSION_MASTER_KEY];
    }

    // Check master_password.php config file
    $configFile = INCLUDES_PATH . '/master_password.php';
    if (file_exists($configFile)) {
        $config = require $configFile;
        if (defined('MASTER_PASSWORD') && !empty(MASTER_PASSWORD)) {
            return MASTER_PASSWORD;
        }
    }

    $userIniFile = ROOT_PATH . '/.user.ini';
    if (file_exists($userIniFile) && is_readable($userIniFile)) {
        $lines = file($userIniFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines !== false) {
            foreach ($lines as $line) {
                $trimmed = trim($line);
                if ($trimmed === '' || str_starts_with($trimmed, ';') || str_starts_with($trimmed, '#')) {
                    continue;
                }

                if (preg_match('/^MASTER_PASSWORD\s*=\s*(.+)$/', $trimmed, $matches) === 1) {
                    $value = trim($matches[1], " \t\n\r\0\x0B\"'");
                    if ($value !== '') {
                        return $value;
                    }
                }
            }
        }
    }

    // Check environment variables (supports both naming conventions)
    // .mcp.json uses MASTER_PASSWORD, PHP code expects LAZYMAN_MASTER_PASSWORD
    return getenv('MASTER_PASSWORD') ?: getenv('LAZYMAN_MASTER_PASSWORD') ?: '';
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
function normalizeTaskStatus(?string $status, string $default = 'todo'): string {
    $normalized = strtolower(trim((string)$status));
    if ($normalized === '') {
        return $default;
    }

    return match ($normalized) {
        'completed' => 'done',
        'in-progress', 'in progress' => 'in_progress',
        default => $normalized
    };
}

function isTaskDone(?string $status): bool {
    return normalizeTaskStatus($status) === 'done';
}

function isTaskRecordDone(array $task): bool {
    if (isTaskDone($task['status'] ?? null)) {
        return true;
    }

    return !empty($task['completedAt']);
}

function normalizeProjectStatus(?string $status, string $default = 'active'): string {
    $normalized = strtolower(trim((string)$status));
    if ($normalized === '') {
        return $default;
    }

    return match ($normalized) {
        'in progress' => 'in_progress',
        'on hold' => 'on_hold',
        'done' => 'completed',
        default => $normalized
    };
}

function isProjectActive(?string $status): bool {
    return !in_array(normalizeProjectStatus($status), ['completed', 'cancelled'], true);
}

function isHabitActive(array $habit): bool {
    return !isset($habit['isActive']) || $habit['isActive'] !== false;
}

function filterActiveHabits(array $habits): array {
    return array_values(array_filter($habits, 'isHabitActive'));
}

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

/**
 * Get favicon URL with cache busting
 *
 * @param string $filename Favicon filename
 * @return string URL with version parameter
 */
function getFaviconUrl(string $filename = 'favicon-32x32.png'): string {
    $path = ASSETS_PATH . '/favicons/' . $filename;
    $baseUrl = 'assets/favicons/' . $filename;

    return file_exists($path) ? $baseUrl . '?v=' . filemtime($path) : $baseUrl;
}

/**
 * Check if custom favicon exists
 *
 * @return bool True if custom favicon exists
 */
function hasCustomFavicon(): bool {
    return file_exists(ASSETS_PATH . '/favicons/favicon.svg') ||
           file_exists(ASSETS_PATH . '/favicons/favicon-32x32.png');
}

/**
 * Get sidebar logo HTML
 *
 * @param int $size Size in pixels (default 40)
 * @return string HTML for logo
 */
function getSidebarLogoHtml(int $size = 40): string {
    $svgPath = ASSETS_PATH . '/favicons/favicon.svg';
    $pngPath = ASSETS_PATH . '/favicons/favicon-32x32.png';

    $hasSvg = file_exists($svgPath);
    $hasPng = file_exists($pngPath);

    // Check if this is a custom uploaded favicon (not the default)
    // Default favicon.svg has a specific size, custom uploads are always PNG
    $isDefaultSvg = false;
    if ($hasSvg) {
        $svgContent = file_get_contents($svgPath);
        // Check if it's the default four-square SVG by looking for its unique pattern
        $isDefaultSvg = strpos($svgContent, '<rect x="2" y="2" width="21" height="21" fill="#ffffff"/>') !== false ||
                       (strpos($svgContent, 'M24 4H6V17.3333H24V4Z') !== false && strpos($svgContent, 'fill="white"') !== false);
    }

    // Prioritize PNG favicon (uploaded custom favicon) over default SVG
    if ($hasPng) {
        $url = 'assets/favicons/favicon-32x32.png?v=' . filemtime($pngPath);
        return '<img src="' . $url . '" alt="Logo" class="w-full h-full object-contain">';
    }

    // Only show SVG if it's NOT the default (i.e., user uploaded an SVG)
    if ($hasSvg && !$isDefaultSvg) {
        $url = 'assets/favicons/favicon.svg?v=' . filemtime($svgPath);
        return '<img src="' . $url . '" alt="Logo" class="w-full h-full object-contain">';
    }

    // Default four-square SVG (hard-coded, not from file)
    return '<svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 48 48" xmlns="http://www.w3.org/2000/svg">
        <path d="M24 4H6V17.3333H24V4Z" fill="white"/>
        <path d="M42 30.6667H24V44H42V30.6667Z" fill="white"/>
        <path d="M24 17.3333H42V30.6667H24V17.3333Z" fill="white" opacity="0.5"/>
        <path d="M6 17.3333V30.6667H24V17.3333H6Z" fill="white" opacity="0.5"/>
    </svg>';
}
