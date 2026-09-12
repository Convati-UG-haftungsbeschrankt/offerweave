(() => {
    'use strict';
    const boot = window.offerweave_boot;
    if (!boot) return;
    const { __ } = window.wp.i18n;
    const roots = () => [...document.querySelectorAll('.cqb-app[data-ow-ssr]')];
    const dirty = new Map(),
        timers = new Map(),
        scripts = new Map();
    const pendingActions = new Set();
    let queue = Promise.resolve(),
        refreshing = false;
    const state = (root) => {
        try {
            return JSON.parse(root.querySelector('template[data-ow-state]')?.content.textContent || '{}');
        } catch {
            return {};
        }
    };
    const key = (node) =>
        node.nodeType !== 1
            ? ''
            : node.id ||
              (node.dataset.card ? 'card:' + node.dataset.card : '') ||
              (node.dataset.line ? 'line:' + node.dataset.line : '') ||
              (node.name
                  ? node.tagName + ':' + node.name + (node.type === 'checkbox' ? ':' + node.value : '')
                  : '') ||
              (node.className && typeof node.className === 'string'
                  ? node.tagName + ':' + node.className
                  : '');
    function compatible(a, b) {
        return (
            a.nodeType === b.nodeType && (a.nodeType !== 1 || (a.tagName === b.tagName && key(a) === key(b)))
        );
    }
    // Preserve live DOM nodes (including focused controls), replacing only changed server output.
    function morph(current, next) {
        if (current.nodeType === 3 || current.nodeType === 8) {
            if (current.nodeValue !== next.nodeValue) current.nodeValue = next.nodeValue;
            return;
        }
        if (current.nodeType !== 1) return;
        const open = current.tagName === 'DETAILS' && current.open;
        const captcha = current.matches('.cqb-captcha') && current.dataset.widget !== undefined;
        for (const attr of [...current.attributes])
            if (!next.hasAttribute(attr.name) && attr.name !== 'data-widget')
                current.removeAttribute(attr.name);
        for (const attr of next.attributes)
            if (current.getAttribute(attr.name) !== attr.value) current.setAttribute(attr.name, attr.value);
        if (open) current.open = true;
        if (captcha) return;
        if (current.tagName === 'TEMPLATE') {
            current.innerHTML = next.innerHTML;
            return;
        }
        let index = 0;
        for (const desired of [...next.childNodes]) {
            let existing = current.childNodes[index];
            if (!existing || !compatible(existing, desired)) {
                const found = [...current.childNodes]
                    .slice(index + 1)
                    .find((n) => compatible(n, desired) && key(n));
                if (found) {
                    current.insertBefore(found, existing || null);
                    existing = found;
                } else {
                    current.insertBefore(desired.cloneNode(true), existing || null);
                    index++;
                    continue;
                }
            }
            morph(existing, desired);
            index++;
        }
        while (current.childNodes.length > index) current.lastChild.remove();
        if (current.matches('input,textarea,select')) {
            if (current.type === 'checkbox' || current.type === 'radio') current.checked = next.checked;
            else current.value = next.value;
        }
    }
    function captureValues(ackId, ackVersion, success) {
        const fields = [];
        for (const field of document.querySelectorAll('.cqb-app input,.cqb-app select,.cqb-app textarea')) {
            const form = field.form;
            if (!form || !field.name) continue;
            const draft =
                !success &&
                (field.matches('[data-field]') ||
                    ['form_token', 'request_key', 'captcha_token'].includes(field.name));
            const unsent = dirty.has(form.id) && (form.id !== ackId || dirty.get(form.id) !== ackVersion);
            if (draft || unsent || (!success && field === document.activeElement))
                fields.push({
                    form: form.id,
                    name: field.name,
                    value: field.value,
                    checked: field.checked,
                    type: field.type,
                });
        }
        return fields;
    }
    function applyHTML(html, ackId = '', ackVersion = -1, action = '') {
        const next = new DOMParser().parseFromString(html, 'text/html');
        const replacements = [...next.querySelectorAll('.cqb-app[data-ow-ssr]')];
        if (!replacements.length) throw new Error(__('The request failed.', 'offerweave'));
        const success = action === 'submit' && replacements.every((r) => !state(r).failed);
        const values = captureValues(ackId, ackVersion, success);
        const active = document.activeElement;
        const focus =
            active?.form && active.name
                ? {
                      form: active.form.id,
                      name: active.name,
                      start: active.selectionStart,
                      end: active.selectionEnd,
                  }
                : null;
        for (const root of roots()) {
            const replacement = replacements.find((r) => r.id === root.id);
            if (replacement) morph(root, replacement);
        }
        for (const saved of values) {
            const form = document.getElementById(saved.form);
            const fields = [...(form?.elements || [])].filter((f) => f.name === saved.name);
            for (const field of fields) {
                if (saved.type === 'checkbox' || saved.type === 'radio') {
                    if (field.value === saved.value) field.checked = saved.checked;
                } else if (
                    field.tagName !== 'SELECT' ||
                    [...field.options].some((o) => o.value === saved.value)
                )
                    field.value = saved.value;
            }
        }
        if (dirty.get(ackId) === ackVersion) dirty.delete(ackId);
        if (focus) {
            const field = [...(document.getElementById(focus.form)?.elements || [])].find(
                (f) => f.name === focus.name
            );
            if (field) {
                field.focus({ preventScroll: true });
                try {
                    if (focus.start !== null) field.setSelectionRange(focus.start, focus.end);
                } catch {}
            }
        }
        if (success)
            for (const container of document.querySelectorAll('.cqb-captcha[data-widget]'))
                resetCaptcha(container);
        for (const id of dirty.keys()) {
            const form = document.getElementById(id);
            if (form) invalidate(form, !form.checkValidity());
        }
        enhance();
        persist();
        return replacements;
    }
    function failure(error, root) {
        const message = error.message || __('The request failed.', 'offerweave');
        for (const app of roots()) {
            app.classList.remove('ow-enhanced');
            app.removeAttribute('aria-busy');
            app.querySelectorAll('[data-ow-pending]').forEach((e) => e.removeAttribute('data-ow-pending'));
        }
        const target =
            root?.querySelector('.cqb-global-message,.cqb-form-message') ||
            document.querySelector('.cqb-global-message');
        if (target) {
            target.textContent = message;
            target.classList.add('cqb-error');
        }
    }
    async function send(formId, action, extra = {}) {
        const form = document.getElementById(formId);
        if (!form?.matches('.ow-server-form')) return;
        const version = dirty.get(formId) || 0,
            root = form.closest('.cqb-app');
        const data = new URLSearchParams(new FormData(form));
        data.set('offerweave_action', action);
        data.set('offerweave_enhanced', '1');
        for (const [k, v] of Object.entries(extra)) data.set(k, v);
        root.setAttribute('aria-busy', 'true');
        try {
            const controller = new AbortController();
            const timeout = setTimeout(() => controller.abort(), 30000);
            let response;
            try {
                response = await fetch(form.action, {
                    method: 'POST',
                    body: data,
                    credentials: 'same-origin',
                    cache: 'no-store',
                    signal: controller.signal,
                });
            } finally {
                clearTimeout(timeout);
            }
            if (!response.ok) throw new Error(__('The request failed.', 'offerweave'));
            applyHTML(await response.text(), formId, version, action);
        } catch (error) {
            if (error.name === 'AbortError' || error instanceof TypeError)
                error = new Error(
                    __(
                        'The connection was interrupted or is taking too long. Your selection will be preserved. Please try again.',
                        'offerweave'
                    )
                );
            failure(error, root);
        } finally {
            root.removeAttribute('aria-busy');
        }
    }
    const enqueue = (job) => {
        queue = queue.then(job, job);
        return queue;
    };
    function affectedCards(form) {
        const root = form.closest('.cqb-app[data-ow-ssr]');
        if (!root) return [];
        const own = form.closest('.cqb-app[data-ow-ssr] [data-card]');
        if (own) return [own];
        return [...root.querySelectorAll('[data-card]')];
    }
    function invalidate(form, invalid = false) {
        for (const card of affectedCards(form)) {
            card.quoteLine = null;
            card.dataset.owPending = 'true';
            card.querySelector('[data-add]')?.setAttribute('disabled', '');
            const price = card.querySelector('[data-price]');
            if (price) price.textContent = invalid ? '—' : __('Calculating price …', 'offerweave');
            card.querySelector('[data-price-details]')?.replaceChildren();
            card.querySelector('[data-breakdown]')?.replaceChildren();
        }
    }
    function change(event) {
        const field = event.target,
            form = field.form;
        if (!form?.matches('.ow-server-form') || !form.closest('.cqb-app[data-ow-ssr]')) return;
        if (field.matches('[data-field]')) return;
        if (!field.matches('[data-people],[data-quantity],[data-count]')) return;
        if (form.closest('.cqb-line-editor')) {
            return;
        }
        const number = (dirty.get(form.id) || 0) + 1;
        dirty.set(form.id, number);
        clearTimeout(timers.get(form.id));
        const card =
            form.closest('.cqb-app[data-ow-ssr] [data-card]') ||
            document.querySelector(
                '.cqb-app[data-ow-ssr] [data-card="' +
                    CSS.escape(form.elements.offerweave_offer?.value || '') +
                    '"]'
            );
        if (!field.checkValidity()) {
            field.setAttribute('aria-invalid', 'true');
            const error =
                field.closest('[data-controls-offer]')?.querySelector('[data-card-input-error]') ||
                card?.querySelector('[data-card-input-error]') ||
                field.closest('.cqb-app')?.querySelector('.cqb-calculation-message');
            if (error) {
                error.hidden = false;
                error.textContent = __('Please enter a whole number between 1 and 100,000.', 'offerweave');
            }
            invalidate(form, true);
            return;
        }
        field.removeAttribute('aria-invalid');
        invalidate(form);
        timers.set(
            form.id,
            setTimeout(() => enqueue(() => send(form.id, 'update')), field.type === 'number' ? 250 : 0)
        );
    }
    document.addEventListener('input', (event) => {
        if (event.target.type !== 'checkbox' && event.target.tagName !== 'SELECT') change(event);
    });
    document.addEventListener('change', (event) => {
        if (event.target.type === 'checkbox' || event.target.tagName === 'SELECT') change(event);
    });
    document.addEventListener('submit', (event) => {
        const form = event.target;
        if (!form.matches('.ow-server-form')) return;
        if (!form.closest('.ow-enhanced')) return; // Native fallback remains usable after a network failure.
        event.preventDefault();
        const action = event.submitter?.value || 'update';
        if (action === 'submit') {
            const captcha = form.querySelector('.cqb-captcha');
            if (captcha?.dataset.provider !== 'none' && !form.elements.captcha_token.value) {
                form.querySelector('.cqb-form-message').textContent = __(
                    'Please complete the CAPTCHA check.',
                    'offerweave'
                );
                return;
            }
        }
        if (pendingActions.has(form.id)) return;
        pendingActions.add(form.id);
        clearTimeout(timers.get(form.id));
        enqueue(() => send(form.id, action)).finally(() => pendingActions.delete(form.id));
    });
    async function refresh(url = location.href, navigate = false) {
        if (refreshing) return;
        refreshing = true;
        try {
            const response = await fetch(url, { credentials: 'same-origin', cache: 'no-store' });
            if (!response.ok) throw new Error(__('The request failed.', 'offerweave'));
            applyHTML(await response.text());
            if (navigate) history.pushState(null, '', url);
        } catch (error) {
            failure(error, roots()[0]);
        } finally {
            refreshing = false;
        }
    }
    document.addEventListener('click', (event) => {
        const link = event.target.closest('[data-category-tab],[data-selection]');
        if (
            !link ||
            !link.closest('.ow-enhanced') ||
            event.ctrlKey ||
            event.metaKey ||
            event.shiftKey ||
            event.altKey
        )
            return;
        const url = new URL(link.href, location.href);
        if (
            url.origin === location.origin &&
            url.pathname === location.pathname &&
            url.search !== location.search
        ) {
            event.preventDefault();
            enqueue(() => refresh(url.href, true));
        }
    });
    window.addEventListener('popstate', () => enqueue(() => refresh()));
    function persist() {
        const current = state(roots()[0]);
        // A rejected expired form must not erase the old selection before migration on reload.
        if (current.failed && current.can_import && !current.cart?.length) return;
        try {
            localStorage.setItem(
                'offerweave_selection_v1',
                JSON.stringify({ version: 1, items: current.cart || [], request_key: '' })
            );
        } catch {}
    }
    window.addEventListener('storage', (event) => {
        if (event.key === 'offerweave_selection_v1') enqueue(() => refresh());
    });
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) enqueue(() => refresh());
    });
    function loadCaptcha(provider) {
        if (!scripts.has(provider))
            scripts.set(
                provider,
                new Promise((resolve, reject) => {
                    const script = document.createElement('script');
                    script.async = true;
                    script.defer = true;
                    script.src =
                        provider === 'hcaptcha'
                            ? 'https://js.hcaptcha.com/1/api.js?render=explicit'
                            : 'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit';
                    script.onload = resolve;
                    script.onerror = () =>
                        reject(
                            new Error(
                                __('CAPTCHA protection could not be loaded. Please reload.', 'offerweave')
                            )
                        );
                    document.head.append(script);
                })
            );
        return scripts.get(provider);
    }
    function vendor(container) {
        return container.dataset.provider === 'hcaptcha' ? window.hcaptcha : window.turnstile;
    }
    function resetCaptcha(container) {
        try {
            vendor(container)?.reset(container.dataset.widget);
        } catch {}
        const field = container.closest('form')?.elements.captcha_token;
        if (field) field.value = '';
    }
    async function mountCaptcha(container) {
        if (
            !['hcaptcha', 'turnstile'].includes(container.dataset.provider) ||
            container.dataset.widget !== undefined
        )
            return;
        container.dataset.widget = '';
        try {
            await loadCaptcha(container.dataset.provider);
            if (!container.isConnected) return;
            const field = container.closest('form').elements.captcha_token;
            container.dataset.widget = vendor(container).render(container, {
                sitekey: container.dataset.sitekey,
                ...(container.dataset.provider === 'turnstile' ? { action: 'offerweave_request' } : {}),
                callback: (token) => {
                    field.value = token;
                },
                'expired-callback': () => {
                    field.value = '';
                },
                'error-callback': () => {
                    field.value = '';
                },
            });
        } catch (error) {
            failure(error, container.closest('.cqb-app'));
        }
    }
    function enhance() {
        for (const root of roots()) root.classList.add('ow-enhanced');
        // Preserve the existing read-only integration property using the PHP calculation result.
        for (const card of document.querySelectorAll('.cqb-app[data-ow-ssr] [data-card]')) {
            try {
                card.quoteLine = card.hasAttribute('data-ow-pending')
                    ? null
                    : JSON.parse(
                          card.querySelector('template[data-ow-quote]')?.content.textContent || 'null'
                      );
            } catch {
                card.quoteLine = null;
            }
        }
        for (const container of document.querySelectorAll('.cqb-captcha')) mountCaptcha(container);
    }
    document.addEventListener(
        'error',
        (event) => {
            const image = event.target;
            if (!image.closest?.('.cqb-app[data-ow-ssr]')) return;
            if (image.matches?.('.cqb-fact-icon')) image.hidden = true;
            if (image.matches?.('.cqb-offer-image')) {
                image.closest('.cqb-card-media').hidden = true;
                image.closest('[data-card]').dataset.owHasImage = 'false';
            }
        },
        true
    );
    async function start() {
        if (!roots().length) return;
        enhance();
        const current = state(roots()[0]);
        try {
            const old = JSON.parse(
                localStorage.getItem('offerweave_selection_v1') ||
                    localStorage.getItem('cqb_selection_v1') ||
                    'null'
            );
            if (
                current.can_import &&
                !current.cart?.length &&
                old?.version === 1 &&
                Array.isArray(old.items) &&
                old.items.length &&
                old.items.length <= 30
            ) {
                const form = document.querySelector('.cqb-app[data-actions="true"] .ow-server-form');
                if (form)
                    await enqueue(() =>
                        send(form.id, 'import', { offerweave_legacy: JSON.stringify(old.items) })
                    );
            } else persist();
        } catch {}
    }
    start();
})();
