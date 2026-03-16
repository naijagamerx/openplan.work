<?php
/**
 * Water Plan Details Page
 * Displays plan with planned vs actual tracking, missed reminders, and progress
 */

$pageTitle = 'Plan Details';
$planId = $_GET['planId'] ?? $_GET['id'] ?? null;
?>

<div class="w-full px-4 py-8 bg-gray-50 min-h-screen">
    <!-- Header Banner -->
    <div class="relative mb-8 overflow-hidden rounded-2xl bg-gradient-to-br from-black via-gray-800 to-gray-600">
        <svg class="absolute right-0 top-0 h-full w-1/2 opacity-10" viewBox="0 0 200 200" preserveAspectRatio="none">
            <defs>
                <linearGradient id="water-gradient-mono" x1="0%" y1="0%" x2="0%" y2="100%">
                    <stop offset="0%" style="stop-color:#ffffff;stop-opacity:0.3"/>
                    <stop offset="100%" style="stop-color:#ffffff;stop-opacity:0"/>
                </linearGradient>
            </defs>
            <path d="M0,200 Q50,180 100,200 T200,200 T300,200 V200 H0 Z"
                  fill="white" opacity="0.2">
                <animate attributeName="d"
                         dur="4s"
                         repeatCount="indefinite"
                         values="M0,200 Q50,180 100,200 T200,200 T300,200 V200 H0 Z;
                                 M0,200 Q50,220 100,200 T200,200 T300,200 V200 H0 Z;
                                 M0,200 Q50,180 100,200 T200,200 T300,200 V200 H0 Z"/>
            </path>
        </svg>

        <div class="relative z-10 px-8 py-12 flex items-center justify-between">
            <div>
                <h1 class="text-4xl font-bold text-white mb-2">Plan Details</h1>
                <p class="text-gray-300 text-lg">Track your daily hydration progress</p>
            </div>
            <div class="flex items-center gap-4">
                <a href="?page=water-plan-history" class="w-10 h-10 flex items-center justify-center bg-white/10 border border-white/20 rounded-xl hover:bg-white/20 transition backdrop-blur-sm">
                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </a>
                <a href="?page=water-plan" class="px-6 py-3 bg-white text-black rounded-xl font-bold hover:bg-gray-100 transition shadow-lg">
                    Create Plan
                </a>
            </div>
        </div>
    </div>

    <!-- Loading State -->
    <div id="details-loading" class="flex items-center justify-center py-20">
        <div class="inline-flex items-center gap-3">
            <div class="w-8 h-8 border-4 border-black border-t-transparent rounded-full animate-spin"></div>
            <span class="text-gray-600">Loading plan details...</span>
        </div>
    </div>

    <!-- Error State -->
    <div id="details-error" class="hidden text-center py-20 bg-white rounded-2xl border border-gray-200">
        <div class="w-20 h-20 bg-red-50 rounded-full flex items-center justify-center mx-auto mb-4">
            <svg class="w-10 h-10 text-red-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
            </svg>
        </div>
        <h3 class="text-xl font-bold text-gray-900">Plan not found</h3>
        <p class="text-gray-500 mt-2">The requested plan could not be loaded</p>
        <a href="?page=water-plan" class="mt-6 inline-block px-6 py-2 bg-black text-white rounded-lg font-medium hover:bg-gray-800 transition">Create New Plan</a>
    </div>

    <!-- Plan Details Content -->
    <div id="details-content" class="hidden">
        <!-- Progress Overview Cards -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
            <div class="bg-white rounded-2xl border border-gray-200 p-6 shadow-sm">
                <div class="text-xs font-bold text-gray-500 uppercase tracking-widest mb-1">Planned</div>
                <div class="text-3xl font-bold text-blue-600" id="detail-planned">0</div>
                <div class="text-xs text-gray-500 mt-1">liters planned</div>
            </div>
            <div class="bg-white rounded-2xl border border-gray-200 p-6 shadow-sm">
                <div class="text-xs font-bold text-gray-500 uppercase tracking-widest mb-1">Completed</div>
                <div class="text-3xl font-bold text-green-600" id="detail-completed">0</div>
                <div class="text-xs text-gray-500 mt-1">liters logged</div>
            </div>
            <div class="bg-white rounded-2xl border border-gray-200 p-6 shadow-sm">
                <div class="text-xs font-bold text-gray-500 uppercase tracking-widest mb-1">Missed</div>
                <div class="text-3xl font-bold text-red-600" id="detail-missed">0</div>
                <div class="text-xs text-gray-500 mt-1">reminders</div>
            </div>
            <div class="bg-white rounded-2xl border border-gray-200 p-6 shadow-sm">
                <div class="text-xs font-bold text-gray-500 uppercase tracking-widest mb-1">Progress</div>
                <div class="text-3xl font-bold text-gray-900" id="detail-percent">0%</div>
                <div class="text-xs text-gray-500 mt-1">completed</div>
            </div>
        </div>

        <!-- Daily Hydration Inspiration (AI Quote) -->
        <div class="bg-white rounded-2xl border border-gray-200 p-6 shadow-sm mb-8">
            <div class="flex items-start gap-4">
                <div class="w-12 h-12 bg-blue-100 rounded-xl flex items-center justify-center flex-shrink-0">
                    <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"></path>
                    </svg>
                </div>
                <div class="flex-1">
                    <h3 class="text-sm font-bold text-gray-500 uppercase tracking-widest mb-2">Daily Hydration Inspiration</h3>
                    <p id="water-quote-details" class="text-lg font-medium text-gray-900 italic">"Water is the driving force of all nature."</p>
                    <div class="mt-3 flex flex-wrap items-center gap-4">
                        <div class="flex items-center gap-2 text-sm text-gray-600">
                            <svg class="w-4 h-4 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"></path>
                            </svg>
                            <span id="water-tip-details">Keep a water bottle at your desk</span>
                        </div>
                        <div class="flex items-center gap-2 text-sm text-gray-600">
                            <svg class="w-4 h-4 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <span id="water-fact-details">Your body is 60% water</span>
                        </div>
                    </div>
                </div>
                <button onclick="getWaterQuoteDetails()" class="px-4 py-2 bg-gray-50 hover:bg-gray-100 border border-gray-200 rounded-lg text-sm font-medium text-gray-700 hover:text-gray-900 transition flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                    </svg>
                    New Quote
                </button>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Cup Animation & Quick Actions -->
            <div class="lg:col-span-1">
                <div class="bg-white rounded-2xl border border-gray-200 p-6 shadow-sm sticky top-8">
                    <h2 class="text-xl font-bold text-gray-900 mb-6">Today's Progress</h2>

                    <!-- Cup SVG Animation -->
                    <div class="relative mx-auto w-48 h-56 mb-6">
                        <svg viewBox="0 0 200 280" class="w-full h-full drop-shadow-xl">
                            <defs>
                                <clipPath id="cup-clip-details">
                                    <path d="M30,60 L50,240 Q50,260 70,260 L130,260 Q150,260 150,240 L170,60 Z"/>
                                </clipPath>
                                <linearGradient id="water-gradient-details" x1="0%" y1="0%" x2="0%" y2="100%">
                                    <stop offset="0%" style="stop-color:#3B82F6"/>
                                    <stop offset="100%" style="stop-color:#1D4ED8"/>
                                </linearGradient>
                                <linearGradient id="glass-gradient-details" x1="0%" y1="0%" x2="100%" y2="0%">
                                    <stop offset="0%" style="stop-color:#E0F2FE"/>
                                    <stop offset="50%" style="stop-color:#BAE6FD"/>
                                    <stop offset="100%" style="stop-color:#E0F2FE"/>
                                </linearGradient>
                            </defs>

                            <!-- Cup outline -->
                            <path d="M30,60 L50,240 Q50,260 70,260 L130,260 Q150,260 150,240 L170,60"
                                  fill="url(#glass-gradient-details)" stroke="#0EA5E9" stroke-width="3"/>

                            <!-- Water fill with animation -->
                            <g clip-path="url(#cup-clip-details)">
                                <!-- Water background -->
                                <rect id="cup-water-fill" x="0" y="260" width="200" height="200"
                                      fill="url(#water-gradient-details)" class="transition-all duration-500 ease-out"/>

                                <!-- Wave animation layer 1 -->
                                <g id="wave1-details" class="transition-transform duration-500">
                                    <path d="M0,200 Q50,180 100,200 T200,200 T300,200 V280 H0 Z"
                                          fill="#60A5FA" opacity="0.6">
                                        <animate attributeName="d"
                                                 dur="3s"
                                                 repeatCount="indefinite"
                                                 values="M0,200 Q50,180 100,200 T200,200 T300,200 V280 H0 Z;
                                                         M0,200 Q50,220 100,200 T200,200 T300,200 V280 H0 Z;
                                                         M0,200 Q50,180 100,200 T200,200 T300,200 V280 H0 Z"/>
                                    </path>
                                </g>

                                <!-- Wave animation layer 2 -->
                                <g id="wave2-details" class="transition-transform duration-500">
                                    <path d="M0,210 Q50,230 100,210 T200,210 T300,210 V280 H0 Z"
                                          fill="#93C5FD" opacity="0.5">
                                        <animate attributeName="d"
                                                 dur="2.5s"
                                                 repeatCount="indefinite"
                                                 values="M0,210 Q50,190 100,210 T200,210 T300,210 V280 H0 Z;
                                                         M0,210 Q50,230 100,210 T200,210 T300,210 V280 H0 Z;
                                                         M0,210 Q50,190 100,210 T200,210 T300,210 V280 H0 Z"/>
                                    </path>
                                </g>

                                <!-- Bubbles -->
                                <circle cx="80" cy="220" r="4" fill="white" opacity="0.6">
                                    <animate attributeName="cy" values="220;180;220" dur="2s" repeatCount="indefinite"/>
                                    <animate attributeName="opacity" values="0.6;0.2;0.6" dur="2s" repeatCount="indefinite"/>
                                </circle>
                                <circle cx="120" cy="240" r="3" fill="white" opacity="0.5">
                                    <animate attributeName="cy" values="240;190;240" dur="2.5s" repeatCount="indefinite"/>
                                    <animate attributeName="opacity" values="0.5;0.1;0.5" dur="2.5s" repeatCount="indefinite"/>
                                </circle>
                                <circle cx="95" cy="250" r="5" fill="white" opacity="0.4">
                                    <animate attributeName="cy" values="250;200;250" dur="3s" repeatCount="indefinite"/>
                                    <animate attributeName="opacity" values="0.4;0.1;0.4" dur="3s" repeatCount="indefinite"/>
                                </circle>
                            </g>

                            <!-- Cup shine -->
                            <path d="M55,80 Q60,120 55,160" stroke="white" stroke-width="4" fill="none" opacity="0.4" stroke-linecap="round"/>

                            <!-- Cup handle -->
                            <path d="M170,80 Q195,80 195,120 Q195,160 170,160"
                                  fill="none" stroke="#0EA5E9" stroke-width="8" stroke-linecap="round"/>
                            <path d="M170,85 Q190,85 190,120 Q190,155 170,155"
                                  fill="none" stroke="#E0F2FE" stroke-width="4" stroke-linecap="round"/>
                        </svg>

                        <!-- Percentage overlay -->
                        <div id="water-percentage-overlay" class="absolute inset-0 flex items-center justify-center pointer-events-none">
                            <span id="cup-percent-text" class="text-4xl font-bold text-white drop-shadow-md">0%</span>
                        </div>
                    </div>

                    <!-- Progress Text -->
                    <div class="space-y-3 text-center mb-6">
                        <div class="text-sm text-gray-500">
                            <span id="cups-completed">0.00</span> L of <span id="cups-planned">0.00</span> L
                        </div>
                    </div>

                    <!-- Quick Add Buttons -->
                    <div class="flex justify-center gap-2 mb-4">
                        <button onclick="quickAddWater()" data-quick-add="1" class="px-4 py-3 bg-black text-white rounded-lg font-medium hover:bg-gray-800 transition flex items-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                            </svg>
                            Log 1 Drink
                        </button>
                    </div>
                    <p id="quick-add-hint" class="text-xs text-gray-500 text-center"></p>

                    <!-- View History Button -->
                    <a href="?page=water-plan-history" class="block w-full py-3 bg-gray-50 text-gray-700 rounded-xl font-medium hover:bg-gray-100 transition text-center">
                        View History
                    </a>
                </div>
            </div>

            <!-- Planned vs Actual Table -->
            <div class="lg:col-span-2">
                <div class="bg-white rounded-2xl border border-gray-200 p-6 shadow-sm">
                    <div class="flex items-center justify-between mb-6">
                        <h2 class="text-xl font-bold text-gray-900">Planned vs. Actual</h2>
                        <div class="flex items-center gap-2 text-xs">
                            <span class="flex items-center gap-1 px-2 py-1 bg-green-100 text-green-700 rounded-full">
                                <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                                Completed
                            </span>
                            <span class="flex items-center gap-1 px-2 py-1 bg-red-100 text-red-700 rounded-full">
                                <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
                                Missed
                            </span>
                            <span class="flex items-center gap-1 px-2 py-1 bg-gray-100 text-gray-600 rounded-full">
                                <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"/></svg>
                                Scheduled
                            </span>
                        </div>
                    </div>

                    <!-- Schedule Table -->
                    <div class="overflow-x-auto">
                        <table class="w-full" id="schedule-table">
                            <thead>
                                <tr class="border-b-2 border-gray-200">
                                    <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Time</th>
                                    <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Planned</th>
                                    <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Actual</th>
                                    <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Status</th>
                                    <th class="px-4 py-3 text-right text-xs font-bold text-gray-500 uppercase tracking-wider">Action</th>
                                </tr>
                            </thead>
                            <tbody id="schedule-body" class="divide-y divide-gray-200">
                                <!-- Schedule rows will be inserted here -->
                            </tbody>
                        </table>
                    </div>

                    <!-- Empty State -->
                    <div id="empty-schedule" class="hidden text-center py-12">
                        <svg class="w-16 h-16 mx-auto text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <h3 class="text-lg font-bold text-gray-900 mb-1">No schedule yet</h3>
                        <p class="text-gray-500 mb-4">Create a water plan to get started</p>
                        <a href="?page=water-plan" class="inline-block px-4 py-2 bg-black text-white rounded-lg font-medium hover:bg-gray-800 transition">
                            Create Plan
                        </a>
                    </div>
                </div>

                <!-- Missed Reminders Summary -->
                <div id="missed-section" class="mt-6 bg-red-50 rounded-2xl border border-red-200 p-6 hidden">
                    <div class="flex items-center gap-3 mb-4">
                        <div class="w-10 h-10 bg-red-100 rounded-full flex items-center justify-center">
                            <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-lg font-bold text-gray-900">Missed Reminders</h3>
                            <p class="text-sm text-gray-600">These reminders were sent but not acknowledged</p>
                        </div>
                    </div>
                    <div id="missed-list" class="space-y-2">
                        <!-- Missed reminders will be listed here -->
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const MISSED_GRACE_MINUTES = 15;
const PRE_ALERT_MINUTES = 5;

