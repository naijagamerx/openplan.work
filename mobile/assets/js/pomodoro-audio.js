/**
 * PomodoroAudioManager - Mobile-safe audio handling for Pomodoro timer
 * 
 * Addresses mobile browser autoplay restrictions by:
 * - Detecting user gestures before audio initialization
 * - Providing proper Promise-based play() error handling
 * - Supporting muted autoplay fallbacks
 * - Managing AudioContext state for Web Audio API
 * 
 * @version 2.0
 */
const PomodoroAudioManager = (function() {
    'use strict';

    // ==================== Private State ====================
    
    let audioContext = null;
    let audioElement = null;
    let userInteracted = false;
    let pendingPlayRequest = null;
    let autoplayDetected = null;
    let interactionCallbacks = [];
    
    // Configuration
    const CONFIG = {
        retryAttempts: 3,
        retryDelay: 100,
        debug: false
    };

    // ==================== Logging ====================
    
    function log(...args) {
        if (CONFIG.debug || window.__POMODORO_AUDIO_DEBUG__) {
            console.log('[PomodoroAudio]', ...args);
        }
    }

    function warn(...args) {
        console.warn('[PomodoroAudio]', ...args);
    }

    function error(...args) {
        console.error('[PomodoroAudio]', ...args);
    }

    // ==================== Feature Detection ====================

    /**
     * Check if Web Audio API is supported
     */
    function isWebAudioSupported() {
        return !!(window.AudioContext || window.webkitAudioContext);
    }

    /**
     * Check if HTML5 Audio is supported
     */
    function isHtml5AudioSupported() {
        const audio = document.createElement('audio');
        return !!(audio.canPlayType && audio.canPlayType('audio/mpeg;').replace(/no/, ''));
    }

    /**
     * Detect if autoplay is allowed without user gesture
     * This is an async test that tries to play a silent audio
     */
    async function detectAutoplayCapability() {
        if (autoplayDetected !== null) {
            return autoplayDetected;
        }

        if (!isHtml5AudioSupported()) {
            autoplayDetected = false;
            return false;
        }

        // Test with silent audio data
        const audio = document.createElement('audio');
        audio.src = 'data:audio/wav;base64,UklGRiQAAABXQVZFZm10IBAAAAABAAEARKwAAIhYAQACABAAZGF0YQAAAAA=';
        
        try {
            await audio.play();
            audio.pause();
            autoplayDetected = true;
            log('Autoplay is allowed');
        } catch (e) {
            autoplayDetected = false;
            log('Autoplay is blocked:', e.name);
        }

        return autoplayDetected;
    }

    // ==================== Audio Context Management ====================

    /**
     * Initialize Web Audio Context
     * Must be called after user gesture on mobile
     */
    async function initializeAudioContext() {
        if (!isWebAudioSupported()) {
            warn('Web Audio API not supported');
            return null;
        }

        if (!audioContext) {
            const AudioContextClass = window.AudioContext || window.webkitAudioContext;
            audioContext = new AudioContextClass();
            log('AudioContext created, state:', audioContext.state);
        }

        // Critical for mobile: resume suspended context
        if (audioContext.state === 'suspended') {
            try {
                await audioContext.resume();
                log('AudioContext resumed successfully');
            } catch (err) {
                error('Failed to resume AudioContext:', err);
                throw new Error('AudioContext resume failed: ' + err.message);
            }
        }

        return audioContext;
    }

    /**
     * Get current AudioContext state
     */
    function getAudioContextState() {
        return audioContext?.state || 'not-initialized';
    }

    // ==================== User Gesture Handling ====================

    /**
     * Mark that user has interacted with the page
     * This unlocks audio capabilities on mobile browsers
     */
    async function markUserInteraction() {
        if (userInteracted) return;

        userInteracted = true;
        log('User interaction detected - audio unlocked');

        // Initialize audio context on first interaction
        try {
            await initializeAudioContext();
        } catch (e) {
            warn('Failed to initialize AudioContext:', e);
        }

        // Notify all registered callbacks
        interactionCallbacks.forEach(callback => {
            try {
                callback();
            } catch (e) {
                warn('Interaction callback error:', e);
            }
        });

        // Process any pending play request
        if (pendingPlayRequest) {
            log('Processing pending play request');
            const { trackId, options } = pendingPlayRequest;
            pendingPlayRequest = null;
            playTrack(trackId, options);
        }

        // Remove gesture listeners
        _removeGestureListeners();
    }

    /**
     * Setup gesture listeners to detect user interaction
     */
    function _setupGestureListeners() {
        const events = ['click', 'touchstart', 'keydown', 'pointerdown'];
        
        events.forEach(eventType => {
            document.addEventListener(eventType, markUserInteraction, { 
                passive: true,
                once: true 
            });
        });

        log('Gesture listeners attached');
    }

    /**
     * Remove gesture listeners after first interaction
     */
    function _removeGestureListeners() {
        const events = ['click', 'touchstart', 'keydown', 'pointerdown'];
        
        events.forEach(eventType => {
            document.removeEventListener(eventType, markUserInteraction);
        });

        log('Gesture listeners removed');
    }

    /**
     * Register a callback to be called when user interacts
     */
    function onUserInteraction(callback) {
        if (userInteracted) {
            // Already interacted, call immediately
            callback();
        } else {
            interactionCallbacks.push(callback);
        }
    }

    // ==================== Audio Playback ====================

    /**
     * Set the audio element to control
     */
    function setAudioElement(element) {
        if (typeof element === 'string') {
            audioElement = document.getElementById(element);
        } else {
            audioElement = element;
        }

        if (audioElement) {
            _setupAudioElementListeners();
        }

        return !!audioElement;
    }

    /**
     * Setup listeners on the audio element
     */
    function _setupAudioElementListeners() {
        if (!audioElement) return;

        audioElement.addEventListener('play', () => {
            log('Playback started');
            _updatePlayButton(true);
        });

        audioElement.addEventListener('pause', () => {
            log('Playback paused');
            _updatePlayButton(false);
        });

        audioElement.addEventListener('ended', () => {
            log('Playback ended');
            if (!audioElement.loop) {
                _updatePlayButton(false);
            }
        });

        audioElement.addEventListener('error', (e) => {
            error('Audio error:', audioElement.error);
            _updatePlayButton(false);
            _showError('Failed to load audio');
        });
    }

    /**
     * Play a track with mobile autoplay handling
     * 
     * @param {string} trackId - The track identifier
     * @param {Object} options - Playback options
     * @param {boolean} options.requireInteraction - Require user interaction first (default: true)
     * @param {boolean} options.mutedFallback - Try muted playback if blocked (default: true)
     * @param {number} options.retries - Number of retry attempts (default: 3)
     * @returns {Promise<{success: boolean, error?: string, message?: string}>}
     */
    async function playTrack(trackId, options = {}) {
        const {
            requireInteraction = true,
            mutedFallback = true,
            retries = CONFIG.retryAttempts
        } = options;

        // Validate audio element
        if (!audioElement) {
            error('No audio element set. Call setAudioElement() first.');
            return {
                success: false,
                error: 'NO_AUDIO_ELEMENT',
                message: 'Audio element not configured'
            };
        }

        // Check for user interaction
        if (requireInteraction && !userInteracted) {
            log('Queueing playback - waiting for user interaction');
            pendingPlayRequest = { trackId, options };
            _showInteractionPrompt();
            return {
                success: false,
                error: 'USER_INTERACTION_REQUIRED',
                message: 'Tap anywhere to enable audio'
            };
        }

        // Initialize audio context
        try {
            await initializeAudioContext();
        } catch (e) {
            warn('AudioContext initialization failed:', e);
        }

        // Set track source
        const baseUrl = window.APP_URL ? window.APP_URL.replace(/\/$/, '') : '';
        const trackUrl = `${baseUrl}/api/pomodoro.php?action=music_download&id=${encodeURIComponent(trackId)}`;
        if (audioElement.src !== trackUrl) {
            audioElement.src = trackUrl;
            audioElement.load();
            log('Track source set:', trackId);
        }

        // Attempt playback with retries
        for (let attempt = 1; attempt <= retries; attempt++) {
            try {
                log(`Play attempt ${attempt}/${retries}`);
                
                const playPromise = audioElement.play();
                
                // Modern browsers return a Promise from play()
                if (playPromise && typeof playPromise.then === 'function') {
                    await playPromise;
                }

                log('Playback started successfully');
                return { success: true };

            } catch (err) {
                warn(`Play attempt ${attempt} failed:`, err.name, err.message);

                // Handle specific error types
                switch (err.name) {
                    case 'NotAllowedError':
                        return await _handleNotAllowedError(mutedFallback);
                        
                    case 'NotSupportedError':
                        return {
                            success: false,
                            error: 'NOT_SUPPORTED',
                            message: 'Audio format not supported on this device'
                        };

                    case 'AbortError':
                        // Usually means a new play() was called before previous completed
                        log('Playback aborted (new play request)');
                        return { success: false, error: 'ABORTED' };

                    default:
                        // Unknown error, retry if attempts remain
                        if (attempt < retries) {
                            await _delay(CONFIG.retryDelay * attempt);
                        }
                }
            }
        }

        return {
            success: false,
            error: 'MAX_RETRIES_EXCEEDED',
            message: 'Failed to play audio after multiple attempts'
        };
    }

    /**
     * Handle NotAllowedError (autoplay blocked)
     */
    async function _handleNotAllowedError(tryMuted) {
        if (tryMuted && audioElement && !audioElement.muted) {
            log('Trying muted fallback...');
            audioElement.muted = true;
            
            try {
                await audioElement.play();
                log('Muted playback succeeded');
                return {
                    success: true,
                    muted: true,
                    message: 'Audio playing muted. Unmute to hear sound.'
                };
            } catch (mutedError) {
                audioElement.muted = false;
                warn('Muted fallback also failed:', mutedError.name);
            }
        }

        _showInteractionPrompt();
        return {
            success: false,
            error: 'AUTOPLAY_BLOCKED',
            message: 'Autoplay blocked. Please interact with the page first.'
        };
    }

    /**
     * Pause playback
     */
    function pauseTrack() {
        if (audioElement && !audioElement.paused) {
            audioElement.pause();
            log('Playback paused');
            return true;
        }
        return false;
    }

    /**
     * Toggle play/pause
     */
    async function togglePlayback(trackId, options = {}) {
        if (!audioElement) {
            return { success: false, error: 'NO_AUDIO_ELEMENT' };
        }

        if (!audioElement.paused) {
            pauseTrack();
            return { success: true, action: 'paused' };
        } else {
            return await playTrack(trackId, options);
        }
    }

    // ==================== Completion Sound ====================

    /**
     * Play a completion notification sound using Web Audio API
     * More reliable than HTML5 Audio for short sounds on mobile
     */
    async function playCompletionSound() {
        if (!userInteracted) {
            log('Cannot play completion sound: no user interaction yet');
            return false;
        }

        if (!isWebAudioSupported()) {
            warn('Web Audio API not supported');
            return false;
        }

        try {
            const ctx = await initializeAudioContext();
            if (!ctx) return false;

            // Create a pleasant notification beep
            const oscillator = ctx.createOscillator();
            const gainNode = ctx.createGain();

            oscillator.connect(gainNode);
            gainNode.connect(ctx.destination);

            // Configure sound - pleasant chime
            oscillator.type = 'sine';
            oscillator.frequency.setValueAtTime(880, ctx.currentTime); // A5
            oscillator.frequency.exponentialRampToValueAtTime(1760, ctx.currentTime + 0.1); // A6

            // Envelope
            gainNode.gain.setValueAtTime(0, ctx.currentTime);
            gainNode.gain.linearRampToValueAtTime(0.3, ctx.currentTime + 0.05);
            gainNode.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.5);

            oscillator.start(ctx.currentTime);
            oscillator.stop(ctx.currentTime + 0.5);

            log('Completion sound played');
            return true;

        } catch (err) {
            error('Failed to play completion sound:', err);
            return false;
        }
    }

    /**
     * Play completion sound using HTML5 Audio as fallback
     */
    async function playCompletionSoundLegacy(soundUrl = 'assets/media/pomodoro/notification.mp3') {
        if (!userInteracted) {
            return false;
        }

        try {
            const audio = new Audio(soundUrl);
            audio.volume = 0.5;
            await audio.play();
            return true;
        } catch (err) {
            warn('Legacy completion sound failed:', err.name);
            return false;
        }
    }

    // ==================== UI Helpers ====================

    /**
     * Update the play button UI
     */
    function _updatePlayButton(isPlaying) {
        const btn = document.getElementById('music-play-btn');
        if (btn) {
            btn.textContent = isPlaying ? 'Pause' : 'Play';
            btn.classList.toggle('playing', isPlaying);
            btn.setAttribute('aria-pressed', String(isPlaying));
        }
    }

    /**
     * Show user interaction prompt
     */
    function _showInteractionPrompt() {
        // Use existing toast system if available
        if (typeof Mobile !== 'undefined' && Mobile.ui?.showToast) {
            Mobile.ui.showToast('Tap anywhere to enable audio', 'info');
        } else if (typeof App !== 'undefined' && App.ui?.showToast) {
            App.ui.showToast('Tap anywhere to enable audio', 'info');
        } else {
            // Fallback to console
            log('USER ACTION REQUIRED: Tap anywhere to enable audio');
        }
    }

    /**
     * Show error message
     */
    function _showError(message) {
        if (typeof Mobile !== 'undefined' && Mobile.ui?.showToast) {
            Mobile.ui.showToast(message, 'error');
        } else if (typeof App !== 'undefined' && App.ui?.showToast) {
            App.ui.showToast(message, 'error');
        } else {
            error(message);
        }
    }

    // ==================== Utilities ====================

    /**
     * Delay helper
     */
    function _delay(ms) {
        return new Promise(resolve => setTimeout(resolve, ms));
    }

    /**
     * Get current state
     */
    function getState() {
        return {
            userInteracted,
            autoplayAllowed: autoplayDetected,
            audioContextState: getAudioContextState(),
            webAudioSupported: isWebAudioSupported(),
            html5AudioSupported: isHtml5AudioSupported(),
            audioElementReady: !!audioElement
        };
    }

    // ==================== Initialization ====================

    /**
     * Initialize the audio manager
     */
    function initialize(options = {}) {
        Object.assign(CONFIG, options);
        
        log('Initializing PomodoroAudioManager');
        
        // Setup gesture detection
        _setupGestureListeners();
        
        // Detect autoplay capability
        detectAutoplayCapability().then(allowed => {
            log('Autoplay capability:', allowed ? 'allowed' : 'blocked');
        });

        // Setup default audio element if exists
        const defaultAudioEl = document.getElementById('pomodoro-audio');
        if (defaultAudioEl) {
            setAudioElement(defaultAudioEl);
        }

        log('Initialization complete');
    }

    // ==================== Public API ====================

    return {
        // Initialization
        initialize,
        setAudioElement,
        
        // Playback
        playTrack,
        pauseTrack,
        togglePlayback,
        
        // Notification sounds
        playCompletionSound,
        playCompletionSoundLegacy,
        
        // User interaction
        markUserInteraction,
        onUserInteraction,
        
        // Audio Context
        initializeAudioContext,
        getAudioContextState,
        
        // Feature detection
        detectAutoplayCapability,
        isWebAudioSupported,
        isHtml5AudioSupported,
        
        // State
        getState,
        get isReady() { return userInteracted; },
        get hasInteracted() { return userInteracted; }
    };
})();

// Auto-initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => PomodoroAudioManager.initialize());
} else {
    PomodoroAudioManager.initialize();
}
