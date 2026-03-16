<?php
// Define ASSETS_PATH constant if not already defined
if (!defined('ASSETS_PATH')) {
    define('ASSETS_PATH', ROOT_PATH . '/assets');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo e($pageTitle ?? getSiteName()); ?></title>

    <!-- Note: Security headers (X-Frame-Options, CSP, etc.) are sent via HTTP in PHP, not meta tags -->

    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght@100..700,0..1&display=swap" rel="stylesheet">

    <!-- Favicon - PNG first for better compatibility, SVG as alternative -->
    <link rel="icon" type="image/png" sizes="32x32" href="assets/favicons/favicon-32x32.png?v=<?php echo file_exists(ASSETS_PATH . '/favicons/favicon-32x32.png') ? filemtime(ASSETS_PATH . '/favicons/favicon-32x32.png') : ''; ?>">
    <link rel="icon" type="image/png" sizes="16x16" href="assets/favicons/favicon-16x16.png?v=<?php echo file_exists(ASSETS_PATH . '/favicons/favicon-16x16.png') ? filemtime(ASSETS_PATH . '/favicons/favicon-16x16.png') : ''; ?>">
    <link rel="icon" type="image/svg+xml" href="assets/favicons/favicon.svg?v=<?php echo file_exists(ASSETS_PATH . '/favicons/favicon.svg') ? filemtime(ASSETS_PATH . '/favicons/favicon.svg') : ''; ?>">
    <link rel="shortcut icon" href="assets/favicons/favicon.ico?v=<?php echo file_exists(ASSETS_PATH . '/favicons/favicon.ico') ? filemtime(ASSETS_PATH . '/favicons/favicon.ico') : ''; ?>">
    <link rel="apple-touch-icon" sizes="180x180" href="assets/favicons/apple-touch-icon.png?v=<?php echo file_exists(ASSETS_PATH . '/favicons/apple-touch-icon.png') ? filemtime(ASSETS_PATH . '/favicons/apple-touch-icon.png') : ''; ?>">
    <link rel="manifest" href="manifest.php">
    <meta name="theme-color" content="#000000">

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <!-- Sortable.js -->
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>
    
    <!-- html2pdf -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    
    <!-- Custom Config -->
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'system-ui', 'sans-serif'],
                    },
                }
            }
        }
        
        // Global config
        const APP_URL = '<?php echo APP_URL; ?>';
        const CSRF_TOKEN = '<?php echo Auth::csrfToken(); ?>';
    </script>
    
    <link rel="stylesheet" href="assets/css/app.css?v=<?php echo filemtime(dirname(__DIR__, 2) . '/assets/css/app.css'); ?>">
    <script src="assets/js/app.js?v=<?php echo filemtime(dirname(__DIR__, 2) . '/assets/js/app.js'); ?>"></script>

    <!-- Habit Timer Manager -->
    <script src="assets/js/habit-timer-manager.js?v=<?php echo filemtime(dirname(__DIR__, 2) . '/assets/js/habit-timer-manager.js'); ?>"></script>
