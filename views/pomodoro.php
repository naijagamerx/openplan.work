<?php
// Pomodoro Timer view
$db = new Database(getMasterPassword(), Auth::userId());
$pomodoroSessionsResult = $db->safeLoad('pomodoro_sessions');
$timerSessions = $pomodoroSessionsResult['data'] ?? [];
$pomodoroDataWarning = !($pomodoroSessionsResult['success'] ?? true);
$canManageFocusMusic = Auth::isAdmin();
$canUploadFocusMusic = Auth::check();

// Calculate statistics
$completedToday = 0;
$totalFocusTime = 0;
$currentSessionCount = 0;
$today = date('Y-m-d');

foreach ($timerSessions as $session) {
    // Extract just the date part (YYYY-MM-DD) from the datetime string
    $sessionDate = substr(($session['date'] ?? ''), 0, 10);
    $sessionStatus = ($session['status'] ?? '');

    // Count completed sessions from today
    if ($sessionDate === $today && $sessionStatus === 'completed') {
        $completedToday++;
    }

    // Count total focus time from completed sessions
    if ($sessionStatus === 'completed') {
        $totalFocusTime += ($session['duration'] ?? 0);
    }

    // Count currently running sessions
    if ($sessionStatus === 'running') {
        $currentSessionCount++;
    }
}

$hours = floor($totalFocusTime / 3600);
$minutes = floor(($totalFocusTime % 3600) / 60);
?>

<!-- Add Manrope font and Material Symbols -->
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@200;300;400;500;600;700;800&display=swap" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>

<style>
    .material-symbols-outlined {
        font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
    }
    .pomodoro-page {
        font-family: 'Manrope', sans-serif;
    }
    .mode-btn.active {
        background-color: #111827;
        color: #ffffff;
        border-color: #111827;
    }
    .mode-btn:not(.active) {
        background-color: #ffffff;
        color: #475569;
        border: 1px solid #d1d5db;
    }
</style>

