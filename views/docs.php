<?php
require_once __DIR__ . '/partials/public-site-context.php';
$pageTitle = $siteName . ' | Documentation';
$pageDescription = 'Clear OpenPlan Work guides for getting started, managing projects, using mobile, and understanding storage, setup, and releases.';
$canonicalUrl = $publicDocsUrl;
$ogImageAlt = 'OpenPlan Work dashboard with sample workspace data';
$publicHeaderCurrentPage = 'docs';
$structuredData = [
    '@context' => 'https://schema.org',
    '@type' => 'WebPage',
    'name' => $pageTitle,
    'description' => $pageDescription,
    'url' => $canonicalUrl,
    'isPartOf' => [
        '@type' => 'WebSite',
        'name' => $siteName,
        'url' => $canonicalHomeUrl,
    ],
];
$docsNav = [
    ['id' => 'start', 'label' => 'Getting started'],
    ['id' => 'work', 'label' => 'Dashboard and work'],
    ['id' => 'clients', 'label' => 'Clients and invoices'],
    ['id' => 'everyday', 'label' => 'Everyday tools'],
    ['id' => 'mobile-access', 'label' => 'Mobile access'],
    ['id' => 'security', 'label' => 'Data and security'],
    ['id' => 'configuration', 'label' => 'Configuration'],
    ['id' => 'local-run', 'label' => 'Run locally'],
    ['id' => 'hosted-run', 'label' => 'Hosted setup'],
    ['id' => 'exports', 'label' => 'Exports and releases'],
    ['id' => 'branding', 'label' => 'Branding'],
    ['id' => 'license', 'label' => 'License'],
];
require __DIR__ . '/partials/public-site-head.php';
?>
<body class="marketing-page marketing-subpage">
<?php require __DIR__ . '/partials/public-site-header.php'; ?>
<main id="main">
    <section class="public-docs-hero" id="top" aria-labelledby="docs-title">
        <div class="section-wrap public-docs-hero-layout">
            <div>
                <p class="eyebrow"><span class="eyebrow__number">01</span> Documentation</p>
                <h1 id="docs-title">A clear next step, whenever you need one.</h1>
                <p class="hero__lede">Short guides for setting up your workspace, finding the right tool, and keeping the work moving.</p>
                <nav class="public-docs-links" aria-label="Popular documentation topics">
                    <a href="#start">Getting started <span aria-hidden="true">↓</span></a>
                    <a href="#work">Managing work <span aria-hidden="true">↓</span></a>
                    <a href="#everyday">Everyday tools <span aria-hidden="true">↓</span></a>
                </nav>
            </div>
            <figure class="public-screen public-screen--hero">
                <img src="<?php echo e($publicBaseUrl); ?>/assets/images/app-dashboard.webp" width="1600" height="1000" alt="OpenPlan dashboard showing example projects, tasks, habits, and revenue totals. Sample workspace data." fetchpriority="high" decoding="async" data-dashboard-shot>
                <figcaption>Dashboard screen · actual product UI with sample workspace data</figcaption>
            </figure>
        </div>
    </section>

    <section class="section public-docs-body" aria-label="OpenPlan Work documentation">
        <div class="section-wrap public-docs-layout">
            <aside class="public-docs-sidebar" aria-label="Documentation contents">
                <p>IN THIS GUIDE</p>
                <?php foreach ($docsNav as $item): ?>
                    <a href="#<?php echo e($item['id']); ?>"><?php echo e($item['label']); ?></a>
                <?php endforeach; ?>
            </aside>

            <article class="public-docs-content">
                <section class="public-docs-section" id="start" aria-labelledby="start-title">
                    <p class="eyebrow">OpenPlan Work guide</p>
                    <h2 id="start-title">Getting started</h2>
                    <p class="section-lede">Begin with the basics: set up your workspace, get familiar with the dashboard, and choose the first part of your work to bring in.</p>
                    <p><?php echo e($siteName); ?> is a self-hosted PHP productivity suite for tasks, projects, notes, habits, invoices, inventory, and AI-assisted workflows. It is designed for local-first storage and portable deployment on a local machine or hosted server.</p>
                    <div class="public-docs-callout"><strong>Start with one useful thing.</strong><span>Add a project, capture a task, or create a client record. Build out the rest of your workspace as you go.</span></div>
                    <div class="public-docs-two-up">
                        <div class="public-docs-topic"><h3>Core modules</h3><p>Projects and tasks, clients, notes and Knowledge Base, habits and Pomodoro, invoices and quotes, and inventory.</p></div>
                        <div class="public-docs-topic"><h3>Choose a starting point</h3><p>See the <a class="text-link" href="<?php echo e($publicHowItWorksUrl); ?>">work flow guide</a>, review <a class="text-link" href="<?php echo e($publicPricingUrl); ?>">pricing</a>, or open the <a class="text-link" href="<?php echo e($publicMobileUrl); ?>">mobile guide</a>.</p></div>
                    </div>
                </section>

                <section class="public-docs-section" id="work" aria-labelledby="work-title">
                    <p class="eyebrow"><span class="eyebrow__number">02</span> Find your way</p>
                    <h2 id="work-title">Dashboard and projects</h2>
                    <p>The dashboard gives you a starting view across the workspace. Projects keep related tasks and progress together, so you can move from the broad plan to the next action without losing context.</p>
                    <ul>
                        <li>Use projects to group work and review its progress.</li>
                        <li>Use tasks to capture the next action and follow its status.</li>
                        <li>Keep notes and knowledge close to the work they support.</li>
                    </ul>
                    <figure class="public-screen public-screen--wide public-docs-shot">
                        <img src="<?php echo e($publicBaseUrl); ?>/assets/images/app-projects.webp" width="1400" height="1064" alt="OpenPlan Projects view showing example project records and task progress. Sample workspace data." loading="lazy" decoding="async">
                        <figcaption>Projects · actual product UI with sample workspace data</figcaption>
                    </figure>
                </section>

                <section class="public-docs-section" id="clients" aria-labelledby="clients-title">
                    <p class="eyebrow"><span class="eyebrow__number">03</span> Client work</p>
                    <h2 id="clients-title">Clients, quotes, and invoices</h2>
                    <p>Keep client details with the work and billing records connected to them. The suite includes client, quote, and invoice modules, along with inventory tools for the operational details behind a business.</p>
                    <figure class="public-screen public-screen--wide public-docs-shot">
                        <img src="<?php echo e($publicBaseUrl); ?>/assets/images/app-invoices.webp" width="1400" height="1064" alt="OpenPlan invoice list with example clients, invoice statuses, and sample amounts. Sample workspace data." loading="lazy" decoding="async">
                        <figcaption>Invoices · actual product UI with sample workspace data</figcaption>
                    </figure>
                    <p><a class="text-link" href="<?php echo e($publicHowItWorksUrl); ?>#billing">Follow the client-to-invoice workflow <span aria-hidden="true">↗</span></a></p>
                </section>

                <section class="public-docs-section" id="everyday" aria-labelledby="everyday-title">
                    <p class="eyebrow"><span class="eyebrow__number">04</span> Everyday tools</p>
                    <h2 id="everyday-title">Notes, habits, and focus</h2>
                    <p>Use the smaller tools around a project plan: capture notes, keep a Knowledge Base, track habits, and use a Pomodoro timer for focused sessions. These modules give day-to-day work a place alongside longer projects.</p>
                    <figure class="public-screen public-screen--wide public-docs-shot">
                        <img src="<?php echo e($publicBaseUrl); ?>/assets/images/app-habits.webp" width="1400" height="1064" alt="OpenPlan Habits view showing example routines and weekly progress. Sample workspace data." loading="lazy" decoding="async">
                        <figcaption>Habits · actual product UI with sample workspace data</figcaption>
                    </figure>
                </section>

                <section class="public-docs-section" id="mobile-access" aria-labelledby="mobile-access-title">
                    <p class="eyebrow"><span class="eyebrow__number">05</span> On your phone</p>
                    <h2 id="mobile-access-title">Mobile access</h2>
                    <p>The mobile view is designed around phone-sized screens and provides access to the workspace modules available in the mobile interface. Visit the dedicated mobile page for the current Android download status.</p>
                    <figure class="public-screen public-screen--wide public-docs-shot">
                        <img src="<?php echo e($publicBaseUrl); ?>/assets/images/app-mobile-dashboard.webp" width="600" height="1298" alt="OpenPlan mobile dashboard with example tasks, projects, and habits. Sample workspace data." loading="lazy" decoding="async">
                        <figcaption>Mobile dashboard · actual product UI with sample workspace data</figcaption>
                    </figure>
                    <p><a class="text-link" href="<?php echo e($publicMobileUrl); ?>">View the mobile and Android page <span aria-hidden="true">↗</span></a></p>
                </section>

                <section class="public-docs-section" id="security" aria-labelledby="security-title">
                    <p class="eyebrow"><span class="eyebrow__number">06</span> Data and security</p>
                    <h2 id="security-title">Your data stays in your workspace</h2>
                    <p>Workspace records are stored as per-user encrypted JSON collections. AES-256-GCM protects data at rest. The master password derives the encryption key and must be kept by the user for future access; the application does not store it in plaintext.</p>
                    <ul>
                        <li>Collections are isolated by user.</li>
                        <li>Authentication uses an email and password, followed by the master password for encrypted data access.</li>
                        <li>Email verification can be enabled, and session timeouts are enforced.</li>
                        <li>Audit logging records security-relevant events.</li>
                    </ul>
                    <div class="public-docs-callout"><strong>Keep the master password safe.</strong><span>It is needed to access encrypted workspace data. Do not share it or store it in logs.</span></div>
                </section>

                <section class="public-docs-section" id="configuration" aria-labelledby="configuration-title">
                    <p class="eyebrow"><span class="eyebrow__number">07</span> Configuration</p>
                    <h2 id="configuration-title">Set up the environment</h2>
                    <p>Email, SMTP, application branding, and hosted-only feature settings can be configured through environment variables and application settings. Use the example configuration as a baseline, then provide real secrets through the server environment.</p>
                    <ul>
                        <li><code>.env.example</code> documents baseline values.</li>
                        <li>Mail delivery uses the configured MAIL and SMTP values.</li>
                        <li>Hosted feature flags control optional authentication and image-service behavior.</li>
                    </ul>
                </section>

                <section class="public-docs-section" id="local-run" aria-labelledby="local-run-title">
                    <p class="eyebrow"><span class="eyebrow__number">08</span> Local development</p>
                    <h2 id="local-run-title">Run locally</h2>
                    <p>Get the code from the <a class="text-link" href="<?php echo e($repoUrl); ?>" target="_blank" rel="noreferrer">open-source repository on GitHub</a>, either as a <a class="text-link" href="<?php echo e($sourceDownloadUrl); ?>">ZIP download</a> or with git:</p>
                    <div class="public-docs-code" aria-label="Clone command">
                        <code>git clone <?php echo e($repoUrl); ?>.git</code>
                    </div>
                    <p>Local development requires PHP 8.0 or newer and the <code>json</code>, <code>mbstring</code>, and <code>openssl</code> extensions.</p>
                    <div class="public-docs-code" aria-label="Local server commands">
                        <p>Start the local server with either command:</p>
                        <code>php start_server.php</code>
                        <code>start_server.bat</code>
                    </div>
                </section>

                <section class="public-docs-section" id="hosted-run" aria-labelledby="hosted-run-title">
                    <p class="eyebrow"><span class="eyebrow__number">09</span> Hosted installation</p>
                    <h2 id="hosted-run-title">Configure a hosted install</h2>
                    <p>Hosted installs rely on server environment configuration for authentication and mail features. Use <code>.env.example</code> as a starting point, then set hosted-only flags and secrets in the server environment.</p>
                    <p>Keep deployment credentials and private configuration out of public release files and source control.</p>
                </section>

                <section class="public-docs-section" id="exports" aria-labelledby="exports-title">
                    <p class="eyebrow"><span class="eyebrow__number">10</span> Exports and releases</p>
                    <h2 id="exports-title">Prepare a clean release</h2>
                    <p>Release exports are intended to create clean artifacts without live user data, sessions, master passwords, or AI keys. The generated ZIP includes a clean data structure and a release manifest for distribution or handoff.</p>
                    <ul>
                        <li>Clean data skeleton</li>
                        <li>No user data or active sessions</li>
                        <li>Release manifest included</li>
                    </ul>
                </section>

                <section class="public-docs-section" id="branding" aria-labelledby="branding-title">
                    <p class="eyebrow"><span class="eyebrow__number">11</span> Branding</p>
                    <h2 id="branding-title">A consistent visual system</h2>
                    <p>The public product pages use a high-contrast black-and-white design with light and dark themes. Logos and icons are intentionally minimal; product screenshots retain their original color.</p>
                </section>

                <section class="public-docs-section" id="license" aria-labelledby="license-title">
                    <p class="eyebrow"><span class="eyebrow__number">12</span> License</p>
                    <h2 id="license-title">Free software under the AGPL-3.0</h2>
                    <p>OpenPlan is licensed under the GNU Affero General Public License v3.0. You may use, self-host, modify and share it, including for commercial work. If you run a changed version for other people over a network, you must offer them the source of your changes under the same license. The software comes with no warranty.</p>
                    <p><a href="<?php echo e($repoUrl); ?>/blob/main/LICENSE" target="_blank" rel="noreferrer">Read the full license on GitHub <span aria-hidden="true">↗</span></a></p>
                </section>
            </article>
        </div>
    </section>

    <section class="section public-docs-topics" aria-labelledby="docs-next-title">
        <div class="section-wrap public-docs-last">
            <div>
                <p class="eyebrow"><span class="eyebrow__number">13</span> Keep exploring</p>
                <h2 id="docs-next-title">Find the guide for your next step.</h2>
                <p class="section-lede">See how everyday work connects, explore the mobile experience, or review the current plans.</p>
            </div>
            <div class="public-doc-links">
                <a href="<?php echo e($publicHowItWorksUrl); ?>">How it works <span>↗</span></a>
                <a href="<?php echo e($publicMobileUrl); ?>">Mobile and Android <span>↗</span></a>
                <a href="<?php echo e($publicPricingUrl); ?>">Pricing <span>↗</span></a>
            </div>
        </div>
    </section>
</main>
<?php require __DIR__ . '/partials/public-site-footer.php'; ?>
<?php require __DIR__ . '/partials/public-site-scripts.php'; ?>
</body>
</html>
