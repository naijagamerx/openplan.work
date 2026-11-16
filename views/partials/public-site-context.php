<?php
$siteName = getPublicAppName();
$publicBaseUrl = rtrim((string) APP_URL, '/');
$canonicalHomeUrl = $publicBaseUrl . '/';
$publicRouteUrl = static function (string $slug) use ($publicBaseUrl): string {
    return $publicBaseUrl . '/?page=' . rawurlencode($slug);
};

$publicHomeUrl = $publicRouteUrl('homepage');
$publicPricingUrl = $publicRouteUrl('pricing');
$publicHowItWorksUrl = $publicRouteUrl('how-it-works');
$publicMobileUrl = $publicRouteUrl('mobile');
$publicDocsUrl = $publicRouteUrl('docs');
$publicBlogUrl = $publicRouteUrl('blog');
$repoUrl = 'https://github.com/naijagamerx/openplan.work';
// Self-hosting downloads come from the public repository. Release zips on this
// server are blocked by .htaccess, so linking to them would give visitors a 403.
$sourceDownloadUrl = $repoUrl . '/archive/refs/heads/main.zip';

// Heroicons (outline, v2) for links and buttons on the public pages, so no text
// glyph ever stands in for an icon. Decorative: the link text carries the meaning.
$publicIcon = static function (string $name): string {
    $paths = [
        'arrow-right' => 'M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3',
        'arrow-up-right' => 'm4.5 19.5 15-15m0 0H8.25m11.25 0v11.25',
        'arrow-down' => 'M19.5 13.5 12 21m0 0-7.5-7.5M12 21V3',
    ];
    $path = $paths[$name] ?? $paths['arrow-right'];
    return '<svg class="icon icon--inline" viewBox="0 0 24 24" fill="none" aria-hidden="true" focusable="false"><path d="' . $path . '" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>';
};

$isAuthenticated = Auth::check();
$registrationEnabled = isRegistrationEnabled();
if ($isAuthenticated) {
    $primaryActionUrl = $publicRouteUrl('dashboard');
    $primaryActionText = 'Go to dashboard';
} elseif ($registrationEnabled) {
    $primaryActionUrl = $publicRouteUrl('register');
    $primaryActionText = 'Create your workspace';
} else {
    $primaryActionUrl = $publicRouteUrl('login');
    $primaryActionText = 'Sign in to continue';
}

$plans = [];
try {
    require_once INCLUDES_PATH . '/PlansAPI.php';
    $plans = (new PlansAPI(new Database(APP_NAME), 'plans'))->findAll(['activeOnly' => true]);
} catch (Throwable $e) {
    $plans = [];
}

$appPath = rtrim((string) (parse_url(APP_URL, PHP_URL_PATH) ?? ''), '/');
$planActionUrl = static function (array $plan) use ($isAuthenticated, $registrationEnabled, $appPath, $publicBaseUrl): string {
    $planId = (string) ($plan['id'] ?? '');
    $buyQuery = '?page=buy-plan&id=' . rawurlencode($planId);
    if ($isAuthenticated) {
        return $publicBaseUrl . '/' . $buyQuery;
    }
    if ($registrationEnabled) {
        // New visitors sign up first. The plan is saved on the new account and
        // opened after the first sign-in (Auth::getPostLoginDestination).
        return $publicBaseUrl . '/?page=register&plan=' . rawurlencode($planId);
    }

    return $publicBaseUrl . '/?page=login&redirect=' . rawurlencode($appPath . '/' . $buyQuery);
};

// Free trial wording for visitors: only when it is switched on, sign-up is open
// and a platform key exists for at least one trial provider.
$publicTrialDays = 0;
$publicTrialRequests = 0;
if (!$isAuthenticated && $registrationEnabled) {
    try {
        require_once INCLUDES_PATH . '/SubscriptionHelper.php';
        $publicTrial = trialSettings();
        if ($publicTrial['enabled'] && array_filter($publicTrial['providers'], static fn($p) => getAdminSharedKey($p) !== null) !== []) {
            $publicTrialDays = (int) $publicTrial['days'];
            $publicTrialRequests = (int) $publicTrial['requests'];
        }
    } catch (Throwable $e) {
        $publicTrialDays = 0;
    }
}
$planPeriod = static function (int $days): string {
    $named = [7 => 'week', 30 => 'month', 365 => 'year'];
    return isset($named[$days]) ? '/ ' . $named[$days] : '/ ' . $days . ' days';
};
$providerNames = ['groq' => 'Groq', 'openrouter' => 'OpenRouter', 'gemini' => 'Gemini', 'ollama' => 'Ollama'];

$apkDownloadUrl = '';
$hasApkRelease = false;
$apkArtifactsDir = ROOT_PATH . '/release-artifacts/android';
if (is_dir($apkArtifactsDir)) {
    $apkCandidates = glob($apkArtifactsDir . '/*.apk');
    if (is_array($apkCandidates) && $apkCandidates !== []) {
        usort($apkCandidates, static fn(string $a, string $b): int => filemtime($b) <=> filemtime($a));
        $apkDownloadUrl = $publicBaseUrl . '/release-artifacts/android/' . rawurlencode(basename($apkCandidates[0]));
        $hasApkRelease = true;
    }
}
