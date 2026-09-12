/* Shared monetary formatting. Currency metadata ships with the plugin; no external requests. */
(() => {
    'use strict';
    const catalog = () => window.offerweave_admin?.currencies || window.offerweave_boot?.currencies || {};
    const definition = (code = 'EUR') => {
        const value = catalog()[code];
        if (!value) throw new Error('Unsupported currency: ' + code);
        return value;
    };
    const factor = (code) => 10 ** definition(code).digits;
    const format = (amount, code = 'EUR', locale = 'de_DE', compact = false) => {
        const d = definition(code),
            lang = locale === 'de_DE' ? 'de' : 'en';
        const digits = compact && amount % factor(code) === 0 ? 0 : d.digits;
        const number = new Intl.NumberFormat(lang === 'de' ? 'de-DE' : 'en-US', {
            minimumFractionDigits: digits,
            maximumFractionDigits: digits,
        }).format(Math.abs(amount) / factor(code));
        return (amount < 0 ? '-' : '') + d[lang].pattern.replace('{amount}', number);
    };
    // Change denomination without exchange rates. Never silently round an existing price.
    const redenominate = (config, next) => {
        const before = config.settings.currency || 'EUR';
        const from = factor(before),
            to = factor(next),
            result = structuredClone(config);
        const convert = (object, key, maximum = 100000000) => {
            if (object[key] == null) return;
            const value = object[key] * to;
            if (!Number.isSafeInteger(value) || value % from !== 0 || value / from > maximum)
                throw new Error('precision');
            object[key] = value / from;
        };
        for (const offer of result.offers || []) {
            for (const key of [
                'base_cents',
                'onsite_cents',
                'travel_km_cents',
                'travel_hour_cents',
                'minimum_cents',
            ])
                convert(offer, key, key === 'travel_km_cents' ? 100000 : 100000000);
            for (const rows of [offer.tiers, offer.bands])
                for (const row of rows || []) convert(row, 'cents');
            for (const key of Object.keys(offer.component_prices || {})) convert(offer.component_prices, key);
            for (const group of offer.variant_groups || [])
                for (const option of group.options || [])
                    if (option.basis !== 'percent') convert(option, 'value');
        }
        for (const promotion of result.promotions || [])
            if (promotion.kind !== 'percent') convert(promotion, 'value');
        for (const charge of result.surcharges || [])
            if (charge.basis !== 'percent') convert(charge, 'value');
        if (result.selection_rules) convert(result.selection_rules, 'min_net_cents');
        result.settings.currency = next;
        return result;
    };
    window.offerweave_Money = { definition, factor, format, redenominate };
})();
