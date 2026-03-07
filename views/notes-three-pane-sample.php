<?php
/**
 * LazyMan Notes - Three-Pane View
 *
 * Replicated from Google Stitch project 10773818954716616028
 * Screen: 0313037a422b4698830fb592bdd89ed7
 * Raw source: sample/notes-three-pane-from-mcp.html
 *
 * This is a SAMPLE/STANDALONE file - not integrated with the main app
 */
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>LazyMan Notes - Three-Pane View (Sample)</title>
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&amp;display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght@100..700,0..1&amp;display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet"/>
<script id="tailwind-config">
    tailwind.config = {
      darkMode: "class",
      theme: {
        extend: {
          colors: {
            "primary": "#17b0cf",
            "background-light": "#f6f8f8",
            "background-dark": "#111e21",
          },
          fontFamily: {
            "display": ["Inter"]
          },
          borderRadius: {"DEFAULT": "0.25rem", "lg": "0.5rem", "xl": "0.75rem", "full": "9999px"},
        },
      },
    }
</script>
<style>
    .material-symbols-outlined {
        font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
        font-size: 24px;
    }
    .active-icon {
        font-variation-settings: 'FILL' 1, 'wght' 400, 'GRAD' 0, 'opsz' 24;
    }
    body {
        font-family: 'Inter', sans-serif;
    }
    .thin-border {
        border: 1px solid #e5e7eb;
    }
    .dark .thin-border {
        border: 1px solid #2d373a;
    }