let currentPlan = null;
let dailyTracking = null;
let planId = '<?php echo $planId ?? ""; ?>';
let autoMissIntervalId = null;
let aggressiveReminderIntervalId = null;
let reminderMinuteMap = {};
let dueQuickAddItemId = null;

function getPlanCreatedAt() {
    return currentPlan?.createdAt ? new Date(currentPlan.createdAt) : new Date();
}

function getPlanGlassSizeMl() {
    const size = Number(currentPlan?.glassSize) || 250;
    return size > 0 ? size : 250;
}

function getScheduleAmountMl(item) {
    const raw = Number(item?.amount) || 0;
    const glassSize = getPlanGlassSizeMl();
    if (raw <= 0) return glassSize;
    return raw <= 20 ? Math.round(raw * glassSize) : Math.round(raw);
}

function formatLitersFromMl(amountMl) {
    return `${(Number(amountMl || 0) / 1000).toFixed(2)} L`;
}

function getScheduledTime(item) {
    const scheduled = new Date(getPlanCreatedAt());
    const [hours, minutes] = String(item?.time || '').split(':');
    scheduled.setHours(parseInt(hours || '0', 10), parseInt(minutes || '0', 10), 0, 0);
    return scheduled;
}

function maybeSendAggressiveReminder(item, scheduled, now) {
    const amountText = formatLitersFromMl(getScheduleAmountMl(item));
    const dueText = formatTime(item.time);
    const body = now < scheduled
        ? `Drink ${amountText} at ${dueText}. Reminder repeats every minute.`
        : `Drink ${amountText}. This reminder is still pending (${dueText}).`;

    if (window.App && App.notifications && typeof App.notifications.playSound === 'function') {
        App.notifications.playSound();
    }
    if (typeof showToast === 'function') {
        showToast(body, 'info');
    }
    if (window.App && App.notifications && typeof App.notifications.send === 'function') {
        App.notifications.send('Water Reminder', {
            body,
            tag: `water-reminder-${item.id || item.time}`,
            requireInteraction: true
        });
    }
}

