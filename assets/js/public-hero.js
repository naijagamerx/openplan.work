/* Homepage hero motion (assets/css/public-hero.css holds the looks).
   1. The ring: three screens turn every 4.8s; click a back screen to bring it
      forward; pauses while the pointer or keyboard focus is on it.
   2. Scroll: the copy lifts and fades, the stage tilts toward the reader.
   Reduced motion: the ring and scroll effects stay still. */
(() => {
    const stage = document.querySelector('[data-hero-stage]');
    if (!stage) return;

    const slides = Array.from(stage.querySelectorAll('.op-slide'));
    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
    const STEP = 120;
    const INTERVAL_MS = 4800;
    // Front, back-right, back-left. The back screens are scaled up a little so
    // their edges show beside the front one (the ring's depth shrinks them).
    const LOOK = [{ z: 3, o: 1, s: 1 }, { z: 2, o: 0.9, s: 1.1 }, { z: 2, o: 0.9, s: 1.1 }];

    const state = slides.map((slide, index) => ({ slide, angle: index * STEP, slot: index }));

    const render = () => {
        state.forEach(({ slide, angle, slot }) => {
            const look = LOOK[slot];
            slide.style.setProperty('--a', angle + 'deg');
            slide.style.setProperty('--o', String(look.o));
            slide.style.setProperty('--s', String(look.s));
            slide.style.zIndex = String(look.z);
            slide.dataset.slot = String(slot);
            const isFront = slot === 0;
            slide.classList.toggle('is-front', isFront);
            slide.removeAttribute('aria-hidden');
            if (isFront) {
                slide.removeAttribute('role');
                slide.removeAttribute('aria-label');
                slide.removeAttribute('tabindex');
            } else {
                const name = (slide.querySelector('figcaption')?.textContent || 'this').split('·')[0].trim();
                slide.setAttribute('role', 'button');
                slide.setAttribute('aria-label', 'Show the ' + name + ' screen');
                slide.tabIndex = 0;
            }
        });
    };

    // Announce a change only when the visitor asked for it, not on every timed turn.
    const announce = () => {
        const front = state.find((item) => item.slot === 0);
        const live = stage.querySelector('[data-hero-live]');
        if (live && front) live.textContent = 'Showing ' + (front.slide.querySelector('figcaption')?.textContent || '');
    };

    const step = (times = 1) => {
        for (let i = 0; i < times; i++) {
            state.forEach((item) => {
                item.angle += STEP;
                item.slot = (item.slot + 1) % state.length;
            });
        }
        render();
    };

    let timer = null;
    const stop = () => { if (timer) { clearInterval(timer); timer = null; } };
    const start = () => {
        stop();
        if (reduceMotion.matches || document.hidden) return;
        timer = setInterval(() => step(), INTERVAL_MS);
    };

    // Bring the clicked (or Enter-ed) back screen to the front.
    const bringForward = (slide) => {
        const item = state.find((entry) => entry.slide === slide);
        if (!item || item.slot === 0) return;
        step(state.length - item.slot);
        announce();
        start();
    };
    slides.forEach((slide) => {
        slide.addEventListener('click', () => bringForward(slide));
        slide.addEventListener('keydown', (event) => {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                bringForward(slide);
            }
        });
    });

    stage.addEventListener('mouseenter', stop);
    stage.addEventListener('mouseleave', start);
    stage.addEventListener('focusin', stop);
    stage.addEventListener('focusout', start);
    document.addEventListener('visibilitychange', () => (document.hidden ? stop() : start()));

    render();
    start();

    // Scroll motion, throttled to one frame.
    const copy = document.querySelector('[data-hero-copy]');
    const clamp = (value) => Math.min(1, Math.max(0, value));
    const smooth = (t) => t * t * (3 - 2 * t);
    let ticking = false;

    const reset = () => {
        if (copy) { copy.style.transform = ''; copy.style.opacity = ''; }
        stage.style.transform = '';
    };
    const update = () => {
        ticking = false;
        if (reduceMotion.matches) { reset(); return; }
        const progress = smooth(clamp(window.scrollY / (window.innerHeight * 0.85)));
        const k = window.innerWidth <= 700 ? 0.5 : 1;
        if (copy) {
            copy.style.transform = 'translate3d(0,' + (-progress * 90 * k) + 'px,0) scale(' + (1 - progress * 0.05 * k) + ')';
            copy.style.opacity = String(1 - progress * 0.9 * k);
        }
        stage.style.transform = 'perspective(1500px) translate3d(0,' + (-progress * 60 * k) + 'px,' + (progress * 200 * k) + 'px) rotateX(' + ((1 - progress) * 6 * k) + 'deg)';
    };
    const onScroll = () => {
        if (!ticking) {
            ticking = true;
            window.requestAnimationFrame(update);
        }
    };

    // A finished CSS animation with fill "forwards" would still override the inline
    // transform, so the stage drops its entrance animation before scroll takes over.
    let begun = false;
    const begin = () => {
        if (begun) return;
        begun = true;
        stage.classList.add('is-entered');
        window.addEventListener('scroll', onScroll, { passive: true });
        window.addEventListener('resize', onScroll, { passive: true });
        update();
    };
    if (reduceMotion.matches) {
        reset();
    } else {
        stage.addEventListener('animationend', begin, { once: true });
        // If the entrance never runs (older browsers), start anyway.
        setTimeout(begin, 1700);
    }
    reduceMotion.addEventListener?.('change', () => { reset(); start(); });
})();
