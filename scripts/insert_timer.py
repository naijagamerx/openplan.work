import sys

filepath = sys.argv[1]

with open(filepath, 'rb') as f:
    content = f.read()

text = content.decode('utf-8', errors='replace')
has_cr = '\r\n' in text
lines = text.split('\n')
lines = [l.rstrip('\r') for l in lines]

# === STEP 1: Find the Time Tracking section and insert timer HTML ===
# Find the line: <div class="mb-2 flex justify-between items-end">
# which is inside the Time Tracking section, right after the header row
timer_html_anchor = None
for i, line in enumerate(lines):
    if '<div class="mb-2 flex justify-between items-end">' in line:
        # Verify it's in the Time Tracking section by checking preceding lines
        for j in range(max(0, i - 15), i):
            if 'Time Tracking' in lines[j]:
                timer_html_anchor = i
                break
        if timer_html_anchor is not None:
            break

if timer_html_anchor is None:
    print("ERROR: Could not find Time Tracking summary row")
    sys.exit(1)

print(f"Found Time Tracking summary row at line {timer_html_anchor + 1}")

timer_html = """        <!-- Per-Task Timer -->
        <div id="mobile-task-timer"
             data-task-id="<?= htmlspecialchars($taskId) ?>"
             data-project-id="<?= htmlspecialchars($projectId) ?>"
             data-task-title="<?= htmlspecialchars($taskTitle) ?>"
             data-estimated-minutes="<?= (int)$estimatedMinutes ?>"
             class="mb-6 p-6 bg-gray-50 dark:bg-zinc-800 rounded-2xl text-center">
            <span class="text-[10px] font-black uppercase tracking-widest text-gray-400 block mb-2">
                <?= $estimatedMinutes > 0 ? 'Countdown Timer' : 'Task Timer' ?>
            </span>
            <div class="text-5xl font-light tabular-nums mb-1" id="mobile-timer-display">
                <?php if ($estimatedMinutes > 0): ?>
                    <?= sprintf('%02d:%02d:00', floor($estimatedMinutes / 60), $estimatedMinutes % 60) ?>
                <?php else: ?>
                    00:00:00
                <?php endif; ?>
            </div>
            <div id="mobile-timer-status" class="text-[10px] font-bold uppercase tracking-widest text-gray-400 mb-2">Ready</div>
            <?php if ($estimatedMinutes > 0): ?>
            <div class="text-[10px] text-gray-400 mb-4">Estimated: <?= formatViewTaskMinutes($estimatedMinutes) ?></div>
            <?php else: ?>
            <div class="mb-2"></div>
            <?php endif; ?>
            <div class="grid grid-cols-2 gap-3">
                <button id="mobile-timer-toggle-btn" onclick="Mobile.viewTaskTimer.toggle()"
                        class="bg-black dark:bg-white text-white dark:text-black py-3 rounded-xl font-bold text-xs uppercase tracking-widest active:scale-95 transition-transform touch-target">
                    Start
                </button>
                <button id="mobile-timer-stop-btn" onclick="Mobile.viewTaskTimer.stop()"
                        class="border border-black dark:border-white py-3 rounded-xl font-bold text-xs uppercase tracking-widest active:scale-95 transition-transform touch-target">
                    Stop &amp; Log
                </button>
            </div>
        </div>
"""

# Insert timer HTML before the summary row
lines = lines[:timer_html_anchor] + [timer_html] + lines[timer_html_anchor:]
offset = 1  # lines shifted by 1 insertion

# === STEP 2: Find the Initialize Mobile section and insert timer JS before it ===
init_idx = None
for i, line in enumerate(lines):
    if '<!-- Initialize Mobile -->' in line:
        init_idx = i
        break

if init_idx is None:
    print("ERROR: Could not find '<!-- Initialize Mobile -->'")
    sys.exit(1)

print(f"Found 'Initialize Mobile' at line {init_idx + 1}")

# The })(); should be 3 lines before <!-- Initialize Mobile -->
# Lines: ...})();\n</script>\n\n<!-- Initialize Mobile -->
closing_idx = None
for i in range(init_idx - 1, max(0, init_idx - 10), -1):
    if lines[i].strip() == '})();':
        closing_idx = i
        break

if closing_idx is None:
    print("ERROR: Could not find })(); closing")
    sys.exit(1)

print(f"Found closing }})( at line {closing_idx + 1}")

