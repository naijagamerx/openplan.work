<script>
    const themeToggle = document.getElementById('theme-toggle');
    const navToggle = document.getElementById('nav-toggle');
    const siteLinks = document.getElementById('site-links');
    const root = document.documentElement;

    themeToggle?.addEventListener('click', () => {
        const isDark = root.classList.toggle('dark');
        try { localStorage.setItem('theme', isDark ? 'dark' : 'light'); } catch (error) {}
    });

    const closeNavigation = (restoreFocus = false) => {
        if (!navToggle || !siteLinks) return;
        navToggle.setAttribute('aria-expanded', 'false');
        navToggle.setAttribute('aria-label', 'Open navigation');
        siteLinks.classList.remove('is-open');
        if (restoreFocus) navToggle.focus();
    };

    navToggle?.addEventListener('click', () => {
        const isOpen = navToggle.getAttribute('aria-expanded') === 'true';
        navToggle.setAttribute('aria-expanded', String(!isOpen));
        navToggle.setAttribute('aria-label', isOpen ? 'Open navigation' : 'Close navigation');
        siteLinks?.classList.toggle('is-open', !isOpen);
    });

    siteLinks?.addEventListener('click', (event) => {
        if (event.target.closest('a')) closeNavigation();
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && navToggle?.getAttribute('aria-expanded') === 'true') {
            closeNavigation(true);
        }
    });
</script>
<script>
    (() => {
        const shots = document.querySelectorAll('[data-dashboard-shot]');
        const reveal = (shot) => shot.classList.add('is-revealed');

        if (!('IntersectionObserver' in window)) {
            shots.forEach(reveal);
            return;
        }

        // Watch the figure, not the image: the image's own clip-path hides it from the observer.
        const observer = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (!entry.isIntersecting) return;
                entry.target.querySelectorAll('[data-dashboard-shot]').forEach(reveal);
                observer.unobserve(entry.target);
            });
        }, { threshold: 0.2, rootMargin: '0px 0px -8% 0px' });

        shots.forEach((shot) => observer.observe(shot.parentElement || shot));
    })();
</script>
