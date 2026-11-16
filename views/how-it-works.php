<?php
require_once __DIR__ . '/partials/public-site-context.php';
$pageTitle = $siteName . ' | How it works';
$pageDescription = 'A step-by-step walkthrough of OpenPlan: set up your workspace, take a client job from first hello to a paid invoice, and let the AI agent and your own AI apps handle the admin.';
$canonicalUrl = $publicHowItWorksUrl;
$ogImageAlt = 'OpenPlan dashboard with sample workspace data';
$publicHeaderCurrentPage = 'how-it-works';
require __DIR__ . '/partials/public-site-head.php';
?>
<body class="marketing-page marketing-subpage">
<?php require __DIR__ . '/partials/public-site-header.php'; ?>
<main id="main">
    <section class="public-page-hero public-page-hero--how public-page-hero--center section-wrap" id="top" aria-labelledby="how-title">
        <div class="public-page-hero__copy">
            <h1 id="how-title">From first hello to a paid invoice.</h1>
            <p class="hero__lede">Set up once, then follow one client job through <?php echo e($siteName); ?>: the client, the project, the tasks, the time, the notes and the invoice, all linked in one encrypted workspace.</p>
            <div class="op-cta-row">
                <a class="button button--dark" href="#setup">Start the walkthrough <?php echo $publicIcon('arrow-down'); ?></a>
                <a class="text-link" href="<?php echo e($primaryActionUrl); ?>"><?php echo e($primaryActionText); ?> <?php echo $publicIcon('arrow-up-right'); ?></a>
            </div>
        </div>
        <figure class="public-screen public-screen--hero">
            <img src="<?php echo e($publicBaseUrl); ?>/assets/images/app-dashboard.webp" width="1600" height="1000" alt="OpenPlan dashboard with tasks, projects, habits and revenue (sample data)" fetchpriority="high" decoding="async" data-dashboard-shot>
            <figcaption>Dashboard &middot; sample workspace data</figcaption>
        </figure>
    </section>

    <section class="op-section op-section--soft" id="setup" aria-labelledby="setup-title">
        <div class="section-wrap op-columns">
            <div class="op-prose">
                <h2 id="setup-title" class="op-prose__title">Set up in a few minutes.</h2>
                <p>You need an email address and two passwords. Nothing to install: it runs in your browser, on your phone, or on your own server.</p>
                <p><strong>Two passwords, on purpose.</strong> Your login password signs you in. Your master password unlocks your encrypted data and <strong>cannot be reset by anyone</strong>, so write it down before you continue.</p>
            </div>
            <ol class="op-steps">
                <li><div><h3>Create your account</h3><p>Enter your name, email, a login password (8 characters or more) and a master password. If the site asks you to confirm your email, open the link it sends.</p></div></li>
                <li><div><h3>Unlock your workspace</h3><p>Each time you sign in, enter your email and password, then your master password. Five wrong master passwords within 15 minutes lock it for 30 minutes.</p></div></li>
                <li><div><h3>Add your business details</h3><p>In <strong>Settings &rarr; Business Information</strong>, add your business name, contact details, currency (USD, EUR, GBP or ZAR) and tax rate. Invoices use them.</p></div></li>
                <li><div><h3>Choose how the AI runs</h3><p>Paste your own Groq, OpenRouter or Gemini key in <strong>Settings &rarr; AI API Keys</strong>, or start <?php echo $publicTrialDays > 0 ? 'the free trial or ' : ''; ?>a plan to use ours. You can skip this; everything else works without AI.</p></div></li>
            </ol>
        </div>
    </section>

    <section class="op-section op-section--paper" id="walkthrough" aria-labelledby="walkthrough-title">
        <div class="section-wrap">
            <div class="op-section__head">
                <h2 id="walkthrough-title">One client job, start to finish.</h2>
                <p>A designer has a new client, Lumen Physio, who wants an online booking flow. Here is how the job runs in <?php echo e($siteName); ?>.</p>
            </div>
            <div class="op-area">
                <div class="op-area__intro">
                    <h3>Plan it</h3>
                    <p>The client, the project and the tasks are linked, so each one shows the others.</p>
                    <figure class="op-area__shot">
                        <img src="<?php echo e(APP_URL); ?>/assets/images/app-projects.webp" width="1400" height="1064" alt="Projects screen with the Lumen Physio booking flow project at 33% (sample data)" loading="lazy" decoding="async">
                        <figcaption>Projects &middot; sample data</figcaption>
                    </figure>
                </div>
                <ol class="op-steps">
                    <li><div><h3>Add the client</h3><p>Open <strong>Clients</strong> and add Lumen Physio with a contact name, email, phone, company and notes.</p></div></li>
                    <li><div><h3>Create the project</h3><p>Open <strong>Projects</strong>, add &ldquo;Lumen Physio booking flow&rdquo;, pick the client, a colour and a status. The project card shows its progress as tasks are done.</p></div></li>
                    <li><div><h3>Break it into tasks</h3><p>Add tasks such as &ldquo;Wireframes&rdquo; and &ldquo;Reminder emails&rdquo; with a priority, due date, time estimate and subtasks. Use a template for jobs you repeat, or press <strong>AI Breakdown</strong> to suggest the subtasks.</p></div></li>
                    <li><div><h3>See it on the calendar</h3><p><strong>Calendar</strong> shows every task on its due date. Add the kickoff call as a meeting with the time, place, attendees and the client.</p></div></li>
                </ol>
            </div>
            <div class="op-area">
                <div class="op-area__intro">
                    <h3>Do it</h3>
                    <p>Move work along, keep track of time, and keep what you learn next to the job.</p>
                </div>
                <ol class="op-steps">
                    <li><div><h3>Work the board</h3><p>The project page and <strong>Tasks</strong> both have a kanban board with Backlog, To Do, In Progress, Review and Done. Drag a card to change its status, or switch to the list view to filter and sort.</p></div></li>
                    <li><div><h3>Track the time</h3><p>Start the timer on a task while you work. The time is logged against the task, next to your estimate.</p></div></li>
                    <li><div><h3>Keep the notes</h3><p>Write the call notes in <strong>Notes</strong> with a checklist of what the client asked for, and tag them &ldquo;lumen&rdquo;. In a note, <strong>Ctrl+I</strong> opens Notes AI, which can turn the checklist into tasks.</p></div></li>
                    <li><div><h3>Keep the reference material</h3><p>Put the brief and specs in a <strong>Knowledge Base</strong> folder as Markdown. Choose that folder in the assistant and it reads them before it answers.</p></div></li>
                </ol>
            </div>
            <div class="op-area">
                <div class="op-area__intro">
                    <h3>Bill it</h3>
                    <p>Invoices know the client and project, and paid ones count as revenue.</p>
                    <figure class="op-area__shot">
                        <img src="<?php echo e(APP_URL); ?>/assets/images/app-invoices.webp" width="1400" height="1064" alt="Invoices screen with paid, pending and draft invoices (sample data)" loading="lazy" decoding="async">
                        <figcaption>Invoices &middot; sample data</figcaption>
                    </figure>
                </div>
                <ol class="op-steps">
                    <li><div><h3>Create the invoice</h3><p>Open <strong>Invoices</strong>, choose the client and project, set the due date and add line items. Tax and currency come from your business details.</p></div></li>
                    <li><div><h3>Send it</h3><p>Download the invoice as a PDF and email it to the client yourself, then mark it <strong>Sent</strong>. Need bank details, a contract period or custom fields on it? Use <strong>Advanced Invoices</strong> instead, with a Classic or Modern layout.</p></div></li>
                    <li><div><h3>Get paid</h3><p>Mark it <strong>Paid</strong> when the money arrives, or <strong>Overdue</strong> if the due date passes, so your totals show what is still owed.</p></div></li>
                    <li><div><h3>See the money</h3><p>Paid invoices count as revenue in <strong>Finance</strong>, next to the expenses you record, with a six-month chart. The client page shows what they have been invoiced and what they have paid.</p></div></li>
                </ol>
            </div>
        </div>
    </section>

    <section class="op-section op-section--soft" id="with-ai" aria-labelledby="with-ai-title">
        <div class="section-wrap op-columns">
            <div class="op-prose">
                <h2 id="with-ai-title" class="op-prose__title">Or ask the AI to do it.</h2>
                <p>Every step above can also be done by asking. In <strong>AI Assistant</strong> with agent mode on, the agent creates the client, project, tasks, invoice and notes for you and then lists what it did. It needs an OpenRouter or Gemini model; Groq models chat and write but do not act.</p>
                <p>From outside the app, AI apps that support MCP, such as Claude Code or Cursor, can do the same through <?php echo e($siteName); ?>&rsquo;s 86 MCP tools.</p>
                <p><a class="text-link" href="<?php echo e($publicDocsUrl); ?>#ai">How the AI works <?php echo $publicIcon('arrow-up-right'); ?></a> &nbsp; <a class="text-link" href="<?php echo e($publicDocsUrl); ?>#mcp">Connect an AI app <?php echo $publicIcon('arrow-up-right'); ?></a></p>
            </div>
            <div>
                <dl class="op-facts">
                    <div><dt>Plan</dt><dd>&ldquo;Create a project for Lumen Physio called booking flow, with tasks for wireframes, build and reminder emails, due over the next three weeks.&rdquo;</dd></div>
                    <div><dt>Bill</dt><dd>&ldquo;Make a draft invoice for Lumen Physio: 12 hours of design at R650.&rdquo;</dd></div>
                    <div><dt>Check</dt><dd>&ldquo;What is overdue this week?&rdquo;</dd></div>
                    <div><dt>Record</dt><dd>&ldquo;Log a R450 expense for software today.&rdquo;</dd></div>
                </dl>
                <p class="op-chat__note">Example requests. The AI&rsquo;s wording varies by model.</p>
            </div>
        </div>
    </section>

    <section class="op-section op-section--paper" id="every-day" aria-labelledby="every-day-title">
        <div class="section-wrap">
            <div class="op-section__head">
                <h2 id="every-day-title">Around the work.</h2>
                <p>The tools that keep a one-person business running between client jobs.</p>
            </div>
            <dl class="op-modules">
                <div class="op-module"><dt>Your morning</dt><dd>The dashboard opens on pending tasks, active projects, today&rsquo;s habits, water and revenue. The bell lists what is overdue or due soon.</dd></div>
                <div class="op-module"><dt>Focus</dt><dd>Start a Pomodoro session with your own music. The timer and player stay on screen while you move between pages, and your sessions and focus time are counted.</dd></div>
                <div class="op-module"><dt>Routines</dt><dd>Add habits such as &ldquo;Plan the day&rdquo; or &ldquo;Send invoices on Friday&rdquo;, with a reminder time. Tick them off, time them, and keep your streaks.</dd></div>
                <div class="op-module"><dt>Water</dt><dd>Set a daily goal and reminder times, then log a glass with one tap from the dashboard.</dd></div>
                <div class="op-module"><dt>Stock</dt><dd>If you sell products, Inventory keeps SKUs, cost and selling prices, stock in and out, and marks items in red once they reach their reorder point.</dd></div>
                <div class="op-module"><dt>Your data</dt><dd>Export everything from <strong>Data Management</strong> as an encrypted ZIP, a readable JSON file or CSV per module, and turn on automatic backups.</dd></div>
            </dl>
        </div>
    </section>

    <section class="op-section op-section--soft" id="accounts" aria-labelledby="accounts-title">
        <div class="section-wrap">
            <div class="op-section__head">
                <h2 id="accounts-title">One workspace per person.</h2>
            </div>
            <dl class="op-facts">
                <div><dt>On <?php echo e(preg_replace('#^https?://#', '', $publicBaseUrl)); ?></dt><dd>Each account is a private workspace, encrypted with its own master password. Workspaces are not shared between accounts.</dd></div>
                <div><dt>On your own server</dt><dd>Setup asks whether the copy is just for you (sign-ups closed) or for several people (anyone with the address can sign up). The admin can promote, ban or remove users, and each person still has a separate encrypted workspace.</dd></div>
                <div><dt>On your phone</dt><dd>Sign in to the mobile web app or the Android app with the same account. <a class="text-link" href="<?php echo e($publicMobileUrl); ?>">Phone guide</a></dd></div>
            </dl>
        </div>
    </section>

    <section class="section public-close" aria-labelledby="how-close-title">
        <div class="section-wrap closing-panel">
            <h2 id="how-close-title">Try it with your next client job.</h2>
            <p>The workspace is free. Add a plan only if you want the AI on our keys.</p>
            <div class="closing-actions">
                <a class="button button--light" href="<?php echo e($primaryActionUrl); ?>"><?php echo e($primaryActionText); ?> <?php echo $publicIcon('arrow-right'); ?></a>
                <a class="text-link text-link--light" href="<?php echo e($publicPricingUrl); ?>">See pricing <?php echo $publicIcon('arrow-up-right'); ?></a>
            </div>
        </div>
    </section>
</main>
<?php require __DIR__ . '/partials/public-site-footer.php'; ?>
<?php require __DIR__ . '/partials/public-site-scripts.php'; ?>
</body>
</html>
