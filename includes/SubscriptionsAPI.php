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
        $subs = $this->db->load('subscriptions', true);
        foreach ($subs as $i => $s) {
            if (($s['id'] ?? '') !== $id) {
                continue;
            }
            $subs[$i]['status'] = 'cancelled';
            $subs[$i]['cancelledAt'] = date('c');
            $subs[$i]['cancelledBy'] = $adminId;
            $subs[$i]['updatedAt'] = date('c');
            $this->db->save('subscriptions', $subs);
            if (class_exists('Audit')) {
                Audit::logData('update', 'subscription', $id, ['status' => 'cancelled']);
            }
            return $subs[$i];
        }
        return null;
    }

    public function extend(string $id, int $extraDays, string $adminId): ?array {
        if ($extraDays <= 0) {
            return null;
        }
        $subs = $this->db->load('subscriptions', true);
        foreach ($subs as $i => $s) {
            if (($s['id'] ?? '') !== $id) {
                continue;
            }
            $current = strtotime((string)($s['expiresAt'] ?? ''));
            if ($current === false || $current < time()) {
                $current = time();
            }
            $newExpiry = $current + $extraDays * 86400;
            $subs[$i]['expiresAt'] = date('c', $newExpiry);
            $subs[$i]['status'] = 'active';
            $subs[$i]['updatedAt'] = date('c');
            $subs[$i]['extendedBy'] = $adminId;
            $this->db->save('subscriptions', $subs);
            if (class_exists('Audit')) {
                Audit::logData('update', 'subscription', $id, ['extendedDays' => $extraDays]);
            }
            return $subs[$i];
        }
        return null;
    }

    public function markExpired(string $id): ?array {
        $subs = $this->db->load('subscriptions', true);
        foreach ($subs as $i => $s) {
            if (($s['id'] ?? '') !== $id) {
                continue;
            }
            $subs[$i]['status'] = 'expired';
            $subs[$i]['updatedAt'] = date('c');
            $this->db->save('subscriptions', $subs);
            if (class_exists('Audit')) {
                Audit::logData('update', 'subscription', $id, ['status' => 'expired']);
            }
            return $subs[$i];
        }
        return null;
    }
}
