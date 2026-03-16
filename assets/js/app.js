/**
 * LazyMan Tools - Main Application JavaScript
 * Uses Module Pattern for encapsulation
 */

const App = (function() {
    'use strict';
    const DEBUG_MODE = false;

    // Private state (encapsulated)
    let _reminderInterval = null;
    let _lastCheckedMinute = null;
    let _initialized = false;
    let _pendingConfirmAction = null;
    let _pendingCancelAction = null;

    // ============================================
    // Private Utilities
    // ============================================

    function _showToast(message, type = 'info') {
        const container = document.getElementById('toast-container');
        if (!container) return;

        const toast = document.createElement('div');
        toast.className = 'bg-black text-white px-4 py-3 border border-black shadow-lg animate-fade-in text-sm font-medium tracking-wide';
        toast.textContent = message;
        container.appendChild(toast);

        setTimeout(() => {
            toast.classList.add('opacity-0', 'transition-opacity');
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    }

    function _handleApiError(error) {
        console.error('API Error:', error);

        // Check for redirect in response first
        if (error.response?.redirect) {
            localStorage.removeItem('lazyman_auto_backup');
            window.location.href = error.response.redirect;
            return;
        }

        // Check for 401 Unauthorized - redirect to login
        if (error.status === 401 ||
            error.response?.error?.code === 'ERROR_UNAUTHORIZED' ||
            error.message?.includes('401')) {
            // Clear any cached state
            localStorage.removeItem('lazyman_auto_backup');
            // Redirect to login with session expired message
            window.location.href = '?page=login&reason=session_expired';
            return;
        }

        let message = 'Something went wrong';

        if (error.response?.error) {
            message = error.response.error.message || error.response.error.code;
        } else if (error.message) {
            message = error.message;
        }

        _showToast(message, 'error');
    }

    // ============================================
    // Public API
    // ============================================

    return {
        /**
         * API Helper Module
         */
        api: {
            async request(endpoint, options = {}) {
                const headers = {
                    'X-CSRF-Token': typeof CSRF_TOKEN !== 'undefined' ? CSRF_TOKEN : '',
                    ...options.headers
                };

                if (!(options.body instanceof FormData)) {
                    headers['Content-Type'] = headers['Content-Type'] || 'application/json';
                }

                // Ensure endpoint doesn't start with a slash if APP_URL is used
                const cleanEndpoint = endpoint.startsWith('/') ? endpoint.substring(1) : endpoint;
                const baseUrl = typeof APP_URL !== 'undefined' ? APP_URL : '';
                const url = baseUrl ? `${baseUrl}/${cleanEndpoint}` : cleanEndpoint;

                if (DEBUG_MODE) {
                    console.log('API Request:', { endpoint, cleanEndpoint, baseUrl, url, method: options.method });
                }

                const fetchOptions = {
                    ...options,
                    headers: headers
                };

                const response = await fetch(url, fetchOptions);

                if (!response.ok) {
                    const error = new Error(`HTTP error! status: ${response.status}`);
                    error.status = response.status;
                    const contentType = response.headers.get('content-type');
                    if (contentType && contentType.includes('application/json')) {
                        error.response = await response.json().catch(() => ({}));
                    } else {
                        const text = await response.text().catch(() => '');
                        error.response = { message: text.substring(0, 200) };
                    }
                    throw error;
                }

                const contentType = response.headers.get('content-type');
                if (!contentType || !contentType.includes('application/json')) {
                    const text = await response.text();
                    throw new Error('Server returned non-JSON response: ' + text.substring(0, 100));
                }

                return response.json().catch(e => {
                    throw new Error('Failed to parse JSON response');
                });
            },

            get(endpoint) {
                return this.request(endpoint);
            },

            post(endpoint, data) {
                return this.request(endpoint, {
                    method: 'POST',
                    body: JSON.stringify(data)
                });
            },

            put(endpoint, data) {
                return this.request(endpoint, {
                    method: 'PUT',
                    body: JSON.stringify(data)
                });
            },

            delete(endpoint) {
                return this.request(endpoint, { method: 'DELETE' });
            }
        },

        /**
         * UI Module - Toast notifications, modals, forms
         */
        ui: {
            showToast: _showToast,

            openModal(content) {
                const container = document.getElementById('modal-container');
                const modalContent = document.getElementById('modal-content');

                if (!container || !modalContent) return;

                modalContent.innerHTML = content;
                container.classList.remove('hidden');
                document.body.style.overflow = 'hidden';
            },

            closeModal() {
                const container = document.getElementById('modal-container');
                if (container) {
                    container.classList.add('hidden');
                    document.body.style.overflow = '';
                }
            },

            async handleForm(formId, onSuccess) {
                const form = document.getElementById(formId);
                if (!form) return;

                form.addEventListener('submit', async (e) => {
                    e.preventDefault();

                    const formData = new FormData(form);
                    const data = Object.fromEntries(formData.entries());
                    const action = form.getAttribute('action') || '';
                    const method = form.getAttribute('method') || 'POST';

                    try {
                        const response = await this.api.request(action, {
                            method: method.toUpperCase(),
                            body: JSON.stringify(data)
                        });

                        if (response.success) {
                            _showToast(response.message || 'Success!', 'success');
                            if (onSuccess) onSuccess(response);
                        } else {
                            _showToast(response.error?.message || 'Something went wrong', 'error');
                        }
                    } catch (error) {
                        _handleApiError(error);
                    }
                });
            },

            confirmAction(message, onConfirm) {
                return new Promise((resolve) => {
                    _pendingConfirmAction = async () => {
                        if (typeof onConfirm === 'function') await onConfirm();
                        resolve(true);
                    };
                    
                    _pendingCancelAction = () => {
                        resolve(false);
                    };

                    this.openModal(`
                        <div class="p-6">
                            <h3 class="text-lg font-semibold text-gray-900 mb-2">Confirm Action</h3>
                            <p class="text-gray-600 mb-6">${message}</p>
                            <div class="flex gap-3 justify-end">
                                <button onclick="App.ui.executeCancel()" class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50 transition">
                                    Cancel
                                </button>
                                <button onclick="App.ui.executeConfirm()" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition">
                                    Confirm
                                </button>
                            </div>
                        </div>
                    `);
                });
            },

            async executeConfirm() {
                this.closeModal();
                if (typeof _pendingConfirmAction === 'function') {
                    const action = _pendingConfirmAction;
                    _pendingConfirmAction = null;
                    _pendingCancelAction = null;
                    await action();
                }
            },

            executeCancel() {
                this.closeModal();
                if (typeof _pendingCancelAction === 'function') {
                    const action = _pendingCancelAction;
                    _pendingConfirmAction = null;
                    _pendingCancelAction = null;
                    action();
                }
            }
        },

        /**
         * Formatting Utilities
         */
        format: {
            currency(amount, currency = 'USD') {
                const symbols = { USD: '$', EUR: 'EUR ', GBP: 'GBP ', ZAR: 'R' };
                const symbol = symbols[currency] || currency + ' ';
                return symbol + parseFloat(amount).toFixed(2);
            },

            date(dateStr) {
                const date = new Date(dateStr);
                return date.toLocaleDateString('en-US', {
                    year: 'numeric',
                    month: 'short',
                    day: 'numeric'
                });
            },

            timeAgo(dateStr) {
                const date = new Date(dateStr);
                const now = new Date();
                const diff = Math.floor((now - date) / 1000);

                if (diff < 60) return 'just now';
                if (diff < 3600) return Math.floor(diff / 60) + ' min ago';
                if (diff < 86400) return Math.floor(diff / 3600) + ' hours ago';
                if (diff < 604800) return Math.floor(diff / 86400) + ' days ago';
                return this.date(dateStr);
            }
        },

        /**
         * Utility Functions
         */
        debounce(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        },

        /**
         * Task Reminder System with Smart Deadline Alerts
         */
        tasks: {
            NOTIFICATION_COOLDOWN_MS: 3600000, // 1 hour between same alert type

            /**
             * Alert type constants - use these instead of hardcoded strings
             * @readonly
             * @enum {string}
             */
            ALERT_TYPES: {
                OVERDUE: 'overdue',
                DUE_SOON: 'dueSoon',
                NOT_STARTED: 'notStarted'
            },

            /**
             * Entity type constants for notification key generation
             * @readonly
             * @enum {string}
             */
            ENTITY_TYPES: {
                TASK: 'task',
                SUBTASK: 'subtask'
            },

            /**
             * Priority levels for notifications
             * @readonly
             * @enum {string}
             */
            PRIORITY: {
                HIGH: 'high',
                MEDIUM: 'medium',
                LOW: 'low'
            },

            /**
             * Toast severity levels mapped to alert types
             * @readonly
             * @enum {string}
             */
            TOAST_SEVERITY: {
                overdue: 'info',
                dueSoon: 'info',
                notStarted: 'info'
            },

            /**
             * Get notification key for spam prevention
             * @param {string} entityId - The task or subtask ID
             * @param {string} alertType - The alert type from ALERT_TYPES
             * @param {string} [entityType='task'] - Entity type from ENTITY_TYPES
             * @returns {string} The localStorage key
             */
            getNotificationKey(entityId, alertType, entityType = 'task') {
                return `notified_${entityType}_${entityId}_${alertType}`;
            },

            /**
             * Check if we should notify (spam prevention)
             * @param {string} entityId - The task or subtask ID
             * @param {string} alertType - The alert type from ALERT_TYPES
             * @param {string} [entityType='task'] - Entity type from ENTITY_TYPES
             * @returns {boolean} Whether notification should be sent
             */
            shouldNotify(entityId, alertType, entityType = 'task') {
                const key = this.getNotificationKey(entityId, alertType, entityType);
                const lastNotified = localStorage.getItem(key);

                if (!lastNotified) {
                    return true;
                }

                const timeSinceLastNotification = Date.now() - parseInt(lastNotified);
                return timeSinceLastNotification > this.NOTIFICATION_COOLDOWN_MS;
            },

            /**
             * Mark notification as sent
             * @param {string} entityId - The task or subtask ID
             * @param {string} alertType - The alert type from ALERT_TYPES
             * @param {string} [entityType='task'] - Entity type from ENTITY_TYPES
             */
            markNotified(entityId, alertType, entityType = 'task') {
                const key = this.getNotificationKey(entityId, alertType, entityType);
                localStorage.setItem(key, Date.now().toString());
            },

            /**
             * Determine alert type based on time difference
             */
            determineAlertType(timeDiff, timerRunning) {
                const minutesDiff = Math.floor(timeDiff / (1000 * 60));
                const hoursDiff = timeDiff / (1000 * 3600);

                // Overdue: past due date
                if (timeDiff < 0) {
                    return this.ALERT_TYPES.OVERDUE;
                }

                // Due Soon: within 15 minutes
                if (minutesDiff >= 0 && minutesDiff <= 15) {
                    return this.ALERT_TYPES.DUE_SOON;
                }

                // Not Started: timer not running and due within 1 hour
                if (!timerRunning && hoursDiff > 0 && hoursDiff <= 1) {
                    return this.ALERT_TYPES.NOT_STARTED;
                }

                return null;
            },

            /**
             * Get alert message based on type
             * @param {Object} task - The task or subtask object
             * @param {string} alertType - The alert type from ALERT_TYPES
             * @param {number} timeDiff - Time difference in milliseconds
             * @returns {Object|null} The alert message with title, body, and priority
             */
            getAlertMessage(task, alertType, timeDiff) {
                const minutesDiff = Math.floor(timeDiff / (1000 * 60));
                const hoursDiff = Math.floor(timeDiff / (1000 * 3600));

                switch (alertType) {
                    case this.ALERT_TYPES.OVERDUE:
                        const minutesOverdue = Math.abs(minutesDiff);
                        return {
                            title: `⏰ OVERDUE: ${task.title}`,
                            body: `This task was due ${minutesOverdue} minute${minutesOverdue !== 1 ? 's' : ''} ago!`,
                            priority: this.PRIORITY.HIGH
                        };

                    case this.ALERT_TYPES.DUE_SOON:
                        return {
                            title: `⚠️ DUE SOON: ${task.title}`,
                            body: `This task is due in ${minutesDiff} minute${minutesDiff !== 1 ? 's' : ''}!`,
                            priority: this.PRIORITY.MEDIUM
                        };

                    case this.ALERT_TYPES.NOT_STARTED:
                        return {
                            title: `📌 NOT STARTED: ${task.title}`,
                            body: `This task hasn't been started and is due in ${hoursDiff} hour${hoursDiff !== 1 ? 's' : ''}!`,
                            priority: this.PRIORITY.LOW
                        };

                    default:
                        return null;
                }
            },

            /**
             * Enhanced task reminder checking with smart alerts
             */
            async checkReminders() {
                try {
                    const now = new Date();
                    const response = await App.api.get('api/tasks.php');
                    const tasks = response.data || [];

                    // Load notification settings
                    const settings = await App.notifications.loadSettings();

                    tasks.forEach(task => {
                        // Skip completed or cancelled tasks
                        if (task.status === 'done' || task.status === 'cancelled' || !task.dueDate) {
                            return;
                        }

                        const dueDate = new Date(task.dueDate);
                        const timeDiff = dueDate.getTime() - now.getTime();

                        // Check if timer is running (from localStorage)
                        const timerKey = `task_timer_${task.id}`;
                        const timerData = localStorage.getItem(timerKey);
                        const timerRunning = timerData ? JSON.parse(timerData).running : false;

                        // Determine alert type
                        const alertType = this.determineAlertType(timeDiff, timerRunning);

                        if (!alertType) {
                            return; // No alert needed
                        }

                        // Check if alert type is enabled in settings
                        const alertSettingKey = `${alertType}AlertEnabled`;
                        if (!settings[alertSettingKey]) {
                            return; // Alert type disabled
                        }

                        // Check spam prevention
                        if (!this.shouldNotify(task.id, alertType)) {
                            return; // Already notified recently
                        }

                        // Get alert message
                        const message = this.getAlertMessage(task, alertType, timeDiff);
                        if (!message) {
                            return;
                        }

                        // Send browser notification
                        App.notifications.send(message.title, {
                            body: message.body,
                            requireInteraction: true,
                            tag: `task-${task.id}-${alertType}`,
                            data: {
                                taskId: task.id,
                                alertType: alertType,
                                priority: message.priority
                            }
                        });

                        // Play sound with alert type
                        App.notifications.playSound(alertType);

                        // Show toast notification
                        _showToast(message.title, this.TOAST_SEVERITY[alertType] || 'warning');

                        // Mark as notified
                        this.markNotified(task.id, alertType);
                    });
                } catch (error) {
                    console.error('Failed to check task reminders:', error);
                }
            },

            /**
             * Phase 5: Check sub-task deadline alerts
             */
            async checkSubtaskReminders() {
                try {
                    const now = new Date();
                    const response = await App.api.get('api/tasks.php');
                    const tasks = response.data || [];

                    // Load notification settings
                    const settings = await App.notifications.loadSettings();

                    tasks.forEach(task => {
                        // Check sub-tasks
                        const subtasks = task.subtasks || [];
                        subtasks.forEach(subtask => {
                            // Skip completed sub-tasks or those without due dates
                            if (subtask.completed || !subtask.dueDate) {
                                return;
                            }

                            const dueDate = new Date(subtask.dueDate);
                            const timeDiff = dueDate.getTime() - now.getTime();

                            // Check if timer is running (from localStorage)
                            const timerKey = `subtask_timer_${subtask.id}`;
                            const timerData = localStorage.getItem(timerKey);
                            const timerRunning = timerData ? JSON.parse(timerData).running : false;

                            // Determine alert type
                            const alertType = this.determineAlertType(timeDiff, timerRunning);

                            if (!alertType) {
                                return; // No alert needed
                            }

                            // Check if alert type is enabled in settings
                            const alertSettingKey = `${alertType}AlertEnabled`;
                            if (!settings[alertSettingKey]) {
                                return; // Alert type disabled
                            }

                            // Check spam prevention using consistent key format
                            if (!this.shouldNotify(subtask.id, alertType, this.ENTITY_TYPES.SUBTASK)) {
                                return; // Already notified recently
                            }

                            // Get alert message
                            const message = this.getAlertMessage(subtask, alertType, timeDiff);
                            if (!message) {
                                return;
                            }

                            // Send browser notification
                            App.notifications.send(message.title, {
                                body: message.body,
                                requireInteraction: true,
                                tag: `subtask-${subtask.id}-${alertType}`,
                                data: {
                                    subtaskId: subtask.id,
                                    taskId: task.id,
                                    alertType: alertType,
                                    priority: message.priority
                                }
                            });

                            // Play sound with alert type
                            App.notifications.playSound(alertType);

                            // Show toast notification
                            _showToast(message.title, this.TOAST_SEVERITY[alertType] || 'warning');

                            // Mark as notified using consistent method
                            this.markNotified(subtask.id, alertType, this.ENTITY_TYPES.SUBTASK);
                        });
                    });
                } catch (error) {
                    console.error('Failed to check subtask reminders:', error);
                }
            },

            /**
             * Initialize task reminder system
             * Separate from habit reminders for clean architecture
             */
            initialize() {
                // Request notification permission on first interaction/init
                if (App.notifications.checkPermission() === 'default') {
                    setTimeout(() => App.notifications.requestPermission(), 2000);
                }

                // Initial check
                this.checkReminders();
                this.checkSubtaskReminders();

                // Set up interval for task reminders
                if (window._taskReminderInterval) {
                    clearInterval(window._taskReminderInterval);
                }
                window._taskReminderInterval = setInterval(() => {
                    this.checkReminders();
                    this.checkSubtaskReminders();
                }, 60000);

                if (DEBUG_MODE) {
                    console.log('Task reminder system initialized');
                }
            }
        },

        /**
         * Habit Reminder System
         */
        habits: {
            RANDOM_REMINDER_STORAGE_KEY: 'lazyman_habit_random_reminders_v1',
            RANDOM_REMINDER_COUNT: 2,
            RANDOM_WINDOW_START_MINUTE: 9 * 60,
            RANDOM_WINDOW_END_MINUTE: 21 * 60,
            getTodayDateKey() {
                return new Date().toISOString().slice(0, 10);
            },
            getCurrentMinuteOfDay() {
                const now = new Date();
                return (now.getHours() * 60) + now.getMinutes();
            },
            loadRandomReminderState() {
                try {
                    const raw = localStorage.getItem(this.RANDOM_REMINDER_STORAGE_KEY);
                    if (!raw) {
                        return null;
                    }
                    const parsed = JSON.parse(raw);
                    if (!parsed || typeof parsed !== 'object') {
                        return null;
                    }
                    return parsed;
                } catch (error) {
                    return null;
                }
            },
            saveRandomReminderState(state) {
                try {
                    localStorage.setItem(this.RANDOM_REMINDER_STORAGE_KEY, JSON.stringify(state));
                } catch (error) {
                    // Ignore storage errors so reminders continue to work.
                }
            },
            generateRandomReminderSlots() {
                const slots = new Set();
                const range = Math.max(1, this.RANDOM_WINDOW_END_MINUTE - this.RANDOM_WINDOW_START_MINUTE);
                while (slots.size < this.RANDOM_REMINDER_COUNT) {
                    const next = this.RANDOM_WINDOW_START_MINUTE + Math.floor(Math.random() * range);
                    slots.add(next);
                }
                return Array.from(slots).sort((a, b) => a - b);
            },
            ensureRandomReminderState(todayKey) {
                const state = this.loadRandomReminderState();
                if (state && state.date === todayKey && Array.isArray(state.slots) && state.slots.length) {
                    return state;
                }
                const freshState = {
                    date: todayKey,
                    slots: this.generateRandomReminderSlots(),
                    sent: {}
                };
                this.saveRandomReminderState(freshState);
                return freshState;
            },
            async checkReminders() {
                try {
                    const currentTimeKey = new Date().toISOString().slice(0, 16);

                    if (_lastCheckedMinute === currentTimeKey) {
                        return;
                    }

                    _lastCheckedMinute = currentTimeKey;

                    const response = await App.api.get('api/habits.php');
                    const habits = Array.isArray(response.data) ? response.data : [];
                    const activeDailyHabits = habits.filter((habit) => {
                        const isDaily = String(habit.frequency || '').toLowerCase() === 'daily';
                        const isActive = habit.isActive !== false;
                        return isDaily && isActive && !habit.todayCompleted;
                    });

                    if (!activeDailyHabits.length) {
                        return;
                    }

                    const todayKey = this.getTodayDateKey();
                    const minuteOfDay = this.getCurrentMinuteOfDay();
                    const state = this.ensureRandomReminderState(todayKey);

                    for (const slot of state.slots) {
                        const slotKey = String(slot);
                        if (minuteOfDay < slot || state.sent[slotKey]) {
                            continue;
                        }

                        const habit = activeDailyHabits[Math.floor(Math.random() * activeDailyHabits.length)];
                        const title = `Habit Reminder: ${habit.name}`;
                        const body = `Have you done "${habit.name}" today?`;

                        App.notifications.send(title, {
                            body,
                            requireInteraction: false,
                            tag: `habit-random-${habit.id}-${todayKey}-${slotKey}`
                        });
                        _showToast(body, 'info');
                        state.sent[slotKey] = true;
                    }

                    this.saveRandomReminderState(state);
                } catch (error) {
                    console.error('Failed to check habit reminders:', error);
                }
            },

            initialize() {
                if (_reminderInterval) {
                    clearInterval(_reminderInterval);
                }

                // Request notification permission on first interaction/init
                if (App.notifications.checkPermission() === 'default') {
                    // Small delay to not annoy the user immediately
                    setTimeout(() => App.notifications.requestPermission(), 2000);
                }

                this.checkReminders();
                App.tasks.checkReminders();
                App.tasks.checkSubtaskReminders(); // Phase 5: Check sub-task reminders

                _reminderInterval = setInterval(() => {
                    this.checkReminders();
                    App.tasks.checkReminders();
                    App.tasks.checkSubtaskReminders(); // Phase 5: Check sub-task reminders
                }, 60000);

                if (DEBUG_MODE) {
                    console.log('Reminder systems initialized (including Phase 5 sub-task alerts)');
                }
            },

            stop() {
                if (_reminderInterval) {
                    clearInterval(_reminderInterval);
                    _reminderInterval = null;
                }
            }
        },

        /**
         * Notification System
         * Enterprise-grade notification handling with graceful degradation
         */
        notifications: {
            STORAGE_KEY: 'lazyman_notification_pref',
            SOUND_URLS: {
                'default': 'https://assets.mixkit.co/active_storage/sfx/2869/2869-preview.mp3',
                'chime': 'https://assets.mixkit.co/active_storage/sfx/2870/2870-preview.mp3',
                'alert': 'https://assets.mixkit.co/active_storage/sfx/2871/2871-preview.mp3',
                'ping': 'https://assets.mixkit.co/active_storage/sfx/2872/2872-preview.mp3',
                'silent': null
            },
            notificationSettings: null,

            /**
             * Load notification settings from config
             */
            async loadSettings() {
                if (this.notificationSettings) {
                    return this.notificationSettings;
                }

                try {
                    const response = await App.api.get('api/settings.php?action=get');
                    if (response.success) {
                        this.notificationSettings = {
                            soundEnabled: response.data.notificationSoundEnabled !== false,
                            soundType: response.data.notificationSound || 'default',
                            volume: (response.data.notificationVolume || 70) / 100,
                            overdueAlertEnabled: response.data.overdueAlertEnabled !== false,
                            dueSoonAlertEnabled: response.data.dueSoonAlertEnabled !== false,
                            notStartedAlertEnabled: response.data.notStartedAlertEnabled !== false
                        };
                        return this.notificationSettings;
                    }
                } catch (error) {
                    console.warn('Failed to load notification settings:', error);
                }

                // Return defaults if loading fails
                return {
                    soundEnabled: true,
                    soundType: 'default',
                    volume: 0.7,
                    overdueAlertEnabled: true,
                    dueSoonAlertEnabled: true,
                    notStartedAlertEnabled: true
                };
            },

            /**
             * Play notification sound with configurable settings
             */
            async playSound(alertType = 'default') {
                try {
                    const settings = await this.loadSettings();

                    // Check if sound is enabled and alert type is enabled
                    if (!settings.soundEnabled) {
                        return;
                    }

                    // Check specific alert type
                    if (alertType === 'overdue' && !settings.overdueAlertEnabled) return;
                    if (alertType === 'dueSoon' && !settings.dueSoonAlertEnabled) return;
                    if (alertType === 'notStarted' && !settings.notStartedAlertEnabled) return;

                    const soundType = settings.soundType || 'default';
                    const soundUrl = this.SOUND_URLS[soundType];

                    if (!soundUrl) {
                        return; // Silent mode
                    }

                    const audio = new Audio(soundUrl);
                    audio.volume = settings.volume;
                    audio.play().catch(e => console.warn('Audio play blocked by browser:', e));
                } catch (e) {
                    console.error('Error playing sound:', e);
                }
            },

            /**
             * Check current notification permission state
             * @returns {string} - 'granted', 'denied', 'default', or 'unsupported'
             */
            checkPermission() {
                if (!('Notification' in window)) {
                    return 'unsupported';
                }
                return Notification.permission;
            },

            /**
             * Request notification permission from user
             * @returns {Promise<string>} - Permission state
             */
            async requestPermission() {
                const currentPermission = this.checkPermission();

                if (currentPermission === 'unsupported') {
                    _showToast('Browser notifications not supported', 'error');
                    return 'unsupported';
                }

                if (currentPermission === 'denied') {
                    this.showDeniedHelp();
                    return 'denied';
                }

                if (currentPermission === 'granted') {
                    localStorage.setItem(this.STORAGE_KEY, 'granted');
                    return 'granted';
                }

                try {
                    const permission = await Notification.requestPermission();

                    if (permission === 'granted') {
                        _showToast('Notifications enabled!', 'success');
                        localStorage.setItem(this.STORAGE_KEY, 'granted');
                    } else if (permission === 'denied') {
                        this.showDeniedHelp();
                        localStorage.setItem(this.STORAGE_KEY, 'denied');
                    } else {
                        _showToast('Notifications not enabled', 'info');
                        localStorage.setItem(this.STORAGE_KEY, 'default');
                    }

                    return permission;
                } catch (error) {
                    console.error('Error requesting notification permission:', error);
                    _showToast('Failed to request notification permission', 'error');
                    return 'error';
                }
            },

            /**
             * Show help modal when notifications are denied
             */
            showDeniedHelp() {
                const helpContent = `
                    <div class="p-6 text-center max-w-md mx-auto">
                        <div class="w-16 h-16 bg-yellow-100 rounded-full flex items-center justify-center mx-auto mb-4">
                            <svg class="w-8 h-8 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                            </svg>
                        </div>
                        <h3 class="text-xl font-bold text-gray-900 mb-2">Notifications Blocked</h3>
                        <p class="text-gray-600 mb-4">Notifications are currently blocked. To enable them, please update your browser settings:</p>
                        <div class="text-left bg-gray-100 p-4 rounded-lg text-sm space-y-2">
                            <p><strong>Chrome/Edge:</strong> Click 🔒 next to URL → Site Settings → Notifications → Allow</p>
                            <p><strong>Firefox:</strong> Click ⊘ in address bar → Permissions → Notifications → Allow</p>
                            <p><strong>Safari:</strong> Safari → Settings → Websites → Notifications → Allow</p>
                        </div>
                        <button onclick="App.ui.closeModal()" class="mt-6 px-6 py-2 bg-black text-white rounded-lg hover:bg-gray-800 transition">
                            I Understand
                        </button>
                    </div>
                `;
                App.ui.openModal(helpContent);
            },

            /**
             * Send notification with fallback to toast
             * @param {string} title - Notification title
             * @param {Object} options - Notification options
             * @param {boolean} forceToast - Force toast notification even if browser notifications available
             */
            async send(title, options = {}, forceToast = false) {
                const permission = this.checkPermission();
                
                console.log('[NOTIFICATION DEBUG] Permission:', permission);
                console.log('[NOTIFICATION DEBUG] Title:', title);
                console.log('[NOTIFICATION DEBUG] Options:', options);
                console.log('[NOTIFICATION DEBUG] forceToast:', forceToast);
                
                // Always try to play sound if not silent
                if (!options.silent) {
                    this.playSound();
                }

                if (permission === 'granted' && !forceToast) {
                    console.log('[NOTIFICATION DEBUG] Attempting browser notification...');
                    try {
                        const validOptions = {};
                        const allowedOptions = ['body', 'icon', 'badge', 'dir', 'lang', 'tag', 'image', 'data', 'vibrate', 'renotify', 'requireInteraction', 'silent', 'timestamp'];

                        for (const key of allowedOptions) {
                            if (options[key] !== undefined) {
                                validOptions[key] = options[key];
                            }
                        }

                        if (options.icon) validOptions.icon = options.icon;
                        if (options.badge) validOptions.badge = options.badge;
                        if (options.requireInteraction !== undefined) validOptions.requireInteraction = options.requireInteraction;

                        console.log('[NOTIFICATION DEBUG] Creating Notification with:', validOptions);
                        const notification = new Notification(title, validOptions);
                        console.log('[NOTIFICATION DEBUG] Notification created successfully:', notification);

                        if (options.onClick) {
                            notification.onclick = options.onClick;
                        }

                        if (options.timeout) {
                            setTimeout(() => notification.close(), options.timeout);
                        }

                        return notification;
                    } catch (error) {
                        console.error('[NOTIFICATION DEBUG] Error creating notification:', error);
                        console.log('[NOTIFICATION DEBUG] Falling back to toast');
                        return this.sendAsToast(title, options);
                    }
                } else {
                    console.log('[NOTIFICATION DEBUG] Permission not granted or forceToast=true, using toast fallback');
                    return this.sendAsToast(title, options);
                }
            },

            /**
             * Fallback to toast notification
             * @param {string} title - Notification title
             * @param {Object} options - Notification options
             */
            sendAsToast(title, options = {}) {
                const message = options.body || title;
                const type = options.type || 'info';
                _showToast(message, type);
                return null;
            },

            /**
             * Check if user previously denied notifications
             * @returns {boolean}
             */
            isDenied() {
                return this.checkPermission() === 'denied';
            },

            /**
             * Check if notifications are supported
             * @returns {boolean}
             */
            isSupported() {
                return 'Notification' in window;
            },

            /**
             * Check if user has granted permission
             * @returns {boolean}
             */
            isGranted() {
                return this.checkPermission() === 'granted';
            },

            /**
             * Get user's saved preference
             * @returns {string|null}
             */
            getPreference() {
                return localStorage.getItem(this.STORAGE_KEY);
            },

            /**
             * Clear user's saved preference
             */
            clearPreference() {
                localStorage.removeItem(this.STORAGE_KEY);
            }
        },

        /**
         * Water Plan Notifications Module
         * Manages cross-page hydration reminders.
         */
        waterPlanNotifications: {
            activePlan: null,
            checkInterval: null,
            initialized: false,
            PRE_ALERT_MINUTES: 5,
            GRACE_PERIOD_MINUTES: 10,
            reminderMinuteMap: {},
            missInFlight: {},
            permissionPromptRequested: false,
            permissionInteractionBound: false,
            permissionHintShown: false,

            /**
             * Prompt for browser notification permission on first user interaction.
             * This covers browsers that suppress non-gesture permission prompts.
             */
            bindPermissionRequestOnInteraction() {
                if (this.permissionInteractionBound) {
                    return;
                }
                if (!App.notifications || typeof App.notifications.checkPermission !== 'function') {
                    return;
                }
                if (App.notifications.checkPermission() !== 'default') {
                    return;
                }

                const requestOnInteraction = async () => {
                    document.removeEventListener('click', requestOnInteraction, true);
                    document.removeEventListener('keydown', requestOnInteraction, true);
                    document.removeEventListener('touchstart', requestOnInteraction, true);
                    await this.maybeRequestNotificationPermission('interaction');
                };

                document.addEventListener('click', requestOnInteraction, true);
                document.addEventListener('keydown', requestOnInteraction, true);
                document.addEventListener('touchstart', requestOnInteraction, true);
                this.permissionInteractionBound = true;
            },

            /**
             * Request native notification permission once for water reminders.
             */
            async maybeRequestNotificationPermission(source = 'init') {
                if (!App.notifications || typeof App.notifications.checkPermission !== 'function') {
                    return;
                }

                const permission = App.notifications.checkPermission();
                if (permission === 'default') {
                    if (this.permissionPromptRequested) {
                        return;
                    }

                    this.permissionPromptRequested = true;
                    const requestPermission = async () => {
                        try {
                            await App.notifications.requestPermission();
                        } catch (error) {
                            if (DEBUG_MODE) {
                                console.warn('Water reminder notification permission request failed:', error);
                            }
                        }
                    };

                    if (source === 'interaction') {
                        await requestPermission();
                    } else {
                        setTimeout(() => {
                            requestPermission();
                        }, 1500);
                    }
                    return;
                }

                if (permission === 'denied' && !this.permissionHintShown) {
                    this.permissionHintShown = true;
                    _showToast('Enable browser notifications in site settings for native water reminders.', 'info');
                }
            },

            /**
             * Initialize reminder checks once per app session.
             */
            async init() {
                if (this.initialized) {
                    return;
                }
                this.initialized = true;

                this.bindPermissionRequestOnInteraction();
                this.maybeRequestNotificationPermission('init');
                await this.bootstrap({ forceRefresh: true, silent: true });
                this.checkInterval = setInterval(() => {
                    this.bootstrap({ silent: true });
                }, 60000);
            },

            /**
             * Load active plan and process reminder window.
             */
            async bootstrap(options = {}) {
                const forceRefresh = options.forceRefresh === true;
                const silent = options.silent === true;
                await this.refreshPlan(forceRefresh);
                this.runTick({ silent });
            },

            /**
             * Refresh the active water plan from API.
             */
            async refreshPlan(forceRefresh = false) {
                try {
                    const response = await App.api.get('api/habits.php?action=get_water_plan');
                    const hasValidSchedule = Array.isArray(response?.data?.schedule);
                    const isExplicitlyActive = response?.data?.isActive === true;
                    if (response.success && response.data && hasValidSchedule && isExplicitlyActive) {
                        this.activePlan = response.data;
                    } else {
                        this.activePlan = null;
                    }
                } catch (error) {
                    // 404 is expected when no active plan exists.
                    this.activePlan = null;
                }

                if (!this.activePlan) {
                    this.reminderMinuteMap = {};
                    this.missInFlight = {};
                }
            },

            /**
             * Convert plan schedule amount to milliliters.
             */
            getScheduleAmountMl(item) {
                const rawAmount = Number(item?.amount) || 0;
                const glassSize = Math.max(1, Number(this.activePlan?.glassSize) || 250);

                if (rawAmount <= 0) {
                    return glassSize;
                }

                return rawAmount <= 20 ? Math.round(rawAmount * glassSize) : Math.round(rawAmount);
            },

            /**
             * Build today's Date object for a HH:mm schedule item.
             */
            getScheduledTimeToday(timeString) {
                const now = new Date();
                const scheduled = new Date(now.getFullYear(), now.getMonth(), now.getDate(), 0, 0, 0, 0);
                const [hours, minutes] = String(timeString || '').split(':');
                scheduled.setHours(parseInt(hours || '0', 10), parseInt(minutes || '0', 10), 0, 0);
                return scheduled;
            },

            /**
             * Do not notify slots that happened before the plan was created on the same day.
             */
            isBeforePlanStart(scheduledTime, planCreatedAt) {
                if (!planCreatedAt || Number.isNaN(planCreatedAt.getTime())) {
                    return false;
                }
                const sameDay = scheduledTime.toDateString() === planCreatedAt.toDateString();
                return sameDay && scheduledTime < planCreatedAt;
            },

            /**
             * Main per-minute tick for pre-alert and grace-window reminders.
             */
            runTick(options = {}) {
                const silent = options.silent === true;
                if (!this.activePlan || !Array.isArray(this.activePlan.schedule) || !this.activePlan.id) {
                    return;
                }

                const now = new Date();
                const minuteKey = Math.floor(now.getTime() / 60000);
                const planCreatedAt = this.activePlan?.createdAt ? new Date(this.activePlan.createdAt) : null;
                const preAlertMs = this.PRE_ALERT_MINUTES * 60 * 1000;
                const graceMs = this.GRACE_PERIOD_MINUTES * 60 * 1000;

                this.activePlan.schedule.forEach((item) => {
                    if (!item || item.completed || item.missed || !item.time) {
                        return;
                    }

                    const scheduled = this.getScheduledTimeToday(item.time);
                    if (this.isBeforePlanStart(scheduled, planCreatedAt)) {
                        return;
                    }

                    const reminderKey = item.id || item.time;
                    const windowStart = scheduled.getTime() - preAlertMs;
                    const graceDeadline = scheduled.getTime() + graceMs;

                    if (now.getTime() > graceDeadline) {
                        this.markMissed(item, { silent });
                        return;
                    }

                    if (now.getTime() < windowStart) {
                        return;
                    }

                    if (this.reminderMinuteMap[reminderKey] === minuteKey) {
                        return;
                    }

                    this.reminderMinuteMap[reminderKey] = minuteKey;

                    const amountLiters = (this.getScheduleAmountMl(item) / 1000).toFixed(2);
                    const dueLabel = this.formatTime(item.time);
                    const body = now < scheduled
                        ? `Hydration reminder: drink ${amountLiters} L by ${dueLabel}.`
                        : `Hydration reminder: drink ${amountLiters} L now (due ${dueLabel}).`;

                    App.notifications.send('Water Reminder', {
                        body,
                        tag: 'water-reminder-' + reminderKey,
                        requireInteraction: true
                    });
                });
            },

            formatTime(timeString) {
                const [hoursRaw, minutesRaw] = String(timeString || '').split(':');
                const hours = parseInt(hoursRaw || '0', 10);
                const minutes = minutesRaw || '00';
                const suffix = hours >= 12 ? 'PM' : 'AM';
                const hour12 = hours % 12 || 12;
                return `${hour12}:${minutes} ${suffix}`;
            },

            async markMissed(item, options = {}) {
                const silent = options.silent === true;
                const scheduleItemId = item?.id;
                if (!scheduleItemId || !this.activePlan?.id) {
                    return;
                }

                if (this.missInFlight[scheduleItemId]) {
                    return;
                }

                this.missInFlight[scheduleItemId] = true;
                try {
                    const response = await App.api.post('api/habits.php?action=mark_reminder_missed', {
                        planId: this.activePlan.id,
                        scheduleItemId,
                        csrf_token: typeof CSRF_TOKEN !== 'undefined' ? CSRF_TOKEN : ''
                    });

                    if (response && response.success) {
                        item.missed = true;
                        delete this.reminderMinuteMap[scheduleItemId];
                        if (!silent) {
                            showToast(`Marked ${this.formatTime(item.time)} as missed.`, 'warning');
                        }
                    }
                } catch (error) {
                    if (DEBUG_MODE) {
                        console.warn('Failed to auto-mark missed water reminder:', error);
                    }
                } finally {
                    delete this.missInFlight[scheduleItemId];
                }
            },

            async markComplete(scheduleItemId) {
                if (!scheduleItemId || !this.activePlan?.id) {
                    return;
                }

                try {
                    await App.api.post('api/habits.php?action=complete_reminder', {
                        planId: this.activePlan.id,
                        scheduleItemId,
                        csrf_token: typeof CSRF_TOKEN !== 'undefined' ? CSRF_TOKEN : ''
                    });
                    delete this.reminderMinuteMap[scheduleItemId];
                } catch (error) {
                    if (DEBUG_MODE) {
                        console.warn('Failed to mark water reminder complete:', error);
                    }
                }
            },

            /**
             * Stop the notification system
             */
            stop() {
                if (this.checkInterval) {
                    clearInterval(this.checkInterval);
                    this.checkInterval = null;
                }
                this.activePlan = null;
                this.reminderMinuteMap = {};
                this.missInFlight = {};
                this.initialized = false;
            }
        },

        /**
         * Scheduler bootstrap module
         * Starts scheduler automatically once per browser session.
         */
        scheduler: {
            SESSION_KEY: 'lazyman_scheduler_bootstrap_done',
            bootstrapPromise: null,

            async bootstrapOnce() {
                if (typeof window === 'undefined' || typeof sessionStorage === 'undefined') {
                    return;
                }

                const params = new URLSearchParams(window.location.search);
                const page = (params.get('page') || 'dashboard').toLowerCase();
                if (page === 'login' || page === 'setup') {
                    return;
                }

                if (sessionStorage.getItem(this.SESSION_KEY) === '1') {
                    return;
                }

                if (this.bootstrapPromise) {
                    return this.bootstrapPromise;
                }

                this.bootstrapPromise = (async () => {
                    try {
                        await App.api.post('api/scheduler_bootstrap.php', {
                            csrf_token: typeof CSRF_TOKEN !== 'undefined' ? CSRF_TOKEN : ''
                        });
                    } catch (error) {
                        if (DEBUG_MODE) {
                            console.warn('Scheduler bootstrap failed:', error);
                        }
                    } finally {
                        sessionStorage.setItem(this.SESSION_KEY, '1');
                        this.bootstrapPromise = null;
                    }
                })();

                return this.bootstrapPromise;
            }
        },
        /**
         * Loading Module
         * Provides loading states, spinners, and skeleton screens
         */
        loading: {
            /**
             * Show spinner in an element
             * @param {string} elementId - Element ID or selector
             * @param {Object} options - Spinner options
             */
            show(elementId, options = {}) {
                const element = typeof elementId === 'string'
                    ? document.querySelector(elementId) || document.getElementById(elementId)
                    : elementId;

                if (!element) return null;

                // Check if already has spinner
                if (element.querySelector('.spinner, .loading-spinner')) {
                    return element.querySelector('.spinner, .loading-spinner');
                }

                const size = options.size || 'md'; // sm, md, lg
                const sizeMap = { sm: 'w-4 h-4', md: 'w-8 h-8', lg: 'w-12 h-12' };
                const containerClass = options.inline ? 'inline-flex' : 'flex';
                const alignClass = options.align || 'center';

                const spinner = document.createElement('div');
                spinner.className = `spinner loading-spinner ${containerClass} items-${alignClass} justify-${alignClass} gap-2`;
                spinner.innerHTML = `
                    <div class="${sizeMap[size]} border-2 border-gray-200 border-t-blue-500 rounded-full animate-spin"></div>
                    ${options.text ? `<span class="text-gray-500 text-sm">${options.text}</span>` : ''}
                `;

                if (options.overlay) {
                    const overlay = document.createElement('div');
                    overlay.className = 'absolute inset-0 bg-white/80 flex items-center justify-center z-10';
                    overlay.appendChild(spinner.cloneNode(true));
                    element.style.position = 'relative';
                    element.appendChild(overlay);
                    return overlay;
                }

                element.classList.add('relative', 'flex', 'items-center', 'justify-center');
                element.appendChild(spinner);
                return spinner;
            },

            /**
             * Hide spinner/loading in an element
             * @param {string} elementId - Element ID or selector
             */
            hide(elementId) {
                const element = typeof elementId === 'string'
                    ? document.querySelector(elementId) || document.getElementById(elementId)
                    : elementId;

                if (!element) return;

                const spinner = element.querySelector('.spinner, .loading-spinner, .loading-overlay');
                if (spinner) {
                    spinner.remove();
                }

                // Remove position classes if added
                if (element.classList.contains('relative')) {
                    element.classList.remove('relative', 'flex', 'items-center', 'justify-center');
                }
            },

            /**
             * Show button loading state
             * @param {HTMLElement} button - Button element
             * @param {string} loadingText - Text to show during loading
             */
            button(button, loadingText = 'Loading...') {
                if (!button) return;

                // Store original content
                if (!button.dataset.originalContent) {
                    button.dataset.originalContent = button.innerHTML;
                }

                button.disabled = true;
                button.innerHTML = `
                    <svg class="w-4 h-4 animate-spin mr-2" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    ${loadingText}
                `;
            },

            /**
             * Restore button to normal state
             * @param {HTMLElement} button - Button element
             */
            restoreButton(button) {
                if (!button || !button.dataset.originalContent) return;

                button.disabled = false;
                button.innerHTML = button.dataset.originalContent;
                delete button.dataset.originalContent;
            },

            /**
             * Show skeleton loading for tables
             * @param {string} tableId - Table element ID
             * @param {number} rows - Number of skeleton rows
             * @param {Array} columns - Column configuration
             */
            table(tableId, rows = 5, columns = [1, 2, 3, 4]) {
                const table = document.getElementById(tableId);
                if (!table) return;

                // Store original tbody
                const tbody = table.querySelector('tbody');
                if (!tbody) return;

                tbody.dataset.originalHTML = tbody.innerHTML;

                let html = '';
                for (let i = 0; i < rows; i++) {
                    html += '<tr>';
                    columns.forEach(col => {
                        html += `<td class="px-4 py-3"><div class="skeleton h-4 rounded" style="width: ${80 + Math.random() * 40}%"></div></td>`;
                    });
                    html += '</tr>';
                }
                tbody.innerHTML = html;
            },

            /**
             * Restore table from skeleton loading
             * @param {string} tableId - Table element ID
             */
            restoreTable(tableId) {
                const table = document.getElementById(tableId);
                if (!table) return;

                const tbody = table.querySelector('tbody');
                if (tbody && tbody.dataset.originalHTML) {
                    tbody.innerHTML = tbody.dataset.originalHTML;
                    delete tbody.dataset.originalHTML;
                }
            },

            /**
             * Show full page loading overlay
             * @param {string} message - Loading message
             */
            fullPage(message = 'Loading...') {
                // Check if already showing
                if (document.getElementById('app-loading-overlay')) {
                    return;
                }

                const overlay = document.createElement('div');
                overlay.id = 'app-loading-overlay';
                overlay.className = 'fixed inset-0 bg-white z-[9999] flex flex-col items-center justify-center';
                overlay.innerHTML = `
                    <div class="spinner w-12 h-12 border-4 border-gray-200 border-t-blue-600 rounded-full animate-spin mb-4"></div>
                    <p class="text-gray-600 text-lg">${message}</p>
                `;
                document.body.appendChild(overlay);
                document.body.style.overflow = 'hidden';
            },

            /**
             * Hide full page loading overlay
             */
            hideFullPage() {
                const overlay = document.getElementById('app-loading-overlay');
                if (overlay) {
                    overlay.remove();
                    document.body.style.overflow = '';
                }
            },

            /**
             * Show skeleton card
             * @param {string} containerId - Container element ID
             * @param {Object} options - Card options
             */
            card(containerId, options = {}) {
                const container = document.getElementById(containerId);
                if (!container) return;

                container.dataset.originalHTML = container.innerHTML;

                const { titleLines = 1, bodyLines = 3, showImage = false } = options;

                let html = '';
                if (showImage) {
                    html += '<div class="skeleton h-48 rounded-lg mb-4"></div>';
                }
                for (let i = 0; i < titleLines; i++) {
                    html += '<div class="skeleton h-6 rounded mb-2" style="width: 60%"></div>';
                }
                for (let i = 0; i < bodyLines; i++) {
                    html += `<div class="skeleton h-4 rounded mb-1" style="width: ${80 + Math.random() * 20}%"></div>`;
                }

                container.innerHTML = html;
            },

            /**
             * Restore container from skeleton
             * @param {string} containerId - Container element ID
             */
            restore(containerId) {
                const container = document.getElementById(containerId);
                if (!container) return;

                if (container.dataset.originalHTML) {
                    container.innerHTML = container.dataset.originalHTML;
                    delete container.dataset.originalHTML;
                }
            }
        },

        /**
         * Initialize the application
         */
        init() {
            if (_initialized) return;
            _initialized = true;

            if (DEBUG_MODE) {
                console.log('Application initialized');
            }

            // Close modal on escape
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') this.ui.closeModal();
            });

            // Initialize habit reminders on relevant pages
            const currentPage = window.location.search;
            if (currentPage.includes('page=habits') || currentPage.includes('page=dashboard')) {
                this.habits.initialize();
            }

            // Initialize task reminders on tasks page (separate from habit initialization)
            if (currentPage.includes('page=tasks') || currentPage.includes('page=dashboard')) {
                this.tasks.initialize();
            }

            // Keep scheduler active and hydration reminders running across pages.
            if (!currentPage.includes('page=login') && !currentPage.includes('page=setup')) {
                this.scheduler.bootstrapOnce();
                this.waterPlanNotifications.init();
            }
        },

        /**
         * Expose error handler for external use
         */
        handleError: _handleApiError
    };
})();

