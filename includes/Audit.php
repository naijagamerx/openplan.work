<?php
/**
 * Audit Class - Activity logging and audit trail management
 *
 * Provides comprehensive activity tracking for security and compliance:
 * - User actions (login, logout, password changes)
 * - Data operations (create, update, delete)
 * - System operations (backup, restore)
 * - Export tracking
 *
 * All logs are stored encrypted in data/audit.json.enc
 */

class Audit {
    // User events
    const EVENT_LOGIN = 'user.login';
    const EVENT_LOGOUT = 'user.logout';
    const EVENT_LOGIN_FAILED = 'user.login_failed';
    const EVENT_REGISTER = 'user.register';
    const EVENT_REGISTER_FAILED = 'user.register_failed';
    const EVENT_PASSWORD_CHANGE = 'user.password_change';
    const EVENT_MASTER_PASSWORD_CHANGE = 'user.master_password_change';

    // Data events
    const EVENT_CREATE = 'data.create';
    const EVENT_UPDATE = 'data.update';
    const EVENT_DELETE = 'data.delete';

    // System events
    const EVENT_BACKUP = 'system.backup';
    const EVENT_RESTORE = 'system.restore';
    const EVENT_EXPORT = 'data.export';
    const EVENT_IMPORT = 'data.import';

    // Resource types
    const RESOURCE_TASK = 'task';
    const RESOURCE_PROJECT = 'project';
    const RESOURCE_CLIENT = 'client';
    const RESOURCE_INVOICE = 'invoice';
    const RESOURCE_FINANCE = 'finance';
    const RESOURCE_INVENTORY = 'inventory';
    const RESOURCE_HABIT = 'habit';
    const RESOURCE_CONFIG = 'config';
    const RESOURCE_USER = 'user';

    protected Database $db;
    protected string $collection = 'audit';

    public function __construct(Database $db) {
        $this->db = $db;
    }

