<?php
/**
 * Mobile Alexa Voice Guide — shows every phrase that triggers each intent.
 * Hit /?page=alexa-voice-guide on mobile to see it.
 */

if (!Auth::check()) { header('Location: ?page=login'); exit; }

$siteName = getSiteName() ?? 'OpenPlan';
?>
<!DOCTYPE html>
<html class="light overflow-x-hidden" lang="en">
<head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0, viewport-fit=cover" name="viewport"/>
<title>Alexa Voice Guide - <?= htmlspecialchars($siteName) ?></title>
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@100;200;300;400;500;600;700;800;900&display=swap" rel="stylesheet"/>
<script>tailwind.config = { darkMode: 'class', theme: { extend: { fontFamily: { display: ['Inter', 'sans-serif'] } } } }</script>
<style type="text/tailwindcss">@layer base { body { @apply bg-white text-black font-display antialiased; } } .touch-target { min-height: 44px; }</style>
<script src="<?= MOBILE_JS_URL ?>/mobile.js?v=1.0.1"></script>
</head>
<body class="bg-gray-100 flex justify-center">
<div class="relative w-full max-w-[420px] min-h-screen bg-white dark:bg-zinc-950 shadow-2xl flex flex-col overflow-hidden">

<?php
$title = 'Alexa Voice Guide';
$leftAction = 'menu';
$rightAction = 'none';
include MOBILE_VIEW_PATH . '/partials/header-mobile.php';
?>

