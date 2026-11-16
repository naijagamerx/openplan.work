<?php
require_once __DIR__ . '/partials/public-site-context.php';
$pageTitle = $siteName . ' | Documentation';
$pageDescription = 'How to use OpenPlan: getting started, every module, the AI assistant and agent, connecting AI apps over MCP, the phone apps, plans, security, backups and self-hosting.';
$canonicalUrl = $publicDocsUrl;
$ogImageAlt = 'OpenPlan dashboard with sample workspace data';
$publicHeaderCurrentPage = 'docs';
$siteHost = preg_replace('#^https?://#', '', $publicBaseUrl);
$structuredData = [
    '@context' => 'https://schema.org',
    '@type' => 'TechArticle',
    'headline' => $siteName . ' documentation',
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
    ['id' => 'workspace', 'label' => 'The workspace'],
    ['id' => 'ai', 'label' => 'AI assistant'],
    ['id' => 'mcp', 'label' => 'Connect AI apps (MCP)'],
    ['id' => 'alexa', 'label' => 'Alexa'],
    ['id' => 'mobile-access', 'label' => 'Phone and Android'],
    ['id' => 'plans', 'label' => 'Plans and payments'],
    ['id' => 'security', 'label' => 'Security and privacy'],
    ['id' => 'data', 'label' => 'Export and backups'],
    ['id' => 'self-host', 'label' => 'Self-hosting'],
    ['id' => 'configuration', 'label' => 'Settings (.env)'],
    ['id' => 'updating', 'label' => 'Updating'],
    ['id' => 'license', 'label' => 'License'],
];
require __DIR__ . '/partials/public-site-head.php';
?>
<body class="marketing-page marketing-subpage">
<?php require __DIR__ . '/partials/public-site-header.php'; ?>
<main id="main">
    <section class="public-docs-hero public-docs-hero--center" id="top" aria-labelledby="docs-title">
        <div class="section-wrap public-docs-hero-layout">
            <div>
                <h1 id="docs-title">How to use <?php echo e($siteName); ?>.</h1>
                <p class="hero__lede">Everything in one place: setting up, each part of the workspace, the AI, connecting your own AI apps, phones, plans, security, and running your own copy.</p>
                <nav class="public-docs-links" aria-label="Popular documentation topics">
                    <a href="#start">Getting started <?php echo $publicIcon('arrow-down'); ?></a>
                    <a href="#mcp">Connect AI apps <?php echo $publicIcon('arrow-down'); ?></a>
                    <a href="#self-host">Self-hosting <?php echo $publicIcon('arrow-down'); ?></a>
                </nav>
            </div>
        </div>
    </section>

    <section class="section public-docs-body" aria-label="<?php echo e($siteName); ?> documentation">
        <div class="section-wrap public-docs-layout">
            <aside class="public-docs-sidebar" aria-label="Documentation contents">
                <?php foreach ($docsNav as $item): ?>
                    <a href="#<?php echo e($item['id']); ?>"><?php echo e($item['label']); ?></a>
                <?php endforeach; ?>
            </aside>

            <article class="public-docs-content">
                <section class="public-docs-section" id="start" aria-labelledby="start-title">
                    <h2 id="start-title">Getting started</h2>
                    <p class="section-lede"><?php echo e($siteName); ?> is one workspace for running a small business: projects and tasks, clients, invoices, money, stock, notes and daily routines, with an AI assistant. You can use it at <?php echo e($siteHost); ?> or run your own copy.</p>
                    <ol>
                        <li><strong>Create an account</strong> with your name, email, a login password (8 characters or more) and a master password. Confirm your email if the site asks.</li>
                        <li><strong>Sign in</strong> with your email and password, then enter your master password to unlock your data. You do this each time you sign in.</li>
                        <li><strong>Add your business details</strong> in Settings &rarr; Business Information: business name, contact details, currency and tax rate. Invoices use them.</li>
                        <li><strong>Add a first client or project</strong>, or press <em>Quick Add</em> at the top of any page to create a task, project, invoice or client.</li>
                        <li><strong>Optional:</strong> set up the AI (see <a class="text-link" href="#ai">AI assistant</a>) and install it on your phone (see <a class="text-link" href="#mobile-access">Phone and Android</a>).</li>
                    </ol>
                    <div class="public-docs-callout"><strong>Write down your master password.</strong><span>It encrypts your workspace. Nobody can reset it, and without it your data cannot be opened.</span></div>
                    <p>For a worked example from first client to paid invoice, read <a class="text-link" href="<?php echo e($publicHowItWorksUrl); ?>">How it works</a>.</p>
                </section>

                <section class="public-docs-section" id="workspace" aria-labelledby="workspace-title">
                    <h2 id="workspace-title">The workspace</h2>
                    <p>Every part is in the left-hand menu on a computer and in <em>Menu</em> on a phone.</p>

                    <h3>Dashboard</h3>
                    <p>Pending tasks, active projects, today&rsquo;s habits, water, revenue, a focus timer and quick actions. A bell at the top lists overdue and due-soon tasks; turn on browser notifications and sounds in Settings.</p>

                    <h3>Projects and tasks</h3>
                    <ul>
                        <li>A project has a client, colour, status and description. Its page shows its tasks on a drag-and-drop board and a progress bar.</li>
                        <li>A task has a priority (low to urgent), start and due dates, a time estimate, subtasks, a colour, and can repeat daily, weekly or monthly.</li>
                        <li><strong>Tasks</strong> shows all tasks as a kanban board (Backlog, To Do, In Progress, Review, Done) or a list, with filters for project, status, priority and due date, bulk actions and CSV export.</li>
                        <li>Start the timer on a task to log time against it. Save a task as a template to reuse it.</li>
                        <li>AI helpers: <em>AI Improve</em> rewrites a description, <em>AI Breakdown</em> suggests subtasks, and <em>AI Generate Kanban</em> drafts a set of tasks from a short description.</li>
                    </ul>

                    <h3>Calendar and meetings</h3>
                    <p>A month view of tasks by due date and your meetings. A meeting has a date, start and end time, location, attendees, notes, and can be linked to a project and client.</p>

                    <h3>Clients</h3>
                    <p>Contact details, company and notes for each client. A client&rsquo;s page lists their projects and what you have invoiced and been paid.</p>

                    <h3>Invoices</h3>
                    <ul>
                        <li>Choose the client and project, set the due date and add line items. The currency (USD, EUR, GBP or ZAR) and tax rate come from Settings &rarr; Business Information.</li>
                        <li>Set each invoice&rsquo;s status to draft, sent, paid or overdue. The totals at the top of the page add them up.</li>
                        <li>Download an invoice as a PDF and send it yourself. <?php echo e($siteName); ?> does not email invoices to clients.</li>
                    </ul>

                    <h3>Advanced invoices</h3>
                    <p>For formal or recurring billing: your company header and logo, customer ID, property address, contract start and end dates, bank details (bank, account, branch code, reference), custom fields you can show or hide on the printout, notes, a footer, a currency per invoice, and Classic or Modern layouts with PDF download.</p>

                    <h3>Finance</h3>
                    <p>Record income and expenses and link them to a client. Paid invoices count as revenue automatically. A six-month chart and filters by type and client show where the money goes.</p>

                    <h3>Inventory</h3>
                    <p>Products with name, SKU, category, description, cost price, selling price and a reorder point. Record stock in and out with a note and see each item&rsquo;s history. Items at or below their reorder point are marked in red. <em>AI Generate</em> suggests items.</p>

                    <h3>Notes</h3>
                    <ul>
                        <li>Write with bold, italic and lists, and add a checklist inside the note. Tag, pin and favourite notes; filter by tag in the side pane, and rename or delete tags everywhere at once.</li>
                        <li><strong>Notes AI</strong> (Ctrl+I): improve, expand, summarise or rewrite the note, generate text, turn the note into tasks, or chat about it. Choose <em>All notes</em> to let it see the titles of your other notes.</li>
                        <li><em>Convert to Knowledge Base</em> saves the note as a Markdown file.</li>
                    </ul>

                    <h3>Knowledge Base</h3>
                    <p>Folders of Markdown (.md) and XML (.xml) files, for briefs, specs, processes and reference material. Upload, search and rename files and folders. Other file types, such as images or PDFs, are not accepted. Choose a folder in the AI assistant and it reads those files before answering.</p>

                    <h3>Habits</h3>
                    <p>Daily or one-time habits with a category, reminder time and target length. Tick them off or time a session, keep current and longest streaks, and look back on a calendar and history. The AI can suggest habits.</p>

                    <h3>Pomodoro</h3>
                    <p>Preset or custom sessions (1 to 180 minutes of focus, 1 to 60 minutes of break). Upload your own focus music (MP3, WAV or M4A) with autoplay, loop and volume. A small timer and player stay on screen while you move between pages.</p>

                    <h3>Water plan</h3>
                    <p>Set a daily goal, reminder times and browser notifications, and log water from the dashboard with one tap.</p>

                    <h3>Support</h3>
                    <p>Open a support ticket with a priority from <em>Support</em> in the menu. The site&rsquo;s admin answers it.</p>
                </section>

                <section class="public-docs-section" id="ai" aria-labelledby="ai-title">
                    <h2 id="ai-title">AI assistant</h2>
                    <p>The AI is optional. Everything else works without it.</p>

                    <h3>Choose how it runs</h3>
                    <ul>
                        <li><strong>Your own key, free:</strong> in Settings &rarr; AI API Keys, paste a key from Groq, OpenRouter or Google Gemini, or the address of your own Ollama server. Then pick the models you want in Model Settings.</li>
                        <li><strong>A plan on our keys:</strong> buy a plan<?php echo $publicTrialDays > 0 ? ' or start the free trial' : ''; ?> and turn on <em>Use admin&rsquo;s shared AI keys</em>. You can use only the models the plan lists, up to its monthly request limit. See <a class="text-link" href="#plans">Plans and payments</a>.</li>
                    </ul>

                    <h3>Chat and agent mode</h3>
                    <ul>
                        <li>Open <strong>AI Assistant</strong> and ask in plain words. With <em>Agent Mode</em> on (the default), the assistant carries out actions in your workspace straight away, without a confirmation step, then tells you what it did.</li>
                        <li>It can create and update tasks and projects, complete or delete them, add and remove clients, create invoices and advanced invoices, record income and expenses, write and delete notes, write Knowledge Base notes, add and complete habits, add stock, start a Pomodoro and log water. It can also list and search all of these.</li>
                        <li><strong>Agent actions need an OpenRouter or Gemini model.</strong> Groq models are kept for chat, writing and summaries: they answer but do not act, and do not read Knowledge Base folders.</li>
                        <li>With two or more providers set up, <em>Smart mode</em> sends long or complex requests to OpenRouter or Gemini and the rest to your first provider.</li>
                    </ul>

                    <h3>Answers from your files</h3>
                    <p>Choose a Knowledge Base folder in the assistant and its files are added to your request. Save any answer to Notes or the Knowledge Base, copy it, or export it as Markdown. The PRD tool turns an idea into a product requirements document and can save it to the Knowledge Base.</p>

                    <div class="public-docs-callout"><strong>What the AI provider sees.</strong><span>The text of your request, and any notes or Knowledge Base files you include, are sent to the AI provider (Groq, OpenRouter, Google, or your own Ollama server) to be answered. Their own privacy terms apply.</span></div>
                </section>

                <section class="public-docs-section" id="mcp" aria-labelledby="mcp-title">
                    <h2 id="mcp-title">Connect AI apps (MCP)</h2>
                    <p><?php echo e($siteName); ?> has a built-in MCP (Model Context Protocol) server with 86 tools, so AI apps that support MCP can read and change your workspace on your behalf. The tools cover todos, projects, tasks and subtasks, clients, meetings, notes, the Knowledge Base, invoices, advanced invoices, finance, inventory, habits, water, and a search across all of them.</p>

                    <h3>1. Make a token</h3>
                    <p>Sign in, open Settings &rarr; Developer Tools &rarr; <em>MCP Config</em>, and press <em>Generate</em>. Copy the token straight away: it is shown once, and only a hash of it is stored. You can revoke it on the same page.</p>
                    <div class="public-docs-callout"><strong>Treat the token like your master password.</strong><span>It carries a locked copy of your master password, so on its own it opens your whole workspace. Never paste it into a shared file or a public repository.</span></div>

                    <h3>2a. Apps that connect over HTTP (Claude Code, Cursor and others)</h3>
                    <p>Add this server to the app&rsquo;s MCP settings, for example <code>.mcp.json</code> in your project for Claude Code, or <code>.cursor/mcp.json</code> for Cursor:</p>