// Backward compatibility - expose global functions and modules
const api = App.api;
function showToast(message, type) { return App.ui.showToast(message, type); }
function openModal(content) { return App.ui.openModal(content); }
function closeModal() { return App.ui.closeModal(); }
function handleForm(formId, onSuccess) { return App.ui.handleForm(formId, onSuccess); }
function confirmAction(message, onConfirm) { return App.ui.confirmAction(message, onConfirm); }
function formatCurrency(amount, currency) { return App.format.currency(amount, currency); }
function formatDate(dateStr) { return App.format.date(dateStr); }
function timeAgo(dateStr) { return App.format.timeAgo(dateStr); }
function debounce(func, wait) { return App.debounce(func, wait); }

// Notification system backward compatibility
const notifications = App.notifications;
function checkNotificationPermission() { return App.notifications.checkPermission(); }
function requestNotificationPermission() { return App.notifications.requestPermission(); }
function showNotificationDeniedHelp() { return App.notifications.showDeniedHelp(); }
function sendNotification(title, options, forceToast) { return App.notifications.send(title, options, forceToast); }

// Loading module backward compatibility
const Loading = App.loading;
function showLoading(elementId, options) { return App.loading.show(elementId, options); }
function hideLoading(elementId) { return App.loading.hide(elementId); }
function setButtonLoading(button, text) { return App.loading.button(button, text); }
function restoreButton(button) { return App.loading.restoreButton(button); }
function showTableSkeleton(tableId, rows, columns) { return App.loading.table(tableId, rows, columns); }
function restoreTable(tableId) { return App.loading.restoreTable(tableId); }
function showFullPageLoading(message) { return App.loading.fullPage(message); }
function hideFullPageLoading() { return App.loading.hideFullPage(); }

