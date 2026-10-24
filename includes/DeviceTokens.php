<?php
/**
 * DeviceTokens — per-device bearer tokens for native mobile clients.
 *
 * Model (mirrors the MCP `mcpWrappedMaster` pattern, see Auth::generateMcpApiToken):
 *  - On issue(), the user's master password is wrapped (AES-256-GCM via the
 *    Encryption class) using the PLAINTEXT device token (+ optional pepper) as the
 *    key, and stored in data/device_tokens.json. Only sha256(token) is persisted
 *    for lookup — the plaintext token is never stored.
 *  - On resolve(), the incoming token unwraps the master, which the caller drops
 *    into $_SESSION[SESSION_MASTER_KEY] so every existing endpoint decrypts as
 *    usual. The master password therefore never travels on each request.
 *
 * Why plaintext storage of this file is acceptable (same rationale as AlexaOAuth):
 *  - wrappedMaster can only be opened with the per-device token, which is never
 *    stored (only its sha256 hash is). A data-dir leak alone decrypts nothing.
 *  - File is written 0600 and lives under DATA_PATH (blocked by data/.htaccess).
 *  - Optional DEVICE_TOKEN_PEPPER env adds offline resistance without becoming a
 *    single secret whose loss would decrypt every user's master.
 *
 * Storage: data/device_tokens.json — array of records:
 *   { id, userId, name, platform, tokenHash, tokenLast4, wrappedMaster,
 *     expiresAt(null), createdAt, lastUsedAt, revoked, revokedAt? }
 */

class DeviceTokens {
    private const TOKEN_PREFIX = 'dvt_';
    private const HASH_ALGO = 'sha256';
    private const MAX_PER_USER = 10;

    // ─────────────────────────────────────────────────────────────────────
    // Public API
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Issue a new device token for a user. The master password is verified by
     * the caller (api/auth.php) BEFORE calling this.
     *
     * @return array{device_token:string, device:array}
     */
    public static function issue(string $userId, string $master, string $name, string $platform): array {
        if ($userId === '' || $master === '') {
            throw new Exception('issue() requires userId and master');
        }

        $token = self::TOKEN_PREFIX . bin2hex(random_bytes(32));
        $id = self::uuid();
        $now = date('c');

        $wrapped = (new Encryption($token . self::pepper()))->encrypt(json_encode(['m' => $master]));

        $rows = self::load();
        $rows[] = [
            'id'           => $id,
            'userId'       => $userId,
            'name'         => self::sanitizeText($name, 60, 'Mobile device'),
            'platform'     => self::sanitizeText($platform, 20, 'unknown'),
            'tokenHash'    => hash(self::HASH_ALGO, $token),
            'tokenLast4'   => substr($token, -4),
            'wrappedMaster' => $wrapped,
            'expiresAt'    => null,            // v1: non-expiring; revoke to invalidate
            'createdAt'    => $now,
            'lastUsedAt'   => $now,
            'revoked'      => false,
        ];

        $rows = self::enforcePerUserCap($rows, $userId);
        self::save($rows);

        return [
            'device_token' => $token,
            'device' => [
                'id'        => $id,
                'name'      => self::sanitizeText($name, 60, 'Mobile device'),
                'platform'  => self::sanitizeText($platform, 20, 'unknown'),
                'last4'     => substr($token, -4),
                'createdAt' => $now,
            ],
        ];
    }

    /**
     * Resolve an incoming plaintext token to its user + unwrapped master.
     * Returns null on any failure (unknown/revoked/expired/banned/unwrap error).
     *
     * @return array{userId:string, master:string, recordId:string}|null
     */
    public static function resolve(string $tokenPlain): ?array {
        $tokenPlain = trim($tokenPlain);
        if ($tokenPlain === '') {
            return null;
        }

        $hash = hash(self::HASH_ALGO, $tokenPlain);
        $now = time();
        $row = null;
        foreach (self::load() as $r) {
            if (!empty($r['revoked'])) continue;
            if (!hash_equals((string)($r['tokenHash'] ?? ''), $hash)) continue;
            $exp = $r['expiresAt'] ?? null;
            if (!empty($exp)) {
                $expTs = strtotime((string)$exp);
                if ($expTs !== false && $expTs < $now) continue;
            }
            $row = $r;
            break;
        }
        if ($row === null) {
            return null;
        }

        // Banned-user check. The `users` collection is plaintext (no key needed),
        // matching Auth::isMcp()'s banned handling. Fail closed if it can't load.
        try {
            $users = (new Database(''))->load('users', true);
            foreach ($users as $u) {
                if (($u['id'] ?? '') === ($row['userId'] ?? '')) {
                    if (!empty($u['banned'])) {
                        return null;
                    }
                    break;
                }
            }
        } catch (Throwable $e) {
            return null;
        }

        // Unwrap the master with the token (+ pepper).
        try {
            $payload = (new Encryption($tokenPlain . self::pepper()))->decrypt((string)($row['wrappedMaster'] ?? ''));
            $master = is_array($payload) ? (string)($payload['m'] ?? '') : (string)$payload;
        } catch (Throwable $e) {
            return null;
        }
        if ($master === '') {
            return null;
        }

        return [
            'userId'   => (string)($row['userId'] ?? ''),
            'master'   => $master,
            'recordId' => (string)($row['id'] ?? ''),
        ];
    }