<pre class="op-code">{
  "mcpServers": {
    "openplan": {
      "type": "http",
      "url": "<?php echo e($publicBaseUrl); ?>/api/mcp-server.php",
      "headers": { "X-MCP-Api-Token": "YOUR_TOKEN" }
    }
  }
}</pre>

                    <h3>2b. Apps that run a local server (Claude Desktop and others)</h3>
                    <p>Download the <a class="text-link" href="<?php echo e($sourceDownloadUrl); ?>">source ZIP</a>, run <code>npm install</code> in its <code>mcp-server</code> folder (Node.js 18 or newer), then add this to the app&rsquo;s config, for example <code>claude_desktop_config.json</code>:</p>
<pre class="op-code">{
  "mcpServers": {
    "openplan": {
      "command": "node",
      "args": ["/full/path/to/mcp-server/index.js"],
      "env": {
        "API_URL": "<?php echo e($publicBaseUrl); ?>/api",
        "MCP_API_TOKEN": "YOUR_TOKEN"
      }
    }
  }
}</pre>

                    <h3>3. Ask</h3>
                    <p>Restart the app and try &ldquo;What is overdue in OpenPlan?&rdquo;, &ldquo;Add a client called Kestrel Outdoor&rdquo; or &ldquo;Search my notes for the Lumen brief&rdquo;. MCP is free on every account; the AI app&rsquo;s own costs are between you and that app.</p>
                </section>

                <section class="public-docs-section" id="alexa" aria-labelledby="alexa-title">
                    <h2 id="alexa-title">Alexa</h2>
                    <p>An Alexa skill (invocation name &ldquo;my plan&rdquo;) can read out your tasks, todos, habits, water and this month&rsquo;s earnings, and add todos, tasks, projects, clients, notes, meetings and habits by voice.</p>
                    <ul>
                        <li>The skill is not yet published in the Alexa store. Settings &rarr; Voice Assistants shows how to link your account when you have been invited to it.</li>
                        <li>Self-hosters can build their own skill from the <code>alexa-skill</code> folder in the source, which holds the interaction model and a step-by-step console guide.</li>
                    </ul>
                </section>

                <section class="public-docs-section" id="mobile-access" aria-labelledby="mobile-access-title">
                    <h2 id="mobile-access-title">Phone and Android</h2>
                    <ul>
                        <li><strong>Mobile web app:</strong> open <code><?php echo e($siteHost); ?>/mobile/</code> in any phone browser and sign in. Use <em>Add to Home Screen</em> to open it like an app. On a self-hosted copy it is at <code>your-address/mobile/</code>.</li>
                        <li><strong>Android app:</strong> a direct download (APK) that opens the mobile workspace full screen. It connects to <?php echo e($siteHost); ?> only, so self-hosters should use the mobile web app.</li>
                        <li><strong>Foldables and tablets:</strong> at 600 pixels wide or more the mobile app switches to a side menu and a wider column, and Notes shows the list and the note side by side. Touch tablets that open the desktop site are sent to the mobile app.</li>
                    </ul>
                    <p><a class="text-link" href="<?php echo e($publicMobileUrl); ?>">Install steps and what you can do on a phone <?php echo $publicIcon('arrow-up-right'); ?></a></p>
                </section>

                <section class="public-docs-section" id="plans" aria-labelledby="plans-title">
                    <h2 id="plans-title">Plans and payments</h2>
                    <ul>
                        <li>The whole workspace, MCP, the phone apps and the AI with your own key are free.</li>
                        <li>A plan lets the AI use our keys, with the models and monthly request limit shown on the plan. Each message to the assistant or Notes AI counts as one request; using your own key never counts. My Subscription shows your usage.</li>
                        <?php if ($publicTrialDays > 0): ?>
                            <li>The free trial gives <?php echo (int) $publicTrialRequests; ?> AI requests over <?php echo (int) $publicTrialDays; ?> days, once per email address, with no card. It ends by itself. Buying a plan replaces it.</li>
                        <?php endif; ?>
                        <li>Pay by card through PayFast (in South African rand, renews until you cancel) where it is offered, or by bank transfer or crypto: upload your proof of payment and the admin activates the plan. Manual payments do not renew.</li>
                        <li>Cancel a card plan in My Subscription; it stays active until the end of the period you paid for. Refunds follow the <a class="text-link" href="<?php echo e($publicRouteUrl('refunds')); ?>">Refund Policy</a>.</li>
                    </ul>
                    <p><a class="text-link" href="<?php echo e($publicPricingUrl); ?>">Plans and pricing questions <?php echo $publicIcon('arrow-up-right'); ?></a></p>
                </section>

                <section class="public-docs-section" id="security" aria-labelledby="security-title">
                    <h2 id="security-title">Security and privacy</h2>
                    <p>What <?php echo e($siteName); ?> protects, and how, stated plainly.</p>
                    <ul>
                        <li><strong>Encrypted storage.</strong> Each account&rsquo;s data is saved in its own encrypted files (AES-256-GCM, a fresh random IV on every save). The key is made from your master password with SHA-256, so <strong>use a long master password that you use nowhere else</strong>, such as four or five random words.</li>
                        <li><strong>Two-step sign-in.</strong> Email and login password (stored as a bcrypt hash), then the master password within five minutes. Five wrong master passwords in 15 minutes lock the account for 30 minutes.</li>
                        <li><strong>What is not encrypted.</strong> The account list (names, email addresses, password hashes and roles), so you can sign in.</li>
                        <li><strong>While you are signed in,</strong> your master password is held in your server session (on the server, until you sign out or it expires) so the app can open your data. Sessions time out after 20 minutes, an hour or never, as you choose in Settings.</li>
                        <li><strong>Tokens.</strong> An MCP token or a phone&rsquo;s device token carries a locked copy of your key, so it can work while you are signed out. A linked Alexa account works the same way with the site&rsquo;s Alexa key. Revoke what you do not use.</li>
                        <li><strong>AI.</strong> Text you send to the AI goes to the AI provider. See <a class="text-link" href="#ai">AI assistant</a>.</li>
                        <li><strong>Web protections.</strong> Every change needs a CSRF token, cookies are HttpOnly and SameSite, and the <code>data</code> folder is blocked from the web.</li>
                    </ul>
                    <div class="public-docs-callout"><strong>This is encryption at rest, not end-to-end encryption.</strong><span>It protects your files where they are stored. Whoever runs the server you sign in to is trusted while you are signed in. If that matters to you, run your own copy.</span></div>
                </section>

                <section class="public-docs-section" id="data" aria-labelledby="data-title">
                    <h2 id="data-title">Export and backups</h2>
                    <p>Open <strong>Data Management</strong> in the menu (Settings &rarr; Backup Management has the same backups).</p>
                    <ul>
                        <li><strong>Secure ZIP:</strong> your files, still encrypted. You need your master password to restore it.</li>
                        <li><strong>Raw JSON:</strong> everything as readable, unencrypted text. Store it somewhere safe.</li>
                        <li><strong>CSV:</strong> tasks, projects, invoices, finance, inventory, clients or habits, for a spreadsheet.</li>
                        <li><strong>Import</strong> a JSON or ZIP export into any <?php echo e($siteName); ?>, including a self-hosted one.</li>
                        <li><strong>Automatic backups</strong> can be turned on there too. Download, restore or delete any backup.</li>
                    </ul>
                    <p>Changing your master password (Settings &rarr; Change Master Password) re-encrypts all your data with the new one.</p>
                </section>

                <section class="public-docs-section" id="self-host" aria-labelledby="self-host-title">
                    <h2 id="self-host-title">Self-hosting</h2>
                    <p>Run your own copy on any PHP web host or your own computer. It has every feature; there is nothing to buy.</p>

                    <h3>What you need</h3>
                    <ul>
                        <li>PHP 8.0 or newer with the <code>json</code>, <code>mbstring</code>, <code>openssl</code>, <code>curl</code>, <code>zip</code> and <code>calendar</code> extensions. No database.</li>
                        <li>Apache with <code>.htaccess</code> files allowed (they keep the <code>data</code> folder and <code>.env</code> private). On Nginx, block web access to <code>/data/</code> and <code>/.env</code> yourself before you add any data.</li>
                        <li>Write access for PHP to the app folder, so it can create <code>data/</code>.</li>
                    </ul>

                    <h3 id="hosted-run">Install</h3>
                    <ol>
                        <li>Download the <a class="text-link" href="<?php echo e($sourceDownloadUrl); ?>">ZIP</a> or clone <a class="text-link" href="<?php echo e($repoUrl); ?>" target="_blank" rel="noreferrer">the GitHub repository</a>, and upload the files to your web folder.</li>
                        <li>Open the site in your browser. It goes to the setup page.</li>
                        <li>Enter your name, email, password and master password, and choose <em>Single User</em> (sign-ups closed) or <em>Multi User</em> (anyone with the address can sign up). This first account is the admin.</li>
                        <li>Optional: copy <code>.env.example</code> to <code>.env</code> to set up email, the daily job and the other settings below.</li>
                    </ol>

                    <h3 id="local-run">Run on your own computer</h3>
                    <p>With PHP installed, run this in the app folder, then open <code>http://localhost:8000</code>:</p>
                    <div class="public-docs-code" aria-label="Local server command"><code>php start_server.php</code></div>

                    <h3>The daily job</h3>
                    <p>Sends plan and trial reminder emails and marks ended plans as expired, cleans up old logs and rate limits, and writes the AI daily brief. You need it if you sell plans. Set <code>LAZYMAN_CRON_KEY</code> in <code>.env</code> to a long random value, then have your host call this once a day:</p>
                    <div class="public-docs-code" aria-label="Daily job command"><code>curl -s "https://your-site/api/cron.php?job=run_daily&amp;cron_key=YOUR_CRON_KEY"</code></div>
                    <p>Or, if you have a shell, run <code>php api/cron.php job=run_daily</code> from the app folder. Called over the web, the job also needs <code>LAZYMAN_MASTER_PASSWORD</code>: the master password of the one account the background work runs as (that account also gets the AI daily brief).</p>

                    <h3>Selling plans on your copy</h3>
                    <p>As the admin, add shared AI keys (Admin Hub &rarr; Shared AI Keys), create plans (Admin Hub &rarr; Plans) and payment methods, and approve payments. Card payments need a PayFast merchant account and the <code>PAYFAST_*</code> settings; the free trial needs <code>TRIAL_ENABLED=1</code>. Fill in the <code>LEGAL_*</code> settings first: the Terms, Privacy and Refund pages show your details from them.</p>
                </section>

                <section class="public-docs-section" id="configuration" aria-labelledby="configuration-title">
                    <h2 id="configuration-title">Settings (.env)</h2>
                    <p>Every setting is optional; <code>.env.example</code> explains each one. The main groups:</p>
                    <ul>
                        <li><strong>Branding:</strong> <code>APP_DISPLAY_NAME</code> and <code>APP_TAGLINE</code>. Each workspace can also set a site name and favicon in Settings.</li>
                        <li><strong>Email:</strong> <code>MAIL_DRIVER</code> (<code>mail</code> or <code>smtp</code>), <code>SMTP_HOST</code>, <code>SMTP_PORT</code>, <code>SMTP_ENCRYPTION</code>, <code>SMTP_USERNAME</code>, <code>SMTP_PASSWORD</code>, <code>MAIL_FROM_ADDRESS</code>, <code>MAIL_FROM_NAME</code> and <code>CONTACT_EMAIL</code>.</li>
                        <li><strong>Sign-up checks:</strong> <code>EMAIL_VERIFICATION_ENABLED</code> and <code>PASSWORD_RESET_ENABLED</code>. Turn them on once email sending works.</li>
                        <li><strong>Daily job:</strong> <code>LAZYMAN_CRON_KEY</code>, <code>AI_CRON_ENABLED</code> and <code>LAZYMAN_MASTER_PASSWORD</code>.</li>
                        <li><strong>Phones:</strong> <code>DEVICE_TOKEN_PEPPER</code>, set before any phone is paired.</li>
                        <li><strong>Alexa:</strong> <code>ALEXA_SKILL_ID</code>, the owner-mode settings and the account-linking settings.</li>
                        <li><strong>Selling:</strong> <code>LEGAL_*</code>, <code>PAYFAST_*</code> and <code>TRIAL_*</code>.</li>
                    </ul>
                    <div class="public-docs-callout"><strong>Keep <code>.env</code> private.</strong><span>It can hold passwords and keys. Never commit it or share it; the root <code>.htaccess</code> blocks it from the web on Apache.</span></div>
                </section>

                <section class="public-docs-section" id="updating" aria-labelledby="updating-title">
                    <h2 id="updating-title">Updating</h2>
                    <ol>
                        <li>Back up the <code>data</code> folder and your <code>.env</code> file, and export a Secure ZIP from Data Management.</li>
                        <li>Download the latest ZIP and upload its files over the old ones. Do not delete <code>data</code> or <code>.env</code>; the download contains neither.</li>
                        <li>Sign in and check that your workspace opens. If anything fails, look in <code>data/php_error.log</code>.</li>
                    </ol>
                </section>

                <section class="public-docs-section" id="license" aria-labelledby="license-title">
                    <h2 id="license-title">License</h2>
                    <p><?php echo e($siteName); ?> is free software under the GNU Affero General Public License v3.0. You may use, self-host, change and share it, including for paid client work. If you run a changed version for other people over a network, you must offer them the source of your changes under the same license. It comes with no warranty.</p>
                    <p><a class="text-link" href="<?php echo e($repoUrl); ?>/blob/main/LICENSE" target="_blank" rel="noreferrer">Read the full license on GitHub <?php echo $publicIcon('arrow-up-right'); ?></a></p>
                </section>
            </article>
        </div>
    </section>

    <section class="section public-docs-topics" aria-labelledby="docs-next-title">
        <div class="section-wrap public-docs-last">
            <div>
                <h2 id="docs-next-title">Ready to try it?</h2>
                <p class="section-lede">Follow a client job from start to finish, set it up on your phone, or compare the plans.</p>
            </div>
            <div class="public-doc-links">
                <a href="<?php echo e($publicHowItWorksUrl); ?>">How it works <?php echo $publicIcon('arrow-up-right'); ?></a>
                <a href="<?php echo e($publicMobileUrl); ?>">Mobile and Android <?php echo $publicIcon('arrow-up-right'); ?></a>
                <a href="<?php echo e($publicPricingUrl); ?>">Pricing <?php echo $publicIcon('arrow-up-right'); ?></a>
            </div>
        </div>
    </section>
</main>
<?php require __DIR__ . '/partials/public-site-footer.php'; ?>
<?php require __DIR__ . '/partials/public-site-scripts.php'; ?>
</body>
</html>
