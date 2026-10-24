<?php
/**
 * Mobile Admin Plans — list + create/edit/delete subscription plans.
 */

if (!Auth::check()) { header('Location: ?page=login'); exit; }
if (!Auth::isAdmin()) {
    http_response_code(403);
    echo '<div style="padding:24px;font-family:system-ui">Admin access required.</div>';
    exit;
}

require_once INCLUDES_PATH . '/PlansAPI.php';
$plansApi = new PlansAPI(new Database(APP_NAME), 'plans');
$plans = $plansApi->findAll();

$siteName = getSiteName() ?? 'LazyMan';
?>
<!DOCTYPE html>
<html class="light overflow-x-hidden" lang="en">
<head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0, viewport-fit=cover" name="viewport"/>
<title>Admin Plans - <?= htmlspecialchars($siteName) ?></title>
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@100;200;300;400;500;600;700;800;900&display=swap" rel="stylesheet"/>
<script>tailwind.config = { darkMode: 'class', theme: { extend: { fontFamily: { display: ['Inter', 'sans-serif'] } } } }</script>
<style type="text/tailwindcss">@layer base { body { @apply bg-white text-black font-display antialiased; } } .touch-target { min-height: 44px; }</style>
<script>const CSRF_TOKEN = '<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>'; const APP_URL = '<?= APP_URL ?? '' ?>';</script>
<script src="<?= MOBILE_JS_URL ?>/mobile.js?v=1.0.1"></script>
</head>
<body class="bg-gray-100 flex justify-center">
<div class="relative w-full max-w-[420px] min-h-screen bg-white dark:bg-zinc-950 shadow-2xl flex flex-col overflow-hidden">

<?php
$title = 'Plans';
$leftAction = 'back';
$rightAction = 'add';
$rightTarget = "openPlanForm()";
include MOBILE_VIEW_PATH . '/partials/header-mobile.php';
?>

<main class="flex-1 overflow-y-auto max-w-md w-full mx-auto px-4 pt-6 pb-40 space-y-3 text-zinc-900 dark:text-zinc-100">

  <section id="plan-form-card" class="hidden border-2 border-black p-3">
    <form id="plan-form" class="space-y-3">
      <input type="hidden" name="id" value="">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Auth::csrfToken()) ?>">

      <div class="flex items-center justify-between">
        <h3 id="plan-form-title" class="text-sm font-bold uppercase tracking-widest">New Plan</h3>
        <button type="button" onclick="document.getElementById('plan-form-card').classList.add('hidden')" class="text-[10px] font-bold text-gray-500 uppercase tracking-widest">Cancel</button>
      </div>

      <div>
        <label class="block text-[10px] font-bold uppercase tracking-widest text-gray-500 mb-1">Name</label>
        <input type="text" name="name" required class="w-full px-3 py-2 border border-black text-sm" placeholder="Pro Monthly">
      </div>

      <div class="grid grid-cols-2 gap-2">
        <div>
          <label class="block text-[10px] font-bold uppercase tracking-widest text-gray-500 mb-1">Price</label>
          <input type="number" name="price" min="0" step="0.01" required class="w-full px-3 py-2 border border-black text-sm" placeholder="9.99">
        </div>
        <div>
          <label class="block text-[10px] font-bold uppercase tracking-widest text-gray-500 mb-1">Currency</label>
          <select name="currency" class="w-full px-3 py-2 border border-black text-sm">
            <option value="USD">USD</option><option value="EUR">EUR</option><option value="GBP">GBP</option><option value="ZAR">ZAR</option>
          </select>
        </div>
        <div>
          <label class="block text-[10px] font-bold uppercase tracking-widest text-gray-500 mb-1">Days</label>
          <input type="number" name="durationDays" min="1" required class="w-full px-3 py-2 border border-black text-sm" placeholder="30">
        </div>
        <div>
          <label class="block text-[10px] font-bold uppercase tracking-widest text-gray-500 mb-1">Req cap (0=∞)</label>
          <input type="number" name="monthlyRequestCap" min="0" class="w-full px-3 py-2 border border-black text-sm" placeholder="200">
        </div>
      </div>

      <div>
        <label class="block text-[10px] font-bold uppercase tracking-widest text-gray-500 mb-1">Providers</label>
        <div class="grid grid-cols-2 gap-1">
          <?php foreach (['groq' => 'Groq', 'openrouter' => 'OpenRouter', 'gemini' => 'Gemini', 'ollama' => 'Ollama'] as $val => $label): ?>
            <label class="flex items-center gap-2 border border-black px-2 py-2 text-xs">
              <input type="checkbox" name="providers[]" value="<?= $val ?>" class="accent-black"><?= $label ?>
            </label>
          <?php endforeach; ?>
        </div>
      </div>

      <div>
        <label class="block text-[10px] font-bold uppercase tracking-widest text-gray-500 mb-1">Description</label>
        <textarea name="description" rows="3" class="w-full px-3 py-2 border border-black text-sm"></textarea>
      </div>

      <label class="flex items-center gap-2 text-xs">
        <input type="checkbox" name="active" value="1" checked class="accent-black">
        Active
      </label>

      <button type="submit" class="w-full bg-black text-white py-3 font-bold text-xs uppercase tracking-widest active:scale-95 transition-transform">Save</button>
    </form>
  </section>

  <?php if (empty($plans)): ?>
    <section class="border-2 border-black p-6 text-center"><p class="text-sm font-bold">No plans yet</p><p class="text-xs text-gray-500 mt-1">Tap + to create one.</p></section>
  <?php else: ?>
    <?php foreach ($plans as $p):
      $symbol = getCurrencySymbol($p['currency'] ?? 'USD');
    ?>
      <article class="border border-black p-3 space-y-2" data-plan-id="<?= htmlspecialchars($p['id']) ?>">
        <div class="flex items-start justify-between gap-3">
          <div class="flex-1 min-w-0">
            <p class="font-bold text-sm truncate"><?= htmlspecialchars($p['name']) ?></p>
            <p class="text-[10px] text-gray-500"><?= htmlspecialchars($symbol . number_format((float)$p['price'], 2)) ?> &middot; <?= (int)($p['durationDays'] ?? 30) ?> days</p>
          </div>
          <span class="<?= !empty($p['active']) ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-700' ?> px-2 py-1 text-[9px] font-bold uppercase tracking-widest"><?= !empty($p['active']) ? 'Active' : 'Inactive' ?></span>
        </div>
        <div class="text-[10px] text-gray-600 space-y-0.5">
          <p>Cap: <?= (int)($p['monthlyRequestCap'] ?? 0) === 0 ? 'unlimited' : number_format((int)$p['monthlyRequestCap']) . ' / mo' ?></p>
          <?php if (!empty($p['providers'])): ?><p>Providers: <?= htmlspecialchars(implode(', ', $p['providers'])) ?></p><?php endif; ?>
        </div>
        <div class="flex gap-2 pt-1">
          <button type="button" onclick='editPlan(<?= htmlspecialchars(json_encode($p, JSON_HEX_APOS | JSON_HEX_QUOT)) ?>)' class="flex-1 border border-black py-1.5 text-[10px] font-bold uppercase tracking-widest active:bg-gray-100">Edit</button>
          <button type="button" onclick='deletePlan("<?= htmlspecialchars($p['id']) ?>")' class="flex-1 border border-red-500 text-red-600 py-1.5 text-[10px] font-bold uppercase tracking-widest active:bg-red-50">Delete</button>
        </div>
      </article>
    <?php endforeach; ?>
  <?php endif; ?>
