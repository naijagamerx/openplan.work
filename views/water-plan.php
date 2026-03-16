<?php
/**
 * Water Plan Page - Manual Plan Creation
 * Create custom water plans with presets
 */

$pageTitle = 'Water Plan';
$db = new Database(getMasterPassword(), Auth::userId());

// Load water plans
$waterPlans = $db->load('water_plans') ?? [];
$config = $db->load('config');
?>

<!-- Header -->
<div class="flex items-center justify-between mb-8">
    <div>
        <h1 class="text-3xl font-bold tracking-tight text-gray-900">Water Plan</h1>
        <p class="text-gray-500 mt-1">Create your personalized hydration schedule</p>
    </div>
    <div class="flex items-center gap-3">
        <a href="?page=water-plan-history" class="flex items-center gap-2 px-4 py-2 bg-white border border-gray-200 text-gray-700 rounded-lg font-medium hover:border-black hover:bg-gray-50 transition shadow-sm text-sm">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            History
        </a>
        <a href="?page=dashboard" class="w-10 h-10 flex items-center justify-center bg-white border border-gray-200 rounded-xl hover:bg-gray-50 transition shadow-sm">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
            </svg>
        </a>
    </div>
</div>

<div class="flex flex-col gap-8">
    <!-- Main Content Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
    <!-- Manual Plan Creation -->
    <div class="lg:col-span-2 bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
        <button onclick="toggleManualSection()" class="w-full p-6 flex items-center justify-between hover:bg-gray-50 transition">
            <div class="flex items-center gap-3">
                <svg class="w-6 h-6 text-gray-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                </svg>
                <h3 class="text-lg font-bold text-gray-900">Manual Plan Creation</h3>
                <span class="text-sm text-gray-500">Create your own schedule</span>
            </div>
            <svg id="manual-chevron" class="w-5 h-5 text-gray-500 transition-transform rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
            </svg>
        </button>

        <div id="manual-section" class="border-t border-gray-200">
            <div class="p-6">
                <!-- Current Plan Summary -->
                <div id="plan-summary" class="hidden bg-gray-50 rounded-xl p-4 mb-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-base font-bold text-gray-900">Current Plan Summary</h3>
                    </div>
                </div>

                <form id="manual-plan-form" class="space-y-6">
                    <!-- Plan Settings -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase tracking-widest mb-2">Plan Name</label>
                            <input type="text" name="planName" value="My Water Plan" placeholder="My Water Plan"
                                   class="w-full px-4 py-3 border-2 border-gray-100 rounded-xl focus:border-black focus:outline-none transition-colors">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase tracking-widest mb-2">Daily Goal (L)</label>
                            <input type="number" name="dailyGoal" value="2.5" min="0.5" max="8" step="0.1"
                                   class="w-full px-4 py-3 border-2 border-gray-100 rounded-xl focus:border-black focus:outline-none transition-colors">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase tracking-widest mb-2">Drink Size (L)</label>
                            <select name="glassSize" class="w-full px-4 py-3 border-2 border-gray-100 rounded-xl focus:border-black focus:outline-none transition-colors appearance-none bg-gray-50">
                                <option value="0.2">0.20 L</option>
                                <option value="0.25" selected>0.25 L</option>
                                <option value="0.3">0.30 L</option>
                                <option value="0.5">0.50 L</option>
                            </select>
                        </div>
                    </div>

                    <!-- Quick Presets -->
                    <div class="bg-gray-50 rounded-xl p-4">
                        <p class="text-xs font-bold text-gray-500 uppercase tracking-widest mb-3">Quick Presets</p>
                        <div class="flex flex-wrap gap-2">
                            <button type="button" onclick="applyPreset('fullDay')" class="px-4 py-2 bg-white border-2 border-gray-200 text-gray-700 rounded-lg font-medium hover:bg-black hover:text-white hover:border-black transition text-sm">
                                Full Day (2.0 L)
                            </button>
                            <button type="button" onclick="applyPreset('halfDay')" class="px-4 py-2 bg-white border-2 border-gray-200 text-gray-700 rounded-lg font-medium hover:bg-black hover:text-white hover:border-black transition text-sm">
                                Half Day (1.0 L)
                            </button>
                            <button type="button" onclick="applyPreset('workDay')" class="px-4 py-2 bg-white border-2 border-gray-200 text-gray-700 rounded-lg font-medium hover:bg-black hover:text-white hover:border-black transition text-sm">
                                Work Day (1.5 L)
                            </button>
                            <button type="button" onclick="clearSchedule()" class="px-4 py-2 bg-white border-2 border-gray-200 text-gray-500 rounded-lg font-medium hover:bg-red-50 hover:text-red-600 hover:border-red-300 transition text-sm">
                                Clear All
                            </button>
                        </div>
                    </div>

                    <!-- Schedule Table -->
                    <div>
                        <div class="flex items-center justify-between mb-3">
                            <p class="text-xs font-bold text-gray-500 uppercase tracking-widest">Schedule Your Water Reminders</p>
                            <button type="button" onclick="addScheduleRow()" class="px-3 py-1.5 bg-black text-white rounded-lg font-medium hover:bg-gray-800 transition text-sm flex items-center gap-1">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                                </svg>
                                Add Reminder
                            </button>
                        </div>

                        <div class="border-2 border-gray-200 rounded-xl overflow-hidden">
                            <table class="w-full" id="schedule-table">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Time</th>
                                        <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Amount (L)</th>
                                        <th class="px-4 py-3 text-right text-xs font-bold text-gray-500 uppercase tracking-wider">Action</th>
                                    </tr>
                                </thead>
                                <tbody id="schedule-body" class="divide-y divide-gray-200">
                                    <!-- Schedule rows will be added here dynamically -->
                                </tbody>
                            </table>
                            <div id="empty-schedule" class="text-center py-8 text-gray-500">
                                <svg class="w-12 h-12 mx-auto text-gray-300 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                <p class="text-sm">No reminders scheduled yet</p>
                                <p class="text-xs text-gray-400">Add a reminder or select a preset above</p>
                            </div>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="flex gap-4">
                        <button type="submit" class="flex-1 py-4 bg-black text-white rounded-xl font-bold hover:bg-gray-800 transition shadow-lg flex items-center justify-center gap-2">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            Save Manual Plan
                        </button>
                        <button type="button" onclick="location.reload()" class="px-6 py-4 bg-white border-2 border-gray-200 text-gray-700 rounded-xl font-bold hover:bg-gray-50 transition">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>


        <!-- Daily Progress Card -->
        <div class="flex flex-col gap-6">
            <div class="bg-black rounded-2xl border border-gray-800 p-6 shadow-xl flex flex-col items-center">
                <h3 class="text-sm font-bold uppercase tracking-widest text-gray-400 mb-8">Daily Progress</h3>

                <div class="w-full text-center mb-6">
                    <div class="text-[10px] font-bold uppercase tracking-widest text-gray-500">Current Plan</div>
                    <div id="current-plan-name" class="text-lg font-bold text-white mt-2">No active plan</div>
                    <div id="current-plan-meta" class="text-xs text-gray-400 mt-1">Create a manual plan to start tracking</div>
                </div>

                <!-- Progress Circle -->
                <div class="relative w-48 h-48 flex items-center justify-center mb-8">
                    <svg class="w-full h-full transform -rotate-90">
                        <circle cx="96" cy="96" fill="transparent" r="88" stroke="#1f2937" stroke-width="12"></circle>
                        <circle id="summary-progress-circle" cx="96" cy="96" fill="transparent" r="88" stroke="#ffffff" stroke-width="12"
                                stroke-dasharray="552.92" stroke-dashoffset="552.92"
                                style="transition: stroke-dashoffset 0.5s ease-out;"></circle>
                    </svg>
                    <div class="absolute flex flex-col items-center gap-1 text-center">
                        <span class="text-[10px] font-bold uppercase tracking-widest text-white/60">Completed</span>
                        <span id="summary-completed-percent" class="text-3xl font-black text-white">0%</span>
                        <span class="text-[10px] font-bold uppercase tracking-widest text-red-400 mt-1">Missed</span>
                        <span class="text-sm font-bold text-red-400">
                            <span id="summary-missed-count">0</span>
                            <span class="text-[10px] font-bold uppercase tracking-widest">-</span>
                            <span id="summary-missed-percent">0%</span>
                        </span>
                        <span id="summary-progress-text" class="text-[11px] text-gray-400 font-medium mt-1">0.00 / 0.00 L</span>
                    </div>
                </div>

                <!-- Stats Grid -->
                <div class="grid grid-cols-2 w-full gap-4 mb-8">
                    <div class="bg-white/10 p-3 rounded-lg text-center">
                        <p class="text-[10px] text-gray-400 uppercase font-bold">Goal</p>
                        <p id="summary-goal" class="text-lg font-bold text-white">2.50<span class="text-xs font-normal ml-1">L</span></p>
                    </div>
                    <div class="bg-white/10 p-3 rounded-lg text-center">
                        <p class="text-[10px] text-gray-400 uppercase font-bold">Planned</p>
                        <p id="summary-glasses" class="text-lg font-bold text-white">0.00<span class="text-xs font-normal ml-1">L</span></p>
                    </div>
                    <div class="bg-white/10 p-3 rounded-lg text-center">
                        <p class="text-[10px] text-gray-400 uppercase font-bold">Completed</p>
                        <p id="summary-completed" class="text-lg font-bold text-white">0</p>
                    </div>
                    <div class="bg-white/10 p-3 rounded-lg text-center">
                        <p class="text-[10px] text-gray-400 uppercase font-bold">Remaining</p>
                        <p id="summary-remaining" class="text-lg font-bold text-white">0.00<span class="text-xs font-normal ml-1">L</span></p>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="flex flex-col w-full gap-3">
                    <button onclick="clearPlan()" class="w-full border border-white/20 text-white font-semibold py-3 rounded-xl hover:bg-white/10 transition-colors text-sm">
                        Clear Plan
                    </button>
                    <div class="flex gap-3">
                        <a href="?page=water-plan-details" id="view-details-link" class="flex-1 border border-white/20 py-3 rounded-xl hover:bg-white/10 transition-colors flex items-center justify-center text-sm">
                            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                            </svg>
                        </a>
                        <button onclick="saveToHistory()" class="flex-1 border border-white/20 py-3 rounded-xl hover:bg-white/10 transition-colors flex items-center justify-center">
                            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"></path>
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Hydration Insights -->
    <div class="flex flex-col gap-6">
        <h3 class="text-xl font-bold text-gray-900">Hydration Insights</h3>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="bg-white border border-gray-200 p-6 rounded-xl hover:border-black transition-colors group">
                <div class="w-12 h-12 rounded-full bg-gray-50 flex items-center justify-center mb-4 group-hover:bg-gray-100 transition-colors">
                    <svg class="w-6 h-6 text-gray-700 group-hover:text-black transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"></path>
                    </svg>
                </div>
                <h4 class="font-bold text-lg mb-2 text-gray-900">Morning Boost</h4>
                <p class="text-sm text-gray-500 leading-relaxed">Drink 0.5 L of water immediately after waking up to jumpstart your metabolism and flush out toxins.</p>
            </div>

            <div class="bg-white border border-gray-200 p-6 rounded-xl hover:border-black transition-colors group">
                <div class="w-12 h-12 rounded-full bg-gray-50 flex items-center justify-center mb-4 group-hover:bg-gray-100 transition-colors">
                    <svg class="w-6 h-6 text-gray-700 group-hover:text-black transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"></path>
                    </svg>
                </div>
                <h4 class="font-bold text-lg mb-2 text-gray-900">With Meals</h4>
                <p class="text-sm text-gray-500 leading-relaxed">Sip water during meals instead of gulping. This aids digestion and helps you feel full faster.</p>
            </div>

            <div class="bg-white border border-gray-200 p-6 rounded-xl hover:border-black transition-colors group">
                <div class="w-12 h-12 rounded-full bg-gray-50 flex items-center justify-center mb-4 group-hover:bg-gray-100 transition-colors">
                    <svg class="w-6 h-6 text-gray-700 group-hover:text-black transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                    </svg>
                </div>
                <h4 class="font-bold text-lg mb-2 text-gray-900">During Exercise</h4>
                <p class="text-sm text-gray-500 leading-relaxed">Consume 0.2 L every 20 minutes during intense activity to maintain peak physical performance levels.</p>
            </div>
        </div>
    </div>
