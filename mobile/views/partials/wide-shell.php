<?php
/**
 * Wide-screen shell for the mobile app (unfolded foldables, tablets).
 *
 * At >= 600px wide (and >= 480px tall, so landscape phones are untouched) this:
 *  - adds a permanent icon rail on the left that expands to labels (pushes content),
 *  - widens the 420px phone column to 760px and re-aligns its fixed bars,
 *  - hides the bottom nav (the rail replaces it).
 * Below the breakpoint nothing changes. Included once per page; skipped for guests
 * and for pages rendered inside an iframe (?embedded=1).
 */

if (defined('OP_WIDE_SHELL') || !Auth::check() || !empty($_GET['embedded'])) {
    return;
}
define('OP_WIDE_SHELL', true);

$opRailNav = require __DIR__ . '/nav-items.php';
$opRailByPage = [];
foreach ($opRailNav['menu'] as $opRailItem) {
    $opRailByPage[$opRailItem['page']] = $opRailItem;
}
$opRailMain = [];
foreach (['dashboard', 'tasks', 'projects', 'clients', 'notes', 'habits', 'calendar', 'invoices', 'finance', 'ai-assistant'] as $opRailPage) {
    if (isset($opRailByPage[$opRailPage])) {
        $opRailMain[] = $opRailByPage[$opRailPage];
    }
}
$opRailSettings = $opRailByPage['settings'] ?? null;
$opRailAppsIcon = $opRailByPage['app']['heroicon'] ?? '';

