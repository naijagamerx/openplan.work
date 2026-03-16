<?php
/**
 * RateLimiter Class - Login attempt and API rate limiting
 *
 * Provides rate limiting functionality to prevent brute-force attacks
 * and API abuse. Uses file-based storage for persistence.
 */

class RateLimiter {
    private string $storagePath;
    private Database $db;

    // Rate limiting constants (can be overridden in config.php)
    private const DEFAULT_MAX_ATTEMPTS = 5;
    private const DEFAULT_WINDOW_SECONDS = 900; // 15 minutes
    private const DEFAULT_API_LIMIT = 100;
    private const DEFAULT_API_WINDOW = 3600; // 1 hour

    public function __construct(Database $db) {
        $this->db = $db;
        $this->storagePath = DATA_PATH . '/rate_limits.json';
    }

    /**
     * Check if an IP-based login should be blocked
     */
    public function isLoginBlocked(string $identifier): bool {
        $attempts = $this->getLoginAttempts($identifier);
        $maxAttempts = defined('LOGIN_MAX_ATTEMPTS') ? LOGIN_MAX_ATTEMPTS : self::DEFAULT_MAX_ATTEMPTS;

        return $attempts['count'] >= $maxAttempts;
    }

    /**
     * Get remaining login attempts for an identifier
     */
    public function getRemainingAttempts(string $identifier): int {
        $attempts = $this->getLoginAttempts($identifier);
        $maxAttempts = defined('LOGIN_MAX_ATTEMPTS') ? LOGIN_MAX_ATTEMPTS : self::DEFAULT_MAX_ATTEMPTS;

        return max(0, $maxAttempts - $attempts['count']);
    }

    /**
     * Record a failed login attempt
     */
    public function recordFailedAttempt(string $identifier): void {
        $data = $this->loadRateLimitData();

        $now = time();
        $window = defined('LOGIN_WINDOW_SECONDS') ? LOGIN_WINDOW_SECONDS : self::DEFAULT_WINDOW_SECONDS;

        if (!isset($data['login_attempts'][$identifier])) {
            $data['login_attempts'][$identifier] = [
                'count' => 0,
                'first_attempt' => $now,
                'last_attempt' => $now
            ];
        }

        // Clean old attempts outside the window
        $this->cleanupOldAttempts($data['login_attempts'], $now, $window);

        $data['login_attempts'][$identifier]['count']++;
        $data['login_attempts'][$identifier]['last_attempt'] = $now;

        $this->saveRateLimitData($data);
    }

    /**
     * Clear login attempts on successful login
     */
    public function clearLoginAttempts(string $identifier): void {
        $data = $this->loadRateLimitData();

        unset($data['login_attempts'][$identifier]);

        $this->saveRateLimitData($data);
    }

    /**
     * Get login attempts data for an identifier
     */
    public function getLoginAttempts(string $identifier): array {
        $data = $this->loadRateLimitData();
        $window = defined('LOGIN_WINDOW_SECONDS') ? LOGIN_WINDOW_SECONDS : self::DEFAULT_WINDOW_SECONDS;
        $now = time();

        if (!isset($data['login_attempts'][$identifier])) {
            return ['count' => 0, 'first_attempt' => $now, 'last_attempt' => $now, 'blocked' => false];
        }

        $attempts = $data['login_attempts'][$identifier];

        // Check if window has expired
        if ($now - $attempts['first_attempt'] > $window) {
            return ['count' => 0, 'first_attempt' => $now, 'last_attempt' => $now, 'blocked' => false];
        }

        $maxAttempts = defined('LOGIN_MAX_ATTEMPTS') ? LOGIN_MAX_ATTEMPTS : self::DEFAULT_MAX_ATTEMPTS;

        return [
            'count' => $attempts['count'],
            'first_attempt' => $attempts['first_attempt'],
            'last_attempt' => $attempts['last_attempt'],
            'blocked' => $attempts['count'] >= $maxAttempts
        ];
    }

    /**
     * Get time until login is unblocked (in seconds)
     */
    public function getUnlockTime(string $identifier): int {
        $attempts = $this->getLoginAttempts($identifier);
        $window = defined('LOGIN_WINDOW_SECONDS') ? LOGIN_WINDOW_SECONDS : self::DEFAULT_WINDOW_SECONDS;

        if (!$attempts['blocked']) {
            return 0;
        }

        $unlockAt = $attempts['first_attempt'] + $window;
        $remaining = $unlockAt - time();

        return max(0, $remaining);
    }

    /**
     * Check API rate limit for a user
     * Returns [allowed => bool, remaining => int, reset => int]
     */
    public function checkApiLimit(string $userId, string $endpoint = 'general'): array {
        $data = $this->loadRateLimitData();
        $now = time();

        // Use higher limits for AI agent endpoint
        if ($endpoint === 'ai-agent') {
            $limit = 500; // 500 requests per hour for AI agent
            $window = 3600; // 1 hour
        } else {
            $limit = defined('API_RATE_LIMIT') ? API_RATE_LIMIT : self::DEFAULT_API_LIMIT;
            $window = defined('API_WINDOW_SECONDS') ? API_WINDOW_SECONDS : self::DEFAULT_API_WINDOW;
        }

        $key = "api_{$userId}_{$endpoint}";

        if (!isset($data['api_limits'][$key])) {
            $data['api_limits'][$key] = [
                'count' => 0,
                'window_start' => $now
            ];
        }

        $apiData = $data['api_limits'][$key];

        // Reset window if expired
        if ($now - $apiData['window_start'] > $window) {
            $apiData['count'] = 0;
            $apiData['window_start'] = $now;
        }

        $remaining = max(0, $limit - $apiData['count']);
        $resetAt = $apiData['window_start'] + $window;

        return [
            'allowed' => $apiData['count'] < $limit,
            'remaining' => $remaining,
            'reset' => $resetAt,
            'limit' => $limit
        ];
    }