</div>

<script>
const WATER_PRE_ALERT_MINUTES = 5;
const WATER_MISSED_GRACE_MINUTES = 10;

let currentPlan = null;
let scheduleRows = [];
let waterReminderInterval = null;
let waterReminderMinuteMap = {};

const PRESETS = {
    fullDay: [
        { time: '07:00', amount: 0.25 },
        { time: '09:00', amount: 0.25 },
        { time: '11:00', amount: 0.25 },
        { time: '13:00', amount: 0.25 },
        { time: '15:00', amount: 0.25 },
        { time: '17:00', amount: 0.25 },
        { time: '19:00', amount: 0.25 },
        { time: '21:00', amount: 0.25 }
    ],
    halfDay: [
        { time: '08:00', amount: 0.25 },
        { time: '10:00', amount: 0.25 },
        { time: '12:00', amount: 0.25 },
        { time: '14:00', amount: 0.25 }
    ],
    workDay: [
        { time: '09:00', amount: 0.25 },
        { time: '11:00', amount: 0.25 },
        { time: '13:00', amount: 0.25 },
        { time: '15:00', amount: 0.25 },
        { time: '17:00', amount: 0.25 },
        { time: '19:00', amount: 0.25 }
    ]
};

document.addEventListener('DOMContentLoaded', async () => {
    await loadExistingPlan();
    if (scheduleRows.length === 0) {
        addScheduleRow();
    }
});

