<?php

$registrationError = isset($registrationError) ? (string)$registrationError : '';
$registrationValues = isset($registrationValues) && is_array($registrationValues) ? $registrationValues : [];

?>

<?php if ($registrationError): ?>
    <div class="mb-6 p-4 bg-red-50 border-l-4 border-red-500 rounded-r-lg">
        <div class="flex items-center gap-2">
            <svg class="w-5 h-5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            <span class="text-sm text-red-700"><?php echo e($registrationError); ?></span>
        </div>
    </div>
<?php endif; ?>

<div class="mb-10">
    <h2 class="text-[#111317] text-3xl font-bold tracking-tight mb-2">Create Account</h2>
    <p class="text-gray-500 text-sm">Set up your encrypted workspace account.</p>
</div>

<form method="POST" class="flex flex-col gap-8">
    <input type="hidden" name="csrf_token" value="<?php echo e(Auth::csrfToken()); ?>">

    <div class="auth-field">
        <label class="auth-label">Full Name</label>
        <input type="text" name="name" required
               class="auth-input"
               placeholder="Your name"
               value="<?php echo e((string)($registrationValues['name'] ?? '')); ?>">
    </div>

    <div class="auth-field">
        <label class="auth-label">Email Address</label>
        <input type="email" name="email" required
               class="auth-input"
               placeholder="name@company.com"
               value="<?php echo e((string)($registrationValues['email'] ?? '')); ?>">
    </div>

    <div class="auth-field">
        <label class="auth-label">Password</label>
        <div class="auth-input-wrap">
            <input id="desktop-register-password" type="password" name="password" required
                   class="auth-input has-trailing-icon"
                   placeholder="Enter password">
            <button type="button" class="auth-visibility-toggle" data-visible="false" onclick="toggleAuthVisibility('desktop-register-password', this)" aria-label="Show value">
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
            <input id="desktop-register-confirm" type="password" name="confirm_password" required
                   class="auth-input has-trailing-icon"
                   placeholder="Confirm password">
            <button type="button" class="auth-visibility-toggle" data-visible="false" onclick="toggleAuthVisibility('desktop-register-confirm', this)" aria-label="Show value">
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
            <input id="desktop-register-master" type="password" name="master_password" required
                   class="auth-input has-trailing-icon"
                   placeholder="Enter security key">
            <button type="button" class="auth-visibility-toggle" data-visible="false" onclick="toggleAuthVisibility('desktop-register-master', this)" aria-label="Show value">
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
        <p class="auth-helper">This key encrypts your data. Keep it safe.</p>
    </div>

    <button type="submit"
            class="w-full py-3.5 bg-black text-white rounded-lg font-bold text-sm uppercase tracking-[0.2em] hover:bg-gray-800 transition-all flex items-center justify-center gap-2">
        Create Account
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"></path>
        </svg>
    </button>
</form>

<div class="mt-10 pt-8 border-t border-gray-100 flex flex-col items-center gap-4">
    <p class="text-sm text-gray-500">Already have an account?</p>
    <a href="?page=login" class="text-[11px] font-bold uppercase tracking-[0.2em] border border-black text-black px-6 py-2 rounded hover:bg-black hover:text-white transition-all">
        Sign In
    </a>
</div>