<div class="pomodoro-page p-6 xl:p-8 space-y-8">
    <?php if ($pomodoroDataWarning): ?>
        <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
            Some Pomodoro history could not be decrypted with the current master password. The page is still available, but history data may be incomplete.
        </div>
    <?php endif; ?>

    <header class="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-4 border-b border-slate-200 pb-8">
        <div>
            <h2 class="text-4xl font-black tracking-tight text-black">Pomodoro Timer</h2>
            <p class="text-slate-500 text-sm font-medium mt-1">Stay focused, take breaks, be productive.</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <button onclick="openMusicLibrary()" class="inline-flex items-center gap-2 rounded-lg h-10 px-4 border border-slate-300 bg-white text-black text-sm font-bold hover:bg-slate-50 transition-colors">
                <span class="material-symbols-outlined text-[20px]">library_music</span>
                Music Library
            </button>
            <button onclick="requestNotificationPermission()" class="inline-flex items-center gap-2 rounded-lg h-10 px-4 border border-slate-300 bg-white text-black text-sm font-bold hover:bg-slate-50 transition-colors">
                <span class="material-symbols-outlined text-[20px]">notifications</span>
                Notifications
            </button>
            <button onclick="testPomodoroNotification()" class="inline-flex items-center gap-2 rounded-lg h-10 px-4 border border-slate-300 bg-white text-black text-sm font-bold hover:bg-slate-50 transition-colors">
                <span class="material-symbols-outlined text-[20px]">bug_report</span>
                Test
            </button>
        </div>
    </header>

    <section class="grid grid-cols-12 gap-6">
        <div class="col-span-12 lg:col-span-7 space-y-6">
            <div class="bg-white border border-slate-200 rounded-[2rem] p-8 xl:p-12 shadow-sm relative overflow-hidden">
                <div class="absolute top-6 left-6 flex items-center gap-2 bg-slate-50 px-3 py-1 rounded-full">
                    <span class="size-2 rounded-full bg-slate-400 animate-pulse"></span>
                    <span id="pomodoro-status" class="text-[10px] font-bold uppercase tracking-[0.2em] text-slate-500">Ready to focus</span>
                </div>

                <div class="text-center pt-6">
                    <h3 id="pomodoro-display" class="text-[92px] xl:text-[130px] leading-none font-black text-black tracking-tighter tabular-nums">25:00</h3>
                </div>

                <div class="mt-8 flex flex-col sm:flex-row gap-3 max-w-md mx-auto">
                    <button onclick="togglePomodoro()" id="pomodoro-btn" class="flex-1 bg-black text-white py-4 rounded-2xl font-bold text-lg hover:bg-slate-800 transition-colors">
                        Start
                    </button>
                    <button onclick="resetPomodoro()" class="flex-1 bg-white border-2 border-slate-200 text-black py-4 rounded-2xl font-bold text-lg hover:bg-slate-50 transition-colors">
                        Reset
                    </button>
                </div>
            </div>

            <div class="bg-white border border-slate-200 rounded-3xl p-6 space-y-6">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h3 class="font-bold text-black">Timer Presets</h3>
                    <div class="flex flex-wrap gap-2">
                        <button onclick="setPomodoroMode(25)" id="mode-25" class="mode-btn px-4 py-1.5 rounded-lg text-xs font-bold transition-colors">25 min</button>
                        <button onclick="setPomodoroMode(15)" id="mode-15" class="mode-btn px-4 py-1.5 rounded-lg text-xs font-bold transition-colors">15 min</button>
                        <button onclick="setPomodoroMode(5)" id="mode-5" class="mode-btn px-4 py-1.5 rounded-lg text-xs font-bold transition-colors">5 min</button>
                        <button onclick="toggleCustomMode()" id="mode-custom" class="mode-btn px-4 py-1.5 rounded-lg text-xs font-bold transition-colors">Custom</button>
                    </div>
                </div>

                <div id="default-break-section" class="grid grid-cols-1 sm:grid-cols-[1fr_auto] items-center gap-3">
                    <label class="text-xs font-bold text-slate-400 uppercase tracking-wider">Break Duration (minutes)</label>
                    <input id="break-minutes" type="number" min="1" max="60" value="5" class="w-full sm:w-28 px-3 py-2 rounded-xl border border-slate-200 text-center font-bold outline-none focus:border-black">
                </div>

                <div id="custom-timer-section" class="hidden flex-col gap-4">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="space-y-2">
                            <label class="text-xs font-bold text-slate-400 uppercase tracking-wider">Focus Duration</label>
                            <input id="custom-focus-minutes" type="number" min="1" max="180" placeholder="25" onchange="applyCustomTimer()" class="w-full px-4 py-3 rounded-xl border border-slate-200 text-sm font-bold outline-none focus:border-black">
                        </div>
                        <div class="space-y-2">
                            <label class="text-xs font-bold text-slate-400 uppercase tracking-wider">Break Duration</label>
                            <input id="custom-break-minutes" type="number" min="1" max="60" placeholder="5" onchange="applyCustomTimer()" class="w-full px-4 py-3 rounded-xl border border-slate-200 text-sm font-bold outline-none focus:border-black">
                        </div>
                    </div>
                    <button onclick="applyCustomTimer()" class="w-full py-3 bg-slate-100 text-black font-bold rounded-xl hover:bg-black hover:text-white transition-colors text-sm">
                        Apply Custom
                    </button>
                </div>
            </div>
        </div>

        <div class="col-span-12 lg:col-span-5 space-y-4">
            <div class="bg-black text-white p-6 rounded-2xl flex flex-col gap-1">
                <span class="text-[10px] font-bold opacity-60 uppercase tracking-[0.2em]">Completed Today</span>
                <span id="pomodoro-completed-today" class="text-3xl font-black" data-completed="<?php echo $completedToday; ?>"><?php echo $completedToday; ?></span>
            </div>
            <div class="bg-white border border-slate-200 p-6 rounded-2xl flex flex-col gap-1">
                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-[0.2em]">Current Session</span>
                <span id="pomodoro-current-session" class="text-3xl font-black text-black">0</span>
            </div>
            <div class="bg-white border border-slate-200 p-6 rounded-2xl flex flex-col gap-1">
                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-[0.2em]">Focus Time</span>
                <span id="pomodoro-focus-time" class="text-3xl font-black text-black" data-total-seconds="<?php echo $totalFocusTime; ?>"><?php echo $hours . 'h ' . $minutes . 'm'; ?></span>
            </div>

            <div class="bg-white border border-slate-200 rounded-3xl overflow-hidden flex flex-col min-h-[320px]">
                <div class="p-6 border-b border-slate-100 flex items-center justify-between">
                    <h3 class="font-bold flex items-center gap-2">
                        <span class="material-symbols-outlined text-xl">insights</span>
                        App Focus Tracker
                    </h3>
                    <span class="text-[10px] bg-slate-100 px-2 py-1 rounded font-bold">LIVE</span>
                </div>
                <div id="app-usage-table" class="p-6 overflow-y-auto flex-1"></div>
            </div>
        </div>
    </section>

    <section class="grid grid-cols-12 gap-6">
        <div class="col-span-12 lg:col-span-6">
            <div class="bg-white border border-slate-200 rounded-3xl p-6 h-full flex flex-col">
                <div class="flex items-center justify-between mb-6">
                    <div>
                        <h3 class="text-xl font-bold text-black flex items-center gap-2">
                            <span class="material-symbols-outlined">music_note</span>
                            Focus Music
                        </h3>
                        <p class="text-xs text-slate-500 mt-1">
                            <?php echo $canManageFocusMusic
                                ? 'Upload mp3, wav, or m4a tracks and manage the shared focus library.'
                                : 'Play shared focus music and manage only tracks you uploaded.'; ?>
                        </p>
                    </div>
                    <button onclick="openMusicLibrary()" class="flex items-center gap-2 text-xs font-bold px-3 py-1.5 bg-slate-50 rounded-lg hover:bg-slate-100 transition-colors">
                        <span class="material-symbols-outlined text-base">library_music</span>
                        Library
                    </button>
                </div>

                <div class="space-y-3">
                    <?php if ($canUploadFocusMusic): ?>
                    <div class="flex flex-wrap items-center gap-2">
                        <input id="music-file" type="file" accept=".mp3,.wav,.m4a,audio/mpeg,audio/wav,audio/mp4,audio/x-m4a" class="flex-1 min-w-[220px] text-sm border border-slate-200 rounded-xl px-3 py-2">
                        <button id="music-upload-btn" class="px-4 py-2 bg-black text-white rounded-lg text-xs font-bold uppercase tracking-widest">Upload</button>
                    </div>
                    <?php endif; ?>

                    <div class="flex flex-wrap items-center gap-2">
                        <select id="music-select" class="flex-1 min-w-[220px] px-3 py-2 border border-slate-200 rounded-lg text-sm">
                            <option value="">Select a track</option>
                        </select>
                        <button id="music-play-btn" class="px-4 py-2 bg-white border border-slate-200 rounded-lg text-xs font-bold uppercase tracking-widest">Play</button>
                        <button id="music-delete-btn" class="px-4 py-2 bg-white border border-slate-200 rounded-lg text-xs font-bold uppercase tracking-widest text-red-600 hidden">Delete</button>
                    </div>
                </div>

                <div class="mt-6 pt-6 border-t border-slate-100 space-y-4">
                    <label class="inline-flex items-center gap-2 text-xs font-bold uppercase tracking-widest text-slate-500">
                        <input id="music-autoplay" type="checkbox" class="rounded">
                        Play while running
                    </label>
                    <label class="inline-flex items-center gap-2 text-xs font-bold uppercase tracking-widest text-slate-500 ml-6">
                        <input id="music-loop" type="checkbox" class="rounded">
                        Loop
                    </label>
                    <div class="flex items-center gap-3">
                        <span class="material-symbols-outlined text-sm text-slate-400">volume_down</span>
                        <input id="music-volume" type="range" min="0" max="1" step="0.05" class="flex-1 accent-black">
                        <span class="material-symbols-outlined text-sm text-slate-400">volume_up</span>
                    </div>
                </div>

            </div>
        </div>

        <div class="col-span-12 lg:col-span-6">
            <div class="bg-white border border-slate-200 rounded-3xl p-6 h-full flex flex-col">
                <h3 class="text-xl font-bold mb-6">Recent Sessions</h3>
                <?php if (empty($timerSessions)): ?>
                    <div class="border-2 border-dashed border-slate-100 rounded-2xl p-12 flex flex-col items-center justify-center text-center flex-1">
                        <div class="size-16 rounded-full bg-slate-50 flex items-center justify-center mb-4">
                            <span class="material-symbols-outlined text-slate-300 text-3xl">history</span>
                        </div>
                        <p class="text-slate-400 font-medium">No sessions yet. Start your first Pomodoro session.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive border border-slate-200 rounded-xl overflow-hidden flex-1">
                        <table class="min-w-full text-sm">
                            <thead class="bg-slate-50 text-slate-500 uppercase text-[11px] font-black tracking-widest">
                                <tr>
                                    <th class="px-4 py-3 text-left">#</th>
                                    <th class="px-4 py-3 text-left">Mode</th>
                                    <th class="px-4 py-3 text-left">Duration</th>
                                    <th class="px-4 py-3 text-left">Date</th>
                                    <th class="px-4 py-3 text-left">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <?php
                                $recentSessions = array_slice(array_reverse($timerSessions), 0, 10);
                                $index = 1;
                                foreach ($recentSessions as $session):
                                    $isCompleted = ($session['status'] ?? '') === 'completed';
                                    $duration = $session['duration'] ?? 0;
                                    $hrs = floor($duration / 3600);
                                    $mins = floor(($duration % 3600) / 60);
                                    $secs = $duration % 60;
                                    $durationText = ($hrs > 0 ? $hrs . 'h ' : '') . ($mins > 0 ? $mins . 'm ' : '') . $secs . 's';
                                ?>
                                    <tr>
                                        <td class="px-4 py-3 font-bold text-slate-400"><?php echo $index++; ?></td>
                                        <td class="px-4 py-3 font-medium text-gray-900"><?php echo e($session['mode'] ?? '25 minutes'); ?> Pomodoro</td>
                                        <td class="px-4 py-3 text-gray-700"><?php echo $durationText; ?></td>
                                        <td class="px-4 py-3 text-gray-600"><?php echo formatDate($session['date'] ?? date('Y-m-d')); ?></td>
                                        <td class="px-4 py-3">
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-black uppercase tracking-widest <?php echo $isCompleted ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500'; ?>">
                                                <?php echo $isCompleted ? 'Completed' : 'Running'; ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>