    /**
     * Log an audit event
     */
    public function log(string $event, array $context = []): bool {
        $user = Auth::user();

        $entry = [
            'id' => $this->generateId(),
            'event' => $event,
            'user_id' => $user['id'] ?? 'system',
            'user_email' => $user['email'] ?? 'system',
            'user_name' => $user['name'] ?? 'System',
            'ip_address' => $this->getClientIP(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'resource_type' => $context['resource_type'] ?? null,
            'resource_id' => $context['resource_id'] ?? null,
            'description' => $this->formatDescription($event, $context),
            'details' => $context['details'] ?? null,
            'old_value' => $context['old_value'] ?? null,
            'new_value' => $context['new_value'] ?? null,
            'timestamp' => date('c'),
            'success' => $context['success'] ?? true
        ];

        // Get current logs and add new entry
        $logs = $this->db->load($this->collection);
        $logs[] = $entry;

        // Keep only last 10000 entries to prevent file growth
        if (count($logs) > 10000) {
            $logs = array_slice($logs, -10000);
        }

        return $this->db->save($this->collection, $logs);
    }

    /**
     * Get audit logs with filtering
     */
    public function getLogs(array $filters = []): array {
        $logs = $this->db->load($this->collection);

        // Apply filters
        if (!empty($filters['event'])) {
            $logs = array_filter($logs, function($log) use ($filters) {
                return $log['event'] === $filters['event'];
            });
        }

        if (!empty($filters['user_id'])) {
            $logs = array_filter($logs, function($log) use ($filters) {
                return $log['user_id'] === $filters['user_id'];
            });
        }

        if (!empty($filters['resource_type'])) {
            $logs = array_filter($logs, function($log) use ($filters) {
                return $log['resource_type'] === $filters['resource_type'];
            });
        }

        if (!empty($filters['resource_id'])) {
            $logs = array_filter($logs, function($log) use ($filters) {
                return $log['resource_id'] === $filters['resource_id'];
            });
        }

        if (!empty($filters['from'])) {
            $from = strtotime($filters['from']);
            $logs = array_filter($logs, function($log) use ($from) {
                return strtotime($log['timestamp']) >= $from;
            });
        }

        if (!empty($filters['to'])) {
            $to = strtotime($filters['to']);
            $logs = array_filter($logs, function($log) use ($to) {
                return strtotime($log['timestamp']) <= $to;
            });
        }

        if (!empty($filters['search'])) {
            $search = strtolower($filters['search']);
            $logs = array_filter($logs, function($log) use ($search) {
                return stripos($log['description'], $search) !== false ||
                       stripos($log['user_email'], $search) !== false ||
                       stripos($log['user_name'], $search) !== false;
            });
        }

        // Sort by timestamp descending
        usort($logs, function($a, $b) {
            return strtotime($b['timestamp']) - strtotime($a['timestamp']);
        });

        // Pagination
        $limit = $filters['limit'] ?? 100;
        $offset = $filters['offset'] ?? 0;

        $total = count($logs);
        $logs = array_slice($logs, $offset, $limit);

        return [
            'logs' => array_values($logs),
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset
        ];
    }

    /**
     * Get log by ID
     */
    public function getLogById(string $id): ?array {
        $logs = $this->db->load($this->collection);

        foreach ($logs as $log) {
            if ($log['id'] === $id) {
                return $log;
            }
        }

        return null;
    }

    /**
     * Get event types
     */
    public function getEventTypes(): array {
        return [
            self::EVENT_LOGIN => ['label' => 'Login', 'icon' => '🔓', 'category' => 'user'],
            self::EVENT_LOGOUT => ['label' => 'Logout', 'icon' => '🔒', 'category' => 'user'],
            self::EVENT_LOGIN_FAILED => ['label' => 'Login Failed', 'icon' => '⚠️', 'category' => 'user'],
            self::EVENT_REGISTER => ['label' => 'User Registration', 'icon' => '🧾', 'category' => 'user'],
            self::EVENT_REGISTER_FAILED => ['label' => 'Registration Failed', 'icon' => '⚠️', 'category' => 'user'],
            self::EVENT_PASSWORD_CHANGE => ['label' => 'Password Change', 'icon' => '🔑', 'category' => 'user'],
            self::EVENT_MASTER_PASSWORD_CHANGE => ['label' => 'Master Password Change', 'icon' => '🔐', 'category' => 'user'],
            self::EVENT_CREATE => ['label' => 'Create', 'icon' => '➕', 'category' => 'data'],
            self::EVENT_UPDATE => ['label' => 'Update', 'icon' => '✏️', 'category' => 'data'],
            self::EVENT_DELETE => ['label' => 'Delete', 'icon' => '🗑️', 'category' => 'data'],
            self::EVENT_BACKUP => ['label' => 'Backup', 'icon' => '💾', 'category' => 'system'],
            self::EVENT_RESTORE => ['label' => 'Restore', 'icon' => '♻️', 'category' => 'system'],
            self::EVENT_EXPORT => ['label' => 'Export', 'icon' => '📤', 'category' => 'data'],
            self::EVENT_IMPORT => ['label' => 'Import', 'icon' => '📥', 'category' => 'data'],
        ];
    }

    /**
     * Get resource types
     */
    public function getResourceTypes(): array {
        return [
            self::RESOURCE_TASK => 'Task',
            self::RESOURCE_PROJECT => 'Project',
            self::RESOURCE_CLIENT => 'Client',
            self::RESOURCE_INVOICE => 'Invoice',
            self::RESOURCE_FINANCE => 'Finance',
            self::RESOURCE_INVENTORY => 'Inventory',
            self::RESOURCE_HABIT => 'Habit',
            self::RESOURCE_CONFIG => 'Configuration',
            self::RESOURCE_USER => 'User'
        ];
    }

    /**
     * Clear logs before a certain date
     */
    public function clearLogs(?string $before = null): int {
        $logs = $this->db->load($this->collection);

        if ($before === null) {
            // Clear all logs
            $deleted = count($logs);
            $this->db->save($this->collection, []);
            return $deleted;
        }

        $beforeTimestamp = strtotime($before);
        $remaining = [];

        foreach ($logs as $log) {
            if (strtotime($log['timestamp']) >= $beforeTimestamp) {
                $remaining[] = $log;
            }
        }

        $deleted = count($logs) - count($remaining);
        $this->db->save($this->collection, $remaining);

        return $deleted;
    }

    /**
     * Get audit statistics
     */
    public function getStats(array $filters = []): array {
        $logs = $this->db->load($this->collection);

        // Apply date filters
        if (!empty($filters['from'])) {
            $from = strtotime($filters['from']);
            $logs = array_filter($logs, function($log) use ($from) {
                return strtotime($log['timestamp']) >= $from;
            });
        }

        if (!empty($filters['to'])) {
            $to = strtotime($filters['to']);
            $logs = array_filter($logs, function($log) use ($to) {
                return strtotime($log['timestamp']) <= $to;
            });
        }

        // Count by event type
        $byEvent = [];
        foreach ($logs as $log) {
            $event = $log['event'];
            $byEvent[$event] = ($byEvent[$event] ?? 0) + 1;
        }

        // Count by user
        $byUser = [];
        foreach ($logs as $log) {
            $userId = $log['user_id'];
            if (!isset($byUser[$userId])) {
                $byUser[$userId] = [
                    'user_id' => $userId,
                    'user_email' => $log['user_email'],
                    'user_name' => $log['user_name'],
                    'count' => 0
                ];
            }
            $byUser[$userId]['count']++;
        }

        // Sort by count
        usort($byUser, function($a, $b) {
            return $b['count'] - $a['count'];
        });
        $byUser = array_slice($byUser, 0, 10);

        // Count by day (last 7 days)
        $byDay = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-{$i} days"));
            $byDay[$date] = 0;
        }

        foreach ($logs as $log) {
            $date = date('Y-m-d', strtotime($log['timestamp']));
            if (isset($byDay[$date])) {
                $byDay[$date]++;
            }
        }

        return [
            'total_logs' => count($logs),
            'by_event' => $byEvent,
            'by_user' => array_values($byUser),
            'by_day' => $byDay,
            'unique_users' => count(array_unique(array_column($logs, 'user_id')))
        ];
    }