<main class="flex-1 overflow-y-auto max-w-md w-full mx-auto px-4 pt-6 pb-40 space-y-4 text-zinc-900 dark:text-zinc-100">

  <section>
    <h2 class="text-2xl font-black">Talking to Alexa</h2>
    <p class="text-xs text-gray-600 mt-1">Every phrase that works — read these to your Echo.</p>
  </section>

  <!-- Critical wake-word rules -->
  <section class="border-2 border-black p-3 space-y-2 bg-yellow-50">
    <p class="text-[10px] font-bold uppercase tracking-widest">Two patterns to remember</p>
    <div class="space-y-2 text-xs">
      <div>
        <p class="font-bold">Pattern 1: Open then chain</p>
        <p class="text-gray-600 mt-1">Say "<em class="font-bold">Alexa, open my plan</em>" once — then for ~8 seconds you can speak directly without prefix:</p>
        <ul class="ml-4 mt-1 space-y-0.5 text-[11px]">
          <li>"what are my tasks"</li>
          <li>"add task buy milk"</li>
          <li>"my habits"</li>
          <li>"stop"</li>
        </ul>
      </div>
      <div>
        <p class="font-bold">Pattern 2: Single-shot</p>
        <p class="text-gray-600 mt-1">Prefix every command with "<em class="font-bold">Alexa, ask my plan</em>...":</p>
        <ul class="ml-4 mt-1 space-y-0.5 text-[11px]">
          <li>"Alexa, ask my plan what are my tasks"</li>
          <li>"Alexa, ask my plan to add task buy milk"</li>
          <li>"Alexa, tell my plan I did meditation"</li>
        </ul>
      </div>
    </div>
  </section>

  <!-- TASKS -->
  <section class="border border-black p-3 space-y-2">
    <p class="text-[10px] font-bold uppercase tracking-widest text-gray-500">📋 Today's Tasks (read)</p>
    <ul class="space-y-1">
      <li class="bg-gray-50 p-1.5 text-[11px] font-mono">tasks</li>
      <li class="bg-gray-50 p-1.5 text-[11px] font-mono">my tasks</li>
      <li class="bg-gray-50 p-1.5 text-[11px] font-mono">what are my tasks</li>
      <li class="bg-gray-50 p-1.5 text-[11px] font-mono">what's on my plate</li>
      <li class="bg-gray-50 p-1.5 text-[11px] font-mono">today's tasks</li>
      <li class="bg-gray-50 p-1.5 text-[11px] font-mono">what is overdue</li>
      <li class="bg-gray-50 p-1.5 text-[11px] font-mono">what do I need to do</li>
    </ul>
  </section>

  <section class="border border-black p-3 space-y-2">
    <p class="text-[10px] font-bold uppercase tracking-widest text-gray-500">➕ Add Task (write)</p>
    <p class="text-[10px] text-gray-600">Task goes to Inbox project. Move later from web.</p>
    <ul class="space-y-1">
      <li class="bg-gray-50 p-1.5 text-[11px] font-mono">add task buy groceries</li>
      <li class="bg-gray-50 p-1.5 text-[11px] font-mono">new task call dentist</li>
      <li class="bg-gray-50 p-1.5 text-[11px] font-mono">create a task to renew domain</li>
      <li class="bg-gray-50 p-1.5 text-[11px] font-mono">I need to fix the bug</li>
    </ul>
  </section>

  <!-- TODOS -->
  <section class="border border-black p-3 space-y-2">
    <p class="text-[10px] font-bold uppercase tracking-widest text-gray-500">✓ Todo List (read)</p>
    <ul class="space-y-1">
      <li class="bg-gray-50 p-1.5 text-[11px] font-mono">todos</li>
      <li class="bg-gray-50 p-1.5 text-[11px] font-mono">my todos</li>
      <li class="bg-gray-50 p-1.5 text-[11px] font-mono">read my todos</li>
      <li class="bg-gray-50 p-1.5 text-[11px] font-mono">what's on my todo list</li>
    </ul>
  </section>

  <section class="border border-black p-3 space-y-2">
    <p class="text-[10px] font-bold uppercase tracking-widest text-gray-500">➕ Add Todo (write)</p>
    <ul class="space-y-1">
      <li class="bg-gray-50 p-1.5 text-[11px] font-mono">add todo buy bread</li>
      <li class="bg-gray-50 p-1.5 text-[11px] font-mono">remind me to take out trash</li>
      <li class="bg-gray-50 p-1.5 text-[11px] font-mono">new todo finish report</li>
      <li class="bg-gray-50 p-1.5 text-[11px] font-mono">put backup database on my list</li>
    </ul>
  </section>

  <!-- HABITS -->
  <section class="border border-black p-3 space-y-2">
    <p class="text-[10px] font-bold uppercase tracking-widest text-gray-500">🔄 Habits (read + mark)</p>
    <ul class="space-y-1">
      <li class="bg-gray-50 p-1.5 text-[11px] font-mono">habits</li>
      <li class="bg-gray-50 p-1.5 text-[11px] font-mono">my habits</li>
      <li class="bg-gray-50 p-1.5 text-[11px] font-mono">did I do my habits</li>
      <li class="bg-gray-50 p-1.5 text-[11px] font-mono">what habits are left</li>
      <li class="bg-gray-50 p-1.5 text-[11px] font-mono">I did meditation</li>
      <li class="bg-gray-50 p-1.5 text-[11px] font-mono">mark drink water as done</li>
      <li class="bg-gray-50 p-1.5 text-[11px] font-mono">finished gym</li>
    </ul>
  </section>

  <section class="border border-black p-3 space-y-2">
    <p class="text-[10px] font-bold uppercase tracking-widest text-gray-500">➕ Add Habit (write)</p>
    <ul class="space-y-1">
      <li class="bg-gray-50 p-1.5 text-[11px] font-mono">add habit meditation</li>
      <li class="bg-gray-50 p-1.5 text-[11px] font-mono">track new habit reading</li>
      <li class="bg-gray-50 p-1.5 text-[11px] font-mono">start tracking gratitude</li>
    </ul>
  </section>

  <!-- WATER -->
  <section class="border border-black p-3 space-y-2">
    <p class="text-[10px] font-bold uppercase tracking-widest text-gray-500">💧 Water</p>
    <ul class="space-y-1">
      <li class="bg-gray-50 p-1.5 text-[11px] font-mono">water</li>
      <li class="bg-gray-50 p-1.5 text-[11px] font-mono">my water</li>
      <li class="bg-gray-50 p-1.5 text-[11px] font-mono">how much water did I drink</li>
      <li class="bg-gray-50 p-1.5 text-[11px] font-mono">did I hit my water goal</li>
    </ul>
  </section>

  <!-- PROJECTS -->
  <section class="border border-black p-3 space-y-2">
    <p class="text-[10px] font-bold uppercase tracking-widest text-gray-500">📁 Projects (write)</p>
    <ul class="space-y-1">
      <li class="bg-gray-50 p-1.5 text-[11px] font-mono">create project home renovation</li>
      <li class="bg-gray-50 p-1.5 text-[11px] font-mono">add new project Q3 launch</li>
      <li class="bg-gray-50 p-1.5 text-[11px] font-mono">new project marketing campaign</li>
    </ul>
  </section>

  <!-- CLIENTS -->
  <section class="border border-black p-3 space-y-2">
    <p class="text-[10px] font-bold uppercase tracking-widest text-gray-500">👤 Clients (write)</p>
    <ul class="space-y-1">
      <li class="bg-gray-50 p-1.5 text-[11px] font-mono">add client John Smith</li>
      <li class="bg-gray-50 p-1.5 text-[11px] font-mono">create new client Acme Corp</li>
      <li class="bg-gray-50 p-1.5 text-[11px] font-mono">register client Jane Doe</li>
    </ul>
  </section>

  <!-- NOTES -->
  <section class="border border-black p-3 space-y-2">
    <p class="text-[10px] font-bold uppercase tracking-widest text-gray-500">📝 Notes (write)</p>
    <ul class="space-y-1">
      <li class="bg-gray-50 p-1.5 text-[11px] font-mono">add note remember to follow up</li>
      <li class="bg-gray-50 p-1.5 text-[11px] font-mono">take a note buy ssl cert</li>
      <li class="bg-gray-50 p-1.5 text-[11px] font-mono">remember domain expires soon</li>
      <li class="bg-gray-50 p-1.5 text-[11px] font-mono">write down meeting was productive</li>
    </ul>
  </section>

  <!-- MEETINGS -->
  <section class="border border-black p-3 space-y-2">
    <p class="text-[10px] font-bold uppercase tracking-widest text-gray-500">📅 Meetings (write)</p>
    <p class="text-[10px] text-gray-600">Default date = today 9am. Adjust precise time from web after.</p>
    <ul class="space-y-1">
      <li class="bg-gray-50 p-1.5 text-[11px] font-mono">schedule meeting design review</li>
      <li class="bg-gray-50 p-1.5 text-[11px] font-mono">add meeting weekly standup</li>
      <li class="bg-gray-50 p-1.5 text-[11px] font-mono">book meeting client call</li>
    </ul>
  </section>

  <!-- POMODORO -->
  <section class="border border-black p-3 space-y-2">
    <p class="text-[10px] font-bold uppercase tracking-widest text-gray-500">⏱️ Pomodoro Timer</p>
    <p class="text-[10px] text-gray-600">Default = 25 minutes. Native Alexa timer rings when done (requires timer permission grant).</p>
    <ul class="space-y-1">
      <li class="bg-gray-50 p-1.5 text-[11px] font-mono">start pomodoro</li>
      <li class="bg-gray-50 p-1.5 text-[11px] font-mono">start a 25 minute pomodoro</li>
      <li class="bg-gray-50 p-1.5 text-[11px] font-mono">start focus mode</li>
      <li class="bg-gray-50 p-1.5 text-[11px] font-mono">begin pomodoro</li>
    </ul>
  </section>

  <!-- INVENTORY -->
  <section class="border border-black p-3 space-y-2">
    <p class="text-[10px] font-bold uppercase tracking-widest text-gray-500">📦 Inventory (adjust stock)</p>
    <p class="text-[10px] text-gray-600">Item must already exist in inventory. Add new items from web.</p>
    <ul class="space-y-1">
      <li class="bg-gray-50 p-1.5 text-[11px] font-mono">add 5 widgets to inventory</li>
      <li class="bg-gray-50 p-1.5 text-[11px] font-mono">remove 2 boxes from inventory</li>
      <li class="bg-gray-50 p-1.5 text-[11px] font-mono">I used 3 markers</li>
      <li class="bg-gray-50 p-1.5 text-[11px] font-mono">restock 10 paper</li>
    </ul>
  </section>

  <!-- FINANCE -->
  <section class="border border-black p-3 space-y-2">
    <p class="text-[10px] font-bold uppercase tracking-widest text-gray-500">💰 Finance (read)</p>
    <ul class="space-y-1">
      <li class="bg-gray-50 p-1.5 text-[11px] font-mono">money</li>
      <li class="bg-gray-50 p-1.5 text-[11px] font-mono">finance summary</li>
      <li class="bg-gray-50 p-1.5 text-[11px] font-mono">what did I earn this month</li>
      <li class="bg-gray-50 p-1.5 text-[11px] font-mono">monthly revenue</li>
      <li class="bg-gray-50 p-1.5 text-[11px] font-mono">how much did I make this month</li>
    </ul>
  </section>

  <!-- SESSION CONTROL -->
  <section class="border border-black p-3 space-y-2">
    <p class="text-[10px] font-bold uppercase tracking-widest text-gray-500">🎙️ Session Control</p>
    <ul class="space-y-1">
      <li class="bg-gray-50 p-1.5 text-[11px] font-mono">Alexa, open my plan <span class="text-gray-400">(starts session)</span></li>
      <li class="bg-gray-50 p-1.5 text-[11px] font-mono">help <span class="text-gray-400">(lists commands)</span></li>
      <li class="bg-gray-50 p-1.5 text-[11px] font-mono">stop / cancel <span class="text-gray-400">(ends session)</span></li>
    </ul>
  </section>

  <section class="border border-gray-300 p-3 text-[10px] text-gray-600 space-y-1">
    <p class="font-bold text-black uppercase tracking-widest">Tips</p>
    <p>&middot; If Alexa says "I did not catch that" — it heard you wrong. Try a shorter phrase from the list.</p>
    <p>&middot; If "Sorry, something went wrong" — server error. Open <a href="?page=alexa-setup" class="underline">Alexa Setup</a> to check status.</p>
    <p>&middot; After ~8 seconds of silence, the session ends. You'll need to say "open my plan" again.</p>
    <p>&middot; Echo Show / Echo Dot with screen will display the response text too.</p>
  </section>

</main>

<?php include MOBILE_VIEW_PATH . '/partials/bottom-nav.php'; ?>

</div>
</body>
</html>
