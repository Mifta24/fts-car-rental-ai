/**
 * Site shell behaviour: the mobile navigation drawer, the header shadow once the
 * page scrolls, and the hand-over of a topic to the AI Rental Agent.
 *
 * A link may carry `data-topic`: the question the customer picked from the chat
 * shortcuts. It is handed to the agent, which raises it once the next page is
 * up (or straight away when the customer is already on that page).
 */
const TOPIC_KEY = 'agent_pending_topic';

function initShell() {
    const header = document.querySelector('[data-site-header]');
    const toggle = document.querySelector('[data-nav-toggle]');

    if (header) {
        const onScroll = () => header.classList.toggle('is-scrolled', window.scrollY > 8);
        onScroll();
        window.addEventListener('scroll', onScroll, { passive: true });
    }

    if (toggle) {
        const setOpen = (open) => {
            document.body.classList.toggle('nav-open', open);
            toggle.setAttribute('aria-expanded', String(open));
        };

        toggle.addEventListener('click', () => setOpen(!document.body.classList.contains('nav-open')));
        document.querySelectorAll('.site-nav a').forEach((link) => link.addEventListener('click', () => setOpen(false)));
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') setOpen(false);
        });
    }

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
