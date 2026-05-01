/**
 * LazyMan Tools - Mobile JavaScript Framework
 *
 * Touch-optimized mobile interactions using Module Pattern
 * Provides navigation, UI utilities, gesture system, and task management
 *
 * @version 1.0.0
 */

// Notification icon builder for mobile views. Same shape as the desktop helper
// in assets/js/app.js — produces a colored circle + glyph PNG data URL so each
// notification source is visually distinct in the OS notification tray.
window.NOTIFICATION_TYPE_COLORS_MOBILE = {
    task: '#2563eb', habit: '#16a34a', pomodoro: '#dc2626',
    water: '#0ea5e9', reminder: '#f59e0b', ai: '#7c3aed', general: '#374151'
};
window.NOTIFICATION_TYPE_GLYPHS_MOBILE = {
    task: '✓', habit: '↻', pomodoro: '⏱',
    water: '☀', reminder: '⏰', ai: '✨', general: '•'
};
window.buildMobileNotificationIcon = function(type, color) {
    try {
        const t = String(type || 'general').toLowerCase();
        const bg = (color && /^#?[0-9a-fA-F]{3,8}$/.test(color))
            ? (color.startsWith('#') ? color : '#' + color)
            : (window.NOTIFICATION_TYPE_COLORS_MOBILE[t] || window.NOTIFICATION_TYPE_COLORS_MOBILE.general);
        const glyph = window.NOTIFICATION_TYPE_GLYPHS_MOBILE[t] || window.NOTIFICATION_TYPE_GLYPHS_MOBILE.general;
        const SIZE = 128;
        const canvas = document.createElement('canvas');
        canvas.width = SIZE; canvas.height = SIZE;
        const ctx = canvas.getContext('2d');
        ctx.fillStyle = bg;
        ctx.beginPath();
        ctx.arc(SIZE / 2, SIZE / 2, SIZE / 2 - 4, 0, Math.PI * 2);
        ctx.fill();
        ctx.fillStyle = '#ffffff';
        ctx.font = 'bold 76px system-ui, -apple-system, "Segoe UI Emoji", Arial, sans-serif';
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        ctx.fillText(glyph, SIZE / 2, SIZE / 2 + 4);
        return canvas.toDataURL('image/png');
    } catch (_) { return ''; }
};

// Ensure mobile shared stylesheet and saved theme are applied on every page.
(function bootstrapMobileTheme() {
    try {
        const path = window.location.pathname || '';
        const mobileSegment = '/mobile/';
        const segmentIndex = path.toLowerCase().indexOf(mobileSegment);
        const basePath = segmentIndex >= 0 ? path.substring(0, segmentIndex) : '';
        const cssHref = `${window.location.origin}${basePath}/mobile/assets/css/mobile.css?v=20260226`;

        if (!document.querySelector('link[data-mobile-core-css="1"]')) {
            const link = document.createElement('link');
            link.rel = 'stylesheet';
            link.href = cssHref;
            link.setAttribute('data-mobile-core-css', '1');
            document.head.appendChild(link);
        }

        // Default mode is always light unless user explicitly saved dark.
        const savedTheme = localStorage.getItem('mobile-theme');
        const useDark = savedTheme === 'dark';
        document.documentElement.classList.toggle('dark', useDark);
        document.documentElement.classList.toggle('light', !useDark);
    } catch (error) {
        console.warn('Failed to bootstrap mobile theme:', error);
    }
})();

function resolveCanonicalAppUrl() {
    const origin = window.location.origin || '';
    const rawBase = (typeof APP_URL !== 'undefined' && typeof APP_URL === 'string' && APP_URL.trim() !== '')
        ? APP_URL.trim()
        : origin;

    try {
        const parsed = new URL(rawBase, origin || undefined);
        let path = (parsed.pathname || '').replace(/\/+$/, '');
        path = path.replace(/\/mobile(?:\/index\.php)?$/i, '');
        if (path === '/') {
            path = '';
        }
        return `${parsed.origin}${path}`;
    } catch (error) {
        const fallback = String(rawBase || origin).replace(/\/+$/, '');
        return fallback.replace(/\/mobile(?:\/index\.php)?$/i, '');
    }
}

function getResponsePreview(text, maxLength = 180) {
    if (!text) return '';
    return String(text).replace(/\s+/g, ' ').trim().slice(0, maxLength);
}

// ============================================
// App API Helper (Mobile Version)
// ============================================
// Lightweight API helper for mobile - mirrors main app.js API
const App = {
    api: {
        async request(endpoint, options = {}) {
            const headers = {
                'X-CSRF-Token': typeof CSRF_TOKEN !== 'undefined' ? CSRF_TOKEN : '',
                ...options.headers
            };

            if (!(options.body instanceof FormData)) {
                headers['Content-Type'] = headers['Content-Type'] || 'application/json';
            }

            // Ensure endpoint doesn't start with a slash, then resolve against canonical app root.
            // This prevents accidental /mobile/api/* calls when a page computes APP_URL incorrectly.
            const cleanEndpoint = endpoint.startsWith('/') ? endpoint.substring(1) : endpoint;
            const baseUrl = resolveCanonicalAppUrl();
            const isAbsoluteUrl = /^https?:\/\//i.test(cleanEndpoint);
            const url = isAbsoluteUrl ? cleanEndpoint : `${baseUrl}/${cleanEndpoint}`;

            const fetchOptions = {
                ...options,
                headers: headers
            };

            const response = await fetch(url, fetchOptions);

            if (!response.ok) {
                const error = new Error(`HTTP ${response.status} for ${url}`);
                error.status = response.status;
                error.url = url;
                const contentType = response.headers.get('content-type');
                if (contentType && contentType.includes('application/json')) {
                    error.response = await response.json().catch(() => ({}));
                } else {
                    const text = await response.text().catch(() => '');
                    error.response = { message: getResponsePreview(text), raw: text };
                }
                throw error;
            }

            const contentType = response.headers.get('content-type');
            if (!contentType || !contentType.includes('application/json')) {
                const text = await response.text().catch(() => '');
                const preview = getResponsePreview(text);
                const error = new Error(`Server returned non-JSON response for ${url} (status ${response.status}): ${preview}`);
                error.status = response.status;
                error.url = url;
                error.response = { message: preview, raw: text };
                throw error;
            }

            return response.json().catch(() => {
                const error = new Error(`Failed to parse JSON response from ${url}`);
                error.status = response.status;
                error.url = url;
                throw error;
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
            return this.request(endpoint, {
                method: 'DELETE'
            });
        }
    }
};

const Mobile = (function() {
    'use strict';

    // ============================================
    // Private State
    // ============================================

    let _initialized = false;
    let _touchStartX = 0;
    let _touchStartY = 0;
    let _currentSwipeElement = null;
    let _debounceTimer = null;
    let _isLoading = false;
    let _sessionKeepaliveTimer = null;
    let _habitReminderTimer = null;
    const FLASH_TOAST_KEY = 'mobile-flash-toast';
    const POMODORO_STATE_KEY = 'pomodoroStateV2';
    const POMODORO_MUSIC_ID_KEY = 'pomodoroMusicId';
    const POMODORO_MUSIC_PLAYING_KEY = 'pomodoroMusicPlaying';
    const POMODORO_MUSIC_TIME_KEY = 'pomodoroMusicTime';
    const POMODORO_MUSIC_VOLUME_KEY = 'pomodoroMusicVolume';
    const POMODORO_MUSIC_LOOP_KEY = 'pomodoroMusicLoop';
    const POMODORO_MUSIC_AUTO_KEY = 'pomodoroMusicAuto';
    const POMODORO_MUSIC_TRACK_CACHE_KEY = 'pomodoroMusicTracksCache';
    const POMODORO_MUSIC_TRACK_ORDER_KEY = 'pomodoroMusicTrackOrder';
    const POMODORO_MUSIC_MANUAL_PAUSE_KEY = 'pomodoroMusicManualPause';
    const MOBILE_POMODORO_OVERLAY_HIDDEN_KEY = 'mobilePomodoroOverlayHidden';
    const MOBILE_POMODORO_OVERLAY_OFFSET_X_KEY = 'mobilePomodoroOverlayOffsetX';
    const MOBILE_POMODORO_OVERLAY_OFFSET_Y_KEY = 'mobilePomodoroOverlayOffsetY';

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
                    window.location.href = '?page=login&error=session_expired';
                }
            } catch (error) {
                console.warn('Mobile session keepalive failed:', error);
            }
        };

        if (_sessionKeepaliveTimer !== null) {
            clearInterval(_sessionKeepaliveTimer);
        }

        _sessionKeepaliveTimer = setInterval(ping, 5 * 60 * 1000);
        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState === 'visible') {
                ping();
            }
        });
    }

    // ============================================
    // Navigation Module
    // ============================================

    const navigation = {
        /**
         * Navigate to a mobile page
         * @param {string} page - Page name (e.g., 'dashboard', 'tasks', 'habits')
         * Note: Device detection is automatic - no need for &device parameter
         */
        navigateTo(page) {
            // Add transition effect
            document.body.classList.add('page-transition');

            setTimeout(() => {
                window.location.href = `?page=${page}`;
            }, 150);
        },

        /**
         * Navigate back to previous page
         */
        goBack() {
            if (window.history.length > 1) {
                window.history.back();
            } else {
                this.navigateTo('dashboard');
            }
        }
    };

    // ============================================
    // UI Module
    // ============================================

    const ui = {
        /**
         * Toggle off-canvas menu (full-page)
         */
        toggleMenu() {
            const menu = document.getElementById('offcanvas-menu');
            const panel = document.getElementById('offcanvas-panel');
            if (!menu || !panel) return;

            const isOpen = !menu.classList.contains('hidden');

            if (isOpen) {
                this.closeMenu();
            } else {
                menu.classList.remove('hidden');
                // Small delay to allow display:block to apply before transform
                setTimeout(() => {
                    panel.classList.remove('translate-x-full');
                }, 10);
                document.body.style.overflow = 'hidden';
            }
        },

        /**
         * Close off-canvas menu (full-page)
         */
        closeMenu() {
            const menu = document.getElementById('offcanvas-menu');
            const panel = document.getElementById('offcanvas-panel');
            if (!menu || !panel) return;

            panel.classList.add('translate-x-full');
            // Wait for animation to finish before hiding
            setTimeout(() => {
                menu.classList.add('hidden');
            }, 300);
            document.body.style.overflow = '';
        },

        /**
         * Open modal with content
         * @param {string} content - HTML content for modal
         */
        openModal(content) {
            // Create modal container if it doesn't exist
            let container = document.getElementById('mobile-modal-container');
            if (!container) {
                container = document.createElement('div');
                container.id = 'mobile-modal-container';
                container.className = 'fixed inset-0 z-50 flex items-end justify-center';
                container.innerHTML = `
                    <div class="absolute inset-0 bg-black/50" onclick="Mobile.ui.closeModal()"></div>
                    <div id="mobile-modal-content" class="relative bg-white dark:bg-zinc-900 text-zinc-900 dark:text-zinc-100 rounded-t-3xl w-full max-h-[90vh] overflow-y-auto transform transition-transform duration-300 translate-y-0" style="padding-bottom: max(1rem, env(safe-area-inset-bottom))"></div>
                `;
                document.body.appendChild(container);
            }

            const modalContent = document.getElementById('mobile-modal-content');
            modalContent.innerHTML = content;
            container.classList.remove('hidden');
            document.body.style.overflow = 'hidden';

            // Animate in
            setTimeout(() => {
                modalContent.classList.remove('translate-y-full');
            }, 10);
        },

        /**
         * Close modal
         */
        closeModal() {
            const container = document.getElementById('mobile-modal-container');
            if (!container) return;

            const modalContent = document.getElementById('mobile-modal-content');
            modalContent.classList.add('translate-y-full');

            setTimeout(() => {
                container.classList.add('hidden');
                document.body.style.overflow = '';
            }, 300);
        },

        /**
         * Open task creation modal
         */
        openTaskModal() {
            const modalHTML = `
                <div class="p-6">
                    <div class="flex items-center justify-between mb-6">
                        <h2 class="text-xl font-bold">New Task</h2>
                        <button onclick="Mobile.ui.closeModal()" class="p-2 -mr-2">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>
                    <form id="mobile-task-form" onsubmit="Mobile.tasks.submitForm(event)">
                        <input type="hidden" name="csrf_token" value="${typeof CSRF_TOKEN !== 'undefined' ? CSRF_TOKEN : ''}">

                        <div class="space-y-4">
                            <div>
                                <label class="block text-xs font-bold text-gray-400 dark:text-zinc-500 uppercase tracking-wider mb-2">Title</label>
                                <input type="text" name="title" required
                                       class="w-full bg-gray-50 dark:bg-zinc-800 border border-transparent dark:border-zinc-700 rounded-xl py-3 px-4 text-sm focus:ring-1 focus:ring-black dark:focus:ring-white outline-none"
                                       placeholder="What needs to be done?">
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-gray-400 dark:text-zinc-500 uppercase tracking-wider mb-2">Description</label>
                                <textarea name="description" rows="3"
                                          class="w-full bg-gray-50 dark:bg-zinc-800 border border-transparent dark:border-zinc-700 rounded-xl py-3 px-4 text-sm focus:ring-1 focus:ring-black dark:focus:ring-white outline-none resize-none"
                                          placeholder="Add details..."></textarea>
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-gray-400 dark:text-zinc-500 uppercase tracking-wider mb-2">Project</label>
                                <select name="projectId"
                                        class="w-full bg-gray-50 dark:bg-zinc-800 border border-transparent dark:border-zinc-700 rounded-xl py-3 px-4 text-sm focus:ring-1 focus:ring-black dark:focus:ring-white outline-none appearance-none">
                                    <option value="">No Project</option>
                                </select>
                            </div>

                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-xs font-bold text-gray-400 dark:text-zinc-500 uppercase tracking-wider mb-2">Priority</label>
                                    <select name="priority"
                                            class="w-full bg-gray-50 dark:bg-zinc-800 border border-transparent dark:border-zinc-700 rounded-xl py-3 px-4 text-sm focus:ring-1 focus:ring-black dark:focus:ring-white outline-none appearance-none">
                                        <option value="medium">Medium</option>
                                        <option value="low">Low</option>
                                        <option value="high">High</option>
                                        <option value="urgent">Urgent</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-gray-400 dark:text-zinc-500 uppercase tracking-wider mb-2">Due Date</label>
                                    <input type="date" name="dueDate"
                                           class="w-full bg-gray-50 dark:bg-zinc-800 border border-transparent dark:border-zinc-700 rounded-xl py-3 px-4 text-sm focus:ring-1 focus:ring-black dark:focus:ring-white outline-none">
                                </div>
                            </div>
                        </div>

                        <button type="submit"
                                class="w-full mt-6 bg-black dark:bg-white text-white dark:text-black py-4 rounded-xl font-semibold text-sm active:scale-95 transition-transform">
                            Create Task
                        </button>
                    </form>
                </div>
            `;

            this.openModal(modalHTML);

            // Load projects
            this.loadProjectsForForm();
        },

        /**
         * Load projects into form select
         */
        async loadProjectsForForm() {
            try {
                const result = await App.api.get('api/projects.php');
                if (result.success && result.data) {
                    const select = document.querySelector('select[name="projectId"]');
                    if (!select) return;

                    result.data.forEach(project => {
                        const option = document.createElement('option');
                        option.value = project.id;
                        option.textContent = project.name;
                        select.appendChild(option);
                    });
                }
            } catch (error) {
                console.error('Failed to load projects:', error);
            }
        },

        /**
         * Toggle filter options panel
         */
        toggleFilterOptions() {
            // This would open a filter modal - implement as needed
            this.showToast('Filter options coming soon!', 'info');
        },

        /**
         * Show toast notification
         * @param {string} message - Toast message
         * @param {string} type - Type: success, error, warning, info
         */
        showToast(message, type = 'info') {
            // Remove existing toast if any
            const existingToast = document.getElementById('mobile-toast');
            if (existingToast) {
                existingToast.remove();
            }

            const toast = document.createElement('div');
            toast.id = 'mobile-toast';
            toast.className = 'fixed top-6 left-6 right-6 bg-black text-white px-4 py-3 border border-black shadow-lg z-50 text-sm font-medium tracking-wide animate-fade-in';
            toast.textContent = message;

            document.body.appendChild(toast);

            setTimeout(() => {
                toast.classList.add('opacity-0', 'transition-opacity');
                setTimeout(() => toast.remove(), 300);
            }, 3000);
        },

        /**
         * Queue a toast to be shown after navigation/reload.
         * @param {string} message
         * @param {string} type
         */
        queueToast(message, type = 'info') {
            try {
                sessionStorage.setItem(FLASH_TOAST_KEY, JSON.stringify({ message, type }));
            } catch (error) {
                console.warn('Unable to queue toast:', error);
            }
        },

        /**
         * Flush queued toast once.
         */
        flushQueuedToast() {
            try {
                const payload = sessionStorage.getItem(FLASH_TOAST_KEY);
                if (!payload) {
                    return;
                }
                sessionStorage.removeItem(FLASH_TOAST_KEY);
                const toast = JSON.parse(payload);
                if (toast && typeof toast.message === 'string' && toast.message) {
                    this.showToast(toast.message, toast.type || 'info');
                }
            } catch (error) {
                console.warn('Unable to flush queued toast:', error);
            }
        },

        /**
         * Show loading overlay
         */
        showLoading() {
            _isLoading = true;
            let overlay = document.getElementById('mobile-loading-overlay');
            if (!overlay) {
                overlay = document.createElement('div');
                overlay.id = 'mobile-loading-overlay';
                overlay.className = 'fixed inset-0 bg-black/20 backdrop-blur-sm z-50 flex items-center justify-center';
                overlay.innerHTML = `
                    <div class="bg-white dark:bg-zinc-900 rounded-2xl p-6 shadow-2xl">
                        <div class="animate-spin w-8 h-8 border-3 border-black dark:border-white border-t-transparent rounded-full"></div>
                    </div>
                `;
                document.body.appendChild(overlay);
            }
            overlay.classList.remove('hidden');
        },

        /**
         * Hide loading overlay
         */
        hideLoading() {
            _isLoading = false;
            const overlay = document.getElementById('mobile-loading-overlay');
            if (overlay) {
                overlay.classList.add('hidden');
            }
        },

        /**
         * Show confirmation dialog with action
         * @param {string} message - Confirmation message
         * @param {Function} onConfirm - Callback to execute on confirm
         */
        confirmAction(message, onConfirm) {
            // Remove existing confirmation if any
            const existing = document.getElementById('mobile-confirm-dialog');
            if (existing) existing.remove();

            // Create confirmation dialog
            const dialog = document.createElement('div');
            dialog.id = 'mobile-confirm-dialog';
            dialog.className = 'fixed inset-0 z-50 flex items-center justify-center p-6';
            dialog.innerHTML = `
                <div class="absolute inset-0 bg-black/50" onclick="Mobile.ui.closeConfirmDialog()"></div>
                <div class="relative bg-white dark:bg-zinc-900 text-zinc-900 dark:text-zinc-100 rounded-2xl p-6 shadow-2xl max-w-sm w-full">
                    <p class="text-lg font-medium mb-6">${message}</p>
                    <div class="flex gap-3">
                        <button onclick="Mobile.ui.closeConfirmDialog()" class="flex-1 py-3 border border-black dark:border-white rounded-xl font-bold text-sm uppercase tracking-tight">Cancel</button>
                        <button id="confirm-dialog-confirm-btn" class="flex-1 py-3 bg-black dark:bg-white text-white dark:text-black rounded-xl font-bold text-sm uppercase tracking-tight">Confirm</button>
                    </div>
                </div>
            `;
            document.body.appendChild(dialog);

            // Add confirm handler
            document.getElementById('confirm-dialog-confirm-btn').addEventListener('click', () => {
                this.closeConfirmDialog();
                if (typeof onConfirm === 'function') {
                    onConfirm();
                }
            });
        },

        /**
         * Close confirmation dialog
         */
        closeConfirmDialog() {
            const dialog = document.getElementById('mobile-confirm-dialog');
            if (dialog) {
                dialog.remove();
            }
        }
    };

    // ============================================
    // Theme Module
    // ============================================

    const theme = {
        get() {
            return document.documentElement.classList.contains('dark') ? 'dark' : 'light';
        },

        apply(nextTheme) {
            const resolvedTheme = nextTheme === 'dark' ? 'dark' : 'light';
            const useDark = resolvedTheme === 'dark';
            document.documentElement.classList.toggle('dark', useDark);
            document.documentElement.classList.toggle('light', !useDark);

            try {
                localStorage.setItem('mobile-theme', resolvedTheme);
                localStorage.setItem('theme', resolvedTheme);
            } catch (error) {
                console.warn('Unable to persist theme preference:', error);
            }

            this.syncControls();
            window.dispatchEvent(new CustomEvent('mobile:theme-changed', { detail: { theme: resolvedTheme } }));
            return resolvedTheme;
        },

        toggle() {
            const nextTheme = this.get() === 'dark' ? 'light' : 'dark';
            return this.apply(nextTheme);
        },

        syncControls() {
            const current = this.get();
            const label = document.querySelector('[data-theme-label]');
            if (label) {
                label.textContent = current === 'dark' ? 'Dark' : 'Light';
            }

            document.querySelectorAll('[data-theme-toggle]').forEach((button) => {
                button.setAttribute(
                    'aria-label',
                    current === 'dark' ? 'Switch to light mode' : 'Switch to dark mode'
                );
            });
        }
    };

    // ============================================
    // Habit Reminder Module
    // ============================================

    const habits = {
        SENT_KEY: 'lazyman_mobile_habit_reminders_sent_v2',
        async requestNotificationPermission() {
            if (!('Notification' in window) || Notification.permission !== 'default') {
                return;
            }
            try {
                await Notification.requestPermission();
            } catch (error) {
                console.warn('Mobile notification permission request failed:', error);
            }
        },
        sendReminder(title, body, tag, opts) {
            if ('Notification' in window && Notification.permission === 'granted') {
                try {
                    const o = { body, tag };
                    // opts: { type?: 'habit'|'task'|..., color?: '#RRGGBB' }
                    const type = (opts && opts.type) || 'habit';
                    const color = opts && opts.color;
                    if (window.buildMobileNotificationIcon) {
                        const icon = window.buildMobileNotificationIcon(type, color);
                        if (icon) o.icon = icon;
                    }
                    new Notification(title, o);
                } catch (error) {
                    console.warn('Mobile notification send failed:', error);
                }
            }
            ui.showToast(body, 'info');
        },
        getTodayDateKey() {
            return new Date().toISOString().slice(0, 10);
        },
        getCurrentMinuteOfDay() {
            const now = new Date();
            return (now.getHours() * 60) + now.getMinutes();
        },
        /** Convert "HH:MM" string to minutes since midnight */
        reminderTimeToMinutes(timeStr) {
            if (!timeStr || typeof timeStr !== 'string') return null;
            const parts = timeStr.split(':');
            if (parts.length < 2) return null;
            const h = parseInt(parts[0], 10);
            const m = parseInt(parts[1], 10);
            if (isNaN(h) || isNaN(m)) return null;
            return h * 60 + m;
        },
        loadSentState() {
            try {
                const raw = localStorage.getItem(this.SENT_KEY);
                return raw ? JSON.parse(raw) : {};
            } catch (e) {
                return {};
            }
        },
        saveSentState(state) {
            try {
                localStorage.setItem(this.SENT_KEY, JSON.stringify(state));
            } catch (e) {
                // Ignore storage errors.
            }
        },
        async checkReminders() {
            try {
                const response = await App.api.get('api/habits.php');
                const habitsData = Array.isArray(response.data) ? response.data : [];

                // Only habits that are active, not completed today, and have a reminderTime set
                const habitsWithReminders = habitsData.filter((habit) => {
                    const isActive = habit.isActive !== false;
                    const notDone = !habit.todayCompleted;
                    const hasTime = habit.reminderTime && typeof habit.reminderTime === 'string' && habit.reminderTime.trim() !== '';
                    return isActive && notDone && hasTime;
                });

                if (!habitsWithReminders.length) {
                    return;
                }

                const todayKey = this.getTodayDateKey();
                const minuteOfDay = this.getCurrentMinuteOfDay();
                const sentState = this.loadSentState();
                let stateChanged = false;

                for (const habit of habitsWithReminders) {
                    const reminderMinute = this.reminderTimeToMinutes(habit.reminderTime);
                    if (reminderMinute === null) continue;

                    // Fire within ±1 minute of the configured time
                    const diff = minuteOfDay - reminderMinute;
                    if (diff < 0 || diff > 1) continue;

                    const sentKey = `${habit.id}_${todayKey}`;
                    if (sentState[sentKey]) continue;

                    const title = `Habit Reminder: ${habit.name}`;
                    const body = `Time to "${habit.name}"`;
                    this.sendReminder(title, body, `mobile-habit-${habit.id}-${todayKey}`);

                    sentState[sentKey] = true;
                    stateChanged = true;
                }

                if (stateChanged) {
                    this.saveSentState(sentState);
                }
            } catch (error) {
                console.warn('Mobile habit reminder check failed:', error);
            }
        },
        init() {
            const params = new URLSearchParams(window.location.search);
            const pageName = params.get('page') || 'dashboard';
            if (pageName === 'login' || pageName === 'setup') {
                return;
            }
            this.requestNotificationPermission();
            this.checkReminders();

            if (_habitReminderTimer !== null) {
                clearInterval(_habitReminderTimer);
            }
            _habitReminderTimer = setInterval(() => this.checkReminders(), 60 * 1000);
        }
    };

    const pomodoroOverlay = {
        intervalId: null,
        audio: null,
        elements: null,
        loadState() {
            try {
                const raw = localStorage.getItem(POMODORO_STATE_KEY);
                if (!raw) {
                    return {
                        phase: 'idle',
                        running: false,
                        secondsLeft: 25 * 60,
                        focusMinutes: 25,
                        breakMinutes: 5,
                        lastTick: null,
                        awaitingNextFocus: false
                    };
                }
                const parsed = JSON.parse(raw);
                const focusMinutes = parseInt(parsed.focusMinutes || 25, 10) || 25;
                const breakMinutes = parseInt(parsed.breakMinutes || 5, 10) || 5;
                return {
                    phase: parsed.phase || 'idle',
                    running: !!parsed.running,
                    secondsLeft: parseInt(parsed.secondsLeft || focusMinutes * 60, 10) || focusMinutes * 60,
                    focusMinutes,
                    breakMinutes,
                    lastTick: parsed.lastTick || null,
                    awaitingNextFocus: !!parsed.awaitingNextFocus
                };
            } catch (error) {
                return {
                    phase: 'idle',
                    running: false,
                    secondsLeft: 25 * 60,
                    focusMinutes: 25,
                    breakMinutes: 5,
                    lastTick: null,
                    awaitingNextFocus: false
                };
            }
        },
        saveState(state) {
            localStorage.setItem(POMODORO_STATE_KEY, JSON.stringify({
                phase: state.phase,
                running: state.running,
                secondsLeft: state.secondsLeft,
                focusMinutes: state.focusMinutes,
                breakMinutes: state.breakMinutes,
                lastTick: state.lastTick,
                awaitingNextFocus: state.awaitingNextFocus
            }));
        },
        syncElapsed(state) {
            if (!state.running || !state.lastTick) {
                return state;
            }
            const now = Date.now();
            let elapsed = Math.floor((now - state.lastTick) / 1000);
            if (elapsed <= 0) {
                return state;
            }
            while (elapsed > 0 && state.running) {
                if (elapsed < state.secondsLeft) {
                    state.secondsLeft -= elapsed;
                    elapsed = 0;
                } else {
                    elapsed -= state.secondsLeft;
                    state.secondsLeft = 0;
                    if (state.phase === 'focus') {
                        state.phase = 'break';
                        state.secondsLeft = state.breakMinutes * 60;
                        state.running = true;
                        state.awaitingNextFocus = false;
                        ui.showToast('Pomodoro complete. Break started.', 'info');
                    } else if (state.phase === 'break') {
                        state.phase = 'idle';
                        state.secondsLeft = state.focusMinutes * 60;
                        state.running = false;
                        state.awaitingNextFocus = true;
                        state.lastTick = null;
                        ui.showToast('Break complete. Ready again.', 'info');
                    } else {
                        state.running = false;
                        state.lastTick = null;
                    }
                }
            }
            if (state.running) {
                state.lastTick = now;
            }
            return state;
        },
        formatClock(totalSeconds) {
            const seconds = Math.max(0, parseInt(totalSeconds || 0, 10));
            const mins = Math.floor(seconds / 60);
            const secs = seconds % 60;
            return `${String(mins).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
        },
        ensureAudioSource() {
            if (!this.audio) {
                return '';
            }
            const trackId = localStorage.getItem(POMODORO_MUSIC_ID_KEY) || '';
            if (!trackId) {
                if (this.audio.getAttribute('src')) {
                    this.audio.removeAttribute('src');
                    this.audio.load();
                }
                this.audio.dataset.trackId = '';
                return '';
            }
            if (this.audio.dataset.trackId === trackId && this.audio.getAttribute('src')) {
                return trackId;
            }
            const baseUrl = resolveCanonicalAppUrl();
            this.audio.src = `${baseUrl}/api/pomodoro.php?action=music_download&id=${encodeURIComponent(trackId)}`;
            this.audio.dataset.trackId = trackId;
            return trackId;
        },
        loadTrackOrder() {
            try {
                const raw = localStorage.getItem(POMODORO_MUSIC_TRACK_ORDER_KEY);
                if (!raw) {
                    return [];
                }
                const parsed = JSON.parse(raw);
                if (!Array.isArray(parsed)) {
                    return [];
                }
                return parsed.filter((item) => typeof item === 'string' && item !== '');
            } catch (error) {
                return [];
            }
        },
        resolveTrackLabel(trackId) {
            if (!trackId) {
                return 'No track selected';
            }
            try {
                const raw = localStorage.getItem(POMODORO_MUSIC_TRACK_CACHE_KEY);
                if (!raw) {
                    return 'Selected track';
                }
                const cache = JSON.parse(raw) || {};
                return cache[trackId] || 'Selected track';
            } catch (error) {
                return 'Selected track';
            }
        },
        switchTrack(direction) {
            const order = this.loadTrackOrder();
            if (!order.length) {
                return '';
            }
            const currentId = localStorage.getItem(POMODORO_MUSIC_ID_KEY) || '';
            let index = order.indexOf(currentId);
            if (index < 0) {
                index = 0;
            } else {
                index = (index + (direction < 0 ? -1 : 1) + order.length) % order.length;
            }
            const nextId = order[index] || '';
            if (!nextId) {
                return '';
            }
            localStorage.setItem(POMODORO_MUSIC_ID_KEY, nextId);
            localStorage.setItem(POMODORO_MUSIC_TIME_KEY, '0');
            this.ensureAudioSource();
            return nextId;
        },
        syncAudioWithState(state) {
            if (!this.audio) {
                return;
            }
            this.ensureAudioSource();
            const autoplay = localStorage.getItem(POMODORO_MUSIC_AUTO_KEY) === '1';
            const manualPause = localStorage.getItem(POMODORO_MUSIC_MANUAL_PAUSE_KEY) === '1';
            const hasTrack = !!this.audio.getAttribute('src');
            if (autoplay && state.running && hasTrack && !manualPause) {
                this.audio.play().catch(() => {});
            } else if (!state.running && !this.audio.paused) {
                this.audio.pause();
            }
        },
        applyPosition() {
            if (!this.elements) {
                return;
            }
            const x = parseInt(localStorage.getItem(MOBILE_POMODORO_OVERLAY_OFFSET_X_KEY) || '0', 10);
            const y = parseInt(localStorage.getItem(MOBILE_POMODORO_OVERLAY_OFFSET_Y_KEY) || '0', 10);
            const offsetX = Number.isFinite(x) ? x : 0;
            const offsetY = Number.isFinite(y) ? y : 0;
            this.elements.overlay.style.transform = `translate(${offsetX}px, ${offsetY}px)`;
            this.elements.showButton.style.transform = `translate(${offsetX}px, ${offsetY}px)`;
        },
        moveBy(deltaX, deltaY) {
            const currentX = parseInt(localStorage.getItem(MOBILE_POMODORO_OVERLAY_OFFSET_X_KEY) || '0', 10);
            const currentY = parseInt(localStorage.getItem(MOBILE_POMODORO_OVERLAY_OFFSET_Y_KEY) || '0', 10);
            const nextX = (Number.isFinite(currentX) ? currentX : 0) + deltaX;
            const nextY = (Number.isFinite(currentY) ? currentY : 0) + deltaY;
            localStorage.setItem(MOBILE_POMODORO_OVERLAY_OFFSET_X_KEY, String(Math.max(-180, Math.min(20, nextX))));
            localStorage.setItem(MOBILE_POMODORO_OVERLAY_OFFSET_Y_KEY, String(Math.max(-260, Math.min(80, nextY))));
        },
        toggleState() {
            const state = this.loadState();
            if (state.running) {
                state.running = false;
                state.lastTick = null;
            } else {
                if (state.phase === 'idle') {
                    state.phase = 'focus';
                    state.awaitingNextFocus = false;
                    state.secondsLeft = state.focusMinutes * 60;
                }
                state.running = true;
                state.lastTick = Date.now();
                localStorage.setItem(POMODORO_MUSIC_MANUAL_PAUSE_KEY, '0');
            }
            this.saveState(state);
            this.syncAudioWithState(state);
            this.render();
        },
        resetState() {
            const state = this.loadState();
            state.phase = 'idle';
            state.awaitingNextFocus = false;
            state.running = false;
            state.secondsLeft = state.focusMinutes * 60;
            state.lastTick = null;
            this.saveState(state);
            this.syncAudioWithState(state);
            this.render();
        },
        render() {
            if (!this.elements) {
                return;
            }
            const state = this.syncElapsed(this.loadState());
            this.saveState(state);
            this.syncAudioWithState(state);
            const trackId = localStorage.getItem(POMODORO_MUSIC_ID_KEY) || '';
            const hidden = localStorage.getItem(MOBILE_POMODORO_OVERLAY_HIDDEN_KEY) === '1';
            const showOverlay = state.running || state.phase === 'focus' || state.phase === 'break' || state.awaitingNextFocus === true;
            this.elements.overlay.classList.toggle('hidden', hidden || !showOverlay);
            this.elements.showButton.classList.toggle('hidden', !hidden || !showOverlay);
            this.elements.track.textContent = this.resolveTrackLabel(trackId);
            this.elements.clock.textContent = this.formatClock(state.secondsLeft);
            if (state.phase === 'focus') {
                this.elements.status.textContent = state.running ? 'Focus' : 'Focus Paused';
            } else if (state.phase === 'break') {
                this.elements.status.textContent = state.running ? 'Break' : 'Break Paused';
            } else {
                this.elements.status.textContent = state.awaitingNextFocus ? 'Break Complete' : 'Ready';
            }
            if (state.running) {
                this.elements.toggle.textContent = 'Pause';
            } else if (state.phase === 'idle') {
                this.elements.toggle.textContent = state.awaitingNextFocus ? 'Resume' : 'Start';
            } else if (state.phase === 'break') {
                this.elements.toggle.textContent = 'Resume';
            } else {
                this.elements.toggle.textContent = 'Resume';
            }
            const volume = parseFloat(localStorage.getItem(POMODORO_MUSIC_VOLUME_KEY) || String(this.audio?.volume || 0.6));
            if (Number.isFinite(volume)) {
                this.elements.volume.value = String(Math.max(0, Math.min(1, volume)));
            }
            const order = this.loadTrackOrder();
            this.elements.prev.disabled = order.length < 2;
            this.elements.next.disabled = order.length < 2;
            this.applyPosition();
        },
        bind() {
            this.elements.hide.addEventListener('click', () => {
                localStorage.setItem(MOBILE_POMODORO_OVERLAY_HIDDEN_KEY, '1');
                this.render();
            });
            this.elements.showButton.addEventListener('click', () => {
                localStorage.setItem(MOBILE_POMODORO_OVERLAY_HIDDEN_KEY, '0');
                this.render();
            });
            this.elements.play.addEventListener('click', async () => {
                const trackId = this.ensureAudioSource();
                if (!trackId) {
                    ui.showToast('Select a track on Pomodoro page first', 'info');
                    this.render();
                    return;
                }
                if (this.audio.paused) {
                    try {
                        await this.audio.play();
                        localStorage.setItem(POMODORO_MUSIC_MANUAL_PAUSE_KEY, '0');
                    } catch (error) {
                        ui.showToast('Playback blocked. Tap again.', 'warning');
                    }
                } else {
                    localStorage.setItem(POMODORO_MUSIC_MANUAL_PAUSE_KEY, '1');
                    this.audio.pause();
                }
                this.render();
            });
            this.elements.prev.addEventListener('click', async () => {
                const nextId = this.switchTrack(-1);
                if (!nextId) {
                    ui.showToast('No additional tracks', 'info');
                    return;
                }
                if (localStorage.getItem(POMODORO_MUSIC_PLAYING_KEY) === '1') {
                    try {
                        await this.audio.play();
                        localStorage.setItem(POMODORO_MUSIC_MANUAL_PAUSE_KEY, '0');
                    } catch (error) {
                    }
                }
                this.render();
            });
            this.elements.next.addEventListener('click', async () => {
                const nextId = this.switchTrack(1);
                if (!nextId) {
                    ui.showToast('No additional tracks', 'info');
                    return;
                }
                if (localStorage.getItem(POMODORO_MUSIC_PLAYING_KEY) === '1') {
                    try {
                        await this.audio.play();
                        localStorage.setItem(POMODORO_MUSIC_MANUAL_PAUSE_KEY, '0');
                    } catch (error) {
                    }
                }
                this.render();
            });
            this.elements.toggle.addEventListener('click', () => this.toggleState());
            this.elements.reset.addEventListener('click', () => this.resetState());
            this.elements.volume.addEventListener('input', () => {
                const value = parseFloat(this.elements.volume.value || '0.6');
                if (!Number.isFinite(value)) {
                    return;
                }
                this.audio.volume = Math.max(0, Math.min(1, value));
                localStorage.setItem(POMODORO_MUSIC_VOLUME_KEY, String(this.audio.volume));
            });
            this.elements.posLeft.addEventListener('click', () => {
                this.moveBy(-20, 0);
                this.render();
            });
            this.elements.posRight.addEventListener('click', () => {
                this.moveBy(20, 0);
                this.render();
            });
            this.elements.posUp.addEventListener('click', () => {
                this.moveBy(0, -20);
                this.render();
            });
            this.elements.posDown.addEventListener('click', () => {
                this.moveBy(0, 20);
                this.render();
            });
            this.elements.posReset.addEventListener('click', () => {
                localStorage.setItem(MOBILE_POMODORO_OVERLAY_OFFSET_X_KEY, '0');
                localStorage.setItem(MOBILE_POMODORO_OVERLAY_OFFSET_Y_KEY, '0');
                this.render();
            });
            this.audio.addEventListener('timeupdate', () => {
                localStorage.setItem(POMODORO_MUSIC_TIME_KEY, String(this.audio.currentTime || 0));
            });
            this.audio.addEventListener('play', () => {
                localStorage.setItem(POMODORO_MUSIC_PLAYING_KEY, '1');
                this.render();
            });
            this.audio.addEventListener('pause', () => {
                localStorage.setItem(POMODORO_MUSIC_PLAYING_KEY, '0');
                this.render();
            });
            this.audio.addEventListener('loadedmetadata', () => {
                const savedTime = parseFloat(localStorage.getItem(POMODORO_MUSIC_TIME_KEY) || '0');
                if (Number.isFinite(savedTime) && savedTime > 0) {
                    this.audio.currentTime = Math.min(savedTime, Math.max(0, this.audio.duration - 1));
                }
                this.render();
            });
            this.audio.addEventListener('ended', () => {
                localStorage.setItem(POMODORO_MUSIC_PLAYING_KEY, '0');
                this.render();
            });
            window.addEventListener('storage', (event) => {
                if (!event.key || event.key.startsWith('pomodoro') || event.key.startsWith('mobilePomodoroOverlay')) {
                    this.ensureAudioSource();
                    this.render();
                }
            });
        },
        init() {
            const overlay = document.getElementById('mobile-pomodoro-overlay');
            const showButton = document.getElementById('mobile-pomodoro-overlay-show');
            const audio = document.getElementById('pomodoro-audio');
            if (!overlay || !showButton || !audio) {
                return;
            }
            this.audio = audio;
            this.elements = {
                overlay,
                showButton,
                track: document.getElementById('mobile-pomodoro-overlay-track'),
                status: document.getElementById('mobile-pomodoro-overlay-status'),
                clock: document.getElementById('mobile-pomodoro-overlay-clock'),
                hide: document.getElementById('mobile-pomodoro-overlay-hide'),
                prev: document.getElementById('mobile-pomodoro-overlay-prev'),
                play: document.getElementById('mobile-pomodoro-overlay-play'),
                next: document.getElementById('mobile-pomodoro-overlay-next'),
                toggle: document.getElementById('mobile-pomodoro-overlay-toggle'),
                reset: document.getElementById('mobile-pomodoro-overlay-reset'),
                volume: document.getElementById('mobile-pomodoro-overlay-volume'),
                posLeft: document.getElementById('mobile-pomodoro-overlay-pos-left'),
                posRight: document.getElementById('mobile-pomodoro-overlay-pos-right'),
                posUp: document.getElementById('mobile-pomodoro-overlay-pos-up'),
                posDown: document.getElementById('mobile-pomodoro-overlay-pos-down'),
                posReset: document.getElementById('mobile-pomodoro-overlay-pos-reset')
            };
            if (Object.values(this.elements).some((item) => !item)) {
                return;
            }
            const savedVolume = parseFloat(localStorage.getItem(POMODORO_MUSIC_VOLUME_KEY) || '0.6');
            if (Number.isFinite(savedVolume)) {
                this.audio.volume = Math.max(0, Math.min(1, savedVolume));
                this.elements.volume.value = String(this.audio.volume);
            }
            this.audio.loop = localStorage.getItem(POMODORO_MUSIC_LOOP_KEY) === '1';
            this.ensureAudioSource();
            this.bind();
            this.render();
            if (this.intervalId === null) {
                this.intervalId = setInterval(() => this.render(), 1000);
            }
        }
    };

    // ============================================
    // Floating Timer Indicator Module
    // Shows a persistent bar at bottom when a task timer is running
    // ============================================
    const floatingTimer = {
        _el: null,
        _interval: null,

        formatTimerDisplay(seconds) {
            const abs = Math.abs(seconds);
            const h = Math.floor(abs / 3600);
            const m = Math.floor((abs % 3600) / 60);
            const s = abs % 60;
            const prefix = seconds < 0 ? '-' : '';
            return prefix + String(h).padStart(2, '0') + ':' + String(m).padStart(2, '0') + ':' + String(s).padStart(2, '0');
        },

        getElapsed(state) {
            if (!state.startTime) return 0;
            let extraPause = 0;
            if (state.paused && state.pauseBegin) {
                extraPause = Date.now() - state.pauseBegin;
            }
            return Math.floor((Date.now() - state.startTime - (state.pausedTime || 0) - extraPause) / 1000);
        },

        create() {
            const el = document.createElement('div');
            el.id = 'mobile-floating-timer';
            el.className = 'fixed bottom-20 left-1/2 -translate-x-1/2 z-50 bg-black text-white rounded-full shadow-2xl flex items-center gap-2 px-4 py-2 cursor-pointer active:scale-95 transition-transform';
            el.innerHTML = `
                <svg class="w-4 h-4 text-white animate-pulse" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.25 5.653c0-.856.917-1.398 1.667-.986l11.54 6.347a1.125 1.125 0 010 1.972l-11.54 6.347a1.125 1.125 0 01-1.667-.986V5.653z"/>
                </svg>
                <div class="flex flex-col leading-tight">
                    <span id="ft-task-title" class="text-[9px] font-bold uppercase tracking-widest opacity-70 truncate max-w-[140px]"></span>
                    <span id="ft-timer-display" class="text-xs font-bold tabular-nums"></span>
                </div>
            `;
            document.body.appendChild(el);
            this._el = el;
        },

        update(state) {
            if (!this._el) return;
            const title = this._el.querySelector('#ft-task-title');
            const display = this._el.querySelector('#ft-timer-display');
            if (title) title.textContent = state.taskTitle || 'Task';
            if (!display) return;

            const elapsed = this.getElapsed(state);
            if (state.estimatedMinutes > 0) {
                const remaining = (state.estimatedMinutes * 60) - elapsed;
                display.textContent = this.formatTimerDisplay(remaining);
                if (remaining <= 0) {
                    display.classList.add('text-red-400');
                    display.classList.remove('text-white');
                } else {
                    display.classList.remove('text-red-400');
                    display.classList.add('text-white');
                }
            } else {
                display.textContent = this.formatTimerDisplay(elapsed);
            }

            // Navigate to view-task page on tap
            this._el.onclick = () => {
                const params = new URLSearchParams(window.location.search);
                params.set('page', 'view-task');
                params.set('id', state.taskId);
                params.set('projectId', state.projectId);
                window.location.href = '?' + params.toString();
            };
        },

        show(state) {
            if (!this._el) this.create();
            this.update(state);
            this._el.style.display = 'flex';

            // Start 1-second update interval
            if (this._interval) clearInterval(this._interval);
            const self = this;
            this._interval = setInterval(() => {
                try {
                    const raw = localStorage.getItem('mobileTaskTimer');
                    if (!raw) { self.hide(); return; }
                    const s = JSON.parse(raw);
                    if (!s.running || s.taskId !== state.taskId) { self.hide(); return; }
                    self.update(s);
                } catch (e) { self.hide(); }
            }, 1000);
        },

        hide() {
            if (this._interval) { clearInterval(this._interval); this._interval = null; }
            if (this._el) this._el.style.display = 'none';
        },

        init() {
            try {
                const raw = localStorage.getItem('mobileTaskTimer');
                if (!raw) return;
                const state = JSON.parse(raw);
                if (!state.running) return;

                // Don't show on view-task page (has its own timer)
                const params = new URLSearchParams(window.location.search);
                if (params.get('page') === 'view-task' && params.get('id') === state.taskId) return;

                this.show(state);
            } catch (e) {}
        }
    };

    // ============================================
    // Utils Module
    // ============================================

    const utils = {
        /**
         * Debounce function execution
         * @param {Function} func - Function to debounce
         * @param {number} wait - Wait time in milliseconds
         * @param {Function} callback - Callback to execute with debounced value
         */
        debounce(func, wait, callback) {
            return (...args) => {
                clearTimeout(_debounceTimer);
                _debounceTimer = setTimeout(() => {
                    callback.apply(this, args);
                }, wait);
            };
        },

        /**
         * Format date to relative time
         * @param {string} dateStr - ISO date string
         * @returns {string} Formatted relative time
         */
        timeAgo(dateStr) {
            const date = new Date(dateStr);
            const now = new Date();
            const seconds = Math.floor((now - date) / 1000);

            if (seconds < 60) return 'just now';
            if (seconds < 3600) return Math.floor(seconds / 60) + 'm ago';
            if (seconds < 86400) return Math.floor(seconds / 3600) + 'h ago';
            if (seconds < 604800) return Math.floor(seconds / 86400) + 'd ago';
            return date.toLocaleDateString();
        },

        /**
         * Format date to readable format
         * @param {string} dateStr - ISO date string
         * @returns {string} Formatted date
         */
        formatDate(dateStr) {
            if (!dateStr) return '';
            const date = new Date(dateStr);
            return date.toLocaleDateString('en-US', {
                month: 'short',
                day: 'numeric',
                year: 'numeric'
            });
        },

        /**
         * Generate unique ID
         * @returns {string} UUID
         */
        generateId() {
            return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
                const r = Math.random() * 16 | 0;
                const v = c === 'x' ? r : (r & 0x3 | 0x8);
                return v.toString(16);
            });
        }
    };

    // ============================================
    // Tasks Module
    // ============================================

    const tasks = {
        /**
         * Toggle task completion status
         * @param {string} taskId - Task ID
         * @param {boolean} checked - Completion status
         */
        async toggleComplete(taskId, checked) {
            try {
                // Show visual feedback immediately
                const taskCard = document.querySelector(`[data-task-id="${taskId}"]`)?.closest('.task-card');
                if (taskCard) {
                    taskCard.classList.toggle('opacity-50', checked);
                }

                const data = {
                    action: 'toggle_complete',
                    id: taskId,
                    completed: checked,
                    csrf_token: typeof CSRF_TOKEN !== 'undefined' ? CSRF_TOKEN : ''
                };

                const result = await App.api.post(`api/tasks.php?action=update&id=${encodeURIComponent(taskId)}`, data);

                if (result.success) {
                    ui.showToast(
                        checked ? 'Task completed! 🎉' : 'Task reopened',
                        'success'
                    );

                    // Reload page after short delay to show updated state
                    setTimeout(() => {
                        window.location.reload();
                    }, 500);
                } else {
                    throw new Error(result.message || 'Failed to update task');
                }
            } catch (error) {
                console.error('Toggle complete error:', error);
                ui.showToast('Failed to update task', 'error');

                // Revert visual state
                const taskCard = document.querySelector(`[data-task-id="${taskId}"]`)?.closest('.task-card');
                if (taskCard) {
                    taskCard.classList.remove('opacity-50');
                }

                // Reset checkbox
                const checkbox = document.querySelector(`[data-task-id="${taskId}"]`);
                if (checkbox) {
                    checkbox.checked = !checked;
                }
            }
        },

        /**
         * Toggle task card expansion
         * @param {string} taskId - Task ID
         */
        toggleExpand(taskId) {
            const details = document.getElementById(`task-details-${taskId}`);
            if (!details) return;

            const isHidden = details.classList.contains('hidden');
            details.classList.toggle('hidden');

            // Update chevron icon
            const button = details.previousElementSibling?.querySelector('button svg');
            if (button) {
                button.style.transform = isHidden ? 'rotate(180deg)' : 'rotate(0deg)';
            }
        },

        /**
         * Submit task form
         * @param {Event} event - Form submit event
         */
        async submitForm(event) {
            event.preventDefault();

            const form = event.target;
            const formData = new FormData(form);
            const data = Object.fromEntries(formData.entries());

            // Add action
            data.action = 'create';

            try {
                ui.showLoading();

                const result = await App.api.post('api/tasks.php', data);

                ui.hideLoading();

                if (result.success) {
                    ui.closeModal();
                    ui.queueToast('Task created successfully!', 'success');

                    const currentPage = new URLSearchParams(window.location.search).get('page') || '';
                    if (currentPage === 'tasks') {
                        window.location.reload();
                    } else {
                        window.location.href = '?page=tasks';
                    }
                } else {
                    throw new Error(result.message || 'Failed to create task');
                }
            } catch (error) {
                ui.hideLoading();
                console.error('Create task error:', error);
                ui.showToast(error.message || 'Failed to create task', 'error');
            }
        },

        /**
         * Delete task with confirmation
         * @param {string} taskId - Task ID
         */
        async deleteTask(taskId) {
            if (!confirm('Are you sure you want to delete this task?')) {
                return;
            }

            try {
                ui.showLoading();

                const result = await App.api.delete(`api/tasks.php?id=${taskId}&csrf_token=${typeof CSRF_TOKEN !== 'undefined' ? CSRF_TOKEN : ''}`);

                ui.hideLoading();

                if (result.success) {
                    ui.showToast('Task deleted', 'success');

                    // Remove from DOM
                    const taskCard = document.querySelector(`[data-task-id="${taskId}"]`)?.closest('.task-card');
                    if (taskCard) {
                        taskCard.style.opacity = '0';
                        taskCard.style.transform = 'translateX(-100%)';
                        setTimeout(() => taskCard.remove(), 300);
                    }
                } else {
                    throw new Error(result.message || 'Failed to delete task');
                }
            } catch (error) {
                ui.hideLoading();
                console.error('Delete task error:', error);
                ui.showToast(error.message || 'Failed to delete task', 'error');
            }
        }
    };

    // ============================================
    // Search Module
    // ============================================

    const search = {
        /**
         * Perform search on current page
         * @param {string} query - Search query
         */
        perform(query) {
            const taskFeed = document.getElementById('task-feed');
            if (!taskFeed) return;

            const taskCards = taskFeed.querySelectorAll('.task-card');
            let visibleCount = 0;

            const lowerQuery = query.toLowerCase().trim();

            taskCards.forEach(card => {
                if (!lowerQuery) {
                    // Show all if query is empty
                    card.style.display = '';
                    visibleCount++;
                    return;
                }

                const title = card.querySelector('.font-medium')?.textContent.toLowerCase() || '';
                const project = card.querySelector('[data-project]')?.textContent.toLowerCase() || '';

                const matches = title.includes(lowerQuery) || project.includes(lowerQuery);

                card.style.display = matches ? '' : 'none';
                if (matches) visibleCount++;
            });

            // Update task count
            const countElement = document.querySelector('.text-xs.font-medium.text-gray-400');
            if (countElement) {
                countElement.textContent = `${visibleCount} tasks found`;
            }

            // Show empty state if no results
            if (lowerQuery && visibleCount === 0) {
                let emptyState = document.getElementById('search-empty-state');
                if (!emptyState) {
                    emptyState = document.createElement('div');
                    emptyState.id = 'search-empty-state';
                    emptyState.className = 'text-center py-12';
                    emptyState.innerHTML = `
                        <svg class="w-12 h-12 text-gray-300 dark:text-zinc-600 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                        <h3 class="text-lg font-medium text-gray-900 dark:text-zinc-100 mb-1">No tasks found</h3>
                        <p class="text-gray-500 dark:text-zinc-400">Try a different search term</p>
                    `;
                    taskFeed.appendChild(emptyState);
                }
                emptyState.style.display = '';
            } else {
                const emptyState = document.getElementById('search-empty-state');
                if (emptyState) {
                    emptyState.style.display = 'none';
                }
            }
        }
    };

    // ============================================
    // Gestures Module
    // ============================================

    const gestures = {
        /**
         * Initialize swipe gestures on task cards
         */
        initSwipes() {
            const taskFeed = document.getElementById('task-feed');
            if (!taskFeed) return;

            const taskCards = taskFeed.querySelectorAll('.task-card');

            taskCards.forEach(card => {
                let startX = 0;
                let currentX = 0;
                let isDragging = false;

                card.addEventListener('touchstart', (e) => {
                    startX = e.touches[0].clientX;
                    isDragging = true;
                }, { passive: true });

                card.addEventListener('touchmove', (e) => {
                    if (!isDragging) return;

                    currentX = e.touches[0].clientX;
                    const diff = currentX - startX;

                    // Only allow horizontal swipes
                    if (Math.abs(diff) > 10) {
                        e.preventDefault();

                        // Visual feedback
                        const maxSwipe = 100;
                        const opacity = Math.max(0.5, 1 - Math.abs(diff) / maxSwipe);
                        card.style.opacity = opacity;

                        // Direction indicator
                        if (diff < -20) {
                            // Swipe left - complete (green)
                            card.style.backgroundColor = 'rgba(34, 197, 94, 0.1)';
                        } else if (diff > 20) {
                            // Swipe right - delete (red)
                            card.style.backgroundColor = 'rgba(239, 68, 68, 0.1)';
                        } else {
                            card.style.backgroundColor = '';
                        }
                    }
                }, { passive: false });

                card.addEventListener('touchend', (e) => {
                    if (!isDragging) return;
                    isDragging = false;

                    const diff = currentX - startX;
                    const threshold = 80;

                    // Reset styles
                    card.style.opacity = '';
                    card.style.backgroundColor = '';

                    if (Math.abs(diff) > threshold) {
                        const checkbox = card.querySelector('input[type="checkbox"]');
                        const taskId = checkbox?.getAttribute('data-task-id');

                        if (diff < 0 && checkbox) {
                            // Swipe left - toggle complete
                            const newState = !checkbox.checked;
                            checkbox.checked = newState;
                            tasks.toggleComplete(taskId, newState);
                        } else if (diff > 0 && taskId) {
                            // Swipe right - delete
                            tasks.deleteTask(taskId);
                        }
                    }

                    startX = 0;
                    currentX = 0;
                }, { passive: true });
            });
        },

        /**
         * Initialize pull-to-refresh
         */
        initPullToRefresh() {
            let startY = 0;
            let isPulling = false;
            const threshold = 100;

            document.addEventListener('touchstart', (e) => {
                if (window.scrollY === 0) {
                    startY = e.touches[0].clientY;
                    isPulling = true;
                }
            }, { passive: true });

            document.addEventListener('touchmove', (e) => {
                if (!isPulling || window.scrollY > 0) return;

                const currentY = e.touches[0].clientY;
                const diff = currentY - startY;

                if (diff > 20) {
                    // Show loading indicator
                    let indicator = document.getElementById('pull-to-refresh-indicator');
                    if (!indicator) {
                        indicator = document.createElement('div');
                        indicator.id = 'pull-to-refresh-indicator';
                        indicator.className = 'fixed top-0 left-0 right-0 bg-black text-white py-3 text-center text-sm font-medium transform -translate-y-full transition-transform duration-200 z-50';
                        indicator.textContent = 'Pull to refresh...';
                        document.body.appendChild(indicator);
                    }

                    const progress = Math.min(diff / threshold, 1);
                    indicator.style.transform = `translateY(${progress * 100}% - 100%)`;

                    if (diff >= threshold) {
                        indicator.textContent = 'Release to refresh...';
                    } else {
                        indicator.textContent = 'Pull to refresh...';
                    }
                }
            }, { passive: true });

            document.addEventListener('touchend', (e) => {
                if (!isPulling) return;
                isPulling = false;

                const indicator = document.getElementById('pull-to-refresh-indicator');
                if (!indicator) return;

                const currentY = e.changedTouches[0].clientY;
                const diff = currentY - startY;

                if (diff >= threshold) {
                    // Trigger refresh
                    indicator.textContent = 'Refreshing...';
                    window.location.reload();
                } else {
                    // Cancel
                    indicator.style.transform = 'translateY(-100%)';
                }
            }, { passive: true });
        }
    };

    // ============================================
    // Public API
    // ============================================

    return {
        /**
         * Initialize Mobile framework
         */
        init() {
            if (_initialized) return;

            console.log('Mobile framework initializing...');

            // Initialize gestures
            if (window.innerWidth < 768) {
                gestures.initSwipes();
                gestures.initPullToRefresh();
            }

            initSessionKeepalive();
            theme.syncControls();
            ui.flushQueuedToast();
            habits.init();
            pomodoroOverlay.init();
            floatingTimer.init();

            // Handle resize
            window.addEventListener('resize', () => {
                if (window.innerWidth < 768 && !_initialized) {
                    gestures.initSwipes();
                    gestures.initPullToRefresh();
                }
            });

            _initialized = true;
            console.log('Mobile framework initialized');
        },

        // Expose modules
        navigation,
        ui,
        theme,
        habits,
        utils,
        tasks,
        search,
        gestures,
        pomodoroOverlay,
        floatingTimer,

        // State
        get initialized() {
            return _initialized;
        }
    };
})();

// Expose for inline handlers and cross-script access on mobile views.
window.Mobile = Mobile;

// Auto-initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => Mobile.init());
} else {
    Mobile.init();
}
