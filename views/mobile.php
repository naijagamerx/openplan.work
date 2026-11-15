<?php
require_once __DIR__ . '/partials/public-site-context.php';
$pageTitle = $siteName . ' | Mobile for Android';
$pageDescription = 'Keep tasks, project progress, and everyday tools close with OpenPlan on mobile. Check the current Android download availability.';
$canonicalUrl = $publicMobileUrl;
$ogImage = $publicBaseUrl . '/assets/images/app-mobile-dashboard.webp';
$ogImageAlt = 'OpenPlan mobile dashboard with sample workspace data';
$publicHeaderCurrentPage = 'mobile';
require __DIR__ . '/partials/public-site-head.php';
?>
<body class="marketing-page marketing-subpage">
<?php require __DIR__ . '/partials/public-site-header.php'; ?>
<main id="main">
    <section class="public-mobile-hero section-wrap" id="top" aria-labelledby="mobile-title">
        <div class="public-mobile-copy">
            <p class="eyebrow"><span class="eyebrow__number">01</span> Work goes where you go</p>
            <h1 id="mobile-title">Your workspace, ready for the day you’re having.</h1>
            <p class="hero__lede">Check the next task, see project progress, and keep everyday tools close when you’re away from your desk.</p>
            <?php if ($hasApkRelease): ?>
                <a class="button button--dark" href="<?php echo e($apkDownloadUrl); ?>" download>Download for Android <span aria-hidden="true">↓</span></a>
                <p class="public-mobile-caption">Android download available for this installation.</p>
            <?php else: ?>
                <a class="button button--dark" href="#android-access">Explore Android access <span aria-hidden="true">↓</span></a>
                <p class="public-mobile-caption">A direct download will appear here when an Android package is published.</p>
            <?php endif; ?>
        </div>
        <figure class="public-phone-frame">
            <div class="public-phone-frame__bezel">
                <img src="<?php echo e($publicBaseUrl); ?>/assets/images/app-mobile-dashboard.webp" width="600" height="1298" alt="OpenPlan mobile dashboard showing example tasks, projects, habits, and water tracking. Sample workspace data." fetchpriority="high" decoding="async">
            </div>
            <figcaption>Mobile dashboard · sample workspace data</figcaption>
        </figure>
    </section>

    <section class="section public-mobile-benefits" id="mobile-details" aria-labelledby="mobile-details-title">
        <div class="section-wrap">
            <div class="public-section-heading">
                <p class="eyebrow"><span class="eyebrow__number">02</span> The essentials, in reach</p>
                <h2 id="mobile-details-title">Pick up where the day takes you.</h2>
                <p class="section-lede">Keep the familiar pieces of the workspace close without squeezing the desktop page into a phone.</p>
            </div>
            <div class="public-mobile-grid">
                <article><span>01</span><h3>See the day</h3><p>Bring tasks, active projects, and habit progress into one mobile starting point.</p></article>
                <article><span>02</span><h3>Move between work</h3><p>Use the menu to reach the modules you need as the day changes shape.</p></article>
                <article><span>03</span><h3>Keep your routine</h3><p>Check habits and everyday tools alongside the work you’re coordinating.</p></article>
            </div>
        </div>
    </section>

    <section class="section public-mobile-proof" id="mobile-views" aria-labelledby="mobile-views-title">
        <div class="section-wrap public-split public-split--mobile">
            <div class="public-copy">
                <p class="eyebrow"><span class="eyebrow__number">03</span> Made for real screens</p>
                <h2 id="mobile-views-title">A clear view without losing the detail.</h2>
                <p class="section-lede">The mobile dashboard keeps the same work nearby in a layout designed around a phone-sized screen.</p>
                <a class="text-link" href="<?php echo e($publicHowItWorksUrl); ?>">See how the work connects <span aria-hidden="true">↗</span></a>
            </div>
            <figure class="public-screen public-screen--wide"><img src="<?php echo e($publicBaseUrl); ?>/assets/images/app-dashboard.webp" width="1600" height="1000" alt="OpenPlan desktop dashboard with sample tasks and project summaries." decoding="async" data-dashboard-shot><figcaption>Desktop dashboard · sample workspace data</figcaption></figure>
        </div>
    </section>

    <section class="section public-android-access" id="android-access" aria-labelledby="android-access-title">
        <div class="section-wrap public-android-access__inner">
            <div>
                <p class="eyebrow"><span class="eyebrow__number">04</span> Android access</p>
                <h2 id="android-access-title">Get the build for this installation.</h2>
                <?php if ($hasApkRelease): ?>
                    <p class="section-lede">An Android package is published and ready to download.</p>
                    <a class="button button--dark" href="<?php echo e($apkDownloadUrl); ?>" download>Download for Android <span aria-hidden="true">↓</span></a>
                <?php else: ?>
                    <p class="section-lede">There isn’t an Android package published on this installation right now. The download link will appear here when one is available.</p>
                    <a class="text-link" href="<?php echo e($publicDocsUrl); ?>#mobile-access">Read the mobile guide <span aria-hidden="true">↗</span></a>
                <?php endif; ?>
            </div>
            <div class="public-android-access__links">
                <a href="<?php echo e($publicPricingUrl); ?>">Pricing <span aria-hidden="true">↗</span></a>
                <a href="<?php echo e($publicDocsUrl); ?>">Documentation <span aria-hidden="true">↗</span></a>
            </div>
        </div>
    </section>
</main>
<?php require __DIR__ . '/partials/public-site-footer.php'; ?>
<?php require __DIR__ . '/partials/public-site-scripts.php'; ?>
</body>
</html>
