<?php
$error = '';
$success = false;
$featureAvailable = isPasswordResetEnabled();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $featureAvailable) {
    $email = trim($_POST['email'] ?? '');
    $csrfToken = $_POST['csrf_token'] ?? '';

    if (!Auth::validateCsrf($csrfToken)) {
        $error = 'Invalid request token. Please refresh and try again.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Enter a valid email address';
    } else {
        $db = new Database(APP_NAME);
        $auth = new Auth($db);
        $result = $auth->issuePasswordReset($email);

        if ($result !== null) {
            $mailer = new Mailer();
            $mailer->sendPasswordResetEmail($result['user'], $result['token']);
        }

        $success = true;
    }
}
?>

<div class="mb-10">
    <h2 class="text-[#111317] text-3xl font-bold tracking-tight mb-2">Reset Password</h2>
    <p class="text-gray-500 text-sm">Use the same desktop auth flow to request a secure reset link.</p>
</div>

<?php if (!$featureAvailable): ?>
    <div class="rounded-lg border border-gray-200 bg-gray-50 px-5 py-4 text-sm text-gray-600">
        Password reset is not available in this installation.
    </div>
<?php elseif ($success): ?>
    <div class="rounded-lg border border-green-200 bg-green-50 px-5 py-4 text-sm text-green-700">
        If the email exists, a reset link has been sent.
    </div>
<?php else: ?>
    <?php if ($error): ?>
        <div class="mb-6 p-4 bg-red-50 border-l-4 border-red-500 rounded-r-lg text-sm text-red-700"><?php echo e($error); ?></div>
    <?php endif; ?>

    <form method="POST" class="flex flex-col gap-8">
        <input type="hidden" name="csrf_token" value="<?php echo e(Auth::csrfToken()); ?>">

        <div class="auth-field">
            <label class="auth-label">Email Address</label>
            <input type="email" name="email" required autofocus
                class="auth-input"
                placeholder="name@company.com"
                value="<?php echo e($_POST['email'] ?? ''); ?>">
        </div>

        <button type="submit"
            class="w-full py-3.5 bg-black text-white rounded-lg font-bold text-sm uppercase tracking-[0.2em] hover:bg-gray-800 transition-all flex items-center justify-center gap-2">
            Send Reset Link
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