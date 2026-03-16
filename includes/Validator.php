<?php
/**
 * Validator Class - Comprehensive Input Validation
 *
 * Provides validation methods for:
 * - Email validation
 * - Password strength validation
 * - String validation (length, format)
 * - Numeric validation
 * - Date/time validation
 * - UUID validation
 * - Custom validation rules
 */

class Validator {
    /**
     * Validate email address
     */
    public static function email(string $email, bool $dnsCheck = false): array {
        if (empty($email)) {
            return ['valid' => false, 'error' => 'Email is required'];
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['valid' => false, 'error' => 'Invalid email format'];
        }

        if (strlen($email) > 254) {
            return ['valid' => false, 'error' => 'Email is too long'];
        }

        // DNS check for MX records (optional, can be slow)
        if ($dnsCheck) {
            $domain = substr(strrchr($email, '@'), 1);
            if (!checkdnsrr($domain, 'MX')) {
                return ['valid' => false, 'error' => 'Email domain does not have valid MX records'];
            }
        }

        return ['valid' => true, 'value' => $email];
    }

    /**
     * Check if email is from a disposable domain
     */
    public static function isDisposableEmail(string $email): bool {
        $domain = substr(strrchr($email, '@'), 1);
        if ($domain === false) {
            return false;
        }
        $domain = strtolower($domain);
        
        // Common disposable domains
        $blacklist = [
            'duck.com',
            'devlug.com',
            'temp-mail.org',
            'guerrillamail.com',
            'sharklasers.com',
            'yopmail.com',
            'mailinator.com',
            '10minutemail.com',
            'throwawaymail.com',
            'getnada.com',
            'dispostable.com',
            'fake-email.com',
            'tempmail.net',
            'mailnes.com',
            'maildrop.cc',
            'trashmail.com'
        ];

        return in_array($domain, $blacklist, true);
    }

    /**
     * Check if email uses plus addressing (e.g. user+tag@example.com)
     */
    public static function isPlusAddress(string $email): bool {
        $parts = explode('@', $email);
        if (count($parts) !== 2) {
            return false;
        }
        return strpos($parts[0], '+') !== false;
    }

    /**
     * Validate password strength
     * Returns array with score and requirements met
     */
    public static function passwordStrength(string $password): array {
        $score = 0;
        $requirements = [];
        $errors = [];

        // Length check
        $length = strlen($password);
        if ($length < 8) {
            $errors[] = 'Password must be at least 8 characters';
        } elseif ($length >= 16) {
            $score += 2;
            $requirements[] = 'length_excellent';
        } elseif ($length >= 12) {
            $score += 1;
            $requirements[] = 'length_good';
        } else {
            $requirements[] = 'length_minimum';
        }

        // Uppercase check
        if (preg_match('/[A-Z]/', $password)) {
            $score += 1;
            $requirements[] = 'uppercase';
        } else {
            $errors[] = 'Password must contain at least one uppercase letter';
        }

        // Lowercase check
        if (preg_match('/[a-z]/', $password)) {
            $score += 1;
            $requirements[] = 'lowercase';
        } else {
            $errors[] = 'Password must contain at least one lowercase letter';
        }

        // Number check
        if (preg_match('/[0-9]/', $password)) {
            $score += 1;
            $requirements[] = 'numbers';
        } else {
            $errors[] = 'Password must contain at least one number';
        }

        // Special character check
        if (preg_match('/[!@#$%^&*()_+\-=\[\]{};\'\\:"|,.<>\/?`~]/', $password)) {
            $score += 1;
            $requirements[] = 'special';
        } else {
            $errors[] = 'Password must contain at least one special character';
        }

        // Common patterns to avoid
        /* Temporarily disabled - can be re-enabled if stricter security needed
        $commonPatterns = [
            '/^[0-9]+$/',           // All numbers
            '/^[a-zA-Z]+$/',        // All letters
            '/(.)\1{2,}/',          // Same character 3+ times
            '/123/',                 // Sequential numbers
            '/abc/',
            '/qwerty/',
            '/password/',
            '/admin/',
        ];

        foreach ($commonPatterns as $pattern) {
            if (preg_match($pattern, strtolower($password))) {
                $score -= 1;
                $errors[] = 'Password contains common patterns';
                break;
            }
        }
        */

        return [
            'valid' => $length >= 8 && $score >= 4,
            'score' => max(0, $score),
            'maxScore' => 6,
            'strength' => $score >= 5 ? 'strong' : ($score >= 3 ? 'medium' : 'weak'),
            'requirements' => $requirements,
            'errors' => $errors,
            'suggestions' => self::getPasswordSuggestions($password)
        ];
    }

