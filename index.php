<?php
/**
 * LazyMan Tools - Main Entry Point & Router
 */

require_once __DIR__ . '/config.php';
require_once INCLUDES_PATH . '/Helpers.php';

// Device Detection - Route mobile users to mobile version
require_once __DIR__ . '/includes/DeviceDetector.php';

// Get the requested page
$rawPage = $_GET['page'] ?? 'homepage';
$page = preg_replace('/[^a-z0-9_-]/i', '', $rawPage);
$publicPages = ['homepage', 'docs', 'login', 'master-code', 'setup', 'register', 'thank-you', 'forgot-password', 'reset-password', 'verify-email', 'privacy', 'terms'];
$verificationAllowedPages = ['verification-required', 'verify-email', 'logout'];

// Mobile routing: phones get the dedicated mobile design (login, register,
// dashboard, etc.) EXCEPT the homepage, which always uses the responsive
// desktop design (the desktop index shrunk to fit mobile). Desktop browsers
// are served the desktop site everywhere. The Android APK loads /mobile/
// directly (mobile/index.php) and is unaffected by this check.
if (DeviceDetector::shouldShowMobile() && $page !== 'homepage') {
    // For public pages (login, setup), include mobile views directly
    if (in_array($page, $publicPages)) {
        $mobileViewFile = __DIR__ . '/mobile/views/' . $page . '.php';
        if (file_exists($mobileViewFile)) {
            include $mobileViewFile;
            exit;
        }
    }

    // For other pages, include mobile index
    require_once __DIR__ . '/mobile/index.php';
    exit;
}

// Get the requested page (for desktop version)
$action = $_GET['action'] ?? null;

// Check authentication for protected pages
$isAuthenticated = Auth::check();

// Capture ?redirect=... param for post-login bounce-back (e.g. Alexa OAuth flow).
// Stored in session so it survives the 2-step (password → master code) login.
if ($page === 'login' && !empty($_GET['redirect'])) {
    $candidate = (string)$_GET['redirect'];
    if (strpos($candidate, '/') === 0 && strpos($candidate, '//') !== 0 && strpos($candidate, '://') === false) {
        $_SESSION['post_login_redirect'] = $candidate;
    }
}

// Redirect to login if not authenticated
if (!in_array($page, $publicPages) && !$isAuthenticated) {
    header('Location: ' . APP_URL . '?page=login');
    exit;
}

$registrationError = '';
$registrationValues = [];

if ($page === 'register' && !isRegistrationEnabled()) {
    header('Location: ' . APP_URL . '?page=login');
    exit;
}

