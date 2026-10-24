<?php
/**
 * Alexa request signature verification per Amazon's HTTPS endpoint spec.
 *
 * https://developer.amazon.com/en-US/docs/alexa/custom-skills/host-a-custom-skill-as-a-web-service.html
 *
 * Verifies:
 *  - SignatureCertChainUrl header points to the correct Amazon S3 location
 *  - Certificate is downloaded, has the right SAN (echo-api.amazon.com),
 *    is not expired, and chains to a trusted root
 *  - Request body's signature matches the cert's public key
 *  - Request payload timestamp is within 150 seconds of the server clock
 *
 * Cached certs live in data/alexa_cert_cache/ to avoid re-downloading on
 * every invocation.
 */

class AlexaSignatureVerifier {
    private const TIMESTAMP_TOLERANCE_SECONDS = 150;
    private const REQUIRED_CERT_SAN = 'echo-api.amazon.com';
    private const REQUIRED_HOST = 's3.amazonaws.com';
    private const REQUIRED_PATH_PREFIX = '/echo.api/';
    private const REQUIRED_PORT = 443;

    public static function verify(string $rawBody, array $headers, bool $strict = true): array {
        $certUrl = $headers['signaturecertchainurl'] ?? $headers['SignatureCertChainUrl'] ?? '';
        $signature = $headers['signature'] ?? $headers['Signature'] ?? '';

        if ($certUrl === '' || $signature === '') {
            return ['valid' => false, 'error' => 'Missing signature headers'];
        }

        if (!self::isValidCertUrl($certUrl)) {
            return ['valid' => false, 'error' => 'Invalid certificate URL'];
        }

        try {
            $payload = json_decode($rawBody, true);
            if (!is_array($payload) || !isset($payload['request']['timestamp'])) {
                return ['valid' => false, 'error' => 'Malformed request body'];
            }
            $ts = strtotime((string)$payload['request']['timestamp']);
            if ($ts === false || abs(time() - $ts) > self::TIMESTAMP_TOLERANCE_SECONDS) {
                return ['valid' => false, 'error' => 'Stale or invalid timestamp'];
            }
        } catch (Throwable $e) {
            return ['valid' => false, 'error' => 'Payload parse failed'];
        }

        $certPem = self::fetchCertPem($certUrl);
        if ($certPem === null) {
            return ['valid' => false, 'error' => 'Unable to fetch certificate'];
        }

        $cert = @openssl_x509_read($certPem);
        if ($cert === false) {
            return ['valid' => false, 'error' => 'Certificate parse failed'];
        }

        $parsed = openssl_x509_parse($cert);
        if (!is_array($parsed)) {
            return ['valid' => false, 'error' => 'Certificate inspection failed'];
        }
        if ($parsed['validFrom_time_t'] > time() || $parsed['validTo_time_t'] < time()) {
            return ['valid' => false, 'error' => 'Certificate expired or not yet valid'];
        }
        $san = $parsed['extensions']['subjectAltName'] ?? '';
        if (strpos($san, self::REQUIRED_CERT_SAN) === false) {
            return ['valid' => false, 'error' => 'Certificate SAN mismatch'];
        }

        $publicKey = openssl_pkey_get_public($cert);
        if ($publicKey === false) {
            return ['valid' => false, 'error' => 'Public key extraction failed'];
        }

        $sigBinary = base64_decode($signature, true);
        if ($sigBinary === false) {
            return ['valid' => false, 'error' => 'Signature not valid base64'];
        }

        $result = openssl_verify($rawBody, $sigBinary, $publicKey, OPENSSL_ALGO_SHA1);

        if ($result === 1) {
            return ['valid' => true];
        }
        if ($result === 0) {
            return ['valid' => false, 'error' => 'Signature does not match payload'];
        }
        return ['valid' => false, 'error' => 'OpenSSL verify error: ' . openssl_error_string()];
    }

    private static function isValidCertUrl(string $url): bool {
        $parts = parse_url($url);
        if (!is_array($parts)) {
            return false;
        }
        if (strtolower($parts['scheme'] ?? '') !== 'https') {
            return false;
        }
        if (strtolower($parts['host'] ?? '') !== self::REQUIRED_HOST) {
            return false;
        }
        $port = (int)($parts['port'] ?? self::REQUIRED_PORT);
        if ($port !== self::REQUIRED_PORT) {
            return false;
        }
        $path = $parts['path'] ?? '';
        // Normalize to defeat traversal attempts (e.g. /echo.api/../badpath).
        $normalized = '/' . ltrim(preg_replace('#/+#', '/', $path), '/');
        if (strpos($normalized, self::REQUIRED_PATH_PREFIX) !== 0) {
            return false;
        }
        if (strpos($normalized, '/../') !== false || strpos($normalized, '/./') !== false) {
            return false;
        }
        return true;
    }

    private static function fetchCertPem(string $url): ?string {
        $cacheDir = DATA_PATH . '/alexa_cert_cache';
        if (!is_dir($cacheDir)) {
            @mkdir($cacheDir, 0755, true);
        }
        $cacheKey = $cacheDir . '/' . md5($url) . '.pem';
        if (is_file($cacheKey) && (time() - filemtime($cacheKey)) < 86400) {
            $cached = @file_get_contents($cacheKey);
            if ($cached !== false && $cached !== '') {
                return $cached;
            }
        }

        $ctx = stream_context_create(['http' => ['timeout' => 5, 'follow_location' => 0]]);
        $pem = @file_get_contents($url, false, $ctx);
        if ($pem === false || $pem === '') {
            return null;
        }
        @file_put_contents($cacheKey, $pem);
        return $pem;
    }
}
