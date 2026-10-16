/**
 * AI Rental Agent chat panel. Vanilla JS, no framework: sends customer messages to
 * the Laravel backend and renders both plain text and the structured
 * ui_payload the AI's tools attach (vehicle cards, price quotes, booking
 * confirmations, handover notices) as real DOM, not markdown-in-a-bubble.
 */
function initAgent() {
    const root = document.getElementById('agent-app');
    const widget = document.querySelector('[data-chat-widget]');
    if (!root || !widget) return;

    const config = {
        startUrl: root.dataset.startUrl,
        messageUrl: root.dataset.messageUrl,
        historyUrl: root.dataset.historyUrl,
        storageKey: root.dataset.storageKey,
        locale: root.dataset.locale,
        currency: root.dataset.currency,
        showroomUrl: root.dataset.showroomUrl,
        vehicleUrlTemplate: root.dataset.vehicleUrl,
        staffUrl: root.dataset.staffUrl,
        reservationUrlTemplate: root.dataset.reservationUrl,
        labels: {
            placeholder: root.dataset.labelPlaceholder,
            send: root.dataset.labelSend,
            open: root.dataset.labelOpen,
            close: root.dataset.labelClose,
            intro: root.dataset.labelIntro,
            insuranceIncluded: root.dataset.labelInsuranceIncluded,
            maxPassengers: root.dataset.labelMaxPassengers,
            noInsurance: root.dataset.labelNoInsurance,
            day: root.dataset.labelDay,
            noAvailability: root.dataset.labelNoAvailability,
            bookingReceived: root.dataset.labelBookingReceived,
            reference: root.dataset.labelReference,
            thinking: root.dataset.labelThinking,
            handedOver: root.dataset.labelHandedOver,
            statusSent: root.dataset.labelStatusSent,
            statusWaiting: root.dataset.labelStatusWaiting,
            statusReplied: root.dataset.labelStatusReplied,
            draftTitle: root.dataset.labelDraftTitle,
            draftBody: root.dataset.labelDraftBody,
            draftKeep: root.dataset.labelDraftKeep,
            draftDiscard: root.dataset.labelDraftDiscard,
            viewDetails: root.dataset.labelViewDetails,
            vehicleDetailsQuestion: root.dataset.labelVehicleDetailsQuestion,
            bookNow: root.dataset.labelBookNow,
            menuHeading: root.dataset.labelMenuHeading,
            staff: root.dataset.labelStaff,
            error: root.dataset.labelError,
            slow: root.dataset.labelSlow,
            retry: root.dataset.labelRetry,
        },
    };

    const messagesEl = root.querySelector('[data-messages]');
    const formEl = root.querySelector('[data-chat-form]');
    const inputEl = root.querySelector('[data-chat-input]');
    const submitEl = root.querySelector('[data-chat-submit]');
    const statusBanner = root.querySelector('[data-status-banner]');
    const chatStatus = root.querySelector('[data-chat-status]');
    const thinkingIndicator = root.querySelector('[data-thinking-indicator]');
    const draftDialog = root.querySelector('[data-draft-dialog]');
    const draftKeep = root.querySelector('[data-draft-keep]');
    const draftDiscard = root.querySelector('[data-draft-discard]');
    const chatLog = root.querySelector('#agent-chat-log');
    const closeButton = widget.querySelector('[data-chat-close]');
    const launcher = widget.querySelector('[data-chat-launcher]');
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

    let ready = false;
    let busy = false;
    let handedOver = false;
    let knownMessageCount = 0;
    let pollTimer = null;
    let isOpen = false;
    let draftDialogOpen = false;

    function setChatStatus(text, tone = '') {
        if (!chatStatus) return;

        chatStatus.textContent = text || '';
        chatStatus.className = `chat-status${text ? ` is-${tone}` : ''}`;
        chatStatus.hidden = !text;
    }

    function showDraftDialog() {
        if (!draftDialog) return;

        draftDialogOpen = true;
        draftDialog.hidden = false;
        draftDialog.setAttribute('aria-hidden', 'false');
        requestAnimationFrame(() => draftKeep?.focus());
    }

    function hideDraftDialog({ focusInput = false } = {}) {
        if (!draftDialog) return;

        draftDialogOpen = false;
        draftDialog.hidden = true;
        draftDialog.setAttribute('aria-hidden', 'true');
        if (focusInput) inputEl.focus();
    }

    function syncPanelState() {
        root.classList.toggle('is-open', isOpen);
        root.dataset.chatState = isOpen ? 'open' : 'closed';
        chatLog?.setAttribute('aria-hidden', String(!isOpen));
        formEl?.setAttribute('aria-hidden', String(!isOpen));
        launcher?.setAttribute('aria-expanded', String(isOpen));
    }

    function openPanel({ focus = false } = {}) {
        isOpen = true;
        syncPanelState();
        scrollToBottom();

        if (focus) {
            requestAnimationFrame(() => inputEl.focus());
        }
    }

    function finishClosePanel({ restoreFocus = true } = {}) {
        isOpen = false;
        syncPanelState();
        hideDraftDialog();
        if (restoreFocus) launcher?.focus();
    }

    function closePanel({ confirmDraft = true, restoreFocus = true } = {}) {
        if (confirmDraft && inputEl?.value.trim()) {
            showDraftDialog();
            return;
        }

        finishClosePanel({ restoreFocus });
    }

    closeButton?.addEventListener('click', () => closePanel());
    launcher?.addEventListener('click', () => openPanel({ focus: true }));
    draftKeep?.addEventListener('click', () => hideDraftDialog({ focusInput: true }));
    draftDiscard?.addEventListener('click', () => {
        inputEl.value = '';
        finishClosePanel();
    });
    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') return;

        if (draftDialogOpen) {
            hideDraftDialog({ focusInput: true });
            return;
        }

        if (isOpen) closePanel();
    });
    syncPanelState();

    function titleCase(value) {
        return String(value ?? '').replace(/_/g, ' ').replace(/^./, (c) => c.toUpperCase());
    }

    function money(value) {
        const n = Number(value) || 0;
        const numberLocale = config.locale === 'ja' ? 'ja-JP' : config.locale === 'en' ? 'en-US' : 'id-ID';
        return `${config.currency} ${new Intl.NumberFormat(numberLocale, { maximumFractionDigits: 0 }).format(n)}`;
    }

    // Cloudflare drops a request that is still unanswered after 100s, so give
    // up just before that and let the customer retry instead of waiting forever.
    const API_TIMEOUT_MS = 95000;

    async function api(url, body) {
        let response;

        try {
            response = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrfToken || '',
                },
                body: JSON.stringify(body || {}),
                signal: AbortSignal.timeout(API_TIMEOUT_MS),
            });
        } catch (err) {
            const error = new Error(err.name === 'TimeoutError' ? 'Request timed out' : 'Network error');
            error.status = err.name === 'TimeoutError' ? 408 : 0;
            throw error;
        }

        if (!response.ok) {
            const payload = await response.json().catch(() => ({}));
            const error = new Error(payload.message || `Request failed (${response.status})`);
            error.status = response.status;
            throw error;
        }

        return response.json();
    }

    function avatarMark() {
        const el = document.createElement('span');
        el.className = 'chat-mark';
        el.textContent = 'AI';
        el.setAttribute('aria-hidden', 'true');
        return el;
    }

    function bubble(role, text) {
        const wrap = document.createElement('div');
        wrap.className =
            role === 'customer' ? 'flex justify-end' : 'flex items-end justify-start gap-2';

        if (role !== 'customer') {
            wrap.appendChild(avatarMark());
        }

        const inner = document.createElement('div');
        inner.className =
            role === 'customer'
                ? 'max-w-[85%] rounded-2xl rounded-br-sm bg-slate-900 px-3 py-2 text-sm text-white'
                : 'max-w-[85%] rounded-2xl rounded-bl-sm bg-slate-100 px-3 py-2 text-sm text-slate-900';
        inner.textContent = text;

        wrap.appendChild(inner);
        return wrap;
    }

    function card(children) {
        const wrap = document.createElement('div');
        wrap.className = 'flex justify-start';
        const inner = document.createElement('div');
        inner.className = 'w-full max-w-[92%] space-y-2';
        children.forEach((c) => inner.appendChild(c));
        wrap.appendChild(inner);
        return wrap;
    }

    function vehicleResultCard(vehicle) {
        const el = document.createElement('div');
        el.className = 'overflow-hidden rounded-xl border border-slate-200 bg-white';
        el.innerHTML = `
            ${vehicle.thumbnail_url ? `<img src="${vehicle.thumbnail_url}" alt="${vehicle.name}" class="h-32 w-full object-cover">` : ''}
            <div class="p-3">
                <p class="font-medium text-slate-900">${vehicle.name}</p>
                <p class="mt-0.5 text-xs text-slate-500">${vehicle.seats} ${config.labels.maxPassengers} · ${vehicle.luggage_capacity} 🧳 · ${titleCase(vehicle.transmission)}${vehicle.insurance_included ? ` · ${config.labels.insuranceIncluded}` : ''}</p>
                <p class="mt-1 text-sm font-semibold text-slate-900">${money(vehicle.total_price)} <span class="font-normal text-slate-500">/ ${vehicle.days} ${config.labels.day}</span></p>
                <button type="button" class="mt-2 w-full rounded-lg border border-slate-300 px-2 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50" data-detail-slug="${vehicle.vehicle_slug}">
                    ${config.labels.viewDetails}
                </button>
            </div>
        `;
        el.querySelector('[data-detail-slug]').addEventListener('click', () => {
            sendMessage(config.labels.vehicleDetailsQuestion.replace(':vehicle', vehicle.name));
        });
        return el;
    }

    function vehicleDetailCard(vehicle) {
        const el = document.createElement('div');
        el.className = 'overflow-hidden rounded-xl border border-slate-200 bg-white';

        const images = (vehicle.images || [])
            .slice(0, 4)
            .map((img) => `<img src="${img.url}" alt="${img.alt || vehicle.name}" class="h-20 w-full rounded-lg object-cover">`)
            .join('');

        el.innerHTML = `
            <div class="p-3">
                <p class="font-medium text-slate-900">${vehicle.name}</p>
                <p class="mt-1 text-xs text-slate-500">${vehicle.description || ''}</p>
                <div class="mt-2 grid grid-cols-4 gap-1">${images}</div>
                <dl class="mt-2 grid grid-cols-2 gap-1 text-xs text-slate-500">
                    <div>${vehicle.seats} ${config.labels.maxPassengers} · ${vehicle.luggage_capacity} 🧳</div>
                    <div>${titleCase(vehicle.transmission)} · ${titleCase(vehicle.fuel_type)}</div>
                    <div>${vehicle.insurance_included ? config.labels.insuranceIncluded : config.labels.noInsurance}</div>
                    <div>${[vehicle.body_type ? titleCase(vehicle.body_type) : '', vehicle.model_year || ''].filter(Boolean).join(' · ')}</div>
                </dl>
            </div>
        `;
        return el;
    }

    function availabilityCard(payload) {
        const el = document.createElement('div');
        el.className = 'rounded-xl border border-slate-200 bg-white p-3';

        if (!payload.available) {
            el.innerHTML = `<p class="text-sm text-slate-600">${config.labels.noAvailability}</p>`;
            return el;
        }

        const q = payload.quote;
        el.innerHTML = `
            <p class="font-medium text-slate-900">${q.name}</p>
            <p class="text-xs text-slate-500">${q.pickup_date} → ${q.return_date} · ${q.days} ${config.labels.day}</p>
            <p class="mt-1 text-base font-semibold text-slate-900">${money(q.grand_total)}</p>
        `;
        return el;
    }

    function bookingConfirmationCard(payload) {
        const b = payload.booking;
        const el = document.createElement('div');
        el.className = 'rounded-xl border border-emerald-200 bg-emerald-50 p-3';
        el.innerHTML = `
            <p class="text-sm font-semibold text-emerald-900">${config.labels.bookingReceived}</p>
            <p class="mt-1 text-xs text-emerald-800">${config.labels.reference}: ${b.booking_reference} · ${b.name}</p>
            <p class="text-xs text-emerald-800">${b.pickup_date} → ${b.return_date} · ${b.days} ${config.labels.day}</p>
            <p class="mt-1 text-sm font-semibold text-emerald-900">${money(b.total_price)}</p>
        `;
        return el;
    }

    function handoverCard(payload) {
        const el = document.createElement('div');
        el.className = 'rounded-xl border border-orange-200 bg-orange-50 p-3 text-xs text-orange-800';
        el.textContent = config.labels.handedOver;
        return el;
    }

    function renderUiPayload(uiPayload) {
        if (!uiPayload || !uiPayload.length) return;

        uiPayload.forEach((payload) => {
            if (payload.type === 'vehicle_results') {
                messagesEl.appendChild(card(payload.vehicles.map(vehicleResultCard)));
            } else if (payload.type === 'vehicle_detail') {
                messagesEl.appendChild(card([vehicleDetailCard(payload.vehicle)]));
            } else if (payload.type === 'availability') {
                messagesEl.appendChild(card([availabilityCard(payload)]));
            } else if (payload.type === 'booking_confirmation') {
                messagesEl.appendChild(card([bookingConfirmationCard(payload)]));
            } else if (payload.type === 'handover') {
                messagesEl.appendChild(card([handoverCard(payload)]));
            }
        });
    }

    function scrollToBottom() {
        messagesEl.scrollTop = messagesEl.scrollHeight;
    }

    function setBusy(value) {
        busy = value;
        const disabled = busy || !ready || handedOver;
        inputEl.disabled = disabled;
        submitEl.disabled = disabled;
        if (launcher) launcher.disabled = busy || !ready;
        document.querySelectorAll('[data-ask-form] input, [data-ask-form] button').forEach((el) => { el.disabled = disabled; });
        root.setAttribute('aria-busy', String(busy));
        document.body.classList.toggle('is-thinking', busy);
        if (thinkingIndicator) {
            thinkingIndicator.hidden = !busy;
        }
        document.querySelectorAll('[data-hero-quick-message], [data-ask-ai-button], [data-quick-message]')
            .forEach((button) => { button.disabled = disabled; });
        submitEl.textContent = busy ? config.labels.thinking : config.labels.send;
        if (busy) scrollToBottom();
    }

    function showHandedOverBanner() {
        handedOver = true;
        statusBanner.textContent = config.labels.handedOver;
        statusBanner.classList.remove('hidden');
        setChatStatus(config.labels.statusWaiting, 'waiting');
        setBusy(busy);
        startPolling();
    }

    function clearHandedOverBanner() {
        handedOver = false;
        statusBanner.classList.add('hidden');
        setBusy(busy);
        stopPolling();
    }

    function startPolling() {
        if (pollTimer) return;
        pollTimer = setInterval(pollForStaffReplies, 4000);
    }

    function stopPolling() {
        if (pollTimer) {
            clearInterval(pollTimer);
            pollTimer = null;
        }
    }

    async function pollForStaffReplies() {
        const customerToken = localStorage.getItem(config.storageKey);
        if (!customerToken) return;

        try {
            const response = await fetch(`${config.historyUrl}?customer_token=${encodeURIComponent(customerToken)}`, {
                headers: { Accept: 'application/json' },
            });
            if (!response.ok) return;

            const data = await response.json();

            if (data.messages.length > knownMessageCount) {
                const newMessages = data.messages.slice(knownMessageCount);
                newMessages.forEach(renderMessage);
                if (newMessages.some((message) => message.role === 'staff')) {
                    setChatStatus(config.labels.statusReplied, 'replied');
                }
                window.rentalSound?.play('incoming');
                knownMessageCount = data.messages.length;
                scrollToBottom();
            }

            if (data.status !== 'handed_over') {
                clearHandedOverBanner();
            }
        } catch {
            // transient network hiccup — try again on the next tick
        }
    }

    function renderMessage(message) {
        if (message.role === 'customer') {
            messagesEl.appendChild(bubble('customer', message.content));
        } else if (message.role === 'assistant') {
            if (message.content) {
                messagesEl.appendChild(bubble('assistant', message.content));
            }
            renderUiPayload(message.ui_payload);
            const chips = actionChips(message.suggested_actions);
            if (chips) messagesEl.appendChild(chips);
        } else if (message.role === 'staff') {
            messagesEl.appendChild(card([staffBubble(message.content)]));
            setChatStatus(config.labels.statusReplied, 'replied');
        } else if (message.role === 'system') {
            messagesEl.appendChild(bubble('assistant', message.content));
        }
    }

    function quickMenuCard() {
        const template = document.querySelector('[data-quick-menu-items]');
        if (!template) return null;

        const el = document.createElement('div');
        el.className = 'overflow-hidden rounded-xl border border-slate-200 bg-white';

        const heading = document.createElement('p');
        heading.className = 'border-b border-slate-100 px-3 py-2 text-xs font-semibold uppercase tracking-wide text-slate-400';
        heading.textContent = config.labels.menuHeading;
        el.appendChild(heading);

        const list = document.createElement('div');
        list.className = 'divide-y divide-slate-100';

        template.content.querySelectorAll('[data-quick-message]').forEach((source) => {
            const item = document.createElement('button');
            item.type = 'button';
            item.className = 'flex w-full items-center justify-between px-3 py-2.5 text-left text-sm text-slate-700 hover:bg-orange-50';
            item.innerHTML = `<span>${source.textContent}</span><span class="text-slate-300">›</span>`;
            item.addEventListener('click', () => sendMessage(source.dataset.quickMessage));
            list.appendChild(item);
        });

        el.appendChild(list);
        return el;
    }

    function staffBubble(text) {
        const el = document.createElement('div');
        el.className = 'rounded-2xl rounded-bl-sm border border-sky-200 bg-sky-50 px-3 py-2 text-sm text-sky-900';
        el.innerHTML = `<p class="mb-0.5 text-[10px] font-semibold uppercase tracking-wide text-sky-500">${config.labels.staff}</p>${text}`;
        return el;
    }

    const sceneNames = { home: 'reception', showroom: 'reception', info: 'reception', vehicles: 'vehicles', vehicle: 'vehicle_detail', services: 'services', service: 'service_detail', reservation: 'reservation', staff: 'handover' };

    /**
     * Where the customer is in the showroom, so the agent can answer for "this
     * vehicle" or the reservation on screen. Never includes name or contact data.
     */
    function uiContext() {
        const pageScene = document.querySelector('[data-showroom]')?.dataset.scene || 'showroom';
        const context = { scene: sceneNames[pageScene] || 'reception' };

        const lastSegment = decodeURIComponent(window.location.pathname.split('/').pop());
        if (pageScene === 'vehicle') context.selected_vehicle = lastSegment;
        if (pageScene === 'service') context.selected_service = Number(lastSegment);

        if (pageScene === 'reservation') {
            try {
                const draft = JSON.parse(sessionStorage.getItem(`reservation_draft_${root.dataset.rentalSlug}`) || 'null');
                const { pickup_date, return_date, passengers, units, vehicle_slug } = draft?.values || {};
                context.reservation = { pickup_date, return_date, passengers, units, vehicle_slug };
                if (vehicle_slug) context.selected_vehicle = vehicle_slug;
            } catch { /* no draft to share */ }
        }

        return context;
    }

    function actionChips(actions) {
        if (!actions || !actions.length) return null;

        const vehicleUrl = (slug) => config.vehicleUrlTemplate.replace('__SLUG__', encodeURIComponent(slug));
        const reservationUrl = (slug) => config.reservationUrlTemplate.replace('__SLUG__', encodeURIComponent(slug));
        const targets = {
            view_vehicle: [config.labels.viewDetails, (action) => vehicleUrl(action.vehicle)],
            reserve: [config.labels.bookNow, (action) => reservationUrl(action.vehicle)],
            staff: [config.labels.staff, () => config.staffUrl],
        };

        const wrap = document.createElement('div');
        wrap.className = 'flex flex-wrap gap-2 pl-10';

        actions.forEach((action) => {
            const target = targets[action.action];
            if (!target) return;
            const link = document.createElement('a');
            link.href = target[1](action);
            link.className = 'rounded-full border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50';
            link.textContent = target[0];
            link.addEventListener('click', (event) => {
                closePanel({ confirmDraft: false, restoreFocus: false });

                // Another page: walk there the way the menu does. A panel on
                // this page is just a hash change, so leave it to the browser.
                if (link.pathname !== window.location.pathname && window.rentalStage) {
                    event.preventDefault();
                    window.rentalStage.leave(link.href);
                }
            });
            wrap.appendChild(link);
        });

        return wrap.childElementCount ? wrap : null;
    }

    function errorCard(text, err) {
        const el = document.createElement('div');
        el.className = 'rounded-xl border border-rose-200 bg-rose-50 p-3 text-sm text-rose-900';

        const message = document.createElement('p');
        message.textContent = err.status === 429 ? config.labels.slow : config.labels.error;

        const actions = document.createElement('div');
        actions.className = 'mt-3 flex flex-wrap gap-2';

        const retry = document.createElement('button');
        retry.type = 'button';
        retry.className = 'rounded-full bg-slate-900 px-3 py-1.5 text-xs font-medium text-white';
        retry.textContent = config.labels.retry;
        retry.addEventListener('click', () => {
            el.remove();
            sendMessage(text, { retry: true });
        });

        const staff = document.createElement('a');
        staff.href = config.staffUrl;
        staff.className = 'rounded-full border border-rose-300 bg-white px-3 py-1.5 text-xs font-medium text-rose-900';
        staff.textContent = config.labels.staff;
        staff.addEventListener('click', (event) => {
            closePanel({ confirmDraft: false, restoreFocus: false });

            if (window.rentalStage) {
                event.preventDefault();
                window.rentalStage.leave(staff.href);
            }
        });

        actions.append(retry, staff);
        el.append(message, actions);
        return el;
    }

    const REPLY_POLL_MS = 1500;
    const REPLY_WAIT_MS = 125000;

    /**
     * The agent answers off the web request: wait here until the conversation
     * reports a finished reply, then show whatever the server added since.
     */
    async function receiveQueuedReply(customerToken) {
        const deadline = Date.now() + REPLY_WAIT_MS;

        while (Date.now() < deadline) {
            await new Promise((resolve) => setTimeout(resolve, REPLY_POLL_MS));

            let data;
            try {
                const response = await fetch(`${config.historyUrl}?customer_token=${encodeURIComponent(customerToken)}`, {
                    headers: { Accept: 'application/json' },
                });
                if (response.status === 429) continue;
                if (!response.ok) continue;
                data = await response.json();
            } catch {
                continue; // transient network hiccup — keep waiting
            }

            if (data.reply_status === 'processing') continue;

            if (data.reply_status === 'failed') {
                const error = new Error('agent_unavailable');
                error.status = 503;
                throw error;
            }

            // The customer's own message is already on screen; show the rest.
            data.messages.slice(knownMessageCount).filter((message) => message.role !== 'customer').forEach(renderMessage);
            knownMessageCount = data.messages.length;

            if (data.status === 'handed_over') {
                showHandedOverBanner();
            }

            return;
        }

        const error = new Error('Request timed out');
        error.status = 408;
        throw error;
    }

    async function sendMessage(text, { retry = false } = {}) {
        if (!text.trim() || handedOver || busy || !ready) return;

        const customerToken = localStorage.getItem(config.storageKey);
        if (!customerToken) return;

        openPanel();
        if (!retry) messagesEl.appendChild(bubble('customer', text));
        window.rentalSound?.play('sent');
        scrollToBottom();
        setBusy(true);

        try {
            const data = await api(config.messageUrl, { customer_token: customerToken, message: text, ...uiContext() });

            if (data.pending) {
                await receiveQueuedReply(customerToken);
            } else {
                renderMessage(data.message);
                knownMessageCount += 2; // the customer message just sent + the reply just rendered
                if (data.status === 'handed_over') {
                    showHandedOverBanner();
                }
            }

            setChatStatus(config.labels.statusSent, 'sent');
            window.rentalSound?.play('incoming');
        } catch (err) {
            messagesEl.appendChild(errorCard(text, err));
        } finally {
            setBusy(false);
            scrollToBottom();
        }
    }

    async function boot() {
        let customerToken = localStorage.getItem(config.storageKey);

        if (!customerToken) {
            const data = await api(config.startUrl, { locale: config.locale });
            customerToken = data.customer_token;
            localStorage.setItem(config.storageKey, customerToken);
            messagesEl.appendChild(bubble('assistant', config.labels.intro));
            const menu = quickMenuCard();
            if (menu) messagesEl.appendChild(card([menu]));
            return;
        }

        try {
            const response = await fetch(`${config.historyUrl}?customer_token=${encodeURIComponent(customerToken)}`, {
                headers: { Accept: 'application/json' },
            });

            if (!response.ok) throw new Error('history_unavailable');

            const data = await response.json();

            if (!data.messages.length) {
                messagesEl.appendChild(bubble('assistant', config.labels.intro));
                const menu = quickMenuCard();
                if (menu) messagesEl.appendChild(card([menu]));
            } else {
                data.messages.forEach(renderMessage);
            }
            knownMessageCount = data.messages.length;

            if (data.status === 'handed_over') {
                showHandedOverBanner();
            }
            if (data.messages.some((message) => message.role === 'staff')) {
                setChatStatus(config.labels.statusReplied, 'replied');
            }

            // The page was reloaded while the agent was still answering.
            if (data.reply_status === 'processing') {
                await receiveQueuedReply(customerToken).catch(() => {});
            }
        } catch {
            localStorage.removeItem(config.storageKey);
            return boot();
        }

        scrollToBottom();
    }

    formEl.addEventListener('submit', (event) => {
        event.preventDefault();
        if (!ready || busy || handedOver || !inputEl.value.trim()) return;
        const text = inputEl.value;
        inputEl.value = '';
        sendMessage(text);
    });

    document.querySelectorAll('[data-ask-ai-button]').forEach((button) => {
        button.addEventListener('click', () => {
            const vehicleCard = button.closest('[data-vehicle-card]');
            const vehicleName = vehicleCard?.dataset.vehicleName || '';
            openPanel();
            sendMessage(`Tell me more about the ${vehicleName}`);
        });
    });

    // Hero quick-start menu — same topics as the in-chat menu, always
    // visible up front so passengers see what the AI can do immediately.
    document.querySelectorAll('[data-hero-quick-message]').forEach((button) => {
        button.addEventListener('click', () => {
            openPanel();
            sendMessage(button.dataset.heroQuickMessage);
        });
    });

    // A menu pick in the same scene: the agent answers right where the customer stands.
    window.addEventListener('agent:ask', (event) => sendMessage(event.detail.message));

    setBusy(true);
    boot().then(() => {
        ready = true;
        scrollToBottom();
    }).catch(() => {
        statusBanner.textContent = root.dataset.labelConnectionError;
        statusBanner.classList.remove('hidden');
        messagesEl.appendChild(bubble('assistant', config.labels.intro));
    }).finally(() => {
        setBusy(false);

        // The customer chose this topic from the menu on the previous scene:
        // let the new scene settle for a beat, then carry on the conversation.
        const topic = window.takePendingAgentTopic?.();
        if (topic && ready) window.setTimeout(() => sendMessage(topic), 650);
    });
}

document.addEventListener('DOMContentLoaded', initAgent);

function initVehicleGallery() {
    document.querySelectorAll('[data-vehicle-gallery]').forEach((gallery) => {
        const main = gallery.querySelector('[data-vehicle-gallery-main]');
        const thumbs = [...gallery.querySelectorAll('[data-vehicle-thumb]')];
        if (!main) return;

        thumbs.forEach((thumb) => {
            thumb.addEventListener('click', () => {
                main.src = thumb.dataset.src;
                main.alt = thumb.dataset.alt || '';
                thumbs.forEach((other) => other.toggleAttribute('aria-current', other === thumb));
            });
        });
    });
}

document.addEventListener('DOMContentLoaded', initVehicleGallery);
