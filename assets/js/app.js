/**
 * LazyMan Tools - Main Application JavaScript
 */

// API Helper
const api = {
    async request(endpoint, options = {}) {
        const headers = {
            'X-CSRF-Token': CSRF_TOKEN,
            ...options.headers
        };

        if (!(options.body instanceof FormData)) {
            headers['Content-Type'] = headers['Content-Type'] || 'application/json';
        }

        const fetchOptions = {
            ...options,
            headers: headers
        };

        const response = await fetch(`${APP_URL}/${endpoint}`, fetchOptions);

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        const contentType = response.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
            throw new Error('Response is not JSON');
        }

        return response.json();
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
};

// Toast Notifications
function showToast(message, type = 'info') {
    const container = document.getElementById('toast-container');
    if (!container) return;

    const toast = document.createElement('div');

    const bgColor = {
        success: 'bg-green-500',
        error: 'bg-red-500',
        warning: 'bg-yellow-500',
        info: 'bg-gray-800'
    }[type] || 'bg-gray-800';

    toast.className = `${bgColor} text-white px-4 py-3 rounded-lg shadow-lg animate-fade-in flex items-center gap-2`;

    const icons = {
        success: '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>',
        error: '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>',
        warning: '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>',
        info: '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>'
    };

    toast.innerHTML = `${icons[type] || icons.info}<span>${message}</span>`;
    container.appendChild(toast);

    setTimeout(() => {
        toast.classList.add('opacity-0', 'transition-opacity');
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

// Modal Management
function openModal(content) {
    const container = document.getElementById('modal-container');
    const modalContent = document.getElementById('modal-content');

    if (!container || !modalContent) return;

    modalContent.innerHTML = content;
    container.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function closeModal() {
    const container = document.getElementById('modal-container');
    if (container) {
        container.classList.add('hidden');
        document.body.style.overflow = '';
    }
}

// Close modal on escape
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') closeModal();
});

// Form Handling
function handleForm(formId, onSuccess) {
    const form = document.getElementById(formId);
    if (!form) return;

    form.addEventListener('submit', async (e) => {
        e.preventDefault();

        const formData = new FormData(form);
        const data = Object.fromEntries(formData.entries());
        const action = form.getAttribute('action') || '';
        const method = form.getAttribute('method') || 'POST';

        try {
            const response = await api.request(action, {
                method: method.toUpperCase(),
                body: JSON.stringify(data)
            });

            if (response.success) {
                showToast(response.message || 'Success!', 'success');
                if (onSuccess) onSuccess(response);
            } else {
                showToast(response.error || 'Something went wrong', 'error');
            }
        } catch (error) {
            showToast('Network error. Please try again.', 'error');
        }
    });
}

// Confirm Dialog
function confirmAction(message, onConfirm) {
    openModal(`
        <div class="p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-2">Confirm Action</h3>
            <p class="text-gray-600 mb-6">${message}</p>
            <div class="flex gap-3 justify-end">
                <button onclick="closeModal()" class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50 transition">
                    Cancel
                </button>
                <button onclick="closeModal(); (${onConfirm.toString()})()" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition">
                    Confirm
                </button>
            </div>
        </div>
    `);
}

// Format currency for display
function formatCurrency(amount, currency = 'USD') {
    const symbols = { USD: '$', EUR: '€', GBP: '£', ZAR: 'R' };
    const symbol = symbols[currency] || currency + ' ';
    return symbol + parseFloat(amount).toFixed(2);
}

// Format date
function formatDate(dateStr) {
    const date = new Date(dateStr);
    return date.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
    });
}

// Time ago
function timeAgo(dateStr) {
    const date = new Date(dateStr);
    const now = new Date();
    const diff = Math.floor((now - date) / 1000);

    if (diff < 60) return 'just now';
    if (diff < 3600) return Math.floor(diff / 60) + ' min ago';
    if (diff < 86400) return Math.floor(diff / 3600) + ' hours ago';
    if (diff < 604800) return Math.floor(diff / 86400) + ' days ago';
    return formatDate(dateStr);
}

// Debounce function
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

// Habit Reminder System
let reminderInterval = null;
let lastCheckedMinute = null;

function initializeReminderCheck() {
    if (reminderInterval) {
        clearInterval(reminderInterval);
    }

    checkHabitReminders();

    reminderInterval = setInterval(() => {
        checkHabitReminders();
    }, 60000);

    console.log('Habit reminder system initialized');
}

async function checkHabitReminders() {
    try {
        const now = new Date();
        const currentHour = now.getHours();
        const currentMinute = now.getMinutes();
        const currentTimeKey = `${currentHour}:${currentMinute}`;

        if (lastCheckedMinute === currentTimeKey) {
            return;
        }

        lastCheckedMinute = currentTimeKey;

        const response = await api.get('api/habits.php');
        const habits = response.data || [];
        const today = now.toISOString().split('T')[0];

        habits.forEach(habit => {
            if (habit.reminderTime && !habit.todayCompleted && habit.frequency === 'daily') {
                const [habitHour, habitMinute] = habit.reminderTime.split(':').map(Number);

                if (currentHour === habitHour && currentMinute === habitMinute) {
                    showToast(`Time to: ${habit.name}`, 'info');
                }
            }
        });
    } catch (error) {
        console.error('Failed to check habit reminders:', error);
    }
}

// Initialize app
document.addEventListener('DOMContentLoaded', () => {
    console.log('LazyMan Tools initialized');

    const currentPage = window.location.search;
    if (currentPage.includes('page=habits') || currentPage.includes('page=dashboard')) {
        initializeReminderCheck();
    }
});