function runAggressiveReminderTick() {
    if (!currentPlan || !Array.isArray(currentPlan.schedule)) return;

    const now = new Date();
    const minuteKey = Math.floor(now.getTime() / 60000);
    const planCreatedAt = getPlanCreatedAt();

    currentPlan.schedule.forEach(item => {
        if (!item || item.completed || item.missed || !item.time) return;
        const scheduled = getScheduledTime(item);
        if (scheduled < planCreatedAt) return;
        const startWindow = scheduled.getTime() - (PRE_ALERT_MINUTES * 60 * 1000);
        if (now.getTime() < startWindow) return;

        const key = item.id || item.time;
        if (reminderMinuteMap[key] === minuteKey) return;
        reminderMinuteMap[key] = minuteKey;
        maybeSendAggressiveReminder(item, scheduled, now);
    });
}

function stopAggressiveReminderLoop() {
    // Local loop is disabled; shared App module handles reminders globally.
    aggressiveReminderIntervalId = null;
    reminderMinuteMap = {};
}

function startAggressiveReminderLoop() {
    stopAggressiveReminderLoop();
    if (window.App && App.waterPlanNotifications) {
        App.waterPlanNotifications.bootstrap({ forceRefresh: true });
    }
}

const PLAN_ID = '<?php echo $planId; ?>';