</main>

<?php include MOBILE_VIEW_PATH . '/partials/bottom-nav.php'; ?>

</div>

<script>
function openPlanForm() {
  const card = document.getElementById('plan-form-card');
  card.classList.remove('hidden');
  card.querySelector('[name=id]').value = '';
  card.querySelector('[name=name]').value = '';
  card.querySelector('[name=price]').value = '';
  card.querySelector('[name=durationDays]').value = '30';
  card.querySelector('[name=monthlyRequestCap]').value = '';
  card.querySelector('[name=description]').value = '';
  card.querySelectorAll('[name="providers[]"]').forEach(cb => cb.checked = false);
  card.querySelector('[name=active]').checked = true;
  document.getElementById('plan-form-title').textContent = 'New Plan';
  card.scrollIntoView({behavior:'smooth'});
}

function editPlan(plan) {
  const card = document.getElementById('plan-form-card');
  card.classList.remove('hidden');
  card.querySelector('[name=id]').value = plan.id || '';
  card.querySelector('[name=name]').value = plan.name || '';
  card.querySelector('[name=price]').value = plan.price || '';
  card.querySelector('[name=currency]').value = plan.currency || 'USD';
  card.querySelector('[name=durationDays]').value = plan.durationDays || 30;
  card.querySelector('[name=monthlyRequestCap]').value = plan.monthlyRequestCap || 0;
  card.querySelector('[name=description]').value = plan.description || '';
  card.querySelector('[name=active]').checked = !!plan.active;
  card.querySelectorAll('[name="providers[]"]').forEach(cb => { cb.checked = (plan.providers || []).includes(cb.value); });
  document.getElementById('plan-form-title').textContent = 'Edit Plan';
  card.scrollIntoView({behavior:'smooth'});
}

async function deletePlan(id) {
  if (!confirm('Delete this plan? Existing subscribers retain their snapshot.')) return;
  const r = await fetch((APP_URL ? APP_URL + '/' : '') + 'api/plans.php?id=' + encodeURIComponent(id), {
    method: 'DELETE', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF_TOKEN },
    body: JSON.stringify({ csrf_token: CSRF_TOKEN })
  });
  const res = await r.json();
  if (res && res.success) location.reload();
  else alert((res.error && res.error.message) || 'Failed');
}

document.getElementById('plan-form').addEventListener('submit', async (e) => {
  e.preventDefault();
  const fd = new FormData(e.target);
  const payload = {
    name: fd.get('name'),
    price: parseFloat(fd.get('price')),
    currency: fd.get('currency'),
    durationDays: parseInt(fd.get('durationDays'), 10),
    monthlyRequestCap: parseInt(fd.get('monthlyRequestCap') || 0, 10),
    description: fd.get('description'),
    providers: fd.getAll('providers[]'),
    active: fd.get('active') ? true : false,
    csrf_token: CSRF_TOKEN
  };
  const id = fd.get('id');
  const url = (APP_URL ? APP_URL + '/' : '') + 'api/plans.php' + (id ? '?id=' + encodeURIComponent(id) : '');
  const method = id ? 'PUT' : 'POST';
  const r = await fetch(url, { method, headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF_TOKEN }, body: JSON.stringify(payload) });
  const res = await r.json();
  if (res && res.success) location.reload();
  else alert((res.error && res.error.message) || 'Failed');
});
</script>

</body>
</html>