function litersToMl(value) {
    const parsed = parseFloat(value);
    if (!Number.isFinite(parsed) || parsed <= 0) {
        return 0;
    }
    return Math.round(parsed * 1000);
}

function mlToLiters(ml) {
    const parsed = Number(ml) || 0;
    return parsed / 1000;
}

function formatLiters(ml) {
    return mlToLiters(ml).toFixed(2);
}

function getPlanGlassSizeMl(plan = currentPlan) {
    const size = Number(plan?.glassSize) || 250;
    return size > 0 ? size : 250;
}

function getScheduleAmountMl(item, plan = currentPlan) {
    const raw = Number(item?.amount) || 0;
    const glassSize = getPlanGlassSizeMl(plan);
    if (raw <= 0) {
        return glassSize;
    }
    return raw <= 20 ? Math.round(raw * glassSize) : Math.round(raw);
}

function getScheduledTimeToday(time) {
    const [hours, minutes] = String(time || '').split(':');
    const scheduled = new Date();
    scheduled.setHours(parseInt(hours || '0', 10), parseInt(minutes || '0', 10), 0, 0);
    return scheduled;
}

function formatTime(time) {
    if (!time) return '--:--';
    const [hours, minutes] = time.split(':');
    const hour = parseInt(hours, 10);
    const ampm = hour >= 12 ? 'PM' : 'AM';
    const hour12 = hour % 12 || 12;
    return `${hour12}:${minutes} ${ampm}`;
}

