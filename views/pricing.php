<?php
require_once __DIR__ . '/partials/public-site-context.php';
require_once INCLUDES_PATH . '/PayFast.php';
$pageTitle = $siteName . ' | Pricing';
$pageDescription = 'The workspace is free, hosted or self-hosted. Plans add AI requests on our keys so you need no AI account of your own. Free trial, card billing in rand, cancel any time.';
$canonicalUrl = $publicPricingUrl;
$ogImageAlt = 'OpenPlan dashboard showing the product workspace with sample records';
$publicHeaderCurrentPage = 'pricing';

// Does the free trial use a provider that can run agent actions? (Groq is chat only.)
$trialHasAgent = false;
if ($publicTrialDays > 0 && isset($publicTrial['providers'])) {
    $trialHasAgent = array_intersect(['openrouter', 'gemini'], (array) $publicTrial['providers']) !== [];
}

$cardPaymentsOn = false;
try {
    $cardPaymentsOn = PayFast::isConfigured();
} catch (Throwable $e) {
    $cardPaymentsOn = false;
}

// Questions are written once and used twice: on the page and in the FAQ markup for search engines.
$pricingFaq = [];
$pricingFaq[] = ['q' => 'Is the workspace really free?', 'a' => [
    'Yes. Projects and tasks, clients, invoices, finance, inventory, notes, the knowledge base, calendar, habits, Pomodoro and the water tracker are free with no time limit. So are the mobile web app, the Android app and the MCP connection for your own AI apps.',
    'The AI assistant is free too when you add your own API key from an AI provider in Settings. A plan only pays for AI on our keys.',
]];
$pricingFaq[] = ['q' => 'What does a plan add?', 'a' => [
    'A plan lets the AI assistant, Notes AI and the AI helpers on tasks and inventory use our AI keys, up to the number of requests a month shown on the plan. You do not need an account with an AI provider or a key of your own.',
    'Each plan card says whether it includes the agent, which carries out actions such as creating tasks, invoices and notes. The agent needs OpenRouter or Gemini models; fast Groq models chat, write and summarise but do not carry out actions.',
]];
$pricingFaq[] = ['q' => 'What counts as one AI request?', 'a' => [
    'Each message you send to the AI on our keys counts once, however much work the agent does to answer it. Anything you run with your own API key never counts.',
    'My Subscription shows how many requests you have used this month.',
]];
$pricingFaq[] = ['q' => 'What happens when I reach the monthly limit?', 'a' => [
    'The AI on our keys pauses until the next month starts, or until you move to a bigger plan. Your workspace keeps working as normal, and you can switch the assistant to your own API key at any time.',
]];
if ($publicTrialDays > 0) {
    $pricingFaq[] = ['q' => 'How does the free trial work?', 'a' => [
        'Create your account and verify your email address, then press Start free trial (in the AI assistant, at checkout or on My Subscription). You get ' . $publicTrialDays . ' days and ' . $publicTrialRequests . ' AI requests on our keys.',
        'There is one trial per email address. It needs no card, ends by itself and never charges you. Buy a plan during the trial and the plan replaces it straight away.',
        $trialHasAgent
            ? 'The trial includes the agent, so you can try it carrying out actions in your workspace.'
            : 'The trial uses fast Groq models: chat, writing help, Notes AI and summaries. Agent actions need a plan with OpenRouter or Gemini models, or your own key for one of them.',
    ]];
}
$payAnswer = [];
if ($cardPaymentsOn) {
    $payAnswer[] = 'By card through PayFast. Card payments are charged in South African rand (ZAR) at the rand price shown at checkout, and you type your card details on PayFast\'s page, so we never see them. A card plan renews every period until you cancel.';
    $payAnswer[] = 'Checkout may also offer bank transfer or crypto: you pay, upload proof, and the plan starts when we confirm it. Those payments do not renew.';
} else {
    $payAnswer[] = 'Checkout lists the payment methods available, such as bank transfer or crypto. You pay, upload proof of payment, and the plan starts when we confirm it. These payments do not renew by themselves.';
}
$pricingFaq[] = ['q' => 'How do I pay?', 'a' => $payAnswer];
$pricingFaq[] = ['q' => 'Can I cancel?', 'a' => [
    $cardPaymentsOn
        ? 'Yes, any time. Open My Subscription and cancel; the card is not charged again and you keep the plan until the end of the period you paid for. Plans paid by bank transfer or crypto simply end when their period ends.'
        : 'Plans paid by bank transfer or crypto simply end when their period ends, so there is nothing to cancel.',
]];
$pricingFaq[] = ['q' => 'Do you give refunds?', 'a' => [
    'If you ask within 7 days of your first payment for a plan, you get a full refund. The <a href="' . e($publicRouteUrl('refunds')) . '">Refund and Cancellation Policy</a> has the details.',
]];
$pricingFaq[] = ['q' => 'Can you read my data?', 'a' => [
    'Your workspace is stored encrypted (AES-256-GCM) with a key made from your master password, and that password is not stored with your data. Your sign-in details (name, email and a hash of your login password) are kept unencrypted so you can sign in.',
    'While you are signed in, your master password is held in your server session, on our server, until you sign out or the session expires, so the app can open your data, and when you use the AI, the text you send goes to the AI provider to be answered. An MCP token or a linked Alexa account also carries a locked copy of your key so it can work without you. So this protects your data where it is stored; it is not end-to-end encryption.',
    'Nobody can reset your master password. If you lose it, the data it protects cannot be recovered, so write it down somewhere safe.',
]];
$pricingFaq[] = ['q' => 'Can I host it myself instead?', 'a' => [
    'Yes, for free. ' . e($siteName) . ' is open source under the AGPL-3.0. Download it, run it on any PHP 8 server and use your own AI keys; no plan is needed. The <a href="' . e($publicDocsUrl) . '#self-host">self-hosting guide</a> walks through it.',
]];

