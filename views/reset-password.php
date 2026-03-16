<?php
$error = '';
$success = false;
$token = trim((string)($_GET['token'] ?? $_POST['token'] ?? ''));
$featureAvailable = isPasswordResetEnabled();
$tokenValid = false;

if ($featureAvailable && $token !== '') {
    $db = new Database(APP_NAME);
    $auth = new Auth($db);
    $tokenValid = $auth->isValidPasswordResetToken($token);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        $csrfToken = $_POST['csrf_token'] ?? '';

        if (!Auth::validateCsrf($csrfToken)) {
            $error = 'Invalid request token. Please refresh and try again.';
        } elseif (!$tokenValid) {
            $error = 'This reset link is invalid or expired';
        } elseif (strlen($password) < 8) {
            $error = 'Password must be at least 8 characters';
        } elseif ($password !== $confirmPassword) {
            $error = 'Passwords do not match';
        } else {
            $result = $auth->resetPasswordWithToken($token, $password);
            if ($result['success']) {
                $success = true;
            } else {
                $error = $result['error'];
            }
        }
    }
}
?>

<div class="mb-10">
    <h2 class="text-[#111317] text-3xl font-bold tracking-tight mb-2">Choose a New Password</h2>
    <p class="text-gray-500 text-sm">Use the reset link we emailed to set a new sign-in password.</p>
</div>

<?php if (!$featureAvailable): ?>
    <div class="rounded-lg border border-gray-200 bg-gray-50 px-5 py-4 text-sm text-gray-600">
        Password reset is not available in this installation.
    </div>
<?php elseif ($success): ?>
    <div class="rounded-lg border border-green-200 bg-green-50 px-5 py-4 text-sm text-green-700">
        Your password has been updated. You can sign in again now.
    </div>
<?php elseif (!$tokenValid): ?>
    <div class="rounded-lg border border-red-200 bg-red-50 px-5 py-4 text-sm text-red-700">
        This reset link is invalid or has expired.
    </div>
<?php else: ?>
    <?php if ($error): ?>
        <div class="mb-6 p-4 bg-red-50 border-l-4 border-red-500 rounded-r-lg text-sm text-red-700"><?php echo e($error); ?></div>
    <?php endif; ?>

    <form method="POST" class="flex flex-col gap-8">
        <input type="hidden" name="csrf_token" value="<?php echo e(Auth::csrfToken()); ?>">
        <input type="hidden" name="token" value="<?php echo e($token); ?>">

        <div class="auth-field">
            <label class="auth-label">New Password</label>
            <div class="auth-input-wrap">
                <input id="desktop-reset-password" type="password" name="password" required minlength="8"
                    class="auth-input has-trailing-icon"
                    placeholder="Create password">
                <button type="button" class="auth-visibility-toggle" data-visible="false" onclick="toggleAuthVisibility('desktop-reset-password', this)" aria-label="Show value">
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
            <label class="auth-label">Confirm Password</label>
            <div class="auth-input-wrap">
                <input id="desktop-reset-confirm-password" type="password" name="confirm_password" required minlength="8"
                    class="auth-input has-trailing-icon"
                    placeholder="Repeat password">
                <button type="button" class="auth-visibility-toggle" data-visible="false" onclick="toggleAuthVisibility('desktop-reset-confirm-password', this)" aria-label="Show value">
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

        <button type="submit"
            class="w-full py-3.5 bg-black text-white rounded-lg font-bold text-sm uppercase tracking-[0.2em] hover:bg-gray-800 transition-all flex items-center justify-center gap-2">
            Save New Password
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"></path>
            </svg>
        </button>
    </form>
<?php endif; ?>

<div class="mt-10 pt-8 border-t border-gray-100 flex flex-col items-center gap-4">
    <a href="?page=login" class="text-[11px] font-bold uppercase tracking-[0.2em] border border-black text-black px-6 py-2 rounded hover:bg-black hover:text-white transition-all">
        Back to Sign In
    </a>
</div>
