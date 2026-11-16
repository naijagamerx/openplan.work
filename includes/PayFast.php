<?php
/**
 * PayFast (South Africa, ZAR): card checkout, recurring billing, payment
 * notifications (ITN) and cancelling a card subscription.
 *
 * Configured only from the environment (.env), never from the database:
 *   PAYFAST_MERCHANT_ID, PAYFAST_MERCHANT_KEY
 *   PAYFAST_PASSPHRASE   required (PayFast needs it for subscriptions)
 *   PAYFAST_SANDBOX      on unless set to 0/false; set it to 0 for real payments
 *   PAYFAST_SITE_URL     optional public base URL for the return/notify links
 *                        (defaults to APP_URL, which comes from the Host header)
 *
 * PayFast signs three things with three different rules:
 *   - checkout form: non-empty fields in the documented field order,
 *     key=urlencode(trim(value)) joined by &, then &passphrase=..., MD5
 *   - notification (ITN): every posted field in the order received, up to
 *     "signature", empty values included, then &passphrase=..., MD5
 *   - REST API (cancel): headers and body sorted by key with the passphrase
 *     included in the sort, MD5
 */

final class PayFast {
    /** Checkout fields in the order PayFast signs them. */
    public const CHECKOUT_FIELD_ORDER = [
        'merchant_id', 'merchant_key', 'return_url', 'cancel_url', 'notify_url',
        'name_first', 'name_last', 'email_address', 'cell_number',
        'm_payment_id', 'amount', 'item_name', 'item_description',
        'custom_int1', 'custom_int2', 'custom_int3', 'custom_int4', 'custom_int5',
        'custom_str1', 'custom_str2', 'custom_str3', 'custom_str4', 'custom_str5',
        'email_confirmation', 'confirmation_address', 'payment_method',
        'subscription_type', 'billing_date', 'recurring_amount', 'frequency', 'cycles',
        'subscription_notify_email', 'subscription_notify_webhook', 'subscription_notify_buyer',
    ];

    /** Plan length in days => [PayFast frequency code, period to extend access by]. */
    private const FREQUENCIES = [
        30 => [3, '+1 month'], 31 => [3, '+1 month'],
        90 => [4, '+3 months'], 91 => [4, '+3 months'],
        180 => [5, '+6 months'], 182 => [5, '+6 months'], 183 => [5, '+6 months'],
        365 => [6, '+1 year'], 366 => [6, '+1 year'],
    ];

    /** Smallest amount PayFast accepts for a subscription charge (ZAR). */
    public const MIN_AMOUNT = 5.00;

    /** Extra days of access on a new card subscription, so a renewal that lands a day late does not cut access. */
    public const GRACE_DAYS = 3;

    private const NOTIFY_HOSTS = ['www.payfast.co.za', 'w1w.payfast.co.za', 'w2w.payfast.co.za', 'sandbox.payfast.co.za'];

    /** Notification source ranges published in PayFast's developer docs (used alongside the DNS lookups). */
    private const NOTIFY_CIDRS = ['197.97.145.144/28', '41.74.179.192/27', '102.216.36.0/28', '102.216.36.128/28', '144.126.193.139/32'];

    public static function config(): array {
        // Not "?:" here: the string "0" is falsy in PHP and would turn sandbox back on.
        $sandboxEnv = getenv('PAYFAST_SANDBOX');
        $sandboxRaw = strtolower(trim($sandboxEnv === false || trim((string)$sandboxEnv) === '' ? '1' : (string)$sandboxEnv));
        $siteUrl = trim((string)(getenv('PAYFAST_SITE_URL') ?: ''));
        if ($siteUrl === '' && defined('APP_URL')) {
            $siteUrl = APP_URL;
        }
        return [
            'merchantId' => trim((string)(getenv('PAYFAST_MERCHANT_ID') ?: '')),
            'merchantKey' => trim((string)(getenv('PAYFAST_MERCHANT_KEY') ?: '')),
            'passphrase' => (string)(getenv('PAYFAST_PASSPHRASE') ?: ''),
            'sandbox' => !in_array($sandboxRaw, ['0', 'false', 'no', 'off'], true),
            'siteUrl' => rtrim($siteUrl, '/'),
        ];
    }

    public static function isConfigured(?array $config = null): bool {
        $config = $config ?? self::config();
        return $config['merchantId'] !== '' && $config['merchantKey'] !== '' && trim($config['passphrase']) !== '';
    }

    public static function processUrl(bool $sandbox): string {
        return $sandbox ? 'https://sandbox.payfast.co.za/eng/process' : 'https://www.payfast.co.za/eng/process';
    }