    /** Revoke a single device token, scoped to its owner. */
    public static function revoke(string $userId, string $recordId): bool {
        $rows = self::load();
        $changed = false;
        foreach ($rows as &$r) {
            if (($r['id'] ?? '') === $recordId
                && ($r['userId'] ?? '') === $userId
                && empty($r['revoked'])) {
                $r['revoked'] = true;
                $r['revokedAt'] = date('c');
                $changed = true;
                break;
            }
        }
        unset($r);
        if ($changed) {
            self::save($rows);
        }
        return $changed;
    }

    /** Revoke every active device token for a user (ban / master-password change). */
    public static function revokeAllForUser(string $userId): int {
        if ($userId === '') {
            return 0;
        }
        $rows = self::load();
        $count = 0;
        foreach ($rows as &$r) {
            if (($r['userId'] ?? '') === $userId && empty($r['revoked'])) {
                $r['revoked'] = true;
                $r['revokedAt'] = date('c');
                $count++;
            }
        }
        unset($r);
        if ($count > 0) {
            self::save($rows);
        }
        return $count;
    }

    /** Rename a device, scoped to its owner. */
    public static function rename(string $userId, string $recordId, string $name): bool {
        $rows = self::load();
        $changed = false;
        foreach ($rows as &$r) {
            if (($r['id'] ?? '') === $recordId && ($r['userId'] ?? '') === $userId) {
                $r['name'] = self::sanitizeText($name, 60, 'Mobile device');
                $changed = true;
                break;
            }
        }
        unset($r);
        if ($changed) {
            self::save($rows);
        }
        return $changed;
    }

    /** Public, non-sensitive view of a user's active devices (for the Devices UI). */
    public static function listForUser(string $userId): array {
        $out = [];
        foreach (self::load() as $r) {
            if (($r['userId'] ?? '') !== $userId) continue;
            if (!empty($r['revoked'])) continue;
            $out[] = [
                'id'         => $r['id'] ?? '',
                'name'       => $r['name'] ?? '',
                'platform'   => $r['platform'] ?? '',
                'last4'      => $r['tokenLast4'] ?? '',
                'createdAt'  => $r['createdAt'] ?? '',
                'lastUsedAt' => $r['lastUsedAt'] ?? '',
                'expiresAt'  => $r['expiresAt'] ?? null,
            ];
        }
        return $out;
    }

    /** Bump lastUsedAt for a record (best-effort; called on each authed request). */
    public static function touchLastUsed(string $recordId): void {
        if ($recordId === '') {
            return;
        }
        $rows = self::load();
        $found = false;
        foreach ($rows as &$r) {
            if (($r['id'] ?? '') === $recordId) {
                $r['lastUsedAt'] = date('c');
                $found = true;
                break;
            }
        }
        unset($r);
        if ($found) {
            self::save($rows);
        }
    }

    /** Optional defense-in-depth pepper (env). Empty string = pure MCP model. */
    public static function pepper(): string {
        return (string)(getenv('DEVICE_TOKEN_PEPPER') ?: '');
    }

    // ─────────────────────────────────────────────────────────────────────
    // Internals
    // ─────────────────────────────────────────────────────────────────────

    /** Cap active tokens per user; revoke oldest (by createdAt) beyond the cap. */
    private static function enforcePerUserCap(array $rows, string $userId): array {
        $active = [];
        foreach ($rows as $i => $r) {
            if (($r['userId'] ?? '') === $userId && empty($r['revoked'])) {
                $active[$i] = strtotime((string)($r['createdAt'] ?? '')) ?: 0;
            }
        }
        if (count($active) <= self::MAX_PER_USER) {
            return $rows;
        }
        asort($active); // oldest first
        $excess = count($active) - self::MAX_PER_USER;
        $toRevoke = array_slice(array_keys($active), 0, $excess);
        foreach ($toRevoke as $idx) {
            $rows[$idx]['revoked'] = true;
            $rows[$idx]['revokedAt'] = date('c');
        }
        return $rows;
    }

    private static function path(): string {
        return DATA_PATH . '/device_tokens.json';
    }

    private static function load(): array {
        $p = self::path();
        if (!file_exists($p)) {
            return [];
        }
        $contents = @file_get_contents($p);
        if ($contents === false || $contents === '') {
            return [];
        }
        $arr = json_decode($contents, true);
        return is_array($arr) ? $arr : [];
    }

    private static function save(array $rows): void {
        $json = json_encode(array_values($rows), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new Exception('Encode failed');
        }
        $path = self::path();
        $tmp = $path . '.tmp';
        $fp = fopen($tmp, 'w');
        if ($fp === false) {
            throw new Exception('Cannot open temp file');
        }
        if (!flock($fp, LOCK_EX)) {
            fclose($fp);
            throw new Exception('Cannot lock');
        }
        fwrite($fp, $json);
        fflush($fp);
        flock($fp, LOCK_UN);
        fclose($fp);
        rename($tmp, $path);
        @chmod($path, 0600);
    }

    private static function sanitizeText(string $value, int $maxLen, string $fallback): string {
        $value = trim(preg_replace('/[\x00-\x1F\x7F]/u', '', $value) ?? '');
        if ($value === '') {
            return $fallback;
        }
        if (function_exists('mb_substr')) {
            return mb_substr($value, 0, $maxLen);
        }
        return substr($value, 0, $maxLen);
    }

    private static function uuid(): string {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }
}