async function refreshTracking() {
    // Only fetch daily tracking if we're looking at today's active plan
    const trackingResponse = await api.get('api/habits.php?action=get_daily_tracking');
    if (trackingResponse.success && trackingResponse.data) {
        dailyTracking = trackingResponse.data;
        // If no PLAN_ID specified, use the active one from tracking
        if (!PLAN_ID && dailyTracking.activePlan) {
            currentPlan = dailyTracking.activePlan;
        }
    }
}

async function loadPlanAndTracking() {
    try {
        await refreshTracking();

        if (!currentPlan) {
            const url = PLAN_ID ? `api/habits.php?action=get_water_plan&planId=${PLAN_ID}` : 'api/habits.php?action=get_water_plan';
            const planResponse = await api.get(url);
            if (planResponse.success && planResponse.data) {
                currentPlan = planResponse.data;
            }
        }

        if (!currentPlan) {
            showError();
            return;
        }

        const didMark = await autoMarkMissedReminders();
        if (didMark) {
            await refreshTracking();
        }

        displayPlanDetails();
        startAggressiveReminderLoop();
    } catch (error) {
        console.error('Failed to load plan:', error);
        showError();
    }
}

async function autoMarkMissedReminders() {
    if (!currentPlan || !currentPlan.schedule || !currentPlan.id) {
        return false;
    }

    const now = new Date();
    const planCreatedAt = getPlanCreatedAt();
    const graceMs = MISSED_GRACE_MINUTES * 60 * 1000;
    
    const toMark = currentPlan.schedule.filter(item => {
        if (!item || item.completed || item.missed || !item.time || !item.id) {
            return false;
        }
        const scheduled = getScheduledTime(item);
        
        // Don't mark as missed if scheduled before the plan was created
        if (scheduled < planCreatedAt) {
            return false;
        }
        
        return now.getTime() - scheduled.getTime() > graceMs;
    });

    if (toMark.length === 0) {
        return false;
    }

    let marked = false;
    for (const item of toMark) {
        try {
            const response = await api.post('api/habits.php?action=mark_reminder_missed', {
                planId: currentPlan.id,
                scheduleItemId: item.id,
                csrf_token: CSRF_TOKEN
            });
            if (response.success) {
                item.missed = true;
                marked = true;
            }
        } catch (error) {
            console.error('Failed to mark missed reminder:', error);
        }
    }

    return marked;
}