function toggleManualSection() {
    const section = document.getElementById('manual-section');
    const chevron = document.getElementById('manual-chevron');
    if (!section || !chevron) return;
    const hidden = section.classList.toggle('hidden');
    chevron.classList.toggle('rotate-180', !hidden);
}

function addScheduleRow(time = '', amount = 0.25) {
    const tbody = document.getElementById('schedule-body');
    const emptyState = document.getElementById('empty-schedule');
    const rowId = 'row-' + Date.now() + '-' + Math.random().toString(36).slice(2, 9);

    const value = Number(amount) > 0 ? Number(amount).toFixed(2) : '0.25';
    const row = document.createElement('tr');
    row.id = rowId;
    row.className = 'hover:bg-gray-50';
    row.innerHTML = `
        <td class="px-4 py-3">
            <input type="time" name="schedule_time[]" value="${time}"
                   class="w-full px-3 py-2 border-2 border-gray-200 rounded-lg focus:border-black focus:outline-none transition-colors">
        </td>
        <td class="px-4 py-3">
            <input type="number" name="schedule_amount[]" value="${value}" min="0.10" max="2.00" step="0.05"
                   class="w-24 px-3 py-2 border-2 border-gray-200 rounded-lg focus:border-black focus:outline-none transition-colors">
        </td>
        <td class="px-4 py-3 text-right">
            <button type="button" onclick="removeScheduleRow('${rowId}')" class="text-red-500 hover:text-red-700 transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                </svg>
            </button>
        </td>
    `;

    tbody.appendChild(row);
    scheduleRows.push(rowId);
    emptyState.classList.add('hidden');
}

