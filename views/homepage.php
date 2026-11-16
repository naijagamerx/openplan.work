<?php
require_once __DIR__ . '/partials/public-site-context.php';
$pageTitle = $siteName . ' | Run your business from one workspace';
$pageDescription = 'Projects, tasks, clients, invoices, finance, inventory, notes and habits in one encrypted workspace, with an AI agent and an MCP server for your own AI apps. Free, and open source.';
$canonicalUrl = $canonicalHomeUrl;
$heroImageUrl = $publicBaseUrl . '/assets/images/app-dashboard.webp';
$shareImageUrl = $publicBaseUrl . '/assets/images/app-dashboard-og.jpg';
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
                'Projects and tasks with kanban, timers and recurring tasks',
                'Clients, invoices and advanced invoices with PDF download',
                'Finance and inventory',
                'Notes, Notes AI and a knowledge base',
                'Calendar, meetings, habits, Pomodoro and water tracking',
                'AI assistant and agent (Groq, OpenRouter, Gemini, Ollama)',
                'MCP server with 86 tools',
                'Encrypted storage with a user master password',
                'Open source under the AGPL-3.0',
            ],
            'offers' => ['@type' => 'Offer', 'price' => '0', 'priceCurrency' => 'USD'],
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
    <meta property="og:image" content="<?php echo e($shareImageUrl); ?>">
    <meta property="og:image:alt" content="The OpenPlan dashboard with tasks, projects, habits and revenue (sample data)">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?php echo e($pageTitle); ?>">
    <meta name="twitter:description" content="<?php echo e($pageDescription); ?>">
    <meta name="twitter:image" content="<?php echo e($shareImageUrl); ?>">

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
        document.documentElement.classList.add('js');
        try {
            if (localStorage.getItem('theme') === 'dark') document.documentElement.classList.add('dark');
        } catch (error) {}
    </script>
    <link rel="stylesheet" href="<?php echo e(APP_URL); ?>/assets/css/public-homepage.css?v=<?php echo (int) @filemtime(ROOT_PATH . '/assets/css/public-homepage.css'); ?>">
    <link rel="stylesheet" href="<?php echo e(APP_URL); ?>/assets/css/public-pages.css?v=<?php echo (int) @filemtime(ROOT_PATH . '/assets/css/public-pages.css'); ?>">
    <link rel="stylesheet" href="<?php echo e(APP_URL); ?>/assets/css/public-hero.css?v=<?php echo (int) @filemtime(ROOT_PATH . '/assets/css/public-hero.css'); ?>">
</head>
<body class="marketing-page">
    <?php $publicHeaderCurrentPage = 'homepage'; require __DIR__ . '/partials/public-site-header.php'; ?>