</div>

<!-- Music Library Modal -->
<div id="music-library-modal" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 hidden items-center justify-center">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl mx-4 max-h-[90vh] overflow-hidden flex flex-col">
        <div class="flex items-center justify-between p-6 border-b border-slate-100">
            <div>
                <h3 class="text-xl font-black text-black">Music Library</h3>
                <p class="text-xs text-slate-500 mt-1"><?php echo $canManageFocusMusic ? 'Manage the shared focus music collection' : 'Browse shared tracks and manage only your uploads'; ?></p>
            </div>
            <button onclick="closeMusicLibrary()" class="p-2 hover:bg-slate-100 rounded-lg transition-colors">
                <span class="material-symbols-outlined text-slate-500">close</span>
            </button>
        </div>

        <?php if ($canUploadFocusMusic): ?>
        <div class="p-6 border-b border-slate-100 bg-slate-50">
            <div class="flex items-center gap-4">
                <div class="flex-1">
                    <label class="block text-xs font-bold uppercase tracking-widest text-slate-500 mb-2">Upload New Track</label>
                    <div class="flex items-center gap-3">
                        <input id="library-music-file" type="file" accept=".mp3,.wav,.m4a,audio/mpeg,audio/wav,audio/mp4,audio/x-m4a" class="flex-1 text-sm file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-bold file:bg-black file:text-white hover:file:bg-slate-800">
                        <button onclick="uploadLibraryMusic()" class="px-4 py-2 bg-black text-white rounded-lg text-xs font-bold uppercase tracking-widest hover:bg-slate-800 transition-colors">
                            Upload
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <div class="flex-1 overflow-y-auto p-6">
            <div id="music-library-list" class="space-y-3">
                <!-- Music tracks will be loaded here -->
            </div>
        </div>

        <div class="p-6 border-t border-slate-100 bg-slate-50">
            <div class="flex items-center justify-between text-xs text-slate-500">
                <span id="library-track-count">0 tracks</span>
                <button onclick="closeMusicLibrary()" class="px-4 py-2 bg-white border border-slate-200 rounded-lg text-xs font-bold uppercase tracking-widest hover:bg-slate-100 transition-colors">
                    Close
                </button>
            </div>
        </div>
    </div>
</div>

<script>
const STATE_KEY = 'pomodoroStateV2';
const OLD_STATE_KEY = 'pomodoroState';
const BREAK_KEY = 'pomodoroBreakMinutes';
const CUSTOM_FOCUS_KEY = 'pomodoroCustomFocus';
const CUSTOM_BREAK_KEY = 'pomodoroCustomBreak';
const CUSTOM_MODE_KEY = 'pomodoroCustomMode';
const MUSIC_SELECTED_KEY = 'pomodoroMusicId';
const MUSIC_AUTOPLAY_KEY = 'pomodoroMusicAuto';
const MUSIC_LOOP_KEY = 'pomodoroMusicLoop';
const MUSIC_VOLUME_KEY = 'pomodoroMusicVolume';
const MUSIC_PLAYING_KEY = 'pomodoroMusicPlaying';
const MUSIC_TIME_KEY = 'pomodoroMusicTime';
const CAN_MANAGE_FOCUS_MUSIC = <?php echo $canManageFocusMusic ? 'true' : 'false'; ?>;
const CAN_UPLOAD_FOCUS_MUSIC = <?php echo $canUploadFocusMusic ? 'true' : 'false'; ?>;

let timerInterval = null;
let audioContext = null;
let musicPermissionMap = {};

const state = {
    phase: 'idle',
    running: false,
    secondsLeft: 25 * 60,
    focusMinutes: 25,
    breakMinutes: 5,
    lastTick: null,
    awaitingNextFocus: false,
    completedToday: 0,
    totalFocusSeconds: 0
};

function initAudioContext() {
    if (!audioContext) {
        audioContext = new (window.AudioContext || window.webkitAudioContext)();
    }
}

async function playAlertSound(type) {
    try {
        initAudioContext();
        if (audioContext.state === 'suspended') {
            await audioContext.resume();
        }
        const tones = type === 'break'
            ? [392.0, 523.25, 659.25]
            : [523.25, 659.25, 783.99];
        tones.forEach((freq, index) => {
            const osc = audioContext.createOscillator();
            const gain = audioContext.createGain();
            osc.connect(gain);
            gain.connect(audioContext.destination);
            const startTime = audioContext.currentTime + index * 0.2;
            osc.frequency.setValueAtTime(freq, startTime);
            osc.type = 'sine';
            gain.gain.setValueAtTime(0.3, startTime);
            gain.gain.exponentialRampToValueAtTime(0.01, startTime + 0.35);
            osc.start(startTime);
            osc.stop(startTime + 0.35);
        });
    } catch (e) {
        console.warn('Sound failed:', e);
    }
}

