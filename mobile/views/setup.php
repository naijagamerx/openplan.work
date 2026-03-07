<?php
/**
 * Mobile Setup Page
 *
 * First-time account creation page with high-contrast design.
 * Used when no user registry file exists.
 *
 * Features:
 * - Create admin account
 * - Set master password
 * - Configure business settings
 */

if (!defined('MOBILE_JS_URL')) {
    require_once __DIR__ . '/../config.php';
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (userRegistryExists()) {
    header('Location: ?page=login');
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$error = $_GET['error'] ?? '';
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Setup - <?= htmlspecialchars(getSiteName()) ?></title>

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
            theme: {
                extend: {
                    fontFamily: {
                        display: ['Inter', 'sans-serif'],
                    },
                    colors: {
                        primary: '#000000',
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
            Est. 2024 &copy; <?= htmlspecialchars(getSiteName()) ?>
        </div>
    </div>

    <div class="flex-1 flex flex-col bg-white px-6 py-10">
        <main class="flex-1 flex flex-col justify-center max-w-md mx-auto w-full">
            <div class="mb-8 text-center">
                <h2 class="text-2xl font-bold text-gray-900 mb-2">Create Your Account</h2>
                <p class="text-sm text-gray-500">Set up your admin account and choose how this installation should run.</p>
            </div>

            <?php if ($error): ?>
                <div class="mb-6 p-4 bg-red-50 border-l-4 border-red-500 rounded">
                    <p class="text-sm text-red-700">
                        <?php
                        $errorMessages = [
                            'passwords_mismatch' => 'Passwords do not match.',
                            'weak_password' => 'Password is too weak. Use at least 8 characters.',
                            'email_exists' => 'An account with this email already exists.',
                            'setup_failed' => 'Setup failed. Please try again.',
                        ];
                        echo htmlspecialchars($errorMessages[$error] ?? 'An error occurred. Please try again.');
                        ?>
                    </p>
                </div>
            <?php endif; ?>

            <form id="setup-form" class="flex flex-col gap-6">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                <input type="hidden" name="action" value="register">

                <div class="flex flex-col">
                    <label for="name" class="text-[10px] font-bold uppercase tracking-[0.2em] text-gray-400 mb-1">
                        Full Name
                    </label>
                    <input
                        id="name"
                        name="name"
                        type="text"
                        required
                        autocomplete="name"
                        class="underlined-input w-full bg-transparent text-[#111317] h-12 text-base font-normal placeholder:text-gray-300"
                        placeholder="John Doe"
                    >
                </div>

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

                <div class="flex flex-col gap-3">
                    <label class="text-[10px] font-bold uppercase tracking-[0.2em] text-gray-400 mb-1">
                        Installation Mode
                    </label>
                    <label class="border border-gray-200 rounded-xl p-4">
                        <input type="radio" name="install_mode" value="single_user" class="mr-2">
                        <span class="font-semibold text-sm text-gray-900">Single User</span>
                        <p class="text-[10px] text-gray-500 mt-2">Public registration stays off after setup.</p>
                    </label>
                    <label class="border border-gray-200 rounded-xl p-4">
                        <input type="radio" name="install_mode" value="multi_user" class="mr-2" checked>
                        <span class="font-semibold text-sm text-gray-900">Multi User</span>
                        <p class="text-[10px] text-gray-500 mt-2">Keep registration available for more users later.</p>
                    </label>
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
                            autocomplete="new-password"
                            minlength="8"
                            class="underlined-input w-full bg-transparent text-[#111317] h-12 text-base font-normal placeholder:text-gray-300 pr-10"
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
                </div>

                <div class="flex flex-col">
                    <label for="confirm_password" class="text-[10px] font-bold uppercase tracking-[0.2em] text-gray-400 mb-1">
                        Confirm Password
                    </label>
                    <div class="relative flex items-center">
                        <input
                            id="confirm_password"
                            name="confirm_password"
                            type="password"
                            required
                            autocomplete="new-password"
                            minlength="8"
                            class="underlined-input w-full bg-transparent text-[#111317] h-12 text-base font-normal placeholder:text-gray-300 pr-10"
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
                    <label for="master_password" class="text-[10px] font-bold uppercase tracking-[0.2em] text-gray-400 mb-1">
                        Master Password
                    </label>
                    <div class="relative flex items-center">
                        <input
                            id="master_password"
                            name="master_password"
                            type="password"
                            required
                            autocomplete="new-password"
                            class="underlined-input w-full bg-transparent text-[#111317] h-12 text-base font-normal placeholder:text-gray-300 pr-10"
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
                    <p class="text-[9px] text-gray-400 mt-1">Used to encrypt your data. Don't lose it.</p>
                </div>

                <div class="mt-4">
                    <button
                        type="submit"
                        class="w-full bg-black text-white h-14 rounded-full font-black text-sm uppercase tracking-[0.3em] hover:opacity-90 transition-all flex items-center justify-center gap-2"
                    >
                        <span>Create Account</span>
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path>
                        </svg>
                    </button>
                </div>
            </form>

            <div class="mt-8 p-4 bg-gray-50 rounded-lg border border-gray-200">
                <div class="flex items-start gap-3">
                    <svg class="w-5 h-5 text-gray-400 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                    </svg>
                    <div>
                        <p class="text-xs text-gray-600 font-medium mb-1">Data Encryption</p>
                        <p class="text-[10px] text-gray-500">Your data is encrypted with AES-256-GCM. The master password is required to decrypt your data. Store it safely.</p>
                    </div>
                </div>
            </div>
        </main>

        <footer class="py-6 flex justify-between items-center text-[10px] text-gray-300 tracking-widest uppercase">
            <span>v1.0.0</span>
            <div class="flex gap-4">
                <a href="#" class="hover:text-black transition-colors">Privacy</a>
                <a href="#" class="hover:text-black transition-colors">Terms</a>
            </div>
        </footer>
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

    <script src="<?= MOBILE_JS_URL ?>/mobile.js"></script>

    <script>
        function togglePasswordVisibility(inputId, button) {
            const input = document.getElementById(inputId);
            const makeVisible = input.type === 'password';
            input.type = makeVisible ? 'text' : 'password';
            button.setAttribute('data-visible', makeVisible ? 'true' : 'false');
            button.setAttribute('aria-label', makeVisible ? 'Hide value' : 'Show value');
        }

        document.addEventListener('DOMContentLoaded', () => {
            document.getElementById('name').focus();

            const setupForm = document.getElementById('setup-form');
            if (setupForm) {
                setupForm.addEventListener('submit', async (e) => {
                    e.preventDefault();

                    const formData = new FormData(setupForm);
                    const data = Object.fromEntries(formData.entries());

                    const password = document.getElementById('password').value;
                    const confirmPassword = document.getElementById('confirm_password').value;

                    if (password !== confirmPassword) {
                        showError('Passwords do not match. Please try again.');
                        return;
                    }

                    const submitBtn = setupForm.querySelector('button[type="submit"]');
                    const originalText = submitBtn.innerHTML;
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<span class="inline-flex items-center gap-2"><svg class="h-4 w-4 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 12a8 8 0 018-8"></path></svg><span>Creating Account...</span></span>';

                    try {
                        const result = await App.api.post('api/auth.php?action=register', data);

                        if (result.success) {
                            window.location.href = result.data?.redirectUrl || '?page=thank-you';
                        } else {
                            showError(result.message || 'Setup failed. Please try again.');
                            submitBtn.disabled = false;
                            submitBtn.innerHTML = originalText;
                        }
                    } catch (error) {
                        console.error('Setup error:', error);

                        let errorMsg = 'An error occurred. Please try again.';
                        if (error.response?.error) {
                            errorMsg = error.response.error.message || error.response.error || errorMsg;
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
                const existingError = document.getElementById('setup-error-message');
                if (existingError) {
                    existingError.remove();
                }

                const errorDiv = document.createElement('div');
                errorDiv.id = 'setup-error-message';
                errorDiv.className = 'mb-6 p-4 bg-red-50 border-l-4 border-red-500 rounded animate-fade-in';
                errorDiv.innerHTML = `
                    <p class="text-sm text-red-700">${escapeHtml(message)}</p>
                `;

                setupForm.parentNode.insertBefore(errorDiv, setupForm);
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
