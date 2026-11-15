<?php
require_once __DIR__ . '/public-site-context.php';
$pageTitle = $pageTitle ?? ($siteName . ' | ' . ucfirst((string) ($page ?? 'homepage')));
$pageDescription = $pageDescription ?? 'Bring projects, clients, invoices, notes, and everyday plans together in one clear workspace.';
$canonicalUrl = $canonicalUrl ?? (($page ?? 'homepage') === 'homepage' ? $canonicalHomeUrl : $publicRouteUrl((string) $page));
$ogImage = $ogImage ?? ($publicBaseUrl . '/assets/images/app-dashboard-og.jpg');
$ogImageAlt = $ogImageAlt ?? 'OpenPlan Work dashboard with sample workspace data';
$homepageCssVersion = @filemtime(ROOT_PATH . '/assets/css/public-homepage.css') ?: 1;
$publicPagesCssVersion = @filemtime(ROOT_PATH . '/assets/css/public-pages.css') ?: 1;
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo e($pageTitle); ?></title>
    <meta name="description" content="<?php echo e($pageDescription); ?>">
    <meta name="author" content="<?php echo e($siteName); ?>">
    <meta name="robots" content="index, follow">
    <link rel="canonical" href="<?php echo e($canonicalUrl); ?>">

    <meta property="og:type" content="website">
    <meta property="og:url" content="<?php echo e($canonicalUrl); ?>">
    <meta property="og:title" content="<?php echo e($pageTitle); ?>">
    <meta property="og:description" content="<?php echo e($pageDescription); ?>">
    <meta property="og:site_name" content="<?php echo e($siteName); ?>">
    <meta property="og:image" content="<?php echo e($ogImage); ?>">
    <meta property="og:image:alt" content="<?php echo e($ogImageAlt); ?>">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?php echo e($pageTitle); ?>">
    <meta name="twitter:description" content="<?php echo e($pageDescription); ?>">
    <meta name="twitter:image" content="<?php echo e($ogImage); ?>">

    <link rel="icon" type="image/png" sizes="32x32" href="<?php echo e($publicBaseUrl); ?>/assets/favicons/favicon-32x32.png">
    <link rel="shortcut icon" href="<?php echo e($publicBaseUrl); ?>/assets/favicons/favicon.ico">
    <link rel="apple-touch-icon" sizes="180x180" href="<?php echo e($publicBaseUrl); ?>/assets/favicons/apple-touch-icon.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script>
        document.documentElement.classList.add('js');
        try {
            if (localStorage.getItem('theme') === 'dark') document.documentElement.classList.add('dark');
        } catch (error) {}
    </script>
    <link rel="stylesheet" href="<?php echo e($publicBaseUrl); ?>/assets/css/public-homepage.css?v=<?php echo (int) $homepageCssVersion; ?>">
    <link rel="stylesheet" href="<?php echo e($publicBaseUrl); ?>/assets/css/public-pages.css?v=<?php echo (int) $publicPagesCssVersion; ?>">
    <?php if (isset($structuredData) && is_array($structuredData)): ?>
        <script type="application/ld+json"><?php
            echo json_encode($structuredData, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES);
        ?></script>
    <?php endif; ?>
</head>
