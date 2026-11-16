<article class="plan-card">
    <h3 class="plan-card__name">Free workspace</h3>
    <p class="plan-card__price"><span><?php echo e(getCurrencySymbol('USD')); ?>0</span> <small>forever</small></p>
    <ul class="plan-card__list">
        <li>Projects, clients, invoices, notes and habits</li>
        <li>Encrypted with your own master password</li>
        <li>Use the AI assistant with your own provider keys</li>
    </ul>
    <a class="button button--outline plan-card__action" href="<?php echo e($primaryActionUrl); ?>"><?php echo e($primaryActionText); ?></a>
</article>
<?php foreach ($plans as $plan): ?>
    <?php
    $cap = (int) ($plan['monthlyRequestCap'] ?? 0);
    $providers = array_map(static fn($provider) => $providerNames[$provider] ?? ucfirst((string) $provider), (array) ($plan['providers'] ?? []));
    ?>
    <article class="plan-card plan-card--paid">
        <h3 class="plan-card__name"><?php echo e($plan['name'] ?? 'Plan'); ?></h3>
        <p class="plan-card__price">
            <span><?php echo e(getCurrencySymbol((string) ($plan['currency'] ?? 'USD')) . number_format((float) ($plan['price'] ?? 0), 2)); ?></span>
            <small><?php echo e($planPeriod((int) ($plan['durationDays'] ?? 30))); ?></small>
        </p>
        <?php if (!empty($plan['description'])): ?>
            <p class="plan-card__description"><?php echo nl2br(e((string) $plan['description'])); ?></p>
        <?php endif; ?>
        <ul class="plan-card__list">
            <li>Everything in the free workspace</li>
            <li><?php echo $cap > 0 ? e(number_format($cap)) . ' AI requests a month' : 'Unlimited AI requests'; ?></li>
            <?php if ($providers !== []): ?>
                <li>AI by <?php echo e(implode(', ', $providers)); ?></li>
                <?php // Groq is chat only in the agent (AIAgent::isChatOnlyProvider), so say which plans can act. ?>
                <?php if (array_intersect(['openrouter', 'gemini'], (array) ($plan['providers'] ?? [])) !== []): ?>
                    <li>The agent: creates tasks, invoices, notes and more for you</li>
                <?php else: ?>
                    <li>Chat, writing help and Notes AI (no agent actions)</li>
                <?php endif; ?>
            <?php endif; ?>
        </ul>
        <?php if ($publicTrialDays > 0): ?>
            <a class="button button--dark plan-card__action" href="<?php echo e($planActionUrl($plan)); ?>">Start free trial</a>
            <p class="plan-card__note"><?php echo (int) $publicTrialDays; ?> days &middot; <?php echo (int) $publicTrialRequests; ?> AI requests &middot; no card</p>
        <?php else: ?>
            <a class="button button--dark plan-card__action" href="<?php echo e($planActionUrl($plan)); ?>">Choose <?php echo e($plan['name'] ?? 'plan'); ?></a>
        <?php endif; ?>
    </article>
<?php endforeach; ?>