const POMODORO_MUSIC_SELECTED_KEY = 'pomodoroMusicId';
const POMODORO_MUSIC_PLAYING_KEY = 'pomodoroMusicPlaying';
const POMODORO_MUSIC_TIME_KEY = 'pomodoroMusicTime';
const POMODORO_MUSIC_VOLUME_KEY = 'pomodoroMusicVolume';
const POMODORO_MUSIC_LOOP_KEY = 'pomodoroMusicLoop';
const POMODORO_MUSIC_AUTOPLAY_KEY = 'pomodoroMusicAuto';
const POMODORO_MUSIC_TRACK_CACHE_KEY = 'pomodoroMusicTracksCache';
const POMODORO_MUSIC_TRACK_ORDER_KEY = 'pomodoroMusicTrackOrder';
const POMODORO_MUSIC_MANUAL_PAUSE_KEY = 'pomodoroMusicManualPause';
const POMODORO_STATE_KEY = 'pomodoroStateV2';
const POMODORO_OVERLAY_OFFSET_X_KEY = 'pomodoroOverlayOffsetX';
const POMODORO_OVERLAY_OFFSET_Y_KEY = 'pomodoroOverlayOffsetY';

function loadPomodoroStateSafe() {
    try {
        const raw = localStorage.getItem(POMODORO_STATE_KEY);
        if (!raw) return {};
        return JSON.parse(raw) || {};
    } catch (error) {
        return {};
    }
}

