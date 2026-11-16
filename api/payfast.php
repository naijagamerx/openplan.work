<?php
/**
 * PayFast card checkout (South Africa, ZAR) for signed-in users.
 *
 * GET  ?action=status                          -> {enabled, sandbox}
 * POST ?action=checkout  {planId, acceptTerms: true, returnTo?}   -> {action, fields}: the browser posts
 *                                                 these signed fields to PayFast
 * POST ?action=cancel                          -> stops the card subscription at PayFast;
 *                                                 the plan stays active until it expires
 *
 * Payment notifications from PayFast go to api/payfast-itn.php (no session there).
 */

require_once __DIR__ . '/../config.php';
require_once INCLUDES_PATH . '/PayFast.php';
require_once INCLUDES_PATH . '/PaymentsAPI.php';
require_once INCLUDES_PATH . '/PlansAPI.php';

if (!Auth::check()) {
    errorResponse('Unauthorized', 401);
}

$action = $_GET['action'] ?? '';
$config = PayFast::config();
$userId = Auth::userId() ?? '';

if (requestMethod() === 'GET' && $action === 'status') {
    successResponse(['enabled' => PayFast::isConfigured($config), 'sandbox' => $config['sandbox']]);
}

if (requestMethod() !== 'POST') {
    errorResponse('Method not allowed', 405);
}

$body = getJsonBody();
$csrf = $body['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!Auth::isTokenAuth() && !Auth::validateCsrf((string)$csrf)) {
    errorResponse('Invalid CSRF token', 403);
}
if ($userId === '') {
    errorResponse('Unauthorized', 401);
}
if (!PayFast::isConfigured($config)) {
    errorResponse('Card payments are not available yet.', 503, 'PAYFAST_DISABLED');
}

$payments = new PaymentsAPI();

try {
    switch ($action) {
        case 'checkout':
            $planId = trim((string)($body['planId'] ?? ''));
            $plan = $planId !== '' ? (new PlansAPI(new Database(APP_NAME), 'plans'))->find($planId) : null;
            if (!$plan || empty($plan['active'])) {
                errorResponse('Plan unavailable', 404, 'NOT_FOUND');
            }
            $amount = PayFast::zarAmountForPlan($plan);
            if ($amount === null) {
                errorResponse('This plan cannot be paid by card yet.', 400, 'NO_CARD_PRICE');
            }
            $frequency = PayFast::frequencyFor((int)($plan['durationDays'] ?? 30));
            if ($frequency !== null && $payments->findActiveCardSubscription($userId) !== null) {
                errorResponse('You already have a card subscription that renews automatically. Cancel it on My Subscription before starting another.', 409, 'ALREADY_SUBSCRIBED');
            }

            // PayFast requires the buyer to accept the refund policy on the checkout
            // screen; keep a record of which version they accepted.
            if (($body['acceptTerms'] ?? false) !== true) {
                errorResponse('Please accept the Terms and the Refund and Cancellation Policy first.', 400, 'TERMS_NOT_ACCEPTED');
            }
            $termsAccepted = ['version' => legalPolicyVersion(), 'at' => date('c')];

            $record = $payments->createCardCheckout($userId, $plan, $amount, $frequency, $termsAccepted);

            $base = $config['siteUrl'];
            $appBase = (($body['returnTo'] ?? '') === 'mobile') ? $base . '/mobile' : $base;
            $amountText = number_format($amount, 2, '.', '');
            $planName = trim((string)($plan['name'] ?? 'Plan'));
            $fields = [
                'merchant_id' => $config['merchantId'],
                'merchant_key' => $config['merchantKey'],
                'return_url' => $appBase . '/?page=my-subscription&payfast=return',
                'cancel_url' => $appBase . '/?page=buy-plan&id=' . rawurlencode($planId) . '&payfast=cancelled',
                'notify_url' => $base . '/api/payfast-itn.php',
                'm_payment_id' => $record['id'],
                'amount' => $amountText,
                'item_name' => mb_substr(APP_DISPLAY_NAME . ' ' . $planName, 0, 100),
                'item_description' => mb_substr($frequency !== null ? 'Renews automatically. Cancel any time in My Subscription.' : (int)($plan['durationDays'] ?? 30) . ' days of access.', 0, 255),
            ];
            $user = Auth::user() ?? [];
            $email = trim((string)($user['email'] ?? ''));
            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $fields['email_address'] = $email;
            }
            if ($frequency !== null) {
                // PayFast reads billing_date in South African time; a UTC date can already be "yesterday" there.
                $fields['subscription_type'] = '1';
                $fields['billing_date'] = (new DateTime('now', new DateTimeZone('Africa/Johannesburg')))->format('Y-m-d');
                $fields['recurring_amount'] = $amountText;
                $fields['frequency'] = (string)$frequency[0];
                $fields['cycles'] = '0';
            }

            successResponse([
                'action' => PayFast::processUrl($config['sandbox']),
                'fields' => PayFast::signedCheckoutFields($fields, $config['passphrase']),
                'sandbox' => $config['sandbox'],
            ]);
            break;

        case 'cancel':
            $sub = $payments->findActiveCardSubscription($userId);
            if (!$sub) {
                errorResponse('There is no card subscription to cancel.', 404, 'NOT_FOUND');
            }
            $result = PayFast::cancelSubscription((string)$sub['gatewayToken'], $config);
            if (!$result['ok']) {
                error_log('PayFast cancel failed for subscription ' . ($sub['id'] ?? '') . ': HTTP ' . $result['status'] . ' ' . $result['message']);
                errorResponse('PayFast could not cancel the subscription right now. Please try again shortly, or contact support.', 502, 'PAYFAST_ERROR');
            }
            $updated = $payments->cancelAutoRenew((string)$sub['id'], (string)$sub['gatewayToken']);
            successResponse(
                ['expiresAt' => $updated['expiresAt'] ?? $sub['expiresAt'] ?? null],
                'Auto-renew is off. Your plan stays active until it expires.'
            );
            break;

        default:
            errorResponse('Invalid action', 400);
    }
} catch (Throwable $e) {
    error_log('api/payfast.php ' . $action . ' failed: ' . $e->getMessage());
    errorResponse('Card payment request failed. Please try again.', 500, 'SERVER');
}