    /**
     * Get password improvement suggestions
     */
    private static function getPasswordSuggestions(string $password): array {
        $suggestions = [];

        if (strlen($password) < 12) {
            $suggestions[] = 'Consider using a longer password (12+ characters)';
        }

        if (!preg_match('/[!@#$%^&*()_+\-=\[\]{};\'\\:"|,.<>\/?`~]/', $password)) {
            $suggestions[] = 'Add special characters like !@#$%^&*';
        }

        if (!preg_match('/[0-9].*[0-9]/', $password)) {
            $suggestions[] = 'Consider using multiple numbers';
        }

        if (preg_match('/(.)\1{2,}/', $password)) {
            $suggestions[] = 'Avoid repeating the same character';
        }

        return $suggestions;
    }

    /**
     * Validate string length
     */
    public static function stringLength(string $value, int $min = 0, int $max = PHP_INT_MAX, string $field = 'Field'): array {
        $length = mb_strlen($value);

        if ($length < $min) {
            return ['valid' => false, 'error' => "{$field} must be at least {$min} characters"];
        }

        if ($length > $max) {
            return ['valid' => false, 'error' => "{$field} must be no more than {$max} characters"];
        }

        return ['valid' => true, 'value' => $value];
    }

    /**
     * Validate UUID format
     */
    public static function uuid(string $uuid): array {
        if (empty($uuid)) {
            return ['valid' => false, 'error' => 'ID is required'];
        }

        $pattern = '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i';

        if (!preg_match($pattern, $uuid)) {
            return ['valid' => false, 'error' => 'Invalid ID format'];
        }

        return ['valid' => true, 'value' => $uuid];
    }

    /**
     * Validate numeric value
     */
    public static function numeric($value, ?float $min = null, ?float $max = null, string $field = 'Value'): array {
        if (!is_numeric($value)) {
            return ['valid' => false, 'error' => "{$field} must be a number"];
        }

        $num = (float)$value;

        if ($min !== null && $num < $min) {
            return ['valid' => false, 'error' => "{$field} must be at least {$min}"];
        }

        if ($max !== null && $num > $max) {
            return ['valid' => false, 'error' => "{$field} must be no more than {$max}"];
        }

        return ['valid' => true, 'value' => $num];
    }

    /**
     * Validate integer value
     */
    public static function integer($value, ?int $min = null, ?int $max = null, string $field = 'Value'): array {
        if (!is_numeric($value) || strpos((string)$value, '.') !== false) {
            return ['valid' => false, 'error' => "{$field} must be an integer"];
        }

        $num = (int)$value;

        if ($min !== null && $num < $min) {
            return ['valid' => false, 'error' => "{$field} must be at least {$min}"];
        }

        if ($max !== null && $num > $max) {
            return ['valid' => false, 'error' => "{$field} must be no more than {$max}"];
        }

        return ['valid' => true, 'value' => $num];
    }

    /**
     * Validate date format
     */
    public static function date(string $date, string $format = 'Y-m-d', string $field = 'Date'): array {
        if (empty($date)) {
            return ['valid' => false, 'error' => "{$field} is required"];
        }

        $d = DateTime::createFromFormat($format, $date);
        if (!$d || $d->format($format) !== $date) {
            return ['valid' => false, 'error' => "{$field} must be in {$format} format"];
        }

        return ['valid' => true, 'value' => $date];
    }

    /**
     * Validate ISO8601 datetime
     */
    public static function datetime(string $datetime, string $field = 'DateTime'): array {
        if (empty($datetime)) {
            return ['valid' => false, 'error' => "{$field} is required"];
        }

        try {
            new DateTime($datetime);
        } catch (Exception $e) {
            return ['valid' => false, 'error' => "{$field} must be a valid ISO 8601 date"];
        }

        return ['valid' => true, 'value' => $datetime];
    }

    /**
     * Validate URL
     */
    public static function url(string $url, array $protocols = ['http', 'https']): array {
        if (empty($url)) {
            return ['valid' => false, 'error' => 'URL is required'];
        }

        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return ['valid' => false, 'error' => 'Invalid URL format'];
        }

        $scheme = parse_url($url, PHP_URL_SCHEME);
        if (!in_array($scheme, $protocols)) {
            return ['valid' => false, 'error' => 'URL must use ' . implode(' or ', $protocols) . ' protocol'];
        }