function loadPomodoroTrackCache() {
    try {
        const raw = localStorage.getItem(POMODORO_MUSIC_TRACK_CACHE_KEY);
        if (!raw) return {};
        const parsed = JSON.parse(raw);
        return parsed && typeof parsed === 'object' ? parsed : {};
    } catch (error) {
        return {};
    }
}

function loadPomodoroTrackOrder() {
    try {
        const raw = localStorage.getItem(POMODORO_MUSIC_TRACK_ORDER_KEY);
        if (raw) {
            const parsed = JSON.parse(raw);
            if (Array.isArray(parsed)) {
                return parsed.filter((item) => typeof item === 'string' && item !== '');
            }
        }
    } catch (error) {
    }
    const cache = loadPomodoroTrackCache();
    return Object.keys(cache);
}

function resolvePomodoroTrackLabel(trackId) {
    if (!trackId) {
        return 'No track selected';
    }
    const cache = loadPomodoroTrackCache();
    return cache[trackId] || 'Selected track';
}

function switchPomodoroTrack(audio, direction) {
    const order = loadPomodoroTrackOrder();
    if (!order.length) {
        return '';
    }
    const currentTrackId = localStorage.getItem(POMODORO_MUSIC_SELECTED_KEY) || '';
    let index = order.indexOf(currentTrackId);
    if (index < 0) {
        index = 0;
    } else {
        const delta = direction < 0 ? -1 : 1;
        index = (index + delta + order.length) % order.length;
    }
    const nextTrackId = order[index] || '';
    if (!nextTrackId) {
        return '';
    }
    localStorage.setItem(POMODORO_MUSIC_SELECTED_KEY, nextTrackId);
    localStorage.setItem(POMODORO_MUSIC_TIME_KEY, '0');
    ensurePomodoroAudioSource(audio);
    return nextTrackId;
}