// Sub-pages highlight their section in the rail
$opRailAliases = [
    'task-form' => 'tasks', 'view-task' => 'tasks',
    'project-form' => 'projects', 'view-project' => 'projects',
    'client-form' => 'clients', 'view-client' => 'clients',
    'view-notes' => 'notes', 'note-form' => 'notes',
    'habit-form' => 'habits',
    'invoice-form' => 'invoices', 'invoice-view' => 'invoices',
    'transaction-form' => 'finance',
];
$opRailCurrent = $_GET['page'] ?? 'dashboard';
$opRailCurrent = $opRailAliases[$opRailCurrent] ?? $opRailCurrent;
?>
<style>
#op-rail { display: none; }
@media screen and (min-width: 600px) and (min-height: 480px) {
    :root { --rail-w: 72px; }
    html.rail-expanded { --rail-w: 240px; }
    body { padding-left: var(--rail-w) !important; transition: padding-left .2s ease; }

    /* Phone column -> wider column, centred in the space right of the rail */
    body > div[class*="max-w-[420px]"] { max-width: 760px !important; }
    body > div[class*="max-w-[420px]"] main.max-w-md,
    body > div[class*="max-w-[420px]"] main .max-w-md { max-width: none !important; }

    /* Fixed bars that were centred on the 420px column */
    .fixed[class*="max-w-[420px]"][class*="mx-auto"] { left: calc(var(--rail-w) + .5rem) !important; }
    .fixed[class*="max-w-[420px]"][class*="-translate-x-1/2"] {
        left: var(--rail-w) !important; right: 0 !important; margin-inline: auto !important;
        width: auto !important; max-width: 760px !important; transform: none !important;
    }
    .fixed[class*="bottom-[84px]"] { bottom: 0 !important; padding-bottom: max(1.5rem, env(safe-area-inset-bottom)) !important; }
    [data-bottom-nav] { display: none !important; }
    /* FABs hard-coded to the 420px column's right edge: keep the same 30px inset on the wide column */
    [data-shell-fab] { right: calc((100vw - var(--rail-w) - min(760px, 100vw - var(--rail-w))) / 2 + 30px) !important; }
    #offcanvas-panel { left: auto !important; width: min(420px, 100%); }

    #op-rail {
        position: fixed; top: 0; bottom: 0; left: 0; z-index: 45;
        width: var(--rail-w); display: flex; flex-direction: column; gap: 2px;
        padding: max(.5rem, env(safe-area-inset-top)) .75rem max(.75rem, env(safe-area-inset-bottom));
        background: #fff; color: #18181b; border-right: 1px solid #e5e7eb;
        overflow-x: hidden; overflow-y: auto; transition: width .2s ease;
    }
    html.dark #op-rail { background: #09090b; color: #f4f4f5; border-color: #27272a; }
    .op-rail__item {
        display: flex; align-items: center; gap: .875rem; width: 100%; min-height: 48px;
        padding: 0 .75rem; border: 0; border-radius: .75rem; background: none; color: inherit;
        font-family: inherit; font-size: .9375rem; font-weight: 600; line-height: 1.2;
        text-align: left; text-decoration: none; white-space: nowrap; cursor: pointer;
    }
    .op-rail__item svg { width: 24px; height: 24px; flex: none; }
    .op-rail__item:hover { background: #f4f4f5; }
    html.dark .op-rail__item:hover { background: #18181b; }
    .op-rail__item[aria-current="page"] { background: #000; color: #fff; }
    html.dark .op-rail__item[aria-current="page"] { background: #fff; color: #000; }
    .op-rail__item:focus-visible { outline: 2px solid currentColor; outline-offset: 2px; }
    .op-rail__label { display: none; overflow: hidden; text-overflow: ellipsis; }
    html.rail-expanded .op-rail__label { display: inline; }
    .op-rail__toggle { margin-bottom: .5rem; }
    .op-rail__spacer { flex: 1 0 .5rem; }
}
@media (prefers-reduced-motion: reduce) {
    body, #op-rail { transition: none !important; }
}
</style>
<script>
(function () {
    try {
        if (localStorage.getItem('op-rail-expanded') === '1') {
            document.documentElement.classList.add('rail-expanded');
        }
    } catch (e) {}
})();
</script>
<nav id="op-rail" aria-label="Main">
    <button type="button" id="op-rail-toggle" class="op-rail__item op-rail__toggle" aria-expanded="false" aria-label="Expand menu" title="Expand menu" onclick="opRailToggle()">
        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
        </svg>
        <span class="op-rail__label"><?= htmlspecialchars(getSiteName()) ?></span>
    </button>
    <?php foreach ($opRailMain as $opRailItem): ?>
    <a href="?page=<?= urlencode($opRailItem['page']) ?>" class="op-rail__item" aria-label="<?= htmlspecialchars($opRailItem['label']) ?>" title="<?= htmlspecialchars($opRailItem['label']) ?>"<?= $opRailCurrent === $opRailItem['page'] ? ' aria-current="page"' : '' ?>>
        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="<?= $opRailItem['heroicon'] ?>"/>
        </svg>
        <span class="op-rail__label"><?= htmlspecialchars($opRailItem['label']) ?></span>
    </a>
    <?php endforeach; ?>
    <div class="op-rail__spacer"></div>
    <button type="button" class="op-rail__item" aria-label="All pages" title="All pages" onclick="opRailAllPages()">
        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="<?= $opRailAppsIcon ?>"/>
        </svg>
        <span class="op-rail__label">All pages</span>
    </button>
    <?php if ($opRailSettings): ?>
    <a href="?page=settings" class="op-rail__item" aria-label="Settings" title="Settings"<?= $opRailCurrent === 'settings' ? ' aria-current="page"' : '' ?>>
        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="<?= $opRailSettings['heroicon'] ?>"/>
        </svg>
        <span class="op-rail__label">Settings</span>
    </a>
    <?php endif; ?>
</nav>
<script>
function opRailSync() {
    var expanded = document.documentElement.classList.contains('rail-expanded');
    var btn = document.getElementById('op-rail-toggle');
    if (!btn) return;
    btn.setAttribute('aria-expanded', expanded ? 'true' : 'false');
    btn.setAttribute('aria-label', expanded ? 'Collapse menu' : 'Expand menu');
    btn.title = expanded ? 'Collapse menu' : 'Expand menu';
}
function opRailToggle() {
    var expanded = document.documentElement.classList.toggle('rail-expanded');
    try { localStorage.setItem('op-rail-expanded', expanded ? '1' : '0'); } catch (e) {}
    opRailSync();
}
function opRailAllPages() {
    if (document.getElementById('offcanvas-menu') && window.Mobile && Mobile.ui && Mobile.ui.toggleMenu) {
        Mobile.ui.toggleMenu();
    } else {
        window.location.href = '?page=app';
    }
}
opRailSync();
</script>