function displayPlanDetails() {
    if (!currentPlan) {
        showError();
        return;
    }

    // Hide loading, show content
    document.getElementById('details-loading').classList.add('hidden');
    document.getElementById('details-content').classList.remove('hidden');

    const schedule = currentPlan.schedule || [];
    const plannedMl = schedule.reduce((sum, item) => sum + getScheduleAmountMl(item), 0);
    const completedMl = schedule.reduce((sum, item) => sum + (item.completed ? getScheduleAmountMl(item) : 0), 0);
    const missed = schedule.filter(s => s.missed).length;
    const percent = plannedMl > 0 ? Math.round((completedMl / plannedMl) * 100) : 0;

    document.getElementById('detail-planned').textContent = formatLitersFromMl(plannedMl).replace(' L', '');
    document.getElementById('detail-completed').textContent = formatLitersFromMl(completedMl).replace(' L', '');
    document.getElementById('detail-missed').textContent = missed;
    document.getElementById('detail-percent').textContent = percent + '%';

    updateCupAnimation(percent, completedMl, plannedMl);

    // Build schedule table
    const tbody = document.getElementById('schedule-body');
    const emptyState = document.getElementById('empty-schedule');

    if (schedule.length === 0) {
        tbody.innerHTML = '';
        emptyState.classList.remove('hidden');
        return;
    }

    emptyState.classList.add('hidden');

    const now = new Date();
    const planCreatedAt = getPlanCreatedAt();
    
    // Check if the plan is from a past day
    const isPastDay = planCreatedAt.toDateString() !== now.toDateString() && planCreatedAt < now;
    
    let html = '';

    schedule.forEach((item) => {
        const scheduledTime = getScheduledTime(item);

        const isPast = scheduledTime < now;
        const isBeforePlan = scheduledTime < planCreatedAt;
        const isCompleted = item.completed;
        const isMissed = item.missed;

        let status = '';
        let statusClass = '';
        let actual = '-';
        let action = '';

        if (isCompleted) {
            status = 'Completed';
            statusClass = 'bg-green-100 text-green-700';
            actual = formatLitersFromMl(getScheduleAmountMl(item));
            action = `<span class="text-xs text-gray-400">Done</span>`;
        } else if (isMissed) {
            status = 'Missed';
            statusClass = 'bg-red-100 text-red-700';
            actual = '0.00 L';
            action = `<span class="text-xs text-gray-400">Missed</span>`;
        } else if (isPastDay) {
            // For past days, anything not completed is missed/expired
            status = 'Expired';
            statusClass = 'bg-gray-100 text-gray-400';
            actual = '-';
            action = `<span class="text-xs text-gray-400 italic">Day passed</span>`;
        } else if (isPast) {
            if (isBeforePlan) {
                status = 'Not Applicable';
                statusClass = 'bg-gray-50 text-gray-400';
                actual = '-';
                action = `<span class="text-xs text-gray-400 italic">Before plan</span>`;
            } else {
                status = 'Due';
                statusClass = 'bg-gray-100 text-gray-600';
                actual = '-';
                action = `<button onclick="markComplete('${item.id}')" class="px-3 py-1 bg-black text-white text-xs rounded-lg hover:bg-gray-800 transition">Mark Done</button>`;
            }
        } else {
            status = 'Scheduled';
            statusClass = 'bg-blue-100 text-blue-700';
            actual = '-';
            action = `<span class="text-xs text-gray-400">Waiting</span>`;
        }

        html += `
            <tr class="${isCompleted ? 'bg-green-50' : (isMissed ? 'bg-red-50' : (isBeforePlan && isPast ? 'bg-gray-50/50' : 'hover:bg-gray-50'))}">
                <td class="px-4 py-3">
                    <span class="font-semibold ${isBeforePlan && isPast ? 'text-gray-400' : 'text-gray-900'}">${formatTime(item.time)}</span>
                </td>
                <td class="px-4 py-3">
                    <span class="${isBeforePlan && isPast ? 'text-gray-400' : 'text-gray-700'}">${formatLitersFromMl(getScheduleAmountMl(item))}</span>
                </td>
                <td class="px-4 py-3">
                    <span class="${isBeforePlan && isPast ? 'text-gray-400' : 'text-gray-700'}">${actual}</span>
                </td>
                <td class="px-4 py-3">
                    <span class="px-2 py-1 ${statusClass} rounded-full text-xs font-medium">${status}</span>
                </td>
                <td class="px-4 py-3 text-right">
                    ${action}
                </td>
            </tr>
        `;
    });

    tbody.innerHTML = html;

    // Display missed reminders section if any
    const missedReminders = dailyTracking?.missedReminders || [];
    const missedSection = document.getElementById('missed-section');
    const missedList = document.getElementById('missed-list');

    if (missedReminders.length > 0) {
        missedSection.classList.remove('hidden');
        missedList.innerHTML = missedReminders.map(missed => `
            <div class="flex items-center justify-between p-3 bg-white rounded-lg border border-red-200">
                <div class="flex items-center gap-3">
                    <svg class="w-4 h-4 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <span class="text-sm font-medium text-gray-900">${formatTime(missed.time)}</span>
                </div>
                <span class="text-xs text-gray-500">${formatMissedTime(missed.missedAt)}</span>
            </div>
        `).join('');
    } else {
        missedSection.classList.add('hidden');
        missedList.innerHTML = '';
    }

    updateQuickAddState(schedule);
}