    public static function validateUrl(bool $sandbox): string {
        return $sandbox ? 'https://sandbox.payfast.co.za/eng/query/validate' : 'https://www.payfast.co.za/eng/query/validate';
    }

    /**
     * ZAR price PayFast should charge for a plan, or null when the plan cannot be paid by card:
     * the plan's own price when it is in ZAR, else its zarPrice. Below MIN_AMOUNT counts as none.
     */
    public static function zarAmountForPlan(array $plan): ?float {
        $amount = 0.0;
        if (strtoupper(trim((string)($plan['currency'] ?? ''))) === 'ZAR') {
            $amount = (float)($plan['price'] ?? 0);
        }
        if ($amount <= 0) {
            $amount = (float)($plan['zarPrice'] ?? 0);
        }
        $amount = round($amount, 2);
        return $amount >= self::MIN_AMOUNT ? $amount : null;
    }

    /** [frequency code, access period] for a plan length, or null when PayFast has no matching billing frequency. */
    public static function frequencyFor(int $durationDays): ?array {
        return self::FREQUENCIES[$durationDays] ?? null;
    }

    /**
     * Signature for a checkout form. Unknown field names are refused so nothing is sent unsigned.
     * Empty values AND "0" are left out of the signature (but still sent), exactly as PayFast's
     * official PHP SDK does (Auth::generateSignature uses !empty(); its changelog lists a fix
     * "for payload parameters with a zero value"). This matters for cycles=0 on subscriptions.
     */
    public static function checkoutSignature(array $fields, string $passphrase): string {
        $unknown = array_diff(array_keys($fields), self::CHECKOUT_FIELD_ORDER, ['signature']);
        if ($unknown !== []) {
            throw new InvalidArgumentException('Unknown PayFast field: ' . implode(', ', $unknown));
        }
        $pairs = [];
        foreach (self::CHECKOUT_FIELD_ORDER as $key) {
            if (!array_key_exists($key, $fields)) {
                continue;
            }
            $value = trim((string)$fields[$key]);
            if ($value === '' || $value === '0') {
                continue;
            }
            $pairs[] = $key . '=' . urlencode($value);
        }
        $string = implode('&', $pairs);
        if (trim($passphrase) !== '') {
            $string .= '&passphrase=' . urlencode(trim($passphrase));
        }
        return md5($string);
    }

    /** Checkout fields in signing order, empty ones removed, with the signature added. */
    public static function signedCheckoutFields(array $fields, string $passphrase): array {
        $signature = self::checkoutSignature($fields, $passphrase);
        $ordered = [];
        foreach (self::CHECKOUT_FIELD_ORDER as $key) {
            if (array_key_exists($key, $fields) && trim((string)$fields[$key]) !== '') {
                $ordered[$key] = trim((string)$fields[$key]);
            }
        }
        $ordered['signature'] = $signature;
        return $ordered;
    }

    /** The posted ITN fields as PayFast signed them: received order, up to "signature", empty values kept. */
    public static function itnParamString(array $post): string {
        $pairs = [];
        foreach ($post as $key => $value) {
            if ($key === 'signature') {
                break;
            }
            $pairs[] = $key . '=' . urlencode(is_scalar($value) ? (string)$value : '');
        }
        return implode('&', $pairs);
    }

    public static function itnSignatureValid(array $post, string $passphrase): bool {
        $given = $post['signature'] ?? '';
        if (!is_string($given) || $given === '') {
            return false;
        }
        $string = self::itnParamString($post);
        if (trim($passphrase) !== '') {
            $string .= '&passphrase=' . urlencode(trim($passphrase));
        }
        return hash_equals(md5($string), strtolower($given));
    }

