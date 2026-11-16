<?php
$publicHeaderCurrentPage = $publicHeaderCurrentPage ?? ($page ?? 'homepage');
$publicHeaderLinks = [
    ['label' => 'Home', 'slug' => 'homepage'],
    ['label' => 'How it works', 'slug' => 'how-it-works'],
    ['label' => 'Mobile', 'slug' => 'mobile'],
    ['label' => 'Pricing', 'slug' => 'pricing'],
    ['label' => 'Docs', 'slug' => 'docs'],
];
?>
<a class="skip-link" href="#main">Skip to content</a>
<nav class="site-nav" aria-label="Main navigation">
    <div class="site-nav__inner">
        <a class="brand" href="<?php echo e($publicHomeUrl); ?>" aria-label="<?php echo e($siteName); ?> home">
            <span class="brand__mark" aria-hidden="true">
                <svg viewBox="0 0 48 48" fill="none"><path d="M24 4H6v26.6667h18V44h18V17.3333H24V4Z" fill="currentColor"/></svg>
            </span>
            <span class="brand__name"><?php echo e($siteName); ?></span>
        </a>

        <button class="nav-toggle" id="nav-toggle" type="button" aria-label="Open navigation" aria-controls="site-links" aria-expanded="false">
            <svg class="icon nav-toggle__open" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>
            <svg class="icon nav-toggle__close" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="m6 6 12 12M18 6 6 18" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>
        </button>

        <div class="site-nav__links" id="site-links">
            <?php foreach ($publicHeaderLinks as $link): ?>
                <a href="<?php echo e($publicRouteUrl($link['slug'])); ?>"<?php echo $publicHeaderCurrentPage === $link['slug'] ? ' aria-current="page"' : ''; ?>><?php echo e($link['label']); ?></a>
            <?php endforeach; ?>
            <a href="<?php echo e($repoUrl); ?>" target="_blank" rel="noreferrer">Open source <?php echo $publicIcon('arrow-up-right'); ?></a>
            <a href="<?php echo e($sourceDownloadUrl); ?>" aria-label="Download the source code (zip)">Download</a>
            <a class="site-nav__account" href="<?php echo e($isAuthenticated ? $publicRouteUrl('dashboard') : $publicRouteUrl('login')); ?>"><?php echo $isAuthenticated ? 'Dashboard' : 'Log in'; ?></a>
        </div>

        <div class="site-nav__actions">
            <?php if (!$isAuthenticated): ?>
                <a class="nav-login" href="<?php echo e($publicRouteUrl('login')); ?>">Log in</a>
            <?php endif; ?>
            <button class="theme-toggle" id="theme-toggle" type="button" aria-label="Switch color theme">
                <svg class="icon theme-toggle__sun" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="4" stroke="currentColor" stroke-width="1.6"/><path d="M12 2v2m0 16v2M4.93 4.93l1.42 1.42m11.3 11.3 1.42 1.42M2 12h2m16 0h2M4.93 19.07l-1.42 1.42m11.3-11.3 1.42-1.42" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
                <svg class="icon theme-toggle__moon" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M20.2 15.2A8.6 8.6 0 0 1 8.8 3.8a8.6 8.6 0 1 0 11.4 11.4Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/></svg>
            </button>
            <?php
            // Phones get a short label so the logo, theme toggle, button and menu fit on one row.
            $navCtaShort = $isAuthenticated ? 'Dashboard' : ($registrationEnabled ? 'Sign up' : 'Log in');
            ?>
            <a class="button button--small button--dark nav-cta" href="<?php echo e($primaryActionUrl); ?>"><span class="nav-cta__long"><?php echo e($isAuthenticated ? 'Dashboard' : $primaryActionText); ?></span><span class="nav-cta__short" aria-hidden="true"><?php echo e($navCtaShort); ?></span></a>
        </div>
    </div>
</nav>
