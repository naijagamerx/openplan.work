<?php
/**
 * The plan a visitor picked on the pricing page, carried into sign-up as ?plan=ID.
 * Sets $chosenPlanId and $chosenPlanName ('' when none or not on sale) and
 * $chosenPlanNote, a one-line message for the top of the register form.
 * Shared by views/register.php and mobile/views/register.php.
 */
$chosenPlanId = '';
$chosenPlanName = '';
$chosenPlanNote = '';
$requestedPlan = (string)($_POST['plan'] ?? $_GET['plan'] ?? '');
if ($requestedPlan !== '' && strlen($requestedPlan) <= 64 && preg_match('/^[A-Za-z0-9_-]+$/', $requestedPlan)) {
    try {
        require_once INCLUDES_PATH . '/PlansAPI.php';
        foreach ((new PlansAPI(new Database(APP_NAME), 'plans'))->findAll(['activeOnly' => true]) as $candidatePlan) {
            if (($candidatePlan['id'] ?? '') === $requestedPlan) {
                $chosenPlanId = $requestedPlan;
                $chosenPlanName = (string)($candidatePlan['name'] ?? 'this plan');
                break;
            }
        }
    } catch (Throwable $e) {
        $chosenPlanId = '';
    }
}
// "Already have an account?" keeps the plan: sign in, then open its checkout.
$chosenPlanLoginUrl = '?page=login';
if ($chosenPlanId !== '') {
    $chosenPlanAppPath = rtrim((string)(parse_url(APP_URL, PHP_URL_PATH) ?? ''), '/');
    $chosenPlanLoginUrl = '?page=login&redirect=' . rawurlencode($chosenPlanAppPath . '/?page=buy-plan&id=' . rawurlencode($chosenPlanId));
}
if ($chosenPlanId !== '') {
    require_once INCLUDES_PATH . '/SubscriptionHelper.php';
    $trialForPlan = trialSettings();
    $chosenPlanNote = $trialForPlan['enabled']
        ? 'You picked ' . $chosenPlanName . '. Create your account, then try it free for ' . $trialForPlan['days'] . ' days. No card needed.'
        : 'You picked ' . $chosenPlanName . '. Create your account, and we will take you straight to it.';
}
