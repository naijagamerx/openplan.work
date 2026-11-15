<?php
require_once __DIR__ . '/partials/public-site-context.php';
$pageTitle = $siteName . ' | Pricing';
$pageDescription = 'See what comes with the free OpenPlan workspace and review any active plans for built-in AI requests.';
$canonicalUrl = $publicPricingUrl;
$ogImageAlt = 'OpenPlan dashboard showing the product workspace with sample records';
$publicHeaderCurrentPage = 'pricing';
require __DIR__ . '/partials/public-site-head.php';
?>
<body class="marketing-page marketing-subpage">
<?php require __DIR__ . '/partials/public-site-header.php'; ?>
<main id="main">
    <section class="public-page-hero section-wrap" id="top" aria-labelledby="pricing-title">
        <div class="public-page-hero__copy">
            <p class="eyebrow"><span class="eyebrow__number">07</span> Pricing</p>
            <h1 id="pricing-title">Start free.<br><span>Add AI when you want it.</span></h1>
            <p class="hero__lede">Keep the everyday work in one place. The workspace is free; published plans add built-in AI requests if and when they’re available.</p>
            <a class="button button--dark" href="<?php echo e($primaryActionUrl); ?>"><?php echo e($primaryActionText); ?> <span aria-hidden="true">↗</span></a>
        </div>
        <figure class="public-screen public-screen--hero">
            <img src="<?php echo e($publicBaseUrl); ?>/assets/images/app-dashboard.webp" width="1600" height="1000" alt="OpenPlan dashboard with example projects, tasks, habits, and revenue totals. Sample workspace data." fetchpriority="high" decoding="async" data-dashboard-shot>
            <figcaption>Dashboard screen · sample workspace data</figcaption>
        </figure>
    </section>

    <section class="section section--pricing public-price-section" id="plans" aria-labelledby="plans-title">
        <div class="section-wrap">
            <div class="pricing-heading">
                <p class="eyebrow"><span class="eyebrow__number">07</span> The price, up front</p>
                <h2 id="plans-title">One workspace. No charge to get started.</h2>
                <p class="section-lede">Start with the free workspace. Any active built-in AI plans are listed here from the current plan catalogue.</p>
            </div>
            <div class="pricing-grid">
                <?php require __DIR__ . '/partials/public-pricing-cards.php'; ?>
            </div>
            <?php if ($plans === []): ?>
                <p class="pricing-note">No built-in AI plans are currently published. You can still choose to configure the assistant with your own provider keys.</p>
            <?php elseif (!$isAuthenticated): ?>
                <p class="pricing-note">Choosing a plan asks you to log in first. You can start with the free workspace, then choose a published plan.</p>
            <?php endif; ?>
        </div>
    </section>

    <section class="section public-includes" id="included" aria-labelledby="included-title">
        <div class="section-wrap public-split">
            <figure class="public-screen public-screen--wide">
                <img src="<?php echo e($publicBaseUrl); ?>/assets/images/app-invoices.webp" width="1400" height="1064" alt="OpenPlan invoice list with example clients, invoice statuses, and sample amounts." loading="lazy" decoding="async">
                <figcaption>Invoice screen · sample workspace data</figcaption>
            </figure>
            <div class="public-copy">
                <p class="eyebrow"><span class="eyebrow__number">01</span> Already included</p>
                <h2 id="included-title">The useful parts of running work, together.</h2>
                <p class="section-lede">The free workspace brings day-to-day work into one place, from planning a project to keeping its invoice nearby.</p>
                <ul class="public-check-list">
                    <li>Projects and tasks</li>
                    <li>Clients, quotes, and invoices</li>
                    <li>Notes, habits, and everyday planning</li>
                    <li>Optional AI assistant with a provider you configure</li>
                </ul>
            </div>
        </div>
    </section>

    <section class="section public-close" aria-labelledby="pricing-close-title">
        <div class="section-wrap closing-panel">
            <p class="eyebrow eyebrow--light">A simple place to begin</p>
            <h2 id="pricing-close-title">Start with the work you already have.</h2>
            <p>See how the workspace brings projects, clients, and daily plans together.</p>
            <div class="closing-actions">
                <a class="button button--light" href="<?php echo e($publicHowItWorksUrl); ?>">How it works <span aria-hidden="true">↗</span></a>
                <a class="text-link text-link--light" href="<?php echo e($publicMobileUrl); ?>">See mobile <span aria-hidden="true">↗</span></a>
            </div>
        </div>
    </section>
</main>
<?php require __DIR__ . '/partials/public-site-footer.php'; ?>
<?php require __DIR__ . '/partials/public-site-scripts.php'; ?>
</body>
</html>
