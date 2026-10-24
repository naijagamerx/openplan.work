<?php
/**
 * AlexaOAuth — OAuth 2.0 provider for Alexa account linking.
 *
 * Responsibilities:
 *  - Wrap/unwrap per-user data keys with the platform key (ALEXA_PLATFORM_KEY)
 *  - Mint short-lived auth codes (10 min TTL) during /authorize step
 *  - Mint access/refresh token pairs during /token step
 *  - Resolve incoming access tokens back to OpenPlan user + wrapped data key
 *  - Validate OAuth client credentials (basic auth from Amazon)
 *  - Storage: plaintext JSON files (sensitive material is already encrypted/hashed)
 *      - data/alexa_links.json          — long-lived per-user links
 *      - data/alexa_auth_codes.json     — short-lived pending auth codes
 *
 * Why plaintext storage is acceptable:
 *  - Data keys are encrypted with ALEXA_PLATFORM_KEY (defense layer 1)
 *  - Access/refresh tokens stored as SHA-256 hashes only (one-way)
 *  - Mapping leak (amazonUserId ↔ openplanUserId) is low value alone
 */

class AlexaOAuth {
    private const CIPHER = 'aes-256-gcm';
    private const IV_LEN = 12;
    private const TAG_LEN = 16;
    private const AUTH_CODE_TTL = 600;      // 10 minutes
    private const ACCESS_TOKEN_TTL = 3600;  // 1 hour
    private const TOKEN_HASH_ALGO = 'sha256';

    // ─────────────────────────────────────────────────────────────────────
    // Data key wrapping
    // ─────────────────────────────────────────────────────────────────────

    public static function platformKey(): string {
        $b64 = (string)(getenv('ALEXA_PLATFORM_KEY') ?: '');
        if ($b64 === '') {
            throw new Exception('ALEXA_PLATFORM_KEY env var not set.');
        }
        $raw = base64_decode($b64, true);
        if ($raw === false || strlen($raw) !== 32) {
            throw new Exception('ALEXA_PLATFORM_KEY must be base64 of 32 random bytes.');
        }
        return $raw;
    }

    /**
     * Wraps a 32-byte data key (K) under the platform key.
     * Returns array with base64 parts ready for JSON storage.
     */
    public static function wrapDataKey(string $rawDataKey): array {
        if (strlen($rawDataKey) !== 32) {
            throw new Exception('rawDataKey must be 32 bytes.');
        }
        $platform = self::platformKey();
        $iv = random_bytes(self::IV_LEN);
        $tag = '';
        $ciphertext = openssl_encrypt(
            $rawDataKey, self::CIPHER, $platform,
            OPENSSL_RAW_DATA, $iv, $tag, '', self::TAG_LEN
        );
        if ($ciphertext === false) {
            throw new Exception('Wrap failed: ' . openssl_error_string());
        }
        return [
            'wrappedDataKey' => base64_encode($ciphertext),
            'wrapIv' => base64_encode($iv),
            'wrapTag' => base64_encode($tag)
        ];
    }

    /**
     * Unwraps a stored link record (or auth code row) back to raw 32-byte K.
     */
    public static function unwrapDataKey(array $record): string {
        $platform = self::platformKey();
        $ciphertext = base64_decode((string)($record['wrappedDataKey'] ?? ''), true);
        $iv = base64_decode((string)($record['wrapIv'] ?? ''), true);
        $tag = base64_decode((string)($record['wrapTag'] ?? ''), true);
        if ($ciphertext === false || $iv === false || $tag === false) {
            throw new Exception('Wrap record malformed.');
        }
        $raw = openssl_decrypt(
            $ciphertext, self::CIPHER, $platform,
            OPENSSL_RAW_DATA, $iv, $tag
        );
        if ($raw === false || strlen($raw) !== 32) {
            throw new Exception('Unwrap failed: invalid key or corrupted record.');
        }
        return $raw;
    }