if ($page === 'register' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    ob_start();

    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $masterPassword = $_POST['master_password'] ?? '';
    $csrfToken = $_POST['csrf_token'] ?? '';

    $registrationValues = [
        'name' => $name,
        'email' => $email
    ];

    if (!Auth::validateCsrf($csrfToken)) {
        $registrationError = 'Invalid request token. Please refresh and try again.';
    } elseif ($name === '' || $email === '' || $password === '' || $masterPassword === '') {
        $registrationError = 'All fields are required';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $registrationError = 'Invalid email address';
    } elseif (Validator::isDisposableEmail($email)) {
        $registrationError = 'Registration is not allowed from disposable email providers.';
    } elseif (Validator::isPlusAddress($email)) {
        $registrationError = 'Email addresses with tags (plus-addressing) are not allowed.';
    } elseif (strlen($password) < 8) {
        $registrationError = 'Password must be at least 8 characters';
    } elseif ($password !== $confirmPassword) {
        $registrationError = 'Passwords do not match';
    } else {
        $_SESSION[SESSION_MASTER_KEY] = $masterPassword;

        $db = new Database($masterPassword);
        $auth = new Auth($db);
        $result = $auth->register($email, $password, $name);

        if ($result['success']) {
            $newUser = $result['user'];
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

            $flash = [
                'mode' => isEmailVerificationEnabled() ? 'verify_email' : 'open_source',
                'title' => isEmailVerificationEnabled() ? 'Welcome to the club.' : 'Account created.',
                'headline_emphasis' => 'club',
                'email' => $email,
                'mail_sent' => false,
                'cta_href' => '?page=login',
                'cta_label' => isEmailVerificationEnabled() ? 'Continue to Sign In' : 'Sign In',
                'status_label' => isEmailVerificationEnabled() ? 'Security Status: Verification Pending' : 'Security Status: Ready to Sign In',
                'message' => isEmailVerificationEnabled()
                    ? 'Your account is ready. Verify your email to unlock the full dashboard.'
                    : 'Your account is ready. You can sign in and continue immediately.',
                'detail' => isEmailVerificationEnabled()
                    ? 'We will send a verification link to your registered email address. If mail delivery fails, you can sign in and resend it from the verification screen.'
                    : 'This installation is running in plug-and-play mode with no email verification required.'
            ];

            if (isEmailVerificationEnabled()) {
                try {
                    $tokenResult = $auth->issueEmailVerification($newUser['id'], true);
                    if (!empty($tokenResult['success']) && !empty($tokenResult['updated']) && !empty($tokenResult['users'])) {
                        $auth->persistUsers($tokenResult['users']);
                        $mailer = new Mailer($userDb->load('config', false));
                        $flash['mail_sent'] = $mailer->sendVerificationEmail($newUser, $tokenResult['token']);
                    }
                } catch (Exception $e) {
                    error_log('Failed to send verification email during registration: ' . $e->getMessage());
                    $flash['mail_sent'] = false;
                }
            } else {
                try {
                    $mailer = new Mailer($userDb->load('config', false));
                    $mailer->sendWelcomeEmail($newUser);
                } catch (Exception $e) {
                    error_log('Failed to send welcome email during registration: ' . $e->getMessage());
                }
            }

            setAuthFlash($flash);

            ob_end_clean();
            session_write_close();
            ?>
            <!DOCTYPE html>
            <html>
            <head>
                <meta http-equiv="refresh" content="0;url=<?php echo APP_URL; ?>?page=thank-you">
                <script>
                    window.location.href = '<?php echo APP_URL; ?>?page=thank-you';
                </script>
                <style>
                    body { font-family: system-ui, sans-serif; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; background: #fff; }
                    .loader { text-align: center; }
                    .spinner { border: 3px solid #f3f3f3; border-top: 3px solid #000; border-radius: 50%; width: 40px; height: 40px; animation: spin 1s linear infinite; margin: 0 auto 20px; }
                    @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
                </style>
            </head>
            <body>
                <div class="loader">
                    <div class="spinner"></div>
                    <p>Creating account...</p>
                </div>
            </body>
            </html>
            <?php
            exit;
        }

        $registrationError = $result['error'];
    }
}

// Redirect to dashboard if authenticated and on login page.
// Honor stored ?redirect=... target if present (used by Alexa OAuth flow).
if (in_array($page, ['login', 'register', 'thank-you', 'forgot-password', 'reset-password'], true) && $isAuthenticated) {
    if (!empty($_SESSION['post_login_redirect'])) {
        $target = (string)$_SESSION['post_login_redirect'];
        unset($_SESSION['post_login_redirect']);
        header('Location: ' . $target);
        exit;
    }
    header('Location: ' . APP_URL . '?page=' . Auth::getPostLoginDestination());
    exit;
}

if ($isAuthenticated && Auth::shouldRestrictToVerification() && !in_array($page, $verificationAllowedPages, true)) {
    header('Location: ' . APP_URL . '?page=verification-required');
    exit;
}

// Check if first-time setup is needed
if (!userRegistryExists()) {
    if ($page !== 'setup') {
        header('Location: ' . APP_URL . '?page=setup');
        exit;
    }
} elseif ($page === 'setup') {
    // App is installed, prevent access to setup
    header('Location: ' . APP_URL . '?page=login');
    exit;
}

// CSRF Token generation
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// IMPORTANT: Process login form BEFORE any HTML output to allow cookies to be set
// Step 1 of the 2-step login flow: validate email + password only, then redirect to master-code page.
if ($page === 'login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    ob_start();

    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $csrfToken = $_POST['csrf_token'] ?? '';
    $rememberMe = !empty($_POST['remember_me']);

    if (!Auth::validateCsrf($csrfToken)) {
        ob_end_clean();
        header('Location: ' . APP_URL . '?page=login&reason=invalid_csrf');
        exit;
    }

    if ($email === '' || $password === '') {
        $_SESSION['login_step1_error'] = 'Email and password are required';
        $_SESSION['login_step1_email'] = $email;
    } else {
        require_once INCLUDES_PATH . '/Database.php';
        require_once INCLUDES_PATH . '/Encryption.php';
        require_once INCLUDES_PATH . '/Auth.php';

        $db = new Database(APP_NAME);
        $auth = new Auth($db);
        $result = $auth->verifyEmailPasswordOnly($email, $password);

        if ($result['success']) {
            $_SESSION['pending_master_user_id'] = $result['user']['id'];
            $_SESSION['pending_master_email'] = $result['user']['email'];
            $_SESSION['pending_master_remember'] = $rememberMe;
            $_SESSION['pending_master_expires'] = time() + 300;
            unset($_SESSION['login_step1_error'], $_SESSION['login_step1_email']);

            ob_end_clean();
            session_write_close();
            ?>
            <!DOCTYPE html>
            <html>
            <head>
                <meta http-equiv="refresh" content="0;url=<?php echo APP_URL; ?>?page=master-code">
                <script>window.location.href = '<?php echo APP_URL; ?>?page=master-code';</script>
                <style>
                    body { font-family: system-ui, sans-serif; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; background: #fff; }
                    .loader { text-align: center; }
                    .spinner { border: 3px solid #f3f3f3; border-top: 3px solid #000; border-radius: 50%; width: 40px; height: 40px; animation: spin 1s linear infinite; margin: 0 auto 20px; }
                    @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
                </style>
            </head>
            <body>
                <div class="loader">
                    <div class="spinner"></div>
                    <p>One more step...</p>
                </div>
            </body>
            </html>
            <?php
            exit;
        } else {
            $_SESSION['login_step1_error'] = $result['error'];
            $_SESSION['login_step1_email'] = $email;
        }
    }

    ob_end_clean();
}

// Step 2 of the 2-step login flow: master code page guard + POST handler.
// Runs BEFORE the layout so header()/Location redirects work cleanly.
if ($page === 'master-code') {
    $pendingUserId = (string)($_SESSION['pending_master_user_id'] ?? '');
    $pendingExpires = (int)($_SESSION['pending_master_expires'] ?? 0);

    if ($pendingUserId === '' || $pendingExpires < time()) {
        unset(
            $_SESSION['pending_master_user_id'],
            $_SESSION['pending_master_email'],
            $_SESSION['pending_master_remember'],
            $_SESSION['pending_master_expires']
        );
        header('Location: ' . APP_URL . '?page=login&reason=session_expired');
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        ob_start();

        require_once INCLUDES_PATH . '/Database.php';
        require_once INCLUDES_PATH . '/Encryption.php';
        require_once INCLUDES_PATH . '/Auth.php';
        require_once INCLUDES_PATH . '/RateLimiter.php';

        $csrfToken = $_POST['csrf_token'] ?? '';
        $masterPasswordInput = $_POST['master_password'] ?? '';
        $pendingEmail = (string)($_SESSION['pending_master_email'] ?? '');

        $stepError = '';

        if (!Auth::validateCsrf($csrfToken)) {
            $stepError = 'Invalid request token. Please refresh and try again.';
        } elseif ($masterPasswordInput === '') {
            $stepError = 'Master password is required';
        } else {
            $usersDb = new Database(APP_NAME);
            $rateLimiter = new RateLimiter($usersDb);
            $clientIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            $identifier = $pendingEmail . '|' . $clientIp;

            if ($rateLimiter->isLoginBlocked($identifier)) {
                $unlockMinutes = ceil($rateLimiter->getUnlockTime($identifier) / 60);
                $stepError = "Too many failed attempts. Please try again in {$unlockMinutes} minutes.";
            } else {
                $auth = new Auth($usersDb);

                if ($auth->validateUserMasterPassword($pendingUserId, $masterPasswordInput)) {
                    $users = $usersDb->load('users', true);
                    $matchedUser = null;
                    foreach ($users as $candidate) {
                        if (($candidate['id'] ?? '') === $pendingUserId) {
                            $matchedUser = $candidate;
                            break;
                        }
                    }

                    if ($matchedUser) {
                        $_SESSION[SESSION_MASTER_KEY] = $masterPasswordInput;
                        $rememberMe = !empty($_SESSION['pending_master_remember']);

                        $auth->completeLogin($matchedUser, $rememberMe);
                        $rateLimiter->clearLoginAttempts($identifier);
                        $rateLimiter->logAudit(
                            $matchedUser['id'],
                            'login',
                            'auth',
                            null,
                            $clientIp,
                            $_SERVER['HTTP_USER_AGENT'] ?? null,
                            true,
                            'Login successful (2-step)'
                        );

                        unset(
                            $_SESSION['pending_master_user_id'],
                            $_SESSION['pending_master_email'],
                            $_SESSION['pending_master_remember'],
                            $_SESSION['pending_master_expires'],
                            $_SESSION['master_code_error']
                        );

                        // Resolve final redirect destination — honor pre-login redirect target if set.
                        $finalRedirect = APP_URL . '?page=' . Auth::getPostLoginDestination() . '&login=success';
                        if (!empty($_SESSION['post_login_redirect'])) {
                            $finalRedirect = (string)$_SESSION['post_login_redirect'];
                            unset($_SESSION['post_login_redirect']);
                        }

                        ob_end_clean();
                        session_write_close();
                        ?>
                        <!DOCTYPE html>
                        <html>
                        <head>
                            <meta http-equiv="refresh" content="0;url=<?php echo htmlspecialchars($finalRedirect, ENT_QUOTES); ?>">
                            <script>window.location.href = '<?php echo htmlspecialchars($finalRedirect, ENT_QUOTES); ?>';</script>
                            <style>
                                body { font-family: system-ui, sans-serif; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; background: #fff; }
                                .loader { text-align: center; }
                                .spinner { border: 3px solid #f3f3f3; border-top: 3px solid #000; border-radius: 50%; width: 40px; height: 40px; animation: spin 1s linear infinite; margin: 0 auto 20px; }
                                @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
                            </style>
                        </head>
                        <body>
                            <div class="loader">
                                <div class="spinner"></div>
                                <p>Signing in...</p>
                            </div>
                        </body>
                        </html>
                        <?php
                        exit;
                    }

                    $stepError = 'User account could not be loaded';
                } else {
                    $rateLimiter->recordFailedAttempt($identifier);
                    $remaining = $rateLimiter->getRemainingAttempts($identifier);
                    $rateLimiter->logAudit(
                        $pendingUserId,
                        'login',
                        'auth',
                        null,
                        $clientIp,
                        $_SERVER['HTTP_USER_AGENT'] ?? null,
                        false,
                        'Failed master password attempt'
                    );
                    if ($rateLimiter->isLoginBlocked($identifier)) {
                        $stepError = "Too many failed attempts. Please try again in " . ceil(LOGIN_LOCKOUT_SECONDS / 60) . " minutes.";
                    } else {
                        $stepError = "Invalid master password. {$remaining} attempts remaining.";
                    }
                }
            }
        }

        if ($stepError !== '') {
            $_SESSION['master_code_error'] = $stepError;
        }

        ob_end_clean();
    }
}

// Route to appropriate view
$viewFile = VIEWS_PATH . '/' . $page . '.php';

// Check if view exists
if (!file_exists($viewFile)) {
    $page = '404';
    $viewFile = VIEWS_PATH . '/404.php';
}

$realViewPath = realpath($viewFile);
$realViewsRoot = realpath(VIEWS_PATH);
if ($realViewPath === false || $realViewsRoot === false || strpos($realViewPath, $realViewsRoot) !== 0) {
    $page = '404';
    $viewFile = VIEWS_PATH . '/404.php';
}

// Do not route to archived/backup/sample views in production runtime.
$blockedPages = [
    'notes-backup',
    'notes-backup-',
    'notes-backup-before-three-pane',
    'view-notes-backup-before-stitch',
    'notes-three-pane-sample'
];
if (in_array($page, $blockedPages, true)) {
    $page = '404';
    $viewFile = VIEWS_PATH . '/404.php';
}

// Set page title
$pageTitle = ucfirst(str_replace('-', ' ', $page)) . ' | ' . getSiteName();

// Pages that use their own full-page layout (bypass main.php)
$fullPageViews = ['homepage', 'docs', 'setup', 'notes-three-pane-sample'];

// Include the appropriate layout
$authLayoutPages = ['login', 'master-code', 'register', 'thank-you', 'forgot-password', 'reset-password', 'verify-email', 'verification-required', 'privacy', 'terms'];
if (in_array($page, $authLayoutPages, true)) {
    include VIEWS_PATH . '/layouts/auth.php';
} elseif (in_array($page, $fullPageViews)) {
    // Full-page view - include only the view file
    include $viewFile;
} else {
    include VIEWS_PATH . '/layouts/main.php';
}
