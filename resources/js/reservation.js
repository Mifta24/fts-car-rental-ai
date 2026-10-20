/**
 * Guided reservation wizard: five short steps in its own scene — dates,
 * passengers, vehicle, contact, summary — then a reference number and
 * WhatsApp / phone / email hand-over. Progress survives leaving the page and
 * switching language (sessionStorage), and every rule is enforced again on the
 * server, which is the only source of price and availability.
 */
function initReservationWizard() {
    const root = document.querySelector('[data-wizard]');
    if (!root) return;

    const labels = JSON.parse(root.querySelector('[data-wizard-labels]').textContent);
    const form = root.querySelector('[data-wizard-form]');
    const flow = root.querySelector('[data-wizard-flow]');
    const done = root.querySelector('[data-wizard-done]');
    const steps = [...root.querySelectorAll('[data-step]')];
    const progress = [...root.querySelectorAll('[data-progress-step]')];
    const stepLabel = root.querySelector('[data-wizard-step-label]');
    const daysHint = root.querySelector('[data-days-hint]');
    const backButton = root.querySelector('[data-wizard-back]');
    const nextButton = root.querySelector('[data-wizard-next]');
    const submitButton = root.querySelector('[data-wizard-submit]');
    const errorBox = root.querySelector('[data-wizard-error]');
    const errorText = root.querySelector('[data-wizard-error-text]');
    const alternativesBox = root.querySelector('[data-wizard-alternatives]');
    const vehicleOptions = [...root.querySelectorAll('[data-vehicle-option]')];
    const withDriverField = root.querySelector('[data-driver-field]');
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

    const config = {
        quoteUrl: root.dataset.quoteUrl,
        submitUrl: root.dataset.submitUrl,
        locale: root.dataset.locale,
        currency: root.dataset.currency,
        today: root.dataset.today,
        maxDays: Number(root.dataset.maxDays),
        draftKey: `reservation_draft_${root.dataset.rentalSlug}`,
        tokenKey: `agent_token_${root.dataset.rentalSlug}`,
    };

    const fieldStep = {
        pickup_date: 1, return_date: 1, passengers: 2, units: 2, vehicle_slug: 3, with_driver: 3, addons: 3, promo_code: 3,
        customer_name: 4, contact_type: 4, contact_value: 4, special_request: 4,
    };

    let current = 1;
    let quote = null;
    let busy = false;

    const text = (template, values) => Object.entries(values).reduce((out, [key, value]) => out.replace(`:${key}`, value), template);
    const money = (value) => `${config.currency} ${new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 }).format(Number(value) || 0)}`;

    function formatDate(iso) {
        const [year, month, day] = iso.split('-').map(Number);
        return new Intl.DateTimeFormat(config.locale, { day: 'numeric', month: 'long', year: 'numeric' }).format(new Date(year, month - 1, day));
    }

    function daysBetween(pickupDate, returnDate) {
        if (!pickupDate || !returnDate) return 0;
        const [y1, m1, d1] = pickupDate.split('-').map(Number);
        const [y2, m2, d2] = returnDate.split('-').map(Number);
        return Math.round((Date.UTC(y2, m2 - 1, d2) - Date.UTC(y1, m1 - 1, d1)) / 86400000);
    }

    function values() {
        const data = new FormData(form);
        return {
            pickup_date: data.get('pickup_date') || '',
            return_date: data.get('return_date') || '',
            passengers: Number(data.get('passengers')) || 0,
            units: Number(data.get('units')) || 0,
            vehicle_slug: data.get('vehicle_slug') || '',
            with_driver: form.elements.with_driver.checked && !withDriverField.hidden,
            addons: [...form.querySelectorAll('input[name="addons"]:checked')].map((box) => Number(box.value)),
            promo_code: (data.get('promo_code') || '').trim().toUpperCase(),
            customer_name: (data.get('customer_name') || '').trim(),
            contact_type: data.get('contact_type') || 'whatsapp',
            contact_value: (data.get('contact_value') || '').trim(),
            special_request: (data.get('special_request') || '').trim(),
        };
    }

    function saveDraft() {
        try {
            sessionStorage.setItem(config.draftKey, JSON.stringify({ step: current, values: values() }));
        } catch { /* storage unavailable: the wizard still works without it */ }
    }

    function clearDraft() {
        try {
            sessionStorage.removeItem(config.draftKey);
        } catch { /* nothing to clear */ }
    }

    function restoreDraft() {
        try {
            const draft = JSON.parse(sessionStorage.getItem(config.draftKey) || 'null');
            if (!draft) return;
            const { values: saved } = draft;
            ['pickup_date', 'return_date', 'passengers', 'units', 'customer_name', 'contact_value', 'special_request'].forEach((name) => {
                if (saved[name] !== undefined && saved[name] !== '' && saved[name] !== 0) form.elements[name].value = saved[name];
            });
            if (saved.contact_type) form.elements.contact_type.value = saved.contact_type;
            if (saved.vehicle_slug) form.elements.vehicle_slug.value = saved.vehicle_slug;
            form.elements.with_driver.checked = Boolean(saved.with_driver);
            form.querySelectorAll('input[name="addons"]').forEach((box) => { box.checked = (saved.addons || []).includes(Number(box.value)); });
            if (saved.promo_code && form.elements.promo_code) form.elements.promo_code.value = saved.promo_code;
            current = Math.min(Math.max(Number(draft.step) || 1, 1), steps.length);
        } catch { /* ignore a corrupt draft */ }
    }

    function clearError() {
        errorBox.hidden = true;
        errorText.textContent = '';
        alternativesBox.hidden = true;
        alternativesBox.querySelector('ul').replaceChildren();
    }

    function showError(message, alternatives = []) {
        errorText.textContent = message;
        errorBox.hidden = false;

        const list = alternativesBox.querySelector('ul');
        list.replaceChildren();
        alternatives.forEach((alternative) => {
            const item = document.createElement('li');
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'wizard-secondary';
            button.textContent = `${alternative.name} · ${money(alternative.total)}`;
            button.addEventListener('click', () => {
                form.elements.vehicle_slug.value = alternative.slug;
                refreshVehicles();
                clearError();
                saveDraft();
            });
            item.appendChild(button);
            list.appendChild(item);
        });
        alternativesBox.hidden = alternatives.length === 0;
    }

    function selectedOption() {
        const slug = form.elements.vehicle_slug.value;
        return vehicleOptions.find((option) => option.querySelector('input').value === slug) || null;
    }

    function fits(option, party) {
        return party.passengers <= Number(option.dataset.seats) * party.units;
    }

    function refreshVehicles() {
        const party = values();

        vehicleOptions.forEach((option) => {
            const input = option.querySelector('input');
            const suitable = fits(option, party);
            input.disabled = !suitable;
            option.classList.toggle('is-disabled', !suitable);
            option.querySelector('[data-vehicle-hint]').textContent = suitable ? '' : labels.too_small;
            if (!suitable && input.checked) input.checked = false;
        });

        const chosen = selectedOption();
        withDriverField.hidden = !chosen || chosen.dataset.driver !== '1';
        if (withDriverField.hidden) form.elements.with_driver.checked = false;
    }

    function refreshDays() {
        const days = daysBetween(form.elements.pickup_date.value, form.elements.return_date.value);
        daysHint.textContent = days > 0 ? `${days} ${labels.days}` : '';
    }

    function validate(step) {
        const data = values();

        if (step === 1) {
            if (!data.pickup_date || data.pickup_date < config.today) return { field: 'pickup_date', message: labels.pickup_date_past };
            if (!data.return_date || data.return_date <= data.pickup_date) return { field: 'return_date', message: labels.return_date_after };
            if (daysBetween(data.pickup_date, data.return_date) > config.maxDays) return { field: 'return_date', message: text(labels.too_long, { max: config.maxDays }) };
        }

        if (step === 2) {
            if (data.passengers < 1) return { field: 'passengers', message: labels.invalid };
            if (data.units < 1) return { field: 'units', message: labels.invalid };
        }

        if (step === 3) {
            const chosen = selectedOption();
            if (!chosen) return { field: 'vehicle_slug', message: labels.select_vehicle };
            if (!fits(chosen, data)) return { field: 'vehicle_slug', message: labels.too_small };
        }

        if (step === 4) {
            if (!data.customer_name) return { field: 'customer_name', message: labels.invalid };
            const valid = data.contact_type === 'email'
                ? /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(data.contact_value)
                : /^\+?[0-9\s\-().]{6,20}$/.test(data.contact_value);
            if (!valid) return { field: 'contact_value', message: data.contact_type === 'email' ? labels.contact_email : labels.contact_phone };
        }

        return null;
    }

    function setBusy(value, label = null) {
        busy = value;
        [backButton, nextButton, submitButton].forEach((button) => { button.disabled = value; });
        if (label) nextButton.firstChild.textContent = `${label} `;
        else nextButton.firstChild.textContent = `${labels.next} `;
    }

    function showStep(step, focus = false) {
        current = step;
        steps.forEach((element, index) => { element.hidden = index + 1 !== step; });
        progress.forEach((element, index) => {
            element.classList.toggle('is-current', index + 1 === step);
            element.classList.toggle('is-done', index + 1 < step);
            if (index + 1 === step) element.setAttribute('aria-current', 'step');
            else element.removeAttribute('aria-current');
        });
        stepLabel.textContent = `${text(labels.step_of, { current: step, total: steps.length })} · ${labels.steps[step - 1]}`;
        backButton.hidden = step === 1;
        nextButton.hidden = step === steps.length;
        submitButton.hidden = step !== steps.length;

        if (step === 2 || step === 3) refreshVehicles();
        if (step === 3 && vehicleOptions.every((option) => option.classList.contains('is-disabled'))) showError(labels.capacity);
        if (step === steps.length) renderSummary();
        if (focus) steps[step - 1].querySelector('input:not([disabled]), textarea')?.focus({ preventScroll: true });
        saveDraft();
    }

    function goToField(field) {
        showStep(fieldStep[field] || current, true);
    }

    async function post(url, payload) {
        const response = await fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken || '' },
            body: JSON.stringify(payload),
        });
        const body = await response.json().catch(() => ({}));
        return { ok: response.ok, status: response.status, body };
    }

    function tripPayload() {
        const { pickup_date, return_date, passengers, units, vehicle_slug, with_driver, addons, promo_code } = values();
        return { pickup_date, return_date, passengers, units, vehicle_slug, with_driver, addons, promo_code: promo_code || undefined, locale: config.locale };
    }

    function handleFailure(result) {
        if (result.status === 422 && result.body.errors) {
            const [field, messages] = Object.entries(result.body.errors)[0];
            showError(messages[0], result.body.alternatives || []);
            if (fieldStep[field] && fieldStep[field] !== current) showStep(fieldStep[field]);
            return;
        }
        showError(labels.error_generic);
    }

    async function next() {
        if (busy || current >= steps.length) return;
        clearError();

        const problem = validate(current);
        if (problem) {
            showError(problem.message);
            form.elements[problem.field]?.focus?.();
            return;
        }

        if (current === 3) {
            setBusy(true, labels.checking);
            try {
                const result = await post(config.quoteUrl, tripPayload());
                if (!result.ok) {
                    handleFailure(result);
                    return;
                }
                quote = result.body;
            } catch {
                showError(labels.error_network);
                return;
            } finally {
                setBusy(false);
            }
        }

        showStep(current + 1, true);
    }

    function summaryRow(label, value, step) {
        const wrapper = document.createElement('div');
        const term = document.createElement('dt');
        term.textContent = label;
        const detail = document.createElement('dd');
        const span = document.createElement('span');
        span.textContent = value;
        const edit = document.createElement('button');
        edit.type = 'button';
        edit.textContent = labels.edit;
        edit.addEventListener('click', () => showStep(step, true));
        detail.append(span, edit);
        wrapper.append(term, detail);
        return wrapper;
    }

    function renderSummary() {
        const data = values();
        const chosen = selectedOption();
        const days = daysBetween(data.pickup_date, data.return_date);
        const vehicle = `${chosen ? chosen.dataset.name : ''} · ${data.with_driver ? labels.driver_yes : labels.driver_no}`;
        const rows = [
            summaryRow(labels.dates, `${formatDate(data.pickup_date)} → ${formatDate(data.return_date)} (${days} ${labels.days})`, 1),
            summaryRow(labels.passengers, `${data.passengers} · ${labels.units}: ${data.units}`, 2),
            summaryRow(labels.vehicle, vehicle, 3),
            summaryRow(labels.contact, `${data.customer_name} · ${labels[data.contact_type]}: ${data.contact_value}`, 4),
        ];
        if (quote?.addons?.length) rows.push(summaryRow(labels.extras, quote.addons.map((addon) => `${addon.name} (${money(addon.total)})`).join(', '), 3));
        if (quote?.duration_discount?.amount > 0) rows.push(summaryRow(labels.long_discount, `−${money(quote.duration_discount.amount)}`, 1));
        if (quote?.promo) rows.push(summaryRow(labels.promo_applied, `${quote.promo.code} · −${money(quote.promo.amount)}`, 3));
        if (data.special_request) rows.push(summaryRow(labels.special, data.special_request, 4));

        root.querySelector('[data-summary]').replaceChildren(...rows);
        root.querySelector('[data-summary-total]').textContent = quote ? money(quote.grand_total) : '';
    }

    function showDone(result) {
        flow.hidden = true;
        done.hidden = false;
        root.querySelector('[data-done-reference]').textContent = result.reference;

        [['whatsapp', 'whatsapp_url'], ['phone', 'phone_url'], ['email', 'email_url']].forEach(([name, key]) => {
            const link = root.querySelector(`[data-done-${name}]`);
            const url = result.handover?.[key];
            link.hidden = !url;
            if (url) link.href = url;
        });
    }

    async function submit(event) {
        event.preventDefault();
        if (busy || current !== steps.length) return;
        clearError();

        const problem = [1, 2, 3, 4].map(validate).find(Boolean);
        if (problem) {
            showError(problem.message);
            goToField(problem.field);
            return;
        }

        setBusy(true);
        submitButton.textContent = labels.sending;

        try {
            const result = await post(config.submitUrl, {
                ...tripPayload(),
                customer_name: values().customer_name,
                contact_type: values().contact_type,
                contact_value: values().contact_value,
                special_request: values().special_request,
                customer_token: localStorage.getItem(config.tokenKey) || undefined,
            });

            if (!result.ok) {
                handleFailure(result);
                return;
            }

            clearDraft();
            showDone(result.body);
        } catch {
            showError(labels.error_network);
        } finally {
            setBusy(false);
            submitButton.textContent = labels.submit;
        }
    }

    function reset() {
        form.reset();
        quote = null;
        clearError();
        done.hidden = true;
        flow.hidden = false;
        clearDraft();
        refreshDays();
        showStep(1, true);
    }

    function preselect(slug) {
        if (!flow.hidden) {
            refreshVehicles();
            const option = vehicleOptions.find((candidate) => candidate.querySelector('input').value === slug);
            if (option && !option.querySelector('input').disabled) {
                form.elements.vehicle_slug.value = slug;
                refreshVehicles();
                saveDraft();
            }
        }
    }

    form.addEventListener('input', () => { refreshDays(); if (current <= 3) refreshVehicles(); saveDraft(); });
    form.addEventListener('change', () => { refreshVehicles(); saveDraft(); });
    form.addEventListener('submit', submit);
    nextButton.addEventListener('click', next);
    backButton.addEventListener('click', () => { clearError(); showStep(Math.max(1, current - 1), true); });
    root.querySelector('[data-wizard-reset]').addEventListener('click', reset);
    form.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' && event.target.tagName !== 'TEXTAREA' && current < steps.length) {
            event.preventDefault();
            next();
        }
    });

    /** Dates and party size handed over from the showroom search strip win over an older draft. */
    function applyPrefill() {
        const prefill = {
            pickup_date: root.dataset.prefillPickupDate,
            return_date: root.dataset.prefillReturnDate,
            passengers: root.dataset.prefillPassengers,
        };

        if (!prefill.pickup_date && !prefill.return_date && !prefill.passengers) return false;

        Object.entries(prefill).forEach(([name, value]) => {
            if (value) form.elements[name].value = value;
        });

        current = prefill.pickup_date && prefill.return_date ? 2 : 1;
        return true;
    }

    restoreDraft();
    applyPrefill();
    refreshDays();
    refreshVehicles();
    showStep(current);
    preselect(root.dataset.preselectVehicle || '');
}

document.addEventListener('DOMContentLoaded', initReservationWizard);