</head>
<body class="min-h-screen bg-gray-50 font-sans overflow-x-hidden">
    <!-- Mobile Sidebar Overlay -->
    <div id="sidebar-overlay" class="fixed inset-0 bg-black/50 z-[55] hidden lg:hidden" onclick="toggleMobileSidebar()"></div>

    <div class="flex min-h-screen">
        <!-- Sidebar -->
        <?php include VIEWS_PATH . '/partials/sidebar.php'; ?>

        <!-- Main Content -->
        <div class="flex-1 flex flex-col">
            <!-- Header -->
            <?php include VIEWS_PATH . '/partials/header.php'; ?>

            <!-- Page Content -->
            <main class="flex-1">
                <div class="w-full max-w-none xl:px-8 py-8 animate-fade-in">
                    <?php include VIEWS_PATH . '/' . $page . '.php'; ?>
                </div>
            </main>
        </div>
    </div>

    <audio id="pomodoro-audio" preload="none" class="hidden"></audio>

    <div id="pomodoro-music-overlay" class="fixed bottom-4 right-4 z-[45] hidden">
        <div class="bg-white/95 backdrop-blur border border-gray-200 rounded-xl shadow-lg p-3 min-w-[280px] max-w-[360px]">
            <div class="flex items-center justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-[11px] uppercase tracking-wider font-semibold text-gray-500">Pomodoro Music</p>
                    <p id="pomodoro-overlay-track" class="text-sm font-medium text-gray-900 truncate">No track selected</p>
                </div>
                <a href="?page=pomodoro" class="text-xs font-semibold text-gray-600 hover:text-black transition">Open</a>
            </div>
            <div class="mt-2 flex items-center justify-between gap-3">
                <p id="pomodoro-overlay-status" class="text-xs text-gray-500">Ready</p>
                <p id="pomodoro-overlay-clock" class="text-base font-semibold text-gray-900 tabular-nums">25:00</p>
            </div>
            <div class="mt-3 flex items-center gap-2">
                <button id="pomodoro-overlay-prev" type="button" class="h-9 w-9 rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50 transition flex items-center justify-center" aria-label="Previous track" title="Previous">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 19l-7-7 7-7m9 14l-7-7 7-7"></path>
                    </svg>
                </button>
                <button id="pomodoro-overlay-play" type="button" class="h-9 w-9 rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50 transition flex items-center justify-center" aria-label="Play or pause music" title="Play / Pause">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-4.197-2.432A1 1 0 009 9.603v4.794a1 1 0 001.555.832l4.197-2.432a1 1 0 000-1.664z"></path>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </button>
                <button id="pomodoro-overlay-next" type="button" class="h-9 w-9 rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50 transition flex items-center justify-center" aria-label="Next track" title="Next">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 5l7 7-7 7M4 5l7 7-7 7"></path>
                    </svg>
                </button>
                <button id="pomodoro-overlay-stop" type="button" class="h-9 w-9 rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50 transition flex items-center justify-center" aria-label="Pause and hide overlay" title="Pause">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 9v6m4-6v6"></path>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </button>
                <input id="pomodoro-overlay-volume" type="range" min="0" max="1" step="0.05" class="flex-1 accent-black" aria-label="Music volume">
            </div>
            <div class="mt-2 flex items-center gap-2">
                <button id="pomodoro-overlay-timer-toggle" type="button" class="px-3 py-1.5 text-xs font-semibold border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition">Start</button>
                <button id="pomodoro-overlay-timer-reset" type="button" class="px-3 py-1.5 text-xs font-semibold border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition">Reset</button>
            </div>
            <div class="mt-2 flex items-center gap-2">
                <button id="pomodoro-overlay-move-up" type="button" class="h-8 w-8 rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50 transition flex items-center justify-center text-sm font-semibold" title="Move up" aria-label="Move overlay up">↑</button>
                <button id="pomodoro-overlay-move-left" type="button" class="h-8 w-8 rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50 transition flex items-center justify-center text-sm font-semibold" title="Move left" aria-label="Move overlay left">←</button>
                <button id="pomodoro-overlay-move-right" type="button" class="h-8 w-8 rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50 transition flex items-center justify-center text-sm font-semibold" title="Move right" aria-label="Move overlay right">→</button>
                <button id="pomodoro-overlay-move-down" type="button" class="h-8 w-8 rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50 transition flex items-center justify-center text-sm font-semibold" title="Move down" aria-label="Move overlay down">↓</button>
                <button id="pomodoro-overlay-move-reset" type="button" class="px-3 h-8 rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50 transition text-xs font-semibold" title="Reset position" aria-label="Reset overlay position">Reset Pos</button>
            </div>
        </div>
    </div>

    <!-- Modal Container -->
    <div id="modal-container" class="fixed inset-0 z-50 hidden">
        <div class="absolute inset-0 bg-black/50" onclick="closeModal()"></div>
        <div class="absolute inset-0 flex items-center justify-center p-4 pointer-events-none">
            <div id="modal-content" class="bg-white rounded-xl shadow-2xl max-w-lg w-full max-h-[90vh] overflow-auto pointer-events-auto"></div>
        </div>
    </div>

    <!-- Toast Container -->
    <div id="toast-container" class="fixed top-4 right-4 z-50 space-y-2"></div>

    <script>
        (function () {
            const usageKey = 'appTabUsage';
            const dateKey = 'appTabUsageDate';
            const today = new Date().toISOString().slice(0, 10);
            const params = new URLSearchParams(window.location.search);
            const pageName = params.get('page') || 'dashboard';
            let active = false;
            let startTime = Date.now();

            function loadUsage() {
                try {
                    return JSON.parse(localStorage.getItem(usageKey) || '{}');
                } catch (e) {
                    return {};
                }
            }

            function saveUsage(data) {
                localStorage.setItem(usageKey, JSON.stringify(data));
            }

            function resetIfNewDay() {
                if (localStorage.getItem(dateKey) !== today) {
                    localStorage.setItem(dateKey, today);
                    localStorage.setItem(usageKey, JSON.stringify({}));
                }
            }

            function addElapsed(ms) {
                if (!ms || ms < 0) return;
                const data = loadUsage();
                data[pageName] = (data[pageName] || 0) + ms;
                saveUsage(data);
            }

            function startTracking() {
                if (active) return;
                active = true;
                startTime = Date.now();
            }

            function stopTracking() {
                if (!active) return;
                const now = Date.now();
                addElapsed(now - startTime);
                active = false;
            }

            resetIfNewDay();
            if (!document.hidden) startTracking();

            document.addEventListener('visibilitychange', () => {
                if (document.hidden) {
                    stopTracking();
                } else {
                    startTracking();
                }
            });

            window.addEventListener('focus', startTracking);
            window.addEventListener('blur', stopTracking);
            window.addEventListener('beforeunload', stopTracking);
        })();
    </script>

    <script>
        function syncSidebarActiveState() {
            const params = new URLSearchParams(window.location.search);
            const pageName = params.get('page') || 'dashboard';
            const items = document.querySelectorAll('#sidebar .sidebar-item');
            items.forEach(item => {
                const itemPage = item.getAttribute('data-page');
                if (itemPage === pageName) {
                    item.classList.add('active', 'bg-black', 'text-white', 'font-medium');
                } else {
                    item.classList.remove('active', 'bg-black', 'text-white', 'font-medium');
                }
            });
        }

        document.addEventListener('DOMContentLoaded', syncSidebarActiveState);
    </script>

    <style>
        .animate-fade-in { animation: fadeIn 0.3s ease-out; }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .sidebar-item.active { background-color: #000; color: #fff; }
        .sidebar-item:not(.active):hover { background-color: #f9fafb; }

        /* Hide scrollbar but keep functionality */
        #sidebar {
            -ms-overflow-style: none;
            scrollbar-width: none;
        }
        #sidebar::-webkit-scrollbar {
            display: none;
        }

        /* Sidebar nav scrollbar */
        #sidebar nav {
            -ms-overflow-style: none;
            scrollbar-width: none;
        }
        #sidebar nav::-webkit-scrollbar {
            display: none;
        }

        .scrollbar-hide {
            -ms-overflow-style: none;  /* IE and Edge */
            scrollbar-width: none;  /* Firefox */
        }
        .scrollbar-hide::-webkit-scrollbar {
            display: none;  /* Chrome, Safari, Opera */
        }

        /* Mobile Sidebar Styles */
        @media (max-width: 1023px) {
            #sidebar {
                position: fixed;
                top: 0;
                left: 0;
                bottom: 0;
                z-[60] !important;
                transform: translateX(-100%);
                transition: transform 0.3s ease-in-out;
            }

            #sidebar.mobile-open {
                transform: translateX(0);
                z-index: 60 !important;
            }

            /* On mobile, sidebar scrolls internally without scrollbar */
            #sidebar nav {
                -ms-overflow-style: none;
                scrollbar-width: none;
            }
            #sidebar nav::-webkit-scrollbar {
                display: none;
            }
        }

        /* Responsive Tables */
        .table-responsive {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        /* Touch-friendly interactions */
        @media (hover: none) and (pointer: coarse) {
            button, a, input, select, textarea {
                min-height: 44px;
            }

            .sidebar-item {
                padding: 12px 16px;
            }
        }

        /* Spinner */
        .spinner {
            width: 24px;
            height: 24px;
            border: 2px solid #e5e7eb;
            border-top-color: #3b82f6;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Skeleton loading */
        .skeleton {
            background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
            background-size: 200% 100%;
            animation: shimmer 1.5s infinite;
            border-radius: 4px;
        }

        @keyframes shimmer {
            0% { background-position: 200% 0; }
            100% { background-position: -200% 0; }
        }

        /* Print styles */
        @media print {
            .no-print { display: none !important; }
            .sidebar, header, .modal-container, #toast-container { display: none !important; }
            body, main { background: white !important; }
        }

        /* Material Symbols Icons */
        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
            font-size: 24px;
            line-height: 1;
            vertical-align: middle;
        }
        .material-symbols-outlined.icon-sm { font-size: 18px; }
        .material-symbols-outlined.icon-lg { font-size: 32px; }
    </style>
</body>
</html>
