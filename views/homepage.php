<?php
$siteName = getPublicAppName();
$pageTitle = $siteName . ' | Make room for work that matters';
$pageDescription = 'Bring projects, clients, invoices, notes, and everyday plans together in one clear workspace for running your business.';
$repoUrl = 'https://github.com/naijagamerx/openplan.work';
$publicDocsUrl = APP_URL . '?page=docs';
$canonicalUrl = rtrim(APP_URL, '/') . '/';
$heroImageUrl = APP_URL . '/assets/images/openplan-landing-hero-20261003.png';
$isAuthenticated = Auth::check();
$registrationEnabled = isRegistrationEnabled();

if ($isAuthenticated) {
    $primaryActionUrl = APP_URL . '?page=dashboard';
    $primaryActionText = 'Open your workspace';
} elseif ($registrationEnabled) {
    $primaryActionUrl = APP_URL . '?page=register';
    $primaryActionText = 'Create your workspace';
} else {
    $primaryActionUrl = APP_URL . '?page=login';
    $primaryActionText = 'Sign in to continue';
}

$releaseDownloadUrl = '';
$hasHostedRelease = false;
$releaseArtifactsDir = ROOT_PATH . '/release-artifacts';
if (is_dir($releaseArtifactsDir)) {
    $hostedCandidates = glob($releaseArtifactsDir . '/*-hosted-clean-*.zip');
    if (is_array($hostedCandidates) && $hostedCandidates !== []) {
        usort($hostedCandidates, static fn(string $a, string $b): int => filemtime($b) <=> filemtime($a));
        $releaseDownloadUrl = APP_URL . '/release-artifacts/' . basename($hostedCandidates[0]);
        $hasHostedRelease = true;
    }
}

$apkDownloadUrl = '';
$hasApkRelease = false;
$apkArtifactsDir = ROOT_PATH . '/release-artifacts/android';
if (is_dir($apkArtifactsDir)) {
    $apkCandidates = glob($apkArtifactsDir . '/*.apk');
    if (is_array($apkCandidates) && $apkCandidates !== []) {
        usort($apkCandidates, static fn(string $a, string $b): int => filemtime($b) <=> filemtime($a));
        $apkDownloadUrl = APP_URL . '/release-artifacts/android/' . basename($apkCandidates[0]);
        $hasApkRelease = true;
    }
}

