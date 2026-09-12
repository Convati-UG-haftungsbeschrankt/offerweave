/* Shared offer workspace. It organises existing controls; price calculation stays on the server. */
(() => {
    'use strict';
    const { __, sprintf } = window.wp.i18n;
    const esc = (v) =>
        String(v ?? '').replace(
            /[&<>"']/g,
            (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' })[c]
        );
    const state = { search: '', role: '', category: '', status: '', area: 'price', resultsScroll: 0 };
    const areas = () => ({
        content: __('Content and appearance', 'offerweave'),
        price: __('Price and quantity', 'offerweave'),
        publication: __('Publication', 'offerweave'),
    });
    const role = (o) =>
        o.kind === 'selection' ? 'selection' : o.component_ids?.length ? 'package' : 'single';
    const roles = () => ({
        single: __('Single offer', 'offerweave'),
        package: __('Package with included offers', 'offerweave'),
        selection: __('Package to assemble', 'offerweave'),
    });
    const effective = (o) => o;
    function summary(o, c, kinds) {
        const source = effective(o, c);
        return source !== o ? sprintf(__('Collected in: %s', 'offerweave'), source.name) : kinds[o.kind];
    }
    function open(control) {
        const section = control?.closest('[data-offer-area]');
        if (!section) return;
        state.area = section.dataset.offerArea;
        show(section.closest('[data-offer-editor]'));
        for (let el = control.parentElement; el && el !== section; el = el.parentElement)
            if (el.tagName === 'DETAILS') el.open = true;
    }
    function show(editor) {
        if (!editor) return;
        editor
            .querySelectorAll('[data-offer-area]')
            .forEach((el) => (el.hidden = el.dataset.offerArea !== state.area));
        editor
            .querySelectorAll('[data-offer-area-button]')
            .forEach((el) =>
                el.setAttribute('aria-pressed', String(el.dataset.offerAreaButton === state.area))
            );
    }
    function mount(ctx) {
        const { root, config: c, selected, kinds } = ctx;
        const workspace = root.querySelector('.qb-workspace');
        if (!workspace) return;
        workspace.classList.add('qb-offer-workspace');
        const sidebar = workspace.querySelector('.qb-list');
        const editor = workspace.lastElementChild;
        const o = c.offers[selected];
        const categories = Object.fromEntries(
            c.offers.map((x) => [x.category, x.category_label || x.category])
        );
        const select = (id, label, values, value) =>
            '<label class="qb-field"><span>' +
            esc(label) +
            '</span><select data-offer-filter="' +
            id +
            '">' +
            Object.entries(values)
                .map(
                    ([v, t]) =>
                        '<option value="' +
                        esc(v) +
                        '" ' +
                        (v === value ? 'selected' : '') +
                        '>' +
                        esc(t) +
                        '</option>'
                )
                .join('') +
            '</select></label>';
        sidebar.insertAdjacentHTML(
            'afterbegin',
            '<div class="qb-offer-filters"><label class="qb-field"><span>' +
                esc(__('Find an offer', 'offerweave')) +
                '</span><input type="search" data-offer-search value="' +
                esc(state.search) +
                '" placeholder="' +
                esc(__('Name, ID or category', 'offerweave')) +
                '"></label><div class="qb-offer-filter-grid">' +
                select(
                    'role',
                    __('Role', 'offerweave'),
                    {
                        '': __('All roles', 'offerweave'),
                        ...roles(),
                    },
                    state.role
                ) +
                select(
                    'status',
                    __('Status', 'offerweave'),
                    {
                        '': __('All statuses', 'offerweave'),
                        active: __('Active', 'offerweave'),
                        inactive: __('Inactive', 'offerweave'),
                    },
                    state.status
                ) +
                '</div>' +
                select(
                    'category',
                    __('Category', 'offerweave'),
                    { '': __('All categories', 'offerweave'), ...categories },
                    state.category
                ) +
                '<div class="qb-offer-list-state"><span data-offer-count role="status"></span><button type="button" class="qb-text-button" data-clear-offer-filters>' +
                esc(__('Reset filters', 'offerweave')) +
                '</button></div></div>'
        );
        sidebar.querySelectorAll('[data-offer-index]').forEach((button) => {
            const entry = c.offers[Number(button.dataset.offerIndex)];
            button.innerHTML =
                '<strong>' +
                esc(entry.name) +
                '</strong><span class="qb-role-label">' +
                esc(roles()[role(entry)]) +
                '</span><small>' +
                esc(summary(entry, c, kinds)) +
                '</small><small>' +
                esc(
                    entry.enabled
                        ? entry.catalog_visible
                            ? __('Own catalog card', 'offerweave')
                            : __('Not listed in catalog', 'offerweave')
                        : __('Inactive', 'offerweave')
                ) +
                '</small>';
        });
        // Filters and creation stay outside the independently scrollable result list.
        const results = document.createElement('div');
        results.className = 'qb-offer-results';
        results.setAttribute('role', 'region');
        results.setAttribute('aria-label', __('Offers', 'offerweave'));
        results.tabIndex = 0;
        results.append(...sidebar.querySelectorAll('[data-offer-index]'));
        sidebar.append(results);
        const rememberScroll = () => {
            if (results.isConnected) state.resultsScroll = results.scrollTop;
        };
        results.addEventListener('scroll', rememberScroll, { passive: true });
        results.addEventListener('click', rememberScroll, true);
        const filter = (resetScroll = false) => {
            let count = 0;
            sidebar.querySelectorAll('[data-offer-index]').forEach((button) => {
                const entry = c.offers[Number(button.dataset.offerIndex)];
                const match =
                    (!state.search ||
                        [entry.name, entry.id, entry.category, entry.category_label]
                            .join(' ')
                            .toLocaleLowerCase()
                            .includes(state.search.toLocaleLowerCase())) &&
                    (!state.role ||
                        (state.role === 'linked' ? !!entry.selection_parent : role(entry) === state.role)) &&
                    (!state.category || entry.category === state.category) &&
                    (!state.status || entry.enabled === (state.status === 'active'));
                button.hidden = !match;
                count += Number(match);
            });
            sidebar.querySelector('[data-offer-count]').textContent = sprintf(
                __('%1$d of %2$d offers', 'offerweave'),
                count,
                c.offers.length
            );
            if (resetScroll) state.resultsScroll = 0;
            results.scrollTop = state.resultsScroll;
            const notice = editor.querySelector('[data-filtered-selection]');
            if (notice)
                notice.hidden = !sidebar.querySelector('[data-offer-index="' + selected + '"]')?.hidden;
        };
        sidebar.querySelector('[data-offer-search]').addEventListener('input', (e) => {
            state.search = e.target.value;
            filter(true);
        });
        sidebar.querySelectorAll('[data-offer-filter]').forEach((input) =>
            input.addEventListener('change', () => {
                state[input.dataset.offerFilter] = input.value;
                filter(true);
            })
        );
        sidebar.querySelector('[data-clear-offer-filters]').addEventListener('click', () => {
            state.search = state.role = state.category = state.status = '';
            sidebar.querySelector('[data-offer-search]').value = '';
            sidebar.querySelectorAll('[data-offer-filter]').forEach((el) => (el.value = ''));
            filter(true);
        });
        // The existing add button opens choices without modifying the draft first.
        const add = sidebar.querySelector('[data-add-offer]');
        if (add) {
            add.removeAttribute('data-add-offer');
            add.dataset.offerCreate = '';
            add.addEventListener('click', () => {
                const previous = editor.querySelector('[data-offer-create-panel]');
                if (previous) {
                    previous.remove();
                    return;
                }
                const box = document.createElement('section');
                box.className = 'qb-panel qb-create-offer';
                box.dataset.offerCreatePanel = '';
                box.innerHTML =
                    '<h2>' +
                    esc(__('What would you like to offer?', 'offerweave')) +
                    '</h2><div class="qb-create-choices">' +
                    ctx.presets
                        .map(
                            (p, i) =>
                                '<button type="button" class="qb-create-choice" data-offer-preset="' +
                                i +
                                '"><strong>' +
                                esc(p.label) +
                                '</strong><span>' +
                                esc(p.help) +
                                '</span></button>'
                        )
                        .join('') +
                    '</div><button type="button" class="qb-text-button" data-cancel-create>' +
                    esc(__('Cancel', 'offerweave')) +
                    '</button>';
                editor.prepend(box);
                box.querySelector('[data-cancel-create]').addEventListener('click', () => {
                    box.remove();
                    add.focus();
                });
                box.querySelectorAll('[data-offer-preset]').forEach((b) =>
                    b.addEventListener('click', () => {
                        state.area = ctx.presets[Number(b.dataset.offerPreset)].area;
                        ctx.create(ctx.presets[Number(b.dataset.offerPreset)]);
                    })
                );
                box.querySelector('button').focus();
                box.scrollIntoView({ block: 'nearest' });
            });
        }
        if (!o || !editor.querySelector('[data-bind]')) {
            filter();
            return;
        }
        editor.dataset.offerEditor = '';
        const source = effective(o, c),
            parents = c.offers.filter((p) => p.component_ids?.includes(o.id));
        const header = document.createElement('section');
        header.className = 'qb-panel qb-offer-heading';
        header.innerHTML =
            '<div class="qb-offer-heading-row"><div><span class="qb-role-label">' +
            esc(roles()[role(o)]) +
            '</span><h2 data-offer-heading-name>' +
            esc(o.name) +
            '</h2></div><span class="qb-offer-visibility">' +
            esc(
                o.enabled
                    ? o.catalog_visible
                        ? __('Own catalog card', 'offerweave')
                        : __('Not listed in catalog', 'offerweave')
                    : __('Inactive', 'offerweave')
            ) +
            '</span></div><p data-offer-effective>' +
            esc(summary(o, c, kinds)) +
            '</p><p class="qb-muted">' +
            esc(
                source !== o
                    ? __(
                          'Selecting this card adds a component to the shared package. The package supplies quantity rules, variants and the final calculation.',
                          'offerweave'
                      )
                    : role(o) === 'package'
                      ? __(
                            'The package has its own price. Included offers are not charged again.',
                            'offerweave'
                        )
                      : role(o) === 'selection'
                        ? __(
                              'Prices stored in this package are added once, then its own quantity rules apply. Individual offer prices remain independent.',
                              'offerweave'
                          )
                        : __('The price is multiplied by the quantity for this offer.', 'offerweave')
            ) +
            '</p>' +
            (parents.length
                ? '<p class="qb-muted">' +
                  esc(__('Used in:', 'offerweave')) +
                  ' ' +
                  parents.map((p) => esc(p.name)).join(', ') +
                  '</p>'
                : '') +
            '<p data-filtered-selection hidden class="qb-muted">' +
            esc(__('The open offer is outside the current filter. Your draft stays open.', 'offerweave')) +
            '</p>';
        const nav = document.createElement('nav');
        nav.className = 'qb-offer-sections';
        nav.setAttribute('aria-label', __('Offer settings', 'offerweave'));
        const sections = {};
        for (const [key, label] of Object.entries(areas())) {
            const area = document.createElement('div');
            area.dataset.offerArea = key;
            area.id = 'offerweave-offer-area-' + key;
            sections[key] = area;
            nav.insertAdjacentHTML(
                'beforeend',
                '<button type="button" data-offer-area-button="' +
                    key +
                    '" aria-controls="' +
                    area.id +
                    '">' +
                    esc(label) +
                    '</button>'
            );
        }
        const base = editor.querySelector('.qb-panel');
        const content = document.createElement('section');
        content.className = 'qb-panel';
        content.innerHTML =
            '<h2>' + esc(__('Offer content', 'offerweave')) + '</h2><div class="qb-grid"></div>';
        const pub = document.createElement('section');
        pub.className = 'qb-panel';
        pub.innerHTML =
            '<h2>' + esc(__('Visibility and placement', 'offerweave')) + '</h2><div class="qb-grid"></div>';
        const price = document.createElement('section');
        price.className = 'qb-panel';
        price.innerHTML = '<h2>' + esc(__('Price rule', 'offerweave')) + '</h2><div class="qb-grid"></div>';
        sections.content.append(content);
        sections.publication.append(pub);
        sections.price.append(price);
        const pubKeys = ['id', 'category', 'category_label', 'detail_url', 'enabled', 'catalog_visible'];
        const priceKeys = ['kind', 'tax_rate_bps'];
        for (const el of [...base.querySelector('.qb-grid').children]) {
            const key = el.querySelector('[data-bind]')?.dataset.bind.split('.').pop();
            (pubKeys.includes(key) ? pub : priceKeys.includes(key) ? price : content)
                .querySelector('.qb-grid')
                .append(el);
        }
        for (const label of [...base.querySelectorAll(':scope > .qb-check')])
            pub.querySelector('.qb-grid').append(label);
        const actions = base.querySelector('.qb-actions');
        if (actions) header.append(actions);
        base.remove();
        for (const panel of [...editor.children]) {
            let area = 'price';
            if (panel.querySelector('.qb-offer-image-editor')) area = 'content';
            else if (panel.querySelector('[data-offer-shortcode]')) area = 'publication';
            if (panel.querySelector('[data-pricing-setup]') || panel.hasAttribute('data-pricing-setup'))
                area = 'price';
            sections[area].append(panel);
        }
        editor.append(header, nav, ...Object.values(sections));
        nav.querySelectorAll('button').forEach((button) =>
            button.addEventListener('click', () => {
                state.area = button.dataset.offerAreaButton;
                show(editor);
            })
        );
        preview(ctx, sections.price, o);
        show(editor);
        filter();
        editor.addEventListener('input', (e) => {
            if (e.target.dataset.bind?.endsWith('.name'))
                header.querySelector('[data-offer-heading-name]').textContent = e.target.value;
        });
    }
    let previewController;
    function preview(ctx, host, offer) {
        previewController?.abort();
        previewController = new AbortController();
        const signal = previewController.signal;
        if (
            offer.kind !== 'fixed' ||
            offer.use_variants ||
            offer.surcharges?.length ||
            offer.component_ids?.length
        )
            return;
        const box = document.createElement('section');
        box.className = 'qb-panel qb-price-preview';
        const quantity = true;
        box.innerHTML =
            '<h2>' +
            esc(__('Test the calculation', 'offerweave')) +
            '</h2><p class="qb-muted">' +
            esc(
                __(
                    'Uses your unsaved draft. This preview does not save settings or create a request. Further offers in a customer selection may change request eligibility.',
                    'offerweave'
                )
            ) +
            '</p><div class="qb-grid"><label class="qb-field"><span>' +
            esc(
                offer.input_unit_plural ||
                    ctx.config.settings.quantity_label ||
                    offer.input_unit_singular ||
                    (quantity ? __('Quantity', 'offerweave') : __('Total input quantity', 'offerweave'))
            ) +
            '</span><input type="number" min="1" max="100000" step="1" data-test-quantity value="' +
            (quantity ? 1 : offer.default_participants) +
            '"></label></div><button type="button" class="qb-secondary" data-test-price>' +
            esc(__('Calculate price', 'offerweave')) +
            '</button><div data-test-result aria-live="polite"></div>';
        host.append(box);
        let revision = 0;
        ctx.root.addEventListener(
            'input',
            () => {
                ++revision;
                box.querySelector('[data-test-result]').replaceChildren();
            },
            { signal }
        );
        ctx.root.addEventListener(
            'change',
            () => {
                ++revision;
                box.querySelector('[data-test-result]').replaceChildren();
            },
            { signal }
        );
        box.querySelector('[data-test-price]').addEventListener('click', async () => {
            for (const input of box.querySelectorAll('input,select')) if (!input.reportValidity()) return;
            const token = ++revision,
                output = box.querySelector('[data-test-result]');
            const item = {
                line_id: 'admin-preview',
                offer_id: offer.id,
                [quantity ? 'quantity' : 'participants']: Number(
                    box.querySelector('[data-test-quantity]').value
                ),
            };
            output.className = '';
            output.textContent = __('Calculating …', 'offerweave');
            try {
                const response = await ctx.api('admin/price-preview', { config: ctx.config, items: [item] });
                if (revision !== token || !box.isConnected) return;
                const q = response.quote,
                    line = q.items[0];
                const money = (value) =>
                    offerweave_Money.format(
                        value,
                        q.currency || 'EUR',
                        window.offerweave_admin.locale || 'de_DE'
                    );
                output.innerHTML =
                    '<p>' +
                    (line.details || []).map(esc).join('<br>') +
                    '</p><dl class="qb-tax-rows">' +
                    (line.breakdown || [])
                        .map(
                            (row) =>
                                '<div><dt>' +
                                esc(row.label) +
                                '</dt><dd>' +
                                esc(money(row.cents)) +
                                '</dd></div>'
                        )
                        .join('') +
                    '</dl>' +
                    ctx.totals(q.summary) +
                    (q.request_requirement?.message
                        ? '<p class="qb-notice">' + esc(q.request_requirement.message) + '</p>'
                        : '');
            } catch (e) {
                if (revision === token && box.isConnected) {
                    output.textContent = e.message;
                    output.className = 'qb-error';
                }
            }
        });
    }
    window.offerweave_OfferWorkspace = {
        mount,
        open,
        role,
        effective,
        area(value) {
            state.area = value;
        },
    };
})();
