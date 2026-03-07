<?php
// Settings View
$db = new Database(getMasterPassword(), Auth::userId());
$config = $db->load('config');
$user = Auth::user();
$authService = new Auth($db);
$sessionTimeoutPreference = Auth::normalizeSessionTimeoutPreference($_SESSION['session_timeout_preference'] ?? null);

$success = '';
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['section'])) {
    $section = $_POST['section'];

    // Validate CSRF
    if (!Auth::validateCsrf($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request';
    } else {
        switch ($section) {
            case 'site':
                $config['siteName'] = trim($_POST['siteName'] ?? '');
                $db->save('config', $config);
                $success = 'Site settings saved';
                break;
            case 'business':
                $config['businessName'] = trim($_POST['businessName'] ?? '');
                $config['businessEmail'] = trim($_POST['businessEmail'] ?? '');
                $config['businessPhone'] = trim($_POST['businessPhone'] ?? '');
                $config['businessAddress'] = trim($_POST['businessAddress'] ?? '');
                $config['currency'] = $_POST['currency'] ?? 'USD';
                $config['taxRate'] = floatval($_POST['taxRate'] ?? 0);
                $db->save('config', $config);
                $success = 'Business settings saved';
                break;

            case 'api':
                $config['groqApiKey'] = trim($_POST['groqApiKey'] ?? '');
                $config['openrouterApiKey'] = trim($_POST['openrouterApiKey'] ?? '');
                $db->save('config', $config);
                $success = 'API keys saved';
                break;

            case 'water':
                $config['waterGoal'] = (int)($_POST['waterGoal'] ?? 8);
                $config['waterReminderInterval'] = (int)($_POST['waterReminderInterval'] ?? 60);
                $config['waterNotificationsEnabled'] = isset($_POST['waterNotificationsEnabled']);
                $db->save('config', $config);
                $success = 'Water settings saved';
                break;

            case 'notifications':
                $config['notificationSoundEnabled'] = isset($_POST['notificationSoundEnabled']);
                $config['notificationSound'] = $_POST['notificationSound'] ?? 'default';
                $config['notificationVolume'] = (int)($_POST['notificationVolume'] ?? 70);
                $config['overdueAlertEnabled'] = isset($_POST['overdueAlertEnabled']);
                $config['dueSoonAlertEnabled'] = isset($_POST['dueSoonAlertEnabled']);
                $config['notStartedAlertEnabled'] = isset($_POST['notStartedAlertEnabled']);
                $db->save('config', $config);
                $success = 'Notification settings saved';
                break;

            case 'session':
                if (!$user || empty($user['id'])) {
                    $error = 'Session expired. Please log in again.';
                    break;
                }

                $selectedPreference = Auth::normalizeSessionTimeoutPreference($_POST['sessionTimeoutPreference'] ?? null);
                if ($authService->updateSessionTimeoutPreference($user['id'], $selectedPreference)) {
                    $sessionTimeoutPreference = $selectedPreference;
                    $success = 'Session timeout settings saved';
                } else {
                    $error = 'Failed to save session timeout settings';
                }
                break;
        }
    }
}
?>

<div class="space-y-6">
    <?php if ($success): ?>
        <div class="bg-green-50 text-green-700 p-4 rounded-lg"><?php echo e($success); ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="bg-red-50 text-red-700 p-4 rounded-lg"><?php echo e($error); ?></div>
    <?php endif; ?>

    <!-- Site Settings -->
    <div class="bg-white rounded-xl border border-gray-200">
        <div class="p-5 border-b border-gray-200">
            <h3 class="font-semibold text-gray-900">Site Settings</h3>
            <p class="text-sm text-gray-500">Customize the workspace name shown after you sign in. Public auth pages use the environment fallback brand.</p>
        </div>
        <form method="POST" class="p-5 space-y-4">
            <input type="hidden" name="section" value="site">
            <input type="hidden" name="csrf_token" value="<?php echo Auth::csrfToken(); ?>">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Workspace Name</label>
                <input type="text" name="siteName" value="<?php echo e($config['siteName'] ?? ''); ?>"
                       placeholder="<?php echo e(APP_NAME); ?>"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-black focus:border-transparent outline-none">
                <p class="text-xs text-gray-500 mt-1">Leave blank to use the public fallback brand for this account.</p>
            </div>
            <button type="submit" class="px-4 py-2 bg-black text-white rounded-lg font-medium hover:bg-gray-800 transition">
                Save Site Settings
            </button>
        </form>
    </div>

    <!-- Business Settings -->
    <div class="bg-white rounded-xl border border-gray-200">
        <div class="p-5 border-b border-gray-200">
            <h3 class="font-semibold text-gray-900">Business Information</h3>
            <p class="text-sm text-gray-500">Used on invoices and client communications</p>
        </div>
        <form method="POST" class="p-5 space-y-4">
            <input type="hidden" name="section" value="business">
            <input type="hidden" name="csrf_token" value="<?php echo Auth::csrfToken(); ?>">
            
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Business Name</label>
                    <input type="text" name="businessName" value="<?php echo e($config['businessName'] ?? ''); ?>"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-black focus:border-transparent outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                    <input type="email" name="businessEmail" value="<?php echo e($config['businessEmail'] ?? ''); ?>"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-black focus:border-transparent outline-none">
                </div>
            </div>
            
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Phone</label>
                    <input type="text" name="businessPhone" value="<?php echo e($config['businessPhone'] ?? ''); ?>"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-black focus:border-transparent outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Currency</label>
                    <select name="currency" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                        <option value="USD" <?php echo ($config['currency'] ?? '') === 'USD' ? 'selected' : ''; ?>>USD ($)</option>
                        <option value="EUR" <?php echo ($config['currency'] ?? '') === 'EUR' ? 'selected' : ''; ?>>EUR (€)</option>
                        <option value="GBP" <?php echo ($config['currency'] ?? '') === 'GBP' ? 'selected' : ''; ?>>GBP (£)</option>
                        <option value="ZAR" <?php echo ($config['currency'] ?? '') === 'ZAR' ? 'selected' : ''; ?>>ZAR (R)</option>
                    </select>
                </div>
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Address</label>
                <textarea name="businessAddress" rows="2" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-black focus:border-transparent outline-none"><?php echo e($config['businessAddress'] ?? ''); ?></textarea>
            </div>
            
            <div class="w-32">
                <label class="block text-sm font-medium text-gray-700 mb-1">Tax Rate (%)</label>
                <input type="number" name="taxRate" value="<?php echo e($config['taxRate'] ?? 0); ?>" step="0.01" min="0" max="100"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-black focus:border-transparent outline-none">
            </div>
            
            <button type="submit" class="px-4 py-2 bg-black text-white rounded-lg font-medium hover:bg-gray-800 transition">
                Save Business Settings
            </button>
        </form>
    </div>

    <!-- Favicon Settings -->
    <div class="bg-white rounded-xl border border-gray-200">
        <div class="p-5 border-b border-gray-200">
            <h3 class="font-semibold text-gray-900">Favicon</h3>
            <p class="text-sm text-gray-500">Upload a custom favicon for browser tabs and bookmarks</p>
        </div>
        <div class="p-5 space-y-4">
            <!-- Current Favicon Preview -->
            <div class="flex items-center gap-4">
                <div class="w-16 h-16 bg-black rounded-lg flex items-center justify-center">
                    <img id="favicon-preview" src="assets/favicons/favicon-32x32.png" alt="Current favicon" class="w-10 h-10">
                </div>
                <div>
                    <p class="font-medium text-gray-900">Current Favicon</p>
                    <p class="text-sm text-gray-500">Black background with "LM" monogram</p>
                </div>
            </div>

            <!-- Upload Form -->
            <form id="favicon-upload-form" enctype="multipart/form-data" class="space-y-4">
                <input type="hidden" name="csrf_token" value="<?php echo Auth::csrfToken(); ?>">

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Upload Favicon Image</label>
                    <input type="file" name="favicon" id="favicon-input" accept="image/png,image/jpeg,image/svg+xml"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-black focus:border-transparent outline-none">
                    <p class="text-xs text-gray-500 mt-1">Recommended: 512x512 PNG or SVG. Will be auto-resized.</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Theme Color</label>
                    <div class="flex items-center gap-2">
                        <input type="color" name="theme_color" id="theme-color-picker" value="#000000"
                               class="w-10 h-10 rounded border border-gray-300 cursor-pointer">
                        <input type="text" name="theme_color_hex" id="theme-color-hex" value="#000000"
                               class="w-24 px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-black focus:border-transparent outline-none font-mono text-sm">
                    </div>
                    <p class="text-xs text-gray-500 mt-1">Browser tab color (affects mobile Chrome, desktop Chrome, Edge)</p>
                </div>

                <div id="favicon-message" class="text-sm hidden"></div>

                <div class="flex items-center gap-3">
                    <button type="submit" class="px-4 py-2 bg-black text-white rounded-lg font-medium hover:bg-gray-800 transition">
                        Upload Favicon
                    </button>
                    <button type="button" onclick="resetFavicon()" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg font-medium hover:bg-gray-50 transition">
                        Reset to Default
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- API Keys -->
    <div class="bg-white rounded-xl border border-gray-200">
        <div class="p-5 border-b border-gray-200">
            <h3 class="font-semibold text-gray-900">AI API Keys</h3>
            <p class="text-sm text-gray-500">Required for AI features (task generation, PRD creation)</p>
        </div>
        <form method="POST" class="p-5 space-y-4">
            <input type="hidden" name="section" value="api">
            <input type="hidden" name="csrf_token" value="<?php echo Auth::csrfToken(); ?>">
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Groq API Key
                    <a href="https://console.groq.com/keys" target="_blank" class="text-blue-600 font-normal ml-2">Get key →</a>
                </label>
                <input type="password" name="groqApiKey" value="<?php echo e($config['groqApiKey'] ?? ''); ?>"
                       placeholder="gsk_..."
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-black focus:border-transparent outline-none font-mono text-sm">
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    OpenRouter API Key
                    <a href="https://openrouter.ai/keys" target="_blank" class="text-blue-600 font-normal ml-2">Get key →</a>
                </label>
                <input type="password" name="openrouterApiKey" value="<?php echo e($config['openrouterApiKey'] ?? ''); ?>"
                       placeholder="sk-or-..."
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-black focus:border-transparent outline-none font-mono text-sm">
            </div>
            
            <button type="submit" class="px-4 py-2 bg-black text-white rounded-lg font-medium hover:bg-gray-800 transition">
                Save API Keys
            </button>
            <a href="?page=model-settings" class="inline-block ml-4 text-sm text-blue-600 hover:underline">
                Configure Specific Models →
            </a>
        </form>
    </div>

    <!-- Water Tracker Settings -->
    <div class="bg-white rounded-xl border border-gray-200">
        <div class="p-5 border-b border-gray-200">
            <h3 class="font-semibold text-gray-900">Water Tracker Settings</h3>
            <p class="text-sm text-gray-500">Configure your daily hydration reminders</p>
        </div>
        <form method="POST" class="p-5 space-y-4">
            <input type="hidden" name="section" value="water">
            <input type="hidden" name="csrf_token" value="<?php echo Auth::csrfToken(); ?>">

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Default Daily Goal (glasses)</label>
                <input type="number" name="waterGoal" value="<?php echo e($config['waterGoal'] ?? 8); ?>" min="1" max="20"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-black focus:border-transparent outline-none">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Default Reminder Interval</label>
                <select name="waterReminderInterval" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                    <option value="30" <?php echo ($config['waterReminderInterval'] ?? 60) == 30 ? 'selected' : ''; ?>>30 minutes</option>
                    <option value="60" <?php echo ($config['waterReminderInterval'] ?? 60) == 60 ? 'selected' : ''; ?>>1 hour</option>
                    <option value="120" <?php echo ($config['waterReminderInterval'] ?? 60) == 120 ? 'selected' : ''; ?>>2 hours</option>
                    <option value="180" <?php echo ($config['waterReminderInterval'] ?? 60) == 180 ? 'selected' : ''; ?>>3 hours</option>
                </select>
            </div>

            <div class="flex items-center gap-2">
                <input type="checkbox" name="waterNotificationsEnabled" id="water-notifications-enabled" class="w-4 h-4 rounded border-gray-300" <?php echo ($config['waterNotificationsEnabled'] ?? true) ? 'checked' : ''; ?>>
                <label for="water-notifications-enabled" class="text-sm text-gray-700">Enable desktop notifications by default</label>
            </div>

            <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg font-medium hover:bg-blue-700 transition">
                Save Water Settings
            </button>
        </form>
    </div>

    <!-- Notification Sound Settings -->
    <div class="bg-white rounded-xl border border-gray-200">
        <div class="p-5 border-b border-gray-200">
            <h3 class="font-semibold text-gray-900">Task Notification Sounds</h3>
            <p class="text-sm text-gray-500">Configure alert sounds for task deadlines and reminders</p>
        </div>
        <form method="POST" class="p-5 space-y-6">
            <input type="hidden" name="section" value="notifications">
            <input type="hidden" name="csrf_token" value="<?php echo Auth::csrfToken(); ?>">

            <!-- Sound Enable Toggle -->
            <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                <div>
                    <label for="notification-sound-enabled" class="text-sm font-medium text-gray-700">Enable Notification Sounds</label>
                    <p class="text-xs text-gray-500 mt-1">Play sound alerts for task notifications</p>
                </div>
                <input type="checkbox" name="notificationSoundEnabled" id="notification-sound-enabled" class="w-5 h-5 rounded border-gray-300 cursor-pointer" <?php echo ($config['notificationSoundEnabled'] ?? true) ? 'checked' : ''; ?>>
            </div>

            <!-- Sound Selection -->
            <div id="sound-settings" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Notification Sound</label>
                    <select name="notificationSound" id="notification-sound-select" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-black focus:border-transparent outline-none">
                        <option value="default" <?php echo ($config['notificationSound'] ?? 'default') === 'default' ? 'selected' : ''; ?>>Default (Bell)</option>
                        <option value="chime" <?php echo ($config['notificationSound'] ?? 'default') === 'chime' ? 'selected' : ''; ?>>Chime</option>
                        <option value="alert" <?php echo ($config['notificationSound'] ?? 'default') === 'alert' ? 'selected' : ''; ?>>Alert</option>
                        <option value="ping" <?php echo ($config['notificationSound'] ?? 'default') === 'ping' ? 'selected' : ''; ?>>Ping</option>
                        <option value="silent" <?php echo ($config['notificationSound'] ?? 'default') === 'silent' ? 'selected' : ''; ?>>Silent</option>
                    </select>
                    <button type="button" onclick="previewSound()" class="mt-2 px-3 py-1.5 bg-gray-200 text-gray-700 text-sm rounded-lg hover:bg-gray-300 transition">
                        🔊 Preview Sound
                    </button>
                </div>

                <!-- Volume Control -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Volume Level</label>
                    <div class="flex items-center gap-4">
                        <input type="range" name="notificationVolume" id="notification-volume" min="0" max="100" value="<?php echo $config['notificationVolume'] ?? 70; ?>" class="flex-1 h-2 bg-gray-200 rounded-lg appearance-none cursor-pointer" oninput="updateVolumeDisplay(this.value)">
                        <span id="volume-display" class="text-sm font-medium text-gray-700 w-12 text-right"><?php echo $config['notificationVolume'] ?? 70; ?>%</span>
                    </div>
                </div>

                <!-- Alert Type Toggles -->
                <div class="border-t border-gray-200 pt-4">
                    <p class="text-sm font-medium text-gray-700 mb-3">Alert Types</p>
                    <div class="space-y-3">
                        <div class="flex items-center gap-3 p-3 bg-gray-50 rounded-lg">
                            <input type="checkbox" name="overdueAlertEnabled" id="overdue-alert" class="w-4 h-4 rounded border-gray-300 cursor-pointer" <?php echo ($config['overdueAlertEnabled'] ?? true) ? 'checked' : ''; ?>>
                            <div class="flex-1">
                                <label for="overdue-alert" class="text-sm font-medium text-gray-700 cursor-pointer">Overdue Task Alert</label>
                                <p class="text-xs text-gray-500">Alert when a task is overdue</p>
                            </div>
                        </div>

                        <div class="flex items-center gap-3 p-3 bg-gray-50 rounded-lg">
                            <input type="checkbox" name="dueSoonAlertEnabled" id="due-soon-alert" class="w-4 h-4 rounded border-gray-300 cursor-pointer" <?php echo ($config['dueSoonAlertEnabled'] ?? true) ? 'checked' : ''; ?>>
                            <div class="flex-1">
                                <label for="due-soon-alert" class="text-sm font-medium text-gray-700 cursor-pointer">Due Soon Alert</label>
                                <p class="text-xs text-gray-500">Alert when a task is due within 15 minutes</p>
                            </div>
                        </div>

                        <div class="flex items-center gap-3 p-3 bg-gray-50 rounded-lg">
                            <input type="checkbox" name="notStartedAlertEnabled" id="not-started-alert" class="w-4 h-4 rounded border-gray-300 cursor-pointer" <?php echo ($config['notStartedAlertEnabled'] ?? true) ? 'checked' : ''; ?>>
                            <div class="flex-1">
                                <label for="not-started-alert" class="text-sm font-medium text-gray-700 cursor-pointer">Not Started Alert</label>
                                <p class="text-xs text-gray-500">Alert when a task hasn't started but is due soon</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <button type="submit" class="px-4 py-2 bg-purple-600 text-white rounded-lg font-medium hover:bg-purple-700 transition">
                Save Notification Settings
            </button>
        </form>
    </div>

    <!-- Session Timeout -->
    <div class="bg-white rounded-xl border border-gray-200">
        <div class="p-5 border-b border-gray-200">
            <h3 class="font-semibold text-gray-900">Session Timeout</h3>
            <p class="text-sm text-gray-500">Controls automatic sign-out behavior while you are inactive</p>
        </div>
        <form method="POST" class="p-5 space-y-4">
            <input type="hidden" name="section" value="session">
            <input type="hidden" name="csrf_token" value="<?php echo Auth::csrfToken(); ?>">

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Timeout Preference</label>
                <select name="sessionTimeoutPreference" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-black focus:border-transparent outline-none">
                    <option value="20m" <?php echo $sessionTimeoutPreference === '20m' ? 'selected' : ''; ?>>20 minutes</option>
                    <option value="1h" <?php echo $sessionTimeoutPreference === '1h' ? 'selected' : ''; ?>>1 hour</option>
                    <option value="indefinite" <?php echo $sessionTimeoutPreference === 'indefinite' ? 'selected' : ''; ?>>Indefinite (no auto-logout)</option>
                </select>
            </div>

            <p class="text-xs text-gray-500">
                Timed options are inactivity-based. Indefinite keeps you signed in across browser restarts until you sign out manually.
            </p>

            <button type="submit" class="px-4 py-2 bg-black text-white rounded-lg font-medium hover:bg-gray-800 transition">
                Save Session Timeout
            </button>
        </form>
    </div>

    <!-- Backup Management -->
    <div class="bg-white rounded-xl border border-gray-200">
        <div class="p-5 border-b border-gray-200 flex items-center justify-between">
            <div>
                <h3 class="font-semibold text-gray-900">Backup Management</h3>
                <p class="text-sm text-gray-500">Create, restore, and manage backups for this signed-in workspace</p>
            </div>
            <div class="flex items-center gap-2">
                <span class="px-2 py-1 bg-green-100 text-green-700 text-xs font-medium rounded">AES-256 Encrypted</span>
            </div>
        </div>

        <div class="p-5 space-y-4">
            <!-- Backup Stats -->
            <div id="backup-stats" class="grid grid-cols-3 gap-4">
                <div class="bg-gray-50 rounded-lg p-4">
                    <p class="text-sm text-gray-500">Total Backups</p>
                    <p id="backup-count" class="text-2xl font-bold text-gray-900">-</p>
                </div>
                <div class="bg-gray-50 rounded-lg p-4">
                    <p class="text-sm text-gray-500">Total Size</p>
                    <p id="backup-size" class="text-2xl font-bold text-gray-900">-</p>
                </div>
                <div class="bg-gray-50 rounded-lg p-4">
                    <p class="text-sm text-gray-500">Latest Backup</p>
                    <p id="backup-latest" class="text-sm font-medium text-gray-900">-</p>
                </div>
            </div>

            <!-- Create Backup -->
            <div class="flex items-center gap-3">
                <button onclick="createBackup()" id="create-backup-btn" class="px-4 py-2 bg-black text-white rounded-lg font-medium hover:bg-gray-800 transition flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                    Create Backup Now
                </button>
                <button onclick="cleanupBackups()" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg font-medium hover:bg-gray-50 transition">
                    Clean Old Backups
                </button>
            </div>

            <!-- Backup List -->
            <div class="border border-gray-200 rounded-lg overflow-hidden">
                <div class="bg-gray-50 px-4 py-3 border-b border-gray-200">
                    <h4 class="font-medium text-gray-900">Available Backups</h4>
                </div>
                <div class="max-h-64 overflow-y-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50 sticky top-0">
                            <tr class="text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                <th class="px-4 py-2">Filename</th>
                                <th class="px-4 py-2">Size</th>
                                <th class="px-4 py-2">Created</th>
                                <th class="px-4 py-2 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="backup-list" class="divide-y divide-gray-100">
                            <tr>
                                <td colspan="4" class="px-4 py-8 text-center text-gray-500">
                                    <div class="spinner mx-auto mb-2"></div>
                                    Loading backups...
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                <h4 class="font-medium text-blue-900 mb-2">Multi-User Backup Scope</h4>
                <p class="text-sm text-blue-700">These backups are tied to the currently signed-in workspace. Import and restore actions only affect this account's encrypted data.</p>
            </div>
        </div>
    </div>

    <!-- Change Password -->
    <div class="bg-white rounded-xl border border-gray-200">
        <div class="p-5 border-b border-gray-200">
            <h3 class="font-semibold text-gray-900">Change Password</h3>
            <p class="text-sm text-gray-500">Update your account password</p>
        </div>
        <form id="change-password-form" class="p-5 space-y-4">
            <input type="hidden" name="csrf_token" value="<?php echo Auth::csrfToken(); ?>">

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Current Password</label>
                <input type="password" id="current-password" required
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-black focus:border-transparent outline-none">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">New Password</label>
                <input type="password" id="new-password" required minlength="8"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-black focus:border-transparent outline-none">
                <p class="text-xs text-gray-500 mt-1">Minimum 8 characters</p>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Confirm New Password</label>
                <input type="password" id="confirm-new-password" required minlength="8"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-black focus:border-transparent outline-none">
            </div>

            <div id="password-error" class="text-red-600 text-sm hidden"></div>
            <div id="password-success" class="text-green-600 text-sm hidden"></div>

            <button type="submit" class="px-4 py-2 bg-black text-white rounded-lg font-medium hover:bg-gray-800 transition">
                Change Password
            </button>
        </form>
    </div>

    <!-- Change Master Password -->
    <div class="bg-white rounded-xl border border-red-200">
        <div class="p-5 border-b border-red-200 bg-red-50">
            <h3 class="font-semibold text-gray-900">Change Master Password</h3>
            <p class="text-sm text-gray-500">Re-encrypts all your data with the new master password</p>
        </div>
        <form id="change-master-password-form" class="p-5 space-y-4">
            <input type="hidden" name="csrf_token" value="<?php echo Auth::csrfToken(); ?>">

            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-4">
                <p class="text-sm text-yellow-800">
                    <strong>Warning:</strong> This will re-encrypt all your data files. Make sure you remember the new master password - there's no way to recover data without it.
                </p>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Current Master Password</label>
                <input type="password" id="current-master-password" required
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent outline-none">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">New Master Password</label>
                <input type="password" id="new-master-password" required minlength="4"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent outline-none">
                <p class="text-xs text-gray-500 mt-1">Minimum 4 characters</p>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Confirm New Master Password</label>
                <input type="password" id="confirm-new-master-password" required minlength="4"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent outline-none">
            </div>

            <div id="master-password-error" class="text-red-600 text-sm hidden"></div>
            <div id="master-password-success" class="text-green-600 text-sm hidden"></div>

            <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded-lg font-medium hover:bg-red-700 transition">
                Change Master Password
            </button>
        </form>
    </div>

    <!-- Data Management -->
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden group hover:shadow-lg transition-all">
        <div class="p-8 flex items-center justify-between">
            <div>
                <h3 class="text-xl font-bold text-gray-900">Data & Security</h3>
                <p class="text-sm text-gray-500 mt-1">Export your data, restore from backup, or manage portable snapshots.</p>
            </div>
            <a href="?page=import-data" class="px-6 py-3 bg-black text-white rounded-xl font-bold text-sm hover:bg-gray-800 transition shadow-sm">
                Manage Data →
            </a>
        </div>
        <div class="px-8 py-4 bg-gray-50/50 border-t border-gray-100 flex items-center gap-2">
            <svg class="w-4 h-4 text-green-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M2.166 4.9L10 1.554 17.834 4.9c.45.19.833.454 1.166.791V11c0 4.619-2.526 8.571-6.55 10.463l-.45.21-.45-.21c-4.024-1.892-6.55-5.844-6.55-10.463V5.691c.333-.337.717-.6 1.166-.791zM10 3.144L4 5.691V11c0 3.737 2.015 6.941 5.253 8.583L10 20.012l.747-.329C13.985 18.041 16 14.837 16 11V5.691L10 3.144zM10 7a1 1 0 011 1v3h3a1 1 0 110 2h-4a1 1 0 01-1-1V8a1 1 0 011-1z" clip-rule="evenodd"></path></svg>
            <span class="text-[10px] font-black uppercase tracking-widest text-gray-400">AES-256-GCM Encryption Active</span>
        </div>
    </div>
    
    <!-- Danger Zone -->
    <div class="bg-white rounded-xl border border-red-200 overflow-hidden">
        <div class="p-5 border-b border-red-200 bg-red-50">
            <h3 class="font-semibold text-red-900">Danger Zone</h3>
            <p class="text-sm text-red-700">Irreversible actions regarding your account and data</p>
        </div>
        <div class="p-5 space-y-6">
            <div class="flex items-center justify-between">
                <div>
                    <h4 class="font-medium text-gray-900">Delete All Data</h4>
                    <p class="text-sm text-gray-500">Permanently remove all your tasks, projects, and notes. Your account remains active.</p>
                </div>
                <button onclick="confirmDeleteData()" class="px-4 py-2 border border-red-300 text-red-700 rounded-lg font-medium hover:bg-red-50 transition">
                    Delete Data
                </button>
            </div>
            
            <div class="border-t border-gray-100 pt-6 flex items-center justify-between">
                <div>
                    <h4 class="font-medium text-gray-900">Delete Account</h4>
                    <p class="text-sm text-gray-500">Permanently remove your account and all associated data.</p>
                </div>
                <button onclick="confirmDeleteAccount()" class="px-4 py-2 bg-red-600 text-white rounded-lg font-medium hover:bg-red-700 transition">
                    Delete Account
                </button>
            </div>
        </div>
    </div>

    
    <!-- Account -->
    <div class="bg-white rounded-xl border border-gray-200">
        <div class="p-5 border-b border-gray-200">
            <h3 class="font-semibold text-gray-900">Account</h3>
        </div>
        <div class="p-5">
            <div class="flex items-center justify-between">
                <div>
                    <?php if ($user): ?>
                    <p class="font-medium text-gray-900"><?php echo e($user['name']); ?></p>
                    <p class="text-sm text-gray-500"><?php echo e($user['email']); ?></p>
                    <?php else: ?>
                    <p class="font-medium text-gray-900">Session expired</p>
                    <p class="text-sm text-gray-500">Please log in again</p>
                    <?php endif; ?>
                </div>
                <a href="<?php echo APP_URL; ?>/api/auth.php?action=logout" class="px-4 py-2 text-red-600 hover:bg-red-50 rounded-lg font-medium transition">
                    Sign Out
                </a>
            </div>
        </div>
    </div>

    <?php if (isAdminUser()): ?>
    <!-- Developer Tools Quick Actions -->
    <div class="bg-gradient-to-br from-gray-900 to-black rounded-2xl p-8 text-white">
        <div class="flex items-center gap-3 mb-6">
            <div class="w-10 h-10 bg-white/10 rounded-xl flex items-center justify-center">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"></path>
                </svg>
            </div>
            <div>
                <h3 class="text-xl font-bold">Developer Tools</h3>
                <p class="text-sm text-gray-400">Quick access to development and debugging utilities</p>
            </div>
        </div>

        <div class="mb-6 rounded-2xl border border-white/10 bg-white/5 p-5">
            <div class="mb-4 flex items-center justify-between gap-4">
                <div>
                    <h4 class="font-semibold text-white">Hosted Status</h4>
                    <p class="text-sm text-gray-400">Compact environment indicators for hosted deployments.</p>
                </div>
                <span class="text-[10px] font-bold uppercase tracking-[0.2em] text-gray-500">ENV</span>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <div class="rounded-xl border border-white/10 bg-black/20 px-4 py-3 flex items-center justify-between gap-3">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-wider text-gray-500">Email Verification</p>
                        <p class="text-xs text-gray-500">EMAIL_VERIFICATION_ENABLED</p>
                    </div>
                    <span class="text-xs font-semibold <?php echo isEmailVerificationEnabled() ? 'text-green-300 bg-green-500/10 border-green-500/30' : 'text-gray-300 bg-white/5 border-white/10'; ?> border rounded-full px-3 py-1"><?php echo isEmailVerificationEnabled() ? 'Enabled' : 'Disabled'; ?></span>
                </div>
                <div class="rounded-xl border border-white/10 bg-black/20 px-4 py-3 flex items-center justify-between gap-3">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-wider text-gray-500">Password Reset</p>
                        <p class="text-xs text-gray-500">PASSWORD_RESET_ENABLED</p>
                    </div>
                    <span class="text-xs font-semibold <?php echo isPasswordResetEnabled() ? 'text-green-300 bg-green-500/10 border-green-500/30' : 'text-gray-300 bg-white/5 border-white/10'; ?> border rounded-full px-3 py-1"><?php echo isPasswordResetEnabled() ? 'Enabled' : 'Disabled'; ?></span>
                </div>
                <div class="rounded-xl border border-white/10 bg-black/20 px-4 py-3 flex items-center justify-between gap-3">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-wider text-gray-500">Image Service</p>
                        <p class="text-xs text-gray-500"><?php echo e(IMAGE_SERVICE_PROVIDER !== '' ? IMAGE_SERVICE_PROVIDER : 'Provider not configured'); ?></p>
                    </div>
                    <span class="text-xs font-semibold <?php echo isImageServiceEnabled() ? 'text-green-300 bg-green-500/10 border-green-500/30' : 'text-gray-300 bg-white/5 border-white/10'; ?> border rounded-full px-3 py-1"><?php echo isImageServiceEnabled() ? 'Enabled' : 'Disabled'; ?></span>
                </div>
                <div class="rounded-xl border border-white/10 bg-black/20 px-4 py-3 flex items-center justify-between gap-3">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-wider text-gray-500">Mail Driver</p>
                        <p class="text-xs text-gray-500"><?php echo e(SMTP_HOST !== '' ? SMTP_HOST : 'SMTP host not configured'); ?></p>
                    </div>
                    <span class="text-xs font-semibold text-gray-200 bg-white/5 border-white/10 border rounded-full px-3 py-1"><?php echo e(strtoupper(MAIL_DRIVER)); ?></span>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
            <!-- Audit Logs -->
            <a href="?page=audit-logs" class="flex items-center gap-3 p-4 bg-white/5 rounded-xl border border-white/10 hover:bg-white/10 hover:border-white/20 transition group">
                <div class="w-10 h-10 bg-amber-500/20 rounded-lg flex items-center justify-center group-hover:bg-amber-500/30 transition">
                    <svg class="w-5 h-5 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                </div>
                <div class="flex-1">
                    <span class="font-bold text-sm">Audit Logs</span>
                    <p class="text-xs text-gray-500">System activity tracking</p>
                </div>
                <svg class="w-4 h-4 text-gray-600 group-hover:text-white transition" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                </svg>
            </a>

            <!-- Scheduler Status -->
            <a href="?page=scheduler-status" class="flex items-center gap-3 p-4 bg-white/5 rounded-xl border border-white/10 hover:bg-white/10 hover:border-white/20 transition group">
                <div class="w-10 h-10 bg-blue-500/20 rounded-lg flex items-center justify-center group-hover:bg-blue-500/30 transition">
                    <svg class="w-5 h-5 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
                <div class="flex-1">
                    <span class="font-bold text-sm">Scheduler Status</span>
                    <p class="text-xs text-gray-500">Cron job monitoring</p>
                </div>
                <svg class="w-4 h-4 text-gray-600 group-hover:text-white transition" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                </svg>
            </a>

            <!-- MCP Config -->
            <a href="?page=mcp" class="flex items-center gap-3 p-4 bg-white/5 rounded-xl border border-white/10 hover:bg-white/10 hover:border-white/20 transition group">
                <div class="w-10 h-10 bg-purple-500/20 rounded-lg flex items-center justify-center group-hover:bg-purple-500/30 transition">
                    <svg class="w-5 h-5 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"></path>
                    </svg>
                </div>
                <div class="flex-1">
                    <span class="font-bold text-sm">MCP Config</span>
                    <p class="text-xs text-gray-500">Model Context Protocol</p>
                </div>
                <svg class="w-4 h-4 text-gray-600 group-hover:text-white transition" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                </svg>
            </a>

            <!-- Speckitty -->
            <a href="?page=speckitty" class="flex items-center gap-3 p-4 bg-white/5 rounded-xl border border-white/10 hover:bg-white/10 hover:border-white/20 transition group">
                <div class="w-10 h-10 bg-pink-500/20 rounded-lg flex items-center justify-center group-hover:bg-pink-500/30 transition">
                    <svg class="w-5 h-5 text-pink-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"></path>
                    </svg>
                </div>
                <div class="flex-1">
                    <span class="font-bold text-sm">Speckitty</span>
                    <p class="text-xs text-gray-500">AI spec generator</p>
                </div>
                <svg class="w-4 h-4 text-gray-600 group-hover:text-white transition" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                </svg>
            </a>

            <!-- Custom Instructions -->
            <a href="?page=custom-instruction" class="flex items-center gap-3 p-4 bg-white/5 rounded-xl border border-white/10 hover:bg-white/10 hover:border-white/20 transition group">
                <div class="w-10 h-10 bg-cyan-500/20 rounded-lg flex items-center justify-center group-hover:bg-cyan-500/30 transition">
                    <svg class="w-5 h-5 text-cyan-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                    </svg>
                </div>
                <div class="flex-1">
                    <span class="font-bold text-sm">Custom Instructions</span>
                    <p class="text-xs text-gray-500">AI behavior settings</p>
                </div>
                <svg class="w-4 h-4 text-gray-600 group-hover:text-white transition" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                </svg>
            </a>

            <!-- Data Recovery -->
            <a href="?page=data-recovery" class="flex items-center gap-3 p-4 bg-white/5 rounded-xl border border-white/10 hover:bg-white/10 hover:border-white/20 transition group">
                <div class="w-10 h-10 bg-green-500/20 rounded-lg flex items-center justify-center group-hover:bg-green-500/30 transition">
                    <svg class="w-5 h-5 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                    </svg>
                </div>
                <div class="flex-1">
                    <span class="font-bold text-sm">Data Recovery</span>
                    <p class="text-xs text-gray-500">Backup & restore tools</p>
                </div>
                <svg class="w-4 h-4 text-gray-600 group-hover:text-white transition" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                </svg>
            </a>

            <a href="?page=users" class="flex items-center gap-3 p-4 bg-white/5 rounded-xl border border-white/10 hover:bg-white/10 hover:border-white/20 transition group">
                <div class="w-10 h-10 bg-indigo-500/20 rounded-lg flex items-center justify-center group-hover:bg-indigo-500/30 transition">
                    <svg class="w-5 h-5 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-1a4 4 0 00-5-4m-4 5H2v-1a4 4 0 015-4m6 5v-1a4 4 0 00-4-4H7m6-8a4 4 0 11-8 0 4 4 0 018 0zm6 2a3 3 0 11-6 0 3 3 0 016 0z"></path>
                    </svg>
                </div>
                <div class="flex-1">
                    <span class="font-bold text-sm">Users</span>
                    <p class="text-xs text-gray-500">Assign admin and user roles</p>
                </div>
                <svg class="w-4 h-4 text-gray-600 group-hover:text-white transition" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                </svg>
            </a>

            <a href="?page=shared-music" class="flex items-center gap-3 p-4 bg-white/5 rounded-xl border border-white/10 hover:bg-white/10 hover:border-white/20 transition group">
                <div class="w-10 h-10 bg-rose-500/20 rounded-lg flex items-center justify-center group-hover:bg-rose-500/30 transition">
                    <svg class="w-5 h-5 text-rose-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19V6l12-2v13M9 19a2 2 0 11-4 0 2 2 0 014 0zm12-2a2 2 0 11-4 0 2 2 0 014 0z"></path>
                    </svg>
                </div>
                <div class="flex-1">
                    <span class="font-bold text-sm">Shared Music</span>
                    <p class="text-xs text-gray-500">Pomodoro tracks for every workspace</p>
                </div>
                <svg class="w-4 h-4 text-gray-600 group-hover:text-white transition" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                </svg>
            </a>

            <a href="?page=release-export" class="flex items-center gap-3 p-4 bg-white/5 rounded-xl border border-white/10 hover:bg-white/10 hover:border-white/20 transition group">
                <div class="w-10 h-10 bg-orange-500/20 rounded-lg flex items-center justify-center group-hover:bg-orange-500/30 transition">
                    <svg class="w-5 h-5 text-orange-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v14m0 0l-5-5m5 5l5-5M19 20H5"></path>
                    </svg>
                </div>
                <div class="flex-1">
                    <span class="font-bold text-sm">Release Export</span>
                    <p class="text-xs text-gray-500">Generate fresh hosted and local ZIP builds</p>
                </div>
                <svg class="w-4 h-4 text-gray-600 group-hover:text-white transition" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                </svg>
            </a>
        </div>

        <div class="mt-6 pt-6 border-t border-white/10 flex items-center gap-2 text-xs text-gray-500">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            <span>Developer tools are for advanced users and system administrators</span>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
function confirmDeleteData() {
    openModal(`
        <div class="p-6">
            <div class="text-center mb-6">
                <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <svg class="w-8 h-8 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                    </svg>
                </div>
                <h3 class="text-xl font-bold text-gray-900 mb-2">Delete All Data?</h3>
                <p class="text-gray-600">This will permanently delete all your tasks, projects, invoices, and notes. This action cannot be undone.</p>
            </div>
            
            <form id="delete-data-form" onsubmit="handleDeleteData(event)" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Confirm Password</label>
                    <input type="password" name="password" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent outline-none">
                </div>
                <div id="delete-data-error" class="text-red-600 text-sm hidden"></div>
                <div class="flex gap-3 pt-2">
                    <button type="button" onclick="closeModal()" class="flex-1 px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50 transition">Cancel</button>
                    <button type="submit" class="flex-1 px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition">Delete Data</button>
                </div>
            </form>
        </div>
    `);
}

async function handleDeleteData(e) {
    e.preventDefault();
    const form = e.target;
    const password = form.password.value;
    const btn = form.querySelector('button[type="submit"]');
    const errorEl = document.getElementById('delete-data-error');
    
    btn.disabled = true;
    btn.textContent = 'Deleting...';
    errorEl.classList.add('hidden');
    
    try {
        const response = await api.post('api/auth.php?action=delete_data', {
            password: password,
            csrf_token: CSRF_TOKEN
        });
        
        if (response.success) {
            showToast('All data deleted successfully', 'success');
            closeModal();
            setTimeout(() => window.location.reload(), 1500);
        } else {
            errorEl.textContent = response.error || 'Failed to delete data';
            errorEl.classList.remove('hidden');
            btn.disabled = false;
            btn.textContent = 'Delete Data';
        }
    } catch (error) {
        errorEl.textContent = error.message || 'An error occurred';
        errorEl.classList.remove('hidden');
        btn.disabled = false;
        btn.textContent = 'Delete Data';
    }
}

function confirmDeleteAccount() {
    openModal(`
        <div class="p-6">
            <div class="text-center mb-6">
                <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <svg class="w-8 h-8 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                    </svg>
                </div>
                <h3 class="text-xl font-bold text-gray-900 mb-2">Delete Account?</h3>
                <p class="text-gray-600">This will permanently delete your account and ALL associated data. This action cannot be undone.</p>
            </div>
            
            <form id="delete-account-form" onsubmit="handleDeleteAccount(event)" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Confirm Password</label>
                    <input type="password" name="password" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent outline-none">
                </div>
                <div id="delete-account-error" class="text-red-600 text-sm hidden"></div>
                <div class="flex gap-3 pt-2">
                    <button type="button" onclick="closeModal()" class="flex-1 px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50 transition">Cancel</button>
                    <button type="submit" class="flex-1 px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition">Delete Account</button>
                </div>
            </form>
        </div>
    `);
}

async function handleDeleteAccount(e) {
    e.preventDefault();
    const form = e.target;
    const password = form.password.value;
    const btn = form.querySelector('button[type="submit"]');
    const errorEl = document.getElementById('delete-account-error');
    
    btn.disabled = true;
    btn.textContent = 'Deleting...';
    errorEl.classList.add('hidden');
    
    try {
        const response = await api.post('api/auth.php?action=delete_account', {
            password: password,
            csrf_token: CSRF_TOKEN
        });
        
        if (response.success) {
            showToast('Account deleted. Redirecting...', 'success');
            closeModal();
            setTimeout(() => window.location.href = '?page=login', 1500);
        } else {
            errorEl.textContent = response.error || 'Failed to delete account';
            errorEl.classList.remove('hidden');
            btn.disabled = false;
            btn.textContent = 'Delete Account';
        }
    } catch (error) {
        errorEl.textContent = error.message || 'An error occurred';
        errorEl.classList.remove('hidden');
        btn.disabled = false;
        btn.textContent = 'Delete Account';
    }
}

// Backup Management Functions
async function loadBackupStats() {
    try {
        const response = await api.get('api/backup.php?action=stats');
        if (response.success) {
            const data = response.data;
            document.getElementById('backup-count').textContent = data.total_backups || 0;
            document.getElementById('backup-size').textContent = data.total_size_formatted || '0 B';
            document.getElementById('backup-latest').textContent = data.latest_backup
                ? new Date(data.latest_backup.created_at).toLocaleString()
                : 'Never';
        }
    } catch (error) {
        console.error('Failed to load backup stats:', error);
        document.getElementById('backup-count').textContent = 'Error';
        document.getElementById('backup-size').textContent = 'Error';
    }
}

async function loadBackupList() {
    const tbody = document.getElementById('backup-list');
    try {
        const response = await api.get('api/backup.php?action=list');
        if (response.success && response.data.backups) {
            const backups = response.data.backups;
            if (backups.length === 0) {
                tbody.innerHTML = '<tr><td colspan="4" class="px-4 py-8 text-center text-gray-500">No backups found. Create your first backup above.</td></tr>';
                return;
            }
            tbody.innerHTML = backups.map(backup => `
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3">
                        <div class="flex items-center gap-2">
                            <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"></path></svg>
                            <span class="font-mono text-sm">${escapeHtml(backup.filename)}</span>
                        </div>
                    </td>
                    <td class="px-4 py-3 text-sm text-gray-600">${backup.size_formatted}</td>
                    <td class="px-4 py-3 text-sm text-gray-600">${new Date(backup.created_at).toLocaleString()}</td>
                    <td class="px-4 py-3 text-right">
                        <div class="flex items-center justify-end gap-1">
                            <button onclick="downloadBackup('${escapeHtml(backup.filename)}')" class="p-1.5 text-gray-400 hover:text-blue-600 transition" title="Download">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                            </button>
                            <button onclick="restoreBackup('${escapeHtml(backup.filename)}')" class="p-1.5 text-gray-400 hover:text-green-600 transition" title="Restore">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                            </button>
                            <button onclick="deleteBackup('${escapeHtml(backup.filename)}')" class="p-1.5 text-gray-400 hover:text-red-600 transition" title="Delete">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                            </button>
                        </div>
                    </td>
                </tr>
            `).join('');
        }
    } catch (error) {
        console.error('Failed to load backup list:', error);
        tbody.innerHTML = '<tr><td colspan="4" class="px-4 py-8 text-center text-red-500">Failed to load backups</td></tr>';
    }
}

async function createBackup() {
    const btn = document.getElementById('create-backup-btn');
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<svg class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg> Creating...';

    try {
        const response = await api.post('api/backup.php?action=create', {
            type: 'full',
            csrf_token: CSRF_TOKEN
        });

        if (response.success) {
            showToast('Backup created successfully!', 'success');
            loadBackupStats();
            loadBackupList();
        } else {
            showToast(response.error || 'Failed to create backup', 'error');
        }
    } catch (error) {
        showToast('Failed to create backup: ' + (error.message || 'Unknown error'), 'error');
    } finally {
        btn.disabled = false;
        btn.innerHTML = originalText;
    }
}

async function cleanupBackups() {
    try {
        const response = await api.post('api/backup.php?action=cleanup', {
            type: 'all',
            csrf_token: CSRF_TOKEN
        });

        if (response.success) {
            showToast(`Cleaned up ${response.data.deleted_count} old backups`, 'success');
            loadBackupStats();
            loadBackupList();
        } else {
            showToast(response.error || 'Failed to cleanup backups', 'error');
        }
    } catch (error) {
        showToast('Failed to cleanup backups: ' + (error.message || 'Unknown error'), 'error');
    }
}

function downloadBackup(filename) {
    showToast('Downloading backup...', 'info');
    window.location.href = `api/backup.php?action=download&filename=${encodeURIComponent(filename)}&csrf_token=${CSRF_TOKEN}`;
}

function restoreBackup(filename) {
    openModal(`
        <div class="p-6 text-center">
            <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
                <svg class="w-8 h-8 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                </svg>
            </div>
            <h3 class="text-xl font-bold text-gray-900 mb-2">Restore Backup?</h3>
            <p class="text-gray-600 mb-6">This will replace all current data with the backup data. This action cannot be undone.</p>
            <p class="text-sm font-medium text-gray-900 mb-6 font-mono">${escapeHtml(filename)}</p>
            <div class="flex gap-3 justify-center">
                <button onclick="closeModal()" class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50 transition">Cancel</button>
                <button onclick="doRestoreBackup('${escapeHtml(filename)}')" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition">Restore</button>
            </div>
        </div>
    `);
}

async function doRestoreBackup(filename) {
    closeModal();
    showToast('Restoring backup...', 'info');

    try {
        const response = await api.post('api/backup.php?action=restore', {
            filename: filename,
            csrf_token: CSRF_TOKEN
        });

        if (response.success) {
            showToast('Backup restored successfully!', 'success');
            setTimeout(() => {
                window.location.reload();
            }, 1500);
        } else {
            showToast(response.error || 'Failed to restore backup', 'error');
        }
    } catch (error) {
        showToast('Failed to restore backup: ' + (error.message || 'Unknown error'), 'error');
    }
}

function deleteBackup(filename) {
    openModal(`
        <div class="p-6 text-center">
            <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
                <svg class="w-8 h-8 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                </svg>
            </div>
            <h3 class="text-xl font-bold text-gray-900 mb-2">Delete Backup?</h3>
            <p class="text-gray-600 mb-6">This action cannot be undone.</p>
            <p class="text-sm font-medium text-gray-900 mb-6 font-mono">${escapeHtml(filename)}</p>
            <div class="flex gap-3 justify-center">
                <button onclick="closeModal()" class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50 transition">Cancel</button>
                <button onclick="doDeleteBackup('${escapeHtml(filename)}')" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition">Delete</button>
            </div>
        </div>
    `);
}

async function doDeleteBackup(filename) {
    closeModal();
    try {
        const response = await api.post('api/backup.php?action=delete', {
            filename: filename,
            csrf_token: CSRF_TOKEN
        });

        if (response.success) {
            showToast('Backup deleted successfully', 'success');
            loadBackupStats();
            loadBackupList();
        } else {
            showToast(response.error || 'Failed to delete backup', 'error');
        }
    } catch (error) {
        showToast('Failed to delete backup: ' + (error.message || 'Unknown error'), 'error');
    }
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Initialize backup data on page load
document.addEventListener('DOMContentLoaded', () => {
    // Only load if on settings page
    if (document.getElementById('backup-list')) {
        loadBackupStats();
        loadBackupList();
    }
});

document.getElementById('change-password-form').addEventListener('submit', async (e) => {
    e.preventDefault();

    const currentPassword = document.getElementById('current-password').value;
    const newPassword = document.getElementById('new-password').value;
    const confirmPassword = document.getElementById('confirm-new-password').value;
    const errorEl = document.getElementById('password-error');
    const successEl = document.getElementById('password-success');
    const submitBtn = e.target.querySelector('button[type="submit"]');

    // Reset messages
    errorEl.classList.add('hidden');
    successEl.classList.add('hidden');

    // Validation
    if (newPassword !== confirmPassword) {
        errorEl.textContent = 'New passwords do not match';
        errorEl.classList.remove('hidden');
        return;
    }

    if (newPassword.length < 8) {
        errorEl.textContent = 'Password must be at least 8 characters';
        errorEl.classList.remove('hidden');
        return;
    }

    submitBtn.disabled = true;
    submitBtn.textContent = 'Changing...';

    try {
        const response = await api.post('api/auth.php?action=change_password', {
            current_password: currentPassword,
            new_password: newPassword,
            csrf_token: CSRF_TOKEN
        });

        if (response.success) {
            successEl.textContent = 'Password changed successfully!';
            successEl.classList.remove('hidden');
            e.target.reset();
        } else {
            // Handle error message - could be string or object
            let errorMessage = 'Failed to change password';
            if (response.error) {
                if (typeof response.error === 'string') {
                    errorMessage = response.error;
                } else if (response.error.message) {
                    errorMessage = response.error.message;
                }
            }
            errorEl.textContent = errorMessage;
            errorEl.classList.remove('hidden');
        }
    } catch (error) {
        errorEl.textContent = error.message || 'An error occurred';
        errorEl.classList.remove('hidden');
    } finally {
        submitBtn.disabled = false;
        submitBtn.textContent = 'Change Password';
    }
});

document.getElementById('change-master-password-form').addEventListener('submit', async (e) => {
    e.preventDefault();

    const currentMasterPassword = document.getElementById('current-master-password').value;
    const newMasterPassword = document.getElementById('new-master-password').value;
    const confirmMasterPassword = document.getElementById('confirm-new-master-password').value;
    const errorEl = document.getElementById('master-password-error');
    const successEl = document.getElementById('master-password-success');
    const submitBtn = e.target.querySelector('button[type="submit"]');

    // Reset messages
    errorEl.classList.add('hidden');
    successEl.classList.add('hidden');

    // Validation
    if (newMasterPassword !== confirmMasterPassword) {
        errorEl.textContent = 'New master passwords do not match';
        errorEl.classList.remove('hidden');
        return;
    }

    if (newMasterPassword.length < 4) {
        errorEl.textContent = 'Master password must be at least 4 characters';
        errorEl.classList.remove('hidden');
        return;
    }

    submitBtn.disabled = true;
    submitBtn.textContent = 'Re-encrypting data...';

    try {
        const response = await api.post('api/auth.php?action=change_master_password', {
            current_master_password: currentMasterPassword,
            new_master_password: newMasterPassword,
            csrf_token: CSRF_TOKEN
        });

        if (response.success) {
            successEl.textContent = 'Master password changed and all data re-encrypted!';
            successEl.classList.remove('hidden');
            e.target.reset();
        } else {
            // Handle error message - could be string or object
            let errorMessage = 'Failed to change master password';
            if (response.error) {
                if (typeof response.error === 'string') {
                    errorMessage = response.error;
                } else if (response.error.message) {
                    errorMessage = response.error.message;
                }
            }
            errorEl.textContent = errorMessage;
            errorEl.classList.remove('hidden');
        }
    } catch (error) {
        errorEl.textContent = error.message || 'An error occurred';
        errorEl.classList.remove('hidden');
    } finally {
        submitBtn.disabled = false;
        submitBtn.textContent = 'Change Master Password';
    }
});

// Favicon Upload Functionality
document.getElementById('favicon-upload-form').addEventListener('submit', async (e) => {
    e.preventDefault();

    const form = e.target;
    const formData = new FormData(form);
    const messageEl = document.getElementById('favicon-message');
    const fileInput = document.getElementById('favicon-input');
    const submitBtn = form.querySelector('button[type="submit"]');

    // Hide previous messages
    messageEl.classList.add('hidden');
    messageEl.className = 'text-sm hidden';

    // Validate file
    if (!fileInput.files.length) {
        messageEl.textContent = 'Please select a file to upload';
        messageEl.className = 'text-sm text-red-600';
        messageEl.classList.remove('hidden');
        return;
    }

    // Add CSRF token if not already in FormData
    if (!formData.has('csrf_token')) {
        formData.append('csrf_token', CSRF_TOKEN);
    }

    submitBtn.disabled = true;
    submitBtn.textContent = 'Uploading...';

    try {
        const response = await fetch('api/favicon.php?action=upload', {
            method: 'POST',
            body: formData
        });

        // Check if response is JSON
        const contentType = response.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
            const text = await response.text();
            throw new Error('Server returned: ' + text.substring(0, 100));
        }

        const result = await response.json();

        if (result.success) {
            messageEl.textContent = 'Favicon uploaded successfully! Refreshing...';
            messageEl.className = 'text-sm text-green-600';
            messageEl.classList.remove('hidden');

            // Update preview with cache bust
            const preview = document.getElementById('favicon-preview');
            preview.src = 'assets/favicons/favicon-32x32.png?' + Date.now();

            // Update layout favicon links with cache bust
            document.querySelectorAll('link[rel*="icon"]').forEach(link => {
                const href = link.getAttribute('href');
                if (href && href.includes('favicon')) {
                    link.setAttribute('href', href.split('?')[0] + '?' + Date.now());
                }
            });

            // Update theme color in layout
            const themeColor = document.getElementById('theme-color-picker').value;
            document.querySelector('meta[name="theme-color"]').setAttribute('content', themeColor);

            // Reload page after short delay to apply changes
            setTimeout(() => {
                window.location.reload();
            }, 1500);
        } else {
            messageEl.textContent = result.error || 'Upload failed';
            messageEl.className = 'text-sm text-red-600';
            messageEl.classList.remove('hidden');
        }
    } catch (error) {
        messageEl.textContent = 'Upload failed: ' + (error.message || 'Unknown error');
        messageEl.className = 'text-sm text-red-600';
        messageEl.classList.remove('hidden');
    } finally {
        submitBtn.disabled = false;
        submitBtn.textContent = 'Upload Favicon';
    }
});

// Theme color sync
const colorPicker = document.getElementById('theme-color-picker');
const colorHex = document.getElementById('theme-color-hex');

if (colorPicker && colorHex) {
    colorPicker.addEventListener('input', (e) => {
        colorHex.value = e.target.value;
    });
    colorHex.addEventListener('input', (e) => {
        if (/^#[0-9A-Fa-f]{6}$/.test(e.target.value)) {
            colorPicker.value = e.target.value;
        }
    });
}

// Reset favicon to default
async function resetFavicon() {
    const messageEl = document.getElementById('favicon-message');
    messageEl.classList.add('hidden');

    if (!confirm('Reset favicon to default LM design?')) return;

    try {
        const response = await api.post('api/favicon.php?action=reset', {
            csrf_token: CSRF_TOKEN
        });

        if (response.success) {
            messageEl.textContent = 'Favicon reset! Refreshing...';
            messageEl.className = 'text-sm text-green-600';
            messageEl.classList.remove('hidden');

            const preview = document.getElementById('favicon-preview');
            preview.src = 'assets/favicons/favicon-32x32.png?' + Date.now();

            // Update all favicon links
            document.querySelectorAll('link[rel*="icon"]').forEach(link => {
                const href = link.getAttribute('href');
                if (href && href.includes('favicon')) {
                    link.setAttribute('href', href.split('?')[0] + '?' + Date.now());
                }
            });

            setTimeout(() => {
                window.location.reload();
            }, 1500);
        } else {
            messageEl.textContent = response.error || 'Reset failed';
            messageEl.className = 'text-sm text-red-600';
            messageEl.classList.remove('hidden');
        }
    } catch (error) {
        messageEl.textContent = 'Reset failed: ' + (error.message || 'Unknown error');
        messageEl.className = 'text-sm text-red-600';
        messageEl.classList.remove('hidden');
    }
}

// Load favicon preview with cache bust on page load
document.addEventListener('DOMContentLoaded', function() {
    const preview = document.getElementById('favicon-preview');
    if (preview) {
        preview.src = 'assets/favicons/favicon-32x32.png?t=' + Date.now();
    }
});

// Notification Sound Settings
const soundUrls = {
    'default': 'https://assets.mixkit.co/active_storage/sfx/2869/2869-preview.mp3',
    'chime': 'https://assets.mixkit.co/active_storage/sfx/2870/2870-preview.mp3',
    'alert': 'https://assets.mixkit.co/active_storage/sfx/2871/2871-preview.mp3',
    'ping': 'https://assets.mixkit.co/active_storage/sfx/2872/2872-preview.mp3',
    'silent': null
};

function previewSound() {
    const soundSelect = document.getElementById('notification-sound-select');
    const volumeSlider = document.getElementById('notification-volume');
    const soundType = soundSelect.value;
    const volume = volumeSlider.value / 100;

    if (soundType === 'silent') {
        showToast('Silent mode - no sound will play', 'info');
        return;
    }

    const soundUrl = soundUrls[soundType];
    if (!soundUrl) {
        showToast('Sound not available', 'error');
        return;
    }

    try {
        const audio = new Audio(soundUrl);
        audio.volume = volume;
        audio.play().catch(err => {
            showToast('Could not play sound: ' + err.message, 'error');
        });
    } catch (error) {
        showToast('Error playing sound: ' + error.message, 'error');
    }
}

function updateVolumeDisplay(value) {
    document.getElementById('volume-display').textContent = value + '%';
}

// Toggle sound settings visibility based on checkbox
document.addEventListener('DOMContentLoaded', function() {
    const soundEnabledCheckbox = document.getElementById('notification-sound-enabled');
    const soundSettings = document.getElementById('sound-settings');

    if (soundEnabledCheckbox && soundSettings) {
        function toggleSoundSettings() {
            if (soundEnabledCheckbox.checked) {
                soundSettings.classList.remove('opacity-50', 'pointer-events-none');
                soundSettings.querySelectorAll('input, select, button').forEach(el => {
                    el.disabled = false;
                });
            } else {
                soundSettings.classList.add('opacity-50', 'pointer-events-none');
                soundSettings.querySelectorAll('input, select, button').forEach(el => {
                    el.disabled = true;
                });
            }
        }

        soundEnabledCheckbox.addEventListener('change', toggleSoundSettings);
        toggleSoundSettings(); // Initialize on page load
    }
});
</script>

