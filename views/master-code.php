<?php
// Master code page — step 2 of 2-step login.
// Guard + POST handler run in index.php BEFORE the auth layout so header() works.
// This view just renders the form and reads any flash error from the session.

$pendingEmail = (string)($_SESSION['pending_master_email'] ?? '');
$error = (string)($_SESSION['master_code_error'] ?? '');
unset($_SESSION['master_code_error']);
?>

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

<div class="mb-10">
    <h2 class="text-[#111317] text-3xl font-bold tracking-tight mb-2">Master Code</h2>
    <p class="text-gray-500 text-sm">Step 2 of 2 — enter your master password to decrypt your dashboard.</p>
    <p class="mt-3 text-[10px] font-bold uppercase tracking-[0.2em] text-gray-400">Signed in as</p>
    <p class="text-sm font-semibold text-gray-900"><?php echo e($pendingEmail); ?></p>
</div>

<form method="POST" class="flex flex-col gap-8">
    <input type="hidden" name="csrf_token" value="<?php echo e(Auth::csrfToken()); ?>">

    <div class="auth-field">
        <label class="auth-label">Master Password</label>
        <div class="auth-input-wrap">
            <input id="desktop-master-code" type="password" name="master_password" required autofocus
                class="auth-input has-trailing-icon"
                placeholder="Enter security key">
            <button type="button" class="auth-visibility-toggle" data-visible="false" onclick="toggleAuthVisibility('desktop-master-code', this)" aria-label="Show value">
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

    <button type="submit"
        class="w-full py-3.5 bg-black text-white rounded-lg font-bold text-sm uppercase tracking-[0.2em] hover:bg-gray-800 transition-all flex items-center justify-center gap-2">
        Sign In
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"></path>
        </svg>
    </button>
</form>

<div class="mt-10 pt-8 border-t border-gray-100 flex flex-col items-center gap-4">
    <a href="?page=login" class="text-[11px] font-bold uppercase tracking-[0.2em] border border-black text-black px-6 py-2 rounded hover:bg-black hover:text-white transition-all inline-flex items-center gap-2">
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
        </svg>
        Back to Sign In
    </a>
</div>