<main id="main">
        <section class="op-hero" id="top" aria-labelledby="hero-title">
            <div class="op-hero__inner">
                <div class="op-hero__copy" data-hero-copy>
                    <?php if ($publicTrialDays > 0): ?>
                        <a class="op-pill" href="<?php echo e($primaryActionUrl); ?>">
                            <span class="op-pill__tag">Free trial</span>
                            <span>Try the AI on our keys for <?php echo (int) $publicTrialDays; ?> days, no card</span>
                            <svg class="op-pill__arrow" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M5 12h14m-6-6 6 6-6 6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        </a>
                    <?php else: ?>
                        <a class="op-pill" href="<?php echo e($publicDocsUrl); ?>#self-host">
                            <span class="op-pill__tag">Open source</span>
                            <span>Use it here, or host it yourself for free</span>
                            <svg class="op-pill__arrow" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M5 12h14m-6-6 6 6-6 6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        </a>
                    <?php endif; ?>
                    <h1 id="hero-title"><span>Run your business</span> <span>from <em>one</em> workspace.</span></h1>
                    <p class="op-hero__lede">Projects, clients, invoices, money, notes and daily habits in one place, stored encrypted with your own master password. An AI agent handles the admin, and your own AI apps can work in it too.</p>
                    <div class="op-hero__actions">
                        <a class="button button--dark" href="<?php echo e($primaryActionUrl); ?>">
                            <?php echo e($primaryActionText); ?>
                            <svg class="icon" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M5 12h14m-6-6 6 6-6 6" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        </a>
                        <a class="button button--outline" href="#workspace">See what&rsquo;s inside</a>
                    </div>
                    <?php if (!$isAuthenticated): ?>
                        <p class="op-hero__note">Already have an account? <a href="<?php echo e(APP_URL . '?page=login'); ?>">Log in</a></p>
                    <?php endif; ?>
                </div>

                <div class="op-stage" data-hero-stage role="group" aria-roledescription="carousel" aria-label="Screens from the app, with sample data">
                    <figure class="op-slide is-front" data-slot="0">
                        <img src="<?php echo e($heroImageUrl); ?>" width="1600" height="1000" alt="The dashboard: pending tasks, active projects, habits and revenue (sample data)" fetchpriority="high" decoding="async">
                        <figcaption>Dashboard &middot; sample data</figcaption>
                    </figure>
                    <figure class="op-slide" data-slot="1">
                        <img src="<?php echo e(APP_URL); ?>/assets/images/app-projects.webp" width="1400" height="1064" alt="The Projects screen with three client projects and their progress (sample data)" decoding="async">
                        <figcaption>Projects &middot; sample data</figcaption>
                    </figure>
                    <figure class="op-slide" data-slot="2">
                        <img src="<?php echo e(APP_URL); ?>/assets/images/app-invoices.webp" width="1400" height="1064" alt="The Invoices screen with paid, pending and overdue totals (sample data)" decoding="async">
                        <figcaption>Invoices &middot; sample data</figcaption>
                    </figure>
                    <p class="op-visually-hidden" aria-live="polite" data-hero-live></p>
                </div>
            </div>

            <div class="op-marquee">
                <ul class="op-marquee__track" aria-label="What the workspace includes">
                    <?php
                    $heroFeatures = ['Projects and tasks', 'Kanban boards', 'Clients', 'Invoices', 'Advanced invoices', 'Finance', 'Inventory', 'Notes', 'Notes AI', 'Knowledge base', 'Calendar and meetings', 'Habits', 'Pomodoro focus', 'Water plan', 'AI agent', 'MCP for your AI apps', 'Android app', 'Self-hosting'];
                    foreach ([false, true] as $heroCopy):
                        foreach ($heroFeatures as $heroFeature): ?>
                            <li<?php echo $heroCopy ? ' aria-hidden="true"' : ''; ?>><?php echo e($heroFeature); ?></li>
                        <?php endforeach;
                    endforeach; ?>
                </ul>
            </div>
        </section>

        <section class="op-section op-section--paper" id="workspace" aria-labelledby="workspace-title">
            <div class="section-wrap">
                <div class="op-section__head">
                    <h2 id="workspace-title">Everything a one&#8209;person business runs on.</h2>
                    <p>Every tool works in the same workspace, so a client, their projects, the tasks and the invoices stay linked. Here is what each part does and how you use it.</p>
                </div>

                <div class="op-area">
                    <div class="op-area__intro">
                        <h3>Plan and do the work</h3>
                        <p>Start with a project, break it into tasks, and see the month on the calendar.</p>
                        <figure class="op-area__shot">
                            <img src="<?php echo e(APP_URL); ?>/assets/images/app-projects.webp" width="1400" height="1064" alt="The Projects screen with three client projects and their progress (sample data)" loading="lazy" decoding="async">
                            <figcaption>Projects &middot; sample data</figcaption>
                        </figure>
                    </div>
                    <dl class="op-modules">
                        <div class="op-module"><dt>Dashboard</dt><dd>Opens on what matters today: pending tasks, active projects, habits, water and revenue, with quick actions and a focus timer.</dd></div>
                        <div class="op-module"><dt>Projects</dt><dd>Give each piece of work a project with a client, colour and status. The project page shows its tasks on a drag-and-drop board and a progress bar that fills as tasks are done.</dd></div>
                        <div class="op-module"><dt>Tasks</dt><dd>
                            <p>Add tasks with a priority, start and due dates, a time estimate, subtasks and a colour, and make them repeat daily, weekly or monthly.</p>
                            <ul>
                                <li>Work on a kanban board or a list, and filter by project, status, priority or date.</li>
                                <li>Start a timer on a task to log the time you spend.</li>
                                <li>Move or close several tasks at once, save templates, and export to CSV.</li>
                                <li><strong>AI Improve</strong> rewrites a description and <strong>AI Breakdown</strong> suggests subtasks.</li>
                            </ul>
                        </dd></div>
                        <div class="op-module"><dt>Calendar and meetings</dt><dd>A month view of every task by due date, plus meetings with time, place, attendees and the client or project they belong to.</dd></div>
                        <div class="op-module"><dt>Reminders</dt><dd>The bell lists overdue and due-soon tasks. Turn on browser notifications and sounds in Settings.</dd></div>
                    </dl>
                </div>

                <div class="op-area">
                    <div class="op-area__intro">
                        <h3>Clients and money</h3>
                        <p>The people you work for, what you bill them, and what comes in and goes out.</p>
                        <figure class="op-area__shot">
                            <img src="<?php echo e(APP_URL); ?>/assets/images/app-invoices.webp" width="1400" height="1064" alt="The Invoices screen with paid, pending and overdue totals (sample data)" loading="lazy" decoding="async">
                            <figcaption>Invoices &middot; sample data</figcaption>
                        </figure>
                    </div>
                    <dl class="op-modules">
                        <div class="op-module"><dt>Clients</dt><dd>One record per client with contact details, company and notes. Their page shows linked projects and a summary of what you have invoiced and been paid.</dd></div>
                        <div class="op-module"><dt>Invoices</dt><dd>Add line items; the tax rate and currency (USD, EUR, GBP or ZAR) come from your business settings. Mark each one draft, sent, paid or overdue, and download it as a PDF to send.</dd></div>
                        <div class="op-module"><dt>Advanced invoices</dt><dd>For formal billing: your company header and logo, customer ID, property address and contract dates, bank payment details, custom fields, notes and footer, a currency per invoice, and Classic or Modern layouts.</dd></div>
                        <div class="op-module"><dt>Finance</dt><dd>Record income and expenses and link them to a client. Paid invoices count as revenue automatically, and a six-month chart shows the trend.</dd></div>
                        <div class="op-module"><dt>Inventory</dt><dd>Products with SKU, category, cost and selling price, and a reorder point. Record stock in and out with a note and see each item&rsquo;s history; items at or below their reorder point are marked in red. <strong>AI Generate</strong> suggests items to add.</dd></div>
                    </dl>
                </div>

                <div class="op-area">
                    <div class="op-area__intro">
                        <h3>Notes and knowledge</h3>
                        <p>Keep what you know beside the work it belongs to, and let the assistant read it.</p>
                    </div>
                    <dl class="op-modules">
                        <div class="op-module"><dt>Notes</dt><dd>Write with bold, italic and lists, add a checklist inside the note, and tag, pin or favourite it. The side pane filters by tag, pinned or favourites.</dd></div>
                        <div class="op-module"><dt>Notes AI</dt><dd>Press <strong>Ctrl+I</strong> in a note to improve, expand, summarise or rewrite it, turn it into tasks, or ask questions about it.</dd></div>
                        <div class="op-module"><dt>Knowledge base</dt><dd>Folders of Markdown and XML documents. Upload, search and rename them, or save a note or an AI answer straight in. Choose a folder in the assistant and it answers from those documents.</dd></div>
                    </dl>
                </div>

                <div class="op-area">
                    <div class="op-area__intro">
                        <h3>Your day</h3>
                        <p>Small routines that keep the work, and you, going.</p>
                        <figure class="op-area__shot">
                            <img src="<?php echo e(APP_URL); ?>/assets/images/app-habits.webp" width="1400" height="1064" alt="The Habits screen with routines like morning planning and sending invoices on Friday (sample data)" loading="lazy" decoding="async">
                            <figcaption>Habits &middot; sample data</figcaption>
                        </figure>
                    </div>
                    <dl class="op-modules">
                        <div class="op-module"><dt>Habits</dt><dd>Daily or one-off habits with a category, reminder time and target length. Time a session, keep your current and longest streaks, and look back on a calendar and history. The AI can suggest habits too.</dd></div>
                        <div class="op-module"><dt>Pomodoro</dt><dd>A focus timer with presets or your own lengths (1 to 180 minutes of focus, 1 to 60 of break) that counts your sessions. Add your own focus music; a small player and timer follow you from page to page.</dd></div>
                        <div class="op-module"><dt>Water plan</dt><dd>Set a daily goal, reminder times and browser notifications, then log glasses with one tap from the dashboard.</dd></div>
                    </dl>
                </div>
            </div>
        </section>

        <section class="op-section op-section--soft" id="assistant" aria-labelledby="assistant-title">
            <div class="section-wrap op-columns">
                <div class="op-prose">
                    <h2 id="assistant-title" class="op-prose__title">An assistant that does the admin.</h2>
                    <p>Open <strong>AI Assistant</strong> and ask in plain words. In agent mode it carries out the work: it creates and updates tasks and projects, adds clients, makes invoices and advanced invoices, logs income and expenses, writes notes, adds habits and stock, starts a Pomodoro and logs water. It acts straight away, then tells you what it did.</p>
                    <h3>Which AI it uses</h3>
                    <p>Add your own key from Groq, OpenRouter or Google Gemini, or point it at your own Ollama server, or use a plan on our keys. Agent actions run on OpenRouter and Gemini models; Groq models are kept for fast chat and writing. With two or more providers set up, <strong>Smart mode</strong> sends long or complex requests to the stronger one.</p>
                    <h3>It can answer from your files</h3>
                    <p>Choose a Knowledge Base folder and the assistant reads it before it answers. Save any answer to Notes or the Knowledge Base, or export it as Markdown. The PRD tool turns an idea into a product requirements document.</p>
                </div>
                <div>
                    <div class="op-chat" aria-label="Example conversation with the assistant">
                        <p class="op-chat__you">Add Lumen Physio as a client and make a draft invoice for the booking flow: 12 hours at R650.</p>
                        <div class="op-chat__ai">Done:
                            <ul>
                                <li>Created the client Lumen Physio</li>
                                <li>Created a draft invoice with one line, 12 hours at R650.00</li>
                            </ul>
                        </div>
                        <p class="op-chat__you">Remind me to send it on Friday.</p>
                        <p class="op-chat__ai">Added the task &ldquo;Send Lumen Physio invoice&rdquo;, due Friday.</p>
                    </div>
                    <p class="op-chat__note">Illustrative example of agent mode. Wording varies by model.</p>
                </div>
            </div>
        </section>

        <section class="op-section op-section--paper" id="mcp" aria-labelledby="mcp-title">
            <div class="section-wrap op-columns">
                <div class="op-prose">
                    <h2 id="mcp-title" class="op-prose__title">Your own AI apps can work in it too.</h2>
                    <p><?php echo e($siteName); ?> has a built-in MCP server with 86 tools, so AI apps that support the Model Context Protocol, such as Claude Code and Cursor, can read and update your workspace. Ask your app what is due this week, add a client, log an expense, search your notes or write a Knowledge Base page.</p>
                    <p>The tools cover todos, projects, tasks, clients, meetings, notes, the knowledge base, invoices, advanced invoices, finance, inventory, habits and water.</p>
                    <p><strong>Keep the token private.</strong> It opens your whole workspace, like your master password. You can revoke it in the same place you made it.</p>
                    <p><a class="text-link" href="<?php echo e($publicDocsUrl); ?>#mcp">Full MCP set-up guide <?php echo $publicIcon('arrow-up-right'); ?></a></p>
                </div>
                <div>
                    <ol class="op-steps">
                        <li><div><h3>Make a token</h3><p>In <?php echo e($siteName); ?>, open Settings &rarr; Developer Tools &rarr; MCP Config and press Generate. Copy the token; it is shown once.</p></div></li>
                        <li><div><h3>Add the server to your AI app</h3><p>Paste this into your app&rsquo;s MCP settings, for example <code>.mcp.json</code> in Claude Code:</p>