timer_js = """
// Per-Task Timer Module
Mobile.viewTaskTimer = (function() {
    'use strict';

    var STORAGE_KEY = 'mobileTaskTimer';
    var timerEl = document.getElementById('mobile-task-timer');
    var _taskId = timerEl ? timerEl.dataset.taskId : '';
    var _projectId = timerEl ? timerEl.dataset.projectId : '';
    var _taskTitle = timerEl ? timerEl.dataset.taskTitle : '';
    var _estimatedMinutes = timerEl ? parseInt(timerEl.dataset.estimatedMinutes, 10) || 0 : 0;

    var _running = false;
    var _paused = false;
    var _startTime = null;
    var _pausedTime = 0;
    var _pauseBegin = null;
    var _overtime = false;
    var _overtimeAlerted = false;
    var _interval = null;

    function formatTime(totalSeconds) {
        var abs = Math.abs(totalSeconds);
        var h = Math.floor(abs / 3600);
        var m = Math.floor((abs % 3600) / 60);
        var s = abs % 60;
        var prefix = totalSeconds < 0 ? '-' : '';
        return prefix + String(h).padStart(2, '0') + ':' + String(m).padStart(2, '0') + ':' + String(s).padStart(2, '0');
    }

    function formatMinutes(min) {
        if (min < 60) return min + 'm';
        return Math.floor(min / 60) + 'h ' + (min % 60) + 'm';
    }

    function getElapsedSeconds() {
        if (!_startTime) return 0;
        var extraPause = 0;
        if (_paused && _pauseBegin) {
            extraPause = Date.now() - _pauseBegin;
        }
        return Math.floor((Date.now() - _startTime - _pausedTime - extraPause) / 1000);
    }

    function getDisplaySeconds() {
        var elapsed = getElapsedSeconds();
        if (_estimatedMinutes > 0) {
            return (_estimatedMinutes * 60) - elapsed;
        }
        return elapsed;
    }

    function updateDisplay() {
        var display = document.getElementById('mobile-timer-display');
        var status = document.getElementById('mobile-timer-status');
        var toggleBtn = document.getElementById('mobile-timer-toggle-btn');
        if (!display) return;

        var seconds = getDisplaySeconds();
        display.textContent = formatTime(seconds);

        if (_estimatedMinutes > 0 && seconds <= 0 && !_overtime && _running && !_paused) {
            _overtime = true;
            display.classList.add('text-red-500');
            if (!_overtimeAlerted) {
                _overtimeAlerted = true;
                if (window.Mobile && Mobile.ui) {
                    Mobile.ui.showToast('Time is up! You are now in overtime.', 'warning');
                }
                if ('vibrate' in navigator) {
                    navigator.vibrate([200, 100, 200, 100, 200]);
                }
            }
        }

        if (_overtime && _running) {
            if (status) { status.textContent = 'OVERTIME'; status.className = 'text-[10px] font-bold uppercase tracking-widest text-red-500 mb-2 animate-pulse'; }
        } else if (_running && !_paused) {
            if (status) { status.textContent = 'Running'; status.className = 'text-[10px] font-bold uppercase tracking-widest text-green-600 mb-2'; }
        } else if (_paused) {
            if (status) { status.textContent = 'Paused'; status.className = 'text-[10px] font-bold uppercase tracking-widest text-yellow-600 mb-2'; }
        } else {
            if (status) { status.textContent = 'Ready'; status.className = 'text-[10px] font-bold uppercase tracking-widest text-gray-400 mb-2'; }
        }

        if (toggleBtn) {
            if (!_running) toggleBtn.textContent = 'Start';
            else if (_paused) toggleBtn.textContent = 'Resume';
            else toggleBtn.textContent = 'Pause';
        }
    }

    function saveState() {
        try {
            localStorage.setItem(STORAGE_KEY, JSON.stringify({
                taskId: _taskId, projectId: _projectId, taskTitle: _taskTitle,
                estimatedMinutes: _estimatedMinutes, running: _running, paused: _paused,
                startTime: _startTime, pausedTime: _pausedTime, pauseBegin: _pauseBegin,
                overtime: _overtime
            }));
        } catch (e) {}
    }

    function clearState() {
        try { localStorage.removeItem(STORAGE_KEY); } catch (e) {}
    }

    function startInterval() {
        if (_interval) clearInterval(_interval);
        _interval = setInterval(function() { updateDisplay(); saveState(); }, 1000);
    }

    function restore() {
        try {
            var raw = localStorage.getItem(STORAGE_KEY);
            if (!raw) return false;
            var state = JSON.parse(raw);
            if (state.taskId !== _taskId) return false;
            _running = state.running; _paused = state.paused;
            _startTime = state.startTime; _pausedTime = state.pausedTime || 0;
            _pauseBegin = state.pauseBegin || null; _overtime = state.overtime || false;
            if (_running && !_paused) startInterval();
            updateDisplay();
            return true;
        } catch (e) { return false; }
    }

    function toggle() {
        if (!_running) {
            _running = true; _paused = false; _startTime = Date.now();
            _pausedTime = 0; _pauseBegin = null; _overtime = false; _overtimeAlerted = false;
            startInterval(); updateDisplay(); saveState();
            if (window.Mobile && Mobile.ui) Mobile.ui.showToast('Timer started', 'success');
        } else if (!_paused) {
            _paused = true; _pauseBegin = Date.now();
            clearInterval(_interval); _interval = null;
            updateDisplay(); saveState();
        } else {
            _paused = false;
            if (_pauseBegin) { _pausedTime += (Date.now() - _pauseBegin); _pauseBegin = null; }
            startInterval(); updateDisplay(); saveState();
        }
    }

    function stop() {
        if (!_running) return;
        var elapsedSeconds = getElapsedSeconds();
        var elapsedMinutes = Math.max(1, Math.round(elapsedSeconds / 60));
        clearInterval(_interval); _interval = null;

        var msg = 'Log ' + formatMinutes(elapsedMinutes) + ' to this task?';
        if (window.Mobile && Mobile.ui && Mobile.ui.confirmAction) {
            Mobile.ui.confirmAction(msg, function() { doLogTime(elapsedSeconds, elapsedMinutes); });
        } else if (confirm(msg)) {
            doLogTime(elapsedSeconds, elapsedMinutes);
        }
    }

    async function doLogTime(elapsedSeconds, elapsedMinutes) {
        if (window.Mobile && Mobile.ui && Mobile.ui.showLoading) Mobile.ui.showLoading();
        try {
            var response = await App.api.put('api/tasks.php?id=' + encodeURIComponent(_taskId), {
                timeEntries: {
                    date: new Date().toISOString(),
                    minutes: elapsedMinutes,
                    description: 'Mobile timer: ' + formatTime(elapsedSeconds)
                },
                addTimeEntry: true,
                csrf_token: (typeof CSRF_TOKEN !== 'undefined') ? CSRF_TOKEN : ''
            });

            if (window.Mobile && Mobile.ui && Mobile.ui.hideLoading) Mobile.ui.hideLoading();

            if (response.success) {
                _running = false; _paused = false; _startTime = null; _overtime = false;
                clearState();
                if (window.Mobile && Mobile.ui) Mobile.ui.showToast('Logged ' + formatMinutes(elapsedMinutes), 'success');
                setTimeout(function() { window.location.reload(); }, 600);
            } else {
                throw new Error(response.message || 'Failed to log time');
            }
        } catch (error) {
            if (window.Mobile && Mobile.ui && Mobile.ui.hideLoading) Mobile.ui.hideLoading();
            if (window.Mobile && Mobile.ui) Mobile.ui.showToast('Failed to log time: ' + (error.message || 'Error'), 'error');
        }
    }

    function checkAutoStart() {
        var params = new URLSearchParams(window.location.search);
        if (params.get('autostart') === '1' && !_running) {
            toggle();
            params.delete('autostart');
            var s = params.toString();
            window.history.replaceState({}, '', s ? '?' + s : window.location.pathname);
        }
    }

    return {
        init: function() { restore(); checkAutoStart(); },
        toggle: toggle, stop: stop, formatTime: formatTime
    };
})();
"""

# Insert timer JS after the })(); closing
lines = lines[:closing_idx + 1] + [timer_js] + lines[closing_idx + 1:]

# Now find the Mobile.init() line again and add viewTaskTimer.init() after it
for i in range(len(lines)):
    if lines[i].strip() == 'Mobile.init();':
        indent = lines[i][:len(lines[i]) - len(lines[i].lstrip())]
        lines[i] = (indent + 'Mobile.init();\n' +
                   indent + "if (Mobile.viewTaskTimer && typeof Mobile.viewTaskTimer.init === 'function') {\n" +
                   indent + '    Mobile.viewTaskTimer.init();\n' +
                   indent + '}')
        break

nl = '\r\n' if has_cr else '\n'
result = nl.join(lines)

with open(filepath, 'wb') as f:
    f.write(result.encode('utf-8'))

print(f'Updated {filepath} - inserted timer HTML + JS module')
