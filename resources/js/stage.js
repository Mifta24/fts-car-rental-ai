/**
 * Stage behaviour: the whole site is one fixed screen, so this script drives
 * the parts that replace scrolling.
 *
 *  - decks: one page at a time (a car, a few services, a few FAQs), moved with
 *    arrows, dots, keys or a swipe. A deck marked `data-deck-narrates` makes
 *    the agent talk about the page now in view.
 *  - the ask dock: the always-visible message bar that talks to the agent.
 *  - topic hand-over: a link may carry `data-topic`, the question the customer
 *    picked from the chat shortcuts; the agent raises it once the next page is up.
 */
const TOPIC_KEY = 'agent_pending_topic';

function initDecks() {
    document.querySelectorAll('[data-deck]').forEach((deck) => {
        const pages = [...deck.querySelectorAll('[data-deck-page]')];
        const bar = deck.querySelector('[data-deck-bar]');
        const dotsBox = deck.querySelector('[data-deck-dots]');
        const counter = deck.querySelector('[data-deck-count]');
        const viewport = deck.querySelector('.deck-viewport');
        let current = 0;

        if (pages.length < 2) {
            if (bar) bar.hidden = true;
            deck.dataset.ready = '';
            return;
        }

        const dots = pages.map((page, index) => {
            const dot = document.createElement('button');
            dot.type = 'button';
            dot.className = 'deck-dot';
            dot.setAttribute('aria-label', String(index + 1));
            dot.addEventListener('click', () => show(index));
            dotsBox?.appendChild(dot);
            return dot;
        });

        function show(index, direction = null) {
            const next = (index + pages.length) % pages.length;
            if (direction === null) direction = next >= current ? 'next' : 'prev';

            pages.forEach((page, i) => {
                page.hidden = i !== next;
                page.classList.remove('is-entering-next', 'is-entering-prev');
            });

            if (next !== current || deck.dataset.ready === 'true') {
                pages[next].classList.add(`is-entering-${direction}`);
            }

            dots.forEach((dot, i) => dot.toggleAttribute('aria-current', i === next));
            if (counter) counter.textContent = `${next + 1} / ${pages.length}`;

            const changed = next !== current;
            current = next;

            if (changed && deck.hasAttribute('data-deck-narrates') && pages[next].dataset.narration) {
                window.dispatchEvent(new CustomEvent('narrator:say', { detail: { text: pages[next].dataset.narration } }));
            }
        }

        deck.querySelector('[data-deck-prev]')?.addEventListener('click', () => show(current - 1, 'prev'));
        deck.querySelector('[data-deck-next]')?.addEventListener('click', () => show(current + 1, 'next'));

        // Swipe on touch screens.
        let startX = null;
        viewport?.addEventListener('pointerdown', (event) => { if (event.pointerType === 'touch') startX = event.clientX; });
        viewport?.addEventListener('pointerup', (event) => {
            if (startX === null) return;
            const delta = event.clientX - startX;
            startX = null;
            if (Math.abs(delta) > 50) show(current + (delta < 0 ? 1 : -1), delta < 0 ? 'next' : 'prev');
        });
        viewport?.addEventListener('pointercancel', () => { startX = null; });

        // Arrow keys move the deck that is on screen, unless the customer is typing.
        document.addEventListener('keydown', (event) => {
            if (event.defaultPrevented || event.altKey || event.ctrlKey || event.metaKey) return;
            if (!['ArrowLeft', 'ArrowRight'].includes(event.key)) return;
            if (!deck.getClientRects().length || event.target.closest?.('input, textarea, select, [contenteditable]')) return;

            event.preventDefault();
            show(current + (event.key === 'ArrowRight' ? 1 : -1), event.key === 'ArrowRight' ? 'next' : 'prev');
        });

        deck.dataset.ready = 'true';
        show(0);
        pages.slice(1).forEach((page) => { page.hidden = true; });
    });
}

function initAskDock() {
    const form = document.querySelector('[data-ask-form]');
    if (!form) return;

    const input = form.querySelector('[data-ask-input]');

    form.addEventListener('submit', (event) => {
        event.preventDefault();

        const message = input.value.trim();
        if (!message || input.disabled) return;

        input.value = '';
        window.dispatchEvent(new CustomEvent('agent:ask', { detail: { message } }));
    });
}

function initShell() {
    /** Walk the customer to `href`, remembering the topic the agent should pick up there. */
    function leave(href, topic = null) {
        try {
            if (topic) sessionStorage.setItem(TOPIC_KEY, topic);
            else sessionStorage.removeItem(TOPIC_KEY);
        } catch { /* private mode: the page still changes, only the topic is dropped */ }

        window.location.href = href;
    }

    document.querySelectorAll('[data-topic]').forEach((link) => {
        link.addEventListener('click', (event) => {
            const modified = event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || event.button !== 0;
            if (event.defaultPrevented || modified || link.target === '_blank') return;

            event.preventDefault();

            // Already on this page: the agent just takes the topic up here.
            if (link.pathname === window.location.pathname) {
                window.dispatchEvent(new CustomEvent('agent:ask', { detail: { message: link.dataset.topic } }));
                return;
            }

            leave(link.href, link.dataset.topic);
        });
    });

    window.rentalStage = { leave: (href) => leave(href) };

    initDecks();
    initAskDock();
}

/** The topic the customer picked on the previous page, if any (read once). */
window.takePendingAgentTopic = () => {
    try {
        const topic = sessionStorage.getItem(TOPIC_KEY);
        sessionStorage.removeItem(TOPIC_KEY);
        return topic;
    } catch {
        return null;
    }
};

document.addEventListener('DOMContentLoaded', initShell);