$offers = [['@type' => 'Offer', 'name' => 'Free workspace', 'price' => '0', 'priceCurrency' => 'USD']];
foreach ($plans as $plan) {
    $offers[] = [
        '@type' => 'Offer',
        'name' => (string) ($plan['name'] ?? 'Plan'),
        'price' => number_format((float) ($plan['price'] ?? 0), 2, '.', ''),
        'priceCurrency' => (string) ($plan['currency'] ?? 'USD'),
        'url' => $planActionUrl($plan),
    ];
}
$structuredData = [
    '@context' => 'https://schema.org',
    '@graph' => [
        [
            '@type' => 'SoftwareApplication',
            'name' => $siteName,
            'applicationCategory' => 'BusinessApplication',
            'operatingSystem' => 'Web, Android',
            'url' => $canonicalHomeUrl,
            'offers' => $offers,
        ],
        [
            '@type' => 'FAQPage',
            'mainEntity' => array_map(static fn(array $item): array => [
                '@type' => 'Question',
                'name' => $item['q'],
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => trim(strip_tags(implode(' ', $item['a'])))],
            ], $pricingFaq),
        ],
    ],
];
require __DIR__ . '/partials/public-site-head.php';
?>
<body class="marketing-page marketing-subpage">
<?php require __DIR__ . '/partials/public-site-header.php'; ?>
<main id="main">
    <section class="public-page-hero public-page-hero--center section-wrap" id="top" aria-labelledby="pricing-title">
        <div class="public-page-hero__copy">
            <h1 id="pricing-title">Free workspace.<br><span>Pay only for AI on our keys.</span></h1>
            <p class="hero__lede">Everything you need to run the work is free, here or on your own server. A plan lets the AI assistant use our keys, so you don&rsquo;t need an AI account of your own.<?php if ($publicTrialDays > 0): ?> Try it free for <?php echo (int) $publicTrialDays; ?> days first.<?php endif; ?></p>
            <div class="op-cta-row">
                <a class="button button--dark" href="<?php echo e($primaryActionUrl); ?>"><?php echo e($primaryActionText); ?> <?php echo $publicIcon('arrow-right'); ?></a>
                <a class="text-link" href="#plans">Compare plans <?php echo $publicIcon('arrow-down'); ?></a>
            </div>
        </div>
    </section>

    <section class="section section--pricing public-price-section" id="plans" aria-labelledby="plans-title">
        <div class="section-wrap">
            <div class="pricing-heading">
                <h2 id="plans-title">Plans</h2>
                <p class="section-lede">Every plan includes the whole free workspace. What changes is how many AI requests a month you can make on our keys, and which AI models you get.</p>
            </div>
            <div class="pricing-grid">
                <?php require __DIR__ . '/partials/public-pricing-cards.php'; ?>
            </div>
            <?php if ($plans === []): ?>
                <p class="pricing-note">No AI plans are on sale right now. The workspace is free, and you can use the assistant with your own AI provider key.</p>
            <?php elseif (!$isAuthenticated): ?>
                <?php require __DIR__ . '/partials/public-plan-note.php'; ?>
            <?php endif; ?>
        </div>
    </section>

    <section class="op-section op-section--paper" id="included" aria-labelledby="included-title">
        <div class="section-wrap public-split">
            <figure class="public-screen public-screen--wide">
                <img src="<?php echo e($publicBaseUrl); ?>/assets/images/app-invoices.webp" width="1400" height="1064" alt="OpenPlan invoice list with example clients, invoice statuses, and sample amounts." loading="lazy" decoding="async">
                <figcaption>Invoices screen &middot; sample workspace data</figcaption>
            </figure>
            <div class="public-copy">
                <h2 id="included-title">In every workspace, free.</h2>
                <p class="section-lede">No feature of the workspace is held back for paying customers.</p>
                <ul class="public-check-list">
                    <li>Projects with tasks, subtasks, priorities, due dates and time tracking</li>
                    <li>Clients, invoices and advanced invoices, with PDF download</li>
                    <li>Income and expenses, and stock in Inventory</li>
                    <li>Notes with checklists and tags, and a knowledge base of Markdown documents</li>
                    <li>Calendar, habits, Pomodoro focus timer and water tracker</li>
                    <li>Mobile web app and the Android app</li>
                    <li>MCP connection for Claude, Cursor and other AI apps</li>
                    <li>The AI assistant with your own API key</li>
                    <li>Encrypted storage, export and backup</li>
                </ul>
            </div>
        </div>
    </section>

    <section class="op-section op-section--soft" id="ai-options" aria-labelledby="ai-options-title">
        <div class="section-wrap">
            <div class="op-section__head">
                <h2 id="ai-options-title">Three ways to use the AI</h2>
                <p>The assistant, the agent and Notes AI work the same way whichever you choose. Only the key that pays for them changes.</p>
            </div>
            <dl class="op-facts">
                <div><dt>Your own key, free</dt><dd>Get an API key from an AI provider and paste it in Settings &rarr; AI API Keys. The provider bills you directly for what you use, and none of it counts against a plan.</dd></div>
                <div><dt>A plan, on our keys</dt><dd>No AI account needed. Requests run on our keys up to the plan&rsquo;s monthly limit, with the models the plan includes.<?php if ($publicTrialDays > 0): ?> The free trial works the same way for <?php echo (int) $publicTrialDays; ?> days.<?php endif; ?></dd></div>
                <div><dt>Self-hosted</dt><dd>Run <?php echo e($siteName); ?> on your own server and add your own keys. Nothing to buy from us.</dd></div>
            </dl>
        </div>
    </section>

    <section class="op-section op-section--paper" id="faq" aria-labelledby="faq-title">
        <div class="section-wrap">
            <div class="op-section__head">
                <h2 id="faq-title">Questions about pricing</h2>
            </div>
            <div class="op-faq">
                <?php foreach ($pricingFaq as $faqItem): ?>
                    <details>
                        <summary><?php echo e($faqItem['q']); ?></summary>
                        <div class="op-faq__answer">
                            <?php foreach ($faqItem['a'] as $faqParagraph): ?>
                                <p><?php echo $faqParagraph; // Written above; the only HTML is our own links. ?></p>
                            <?php endforeach; ?>
                        </div>
                    </details>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <section class="section public-close" aria-labelledby="pricing-close-title">
        <div class="section-wrap closing-panel">
            <h2 id="pricing-close-title">Start with the work you already have.</h2>
            <p>Create a free workspace now and add a plan only if you want the AI on our keys.</p>
            <div class="closing-actions">
                <a class="button button--light" href="<?php echo e($primaryActionUrl); ?>"><?php echo e($primaryActionText); ?> <?php echo $publicIcon('arrow-right'); ?></a>
                <a class="text-link text-link--light" href="<?php echo e($publicHowItWorksUrl); ?>">See how it works <?php echo $publicIcon('arrow-up-right'); ?></a>
            </div>
        </div>
    </section>
</main>
<?php require __DIR__ . '/partials/public-site-footer.php'; ?>
<?php require __DIR__ . '/partials/public-site-scripts.php'; ?>
</body>
</html>
