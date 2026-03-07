<?php
require_once __DIR__ . '/../config/init.php';
require_once __DIR__ . '/../includes/auth.php';

Auth::check();

$db = new Database(getMasterPassword());
$waterTracker = $db->load('water_tracker');

$today = date('Y-m-d');
$todayEntry = null;
foreach ($waterTracker as $entry) {
    if ($entry['date'] === $today) {
        $todayEntry = $entry;
        break;
    }
}

if (!$todayEntry) {
    $todayEntry = [
        'glasses' => 0,
        'goal' => 8,
        'reminderInterval' => 60,
        'lastReminder' => null
    ];
}

$entries = array_filter($waterTracker, fn($e) => $e['date'] === $today);

$last7Days = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $entry = array_filter($waterTracker, fn($e) => $e['date'] === $date);
    $last7Days[] = [
        'date' => $date,
        'glasses' => !empty($entry) ? array_values($entry)[0]['glasses'] : 0,
        'goal' => !empty($entry) ? array_values($entry)[0]['goal'] : 8
    ];
}

$totalGlasses = array_sum(array_column($last7Days, 'glasses'));
$averageGlasses = round($totalGlasses / 7, 1);
$goalMetDays = count(array_filter($last7Days, fn($d) => $d['glasses'] >= $d['goal']));

