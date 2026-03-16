<?php
/**
 * Mobile Registration Page
 */

// Ensure mobile config is loaded
if (!defined('MOBILE_JS_URL')) {
    require_once __DIR__ . '/../config.php';
}

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if (!isRegistrationEnabled()) {
    header('Location: ?page=login');
    exit;
}

// Handle form submission
$error = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Read JSON input if available (for fetch API)
    $jsonInput = json_decode(file_get_contents('php://input'), true);
    if ($jsonInput) {
        $_POST = array_merge($_POST, $jsonInput);
    }

    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $masterPassword = $_POST['master_password'] ?? '';
    $csrfToken = $_POST['csrf_token'] ?? '';

    // Validation
    $validCsrf = false;
    if (!empty($csrfToken) && hash_equals($_SESSION['csrf_token'], $csrfToken)) {
        $validCsrf = true;
    } else {
        // Check headers
        $headerToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!empty($headerToken) && hash_equals($_SESSION['csrf_token'], $headerToken)) {
            $validCsrf = true;
        }
    }

    if (!$validCsrf) {
        $error = 'Invalid request token. Please refresh and try again.';
    } elseif (empty($name) || empty($email) || empty($password) || empty($masterPassword)) {
        $error = 'All fields are required';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email address';
    } elseif (Validator::isDisposableEmail($email)) {
        $error = 'Registration is not allowed from disposable email providers.';
    } elseif (Validator::isPlusAddress($email)) {
        $error = 'Email addresses with tags (plus-addressing) are not allowed.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters';
    } elseif ($password !== $confirmPassword) {
        $error = 'Passwords do not match';
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
                $tokenResult = $auth->issueEmailVerification($newUser['id'], true);
                if (!empty($tokenResult['success']) && !empty($tokenResult['updated']) && !empty($tokenResult['users'])) {
                    $auth->persistUsers($tokenResult['users']);
                    $mailer = new Mailer($userDb->load('config', false));
                    $flash['mail_sent'] = $mailer->sendVerificationEmail($newUser, $tokenResult['token']);
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
            $success = true;

            if (isset($jsonInput)) {
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => true,
                    'redirectUrl' => '?page=thank-you',
                    'destination' => 'thank-you'
                ]);
                exit;
            }

            header('Location: ?page=thank-you');
            exit;
        } else {
            $error = $result['error'];
            if (isset($jsonInput)) {
                header('Content-Type: application/json');
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => $error]);
                exit;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Create Account - <?= htmlspecialchars(getSiteName()) ?></title>

    <link rel="icon" type="image/svg+xml" href="<?= APP_URL ?>/assets/favicons/favicon.svg">
    <link rel="icon" type="image/png" sizes="32x32" href="<?= APP_URL ?>/assets/favicons/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="<?= APP_URL ?>/assets/favicons/favicon-16x16.png">
    <link rel="shortcut icon" href="<?= APP_URL ?>/assets/favicons/favicon.ico">
    <link rel="apple-touch-icon" sizes="180x180" href="<?= APP_URL ?>/assets/favicons/apple-touch-icon.png">

    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@100;200;300;400;500;600;700;800;900&display=swap" rel="stylesheet">

    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: {
                        display: ['Inter', 'sans-serif'],
                    },
                    colors: {
                        primary: '#000000',
                        'background-light': '#ffffff',
                        'background-dark': '#0A0A0A',
                    },
                    borderRadius: {
                        DEFAULT: '0.25rem',
                        lg: '0.5rem',
                        xl: '0.75rem',
                        full: '9999px',
                    },
                },
            },
        }
    </script>

    <style type="text/tailwindcss">
        @layer base {
            body {
                font-family: 'Inter', sans-serif;
            }
        }

        .underlined-input {
            border: none !important;
            border-bottom: 1px solid #e5e7eb !important;
            border-radius: 0 !important;
            padding-left: 0 !important;
            padding-right: 0 !important;
            transition: border-color 0.3s ease;
        }

        .underlined-input:focus {
            border-bottom-color: #000000 !important;
            box-shadow: none !important;
            outline: none !important;
        }

        .auth-visibility-toggle {
            position: absolute;
            right: 0;
            top: 50%;
            transform: translateY(-50%);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 2rem;
            height: 2rem;
            color: #9ca3af;
            transition: color 0.2s ease;
        }

        .auth-visibility-toggle:hover {
            color: #111317;
        }

        .auth-visibility-toggle .icon-hide {
            display: none;
        }

        .auth-visibility-toggle[data-visible="true"] .icon-show {
            display: none;
        }

        .auth-visibility-toggle[data-visible="true"] .icon-hide {
            display: block;
        }
    </style>

    <!-- Theme initialization for auth pages -->
    <script>
    (function initAuthPageTheme() {
        try {
            const savedTheme = localStorage.getItem('mobile-theme');
            const useDark = savedTheme === 'dark';
            document.documentElement.classList.toggle('dark', useDark);
            document.documentElement.classList.toggle('light', !useDark);
            if (useDark) {
                document.body.classList.remove('bg-white');
                document.body.classList.add('bg-zinc-950');
            }
        } catch (error) {
            console.warn('Failed to initialize auth page theme:', error);
        }
    })();
    </script>