    /**
     * Increment API usage counter
     */
    public function incrementApiUsage(string $userId, string $endpoint = 'general'): void {
        $data = $this->loadRateLimitData();
        $now = time();
        $window = defined('API_WINDOW_SECONDS') ? API_WINDOW_SECONDS : self::DEFAULT_API_WINDOW;

        $key = "api_{$userId}_{$endpoint}";

        if (!isset($data['api_limits'][$key])) {
            $data['api_limits'][$key] = [
                'count' => 0,
                'window_start' => $now
            ];
        }

        // Reset window if expired
        if ($now - $data['api_limits'][$key]['window_start'] > $window) {
            $data['api_limits'][$key]['count'] = 0;
            $data['api_limits'][$key]['window_start'] = $now;
        }

        $data['api_limits'][$key]['count']++;

        $this->saveRateLimitData($data);
    }

    /**
     * Record audit log entry
     */
    public function logAudit(?string $userId, string $action, string $resource,
                             ?string $resourceId = null, ?string $ipAddress = null,
                             ?string $userAgent = null, bool $success = true,
                             ?string $details = null): void {
        $data = $this->loadRateLimitData();

        $entry = [
            'id' => bin2hex(random_bytes(16)),
            'timestamp' => date('c'),
            'user_id' => $userId,
            'action' => $action,
            'resource' => $resource,
            'resource_id' => $resourceId,
            'ip_address' => $ipAddress ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $userAgent ?? $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'success' => $success,
            'details' => $details
        ];

        $data['audit_log'][] = $entry;

        // Keep only last 10000 entries to prevent file bloat
        if (count($data['audit_log']) > 10000) {
            $data['audit_log'] = array_slice($data['audit_log'], -10000);
        }

        $this->saveRateLimitData($data);
    }

    /**
     * Get audit log entries
     */
    public function getAuditLog(array $filters = []): array {
        $data = $this->loadRateLimitData();
        $logs = $data['audit_log'] ?? [];

        // Apply filters
        if (!empty($filters['user_id'])) {
            $logs = array_filter($logs, fn($l) => $l['user_id'] === $filters['user_id']);
        }

        if (!empty($filters['action'])) {
            $logs = array_filter($logs, fn($l) => $l['action'] === $filters['action']);
        }

        if (!empty($filters['resource'])) {
            $logs = array_filter($logs, fn($l) => $l['resource'] === $filters['resource']);
        }

        if (!empty($filters['success'])) {
            $logs = array_filter($logs, fn($l) => $l['success'] === ($filters['success'] === 'true'));
        }

        if (!empty($filters['from_date'])) {
            $logs = array_filter($logs, fn($l) => $l['timestamp'] >= $filters['from_date']);
        }

        if (!empty($filters['to_date'])) {
            $logs = array_filter($logs, fn($l) => $l['timestamp'] <= $filters['to_date']);
        }

        // Sort by timestamp descending
        usort($logs, fn($a, $b) => strcmp($b['timestamp'], $a['timestamp']));

        return array_values($logs);
    }

    /**
     * Clean up old attempts outside the time window
     */
    private function cleanupOldAttempts(array &$attempts, int $now, int $window): void {
        foreach ($attempts as $identifier => $data) {
            if ($now - $data['first_attempt'] > $window) {
                unset($attempts[$identifier]);
            }
        }
    }

    /**
     * Load rate limit data from storage
     */
    private function loadRateLimitData(): array {
        // Ensure storage directory exists
        if (!is_dir(DATA_PATH)) {
            mkdir(DATA_PATH, 0755, true);
        }

        if (!file_exists($this->storagePath)) {
            return [
                'login_attempts' => [],
                'api_limits' => [],
                'audit_log' => []
            ];
        }

        $content = file_get_contents($this->storagePath);
        if (empty($content)) {
            return [
                'login_attempts' => [],
                'api_limits' => [],
                'audit_log' => []
            ];
        }

        return json_decode($content, true) ?? [
            'login_attempts' => [],
            'api_limits' => [],
            'audit_log' => []
        ];
    }

    /**
     * Save rate limit data to storage
     */
    private function saveRateLimitData(array $data): void {
        // Ensure storage directory exists
        if (!is_dir(DATA_PATH)) {
            mkdir(DATA_PATH, 0755, true);
        }

        file_put_contents($this->storagePath, json_encode($data, JSON_PRETTY_PRINT), LOCK_EX);
    }

    /**
     * Clear all rate limit data (for testing or reset)
     */
    public function clearAll(): void {
        if (file_exists($this->storagePath)) {
            unlink($this->storagePath);
        }
    }
}