$pageTitle = 'Water Tracker';
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="flex items-center justify-between mb-8">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Water Tracker</h1>
            <p class="text-gray-600 mt-1">Track your daily water intake</p>
        </div>
        <a href="?page=dashboard" class="text-gray-600 hover:text-black">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
            </svg>
        </a>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
        <div class="lg:col-span-2 bg-gradient-to-br from-blue-50 to-cyan-50 rounded-xl p-6 border border-blue-200">
            <h2 class="text-xl font-semibold text-gray-900 mb-6">Today's Intake</h2>

            <div class="flex items-center justify-between mb-6">
                <div>
                    <p class="text-sm text-blue-600 font-medium mb-1">Glasses Consumed</p>
                    <p class="text-5xl font-bold text-blue-900" id="water-counter">
                        <?php echo $todayEntry['glasses']; ?> / <?php echo $todayEntry['goal']; ?>
                    </p>
                </div>
                <div class="w-16 h-16 bg-blue-200 rounded-2xl flex items-center justify-center">
                    <svg class="w-8 h-8 text-blue-600" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M12 2c-5.33 4.55-8 8.48-8 11.8 0 4.98 3.8 9.2 8 9.2s8-4.22 8-9.2c0-3.32-2.67-7.25-8-11.8zm0 18c-3.35 0-6-2.57-6-6.2 0-2.62 1.8-5.2 4.8-7.2 1.5 1.3 3.3 2.4 5.2 3.1V20z"/>
                    </svg>
                </div>
            </div>

            <div class="w-full bg-blue-200 rounded-full h-3 mb-6">
                <div class="bg-blue-600 h-3 rounded-full transition-all" style="width: <?php echo min(($todayEntry['glasses'] / $todayEntry['goal']) * 100, 100); ?>%"></div>
            </div>

            <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                <button onclick="addWaterGlass(1)" class="py-3 px-4 bg-blue-600 text-white rounded-xl font-semibold hover:bg-blue-700 transition">
                    +1 Glass
                </button>
                <button onclick="addWaterGlass(2)" class="py-3 px-4 bg-blue-500 text-white rounded-xl font-semibold hover:bg-blue-600 transition">
                    +2 Glasses
                </button>
                <button onclick="addWaterGlass(3)" class="py-3 px-4 bg-blue-400 text-white rounded-xl font-semibold hover:bg-blue-500 transition">
                    +3 Glasses
                </button>
                <button onclick="showWaterSettings()" class="py-3 px-4 bg-white border-2 border-gray-300 text-gray-700 rounded-xl font-semibold hover:bg-gray-50 transition">
                    Settings
                </button>
            </div>
        </div>

        <div class="bg-white rounded-xl p-6 border border-gray-200">
            <h3 class="font-semibold text-gray-900 mb-4">7-Day Statistics</h3>
            <div class="space-y-4">
                <div class="flex items-center justify-between">
                    <span class="text-sm text-gray-600">Total Glasses</span>
                    <span class="text-2xl font-bold text-gray-900"><?php echo $totalGlasses; ?></span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-sm text-gray-600">Daily Average</span>
                    <span class="text-2xl font-bold text-gray-900"><?php echo $averageGlasses; ?></span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-sm text-gray-600">Goals Met</span>
                    <span class="text-2xl font-bold text-green-600"><?php echo $goalMetDays; ?>/7</span>
                </div>
                <div class="pt-4 border-t border-gray-200">
                    <div class="flex items-center gap-2 text-sm text-gray-600">
                        <svg class="w-4 h-4 text-orange-500" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M17.657 18.657A8 8 0 016.343 7.343S7 9 9 10c0-2 .5-5 2.986-7C14 5 16.09 5.777 17.656 7.343A7.975 7.975 0 0120 13a7.975 7.975 0 01-2.343 5.657z"></path>
                        </svg>
                        <span>Current Streak: <strong class="text-gray-900">0 days</strong></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl border border-gray-200 p-6 mb-8">
        <h3 class="font-semibold text-gray-900 mb-4">Last 7 Days</h3>
        <div class="grid grid-cols-7 gap-3">
            <?php foreach ($last7Days as $day): ?>
                <div class="bg-gray-50 rounded-lg p-4 text-center">
                    <p class="text-xs text-gray-500 mb-2">
                        <?php echo date('D', strtotime($day['date'])); ?>
                        <br>
                        <?php echo date('M j', strtotime($day['date'])); ?>
                    </p>
                    <p class="text-2xl font-bold text-blue-600 mb-1">
                        <?php echo $day['glasses']; ?>
                    </p>
                    <p class="text-xs text-gray-500">
                        Goal: <?php echo $day['goal']; ?>
                    </p>
                    <div class="w-full bg-gray-200 rounded-full h-1.5 mt-2">
                        <div class="h-1.5 rounded-full <?php echo $day['glasses'] >= $day['goal'] ? 'bg-green-500' : 'bg-blue-500'; ?>" style="width: <?php echo min(($day['glasses'] / $day['goal']) * 100, 100); ?>%"></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="bg-white rounded-xl border border-gray-200 p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="font-semibold text-gray-900">Water History</h3>
            <a href="api/export.php?action=export_habits&format=csv" class="text-sm text-gray-600 hover:text-black">
                Export Data →
            </a>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="border-b border-gray-200">
                        <th class="text-left py-3 px-4 text-sm font-medium text-gray-600">Date</th>
                        <th class="text-left py-3 px-4 text-sm font-medium text-gray-600">Glasses</th>
                        <th class="text-left py-3 px-4 text-sm font-medium text-gray-600">Goal</th>
                        <th class="text-left py-3 px-4 text-sm font-medium text-gray-600">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $sortedEntries = array_slice(array_reverse(array_values($waterTracker)), 0, 30);
                    if (empty($sortedEntries)):
                    ?>
                        <tr>
                            <td colspan="4" class="py-8 text-center text-gray-500">No water intake recorded yet</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($sortedEntries as $entry): ?>
                            <tr class="border-b border-gray-100">
                                <td class="py-3 px-4 text-sm">
                                    <?php echo date('M j, Y', strtotime($entry['date'])); ?>
                                </td>
                                <td class="py-3 px-4 text-sm font-medium text-gray-900">
                                    <?php echo $entry['glasses']; ?>
                                </td>
                                <td class="py-3 px-4 text-sm text-gray-600">
                                    <?php echo $entry['goal']; ?>
                                </td>
                                <td class="py-3 px-4">
                                    <?php if ($entry['glasses'] >= $entry['goal']): ?>
                                        <span class="px-2 py-1 text-xs font-medium bg-green-100 text-green-700 rounded">Goal Met</span>
                                    <?php else: ?>
                                        <span class="px-2 py-1 text-xs font-medium bg-yellow-100 text-yellow-700 rounded">Below Goal</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div id="water-settings-modal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-xl p-6 w-full max-w-md mx-4">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">Water Tracker Settings</h3>
        <div class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Daily Goal (glasses)</label>
                <input type="number" id="water-goal-input" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" min="1" value="<?php echo $todayEntry['goal']; ?>">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Reminder Interval (minutes)</label>
                <select id="water-reminder-input" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    <option value="30" <?php echo $todayEntry['reminderInterval'] == 30 ? 'selected' : ''; ?>>30 minutes</option>
                    <option value="60" <?php echo $todayEntry['reminderInterval'] == 60 ? 'selected' : ''; ?>>1 hour</option>
                    <option value="120" <?php echo $todayEntry['reminderInterval'] == 120 ? 'selected' : ''; ?>>2 hours</option>
                    <option value="180" <?php echo $todayEntry['reminderInterval'] == 180 ? 'selected' : ''; ?>>3 hours</option>
                </select>
            </div>
            <div class="flex items-center gap-2">
                <input type="checkbox" id="water-notifications-enabled" class="w-4 h-4 rounded border-gray-300" checked>
                <label for="water-notifications-enabled" class="text-sm text-gray-700">Enable desktop notifications</label>
            </div>
        </div>
        <div class="flex gap-3 justify-end mt-6">
            <button onclick="closeWaterSettings()" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition">Cancel</button>
            <button onclick="saveWaterSettings()" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">Save Settings</button>
        </div>
    </div>