function applyPomodoroOverlayPosition(overlay) {
    if (!overlay) {
        return;
    }
    const x = parseInt(localStorage.getItem(POMODORO_OVERLAY_OFFSET_X_KEY) || '0', 10);
    const y = parseInt(localStorage.getItem(POMODORO_OVERLAY_OFFSET_Y_KEY) || '0', 10);
    const offsetX = Number.isFinite(x) ? x : 0;
    const offsetY = Number.isFinite(y) ? y : 0;
    overlay.style.transform = `translate(${offsetX}px, ${offsetY}px)`;
}

function movePomodoroOverlayBy(deltaX, deltaY) {
    const currentX = parseInt(localStorage.getItem(POMODORO_OVERLAY_OFFSET_X_KEY) || '0', 10);
    const currentY = parseInt(localStorage.getItem(POMODORO_OVERLAY_OFFSET_Y_KEY) || '0', 10);
    const nextX = (Number.isFinite(currentX) ? currentX : 0) + deltaX;
    const nextY = (Number.isFinite(currentY) ? currentY : 0) + deltaY;
    const clampedX = Math.max(-520, Math.min(120, nextX));
    const clampedY = Math.max(-420, Math.min(120, nextY));
    localStorage.setItem(POMODORO_OVERLAY_OFFSET_X_KEY, String(clampedX));
    localStorage.setItem(POMODORO_OVERLAY_OFFSET_Y_KEY, String(clampedY));
}

