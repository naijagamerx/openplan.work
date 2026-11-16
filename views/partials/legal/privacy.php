<?php
/**
 * Privacy Policy wording (POPIA notice). Shared by views/privacy.php and
 * mobile/views/privacy.php. Needs the variables from operator.php. DRAFT: the
 * operator approves the wording before relying on it, and updates it whenever
 * the app starts sending data somewhere new.
 */

$legalSummary = 'What personal information ' . $legalSiteName . ' collects, why, who receives it, how long it is kept, and your rights.';
$legalNav = [
    'responsible' => 'Who is responsible',
    'collect' => 'What we collect',
    'use' => 'Why we use it',
    'share' => 'Who receives it',
    'abroad' => 'Outside South Africa',
    'security' => 'How we protect it',
    'keep' => 'How long we keep it',
    'rights' => 'Your rights',
    'cookies' => 'Cookies',
    'changes' => 'Changes',
];
?>
<section class="public-docs-section legal-section" id="responsible" aria-labelledby="privacy-responsible">
    <h2 id="privacy-responsible">Who is responsible</h2>
    <p><?= e($legalOperatorName) ?> runs <?= e($legalSiteName) ?> at <?= e($legalSiteHost) ?> and is responsible for the personal information described here (the "responsible party" under the Protection of Personal Information Act, POPIA).</p>
    <dl>
        <dt>Information Officer</dt><dd><?= $legalOperator['informationOfficer'] !== '' ? e($legalOperator['informationOfficer']) : '<em>not published yet</em>' ?></dd>
        <dt>Email</dt><dd><?= $legalOperator['email'] !== '' ? $legalEmailLink() : '<em>not published yet</em>' ?></dd>
        <?php if ($legalOperator['address'] !== ''): ?><dt>Address</dt><dd><?= e($legalOperator['address']) ?></dd><?php endif; ?>
        <dt>PAIA manual</dt><dd><?= $legalOperator['paiaManualUrl'] !== '' ? '<a href="' . e($legalOperator['paiaManualUrl']) . '" target="_blank" rel="noopener">Read our PAIA manual</a>' : 'Available on request from the Information Officer' ?></dd>
    </dl>
    <p>Information about other people that you store in your workspace, such as your clients' contact details, belongs to your records. You decide what to keep and why; we store and process it only to provide the service to you.</p>
</section>

<section class="public-docs-section legal-section" id="collect" aria-labelledby="privacy-collect">
    <h2 id="privacy-collect">What we collect</h2>
    <h3>Your account</h3>
    <p>Your name, email address, role, and the dates you signed up, last signed in and verified your email. Your sign-in password is stored only as a one-way hash.</p>
    <h3>Your workspace</h3>
    <p>Everything you put in: tasks, projects, notes, clients, invoices, finance and inventory records, habits, files you upload, and AI chat history. It is stored encrypted with a key made from your master password.</p>
    <h3>Payments</h3>
    <p>The plan, amount, currency, payment method and dates. For card payments, PayFast's payment reference and subscription token. <strong>We never see or store your card number</strong>: you enter it on PayFast's page. For bank transfer or crypto payments, the proof-of-payment file you upload.</p>
    <h3>Security and usage</h3>
    <ul>
        <li>An activity log of sign-ins and changes, with your IP address and browser details.</li>
        <li>A session cookie, and a fingerprint of your browser and IP address that protects your session from being taken over.</li>
        <li>Server and error logs kept by us and our hosting provider.</li>
        <li>How many AI requests you make each month, per provider, so plan limits work. Not what you asked.</li>
    </ul>
    <h3>Connections you turn on</h3>
    <p>When you create a token for the mobile app or an AI tool (MCP), we store a hash of the token, not the token itself, together with a copy of your master password that only that token can unlock, so the connected app can reach your workspace. Revoking a token stops it working straight away. If you link Amazon Alexa, Amazon processes your voice requests and sends us the linked request.</p>
    <h3>Messages</h3>
    <p>Emails you send us.</p>
    <h3>What you must give us</h3>
    <p>A name, an email address and a password are needed to create an account; without them we cannot open one. Payment details are needed only if you buy a plan. Everything else, including what you put in your workspace, is up to you.</p>
</section>

<section class="public-docs-section legal-section" id="use" aria-labelledby="privacy-use">
    <h2 id="privacy-use">Why we use it</h2>
    <ul>
        <li><strong>To provide the service you signed up for:</strong> your account, workspace, AI features, sync with the mobile apps, and service emails (verification, password reset, payment confirmations, important changes).</li>
        <li><strong>To take payments and keep accounting records,</strong> as the law requires.</li>
        <li><strong>To keep the service and your account secure,</strong> detect abuse and fix errors.</li>
        <li><strong>Marketing email only if you agree to it.</strong> Every such email has an unsubscribe link.</li>
    </ul>
    <p>We do not sell personal information, show ads, use advertising or tracking cookies, or use your workspace content to train AI models.</p>
</section>

