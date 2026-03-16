<?php
/**
 * Mobile Pomodoro Page
 */

if (!Auth::check()) {
    header('Location: ?page=login');
    exit;
}

$masterPassword = getMasterPassword();
if (empty($masterPassword)) {
    die('<html><body style="font-family: sans-serif; padding: 20px; text-align: center;">
        <h2>Session Error</h2>
        <p>Your session has expired or the master password is not available.</p>
        <p>Please <a href="?page=login">log in again</a>.</p>
    </body></html>');
}

try {
    $db = new Database($masterPassword, Auth::userId());
} catch (Exception $e) {
    die('<html><body style="font-family: sans-serif; padding: 20px; text-align: center;">
        <h2>Database Error</h2>
        <p>Failed to load data: ' . htmlspecialchars($e->getMessage()) . '</p>
        <p><a href="?page=dashboard">Return to dashboard</a></p>
    </body></html>');
}

$timerSessions = $db->load('pomodoro_sessions') ?? [];
$musicTracks = $db->load('pomodoro_music') ?? [];
$appFocusData = $db->load('pomodoro_page_focus') ?? [];

$today = date('Y-m-d');
$completedToday = 0;
$totalFocusSeconds = 0;

foreach ($timerSessions as $session) {
    $status = (string)($session['status'] ?? '');
    $duration = (int)($session['duration'] ?? 0);
    $sessionDateRaw = (string)($session['date'] ?? $session['createdAt'] ?? '');
    $sessionDate = $sessionDateRaw !== '' ? date('Y-m-d', strtotime($sessionDateRaw)) : '';

    if ($status === 'completed') {
        $totalFocusSeconds += $duration;
        if ($sessionDate === $today) {
            $completedToday++;
        }
    }
}

$formatDuration = static function (int $seconds): string {
    if ($seconds < 60) {
        return $seconds . 's';
    }
    $hours = (int)floor($seconds / 3600);
    $minutes = (int)floor(($seconds % 3600) / 60);
    $secs = $seconds % 60;
    if ($hours > 0) {
        return $hours . 'h ' . $minutes . 'm';
    }
    return $minutes . 'm ' . $secs . 's';
};

$siteName = getSiteName() ?? 'LazyMan';
$recentSessions = array_slice(array_reverse($timerSessions), 0, 8);
if (!is_array($appFocusData)) {
    $appFocusData = [];
}
$focusRows = array_slice($appFocusData, 0, 8);
if (empty($focusRows)) {
    $focusRows = [
        ['page' => 'Dashboard', 'seconds' => 0],
    ];
}
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0, viewport-fit=cover" name="viewport"/>
<title>Pomodoro Timer - <?= htmlspecialchars($siteName) ?></title>

<link rel="icon" type="image/svg+xml" href="<?= APP_URL ?>/assets/favicons/favicon.svg">
    <link rel="icon" type="image/png" sizes="32x32" href="<?= APP_URL ?>/assets/favicons/favicon-32x32.png"/>
<link rel="icon" type="image/png" sizes="16x16" href="<?= APP_URL ?>/assets/favicons/favicon-16x16.png"/>
<link rel="shortcut icon" href="<?= APP_URL ?>/assets/favicons/favicon.ico"/>
<link rel="apple-touch-icon" sizes="180x180" href="<?= APP_URL ?>/assets/favicons/apple-touch-icon.png"/>

<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@100;200;300;400;500;600;700;800;900&display=swap" rel="stylesheet"/>
<script id="tailwind-config">
    tailwind.config = {
        darkMode: "class",
        theme: {
            extend: {
                colors: {
                    primary: "#000000",
                    "background-light": "#ffffff",
                    "background-dark": "#0a0a0a",
                },
                fontFamily: {
                    display: ["Inter", "sans-serif"]
                },
            },
        },
    }
</script>
<style type="text/tailwindcss">
    @layer base {
        body {
            @apply bg-zinc-50 dark:bg-zinc-950 text-black dark:text-white font-display antialiased;
        }
        input[type="number"], input[type="text"] {
            @apply w-full bg-white dark:bg-zinc-900 border border-black dark:border-white rounded-none px-3 py-2 text-sm focus:ring-1 focus:ring-black dark:focus:ring-white focus:border-black dark:focus:border-white outline-none transition-all placeholder:text-zinc-400 font-medium;
        }
        label {
            @apply block text-[9px] font-black uppercase tracking-[0.2em] mb-1.5;
        }
    }
    .no-scrollbar::-webkit-scrollbar {
        display: none;
    }
    .no-scrollbar {
        -ms-overflow-style: none;
        scrollbar-width: none;
    }
    .preset-toggle input:checked + div {
        @apply bg-black dark:bg-white text-white dark:text-black;
    }
    .switch {
        @apply relative inline-flex h-5 w-9 items-center rounded-none border border-black dark:border-white transition-colors;
    }
    .switch-dot {
        @apply inline-block h-3 w-3 transform bg-black dark:bg-white transition-transform translate-x-1;
    }
    input:checked + .switch {
        @apply bg-black dark:bg-white;
    }
    input:checked + .switch .switch-dot {
        @apply translate-x-5 bg-white dark:bg-black;
    }
    .touch-target {
        min-height: 44px;
        min-width: 44px;
    }
    .timer-progress-shell {
        width: 17rem;
        height: 17rem;
        position: relative;
    }
    .timer-progress-ring {
        position: absolute;
        inset: 0;
        border-radius: 9999px;
        background: conic-gradient(#000000 0%, #e5e7eb 0%);
    }
    .timer-inner-circle {
        position: absolute;
        inset: 0.75rem;
        border-radius: 9999px;
        background: #000000;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.08);
    }
    @supports (-webkit-touch-callout: none) {
        input, textarea, select {
            font-size: 16px !important;
        }
    }
</style>
</head>
<body class="flex justify-center">
<div class="relative w-full max-w-[420px] min-h-screen bg-white dark:bg-zinc-950 shadow-2xl flex flex-col border-x border-zinc-100 dark:border-zinc-800 overflow-hidden">
<?php
$title = 'Pomodoro Timer';
$leftAction = 'menu';
$rightAction = 'none';
include MOBILE_VIEW_PATH . '/partials/header-mobile.php';
?>

<main class="flex-1 overflow-y-auto no-scrollbar px-6 pb-32 text-zinc-900 dark:text-zinc-100">
    <section class="py-8 flex flex-col items-center border-b border-zinc-100 dark:border-zinc-800">
        <div class="timer-progress-shell mb-5">
            <div id="timer-progress-ring" class="timer-progress-ring"></div>
            <div class="timer-inner-circle">
                <div id="timer-display" class="text-[62px] text-white font-black leading-none tracking-tighter tabular-nums">25:00</div>
                <div id="timer-state" class="mt-2 text-[10px] text-zinc-300 font-bold uppercase tracking-[0.3em]">Ready to focus</div>
            </div>
        </div>
        <div class="flex gap-4 mt-8 w-full max-w-[280px]">
            <button id="start-btn" type="button" onclick="toggleTimer()" class="flex-1 py-4 bg-black dark:bg-white text-white dark:text-black text-[11px] font-black uppercase tracking-[0.2em] hover:opacity-90 transition-opacity touch-target">
                Start
            </button>
            <button type="button" onclick="resetTimer()" class="flex-1 py-4 border border-black dark:border-white text-black dark:text-white text-[11px] font-black uppercase tracking-[0.2em] hover:bg-zinc-50 dark:hover:bg-zinc-900 transition-colors touch-target">
                Reset
            </button>
        </div>
    </section>

    <section class="py-6 border-b border-zinc-100 dark:border-zinc-800">
        <div class="flex flex-col gap-4">
            <div class="flex-1">
                <label>Timer Presets</label>
                <div class="flex gap-2">
                    <label class="preset-toggle flex-1 cursor-pointer">
                        <input checked class="hidden" name="preset" type="radio" value="25" onchange="changePreset(this.value)"/>
                        <div class="border border-black dark:border-white p-2 text-center text-[10px] font-bold uppercase tracking-widest transition-colors">25m</div>
                    </label>
                    <label class="preset-toggle flex-1 cursor-pointer">
                        <input class="hidden" name="preset" type="radio" value="15" onchange="changePreset(this.value)"/>
                        <div class="border border-black dark:border-white p-2 text-center text-[10px] font-bold uppercase tracking-widest transition-colors">15m</div>
                    </label>
                    <label class="preset-toggle flex-1 cursor-pointer">
                        <input class="hidden" name="preset" type="radio" value="5" onchange="changePreset(this.value)"/>
                        <div class="border border-black dark:border-white p-2 text-center text-[10px] font-bold uppercase tracking-widest transition-colors">5m</div>
                    </label>
                    <label class="preset-toggle flex-1 cursor-pointer">
                        <input class="hidden" name="preset" type="radio" value="custom" onchange="toggleCustomMode()"/>
                        <div class="border border-black dark:border-white p-2 text-center text-[10px] font-bold uppercase tracking-widest transition-colors">Custom</div>
                    </label>
                </div>
            </div>

            <!-- Custom Timer Inputs (hidden by default) -->
            <div id="custom-timer-section" class="hidden flex-col gap-3 border border-zinc-200 dark:border-zinc-700 p-3">
                <div class="flex gap-3">
                    <div class="flex-1">
                        <label for="custom-focus">Focus (min)</label>
                        <input id="custom-focus" type="number" min="1" max="180" value="25" onchange="applyCustomTimer()"/>
                    </div>
                    <div class="flex-1">
                        <label for="custom-break">Break (min)</label>
                        <input id="custom-break" type="number" min="1" max="60" value="5" onchange="applyCustomTimer()"/>
                    </div>
                </div>
                <button type="button" onclick="applyCustomTimer()" class="w-full py-2 bg-black dark:bg-white text-white dark:text-black text-[10px] font-black uppercase tracking-widest">Apply</button>
            </div>

            <!-- Default Break Input -->
            <div id="default-break-section" class="w-24">
                <label for="break-min">Break (min)</label>
                <input id="break-min" type="number" min="1" value="5"/>
            </div>
        </div>
    </section>

    <section class="py-6 grid grid-cols-3 gap-3">
        <div class="border border-zinc-100 dark:border-zinc-800 p-3 bg-zinc-50 dark:bg-zinc-900">
            <p class="text-[8px] font-bold uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Completed Today</p>
            <p id="completed-today" class="text-xl font-black mt-1"><?= (int)$completedToday ?></p>
        </div>
        <div class="border border-zinc-100 dark:border-zinc-800 p-3 bg-zinc-50 dark:bg-zinc-900">
            <p class="text-[8px] font-bold uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Current Session</p>
            <p id="current-session" class="text-xl font-black mt-1">0m</p>
        </div>
        <div class="border border-zinc-100 dark:border-zinc-800 p-3 bg-zinc-50 dark:bg-zinc-900">
            <p class="text-[8px] font-bold uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Focus Time</p>
            <p id="total-focus-time" class="text-xl font-black mt-1"><?= htmlspecialchars($formatDuration((int)$totalFocusSeconds)) ?></p>
        </div>
    </section>

    <section class="py-6 border-y border-zinc-100 dark:border-zinc-800">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-[10px] font-black uppercase tracking-[0.2em]">Focus Music</h3>
            <div class="flex items-center gap-2">
                <button type="button" onclick="openMusicLibrary()" class="border border-black dark:border-white text-black dark:text-white px-3 py-1.5 flex items-center gap-1.5 hover:bg-zinc-50 dark:hover:bg-zinc-900 transition-colors touch-target">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zM9 10l12-3"/></svg>
                    <span class="text-[9px] font-bold uppercase tracking-widest">Library</span>
                </button>
                <button type="button" onclick="toggleMusicPlayback()" id="music-play-btn" class="border border-black dark:border-white text-black dark:text-white px-3 py-1.5 flex items-center gap-1.5 hover:bg-zinc-50 dark:hover:bg-zinc-900 transition-colors touch-target">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <span class="text-[9px] font-bold uppercase tracking-widest" id="music-play-text">Play</span>
                </button>
                <input id="music-file-input" type="file" accept="audio/*" class="hidden" onchange="uploadMusic(this)"/>
                <button type="button" onclick="document.getElementById('music-file-input').click()" class="bg-black dark:bg-white text-white dark:text-black px-3 py-1.5 flex items-center gap-1.5 hover:opacity-90 transition-opacity touch-target">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1M8 12l4 4m0 0l4-4m-4 4V4"/></svg>
                    <span class="text-[9px] font-bold uppercase tracking-widest">Upload</span>
                </button>
            </div>
        </div>
        <div class="border border-black dark:border-white p-4 mb-4">
            <div id="music-list" class="space-y-3"></div>
            <div class="space-y-4 mt-3">
                <div class="flex items-center justify-between">
                    <span class="text-[9px] font-bold uppercase tracking-widest">Play while running</span>
                    <label class="cursor-pointer">
                        <input id="play-while-running" checked class="hidden" type="checkbox"/>
                        <div class="switch"><div class="switch-dot"></div></div>
                    </label>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-[9px] font-bold uppercase tracking-widest">Loop Track</span>
                    <label class="cursor-pointer">
                        <input id="loop-track" class="hidden" type="checkbox" onchange="updateAudioSettings()"/>
                        <div class="switch"><div class="switch-dot"></div></div>
                    </label>
                </div>
                <div class="flex items-center gap-3">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5L6 9H3v6h3l5 4V5zm4.54 3.46a5 5 0 010 7.08m2.83-9.9a9 9 0 010 12.72"/></svg>
                    <input id="music-volume" class="flex-1 accent-black dark:accent-white h-px bg-zinc-200 dark:bg-zinc-700 appearance-none cursor-pointer" type="range" min="0" max="1" step="0.05" value="0.6" onchange="updateAudioSettings()"/>
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5L6 9H3v6h3l5 4V5zm8 1a11 11 0 010 16m-3-13a7 7 0 010 10"/></svg>
                </div>
            </div>
        </div>
    </section>

    <section class="py-6">
        <h3 class="text-[10px] font-black uppercase tracking-[0.2em] mb-4">App Focus Tracker</h3>
        <div class="border border-black dark:border-white overflow-hidden">
            <table class="w-full text-left">
                <thead>
                    <tr class="bg-black dark:bg-white text-white dark:text-black">
                        <th class="px-4 py-2 text-[9px] font-bold uppercase tracking-widest">Page</th>
                        <th class="px-4 py-2 text-[9px] font-bold uppercase tracking-widest text-right">Time</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    <?php foreach ($focusRows as $row): ?>
                        <?php
                        $pageName = is_scalar($row['page'] ?? null) ? (string)$row['page'] : 'Unknown';
                        $seconds = (int)($row['seconds'] ?? 0);
                        ?>
                        <tr>
                            <td class="px-4 py-3 text-[11px] font-medium"><?= htmlspecialchars($pageName) ?></td>
                            <td class="px-4 py-3 text-[11px] font-black text-right"><?= htmlspecialchars($formatDuration($seconds)) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="py-6">
        <h3 class="text-[10px] font-black uppercase tracking-[0.2em] mb-4">Recent Sessions</h3>
        <div class="border border-black dark:border-white overflow-hidden">
            <table class="w-full text-left">
                <thead class="bg-zinc-50 dark:bg-zinc-900 border-b border-black dark:border-white">
                    <tr>
                        <th class="px-4 py-2 text-[8px] font-bold uppercase tracking-widest">ID</th>
                        <th class="px-4 py-2 text-[8px] font-bold uppercase tracking-widest">Date</th>
                        <th class="px-4 py-2 text-[8px] font-bold uppercase tracking-widest text-right">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    <?php if (empty($recentSessions)): ?>
                        <tr>
                            <td colspan="3" class="px-4 py-5 text-center text-[10px] uppercase tracking-widest text-zinc-500">No sessions yet</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($recentSessions as $index => $session): ?>
                            <?php
                            $sessionStatus = (string)($session['status'] ?? 'unknown');
                            $sessionDate = (string)($session['date'] ?? $session['createdAt'] ?? '');
                            $sessionDuration = (int)($session['duration'] ?? 0);
                            $sessionLabel = 'Session #' . ($index + 1);
                            ?>
                            <tr>
                                <td class="px-4 py-3">
                                    <div class="text-[10px] font-black uppercase"><?= htmlspecialchars($sessionLabel) ?></div>
                                    <div class="text-[9px] text-zinc-400 uppercase tracking-widest"><?= htmlspecialchars($formatDuration($sessionDuration)) ?></div>
                                </td>
                                <td class="px-4 py-3 text-[10px] font-medium uppercase"><?= htmlspecialchars($sessionDate ? date('M d, Y', strtotime($sessionDate)) : '-') ?></td>
                                <td class="px-4 py-3 text-right">
                                    <span class="text-[8px] font-black uppercase tracking-widest px-2 py-0.5 border border-black dark:border-white"><?= htmlspecialchars($sessionStatus) ?></span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <!-- Music Library Modal -->
    <div id="music-library-modal" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 hidden items-center justify-center p-4">
        <div class="bg-white dark:bg-zinc-900 rounded-none border border-black dark:border-white w-full max-w-md max-h-[85vh] overflow-hidden flex flex-col">
            <div class="flex items-center justify-between p-4 border-b border-black dark:border-white">
                <h3 class="text-[12px] font-black uppercase tracking-[0.2em]">Music Library</h3>
                <button type="button" onclick="closeMusicLibrary()" class="p-2 hover:bg-zinc-100 dark:hover:bg-zinc-800">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <div class="p-4 border-b border-black dark:border-white bg-zinc-50 dark:bg-zinc-800">
                <input id="library-music-file" type="file" accept="audio/*" class="hidden" onchange="uploadLibraryMusic(this)"/>
                <button type="button" onclick="document.getElementById('library-music-file').click()" class="w-full py-3 bg-black dark:bg-white text-white dark:text-black text-[10px] font-black uppercase tracking-widest flex items-center justify-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1M8 12l4 4m0 0l4-4m-4 4V4"/></svg>
                    Upload New Track
                </button>
            </div>

            <div class="flex-1 overflow-y-auto p-4">
                <div id="music-library-list" class="space-y-2"></div>
            </div>

            <div class="p-4 border-t border-black dark:border-white bg-zinc-50 dark:bg-zinc-800 flex justify-between items-center">
                <span id="library-track-count" class="text-[10px] text-zinc-500">0 tracks</span>
                <button type="button" onclick="closeMusicLibrary()" class="px-4 py-2 border border-black dark:border-white text-[10px] font-bold uppercase tracking-widest">Close</button>
            </div>
        </div>
    </div>
</main>

<?php
$activePage = 'settings';
include MOBILE_VIEW_PATH . '/partials/bottom-nav.php';
?>
<?php include MOBILE_VIEW_PATH . '/partials/offcanvas-menu.php'; ?>
<div class="absolute bottom-2 left-1/2 -translate-x-1/2 w-12 h-1 bg-zinc-200 dark:bg-zinc-700 rounded-full z-40"></div>
</div>

<script>
    (function() {
        const path = window.location.pathname;
        let basePath = path;
        
        // Remove /index.php
        basePath = basePath.replace(/\/index\.php$/i, '');
        
        // Remove trailing slash
        basePath = basePath.replace(/\/+$/, '');
        
        // Remove /mobile segment to get app root
        basePath = basePath.replace(/\/mobile$/i, '');
        
        window.BASE_PATH = basePath;
    })();
    const APP_URL = window.location.origin + window.BASE_PATH;
    const CSRF_TOKEN = '<?= $_SESSION['csrf_token'] ?? '' ?>';
    const INITIAL_COMPLETED_TODAY = <?= (int)$completedToday ?>;
    const INITIAL_TOTAL_FOCUS_SECONDS = <?= (int)$totalFocusSeconds ?>;
    const INITIAL_MUSIC_TRACKS = <?= json_encode($musicTracks) ?>;
</script>
<script src="mobile/assets/js/pomodoro-audio.js"></script>
<script src="<?= MOBILE_JS_URL ?>/mobile.js"></script>

<script>
let selectedMinutes = 25;
let timeLeft = selectedMinutes * 60;
let isRunning = false;
let timerInterval = null;
let sessionStartedAt = null;
let completedTodayCount = Number(INITIAL_COMPLETED_TODAY) || 0;
let totalFocusSeconds = Number(INITIAL_TOTAL_FOCUS_SECONDS) || 0;
let musicTracks = Array.isArray(INITIAL_MUSIC_TRACKS) ? INITIAL_MUSIC_TRACKS : [];
let activeTrackId = null;
let musicPermissionMap = {};

// localStorage keys
const CUSTOM_FOCUS_KEY = 'pomodoroCustomFocus';
const CUSTOM_BREAK_KEY = 'pomodoroCustomBreak';
const CUSTOM_MODE_KEY = 'pomodoroCustomMode';
const POMODORO_STATE_KEY = 'pomodoroStateV2';
const POMODORO_MUSIC_ID_KEY = 'pomodoroMusicId';
const POMODORO_MUSIC_AUTO_KEY = 'pomodoroMusicAuto';
const POMODORO_MUSIC_LOOP_KEY = 'pomodoroMusicLoop';
const POMODORO_MUSIC_VOLUME_KEY = 'pomodoroMusicVolume';
const POMODORO_MUSIC_PLAYING_KEY = 'pomodoroMusicPlaying';
const POMODORO_MUSIC_TIME_KEY = 'pomodoroMusicTime';
const POMODORO_MUSIC_TRACK_CACHE_KEY = 'pomodoroMusicTracksCache';
const POMODORO_MUSIC_TRACK_ORDER_KEY = 'pomodoroMusicTrackOrder';
const POMODORO_MUSIC_MANUAL_PAUSE_KEY = 'pomodoroMusicManualPause';

// Initialize Audio Manager
document.addEventListener('DOMContentLoaded', () => {
    if (typeof PomodoroAudioManager !== 'undefined') {
        PomodoroAudioManager.initialize({
            debug: true
        });
        
        const audioEl = document.getElementById('pomodoro-audio');
        if (audioEl) {
            PomodoroAudioManager.setAudioElement(audioEl);
            const loopTrack = document.getElementById('loop-track');
            const volumeEl = document.getElementById('music-volume');
            const savedLoop = localStorage.getItem(POMODORO_MUSIC_LOOP_KEY) === '1';
            const savedVolume = parseFloat(localStorage.getItem(POMODORO_MUSIC_VOLUME_KEY) || String(volumeEl?.value || 0.6));
            if (loopTrack) {
                loopTrack.checked = savedLoop || loopTrack.checked;
                audioEl.loop = !!loopTrack.checked;
            }
            if (Number.isFinite(savedVolume)) {
                audioEl.volume = Math.min(1, Math.max(0, savedVolume));
                if (volumeEl) {
                    volumeEl.value = String(audioEl.volume);
                }
            }
            audioEl.addEventListener('play', updateMusicPlayButton);
            audioEl.addEventListener('pause', updateMusicPlayButton);
            audioEl.addEventListener('timeupdate', () => {
                localStorage.setItem(POMODORO_MUSIC_TIME_KEY, String(audioEl.currentTime || 0));
            });
            audioEl.addEventListener('play', () => {
                localStorage.setItem(POMODORO_MUSIC_PLAYING_KEY, '1');
                localStorage.setItem(POMODORO_MUSIC_MANUAL_PAUSE_KEY, '0');
            });
            audioEl.addEventListener('pause', () => {
                localStorage.setItem(POMODORO_MUSIC_PLAYING_KEY, '0');
            });
            audioEl.addEventListener('ended', () => {
                if (!audioEl.loop) updateMusicPlayButton();
                localStorage.setItem(POMODORO_MUSIC_PLAYING_KEY, '0');
            });
        }
    }
});

function waitMs(ms) {
    return new Promise((resolve) => setTimeout(resolve, ms));
}

function formatClock(seconds) {
    const mins = Math.floor(seconds / 60);
    const secs = seconds % 60;
    return `${String(mins).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
}

function formatDuration(seconds) {
    if (seconds < 60) {
        return `${seconds}s`;
    }
    const hours = Math.floor(seconds / 3600);
    const minutes = Math.floor((seconds % 3600) / 60);
    const secs = seconds % 60;
    if (hours > 0) {
        return `${hours}h ${minutes}m`;
    }
    return `${minutes}m ${secs}s`;
}

function syncSharedPomodoroState() {
    const focusMinutes = parseInt(localStorage.getItem(CUSTOM_FOCUS_KEY) || String(selectedMinutes || 25), 10) || 25;
    const breakMinutes = parseInt(localStorage.getItem(CUSTOM_BREAK_KEY) || '5', 10) || 5;
    const phase = selectedMinutes === breakMinutes ? 'break' : 'focus';
    const payload = {
        phase: isRunning ? phase : (timeLeft === focusMinutes * 60 ? 'idle' : phase),
        running: !!isRunning,
        secondsLeft: Math.max(0, parseInt(timeLeft || 0, 10)),
        focusMinutes,
        breakMinutes,
        lastTick: isRunning ? Date.now() : null,
        awaitingNextFocus: !isRunning && selectedMinutes === breakMinutes && timeLeft === breakMinutes * 60
    };
    localStorage.setItem(POMODORO_STATE_KEY, JSON.stringify(payload));
}

function initializeStateFromSharedPomodoro() {
    try {
        const raw = localStorage.getItem(POMODORO_STATE_KEY);
        if (!raw) return;
        const state = JSON.parse(raw);
        if (!state || typeof state !== 'object') return;
        const focusMinutes = parseInt(state.focusMinutes || localStorage.getItem(CUSTOM_FOCUS_KEY) || '25', 10) || 25;
        const breakMinutes = parseInt(state.breakMinutes || localStorage.getItem(CUSTOM_BREAK_KEY) || '5', 10) || 5;
        const secondsLeft = parseInt(state.secondsLeft || focusMinutes * 60, 10) || focusMinutes * 60;
        if (state.phase === 'break') {
            selectedMinutes = breakMinutes;
            timeLeft = secondsLeft;
        } else if (state.phase === 'focus') {
            selectedMinutes = focusMinutes;
            timeLeft = secondsLeft;
        }
    } catch (error) {
    }
}

function updateDisplay() {
    const timerEl = document.getElementById('timer-display');
    const stateEl = document.getElementById('timer-state');
    const startBtn = document.getElementById('start-btn');
    const timerProgressEl = document.getElementById('timer-progress-ring');
    const currentSessionEl = document.getElementById('current-session');
    const completedTodayEl = document.getElementById('completed-today');
    const totalFocusEl = document.getElementById('total-focus-time');

    if (timerEl) timerEl.textContent = formatClock(timeLeft);
    if (stateEl) {
        if (isRunning) {
            stateEl.textContent = 'Focus in progress';
        } else if (timeLeft === selectedMinutes * 60) {
            stateEl.textContent = 'Ready to focus';
        } else {
            stateEl.textContent = 'Paused';
        }
    }

    if (startBtn) {
        startBtn.textContent = isRunning ? 'Pause' : 'Start';
    }

    const elapsed = Math.max(0, (selectedMinutes * 60) - timeLeft);
    const totalSeconds = Math.max(1, selectedMinutes * 60);
    const progressPercent = Math.max(0, Math.min(100, (elapsed / totalSeconds) * 100));

    if (timerProgressEl) {
        timerProgressEl.style.background = `conic-gradient(#000000 ${progressPercent}%, #e5e7eb ${progressPercent}% 100%)`;
    }

    if (currentSessionEl) {
        currentSessionEl.textContent = `${Math.floor(elapsed / 60)}m`;
    }
    if (completedTodayEl) {
        completedTodayEl.textContent = String(completedTodayCount);
    }
    if (totalFocusEl) {
        totalFocusEl.textContent = formatDuration(totalFocusSeconds);
    }
    syncSharedPomodoroState();
}

// Custom Timer Functions
function toggleCustomMode() {
    const customSection = document.getElementById('custom-timer-section');
    const defaultBreakSection = document.getElementById('default-break-section');
    const isCustom = !customSection.classList.contains('hidden');

    if (!isCustom) {
        customSection.classList.remove('hidden');
        customSection.classList.add('flex');
        defaultBreakSection.classList.add('hidden');
        localStorage.setItem(CUSTOM_MODE_KEY, '1');

        // Load saved custom values
        const savedFocus = localStorage.getItem(CUSTOM_FOCUS_KEY) || '25';
        const savedBreak = localStorage.getItem(CUSTOM_BREAK_KEY) || '5';
        document.getElementById('custom-focus').value = savedFocus;
        document.getElementById('custom-break').value = savedBreak;
    } else {
        customSection.classList.add('hidden');
        customSection.classList.remove('flex');
        defaultBreakSection.classList.remove('hidden');
        localStorage.removeItem(CUSTOM_MODE_KEY);
    }
}

function applyCustomTimer() {
    const focusInput = document.getElementById('custom-focus');
    const breakInput = document.getElementById('custom-break');

    let focusMinutes = parseInt(focusInput?.value, 10);
    let breakMinutes = parseInt(breakInput?.value, 10);

    // Validate
    if (!Number.isFinite(focusMinutes) || focusMinutes < 1) focusMinutes = 25;
    if (!Number.isFinite(breakMinutes) || breakMinutes < 1) breakMinutes = 5;
    if (focusMinutes > 180) focusMinutes = 180;
    if (breakMinutes > 60) breakMinutes = 60;

    // Save to localStorage
    localStorage.setItem(CUSTOM_FOCUS_KEY, String(focusMinutes));
    localStorage.setItem(CUSTOM_BREAK_KEY, String(breakMinutes));

    // Update inputs
    if (focusInput) focusInput.value = focusMinutes;
    if (breakInput) breakInput.value = breakMinutes;

    // Apply to timer
    selectedMinutes = focusMinutes;
    timeLeft = selectedMinutes * 60;
    updateDisplay();

    Mobile.ui.showToast(`Timer: ${focusMinutes}min focus, ${breakMinutes}min break`, 'success');
}

function loadCustomSettings() {
    const isCustomMode = localStorage.getItem(CUSTOM_MODE_KEY);
    if (isCustomMode) {
        const savedFocus = parseInt(localStorage.getItem(CUSTOM_FOCUS_KEY) || '25', 10);
        const savedBreak = parseInt(localStorage.getItem(CUSTOM_BREAK_KEY) || '5', 10);

        // Set custom mode UI
        const customRadio = document.querySelector('input[name="preset"][value="custom"]');
        if (customRadio) {
            customRadio.checked = true;
            toggleCustomMode();
        }

        // Apply values
        const focusInput = document.getElementById('custom-focus');
        const breakInput = document.getElementById('custom-break');
        if (focusInput) focusInput.value = savedFocus;
        if (breakInput) breakInput.value = savedBreak;

        selectedMinutes = savedFocus;
        timeLeft = selectedMinutes * 60;
    }
}

function changePreset(minutes) {
    if (minutes === 'custom') {
        toggleCustomMode();
        return;
    }

    // Hide custom section when selecting presets
    const customSection = document.getElementById('custom-timer-section');
    const defaultBreakSection = document.getElementById('default-break-section');
    customSection.classList.add('hidden');
    customSection.classList.remove('flex');
    defaultBreakSection.classList.remove('hidden');
    localStorage.removeItem(CUSTOM_MODE_KEY);

    const parsed = Number(minutes);
    if (!Number.isFinite(parsed) || parsed <= 0) {
        return;
    }
    selectedMinutes = parsed;
    resetTimer();
}

function toggleTimer() {
    // Unlock audio context on interaction
    if (typeof PomodoroAudioManager !== 'undefined') {
        PomodoroAudioManager.markUserInteraction();
    }

    if (isRunning) {
        pauseTimer();
    } else {
        startTimer();
    }
}

function startTimer() {
    if (isRunning) {
        return;
    }
    isRunning = true;
    if (!sessionStartedAt) {
        sessionStartedAt = Date.now();
    }

    // Unlock audio
    if (typeof PomodoroAudioManager !== 'undefined') {
        PomodoroAudioManager.markUserInteraction();
        ensurePomodoroAutoplayOnStart();
    }

    timerInterval = setInterval(() => {
        timeLeft -= 1;
        if (timeLeft <= 0) {
            timeLeft = 0;
            updateDisplay();
            completeTimer();
            return;
        }
        updateDisplay();
    }, 1000);

    updateDisplay();
}

function pauseTimer() {
    isRunning = false;
    if (timerInterval) {
        clearInterval(timerInterval);
        timerInterval = null;
    }

    // Pause audio if playing and "Play while running" is checked
    const playWhileRunning = document.getElementById('play-while-running');
    if (playWhileRunning && playWhileRunning.checked && typeof PomodoroAudioManager !== 'undefined') {
        PomodoroAudioManager.pauseTrack();
    }

    updateDisplay();
}

function resetTimer() {
    pauseTimer();
    timeLeft = selectedMinutes * 60;
    sessionStartedAt = null;
    updateDisplay();
}

async function completeTimer() {
    pauseTimer();

    const elapsed = Math.max(1, (selectedMinutes * 60));
    completedTodayCount += 1;
    totalFocusSeconds += elapsed;
    updateDisplay();

    // Play completion sound
    if (typeof PomodoroAudioManager !== 'undefined') {
        PomodoroAudioManager.playCompletionSound();
    }

    try {
        await App.api.post('api/pomodoro.php?action=complete', {
            mode: selectedMinutes,
            duration: elapsed,
            csrf_token: CSRF_TOKEN
        });
        Mobile.ui.showToast('Pomodoro session completed.', 'success');
    } catch (error) {
        Mobile.ui.showToast('Session saved locally, sync failed.', 'warning');
    }

    // Switch to break time
    const breakInput = document.getElementById('break-min');
    const customBreakInput = document.getElementById('custom-break');
    const isCustomMode = localStorage.getItem(CUSTOM_MODE_KEY);

    let breakMinutes;
    if (isCustomMode && customBreakInput) {
        breakMinutes = parseInt(customBreakInput.value, 10);
    } else if (breakInput) {
        breakMinutes = parseInt(breakInput.value, 10);
    }

    if (!Number.isFinite(breakMinutes) || breakMinutes < 1) breakMinutes = 5;

    selectedMinutes = breakMinutes;
    timeLeft = selectedMinutes * 60;
    sessionStartedAt = null;
    updateDisplay();

    Mobile.ui.showToast(`Break time: ${breakMinutes} minutes`, 'info');
}

function updateAudioSettings() {
    const audioEl = document.getElementById('pomodoro-audio');
    if (!audioEl) return;
    
    const loopTrack = document.getElementById('loop-track');
    const volumeEl = document.getElementById('music-volume');
    
    audioEl.loop = !!(loopTrack && loopTrack.checked);
    audioEl.volume = Math.min(1, Math.max(0, Number(volumeEl?.value || 0.6)));
    localStorage.setItem(POMODORO_MUSIC_LOOP_KEY, audioEl.loop ? '1' : '0');
    localStorage.setItem(POMODORO_MUSIC_VOLUME_KEY, String(audioEl.volume));
}

function updateMusicPlayButton() {
    const btnText = document.getElementById('music-play-text');
    const audioEl = document.getElementById('pomodoro-audio');
    if (btnText && audioEl) {
        btnText.textContent = (!audioEl.paused) ? 'Pause' : 'Play';
    }
}

function toggleMusicPlayback() {
    if (typeof PomodoroAudioManager !== 'undefined') {
        PomodoroAudioManager.markUserInteraction();
    }

    if (!activeTrackId) {
        Mobile.ui.showToast('Select a track first', 'info');
        return;
    }

    if (typeof PomodoroAudioManager !== 'undefined') {
        PomodoroAudioManager.togglePlayback(activeTrackId).catch(err => {
            console.error('Playback toggle failed', err);
        });
    }
}

function pickRandomTrackId() {
    if (!Array.isArray(musicTracks) || musicTracks.length === 0) {
        return null;
    }
    const randomTrack = musicTracks[Math.floor(Math.random() * musicTracks.length)];
    return String(randomTrack?.id || '');
}

function getTrackById(trackId) {
    return musicTracks.find((item) => String(item.id) === String(trackId)) || null;
}

function getFirstAvailableTrackId(excludeId = null) {
    for (const track of musicTracks) {
        const id = String(track?.id || '');
        if (!id) {
            continue;
        }
        if (excludeId !== null && id === String(excludeId)) {
            continue;
        }
        return id;
    }
    return null;
}

async function ensurePomodoroAutoplayOnStart() {
    const playWhileRunning = document.getElementById('play-while-running');
    if (!(playWhileRunning && playWhileRunning.checked)) {
        localStorage.setItem(POMODORO_MUSIC_AUTO_KEY, '0');
        return;
    }
    localStorage.setItem(POMODORO_MUSIC_AUTO_KEY, '1');
    if (!musicTracks.length) {
        return;
    }

    if (!activeTrackId) {
        activeTrackId = pickRandomTrackId() || getFirstAvailableTrackId();
    }
    
    if (!activeTrackId) {
        return;
    }

    renderMusicTracks();
    localStorage.setItem(POMODORO_MUSIC_ID_KEY, activeTrackId);

    if (typeof PomodoroAudioManager !== 'undefined') {
        const result = await PomodoroAudioManager.playTrack(activeTrackId, {
            mutedFallback: true,
            showFailureToast: false
        });
        
        if (!result.success && result.message) {
             Mobile.ui.showToast(result.message, 'warning');
        }
    }
}

// Mobile Audio Persistence - Handle visibility changes
document.addEventListener('visibilitychange', () => {
    if (document.hidden) {
        // Page is hidden
    } else {
        // Page is visible again
        if (typeof PomodoroAudioManager !== 'undefined') {
             // Resume audio context if suspended
             PomodoroAudioManager.initializeAudioContext();
        }
        
        // Check if we need to restart audio
        const audioEl = document.getElementById('pomodoro-audio');
        if (isRunning && activeTrackId && audioEl && audioEl.paused) {
            const playWhileRunning = document.getElementById('play-while-running');
            if (playWhileRunning && playWhileRunning.checked) {
                audioEl.play().catch(() => {});
            }
        }
    }
});

// Handle beforeunload to save state
window.addEventListener('beforeunload', () => {
    const audioEl = document.getElementById('pomodoro-audio');
    if (audioEl) {
        localStorage.setItem('pomodoroAudioTime', String(audioEl.currentTime || 0));
    }
});

function renderMusicTracks() {
    const list = document.getElementById('music-list');
    if (!list) {
        return;
    }

    if (!musicTracks.length) {
        list.innerHTML = '<p class="text-[10px] uppercase tracking-widest text-zinc-500">No tracks uploaded yet</p>';
        localStorage.setItem(POMODORO_MUSIC_TRACK_CACHE_KEY, JSON.stringify({}));
        localStorage.setItem(POMODORO_MUSIC_TRACK_ORDER_KEY, JSON.stringify([]));
        return;
    }

    if (!activeTrackId) {
        activeTrackId = String(localStorage.getItem(POMODORO_MUSIC_ID_KEY) || musicTracks[0].id || '');
    }
    const trackCache = {};
    const trackOrder = [];
    musicPermissionMap = {};
    musicTracks.forEach((track) => {
        const id = String(track?.id || '');
        if (!id) return;
        trackCache[id] = String(track?.name || 'Track');
        trackOrder.push(id);
        musicPermissionMap[id] = {
            canDelete: !!track?.canDelete,
            canRename: !!track?.canRename
        };
    });
    localStorage.setItem(POMODORO_MUSIC_TRACK_CACHE_KEY, JSON.stringify(trackCache));
    localStorage.setItem(POMODORO_MUSIC_TRACK_ORDER_KEY, JSON.stringify(trackOrder));
    if (activeTrackId && trackCache[activeTrackId]) {
        localStorage.setItem(POMODORO_MUSIC_ID_KEY, activeTrackId);
    }

    list.innerHTML = musicTracks.map((track) => {
        const id = String(track.id || '');
        const name = String(track.name || 'Track');
        const isActive = id === String(activeTrackId);
        const canDelete = !!track.canDelete;
        return `
            <div class="border ${isActive ? 'border-black dark:border-white' : 'border-zinc-200 dark:border-zinc-800'} p-3">
                <div class="flex items-center justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-xs font-black uppercase truncate">${escapeHtml(name)}</p>
                        <p class="text-[9px] text-zinc-400 uppercase tracking-widest">${(track.mime || '').toString().replace('audio/', '') || 'audio'}</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <button type="button" onclick="selectTrack('${id}')" class="border border-black dark:border-white px-2 py-1 text-[9px] font-black uppercase tracking-widest touch-target">Play</button>
                        ${canDelete ? `<button type="button" onclick="deleteTrack('${id}')" class="border border-red-500 text-red-600 px-2 py-1 text-[9px] font-black uppercase tracking-widest touch-target">Del</button>` : ''}
                    </div>
                </div>
            </div>
        `;
    }).join('');
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function selectTrack(trackId) {
    activeTrackId = String(trackId || '');
    localStorage.setItem(POMODORO_MUSIC_ID_KEY, activeTrackId);
    localStorage.setItem(POMODORO_MUSIC_TIME_KEY, '0');
    renderMusicTracks();
    if (typeof PomodoroAudioManager !== 'undefined') {
        PomodoroAudioManager.playTrack(activeTrackId);
    }
}

async function uploadMusic(input) {
    const file = input?.files?.[0];
    if (!file) {
        return;
    }

    const formData = new FormData();
    formData.append('file', file);

    try {
        const response = await fetch(`${APP_URL}/api/pomodoro.php?action=music_upload`, {
            method: 'POST',
            headers: {
                'X-CSRF-Token': CSRF_TOKEN
            },
            body: formData
        });

        const result = await response.json();

        if (!response.ok || !result.success) {
            throw new Error(result?.error?.message || 'Upload failed');
        }

        musicTracks.push(result.data);
        activeTrackId = String(result.data.id || '');
        renderMusicTracks();
        Mobile.ui.showToast('Track uploaded.', 'success');
    } catch (error) {
        Mobile.ui.showToast(error.message || 'Failed to upload track.', 'error');
    } finally {
        input.value = '';
    }
}

async function deleteTrack(trackId) {
    if (!musicPermissionMap[String(trackId)]?.canDelete) {
        Mobile.ui.showToast('You can only delete tracks you uploaded.', 'warning');
        return;
    }
    if (!confirm('Delete this track?')) {
        return;
    }

    try {
        const response = await App.api.post('api/pomodoro.php?action=music_delete', {
            id: trackId,
            csrf_token: CSRF_TOKEN
        });
        if (!response.success) {
            throw new Error('Delete failed');
        }

        musicTracks = musicTracks.filter((item) => String(item.id) !== String(trackId));
        if (String(activeTrackId) === String(trackId)) {
            activeTrackId = musicTracks.length ? String(musicTracks[0].id || '') : null;
            if (typeof PomodoroAudioManager !== 'undefined') {
                PomodoroAudioManager.pauseTrack();
            }
        }
        renderMusicTracks();
        Mobile.ui.showToast('Track deleted.', 'success');
    } catch (error) {
        Mobile.ui.showToast('Failed to delete track.', 'error');
    }
}

// Music Library Functions
function openMusicLibrary() {
    const modal = document.getElementById('music-library-modal');
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    loadMusicLibrary();
}

function closeMusicLibrary() {
    const modal = document.getElementById('music-library-modal');
    modal.classList.add('hidden');
    modal.classList.remove('flex');
}

async function loadMusicLibrary() {
    const list = document.getElementById('music-library-list');
    const countEl = document.getElementById('library-track-count');

    try {
        const response = await fetch(`${APP_URL}/api/pomodoro.php?action=music_list`);
        const result = await response.json();
        const tracks = result.data || [];

        musicTracks = tracks;
        renderMusicTracks();

        countEl.textContent = `${tracks.length} track${tracks.length !== 1 ? 's' : ''}`;

        if (!tracks.length) {
            list.innerHTML = '<p class="text-[10px] uppercase tracking-widest text-zinc-500 text-center py-8">No tracks yet</p>';
            return;
        }

        list.innerHTML = tracks.map((track) => {
            const id = String(track.id || '');
            const name = String(track.name || 'Track');
            const sizeMB = ((track.size || 0) / (1024 * 1024)).toFixed(1);
            const date = track.uploadedAt ? new Date(track.uploadedAt).toLocaleDateString() : 'Unknown';
            const isActive = id === String(activeTrackId);
            const canDelete = !!track.canDelete;

            return `
                <div class="flex items-center justify-between p-3 border ${isActive ? 'border-black dark:border-white' : 'border-zinc-200 dark:border-zinc-800'} hover:border-zinc-400 transition-colors">
                    <div class="min-w-0 flex-1">
                        <p class="text-xs font-black uppercase truncate">${escapeHtml(name)}</p>
                        <p class="text-[9px] text-zinc-400">${sizeMB} MB · ${date}</p>
                    </div>
                    <div class="flex items-center gap-1">
                        <button type="button" onclick="playLibraryTrack('${id}')" class="p-2 hover:bg-zinc-100 dark:hover:bg-zinc-800">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        </button>
                        ${canDelete ? `<button type="button" onclick="deleteLibraryTrack('${id}')" class="p-2 hover:bg-red-50 text-red-500">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                        </button>` : ''}
                    </div>
                </div>
            `;
        }).join('');
    } catch (error) {
        console.error('Failed to load music library:', error);
        list.innerHTML = '<p class="text-[10px] text-red-500 text-center py-8">Failed to load tracks</p>';
    }
}

async function uploadLibraryMusic(input) {
    const file = input?.files?.[0];
    if (!file) {
        return;
    }

    const formData = new FormData();
    formData.append('file', file);

    try {
        const response = await fetch(`${APP_URL}/api/pomodoro.php?action=music_upload`, {
            method: 'POST',
            headers: {
                'X-CSRF-Token': CSRF_TOKEN
            },
            body: formData
        });

        const result = await response.json();

        if (!response.ok || !result.success) {
            throw new Error(result?.error?.message || 'Upload failed');
        }

        musicTracks.push(result.data);
        activeTrackId = String(result.data.id || '');
        renderMusicTracks();
        loadMusicLibrary();
        Mobile.ui.showToast('Track uploaded.', 'success');
    } catch (error) {
        Mobile.ui.showToast(error.message || 'Failed to upload track.', 'error');
    } finally {
        input.value = '';
    }
}

function playLibraryTrack(trackId) {
    activeTrackId = String(trackId);
    renderMusicTracks();
    if (typeof PomodoroAudioManager !== 'undefined') {
        PomodoroAudioManager.playTrack(activeTrackId);
    }
    closeMusicLibrary();
}

async function deleteLibraryTrack(trackId) {
    if (!musicPermissionMap[String(trackId)]?.canDelete) {
        Mobile.ui.showToast('You can only delete tracks you uploaded.', 'warning');
        return;
    }
    if (!confirm('Delete this track?')) {
        return;
    }

    try {
        const response = await App.api.post('api/pomodoro.php?action=music_delete', {
            id: trackId,
            csrf_token: CSRF_TOKEN
        });

        if (!response.success) {
            throw new Error('Delete failed');
        }

        musicTracks = musicTracks.filter((item) => String(item.id) !== String(trackId));
        if (String(activeTrackId) === String(trackId)) {
            activeTrackId = musicTracks.length ? String(musicTracks[0].id || '') : null;
            if (typeof PomodoroAudioManager !== 'undefined') {
                PomodoroAudioManager.pauseTrack();
            }
        }
        renderMusicTracks();
        loadMusicLibrary();
        Mobile.ui.showToast('Track deleted.', 'success');
    } catch (error) {
        Mobile.ui.showToast('Failed to delete track.', 'error');
    }
}

// Close modal when clicking outside
document.addEventListener('click', (e) => {
    const modal = document.getElementById('music-library-modal');
    if (e.target === modal) {
        closeMusicLibrary();
    }
});

// Initialize
loadCustomSettings();
initializeStateFromSharedPomodoro();
activeTrackId = String(localStorage.getItem(POMODORO_MUSIC_ID_KEY) || activeTrackId || '');
const autoplayCheckbox = document.getElementById('play-while-running');
if (autoplayCheckbox) {
    autoplayCheckbox.checked = localStorage.getItem(POMODORO_MUSIC_AUTO_KEY) === '1' || autoplayCheckbox.checked;
    localStorage.setItem(POMODORO_MUSIC_AUTO_KEY, autoplayCheckbox.checked ? '1' : '0');
    autoplayCheckbox.addEventListener('change', () => {
        localStorage.setItem(POMODORO_MUSIC_AUTO_KEY, autoplayCheckbox.checked ? '1' : '0');
    });
}
updateDisplay();
renderMusicTracks();

if (window.Mobile && typeof Mobile.init === 'function') {
    Mobile.init();
}
</script>
</body>
</html>
