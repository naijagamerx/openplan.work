<?php
/**
 * Auth Class - Authentication and session management
 */

class Auth {
    private Database $db;
    private const USER_KEY_CHECK_COLLECTION = 'key_check';
    public const ROLE_ADMIN = 'admin';
    public const ROLE_USER = 'user';

    private const SESSION_TIMEOUT_20M = '20m';
    private const SESSION_TIMEOUT_1H = '1h';
    private const SESSION_TIMEOUT_INDEFINITE = 'indefinite';
    private const SESSION_TIMEOUT_DEFAULT = self::SESSION_TIMEOUT_1H;
    private const PERSISTENT_AUTH_COOKIE = 'lazyman_auth_token';
    private const SESSION_ACTIVITY_TOUCH_INTERVAL = 60;
    private const EMAIL_VERIFICATION_TTL = 86400;
    private const PASSWORD_RESET_TTL = 3600;
    private const RESEND_VERIFICATION_COOLDOWN = 60;
    
    private static ?string $mcpUserId = null;

    public function __construct(Database $db) {
        $this->db = $db;
    }

    /**
     * Attempt login
     */
    public function login(string $email, string $password, bool $rememberMe = false): array {
        try {
            $users = $this->db->load('users', true);
        } catch (Exception $e) {
            return ['success' => false, 'error' => 'Unable to load user registry'];
        }

        foreach ($users as &$user) {
            if (($user['email'] ?? '') !== $email) {
                continue;
            }

            if (!Encryption::verifyPassword($password, $user['passwordHash'] ?? '')) {
                return ['success' => false, 'error' => 'Invalid password'];
            }

            if (!$this->validateUserMasterPassword((string)($user['id'] ?? ''))) {
                return ['success' => false, 'error' => 'Invalid master password'];
            }

            $timeoutPreference = self::normalizeSessionTimeoutPreference($user['sessionTimeoutPreference'] ?? null);
            $now = time();

            // Keep stored preference normalized and clear persistent tokens for timed sessions.
            $updates = [
                'lastLogin' => date('c'),
                'sessionTimeoutPreference' => $timeoutPreference
            ];
            if ($timeoutPreference !== self::SESSION_TIMEOUT_INDEFINITE) {
                $updates['authTokens'] = [];
                $user['authTokens'] = [];
            }
            $this->db->update('users', $user['id'], $updates);

            // Set session
            $_SESSION[SESSION_USER_ID] = $user['id'];
            $_SESSION[SESSION_USER_EMAIL] = $user['email'];
            $_SESSION[SESSION_USER_NAME] = $user['name'];
            $_SESSION['user_role'] = self::normalizeRole($user['role'] ?? null);
            $_SESSION[SESSION_LOGIN_TIME] = $now;
            $_SESSION['session_timeout_preference'] = $timeoutPreference;
            $_SESSION['last_activity_time'] = $now;
            $_SESSION['last_activity_touch'] = $now;
            $_SESSION[SESSION_REMEMBER_ME] = ($timeoutPreference === self::SESSION_TIMEOUT_INDEFINITE);
            $_SESSION['auth_verification_required'] = self::requiresEmailVerification($user);

            // Generate session fingerprint for security
            $_SESSION['fingerprint'] = self::generateSessionFingerprint();

            // Regenerate session ID
            if (session_status() === PHP_SESSION_ACTIVE && !headers_sent()) {
                session_regenerate_id(true);
            }

            // Persistent auth token is controlled by timeout preference.
            if ($timeoutPreference === self::SESSION_TIMEOUT_INDEFINITE) {
                $token = $this->generateAuthToken($user);
                $_SESSION['auth_token'] = $token;
                $this->setPersistentAuthCookie($token);
            } else {
                unset($_SESSION['auth_token']);
                $this->clearPersistentAuthCookie();
            }

            $verificationDispatch = $this->maybeResendVerificationOnLogin($user);
            $verificationNotice = self::verificationDispatchNotice($verificationDispatch);
            if ($verificationNotice !== null) {
                $_SESSION['verification_notice'] = $verificationNotice;
            } else {
                unset($_SESSION['verification_notice']);
            }

            return [
                'success' => true,
                'user' => $user,
                'verification_dispatch' => $verificationDispatch,
                'verification_notice' => $verificationNotice
            ];
        }

        return ['success' => false, 'error' => 'User not found'];
    }

    /**
     * Generate a secure authentication token
     */
    private function generateAuthToken(array &$user): string {
        $token = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $token);

        if (!isset($user['authTokens']) || !is_array($user['authTokens'])) {
            $user['authTokens'] = [];
        }

        $user['authTokens'][] = [
            'hash' => $tokenHash,
            'createdAt' => date('c'),
            // Null means it only expires on explicit logout/token revocation.
            'expiresAt' => null,
            'lastUsed' => date('c')
        ];

        if (count($user['authTokens']) > 5) {
            $user['authTokens'] = array_slice($user['authTokens'], -5);
        }