<pre class="op-code">{
  "mcpServers": {
    "openplan": {
      "type": "http",
      "url": "<?php echo e($publicBaseUrl); ?>/api/mcp-server.php",
      "headers": { "X-MCP-Api-Token": "YOUR_TOKEN" }
    }
  }
}</pre></div></li>
                        <li><div><h3>Ask</h3><p>&ldquo;What&rsquo;s overdue in OpenPlan?&rdquo; or &ldquo;Log a R450 software expense for today.&rdquo;</p></div></li>
                    </ol>
                </div>
            </div>
        </section>

        <section class="op-section op-section--soft" id="privacy" aria-labelledby="privacy-title">
            <div class="section-wrap">
                <div class="op-section__head">
                    <h2 id="privacy-title">Encrypted with your own master password.</h2>
                    <p>Plainly, here is what that protects and what it does not.</p>
                </div>
                <dl class="op-facts">
                    <div><dt>Two passwords</dt><dd>Your login password signs you in. Your master password, asked for next, is what unlocks your data.</dd></div>
                    <div><dt>How it is stored</dt><dd>Your projects, clients, invoices, notes and everything else are saved as encrypted files (AES-256-GCM) with a key made from your master password. The master password is not stored with your data.</dd></div>
                    <div><dt>What the server sees</dt><dd>Your name and email, so you can sign in. While you are signed in, your server session (kept on the server until you sign out or it expires) holds the master password so the app can open your data, and anything you send to the AI goes to that AI provider. This protects stored data; it is not end-to-end encryption.</dd></div>
                    <div><dt>If you forget it</dt><dd>Nobody can reset the master password, and the data it protects cannot be opened without it. Write it down somewhere safe.</dd></div>
                    <div><dt>Your copy</dt><dd>Export everything as an encrypted ZIP, as a readable JSON file (not encrypted, so keep it somewhere safe) or as CSV per module, and import it into any <?php echo e($siteName); ?>, including one you host yourself.</dd></div>
                </dl>
            </div>
        </section>

        <section class="op-section op-section--paper section--mobile" id="mobile" aria-labelledby="mobile-title">
            <div class="section-wrap mobile-layout">
                <div class="mobile-visual mobile-visual--phone">
                    <img class="app-shot--phone" src="<?php echo e(APP_URL); ?>/assets/images/app-mobile-dashboard.webp" width="600" height="1298" alt="The mobile dashboard with tasks, projects, habits and the water tracker (sample data)" loading="lazy" decoding="async">
                </div>
                <div class="mobile-copy">
                    <h2 id="mobile-title" class="op-prose__title">On your phone, too.</h2>
                    <p class="section-lede">Use the mobile web app in any phone browser, or install the Android app. Same account, same data, a layout made for one hand. On an unfolded phone or a tablet it switches to a wider layout with a side menu.</p>
                    <div class="op-cta-row">
                        <?php if ($hasApkRelease): ?>
                            <a class="button button--dark" href="<?php echo e($apkDownloadUrl); ?>" download>Download for Android <?php echo $publicIcon('arrow-down'); ?></a>
                        <?php endif; ?>
                        <a class="text-link" href="<?php echo e($publicMobileUrl); ?>">Phone and Android guide <?php echo $publicIcon('arrow-up-right'); ?></a>
                    </div>
                </div>
            </div>
        </section>

        <section class="section section--pricing" id="pricing" aria-labelledby="pricing-title">
            <div class="section-wrap">
                <div class="pricing-heading">
                    <h2 id="pricing-title">Free workspace. Pay only for AI on our keys.</h2>
                    <p class="section-lede">Every part of the workspace is free. A plan lets the assistant use our AI keys, so you don&rsquo;t need an AI account of your own.</p>
                </div>
                <div class="pricing-grid">
                    <?php require __DIR__ . '/partials/public-pricing-cards.php'; ?>
                </div>
                <?php if (!$isAuthenticated && $plans !== []): ?>
                    <?php require __DIR__ . '/partials/public-plan-note.php'; ?>
                <?php endif; ?>
                <p class="pricing-more"><a class="text-link" href="<?php echo e($publicPricingUrl); ?>#faq">Pricing questions answered <?php echo $publicIcon('arrow-up-right'); ?></a></p>
            </div>
        </section>

        <section class="op-section op-section--soft" id="open-source" aria-labelledby="open-source-title">
            <div class="section-wrap op-columns">
                <div class="op-prose">
                    <h2 id="open-source-title" class="op-prose__title">Open source. Host it yourself if you like.</h2>
                    <p><?php echo e($siteName); ?> is free software under the AGPL-3.0. You can run it on your own server, change it and use it for paid client work. If you offer a changed version to other people over a network, you share your changes under the same licence.</p>
                    <p>A self-hosted copy has every feature. Bring your own AI keys; there is nothing to buy.</p>
                    <div class="op-cta-row">
                        <a class="button button--dark" href="<?php echo e($sourceDownloadUrl); ?>">Download the code (.zip)</a>
                        <a class="text-link" href="<?php echo e($repoUrl); ?>" target="_blank" rel="noreferrer">View on GitHub <?php echo $publicIcon('arrow-up-right'); ?></a>
                    </div>
                </div>
                <ol class="op-steps">
                    <li><div><h3>Download</h3><p>Get the ZIP above, or clone the repository from GitHub.</p></div></li>
                    <li><div><h3>Upload to a PHP server</h3><p>PHP 8.0 or newer on Apache, with the json, mbstring, openssl, curl and zip extensions. No database is needed; the <code>data/</code> folder must be writable.</p></div></li>
                    <li><div><h3>Run the setup page</h3><p>Open the site in your browser. Setup creates your admin account and asks whether it is just for you or for several people.</p></div></li>
                    <li><div><h3>Optional extras</h3><p>Copy <code>.env.example</code> to <code>.env</code> for email, sign-up rules and the daily job. The <a href="<?php echo e($publicDocsUrl); ?>#self-host">self-hosting guide</a> covers each setting.</p></div></li>
                </ol>
            </div>
        </section>

        <section class="section section--closing" id="get-started" aria-labelledby="closing-title">
            <div class="section-wrap closing-panel">
                <div class="closing-decoration" aria-hidden="true"><span></span><span></span><span></span><span></span></div>
                <h2 id="closing-title">Bring the whole business into one place.</h2>
                <p>Create a free workspace in a minute. Add a plan only if you want the AI on our keys.</p>
                <div class="closing-actions">
                    <a class="button button--light" href="<?php echo e($primaryActionUrl); ?>">
                        <?php echo e($primaryActionText); ?>
                        <svg class="icon" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M5 12h14m-6-6 6 6-6 6" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </a>
                    <a class="text-link text-link--light" href="<?php echo e($publicDocsUrl); ?>">Read the documentation <?php echo $publicIcon('arrow-up-right'); ?></a>
                </div>
            </div>
        </section>
    </main>

    <?php require __DIR__ . '/partials/public-site-footer.php'; ?>
    <?php require __DIR__ . '/partials/public-site-scripts.php'; ?>
    <script src="<?php echo e(APP_URL); ?>/assets/js/public-hero.js?v=<?php echo (int) @filemtime(ROOT_PATH . '/assets/js/public-hero.js'); ?>" defer></script>
</body>
</html>