$structuredData = [
    '@context' => 'https://schema.org',
    '@graph' => [
        [
            '@type' => 'Organization',
            'name' => $siteName,
            'url' => $canonicalUrl,
            'logo' => APP_URL . '/assets/favicons/apple-touch-icon.png',
            'sameAs' => [$repoUrl],
        ],
        [
            '@type' => 'SoftwareApplication',
            'name' => $siteName,
            'applicationCategory' => 'BusinessApplication',
            'operatingSystem' => 'Web, Android',
            'featureList' => [
                'Projects and tasks',
                'Client records and invoicing',
                'Finance and inventory',
                'Notes and calendar',
                'Everyday planning tools',
                'Optional AI assistance',
                'Information encrypted while stored',
            ],
            'url' => $canonicalUrl,
        ],
    ],
];
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo e($pageTitle); ?></title>
    <meta name="description" content="<?php echo e($pageDescription); ?>">
    <meta name="author" content="<?php echo e($siteName); ?>">
    <meta name="robots" content="index, follow">
    <link rel="canonical" href="<?php echo e($canonicalUrl); ?>">

    <meta property="og:type" content="website">
    <meta property="og:url" content="<?php echo e($canonicalUrl); ?>">
    <meta property="og:title" content="<?php echo e($pageTitle); ?>">
    <meta property="og:description" content="<?php echo e($pageDescription); ?>">
    <meta property="og:site_name" content="<?php echo e($siteName); ?>">
    <meta property="og:locale" content="en_US">
    <meta property="og:image" content="<?php echo e($heroImageUrl); ?>">
    <meta property="og:image:alt" content="A quiet, sunlit desk where someone plans the work ahead">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?php echo e($pageTitle); ?>">
    <meta name="twitter:description" content="<?php echo e($pageDescription); ?>">
    <meta name="twitter:image" content="<?php echo e($heroImageUrl); ?>">

    <link rel="icon" type="image/png" sizes="32x32" href="<?php echo e(APP_URL); ?>/assets/favicons/favicon-32x32.png">
    <link rel="shortcut icon" href="<?php echo e(APP_URL); ?>/assets/favicons/favicon.ico">
    <link rel="apple-touch-icon" sizes="180x180" href="<?php echo e(APP_URL); ?>/assets/favicons/apple-touch-icon.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script type="application/ld+json"><?php
        echo json_encode($structuredData, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    ?></script>
    <script>
        try {
            if (localStorage.getItem('theme') === 'dark') document.documentElement.classList.add('dark');
        } catch (error) {}
    </script>
    <link rel="stylesheet" href="<?php echo e(APP_URL); ?>/assets/css/public-homepage.css">
</head>
<body class="marketing-page">
    <a class="skip-link" href="#main">Skip to content</a>

    <nav class="site-nav" aria-label="Main navigation">
        <div class="site-nav__inner">
            <a class="brand" href="<?php echo e($canonicalUrl); ?>" aria-label="<?php echo e($siteName); ?> home">
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
                <a href="#workspace">Workspace</a>
                <a href="#workflow">How it works</a>
                <a href="#mobile">Mobile</a>
                <a href="<?php echo e($publicDocsUrl); ?>">Documentation</a>
            </div>

            <div class="site-nav__actions">
                <a class="nav-login" href="<?php echo e(APP_URL . '?page=' . ($isAuthenticated ? 'dashboard' : 'login')); ?>">
                    <?php echo $isAuthenticated ? 'Dashboard' : 'Log in'; ?>
                </a>
                <button class="theme-toggle" id="theme-toggle" type="button" aria-label="Switch color theme">
                    <svg class="icon theme-toggle__sun" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="4" stroke="currentColor" stroke-width="1.6"/><path d="M12 2v2m0 16v2M4.93 4.93l1.42 1.42m11.3 11.3 1.42 1.42M2 12h2m16 0h2M4.93 19.07l1.42-1.42m11.3-11.3 1.42-1.42" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
                    <svg class="icon theme-toggle__moon" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M20.2 15.2A8.6 8.6 0 0 1 8.8 3.8a8.6 8.6 0 1 0 11.4 11.4Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/></svg>
                </button>
                <a class="button button--small button--dark nav-cta" href="<?php echo e($primaryActionUrl); ?>"><?php echo e($primaryActionText); ?></a>
            </div>
        </div>
    </nav>

    <main id="main">
        <section class="hero section-wrap" id="top" aria-labelledby="hero-title">
            <div class="hero__copy">
                <p class="eyebrow"><span class="eyebrow__dot"></span> A little more room to do good work</p>
                <h1 id="hero-title">Make room for work <span>that matters.</span></h1>
                <p class="hero__lede">Projects, clients, invoices, and the small details that keep a business moving—together in one thoughtful workspace.</p>
                <div class="hero__actions">
                    <a class="button button--dark" href="<?php echo e($primaryActionUrl); ?>">
                        <?php echo e($primaryActionText); ?>
                        <svg class="icon" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M5 12h14m-6-6 6 6-6 6" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </a>
                    <a class="text-link" href="#workspace">Take a look around <span aria-hidden="true">↓</span></a>
                </div>
                <div class="hero__categories" aria-label="A few things you can organize">
                    <span>Projects</span><span>Clients</span><span>Invoices</span><span>Everyday plans</span>
                </div>
            </div>

            <figure class="hero__visual">
                <img src="<?php echo e($heroImageUrl); ?>" alt="A sunlit home workspace with a notebook, laptop, and leafy plants" fetchpriority="high" decoding="async">
                <figcaption class="hero-note"><span class="hero-note__spark" aria-hidden="true">✳</span><span><strong>Make space for the next good idea.</strong><small>Your work, thoughtfully in view.</small></span></figcaption>
                <span class="hero__image-label" aria-hidden="true">A workspace for your kind of work</span>
            </figure>
        </section>

        <section class="section section--workspace" id="workspace" aria-labelledby="workspace-title">
            <div class="section-wrap workspace-layout">
                <div class="workspace-photo">
                    <img src="<?php echo e(APP_URL); ?>/assets/images/openplan-landing-planning-20261003.png" alt="Hands arranging a notebook and planner on a warm wooden desk" loading="lazy" decoding="async">
                    <span class="photo-caption">A calmer place for the moving pieces.</span>
                </div>
                <div class="workspace-copy">
                    <p class="eyebrow"><span class="eyebrow__number">01</span> The whole picture</p>
                    <h2 id="workspace-title">Your work has a lot of moving parts. Give them one home.</h2>
                    <p class="section-lede">Keep the plan, the people, and the follow-through close enough to work together. OpenPlan gives the everyday side of running a business a little more breathing room.</p>
                    <div class="workspace-list">
                        <article><span class="list-mark">01</span><div><h3>Plan the work</h3><p>Projects, tasks, meetings, and dates in one clear view.</p></div></article>
                        <article><span class="list-mark">02</span><div><h3>Look after your clients</h3><p>Keep client details, quotes, and invoices connected.</p></div></article>
                        <article><span class="list-mark">03</span><div><h3>Keep your context</h3><p>Notes, files, and useful knowledge stay close at hand.</p></div></article>
                        <article><span class="list-mark">04</span><div><h3>Mind the everyday</h3><p>Make space for routines, focus time, and the little lists.</p></div></article>
                    </div>
                </div>
            </div>
        </section>

        <section class="section section--workflow" id="workflow" aria-labelledby="workflow-title">
            <div class="section-wrap">
                <div class="workflow-heading">
                    <p class="eyebrow eyebrow--light"><span class="eyebrow__number">02</span> From first hello to finished work</p>
                    <h2 id="workflow-title">Let the business side follow the work.</h2>
                    <p>Keep each next step close to the conversation that started it.</p>
                </div>
                <div class="workflow-path" aria-label="Example client workflow">
                    <article class="workflow-card"><span class="workflow-card__index">01</span><span class="workflow-card__icon" aria-hidden="true">◌</span><h3>Meet a client</h3><p>Keep the relationship and its details in one place.</p></article>
                    <span class="workflow-arrow" aria-hidden="true">↗</span>
                    <article class="workflow-card"><span class="workflow-card__index">02</span><span class="workflow-card__icon" aria-hidden="true">⌁</span><h3>Shape the project</h3><p>Give the goals, plan, and people a shared home.</p></article>
                    <span class="workflow-arrow" aria-hidden="true">↗</span>
                    <article class="workflow-card"><span class="workflow-card__index">03</span><span class="workflow-card__icon" aria-hidden="true">✓</span><h3>Move the work forward</h3><p>Keep tasks and the next useful action in view.</p></article>
                    <span class="workflow-arrow" aria-hidden="true">↗</span>
                    <article class="workflow-card"><span class="workflow-card__index">04</span><span class="workflow-card__icon" aria-hidden="true">↗</span><h3>Close the loop</h3><p>Keep quotes, invoices, and records with the work.</p></article>
                </div>
                <p class="workflow-footnote">A clearer path through the work, from the first conversation onward.</p>
            </div>
        </section>

        <section class="section section--day" id="day" aria-labelledby="day-title">
            <div class="section-wrap day-layout">
                <div class="day-copy">
                    <p class="eyebrow"><span class="eyebrow__number">03</span> The shape of a day</p>
                    <h2 id="day-title">Make room for the workday around the work.</h2>
                    <p class="section-lede">Not every useful thing is a project. Keep quick lists, quiet focus time, and the routines you want to return to in the same place you plan your work.</p>
                    <a class="text-link" href="<?php echo e($publicDocsUrl); ?>">See what’s inside <span aria-hidden="true">↗</span></a>
                </div>
                <div class="day-visual">
                    <div class="day-image day-image--large"><img src="<?php echo e(APP_URL); ?>/assets/images/openplan-landing-planning-20261003.png" alt="A notebook and a cup of coffee ready for a new workday" loading="lazy" decoding="async"></div>
                    <div class="day-note"><span class="day-note__line"></span><span>Progress, with a little more perspective.</span></div>
                    <div class="routine-card">
                        <span class="routine-card__label">YOUR DAY, YOUR WAY</span>
                        <div class="routine-row"><span class="routine-row__icon">○</span><span>Choose what comes next</span><span class="routine-row__arrow">↗</span></div>
                        <div class="routine-row"><span class="routine-row__icon">◷</span><span>Protect a little focus time</span><span class="routine-row__arrow">↗</span></div>
                        <div class="routine-row"><span class="routine-row__icon">✳</span><span>Make a routine your own</span><span class="routine-row__arrow">↗</span></div>
                    </div>
                </div>
            </div>
        </section>

        <section class="section section--assistant" id="assistant" aria-labelledby="assistant-title">
            <div class="section-wrap assistant-layout">
                <div class="assistant-mark" aria-hidden="true"><span>✳</span><span class="assistant-mark__orbit assistant-mark__orbit--one"></span><span class="assistant-mark__orbit assistant-mark__orbit--two"></span></div>
                <div class="assistant-copy">
                    <p class="eyebrow"><span class="eyebrow__number">04</span> A little help, when you want it</p>
                    <h2 id="assistant-title">Turn a thought into a next step.</h2>
                    <p>OpenPlan includes an optional assistant that can help with supported everyday actions—like capturing a task, updating a project, or saving a note from a simple request.</p>
                    <a class="text-link" href="<?php echo e($publicDocsUrl); ?>">Learn about the assistant <span aria-hidden="true">↗</span></a>
                </div>
                <div class="assistant-quote" aria-label="Example assistant request">
                    <span class="assistant-quote__label">A QUICK THOUGHT</span>
                    <p>“Add a task to send the project outline to my client tomorrow.”</p>
                    <span class="assistant-quote__rule"></span>
                    <span class="assistant-quote__answer"><span class="assistant-quote__check">✓</span> A clear next step, captured.</span>
                    <small>Illustrative example</small>
                </div>
            </div>
        </section>

        <section class="section section--ownership" id="ownership" aria-labelledby="ownership-title">
            <div class="section-wrap ownership-layout">
                <div class="ownership-copy">
                    <p class="eyebrow"><span class="eyebrow__number">05</span> Yours to shape</p>
                    <h2 id="ownership-title">Your business is yours. Your workspace should feel that way.</h2>
                    <p class="section-lede">Choose where your workspace runs and keep the day-to-day details under your control. Information is encrypted while it’s stored, so the things you keep here stay private.</p>
                    <a class="text-link" href="<?php echo e($publicDocsUrl); ?>">Read about keeping your information safe <span aria-hidden="true">↗</span></a>
                </div>
                <div class="ownership-image">
                    <img src="<?php echo e(APP_URL); ?>/assets/images/openplan-landing-hero-20261003.png" alt="A personal workspace set up beside a sunny window" loading="lazy" decoding="async">
                    <div class="ownership-stamp"><span class="ownership-stamp__icon" aria-hidden="true">✳</span><span><strong>Work, on your terms.</strong><small>A space that feels like yours.</small></span></div>
                </div>
            </div>
        </section>

        <section class="section section--mobile" id="mobile" aria-labelledby="mobile-title">
            <div class="section-wrap mobile-layout">
                <div class="mobile-visual">
                    <img src="<?php echo e(APP_URL); ?>/assets/images/openplan-landing-mobile-20261003.png" alt="A person checking their phone at a sunlit cafe" loading="lazy" decoding="async">
                    <span class="mobile-photo-note">A little clarity, wherever the day goes.</span>
                </div>
                <div class="mobile-copy">
                    <p class="eyebrow"><span class="eyebrow__number">06</span> Work goes where you go</p>
                    <h2 id="mobile-title">A clear view, even away from your desk.</h2>
                    <p class="section-lede">Pick up your plans, check a task, or find a client detail from your phone. The mobile experience keeps the essentials close when the day takes you out of the office.</p>
                    <div class="mobile-actions">
                        <?php if ($hasApkRelease): ?>
                            <a class="button button--dark" href="<?php echo e($apkDownloadUrl); ?>">
                                Download for Android
                                <svg class="icon" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 3v12m0 0 4-4m-4 4-4-4M5 17v3h14v-3" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            </a>
                            <span class="mobile-build">Android download available</span>
                        <?php else: ?>
                            <a class="button button--dark" href="<?php echo e($publicDocsUrl); ?>">Explore mobile access</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </section>

        <section class="section section--closing" id="get-started" aria-labelledby="closing-title">
            <div class="section-wrap closing-panel">
                <div class="closing-decoration" aria-hidden="true"><span></span><span></span><span></span><span></span></div>
                <p class="eyebrow eyebrow--light"><span class="eyebrow__number">07</span> A fresh start for the work ahead</p>
                <h2 id="closing-title">Bring a little more order to your work.</h2>
                <p>Give the projects, people, and plans you care about a place to come together.</p>
                <div class="closing-actions">
                    <a class="button button--light" href="<?php echo e($primaryActionUrl); ?>">
                        <?php echo e($primaryActionText); ?>
                        <svg class="icon" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M5 12h14m-6-6 6 6-6 6" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </a>
                    <a class="text-link text-link--light" href="<?php echo e($publicDocsUrl); ?>">Explore the documentation <span aria-hidden="true">↗</span></a>
                </div>
            </div>
        </section>
    </main>

    <footer class="site-footer">
        <div class="site-footer__inner">
            <div class="footer-top">
                <a class="brand brand--footer" href="<?php echo e($canonicalUrl); ?>" aria-label="<?php echo e($siteName); ?> home">
                    <span class="brand__mark" aria-hidden="true"><svg viewBox="0 0 48 48" fill="none"><path d="M24 4H6v26.6667h18V44h18V17.3333H24V4Z" fill="currentColor"/></svg></span>
                    <span class="brand__name"><?php echo e($siteName); ?></span>
                </a>
                <p>Make room for work that matters.</p>
                <div class="site-footer__links">
                    <a href="<?php echo e($publicDocsUrl); ?>">Documentation</a>
                    <?php if ($hasApkRelease): ?><a href="<?php echo e($apkDownloadUrl); ?>">Android download</a><?php endif; ?>
                    <?php if ($hasHostedRelease): ?><a href="<?php echo e($releaseDownloadUrl); ?>">Self-hosting package</a><?php endif; ?>
                    <a href="<?php echo e($repoUrl); ?>" target="_blank" rel="noreferrer">GitHub <span aria-hidden="true">↗</span></a>
                </div>
            </div>
            <div class="footer-bottom">
                <span>© <?php echo date('Y'); ?> <?php echo e($siteName); ?></span>
                <a href="#top">Back to top ↑</a>
            </div>
        </div>
    </footer>

    <script>
        const themeToggle = document.getElementById('theme-toggle');
        const navToggle = document.getElementById('nav-toggle');
        const siteLinks = document.getElementById('site-links');
        const root = document.documentElement;

        themeToggle.addEventListener('click', () => {
            const isDark = root.classList.toggle('dark');
            try {
                localStorage.setItem('theme', isDark ? 'dark' : 'light');
            } catch (error) {}
        });

        navToggle.addEventListener('click', () => {
            const isOpen = navToggle.getAttribute('aria-expanded') === 'true';
            navToggle.setAttribute('aria-expanded', String(!isOpen));
            navToggle.setAttribute('aria-label', isOpen ? 'Open navigation' : 'Close navigation');
            siteLinks.classList.toggle('is-open', !isOpen);
        });

        siteLinks.addEventListener('click', (event) => {
            if (event.target.closest('a')) {
                navToggle.setAttribute('aria-expanded', 'false');
                navToggle.setAttribute('aria-label', 'Open navigation');
                siteLinks.classList.remove('is-open');
            }
        });
    </script>
</body>
</html>
