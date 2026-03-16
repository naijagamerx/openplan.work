<?php
// Login page
// NOTE: This file is included AFTER HTML output has started in auth.php layout
// To properly set cookies, we need to handle form processing before layout output
// This is handled by the layout file which checks for redirects

$error = '';
$reason = (string)($_GET['reason'] ?? '');
$sessionExpired = in_array($reason, ['session_expired', 'session_timeout', 'session_missing', 'token_restore_failed'], true);
$invalidCsrf = isset($_GET['reason']) && $_GET['reason'] === 'invalid_csrf';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $masterPassword = $_POST['master_password'] ?? '';
    $csrfToken = $_POST['csrf_token'] ?? '';
    $rememberMe = !empty($_POST['remember_me']);

    if (!Auth::validateCsrf($csrfToken)) {
        $error = 'Invalid request token. Please refresh and try again.';
    } elseif (empty($email) || empty($password) || empty($masterPassword)) {
        $error = 'All fields are required';
    } else {
        // Store master password for this session
        $_SESSION[SESSION_MASTER_KEY] = $masterPassword;

        // Attempt login
        $db = new Database($masterPassword);
        $auth = new Auth($db);
        $result = $auth->login($email, $password, $rememberMe);

        if ($result['success']) {
            // Write session data before redirect
            session_write_close();

            // Output redirect page (works better with built-in server than Location header)
            ?>
            <!DOCTYPE html>
            <html>
            <head>
                <meta http-equiv="refresh" content="0;url=<?php echo APP_URL; ?>?page=<?php echo e(Auth::getPostLoginDestination()); ?>&login=success">
                <script>
                    // JavaScript redirect as fallback
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
        } else {
            $error = $result['error'];
            unset($_SESSION[SESSION_MASTER_KEY]);
        }
    }
}
?>

<!-- Session Expired Alert -->
<?php if ($sessionExpired): ?>
    <div class="mb-6 p-4 bg-yellow-50 border-l-4 border-yellow-400 rounded-r-lg">
        <div class="flex items-center gap-2">
            <svg class="w-5 h-5 text-yellow-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
            </svg>
            <span class="text-sm text-yellow-700 font-medium">
                <?php
                $reasonMessage = match ($reason) {
                    'session_timeout' => 'Your session timed out due to inactivity. Please sign in again.',
                    'session_missing' => 'Your login session is no longer available. Please sign in again.',
                    'token_restore_failed' => 'Persistent login restore failed. Please sign in again.',
                    default => 'Your session has expired. Please sign in again.'
                };
                echo e($reasonMessage);
                ?>
            </span>
        </div>
    </div>
<?php endif; ?>

<?php if ($invalidCsrf): ?>
    <div class="mb-6 p-4 bg-yellow-50 border-l-4 border-yellow-400 rounded-r-lg">
        <div class="flex items-center gap-2">
            <svg class="w-5 h-5 text-yellow-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M4.93 4.93l14.14 14.14"></path>
            </svg>
            <span class="text-sm text-yellow-700 font-medium">Your form expired. Please try signing in again.</span>
        </div>
    </div>
<?php endif; ?>

<!-- Error Alert -->
<?php if ($error): ?>
    <div class="mb-6 p-4 bg-red-50 border-l-4 border-red-500 rounded-r-lg">
        <div class="flex items-center gap-2">
            <svg class="w-5 h-5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            <span class="text-sm text-red-700"><?php echo e($error); ?></span>
        </div>
    </div>
<?php endif; ?>

<!-- Form Header -->
<div class="mb-10">
    <h2 class="text-[#111317] text-3xl font-bold tracking-tight mb-2">Authentication</h2>
    <p class="text-gray-500 text-sm">Please enter your credentials to access your dashboard.</p>
</div>

<!-- Login Form -->
<form method="POST" class="flex flex-col gap-8">
    <input type="hidden" name="csrf_token" value="<?php echo e(Auth::csrfToken()); ?>">

    <div class="auth-field">
        <label class="auth-label">Email Address</label>
        <input type="email" name="email" required autofocus
            class="auth-input"
            placeholder="name@company.com"
            value="<?php echo e($_POST['email'] ?? ''); ?>">
    </div>

    <div class="auth-field">
        <label class="auth-label">Password</label>
        <div class="auth-input-wrap">
            <input id="desktop-login-password" type="password" name="password" required
                class="auth-input has-trailing-icon"
                placeholder="Enter password">
            <button type="button" class="auth-visibility-toggle" data-visible="false" onclick="toggleAuthVisibility('desktop-login-password', this)" aria-label="Show value">
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

    <div class="auth-field">
        <label class="auth-label">Master Password</label>
        <div class="auth-input-wrap">
            <input id="desktop-login-master-password" type="password" name="master_password" required
                class="auth-input has-trailing-icon"
                placeholder="Enter security key">
            <button type="button" class="auth-visibility-toggle" data-visible="false" onclick="toggleAuthVisibility('desktop-login-master-password', this)" aria-label="Show value">
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
        <p class="auth-helper">Your data encryption key.</p>
    </div>

    <div class="flex items-center justify-between">
        <label class="inline-flex items-center gap-2 text-xs text-gray-500 uppercase tracking-wide cursor-pointer select-none">
            <input type="checkbox" name="remember_me" value="1"
                class="h-4 w-4 rounded border-gray-300 text-black focus:ring-black"
                <?php echo !empty($_POST['remember_me']) ? 'checked' : ''; ?>>
            <span>Remember me</span>
        </label>
        <?php if (isPasswordResetEnabled()): ?>
            <a href="?page=forgot-password" class="text-[10px] font-bold uppercase tracking-[0.1em] text-primary hover:underline">Forgot?</a>
        <?php endif; ?>
    </div>

    <button type="submit"
        class="w-full py-3.5 bg-black text-white rounded-lg font-bold text-sm uppercase tracking-[0.2em] hover:bg-gray-800 transition-all flex items-center justify-center gap-2">
        Sign In
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"></path>
        </svg>
    </button>
</form>

<!-- Footer -->
<div class="mt-10 pt-8 border-t border-gray-100 flex flex-col items-center gap-4">
    <?php if (isRegistrationEnabled()): ?>
    <p class="text-sm text-gray-500">Don't have an account?</p>
    <a href="?page=register" class="text-[11px] font-bold uppercase tracking-[0.2em] border border-black text-black px-6 py-2 rounded hover:bg-black hover:text-white transition-all">
        Create Account
    </a>
    <?php else: ?>
    <p class="text-sm text-gray-500 text-center">This installation is running in single-user mode. Public registration is disabled.</p>
    <?php endif; ?>
</div>
