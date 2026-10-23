/**
 * Small motion helpers, all skipped when the visitor prefers reduced motion.
 *
 *  - `[data-countup="1234"]`: counts the number inside the element up to the
 *    given value. Optional `data-countup-decimals` and `data-countup-group`
 *    (thousands separator). The original text is restored when it finishes.
 *  - hero parallax: the showroom figure leans slightly away from the pointer
 *    (fine pointers only), through the --px / --py custom properties.
 */
const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

function formatNumber(value, decimals, group) {
    const [whole, fraction] = value.toFixed(decimals).split('.');
    const grouped = group ? whole.replace(/\B(?=(\d{3})+(?!\d))/g, group) : whole;

    return fraction ? `${grouped}${group === '.' ? ',' : '.'}${fraction}` : grouped;
}

/** Count one element's number up to its data-countup value. */
function countUp(element) {
    if (reducedMotion) return;

    const target = Number.parseFloat(element.dataset.countup);
    const original = element.textContent;
    const match = original.match(/[\d.,]+/);
    if (!match || Number.isNaN(target)) return;

    const decimals = Number.parseInt(element.dataset.countupDecimals ?? '0', 10);
    const group = element.dataset.countupGroup ?? '';
    const duration = 900;
    const start = performance.now();

    function frame(now) {
        const progress = Math.min(1, (now - start) / duration);
        const eased = 1 - (1 - progress) ** 3;

        element.textContent = progress === 1
            ? original
            : original.replace(match[0], formatNumber(target * eased, decimals, group));

        if (progress < 1) requestAnimationFrame(frame);
    }

    requestAnimationFrame(frame);
}

function initCountUp() {
    document.querySelectorAll('[data-countup]').forEach(countUp);
}

function initHeroParallax() {
    const hero = document.querySelector('.hero');
    if (!hero || !window.matchMedia('(hover: hover) and (pointer: fine)').matches) return;

    window.addEventListener('pointermove', (event) => {
        const x = event.clientX / window.innerWidth - 0.5;
        const y = event.clientY / window.innerHeight - 0.5;

        hero.style.setProperty('--px', x.toFixed(3));
        hero.style.setProperty('--py', y.toFixed(3));
    }, { passive: true });
}

window.rentalMotion = { countUp };

document.addEventListener('DOMContentLoaded', () => {
    if (reducedMotion) return;

    initCountUp();
    initHeroParallax();
});
