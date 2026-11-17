const guide = document.querySelector('[data-calculation-guide]');

if (guide) {
    guide.querySelector('[data-guide-print]')?.addEventListener('click', () => window.print());

    const toggle = guide.querySelector('[data-guide-expand]');
    toggle?.addEventListener('click', () => {
        const expand = toggle.getAttribute('aria-expanded') !== 'true';
        guide.querySelectorAll('details').forEach((detail) => { detail.open = expand; });
        toggle.setAttribute('aria-expanded', String(expand));
        toggle.textContent = expand ? 'Collapse detailed working' : 'Expand detailed working';
    });

    let printState = [];
    window.addEventListener('beforeprint', () => {
        printState = Array.from(guide.querySelectorAll('details'), (detail) => [detail, detail.open]);
        printState.forEach(([detail]) => { detail.open = true; });
    });
    window.addEventListener('afterprint', () => {
        printState.forEach(([detail, open]) => { detail.open = open; });
    });

    if ('IntersectionObserver' in window) {
        const links = Array.from(guide.querySelectorAll('[data-guide-nav] a'));
        const observer = new IntersectionObserver((entries) => {
            for (const entry of entries) {
                if (!entry.isIntersecting) continue;
                links.forEach((link) => {
                    if (link.hash === `#${entry.target.id}`) link.setAttribute('aria-current', 'location');
                    else link.removeAttribute('aria-current');
                });
            }
        }, { rootMargin: '-12% 0px -65% 0px' });
        guide.querySelectorAll('[data-guide-section]').forEach((section) => observer.observe(section));
    }
}
