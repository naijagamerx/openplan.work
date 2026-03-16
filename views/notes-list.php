<?php
/**
 * Notes List View - Mobile-First Design
 * Displays all notes with search, filters, and tag navigation
 */
require_once __DIR__ . '/../config.php';
Auth::check();

// Load notes from database
$db = new Database(getMasterPassword(), Auth::userId());
$allNotes = $db->load('notes') ?? [];

// Get filter parameters
$filter = $_GET['filter'] ?? 'all';
$tagFilter = $_GET['tag'] ?? '';
$searchQuery = $_GET['search'] ?? '';

// Filter notes
$filteredNotes = array_filter($allNotes, function($note) use ($filter, $tagFilter, $searchQuery) {
    // Status filter
    if ($filter === 'pinned' && empty($note['isPinned'])) return false;
    if ($filter === 'favorites' && empty($note['isFavorite'])) return false;

    // Tag filter
    if (!empty($tagFilter) && !in_array($tagFilter, $note['tags'] ?? [])) return false;

    // Search query
    if (!empty($searchQuery)) {
        $searchLower = strtolower($searchQuery);
        $title = strtolower($note['title'] ?? '');
        $content = strtolower($note['content'] ?? '');
        if (strpos($title, $searchLower) === false && strpos($content, $searchLower) === false) {
            return false;
        }
    }

    return true;
});

// Sort by updated date
usort($filteredNotes, function($a, $b) {
    return strtotime($b['updatedAt'] ?? 0) - strtotime($a['updatedAt'] ?? 0);
});

// Get all unique tags
$allTags = [];
foreach ($allNotes as $note) {
    foreach ($note['tags'] ?? [] as $tag) {
        if (!in_array($tag, $allTags)) {
            $allTags[] = $tag;
        }
    }
}
sort($allTags);

// Count stats
$pinnedCount = count(array_filter($allNotes, fn($n) => !empty($n['isPinned'])));
$favoritesCount = count(array_filter($allNotes, fn($n) => !empty($n['isFavorite'])));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notes - LazyMan Tools</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@100;200;300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }
        .no-scrollbar::-webkit-scrollbar {
            display: none;
        }
        .no-scrollbar {
            -ms-overflow-style: none;
            scrollbar-width: none;
        }
        .note-card-container {
            position: relative;
            overflow: hidden;
        }
        .note-actions {
            position: absolute;
            right: 0;
            top: 0;
            height: 100%;
            display: flex;
            align-items: center;
            transform: translateX(100%);
            transition: transform 0.2s ease;
        }
        .note-card-container:hover .note-actions {
            transform: translateX(0);
        }
        .note-card-container:hover .note-content {
            transform: translateX(-120px);
        }
        .note-content {
            transition: transform 0.2s ease;
            background: white;
            position: relative;
            z-index: 10;
        }
        @media (max-width: 768px) {
            .note-card-container:hover .note-actions {
                transform: translateX(0);
            }
            .note-actions {
                transform: translateX(100%);
            }
            .note-card-container.active .note-actions {
                transform: translateX(0);
            }
            .note-card-container.active .note-content {
                transform: translateX(-120px);
            }
        }
    </style>
