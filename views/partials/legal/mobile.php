<?php
/**
 * Mobile-app layout for the legal pages (/mobile/?page=terms|privacy|refunds,
 * also what the Android app shows). Same wording as the public pages.
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

$legalBackUrl = Auth::check() ? '?page=settings' : '?page=login';
$legalBackLabel = Auth::check() ? 'Back to Settings' : 'Back to Sign In';
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
<meta charset="utf-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover"/>
<title><?= htmlspecialchars($legalDocs[$legalDoc] . ' - ' . $legalSiteName) ?></title>
<script src="https://cdn.tailwindcss.com?plugins=forms"></script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@100;200;300;400;500;600;700;800;900&display=swap" rel="stylesheet"/>
<script>tailwind.config = { darkMode: 'class', theme: { extend: { fontFamily: { display: ['Inter', 'sans-serif'] } } } }</script>
<style type="text/tailwindcss">@layer base { body { @apply bg-white text-black font-display antialiased; } }</style>
<style>
  .legal-mobile section { padding: 1.25rem 0; border-top: 1px solid #e5e5e5; scroll-margin-top: 1rem; }
  .legal-mobile h2 { font-size: 1.05rem; font-weight: 800; margin-bottom: .5rem; color: #000; }
  .legal-mobile h3 { font-size: .9rem; font-weight: 700; margin: .9rem 0 .35rem; color: #000; }
  .legal-mobile p, .legal-mobile li { font-size: .875rem; line-height: 1.6; color: #3f3f46; overflow-wrap: anywhere; }
  .legal-mobile p + p { margin-top: .6rem; }
  .legal-mobile ul { list-style: disc; padding-left: 1.2rem; margin: .5rem 0; display: grid; gap: .3rem; }
  .legal-mobile a { text-decoration: underline; color: #000; }
  .legal-mobile dl { display: grid; grid-template-columns: max-content 1fr; gap: .3rem .8rem; font-size: .85rem; margin-top: .5rem; }
  .legal-mobile dt { font-weight: 700; color: #000; }
  .legal-mobile dd { color: #3f3f46; overflow-wrap: anywhere; }
</style>
</head>
<body class="bg-gray-100 flex justify-center">
<div class="relative w-full max-w-[420px] min-h-screen bg-white flex flex-col">
  <main class="flex-1 px-6 pt-10 pb-12">
    <p class="text-[10px] font-bold uppercase tracking-widest text-gray-500 mb-2">Legal</p>
    <h1 class="text-2xl font-black leading-tight mb-2"><?= htmlspecialchars($legalDocs[$legalDoc]) ?></h1>
    <p class="text-xs text-gray-500 mb-4">Last updated <?= htmlspecialchars($legalUpdated) ?></p>
    <nav class="flex flex-wrap gap-2 mb-6" aria-label="Legal pages">
      <?php foreach ($legalDocs as $slug => $label): ?>
        <a href="?page=<?= $slug ?>" class="px-3 py-1.5 text-[10px] font-bold uppercase tracking-widest <?= $slug === $legalDoc ? 'bg-black text-white' : 'border border-black' ?>"<?= $slug === $legalDoc ? ' aria-current="page"' : '' ?>><?= htmlspecialchars($slug === 'refunds' ? 'Refunds' : ($slug === 'terms' ? 'Terms' : 'Privacy')) ?></a>
      <?php endforeach; ?>
    </nav>
    <?php if ($legalDetailsMissing): ?>
      <div class="border-2 border-black p-3 mb-4 text-xs" role="note">
        <p class="font-bold">Operator details not published yet</p>
        <p class="text-gray-700 mt-1">The person or business running this site has not filled in its name, address and contact email. Do not pay for anything here until those details appear on this page.</p>
      </div>
    <?php endif; ?>
    <?php if (!empty($legalSummary)): ?>
      <p class="text-sm text-gray-700 mb-2"><?= htmlspecialchars($legalSummary) ?></p>
    <?php endif; ?>
    <div class="legal-mobile">
      <?= $legalBody ?>
    </div>
    <div class="mt-10">
      <a href="<?= $legalBackUrl ?>" class="block w-full text-center bg-black text-white py-3 font-bold text-xs uppercase tracking-[0.3em] active:scale-95 transition-transform"><?= $legalBackLabel ?></a>
    </div>
  </main>
</div>
</body>
</html>
