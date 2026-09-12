(() => {
    'use strict';
    const { __, sprintf } = window.wp.i18n;
    const esc = (v) =>
        String(v ?? '').replace(
            /[&<>"']/g,
            (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' })[c]
        );
    let ctx,
        target = '',
        area = 'settings',
        entries = [];
    const names = () => ({ de_DE: __('German', 'offerweave'), en_US: __('English', 'offerweave') });
    const encoded = (s) =>
        encodeURIComponent(s).replace(/[!'()*]/g, (c) => '%' + c.charCodeAt(0).toString(16).toUpperCase());
    // Match Catalog::supports: retained extension data is not an editable Free offer.
    const supports = (o) =>
        (o.kind || 'fixed') === 'fixed' &&
        !o.selection_parent &&
        !o.use_variants &&
        !o.surcharges?.length &&
        !o.component_ids?.length;
    function schema(c) {
        const rows = [];
        const add = (key, label, value, group, type = 'text') =>
            rows.push({ key, label, source: value || '', group, type });
        // Values come from the current draft, including newly created offers/fields.
        const settings = [
            'brand',
            'intro',
            'submit_label',
            'success_text',
            'privacy_text',
            'selection_url',
            'privacy_url',
            'quantity_label',
            'scope_help',
            'individual_quantity_label',
        ];
        const supplied = new Map(
            (window.offerweave_admin.translation_schema || []).map((r) => [r.key, r.label])
        );
        for (const key of settings)
            add(
                'settings:' + key,
                supplied.get('settings:' + key) || key,
                c.settings[key],
                'settings',
                key.endsWith('_url') ? 'url' : 'text'
            );
        add(
            'legal:additional_info',
            __('Additional service and provider information', 'offerweave'),
            c.legal?.additional_info,
            'legal'
        );
        for (const [key, definition] of Object.entries(window.offerweave_admin.legalTexts || {}))
            add('legal:' + key, definition.label + ' (' + definition.mode + ')', c.legal?.[key], 'legal');
        const categories = new Map();
        for (const o of c.offers.filter(supports)) {
            const group = 'offer:' + o.id;
            for (const key of [
                'name',
                'description',
                'eyebrow',
                'badge',
                'price_unit',
                'unit_help_text',
                'input_unit_singular',
                'input_unit_plural',
                'price_note',
                'term_label',
                'term_total_label',
                'content_heading',
            ])
                add(
                    group + ':' + key,
                    supplied.get(group + ':' + key) || o.name + ' · ' + key,
                    o[key],
                    group
                );
            add(
                group + ':detail_url',
                o.name + ' · ' + __('Detail page URL', 'offerweave'),
                o.detail_url,
                group,
                'url'
            );
            add(
                group + ':image_alt',
                o.name + ' · ' + __('Alternative text', 'offerweave'),
                o.image?.alt,
                group
            );
            (o.facts || []).forEach((value, i) =>
                add(
                    group + ':fact:' + i,
                    o.name + ' · ' + __('Key facts', 'offerweave') + ' ' + (i + 1),
                    value,
                    group
                )
            );
            (o.features || []).forEach((value, i) =>
                add(
                    group + ':feature:' + i,
                    // translators: %d: feature number.
                    o.name + ' · ' + sprintf(__('Feature %d', 'offerweave'), i + 1),
                    value,
                    group
                )
            );
            if (o.category_label) categories.set(o.category, o.category_label);
        }
        for (const [id, value] of categories) add('category:' + id, value, value, 'categories');
        for (const f of c.fields) {
            for (const key of ['label', 'placeholder'])
                add('field:' + f.id + ':' + key, f.label + ' · ' + key, f[key], 'fields');
            for (const value of f.options || [])
                add('field:' + f.id + ':option:' + encoded(value), f.label + ' · ' + value, value, 'fields');
        }
        return rows;
    }
    function render(context) {
        ctx = context;
        const c = ctx.config();
        c.languages ||= { source_locale: 'de_DE', translations: {} };
        if (!target || target === c.languages.source_locale)
            target = c.languages.source_locale === 'de_DE' ? 'en_US' : 'de_DE';
        entries = schema(c);
        const areas = {
            settings: __('Website texts and links', 'offerweave'),
            categories: __('Categories', 'offerweave'),
            fields: __('Form fields', 'offerweave'),
            legal: __('Email information texts', 'offerweave'),
        };
        for (const o of c.offers.filter(supports)) areas['offer:' + o.id] = o.name + ' · ' + o.id;
        if (!areas[area]) area = 'settings';
        const options = (list, selected, disabled = '') =>
            Object.entries(list)
                .map(
                    ([id, text]) =>
                        '<option value="' +
                        esc(id) +
                        '"' +
                        (id === selected ? ' selected' : '') +
                        (id === disabled ? ' disabled' : '') +
                        '>' +
                        esc(text) +
                        '</option>'
                )
                .join('');
        const translations = c.languages.translations[target] || {};
        return (
            '<section class="qb-panel"><h2>' +
            esc(__('Languages', 'offerweave')) +
            '</h2><p>' +
            esc(
                __(
                    'Prices, offer IDs and selections are shared across languages. Translate only the presentation here. Edit original content in its usual tab.',
                    'offerweave'
                )
            ) +
            '</p><div class="qb-grid"><label>' +
            esc(__('Original content language', 'offerweave')) +
            '<select data-source-language>' +
            options(names(), c.languages.source_locale) +
            '</select></label><label>' +
            esc(__('Translation language', 'offerweave')) +
            '<select data-target-language>' +
            options(names(), target, c.languages.source_locale) +
            '</select></label><label>' +
            esc(__('Section', 'offerweave')) +
            '<select data-language-area>' +
            options(areas, area) +
            '</select></label></div><p class="qb-muted">' +
            esc(
                __(
                    'Blank translations use the original text. If an original text changes, its old translation is retained for review but is not published until confirmed again.',
                    'offerweave'
                )
            ) +
            '</p><p>' +
            esc(
                __(
                    'Without Polylang, the website language is used. With Polylang, the current page language and linked request/privacy pages are used automatically.',
                    'offerweave'
                )
            ) +
            '</p></section><section class="qb-panel"><h2>' +
            esc(areas[area]) +
            '</h2><div class="qb-language-entries">' +
            entries
                .filter((r) => r.group === area)
                .map((r) => {
                    const e = translations[r.key],
                        stale = e?.value && e.source !== r.source;
                    return (
                        '<div class="qb-language-row"><h3>' +
                        esc(r.label) +
                        '</h3><div class="qb-grid"><label>' +
                        esc(__('Original text', 'offerweave')) +
                        '<textarea rows="4" readonly>' +
                        esc(r.source) +
                        '</textarea></label><label>' +
                        esc(names()[target]) +
                        '<textarea rows="4" data-translation-key="' +
                        esc(r.key) +
                        '" maxlength="40000">' +
                        esc(e?.value || '') +
                        '</textarea></label></div>' +
                        (stale
                            ? '<p class="qb-error">' +
                              esc(
                                  __(
                                      'Original text changed. Review this translation and confirm it before publishing.',
                                      'offerweave'
                                  )
                              ) +
                              '</p><button type="button" class="qb-secondary" data-confirm-translation="' +
                              esc(r.key) +
                              '">' +
                              esc(__('Confirm reviewed translation', 'offerweave')) +
                              '</button>'
                            : '') +
                        '</div>'
                    );
                })
                .join('') +
            '</div></section>'
        );
    }
    function update(key, value) {
        const c = ctx.config(),
            row = entries.find((r) => r.key === key);
        if (!row) return;
        const languages = structuredClone(c.languages);
        languages.translations[target] ||= {};
        languages.translations[target][key] = { source: row.source, value };
        ctx.set('languages', languages);
    }
    function bind() {
        const root = ctx.root;
        root.querySelector('[data-source-language]').onchange = async (event) => {
            const nextLocale = event.target.value;
            event.target.value = ctx.config().languages.source_locale;
            if (
                !(await offerweave_Dialogs.confirm(
                    __(
                        'This changes which language the existing original texts represent. It does not translate or replace them. Continue?',
                        'offerweave'
                    ),
                    {
                        title: __('Change source language', 'offerweave'),
                        confirmLabel: __('Change language', 'offerweave'),
                    }
                ))
            ) {
                ctx.render();
                return;
            }
            ctx.set('languages.source_locale', nextLocale);
            target = '';
            ctx.render();
        };
        root.querySelector('[data-target-language]').onchange = (event) => {
            target = event.target.value;
            ctx.render();
        };
        root.querySelector('[data-language-area]').onchange = (event) => {
            area = event.target.value;
            ctx.render();
        };
        root.querySelectorAll('[data-translation-key]').forEach((input) =>
            input.addEventListener('input', () => update(input.dataset.translationKey, input.value))
        );
        root.querySelectorAll('[data-confirm-translation]').forEach(
            (button) =>
                (button.onclick = () => {
                    const key = button.dataset.confirmTranslation;
                    update(key, ctx.config().languages.translations[target][key].value);
                    ctx.render();
                })
        );
    }
    window.offerweave_Languages = { render, bind };
})();
