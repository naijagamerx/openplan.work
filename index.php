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
$publicPages = ['homepage', 'docs', 'login', 'setup', 'register', 'thank-you', 'forgot-password', 'reset-password', 'verify-email', 'privacy', 'terms'];
$verificationAllowedPages = ['verification-required', 'verify-email', 'logout'];

// Check if should show mobile version
if (DeviceDetector::shouldShowMobile()) {
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

// Redirect to dashboard if authenticated and on login page
if (in_array($page, ['login', 'register', 'thank-you', 'forgot-password', 'reset-password'], true) && $isAuthenticated) {
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
// This must happen before the layout is included
if ($page === 'login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // Start output buffering to capture any HTML output from login processing
    ob_start();

    // Process login form
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $masterPassword = $_POST['master_password'] ?? '';
    $csrfToken = $_POST['csrf_token'] ?? '';
    $rememberMe = !empty($_POST['remember_me']);

    if (!Auth::validateCsrf($csrfToken)) {
        header('Location: ' . APP_URL . '?page=login&reason=invalid_csrf');
        exit;
    }

    if (!empty($email) && !empty($password) && !empty($masterPassword)) {
        // Store master password for this session
        $_SESSION[SESSION_MASTER_KEY] = $masterPassword;

        // Attempt login
        require_once INCLUDES_PATH . '/Database.php';
        require_once INCLUDES_PATH . '/Encryption.php';
        require_once INCLUDES_PATH . '/Auth.php';

        $db = new Database($masterPassword);
        $auth = new Auth($db);
        $result = $auth->login($email, $password, $rememberMe);

        if ($result['success']) {
            // Clear output buffer
            ob_end_clean();

            // Write session data before redirect
            session_write_close();

            // Output redirect page
            ?>
            <!DOCTYPE html>
            <html>
            <head>
                <meta http-equiv="refresh" content="0;url=<?php echo APP_URL; ?>?page=<?php echo e(Auth::getPostLoginDestination()); ?>&login=success">
                <script>
                    window.location.href = '<?php echo APP_URL; ?>?page=<?php echo e(Auth::getPostLoginDestination()); ?>&login=success';
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
                    <p>Signing in...</p>
                </div>
            </body>
            </html>
            <?php
            exit;
        }
    }

    // Clear output buffer and continue with normal page rendering
    ob_end_clean();
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
$authLayoutPages = ['login', 'register', 'thank-you', 'forgot-password', 'reset-password', 'verify-email', 'verification-required', 'privacy', 'terms'];
if (in_array($page, $authLayoutPages, true)) {
    include VIEWS_PATH . '/layouts/auth.php';
} elseif (in_array($page, $fullPageViews)) {
    // Full-page view - include only the view file
    include $viewFile;
} else {
    include VIEWS_PATH . '/layouts/main.php';
}
