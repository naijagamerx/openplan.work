<?php
/**
 * Mobile Settings Page - LazyMan Tools
 *
 * Mobile-optimized settings page with touch-friendly forms.
 * Uses Heroicons inline SVG (no Material Symbols).
 * Integrates with existing LazyMan backend.
 */

// Ensure user is authenticated
if (!Auth::check()) {
    header('Location: ?page=login');
    exit;
}

// Get master password from session
$masterPassword = getMasterPassword();

// Check if master password is available
if (empty($masterPassword)) {
    die('<html><body style="font-family: sans-serif; padding: 20px; text-align: center;">
        <h2>Session Error</h2>
        <p>Your session has expired or the master password is not available.</p>
        <p>Please <a href="?page=login">log in again</a>.</p>
    </body></html>');
}

// Load data
try {
    $db = new Database($masterPassword, Auth::userId());
} catch (Exception $e) {
    die('<html><body style="font-family: sans-serif; padding: 20px; text-align: center;">
        <h2>Database Error</h2>
        <p>Failed to load data: ' . htmlspecialchars($e->getMessage()) . '</p>
        <p><a href="?page=login">Return to login</a></p>
    </body></html>');
}

$config = $db->load('config');
$user = Auth::user();
$authService = new Auth($db);
$sessionTimeoutPreference = Auth::normalizeSessionTimeoutPreference($_SESSION['session_timeout_preference'] ?? null);

$success = '';
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['section'])) {
    $section = $_POST['section'];

    // Validate CSRF
    if (!Auth::validateCsrf($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request';
    } else {
        switch ($section) {
            case 'site':
                $config['siteName'] = trim($_POST['siteName'] ?? '');
                $db->save('config', $config);
                $success = 'Site settings saved';
                break;

            case 'business':
                $config['businessName'] = trim($_POST['businessName'] ?? '');
                $config['businessEmail'] = trim($_POST['businessEmail'] ?? '');
                $config['businessPhone'] = trim($_POST['businessPhone'] ?? '');
                $config['businessAddress'] = trim($_POST['businessAddress'] ?? '');
                $config['currency'] = $_POST['currency'] ?? 'USD';
                $config['taxRate'] = floatval($_POST['taxRate'] ?? 0);
                $db->save('config', $config);
                $success = 'Business settings saved';
                break;

            case 'api':
                $config['groqApiKey'] = trim($_POST['groqApiKey'] ?? '');
                $config['openrouterApiKey'] = trim($_POST['openrouterApiKey'] ?? '');
                $db->save('config', $config);
                $success = 'API keys saved';
                break;

            case 'water':
                $config['waterGoal'] = (int)($_POST['waterGoal'] ?? 8);
                $config['waterReminderInterval'] = (int)($_POST['waterReminderInterval'] ?? 60);
                $config['waterNotificationsEnabled'] = isset($_POST['waterNotificationsEnabled']);
                $db->save('config', $config);
                $success = 'Water settings saved';
                break;

            case 'notifications':
                $config['notificationSoundEnabled'] = isset($_POST['notificationSoundEnabled']);
                $config['notificationSound'] = $_POST['notificationSound'] ?? 'default';
                $config['notificationVolume'] = (int)($_POST['notificationVolume'] ?? 70);
                $config['overdueAlertEnabled'] = isset($_POST['overdueAlertEnabled']);
                $config['dueSoonAlertEnabled'] = isset($_POST['dueSoonAlertEnabled']);
                $config['notStartedAlertEnabled'] = isset($_POST['notStartedAlertEnabled']);
                $db->save('config', $config);
                $success = 'Notification settings saved';
                break;

            case 'session':
                if (!$user || empty($user['id'])) {
                    $error = 'Session expired. Please log in again.';
                    break;
                }

                $selectedPreference = Auth::normalizeSessionTimeoutPreference($_POST['sessionTimeoutPreference'] ?? null);
                if ($authService->updateSessionTimeoutPreference($user['id'], $selectedPreference)) {
                    $sessionTimeoutPreference = $selectedPreference;
                    $success = 'Session timeout settings saved';
                } else {
                    $error = 'Failed to save session timeout settings';
                }
                break;
        }
    }

    // Reload config after save
    $config = $db->load('config');
}

// Get user name and site name
$userName = $user['name'] ?? 'User';
$siteName = getSiteName() ?? 'LazyMan';
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>Settings - <?= htmlspecialchars($siteName) ?></title>

<!-- Favicons -->
<link rel="icon" type="image/svg+xml" href="<?= APP_URL ?>/assets/favicons/favicon.svg">
    <link rel="icon" type="image/png" sizes="32x32" href="<?= APP_URL ?>/assets/favicons/favicon-32x32.png"/>
<link rel="icon" type="image/png" sizes="16x16" href="<?= APP_URL ?>/assets/favicons/favicon-16x16.png"/>
<link rel="shortcut icon" href="<?= APP_URL ?>/assets/favicons/favicon.ico"/>
<link rel="apple-touch-icon" sizes="180x180" href="<?= APP_URL ?>/assets/favicons/apple-touch-icon.png"/>

<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
<script id="tailwind-config">
    tailwind.config = {
        darkMode: "class",
        theme: {
            extend: {
                colors: {
                    primary: "#000000",
                    "background-light": "#F9FAFB",
                    "background-dark": "#0A0A0A",
                },
                fontFamily: {
                    display: ["Inter", "sans-serif"],
                },
                borderRadius: {
                    DEFAULT: "12px",
                },
            },
        },
    }
</script>
<style type="text/tailwindcss">
    @layer base {
        body {
            @apply bg-white text-black font-display antialiased;
        }
    }
    .touch-target {
        min-height: 44px;
        min-width: 44px;
    }
    .safe-bottom {
        padding-bottom: max(1rem, env(safe-area-inset-bottom));
    }
</style>
</head>
<body class="bg-gray-100 flex justify-center">
<div class="relative w-full max-w-[420px] min-h-screen bg-white dark:bg-zinc-950 shadow-2xl flex flex-col overflow-hidden">

<?php
$title = 'Settings';
$leftAction = 'menu';
$rightAction = 'none';
include MOBILE_VIEW_PATH . '/partials/header-mobile.php';
?>

<main class="flex-1 overflow-y-auto no-scrollbar px-4 pt-6 space-y-6 pb-32">
    <!-- Success/Error Messages -->
    <?php if ($success): ?>
        <div class="bg-green-50 dark:bg-green-900/20 text-green-700 dark:text-green-400 p-4 flex items-center gap-3 animate-fade-in">
            <!-- Heroicon: Check Circle -->
            <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <span class="text-sm font-medium"><?= htmlspecialchars($success) ?></span>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="bg-red-50 dark:bg-red-900/20 text-red-700 dark:text-red-400 p-4 flex items-center gap-3 animate-fade-in">
            <!-- Heroicon: X Circle -->
            <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/>
            </svg>
            <span class="text-sm font-medium"><?= htmlspecialchars($error) ?></span>
        </div>
    <?php endif; ?>

    <section class="bg-white dark:bg-zinc-900 border border-gray-200 dark:border-zinc-800 overflow-hidden">
        <button onclick="Mobile.settings.toggleSection('hosted-section')" class="w-full flex items-center justify-between p-4 active:bg-gray-50 dark:active:bg-zinc-800 transition-colors">
            <div class="flex items-center gap-3">
                <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.75 3v18m0-18h9.75a3 3 0 013 3v12a3 3 0 01-3 3H3.75m0-18A2.25 2.25 0 001.5 5.25v13.5A2.25 2.25 0 003.75 21m0-18A2.25 2.25 0 016 5.25v13.5A2.25 2.25 0 013.75 21"/>
                </svg>
                <div class="text-left">
                    <h3 class="font-semibold text-sm">Hosted Features</h3>
                    <p class="text-xs text-gray-500">Env-controlled status</p>
                </div>
            </div>
            <svg id="hosted-section-chevron" class="w-5 h-5 text-gray-400 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.5 8.25l-7.5 7.5-7.5-7.5"/>
            </svg>
        </button>
        <div id="hosted-section" class="hidden border-t border-gray-100 dark:border-gray-800">
            <div class="p-4 space-y-3 text-sm">
                <div class="bg-gray-50 dark:bg-zinc-800 p-3">
                    <p class="text-[10px] font-bold uppercase tracking-widest text-gray-400 mb-1">Email Verification</p>
                    <p class="font-semibold"><?= isEmailVerificationEnabled() ? 'Enabled' : 'Disabled' ?></p>
                    <p class="text-xs text-gray-500 mt-1"><code>EMAIL_VERIFICATION_ENABLED</code></p>
                </div>
                <div class="bg-gray-50 dark:bg-zinc-800 p-3">
                    <p class="text-[10px] font-bold uppercase tracking-widest text-gray-400 mb-1">Password Reset</p>
                    <p class="font-semibold"><?= isPasswordResetEnabled() ? 'Enabled' : 'Disabled' ?></p>
                    <p class="text-xs text-gray-500 mt-1"><code>PASSWORD_RESET_ENABLED</code></p>
                </div>
                <div class="bg-gray-50 dark:bg-zinc-800 p-3">
                    <p class="text-[10px] font-bold uppercase tracking-widest text-gray-400 mb-1">Image Service</p>
                    <p class="font-semibold"><?= isImageServiceEnabled() ? 'Enabled' : 'Disabled' ?></p>
                    <p class="text-xs text-gray-500 mt-1"><code>IMAGE_SERVICE_ENABLED</code></p>
                </div>
                <div class="bg-gray-50 dark:bg-zinc-800 p-3">
                    <p class="text-[10px] font-bold uppercase tracking-widest text-gray-400 mb-1">Mail Driver</p>
                    <p class="font-semibold"><?= htmlspecialchars(strtoupper(MAIL_DRIVER)) ?></p>
                    <p class="text-xs text-gray-500 mt-1">SMTP host: <?= htmlspecialchars(SMTP_HOST !== '' ? SMTP_HOST : 'not configured') ?></p>
                </div>
            </div>
        </div>
    </section>

    <section class="bg-white dark:bg-zinc-900 border border-gray-200 dark:border-zinc-800 overflow-hidden">
        <div class="p-4 border-b border-gray-100 dark:border-gray-800">
            <h3 class="font-semibold text-sm">Hosted Deployment Controls</h3>
            <p class="text-xs text-gray-500 mt-1">Read-only status for env-based hosted features.</p>
        </div>
        <div class="p-4 grid grid-cols-2 gap-3 text-sm">
            <div class="border border-gray-200 dark:border-zinc-700 p-3">
                <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400">Verification</p>
                <p class="mt-2 font-medium"><?= EMAIL_VERIFICATION_ENABLED ? 'Enabled' : 'Disabled' ?></p>
            </div>
            <div class="border border-gray-200 dark:border-zinc-700 p-3">
                <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400">Reset</p>
                <p class="mt-2 font-medium"><?= PASSWORD_RESET_ENABLED ? 'Enabled' : 'Disabled' ?></p>
            </div>
            <div class="border border-gray-200 dark:border-zinc-700 p-3">
                <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400">Image Service</p>
                <p class="mt-2 font-medium"><?= IMAGE_SERVICE_ENABLED ? 'Enabled' : 'Disabled' ?></p>
                <p class="text-[10px] text-gray-500 mt-1"><?= htmlspecialchars(IMAGE_SERVICE_PROVIDER !== '' ? IMAGE_SERVICE_PROVIDER : 'No provider') ?></p>
            </div>
            <div class="border border-gray-200 dark:border-zinc-700 p-3">
                <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400">Mail</p>
                <p class="mt-2 font-medium"><?= htmlspecialchars(strtoupper(MAIL_DRIVER)) ?></p>
                <p class="text-[10px] text-gray-500 mt-1"><?= htmlspecialchars(SMTP_HOST !== '' ? SMTP_HOST : 'SMTP not set') ?></p>
            </div>
        </div>
        <div class="px-4 pb-4">
            <p class="text-[11px] text-gray-500">Set `EMAIL_VERIFICATION_ENABLED`, `PASSWORD_RESET_ENABLED`, `IMAGE_SERVICE_ENABLED`, and mail env values outside the app. See `docs/HOSTED_SETUP.md`.</p>
        </div>
    </section>

    <!-- Site Settings Section -->
    <section class="bg-white dark:bg-zinc-900 border border-gray-200 dark:border-zinc-800 overflow-hidden">
        <button onclick="Mobile.settings.toggleSection('site-section')" class="w-full flex items-center justify-between p-4 active:bg-gray-50 dark:active:bg-zinc-800 transition-colors">
            <div class="flex items-center gap-3">
                <!-- Heroicon: Globe -->
                <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 21a9.004 9.004 0 008.716-6.747M12 21a9.004 9.004 0 01-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 017.843 4.582M12 3a8.997 8.997 0 00-7.843 4.582m15.686 0A11.953 11.953 0 0112 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0121 12c0 .778-.099 1.533-.284 2.253m0 0A17.919 17.919 0 0112 16.5c-3.162 0-6.133-.815-8.716-2.247m0 0A9.015 9.015 0 013 12c0-1.605.42-3.113 1.157-4.418"/>
                </svg>
                <div class="text-left">
                    <h3 class="font-semibold text-sm">Site Settings</h3>
                    <p class="text-xs text-gray-500">App name and branding</p>
                </div>
            </div>
            <!-- Heroicon: Chevron Down -->
            <svg id="site-section-chevron" class="w-5 h-5 text-gray-400 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.5 8.25l-7.5 7.5-7.5-7.5"/>
            </svg>
        </button>
        <div id="site-section" class="hidden border-t border-gray-100 dark:border-gray-800">
            <form method="POST" class="p-4 space-y-4">
                <input type="hidden" name="section" value="site">
                <input type="hidden" name="csrf_token" value="<?= Auth::csrfToken() ?>">

                <div>
                    <label class="block text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">Site Name</label>
                    <input type="text" name="siteName" value="<?= htmlspecialchars($config['siteName'] ?? '') ?>"
                           placeholder="Your Site Name"
                           class="w-full bg-gray-50 dark:bg-zinc-800 border border-gray-200 dark:border-zinc-700 py-3 px-4 text-sm focus:ring-2 focus:ring-black focus:border-transparent outline-none">
                </div>

                <button type="submit" class="w-full bg-black dark:bg-white text-white dark:text-black py-3 font-bold text-sm uppercase tracking-wider active:scale-95 transition-transform">
                    Save Site Settings
                </button>
            </form>
        </div>
    </section>

    <!-- Business Settings Section -->
    <section class="bg-white dark:bg-zinc-900 border border-gray-200 dark:border-zinc-800 overflow-hidden">
        <button onclick="Mobile.settings.toggleSection('business-section')" class="w-full flex items-center justify-between p-4 active:bg-gray-50 dark:active:bg-zinc-800 transition-colors">
            <div class="flex items-center gap-3">
                <!-- Heroicon: Building Office -->
                <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3.75c.621 0 1.125-.504 1.125-1.125a1.125 1.125 0 00-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125C2.25 8.496 2.754 9 3.375 9z"/>
                </svg>
                <div class="text-left">
                    <h3 class="font-semibold text-sm">Business Information</h3>
                    <p class="text-xs text-gray-500">For invoices and documents</p>
                </div>
            </div>
            <!-- Heroicon: Chevron Down -->
            <svg id="business-section-chevron" class="w-5 h-5 text-gray-400 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.5 8.25l-7.5 7.5-7.5-7.5"/>
            </svg>
        </button>
        <div id="business-section" class="hidden border-t border-gray-100 dark:border-gray-800">
            <form method="POST" class="p-4 space-y-4">
                <input type="hidden" name="section" value="business">
                <input type="hidden" name="csrf_token" value="<?= Auth::csrfToken() ?>">

                <div>
                    <label class="block text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">Business Name</label>
                    <input type="text" name="businessName" value="<?= htmlspecialchars($config['businessName'] ?? '') ?>"
                           class="w-full bg-gray-50 dark:bg-zinc-800 border border-gray-200 dark:border-zinc-700 py-3 px-4 text-sm focus:ring-2 focus:ring-black outline-none">
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">Email</label>
                    <input type="email" name="businessEmail" value="<?= htmlspecialchars($config['businessEmail'] ?? '') ?>"
                           class="w-full bg-gray-50 dark:bg-zinc-800 border border-gray-200 dark:border-zinc-700 py-3 px-4 text-sm focus:ring-2 focus:ring-black outline-none">
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">Phone</label>
                    <input type="text" name="businessPhone" value="<?= htmlspecialchars($config['businessPhone'] ?? '') ?>"
                           class="w-full bg-gray-50 dark:bg-zinc-800 border border-gray-200 dark:border-zinc-700 py-3 px-4 text-sm focus:ring-2 focus:ring-black outline-none">
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">Address</label>
                    <textarea name="businessAddress" rows="2"
                              class="w-full bg-gray-50 dark:bg-zinc-800 border border-gray-200 dark:border-zinc-700 py-3 px-4 text-sm focus:ring-2 focus:ring-black outline-none resize-none"><?= htmlspecialchars($config['businessAddress'] ?? '') ?></textarea>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">Currency</label>
                        <select name="currency" class="w-full bg-gray-50 dark:bg-zinc-800 border border-gray-200 dark:border-zinc-700 py-3 px-4 text-sm focus:ring-2 focus:ring-black outline-none appearance-none">
                            <option value="USD" <?= ($config['currency'] ?? '') === 'USD' ? 'selected' : '' ?>>USD ($)</option>
                            <option value="EUR" <?= ($config['currency'] ?? '') === 'EUR' ? 'selected' : '' ?>>EUR (€)</option>
                            <option value="GBP" <?= ($config['currency'] ?? '') === 'GBP' ? 'selected' : '' ?>>GBP (£)</option>
                            <option value="ZAR" <?= ($config['currency'] ?? '') === 'ZAR' ? 'selected' : '' ?>>ZAR (R)</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">Tax Rate %</label>
                        <input type="number" name="taxRate" value="<?= htmlspecialchars($config['taxRate'] ?? 0) ?>" step="0.01" min="0" max="100"
                               class="w-full bg-gray-50 dark:bg-zinc-800 border border-gray-200 dark:border-zinc-700 py-3 px-4 text-sm focus:ring-2 focus:ring-black outline-none">
                    </div>
                </div>

                <button type="submit" class="w-full bg-black dark:bg-white text-white dark:text-black py-3 font-bold text-sm uppercase tracking-wider active:scale-95 transition-transform">
                    Save Business Settings
                </button>
            </form>
        </div>
    </section>

    <!-- API Keys Section -->
    <section class="bg-white dark:bg-zinc-900 border border-gray-200 dark:border-zinc-800 overflow-hidden">
        <button onclick="Mobile.settings.toggleSection('api-section')" class="w-full flex items-center justify-between p-4 active:bg-gray-50 dark:active:bg-zinc-800 transition-colors">
            <div class="flex items-center gap-3">
                <!-- Heroicon: Key -->
                <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.75 5.25a3 3 0 013 3m3 0a6 6 0 01-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 1121.75 8.25z"/>
                </svg>
                <div class="text-left">
                    <h3 class="font-semibold text-sm">AI API Keys</h3>
                    <p class="text-xs text-gray-500">Groq and OpenRouter</p>
                </div>
            </div>
            <!-- Heroicon: Chevron Down -->
            <svg id="api-section-chevron" class="w-5 h-5 text-gray-400 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.5 8.25l-7.5 7.5-7.5-7.5"/>
            </svg>
        </button>
        <div id="api-section" class="hidden border-t border-gray-100 dark:border-gray-800">
            <form method="POST" class="p-4 space-y-4">
                <input type="hidden" name="section" value="api">
                <input type="hidden" name="csrf_token" value="<?= Auth::csrfToken() ?>">

                <div>
                    <label class="block text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">
                        Groq API Key
                        <a href="https://console.groq.com/keys" target="_blank" class="text-blue-500 font-normal ml-2">Get key →</a>
                    </label>
                    <input type="password" name="groqApiKey" value="<?= htmlspecialchars($config['groqApiKey'] ?? '') ?>"
                           placeholder="gsk_..."
                           class="w-full bg-gray-50 dark:bg-zinc-800 border border-gray-200 dark:border-zinc-700 py-3 px-4 text-sm focus:ring-2 focus:ring-black outline-none font-mono">
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">
                        OpenRouter API Key
                        <a href="https://openrouter.ai/keys" target="_blank" class="text-blue-500 font-normal ml-2">Get key →</a>
                    </label>
                    <input type="password" name="openrouterApiKey" value="<?= htmlspecialchars($config['openrouterApiKey'] ?? '') ?>"
                           placeholder="sk-or-..."
                           class="w-full bg-gray-50 dark:bg-zinc-800 border border-gray-200 dark:border-zinc-700 py-3 px-4 text-sm focus:ring-2 focus:ring-black outline-none font-mono">
                </div>

                <button type="submit" class="w-full bg-black dark:bg-white text-white dark:text-black py-3 font-bold text-sm uppercase tracking-wider active:scale-95 transition-transform">
                    Save API Keys
                </button>
            </form>
        </div>
    </section>

    <!-- Water Settings Section -->
    <section class="bg-white dark:bg-zinc-900 border border-gray-200 dark:border-zinc-800 overflow-hidden">
        <button onclick="Mobile.settings.toggleSection('water-section')" class="w-full flex items-center justify-between p-4 active:bg-gray-50 dark:active:bg-zinc-800 transition-colors">
            <div class="flex items-center gap-3">
                <!-- Heroicon: Beaker/Water -->
                <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 2.25c-5.385 0-9.75 4.365-9.75 9.75s4.365 9.75 9.75 9.75 9.75-4.365 9.75-9.75S17.385 2.25 12 2.25z"/>
                </svg>
                <div class="text-left">
                    <h3 class="font-semibold text-sm">Water Tracker</h3>
                    <p class="text-xs text-gray-500">Daily goals and reminders</p>
                </div>
            </div>
            <!-- Heroicon: Chevron Down -->
            <svg id="water-section-chevron" class="w-5 h-5 text-gray-400 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.5 8.25l-7.5 7.5-7.5-7.5"/>
            </svg>
        </button>
        <div id="water-section" class="hidden border-t border-gray-100 dark:border-gray-800">
            <form method="POST" class="p-4 space-y-4">
                <input type="hidden" name="section" value="water">
                <input type="hidden" name="csrf_token" value="<?= Auth::csrfToken() ?>">

                <div>
                    <label class="block text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">Daily Goal (glasses)</label>
                    <input type="number" name="waterGoal" value="<?= htmlspecialchars($config['waterGoal'] ?? 8) ?>" min="1" max="20"
                           class="w-full bg-gray-50 dark:bg-zinc-800 border border-gray-200 dark:border-zinc-700 py-3 px-4 text-sm focus:ring-2 focus:ring-black outline-none">
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">Reminder Interval</label>
                    <select name="waterReminderInterval" class="w-full bg-gray-50 dark:bg-zinc-800 border border-gray-200 dark:border-zinc-700 py-3 px-4 text-sm focus:ring-2 focus:ring-black outline-none appearance-none">
                        <option value="30" <?= ($config['waterReminderInterval'] ?? 60) == 30 ? 'selected' : '' ?>>30 minutes</option>
                        <option value="60" <?= ($config['waterReminderInterval'] ?? 60) == 60 ? 'selected' : '' ?>>1 hour</option>
                        <option value="120" <?= ($config['waterReminderInterval'] ?? 60) == 120 ? 'selected' : '' ?>>2 hours</option>
                        <option value="180" <?= ($config['waterReminderInterval'] ?? 60) == 180 ? 'selected' : '' ?>>3 hours</option>
                    </select>
                </div>

                <div class="flex items-center gap-3 p-3 bg-gray-50 dark:bg-zinc-800">
                    <input type="checkbox" name="waterNotificationsEnabled" id="water-notifications-enabled" class="w-5 h-5 rounded border-gray-300" <?= ($config['waterNotificationsEnabled'] ?? true) ? 'checked' : '' ?>>
                    <label for="water-notifications-enabled" class="text-sm font-medium">Enable notifications</label>
                </div>

                <button type="submit" class="w-full bg-black dark:bg-white text-white dark:text-black py-3 font-bold text-sm uppercase tracking-wider active:scale-95 transition-transform">
                    Save Water Settings
                </button>
            </form>
        </div>
    </section>

    <!-- Notification Settings Section -->
    <section class="bg-white dark:bg-zinc-900 border border-gray-200 dark:border-zinc-800 overflow-hidden">
        <button onclick="Mobile.settings.toggleSection('notifications-section')" class="w-full flex items-center justify-between p-4 active:bg-gray-50 dark:active:bg-zinc-800 transition-colors">
            <div class="flex items-center gap-3">
                <!-- Heroicon: Bell -->
                <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0"/>
                </svg>
                <div class="text-left">
                    <h3 class="font-semibold text-sm">Notifications</h3>
                    <p class="text-xs text-gray-500">Alerts and sounds</p>
                </div>
            </div>
            <!-- Heroicon: Chevron Down -->
            <svg id="notifications-section-chevron" class="w-5 h-5 text-gray-400 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.5 8.25l-7.5 7.5-7.5-7.5"/>
            </svg>
        </button>
        <div id="notifications-section" class="hidden border-t border-gray-100 dark:border-gray-800">
            <form method="POST" class="p-4 space-y-4">
                <input type="hidden" name="section" value="notifications">
                <input type="hidden" name="csrf_token" value="<?= Auth::csrfToken() ?>">

                <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-zinc-800">
                    <div>
                        <label for="notification-sound-enabled" class="text-sm font-medium">Enable Sounds</label>
                        <p class="text-xs text-gray-500">Play sounds for notifications</p>
                    </div>
                    <input type="checkbox" name="notificationSoundEnabled" id="notification-sound-enabled" class="w-5 h-5 rounded border-gray-300" <?= ($config['notificationSoundEnabled'] ?? true) ? 'checked' : '' ?>>
                </div>

                <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-zinc-800">
                    <div>
                        <label for="overdue-alert-enabled" class="text-sm font-medium">Overdue Tasks</label>
                        <p class="text-xs text-gray-500">Alert when tasks are overdue</p>
                    </div>
                    <input type="checkbox" name="overdueAlertEnabled" id="overdue-alert-enabled" class="w-5 h-5 rounded border-gray-300" <?= ($config['overdueAlertEnabled'] ?? true) ? 'checked' : '' ?>>
                </div>

                <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-zinc-800">
                    <div>
                        <label for="due-soon-alert-enabled" class="text-sm font-medium">Due Soon</label>
                        <p class="text-xs text-gray-500">Alert for upcoming deadlines</p>
                    </div>
                    <input type="checkbox" name="dueSoonAlertEnabled" id="due-soon-alert-enabled" class="w-5 h-5 rounded border-gray-300" <?= ($config['dueSoonAlertEnabled'] ?? true) ? 'checked' : '' ?>>
                </div>

                <button type="submit" class="w-full bg-black dark:bg-white text-white dark:text-black py-3 font-bold text-sm uppercase tracking-wider active:scale-95 transition-transform">
                    Save Notification Settings
                </button>
            </form>
        </div>
    </section>

    <!-- Session Timeout Section -->
    <section class="bg-white dark:bg-zinc-900 border border-gray-200 dark:border-zinc-800 overflow-hidden">
        <button onclick="Mobile.settings.toggleSection('session-section')" class="w-full flex items-center justify-between p-4 active:bg-gray-50 dark:active:bg-zinc-800 transition-colors">
            <div class="flex items-center gap-3">
                <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6l4 2m6-2a10 10 0 11-20 0 10 10 0 0120 0z"/>
                </svg>
                <div class="text-left">
                    <h3 class="font-semibold text-sm">Session Timeout</h3>
                    <p class="text-xs text-gray-500">Auto logout preference</p>
                </div>
            </div>
            <svg id="session-section-chevron" class="w-5 h-5 text-gray-400 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.5 8.25l-7.5 7.5-7.5-7.5"/>
            </svg>
        </button>
        <div id="session-section" class="hidden border-t border-gray-100 dark:border-gray-800">
            <form method="POST" class="p-4 space-y-4">
                <input type="hidden" name="section" value="session">
                <input type="hidden" name="csrf_token" value="<?= Auth::csrfToken() ?>">

                <div>
                    <label class="block text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">Timeout Preference</label>
                    <select name="sessionTimeoutPreference" class="w-full bg-gray-50 dark:bg-zinc-800 border border-gray-200 dark:border-zinc-700 py-3 px-4 text-sm focus:ring-2 focus:ring-black outline-none appearance-none">
                        <option value="20m" <?= $sessionTimeoutPreference === '20m' ? 'selected' : '' ?>>20 minutes</option>
                        <option value="1h" <?= $sessionTimeoutPreference === '1h' ? 'selected' : '' ?>>1 hour</option>
                        <option value="indefinite" <?= $sessionTimeoutPreference === 'indefinite' ? 'selected' : '' ?>>Indefinite (no auto-logout)</option>
                    </select>
                </div>

                <p class="text-xs text-gray-500">
                    Timed options log you out after inactivity. Indefinite keeps you signed in across browser restarts until manual sign out.
                </p>

                <button type="submit" class="w-full bg-black dark:bg-white text-white dark:text-black py-3 font-bold text-sm uppercase tracking-wider active:scale-95 transition-transform">
                    Save Session Timeout
                </button>
            </form>
        </div>
    </section>

    <?php if (Auth::isAdmin()): ?>
    <!-- Favicon Settings Section -->
    <section class="bg-white dark:bg-zinc-900 border border-gray-200 dark:border-zinc-800 overflow-hidden">
        <button onclick="Mobile.settings.toggleSection('favicon-section')" class="w-full flex items-center justify-between p-4 active:bg-gray-50 dark:active:bg-zinc-800 transition-colors">
            <div class="flex items-center gap-3">
                <!-- Heroicon: Photo/Image -->
                <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 001.5-1.5V6a1.5 1.5 0 00-1.5-1.5H3.75A1.5 1.5 0 002.25 6v12a1.5 1.5 0 001.5 1.5zm10.5-11.25h.008v.008h-.008V8.25zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z"/>
                </svg>
                <div class="text-left">
                    <h3 class="font-semibold text-sm">Favicon & Branding</h3>
                    <p class="text-xs text-gray-500">Custom icon and theme</p>
                </div>
            </div>
            <!-- Heroicon: Chevron Down -->
            <svg id="favicon-section-chevron" class="w-5 h-5 text-gray-400 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.5 8.25l-7.5 7.5-7.5-7.5"/>
            </svg>
        </button>
        <div id="favicon-section" class="hidden border-t border-gray-100 dark:border-zinc-800">
            <div class="p-4 space-y-4">
                <!-- Current Favicon -->
                <div class="flex items-center gap-4">
                    <div class="w-16 h-16 bg-black flex items-center justify-center">
                        <img id="favicon-preview" src="assets/favicons/favicon-32x32.png" alt="Current favicon" class="w-10 h-10" onerror="this.style.display='none'">
                        <span id="favicon-fallback" class="text-white text-xs font-bold">LM</span>
                    </div>
                    <div>
                        <p class="font-medium text-sm">Current Favicon</p>
                        <p class="text-xs text-gray-500">Black with "LM" monogram</p>
                    </div>
                </div>

                <!-- Upload Form -->
                <form id="favicon-upload-form" enctype="multipart/form-data" class="space-y-4">
                    <input type="hidden" name="csrf_token" value="<?= Auth::csrfToken() ?>">

                    <div>
                        <label class="block text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">Upload New Favicon</label>
                        <input type="file" name="favicon" id="favicon-input" accept="image/png,image/jpeg,image/svg+xml"
                               class="w-full bg-gray-50 dark:bg-zinc-800 border border-gray-200 dark:border-zinc-700 py-3 px-4 text-sm focus:ring-2 focus:ring-black outline-none">
                        <p class="text-xs text-gray-500 mt-1">Recommended: 512x512 PNG or SVG</p>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">Theme Color</label>
                        <div class="flex items-center gap-3">
                            <input type="color" name="theme_color" id="theme-color-picker" value="#000000"
                                   class="w-12 h-12 border border-gray-200 dark:border-zinc-700 cursor-pointer">
                            <input type="text" name="theme_color_hex" id="theme-color-hex" value="#000000"
                                   class="flex-1 bg-gray-50 dark:bg-zinc-800 border border-gray-200 dark:border-zinc-700 py-3 px-4 text-sm focus:ring-2 focus:ring-black outline-none font-mono">
                        </div>
                        <p class="text-xs text-gray-500 mt-1">Browser tab color</p>
                    </div>

                    <div id="favicon-message" class="text-sm hidden"></div>

                    <div class="flex gap-3">
                        <button type="submit" class="flex-1 bg-black dark:bg-white text-white dark:text-black py-3 font-bold text-sm uppercase tracking-wider active:scale-95 transition-transform">
                            Upload
                        </button>
                        <button type="button" onclick="Mobile.settings.resetFavicon()" class="flex-1 border border-gray-300 dark:border-zinc-600 py-3 font-bold text-sm active:scale-95 transition-transform">
                            Reset
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- Backup Management Section -->
    <section class="bg-white dark:bg-zinc-900 border border-gray-200 dark:border-zinc-800 overflow-hidden">
        <button onclick="Mobile.settings.toggleSection('backup-section')" class="w-full flex items-center justify-between p-4 active:bg-gray-50 dark:active:bg-zinc-800 transition-colors">
            <div class="flex items-center gap-3">
                <!-- Heroicon: Cloud Arrow Down -->
                <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m0 0L12 16.5m-4-4.5h9M12 3v13.5"/>
                </svg>
                <div class="text-left">
                    <h3 class="font-semibold text-sm">Backup & Export</h3>
                    <p class="text-xs text-gray-500">AES-256 encrypted backups</p>
                </div>
            </div>
            <!-- Heroicon: Chevron Down -->
            <svg id="backup-section-chevron" class="w-5 h-5 text-gray-400 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.5 8.25l-7.5 7.5-7.5-7.5"/>
            </svg>
        </button>
        <div id="backup-section" class="hidden border-t border-gray-100 dark:border-gray-800">
            <div class="p-4 space-y-4">
                <!-- Quick Stats -->
                <div class="grid grid-cols-3 gap-2">
                    <div class="bg-gray-50 dark:bg-zinc-800 p-3 text-center">
                        <p id="backup-count" class="text-xl font-bold">-</p>
                        <p class="text-[10px] text-gray-500 uppercase">Backups</p>
                    </div>
                    <div class="bg-gray-50 dark:bg-zinc-800 p-3 text-center">
                        <p id="backup-size" class="text-xl font-bold">-</p>
                        <p class="text-[10px] text-gray-500 uppercase">Size</p>
                    </div>
                    <div class="bg-gray-50 dark:bg-zinc-800 p-3 text-center">
                        <p id="backup-latest" class="text-xs font-medium">-</p>
                        <p class="text-[10px] text-gray-500 uppercase">Latest</p>
                    </div>
                </div>

                <!-- Actions -->
                <div class="grid grid-cols-2 gap-3">
                    <button onclick="Mobile.settings.createBackup()" id="create-backup-btn" class="flex items-center justify-center gap-2 bg-black dark:bg-white text-white dark:text-black py-3 font-bold text-sm active:scale-95 transition-transform">
                        <!-- Heroicon: Document Arrow Down -->
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m0 0L12 16.5m-4-4.5h9M12 3v13.5"/>
                        </svg>
                        Create Backup
                    </button>
                    <button onclick="Mobile.settings.cleanupBackups()" class="flex items-center justify-center gap-2 border border-gray-300 dark:border-zinc-600 py-3 font-bold text-sm active:scale-95 transition-transform">
                        <!-- Heroicon: Trash -->
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0"/>
                        </svg>
                        Clean Old
                    </button>
                </div>

                <!-- Backup List -->
                <div class="border border-gray-200 dark:border-zinc-700 overflow-hidden">
                    <div class="bg-gray-50 dark:bg-zinc-800 px-4 py-3">
                        <h4 class="font-medium text-sm">Recent Backups</h4>
                    </div>
                    <div id="backup-list" class="max-h-48 overflow-y-auto divide-y divide-gray-100 dark:divide-gray-800">
                        <div class="p-4 text-center text-gray-500 text-sm">Loading backups...</div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Security Section -->
    <section class="bg-white dark:bg-zinc-900 border border-gray-200 dark:border-zinc-800 overflow-hidden">
        <button onclick="Mobile.settings.toggleSection('security-section')" class="w-full flex items-center justify-between p-4 active:bg-gray-50 dark:active:bg-zinc-800 transition-colors">
            <div class="flex items-center gap-3">
                <!-- Heroicon: Shield -->
                <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12.75L11.25 15 15 9.75m-3.757 4.789a8.25 8.25 0 11-6.866-3.708 8.25 8.25 0 016.866 3.708zM3 9a9 9 0 009 9m0 0a9 9 0 019-9m0 0c-.476 0-.944.034-1.4.095M12 3a9.001 9.001 0 018.357 5.105M12 21a9.001 9.001 0 01-8.357-5.105"/>
                </svg>
                <div class="text-left">
                    <h3 class="font-semibold text-sm">Security</h3>
                    <p class="text-xs text-gray-500">Passwords and encryption</p>
                </div>
            </div>
            <!-- Heroicon: Chevron Down -->
            <svg id="security-section-chevron" class="w-5 h-5 text-gray-400 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.5 8.25l-7.5 7.5-7.5-7.5"/>
            </svg>
        </button>
        <div id="security-section" class="hidden border-t border-gray-100 dark:border-gray-800">
            <div class="p-4 space-y-4">
                <!-- Change Account Password -->
                <div class="border-b border-gray-100 dark:border-gray-800 pb-4">
                    <h4 class="font-semibold text-sm mb-3">Change Account Password</h4>
                    <form id="change-password-form" class="space-y-3">
                        <input type="hidden" name="csrf_token" value="<?= Auth::csrfToken() ?>">
                        <div>
                            <input type="password" id="current-password" placeholder="Current password" required
                                   class="w-full bg-gray-50 dark:bg-zinc-800 border border-gray-200 dark:border-zinc-700 py-3 px-4 text-sm focus:ring-2 focus:ring-black outline-none">
                        </div>
                        <div>
                            <input type="password" id="new-password" placeholder="New password (min 8 chars)" required minlength="8"
                                   class="w-full bg-gray-50 dark:bg-zinc-800 border border-gray-200 dark:border-zinc-700 py-3 px-4 text-sm focus:ring-2 focus:ring-black outline-none">
                        </div>
                        <div>
                            <input type="password" id="confirm-new-password" placeholder="Confirm new password" required minlength="8"
                                   class="w-full bg-gray-50 dark:bg-zinc-800 border border-gray-200 dark:border-zinc-700 py-3 px-4 text-sm focus:ring-2 focus:ring-black outline-none">
                        </div>
                        <button type="submit" class="w-full bg-gray-900 text-white py-3 font-bold text-sm active:scale-95 transition-transform">
                            Update Password
                        </button>
                    </form>
                </div>

                <!-- Change Master Password -->
                <div class="bg-red-50 dark:bg-red-900/10 -mx-4 -mb-4 px-4 pb-4 rounded-t-2xl">
                    <h4 class="font-semibold text-sm text-red-900 dark:text-red-300 mb-3">Change Master Password</h4>
                    <p class="text-xs text-red-700 dark:text-red-400 mb-3">Warning: This re-encrypts all data. Make sure you remember the new password!</p>
                    <form id="change-master-password-form" class="space-y-3">
                        <input type="hidden" name="csrf_token" value="<?= Auth::csrfToken() ?>">
                        <div>
                            <input type="password" id="current-master-password" placeholder="Current master password" required
                                   class="w-full bg-white dark:bg-zinc-800 border border-red-200 dark:border-red-800 py-3 px-4 text-sm focus:ring-2 focus:ring-red-500 outline-none">
                        </div>
                        <div>
                            <input type="password" id="new-master-password" placeholder="New master password (min 4 chars)" required minlength="4"
                                   class="w-full bg-white dark:bg-zinc-800 border border-red-200 dark:border-red-800 py-3 px-4 text-sm focus:ring-2 focus:ring-red-500 outline-none">
                        </div>
                        <div>
                            <input type="password" id="confirm-new-master-password" placeholder="Confirm new master password" required minlength="4"
                                   class="w-full bg-white dark:bg-zinc-800 border border-red-200 dark:border-red-800 py-3 px-4 text-sm focus:ring-2 focus:ring-red-500 outline-none">
                        </div>
                        <button type="submit" class="w-full bg-red-600 hover:bg-red-700 text-white py-3 font-bold text-sm active:scale-95 transition-transform">
                            Change Master Password
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </section>

    <!-- Developer Tools Section -->
    <section class="bg-gradient-to-br from-gray-900 to-black dark:from-black dark:to-zinc-900 border border-gray-800 dark:border-zinc-800 overflow-hidden">
        <button onclick="Mobile.settings.toggleSection('dev-section')" class="w-full flex items-center justify-between p-4 active:bg-gray-800 dark:active:bg-zinc-800 transition-colors">
            <div class="flex items-center gap-3">
                <!-- Heroicon: Code Bracket -->
                <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.25 6.75L22.5 12l-5.25 5.25m-10.5 0L1.5 12l5.25-5.25m7.5-3l-4.5 16.5"/>
                </svg>
                <div class="text-left">
                    <h3 class="font-semibold text-sm text-white">Developer Tools</h3>
                    <p class="text-xs text-gray-400">Advanced utilities</p>
                </div>
            </div>
            <!-- Heroicon: Chevron Down (white) -->
            <svg id="dev-section-chevron" class="w-5 h-5 text-gray-400 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.5 8.25l-7.5 7.5-7.5-7.5"/>
            </svg>
        </button>
        <div id="dev-section" class="hidden border-t border-white/10">
            <div class="p-2 space-y-1">
                <?php if (Auth::isAdmin()): ?>
                <!-- Audit Logs -->
                <a href="?page=audit-logs" class="flex items-center gap-3 p-3 bg-white/5 hover:bg-white/10 transition-colors">
                    <div class="w-10 h-10 bg-amber-500/20 rounded-lg flex items-center justify-center">
                        <svg class="w-5 h-5 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                    </div>
                    <div class="flex-1">
                        <span class="font-bold text-sm text-white">Audit Logs</span>
                        <p class="text-xs text-gray-400">System activity tracking</p>
                    </div>
                    <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.25 4.5l7.5 7.5-7.5 7.5"/>
                    </svg>
                </a>

                <!-- Scheduler Status -->
                <a href="?page=scheduler-status" class="flex items-center gap-3 p-3 bg-white/5 hover:bg-white/10 transition-colors">
                    <div class="w-10 h-10 bg-blue-500/20 rounded-lg flex items-center justify-center">
                        <svg class="w-5 h-5 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    <div class="flex-1">
                        <span class="font-bold text-sm text-white">Scheduler Status</span>
                        <p class="text-xs text-gray-400">Cron job monitoring</p>
                    </div>
                    <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.25 4.5l7.5 7.5-7.5 7.5"/>
                    </svg>
                </a>
                <?php endif; ?>

                <!-- MCP Config -->
                <a href="?page=mcp" class="flex items-center gap-3 p-3 bg-white/5 hover:bg-white/10 transition-colors">
                    <div class="w-10 h-10 bg-purple-500/20 rounded-lg flex items-center justify-center">
                        <svg class="w-5 h-5 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.25 6.75L22.5 12l-5.25 5.25m-10.5 0L1.5 12l5.25-5.25m7.5-3l-4.5 16.5"/>
                        </svg>
                    </div>
                    <div class="flex-1">
                        <span class="font-bold text-sm text-white">MCP Config</span>
                        <p class="text-xs text-gray-400">Model Context Protocol</p>
                    </div>
                    <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.25 4.5l7.5 7.5-7.5 7.5"/>
                    </svg>
                </a>

                <?php if (Auth::isAdmin()): ?>
                <!-- Speckitty -->
                <a href="?page=speckitty" class="flex items-center gap-3 p-3 bg-white/5 hover:bg-white/10 transition-colors">
                    <div class="w-10 h-10 bg-pink-500/20 rounded-lg flex items-center justify-center">
                        <svg class="w-5 h-5 text-pink-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z"/>
                        </svg>
                    </div>
                    <div class="flex-1">
                        <span class="font-bold text-sm text-white">Speckitty</span>
                        <p class="text-xs text-gray-400">AI spec generator</p>
                    </div>
                    <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.25 4.5l7.5 7.5-7.5 7.5"/>
                    </svg>
                </a>

                <!-- Custom Instructions -->
                <a href="?page=custom-instruction" class="flex items-center gap-3 p-3 bg-white/5 hover:bg-white/10 transition-colors">
                    <div class="w-10 h-10 bg-cyan-500/20 rounded-lg flex items-center justify-center">
                        <svg class="w-5 h-5 text-cyan-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                        </svg>
                    </div>
                    <div class="flex-1">
                        <span class="font-bold text-sm text-white">Custom Instructions</span>
                        <p class="text-xs text-gray-400">AI behavior settings</p>
                    </div>
                    <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.25 4.5l7.5 7.5-7.5 7.5"/>
                    </svg>
                </a>
                <?php endif; ?>

                <!-- Model Settings -->
                <a href="?page=model-settings" class="flex items-center gap-3 p-3 bg-white/5 hover:bg-white/10 transition-colors">
                    <div class="w-10 h-10 bg-violet-500/20 rounded-lg flex items-center justify-center">
                        <svg class="w-5 h-5 text-violet-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L8.25 21l-1.5-4M15.75 17l-1.5 4-1.5-4M12 3v14m6.75-10.5H5.25"/>
                        </svg>
                    </div>
                    <div class="flex-1">
                        <span class="font-bold text-sm text-white">Model Settings</span>
                        <p class="text-xs text-gray-400">AI model selection</p>
                    </div>
                    <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.25 4.5l7.5 7.5-7.5 7.5"/>
                    </svg>
                </a>

                <!-- Data Management -->
                <a href="?page=data-management" class="flex items-center gap-3 p-3 bg-white/5 hover:bg-white/10 transition-colors">
                    <div class="w-10 h-10 bg-teal-500/20 rounded-lg flex items-center justify-center">
                        <svg class="w-5 h-5 text-teal-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7h16M4 12h16M4 17h16"/>
                        </svg>
                    </div>
                    <div class="flex-1">
                        <span class="font-bold text-sm text-white">Data Management</span>
                        <p class="text-xs text-gray-400">Import, export and backups</p>
                    </div>
                    <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.25 4.5l7.5 7.5-7.5 7.5"/>
                    </svg>
                </a>

                <!-- Import / Export Data -->
                <a href="?page=import-data" class="flex items-center gap-3 p-3 bg-white/5 hover:bg-white/10 transition-colors">
                    <div class="w-10 h-10 bg-emerald-500/20 rounded-lg flex items-center justify-center">
                        <svg class="w-5 h-5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 16V4m0 12l-3-3m3 3l3-3M5.25 19.5h13.5"/>
                        </svg>
                    </div>
                    <div class="flex-1">
                        <span class="font-bold text-sm text-white">Import / Export</span>
                        <p class="text-xs text-gray-400">Data portability tools</p>
                    </div>
                    <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.25 4.5l7.5 7.5-7.5 7.5"/>
                    </svg>
                </a>

                <!-- Data Recovery -->
                <a href="?page=data-recovery" class="flex items-center gap-3 p-3 bg-white/5 hover:bg-white/10 transition-colors">
                    <div class="w-10 h-10 bg-green-500/20 rounded-lg flex items-center justify-center">
                        <svg class="w-5 h-5 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                        </svg>
                    </div>
                    <div class="flex-1">
                        <span class="font-bold text-sm text-white">Data Recovery</span>
                        <p class="text-xs text-gray-400">Backup & restore</p>
                    </div>
                    <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.25 4.5l7.5 7.5-7.5 7.5"/>
                    </svg>
                </a>
            </div>
        </div>
    </section>

    <!-- Account Actions -->
    <section class="bg-white dark:bg-zinc-900 border border-gray-200 dark:border-zinc-800 overflow-hidden">
        <div class="p-4 border-b border-gray-100 dark:border-gray-800">
            <h3 class="font-semibold text-sm text-gray-500 uppercase tracking-wider">Account</h3>
        </div>
        <div class="divide-y divide-gray-100 dark:divide-gray-800">
            <button onclick="Mobile.settings.confirmLogout()" class="w-full flex items-center justify-between p-4 active:bg-gray-50 dark:active:bg-zinc-800 transition-colors">
                <div class="flex items-center gap-3">
                    <!-- Heroicon: Arrow Right on Rectangle (Logout) -->
                    <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15M12 9l-3 3m0 0l3-3m-3 3h12.75"/>
                    </svg>
                    <span class="font-medium text-sm">Sign Out</span>
                </div>
                <!-- Heroicon: Chevron Right -->
                <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.25 4.5l7.5 7.5-7.5 7.5"/>
                </svg>
            </button>
            <button class="w-full flex items-center justify-between p-4 active:bg-gray-50 dark:active:bg-zinc-800 transition-colors text-red-600">
                <div class="flex items-center gap-3">
                    <!-- Heroicon: Trash -->
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0"/>
                    </svg>
                    <span class="font-medium text-sm">Delete All Data</span>
                </div>
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.25 4.5l7.5 7.5-7.5 7.5"/>
                </svg>
            </button>
        </div>
    </section>

    <!-- App Version -->
    <div class="text-center py-8">
        <p class="text-xs text-gray-400 font-medium"><?= htmlspecialchars(getSiteName()) ?> Mobile</p>
        <p class="text-xs text-gray-300 mt-1">v<?= MOBILE_VERSION ?></p>
    </div>
</main>

<!-- Universal Bottom Navigation -->
<?php
$activePage = 'settings';
include MOBILE_VIEW_PATH . '/partials/bottom-nav.php';
?>

<!-- Universal Off-Canvas Menu -->
<?php include MOBILE_VIEW_PATH . '/partials/offcanvas-menu.php'; ?>

<!-- Theme Toggle -->
<button onclick="document.documentElement.classList.toggle('dark')"
        class="fixed right-4 bottom-24 bg-white dark:bg-zinc-900 border border-gray-200 dark:border-zinc-800 p-3 rounded-full shadow-lg z-40 active:scale-90 transition-transform touch-target">
    <svg class="w-5 h-5 block dark:hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21.752 15.002A9.718 9.718 0 0118 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 003 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 009.002-5.998z"/>
    </svg>
    <svg class="w-5 h-5 hidden dark:block" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v2.25m6.364.386l-1.591 1.591M21 12h-2.25m-.386 6.364l-1.591-1.591M12 18.75V21m-4.773-4.227l-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0z"/>
    </svg>
</button>

<!-- App Config -->
<script>
    // Dynamic base path detection for mobile views
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

<!-- Mobile JS -->
<script src="<?= MOBILE_JS_URL ?>/mobile.js"></script>

<!-- Settings-Specific JS -->
<script>
Mobile.settings = (function() {
    // Toggle section expansion
    function toggleSection(sectionId) {
        const section = document.getElementById(sectionId);
        const chevron = document.getElementById(sectionId + '-chevron');

        if (section.classList.contains('hidden')) {
            section.classList.remove('hidden');
            chevron.style.transform = 'rotate(180deg)';

            // Load backups if opening backup section
            if (sectionId === 'backup-section') {
                loadBackupStats();
                loadBackupList();
            }
        } else {
            section.classList.add('hidden');
            chevron.style.transform = 'rotate(0deg)';
        }
    }

    // Confirm logout
    function confirmLogout() {
        Mobile.ui.confirmAction('Are you sure you want to sign out?', function() {
            window.location.href = '?page=logout';
        });
    }

    // ========== Backup Functions ==========
    async function loadBackupStats() {
        try {
            const response = await App.api.get('api/backup.php?action=stats');
            if (response.success) {
                const data = response.data;
                document.getElementById('backup-count').textContent = data.total_backups || 0;
                document.getElementById('backup-size').textContent = data.total_size_formatted || '0 B';
                document.getElementById('backup-latest').textContent = data.latest_backup
                    ? new Date(data.latest_backup.created_at).toLocaleDateString()
                    : 'Never';
            }
        } catch (error) {
            console.error('Failed to load backup stats:', error);
        }
    }

    async function loadBackupList() {
        const listEl = document.getElementById('backup-list');
        try {
            const response = await App.api.get('api/backup.php?action=list');
            if (response.success && response.data.backups) {
                const backups = response.data.backups;
                if (backups.length === 0) {
                    listEl.innerHTML = '<div class="p-4 text-center text-gray-500 text-sm">No backups yet</div>';
                    return;
                }
                listEl.innerHTML = backups.slice(0, 5).map(backup => `
                    <div class="flex items-center justify-between p-3">
                        <div class="flex-1 min-w-0">
                            <p class="text-xs font-mono truncate">${escapeHtml(backup.filename)}</p>
                            <p class="text-[10px] text-gray-500">${backup.size_formatted} • ${new Date(backup.created_at).toLocaleDateString()}</p>
                        </div>
                        <div class="flex gap-1">
                            <button onclick="Mobile.settings.downloadBackup('${escapeHtml(backup.filename)}')" class="p-2 text-blue-500">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l4-4m4 4V4"/>
                                </svg>
                            </button>
                            <button onclick="Mobile.settings.restoreBackup('${escapeHtml(backup.filename)}')" class="p-2 text-emerald-500">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12a9 9 0 101.757-5.244M3 4.5v4.5h4.5"/>
                                </svg>
                            </button>
                            <button onclick="Mobile.settings.deleteBackup('${escapeHtml(backup.filename)}')" class="p-2 text-red-500">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0"/>
                                </svg>
                            </button>
                        </div>
                    </div>
                `).join('');
            }
        } catch (error) {
            console.error('Failed to load backups:', error);
        }
    }

    async function createBackup() {
        const btn = document.getElementById('create-backup-btn');
        const originalHTML = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<svg class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>';

        try {
            const response = await App.api.post('api/backup.php?action=create', {
                type: 'full',
                csrf_token: CSRF_TOKEN
            });

            if (response.success) {
                Mobile.ui.showToast('Backup created!', 'success');
                loadBackupStats();
                loadBackupList();
            } else {
                Mobile.ui.showToast(response.error || 'Failed to create backup', 'error');
            }
        } catch (error) {
            Mobile.ui.showToast('Failed to create backup', 'error');
        } finally {
            btn.disabled = false;
            btn.innerHTML = originalHTML;
        }
    }

    async function cleanupBackups() {
        Mobile.ui.confirmAction('Delete old backups (keep only latest 5)?', async function() {
            try {
                const response = await App.api.post('api/backup.php?action=cleanup', {
                    csrf_token: CSRF_TOKEN
                });

                if (response.success) {
                    Mobile.ui.showToast(`Cleaned ${response.data.deleted_count} old backups`, 'success');
                    loadBackupStats();
                    loadBackupList();
                } else {
                    Mobile.ui.showToast(response.error || 'Failed to cleanup', 'error');
                }
            } catch (error) {
                Mobile.ui.showToast('Failed to cleanup backups', 'error');
            }
        });
    }

    function downloadBackup(filename) {
        window.location.href = `api/backup.php?action=download&filename=${encodeURIComponent(filename)}&csrf_token=${CSRF_TOKEN}`;
    }

    function deleteBackup(filename) {
        Mobile.ui.confirmAction('Delete this backup?', async function() {
            try {
                const response = await App.api.post('api/backup.php?action=delete', {
                    filename: filename,
                    csrf_token: CSRF_TOKEN
                });

                if (response.success) {
                    Mobile.ui.showToast('Backup deleted', 'success');
                    loadBackupStats();
                    loadBackupList();
                } else {
                    Mobile.ui.showToast(response.error || 'Failed to delete', 'error');
                }
            } catch (error) {
                Mobile.ui.showToast('Failed to delete backup', 'error');
            }
        });
    }

    function restoreBackup(filename) {
        Mobile.ui.confirmAction('Restore this backup? This replaces current data.', async function() {
            try {
                const response = await App.api.post('api/backup.php?action=restore', {
                    filename: filename,
                    csrf_token: CSRF_TOKEN
                });

                if (response.success) {
                    Mobile.ui.showToast('Backup restored. Reloading...', 'success');
                    setTimeout(() => window.location.reload(), 1500);
                } else {
                    Mobile.ui.showToast(response.error || 'Failed to restore backup', 'error');
                }
            } catch (error) {
                Mobile.ui.showToast('Failed to restore backup', 'error');
            }
        });
    }

    // ========== Favicon Functions ==========
    function resetFavicon() {
        Mobile.ui.confirmAction('Reset to default favicon?', async function() {
            try {
                const response = await App.api.post('api/favicon.php?action=reset', {
                    csrf_token: CSRF_TOKEN
                });

                if (response.success) {
                    Mobile.ui.showToast('Favicon reset to default', 'success');
                    // Update preview
                    document.getElementById('favicon-fallback').style.display = '';
                    if (document.getElementById('favicon-preview')) {
                        document.getElementById('favicon-preview').src = window.BASE_PATH + '/assets/favicons/favicon-32x32.png?' + Date.now();
                    }
                } else {
                    Mobile.ui.showToast(response.error || 'Failed to reset favicon', 'error');
                }
            } catch (error) {
                Mobile.ui.showToast('Failed to reset favicon', 'error');
            }
        });
    }

    // Helper function
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    return {
        toggleSection,
        confirmLogout,
        createBackup,
        cleanupBackups,
        downloadBackup,
        restoreBackup,
        deleteBackup,
        resetFavicon
    };
})();

// ========== Password Forms ==========
document.addEventListener('DOMContentLoaded', function() {
    // Change Password Form
    const changePasswordForm = document.getElementById('change-password-form');
    if (changePasswordForm) {
        changePasswordForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            const currentPassword = document.getElementById('current-password').value;
            const newPassword = document.getElementById('new-password').value;
            const confirmPassword = document.getElementById('confirm-new-password').value;

            if (newPassword !== confirmPassword) {
                Mobile.ui.showToast('Passwords do not match', 'error');
                return;
            }

            if (newPassword.length < 8) {
                Mobile.ui.showToast('Password must be at least 8 characters', 'error');
                return;
            }

            try {
                const response = await App.api.post('api/auth.php?action=change_password', {
                    current_password: currentPassword,
                    new_password: newPassword,
                    csrf_token: CSRF_TOKEN
                });

                if (response.success) {
                    Mobile.ui.showToast('Password updated successfully!', 'success');
                    changePasswordForm.reset();
                } else {
                    Mobile.ui.showToast(response.error || 'Failed to update password', 'error');
                }
            } catch (error) {
                Mobile.ui.showToast('Failed to update password', 'error');
            }
        });
    }

    // Change Master Password Form
    const changeMasterPasswordForm = document.getElementById('change-master-password-form');
    if (changeMasterPasswordForm) {
        changeMasterPasswordForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            const currentPassword = document.getElementById('current-master-password').value;
            const newPassword = document.getElementById('new-master-password').value;
            const confirmPassword = document.getElementById('confirm-new-master-password').value;

            if (newPassword !== confirmPassword) {
                Mobile.ui.showToast('Passwords do not match', 'error');
                return;
            }

            if (newPassword.length < 4) {
                Mobile.ui.showToast('Master password must be at least 4 characters', 'error');
                return;
            }

            try {
                const response = await App.api.post('api/auth.php?action=change_master_password', {
                    current_master_password: currentPassword,
                    new_master_password: newPassword,
                    csrf_token: CSRF_TOKEN
                });

                if (response.success) {
                    Mobile.ui.showToast('Master password changed! Please log in again.', 'success');
                    setTimeout(() => {
                        window.location.href = '?page=logout';
                    }, 1500);
                } else {
                    Mobile.ui.showToast(response.error || 'Failed to change master password', 'error');
                }
            } catch (error) {
                Mobile.ui.showToast('Failed to change master password', 'error');
            }
        });
    }

    // Favicon Upload Form
    const faviconForm = document.getElementById('favicon-upload-form');
    if (faviconForm) {
        faviconForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            const fileInput = document.getElementById('favicon-input');
            const themeColor = document.getElementById('theme-color-picker').value;

            if (fileInput.files.length > 0) {
                const formData = new FormData();
                formData.append('favicon', fileInput.files[0]);
                formData.append('theme_color', themeColor);
                formData.append('csrf_token', CSRF_TOKEN);

                try {
                    const response = await fetch('api/favicon.php?action=upload', {
                        method: 'POST',
                        body: formData
                    });
                    const result = await response.json();

                    if (result.success) {
                        Mobile.ui.showToast('Favicon uploaded successfully!', 'success');
                        // Update preview
                        const preview = document.getElementById('favicon-preview');
                        preview.src = window.BASE_PATH + '/assets/favicons/favicon-32x32.png?' + Date.now();
                        preview.style.display = 'block';
                        document.getElementById('favicon-fallback').style.display = 'none';
                    } else {
                        Mobile.ui.showToast(result.error || 'Failed to upload favicon', 'error');
                    }
                } catch (error) {
                    Mobile.ui.showToast('Failed to upload favicon', 'error');
                }
            } else {
                Mobile.ui.showToast('Please select a file', 'error');
            }
        });
    }

    // Theme color picker sync
    const themeColorPicker = document.getElementById('theme-color-picker');
    const themeColorHex = document.getElementById('theme-color-hex');
    if (themeColorPicker && themeColorHex) {
        themeColorPicker.addEventListener('input', function() {
            themeColorHex.value = this.value;
        });
        themeColorHex.addEventListener('input', function() {
            if (/^#[0-9A-Fa-f]{6}$/.test(this.value)) {
                themeColorPicker.value = this.value;
            }
        });
    }
});
</script>

<!-- Initialize Mobile -->
<script>
document.addEventListener('DOMContentLoaded', () => {
    Mobile.init();
});
</script>

<!-- iPhone Home Indicator -->
<div class="absolute bottom-2 left-1/2 -translate-x-1/2 w-12 h-1 bg-gray-200 rounded-full z-40"></div>

</div>
</body>
</html>
