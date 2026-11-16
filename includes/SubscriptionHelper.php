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
        // A paid subscription wins over a free trial if both are somehow active.
        $trialFallback = null;
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
            if (!empty($sub['trial'])) {
                $trialFallback = $trialFallback ?? $sub;
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
                        if (isset($plan['allowedModels']) && is_array($plan['allowedModels'])) {
                            $sub['allowedModels'] = $plan['allowedModels'];
                        }
                        break;
                    }
                } catch (Exception $e) {
                    // Keep snapshot
                }
            }
            return $sub;
        }
        return $trialFallback;
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
        $usage = subscriptionUsage($userId, $subscription);
        if ($usage['cap'] <= 0) {
            return false; // Zero/unset cap means unlimited.
        }
        return $usage['used'] >= $usage['cap'];
    }
}

if (!function_exists('quotaExceededMessage')) {
    /** What to tell someone who has used up the requests on their trial or plan. */
    function quotaExceededMessage(array $subscription): string {
        if (!empty($subscription['trial'])) {
            return 'You have used all the AI requests in your free trial. Pick a plan to keep going, or add your own API key in Settings.';
        }
        return 'You have used all of this month\'s AI requests on your plan. Upgrade, add your own API key in Settings, or wait for next month.';
    }
}

if (!function_exists('subscriptionUsage')) {
    /**
     * Requests used against this subscription's cap: ['used', 'cap', 'perTrial'].
     * A paid plan counts the current calendar month. A free trial counts every
     * platform-key request since it started (a trial is only given to accounts
     * that never had a plan, and only platform-key use is counted, so all counted
     * requests in those months belong to the trial).
     */
    function subscriptionUsage(string $userId, array $subscription): array {
        $cap = (int)($subscription['monthlyRequestCap'] ?? 0);
        if (empty($subscription['trial'])) {
            return ['used' => (int)(getMonthlyUsage($userId)['totalRequests'] ?? 0), 'cap' => $cap, 'perTrial' => false];
        }
        $start = strtotime((string)($subscription['startsAt'] ?? '')) ?: time();
        $used = 0;
        $month = strtotime(date('Y-m-01', $start));
        $guard = 0;
        while ($month <= time() && $guard++ < 24) {
            $used += (int)(getMonthlyUsage($userId, date('Y-m', $month))['totalRequests'] ?? 0);
            $month = strtotime('+1 month', $month);
        }
        return ['used' => $used, 'cap' => $cap, 'perTrial' => true];
    }
}

if (!function_exists('userUsesSharedKeys')) {
    /**
     * Whether this user's AI calls run on the platform's shared keys.
     *
     * Decided from the GLOBAL subscription record because a plan can be activated
     * without the buyer's session (admin approval, PayFast notification), and no
     * other session can open the buyer's encrypted config. While a subscription
     * is active the answer is yes, unless the user switched it off in Settings
     * after that subscription started.
     */
    function userUsesSharedKeys(array $config, ?array $subscription): bool {
        if (!$subscription) {
            return false;
        }
        if (!array_key_exists('useAdminSharedAiKeys', $config) || !empty($config['useAdminSharedAiKeys'])) {
            return true;
        }
        $optedOutAt = strtotime((string)($config['useAdminSharedAiKeysSetAt'] ?? '')) ?: 0;
        $startedAt = strtotime((string)($subscription['startsAt'] ?? $subscription['createdAt'] ?? '')) ?: 0;
        return $optedOutAt < $startedAt;
    }
}

if (!function_exists('applyEffectiveSharedKeyFlag')) {
    /**
     * Returns $config with useAdminSharedAiKeys set to its effective value.
     * For use in memory only: never save the returned array.
     */
    function applyEffectiveSharedKeyFlag(array $config, ?string $userId): array {
        $config['useAdminSharedAiKeys'] = userUsesSharedKeys($config, $userId ? getActiveSubscription($userId) : null);
        return $config;
    }
}

