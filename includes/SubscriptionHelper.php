<?php
/**
 * SubscriptionHelper — gating / quota / shared-key resolution for the SaaS layer.
 *
 * All collections referenced here live in the GLOBAL encrypted store
 * (Database(APP_NAME)) so any user's request can read them without holding
 * the admin's master password. The per-user ai_usage counter is the only
 * per-user file involved here.
 */

require_once __DIR__ . '/Database.php';

if (!function_exists('saas_globalDatabase')) {
    function saas_globalDatabase(): Database {
        static $db = null;
        if ($db === null) {
            $db = new Database(APP_NAME);
        }
        return $db;
    }
}

if (!function_exists('getActiveSubscription')) {
    function getActiveSubscription(?string $userId): ?array {
        if (!$userId) {
            return null;
        }
        try {
            $subs = saas_globalDatabase()->load('subscriptions', true);
        } catch (Exception $e) {
            error_log('getActiveSubscription load failed: ' . $e->getMessage());
            return null;
        }
        $now = time();
        foreach ($subs as $sub) {
            if (($sub['userId'] ?? '') !== $userId) {
                continue;
            }
            if (($sub['status'] ?? '') !== 'active') {
                continue;
            }
            $expiresAt = strtotime((string)($sub['expiresAt'] ?? ''));
            if ($expiresAt === false || $expiresAt < $now) {
                continue;
            }

            // Resync providers + monthlyRequestCap from the CURRENT plan record
            // so admin plan edits propagate to every active subscriber on the
            // next page load. Falls back to the snapshot if the plan was
            // deleted.
            if (!empty($sub['planId'])) {
                try {
                    $plans = saas_globalDatabase()->load('plans', true);
                    foreach ($plans as $plan) {
                        if (($plan['id'] ?? '') !== $sub['planId']) {
                            continue;
                        }
                        if (isset($plan['providers']) && is_array($plan['providers'])) {
                            $sub['providers'] = $plan['providers'];
                        }
                        if (isset($plan['monthlyRequestCap'])) {
                            $sub['monthlyRequestCap'] = (int)$plan['monthlyRequestCap'];
                        }
                        break;
                    }
                } catch (Exception $e) {
                    // Keep snapshot
                }
            }
            return $sub;
        }
        return null;
    }
}

if (!function_exists('getAdminSharedKey')) {
    function getAdminSharedKey(string $provider): ?string {
        try {
            $keys = saas_globalDatabase()->load('admin_shared_keys', false);
        } catch (Exception $e) {
            error_log('getAdminSharedKey load failed: ' . $e->getMessage());
            return null;
        }
        if (!is_array($keys)) {
            return null;
        }
        $map = [
            'groq' => 'groqApiKey',
            'openrouter' => 'openrouterApiKey',
            'gemini' => 'geminiApiKey'
        ];
        $field = $map[$provider] ?? null;
        if ($field === null) {
            return null;
        }
        $key = $keys[$field] ?? '';
        return $key !== '' ? $key : null;
    }
}

if (!function_exists('saas_userUsageDb')) {
    function saas_userUsageDb(string $userId): ?Database {
        if ($userId === '') {
            return null;
        }
        $masterPassword = getMasterPassword();
        if ($masterPassword === '') {
            return null;
        }
        try {
            return new Database($masterPassword, $userId);
        } catch (Exception $e) {
            error_log('saas_userUsageDb failed: ' . $e->getMessage());
            return null;
        }
    }
}

if (!function_exists('getMonthlyUsage')) {
    function getMonthlyUsage(string $userId, ?string $yearMonth = null): array {
        $yearMonth = $yearMonth ?? date('Y-m');
        $empty = ['totalRequests' => 0, 'byProvider' => []];
        $db = saas_userUsageDb($userId);
        if ($db === null) {
            return $empty;
        }
        try {
            $usage = $db->load('ai_usage', false);
        } catch (Exception $e) {
            return $empty;
        }
        if (!is_array($usage) || !isset($usage[$yearMonth])) {
            return $empty;
        }
        $month = $usage[$yearMonth];
        return [
            'totalRequests' => (int)($month['totalRequests'] ?? 0),
            'byProvider' => is_array($month['byProvider'] ?? null) ? $month['byProvider'] : []
        ];
    }
}

if (!function_exists('incrementUsage')) {
    function incrementUsage(string $userId, string $provider): void {
        $db = saas_userUsageDb($userId);
        if ($db === null) {
            return;
        }

        // Per-user advisory lock to make the load-modify-save cycle atomic
        // across concurrent requests. Lock file lives in sys_get_temp_dir so
        // OS cleanup handles stale entries.
        $lockPath = sys_get_temp_dir() . '/lazyman_ai_usage_' . md5($userId) . '.lock';
        $lockHandle = @fopen($lockPath, 'c');
        $haveLock = false;
        if ($lockHandle !== false) {
            $haveLock = flock($lockHandle, LOCK_EX);
        }
        try {
            $usage = $db->load('ai_usage', false);
            if (!is_array($usage)) {
                $usage = [];
            }
            $month = date('Y-m');
            if (!isset($usage[$month]) || !is_array($usage[$month])) {
                $usage[$month] = [
                    'totalRequests' => 0,
                    'byProvider' => [],
                    'lastReset' => date('c')
                ];
            }
            $usage[$month]['totalRequests'] = (int)($usage[$month]['totalRequests'] ?? 0) + 1;
            $byProvider = $usage[$month]['byProvider'] ?? [];
            if (!is_array($byProvider)) {
                $byProvider = [];
            }
            $byProvider[$provider] = (int)($byProvider[$provider] ?? 0) + 1;
            $usage[$month]['byProvider'] = $byProvider;
            $db->save('ai_usage', $usage);
        } catch (Exception $e) {
            error_log('incrementUsage save failed: ' . $e->getMessage());
        } finally {
            if ($lockHandle !== false) {
                if ($haveLock) {
                    flock($lockHandle, LOCK_UN);
                }
                fclose($lockHandle);
            }
        }
    }
}

if (!function_exists('overQuota')) {
    function overQuota(string $userId, array $subscription): bool {
        $cap = (int)($subscription['monthlyRequestCap'] ?? 0);
        if ($cap <= 0) {
            return false; // Zero/unset cap means unlimited.
        }
        $usage = getMonthlyUsage($userId);
        return ((int)($usage['totalRequests'] ?? 0)) >= $cap;
    }
}

if (!function_exists('subscriptionUnlocksProvider')) {
    function subscriptionUnlocksProvider(?array $subscription, string $provider): bool {
        if (!$subscription) {
            return false;
        }
        $providers = $subscription['providers'] ?? [];
        if (!is_array($providers) || empty($providers)) {
            return false;
        }
        return in_array($provider, $providers, true);
    }
}