function updateQuickAddState(schedule) {
    const buttons = document.querySelectorAll('[data-quick-add]');
    const hint = document.getElementById('quick-add-hint');
    dueQuickAddItemId = null;

    if (!schedule || schedule.length === 0) {
        buttons.forEach(btn => setQuickAddDisabled(btn, true));
        if (hint) {
            hint.textContent = 'Create a schedule to enable quick log.';
        }
        return;
    }

    const planCreatedAt = getPlanCreatedAt();
    const now = new Date();
    const dueItems = schedule.filter(item => {
        if (!item || item.completed || item.missed || !item.time) {
            return false;
        }
        const scheduledTime = getScheduledTime(item);
        if (scheduledTime < planCreatedAt) {
            return false;
        }
        return scheduledTime <= now;
    });
    dueItems.sort((a, b) => getScheduledTime(a) - getScheduledTime(b));
    const dueItem = dueItems[0] || null;
    dueQuickAddItemId = dueItem?.id || null;
    const hasAvailableSlot = !!dueQuickAddItemId;

    buttons.forEach(btn => setQuickAddDisabled(btn, !hasAvailableSlot));
    if (hint) {
        if (hasAvailableSlot) {
            hint.textContent = `1 tap logs ${formatLitersFromMl(getScheduleAmountMl(dueItem))}.`;
        } else {
            const nextPending = schedule
                .filter(item => item && !item.completed && !item.missed && item.time)
                .filter(item => {
                    const scheduledTime = getScheduledTime(item);
                    return scheduledTime >= planCreatedAt && scheduledTime >= now;
                })
                .sort((a, b) => getScheduledTime(a) - getScheduledTime(b))[0];
            hint.textContent = nextPending
                ? `Quick log unlocks at ${formatTime(nextPending.time)}.`
                : 'No pending reminders right now.';
        }
    }
}