    /**
     * Convenience: derives raw data key from the user's master password
     * (same SHA-256 step used by the Encryption class) and immediately wraps it.
     * Used by /api/users.php?action=enable_alexa.
     */
    public static function wrapMasterPassword(string $masterPassword): array {
        $rawKey = hash('sha256', $masterPassword, true);
        return self::wrapDataKey($rawKey);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Client credentials
    // ─────────────────────────────────────────────────────────────────────

    public static function expectedClientId(): string {
        return (string)(getenv('ALEXA_OAUTH_CLIENT_ID') ?: 'openplan-alexa');
    }

    public static function validateClientCredentials(?string $clientId, ?string $clientSecret): bool {
        $expectedId = self::expectedClientId();
        $expectedSecret = (string)(getenv('ALEXA_OAUTH_CLIENT_SECRET') ?: '');
        if ($expectedSecret === '') return false;
        if (!is_string($clientId) || !is_string($clientSecret)) return false;
        return hash_equals($expectedId, $clientId) && hash_equals($expectedSecret, $clientSecret);
    }

    /**
     * Allowlist of redirect URIs Amazon may send us back to.
     * https://developer.amazon.com/en-US/docs/alexa/account-linking/configure-authorization-code-grant.html
     */
    public static function isValidRedirectUri(string $uri): bool {
        $allowed = [
            'https://layla.amazon.com/api/skill/link/',
            'https://pitangui.amazon.com/api/skill/link/',
            'https://alexa.amazon.co.jp/api/skill/link/'
        ];
        foreach ($allowed as $prefix) {
            if (strpos($uri, $prefix) === 0) return true;
        }
        return false;
    }

    // ─────────────────────────────────────────────────────────────────────
    // Auth codes (short-lived; phase 1 of OAuth dance)
    // ─────────────────────────────────────────────────────────────────────

    public static function mintAuthCode(
        string $openplanUserId,
        string $redirectUri,
        ?string $codeChallenge,
        ?string $codeChallengeMethod,
        array $wrappedKey
    ): string {
        $code = bin2hex(random_bytes(32));
        $codes = self::loadCodes();
        $codes[] = [
            'code' => $code,
            'openplanUserId' => $openplanUserId,
            'wrappedDataKey' => $wrappedKey['wrappedDataKey'],
            'wrapIv' => $wrappedKey['wrapIv'],
            'wrapTag' => $wrappedKey['wrapTag'],
            'redirectUri' => $redirectUri,
            'codeChallenge' => $codeChallenge,
            'codeChallengeMethod' => $codeChallengeMethod,
            'expiresAt' => date('c', time() + self::AUTH_CODE_TTL),
            'consumed' => false,
            'createdAt' => date('c')
        ];
        self::saveCodes(self::pruneExpiredCodes($codes));
        return $code;
    }

    public static function consumeAuthCode(string $code, string $redirectUri, ?string $codeVerifier): ?array {
        $codes = self::loadCodes();
        $matchIdx = -1;
        $now = time();
        foreach ($codes as $i => $row) {
            if (!empty($row['consumed'])) continue;
            if (($row['code'] ?? '') !== $code) continue;
            if (($row['redirectUri'] ?? '') !== $redirectUri) continue;
            if (strtotime((string)($row['expiresAt'] ?? '0')) < $now) continue;
            $matchIdx = $i;
            break;
        }
        if ($matchIdx === -1) return null;

        // PKCE check
        $challenge = $codes[$matchIdx]['codeChallenge'] ?? null;
        $method = $codes[$matchIdx]['codeChallengeMethod'] ?? null;
        if ($challenge !== null && $challenge !== '') {
            if ($codeVerifier === null || $codeVerifier === '') return null;
            if ($method === 'S256' || $method === null) {
                $derived = rtrim(strtr(base64_encode(hash('sha256', $codeVerifier, true)), '+/', '-_'), '=');
                if (!hash_equals($challenge, $derived)) return null;
            } else if ($method === 'plain') {
                if (!hash_equals($challenge, $codeVerifier)) return null;
            } else {
                return null;  // unknown method
            }
        }

        $codes[$matchIdx]['consumed'] = true;
        self::saveCodes($codes);
        return $codes[$matchIdx];
    }

    // ─────────────────────────────────────────────────────────────────────
    // Access / refresh tokens (phase 2 of OAuth dance)
    // ─────────────────────────────────────────────────────────────────────

    public static function issueTokensForCode(array $consumedCodeRow, ?string $amazonUserId): array {
        $accessTokenPlain = 'at_' . bin2hex(random_bytes(32));
        $refreshTokenPlain = 'rt_' . bin2hex(random_bytes(32));
        $linkId = self::uuid();
        $now = date('c');
        $expiresAt = date('c', time() + self::ACCESS_TOKEN_TTL);

        $links = self::loadLinks();

        // If this Amazon user already linked, update existing row (one link per Amazon user).
        $existingIdx = -1;
        if ($amazonUserId !== null && $amazonUserId !== '') {
            foreach ($links as $i => $l) {
                if (($l['amazonUserId'] ?? '') === $amazonUserId) { $existingIdx = $i; break; }
            }
        }

        $row = [
            'id' => $linkId,
            'amazonUserId' => $amazonUserId ?? '',
            'openplanUserId' => $consumedCodeRow['openplanUserId'],
            'wrappedDataKey' => $consumedCodeRow['wrappedDataKey'],
            'wrapIv' => $consumedCodeRow['wrapIv'],
            'wrapTag' => $consumedCodeRow['wrapTag'],
            'accessTokenHash' => hash(self::TOKEN_HASH_ALGO, $accessTokenPlain),
            'accessTokenLast4' => substr($accessTokenPlain, -4),
            'refreshTokenHash' => hash(self::TOKEN_HASH_ALGO, $refreshTokenPlain),
            'tokenExpiresAt' => $expiresAt,
            'createdAt' => $existingIdx === -1 ? $now : ($links[$existingIdx]['createdAt'] ?? $now),
            'lastUsedAt' => $now,
            'revoked' => false
        ];

        if ($existingIdx === -1) {
            $links[] = $row;
        } else {
            $links[$existingIdx] = $row;
        }
        self::saveLinks($links);

        return [
            'access_token' => $accessTokenPlain,
            'token_type' => 'bearer',
            'expires_in' => self::ACCESS_TOKEN_TTL,
            'refresh_token' => $refreshTokenPlain
        ];
    }

    public static function refreshAccessToken(string $refreshTokenPlain): ?array {
        $hash = hash(self::TOKEN_HASH_ALGO, $refreshTokenPlain);
        $links = self::loadLinks();
        $matchIdx = -1;
        foreach ($links as $i => $l) {
            if (!empty($l['revoked'])) continue;
            if (($l['refreshTokenHash'] ?? '') === $hash) { $matchIdx = $i; break; }
        }
        if ($matchIdx === -1) return null;

        $newAccess = 'at_' . bin2hex(random_bytes(32));
        $newRefresh = 'rt_' . bin2hex(random_bytes(32));
        $links[$matchIdx]['accessTokenHash'] = hash(self::TOKEN_HASH_ALGO, $newAccess);
        $links[$matchIdx]['accessTokenLast4'] = substr($newAccess, -4);
        $links[$matchIdx]['refreshTokenHash'] = hash(self::TOKEN_HASH_ALGO, $newRefresh);
        $links[$matchIdx]['tokenExpiresAt'] = date('c', time() + self::ACCESS_TOKEN_TTL);
        $links[$matchIdx]['lastUsedAt'] = date('c');
        self::saveLinks($links);

        return [
            'access_token' => $newAccess,
            'token_type' => 'bearer',
            'expires_in' => self::ACCESS_TOKEN_TTL,
            'refresh_token' => $newRefresh
        ];
    }

    public static function resolveAccessToken(string $accessTokenPlain): ?array {
        $hash = hash(self::TOKEN_HASH_ALGO, $accessTokenPlain);
        $links = self::loadLinks();
        foreach ($links as $l) {
            if (!empty($l['revoked'])) continue;
            if (($l['accessTokenHash'] ?? '') !== $hash) continue;
            $exp = strtotime((string)($l['tokenExpiresAt'] ?? '0'));
            if ($exp !== false && $exp < time()) continue;
            return $l;
        }
        return null;
    }

    public static function touchLastUsed(string $linkId): void {
        if ($linkId === '') return;
        $links = self::loadLinks();
        foreach ($links as &$l) {
            if (($l['id'] ?? '') === $linkId) {
                $l['lastUsedAt'] = date('c');
                self::saveLinks($links);
                return;
            }
        }
    }

    public static function revokeByOpenplanUser(string $openplanUserId): int {
        $links = self::loadLinks();
        $count = 0;
        foreach ($links as &$l) {
            if (($l['openplanUserId'] ?? '') === $openplanUserId && empty($l['revoked'])) {
                $l['revoked'] = true;
                $l['revokedAt'] = date('c');
                $count++;
            }
        }
        if ($count > 0) self::saveLinks($links);
        return $count;
    }

    public static function listLinksForUser(string $openplanUserId): array {
        $out = [];
        foreach (self::loadLinks() as $l) {
            if (($l['openplanUserId'] ?? '') !== $openplanUserId) continue;
            if (!empty($l['revoked'])) continue;
            $out[] = [
                'id' => $l['id'] ?? '',
                'amazonUserIdMasked' => self::maskId($l['amazonUserId'] ?? ''),
                'accessTokenLast4' => $l['accessTokenLast4'] ?? '',
                'createdAt' => $l['createdAt'] ?? '',
                'lastUsedAt' => $l['lastUsedAt'] ?? ''
            ];
        }
        return $out;
    }

    // ─────────────────────────────────────────────────────────────────────
    // Storage helpers (file-based + flock)
    // ─────────────────────────────────────────────────────────────────────

    private static function linksPath(): string {
        return DATA_PATH . '/alexa_links.json';
    }

    private static function codesPath(): string {
        return DATA_PATH . '/alexa_auth_codes.json';
    }

    private static function loadLinks(): array {
        return self::loadJsonArray(self::linksPath());
    }

    private static function saveLinks(array $links): void {
        self::saveJsonArray(self::linksPath(), $links);
    }

    private static function loadCodes(): array {
        return self::loadJsonArray(self::codesPath());
    }

    private static function saveCodes(array $codes): void {
        self::saveJsonArray(self::codesPath(), $codes);
    }

    private static function loadJsonArray(string $path): array {
        if (!file_exists($path)) return [];
        $contents = @file_get_contents($path);
        if ($contents === false || $contents === '') return [];
        $arr = json_decode($contents, true);
        return is_array($arr) ? $arr : [];
    }

    private static function saveJsonArray(string $path, array $data): void {
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($json === false) throw new Exception('Encode failed');
        $tmp = $path . '.tmp';
        $fp = fopen($tmp, 'w');
        if ($fp === false) throw new Exception('Cannot open temp file');
        if (!flock($fp, LOCK_EX)) { fclose($fp); throw new Exception('Cannot lock'); }
        fwrite($fp, $json);
        fflush($fp);
        flock($fp, LOCK_UN);
        fclose($fp);
        rename($tmp, $path);
        @chmod($path, 0600);
    }

    private static function pruneExpiredCodes(array $codes): array {
        $now = time();
        return array_values(array_filter($codes, function($c) use ($now) {
            if (!empty($c['consumed'])) return false;
            return strtotime((string)($c['expiresAt'] ?? '0')) >= $now;
        }));
    }

    private static function uuid(): string {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }

    private static function maskId(string $id): string {
        $len = strlen($id);
        if ($len <= 8) return str_repeat('*', $len);
        return substr($id, 0, 4) . str_repeat('*', $len - 8) . substr($id, -4);
    }
}