function removeScheduleRow(rowId) {
    const row = document.getElementById(rowId);
    if (!row) return;
    row.remove();
    scheduleRows = scheduleRows.filter(id => id !== rowId);
    if (scheduleRows.length === 0) {
        document.getElementById('empty-schedule').classList.remove('hidden');
    }
}

function clearSchedule() {
    document.getElementById('schedule-body').innerHTML = '';
    scheduleRows = [];
    document.getElementById('empty-schedule').classList.remove('hidden');
}

function applyPreset(presetName) {
    clearSchedule();
    const preset = PRESETS[presetName];
    if (!preset) return;
    preset.forEach(item => addScheduleRow(item.time, item.amount));
    showToast(`Applied ${presetName} preset`, 'success');
}

document.getElementById('manual-plan-form').addEventListener('submit', async (e) => {
    e.preventDefault();

    const formData = new FormData(e.target);
    const planName = formData.get('planName') || 'My Water Plan';
    const dailyGoalMl = litersToMl(formData.get('dailyGoal')) || 2500;
    const glassSizeMl = litersToMl(formData.get('glassSize')) || 250;

    const times = formData.getAll('schedule_time[]');
    const amounts = formData.getAll('schedule_amount[]');
    const schedule = [];

    for (let i = 0; i < times.length; i++) {
        const time = times[i];
        if (!time) continue;
        const amountMl = litersToMl(amounts[i]) || glassSizeMl;
        schedule.push({
            time: time,
            amount: amountMl
        });
    }

    if (schedule.length === 0) {
        showToast('Please add at least one reminder time', 'warning');
        return;
    }

    schedule.sort((a, b) => a.time.localeCompare(b.time));

    try {
        const response = await api.post('api/habits.php?action=create_manual_plan', {
            name: planName,
            dailyGoal: dailyGoalMl,
            glassSize: glassSizeMl,
            schedule: schedule,
            csrf_token: CSRF_TOKEN
        });

        if (response.success && response.data) {
            currentPlan = response.data;
            displaySummary(response.data);
            await requestAndScheduleNotifications();
            showToast('Water plan created!', 'success');
        } else {
            throw new Error(response.error || 'Failed to create plan');
        }
    } catch (error) {
        if (error.status === 401 || error.message?.includes('401')) {
            window.location.href = '?page=login&reason=session_expired';
            return;
        }
        showToast(error.message || 'Failed to create plan', 'error');
    }
});

async function loadExistingPlan() {
    try {
        const response = await api.get('api/habits.php?action=get_water_plan');
        if (response && response.success && response.data) {
            currentPlan = response.data;
            displaySummary(response.data);
            scheduleNotificationsIfAllowed();
        } else {
            setNoPlanState();
        }
    } catch (error) {
        if (error.status === 404) {
            setNoPlanState();
        } else if (error.status === 401 || error.message?.includes('401')) {
            window.location.href = '?page=login&reason=session_expired';
        }
    }
}