        $this->db->update('users', $user['id'], [
            'authTokens' => $user['authTokens']
        ]);

        return $token;
    }

    /**
     * Verify and restore session from auth token
     */
    public function restoreSessionFromToken(string $token): bool {
        try {
            $users = $this->db->load('users', true);
        } catch (Exception $e) {
            return false;
        }

        $tokenHash = hash('sha256', $token);

        foreach ($users as &$user) {
            if (!isset($user['authTokens']) || !is_array($user['authTokens'])) {
                continue;
            }

            foreach ($user['authTokens'] as &$authToken) {
                $hashMatches = ($authToken['hash'] ?? '') === $tokenHash;
                if (!$hashMatches) {
                    continue;
                }

                $expiresAt = $authToken['expiresAt'] ?? null;
                $isExpired = !empty($expiresAt) && strtotime((string)$expiresAt) <= time();
                if ($isExpired) {
                    continue;
                }

                $timeoutPreference = self::normalizeSessionTimeoutPreference($user['sessionTimeoutPreference'] ?? null);
                if ($timeoutPreference !== self::SESSION_TIMEOUT_INDEFINITE) {
                    // Timed sessions should not restore from persistent token.
                    $this->db->update('users', $user['id'], ['authTokens' => []]);
                    $this->clearPersistentAuthCookie();
                    return false;
                }

                $now = time();

                // Restore session
                $_SESSION[SESSION_USER_ID] = $user['id'];
                $_SESSION[SESSION_USER_EMAIL] = $user['email'];
                $_SESSION[SESSION_USER_NAME] = $user['name'];
                $_SESSION['user_role'] = self::normalizeRole($user['role'] ?? null);
                $_SESSION[SESSION_LOGIN_TIME] = $now;
                $_SESSION[SESSION_REMEMBER_ME] = true;
                $_SESSION['auth_token'] = $token;
                $_SESSION['session_timeout_preference'] = $timeoutPreference;
                $_SESSION['last_activity_time'] = $now;
                $_SESSION['last_activity_touch'] = $now;
                $_SESSION['auth_verification_required'] = self::requiresEmailVerification($user);

                // Generate session fingerprint for security
                $_SESSION['fingerprint'] = self::generateSessionFingerprint();

                // Update last used timestamp
                $authToken['lastUsed'] = date('c');
                $this->db->update('users', $user['id'], [
                    'authTokens' => $user['authTokens']
                ]);

                // Regenerate session ID
                if (session_status() === PHP_SESSION_ACTIVE && !headers_sent()) {
                    session_regenerate_id(true);
                }

                return true;
            }
        }

        return false;
    }

    /**
     * Revoke all auth tokens for a user (logout)
     */
    public function revokeAllTokens(string $userId): bool {
        return $this->db->update('users', $userId, [
            'authTokens' => []
        ]);
    }

    /**
     * Update a user's session timeout preference.
     */
    public function updateSessionTimeoutPreference(string $userId, ?string $preference): bool {
        $normalized = self::normalizeSessionTimeoutPreference($preference);

        try {
            $users = $this->db->load('users', true);
        } catch (Exception $e) {
            return false;
        }

        $targetFound = false;
        foreach ($users as &$user) {
            if (($user['id'] ?? '') !== $userId) {
                continue;
            }

            $user['sessionTimeoutPreference'] = $normalized;
            if ($normalized !== self::SESSION_TIMEOUT_INDEFINITE) {
                $user['authTokens'] = [];
            }
            $targetFound = true;
            break;
        }

        if (!$targetFound) {
            return false;
        }

        if (!$this->db->save('users', $users)) {
            return false;
        }

        // Apply immediately for current session.
        if (($userId === ($_SESSION[SESSION_USER_ID] ?? ''))) {
            $now = time();
            $_SESSION['session_timeout_preference'] = $normalized;
            $_SESSION['last_activity_time'] = $now;
            $_SESSION['last_activity_touch'] = $now;
            $_SESSION[SESSION_REMEMBER_ME] = ($normalized === self::SESSION_TIMEOUT_INDEFINITE);

            if ($normalized === self::SESSION_TIMEOUT_INDEFINITE) {
                $currentToken = $_SESSION['auth_token'] ?? '';
                if (!is_string($currentToken) || $currentToken === '') {
                    foreach ($users as &$currentUser) {
                        if (($currentUser['id'] ?? '') === $userId) {
                            $currentToken = $this->generateAuthToken($currentUser);
                            break;
                        }
                    }
                }

                if (is_string($currentToken) && $currentToken !== '') {
                    $_SESSION['auth_token'] = $currentToken;
                    $this->setPersistentAuthCookie($currentToken);
                }
            } else {
                unset($_SESSION['auth_token']);
                $this->clearPersistentAuthCookie();
            }
        }

        return true;
    }

    /**
     * Logout
     */
    public function logout(): void {
        // Get user ID before clearing session
        $userId = $_SESSION[SESSION_USER_ID] ?? null;

        // Revoke all auth tokens if user ID is available
        if (is_string($userId) && $userId !== '') {
            try {
                $this->revokeAllTokens($userId);
            } catch (Exception $e) {
                // Token revocation failed, continue with logout
            }
        }

        self::destroyCurrentSession(true);
    }

    /**
     * Register a new user
     */
    public function register(string $email, string $password, string $name, string $role = self::ROLE_USER): array {
        try {
            $users = $this->db->load('users', true);
        } catch (Exception $e) {
            $this->logRegistrationEvent($email, false, null, 'Unable to load user registry');
            return ['success' => false, 'error' => 'Unable to load user registry'];
        }

        // Check if email exists
        foreach ($users as $user) {
            if (($user['email'] ?? '') === $email) {
                $this->logRegistrationEvent($email, false, null, 'Email already registered');
                return ['success' => false, 'error' => 'Email already registered'];
            }
        }

        // Create user
        $newUser = [
            'id' => $this->db->generateId(),
            'email' => $email,
            'passwordHash' => Encryption::hashPassword($password),
            'name' => $name,
            'role' => self::normalizeRole($role),
            'createdAt' => date('c'),
            'lastLogin' => null,
            'sessionTimeoutPreference' => self::SESSION_TIMEOUT_DEFAULT,
            'authTokens' => [],
            'emailVerifiedAt' => isEmailVerificationEnabled() ? null : date('c'),
            'emailVerificationTokenHash' => null,
            'emailVerificationExpiresAt' => null,
            'lastVerificationSentAt' => null,
            'passwordResetTokenHash' => null,
            'passwordResetExpiresAt' => null
        ];

        $users[] = $newUser;

        if ($this->db->save('users', $users)) {
            // Create user data directory
            $userDir = DATA_PATH . '/users/' . $newUser['id'];
            if (!file_exists($userDir)) {
                if (!mkdir($userDir, 0755, true)) {
                    // Rollback user creation if directory fails
                    $this->db->delete('users', $newUser['id']);
                    $this->logRegistrationEvent($email, false, $newUser['id'], 'Failed to create user directory');
                    return ['success' => false, 'error' => 'Failed to create user directory'];
                }
            }

            if (!$this->initializeUserKeyVerifier($newUser['id'])) {
                $this->db->delete('users', $newUser['id']);
                $this->logRegistrationEvent($email, false, $newUser['id'], 'Failed to initialize user encryption');
                return ['success' => false, 'error' => 'Failed to initialize user encryption'];
            }
             
            $this->logRegistrationEvent($email, true, $newUser['id'], null);
            return ['success' => true, 'user' => $newUser];
        }

        $this->logRegistrationEvent($email, false, null, 'Failed to create user');
        return ['success' => false, 'error' => 'Failed to create user'];
    }

    /**
     * Delete user account and all associated data
     */
    public function deleteAccount(string $userId): array {
        // 1. Validate user exists
        $user = $this->db->findById('users', $userId);
        if (!$user) {
            return ['success' => false, 'error' => 'User not found'];
        }

        if (self::normalizeRole($user['role'] ?? null) === self::ROLE_ADMIN && self::isLastAdmin($userId)) {
            return ['success' => false, 'error' => 'You cannot delete the last administrator account'];
        }
        
        // 2. Delete user folder and contents
        $userDir = DATA_PATH . '/users/' . $userId;
        if (is_dir($userDir)) {
            if (!$this->recursiveDelete($userDir)) {
                return ['success' => false, 'error' => 'Failed to delete user data'];
            }
        }
        
        // 3. Delete user from users collection
        if (!$this->db->delete('users', $userId)) {
            return ['success' => false, 'error' => 'Failed to remove user account'];
        }
        
        // 4. Logout if it's the current user
        if (isset($_SESSION[SESSION_USER_ID]) && $_SESSION[SESSION_USER_ID] === $userId) {
            $this->logout();
        }
        
        return ['success' => true];
    }
    
    /**
     * Delete user data but keep account
     */
    public function deleteUserData(string $userId): array {
        $userDir = DATA_PATH . '/users/' . $userId;
        if (!is_dir($userDir)) {
            return ['success' => true, 'message' => 'No data to delete'];
        }
        
        // Delete all files in user directory but keep the directory
        $files = glob($userDir . '/*');
        foreach ($files as $file) {
            if (is_file($file)) {
                if (basename($file) === self::USER_KEY_CHECK_COLLECTION . '.json.enc') {
                    continue;
                }
                unlink($file);
            }
        }
        
        return ['success' => true];
    }

    public function getUserByEmail(string $email): ?array {
        $users = $this->db->load('users', true);

        foreach ($users as $user) {
            if (strcasecmp((string)($user['email'] ?? ''), $email) === 0) {
                return $user;
            }
        }

        return null;
    }

    public function getUserById(string $userId): ?array {
        $users = $this->db->load('users', true);

        foreach ($users as $user) {
            if (($user['id'] ?? '') === $userId) {
                return $user;
            }
        }

        return null;
    }

    public function issueEmailVerification(string $userId, bool $ignoreCooldown = false): array {
        if (!isEmailVerificationEnabled()) {
            return ['success' => false, 'error' => 'Email verification is disabled'];
        }

        $users = $this->db->load('users', true);
        $updated = false;
        $plainToken = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $plainToken);
        $now = time();

        foreach ($users as &$user) {
            if (($user['id'] ?? '') !== $userId) {
                continue;
            }

            if (!empty($user['emailVerifiedAt'])) {
                return ['success' => true, 'already_verified' => true, 'user' => $user];
            }

            $lastSentAt = strtotime((string)($user['lastVerificationSentAt'] ?? ''));
            if (!$ignoreCooldown && $lastSentAt !== false && ($now - $lastSentAt) < self::RESEND_VERIFICATION_COOLDOWN) {
                return [
                    'success' => false,
                    'error' => 'Please wait before requesting another verification email',
                    'retry_after' => self::RESEND_VERIFICATION_COOLDOWN - ($now - $lastSentAt)
                ];
            }

            $user['emailVerificationTokenHash'] = $tokenHash;
            $user['emailVerificationExpiresAt'] = date('c', $now + self::EMAIL_VERIFICATION_TTL);
            $user['lastVerificationSentAt'] = date('c', $now);
            $updated = true;

            return [
                'success' => true,
                'token' => $plainToken,
                'user' => $user,
                'users' => $users,
                'updated' => $updated
            ];
        }

        return ['success' => false, 'error' => 'User not found'];
    }

    public function persistUsers(array $users): bool {
        return $this->db->save('users', $users);
    }

    public function maybeResendVerificationOnLogin(array $user): array {
        if (!self::requiresEmailVerification($user)) {
            return ['attempted' => false, 'status' => 'not_needed'];
        }

        $result = $this->issueEmailVerification((string)($user['id'] ?? ''));
        if (!empty($result['success']) && !empty($result['updated']) && !empty($result['users'])) {
            $persisted = $this->persistUsers($result['users']);
            if (!$persisted) {
                return ['attempted' => true, 'status' => 'persist_failed'];
            }

            $mailer = new Mailer($this->getUserMailerConfig((string)($user['id'] ?? '')));
            $sent = $mailer->sendVerificationEmail($result['user'], $result['token']);
            return ['attempted' => true, 'status' => $sent ? 'resent' : 'delivery_failed'];
        }

        if (!empty($result['already_verified'])) {
            return ['attempted' => false, 'status' => 'already_verified'];
        }

        if (!empty($result['retry_after'])) {
            return ['attempted' => true, 'status' => 'cooldown', 'retry_after' => $result['retry_after']];
        }

        return ['attempted' => true, 'status' => 'not_sent', 'error' => $result['error'] ?? null];
    }

    public static function verificationDispatchNotice(array $result): ?string {
        return match ($result['status'] ?? null) {
            'resent' => 'A new verification email has been sent.',
            'delivery_failed' => 'Verification email was prepared, but delivery could not be confirmed.',
            'cooldown' => 'A verification email was already sent recently. Please check your inbox.',
            'persist_failed' => 'Verification email could not be prepared right now.',
            'not_sent' => 'Verification email could not be sent right now.',
            default => null
        };
    }

    public function verifyEmailToken(string $token): array {
        if ($token === '') {
            return ['success' => false, 'error' => 'Verification token is required'];
        }

        $users = $this->db->load('users', true);
        $tokenHash = hash('sha256', $token);
        $now = time();

        foreach ($users as &$user) {
            if (($user['emailVerificationTokenHash'] ?? '') !== $tokenHash) {
                continue;
            }

            $expiresAt = strtotime((string)($user['emailVerificationExpiresAt'] ?? ''));
            if ($expiresAt === false || $expiresAt <= $now) {
                return ['success' => false, 'error' => 'This verification link has expired'];
            }

            $user['emailVerifiedAt'] = date('c', $now);
            $user['emailVerificationTokenHash'] = null;
            $user['emailVerificationExpiresAt'] = null;

            if (!$this->db->save('users', $users)) {
                return ['success' => false, 'error' => 'Unable to verify email right now'];
            }

            if (($user['id'] ?? '') === ($_SESSION[SESSION_USER_ID] ?? '')) {
                $_SESSION['auth_verification_required'] = false;
            }

            return ['success' => true, 'user' => $user];
        }

        return ['success' => false, 'error' => 'This verification link is invalid or has already been used'];
    }

    public function issuePasswordReset(string $email): ?array {
        if (!isPasswordResetEnabled()) {
            return null;
        }

        $users = $this->db->load('users', true);
        $plainToken = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $plainToken);
        $expiresAt = date('c', time() + self::PASSWORD_RESET_TTL);

        foreach ($users as &$user) {
            if (strcasecmp((string)($user['email'] ?? ''), $email) !== 0) {
                continue;
            }

            $user['passwordResetTokenHash'] = $tokenHash;
            $user['passwordResetExpiresAt'] = $expiresAt;

            if (!$this->db->save('users', $users)) {
                return null;
            }

            return ['token' => $plainToken, 'user' => $user];
        }

        return null;
    }

    public function isValidPasswordResetToken(string $token): bool {
        return $this->getPasswordResetUser($token) !== null;
    }

    public function resetPasswordWithToken(string $token, string $newPassword): array {
        $users = $this->db->load('users', true);
        $tokenHash = hash('sha256', $token);
        $now = time();

        foreach ($users as &$user) {
            if (($user['passwordResetTokenHash'] ?? '') !== $tokenHash) {
                continue;
            }

            $expiresAt = strtotime((string)($user['passwordResetExpiresAt'] ?? ''));
            if ($expiresAt === false || $expiresAt <= $now) {
                return ['success' => false, 'error' => 'This reset link has expired'];
            }

            $user['passwordHash'] = Encryption::hashPassword($newPassword);
            $user['passwordResetTokenHash'] = null;
            $user['passwordResetExpiresAt'] = null;
            $user['authTokens'] = [];

            if (!$this->db->save('users', $users)) {
                return ['success' => false, 'error' => 'Unable to reset password right now'];
            }

            return ['success' => true, 'user' => $user];
        }

        return ['success' => false, 'error' => 'This reset link is invalid or has already been used'];
    }

    public static function requiresEmailVerification(array $user): bool {
        return isEmailVerificationEnabled() && empty($user['emailVerifiedAt']);
    }

    public static function shouldRestrictToVerification(): bool {
        return isEmailVerificationEnabled()
            && !empty($_SESSION[SESSION_USER_ID] ?? null)
            && !empty($_SESSION['auth_verification_required']);
    }

    public static function getPostLoginDestination(): string {
        return self::shouldRestrictToVerification() ? 'verification-required' : 'dashboard';
    }

    private function initializeUserKeyVerifier(string $userId): bool {
        $masterPassword = getMasterPassword();
        if ($masterPassword === '' || $userId === '') {
            return false;
        }

        try {
            $userDb = new Database($masterPassword, $userId);
            return $userDb->save(self::USER_KEY_CHECK_COLLECTION, [
                'id' => 'master-key-check',
                'version' => 1,
                'createdAt' => date('c')
            ]);
        } catch (Exception $e) {
            error_log('Failed to initialize key verifier: ' . $e->getMessage());
            return false;
        }
    }

    private function logRegistrationEvent(string $email, bool $success, ?string $userId, ?string $error): void {
        try {
            if (!class_exists('Audit')) {
                return;
            }
            $audit = new Audit($this->db);
            $audit->log($success ? Audit::EVENT_REGISTER : Audit::EVENT_REGISTER_FAILED, [
                'resource_type' => Audit::RESOURCE_USER,
                'resource_id' => $userId,
                'details' => [
                    'email' => $email,
                    'error' => $error
                ],
                'success' => $success
            ]);
        } catch (Exception $e) {
            error_log('Failed to log registration audit: ' . $e->getMessage());
        }
    }

    public function validateUserMasterPassword(string $userId, ?string $masterPassword = null): bool {
        $resolvedMasterPassword = $masterPassword ?? getMasterPassword();
        if ($resolvedMasterPassword === '' || $userId === '') {
            return false;
        }

        $userDataPath = DATA_PATH . '/users/' . $userId;

        try {
            $userDb = new Database($resolvedMasterPassword, $userId);
            $verifier = $userDb->load(self::USER_KEY_CHECK_COLLECTION, true);
            return !empty($verifier);
        } catch (Exception $e) {
            $encryptedFiles = glob($userDataPath . '/*.json.enc') ?: [];
            $nonVerifierFiles = array_values(array_filter($encryptedFiles, function($file) {
                return basename($file) !== self::USER_KEY_CHECK_COLLECTION . '.json.enc';
            }));

            if (empty($nonVerifierFiles)) {
                return $this->initializeUserKeyVerifier($userId);
            }

            try {
                $fallbackDb = new Database($resolvedMasterPassword, $userId);
                foreach ($nonVerifierFiles as $file) {
                    $collection = basename($file, '.json.enc');
                    $fallbackDb->load($collection, true);
                    return $this->initializeUserKeyVerifier($userId);
                }
            } catch (Exception $innerException) {
                return false;
            }

            return false;
        }
    }

    private function getUserMailerConfig(string $userId): array {
        $masterPassword = getMasterPassword();
        if ($masterPassword === '' || $userId === '') {
            return [];
        }

        try {
            $userDb = new Database($masterPassword, $userId);
            return $userDb->load('config', false);
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * Helper to recursively delete a directory
     */
    private function recursiveDelete(string $dir): bool {
        if (!is_dir($dir)) {
            return true;
        }
        
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            if (is_dir($path)) {
                $this->recursiveDelete($path);
            } else {
                unlink($path);
            }
        }
        
        return rmdir($dir);
    }

    /**
     * Check if request is from MCP (Master Control Panel) via Header
     */
    public static function isMcp(): bool {
        $header = $_SERVER['HTTP_X_MASTER_PASSWORD'] ?? '';
        if ($header === '') {
            return false;
        }

        // MCP access is restricted to local requests by default.
        if (!self::isLoopbackRequest()) {
            return false;
        }

        $expected = self::getExpectedMcpSecret();
        if ($expected === '') {
            return false;
        }

        return hash_equals($expected, $header);
    }

    /**
     * Check if user is authenticated
     */
    public static function check(): bool {
        // Allow MCP access
        if (self::isMcp()) {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            if (!isset($_SESSION[SESSION_MASTER_KEY])) {
                $_SESSION[SESSION_MASTER_KEY] = self::getExpectedMcpSecret();
            }

            // Resolve MCP user context from header
            $mcpUserEmail = $_SERVER['HTTP_X_MCP_USER_EMAIL'] ?? '';
            if ($mcpUserEmail !== '') {
                try {
                    // We need to load users to find the ID. 
                    // Use the secret directly since we are in MCP context.
                    $db = new Database($_SESSION[SESSION_MASTER_KEY]);
                    $users = $db->load('users', true);
                    foreach ($users as $user) {
                        if (($user['email'] ?? '') === $mcpUserEmail) {
                            self::$mcpUserId = $user['id'];
                            break;
                        }
                    }
                } catch (Exception $e) {
                    // Ignore error, fallback to global scope
                }
            }

            return true;
        }

        // Start session if not already started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Check if session is active
        if (!isset($_SESSION[SESSION_USER_ID])) {
            // Try to restore from persistent auth token
            $token = $_COOKIE[self::PERSISTENT_AUTH_COOKIE] ?? '';
            if (is_string($token) && $token !== '') {
                try {
                    $masterPassword = getMasterPassword();
                    if ($masterPassword !== '') {
                        $db = new Database($masterPassword);
                        $auth = new self($db);

                        if ($auth->restoreSessionFromToken($token)) {
                            // Keep encrypted data access working after token-based restore.
                            if (!isset($_SESSION[SESSION_MASTER_KEY])) {
                                $_SESSION[SESSION_MASTER_KEY] = $masterPassword;
                            }
                            return true;
                        }
                    }
                } catch (Exception $e) {
                    // Token restoration failed, continue to return false
                }
                $_SERVER['AUTH_LOGOUT_REASON'] = 'token_restore_failed';
                self::clearPersistentAuthCookieStatic();
            } else {
                $_SERVER['AUTH_LOGOUT_REASON'] = 'session_missing';
            }
            return false;
        }

        $now = time();
        $timeoutPreference = self::normalizeSessionTimeoutPreference($_SESSION['session_timeout_preference'] ?? null);

        // Hydrate preference from user profile if session did not carry it.
        if (!isset($_SESSION['session_timeout_preference'])) {
            $resolvedPreference = self::resolveUserTimeoutPreference((string)$_SESSION[SESSION_USER_ID]);
            $timeoutPreference = self::normalizeSessionTimeoutPreference($resolvedPreference);
            $_SESSION['session_timeout_preference'] = $timeoutPreference;
        }

        $timeoutSeconds = self::timeoutPreferenceToSeconds($timeoutPreference);
        if ($timeoutSeconds !== null) {
            $lastActivity = isset($_SESSION['last_activity_time'])
            ? (int)$_SESSION['last_activity_time']
            : (int)($_SESSION[SESSION_LOGIN_TIME] ?? $now);

            if (($now - $lastActivity) > $timeoutSeconds) {
                $_SERVER['AUTH_LOGOUT_REASON'] = 'session_timeout';
                self::destroyCurrentSession(true);
                return false;
            }
        }

        self::touchSessionActivity($now);

        // Validate session fingerprint (security check)
        // Note: Fingerprint validation is disabled for now due to issues with
        // User-Agent changes between requests (mobile browsers, proxies, etc.)
        // TODO: Implement a more robust session validation mechanism

        return true;
    }

    /**
     * Get current user ID
     */
    public static function userId(): ?string {
        if (self::$mcpUserId !== null) {
            return self::$mcpUserId;
        }
        return $_SESSION[SESSION_USER_ID] ?? null;
    }

    /**
     * Get current user info
     */
    public static function user(): ?array {
        if (!self::check()) {
            return null;
        }

        $resolvedUserId = self::userId();
        $masterPassword = getMasterPassword();
        if ($resolvedUserId !== null && $masterPassword !== '') {
            try {
                $db = new Database($masterPassword);
                $users = self::normalizeUserRoles($db, $db->load('users', true));
                foreach ($users as $user) {
                    if (($user['id'] ?? '') === $resolvedUserId) {
                        $resolvedUser = [
                            'id' => $user['id'],
                            'email' => $user['email'],
                            'name' => $user['name'],
                            'role' => self::normalizeRole($user['role'] ?? null)
                        ];
                        $_SESSION['user_role'] = $resolvedUser['role'];
                        return $resolvedUser;
                    }
                }
            } catch (Exception $e) {
                // Fall back to session state below.
            }
        }

        if (self::$mcpUserId !== null) {
            try {
                $db = new Database($_SESSION[SESSION_MASTER_KEY]);
                $users = $db->load('users', true);
                foreach ($users as $user) {
                    if (($user['id'] ?? '') === self::$mcpUserId) {
                        return [
                            'id' => $user['id'],
                            'email' => $user['email'],
                            'name' => $user['name'],
                            'role' => self::normalizeRole($user['role'] ?? null)
                        ];
                    }
                }
            } catch (Exception $e) {
                return null;
            }
        }

        return [
            'id' => $_SESSION[SESSION_USER_ID],
            'email' => $_SESSION[SESSION_USER_EMAIL],
            'name' => $_SESSION[SESSION_USER_NAME],
            'role' => self::normalizeRole($_SESSION['user_role'] ?? null)
        ];
    }

    public static function role(): string {
        return self::normalizeRole(self::user()['role'] ?? null);
    }

    public static function isAdmin(): bool {
        return self::role() === self::ROLE_ADMIN;
    }

    public static function requireAdmin(): void {
        if (!self::check()) {
            errorResponse('Unauthorized', 401, ERROR_UNAUTHORIZED);
        }
        if (!self::isAdmin()) {
            errorResponse('Administrator access is required', 403, ERROR_FORBIDDEN);
        }
    }

    public static function allUsers(): array {
        $masterPassword = getMasterPassword();
        if ($masterPassword === '') {
            return [];
        }

        try {
            $db = new Database($masterPassword);
            return self::normalizeUserRoles($db, $db->load('users', true));
        } catch (Exception $e) {
            return [];
        }
    }

    public static function isLastAdmin(string $userId): bool {
        if ($userId === '') {
            return false;
        }

        $adminCount = 0;
        foreach (self::allUsers() as $user) {
            if (self::normalizeRole($user['role'] ?? null) === self::ROLE_ADMIN) {
                $adminCount++;
                if (($user['id'] ?? '') !== $userId) {
                    return false;
                }
            }
        }

        return $adminCount === 1;
    }

    /**
     * Validate and normalize timeout preference input.
     */
    public static function normalizeSessionTimeoutPreference(?string $preference): string {
        $value = is_string($preference) ? strtolower(trim($preference)) : '';
        $allowed = [
            self::SESSION_TIMEOUT_20M,
            self::SESSION_TIMEOUT_1H,
            self::SESSION_TIMEOUT_INDEFINITE
        ];

        if (!in_array($value, $allowed, true)) {
            return self::SESSION_TIMEOUT_DEFAULT;
        }

        return $value;
    }

    /**
     * Convert a timeout preference to inactivity seconds (null = no timeout).
     */
    private static function timeoutPreferenceToSeconds(string $preference): ?int {
        $normalized = self::normalizeSessionTimeoutPreference($preference);
        if ($normalized === self::SESSION_TIMEOUT_20M) {
            return 20 * 60;
        }
        if ($normalized === self::SESSION_TIMEOUT_INDEFINITE) {
            return null;
        }
        return 60 * 60;
    }

    /**
     * Generate session fingerprint for security
     * Prevents session hijacking by validating user agent and IP
     */
    private static function generateSessionFingerprint(): string {
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '';
        return hash('sha256', $userAgent . '|' . $ipAddress);
    }

    /**
     * Validate session fingerprint
     * Returns false if fingerprint doesn't match (potential hijacking)
     */
    private static function validateSessionFingerprint(): bool {
        if (!isset($_SESSION['fingerprint'])) {
            return true;
        }

        $currentFingerprint = self::generateSessionFingerprint();
        return hash_equals($_SESSION['fingerprint'], $currentFingerprint);
    }

    /**
     * Validate CSRF token
     */
    public static function validateCsrf(string $token): bool {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }

    /**
     * Get CSRF token
     */
    public static function csrfToken(): string {
        return $_SESSION['csrf_token'] ?? '';
    }

    /**
     * Resolve MCP secret from environment or static configuration.
     */
    private static function getExpectedMcpSecret(): string {
        $env = getenv('MCP_MASTER_PASSWORD') ?: getenv('MASTER_PASSWORD') ?: getenv('LAZYMAN_MASTER_PASSWORD');
        if (is_string($env) && $env !== '') {
            return $env;
        }

        if (defined('MASTER_PASSWORD') && is_string(MASTER_PASSWORD) && MASTER_PASSWORD !== '') {
            return MASTER_PASSWORD;
        }

        // Final fallback: resolve from standard master-password lookup chain
        // (session, env, includes/master_password.php). This keeps MCP compatible
        // with installations that only define the runtime master secret there.
        $resolved = getMasterPassword();
        if (is_string($resolved) && $resolved !== '') {
            return $resolved;
        }

        return '';
    }

    /**
     * Check if request originates from localhost.
     */
    private static function isLoopbackRequest(): bool {
        $remote = $_SERVER['REMOTE_ADDR'] ?? '';
        return in_array($remote, ['127.0.0.1', '::1'], true);
    }

    /**
     * Resolve timeout preference from currently logged-in user's profile.
     */
    private static function resolveUserTimeoutPreference(string $userId): string {
        if ($userId === '') {
            return self::SESSION_TIMEOUT_DEFAULT;
        }

        try {
            $masterPassword = getMasterPassword();
            if ($masterPassword === '') {
                return self::SESSION_TIMEOUT_DEFAULT;
            }

            $db = new Database($masterPassword);
            $users = $db->load('users', true);
            foreach ($users as $user) {
                if (($user['id'] ?? '') === $userId) {
                    return self::normalizeSessionTimeoutPreference($user['sessionTimeoutPreference'] ?? null);
                }
            }
        } catch (Exception $e) {
            // Fall back to default below.
        }

        return self::SESSION_TIMEOUT_DEFAULT;
    }

    public static function normalizeRole(mixed $role): string {
        $value = is_string($role) ? strtolower(trim($role)) : '';
        return $value === self::ROLE_ADMIN ? self::ROLE_ADMIN : self::ROLE_USER;
    }

    private static function normalizeUserRoles(Database $db, array $users): array {
        $changed = false;
        $hasAdmin = false;
        $firstUserIndex = null;

        foreach ($users as $index => $user) {
            if ($firstUserIndex === null && !empty($user['id'])) {
                $firstUserIndex = $index;
            }

            $normalizedRole = self::normalizeRole($user['role'] ?? null);
            if (($user['role'] ?? null) !== $normalizedRole) {
                $users[$index]['role'] = $normalizedRole;
                $changed = true;
            }

            if ($normalizedRole === self::ROLE_ADMIN) {
                $hasAdmin = true;
            }
        }

        if (!$hasAdmin && $firstUserIndex !== null) {
            $users[$firstUserIndex]['role'] = self::ROLE_ADMIN;
            $hasAdmin = true;
            $changed = true;
        }

        if ($changed) {
            $db->save('users', $users);
        }

        return $users;
    }

    /**
     * Write activity timestamp at a controlled frequency.
     */
    private static function touchSessionActivity(int $now): void {
        $lastTouch = (int)($_SESSION['last_activity_touch'] ?? 0);
        if ($lastTouch === 0 || ($now - $lastTouch) >= self::SESSION_ACTIVITY_TOUCH_INTERVAL) {
            $_SESSION['last_activity_time'] = $now;
            $_SESSION['last_activity_touch'] = $now;
        }
    }

    /**
     * Set persistent auth cookie.
     */
    private function setPersistentAuthCookie(string $token): void {
        if ($token === '') {
            return;
        }

        $params = session_get_cookie_params();
        setcookie(
            self::PERSISTENT_AUTH_COOKIE,
            $token,
            [
                'expires' => time() + REMEMBER_ME_LIFETIME,
                'path' => $params['path'] ?? '/',
                'domain' => $params['domain'] ?? '',
                'secure' => (bool)($params['secure'] ?? false),
                'httponly' => true,
                'samesite' => SESSION_COOKIE_SAMESITE
            ]
        );
    }

    /**
     * Clear persistent auth cookie.
     */
    private function clearPersistentAuthCookie(): void {
        self::clearPersistentAuthCookieStatic();
    }

    /**
     * Clear persistent auth cookie (static variant).
     */
    private static function clearPersistentAuthCookieStatic(): void {
        $params = session_get_cookie_params();
        setcookie(
            self::PERSISTENT_AUTH_COOKIE,
            '',
            [
                'expires' => time() - 42000,
                'path' => $params['path'] ?? '/',
                'domain' => $params['domain'] ?? '',
                'secure' => (bool)($params['secure'] ?? false),
                'httponly' => true,
                'samesite' => SESSION_COOKIE_SAMESITE
            ]
        );
    }

    /**
     * Destroy current session and optionally clear persistent auth cookie.
     */
    private static function destroyCurrentSession(bool $clearPersistentAuthToken): void {
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                [
                    'expires' => time() - 42000,
                    'path' => $params['path'] ?? '/',
                    'domain' => $params['domain'] ?? '',
                    'secure' => (bool)($params['secure'] ?? false),
                    'httponly' => (bool)($params['httponly'] ?? true),
                    'samesite' => SESSION_COOKIE_SAMESITE
                ]
            );
        }

        if ($clearPersistentAuthToken) {
            self::clearPersistentAuthCookieStatic();
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }

    private function getPasswordResetUser(string $token): ?array {
        if ($token === '') {
            return null;
        }

        $users = $this->db->load('users', true);
        $tokenHash = hash('sha256', $token);
        $now = time();

        foreach ($users as $user) {
            if (($user['passwordResetTokenHash'] ?? '') !== $tokenHash) {
                continue;
            }

            $expiresAt = strtotime((string)($user['passwordResetExpiresAt'] ?? ''));
            if ($expiresAt === false || $expiresAt <= $now) {
                return null;
            }

            return $user;
        }

        return null;
    }
}
