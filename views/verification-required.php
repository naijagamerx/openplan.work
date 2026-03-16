<?php
if (!Auth::check()) {
    header('Location: ' . APP_URL . '?page=login');
    exit;
}

$user = Auth::user();
$error = '';
$success = '';
$autoNotice = $_SESSION['verification_notice'] ?? '';
unset($_SESSION['verification_notice']);
$masterPassword = getMasterPassword();
$canResend = isEmailVerificationEnabled() && $masterPassword !== '' && $user !== null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $canResend) {
    $csrfToken = $_POST['csrf_token'] ?? '';

    if (!Auth::validateCsrf($csrfToken)) {
        $error = 'Invalid request token. Please refresh and try again.';
    } else {
        $db = new Database($masterPassword);
        $auth = new Auth($db);
        $result = $auth->issueEmailVerification((string)$user['id']);

        if (!empty($result['success']) && !empty($result['updated']) && !empty($result['users'])) {
            if ($auth->persistUsers($result['users'])) {
                $userDb = new Database($masterPassword, (string)$user['id']);
                $mailer = new Mailer($userDb->load('config', false));
                $mailer->sendVerificationEmail($result['user'], $result['token']);
                $success = 'A new verification link has been sent.';
            } else {
                $error = 'Unable to prepare a new verification email right now.';
            }
        } elseif (!empty($result['already_verified'])) {
            $_SESSION['auth_verification_required'] = false;
            header('Location: ' . APP_URL . '?page=dashboard');
            exit;
        } else {
            $error = $result['error'] ?? 'Unable to resend verification email right now.';
        }
    }
}
?>

<div class="mb-10">
    <h2 class="text-[#111317] text-3xl font-bold tracking-tight mb-2">Verification Required</h2>
    <p class="text-gray-500 text-sm">Your account is signed in, but the dashboard stays locked until your email is verified.</p>
</div>

<?php if ($success !== ''): ?>
    <div class="mb-6 rounded-lg border border-green-200 bg-green-50 px-5 py-4 text-sm text-green-700"><?php echo e($success); ?></div>
<?php endif; ?>
<?php if ($autoNotice !== ''): ?>
    <div class="mb-6 rounded-lg border border-blue-200 bg-blue-50 px-5 py-4 text-sm text-blue-700"><?php echo e($autoNotice); ?></div>
<?php endif; ?>
<?php if ($error !== ''): ?>
    <div class="mb-6 rounded-lg border border-red-200 bg-red-50 px-5 py-4 text-sm text-red-700"><?php echo e($error); ?></div>
<?php endif; ?>

<div class="space-y-6">
    <div class="rounded-2xl border border-gray-200 p-6">
        <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-gray-400 mb-3">Signed in as</p>
        <p class="text-lg font-semibold text-gray-900"><?php echo e($user['email'] ?? ''); ?></p>
        <p class="text-sm text-gray-500 mt-3">Check your inbox for the verification link we sent when you registered.</p>
    </div>

    <?php if ($canResend): ?>
        <form method="POST" class="flex flex-col gap-4">
            <input type="hidden" name="csrf_token" value="<?php echo e(Auth::csrfToken()); ?>">
            <button type="submit"
                class="w-full py-3.5 bg-black text-white rounded-lg font-bold text-sm uppercase tracking-[0.2em] hover:bg-gray-800 transition-all flex items-center justify-center gap-2">
                Resend Verification Link
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"></path></svg>
            </button>
        </form>
    <?php endif; ?>
</div>

<div class="mt-10 pt-8 border-t border-gray-100 flex flex-col items-center gap-4">
    <a href="api/auth.php?action=logout" class="text-[11px] font-bold uppercase tracking-[0.2em] border border-black text-black px-6 py-2 rounded hover:bg-black hover:text-white transition-all">
        Sign Out
    </a>
</div>