function ensurePomodoroAudioSource(audio) {
    if (!audio) return '';
    const trackId = localStorage.getItem(POMODORO_MUSIC_SELECTED_KEY) || '';
    if (!trackId) {
        if (audio.getAttribute('src')) {
            audio.removeAttribute('src');
            audio.load();
        }
        audio.dataset.trackId = '';
        return '';
    }
    if (audio.dataset.trackId === trackId && audio.getAttribute('src')) {
        return trackId;
    }
    audio.src = `api/pomodoro.php?action=music_download&id=${encodeURIComponent(trackId)}`;
    audio.dataset.trackId = trackId;
    return trackId;
}

function initPomodoroAudioPersistence() {
    const audio = document.getElementById('pomodoro-audio');
    if (!audio) return;

    const savedVolume = parseFloat(localStorage.getItem(POMODORO_MUSIC_VOLUME_KEY) || '0.6');
    const savedLoop = localStorage.getItem(POMODORO_MUSIC_LOOP_KEY) === '1';
    const savedTime = parseFloat(localStorage.getItem(POMODORO_MUSIC_TIME_KEY) || '0');
    const shouldPlay = localStorage.getItem(POMODORO_MUSIC_PLAYING_KEY) === '1';
    let isNavigatingAway = false;

    const attemptResumePlayback = () => {
        if (!shouldPlay) {
            return;
        }
        if (localStorage.getItem(POMODORO_MUSIC_MANUAL_PAUSE_KEY) === '1') {
            return;
        }
        const state = loadPomodoroStateSafe();
        if (state && state.running === false) {
            return;
        }
        audio.play().catch(() => {});
    };

    ensurePomodoroAudioSource(audio);

    if (Number.isFinite(savedVolume)) {
        audio.volume = Math.min(1, Math.max(0, savedVolume));
    }
    audio.loop = savedLoop;

    audio.addEventListener('loadedmetadata', () => {
        if (Number.isFinite(savedTime) && savedTime > 0) {
            audio.currentTime = Math.min(savedTime, Math.max(0, audio.duration - 1));
        }
        attemptResumePlayback();
    }, { once: true });
    audio.addEventListener('canplay', attemptResumePlayback, { once: true });

    if (audio.readyState >= 1) {
        if (Number.isFinite(savedTime) && savedTime > 0) {
            audio.currentTime = Math.min(savedTime, Math.max(0, (audio.duration || savedTime + 1) - 1));
        }
        attemptResumePlayback();
    }

    audio.addEventListener('timeupdate', () => {
        localStorage.setItem(POMODORO_MUSIC_TIME_KEY, String(audio.currentTime || 0));
    });
    audio.addEventListener('play', () => {
        localStorage.setItem(POMODORO_MUSIC_PLAYING_KEY, '1');
    });
    audio.addEventListener('pause', () => {
        if (isNavigatingAway) {
            return;
        }
        localStorage.setItem(POMODORO_MUSIC_PLAYING_KEY, '0');
    });
    audio.addEventListener('ended', () => {
        localStorage.setItem(POMODORO_MUSIC_PLAYING_KEY, '0');
    });

    window.addEventListener('beforeunload', () => {
        isNavigatingAway = true;
        if (!audio.paused) {
            localStorage.setItem(POMODORO_MUSIC_PLAYING_KEY, '1');
            localStorage.setItem(POMODORO_MUSIC_TIME_KEY, String(audio.currentTime || 0));
        }
    });

    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) {
            attemptResumePlayback();
        }
    });
}

function renderPomodoroOverlayPlayButton(button, isPlaying) {
    if (!button) return;
    if (isPlaying) {
        button.innerHTML = `
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 9v6m4-6v6"></path>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
        `;
        button.setAttribute('title', 'Pause');
        button.setAttribute('aria-label', 'Pause music');
        return;
    }
    button.innerHTML = `
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-4.197-2.432A1 1 0 009 9.603v4.794a1 1 0 001.555.832l4.197-2.432a1 1 0 000-1.664z"></path>
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
        </svg>
    `;
    button.setAttribute('title', 'Play');
    button.setAttribute('aria-label', 'Play music');
}

