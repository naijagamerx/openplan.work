<?php
/**
 * Refund and Cancellation Policy wording (also the delivery policy PayFast asks
 * for). Shared by views/refunds.php and mobile/views/refunds.php. Needs the
 * variables from operator.php. DRAFT: the operator approves it before relying
 * on it. The 7-day first-payment refund and the annual pro-rata refund are the
 * operator's choices, chosen to sit at or above what ECTA s44 and CPA s14 give.
 */

$legalSummary = 'How to cancel a plan, when you get your money back, and how refunds are paid.';
$legalNav = [
    'short' => 'In short',
    'cancel' => 'Cancelling',
    'first' => '7-day refund',
    'annual' => 'Annual plans',
    'renewals' => 'Renewals and mistakes',
    'manual' => 'Bank and crypto',
    'how' => 'How refunds work',
    'delivery' => 'Delivery',
    'law' => 'Your rights',
];
?>
<section class="public-docs-section legal-section" id="short" aria-labelledby="refunds-short">
    <h2 id="refunds-short">In short</h2>
    <ul>
        <li>Cancel a card subscription any time under My Subscription. No more charges; you keep access until the end of the period you paid for.</li>
        <li>Changed your mind? Ask within 7 days of your first payment for a plan and we refund it in full.</li>
        <li>Annual plans: cancel early and you can ask for a refund of the unused full months.</li>
        <li>Charged by mistake, twice, or after you cancelled? We refund it in full.</li>
        <li>Refunds go back the way you paid, within 30 days of your request.</li>
    </ul>
</section>

<section class="public-docs-section legal-section" id="cancel" aria-labelledby="refunds-cancel">
    <h2 id="refunds-cancel">Cancelling a card subscription</h2>
    <p>Go to <strong>My Subscription</strong> and choose <strong>Cancel auto-renew</strong>. We tell PayFast to stop the subscription straight away, so no further payments are taken, and PayFast emails you to confirm. Your plan stays active until the end of the period you have already paid for, then ends. Your workspace and data stay, and AI features keep working with your own keys.</p>
    <p>An account cannot be deleted while its card subscription is still renewing, so that PayFast never charges a card for an account that no longer exists. Cancel first, then delete the account.</p>
    <p>There is no minimum term and no cancellation fee.</p>
</section>

<section class="public-docs-section legal-section" id="first" aria-labelledby="refunds-first">
    <h2 id="refunds-first">7-day refund on your first payment</h2>
    <p>If you ask within 7 days of your first payment for a plan, we refund that payment in full, with no questions asked, and the plan ends when the refund is made. This applies once per customer, to the first payment for a plan, whether you paid by card, bank transfer or crypto. It does not apply to renewals.</p>
</section>

<section class="public-docs-section legal-section" id="annual" aria-labelledby="refunds-annual">
    <h2 id="refunds-annual">Annual plans</h2>
    <p>You can cancel an annual plan at any time. After the first 7 days you choose what happens next:</p>
    <ul>
        <li><strong>keep using it</strong> until the year you paid for ends, with no refund; or</li>
        <li><strong>ask for a refund of the unused full months.</strong> Your plan then ends at the end of the current month. We do not charge a cancellation fee.</li>
    </ul>
</section>

<section class="public-docs-section legal-section" id="renewals" aria-labelledby="refunds-renewals">
    <h2 id="refunds-renewals">Renewals and mistakes</h2>
    <p>Monthly renewals are not refunded. Cancel before your renewal date if you do not want the next month; My Subscription shows the date. We refund in full any payment that was:</p>
    <ul>
        <li>taken after you cancelled;</li>
        <li>taken twice for the same period; or</li>
        <li>for a different amount than the price you agreed to.</li>
    </ul>
    <p>If we cannot provide a paid feature for an extended time, or we withdraw it, we refund the unused part of your period.</p>
</section>

<section class="public-docs-section legal-section" id="manual" aria-labelledby="refunds-manual">
    <h2 id="refunds-manual">Bank transfer and crypto payments</h2>
    <p>These payments are once-off and never renew, so there is nothing to cancel. The same refund rules apply. If we decline your proof of payment, for example because the money has not arrived, your plan does not start; when a payment did arrive, we either start the plan or return the money.</p>
    <p>We refund bank payments to the account you paid from. We refund crypto payments to a wallet address you give us, for the amount we received in the same currency.</p>
</section>

<section class="public-docs-section legal-section" id="how" aria-labelledby="refunds-how">
    <h2 id="refunds-how">How to ask, and how refunds are paid</h2>
    <p>Email <?= $legalEmailLink() ?> from your account's email address and tell us which payment it is about. We reply within 5 business days.</p>
    <ul>
        <li><strong>Card payments</strong> are refunded through PayFast to the same card you paid with. Banks usually show the refund within 7 to 10 business days.</li>
        <li><strong>Every refund is made within 30 days</strong> of the day you asked for it.</li>
        <li>Please contact us before disputing a card payment with your bank. It is usually quicker for you.</li>
    </ul>
</section>

<section class="public-docs-section legal-section" id="delivery" aria-labelledby="refunds-delivery">
    <h2 id="refunds-delivery">Delivery</h2>
    <p>Plans are delivered online. Nothing is shipped. A card plan starts as soon as PayFast confirms your payment, normally within minutes. A bank or crypto plan starts when we have checked your proof of payment, normally within 2 business days. If we cannot start a plan within 30 days of your payment, we refund it in full.</p>
    <p>Card prices are charged in South African rand (ZAR). The checkout shows the exact amount, and that amount includes any tax we are required to charge.</p>
</section>

<section class="public-docs-section legal-section" id="law" aria-labelledby="refunds-law">
    <h2 id="refunds-law">Your rights under the law</h2>
    <p>If you buy as a consumer, the Electronic Communications and Transactions Act (section 44) lets you cancel an online purchase of services within 7 days of the agreement, without reason or penalty, with a full refund within 30 days. The Consumer Protection Act also gives you rights for fixed-term agreements, such as annual plans. The rules above are meant to give you at least those rights, and nothing in this policy takes away any right the law gives you.</p>
    <p>Read this policy together with our <a href="<?= e($legalRouteUrl('terms')) ?>">Terms of Service</a>.</p>
</section>
