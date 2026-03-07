<?php
/**
 * Mobile Login Page
 *
 * High-contrast split-screen login design adapted from Stitch.
 * Mobile-optimized with stacked layout.
 *
 * Uses:
 * - Device detection to determine if showing mobile version
 * - Session-based authentication with CSRF protection
 * - Email, Password, and Master Password fields
 * - Underlined input design pattern
 */

// Ensure mobile config is loaded (handles both direct access and inclusion from main index.php)
if (!defined('MOBILE_JS_URL')) {
    require_once __DIR__ . '/../config.php';
}

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if already logged in (includes persistent token restore).
if (Auth::check()) {
    header('Location: ?page=' . Auth::getPostLoginDestination());
    exit;
}

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Get error message from URL if present
$error = $_GET['error'] ?? '';
$success = $_GET['success'] ?? '';
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Sign In - <?= htmlspecialchars(getSiteName()) ?></title>

    <!-- Favicons -->
    <link rel="icon" type="image/png" sizes="32x32" href="<?= APP_URL ?>/assets/favicons/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="<?= APP_URL ?>/assets/favicons/favicon-16x16.png">
    <link rel="shortcut icon" href="<?= APP_URL ?>/assets/favicons/favicon.ico">
    <link rel="apple-touch-icon" sizes="180x180" href="<?= APP_URL ?>/assets/favicons/apple-touch-icon.png">

    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>

    <!-- Google Fonts - Inter -->
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
</head>
<body class="bg-white min-h-screen font-display">
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

    <div class="bg-[#000000] flex items-center justify-center relative overflow-hidden rounded-b-[2rem]">
        <div class="absolute inset-0 opacity-10 bg-[radial-gradient(#ffffff_1px,transparent_1px)] [background-size:40px_40px]"></div>

        <div class="relative z-10 flex flex-col items-center gap-6 py-16 px-6">
            <div class="w-16 h-16 text-white">
                <svg fill="none" viewBox="0 0 48 48" xmlns="http://www.w3.org/2000/svg">
                    <path clip-rule="evenodd" d="M24 4H6V17.3333V30.6667H24V44H42V30.6667V17.3333H24V44H42V30.6667V17.3333H24V4Z" fill="currentColor" fill-rule="evenodd" opacity="0.1"></path>
                    <path d="M24 4H6V17.3333H24V4Z" fill="white"></path>
                    <path d="M42 30.6667H24V44H42V30.6667Z" fill="white"></path>
                    <path d="M24 17.3333H42V30.6667H24V17.3333Z" fill="white" opacity="0.5"></path>
                    <path d="M6 17.3333V30.6667H24V17.3333H6Z" fill="white" opacity="0.5"></path>
                </svg>
            </div>

            <h1 class="text-white text-4xl font-black tracking-tighter uppercase text-center leading-none">
                <?= htmlspecialchars(getSiteName()) ?>
            </h1>

            <p class="text-white/40 text-[10px] tracking-[0.4em] uppercase font-light">
                <?= htmlspecialchars(getSiteName()) ?>
            </p>
        </div>

        <div class="absolute bottom-6 left-1/2 -translate-x-1/2 text-white/20 text-[8px] tracking-widest uppercase">
            Est. 2024 &copy; Mobile
        </div>
    </div>

    <div class="flex-1 flex flex-col bg-white px-6 py-10">
        <main class="flex-1 flex flex-col justify-center max-w-md mx-auto w-full">
            <?php if ($error): ?>
                <div class="mb-6 p-4 bg-red-50 border-l-4 border-red-500 rounded">
                    <p class="text-sm text-red-700">
                        <?php
                        $errorMessages = [
                            'invalid_credentials' => 'Invalid email or password.',
                            'session_expired' => 'Your session has expired. Please log in again.',
                            'session_timeout' => 'Your session timed out due to inactivity.',
                            'session_missing' => 'Your login session is no longer available on this device.',
                            'token_restore_failed' => 'Your persistent session could not be restored. Please sign in again.',
                            'login_required' => 'Please log in to access this page.',
                        ];
                        echo htmlspecialchars($errorMessages[$error] ?? 'An error occurred. Please try again.');
                        ?>
                    </p>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="mb-6 p-4 bg-green-50 border-l-4 border-green-500 rounded">
                    <p class="text-sm text-green-700">
                        <?php
                        $successMessages = [
                            'logged_out' => 'You have been logged out successfully.',
                            'account_created' => 'Account created successfully. Please log in.',
                        ];
                        echo htmlspecialchars($successMessages[$success] ?? 'Operation completed.');
                        ?>
                    </p>
                </div>
            <?php endif; ?>

            <form id="login-form" class="flex flex-col gap-8">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                <input type="hidden" name="action" value="login">

                <div class="flex flex-col">
                    <label for="email" class="text-[10px] font-bold uppercase tracking-[0.2em] text-gray-400 mb-1">
                        Email Address
                    </label>
                    <input
                        id="email"
                        name="email"
                        type="email"
                        required
                        autocomplete="email"
                        autocapitalize="off"
                        class="underlined-input w-full bg-transparent text-[#111317] h-12 text-base font-normal placeholder:text-gray-300"
                        placeholder="your@email.com"
                    >
                </div>

                <div class="flex flex-col">
                    <label for="password" class="text-[10px] font-bold uppercase tracking-[0.2em] text-gray-400 mb-1">
                        Password
                    </label>
                    <div class="relative flex items-center">
                        <input
                            id="password"
                            name="password"
                            type="password"
                            required
                            autocomplete="current-password"
                            class="underlined-input w-full bg-transparent text-[#111317] h-12 text-base font-normal placeholder:text-gray-300 pr-10"
                            placeholder="Enter password"
                        >
                        <button
                            type="button"
                            onclick="togglePasswordVisibility('password', this)"
                            class="auth-visibility-toggle"
                            data-visible="false"
                            aria-label="Show value"
                        >
                            <svg class="icon-show h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M2.458 12C3.732 7.943 7.523 5 12 5s8.268 2.943 9.542 7c-1.274 4.057-5.065 7-9.542 7S3.732 16.057 2.458 12Z"></path>
                                <circle cx="12" cy="12" r="3" stroke-width="1.8"></circle>
                            </svg>
                            <svg class="icon-hide h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 3l18 18"></path>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M10.58 10.58A2 2 0 0012 14a2 2 0 001.42-.58"></path>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9.88 5.09A10.94 10.94 0 0112 5c4.48 0 8.27 2.94 9.54 7a11.81 11.81 0 01-4.24 5.09"></path>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M6.61 6.61A11.84 11.84 0 002.46 12c1.27 4.06 5.06 7 9.54 7 1.79 0 3.48-.47 4.94-1.29"></path>
                            </svg>
                        </button>
                    </div>
                </div>

                <div class="flex flex-col">
                    <label for="master_password" class="text-[10px] font-bold uppercase tracking-[0.2em] text-gray-400 mb-1">
                        Master Password
                    </label>
                    <div class="relative flex items-center">
                        <input
                            id="master_password"
                            name="master_password"
                            type="password"
                            required
                            autocomplete="current-password"
                            class="underlined-input w-full bg-transparent text-[#111317] h-12 text-base font-normal placeholder:text-gray-300 pr-10"
                            placeholder="Enter security key"
                        >
                        <button
                            type="button"
                            onclick="togglePasswordVisibility('master_password', this)"
                            class="auth-visibility-toggle"
                            data-visible="false"
                            aria-label="Show value"
                        >
                            <svg class="icon-show h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15 7a3 3 0 10-6 0v3"></path>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M7 10h10a1 1 0 011 1v7a1 1 0 01-1 1H7a1 1 0 01-1-1v-7a1 1 0 011-1Z"></path>
                            </svg>
                            <svg class="icon-hide h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 3l18 18"></path>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 7.5a3 3 0 015.16-2.1"></path>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M6 10h2m4.59 0H17a1 1 0 011 1v7a1 1 0 01-1 1H7a1 1 0 01-1-1v-7a1 1 0 011-1"></path>
                            </svg>
                        </button>
                    </div>
                </div>

                <div class="mt-4">
                    <button
                        type="submit"
                        class="w-full bg-black text-white h-14 rounded-full font-black text-sm uppercase tracking-[0.3em] hover:opacity-90 transition-all flex items-center justify-center gap-2"
                    >
                        <span>Sign In</span>
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path>
                        </svg>
                    </button>
                </div>
            </form>

            <div class="mt-8 flex flex-col items-center gap-4">
                <?php if (isRegistrationEnabled()): ?>
                <p class="text-sm text-gray-500">Don't have an account?</p>
                <a href="?page=register" class="text-sm font-bold uppercase tracking-widest text-black hover:underline">
                    Create Account
                </a>
                <?php else: ?>
                <p class="text-sm text-gray-500 text-center">This installation is running in single-user mode. Public registration is disabled.</p>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script>
        (function() {
            const path = window.location.pathname;
            const baseMatch = path.match(/^(\/[^\/]*?)?\//);
            window.BASE_PATH = baseMatch ? baseMatch[1] || '' : '';
        })();
        const APP_URL = window.location.origin + window.BASE_PATH;
        const CSRF_TOKEN = '<?= $_SESSION['csrf_token'] ?? '' ?>';
        const MOBILE_VERSION = true;
    </script>

    <script>
        function toggleLoginTheme() {
            if (window.Mobile && Mobile.theme && typeof Mobile.theme.toggle === 'function') {
                Mobile.theme.toggle();
                return;
            }

            const root = document.documentElement;
            const useDark = !root.classList.contains('dark');
            root.classList.toggle('dark', useDark);
            root.classList.toggle('light', !useDark);

            try {
                localStorage.setItem('mobile-theme', useDark ? 'dark' : 'light');
                localStorage.setItem('theme', useDark ? 'dark' : 'light');
            } catch (error) {
                console.warn('Unable to persist theme on login page:', error);
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
            document.getElementById('email').focus();

            const loginForm = document.getElementById('login-form');
            if (loginForm) {
                loginForm.addEventListener('submit', async (e) => {
                    e.preventDefault();

                    const formData = new FormData(loginForm);
                    const data = Object.fromEntries(formData.entries());

                    console.log('Login attempt:', { email: data.email, csrf_token: data.csrf_token ? 'present' : 'missing' });
                    console.log('APP_URL:', APP_URL);

                    const submitBtn = loginForm.querySelector('button[type="submit"]');
                    const originalText = submitBtn.innerHTML;
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<span class="inline-flex items-center gap-2"><svg class="h-4 w-4 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 12a8 8 0 018-8"></path></svg><span>Signing In...</span></span>';

                    try {
                        const response = await fetch(`${APP_URL}/api/auth.php?action=login`, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-Token': CSRF_TOKEN
                            },
                            body: JSON.stringify(data)
                        });
                        const result = await response.json();
                        console.log('Login response:', result);

                        if (response.ok && result.success) {
                            const destination = result.data?.destination || 'dashboard';
                            window.location.href = `?page=${destination}`;
                        } else {
                            const msg = result?.error?.message || result.message || 'Login failed. Please try again.';
                            showError(msg);
                            submitBtn.disabled = false;
                            submitBtn.innerHTML = originalText;
                        }
                    } catch (error) {
                        console.error('Login error:', error);
                        console.error('Error response:', error.response);

                        let errorMsg = 'An error occurred. Please try again.';
                        if (error.response?.error?.message) {
                            errorMsg = error.response.error.message;
                        } else if (error.response?.error) {
                            errorMsg = error.response.error;
                        } else if (error.message) {
                            errorMsg = error.message;
                        }

                        showError(errorMsg);
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = originalText;
                    }
                });
            }

            function showError(message) {
                const existingError = document.getElementById('login-error-message');
                if (existingError) {
                    existingError.remove();
                }

                const errorDiv = document.createElement('div');
                errorDiv.id = 'login-error-message';
                errorDiv.className = 'mb-6 p-4 bg-red-50 border-l-4 border-red-500 rounded animate-fade-in';
                errorDiv.innerHTML = `
                    <p class="text-sm text-red-700">${escapeHtml(message)}</p>
                `;

                loginForm.parentNode.insertBefore(errorDiv, loginForm);
            }

            function escapeHtml(text) {
                const map = {
                    '&': '&amp;',
                    '<': '&lt;',
                    '>': '&gt;',
                    '"': '&quot;',
                    "'": '&#039;'
                };
                return text.replace(/[&<>"']/g, m => map[m]);
            }
        });
    </script>
</body>
</html>