function updatePomodoroMusicOverlay() {
    const overlay = document.getElementById('pomodoro-music-overlay');
    const trackEl = document.getElementById('pomodoro-overlay-track');
    const statusEl = document.getElementById('pomodoro-overlay-status');
    const clockEl = document.getElementById('pomodoro-overlay-clock');
    const prevBtn = document.getElementById('pomodoro-overlay-prev');
    const playBtn = document.getElementById('pomodoro-overlay-play');
    const nextBtn = document.getElementById('pomodoro-overlay-next');
    const volumeInput = document.getElementById('pomodoro-overlay-volume');
    const timerToggleBtn = document.getElementById('pomodoro-overlay-timer-toggle');
    const moveUpBtn = document.getElementById('pomodoro-overlay-move-up');
    const moveLeftBtn = document.getElementById('pomodoro-overlay-move-left');
    const moveRightBtn = document.getElementById('pomodoro-overlay-move-right');
    const moveDownBtn = document.getElementById('pomodoro-overlay-move-down');
    const moveResetBtn = document.getElementById('pomodoro-overlay-move-reset');
    const audio = document.getElementById('pomodoro-audio');
    if (!overlay || !trackEl || !statusEl || !clockEl || !prevBtn || !playBtn || !nextBtn || !volumeInput || !timerToggleBtn || !moveUpBtn || !moveLeftBtn || !moveRightBtn || !moveDownBtn || !moveResetBtn || !audio) return;

    const state = loadPomodoroStateSafe();
    const trackId = localStorage.getItem(POMODORO_MUSIC_SELECTED_KEY) || '';
    const trackLabel = resolvePomodoroTrackLabel(trackId);
    const trackOrder = loadPomodoroTrackOrder();
    const volume = parseFloat(localStorage.getItem(POMODORO_MUSIC_VOLUME_KEY) || String(audio.volume || 0.6));
    const isRunning = !!state.running;
    const showOverlay = isRunning || state.phase === 'focus' || state.phase === 'break' || state.awaitingNextFocus === true;
    const secondsLeft = Number.isFinite(parseInt(state.secondsLeft, 10)) ? parseInt(state.secondsLeft, 10) : 25 * 60;
    const mins = Math.floor(secondsLeft / 60);
    const secs = Math.floor(secondsLeft % 60);

    overlay.classList.toggle('hidden', !showOverlay);
    trackEl.textContent = trackLabel;
    prevBtn.disabled = trackOrder.length < 2;
    nextBtn.disabled = trackOrder.length < 2;
    clockEl.textContent = `${String(mins).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
    if (state.phase === 'focus') {
        statusEl.textContent = isRunning ? 'Focus' : 'Focus Paused';
    } else if (state.phase === 'break') {
        statusEl.textContent = isRunning ? 'Break' : 'Break Paused';
    } else {
        statusEl.textContent = state.awaitingNextFocus ? 'Break Complete' : 'Ready';
    }
    if (isRunning) {
        timerToggleBtn.textContent = 'Pause';
    } else if (state.phase === 'idle') {
        timerToggleBtn.textContent = state.awaitingNextFocus ? 'Resume' : 'Start';
    } else if (state.phase === 'break') {
        timerToggleBtn.textContent = 'Resume Break';
    } else {
        timerToggleBtn.textContent = 'Resume';
    }
    if (Number.isFinite(volume)) {
        volumeInput.value = String(Math.min(1, Math.max(0, volume)));
    }
    renderPomodoroOverlayPlayButton(playBtn, !audio.paused);
    applyPomodoroOverlayPosition(overlay);
}

function initPomodoroMusicOverlay() {
    const overlay = document.getElementById('pomodoro-music-overlay');
    const prevBtn = document.getElementById('pomodoro-overlay-prev');
    const playBtn = document.getElementById('pomodoro-overlay-play');
    const nextBtn = document.getElementById('pomodoro-overlay-next');
    const stopBtn = document.getElementById('pomodoro-overlay-stop');
    const volumeInput = document.getElementById('pomodoro-overlay-volume');
    const timerToggleBtn = document.getElementById('pomodoro-overlay-timer-toggle');
    const timerResetBtn = document.getElementById('pomodoro-overlay-timer-reset');
    const moveUpBtn = document.getElementById('pomodoro-overlay-move-up');
    const moveLeftBtn = document.getElementById('pomodoro-overlay-move-left');
    const moveRightBtn = document.getElementById('pomodoro-overlay-move-right');
    const moveDownBtn = document.getElementById('pomodoro-overlay-move-down');
    const moveResetBtn = document.getElementById('pomodoro-overlay-move-reset');
    const audio = document.getElementById('pomodoro-audio');
    if (!overlay || !prevBtn || !playBtn || !nextBtn || !stopBtn || !volumeInput || !timerToggleBtn || !timerResetBtn || !moveUpBtn || !moveLeftBtn || !moveRightBtn || !moveDownBtn || !moveResetBtn || !audio) return;

    if (overlay.dataset.bound === '1') {
        updatePomodoroMusicOverlay();
        return;
    }
    overlay.dataset.bound = '1';

    const persistedVolume = parseFloat(localStorage.getItem(POMODORO_MUSIC_VOLUME_KEY) || '0.6');
    if (Number.isFinite(persistedVolume)) {
        const normalized = Math.min(1, Math.max(0, persistedVolume));
        audio.volume = normalized;
        volumeInput.value = String(normalized);
    }

    playBtn.addEventListener('click', async () => {
        const trackId = ensurePomodoroAudioSource(audio);
        if (!trackId) {
            if (typeof showToast === 'function') {
                showToast('Select a track on Pomodoro page first', 'info');
            }
            updatePomodoroMusicOverlay();
            return;
        }
        if (audio.paused) {
            try {
                await audio.play();
                localStorage.setItem(POMODORO_MUSIC_MANUAL_PAUSE_KEY, '0');
            } catch (error) {
                if (typeof showToast === 'function') {
                    showToast('Playback failed. Interact with the page and try again.', 'warning');
                }
            }
        } else {
            localStorage.setItem(POMODORO_MUSIC_MANUAL_PAUSE_KEY, '1');
            audio.pause();
        }
        updatePomodoroMusicOverlay();
    });

    prevBtn.addEventListener('click', async () => {
        const trackId = switchPomodoroTrack(audio, -1);
        if (!trackId) {
            if (typeof showToast === 'function') {
                showToast('No additional tracks in library', 'info');
            }
            updatePomodoroMusicOverlay();
            return;
        }
        const state = loadPomodoroStateSafe();
        const shouldPlay = localStorage.getItem(POMODORO_MUSIC_PLAYING_KEY) === '1' || (!!state.running && localStorage.getItem(POMODORO_MUSIC_AUTOPLAY_KEY) === '1');
        if (shouldPlay) {
            try {
                await audio.play();
                localStorage.setItem(POMODORO_MUSIC_MANUAL_PAUSE_KEY, '0');
            } catch (error) {
            }
        }
        updatePomodoroMusicOverlay();
    });

    nextBtn.addEventListener('click', async () => {
        const trackId = switchPomodoroTrack(audio, 1);
        if (!trackId) {
            if (typeof showToast === 'function') {
                showToast('No additional tracks in library', 'info');
            }
            updatePomodoroMusicOverlay();
            return;
        }
        const state = loadPomodoroStateSafe();
        const shouldPlay = localStorage.getItem(POMODORO_MUSIC_PLAYING_KEY) === '1' || (!!state.running && localStorage.getItem(POMODORO_MUSIC_AUTOPLAY_KEY) === '1');
        if (shouldPlay) {
            try {
                await audio.play();
                localStorage.setItem(POMODORO_MUSIC_MANUAL_PAUSE_KEY, '0');
            } catch (error) {
            }
        }
        updatePomodoroMusicOverlay();
    });

    stopBtn.addEventListener('click', () => {
        if (!audio.paused) {
            audio.pause();
        }
        localStorage.setItem(POMODORO_MUSIC_MANUAL_PAUSE_KEY, '1');
        localStorage.setItem(POMODORO_MUSIC_PLAYING_KEY, '0');
        updatePomodoroMusicOverlay();
    });

    timerToggleBtn.addEventListener('click', () => {
        const state = PomodoroShared.toggle();
        syncPomodoroAudioPlayback(state);
        updatePomodoroMusicOverlay();
    });

    timerResetBtn.addEventListener('click', () => {
        const state = PomodoroShared.reset();
        syncPomodoroAudioPlayback(state);
        updatePomodoroMusicOverlay();
    });

    moveUpBtn.addEventListener('click', () => {
        movePomodoroOverlayBy(0, -40);
        updatePomodoroMusicOverlay();
    });
    moveLeftBtn.addEventListener('click', () => {
        movePomodoroOverlayBy(-40, 0);
        updatePomodoroMusicOverlay();
    });
    moveRightBtn.addEventListener('click', () => {
        movePomodoroOverlayBy(40, 0);
        updatePomodoroMusicOverlay();
    });
    moveDownBtn.addEventListener('click', () => {
        movePomodoroOverlayBy(0, 40);
        updatePomodoroMusicOverlay();
    });
    moveResetBtn.addEventListener('click', () => {
        localStorage.setItem(POMODORO_OVERLAY_OFFSET_X_KEY, '0');
        localStorage.setItem(POMODORO_OVERLAY_OFFSET_Y_KEY, '0');
        updatePomodoroMusicOverlay();
    });

    volumeInput.addEventListener('input', () => {
        const value = parseFloat(volumeInput.value || '0.6');
        if (!Number.isFinite(value)) return;
        audio.volume = Math.min(1, Math.max(0, value));
        localStorage.setItem(POMODORO_MUSIC_VOLUME_KEY, String(audio.volume));
    });

    audio.addEventListener('play', updatePomodoroMusicOverlay);
    audio.addEventListener('pause', updatePomodoroMusicOverlay);
    audio.addEventListener('loadedmetadata', updatePomodoroMusicOverlay);
    audio.addEventListener('emptied', updatePomodoroMusicOverlay);
    audio.addEventListener('volumechange', () => {
        localStorage.setItem(POMODORO_MUSIC_VOLUME_KEY, String(audio.volume));
        updatePomodoroMusicOverlay();
    });

    window.addEventListener('storage', (event) => {
        if (!event.key || event.key.startsWith('pomodoro')) {
            ensurePomodoroAudioSource(audio);
            updatePomodoroMusicOverlay();
        }
    });

    if (!window.__pomodoroOverlayInterval) {
        window.__pomodoroOverlayInterval = setInterval(updatePomodoroMusicOverlay, 1000);
    }

    ensurePomodoroAudioSource(audio);
    updatePomodoroMusicOverlay();
}



const PomodoroShared = (() => {
    const STATE_KEY = 'pomodoroStateV2';
    const BREAK_KEY = 'pomodoroBreakMinutes';
    const DEFAULT_FOCUS = 25;
    const DEFAULT_BREAK = 5;

    function getBreakMinutes() {
        const saved = parseInt(localStorage.getItem(BREAK_KEY) || String(DEFAULT_BREAK), 10);
        return Number.isFinite(saved) && saved > 0 ? saved : DEFAULT_BREAK;
    }

    function normalizeState(raw) {
        const focusMinutes = parseInt(raw?.focusMinutes || DEFAULT_FOCUS, 10) || DEFAULT_FOCUS;
        const breakMinutes = parseInt(raw?.breakMinutes || getBreakMinutes(), 10) || getBreakMinutes();
        return {
            phase: raw?.phase || 'idle',
            running: !!raw?.running,
            secondsLeft: parseInt(raw?.secondsLeft || focusMinutes * 60, 10) || focusMinutes * 60,
            focusMinutes,
            breakMinutes,
            lastTick: raw?.lastTick || null,
            awaitingNextFocus: !!raw?.awaitingNextFocus
        };
    }

    function loadState() {
        const saved = localStorage.getItem(STATE_KEY);
        if (saved) {
            try {
                const parsed = JSON.parse(saved);
                return normalizeState(parsed);
            } catch (e) {
                return normalizeState(null);
            }
        }
        return normalizeState(null);
    }

    function saveState(state) {
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

    function notify(title, body) {
        if (window.App && App.notifications && typeof App.notifications.send === 'function') {
            App.notifications.send(title, { body });
            return;
        }
        if (typeof showToast === 'function') {
            showToast(body || title, 'success');
        }
    }

    async function saveSession(state) {
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
        } catch (e) {
            console.warn('Failed to save pomodoro session:', e);
        }
    }

    function completeFocus(state, silent) {
        if (!silent) {
            notify('Pomodoro Complete', 'Time for a break.');
        }
        saveSession(state);
        state.phase = 'break';
        state.secondsLeft = state.breakMinutes * 60;
        state.running = true;
        state.lastTick = Date.now();
        state.awaitingNextFocus = false;
    }

    function completeBreak(state, silent) {
        if (!silent) {
            notify('Break Complete', 'Ready for the next focus session.');
        }
        state.phase = 'idle';
        state.awaitingNextFocus = true;
        state.running = false;
        state.secondsLeft = state.focusMinutes * 60;
        state.lastTick = null;
    }

    function applyElapsed(state, elapsedSeconds, silent) {
        let remaining = elapsedSeconds;
        while (remaining > 0 && state.running) {
            if (remaining < state.secondsLeft) {
                state.secondsLeft -= remaining;
                remaining = 0;
            } else {
                remaining -= state.secondsLeft;
                state.secondsLeft = 0;
                if (state.phase === 'focus') {
                    completeFocus(state, silent);
                } else if (state.phase === 'break') {
                    completeBreak(state, silent);
                } else {
                    state.running = false;
                    remaining = 0;
                }
            }
        }
    }

    function syncElapsed(state, silent) {
        if (!state.running || !state.lastTick) return state;
        const now = Date.now();
        const elapsed = Math.floor((now - state.lastTick) / 1000);
        if (elapsed <= 0) return state;
        applyElapsed(state, elapsed, silent);
        state.lastTick = state.running ? now : null;
        return state;
    }

    function startTimer(state) {
        state.running = true;
        state.lastTick = Date.now();
    }

    function startFocus(state) {
        state.phase = 'focus';
        state.awaitingNextFocus = false;
        state.secondsLeft = state.focusMinutes * 60;
        startTimer(state);
    }

    function toggle() {
        let state = loadState();
        if (state.running) {
            state.running = false;
            state.lastTick = null;
        } else {
            if (state.phase === 'idle') {
                startFocus(state);
            } else {
                startTimer(state);
            }
        }
        saveState(state);
        return state;
    }

    function reset() {
        const state = loadState();
        state.phase = 'idle';
        state.awaitingNextFocus = false;
        state.running = false;
        state.secondsLeft = state.focusMinutes * 60;
        state.lastTick = null;
        saveState(state);
        return state;
    }

    function snapshot(silent = true) {
        const state = loadState();
        syncElapsed(state, silent);
        saveState(state);
        return state;
    }

    return {
        toggle,
        reset,
        snapshot
    };
})();

function syncPomodoroAudioPlayback(state) {
    const audio = document.getElementById('pomodoro-audio');
    if (!audio) return;
    const autoplay = localStorage.getItem(POMODORO_MUSIC_AUTOPLAY_KEY) === '1';
    const manualPause = localStorage.getItem(POMODORO_MUSIC_MANUAL_PAUSE_KEY) === '1';
    const trackId = ensurePomodoroAudioSource(audio);
    if (autoplay && state.running && trackId && !manualPause) {
        audio.play().catch(() => {});
    } else if (!state.running && !audio.paused) {
        audio.pause();
    }
    updatePomodoroMusicOverlay();
}

function initDashboardPomodoro() {
    const params = new URLSearchParams(window.location.search);
    const pageName = params.get('page') || 'dashboard';
    if (pageName !== 'dashboard') return;

    const display = document.getElementById('pomodoro-display');
    const statusEl = document.getElementById('pomodoro-status-mini');
    const ring = document.getElementById('pomodoro-ring');
    const btn = document.getElementById('pomodoro-btn');

    if (!display || !btn) return;

    function formatClock(seconds) {
        const mins = Math.floor(seconds / 60);
        const secs = Math.floor(seconds % 60);
        return `${String(mins).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
    }

    function updateRing(state) {
        if (!ring) return;
        const radius = parseFloat(ring.getAttribute('r') || '52');
        const circumference = 2 * Math.PI * radius;
        const totalSeconds = state.phase === 'break'
            ? state.breakMinutes * 60
            : state.focusMinutes * 60;
        const progress = totalSeconds > 0 ? (totalSeconds - state.secondsLeft) / totalSeconds : 0;
        ring.style.strokeDasharray = String(circumference);
        ring.style.strokeDashoffset = String(Math.max(0, circumference * (1 - progress)));
    }

    function updateDisplay() {
        const state = PomodoroShared.snapshot();
        display.textContent = formatClock(state.secondsLeft);
        if (statusEl) {
            if (state.phase === 'focus') {
                statusEl.textContent = state.running ? 'Focus' : 'Focus Paused';
            } else if (state.phase === 'break') {
                statusEl.textContent = state.running ? 'Break' : 'Break Paused';
            } else {
                statusEl.textContent = state.awaitingNextFocus ? 'Break Complete' : 'Ready';
            }
        }
        if (state.running) {
            btn.textContent = 'Pause';
        } else if (state.phase === 'idle') {
            btn.textContent = state.awaitingNextFocus ? 'Resume' : 'Start';
        } else if (state.phase === 'break') {
            btn.textContent = 'Resume Break';
        } else {
            btn.textContent = 'Resume';
        }
        updateRing(state);
        syncPomodoroAudioPlayback(state);
    }

    window.togglePomodoro = function () {
        PomodoroShared.toggle();
        updateDisplay();
    };
    window.resetPomodoro = function () {
        PomodoroShared.reset();
        updateDisplay();
    };

    updateDisplay();
    setInterval(updateDisplay, 1000);
}