    /**
     * Generate unique ID
     */
    protected function generateId(): string {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }

    /**
     * Get client IP address
     */
    protected function getClientIP(): string {
        $ipKeys = [
            'HTTP_CF_CONNECTING_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'HTTP_CLIENT_IP',
            'REMOTE_ADDR'
        ];

        foreach ($ipKeys as $key) {
            if (!empty($_SERVER[$key])) {
                $ips = explode(',', $_SERVER[$key]);
                $ip = trim($ips[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

        return 'unknown';
    }

    /**
     * Format event description
     */
    protected function formatDescription(string $event, array $context): string {
        $resourceType = $context['resource_type'] ?? 'unknown';
        $resourceId = $context['resource_id'] ?? '';

        return match ($event) {
            self::EVENT_LOGIN => 'User logged in',
            self::EVENT_LOGOUT => 'User logged out',
            self::EVENT_LOGIN_FAILED => 'Failed login attempt',
            self::EVENT_REGISTER => 'User registered',
            self::EVENT_REGISTER_FAILED => 'User registration failed',
            self::EVENT_PASSWORD_CHANGE => 'User changed their password',
            self::EVENT_MASTER_PASSWORD_CHANGE => 'User changed master password',
            self::EVENT_CREATE => "Created new {$resourceType}" . ($resourceId ? " [{$resourceId}]" : ''),
            self::EVENT_UPDATE => "Updated {$resourceType}" . ($resourceId ? " [{$resourceId}]" : ''),
            self::EVENT_DELETE => "Deleted {$resourceType}" . ($resourceId ? " [{$resourceId}]" : ''),
            self::EVENT_BACKUP => 'Created a data backup',
            self::EVENT_RESTORE => 'Restored from backup',
            self::EVENT_EXPORT => "Exported {$resourceType} data",
            self::EVENT_IMPORT => 'Imported data',
            default => $event
        };
    }

    /**
     * Convenience method for logging login
     */
    public static function logLogin(bool $success, string $email = ''): void {
        if (class_exists('Database') && class_exists('Auth')) {
            $db = new Database(getMasterPassword());
            $audit = new Audit($db);
            $audit->log($success ? self::EVENT_LOGIN : self::EVENT_LOGIN_FAILED, [
                'details' => ['email' => $email, 'success' => $success],
                'success' => $success
            ]);
        }
    }

    /**
     * Convenience method for logging data operations
     */
    public static function logData(string $operation, string $resourceType, string $resourceId = '', array $changes = []): void {
        if (class_exists('Database') && class_exists('Auth')) {
            $db = new Database(getMasterPassword());
            $audit = new Audit($db);

            $event = match ($operation) {
                'create' => self::EVENT_CREATE,
                'update' => self::EVENT_UPDATE,
                'delete' => self::EVENT_DELETE,
                default => self::EVENT_UPDATE
            };

            $audit->log($event, [
                'resource_type' => $resourceType,
                'resource_id' => $resourceId,
                'details' => $changes
            ]);
        }
    }

    /**
     * Convenience method for logging backup/restore
     */
    public static function logSystem(string $operation, string $details = ''): void {
        if (class_exists('Database') && class_exists('Auth')) {
            $db = new Database(getMasterPassword());
            $audit = new Audit($db);

            $event = match ($operation) {
                'backup' => self::EVENT_BACKUP,
                'restore' => self::EVENT_RESTORE,
                'export' => self::EVENT_EXPORT,
                'import' => self::EVENT_IMPORT,
                default => self::EVENT_BACKUP
            };

            $audit->log($event, [
                'details' => ['info' => $details]
            ]);
        }
    }
}
