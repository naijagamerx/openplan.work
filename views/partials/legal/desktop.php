<?php
/**
 * Desktop/public layout for the legal pages. The caller sets $legalDoc to
 * 'terms', 'privacy' or 'refunds'; the wording lives in the matching partial so
 * the mobile app shows exactly the same text.
 */

$legalDocs = [
    'terms' => 'Terms of Service',
    'privacy' => 'Privacy Policy',
    'refunds' => 'Refund and Cancellation Policy',
];
if (!isset($legalDocs[$legalDoc ?? ''])) {
    $legalDoc = 'terms';
}

require __DIR__ . '/operator.php';

ob_start();
require __DIR__ . '/' . $legalDoc . '.php';
$legalBody = ob_get_clean();

require_once __DIR__ . '/../public-site-context.php';
$pageTitle = $siteName . ' | ' . $legalDocs[$legalDoc];
$pageDescription = $legalSummary ?? $legalDocs[$legalDoc];
$canonicalUrl = $publicRouteUrl($legalDoc);
$publicHeaderCurrentPage = $legalDoc;
require __DIR__ . '/../public-site-head.php';
?>
<body class="marketing-page marketing-subpage">
<?php require __DIR__ . '/../public-site-header.php'; ?>
<main id="main">
    <section class="public-docs-hero public-legal-hero" id="top" aria-labelledby="legal-title">
        <div class="section-wrap">
            <p class="eyebrow">Legal</p>
            <h1 id="legal-title"><?php echo e($legalDocs[$legalDoc]); ?></h1>
            <p class="hero__lede"><?php echo e($legalSummary ?? ''); ?></p>
            <p class="public-legal-updated">Last updated <?php echo e($legalUpdated); ?></p>
            <nav class="public-docs-links" aria-label="Legal pages">
                <?php foreach ($legalDocs as $slug => $label): ?>
                    <a href="<?php echo e($publicRouteUrl($slug)); ?>"<?php echo $slug === $legalDoc ? ' aria-current="page"' : ''; ?>><?php echo e($label); ?></a>
                <?php endforeach; ?>
            </nav>
        </div>
    </section>

    <section class="section public-docs-body" aria-label="<?php echo e($legalDocs[$legalDoc]); ?>">
        <div class="section-wrap public-docs-layout">
            <aside class="public-docs-sidebar" aria-label="Sections">
                <p>ON THIS PAGE</p>
                <?php foreach (($legalNav ?? []) as $id => $label): ?>
                    <a href="#<?php echo e($id); ?>"><?php echo e($label); ?></a>
                <?php endforeach; ?>
            </aside>

            <article class="public-docs-content public-legal">
                <?php if ($legalDetailsMissing): ?>
                    <div class="public-docs-callout public-legal-missing" role="note">
                        <strong>Operator details not published yet</strong>
                        <span>The person or business running this site has not filled in its name, address and contact email. Do not pay for anything here until those details appear on this page.</span>
                    </div>
                <?php endif; ?>
                <?php echo $legalBody; ?>
            </article>
        </div>
    </section>
</main>
<?php require __DIR__ . '/../public-site-footer.php'; ?>
<?php require __DIR__ . '/../public-site-scripts.php'; ?>
</body>
</html>