function displaySummary(plan) {
    const summarySection = document.getElementById('plan-summary');
    if (summarySection) {
        summarySection.classList.remove('hidden');
    }

    const goalMl = Number(plan.dailyGoal) || 2500;
    const schedule = Array.isArray(plan.schedule) ? plan.schedule : [];
    const now = new Date();
    const planCreatedAt = plan.createdAt ? new Date(plan.createdAt) : new Date();
    const graceMs = WATER_MISSED_GRACE_MINUTES * 60 * 1000;

    schedule.forEach(item => {
        if (!item || item.completed || item.missed || !item.time) return;
        const scheduled = getScheduledTimeToday(item.time);
        if (scheduled >= planCreatedAt && (now.getTime() - scheduled.getTime() > graceMs)) {
            item.missed = true;
        }
    });

    const plannedMl = schedule.reduce((sum, item) => sum + getScheduleAmountMl(item, plan), 0);
    const completedMl = schedule.reduce((sum, item) => sum + (item.completed ? getScheduleAmountMl(item, plan) : 0), 0);
    const completedCount = schedule.filter(item => item?.completed).length;
    const missedCount = schedule.filter(item => item?.missed).length;
    const remainingMl = Math.max(0, plannedMl - completedMl);

    const completedPercent = plannedMl > 0 ? Math.round((completedMl / plannedMl) * 100) : 0;
    const missedPercent = schedule.length > 0 ? Math.round((missedCount / schedule.length) * 100) : 0;

    const summaryGoalEl = document.getElementById('summary-goal');
    if (summaryGoalEl) {
        summaryGoalEl.innerHTML = `${formatLiters(goalMl)}<span class="text-xs font-normal ml-1">L</span>`;
    }

    const summaryPlannedEl = document.getElementById('summary-glasses');
    if (summaryPlannedEl) {
        summaryPlannedEl.innerHTML = `${formatLiters(plannedMl)}<span class="text-xs font-normal ml-1">L</span>`;
    }

    const summaryCompletedEl = document.getElementById('summary-completed');
    if (summaryCompletedEl) {
        summaryCompletedEl.textContent = String(completedCount);
    }

    const summaryRemainingEl = document.getElementById('summary-remaining');
    if (summaryRemainingEl) {
        summaryRemainingEl.innerHTML = `${formatLiters(remainingMl)}<span class="text-xs font-normal ml-1">L</span>`;
    }

    const summaryCompletedPercentEl = document.getElementById('summary-completed-percent');
    if (summaryCompletedPercentEl) {
        summaryCompletedPercentEl.textContent = `${completedPercent}%`;
    }

    const summaryMissedCountEl = document.getElementById('summary-missed-count');
    if (summaryMissedCountEl) {
        summaryMissedCountEl.textContent = String(missedCount);
    }

    const summaryMissedPercentEl = document.getElementById('summary-missed-percent');
    if (summaryMissedPercentEl) {
        summaryMissedPercentEl.textContent = `${missedPercent}%`;
    }

    const summaryProgressTextEl = document.getElementById('summary-progress-text');
    if (summaryProgressTextEl) {
        summaryProgressTextEl.textContent = `${formatLiters(completedMl)} / ${formatLiters(goalMl)} L`;
    }

    const planNameEl = document.getElementById('current-plan-name');
    if (planNameEl) {
        planNameEl.textContent = plan.name || 'Water Plan';
    }

    const planMetaEl = document.getElementById('current-plan-meta');
    if (planMetaEl) {
        planMetaEl.textContent = schedule.length > 0
            ? `${schedule.length} reminders - ${formatLiters(goalMl)} L goal`
            : 'No reminders scheduled yet';
    }

    const circle = document.getElementById('summary-progress-circle');
    if (circle) {
        const circumference = 552.92;
        const offset = circumference - (completedPercent / 100) * circumference;
        circle.style.strokeDashoffset = offset;
    }
}