</style>
</head>
<body class="bg-background-light dark:bg-background-dark text-[#0e191b] dark:text-[#e7f1f3] h-screen overflow-hidden">
<div class="flex h-full w-full">
<!-- Global Side Navigation -->
<aside class="w-64 flex flex-col h-full bg-white dark:bg-background-dark border-r border-[#d0e3e7] dark:border-[#2d373a] p-4">
<div class="flex flex-col gap-6 h-full">
<!-- Brand -->
<div class="flex gap-3 px-2">
<div class="bg-primary rounded-lg size-10 flex items-center justify-center text-white">
<span class="material-symbols-outlined">bolt</span>
</div>
<div class="flex flex-col">
<h1 class="text-[#0e191b] dark:text-white text-base font-bold leading-none">LazyMan</h1>
<p class="text-[#4e8b97] text-xs font-normal">Productivity Suite</p>
</div>
</div>
<!-- Nav Links -->
<nav class="flex flex-col gap-1 flex-1">
<a class="flex items-center gap-3 px-3 py-2 rounded-lg text-[#4e8b97] hover:bg-[#f0f4f5] dark:hover:bg-[#1a2b2e] transition-colors" href="#">
<span class="material-symbols-outlined">dashboard</span>
<span class="text-sm font-medium">Dashboard</span>
</a>
<a class="flex items-center gap-3 px-3 py-2 rounded-lg text-[#4e8b97] hover:bg-[#f0f4f5] dark:hover:bg-[#1a2b2e] transition-colors" href="#">
<span class="material-symbols-outlined">check_box</span>
<span class="text-sm font-medium">Tasks</span>
</a>
<a class="flex items-center gap-3 px-3 py-2 rounded-lg text-[#4e8b97] hover:bg-[#f0f4f5] dark:hover:bg-[#1a2b2e] transition-colors" href="#">
<span class="material-symbols-outlined">query_stats</span>
<span class="text-sm font-medium">Habits</span>
</a>
<a class="flex items-center gap-3 px-3 py-2 rounded-lg bg-[#e7f1f3] dark:bg-[#1a2b2e] text-[#0e191b] dark:text-white" href="#">
<span class="material-symbols-outlined active-icon">description</span>
<span class="text-sm font-bold">Notes</span>
</a>
<a class="flex items-center gap-3 px-3 py-2 rounded-lg text-[#4e8b97] hover:bg-[#f0f4f5] dark:hover:bg-[#1a2b2e] transition-colors" href="#">
<span class="material-symbols-outlined">calendar_today</span>
<span class="text-sm font-medium">Calendar</span>
</a>
<a class="flex items-center gap-3 px-3 py-2 rounded-lg text-[#4e8b97] hover:bg-[#f0f4f5] dark:hover:bg-[#1a2b2e] transition-colors mt-auto" href="#">
<span class="material-symbols-outlined">settings</span>
<span class="text-sm font-medium">Settings</span>
</a>
</nav>
<!-- User Profile -->
<div class="border-t border-[#d0e3e7] dark:border-[#2d373a] pt-4">
<div class="flex items-center gap-3 px-2">
<div class="size-8 rounded-full bg-cover bg-center" style='background-image: url("https://lh3.googleusercontent.com/aida-public/AB6AXuB9Wk5thmVqkzgC0WaZzlkVUOznCU-T2cXtaFzB32NGXDERCBYScc8smdWBXvbRjnf5mjTBC0FfOKtG626_J5xHEATdEnt7fvnZ4M-2hjDZ6ebwu67zsDf5gQbx9pLmqbHTgJLpzaswDelIEK07c-NbcWvuQjIMZE5cWUbi6oNt463f97yV4Rfa7zxQPCUINrefI1xmtomlmCOmptzEeE1jeCId-3Ippez_RQ9VtcobUhrOgPPTWNNBDWNmPop2CH-fFUAWxl6yNnW0");'></div>
<div class="flex flex-col">
<span class="text-xs font-bold dark:text-white">Alex Rivers</span>
<span class="text-[10px] text-[#4e8b97]">Pro Member</span>
</div>
</div>
</div>
</div>
</aside>
<!-- Main Three-Pane Container -->
<main class="flex-1 flex overflow-hidden">
<!-- Left Pane: Categories -->
<section class="w-60 flex flex-col bg-white dark:bg-background-dark border-r border-[#d0e3e7] dark:border-[#2d373a]">
<div class="p-4">
<button class="w-full flex items-center justify-center gap-2 bg-[#0e191b] hover:bg-black text-white rounded-lg h-11 transition-all active:scale-[0.98]">
<span class="material-symbols-outlined text-sm">add</span>
<span class="text-sm font-bold">New Note</span>
</button>
</div>
<div class="px-2 py-2">
<h3 class="px-3 text-[10px] font-bold uppercase tracking-wider text-[#4e8b97] mb-2">Folders</h3>
<div class="flex flex-col gap-1">
<button class="flex items-center gap-3 px-3 py-2 rounded-lg bg-primary/10 text-primary border-l-4 border-primary">
<span class="material-symbols-outlined text-[20px]">inventory_2</span>
<span class="text-sm font-medium">All Notes</span>
</button>
<button class="flex items-center gap-3 px-3 py-2 rounded-lg text-[#4e8b97] hover:bg-[#f8fbfc] dark:hover:bg-[#1a2b2e] transition-colors">
<span class="material-symbols-outlined text-[20px]">bolt</span>
<span class="text-sm font-medium">Quick Notes</span>
</button>
<button class="flex items-center gap-3 px-3 py-2 rounded-lg text-[#4e8b97] hover:bg-[#f8fbfc] dark:hover:bg-[#1a2b2e] transition-colors">
<span class="material-symbols-outlined text-[20px]">folder</span>
<span class="text-sm font-medium">Project Notes</span>
</button>
<button class="flex items-center gap-3 px-3 py-2 rounded-lg text-[#4e8b97] hover:bg-[#f8fbfc] dark:hover:bg-[#1a2b2e] transition-colors">
<span class="material-symbols-outlined text-[20px]">lightbulb</span>
<span class="text-sm font-medium">Ideas</span>
</button>
<button class="flex items-center gap-3 px-3 py-2 rounded-lg text-[#4e8b97] hover:bg-[#f8fbfc] dark:hover:bg-[#1a2b2e] transition-colors">
<span class="material-symbols-outlined text-[20px]">archive</span>
<span class="text-sm font-medium">Archived</span>
</button>
</div>
</div>
</section>
<!-- Middle Pane: Note List -->
<section class="w-80 flex flex-col bg-white dark:bg-[#152427] border-r border-[#d0e3e7] dark:border-[#2d373a]">
<!-- Search Header -->
<div class="p-4 border-b border-[#d0e3e7] dark:border-[#2d373a]">
<div class="relative">
<span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-[#4e8b97] text-lg">search</span>
<input class="w-full bg-transparent border border-[#d0e3e7] dark:border-[#2d373a] rounded-lg pl-10 pr-4 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary outline-none transition-all placeholder:text-[#4e8b97]/50" placeholder="Search notes..." type="text"/>
</div>
</div>
<!-- Note List Cards -->
<div class="flex-1 overflow-y-auto custom-scrollbar">
<!-- Note Item Active -->
<div class="p-4 bg-primary/5 border-b border-[#d0e3e7] dark:border-[#2d373a] relative cursor-pointer">
<div class="absolute left-0 top-0 bottom-0 w-1 bg-primary"></div>
<div class="flex justify-between items-start mb-1">
<h4 class="font-bold text-sm text-[#0e191b] dark:text-white truncate">Project Brainstorming</h4>
<span class="text-[10px] text-primary font-bold">Today</span>
</div>
<p class="text-xs text-[#4e8b97] line-clamp-2 leading-relaxed">Let's refine the core value proposition of the LazyMan suite. We need to focus on minimalism...</p>
</div>
<!-- Note Item -->
<div class="p-4 hover:bg-[#f8fbfc] dark:hover:bg-[#1a2b2e] border-b border-[#d0e3e7] dark:border-[#2d373a] cursor-pointer transition-colors">
<div class="flex justify-between items-start mb-1">
<h4 class="font-bold text-sm text-[#0e191b] dark:text-white truncate">Weekly Sync Notes</h4>
<span class="text-[10px] text-[#4e8b97]">Oct 24</span>
</div>
<p class="text-xs text-[#4e8b97] line-clamp-2 leading-relaxed">Reviewed the upcoming marketing sprint. Goals are set for the next Q3 release cycle.</p>
</div>
<!-- Note Item -->
<div class="p-4 hover:bg-[#f8fbfc] dark:hover:bg-[#1a2b2e] border-b border-[#d0e3e7] dark:border-[#2d373a] cursor-pointer transition-colors">
<div class="flex justify-between items-start mb-1">
<h4 class="font-bold text-sm text-[#0e191b] dark:text-white truncate">Coffee Machine User Guide</h4>
<span class="text-[10px] text-[#4e8b97]">Oct 22</span>
</div>
<p class="text-xs text-[#4e8b97] line-clamp-2 leading-relaxed">1. Fill water tank 2. Add beans to hopper 3. Select espresso... </p>
</div>
<!-- Note Item -->
<div class="p-4 hover:bg-[#f8fbfc] dark:hover:bg-[#1a2b2e] border-b border-[#d0e3e7] dark:border-[#2d373a] cursor-pointer transition-colors">
<div class="flex justify-between items-start mb-1">
<h4 class="font-bold text-sm text-[#0e191b] dark:text-white truncate">App Architecture Ideas</h4>
<span class="text-[10px] text-[#4e8b97]">Oct 18</span>
</div>
<p class="text-xs text-[#4e8b97] line-clamp-2 leading-relaxed">Considering a move to serverless functions for the background processing of tool chains.</p>
</div>
</div>
</section>
<!-- Right Pane: Editor -->
<section class="flex-1 flex flex-col bg-white dark:bg-background-dark overflow-hidden">
<!-- Editor Toolbar -->
<div class="h-14 border-b border-[#d0e3e7] dark:border-[#2d373a] flex items-center justify-between px-6">
<div class="flex items-center gap-1">
<button class="p-1.5 rounded-md hover:bg-[#f0f4f5] dark:hover:bg-[#1a2b2e] text-[#0e191b] dark:text-white transition-colors" title="Bold">
<span class="material-symbols-outlined text-[20px]">format_bold</span>
</button>
<button class="p-1.5 rounded-md hover:bg-[#f0f4f5] dark:hover:bg-[#1a2b2e] text-[#0e191b] dark:text-white transition-colors" title="Italic">
<span class="material-symbols-outlined text-[20px]">format_italic</span>
</button>
<div class="w-px h-4 bg-[#d0e3e7] dark:border-[#2d373a] mx-1"></div>
<button class="p-1.5 rounded-md hover:bg-[#f0f4f5] dark:hover:bg-[#1a2b2e] text-[#0e191b] dark:text-white transition-colors" title="Checklist">
<span class="material-symbols-outlined text-[20px]">checklist</span>
</button>
<button class="p-1.5 rounded-md hover:bg-[#f0f4f5] dark:hover:bg-[#1a2b2e] text-[#0e191b] dark:text-white transition-colors" title="List">
<span class="material-symbols-outlined text-[20px]">format_list_bulleted</span>
</button>
<button class="p-1.5 rounded-md hover:bg-[#f0f4f5] dark:hover:bg-[#1a2b2e] text-[#0e191b] dark:text-white transition-colors" title="Image">
<span class="material-symbols-outlined text-[20px]">image</span>
</button>
</div>
<div class="flex items-center gap-3">
<button class="flex items-center gap-2 px-3 py-1.5 rounded-lg border border-[#d0e3e7] dark:border-[#2d373a] text-xs font-bold hover:bg-[#f8fbfc] dark:hover:bg-[#1a2b2e] transition-colors">
<span class="material-symbols-outlined text-[16px]">ios_share</span>
Export
</button>
<button class="p-1.5 rounded-md hover:bg-[#f0f4f5] dark:hover:bg-[#1a2b2e] text-[#4e8b97]">
<span class="material-symbols-outlined text-[20px]">more_vert</span>
</button>
</div>
</div>
<!-- Content Area -->
<div class="flex-1 overflow-y-auto px-12 py-12 flex justify-center">
<article class="max-w-2xl w-full">
<header class="mb-8">
<input class="w-full text-4xl font-extrabold text-[#0e191b] dark:text-white border-none focus:ring-0 p-0 placeholder:text-gray-300 bg-transparent" type="text" value="Project Brainstorming"/>
<div class="flex items-center gap-4 mt-4 text-[#4e8b97] text-sm">
<span class="flex items-center gap-1"><span class="material-symbols-outlined text-[14px]">calendar_today</span> Oct 24, 2023</span>
<span class="flex items-center gap-1"><span class="material-symbols-outlined text-[14px]">folder</span> Project Notes</span>
</div>
</header>
<div class="prose dark:prose-invert max-w-none text-[#0e191b] dark:text-[#e7f1f3] leading-relaxed">
<p class="mb-6">We are redefining the core philosophy of <strong><?php echo e(getSiteName()); ?></strong>. The objective is to create a suite that works <i>for</i> the user, not the other way around. Every interaction must be intentional and high-value.</p>
<h2 class="text-xl font-bold mb-4 mt-8 flex items-center gap-2">
<span class="w-1 h-6 bg-primary rounded-full"></span>
Core Value Pillars
</h2>
<ul class="list-disc pl-5 space-y-2 mb-6 text-[#4e8b97] dark:text-[#b0bec3]">
<li><strong>Minimalism:</strong> Only show what is necessary for the current task.</li>
<li><strong>Efficiency:</strong> Keyboard-first design for power users.</li>
<li><strong>Contextual:</strong> The tool should adapt to the data it contains.</li>
</ul>
<h2 class="text-xl font-bold mb-4 mt-8 flex items-center gap-2">
<span class="w-1 h-6 bg-primary rounded-full"></span>
Next Steps
</h2>
<div class="space-y-3">
<label class="flex items-center gap-3 cursor-pointer group">
<input checked="" class="rounded border-[#d0e3e7] text-primary focus:ring-primary h-5 w-5" type="checkbox"/>
<span class="text-sm line-through text-[#4e8b97]">Finalize the high-contrast color palette</span>
</label>
<label class="flex items-center gap-3 cursor-pointer group">
<input class="rounded border-[#d0e3e7] text-primary focus:ring-primary h-5 w-5" type="checkbox"/>
<span class="text-sm">Design the three-pane responsive layout for desktop</span>
</label>
<label class="flex items-center gap-3 cursor-pointer group">
<input class="rounded border-[#d0e3e7] text-primary focus:ring-primary h-5 w-5" type="checkbox"/>
<span class="text-sm">Implement markdown shortcuts in the editor</span>
</label>
<label class="flex items-center gap-3 cursor-pointer group">
<input class="rounded border-[#d0e3e7] text-primary focus:ring-primary h-5 w-5" type="checkbox"/>
<span class="text-sm">Review icon set for visual consistency</span>
</label>
</div>
<div class="mt-12 p-6 rounded-xl bg-[#f8fbfc] dark:bg-[#1a2b2e] border border-[#d0e3e7] dark:border-[#2d373a]">
<h3 class="font-bold text-sm mb-2 flex items-center gap-2">
<span class="material-symbols-outlined text-primary text-sm">info</span>
Internal Note
</h3>
<p class="text-xs text-[#4e8b97] italic">"The simplicity of the tool should reflect the simplicity we want to bring to the user's workflow. If a feature feels heavy, it shouldn't be there." — Product Lead</p>
</div>
</div>
</article>
</div>
</section>
</main>
</div>
</body>
</html>
