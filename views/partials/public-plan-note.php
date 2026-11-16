<?php
/**
 * One line under the public plan cards for visitors who are not signed in.
 * Uses $publicTrialDays, $publicTrialRequests, $registrationEnabled and
 * $publicRouteUrl from public-site-context.php.
 */
$planNoteLoginUrl = $publicRouteUrl('login');
?>
<?php if ($publicTrialDays > 0): ?>
    <p class="pricing-note">Start free: create your account and try the AI on our keys for <?php echo (int) $publicTrialDays; ?> days (<?php echo (int) $publicTrialRequests; ?> requests, no card). Pick a plan whenever you are ready. Already have an account? <a href="<?php echo e($planNoteLoginUrl); ?>">Log in</a>.</p>
<?php elseif ($registrationEnabled): ?>
    <p class="pricing-note">Choosing a plan creates your free account first, then takes you straight to checkout. Already have an account? <a href="<?php echo e($planNoteLoginUrl); ?>">Log in</a>.</p>
<?php else: ?>
    <p class="pricing-note">Choosing a plan asks you to log in first.</p>
<?php endif; ?>
