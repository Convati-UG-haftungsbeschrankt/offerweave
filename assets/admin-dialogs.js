(() => {
    'use strict';
    const { __ } = window.wp.i18n;
    let active = null;
    let sequence = 0;

    function show(message, options, confirmation) {
        // A second action must never inherit another action's positive confirmation.
        if (active) return Promise.resolve(false);
        const trigger = document.activeElement;
        const binding = trigger?.dataset?.bind;
        const triggerId = trigger?.id;
        const attribute = trigger?.getAttributeNames().find((name) => name.startsWith('data-'));
        const selector = attribute
            ? '[' + CSS.escape(attribute) + '="' + CSS.escape(trigger.getAttribute(attribute)) + '"]'
            : null;
        const parentDialog = trigger?.closest('dialog');
        const d = document.createElement('dialog');
        const id = 'offerweave-notice-' + ++sequence;
        d.className = 'offerweave-dialog offerweave-notice-dialog';
        d.dataset.owDialog = confirmation ? 'confirm' : 'notice';
        d.setAttribute('aria-labelledby', id + '-title');
        d.setAttribute('aria-describedby', id + '-message');
        d.innerHTML =
            '<header class="qb-notice-head"><span class="qb-notice-brand" aria-hidden="true"></span><h2></h2><button type="button" class="qb-secondary qb-notice-close" data-ow-dialog-close>×</button></header><p class="qb-notice-message"></p><div class="qb-actions"><button type="button" class="qb-secondary" data-ow-dialog-cancel></button><button type="button" data-ow-dialog-confirm></button></div>';
        const title = d.querySelector('h2');
        title.id = id + '-title';
        title.textContent =
            options.title ||
            (confirmation ? __('Confirm action', 'offerweave') : __('Please note', 'offerweave'));
        const body = d.querySelector('.qb-notice-message');
        body.id = id + '-message';
        body.textContent = String(message ?? '');
        const close = d.querySelector('[data-ow-dialog-close]');
        close.setAttribute('aria-label', __('Close', 'offerweave'));
        const cancel = d.querySelector('[data-ow-dialog-cancel]');
        cancel.textContent = __('Cancel', 'offerweave');
        cancel.hidden = !confirmation;
        const accept = d.querySelector('[data-ow-dialog-confirm]');
        accept.className = options.danger ? 'qb-danger' : 'qb-button';
        accept.textContent =
            options.confirmLabel ||
            (confirmation ? __('Continue', 'offerweave') : __('Understood', 'offerweave'));
        const logo = document.createElement('img');
        logo.src = window.offerweave_admin.assets + 'brand/offerweave-logo.png';
        logo.alt = '';
        logo.width = logo.height = 32;
        d.querySelector('.qb-notice-brand').append(logo);
        return new Promise((resolve) => {
            active = d;
            let accepted = false;
            let settled = false;
            const finish = () => {
                if (settled) return;
                settled = true;
                d.remove();
                active = null;
                resolve(accepted);
                queueMicrotask(() => {
                    if (active) return;
                    // Editor renders can replace the element which originally opened the dialog.
                    const available = (el) =>
                        el?.isConnected && !el.disabled && el.getClientRects().length > 0;
                    let focus = available(trigger) ? trigger : null;
                    if (!focus && binding)
                        focus = document.querySelector('[data-bind="' + CSS.escape(binding) + '"]');
                    if (!focus && triggerId) focus = document.getElementById(triggerId);
                    if (!focus && selector) focus = document.querySelector(selector);
                    if (!available(focus)) focus = null;
                    if (!focus && parentDialog?.open)
                        focus = parentDialog.querySelector('button:not(:disabled)');
                    if (!focus) focus = document.querySelector('#offerweave-admin button:not(:disabled)');
                    focus?.focus({ preventScroll: true });
                });
            };
            const dismiss = () => d.close();
            close.onclick = dismiss;
            cancel.onclick = dismiss;
            accept.onclick = () => {
                accepted = true;
                d.close();
            };
            d.addEventListener('cancel', (event) => {
                event.preventDefault();
                dismiss();
            });
            d.addEventListener('close', finish, { once: true });
            document.body.append(d);
            d.showModal();
            (confirmation ? cancel : accept).focus();
        });
    }

    window.offerweave_Dialogs = Object.freeze({
        notice: (message, options = {}) => show(message, options, false),
        confirm: (message, options = {}) => show(message, options, true),
    });
})();