    /**
     * Whether the notification came from PayFast. Checks REMOTE_ADDR. Only when
     * $trustProxy is on (PAYFAST_TRUST_PROXY=1: the site sits behind a proxy/CDN that
     * appends the caller's address) does it also accept the LAST X-Forwarded-For entry,
     * the one that proxy added; without a proxy the whole header is client-written.
     * Defence in depth only: the signature, PayFast's own confirmation and the amount
     * check are what gate activation.
     */
    public static function sourceIpTrusted(array $server, ?callable $resolve = null, ?bool $trustProxy = null): bool {
        $trustProxy = $trustProxy ?? in_array(strtolower(trim((string)getenv('PAYFAST_TRUST_PROXY'))), ['1', 'true', 'yes', 'on'], true);
        $candidates = [];
        if (!empty($server['REMOTE_ADDR'])) {
            $candidates[] = trim((string)$server['REMOTE_ADDR']);
        }
        if ($trustProxy && !empty($server['HTTP_X_FORWARDED_FOR'])) {
            $parts = array_map('trim', explode(',', (string)$server['HTTP_X_FORWARDED_FOR']));
            $last = end($parts);
            if ($last !== false && $last !== '') {
                $candidates[] = $last;
            }
        }
        if ($candidates === []) {
            return false;
        }
        $resolve = $resolve ?? static fn(string $host) => gethostbynamel($host) ?: [];
        $known = [];
        foreach (self::NOTIFY_HOSTS as $host) {
            foreach ($resolve($host) as $ip) {
                $known[$ip] = true;
            }
        }
        foreach ($candidates as $ip) {
            if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                continue;
            }
            if (isset($known[$ip])) {
                return true;
            }
            foreach (self::NOTIFY_CIDRS as $cidr) {
                if (self::ipv4InCidr($ip, $cidr)) {
                    return true;
                }
            }
        }
        return false;
    }

    public static function ipv4InCidr(string $ip, string $cidr): bool {
        [$subnet, $bits] = array_pad(explode('/', $cidr, 2), 2, '32');
        $ipLong = ip2long($ip);
        $subnetLong = ip2long($subnet);
        $bits = (int)$bits;
        if ($ipLong === false || $subnetLong === false || $bits < 0 || $bits > 32) {
            return false;
        }
        $mask = $bits === 0 ? 0 : (~0 << (32 - $bits)) & 0xFFFFFFFF;
        return ($ipLong & $mask) === ($subnetLong & $mask);
    }

    public static function amountMatches(float $expected, $gross): bool {
        if (!is_numeric($gross)) {
            return false;
        }
        return abs($expected - (float)$gross) <= 0.01;
    }

    /** Ask PayFast whether this notification is genuine (it answers VALID or INVALID). */
    public static function confirmWithPayFast(string $paramString, bool $sandbox): bool {
        if (!function_exists('curl_init')) {
            error_log('PayFast confirm: cURL is not available');
            return false;
        }
        $ch = curl_init(self::validateUrl($sandbox));
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $paramString,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_USERAGENT => 'OpenPlan-ITN',
        ]);
        $body = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);
        if ($body === false) {
            error_log('PayFast confirm failed: ' . $error);
            return false;
        }
        return trim((string)$body) === 'VALID';
    }

    /** Signature for the REST API: headers and body sorted by key, passphrase included in the sort. */
    public static function apiSignature(array $params, string $passphrase): string {
        if (trim($passphrase) !== '') {
            $params['passphrase'] = trim($passphrase);
        }
        ksort($params);
        $pairs = [];
        foreach ($params as $key => $value) {
            $pairs[] = $key . '=' . urlencode((string)$value);
        }
        return md5(implode('&', $pairs));
    }

    /**
     * Cancel a card subscription at PayFast so it stops charging.
     * Returns ['ok' => bool, 'status' => int, 'message' => string].
     */
    public static function cancelSubscription(string $token, ?array $config = null): array {
        $config = $config ?? self::config();
        if (!self::isConfigured($config)) {
            return ['ok' => false, 'status' => 0, 'message' => 'Card payments are not configured'];
        }
        if (!preg_match('/^[A-Za-z0-9-]{8,64}$/', $token)) {
            return ['ok' => false, 'status' => 0, 'message' => 'Invalid subscription token'];
        }
        if (!function_exists('curl_init')) {
            return ['ok' => false, 'status' => 0, 'message' => 'cURL is not available'];
        }
        $timestamp = date('Y-m-d\TH:i:sP');
        $headers = [
            'merchant-id' => $config['merchantId'],
            'version' => 'v1',
            'timestamp' => $timestamp,
        ];
        $signature = self::apiSignature($headers, $config['passphrase']);
        $url = 'https://api.payfast.co.za/subscriptions/' . rawurlencode($token) . '/cancel' . ($config['sandbox'] ? '?testing=true' : '');
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => 'PUT',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPHEADER => [
                'merchant-id: ' . $headers['merchant-id'],
                'version: v1',
                'timestamp: ' . $timestamp,
                'signature: ' . $signature,
                'Content-Length: 0',
            ],
        ]);
        $body = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        if ($body === false) {
            return ['ok' => false, 'status' => 0, 'message' => 'Could not reach PayFast: ' . $error];
        }
        $decoded = json_decode((string)$body, true);
        $ok = $status >= 200 && $status < 300
            && (!is_array($decoded) || !isset($decoded['status']) || in_array(strtolower((string)$decoded['status']), ['success', 'ok'], true));
        $message = is_array($decoded) ? (string)($decoded['data']['message'] ?? $decoded['data']['response'] ?? $decoded['status'] ?? '') : '';
        return ['ok' => $ok, 'status' => $status, 'message' => $message];
    }
}
