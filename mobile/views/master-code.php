<?php
/**
 * Mobile Master Code Page — step 2 of 2-step login.
 * Reads email/password/remember_me from sessionStorage (set by step 1) and submits
 * all three together to api/auth.php to preserve the existing one-shot API contract.
 */

if (!defined('MOBILE_JS_URL')) {
    require_once __DIR__ . '/../config.php';
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (Auth::check()) {
    header('Location: ?page=' . Auth::getPostLoginDestination());
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Master Code - <?= htmlspecialchars(getSiteName()) ?></title>

    <?php if (file_exists(__DIR__ . '/../../assets/favicons/favicon.svg')): ?>
    <link rel="icon" type="image/svg+xml" href="<?= APP_URL ?>/assets/favicons/favicon.svg?v=<?= filemtime(__DIR__ . '/../../assets/favicons/favicon.svg') ?>">
    <?php endif; ?>

    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@100;200;300;400;500;600;700;800;900&display=swap" rel="stylesheet">

    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: { display: ['Inter', 'sans-serif'] },
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
            body { font-family: 'Inter', sans-serif; }
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
            position: absolute; right: 0; top: 50%;
            transform: translateY(-50%);
            display: inline-flex; align-items: center; justify-content: center;
            width: 2rem; height: 2rem;
            color: #9ca3af;
            transition: color 0.2s ease;
        }
        .auth-visibility-toggle:hover { color: #111317; }
        .auth-visibility-toggle .icon-hide { display: none; }
        .auth-visibility-toggle[data-visible="true"] .icon-show { display: none; }
        .auth-visibility-toggle[data-visible="true"] .icon-hide { display: block; }
    </style>

    <script>
    (function initAuthPageTheme() {
        try {
            const savedTheme = localStorage.getItem('mobile-theme');
            const useDark = savedTheme === 'dark';
            document.documentElement.classList.toggle('dark', useDark);
            document.documentElement.classList.toggle('light', !useDark);
            if (useDark) {
                document.body && document.body.classList.remove('bg-white');
                document.body && document.body.classList.add('bg-zinc-950');
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
        class="fixed right-4 z-50 bg-white/90 dark:bg-zinc-900/90 border border-gray-200 dark:border-zinc-700 text-black dark:text-white p-2 rounded-full shadow-sm touch-target"
        style="top: calc(1rem + env(safe-area-inset-top))"
        aria-label="Switch theme"
    >
        <svg class="w-5 h-5 block dark:hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21.752 15.002A9.718 9.718 0 0118 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 003 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 009.002-5.998z"/>
        </svg>
        <svg class="w-5 h-5 hidden dark:block" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v2.25m6.364.386l-1.591 1.591M21 12h-2.25m-.386 6.364l-1.591-1.591M12 18.75V21m-4.773-4.227l-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0z"/>
        </svg>
    </button>

    <div class="bg-[#000000] flex items-center justify-center relative overflow-hidden rounded-b-[2rem] dark:border-b dark:border-zinc-800" style="padding-top: env(safe-area-inset-top)">
        <div class="absolute inset-0 opacity-10 bg-[radial-gradient(#ffffff_1px,transparent_1px)] [background-size:40px_40px]"></div>
        <div class="relative z-10 flex flex-col items-center gap-6 py-16 px-6">
            <div class="w-16 h-16 text-white flex items-center justify-center">
                <?php echo getSidebarLogoHtml(64); ?>
            </div>
            <h1 class="text-white text-4xl font-black tracking-tighter uppercase text-center leading-none">
                Master Code
            </h1>
            <p class="text-white/40 text-[10px] tracking-[0.4em] uppercase font-light">
                Step 2 of 2
            </p>
        </div>
    </div>

    <div class="flex-1 flex flex-col bg-white dark:bg-zinc-950 px-6 py-10" style="padding-bottom: max(2.5rem, calc(2.5rem + env(safe-area-inset-bottom)))">
        <main class="flex-1 flex flex-col justify-center max-w-md mx-auto w-full">

            <div id="app-notification" class="hidden mb-6 rounded-xl p-4 flex items-center gap-3 transition-all duration-300">
                <div id="notification-icon"></div>
                <p id="notification-message" class="text-sm font-medium flex-1"></p>
                <button onclick="hideNotification()" class="p-1 hover:bg-white/20 rounded transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>

            <div class="mb-8">
                <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-gray-400 dark:text-gray-500">Signed in as</p>
                <p id="pending-email" class="text-base font-semibold text-gray-900 dark:text-white mt-1">&mdash;</p>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-4">Enter your master password to decrypt your dashboard.</p>
            </div>

            <form id="master-code-form" class="flex flex-col gap-8">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

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
                            autocomplete="current-password"
                            class="underlined-input w-full bg-transparent text-[#111317] dark:text-white h-12 text-base font-normal placeholder:text-gray-300 dark:placeholder:text-gray-500 pr-10"
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
                        class="w-full bg-black text-white h-14 rounded-full font-black text-sm uppercase tracking-[0.3em] hover:opacity-90 transition-all flex items-center justify-center gap-2 dark:border dark:border-white/30"
                    >
                        <span>Sign In</span>
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path>
                        </svg>
                    </button>
                </div>
            </form>

            <div class="mt-8 flex flex-col items-center gap-4">
                <a href="?page=login" class="text-sm font-bold uppercase tracking-widest text-black dark:text-white hover:underline inline-flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                    </svg>
                    Back to Sign In
                </a>
            </div>
        </main>
    </div>

    <script>
        (function() {
            const path = window.location.pathname;
            let basePath = path.replace(/\/index\.php$/i, '');
            basePath = basePath.replace(/\/+$/, '');
            basePath = basePath.replace(/\/mobile$/i, '');
            window.BASE_PATH = basePath;
        })();
        const APP_URL = window.location.origin + window.BASE_PATH;
        const CSRF_TOKEN = '<?= $_SESSION['csrf_token'] ?? '' ?>';
        const MOBILE_VERSION = true;
    </script>

    <script>
        let notificationTimeout = null;

        function showNotification(message, type = 'info') {
            const banner = document.getElementById('app-notification');
            const iconContainer = document.getElementById('notification-icon');
            const messageEl = document.getElementById('notification-message');
            if (!banner || !iconContainer || !messageEl) return;
            if (notificationTimeout) clearTimeout(notificationTimeout);

            const isDark = document.documentElement.classList.contains('dark');
            const styles = {
                success: isDark ? 'bg-green-950 border border-green-800 text-green-200' : 'bg-green-50 border border-green-200 text-green-800',
                error: isDark ? 'bg-red-950 border border-red-800 text-red-200' : 'bg-red-50 border border-red-200 text-red-800',
                info: isDark ? 'bg-blue-950 border border-blue-800 text-blue-200' : 'bg-blue-50 border border-blue-200 text-blue-800'
            };
            const icons = {
                success: `<svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>`,
                error: `<svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>`,
                info: `<svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>`
            };
            banner.className = `mb-6 rounded-xl p-4 flex items-center gap-3 transition-all duration-300 ${styles[type] || styles.info}`;
            iconContainer.innerHTML = icons[type] || icons.info;
            messageEl.textContent = message;
            banner.classList.remove('hidden');
            notificationTimeout = setTimeout(hideNotification, 4000);
        }

        function hideNotification() {
            const banner = document.getElementById('app-notification');
            if (banner) banner.classList.add('hidden');
            if (notificationTimeout) {
                clearTimeout(notificationTimeout);
                notificationTimeout = null;
            }
        }

        function toggleLoginTheme() {
            if (window.Mobile && Mobile.theme && typeof Mobile.theme.toggle === 'function') {
                Mobile.theme.toggle();
                return;
            }
            const root = document.documentElement;
            const useDark = !root.classList.contains('dark');
            root.classList.toggle('dark', useDark);
            root.classList.toggle('light', !useDark);
            if (useDark) {
                document.body.classList.remove('bg-white');
                document.body.classList.add('bg-zinc-950');
            } else {
                document.body.classList.remove('bg-zinc-950');
                document.body.classList.add('bg-white');
            }
            try {
                localStorage.setItem('mobile-theme', useDark ? 'dark' : 'light');
                localStorage.setItem('theme', useDark ? 'dark' : 'light');
            } catch (error) {
                console.warn('Unable to persist theme:', error);
            }
        }

        function togglePasswordVisibility(inputId, button) {
            const input = document.getElementById(inputId);
            const makeVisible = input.type === 'password';
            input.type = makeVisible ? 'text' : 'password';
            button.setAttribute('data-visible', makeVisible ? 'true' : 'false');
            button.setAttribute('aria-label', makeVisible ? 'Hide value' : 'Show value');
        }

        function readPendingLogin() {
            try {
                const raw = sessionStorage.getItem('pending_login');
                if (!raw) return null;
                const parsed = JSON.parse(raw);
                if (!parsed || !parsed.email || !parsed.password) return null;
                if (parsed.expires && Date.now() > parsed.expires) return null;
                return parsed;
            } catch (err) {
                console.warn('Unable to read pending login:', err);
                return null;
            }
        }

        function clearPendingLogin() {
            try { sessionStorage.removeItem('pending_login'); } catch (err) {}
        }

        document.addEventListener('DOMContentLoaded', () => {
            const pending = readPendingLogin();
            if (!pending) {
                window.location.href = '?page=login&reason=session_expired';
                return;
            }

            const emailEl = document.getElementById('pending-email');
            if (emailEl) emailEl.textContent = pending.email;

            const masterInput = document.getElementById('master_password');
            if (masterInput) masterInput.focus();

            const form = document.getElementById('master-code-form');
            if (!form) return;

            form.addEventListener('submit', async (e) => {
                e.preventDefault();

                const pendingNow = readPendingLogin();
                if (!pendingNow) {
                    clearPendingLogin();
                    showNotification('Session expired. Please sign in again.', 'error');
                    setTimeout(() => { window.location.href = '?page=login&reason=session_expired'; }, 800);
                    return;
                }

                const masterPassword = document.getElementById('master_password').value || '';
                if (masterPassword === '') {
                    showNotification('Master password is required.', 'error');
                    return;
                }

                const submitBtn = form.querySelector('button[type="submit"]');
                const originalText = submitBtn.innerHTML;
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<span class="inline-flex items-center gap-2"><svg class="h-4 w-4 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 12a8 8 0 018-8"></path></svg><span>Signing In...</span></span>';

                try {
                    const payload = {
                        email: pendingNow.email,
                        password: pendingNow.password,
                        master_password: masterPassword,
                        remember_me: !!pendingNow.remember_me,
                        csrf_token: CSRF_TOKEN
                    };

                    const response = await fetch(`${APP_URL}/api/auth.php?action=login`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-Token': CSRF_TOKEN
                        },
                        body: JSON.stringify(payload)
                    });

                    const contentType = response.headers.get('content-type');
                    let result;
                    if (contentType && contentType.includes('application/json')) {
                        result = await response.json();
                    } else {
                        const text = await response.text();
                        console.error('Non-JSON response:', text);
                        throw new Error('Server returned an unexpected response format');
                    }

                    if (response.ok && result.success) {
                        clearPendingLogin();
                        showNotification('Login successful!', 'success');
                        const destination = result.data?.destination || 'dashboard';
                        setTimeout(() => { window.location.href = `?page=${destination}`; }, 500);
                    } else {
                        const msg = result.error?.message || result.message || 'Login failed. Please try again.';
                        throw new Error(msg);
                    }
                } catch (error) {
                    console.error('Master code submit error:', error);
                    showNotification(error.message || 'An error occurred. Please try again.', 'error');
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalText;
                }
            });
        });
    </script>
</body>
</html>