async function requestNotificationPermission() {
    if (!('Notification' in window)) {
        if (typeof showToast !== 'undefined') showToast('Browser notifications not supported', 'warning');
        return false;
    }
    if (Notification.permission === 'granted') {
        if (typeof showToast !== 'undefined') showToast('Notifications already enabled', 'success');
        return true;
    }
    if (Notification.permission === 'denied') {
        if (typeof showToast !== 'undefined') showToast('Notifications are blocked in browser settings', 'warning');
        return false;
    }
    const permission = await Notification.requestPermission();
    return permission === 'granted';
}

function sendPomodoroNotification(title, body) {
    if ('Notification' in window && Notification.permission === 'granted') {
        try {
            new Notification(title, { body: body || '' });
            return;
        } catch (e) {
            console.warn('Notification failed:', e);
        }
    }
    if (typeof showToast !== 'undefined') {
        showToast(body || title, 'success');
    } else {
        alert(body || title);
    }
}

function testPomodoroNotification() {
    sendPomodoroNotification('Pomodoro Test', 'Notifications are working.');
}

function formatClock(seconds) {
    const mins = Math.floor(seconds / 60);
    const secs = Math.floor(seconds % 60);
    return `${String(mins).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
}

function formatDuration(totalSeconds) {
    const hrs = Math.floor(totalSeconds / 3600);
    const mins = Math.floor((totalSeconds % 3600) / 60);
    return `${hrs}h ${mins}m`;
}

function loadBreakMinutes() {
    const saved = parseInt(localStorage.getItem(BREAK_KEY) || '5', 10);
    return Number.isFinite(saved) && saved > 0 ? saved : 5;
}

function setBreakMinutes(value) {
    const minutes = Math.min(60, Math.max(1, parseInt(value || '5', 10)));
    state.breakMinutes = minutes;
    localStorage.setItem(BREAK_KEY, String(minutes));
    const input = document.getElementById('break-minutes');
    if (input && String(input.value) != String(minutes)) {
        input.value = minutes;
    }
    const customBreakInput = document.getElementById('custom-break-minutes');
    if (customBreakInput && String(customBreakInput.value) != String(minutes)) {
        customBreakInput.value = minutes;
    }
    if (state.phase === 'break' && !state.running) {
        state.secondsLeft = minutes * 60;
    }
    saveState();
    updateDisplay();
}

function toggleCustomMode() {
    const customSection = document.getElementById('custom-timer-section');
    const defaultBreakSection = document.getElementById('default-break-section');
    const isCustom = customSection.classList.contains('hidden');

    if (isCustom) {
        customSection.classList.remove('hidden');
        customSection.classList.add('flex');
        defaultBreakSection.classList.add('hidden');
        localStorage.setItem(CUSTOM_MODE_KEY, '1');

        // Load saved custom values
        const savedFocus = localStorage.getItem(CUSTOM_FOCUS_KEY) || '25';
        const savedBreak = localStorage.getItem(CUSTOM_BREAK_KEY) || '5';
        document.getElementById('custom-focus-minutes').value = savedFocus;
        document.getElementById('custom-break-minutes').value = savedBreak;
    } else {
        customSection.classList.add('hidden');
        customSection.classList.remove('flex');
        defaultBreakSection.classList.remove('hidden');
        localStorage.removeItem(CUSTOM_MODE_KEY);
    }
    updateModeButtons();
}

function applyCustomTimer() {
    if (state.running) {
        if (typeof showToast !== 'undefined') showToast('Pause the timer to change settings', 'info');
        return;
    }

    const focusInput = document.getElementById('custom-focus-minutes');
    const breakInput = document.getElementById('custom-break-minutes');

    let focusMinutes = parseInt(focusInput.value, 10);
    let breakMinutes = parseInt(breakInput.value, 10);

    // Validate
    if (!Number.isFinite(focusMinutes) || focusMinutes < 1) focusMinutes = 25;
    if (!Number.isFinite(breakMinutes) || breakMinutes < 1) breakMinutes = 5;
    if (focusMinutes > 180) focusMinutes = 180;
    if (breakMinutes > 60) breakMinutes = 60;

    // Save to localStorage
    localStorage.setItem(CUSTOM_FOCUS_KEY, String(focusMinutes));
    localStorage.setItem(CUSTOM_BREAK_KEY, String(breakMinutes));

    // Update inputs with validated values
    focusInput.value = focusMinutes;
    breakInput.value = breakMinutes;

    // Apply to state
    state.focusMinutes = focusMinutes;
    state.breakMinutes = breakMinutes;
    state.phase = 'idle';
    state.awaitingNextFocus = false;
    state.secondsLeft = focusMinutes * 60;

    updateDisplay();
    saveState();

    if (typeof showToast !== 'undefined') showToast(`Timer set: ${focusMinutes}min focus, ${breakMinutes}min break`, 'success');
}

function saveState() {
    localStorage.setItem(STATE_KEY, JSON.stringify({
        phase: state.phase,
        running: state.running,
        secondsLeft: state.secondsLeft,
        focusMinutes: state.focusMinutes,
        breakMinutes: state.breakMinutes,
        lastTick: state.lastTick,
        awaitingNextFocus: state.awaitingNextFocus
    }));
}

function loadState() {
    const saved = localStorage.getItem(STATE_KEY);
    if (saved) {
        try {
            const parsed = JSON.parse(saved);
            state.phase = parsed.phase || 'idle';
            state.running = !!parsed.running;
            state.secondsLeft = parseInt(parsed.secondsLeft || 0, 10) || state.focusMinutes * 60;
            state.focusMinutes = parseInt(parsed.focusMinutes || 25, 10) || 25;
            state.breakMinutes = parseInt(parsed.breakMinutes || loadBreakMinutes(), 10) || loadBreakMinutes();
            state.lastTick = parsed.lastTick || null;
            state.awaitingNextFocus = !!parsed.awaitingNextFocus;
            return;
        } catch (e) {
            console.warn('Failed to load new state:', e);
        }
    }

    const old = localStorage.getItem(OLD_STATE_KEY);
    if (old) {
        try {
            const parsed = JSON.parse(old);
            state.focusMinutes = parseInt(parsed.mode || 25, 10) || 25;
            state.breakMinutes = loadBreakMinutes();
            state.secondsLeft = parseInt(parsed.seconds || state.focusMinutes * 60, 10);
            state.running = !!parsed.running;
            state.phase = state.running || state.secondsLeft < state.focusMinutes * 60 ? 'focus' : 'idle';
            state.lastTick = parsed.lastUpdate || Date.now();
            state.awaitingNextFocus = false;
            localStorage.removeItem(OLD_STATE_KEY);
        } catch (e) {
            console.warn('Failed to load old state:', e);
        }
    }
}

function initStats() {
    const completedEl = document.getElementById('pomodoro-completed-today');
    const focusEl = document.getElementById('pomodoro-focus-time');
    state.completedToday = parseInt(completedEl?.dataset.completed || '0', 10) || 0;
    state.totalFocusSeconds = parseInt(focusEl?.dataset.totalSeconds || '0', 10) || 0;
}

function updateStatsDisplay() {
    const completedEl = document.getElementById('pomodoro-completed-today');
    const focusEl = document.getElementById('pomodoro-focus-time');
    const currentEl = document.getElementById('pomodoro-current-session');
    if (completedEl) completedEl.textContent = String(state.completedToday);
    if (focusEl) focusEl.textContent = formatDuration(state.totalFocusSeconds);
    if (currentEl) currentEl.textContent = state.phase === 'idle' ? '0' : '1';
}

function updateModeButtons() {
    const isCustomMode = !!localStorage.getItem(CUSTOM_MODE_KEY);
    const customFocus = parseInt(localStorage.getItem(CUSTOM_FOCUS_KEY) || '25', 10);

    [25, 15, 5].forEach(mode => {
        const btn = document.getElementById(`mode-${mode}`);
        if (!btn) return;
        btn.classList.toggle('active', !isCustomMode && mode === state.focusMinutes);
    });

    const customBtn = document.getElementById('mode-custom');
    if (customBtn) {
        customBtn.classList.toggle('active', isCustomMode);
    }
}

function updateDisplay() {
    const display = document.getElementById('pomodoro-display');
    if (display) display.textContent = formatClock(state.secondsLeft);

    const statusEl = document.getElementById('pomodoro-status');
    if (statusEl) {
        if (state.phase === 'focus') {
            statusEl.textContent = state.running ? 'Focus time' : 'Focus paused';
        } else if (state.phase === 'break') {
            statusEl.textContent = state.running ? 'Break time' : 'Break paused';
        } else {
            statusEl.textContent = state.awaitingNextFocus ? 'Break complete - resume when ready' : 'Ready to focus';
        }
    }

    const btn = document.getElementById('pomodoro-btn');
    if (btn) {
        if (state.running) {
            btn.textContent = 'Pause';
        } else if (state.phase === 'idle') {
            btn.textContent = state.awaitingNextFocus ? 'Resume' : 'Start';
        } else if (state.phase === 'break') {
            btn.textContent = 'Resume Break';
        } else {
            btn.textContent = 'Resume';
        }
    }

    document.title = `${formatClock(state.secondsLeft)} - ${state.running ? (state.phase === 'break' ? 'Break' : 'Focus') : 'Pomodoro'}`;
    updateModeButtons();
    updateStatsDisplay();
    syncMusicPlayback();
}

function startInterval() {
    if (timerInterval) clearInterval(timerInterval);
    timerInterval = setInterval(tick, 1000);
}

function startTimer() {
    state.running = true;
    state.lastTick = Date.now();
    startInterval();
    updateDisplay();
    saveState();
}

function pauseTimer() {
    state.running = false;
    state.lastTick = null;
    if (timerInterval) {
        clearInterval(timerInterval);
        timerInterval = null;
    }
    updateDisplay();
    saveState();
}

function startFocus() {
    state.phase = 'focus';
    state.awaitingNextFocus = false;
    state.secondsLeft = state.focusMinutes * 60;
    startTimer();
}

function startBreak() {
    state.phase = 'break';
    state.awaitingNextFocus = false;
    state.secondsLeft = state.breakMinutes * 60;
    startTimer();
}

async function savePomodoroSession() {
    try {
        await fetch('api/pomodoro.php?action=complete', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': typeof CSRF_TOKEN !== 'undefined' ? CSRF_TOKEN : ''
            },
            body: JSON.stringify({
                mode: state.focusMinutes,
                duration: state.focusMinutes * 60,
                csrf_token: typeof CSRF_TOKEN !== 'undefined' ? CSRF_TOKEN : ''
            })
        });
    } catch (error) {
        console.error('Failed to save pomodoro session:', error);
    }
}

function completeFocus(silent) {
    if (!silent) {
        playAlertSound('focus');
        sendPomodoroNotification('Pomodoro Complete', 'Time for a break.');
    }
    state.awaitingNextFocus = false;
    state.completedToday += 1;
    state.totalFocusSeconds += state.focusMinutes * 60;
    savePomodoroSession();
    state.phase = 'break';
    state.secondsLeft = state.breakMinutes * 60;
    state.running = true;
    state.lastTick = Date.now();
}

function completeBreak(silent) {
    if (!silent) {
        playAlertSound('break');
        sendPomodoroNotification('Break Complete', 'Ready for the next focus session.');
    }
    state.phase = 'idle';
    state.awaitingNextFocus = true;
    state.running = false;
    state.secondsLeft = state.focusMinutes * 60;
    state.lastTick = null;
}

function applyElapsed(elapsedSeconds, silent) {
    let remaining = elapsedSeconds;
    while (remaining > 0 && state.running) {
        if (remaining < state.secondsLeft) {
            state.secondsLeft -= remaining;
            remaining = 0;
        } else {
            remaining -= state.secondsLeft;
            state.secondsLeft = 0;
            if (state.phase === 'focus') {
                completeFocus(silent);
            } else if (state.phase === 'break') {
                completeBreak(silent);
            } else {
                state.running = false;
                remaining = 0;
            }
        }
    }
}

function tick() {
    if (!state.running) return;
    const now = Date.now();
    const elapsed = Math.floor((now - (state.lastTick || now)) / 1000);
    if (elapsed <= 0) return;
    applyElapsed(elapsed, false);
    state.lastTick = now;
    if (!state.running && timerInterval) {
        clearInterval(timerInterval);
        timerInterval = null;
    }
    updateDisplay();
    saveState();
}

function syncElapsed() {
    if (!state.running || !state.lastTick) return;
    const now = Date.now();
    const elapsed = Math.floor((now - state.lastTick) / 1000);
    if (elapsed <= 0) return;
    applyElapsed(elapsed, true);
    state.lastTick = now;
    if (!state.running && timerInterval) {
        clearInterval(timerInterval);
        timerInterval = null;
    }
    updateDisplay();
    saveState();
}

function togglePomodoro() {
    initAudioContext();
    if (!state.running && 'Notification' in window && Notification.permission === 'default') {
        requestNotificationPermission();
    }
    if (state.running) {
        pauseTimer();
        return;
    }
    if (state.phase === 'idle') {
        startFocus();
    } else {
        startTimer();
    }
}

function resetPomodoro() {
    pauseTimer();
    state.phase = 'idle';
    state.awaitingNextFocus = false;
    state.secondsLeft = state.focusMinutes * 60;
    updateDisplay();
    saveState();
}

function setPomodoroMode(minutes) {
    if (state.running) {
        if (typeof showToast !== 'undefined') showToast('Pause the timer to change focus length', 'info');
        return;
    }
    state.focusMinutes = minutes;
    state.phase = 'idle';
    state.awaitingNextFocus = false;
    state.secondsLeft = minutes * 60;
    updateDisplay();
    saveState();
}

function renderUsageTable() {
    const container = document.getElementById('app-usage-table');
    if (!container) return;
    let data = {};
    try {
        data = JSON.parse(localStorage.getItem('appTabUsage') || '{}');
    } catch (e) {
        data = {};
    }
    const entries = Object.entries(data).sort((a, b) => b[1] - a[1]).slice(0, 5);
    if (!entries.length) {
        container.innerHTML = '<p class="text-sm text-slate-400">No usage data yet.</p>';
        return;
    }
    const rows = entries.map(([page, ms], idx) => {
        const totalSeconds = Math.floor(ms / 1000);
        const minutes = Math.floor(totalSeconds / 60);
        const seconds = totalSeconds % 60;
        const label = page.replace(/[-_]/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
        return `
            <tr>
                <td class="px-4 py-3 text-slate-400 font-bold">${idx + 1}</td>
                <td class="px-4 py-3 text-gray-900 font-medium">${label}</td>
                <td class="px-4 py-3 text-gray-600">${minutes}m ${seconds}s</td>
            </tr>
        `;
    }).join('');
    container.innerHTML = `
        <div class="border border-slate-200 rounded-xl overflow-hidden">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-slate-500 uppercase text-[11px] font-black tracking-widest">
                    <tr>
                        <th class="px-4 py-3 text-left">#</th>
                        <th class="px-4 py-3 text-left">Page</th>
                        <th class="px-4 py-3 text-left">Time</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">${rows}</tbody>
            </table>
        </div>
    `;
}

function setupMusicPlayer() {
    const fileInput = document.getElementById('music-file');
    const uploadBtn = document.getElementById('music-upload-btn');
    const select = document.getElementById('music-select');
    const playBtn = document.getElementById('music-play-btn');
    const deleteBtn = document.getElementById('music-delete-btn');
    const autoplayToggle = document.getElementById('music-autoplay');
    const loopToggle = document.getElementById('music-loop');
    const volumeSlider = document.getElementById('music-volume');
    const audio = document.getElementById('pomodoro-audio');

    if (!audio) return;

    function refreshDeleteButton() {
        if (!deleteBtn) {
            return;
        }
        const selectedId = select.value || '';
        const canDelete = !!(selectedId && musicPermissionMap[selectedId]?.canDelete);
        deleteBtn.classList.toggle('hidden', !canDelete);
        deleteBtn.disabled = !canDelete;
    }

    autoplayToggle.checked = localStorage.getItem(MUSIC_AUTOPLAY_KEY) === '1';
    loopToggle.checked = localStorage.getItem(MUSIC_LOOP_KEY) === '1';
    volumeSlider.value = localStorage.getItem(MUSIC_VOLUME_KEY) || '0.6';
    audio.volume = parseFloat(volumeSlider.value || '0.6');
    audio.loop = loopToggle.checked;

    const savedTime = parseFloat(localStorage.getItem(MUSIC_TIME_KEY) || '0');
    audio.addEventListener('loadedmetadata', () => {
        if (Number.isFinite(savedTime) && savedTime > 0) {
            audio.currentTime = Math.min(savedTime, Math.max(0, audio.duration - 1));
        }
    }, { once: true });

    function updatePlayButton() {
        playBtn.textContent = audio.paused ? 'Play' : 'Pause';
    }

    async function loadMusicList() {
        const response = await fetch('api/pomodoro.php?action=music_list');
        const result = await response.json();
        const tracks = result.data || [];
        const trackCache = {};
        const trackOrder = [];
        musicPermissionMap = {};
        select.innerHTML = '<option value="">Select a track</option>';
        tracks.forEach(track => {
            const option = document.createElement('option');
            option.value = track.id;
            option.textContent = track.name || 'Track';
            select.appendChild(option);
            if (track.id) {
                trackOrder.push(track.id);
                trackCache[track.id] = track.name || 'Track';
                musicPermissionMap[track.id] = {
                    canDelete: !!track.canDelete,
                    canRename: !!track.canRename
                };
            }
        });
        localStorage.setItem('pomodoroMusicTracksCache', JSON.stringify(trackCache));
        localStorage.setItem('pomodoroMusicTrackOrder', JSON.stringify(trackOrder));
        const savedId = localStorage.getItem(MUSIC_SELECTED_KEY) || '';
        if (savedId) {
            select.value = savedId;
            if (select.value) {
                audio.src = `api/pomodoro.php?action=music_download&id=${encodeURIComponent(savedId)}`;
            }
        }
        updatePlayButton();
        refreshDeleteButton();
    }

    if (CAN_UPLOAD_FOCUS_MUSIC && uploadBtn && fileInput) {
        uploadBtn.addEventListener('click', async () => {
            const file = fileInput.files[0];
            if (!file) {
                if (typeof showToast !== 'undefined') showToast('Select an audio file first', 'info');
                return;
            }
            const formData = new FormData();
            formData.append('file', file);
            try {
                const response = await fetch('api/pomodoro.php?action=music_upload', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-Token': typeof CSRF_TOKEN !== 'undefined' ? CSRF_TOKEN : ''
                    },
                    body: formData
                });
                const result = await response.json();
                if (result.success) {
                    fileInput.value = '';
                    if (typeof showToast !== 'undefined') showToast('Track uploaded', 'success');
                    await loadMusicList();
                } else {
                    if (typeof showToast !== 'undefined') showToast(result.error?.message || 'Upload failed', 'error');
                }
            } catch (e) {
                console.error(e);
                if (typeof showToast !== 'undefined') showToast('Upload failed', 'error');
            }
        });
    }

    select.addEventListener('change', () => {
        const id = select.value;
        localStorage.setItem(MUSIC_SELECTED_KEY, id);
        localStorage.setItem(MUSIC_TIME_KEY, '0');
        if (id) {
            audio.src = `api/pomodoro.php?action=music_download&id=${encodeURIComponent(id)}`;
        } else {
            audio.removeAttribute('src');
            audio.pause();
        }
        updatePlayButton();
        refreshDeleteButton();
    });

    playBtn.addEventListener('click', async () => {
        if (!audio.src) {
            if (typeof showToast !== 'undefined') showToast('Select a track first', 'info');
            return;
        }
        if (audio.paused) {
            try {
                await audio.play();
            } catch (e) {
                console.warn('Playback failed:', e);
            }
        } else {
            audio.pause();
        }
        updatePlayButton();
    });

    if (deleteBtn) deleteBtn.addEventListener('click', async () => {
        const id = select.value;
        if (!id) {
            if (typeof showToast !== 'undefined') showToast('Select a track to delete', 'info');
            return;
        }
        if (!musicPermissionMap[id]?.canDelete) {
            if (typeof showToast !== 'undefined') showToast('You can only delete tracks you uploaded', 'warning');
            return;
        }
        const confirmed = typeof confirmAction === 'function'
            ? await confirmAction('Delete this track?')
            : confirm('Delete this track?');
        if (!confirmed) return;
        const response = await fetch('api/pomodoro.php?action=music_delete', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id, csrf_token: typeof CSRF_TOKEN !== 'undefined' ? CSRF_TOKEN : '' })
        });
        const result = await response.json();
        if (result.success) {
            if (typeof showToast !== 'undefined') showToast('Track deleted', 'success');
            localStorage.removeItem(MUSIC_SELECTED_KEY);
            localStorage.removeItem(MUSIC_TIME_KEY);
            localStorage.removeItem(MUSIC_PLAYING_KEY);
            audio.removeAttribute('src');
            audio.pause();
            await loadMusicList();
        } else {
            if (typeof showToast !== 'undefined') showToast(result.error?.message || 'Delete failed', 'error');
        }
    });

    autoplayToggle.addEventListener('change', () => {
        localStorage.setItem(MUSIC_AUTOPLAY_KEY, autoplayToggle.checked ? '1' : '0');
        syncMusicPlayback();
    });

    loopToggle.addEventListener('change', () => {
        localStorage.setItem(MUSIC_LOOP_KEY, loopToggle.checked ? '1' : '0');
        audio.loop = loopToggle.checked;
    });

    volumeSlider.addEventListener('input', () => {
        const volume = parseFloat(volumeSlider.value || '0.6');
        audio.volume = volume;
        localStorage.setItem(MUSIC_VOLUME_KEY, String(volume));
    });

    audio.addEventListener('timeupdate', () => {
        localStorage.setItem(MUSIC_TIME_KEY, String(audio.currentTime || 0));
    });
    audio.addEventListener('play', () => {
        localStorage.setItem(MUSIC_PLAYING_KEY, '1');
        updatePlayButton();
    });
    audio.addEventListener('pause', () => {
        localStorage.setItem(MUSIC_PLAYING_KEY, '0');
        updatePlayButton();
    });
    audio.addEventListener('ended', () => {
        localStorage.setItem(MUSIC_PLAYING_KEY, '0');
        updatePlayButton();
    });

    loadMusicList();
}

function syncMusicPlayback() {
    const audio = document.getElementById('pomodoro-audio');
    const autoplay = localStorage.getItem(MUSIC_AUTOPLAY_KEY) === '1';
    if (!audio) return;
    if (autoplay && state.running) {
        audio.play().catch(() => {});
    } else if (!state.running && !audio.paused) {
        audio.pause();
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
        const response = await fetch('api/pomodoro.php?action=music_list');
        const result = await response.json();
        const tracks = result.data || [];
        const trackCache = {};
        const trackOrder = [];
        tracks.forEach(track => {
            if (track.id) {
                trackOrder.push(track.id);
                trackCache[track.id] = track.name || 'Track';
                musicPermissionMap[track.id] = {
                    canDelete: !!track.canDelete,
                    canRename: !!track.canRename
                };
            }
        });
        localStorage.setItem('pomodoroMusicTracksCache', JSON.stringify(trackCache));
        localStorage.setItem('pomodoroMusicTrackOrder', JSON.stringify(trackOrder));

        countEl.textContent = `${tracks.length} track${tracks.length !== 1 ? 's' : ''}`;

        if (!tracks.length) {
            list.innerHTML = `
                <div class="text-center py-12">
                    <span class="material-symbols-outlined text-4xl text-slate-300 mb-3">music_note</span>
                    <p class="text-slate-400 text-sm">${CAN_MANAGE_FOCUS_MUSIC ? 'No tracks yet. Upload your first focus music!' : 'No shared focus music has been added yet.'}</p>
                </div>
            `;
            return;
        }

        list.innerHTML = tracks.map(track => {
            const sizeMB = ((track.size || 0) / (1024 * 1024)).toFixed(1);
            const date = track.uploadedAt ? new Date(track.uploadedAt).toLocaleDateString() : 'Unknown';
            const canDelete = !!track.canDelete;
            const canRename = !!track.canRename;
            return `
                <div class="flex items-center justify-between p-4 border border-slate-200 rounded-xl hover:border-slate-300 transition-colors">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 bg-slate-100 rounded-lg flex items-center justify-center">
                            <span class="material-symbols-outlined text-slate-500">music_note</span>
                        </div>
                        <div>
                            <p class="font-bold text-sm text-black">${escapeHtml(track.name || 'Track')}</p>
                            <p class="text-xs text-slate-500">${sizeMB} MB · ${date}</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <button onclick='playLibraryTrack(${JSON.stringify(track.id)})' class="p-2 hover:bg-slate-100 rounded-lg transition-colors" title="Play">
                            <span class="material-symbols-outlined text-slate-600">play_arrow</span>
                        </button>
                        ${canRename ? `
                        <button onclick='renameLibraryTrack(${JSON.stringify(track.id)}, ${JSON.stringify(track.name || "Track")})' class="p-2 hover:bg-slate-100 rounded-lg transition-colors" title="Rename">
                            <span class="material-symbols-outlined text-slate-600">edit</span>
                        </button>
                        ` : ''}
                        ${canDelete ? `
                        <button onclick='deleteLibraryTrack(${JSON.stringify(track.id)})' class="p-2 hover:bg-red-50 rounded-lg transition-colors" title="Delete">
                            <span class="material-symbols-outlined text-red-500">delete</span>
                        </button>
                        ` : ''}
                    </div>
                </div>
            `;
        }).join('');
    } catch (error) {
        console.error('Failed to load music library:', error);
        list.innerHTML = '<p class="text-center text-red-500 py-8">Failed to load tracks</p>';
    }
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

async function uploadLibraryMusic() {
    const fileInput = document.getElementById('library-music-file');
    const file = fileInput.files[0];

    if (!file) {
        if (typeof showToast !== 'undefined') showToast('Select an audio file first', 'info');
        return;
    }

    const formData = new FormData();
    formData.append('file', file);

    try {
        const response = await fetch('api/pomodoro.php?action=music_upload', {
            method: 'POST',
            headers: {
                'X-CSRF-Token': typeof CSRF_TOKEN !== 'undefined' ? CSRF_TOKEN : ''
            },
            body: formData
        });

        const result = await response.json();

        if (result.success) {
            fileInput.value = '';
            if (typeof showToast !== 'undefined') showToast('Track uploaded', 'success');
            await loadMusicLibrary();
            // Also refresh the main dropdown
            const select = document.getElementById('music-select');
            if (select) {
                const option = document.createElement('option');
                option.value = result.data.id;
                option.textContent = result.data.name || 'Track';
                select.appendChild(option);
                select.value = result.data.id;
                // Trigger change to load the track
                select.dispatchEvent(new Event('change'));
            }
        } else {
            if (typeof showToast !== 'undefined') showToast(result.error?.message || 'Upload failed', 'error');
        }
    } catch (e) {
        console.error(e);
        if (typeof showToast !== 'undefined') showToast('Upload failed', 'error');
    }
}

function playLibraryTrack(trackId) {
    const select = document.getElementById('music-select');
    if (select) {
        select.value = trackId;
        select.dispatchEvent(new Event('change'));
        const playBtn = document.getElementById('music-play-btn');
        if (playBtn) playBtn.click();
    }
}

async function renameLibraryTrack(trackId, currentName) {
    if (!musicPermissionMap[trackId]?.canRename) {
        if (typeof showToast !== 'undefined') showToast('You can only rename tracks you uploaded', 'warning');
        return;
    }
    const newName = prompt('Enter new name:', currentName);
    if (!newName || newName === currentName) return;

    try {
        const response = await fetch('api/pomodoro.php?action=music_rename', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: trackId, name: newName, csrf_token: typeof CSRF_TOKEN !== 'undefined' ? CSRF_TOKEN : '' })
        });

        const result = await response.json();

        if (result.success) {
            if (typeof showToast !== 'undefined') showToast('Track renamed', 'success');
            await loadMusicLibrary();
            // Refresh the main dropdown
            const select = document.getElementById('music-select');
            if (select) {
                const option = select.querySelector(`option[value="${trackId}"]`);
                if (option) option.textContent = newName;
            }
        } else {
            if (typeof showToast !== 'undefined') showToast(result.error?.message || 'Rename failed', 'error');
        }
    } catch (e) {
        console.error(e);
        if (typeof showToast !== 'undefined') showToast('Rename failed', 'error');
    }
}

async function deleteLibraryTrack(trackId) {
    if (!musicPermissionMap[trackId]?.canDelete) {
        if (typeof showToast !== 'undefined') showToast('You can only delete tracks you uploaded', 'warning');
        return;
    }
    const confirmed = typeof confirmAction === 'function'
        ? await confirmAction('Delete this track?')
        : confirm('Delete this track?');
    if (!confirmed) return;

    try {
        const response = await fetch('api/pomodoro.php?action=music_delete', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: trackId, csrf_token: typeof CSRF_TOKEN !== 'undefined' ? CSRF_TOKEN : '' })
        });

        const result = await response.json();

        if (result.success) {
            if (typeof showToast !== 'undefined') showToast('Track deleted', 'success');
            await loadMusicLibrary();
            // Remove from main dropdown
            const select = document.getElementById('music-select');
            const audio = document.getElementById('pomodoro-audio');
            if (select) {
                const option = select.querySelector(`option[value="${trackId}"]`);
                if (option) option.remove();
            }
            if (audio && select && select.value === trackId) {
                audio.removeAttribute('src');
                audio.pause();
            }
        } else {
            if (typeof showToast !== 'undefined') showToast(result.error?.message || 'Delete failed', 'error');
        }
    } catch (e) {
        console.error(e);
        if (typeof showToast !== 'undefined') showToast('Delete failed', 'error');
    }
}

// Close modal when clicking outside
document.addEventListener('click', (e) => {
    const modal = document.getElementById('music-library-modal');
    if (e.target === modal) {
        closeMusicLibrary();
    }
});

document.addEventListener('visibilitychange', () => {
    if (!document.hidden) {
        syncElapsed();
        renderUsageTable();
    }
});

window.addEventListener('beforeunload', () => {
    saveState();
});

document.addEventListener('DOMContentLoaded', () => {
    loadState();
    initStats();
    setBreakMinutes(loadBreakMinutes());
    renderUsageTable();
    setupMusicPlayer();

    if (state.running) {
        startInterval();
        syncElapsed();
    }

    const breakInput = document.getElementById('break-minutes');
    if (breakInput) {
        breakInput.value = state.breakMinutes;
        breakInput.addEventListener('change', (e) => setBreakMinutes(e.target.value));
    }

    // Initialize custom timer mode
    const isCustomMode = !!localStorage.getItem(CUSTOM_MODE_KEY);
    const customSection = document.getElementById('custom-timer-section');
    const defaultBreakSection = document.getElementById('default-break-section');

    if (isCustomMode && customSection && defaultBreakSection) {
        customSection.classList.remove('hidden');
        customSection.classList.add('flex');
        defaultBreakSection.classList.add('hidden');

        // Load saved custom values
        const savedFocus = localStorage.getItem(CUSTOM_FOCUS_KEY) || '25';
        const savedBreak = localStorage.getItem(CUSTOM_BREAK_KEY) || '5';
        const focusInput = document.getElementById('custom-focus-minutes');
        const customBreakInput = document.getElementById('custom-break-minutes');
        if (focusInput) focusInput.value = savedFocus;
        if (customBreakInput) customBreakInput.value = savedBreak;

        // Apply custom values to state
        state.focusMinutes = parseInt(savedFocus, 10) || 25;
        state.breakMinutes = parseInt(savedBreak, 10) || 5;
        if (state.phase === 'idle') {
            state.secondsLeft = state.focusMinutes * 60;
        }
    }
});

</script>

