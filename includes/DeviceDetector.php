<?php

class DeviceDetector {
    private const COOKIE_NAME = 'lazyman_device_preference';
    private const COOKIE_TTL = 2592000;

    public static function shouldShowMobile(): bool {
        if (PHP_SAPI === 'cli') {
            return false;
        }

        $requestPath = strtolower((string)(parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?? ''));
        $scriptName = strtolower((string)($_SERVER['SCRIPT_NAME'] ?? ''));
        if (
            preg_match('#/mobile(/|$)#', $requestPath) === 1 ||
            preg_match('#/mobile(/|$)#', $scriptName) === 1
        ) {
            // Removed: self::setPreferenceCookie('mobile'); - prevents sticky mobile view on PC
            return true;
        }

        $requested = strtolower(trim((string)($_GET['device'] ?? '')));
        if ($requested === 'mobile' || $requested === 'desktop') {
            self::setPreferenceCookie($requested);
            return $requested === 'mobile';
        }

        $cookiePref = strtolower(trim((string)($_COOKIE[self::COOKIE_NAME] ?? '')));
        if ($cookiePref === 'mobile') {
            return true;
        }
        if ($cookiePref === 'desktop') {
            return false;
        }

        $clientHint = strtolower(trim((string)($_SERVER['HTTP_SEC_CH_UA_MOBILE'] ?? '')));
        if ($clientHint === '?1' || $clientHint === '1') {
            return true;
        }
        if ($clientHint === '?0' || $clientHint === '0') {
            return false;
        }

        $ua = strtolower((string)($_SERVER['HTTP_USER_AGENT'] ?? ''));
        if ($ua === '') {
            return false;
        }

        if (str_contains($ua, 'macintosh') && str_contains($ua, 'mobile')) {
            return true;
        }

        return preg_match('/android|iphone|ipod|ipad|iemobile|blackberry|opera mini|mobile|webos/', $ua) === 1;
    }

    private static function setPreferenceCookie(string $value): void {
        if (headers_sent()) {
            return;
        }

        setcookie(self::COOKIE_NAME, $value, [
            'expires' => time() + self::COOKIE_TTL,
            'path' => '/',
            'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
            'httponly' => false,
            'samesite' => 'Lax'
        ]);
        $_COOKIE[self::COOKIE_NAME] = $value;
    }
}
