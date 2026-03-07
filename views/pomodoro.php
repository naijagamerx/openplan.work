<?php
// Pomodoro Timer View
?>

<div class="space-y-8">
    <!-- Timer Section (Full Width) -->
    <div class="bg-black text-white rounded-2xl p-12 text-center shadow-lg">
        <div class="mb-4">
            <span id="timer-mode" class="text-gray-400 text-sm uppercase tracking-wider font-semibold">Focus Session</span>
        </div>
        
        <div id="timer-display" class="text-[120px] font-thin tabular-nums leading-none mb-8">25:00</div>
        
        <div class="flex items-center justify-center gap-3 mb-10">
            <span id="session-dots" class="flex gap-2">
                <span class="w-4 h-4 rounded-full bg-gray-700 transition-colors duration-500"></span>
                <span class="w-4 h-4 rounded-full bg-gray-700 transition-colors duration-500"></span>
                <span class="w-4 h-4 rounded-full bg-gray-700 transition-colors duration-500"></span>
                <span class="w-4 h-4 rounded-full bg-gray-700 transition-colors duration-500"></span>
            </span>
        </div>
        
        <div class="flex gap-6 justify-center">
            <button onclick="toggleTimer()" id="timer-btn" 
                    class="px-12 py-4 bg-white text-black rounded-full font-bold text-xl hover:scale-105 active:scale-95 transition-all shadow-xl">
                Start
            </button>
            <button onclick="resetTimer()" 
                    class="px-12 py-4 border-2 border-gray-700 text-white rounded-full font-bold text-xl hover:bg-gray-900 transition-all">
                Reset
            </button>
        </div>
    </div>
    
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Settings (Left) -->
        <div class="lg:col-span-2 bg-white rounded-2xl border border-gray-200 p-8 shadow-sm">
            <h3 class="font-bold text-xl text-gray-900 mb-6 flex items-center gap-2">
                <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                Timer Settings
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div>
                    <label class="block text-sm font-semibold text-gray-600 mb-2 uppercase tracking-wide">Work Duration</label>
                    <select id="work-duration" onchange="updateSettings()" class="w-full px-4 py-3 border-2 border-gray-100 rounded-xl focus:border-black outline-none transition-colors appearance-none bg-gray-50">
                        <option value="15">15 min</option>
                        <option value="25" selected>25 min</option>
                        <option value="30">30 min</option>
                        <option value="45">45 min</option>
                        <option value="50">50 min</option>
                        <option value="60">60 min</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-600 mb-2 uppercase tracking-wide">Short Break</label>
                    <select id="short-break" onchange="updateSettings()" class="w-full px-4 py-3 border-2 border-gray-100 rounded-xl focus:border-black outline-none transition-colors appearance-none bg-gray-50">
                        <option value="3">3 min</option>
                        <option value="5" selected>5 min</option>
                        <option value="10">10 min</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-600 mb-2 uppercase tracking-wide">Long Break</label>
                    <select id="long-break" onchange="updateSettings()" class="w-full px-4 py-3 border-2 border-gray-100 rounded-xl focus:border-black outline-none transition-colors appearance-none bg-gray-50">
                        <option value="10">10 min</option>
                        <option value="15" selected>15 min</option>
                        <option value="20">20 min</option>
                        <option value="30">30 min</option>
                    </select>
                </div>
            </div>
            
            <div class="flex flex-wrap items-center gap-8 mt-8 pt-8 border-t border-gray-100">
                <label class="flex items-center gap-3 cursor-pointer group">
                    <input type="checkbox" id="sound-enabled" checked class="w-5 h-5 rounded-md border-2 border-gray-200 checked:bg-black checked:border-black transition-all">
                    <span class="text-sm font-medium text-gray-700 group-hover:text-black">Sound Notifications</span>
                </label>
                <label class="flex items-center gap-3 cursor-pointer group">
                    <input type="checkbox" id="auto-start" class="w-5 h-5 rounded-md border-2 border-gray-200 checked:bg-black checked:border-black transition-all">
                    <span class="text-sm font-medium text-gray-700 group-hover:text-black">Auto-start Next Session</span>
                </label>
            </div>
        </div>
        
        <!-- Stats (Right) -->
        <div class="space-y-6">
            <div class="bg-white rounded-2xl border border-gray-200 p-8 shadow-sm flex items-center justify-between">
                <div>
                    <div class="text-sm font-bold text-gray-400 uppercase tracking-widest mb-1">Sessions</div>
                    <div class="text-4xl font-extrabold text-black" id="today-sessions">0</div>
                </div>
                <div class="w-12 h-12 bg-gray-50 rounded-full flex items-center justify-center">
                    <svg class="w-6 h-6 text-black" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path></svg>
                </div>
            </div>
            <div class="bg-white rounded-2xl border border-gray-200 p-8 shadow-sm flex items-center justify-between">
                <div>
                    <div class="text-sm font-bold text-gray-400 uppercase tracking-widest mb-1">Focused</div>
                    <div class="text-4xl font-extrabold text-black"><span id="today-minutes">0</span><span class="text-xl font-normal text-gray-400 ml-1">m</span></div>
                </div>
                <div class="w-12 h-12 bg-gray-50 rounded-full flex items-center justify-center">
                    <svg class="w-6 h-6 text-black" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                </div>
            </div>
            <div class="bg-white rounded-2xl border border-gray-200 p-8 shadow-sm flex items-center justify-between">
                <div>
                    <div class="text-sm font-bold text-gray-400 uppercase tracking-widest mb-1">Streak</div>
                    <div class="text-4xl font-extrabold text-black" id="streak">1</div>
                </div>
                <div class="w-12 h-12 bg-gray-50 rounded-full flex items-center justify-center">
                    <svg class="w-6 h-6 text-black" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                </div>
            </div>
        </div>
    </div>