</head>
<body class="bg-gray-50 flex justify-center min-h-screen">
    <!-- Mobile Container -->
    <div class="relative w-full max-w-[420px] min-h-screen bg-white shadow-2xl flex flex-col border-x border-gray-100 overflow-hidden md:max-w-full md:border-x-0">

        <!-- Header -->
        <header class="px-6 pt-12 pb-4 flex justify-between items-center bg-white sticky top-0 z-30">
            <div class="flex items-center gap-4">
                <button onclick="window.history.back()" class="p-1 -ml-1 hover:opacity-60 transition-opacity">
                    <!-- Menu Icon - Heroicon -->
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                    </svg>
                </button>
                <h1 class="text-xl font-black tracking-tight uppercase">Notes</h1>
            </div>
            <button onclick="location.href='?page=notes&action=create'" class="bg-black text-white px-4 py-2 text-[10px] font-bold uppercase tracking-widest flex items-center gap-2 rounded-sm hover:opacity-90 transition-opacity">
                <!-- Plus Icon - Heroicon -->
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                </svg>
                New Note
            </button>
        </header>

        <!-- Search Bar -->
        <div class="px-6 pb-4">
            <form action="" method="GET" class="relative">
                <input type="hidden" name="page" value="notes">
                <!-- Search Icon - Heroicon -->
                <svg class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                </svg>
                <input
                    class="w-full bg-gray-50 border-none rounded-md py-2 pl-10 pr-4 text-sm focus:ring-1 focus:ring-black"
                    placeholder="Search notes..."
                    type="text"
                    name="search"
                    value="<?= htmlspecialchars($searchQuery) ?>"
                >
            </form>
        </div>

        <!-- Main Content -->
        <main class="flex-1 overflow-y-auto no-scrollbar pb-32">
            <!-- Filters Section -->
            <section class="mb-6">
                <!-- Status Filters -->
                <div class="flex gap-2 px-6 overflow-x-auto no-scrollbar mb-3">
                    <a href="?page=notes&filter=all" class="whitespace-nowrap <?= $filter === 'all' ? 'bg-black text-white' : 'border border-gray-200 text-gray-500 hover:border-black hover:text-black' ?> px-4 py-1.5 rounded-full text-[10px] font-bold uppercase tracking-wider transition-colors">
                        All Notes (<?= count($allNotes) ?>)
                    </a>
                    <a href="?page=notes&filter=pinned" class="whitespace-nowrap <?= $filter === 'pinned' ? 'bg-black text-white' : 'border border-gray-200 text-gray-500 hover:border-black hover:text-black' ?> px-4 py-1.5 rounded-full text-[10px] font-bold uppercase tracking-wider transition-colors">
                        Pinned (<?= $pinnedCount ?>)
                    </a>
                    <a href="?page=notes&filter=favorites" class="whitespace-nowrap <?= $filter === 'favorites' ? 'bg-black text-white' : 'border border-gray-200 text-gray-500 hover:border-black hover:text-black' ?> px-4 py-1.5 rounded-full text-[10px] font-bold uppercase tracking-wider transition-colors">
                        Favorites (<?= $favoritesCount ?>)
                    </a>
                </div>
                <!-- Tag Filters -->
                <div class="flex gap-2 px-6 overflow-x-auto no-scrollbar">
                    <?php foreach ($allTags as $tag): ?>
                        <a href="?page=notes&tag=<?= urlencode($tag) ?>" class="whitespace-nowrap text-[10px] <?= $tagFilter === $tag ? 'text-black font-bold' : 'text-gray-400' ?> hover:text-black transition-colors">
                            #<?= htmlspecialchars($tag) ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </section>

            <!-- Notes List -->
            <section class="border-t border-gray-100">
                <?php if (empty($filteredNotes)): ?>
                    <div class="px-6 py-12 text-center">
                        <svg class="w-12 h-12 mx-auto text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                        <p class="text-gray-500 text-sm">No notes found</p>
                        <button onclick="location.href='?page=notes&action=create'" class="mt-4 text-xs font-bold uppercase tracking-widest bg-black text-white px-4 py-2 rounded-sm hover:opacity-90">
                            Create Note
                        </button>
                    </div>
                <?php else: ?>
                    <?php foreach ($filteredNotes as $note): ?>
                        <div class="note-card-container border-b border-gray-100" data-note-id="<?= htmlspecialchars($note['id'] ?? '') ?>">
                            <div class="note-content p-6 cursor-pointer" onclick="location.href='?page=view-notes&id=<?= htmlspecialchars($note['id'] ?? '') ?>'">
                                <div class="flex justify-between items-start mb-1">
                                    <h2 class="text-sm font-bold uppercase tracking-tight pr-8">
                                        <?= htmlspecialchars($note['title'] ?? 'Untitled') ?>
                                        <?php if (!empty($note['isPinned'])): ?>
                                            <!-- Pin Icon - Heroicon -->
                                            <svg class="w-3 h-3 inline ml-1 text-black" fill="currentColor" viewBox="0 0 24 24">
                                                <path d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z"></path>
                                            </svg>
                                        <?php endif; ?>
                                    </h2>
                                    <span class="text-[10px] text-gray-400 font-medium">
                                        <?= date('M d', strtotime($note['updatedAt'] ?? 'now')) ?>
                                    </span>
                                </div>
                                <p class="text-xs text-gray-500 line-clamp-2 leading-relaxed mb-3">
                                    <?= htmlspecialchars(substr(strip_tags($note['content'] ?? ''), 0, 120)) ?>
                                    <?php if (strlen($note['content'] ?? '') > 120) echo '...'; ?>
                                </p>
                                <div class="flex gap-2">
                                    <?php foreach (($note['tags'] ?? []) as $tag): ?>
                                        <span class="text-[9px] uppercase tracking-widest text-gray-400">#<?= htmlspecialchars($tag) ?></span>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <div class="note-actions">
                                <button onclick="event.stopPropagation(); togglePin('<?= htmlspecialchars($note['id'] ?? '') ?>')" class="w-[60px] h-full bg-gray-50 flex items-center justify-center border-l border-gray-100 hover:bg-gray-100">
                                    <?php if (!empty($note['isPinned'])): ?>
                                        <!-- Filled Pin Icon - Heroicon -->
                                        <svg class="w-5 h-5 text-black" fill="currentColor" viewBox="0 0 24 24">
                                            <path d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z"></path>
                                        </svg>
                                    <?php else: ?>
                                        <!-- Outline Pin Icon - Heroicon -->
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z"></path>
                                        </svg>
                                    <?php endif; ?>
                                </button>
                                <button onclick="event.stopPropagation(); deleteNote('<?= htmlspecialchars($note['id'] ?? '') ?>')" class="w-[60px] h-full bg-gray-50 flex items-center justify-center border-l border-gray-100 text-red-500 hover:bg-red-50">
                                    <!-- Trash Icon - Heroicon -->
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                    </svg>
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </section>
        </main>

        <!-- Bottom Navigation -->
        <nav class="absolute bottom-0 left-0 right-0 bg-white border-t border-gray-100 px-8 py-6 flex justify-between items-center z-40">
            <a href="?page=dashboard" class="text-gray-300 hover:text-black transition-colors flex flex-col items-center gap-1">
                <!-- Grid View Icon - Heroicon -->
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"></path>
                </svg>
                <span class="text-[8px] font-black uppercase tracking-widest">Dash</span>
            </a>
            <a href="?page=notes" class="text-black flex flex-col items-center gap-1">
                <!-- Document Icon - Heroicon -->
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
                <span class="text-[8px] font-black uppercase tracking-widest">Notes</span>
            </a>
            <a href="?page=habits" class="text-gray-300 hover:text-black transition-colors flex flex-col items-center gap-1">
                <!-- Check Circle Icon - Heroicon -->
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <span class="text-[8px] font-black uppercase tracking-widest">Habits</span>
            </a>
            <a href="?page=settings" class="text-gray-300 hover:text-black transition-colors flex flex-col items-center gap-1">
                <!-- Settings Icon - Heroicon -->
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                </svg>
                <span class="text-[8px] font-black uppercase tracking-widest">Config</span>
            </a>
        </nav>

        <!-- Home Indicator -->
        <div class="absolute bottom-2 left-1/2 -translate-x-1/2 w-12 h-1 bg-gray-200 rounded-full z-50"></div>

    </div>

    <script>
        const API_BASE = 'api/notes.php';

        async function togglePin(noteId) {
            try {
                const response = await fetch(`${API_BASE}?id=${noteId}`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'toggle_pin',
                        csrf_token: '<?= CSRF_TOKEN ?>'
                    })
                });
                const result = await response.json();
                if (result.success) {
                    location.reload();
                }
            } catch (error) {
                console.error('Error toggling pin:', error);
            }
        }

        async function deleteNote(noteId) {
            if (!confirm('Are you sure you want to delete this note?')) return;
            try {
                const response = await fetch(`${API_BASE}?id=${noteId}`, {
                    method: 'DELETE',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        csrf_token: '<?= CSRF_TOKEN ?>'
                    })
                });
                const result = await response.json();
                if (result.success) {
                    location.reload();
                }
            } catch (error) {
                console.error('Error deleting note:', error);
            }
        }

        // Mobile: toggle swipe actions
        document.querySelectorAll('.note-card-container').forEach(card => {
            let startX = 0;
            let isSwiping = false;

            card.addEventListener('touchstart', (e) => {
                startX = e.touches[0].clientX;
                isSwiping = true;
            });

            card.addEventListener('touchmove', (e) => {
                if (!isSwiping) return;
                const currentX = e.touches[0].clientX;
                const diff = startX - currentX;

                if (diff > 50) {
                    card.classList.add('active');
                } else if (diff < -50) {
                    card.classList.remove('active');
                }
            });

            card.addEventListener('touchend', () => {
                isSwiping = false;
            });
        });
    </script>
</body>
</html>