</div>

<script>
async function addWaterGlass(count) {
    try {
        const response = await api.post('api/habits.php?action=add_water_glass', {
            count: count,
            csrf_token: CSRF_TOKEN
        });

        if (response.success) {
            showToast(`Added ${count} glass${count > 1 ? 'es' : ''}!`, 'success');
            updateWaterDisplay(response.data.glasses, response.data.goal);
        }
    } catch (error) {
        console.error('Failed to add water glass:', error);
        showToast('Failed to update water intake', 'error');
    }
}

function updateWaterDisplay(glasses, goal) {
    const counterEl = document.getElementById('water-counter');
    if (counterEl) {
        counterEl.textContent = `${glasses} / ${goal}`;
    }

    const progressBar = document.querySelector('.from-blue-50 .bg-blue-600');
    if (progressBar) {
        const percentage = Math.min((glasses / goal) * 100, 100);
        progressBar.style.width = `${percentage}%`;
    }
}

function showWaterSettings() {
    document.getElementById('water-settings-modal').classList.remove('hidden');
    document.getElementById('water-settings-modal').classList.add('flex');
}

function closeWaterSettings() {
    document.getElementById('water-settings-modal').classList.add('hidden');
    document.getElementById('water-settings-modal').classList.remove('flex');
}

async function saveWaterSettings() {
    const goal = parseInt(document.getElementById('water-goal-input').value);
    const reminderInterval = parseInt(document.getElementById('water-reminder-input').value);
    const notificationsEnabled = document.getElementById('water-notifications-enabled').checked;

    try {
        const response = await api.post('api/habits.php?action=set_water_goal', {
            goal: goal,
            reminderInterval: reminderInterval,
            csrf_token: CSRF_TOKEN
        });

        if (response.success) {
            showToast('Water settings saved!', 'success');
            closeWaterSettings();

            localStorage.setItem('waterTracker', JSON.stringify({
                notificationsEnabled,
                reminderInterval,
                goal
            }));

            if (notificationsEnabled && 'Notification' in window && Notification.permission === 'default') {
                await Notification.requestPermission();
            }

            location.reload();
        }
    } catch (error) {
        console.error('Failed to save water settings:', error);
        showToast('Failed to save settings', 'error');
    }
}

document.addEventListener('DOMContentLoaded', () => {
    const saved = localStorage.getItem('waterTracker');
    if (saved) {
        const data = JSON.parse(saved);
        if (data.notificationsEnabled && 'Notification' in window) {
            Notification.requestPermission();
        }
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