function setNoPlanState() {
    currentPlan = null;
    clearWaterNotificationLoop();
    if (window.App && App.waterPlanNotifications) {
        App.waterPlanNotifications.bootstrap({ forceRefresh: true, silent: true });
    }

    const summarySection = document.getElementById('plan-summary');
    if (summarySection) {
        summarySection.classList.add('hidden');
    }

    const planNameEl = document.getElementById('current-plan-name');
    if (planNameEl) {
        planNameEl.textContent = 'No active plan';
    }

    const planMetaEl = document.getElementById('current-plan-meta');
    if (planMetaEl) {
        planMetaEl.textContent = 'Create a manual plan to start tracking';
    }

    document.getElementById('summary-goal').innerHTML = '0.00<span class="text-xs font-normal ml-1">L</span>';
    document.getElementById('summary-glasses').innerHTML = '0.00<span class="text-xs font-normal ml-1">L</span>';
    document.getElementById('summary-completed').textContent = '0';
    document.getElementById('summary-remaining').innerHTML = '0.00<span class="text-xs font-normal ml-1">L</span>';
    document.getElementById('summary-completed-percent').textContent = '0%';
    document.getElementById('summary-missed-count').textContent = '0';
    document.getElementById('summary-missed-percent').textContent = '0%';
    document.getElementById('summary-progress-text').textContent = '0.00 / 0.00 L';

    const circle = document.getElementById('summary-progress-circle');
    if (circle) {
        circle.style.strokeDashoffset = 552.92;
    }
}

function scheduleNotificationsIfAllowed() {
    if (typeof Notification === 'undefined') return;
    if (Notification.permission === 'granted') {
        scheduleWaterNotifications();
    }
}

async function requestAndScheduleNotifications() {
    if (typeof Notification === 'undefined') {
        return;
    }
    let permission = Notification.permission;
    if (permission !== 'granted') {
        if (typeof requestNotificationPermission === 'function') {
            permission = await requestNotificationPermission();
        } else {
            permission = await Notification.requestPermission();
        }
    }
    if (permission === 'granted') {
        scheduleWaterNotifications();
        showToast('Aggressive reminders are active.', 'success');
    } else {
        showToast('Enable notifications to get reminders.', 'info');
    }
}

function clearWaterNotificationLoop() {
    // Local page loop is deprecated; shared App module handles cross-page reminders.
    waterReminderInterval = null;
    waterReminderMinuteMap = {};
}

function scheduleWaterNotifications() {
    if (!window.App || !App.waterPlanNotifications) {
        return;
    }
    App.waterPlanNotifications.bootstrap({ forceRefresh: true });
}

function runAggressiveWaterReminderCheck() {
    if (!window.App || !App.waterPlanNotifications) {
        return;
    }
    App.waterPlanNotifications.runTick();
}

function showWaterReminder(item, scheduled, now) {
    const amountMl = getScheduleAmountMl(item);
    const litersText = `${formatLiters(amountMl)} L`;
    const dueText = `due at ${formatTime(item.time)}`;
    const message = now < scheduled
        ? `Hydration reminder: ${litersText} ${dueText}.`
        : `Hydration reminder: ${litersText} still pending (${dueText}).`;

    if (App.notifications && typeof App.notifications.playSound === 'function') {
        App.notifications.playSound();
    }

    showToast(message, 'info');

    if (App.notifications && typeof App.notifications.send === 'function') {
        App.notifications.send('Water Reminder', {
            body: message,
            tag: 'water-reminder-' + (item.id || item.time),
            requireInteraction: true
        });
    }
}

async function clearPlan() {
    currentPlan = null;
    setNoPlanState();
    try {
        await api.post('api/habits.php?action=clear_water_plan', {
            csrf_token: CSRF_TOKEN
        });
        if (window.App && App.waterPlanNotifications) {
            App.waterPlanNotifications.bootstrap({ forceRefresh: true, silent: true });
        }
        showToast('Plan cleared', 'info');
    } catch (error) {
        if (error.status !== 401) {
            showToast('Failed to clear plan', 'error');
        }
    }
}

function saveToHistory() {
    showToast('Progress saved to history', 'success');
}
</script>

