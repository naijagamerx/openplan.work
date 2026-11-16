<?php
/**
 * SubscriptionsAPI — admin cancel/extend + user own subscription getters.
 * Storage: global `subscriptions` collection (Database(APP_NAME)).
 */

require_once __DIR__ . '/Database.php';

class SubscriptionsAPI {
    private Database $db;

    public function __construct() {
        $this->db = new Database(APP_NAME);
    }

    public function getAll(): array {
        $subs = $this->db->load('subscriptions', true);
        usort($subs, function ($a, $b) {
            // Active first, then most recently created.
            $aActive = (($a['status'] ?? '') === 'active') ? 0 : 1;
            $bActive = (($b['status'] ?? '') === 'active') ? 0 : 1;
            if ($aActive !== $bActive) {
                return $aActive - $bActive;
            }
            return strcmp((string)($b['createdAt'] ?? ''), (string)($a['createdAt'] ?? ''));
        });
        return $subs;
    }

    public function getByUser(string $userId): array {
        $subs = $this->db->load('subscriptions', true);
        return array_values(array_filter($subs, fn($s) => ($s['userId'] ?? '') === $userId));
    }

    public function getActiveByUser(string $userId): ?array {
        require_once __DIR__ . '/SubscriptionHelper.php';
        return getActiveSubscription($userId);
    }

    public function find(string $id): ?array {
        $subs = $this->db->load('subscriptions', true);
        foreach ($subs as $s) {
            if (($s['id'] ?? '') === $id) {
                return $s;
            }
        }
        return null;
    }

    public function cancel(string $id, string $adminId): ?array {
        return $this->updateLocked($id, function (array $s) use ($adminId): array {
            $s['status'] = 'cancelled';
            $s['autoRenew'] = false;
            $s['cancelledAt'] = $s['cancelledAt'] ?? date('c');
            $s['cancelledBy'] = $adminId;
            return $s;
        }, ['status' => 'cancelled']);
    }

    public function extend(string $id, int $extraDays, string $adminId): ?array {
        if ($extraDays <= 0) {
            return null;
        }
        return $this->updateLocked($id, function (array $s) use ($extraDays, $adminId): array {
            $current = strtotime((string)($s['expiresAt'] ?? ''));
            if ($current === false || $current < time()) {
                $current = time();
            }
            $s['expiresAt'] = date('c', $current + $extraDays * 86400);
            $s['status'] = 'active';
            $s['extendedBy'] = $adminId;
            // The new end date gets its own "expiring soon" email.
            $s['expiringEmailSentAt'] = null;
            return $s;
        }, ['extendedDays' => $extraDays]);
    }

    public function markExpired(string $id): ?array {
        return $this->updateLocked($id, function (array $s): array {
            $s['status'] = 'expired';
            return $s;
        }, ['status' => 'expired']);
    }

    /**
     * Load-modify-save of one subscription under the subscriptions lock, so an
     * admin change and a PayFast notification arriving together cannot overwrite
     * each other.
     */
    private function updateLocked(string $id, callable $change, array $auditDetails): ?array {
        require_once __DIR__ . '/SubscriptionHelper.php';
        $updated = null;
        $lock = saas_lockSubscriptions();
        try {
            $subs = $this->db->load('subscriptions', true);
            foreach ($subs as $i => $s) {
                if (($s['id'] ?? '') !== $id) {
                    continue;
                }
                $subs[$i] = $change($s);
                $subs[$i]['updatedAt'] = date('c');
                $this->db->save('subscriptions', $subs);
                $updated = $subs[$i];
                break;
            }
        } finally {
            saas_unlockSubscriptions($lock);
        }
        if ($updated !== null && class_exists('Audit')) {
            try {
                Audit::logData('update', 'subscription', $id, $auditDetails);
            } catch (Throwable $e) {
                error_log('subscription audit failed: ' . $e->getMessage());
            }
        }
        return $updated;
    }
}
