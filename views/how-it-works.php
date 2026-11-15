<?php
require_once __DIR__ . '/partials/public-site-context.php';
$pageTitle = $siteName . ' | How it works';
$pageDescription = 'See how OpenPlan connects client relationships, project plans, tasks, invoices, and everyday routines.';
$canonicalUrl = $publicHowItWorksUrl;
$ogImageAlt = 'OpenPlan dashboard with sample workspace data';
$publicHeaderCurrentPage = 'how-it-works';
require __DIR__ . '/partials/public-site-head.php';
?>
<body class="marketing-page marketing-subpage">
<?php require __DIR__ . '/partials/public-site-header.php'; ?>
<main id="main">
    <section class="public-page-hero public-page-hero--how section-wrap" id="top" aria-labelledby="how-title">
        <div class="public-page-hero__copy">
            <p class="eyebrow"><span class="eyebrow__number">01</span> How it works</p>
            <h1 id="how-title">Keep the work connected, from first hello to final invoice.</h1>
            <p class="hero__lede">OpenPlan brings the people, plans, and follow-through of a small business into one shared workspace.</p>
            <a class="button button--dark" href="#the-steps">Follow the work <span aria-hidden="true">↓</span></a>
        </div>
        <figure class="public-screen public-screen--hero">
            <img src="<?php echo e($publicBaseUrl); ?>/assets/images/app-dashboard.webp" width="1600" height="1000" alt="OpenPlan dashboard showing example projects, tasks, habits, and revenue totals. Sample workspace data." fetchpriority="high" decoding="async" data-dashboard-shot>
            <figcaption>Dashboard screen · sample workspace data</figcaption>
        </figure>
    </section>

    <section class="section public-steps" id="the-steps" aria-labelledby="steps-title">
        <div class="section-wrap">
            <div class="public-section-heading">
                <p class="eyebrow"><span class="eyebrow__number">02</span> A natural flow</p>
                <h2 id="steps-title">One piece of work can carry its context with it.</h2>
                <p class="section-lede">Move between each step without losing sight of the relationship behind the work.</p>
            </div>
            <div class="public-step-list">
                <article><span>01</span><div><h3>Start with the people</h3><p>Keep client details in a place you can find when a new conversation begins.</p></div><span class="public-step-arrow" aria-hidden="true">↘</span></article>
                <article><span>02</span><div><h3>Shape the project</h3><p>Connect the project to its client, then gather the tasks and milestones around it.</p></div><span class="public-step-arrow" aria-hidden="true">↘</span></article>
                <article><span>03</span><div><h3>Move through the day</h3><p>Use tasks, dates, notes, and routines to keep the next step in view.</p></div><span class="public-step-arrow" aria-hidden="true">↘</span></article>
                <article><span>04</span><div><h3>Close the loop</h3><p>Keep quotes and invoices close to the client and project they belong to.</p></div><span class="public-step-arrow" aria-hidden="true">✓</span></article>
            </div>
        </div>
    </section>

    <section class="section public-feature" id="projects" aria-labelledby="projects-title">
        <div class="section-wrap public-feature-row">
            <div class="public-feature-copy">
                <p class="eyebrow"><span class="eyebrow__number">01</span> Keep the plan close</p>
                <h2 id="projects-title">See what’s moving across your projects.</h2>
                <p class="section-lede">The Projects view keeps project status and the tasks connected to the work in the same place.</p>
            </div>
            <figure class="public-screen public-screen--wide"><img src="<?php echo e($publicBaseUrl); ?>/assets/images/app-projects.webp" width="1400" height="1064" alt="OpenPlan Projects view with sample project names and completion progress." loading="lazy" decoding="async"><figcaption>Projects screen · sample workspace data</figcaption></figure>
        </div>
    </section>

    <section class="section public-feature public-feature--reverse" id="billing" aria-labelledby="billing-title">
        <div class="section-wrap public-feature-row">
            <div class="public-feature-copy">
                <p class="eyebrow"><span class="eyebrow__number">02</span> Keep the follow-through</p>
                <h2 id="billing-title">Keep client billing within the same story.</h2>
                <p class="section-lede">Bring invoices and their status into view alongside the client work they support.</p>
            </div>
            <figure class="public-screen public-screen--wide"><img src="<?php echo e($publicBaseUrl); ?>/assets/images/app-invoices.webp" width="1400" height="1064" alt="OpenPlan invoices view with sample client, status, due date, and amount values." loading="lazy" decoding="async"><figcaption>Invoices screen · sample workspace data</figcaption></figure>
        </div>
    </section>

    <section class="section public-proof" id="day-to-day" aria-labelledby="day-title">
        <div class="section-wrap public-proof-layout">
            <figure class="public-screen public-screen--wide"><img src="<?php echo e($publicBaseUrl); ?>/assets/images/app-habits.webp" width="1400" height="1064" alt="OpenPlan Habits view showing sample routines and weekly progress." loading="lazy" decoding="async"><figcaption>Habits screen · sample workspace data</figcaption></figure>
            <div>
                <p class="eyebrow"><span class="eyebrow__number">03</span> Make it your own</p>
                <h2 id="day-title">Leave room for the habits that make a day work.</h2>
                <p class="section-lede">Use the workspace for the project plan and the smaller routines around it—notes, habits, focus sessions, and daily lists.</p>
                <a class="text-link" href="<?php echo e($publicMobileUrl); ?>">See the mobile experience <span aria-hidden="true">↗</span></a>
            </div>
        </div>
    </section>

    <section class="section public-close" aria-labelledby="how-close-title">
        <div class="section-wrap closing-panel">
            <p class="eyebrow eyebrow--light">Your work, in context</p>
            <h2 id="how-close-title">Bring the next piece of work into view.</h2>
            <p>Explore the pages for pricing, mobile access, and workspace guidance.</p>
            <div class="closing-actions">
                <a class="button button--light" href="<?php echo e($publicPricingUrl); ?>">View pricing <span aria-hidden="true">↗</span></a>
                <a class="text-link text-link--light" href="<?php echo e($publicDocsUrl); ?>">Open documentation <span aria-hidden="true">↗</span></a>
            </div>
        </div>
    </section>
</main>
<?php require __DIR__ . '/partials/public-site-footer.php'; ?>
<?php require __DIR__ . '/partials/public-site-scripts.php'; ?>
</body>
</html>
