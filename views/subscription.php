<?php
/**
 * Subscription — user-facing plan browser + current-plan widget.
 * Shows all active plans. Lets the user pick one → ?page=buy-plan&id=...
 */

require_once INCLUDES_PATH . '/PlansAPI.php';
require_once INCLUDES_PATH . '/SubscriptionHelper.php';

$plansApi = new PlansAPI(new Database(APP_NAME), 'plans');
$plans = $plansApi->findAll(['activeOnly' => true]);
$currentSub = getActiveSubscription(Auth::userId());
?>

<div>
    <div class="flex items-center gap-4 mb-8">
        <div>
            <h2 class="text-3xl font-bold text-gray-900">Subscription Plans</h2>
            <p class="text-gray-500 font-medium tracking-tight">Choose a plan to unlock admin-shared AI access.</p>
        </div>
    </div>

    <?php if ($currentSub): ?>
        <div class="mb-6 bg-black text-white rounded-2xl p-6 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <p class="text-[10px] font-bold uppercase tracking-widest text-white/60 mb-1">Active subscription</p>
                <p class="text-2xl font-bold"><?php echo e($currentSub['planSnapshot']['name'] ?? 'Active plan'); ?></p>
                <p class="text-sm text-white/70 mt-1">
                    Expires <?php echo e(date('M j, Y', strtotime((string)($currentSub['expiresAt'] ?? '')))); ?>
                    &middot; <?php echo max(0, (int)(strtotime((string)$currentSub['expiresAt']) - time()) / 86400); ?> days left
                </p>
            </div>
            <div class="flex gap-2">
                <a href="?page=my-subscription" class="px-4 py-2 bg-white text-black rounded-lg text-xs font-bold uppercase tracking-widest hover:bg-gray-100 transition">View details</a>
            </div>
        </div>
    <?php endif; ?>

    <?php if (empty($plans)): ?>
        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-12 text-center">
            <p class="text-gray-500 text-sm mb-2">No plans available yet.</p>
            <?php if (Auth::isAdmin()): ?>
                <p class="text-gray-400 text-xs">As admin, you can create plans at <a href="?page=admin-plans" class="underline">Admin → SaaS Plans</a>.</p>
            <?php else: ?>
                <p class="text-gray-400 text-xs">Check back later, or contact support to inquire about plans.</p>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php foreach ($plans as $plan):
                $price = (float)($plan['price'] ?? 0);
                $currency = $plan['currency'] ?? 'USD';
                $symbol = getCurrencySymbol($currency);
                $days = (int)($plan['durationDays'] ?? 30);
                $cap = (int)($plan['monthlyRequestCap'] ?? 0);
                $providers = $plan['providers'] ?? [];
            ?>
                <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-8 flex flex-col gap-5 hover:shadow-lg transition-all">
                    <div>
                        <h3 class="text-2xl font-bold text-gray-900"><?php echo e($plan['name'] ?? ''); ?></h3>
                        <?php if (!empty($plan['description'])): ?>
                            <p class="text-sm text-gray-500 mt-2"><?php echo nl2br(e($plan['description'])); ?></p>
                        <?php endif; ?>
                    </div>

                    <div class="border-t border-gray-100 pt-5">
                        <div class="flex items-baseline gap-1">
                            <span class="text-4xl font-black"><?php echo e($symbol . number_format($price, 2)); ?></span>
                            <span class="text-sm text-gray-500">/ <?php echo $days; ?> days</span>
                        </div>
                    </div>

                    <ul class="text-sm text-gray-600 space-y-2 flex-1">
                        <li class="flex items-start gap-2">
                            <svg class="w-4 h-4 text-black mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
                            <span>
                                <?php echo $cap > 0 ? number_format($cap) . ' AI requests / month' : 'Unlimited AI requests'; ?>
                            </span>
                        </li>
                        <?php if (!empty($providers)): ?>
                            <li class="flex items-start gap-2">
                                <svg class="w-4 h-4 text-black mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
                                <span>Providers: <?php echo e(implode(', ', $providers)); ?></span>
                            </li>
                        <?php endif; ?>
                        <li class="flex items-start gap-2">
                            <svg class="w-4 h-4 text-black mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
                            <span>Cancel anytime &middot; no auto-renew</span>
                        </li>
                    </ul>

                    <a href="?page=buy-plan&id=<?php echo e($plan['id']); ?>" class="w-full text-center px-6 py-3 bg-black text-white rounded-xl text-sm font-bold uppercase tracking-widest hover:bg-gray-800 transition inline-flex items-center justify-center gap-2">
                        <?php echo $currentSub ? 'Switch / Extend' : 'Get this Plan'; ?>
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"></path></svg>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="mt-8 bg-gray-50 rounded-2xl border border-gray-200 p-6 text-sm text-gray-600">
        <p class="font-bold text-gray-900 mb-2">How it works</p>
        <ol class="list-decimal pl-5 space-y-1">
            <li>Pick a plan above.</li>
            <li>Choose a payment method (bank transfer or crypto) and send payment manually.</li>
            <li>Upload payment proof (PNG / JPG / PDF).</li>
            <li>The admin reviews and approves within 24 hours.</li>
            <li>Toggle "Use admin shared AI keys" in Settings to consume the shared quota.</li>
        </ol>
    </div>
</div>
