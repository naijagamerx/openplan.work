/**
 * HabitTimerManager — Global singleton for habit session timing.
 *
 * Persists state to localStorage so the timer survives page navigation.
 * Uses a simple Observer pattern: register listeners with .on(event, cb).
 *
 * Events emitted:
 *   timer:started        — { habitId, targetMinutes, elapsedSeconds }
 *   timer:tick           — { habitId, elapsedSeconds, targetMinutes }
 *   timer:target-reached — { habitId, elapsedSeconds, targetMinutes }
 *   timer:stopped        — { habitId, duration }
 *   timer:restored       — { habitId, elapsedSeconds, targetMinutes }
 */
const HabitTimerManager = (() => {
    const STORAGE_KEY = 'habit_timer_state';

    let state = {
        running: false,
        habitId: null,
        sessionId: null,
        elapsedSeconds: 0,
        targetMinutes: 0,
        startEpoch: null   // wall-clock start time for drift correction
    };

    let intervalId = null;
    const listeners = {};

    // ── Event emitter ────────────────────────────────────────────────────────

    function on(event, cb) {
        if (!listeners[event]) listeners[event] = [];
        listeners[event].push(cb);
    }

    function emit(event, data) {
        (listeners[event] || []).forEach(cb => {
            try { cb(data); } catch (e) { console.error('HabitTimerManager event error:', e); }
        });
    }

    // ── Storage helpers ──────────────────────────────────────────────────────

    function persist() {
        try {
            localStorage.setItem(STORAGE_KEY, JSON.stringify(state));
        } catch (_) {}
    }

    function clearStorage() {
        try { localStorage.removeItem(STORAGE_KEY); } catch (_) {}
    }

    // ── Tick loop ────────────────────────────────────────────────────────────

    function startTick() {
        if (intervalId) clearInterval(intervalId);
        intervalId = setInterval(() => {
            if (!state.running) return;

            // Drift-correct using wall clock
            const elapsed = Math.floor((Date.now() - state.startEpoch) / 1000);
            state.elapsedSeconds = elapsed;
            persist();

            emit('timer:tick', { ...state });

            if (state.targetMinutes > 0 && elapsed >= state.targetMinutes * 60) {
                emit('timer:target-reached', { ...state });
                // Don't auto-stop — let the user decide
            }
        }, 1000);
    }

    function stopTick() {
        if (intervalId) {
            clearInterval(intervalId);
            intervalId = null;
        }
    }

    // ── Public API ───────────────────────────────────────────────────────────

    async function start(habitId, targetMinutes = 0) {
        if (state.running) await stop();

        // Tell the server a session has started
        let sessionId = null;
        try {
            const res = await fetch('api/habits.php?action=start_timer', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ habitId, csrf_token: window.CSRF_TOKEN || '' })
            });
            const json = await res.json();
            sessionId = json.data?.id ?? null;
        } catch (e) {
            console.warn('HabitTimerManager: Could not notify server of timer start:', e);
        }

        state = {
            running: true,
            habitId,
            sessionId,
            elapsedSeconds: 0,
            targetMinutes,
            startEpoch: Date.now()
        };

        persist();
        startTick();
        emit('timer:started', { ...state });
    }

    async function stop() {
        if (!state.running) return;

        stopTick();
        const duration = state.elapsedSeconds;
        const { habitId, sessionId } = state;

        // Tell the server the session ended
        if (sessionId) {
            try {
                await fetch('api/habits.php?action=stop_timer', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ sessionId, csrf_token: window.CSRF_TOKEN || '' })
                });
            } catch (e) {
                console.warn('HabitTimerManager: Could not notify server of timer stop:', e);
            }
        }

        state = { running: false, habitId: null, sessionId: null, elapsedSeconds: 0, targetMinutes: 0, startEpoch: null };
        clearStorage();
        emit('timer:stopped', { habitId, duration });
    }

    function getState() {
        return { ...state };
    }

    function restoreFromStorage() {
        try {
            const saved = localStorage.getItem(STORAGE_KEY);
            if (!saved) return;
            const parsed = JSON.parse(saved);
            if (!parsed || !parsed.running || !parsed.habitId || !parsed.startEpoch) return;

            // Recalculate elapsed using wall clock
            const elapsed = Math.floor((Date.now() - parsed.startEpoch) / 1000);
            state = { ...parsed, elapsedSeconds: elapsed };
            persist();
            startTick();
            emit('timer:restored', { ...state });
        } catch (e) {
            console.warn('HabitTimerManager: Could not restore timer state:', e);
            clearStorage();
        }
    }

    return { on, start, stop, getState, restoreFromStorage };
})();
