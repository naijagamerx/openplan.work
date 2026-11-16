<?php
require_once __DIR__ . '/partials/public-site-context.php';
$pageTitle = $siteName . ' | Mobile and Android';
$pageDescription = 'Use OpenPlan on your phone: the mobile web app in any browser, or the Android app. Same account and encrypted data, laid out for a small screen, with a wide layout for foldables and tablets.';
$canonicalUrl = $publicMobileUrl;
$ogImage = $publicBaseUrl . '/assets/images/app-mobile-dashboard.webp';
$ogImageAlt = 'OpenPlan mobile dashboard with sample workspace data';
$publicHeaderCurrentPage = 'mobile';
$mobileWebUrl = $publicBaseUrl . '/mobile/';
// The Android app is a thin shell around this site's hosted mobile app.
$apkVersion = $hasApkRelease && preg_match('/(\d+(?:\.\d+)+)\.apk$/', $apkDownloadUrl, $apkMatch) ? $apkMatch[1] : '';
$apkSizeMb = $hasApkRelease ? (int) round(((int) @filesize(ROOT_PATH . '/release-artifacts/android/' . rawurldecode(basename((string) parse_url($apkDownloadUrl, PHP_URL_PATH))))) / 1048576) : 0;
require __DIR__ . '/partials/public-site-head.php';
?>
<body class="marketing-page marketing-subpage">
<?php require __DIR__ . '/partials/public-site-header.php'; ?>
<main id="main">
    <section class="public-mobile-hero section-wrap" id="top" aria-labelledby="mobile-title">
        <div class="public-mobile-copy">
            <h1 id="mobile-title">Your workspace, in your pocket.</h1>
            <p class="hero__lede">Use the mobile web app in any phone browser, or install the Android app. It is the same account and the same encrypted workspace as on your computer, laid out for a small screen.</p>
            <div class="op-cta-row">
                <?php if ($hasApkRelease): ?>
                    <a class="button button--dark" href="<?php echo e($apkDownloadUrl); ?>" download>Download for Android <?php echo $publicIcon('arrow-down'); ?></a>
                <?php endif; ?>
                <a class="<?php echo $hasApkRelease ? 'text-link' : 'button button--dark'; ?>" href="<?php echo e($mobileWebUrl); ?>">Open the mobile web app <?php echo $publicIcon('arrow-up-right'); ?></a>
            </div>
            <p class="public-mobile-caption"><?php echo $hasApkRelease ? 'Android app' . ($apkVersion !== '' ? ' ' . e($apkVersion) : '') . ', a direct download (APK). ' : ''; ?>The mobile web app works on iPhone and Android with no install.</p>
        </div>
        <figure class="public-phone-frame">
            <div class="public-phone-frame__bezel">
                <img src="<?php echo e($publicBaseUrl); ?>/assets/images/app-mobile-dashboard.webp" width="600" height="1298" alt="OpenPlan mobile dashboard showing example tasks, projects, habits, and water tracking. Sample workspace data." fetchpriority="high" decoding="async">
            </div>
            <figcaption>Mobile dashboard &middot; sample workspace data</figcaption>
        </figure>
    </section>

    <section class="op-section op-section--soft" id="ways" aria-labelledby="ways-title">
        <div class="section-wrap">
            <div class="op-section__head">
                <h2 id="ways-title">Two ways to use it on a phone</h2>
                <p>Both open the same workspace. Changes you make on the phone are there on your computer straight away, and the other way round.</p>
            </div>
            <dl class="op-facts">
                <div>
                    <dt>Mobile web app</dt>
                    <dd>Go to <a class="text-link" href="<?php echo e($mobileWebUrl); ?>"><?php echo e(preg_replace('#^https?://#', '', $mobileWebUrl)); ?></a> in Safari, Chrome or any phone browser and sign in. To open it like an app, use your browser&rsquo;s <strong>Add to Home Screen</strong>. Works on iPhone, Android and tablets, and on any self-hosted copy at <code>your-address/mobile/</code>.</dd>
                </div>
                <?php if ($hasApkRelease): ?>
                    <div>
                        <dt>Android app</dt>
                        <dd>A small app that opens the mobile workspace full screen, with its own icon, no browser bar and the Android back button working inside the app. It connects to this site (<?php echo e(preg_replace('#^https?://#', '', $publicBaseUrl)); ?>), so if you host <?php echo e($siteName); ?> yourself, use the mobile web app on your own address instead.</dd>
                    </div>
                <?php endif; ?>
                <div>
                    <dt>Foldables and tablets</dt>
                    <dd>On a wide screen, such as an unfolded phone or a tablet, the mobile app switches to a wider layout with a side menu, and Notes shows the list and the open note side by side.</dd>
                </div>
            </dl>
        </div>
    </section>

    <?php if ($hasApkRelease): ?>
        <section class="op-section op-section--paper" id="android-access" aria-labelledby="android-access-title">
            <div class="section-wrap op-columns">
                <div class="op-prose">
                    <h2 id="android-access-title" class="op-prose__title">Install the Android app</h2>
                    <p>The app is a direct download rather than a Play Store listing, so Android asks you once to allow the install.</p>
                    <a class="button button--dark" href="<?php echo e($apkDownloadUrl); ?>" download>Download for Android <?php echo $publicIcon('arrow-down'); ?></a>
                </div>
                <ol class="op-steps">
                    <li><div><h3>Download the app</h3><p>Open this page on your Android phone and tap <strong>Download for Android</strong>. <?php if ($apkSizeMb > 0): ?>The file is about <?php echo $apkSizeMb; ?>&nbsp;MB.<?php endif; ?></p></div></li>
                    <li><div><h3>Allow the install</h3><p>Open the downloaded file. If Android says the browser is not allowed to install apps, tap <strong>Settings</strong>, turn on <strong>Allow from this source</strong>, then go back and tap <strong>Install</strong>.</p></div></li>
                    <li><div><h3>Sign in</h3><p>Open <?php echo e($siteName); ?>, sign in with your email and password, then enter your master password to unlock your workspace.</p></div></li>
                    <li><div><h3>Updating later</h3><p>When a new version is listed here, download it and install it over the old one. Your data lives in your account, not on the phone, so nothing is lost.</p></div></li>
                </ol>
            </div>
        </section>
    <?php endif; ?>

    <section class="op-section op-section--soft" id="mobile-details" aria-labelledby="mobile-details-title">
        <div class="section-wrap">
            <div class="op-section__head">
                <h2 id="mobile-details-title">What you can do on your phone</h2>
                <p>The bottom bar has <strong>Menu</strong>, <strong>Tasks</strong>, <strong>Habits</strong> and <strong>Settings</strong>. The menu opens every other part of the workspace.</p>
            </div>
            <dl class="op-modules">
                <div class="op-module"><dt>Your day</dt><dd>The dashboard shows pending tasks, active projects, today&rsquo;s habits and your water progress, with links straight into each one.</dd></div>
                <div class="op-module"><dt>Tasks and projects</dt><dd>Add and edit tasks, change status and priority, open a project to see its tasks, or switch to the kanban board.</dd></div>
                <div class="op-module"><dt>Clients and billing</dt><dd>Look up a client, create an invoice and download it as a PDF, and record income and expenses in Finance. Inventory and stock history are there too.</dd></div>
                <div class="op-module"><dt>Notes and files</dt><dd>Write notes with checklists and tags, pin the important ones, and open your knowledge base files.</dd></div>
                <div class="op-module"><dt>Focus and routines</dt><dd>Run a Pomodoro session with music; a small player stays on screen while you move around the app. Track habits, see their history, and follow your water plan.</dd></div>
                <div class="op-module"><dt>AI assistant</dt><dd>Chat with the assistant or, with an OpenRouter or Gemini model, let the agent create tasks, notes and records for you, the same as on desktop.</dd></div>
                <div class="op-module"><dt>Settings</dt><dd>Dark mode, AI keys and models, your plan, connecting AI apps over MCP, backups and support.</dd></div>
            </dl>
        </div>
    </section>

    <section class="op-section op-section--paper" id="mobile-views" aria-labelledby="mobile-views-title">
        <div class="section-wrap public-split public-split--mobile">
            <div class="public-copy">
                <h2 id="mobile-views-title">Start on the phone, finish at the desk.</h2>
                <p class="section-lede">Capture a task on the way to a meeting, then plan the week on the full desktop dashboard. Everything is saved to your account the moment you make it.</p>
                <a class="text-link" href="<?php echo e($publicHowItWorksUrl); ?>">See how the work connects <?php echo $publicIcon('arrow-up-right'); ?></a>
            </div>
            <figure class="public-screen public-screen--wide"><img src="<?php echo e($publicBaseUrl); ?>/assets/images/app-dashboard.webp" width="1600" height="1000" alt="OpenPlan desktop dashboard with sample tasks and project summaries." loading="lazy" decoding="async" data-dashboard-shot><figcaption>Desktop dashboard &middot; sample workspace data</figcaption></figure>
        </div>
    </section>

    <section class="section public-close" aria-labelledby="mobile-close-title">
        <div class="section-wrap closing-panel">
            <h2 id="mobile-close-title">Take the workspace with you.</h2>
            <p>Create your workspace on any device, then sign in on your phone.</p>
            <div class="closing-actions">
                <a class="button button--light" href="<?php echo e($primaryActionUrl); ?>"><?php echo e($primaryActionText); ?> <?php echo $publicIcon('arrow-right'); ?></a>
                <a class="text-link text-link--light" href="<?php echo e($publicDocsUrl); ?>">Read the documentation <?php echo $publicIcon('arrow-up-right'); ?></a>
            </div>
        </div>
    </section>
</main>
<?php require __DIR__ . '/partials/public-site-footer.php'; ?>
<?php require __DIR__ . '/partials/public-site-scripts.php'; ?>
</body>
</html>
