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
$planActionUrl = static function (array $plan) use ($isAuthenticated, $appPath, $publicBaseUrl): string {
    $buyQuery = '?page=buy-plan&id=' . rawurlencode((string) ($plan['id'] ?? ''));
    if ($isAuthenticated) {
        return $publicBaseUrl . '/' . $buyQuery;
    }

    return $publicBaseUrl . '/?page=login&redirect=' . rawurlencode($appPath . '/' . $buyQuery);
};
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
