<?php
/**
 * Terms of Service wording. Shared by views/terms.php and mobile/views/terms.php.
 * Needs the variables from operator.php. DRAFT: the operator approves the wording
 * (ideally with a lawyer) before relying on it.
 */

$legalSummary = 'The agreement between you and ' . $legalOperatorName . ' for using ' . $legalSiteName . ' at ' . $legalSiteHost . '.';
$legalNav = [
    'who' => 'Who we are',
    'service' => 'The service',
    'account' => 'Your account',
    'use' => 'Acceptable use',
    'ai' => 'AI features',
    'plans' => 'Plans and payment',
    'data' => 'Your data',
    'availability' => 'Availability',
    'ending' => 'Closing an account',
    'liability' => 'Liability',
    'law' => 'Changes and law',
    'contact' => 'Contact',
];
$notSet = '<em>not published yet</em>';
?>
<section class="public-docs-section legal-section" id="who" aria-labelledby="terms-who">
    <h2 id="terms-who">Who we are</h2>
    <p><?= e($legalSiteName) ?> at <?= e($legalSiteHost) ?> is run by <?= e($legalOperatorName) ?> ("we", "us"). These Terms are an agreement between you and us. By creating an account or paying for a plan you accept them. If you do not accept them, do not use the service.</p>
    <dl>
        <dt>Name</dt><dd><?= $legalOperator['name'] !== '' ? e($legalOperator['name']) : $notSet ?></dd>
        <?php if ($legalOperator['status'] !== ''): ?><dt>Legal status</dt><dd><?= e($legalOperator['status']) ?></dd><?php endif; ?>
        <?php if ($legalOperator['registration'] !== ''): ?><dt>Registration number</dt><dd><?= e($legalOperator['registration']) ?></dd><?php endif; ?>
        <?php if ($legalOperator['registeredIn'] !== ''): ?><dt>Place of registration</dt><dd><?= e($legalOperator['registeredIn']) ?></dd><?php endif; ?>
        <?php if ($legalOperator['officeBearers'] !== ''): ?><dt>Directors</dt><dd><?= e($legalOperator['officeBearers']) ?></dd><?php endif; ?>
        <dt>Physical address</dt><dd><?= $legalOperator['address'] !== '' ? e($legalOperator['address']) . '<br><small>Legal documents may be served at this address.</small>' : $notSet ?></dd>
        <?php if ($legalOperator['phone'] !== ''): ?><dt>Phone</dt><dd><?= e($legalOperator['phone']) ?></dd><?php endif; ?>
        <dt>Email</dt><dd><?= $legalOperator['email'] !== '' ? $legalEmailLink() : $notSet ?></dd>
        <dt>Website</dt><dd><a href="<?= e($legalSiteUrl) ?>/"><?= e($legalSiteUrl) ?></a></dd>
        <?php if ($legalOperator['country'] !== ''): ?><dt>Country</dt><dd><?= e($legalOperator['country']) ?></dd><?php endif; ?>
        <dt>Memberships</dt><dd>We do not belong to any self-regulatory or accreditation body, code of conduct, or alternative dispute resolution scheme.</dd>
    </dl>
    <p>You can save or print these Terms from your browser at any time. The version you accepted is recorded with each payment.</p>
</section>

<section class="public-docs-section legal-section" id="service" aria-labelledby="terms-service">
    <h2 id="terms-service">The service</h2>
    <p><?= e($legalSiteName) ?> is an online workspace for tasks, projects, clients, notes, invoices, finance, inventory and AI-assisted work, used in a web browser, in our mobile web app, in our Android app, and through AI tools you connect yourself.</p>
    <p>The software behind it is open source under the GNU Affero General Public License v3.0. You may run your own copy under that license. These Terms cover only the hosted service at <?= e($legalSiteHost) ?>. If you run your own copy, you are the operator of that copy and these Terms do not apply to it.</p>
</section>

<section class="public-docs-section legal-section" id="account" aria-labelledby="terms-account">
    <h2 id="terms-account">Your account and master password</h2>
    <ul>
        <li>You must be at least 18, or have your parent's or guardian's consent, and give a real email address.</li>
        <li>Keep your sign-in password, master password and any API or device tokens secret. Anyone holding them can use your account. Revoke a token you have lost.</li>
        <li>Your workspace is stored encrypted with a key made from your master password. <strong>We cannot reset or recover a lost master password, and without it your data cannot be decrypted.</strong> Keep it somewhere safe and export your data regularly.</li>
        <li>You are responsible for what happens under your account.</li>
    </ul>
</section>

<section class="public-docs-section legal-section" id="use" aria-labelledby="terms-use">
    <h2 id="terms-use">Acceptable use</h2>
    <p>Do not use the service to:</p>
    <ul>
        <li>break the law, or store or send content that is unlawful, infringes someone else's rights, or harasses people;</li>
        <li>send spam, for example through the invoice or email features;</li>
        <li>upload malware, probe or attack the service, or try to reach other people's accounts or data;</li>
        <li>share your account, or resell or automate access to the AI requests included in a plan beyond normal personal or business use.</li>
    </ul>
    <p>We may limit, suspend or close an account that breaks these rules. Where it is safe and lawful to do so, we will tell you why first.</p>
</section>

