<?php
$pageTitle = 'Terms | ' . getPublicAppName();
?>

<div class="mb-16">
    <div class="mb-8 inline-flex items-center justify-center w-12 h-12 rounded-full border border-black">
        <svg class="w-6 h-6 text-black" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6M7 4h10a2 2 0 012 2v12a2 2 0 01-2 2H7a2 2 0 01-2-2V6a2 2 0 012-2z"></path>
        </svg>
    </div>
    <h2 class="text-[#000000] text-5xl lg:text-7xl font-light tracking-tight mb-8 leading-tight">
        Terms<br>
        <span class="font-black italic uppercase">of use.</span>
    </h2>
    <div class="space-y-6 max-w-2xl text-sm leading-7 text-gray-600">
        <p>By using <?php echo e(getPublicAppName()); ?>, you accept responsibility for the information you create, store, and manage in the app.</p>
        <p>You are responsible for protecting your login credentials and, where applicable, your master encryption key or password. That key is essential to accessing encrypted data.</p>
        <p>If you misplace or forget the encryption key required to unlock your data, recovery may not be possible. In that situation, the operator of the app cannot guarantee access restoration and is not responsible for resulting data loss.</p>
        <p>Hosted features such as email verification, password reset, and SMTP delivery support account workflows, but they do not replace your responsibility to keep access details secure and backed up appropriately.</p>
        <p>The service may be updated, reconfigured, or self-hosted in different environments. Operators and deployers remain responsible for configuring environment variables, mail transport, security settings, and data-handling practices correctly.</p>
    </div>
</div>

<div class="mt-12">
    <a href="?page=login" class="group inline-flex w-full lg:w-auto px-12 bg-black text-white h-16 rounded-none font-bold text-xs uppercase tracking-[0.4em] hover:bg-gray-900 transition-all items-center justify-center gap-4">
        Back to Sign In
        <svg class="h-4 w-4 transition-transform group-hover:translate-x-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"></path></svg>
    </a>
</div>
