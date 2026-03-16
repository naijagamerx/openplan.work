<?php
/**
 * Authentication API Endpoint
 */

// Start output buffering to prevent any accidental output breaking JSON responses
ob_start();

require_once __DIR__ . '/../config.php';

// Force JSON response headers for all API responses
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

// Enable error logging (never display errors in API responses)
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../data/php_error.log');
ini_set('display_errors', 0);

$action = $_GET['action'] ?? null;

switch ($action) {
    case 'login':
        if (requestMethod() !== 'POST') {
            errorResponse('Method not allowed', 405);
        }

        $body = getJsonBody();
        $email = trim($body['email'] ?? '');
        $password = $body['password'] ?? '';
        $masterPassword = $body['master_password'] ?? '';
        $csrfToken = $body['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        // Optional login-time remember-me preference. When true, login will switch
        // the user preference to persistent session mode.
        $rememberMe = !empty($body['remember_me']);

        // CSRF validation
        if (!Auth::validateCsrf($csrfToken)) {
            errorResponse('Invalid CSRF token', 403);
        }

        if (empty($email) || empty($password) || empty($masterPassword)) {
            errorResponse('All fields are required');
        }

        // Initialize rate limiter and check for rate limiting
        $db = new Database($masterPassword);
        $rateLimiter = new RateLimiter($db);
        $clientIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $identifier = $email . '|' . $clientIp;

        // Check if currently blocked
        if ($rateLimiter->isLoginBlocked($identifier)) {
            $unlockTime = $rateLimiter->getUnlockTime($identifier);
            $minutes = ceil($unlockTime / 60);
            errorResponse(
                "Too many login attempts. Please try again in {$minutes} minutes.",
                429,
                'RATE_LIMIT_EXCEEDED'
            );
        }

        $_SESSION[SESSION_MASTER_KEY] = $masterPassword;

        $auth = new Auth($db);
        $result = $auth->login($email, $password, $rememberMe);

        if ($result['success']) {
            $verificationNotice = $result['verification_notice'] ?? null;

            // Clear rate limit attempts on successful login
            $rateLimiter->clearLoginAttempts($identifier);

            // Log successful login to audit
            $rateLimiter->logAudit(
                $result['user']['id'],
                'login',
                'auth',
                null,
                $clientIp,
                $_SERVER['HTTP_USER_AGENT'] ?? null,
                true,
                'Login successful'
            );

            successResponse([
                'user' => [
                    'id' => $result['user']['id'],
                    'email' => $result['user']['email'],
                    'name' => $result['user']['name'],
                    'role' => Auth::normalizeRole($result['user']['role'] ?? null)
                ],
                'destination' => Auth::getPostLoginDestination(),
                'verificationNotice' => $verificationNotice
            ], 'Login successful');
        } else {
            // Record failed attempt
            $rateLimiter->recordFailedAttempt($identifier);

            // Get remaining attempts
            $remaining = $rateLimiter->getRemainingAttempts($identifier);

            // Log failed login to audit
            $rateLimiter->logAudit(
                null,
                'login',
                'auth',
                null,
                $clientIp,
                $_SERVER['HTTP_USER_AGENT'] ?? null,
                false,
                "Failed login attempt: {$result['error']}. {$remaining} attempts remaining."
            );

            unset($_SESSION[SESSION_MASTER_KEY]);

            // Check if now blocked
            if ($rateLimiter->isLoginBlocked($identifier)) {
                errorResponse(
                    "Invalid credentials. Account temporarily locked due to too many failed attempts. Please try again in " . ceil(LOGIN_LOCKOUT_SECONDS / 60) . " minutes.",
                    429,
                    'RATE_LIMIT_EXCEEDED'
                );
            }

            errorResponse($result['error'], 401);
        }
        break;
        
    case 'logout':
        // Log logout to audit
        $db = new Database(getMasterPassword());
        $rateLimiter = new RateLimiter($db);
        $userId = Auth::userId();

        if (Auth::check()) {
            $rateLimiter->logAudit(
                $userId,
                'logout',
                'auth',
                null,
                $_SERVER['REMOTE_ADDR'] ?? null,
                $_SERVER['HTTP_USER_AGENT'] ?? null,
                true,
                'User logged out'
            );

            // Revoke all auth tokens
            $auth = new Auth($db);
            $auth->revokeAllTokens($userId);
        }

        // Always destroy session, even if already expired
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );

            // Clear auth token cookie
            setcookie(
                'lazyman_auth_token',
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                true // httponly
            );
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }

        // Redirect for GET requests
        if (requestMethod() === 'GET') {
            // Use absolute path - ensure we redirect to main app, not API directory
            $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'];
            $scriptPath = dirname($_SERVER['SCRIPT_NAME']);
            $basePath = str_replace('/api', '', $scriptPath);

            // Preserve device parameter if present
            $deviceParam = isset($_GET['device']) ? '&device=' . urlencode($_GET['device']) : '';
            $loginUrl = $protocol . '://' . $host . $basePath . '/?page=login' . $deviceParam;
            header('Location: ' . $loginUrl);
            exit;
        }

        successResponse(null, 'Logged out');
        break;
        
    case 'status':
        if (Auth::check()) {
            successResponse([
                'authenticated' => true,
                'user' => Auth::user()
            ]);
        } else {
            successResponse(['authenticated' => false]);
        }
        break;
        
    case 'register':
        if (requestMethod() !== 'POST') {
            errorResponse('Method not allowed', 405);
        }

        $isBootstrapRegistration = !userRegistryExists();
        if (!$isBootstrapRegistration && !isRegistrationEnabled()) {
            errorResponse('Registration is disabled for this installation', 403, ERROR_FORBIDDEN);
        }

        $body = getJsonBody();
        $email = trim($body['email'] ?? '');
        $password = $body['password'] ?? '';
        $name = trim($body['name'] ?? '');
        $masterPassword = $body['master_password'] ?? '';
        $installMode = $body['install_mode'] ?? 'multi_user';
        $csrfToken = $body['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';

        // CSRF validation
        if (!Auth::validateCsrf($csrfToken)) {
            errorResponse('Invalid CSRF token', 403);
        }

        if (empty($email) || empty($password) || empty($name) || empty($masterPassword)) {
            errorResponse('All fields are required');
        }

        // Validate email
        $emailResult = Validator::email($email);
        if (!$emailResult['valid']) {
            errorResponse($emailResult['error']);
        }

        // Check for disposable emails
        if (Validator::isDisposableEmail($email)) {
            errorResponse('Registration is not allowed from disposable email providers.');
        }

        // Check for plus addressing (anti-spam measure)
        if (Validator::isPlusAddress($email)) {
            errorResponse('Email addresses with tags (plus-addressing) are not allowed.');
        }

        // Validate password strength
        $passwordResult = Validator::passwordStrength($password);
        if (!$passwordResult['valid']) {
            errorResponse('Password does not meet requirements: ' . implode(', ', $passwordResult['errors']));
        }

        $_SESSION[SESSION_MASTER_KEY] = $masterPassword;
        
        $db = new Database($masterPassword);
        $auth = new Auth($db);
        if ($isBootstrapRegistration && !in_array($installMode, ['single_user', 'multi_user'], true)) {
            errorResponse('Invalid install mode selected');
        }

        $result = $auth->register($email, $password, $name, $isBootstrapRegistration ? Auth::ROLE_ADMIN : Auth::ROLE_USER);

        if ($result['success']) {
            $newUser = $result['user'];
            if ($isBootstrapRegistration) {
                savePublicConfig(['installMode' => $installMode]);
            }
            $userDb = new Database($masterPassword, $newUser['id']);
            $userDb->save('config', [
                'siteName' => '',
                'businessName' => '',
                'businessEmail' => $email,
                'currency' => 'USD',
                'taxRate' => 0,
                'groqApiKey' => '',
                'openrouterApiKey' => '',
                'setupComplete' => true,
                'createdAt' => date('c')
            ]);

            $mailSent = false;
            if (isEmailVerificationEnabled()) {
                try {
                    $tokenResult = $auth->issueEmailVerification($newUser['id'], true);
                    if (!empty($tokenResult['success']) && !empty($tokenResult['updated']) && !empty($tokenResult['users'])) {
                        $auth->persistUsers($tokenResult['users']);
                        $mailer = new Mailer($userDb->load('config', false));
                        $mailSent = $mailer->sendVerificationEmail($newUser, $tokenResult['token']);
                    }
                } catch (Exception $e) {
                    // Log error but don't block registration - user can still verify later
                    error_log('Failed to send verification email during registration: ' . $e->getMessage());
                    $mailSent = false;
                }
            } else {
                // No email verification - send welcome email anyway
                try {
                    $mailer = new Mailer($userDb->load('config', false));
                    $mailer->sendWelcomeEmail($newUser);
                } catch (Exception $e) {
                    // Log but don't block - welcome email is nice-to-have
                    error_log('Failed to send welcome email during registration: ' . $e->getMessage());
                }
            }

            setAuthFlash([
                'mode' => isEmailVerificationEnabled() ? 'verify_email' : 'open_source',
                'title' => isEmailVerificationEnabled() ? 'Welcome to the club.' : 'Account created.',
                'headline_emphasis' => 'club',
                'email' => $email,
                'mail_sent' => $mailSent,
                'cta_href' => '?page=login',
                'cta_label' => isEmailVerificationEnabled() ? 'Continue to Sign In' : 'Sign In',
                'status_label' => isEmailVerificationEnabled() ? 'Security Status: Verification Pending' : 'Security Status: Ready to Sign In',
                'message' => isEmailVerificationEnabled()
                    ? 'Your account is ready. Verify your email to unlock the full dashboard.'
                    : 'Your account is ready. You can sign in and continue immediately.',
                'detail' => isEmailVerificationEnabled()
                    ? 'We will send a verification link to your registered email address. If mail delivery fails, you can sign in and resend it from the verification screen.'
                    : 'This installation is running in plug-and-play mode with no email verification required.'
            ]);

            successResponse([
                'user' => [
                    'id' => $newUser['id'],
                    'email' => $newUser['email'],
                    'name' => $newUser['name'],
                    'role' => Auth::normalizeRole($newUser['role'] ?? null)
                ],
                'verificationEnabled' => isEmailVerificationEnabled(),
                'mailSent' => $mailSent,
                'destination' => 'thank-you',
                'redirectUrl' => '?page=thank-you'
            ], 'Registration successful');
        } else {
            errorResponse($result['error']);
        }
        break;

    case 'change_password':
        if (requestMethod() !== 'POST') {
            errorResponse('Method not allowed', 405);
        }

        if (!Auth::check()) {
            errorResponse('Unauthorized', 401);
        }

        $body = getJsonBody();
        $currentPassword = $body['current_password'] ?? '';
        $newPassword = $body['new_password'] ?? '';
        $csrfToken = $body['csrf_token'] ?? '';

        // CSRF validation
        if (!Auth::validateCsrf($csrfToken)) {
            errorResponse('Invalid CSRF token', 403);
        }

        if (empty($currentPassword) || empty($newPassword)) {
            errorResponse('All fields are required');
        }

        if (strlen($newPassword) < 8) {
            errorResponse('New password must be at least 8 characters');
        }

        if ($currentPassword === $newPassword) {
            errorResponse('New password must be different from current password');
        }

        $db = new Database(getMasterPassword());
        $auth = new Auth($db);

        // Verify current password
        $users = $db->load('users', true);
        $currentUser = Auth::user();
        $userFound = false;

        foreach ($users as &$user) {
            if ($user['id'] === $currentUser['id']) {
                $userFound = true;
                if (!Encryption::verifyPassword($currentPassword, $user['passwordHash'])) {
                    errorResponse('Current password is incorrect', 400);
                }

                // Update password
                $user['passwordHash'] = Encryption::hashPassword($newPassword);
                $user['updatedAt'] = date('c');

                if ($db->save('users', $users)) {
                    successResponse(null, 'Password changed successfully');
                } else {
                    errorResponse('Failed to update password');
                }
                break;
            }
        }

        if (!$userFound) {
            errorResponse('User not found', 404);
        }
        break;

    case 'change_master_password':
        if (requestMethod() !== 'POST') {
            errorResponse('Method not allowed', 405);
        }

        if (!Auth::check()) {
            errorResponse('Unauthorized', 401);
        }

        $body = getJsonBody();
        // Accept both explicit master-password field names and legacy mobile aliases.
        $currentMasterPassword = $body['current_master_password'] ?? ($body['current_password'] ?? '');
        $newMasterPassword = $body['new_master_password'] ?? ($body['new_password'] ?? '');
        $csrfToken = $body['csrf_token'] ?? '';

        // CSRF validation
        if (!Auth::validateCsrf($csrfToken)) {
            errorResponse('Invalid CSRF token', 403);
        }

        if (empty($currentMasterPassword) || empty($newMasterPassword)) {
            errorResponse('All fields are required');
        }

        if (strlen($newMasterPassword) < 4) {
            errorResponse('Master password must be at least 4 characters');
        }

        if ($currentMasterPassword === $newMasterPassword) {
            errorResponse('New master password must be different from current');
        }

        $currentUserId = Auth::userId();
        if (!$currentUserId) {
            errorResponse('Unauthorized', 401);
        }

        // Verify current master password against the current user's encrypted data
        try {
            $oldDb = new Database($currentMasterPassword, $currentUserId);
            $oldDb->load('key_check', true);
        } catch (Exception $e) {
            errorResponse('Current master password is incorrect', 400);
        }

        // Recursive function to find all encrypted files
        function getAllEncryptedFiles($dir) {
            $results = [];
            $files = scandir($dir);
            
            foreach ($files as $value) {
                $path = realpath($dir . DIRECTORY_SEPARATOR . $value);
                if (!is_dir($path)) {
                    if (str_ends_with($value, '.json.enc')) {
                        $results[] = $path;
                    }
                } else if ($value != "." && $value != "..") {
                    $results = array_merge($results, getAllEncryptedFiles($path));
                }
            }
            return $results;
        }

        $userDataPath = DATA_PATH . '/users/' . $currentUserId;
        $allFiles = getAllEncryptedFiles($userDataPath);

        if (empty($allFiles)) {
            errorResponse('No encrypted user data files found to re-encrypt', 500);
        }

        // Re-encrypt only the current user's data with the new password
        foreach ($allFiles as $filePath) {
            // Read raw encrypted content
            $encryptedData = file_get_contents($filePath);
            if (empty($encryptedData)) continue;

            try {
                // Decrypt with old password
                $encryption = new Encryption($currentMasterPassword);
                $data = $encryption->decrypt($encryptedData);
                
                // Encrypt with new password
                $newEncryption = new Encryption($newMasterPassword);
                $newEncryptedData = $newEncryption->encrypt($data);
                
                // Write back to file
                file_put_contents($filePath, $newEncryptedData);
            } catch (Exception $e) {
                error_log("Failed to re-encrypt {$filePath}: " . $e->getMessage());
                errorResponse('Failed to re-encrypt all user data. The master password was not fully updated.', 500);
            }
        }

        // Update session with new master password
        $_SESSION[SESSION_MASTER_KEY] = $newMasterPassword;

        successResponse(null, 'Master password changed and your encrypted data was re-encrypted successfully');
        break;
        
    case 'delete_account':
        if (requestMethod() !== 'POST') {
            errorResponse('Method not allowed', 405);
        }

        if (!Auth::check()) {
            errorResponse('Unauthorized', 401);
        }

        $body = getJsonBody();
        $password = $body['password'] ?? '';
        $csrfToken = $body['csrf_token'] ?? '';

        // CSRF validation
        if (!Auth::validateCsrf($csrfToken)) {
            errorResponse('Invalid CSRF token', 403);
        }

        if (empty($password)) {
            errorResponse('Password is required to confirm account deletion');
        }

        $db = new Database(getMasterPassword());
        $auth = new Auth($db);
        $userId = Auth::userId();

        // Verify password before deletion
        $users = $db->load('users', true);
        $verified = false;
        foreach ($users as $user) {
            if ($user['id'] === $userId) {
                if (Encryption::verifyPassword($password, $user['passwordHash'])) {
                    $verified = true;
                }
                break;
            }
        }

        if (!$verified) {
            errorResponse('Incorrect password', 401);
        }

        // Perform deletion
        $result = $auth->deleteAccount($userId);
        
        if ($result['success']) {
            successResponse(null, 'Account deleted successfully');
        } else {
            errorResponse($result['error']);
        }
        break;
        
    case 'delete_data':
        if (requestMethod() !== 'POST') {
            errorResponse('Method not allowed', 405);
        }

        if (!Auth::check()) {
            errorResponse('Unauthorized', 401);
        }

        $body = getJsonBody();
        $password = $body['password'] ?? '';
        $csrfToken = $body['csrf_token'] ?? '';

        // CSRF validation
        if (!Auth::validateCsrf($csrfToken)) {
            errorResponse('Invalid CSRF token', 403);
        }
        
        if (empty($password)) {
            errorResponse('Password is required to confirm data deletion');
        }

        $db = new Database(getMasterPassword());
        $auth = new Auth($db);
        $userId = Auth::userId();
        
        // Verify password
        $users = $db->load('users', true);
        $verified = false;
        foreach ($users as $user) {
            if ($user['id'] === $userId) {
                if (Encryption::verifyPassword($password, $user['passwordHash'])) {
                    $verified = true;
                }
                break;
            }
        }

        if (!$verified) {
            errorResponse('Incorrect password', 401);
        }

        $result = $auth->deleteUserData($userId);
        
        if ($result['success']) {
            successResponse(null, 'All user data deleted successfully');
        } else {
            errorResponse('Failed to delete user data');
        }
        break;
        
    case 'update_config':
        // Update user-specific settings (timeout preference, etc.)
        if (requestMethod() !== 'POST') {
            errorResponse('Method not allowed', 405);
        }
        
        if (!Auth::check()) {
            errorResponse('Unauthorized', 401);
        }
        
        $body = getJsonBody();
        $csrfToken = $body['csrf_token'] ?? '';
        
        if (!Auth::validateCsrf($csrfToken)) {
            errorResponse('Invalid CSRF token', 403);
        }
        
        $db = new Database(getMasterPassword());
        $auth = new Auth($db);
        
        // Handle session timeout preference
        if (isset($body['session_timeout'])) {
            $preference = $body['session_timeout'];
            if ($auth->updateSessionTimeoutPreference(Auth::userId(), $preference)) {
                successResponse(null, 'Settings updated');
            } else {
                errorResponse('Failed to update settings');
            }
        }
        
        // Handle other user config updates here if needed
        
        successResponse(null, 'No changes made');
        break;
        
    default:
        errorResponse('Invalid action', 400);
}
