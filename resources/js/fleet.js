/**
 * Fleet browser: filters the vehicle cards by body type without leaving the
 * scene. The cards themselves are server-rendered, so with JavaScript off the
 * whole fleet simply stays visible.
 */
function initFleet() {
    const panel = document.querySelector('.fleet-panel');
    if (!panel) return;

    const chips = [...panel.querySelectorAll('[data-fleet-filter]')];
    const cards = [...panel.querySelectorAll('[data-fleet-card]')];
    const counter = panel.querySelector('[data-fleet-count]');

    function apply(type) {
        let visible = 0;

        cards.forEach((card) => {
            const show = type === '' || card.dataset.body === type;
            card.hidden = !show;
            if (show) visible += 1;
        });

        chips.forEach((chip) => {
            const active = chip.dataset.fleetFilter === type;
            chip.classList.toggle('is-active', active);
            chip.setAttribute('aria-pressed', String(active));
        });

        if (counter) counter.textContent = counter.dataset.template.replace(':count', String(visible));
    }

    chips.forEach((chip) => chip.addEventListener('click', () => apply(chip.dataset.fleetFilter)));
}

document.addEventListener('DOMContentLoaded', initFleet);