function setQuickAddDisabled(button, disabled) {
    if (!button) return;
    button.disabled = disabled;
    button.classList.toggle('opacity-50', disabled);
    button.classList.toggle('cursor-not-allowed', disabled);
    button.classList.toggle('pointer-events-none', disabled);
}

function updateCupAnimation(percent, completedMl = 0, plannedMl = 0) {
    const waterFill = document.getElementById('cup-water-fill');
    const wave1 = document.getElementById('wave1-details');
    const wave2 = document.getElementById('wave2-details');
    const percentText = document.getElementById('cup-percent-text');

    if (waterFill) {
        // Cup height is from y=60 to y=260, so 200px total
        // At 0%: y=260 (water below cup), At 100%: y=60 (water at top)
        const fillHeight = (percent / 100) * 200;
        const fillY = 260 - fillHeight;
        waterFill.setAttribute('y', fillY);
        waterFill.setAttribute('height', fillHeight);
    }

    // Update wave position - transform based on percentage
    const waveOffset = -60 + (percent / 100) * 200;
    if (wave1) {
        wave1.setAttribute('transform', `translate(0, ${waveOffset})`);
    }
    if (wave2) {
        wave2.setAttribute('transform', `translate(0, ${waveOffset})`);
    }

    if (percentText) {
        percentText.textContent = percent + '%';
    }

    const cupsCompleted = document.getElementById('cups-completed');
    const cupsPlanned = document.getElementById('cups-planned');
    if (cupsCompleted) cupsCompleted.textContent = (completedMl / 1000).toFixed(2);
    if (cupsPlanned) cupsPlanned.textContent = (plannedMl / 1000).toFixed(2);
}