        return ['valid' => true, 'value' => $url];
    }

    /**
     * Validate phone number (basic format)
     */
    public static function phone(string $phone, string $country = 'US'): array {
        if (empty($phone)) {
            return ['valid' => false, 'error' => 'Phone number is required'];
        }

        // Remove common formatting characters
        $clean = preg_replace('/[\s\-\.\(\)]+/', '', $phone);

        // Basic validation - allow international formats
        if (!preg_match('/^\+?[0-9]{7,15}$/', $clean)) {
            return ['valid' => false, 'error' => 'Invalid phone number format'];
        }

        return ['valid' => true, 'value' => $phone];
    }

    /**
     * Validate SKU format
     */
    public static function sku(string $sku): array {
        if (empty($sku)) {
            return ['valid' => false, 'error' => 'SKU is required'];
        }

        if (!preg_match('/^[A-Z0-9]{3,20}(-[A-Z0-9]{2,10})?$/', strtoupper($sku))) {
            return ['valid' => false, 'error' => 'Invalid SKU format (e.g., ABC-1234)'];
        }

        return ['valid' => true, 'value' => strtoupper($sku)];
    }

    /**
     * Validate currency amount
     */
    public static function currency(float $amount, string $field = 'Amount'): array {
        if ($amount < 0) {
            return ['valid' => false, 'error' => "{$field} cannot be negative"];
        }

        if ($amount > 999999999.99) {
            return ['valid' => false, 'error' => "{$field} is too large"];
        }

        return ['valid' => true, 'value' => round($amount, 2)];
    }

    /**
     * Validate quantity
     */
    public static function quantity(int $quantity, int $min = 0, int $max = PHP_INT_MAX): array {
        if ($quantity < $min) {
            return ['valid' => false, 'error' => "Quantity must be at least {$min}"];
        }

        if ($quantity > $max) {
            return ['valid' => false, 'error' => "Quantity cannot exceed {$max}"];
        }

        return ['valid' => true, 'value' => $quantity];
    }

    /**
     * Validate enum value
     */
    public static function enum($value, array $allowed, string $field = 'Value'): array {
        if (!in_array($value, $allowed, true)) {
            return ['valid' => false, 'error' => "{$field} must be one of: " . implode(', ', $allowed)];
        }

        return ['valid' => true, 'value' => $value];
    }

    /**
     * Validate array of items
     */
    public static function arrayOf(array $items, string $itemType = 'items'): array {
        if (empty($items)) {
            return ['valid' => true, 'value' => []];
        }

        if (!is_array($items) || array_keys($items) !== range(0, count($items) - 1)) {
            return ['valid' => false, 'error' => "Must be a list of {$itemType}"];
        }

        return ['valid' => true, 'value' => $items];
    }

    /**
     * Sanitize string (remove dangerous characters)
     */
    public static function sanitize(string $value, string $encoding = 'UTF-8'): string {
        // Remove null bytes
        $value = str_replace("\0", '', $value);

        // Convert special HTML entities
        $value = htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, $encoding);

        // Remove control characters except newlines and tabs
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $value);

        return trim($value);
    }

    /**
     * Validate multiple fields at once
     */
    public static function validateAll(array $data, array $rules): array {
        $errors = [];
        $validated = [];

        foreach ($rules as $field => $ruleSet) {
            $value = $data[$field] ?? null;
            $rulesList = is_array($ruleSet) ? $ruleSet : [$ruleSet];

            foreach ($rulesList as $rule) {
                $result = self::applyRule($field, $value, $rule, $data);

                if (!$result['valid']) {
                    $errors[$field] = $result['error'];
                    continue 2;
                }
            }

            $validated[$field] = $value;
        }

        return [
            'valid' => empty($errors),
            'data' => $validated,
            'errors' => $errors
        ];
    }

    /**
     * Apply a single validation rule
     */
    private static function applyRule(string $field, $value, string $rule, array $context): array {
        $params = [];
        if (strpos($rule, ':') !== false) {
            [$rule, $paramStr] = explode(':', $rule, 2);
            $params = explode(',', $paramStr);
        }

        switch ($rule) {
            case 'required':
                if (empty($value) && $value !== 0 && $value !== '0') {
                    return ['valid' => false, 'error' => ucfirst($field) . ' is required'];
                }
                return ['valid' => true, 'value' => $value];

            case 'email':
                return self::email($value ?? '');

            case 'password':
                return self::passwordStrength($value ?? '');

            case 'min':
                return self::stringLength($value ?? '', (int)($params[0] ?? 0), PHP_INT_MAX, ucfirst($field));

            case 'max':
                return self::stringLength($value ?? '', 0, (int)($params[0] ?? 1000), ucfirst($field));

            case 'numeric':
                return self::numeric($value, isset($params[0]) ? (float)$params[0] : null, isset($params[1]) ? (float)$params[1] : null, ucfirst($field));

            case 'integer':
                return self::integer($value, isset($params[0]) ? (int)$params[0] : null, isset($params[1]) ? (int)$params[1] : null, ucfirst($field));

            case 'date':
                return self::date($value ?? '', $params[0] ?? 'Y-m-d', ucfirst($field));

            case 'uuid':
                return self::uuid($value ?? '');

            case 'enum':
                return self::enum($value, $params, ucfirst($field));

            case 'url':
                return self::url($value ?? '');

            case 'phone':
                return self::phone($value ?? '');

            case 'sku':
                return self::sku($value ?? '');

            default:
                return ['valid' => true, 'value' => $value];
        }
    }
}