</div>
</div>

<script>
// Pomodoro Timer
let timerSeconds = 25 * 60;
let timerRunning = false;
let timerInterval = null;
let currentMode = 'work'; // work, shortBreak, longBreak
let sessionsCompleted = 0;
let todaySessions = 0;
let todayMinutes = 0;

const settings = {
    work: 25,
    shortBreak: 5,
    longBreak: 15
};

function updateDisplay() {
    const mins = Math.floor(timerSeconds / 60);
    const secs = timerSeconds % 60;
    document.getElementById('timer-display').textContent = 
        `${mins.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
}

function toggleTimer() {
    const btn = document.getElementById('timer-btn');
    if (timerRunning) {
        clearInterval(timerInterval);
        btn.textContent = 'Resume';
    } else {
        timerInterval = setInterval(() => {
            timerSeconds--;
            updateDisplay();
            
            if (timerSeconds <= 0) {
                completeSession();
            }
        }, 1000);
        btn.textContent = 'Pause';
    }
    timerRunning = !timerRunning;
}

function resetTimer() {
    clearInterval(timerInterval);
    timerRunning = false;
    timerSeconds = settings.work * 60;
    currentMode = 'work';
    updateDisplay();
    updateModeDisplay();
    document.getElementById('timer-btn').textContent = 'Start';
}

function completeSession() {
    clearInterval(timerInterval);
    timerRunning = false;
    
    if (currentMode === 'work') {
        sessionsCompleted++;
        todaySessions++;
        todayMinutes += settings.work;
        updateSessionDots();
        updateStats();
        
        // Play sound
        if (document.getElementById('sound-enabled').checked) {
            playNotification();
        }
        
        showToast('Session complete! Take a break.', 'success');
        
        // Switch to break
        if (sessionsCompleted >= 4) {
            currentMode = 'longBreak';
            timerSeconds = settings.longBreak * 60;
            sessionsCompleted = 0;
        } else {
            currentMode = 'shortBreak';
            timerSeconds = settings.shortBreak * 60;
        }
    } else {
        showToast('Break over! Ready to focus?', 'info');
        currentMode = 'work';
        timerSeconds = settings.work * 60;
    }
    
    updateDisplay();
    updateModeDisplay();
    document.getElementById('timer-btn').textContent = 'Start';
    
    // Auto-start
    if (document.getElementById('auto-start').checked) {
        setTimeout(() => toggleTimer(), 1000);
    }
}

function updateModeDisplay() {
    const modeLabels = {
        work: 'Focus Session',
        shortBreak: 'Short Break',
        longBreak: 'Long Break'
    };
    document.getElementById('timer-mode').textContent = modeLabels[currentMode];
}

function updateSessionDots() {
    const dots = document.querySelectorAll('#session-dots span');
    dots.forEach((dot, i) => {
        dot.classList.toggle('bg-white', i < sessionsCompleted);
        dot.classList.toggle('bg-gray-600', i >= sessionsCompleted);
    });
}

function updateStats() {
    document.getElementById('today-sessions').textContent = todaySessions;
    document.getElementById('today-minutes').textContent = todayMinutes;
}

function updateSettings() {
    settings.work = parseInt(document.getElementById('work-duration').value);
    settings.shortBreak = parseInt(document.getElementById('short-break').value);
    settings.longBreak = parseInt(document.getElementById('long-break').value);
    
    if (!timerRunning && currentMode === 'work') {
        timerSeconds = settings.work * 60;
        updateDisplay();
    }
}

function playNotification() {
    // Simple beep sound
    const audioCtx = new (window.AudioContext || window.webkitAudioContext)();
    const oscillator = audioCtx.createOscillator();
    const gainNode = audioCtx.createGain();
    
    oscillator.connect(gainNode);
    gainNode.connect(audioCtx.destination);
    
    oscillator.frequency.value = 800;
    oscillator.type = 'sine';
    gainNode.gain.value = 0.3;
    
    oscillator.start();
    setTimeout(() => oscillator.stop(), 200);
}

// Load saved stats from localStorage
document.addEventListener('DOMContentLoaded', () => {
    const saved = localStorage.getItem('pomodoroStats');
    if (saved) {
        const stats = JSON.parse(saved);
        const today = new Date().toDateString();
        if (stats.date === today) {
            todaySessions = stats.sessions || 0;
            todayMinutes = stats.minutes || 0;
            updateStats();
        }
    }
});

// Save stats on session complete
const originalCompleteSession = completeSession;
completeSession = function() {
    originalCompleteSession();
    localStorage.setItem('pomodoroStats', JSON.stringify({
        date: new Date().toDateString(),
        sessions: todaySessions,
        minutes: todayMinutes
    }));
};
</script>
