<?php
// Login page — step 1 of 2-step login (email + password).
// POST processing happens in index.php BEFORE the layout is included so cookies/redirects
// work cleanly. Here we only consume any flash error left in the session.

$reason = (string)($_GET['reason'] ?? '');
$sessionExpired = in_array($reason, ['session_expired', 'session_timeout', 'session_missing', 'token_restore_failed'], true);
$invalidCsrf = isset($_GET['reason']) && $_GET['reason'] === 'invalid_csrf';

$error = (string)($_SESSION['login_step1_error'] ?? '');
$lastEmail = (string)($_SESSION['login_step1_email'] ?? '');
unset($_SESSION['login_step1_error'], $_SESSION['login_step1_email']);
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
<?php if ($error !== ''): ?>
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
    <p class="text-gray-500 text-sm">Step 1 of 2 — enter your email and password to continue.</p>
</div>

<!-- Login Form (Step 1) -->
<form method="POST" class="flex flex-col gap-8">
    <input type="hidden" name="csrf_token" value="<?php echo e(Auth::csrfToken()); ?>">

    <div class="auth-field">
        <label class="auth-label">Email Address</label>
        <input type="email" name="email" required autofocus
            class="auth-input"
            placeholder="name@company.com"
            value="<?php echo e($lastEmail); ?>">
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

    <div class="flex items-center justify-between">
        <label class="inline-flex items-center gap-2 text-xs text-gray-500 uppercase tracking-wide cursor-pointer select-none">
            <input type="checkbox" name="remember_me" value="1"
                class="h-4 w-4 rounded border-gray-300 text-black focus:ring-black">
            <span>Remember me</span>
        </label>
        <?php if (isPasswordResetEnabled()): ?>
            <a href="?page=forgot-password" class="text-[10px] font-bold uppercase tracking-[0.1em] text-primary hover:underline">Forgot?</a>
        <?php endif; ?>
    </div>

    <button type="submit"
        class="w-full py-3.5 bg-black text-white rounded-lg font-bold text-sm uppercase tracking-[0.2em] hover:bg-gray-800 transition-all flex items-center justify-center gap-2">
        Continue
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