</head>
<body class="bg-white dark:bg-zinc-950 min-h-screen font-display">
    <button
        type="button"
        data-theme-toggle
        onclick="toggleLoginTheme()"
        class="fixed top-4 right-4 z-50 bg-white/90 dark:bg-zinc-900/90 border border-gray-200 dark:border-zinc-700 text-black dark:text-white p-2 rounded-full shadow-sm touch-target"
        aria-label="Switch theme"
    >
        <svg class="w-5 h-5 block dark:hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21.752 15.002A9.718 9.718 0 0118 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 003 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 009.002-5.998z"/>
        </svg>
        <svg class="w-5 h-5 hidden dark:block" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v2.25m6.364.386l-1.591 1.591M21 12h-2.25m-.386 6.364l-1.591-1.591M12 18.75V21m-4.773-4.227l-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0z"/>
        </svg>
    </button>

    <div class="bg-[#000000] flex items-center justify-center relative overflow-hidden rounded-b-[2rem] dark:border-b dark:border-zinc-800">
        <div class="absolute inset-0 opacity-10 bg-[radial-gradient(#ffffff_1px,transparent_1px)] [background-size:40px_40px]"></div>

        <div class="relative z-10 flex flex-col items-center gap-6 py-12 px-6">
            <div class="w-12 h-12 text-white flex items-center justify-center">
                <?php echo getSidebarLogoHtml(48); ?>
            </div>

            <h1 class="text-white text-2xl font-black tracking-tighter uppercase text-center leading-none">
                Create Account
            </h1>
        </div>

    </div>

    <div class="flex-1 flex flex-col bg-white dark:bg-zinc-950 px-6 py-10">
        <main class="flex-1 flex flex-col justify-center max-w-md mx-auto w-full">
            <!-- App-Style Notification Banner -->
            <div id="app-notification" class="hidden mb-6 rounded-xl p-4 flex items-center gap-3 transition-all duration-300">
                <div id="notification-icon"></div>
                <p id="notification-message" class="text-sm font-medium flex-1"></p>
                <button onclick="hideNotification()" class="p-1 hover:bg-white/20 rounded transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>

            <?php if ($error): ?>
                <div class="mb-6 p-4 bg-red-50 border-l-4 border-red-500 rounded">
                    <p class="text-sm text-red-700"><?php echo htmlspecialchars($error); ?></p>
                </div>
            <?php endif; ?>

            <form id="register-form" class="flex flex-col gap-6" method="POST">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

                <div class="flex flex-col">
                    <label for="name" class="text-[10px] font-bold uppercase tracking-[0.2em] text-gray-400 dark:text-gray-500 mb-1">
                        Full Name
                    </label>
                    <input
                        id="name"
                        name="name"
                        type="text"
                        required
                        class="underlined-input w-full bg-transparent text-[#111317] dark:text-white h-12 text-base font-normal placeholder:text-gray-300 dark:placeholder:text-gray-500"
                        placeholder="John Doe"
                    >
                </div>

                <div class="flex flex-col">
                    <label for="email" class="text-[10px] font-bold uppercase tracking-[0.2em] text-gray-400 dark:text-gray-500 mb-1">
                        Email Address
                    </label>
                    <input
                        id="email"
                        name="email"
                        type="email"
                        required
                        autocomplete="email"
                        autocapitalize="off"
                        class="underlined-input w-full bg-transparent text-[#111317] dark:text-white h-12 text-base font-normal placeholder:text-gray-300 dark:placeholder:text-gray-500"
                        placeholder="your@email.com"
                    >
                </div>

                <div class="flex flex-col">
                    <label for="password" class="text-[10px] font-bold uppercase tracking-[0.2em] text-gray-400 dark:text-gray-500 mb-1">
                        Password
                    </label>
                    <div class="relative flex items-center">
                        <input
                            id="password"
                            name="password"
                            type="password"
                            required
                            minlength="8"
                            class="underlined-input w-full bg-transparent text-[#111317] dark:text-white h-12 text-base font-normal placeholder:text-gray-300 dark:placeholder:text-gray-500 pr-10"
                            placeholder="Create password"
                        >
                        <button
                            type="button"
                            onclick="togglePasswordVisibility('password', this)"
                            class="auth-visibility-toggle"
                            data-visible="false"
                            aria-label="Show value"
                        >
                            <svg class="icon-show h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M2.458 12C3.732 7.943 7.523 5 12 5s8.268 2.943 9.542 7c-1.274 4.057-5.065 7-9.542 7S3.732 16.057 2.458 12Z"></path><circle cx="12" cy="12" r="3" stroke-width="1.8"></circle></svg>
                            <svg class="icon-hide h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 3l18 18"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M10.58 10.58A2 2 0 0012 14a2 2 0 001.42-.58"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9.88 5.09A10.94 10.94 0 0112 5c4.48 0 8.27 2.94 9.54 7a11.81 11.81 0 01-4.24 5.09"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M6.61 6.61A11.84 11.84 0 002.46 12c1.27 4.06 5.06 7 9.54 7 1.79 0 3.48-.47 4.94-1.29"></path></svg>
                        </button>
                    </div>
                    <p class="text-[10px] text-gray-400 dark:text-gray-500 mt-1">Min 8 chars, uppercase, lowercase, number, special char.</p>
                </div>

                <div class="flex flex-col">
                    <label for="confirm_password" class="text-[10px] font-bold uppercase tracking-[0.2em] text-gray-400 dark:text-gray-500 mb-1">
                        Confirm Password
                    </label>
                    <div class="relative flex items-center">
                        <input
                            id="confirm_password"
                            name="confirm_password"
                            type="password"
                            required
                            class="underlined-input w-full bg-transparent text-[#111317] dark:text-white h-12 text-base font-normal placeholder:text-gray-300 dark:placeholder:text-gray-500 pr-10"
                            placeholder="Repeat password"
                        >
                        <button
                            type="button"
                            onclick="togglePasswordVisibility('confirm_password', this)"
                            class="auth-visibility-toggle"
                            data-visible="false"
                            aria-label="Show value"
                        >
                            <svg class="icon-show h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M2.458 12C3.732 7.943 7.523 5 12 5s8.268 2.943 9.542 7c-1.274 4.057-5.065 7-9.542 7S3.732 16.057 2.458 12Z"></path><circle cx="12" cy="12" r="3" stroke-width="1.8"></circle></svg>
                            <svg class="icon-hide h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 3l18 18"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M10.58 10.58A2 2 0 0012 14a2 2 0 001.42-.58"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9.88 5.09A10.94 10.94 0 0112 5c4.48 0 8.27 2.94 9.54 7a11.81 11.81 0 01-4.24 5.09"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M6.61 6.61A11.84 11.84 0 002.46 12c1.27 4.06 5.06 7 9.54 7 1.79 0 3.48-.47 4.94-1.29"></path></svg>
                        </button>
                    </div>
                </div>

                <div class="flex flex-col">
                    <label for="master_password" class="text-[10px] font-bold uppercase tracking-[0.2em] text-gray-400 dark:text-gray-500 mb-1">
                        Master Password
                    </label>
                    <div class="relative flex items-center">
                        <input
                            id="master_password"
                            name="master_password"
                            type="password"
                            required
                            class="underlined-input w-full bg-transparent text-[#111317] dark:text-white h-12 text-base font-normal placeholder:text-gray-300 dark:placeholder:text-gray-500 pr-10"
                            placeholder="Create security key"
                        >
                        <button
                            type="button"
                            onclick="togglePasswordVisibility('master_password', this)"
                            class="auth-visibility-toggle"
                            data-visible="false"
                            aria-label="Show value"
                        >
                            <svg class="icon-show h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15 7a3 3 0 10-6 0v3"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M7 10h10a1 1 0 011 1v7a1 1 0 01-1 1H7a1 1 0 01-1-1v-7a1 1 0 011-1Z"></path></svg>
                            <svg class="icon-hide h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 3l18 18"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 7.5a3 3 0 015.16-2.1"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M6 10h2m4.59 0H17a1 1 0 011 1v7a1 1 0 01-1 1H7a1 1 0 01-1-1v-7a1 1 0 011-1"></path></svg>
                        </button>
                    </div>
                    <p class="text-[10px] text-gray-400 dark:text-gray-500 mt-1">Required to encrypt your data.</p>
                </div>

                <div class="mt-4">
                    <button
                        type="submit"
                        class="w-full bg-black text-white h-14 rounded-full font-black text-sm uppercase tracking-[0.3em] hover:opacity-90 transition-all flex items-center justify-center gap-2 dark:border dark:border-white/30"
                    >
                        <span>Create Account</span>
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                        </svg>
                    </button>
                </div>
            </form>

            <div class="mt-8 flex flex-col items-center gap-4">
                <p class="text-sm text-gray-500 dark:text-gray-400">Already have an account?</p>
                <a href="?page=login" class="text-sm font-bold uppercase tracking-widest text-black dark:text-white hover:underline">
                    Sign In
                </a>
            </div>
        </main>
    </div>

    <script>
        (function() {
            const path = window.location.pathname;
            // Robust path calculation: strip /mobile (case-insensitive) and trailing slashes
            // Also handles index.php if present
            const cleanPath = path.replace(/\/mobile(\/.*)?$/i, '').replace(/\/index\.php$/i, '');
            window.BASE_PATH = cleanPath.replace(/\/+$/, '');
        })();
        const APP_URL = window.location.origin + window.BASE_PATH;
        const CSRF_TOKEN = '<?= $_SESSION['csrf_token'] ?? '' ?>';
    </script>

    <script>
        // App-Style Notification Banner (for mobile registration)
        let notificationTimeout = null;

        function showNotification(message, type = 'info') {
            const banner = document.getElementById('app-notification');
            const iconContainer = document.getElementById('notification-icon');
            const messageEl = document.getElementById('notification-message');

            if (!banner || !iconContainer || !messageEl) return;

            // Clear existing timeout
            if (notificationTimeout) {
                clearTimeout(notificationTimeout);
            }

            // Set styles based on type
            const isDark = document.documentElement.classList.contains('dark');
            const styles = {
                success: isDark
                    ? 'bg-green-950 border border-green-800 text-green-200'
                    : 'bg-green-50 border border-green-200 text-green-800',
                error: isDark
                    ? 'bg-red-950 border border-red-800 text-red-200'
                    : 'bg-red-50 border border-red-200 text-red-800',
                info: isDark
                    ? 'bg-blue-950 border border-blue-800 text-blue-200'
                    : 'bg-blue-50 border border-blue-200 text-blue-800'
            };

            const icons = {
                success: `<svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>`,
                error: `<svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>`,
                info: `<svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>`
            };

            // Remove old classes and add new ones
            banner.className = `mb-6 rounded-xl p-4 flex items-center gap-3 transition-all duration-300 ${styles[type] || styles.info}`;
            iconContainer.innerHTML = icons[type] || icons.info;
            messageEl.textContent = message;

            // Show banner
            banner.classList.remove('hidden');

            // Auto-hide after 4 seconds
            notificationTimeout = setTimeout(() => {
                hideNotification();
            }, 4000);
        }

        function hideNotification() {
            const banner = document.getElementById('app-notification');
            if (banner) banner.classList.add('hidden');
            if (notificationTimeout) {
                clearTimeout(notificationTimeout);
                notificationTimeout = null;
            }
        }

        // Legacy function - redirects to banner
        function showToast(message, type = 'info') {
            showNotification(message, type);
        }

        function toggleLoginTheme() {
            if (window.Mobile && Mobile.theme && typeof Mobile.theme.toggle === 'function') {
                Mobile.theme.toggle();
                return;
            }

            const root = document.documentElement;
            const useDark = !root.classList.contains('dark');

            // Toggle classes on document root
            root.classList.toggle('dark', useDark);
            root.classList.toggle('light', !useDark);

            // Update body background class
            if (useDark) {
                document.body.classList.remove('bg-white');
                document.body.classList.add('bg-zinc-950');
            } else {
                document.body.classList.remove('bg-zinc-950');
                document.body.classList.add('bg-white');
            }

            // Persist to both keys for compatibility
            try {
                localStorage.setItem('mobile-theme', useDark ? 'dark' : 'light');
                localStorage.setItem('theme', useDark ? 'dark' : 'light');
            } catch (error) {
                console.warn('Unable to persist theme on auth page:', error);
            }
        }

        function togglePasswordVisibility(inputId, button) {
            const input = document.getElementById(inputId);
            const makeVisible = input.type === 'password';
            input.type = makeVisible ? 'text' : 'password';
            button.setAttribute('data-visible', makeVisible ? 'true' : 'false');
            button.setAttribute('aria-label', makeVisible ? 'Hide value' : 'Show value');
        }

        document.addEventListener('DOMContentLoaded', () => {
            document.getElementById('name').focus();

            const registerForm = document.getElementById('register-form');
            if (registerForm) {
                registerForm.addEventListener('submit', async (e) => {
                    e.preventDefault();

                    const formData = new FormData(registerForm);
                    const data = Object.fromEntries(formData.entries());

                    // Client-side validation
                    const password = data.password || '';
                    const confirmPassword = data.confirm_password || '';

                    if (password.length < 8) {
                        showToast('Password must be at least 8 characters', 'error');
                        return;
                    }
                    if (!/[A-Z]/.test(password)) {
                        showToast('Password must contain at least one uppercase letter', 'error');
                        return;
                    }
                    if (!/[a-z]/.test(password)) {
                        showToast('Password must contain at least one lowercase letter', 'error');
                        return;
                    }
                    if (!/[0-9]/.test(password)) {
                        showToast('Password must contain at least one number', 'error');
                        return;
                    }
                    if (!/[!@#$%^&*()_+\-=\[\]{};':"|,.<>\/?`~]/.test(password)) {
                        showToast('Password must contain at least one special character', 'error');
                        return;
                    }
                    if (password !== confirmPassword) {
                        showToast('Passwords do not match', 'error');
                        return;
                    }

                    const submitBtn = registerForm.querySelector('button[type="submit"]');
                    const originalText = submitBtn.innerHTML;
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<span class="inline-flex items-center gap-2"><svg class="h-4 w-4 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 12a8 8 0 018-8"></path></svg><span>Creating...</span></span>';

                    try {
                        const response = await fetch(`${APP_URL}/api/auth.php?action=register`, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-Token': CSRF_TOKEN
                            },
                            body: JSON.stringify(data)
                        });
                        const result = await response.json();

                        if (response.ok && result.success) {
                            showToast('Account created successfully!', 'success');
                            // Small delay to show toast before redirect
                            setTimeout(() => {
                                window.location.href = result.redirectUrl || '?page=thank-you';
                            }, 1200);
                        } else {
                            const msg = result.message || (result.error && result.error.message) || 'Registration failed.';
                            showToast(msg, 'error');
                            submitBtn.disabled = false;
                            submitBtn.innerHTML = originalText;
                        }
                    } catch (error) {
                        console.error('Registration error:', error);
                        showToast('An error occurred. Please try again.', 'error');
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = originalText;
                    }
                });
            }
        });
    </script>
</body>
</html>