<section class="public-docs-section legal-section" id="ai" aria-labelledby="terms-ai">
    <h2 id="terms-ai">AI features</h2>
    <ul>
        <li>When you use an AI feature, the text you send and the workspace content the feature needs are sent to the AI provider that answers it. The <a href="<?= e($legalRouteUrl('privacy')) ?>">Privacy Policy</a> lists those providers.</li>
        <li>AI answers can be wrong or incomplete. Check them before you rely on them, especially for invoices, money, legal or health matters.</li>
        <li>You can use your own provider keys at no charge from us. A paid plan lets you use ours instead, within that plan's monthly limit and model list.</li>
        <li>We do not use your workspace content to train AI models.</li>
    </ul>
</section>

<section class="public-docs-section legal-section" id="plans" aria-labelledby="terms-plans">
    <h2 id="terms-plans">Free workspace, paid plans and payment</h2>
    <ul>
        <li>A workspace with every module is free. Paid plans add AI requests on our provider keys.</li>
        <li>Each plan shows its price, currency, period, monthly AI request limit and AI providers before you pay. One request is one message you send to an AI feature, even if the assistant takes several steps to answer. Unused requests do not carry over to the next calendar month.</li>
        <li><strong>Card payments</strong> are processed by PayFast in South African rand. A card subscription renews automatically at the end of each period, at the same price, until you cancel. You can cancel any time under My Subscription; you keep access until the end of the period you have paid for.</li>
        <li><strong>Bank transfer or crypto payments</strong> are once-off. Your plan starts when we have checked your proof of payment, normally within 2 business days, and does not renew by itself.</li>
        <li><strong>When your plan starts:</strong> card plans start as soon as PayFast confirms the payment, normally within minutes. There is no minimum term beyond the period you pay for.</li>
        <li><strong>Before you pay,</strong> the checkout shows the plan, its price and how it renews. You can go back, or cancel on PayFast's page, without being charged.</li>
        <li><strong>Your records:</strong> My Subscription shows your plan history, we email you each time a payment is applied, and PayFast emails a receipt for card payments.</li>
        <li>The price shown at checkout is the full price you pay, including any tax we are required to charge. If we change a plan's price, the new price applies only from your next renewal, and we will email you before that renewal.</li>
        <li>When a plan ends, your workspace stays and AI features keep working with your own keys.</li>
        <li>Cancelling and refunds are covered by the <a href="<?= e($legalRouteUrl('refunds')) ?>">Refund and Cancellation Policy</a>, which forms part of these Terms.</li>
    </ul>
</section>

<section class="public-docs-section legal-section" id="data" aria-labelledby="terms-data">
    <h2 id="terms-data">Your data</h2>
    <p>Your content stays yours. You give us only the permission we need to store it, process it and show it back to you so the service works. You can export your workspace at any time (Export Data in the account menu) and delete your account in Settings. How we handle personal information is explained in the <a href="<?= e($legalRouteUrl('privacy')) ?>">Privacy Policy</a>.</p>
</section>

<section class="public-docs-section legal-section" id="availability" aria-labelledby="terms-availability">
    <h2 id="terms-availability">Availability and changes to the service</h2>
    <p>We work to keep the service running and your data safe, but we cannot promise it will always be available or free of errors. We may change, add or remove features. If we remove something a paid plan depends on, we will offer you a pro-rata refund for the unused part of your period.</p>
    <p>Keep your own exports. We back up the service, but backups are for restoring the whole service, not for recovering items you have deleted.</p>
</section>

<section class="public-docs-section legal-section" id="ending" aria-labelledby="terms-ending">
    <h2 id="terms-ending">Suspension and closing your account</h2>
    <p>You can close your account at any time in Settings. This deletes your workspace and account straight away; see the Privacy Policy for the few records we must keep. If you have a card subscription that is still renewing, cancel it under My Subscription first; until then the account cannot be deleted.</p>
    <p>We may suspend or close an account that seriously or repeatedly breaks these Terms, or if the law requires it. If we close the service as a whole, we will give at least 30 days' notice by email so you can export your data, and refund the unused part of any prepaid period.</p>
</section>

<section class="public-docs-section legal-section" id="liability" aria-labelledby="terms-liability">
    <h2 id="terms-liability">Liability</h2>
    <p>As far as the law allows, we are not liable for indirect or consequential loss, or for loss of data you could have restored from your own export, and our total liability for any claim is limited to the amount you paid us in the 12 months before the claim.</p>
    <p>Nothing in these Terms limits liability for fraud or gross negligence, or takes away any right you have under the Consumer Protection Act, the Electronic Communications and Transactions Act or any other law that cannot be excluded by agreement.</p>
</section>

<section class="public-docs-section legal-section" id="law" aria-labelledby="terms-law">
    <h2 id="terms-law">Changes to these Terms, law and disputes</h2>
    <p>We may update these Terms. The date at the top shows the latest version. For changes that matter to you, we will email you at least 14 days before they apply; if you do not agree, you can cancel and close your account before then.</p>
    <p>These Terms are governed by the law of <?= e($legalCountry) ?>. If something goes wrong, please contact us first so we can try to fix it. That does not stop you from going to a court, ombud or regulator that can hear your complaint.</p>
</section>

<section class="public-docs-section legal-section" id="contact" aria-labelledby="terms-contact">
    <h2 id="terms-contact">Contact</h2>
    <p>Questions about these Terms: <?= $legalEmailLink() ?>.</p>
</section>