async function markComplete(scheduleItemId) {
    if (!currentPlan || !currentPlan.id) {
        showToast('No active plan', 'warning');
        return;
    }

    try {
        const response = await api.post('api/habits.php?action=complete_reminder', {
            planId: currentPlan.id,
            scheduleItemId: scheduleItemId,
            csrf_token: CSRF_TOKEN
        });

        if (response.success) {
            // Play sound
            if (App.notifications && App.notifications.playSound) {
                App.notifications.playSound();
            }

            showToast('Water logged!', 'success');
            // Reload data
            await loadPlanAndTracking();
        } else {
            showToast(response.error || 'Failed to mark complete', 'error');
        }
    } catch (error) {
        if (error.status === 401 || error.message?.includes('401')) {
            window.location.href = '?page=login&reason=session_expired';
            return;
        }
        showToast('Failed to mark complete', 'error');
    }
}

async function quickAddWater() {
    if (!dueQuickAddItemId) {
        showToast('Not due yet. Quick log unlocks at the reminder time.', 'warning');
        return;
    }
    await markComplete(dueQuickAddItemId);
}

function showError() {
    document.getElementById('details-loading').classList.add('hidden');
    document.getElementById('details-error').classList.remove('hidden');
}

// Load water quote on page load
async function getWaterQuoteDetails() {
    const quoteEl = document.getElementById('water-quote-details');
    const tipEl = document.getElementById('water-tip-details');
    const factEl = document.getElementById('water-fact-details');

    // Show loading state
    if (quoteEl) quoteEl.textContent = 'Loading...';

    try {
        const response = await api.get('api/habits.php?action=get_water_quote');

        // Check if response is successful and has data
        if (response && response.success && response.data && response.data.quote) {
            if (quoteEl) quoteEl.textContent = `"${response.data.quote}"`;
            if (tipEl) tipEl.textContent = response.data.tip || 'Keep a water bottle at your desk';
            if (factEl) factEl.textContent = response.data.fact || 'Your body is 60% water';
        } else {
            // Response didn't have expected data, use fallback
            setFallbackQuote();
        }
    } catch (error) {
        console.error('Failed to load quote:', error);
        setFallbackQuote();
    }

    function setFallbackQuote() {
        const fallbacks = [
            ['"Water is the driving force of all nature." - Leonardo da Vinci', 'Keep a water bottle at your desk', 'Your body is 60% water'],
            ['"Thousands have lived without love, not one without water." - W. H. Auden', 'Drink a glass of water before each meal', 'Your brain is 75% water'],
            ['"Pure water is the world\'s first and foremost medicine." - Slovakian proverb', 'Set reminders on your phone', 'Blood is 90% water']
        ];
        const random = fallbacks[Math.floor(Math.random() * fallbacks.length)];

        if (quoteEl) quoteEl.textContent = random[0];
        if (tipEl) tipEl.textContent = random[1];
        if (factEl) factEl.textContent = random[2];
    }
}

// Load quote on page load
document.addEventListener('DOMContentLoaded', async () => {
    await loadPlanAndTracking();
    await getWaterQuoteDetails(); // Load initial quote

    if (autoMissIntervalId) {
        clearInterval(autoMissIntervalId);
    }
    autoMissIntervalId = setInterval(async () => {
        const didMark = await autoMarkMissedReminders();
        if (didMark) {
            await refreshTracking();
            displayPlanDetails();
        }
    }, 60000);
});

window.addEventListener('beforeunload', () => {
    stopAggressiveReminderLoop();
    if (autoMissIntervalId) {
        clearInterval(autoMissIntervalId);
        autoMissIntervalId = null;
    }
});

function formatTime(time) {
    if (!time) return '--:--';
    const [hours, minutes] = time.split(':');
    const h = parseInt(hours);
    const ampm = h >= 12 ? 'PM' : 'AM';
    const hour12 = h % 12 || 12;
    return hour12 + ':' + minutes + ' ' + ampm;
}

function formatMissedTime(missedAt) {
    if (!missedAt) return '';
    const date = new Date(missedAt);
    const now = new Date();
    const diff = Math.floor((now - date) / 1000 / 60); // minutes

    if (diff < 60) return diff + ' min ago';
    if (diff < 1440) return Math.floor(diff / 60) + ' hours ago';
    return Math.floor(diff / 1440) + ' days ago';
}
</script>
