(() => {
    'use strict';
    const { __, _x, _n, sprintf } = window.wp.i18n;
    const esc = (v) =>
        String(v ?? '').replace(
            /[&<>"']/g,
            (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' })[c]
        );
    const imageMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/avif', 'image/svg+xml'];
    const mediaSource = (a, size) => (a.mime === 'image/svg+xml' ? a.url : a.sizes?.[size]?.url || a.url);
    const empty = () => ({ attachment_id: 0, url: '', alt: '', decorative: false });
    function render(image = {}) {
        const i = { ...empty(), ...image };
        return (
            '<div class="qb-offer-image-editor"><div class="qb-image-preview"><img alt="' +
            esc(__('Offer image preview', 'offerweave')) +
            '"' +
            (i.url ? ' src="' + esc(i.url) + '"' : ' hidden') +
            '><span data-image-empty' +
            (i.url ? ' hidden' : '') +
            ('>' + esc(__('No image assigned yet', 'offerweave')) + '</span></div>') +
            ('<div class="qb-actions"><button type="button" class="qb-secondary" data-image-select>' +
                esc(__('Choose image from media library', 'offerweave')) +
                '</button><button type="button" class="qb-secondary" data-image-remove>' +
                esc(__('Remove image from offer', 'offerweave')) +
                '</button></div>') +
            ('<label class="qb-field">' +
                esc(__('Alternative: direct image address', 'offerweave')) +
                '<input type="url" data-image-url value="') +
            esc(i.url) +
            '" ' +
            (i.attachment_id ? 'readonly' : '') +
            ' placeholder="https://…"><small data-image-source>' +
            (i.attachment_id
                ? __('Media library image #', 'offerweave') +
                  Number(i.attachment_id) +
                  __('. To use a direct address, remove the assignment first.', 'offerweave')
                : __(
                      'Optional alternative to the media library. External images are loaded in the browser from the specified provider.',
                      'offerweave'
                  )) +
            '</small></label>' +
            ('<label class="qb-field">' +
                esc(__('Alternative text', 'offerweave')) +
                '<input type="text" data-image-alt value="') +
            esc(i.alt) +
            '" maxlength="400"' +
            (i.decorative ? ' disabled' : '') +
            '><small>' +
            esc(
                __(
                    'Describes the image for screen readers. Empty = media library alternative text, otherwise the offer name.',
                    'offerweave'
                )
            ) +
            '</small></label>' +
            '<label class="qb-check"><input type="checkbox" data-image-decorative' +
            (i.decorative ? ' checked' : '') +
            ('> ' + esc(__('Decorative image (empty alternative text)', 'offerweave')) + '</label>') +
            ('<p class="qb-muted">' +
                esc(
                    __(
                        'One image per offer. JPEG, PNG, GIF, WebP, AVIF or SVG. SVG uploads require support enabled in WordPress. Removing it here does not delete the media library file. The assignment is published only after saving.',
                        'offerweave'
                    )
                ) +
                '</p><p data-image-status role="status"></p></div>')
        );
    }
    function bind(root, get, set, changed = () => {}) {
        if (!root) return;
        const url = root.querySelector('[data-image-url]'),
            alt = root.querySelector('[data-image-alt]'),
            decorative = root.querySelector('[data-image-decorative]'),
            img = root.querySelector('.qb-image-preview img'),
            status = root.querySelector('[data-image-status]');
        const refresh = () => {
            const i = { ...empty(), ...get() };
            url.value = i.url;
            url.readOnly = Boolean(i.attachment_id);
            alt.value = i.alt;
            alt.disabled = i.decorative;
            decorative.checked = i.decorative;
            root.querySelector('[data-image-source]').textContent = i.attachment_id
                ? __('Media library image #', 'offerweave') +
                  i.attachment_id +
                  __('. To use a direct address, remove the assignment first.', 'offerweave')
                : __(
                      'Optional alternative to the media library. External images are loaded in the browser from the specified provider.',
                      'offerweave'
                  );
            const valid = /^https?:\/\//i.test(i.url);
            img.hidden = !valid;
            if (valid) img.src = i.url;
            else img.removeAttribute('src');
            root.querySelector('[data-image-empty]').hidden = valid;
        };
        const update = (image) => {
            set(image);
            status.textContent = '';
            changed();
        };
        img.addEventListener('error', () => {
            img.hidden = true;
            status.textContent = __(
                'The image cannot be loaded at this time. Please check the address or media library assignment.',
                'offerweave'
            );
        });
        url.addEventListener('input', () => {
            update({ ...get(), attachment_id: 0, url: url.value.trim() });
            const valid = /^https?:\/\//i.test(url.value.trim());
            img.hidden = !valid;
            if (valid) img.src = url.value.trim();
            else img.removeAttribute('src');
            root.querySelector('[data-image-empty]').hidden = valid;
        });
        alt.addEventListener('input', () => update({ ...get(), alt: alt.value }));
        decorative.addEventListener('change', () => {
            update({ ...get(), decorative: decorative.checked });
            alt.disabled = decorative.checked;
        });
        root.querySelector('[data-image-remove]').onclick = () => {
            update(empty());
            refresh();
        };
        root.querySelector('[data-image-select]').onclick = () => {
            if (!window.wp?.media) {
                status.textContent = __('The WordPress media library is unavailable.', 'offerweave');
                return;
            }
            const frame = wp.media({
                title: __('Select offer image', 'offerweave'),
                button: { text: __('Use image for this offer', 'offerweave') },
                library: { type: 'image' },
                multiple: false,
            });
            frame.on('open', () => {
                if (get().attachment_id)
                    frame.state().get('selection').add(wp.media.attachment(get().attachment_id));
            });
            frame.on('select', () => {
                const a = frame.state().get('selection').first().toJSON();
                if (!imageMimes.includes(a.mime)) {
                    status.textContent = __('Please select JPEG, PNG, GIF, WebP, AVIF or SVG.', 'offerweave');
                    return;
                }
                update({
                    attachment_id: Number(a.id),
                    url: mediaSource(a, 'large'),
                    alt: a.alt || '',
                    decorative: false,
                });
                refresh();
            });
            // WordPress fires selection immediately after closing; dispose after that callback finishes.
            frame.on('close', () => setTimeout(() => frame.remove(), 0));
            frame.open();
        };
    }
    const emptyIcon = () => ({ ...empty(), decorative: true });
    function iconEditor(icon = {}, number) {
        const label = sprintf(__('Key fact %d: icon', 'offerweave'), number);
        return (
            '<div class="qb-fact-icon-control" role="group" aria-label="' +
            esc(label) +
            '">' +
            '<div class="qb-fact-icon-heading"><button type="button" class="qb-icon-remove" data-icon-remove title="' +
            esc(__('Remove icon', 'offerweave')) +
            '" aria-label="' +
            esc(sprintf(__('Remove icon from key fact %d', 'offerweave'), number)) +
            '"><span aria-hidden="true">×</span></button><span>' +
            esc(__('Own icon', 'offerweave')) +
            '</span></div>' +
            '<span class="qb-fact-icon-preview" aria-hidden="true"><img alt="" hidden></span>' +
            '<button type="button" class="qb-secondary" data-icon-select aria-label="' +
            esc(sprintf(__('Choose or upload an icon for key fact %d', 'offerweave'), number)) +
            '">' +
            esc(__('Choose / upload', 'offerweave')) +
            '</button>' +
            '<small data-icon-status role="status"></small></div>'
        );
    }
    function bindIcon(root, get, set) {
        if (!root) return;
        const img = root.querySelector('img'),
            status = root.querySelector('[data-icon-status]'),
            remove = root.querySelector('[data-icon-remove]');
        const refresh = () => {
            const icon = { ...emptyIcon(), ...get() };
            const valid = /^https?:\/\//i.test(icon.url);
            img.hidden = !valid;
            if (valid) img.src = icon.url;
            else img.removeAttribute('src');
            remove.hidden = !valid && !icon.attachment_id;
            status.textContent = valid ? '' : __('No icon', 'offerweave');
        };
        img.addEventListener('error', () => {
            img.hidden = true;
            status.textContent = __(
                'Icon unavailable. Choose another image or remove the assignment.',
                'offerweave'
            );
        });
        remove.onclick = () => {
            set(emptyIcon());
            refresh();
            root.querySelector('[data-icon-select]').focus();
        };
        root.querySelector('[data-icon-select]').onclick = () => {
            if (!window.wp?.media) {
                status.textContent = __('The WordPress media library is unavailable.', 'offerweave');
                return;
            }
            const frame = wp.media({
                title: __('Choose or upload your icon', 'offerweave'),
                button: { text: __('Use this icon', 'offerweave') },
                library: { type: 'image' },
                multiple: false,
            });
            frame.on('open', () => {
                if (get()?.attachment_id)
                    frame.state().get('selection').add(wp.media.attachment(get().attachment_id));
            });
            frame.on('select', () => {
                const a = frame.state().get('selection').first().toJSON();
                if (!imageMimes.includes(a.mime)) {
                    status.textContent = __('Please select JPEG, PNG, GIF, WebP, AVIF or SVG.', 'offerweave');
                    return;
                }
                set({ ...emptyIcon(), attachment_id: Number(a.id), url: mediaSource(a, 'thumbnail') });
                refresh();
            });
            frame.on('close', () => setTimeout(() => frame.remove(), 0));
            frame.open();
        };
        refresh();
    }
    window.offerweave_Images = { render, bind, iconEditor, bindIcon, emptyIcon };
})();
