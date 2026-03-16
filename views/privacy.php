<?php
$pageTitle = 'Privacy | ' . getPublicAppName();
?>

<div class="mb-16">
    <div class="mb-8 inline-flex items-center justify-center w-12 h-12 rounded-full border border-black">
        <svg class="w-6 h-6 text-black" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3l7 4v5c0 5-3.5 8-7 9-3.5-1-7-4-7-9V7l7-4z"></path>
        </svg>
    </div>
    <h2 class="text-[#000000] text-5xl lg:text-7xl font-light tracking-tight mb-8 leading-tight">
        Privacy<br>
        <span class="font-black italic uppercase">policy.</span>
    </h2>
    <div class="space-y-6 max-w-2xl text-sm leading-7 text-gray-600">
        <p><?php echo e(getPublicAppName()); ?> is designed to help users manage tasks, projects, notes, and productivity data without giving the operator ownership of that information.</p>
        <p>We do not claim rights over the content you create in the app. Your records, notes, schedules, and uploaded information remain your responsibility and your data.</p>
        <p>Where encryption is used, your master key or password is critical. The platform is intended to keep your information private from unauthorized access, but that also means we may not be able to recover encrypted data if you lose the key required to decrypt it.</p>
        <p>Mail, verification, and password-reset services may rely on third-party infrastructure such as SMTP providers. Those services are used only to support account access and operational messaging.</p>
        <p>If you choose to run the project in a hosted environment, you are responsible for the operational settings, retention decisions, and security practices used for that deployment.</p>
    </div>
</div>

<div class="mt-12">
    <a href="?page=login" class="group inline-flex w-full lg:w-auto px-12 bg-black text-white h-16 rounded-none font-bold text-xs uppercase tracking-[0.4em] hover:bg-gray-900 transition-all items-center justify-center gap-4">
        Back to Sign In
        <svg class="h-4 w-4 transition-transform group-hover:translate-x-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"></path></svg>
    </a>
</div>