function initGlobalPomodoroEngine() {
    const tick = () => {
        const state = PomodoroShared.snapshot();
        syncPomodoroAudioPlayback(state);
        updatePomodoroMusicOverlay();
    };
    tick();
    if (!window.__pomodoroGlobalTickInterval) {
        window.__pomodoroGlobalTickInterval = setInterval(tick, 1000);
    }
    window.addEventListener('storage', (event) => {
        if (!event.key || event.key.startsWith('pomodoro')) {
            tick();
        }
    });
}

// Check for toast message in URL parameters
function checkForToastMessage() {
    const params = new URLSearchParams(window.location.search);
    const msg = params.get('msg');
    const type = params.get('type') || 'info';

    if (msg) {
        // Decode and show toast
        showToast(decodeURIComponent(msg), type);
        // Clean URL without reloading
        const url = new URL(window.location);
        url.searchParams.delete('msg');
        url.searchParams.delete('type');
        window.history.replaceState({}, '', url);
    }
}

function initSessionKeepalive() {
    const params = new URLSearchParams(window.location.search);
    const pageName = params.get('page') || 'dashboard';
    if (pageName === 'login' || pageName === 'setup') {
        return;
    }

    const ping = async () => {
        if (document.visibilityState !== 'visible') {
            return;
        }

        try {
            const status = await App.api.get('api/auth.php?action=status');
            if (status?.success && status?.data?.authenticated === false) {
                window.location.href = '?page=login&reason=session_expired';
            }
        } catch (error) {
            // Existing global API handlers manage redirects for hard auth failures.
            console.warn('Session keepalive failed:', error);
        }
    };

    // Keep the session active during long-running interactive workflows.
    setInterval(ping, 5 * 60 * 1000);
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') {
            ping();
        }
    });
}

// Initialize on DOM ready
document.addEventListener('DOMContentLoaded', () => {
    App.init();
    initPomodoroAudioPersistence();
    initPomodoroMusicOverlay();
    initGlobalPomodoroEngine();
    initDashboardPomodoro();
    checkForToastMessage();
    initSessionKeepalive();
});