if (!function_exists('saas_lockSubscriptions')) {
    /**
     * Exclusive lock for a load-modify-save of the global subscriptions file.
     * Database::save() only locks the write itself, so two writers (a payment
     * notification and a page clearing a notice) could otherwise lose an update.
     * Pass the handle to saas_unlockSubscriptions() in a finally block.
     */
    function saas_lockSubscriptions() {
        $handle = @fopen(DATA_PATH . '/subscriptions.lock', 'c');
        if ($handle !== false) {
            flock($handle, LOCK_EX);
        }
        return $handle;
    }

    function saas_unlockSubscriptions($handle): void {
        if ($handle !== false) {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }
}

if (!function_exists('consumeSubscriptionNotice')) {
    /**
     * One-shot "your plan is active" notice, set on the global subscription when
     * a payment activates or extends it. Returns it once, then clears it.
     */
    function consumeSubscriptionNotice(?string $userId): ?array {
        if (!$userId) {
            return null;
        }
        $lock = saas_lockSubscriptions();
        try {
            $db = saas_globalDatabase();
            $subs = $db->load('subscriptions', true);
            foreach ($subs as $i => $sub) {
                if (($sub['userId'] ?? '') !== $userId || empty($sub['noticePending'])) {
                    continue;
                }
                $subs[$i]['noticePending'] = false;
                $db->save('subscriptions', $subs);
                return [
                    'planName' => (string)($sub['planSnapshot']['name'] ?? 'Your plan'),
                    'expiresAt' => (string)($sub['expiresAt'] ?? ''),
                    'providers' => is_array($sub['providers'] ?? null) ? $sub['providers'] : [],
                ];
            }
        } catch (Throwable $e) {
            error_log('consumeSubscriptionNotice failed: ' . $e->getMessage());
        } finally {
            saas_unlockSubscriptions($lock);
        }
        return null;
    }
}

if (!function_exists('platformKeyCovers')) {
    /** True when calls to $provider for this subscriber are paid by the platform's shared key. */
    function platformKeyCovers(?array $subscription, string $provider): bool {
        return $subscription !== null
            && subscriptionUnlocksProvider($subscription, $provider)
            && getAdminSharedKey($provider) !== null;
    }
}

if (!function_exists('sharedModelAllowList')) {
    /**
     * Models a subscriber may run on the platform key for $provider: the plan's own
     * `allowedModels[$provider]` list when the plan sets one, otherwise the models
     * the admin enabled for the shared keys. Empty means none are allowed.
     * A plan's list never reaches past the shared list: a model the admin later
     * disables under Shared AI Keys stops working for every plan at once.
     */
    function sharedModelAllowList(?array $subscription, string $provider): array {
        if (!$subscription) {
            return [];
        }
        require_once __DIR__ . '/AISharedKeysAPI.php';
        try {
            $shared = (new AISharedKeysAPI())->getSharedModelIds($provider);
        } catch (Throwable $e) {
            error_log('sharedModelAllowList failed: ' . $e->getMessage());
            return [];
        }
        $planList = $subscription['allowedModels'][$provider] ?? null;
        if (is_array($planList) && $planList !== []) {
            $planList = array_values(array_filter(array_map('strval', $planList), fn($id) => $id !== ''));
            return array_values(array_intersect($planList, $shared));
        }
        return $shared;
    }
}

if (!function_exists('planSharedModels')) {
    /**
     * Shared model records (modelId, displayName, isDefault) this subscriber may
     * pick for $provider, in the admin's order. Used to fill model dropdowns, so a
     * plan user is never offered a model the server would refuse.
     */
    function planSharedModels(?array $subscription, string $provider): array {
        if (!$subscription || !subscriptionUnlocksProvider($subscription, $provider)) {
            return [];
        }
        $allowed = sharedModelAllowList($subscription, $provider);
        if ($allowed === []) {
            return [];
        }
        require_once __DIR__ . '/AISharedKeysAPI.php';
        try {
            $models = (new AISharedKeysAPI())->getSharedModels($provider);
        } catch (Throwable $e) {
            error_log('planSharedModels failed: ' . $e->getMessage());
            return [];
        }
        return array_values(array_filter($models, fn($m) => in_array((string)($m['modelId'] ?? ''), $allowed, true)));
    }
}

if (!function_exists('choosePlanModel')) {
    /**
     * The model to run on the platform key for one request.
     * Returns [modelId, null] on success or [null, message] when it must be refused.
     * $autoRequested: the client asked for "auto"/no model, so the plan's first model may be used.
     */
    function choosePlanModel(array $subscription, string $provider, ?string $model, bool $autoRequested): array {
        $planModels = sharedModelAllowList($subscription, $provider);
        if ($planModels === []) {
            return [null, 'No ' . $provider . ' models are enabled for plan AI yet. Ask the admin, or add your own key in Settings.'];
        }
        if (in_array((string)$model, $planModels, true)) {
            return [(string)$model, null];
        }
        if ($autoRequested) {
            return [$planModels[0], null];
        }
        return [null, 'The model ' . $model . ' is not included in your plan. Choose a model from the list, or add your own API key in Settings to use it.'];
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

/* ---------------------------------------------------------------------------
 * Free trial: no card, a fixed number of AI requests on the platform keys for a
 * fixed number of days, once per account. Off unless TRIAL_ENABLED=1, because it
 * spends the operator's AI money.
 * ------------------------------------------------------------------------- */

if (!function_exists('trialSettings')) {
    function trialSettings(): array {
        $enabled = filter_var(getenv('TRIAL_ENABLED') ?: '0', FILTER_VALIDATE_BOOLEAN);
        $days = max(1, min(60, (int)(getenv('TRIAL_DAYS') ?: 14)));
        $requests = max(1, min(10000, (int)(getenv('TRIAL_REQUESTS') ?: 50)));
        $known = ['groq', 'openrouter', 'gemini'];
        $providers = array_values(array_unique(array_filter(
            array_map(fn($p) => strtolower(trim($p)), explode(',', (string)(getenv('TRIAL_PROVIDERS') ?: 'groq'))),
            fn($p) => in_array($p, $known, true)
        )));
        // Optional TRIAL_MODELS="groq:llama-3.1-8b-instant,openrouter:some/model".
        $allowed = [];
        foreach (array_filter(array_map('trim', explode(',', (string)(getenv('TRIAL_MODELS') ?: '')))) as $pair) {
            [$p, $m] = array_pad(explode(':', $pair, 2), 2, '');
            $p = strtolower(trim($p));
            $m = trim($m);
            if (in_array($p, $providers, true) && $m !== '') {
                $allowed[$p][] = $m;
            }
        }
        return ['enabled' => $enabled, 'days' => $days, 'requests' => $requests, 'providers' => $providers, 'allowedModels' => $allowed];
    }
}

if (!function_exists('trialEligibility')) {
    /**
     * Whether $userId may start a free trial now.
     * Returns ['ok' => bool, 'code' => string, 'message' => string].
     */
    function trialEligibility(string $userId): array {
        $no = fn(string $code, string $message) => ['ok' => false, 'code' => $code, 'message' => $message];
        $settings = trialSettings();
        if (!$settings['enabled'] || $userId === '') {
            return $no('TRIAL_UNAVAILABLE', 'Free trials are not available right now.');
        }
        $db = saas_globalDatabase();
        $user = null;
        foreach ($db->load('users', true) as $u) {
            if (($u['id'] ?? '') === $userId) {
                $user = $u;
                break;
            }
        }
        if ($user === null) {
            return $no('TRIAL_UNAVAILABLE', 'Free trials are not available right now.');
        }
        if (function_exists('isEmailVerificationEnabled') && isEmailVerificationEnabled() && empty($user['emailVerifiedAt'])) {
            return $no('EMAIL_NOT_VERIFIED', 'Verify your email address first, then start your free trial.');
        }
        if (!empty($user['trialStartedAt'])) {
            return $no('TRIAL_USED', 'You have already used your free trial. Pick a plan to keep using the AI on our keys.');
        }
        foreach ($db->load('subscriptions', true) as $s) {
            if (($s['userId'] ?? '') === $userId) {
                return $no('TRIAL_USED', 'Free trials are for accounts that have not had a plan yet. Pick a plan instead.');
            }
        }
        $covered = array_filter($settings['providers'], fn($p) => getAdminSharedKey($p) !== null);
        if ($covered === []) {
            return $no('TRIAL_UNAVAILABLE', 'Free trials are not available right now.');
        }
        return ['ok' => true, 'code' => 'OK', 'message' => ''];
    }
}

if (!function_exists('startTrial')) {
    /**
     * Start the free trial for $userId. Returns ['ok' => true, 'subscription' => [...]]
     * or the refusal from trialEligibility(). The check and the write happen under
     * the subscriptions lock, so two clicks cannot start two trials.
     */
    function startTrial(string $userId): array {
        $settings = trialSettings();
        $lock = saas_lockSubscriptions();
        try {
            $check = trialEligibility($userId);
            if (!$check['ok']) {
                return $check;
            }
            $db = saas_globalDatabase();
            $providers = array_values(array_filter($settings['providers'], fn($p) => getAdminSharedKey($p) !== null));
            $now = time();
            $nowIso = date('c', $now);
            $sub = [
                'id' => $db->generateId(),
                'userId' => $userId,
                'planId' => null,
                'trial' => true,
                'provider' => 'trial',
                'planSnapshot' => [
                    'name' => 'Pro trial',
                    'price' => 0,
                    'currency' => 'USD',
                    'durationDays' => $settings['days'],
                    'providers' => $providers,
                    'monthlyRequestCap' => $settings['requests'],
                ],
                'paymentId' => null,
                'status' => 'active',
                'startsAt' => $nowIso,
                'expiresAt' => date('c', $now + $settings['days'] * 86400),
                'cancelledAt' => null,
                'providers' => $providers,
                'monthlyRequestCap' => $settings['requests'],
                'noticePending' => false,
                'createdAt' => $nowIso,
                'updatedAt' => $nowIso,
            ];
            if ($settings['allowedModels'] !== []) {
                $sub['allowedModels'] = $settings['allowedModels'];
            }
            $subs = $db->load('subscriptions', true);
            $subs[] = $sub;
            $db->save('subscriptions', $subs);
            // Second marker on the account (the subscription record is the first).
            $db->update('users', $userId, ['trialStartedAt' => $nowIso]);
            return ['ok' => true, 'subscription' => $sub];
        } finally {
            saas_unlockSubscriptions($lock);
        }
    }
}