<section class="public-docs-section legal-section" id="share" aria-labelledby="privacy-share">
    <h2 id="privacy-share">Who receives it</h2>
    <p>Only the service providers that make each feature work, and only what that feature needs:</p>
    <ul>
        <li><strong>Our hosting provider</strong> stores the server, the encrypted data and backups.</li>
        <li><strong>Our email provider</strong> delivers the emails the service sends.</li>
        <li><strong>PayFast</strong> processes card payments and receives what you enter on its payment page, plus the email address and plan we pass to it.</li>
        <li><strong>AI providers</strong>, when you use an AI feature: Groq, OpenRouter (which passes the request to the model maker you or your plan selects, such as Meta, Google, Mistral, OpenAI or Anthropic) and Google Gemini. They receive your prompt and the workspace content the feature needs. Ollama runs on a server you choose yourself.</li>
        <li><strong>Amazon</strong>, only if you link Alexa.</li>
        <li><strong>AI tools you connect yourself</strong> through an MCP token receive what they request on your behalf.</li>
        <li><strong>Font and script networks</strong> (Google Fonts, Tailwind CDN, jsDelivr, cdnjs): our pages load files from them, so your browser sends them your IP address.</li>
        <li><strong>Authorities</strong>, only when the law requires us to.</li>
    </ul>
</section>

<section class="public-docs-section legal-section" id="abroad" aria-labelledby="privacy-abroad">
    <h2 id="privacy-abroad">Information sent outside South Africa</h2>
    <p>Several of these providers, including the AI providers, the card networks behind PayFast, and the font and script networks, process information in other countries, mainly the United States. Those countries may not have data protection laws that POPIA treats as equivalent to South Africa's.</p>
    <p>We send information abroad only when it is needed to provide the feature you are using (POPIA section 72(1)(c)), and we use providers that publish their own data protection commitments. You can keep your workspace content away from outside AI providers by not using the AI features, or by using your own Ollama server.</p>
</section>

<section class="public-docs-section legal-section" id="security" aria-labelledby="privacy-security">
    <h2 id="privacy-security">How we protect it</h2>
    <ul>
        <li>Your workspace is encrypted at rest (AES-256-GCM) with a key made from your master password, and connections use HTTPS.</li>
        <li><strong>This is not end-to-end encryption.</strong> While you are signed in, your master password is held in your session on our server so the app can decrypt and work with your data. It leaves the session when you sign out or the session ends.</li>
        <li>Passwords are stored as one-way hashes, and payment proofs and data files cannot be opened from the web.</li>
        <li>If a security breach affects your personal information, we will tell you and the Information Regulator as POPIA requires.</li>
    </ul>
</section>

<section class="public-docs-section legal-section" id="keep" aria-labelledby="privacy-keep">
    <h2 id="privacy-keep">How long we keep it</h2>
    <ul>
        <li><strong>Account and workspace:</strong> until you delete your account in Settings. Deletion removes them from the live service straight away; copies in server backups disappear as those backups are replaced.</li>
        <li><strong>Payment records and proofs of payment:</strong> at least five years, because the Tax Administration Act (section 29) requires us to keep them, and longer if they are part of a tax audit or dispute. They are kept even if you delete your account.</li>
        <li><strong>Activity log:</strong> the most recent 10,000 events.</li>
        <li><strong>Emails you send us:</strong> as long as needed to deal with them.</li>
    </ul>
</section>

<section class="public-docs-section legal-section" id="rights" aria-labelledby="privacy-rights">
    <h2 id="privacy-rights">Your rights</h2>
    <p>Under POPIA you can:</p>
    <ul>
        <li>ask, free of charge, whether we hold personal information about you, and for a copy of it and a list of who has received it;</li>
        <li>ask us to correct or delete information that is wrong, out of date or that we are no longer allowed to keep;</li>
        <li>object to how we use it, and object at any time to direct marketing;</li>
        <li>complain to the Information Regulator, or go to court.</li>
    </ul>
    <p>Much of this you can do yourself: edit your profile, export your workspace (Export Data in the account menu) or delete your account in Settings. For anything else, email <?= $legalEmailLink() ?>. We will reply within 30 days. We do not make decisions about you based only on automated processing.</p>
    <h3>Information Regulator (South Africa)</h3>
    <dl>
        <dt>Complaints</dt><dd><a href="mailto:POPIAComplaints@inforegulator.org.za">POPIAComplaints@inforegulator.org.za</a>, or the eServices portal on the Regulator's website</dd>
        <dt>Enquiries</dt><dd><a href="mailto:enquiries@inforegulator.org.za">enquiries@inforegulator.org.za</a> · 010 023 5200 · toll-free 0800 017 160</dd>
        <dt>Address</dt><dd>Woodmead North Office Park, 54 Maxwell Drive, Woodmead, Johannesburg, 2191</dd>
        <dt>Website</dt><dd><a href="https://inforegulator.org.za/" target="_blank" rel="noopener">inforegulator.org.za</a></dd>
    </dl>
</section>

<section class="public-docs-section legal-section" id="cookies" aria-labelledby="privacy-cookies">
    <h2 id="privacy-cookies">Cookies and browser storage</h2>
    <p>We use one essential cookie to keep you signed in; with "Remember me" it lasts up to 30 days. The app also keeps small settings in your browser's local storage, such as your theme and notification preferences. We use no advertising or analytics cookies.</p>
</section>

<section class="public-docs-section legal-section" id="changes" aria-labelledby="privacy-changes">
    <h2 id="privacy-changes">Children and changes to this policy</h2>
    <p>The service is not meant for children under 18 without a parent's or guardian's consent.</p>
    <p>We will update this policy when what we collect or who receives it changes. The date at the top shows the latest version, and we will email you about important changes before they apply.</p>
</section>
