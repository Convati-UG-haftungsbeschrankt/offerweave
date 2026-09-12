<?php
namespace OfferWeave;

/** Free package implementation. All functionality in this file is available without an upgrade. */
final class Translations
{
    public static function normalize(array $config): array
    {
        $legacy = !isset($config['languages']);
        $config['languages'] = array_replace(
            ['source_locale' => 'de_DE', 'translations' => []],
            $config['languages'] ?? [],
        );
        if ($legacy) {
            // Seed only unchanged factory text at its original key. Never replace custom copy.
            $stock = EmailConfig::normalize(Defaults::config());
            $stockRows = self::schema($stock);
            $catalog = self::defaultCatalog();
            foreach (self::schema($config) as $key => $row) {
                if (
                    ($stockRows[$key]['source'] ?? null) === $row['source'] &&
                    isset($catalog[$row['source']])
                ) {
                    $config['languages']['translations']['en_US'][$key] = [
                        'source' => $row['source'],
                        'value' => $catalog[$row['source']],
                    ];
                }
            }
        }
        return $config;
    }
    private static function defaultCatalog(): array
    {
        static $catalog;
        return $catalog ??= apply_filters(
            'offerweave_content_dictionary',
            json_decode(file_get_contents(OFFERWEAVE_DIR . 'languages/default-content.json'), true),
        );
    }
    public static function schema(array $c): array
    {
        $rows = [];
        $add = static function ($key, $label, $value, $type = 'text') use (&$rows) {
            $rows[$key] = ['key' => $key, 'label' => $label, 'source' => (string) $value, 'type' => $type];
        };
        foreach (
            [
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
            ]
            as $key
        ) {
            $add(
                'settings:' . $key,
                __('Website', 'offerweave') . ' · ' . $key,
                $c['settings'][$key] ?? '',
                str_ends_with($key, '_url') ? 'url' : 'text',
            );
        }
        $add(
            'legal:additional_info',
            __('Additional service and provider information', 'offerweave'),
            $c['legal']['additional_info'] ?? '',
        );
        foreach (Legal::textSchema() as $key => $definition) {
            $add(
                'legal:' . $key,
                __('Email information texts', 'offerweave') .
                    ' · ' .
                    $definition['label'] .
                    ' (' .
                    $definition['mode'] .
                    ')',
                $c['legal'][$key] ?? '',
            );
        }
        $categories = [];
        foreach ($c['offers'] as $o) {
            if (!Catalog::supports($o)) {
                continue;
            }
            foreach (
                [
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
                ]
                as $key
            ) {
                $add('offer:' . $o['id'] . ':' . $key, $o['name'] . ' · ' . $key, $o[$key] ?? '');
            }
            $add(
                'offer:' . $o['id'] . ':detail_url',
                $o['name'] . ' · ' . __('Detail page URL', 'offerweave'),
                $o['detail_url'] ?? '',
                'url',
            );
            $add(
                'offer:' . $o['id'] . ':image_alt',
                $o['name'] . ' · ' . __('Alternative text', 'offerweave'),
                $o['image']['alt'] ?? '',
            );
            foreach ($o['facts'] ?? [] as $i => $value) {
                $add(
                    'offer:' . $o['id'] . ':fact:' . $i,
                    $o['name'] . ' · ' . __('Key facts', 'offerweave'),
                    $value,
                );
            }
            foreach ($o['features'] as $i => $value) {
                $add(
                    'offer:' . $o['id'] . ':feature:' . $i,
                    // translators: Numeric placeholders are counts or limits; string placeholders are field labels or email addresses.
                    $o['name'] . ' · ' . sprintf(__('Feature %d', 'offerweave'), $i + 1),
                    $value,
                );
            }
            if ($o['category_label'] !== '') {
                $categories[$o['category']] = $o['category_label'];
            }
        }
        foreach ($categories as $id => $label) {
            $add('category:' . $id, __('Category', 'offerweave') . ' · ' . $id, $label);
        }
        foreach ($c['fields'] as $f) {
            foreach (['label', 'placeholder'] as $key) {
                $add('field:' . $f['id'] . ':' . $key, $f['label'] . ' · ' . $key, $f[$key]);
            }
            foreach ($f['options'] as $value) {
                $add(
                    'field:' . $f['id'] . ':option:' . rawurlencode($value),
                    $f['label'] . ' · ' . $value,
                    $value,
                );
            }
        }
        return $rows;
    }
    public static function validate($raw, array $c): array
    {
        if (!is_array($raw) || !in_array($raw['source_locale'] ?? 'de_DE', I18n::LOCALES, true)) {
            throw new \DomainException(__('Invalid content language settings.', 'offerweave')); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Config::validate reaches Api JSON; admin escapes validation text.
        }
        $out = ['source_locale' => $raw['source_locale'] ?? 'de_DE', 'translations' => []];
        $all = $raw['translations'] ?? [];
        if (!is_array($all) || count($all) > 2) {
            throw new \DomainException(__('Invalid translations.', 'offerweave')); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Config::validate reaches Api JSON; admin escapes validation text.
        }
        $schema = self::schema($c);
        foreach ($all as $locale => $entries) {
            if (!in_array($locale, I18n::LOCALES, true) || !is_array($entries) || count($entries) > 5000) {
                throw new \DomainException(
                    __('Unsupported language or too many translations.', 'offerweave'), // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Config::validate reaches Api JSON; admin escapes validation text.
                );
            }
            foreach ($entries as $key => $entry) {
                // Deleted offers/fields are pruned. Unknown paths never become writable configuration.
                if (!isset($schema[$key])) {
                    continue;
                }
                if (
                    !is_array($entry) ||
                    !is_string($entry['source'] ?? null) ||
                    !is_string($entry['value'] ?? null) ||
                    strlen($entry['source']) > 40000 ||
                    strlen($entry['value']) > 40000
                ) {
                    throw new \DomainException(__('A translation is invalid or too long.', 'offerweave')); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Config::validate reaches Api JSON; admin escapes validation text.
                }
                $value = trim($entry['value']);
                $type = $schema[$key]['type'];
                if ($type === 'url' && $value !== '') {
                    $value = esc_url_raw($value, ['http', 'https']);
                    if (!$value || !wp_parse_url($value, PHP_URL_HOST)) {
                        throw new \DomainException(
                            __('Please use a valid HTTP/HTTPS address.', 'offerweave'), // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Config::validate reaches Api JSON; admin escapes validation text.
                        );
                    }
                } else {
                    $value = sanitize_textarea_field($value);
                }
                $out['translations'][$locale][$key] = ['source' => $entry['source'], 'value' => $value];
            }
        }
        foreach ($c['languages']['translations'] ?? [] as $locale => $entries) {
            foreach ($entries as $key => $entry) {
                if (!isset($schema[$key])) {
                    $out['translations'][$locale][$key] = $entry;
                }
            }
        }
        return $out;
    }
    public static function defaults(array $c): array
    {
        $c = self::normalize($c);
        $catalog = self::defaultCatalog();
        foreach (self::schema($c) as $key => $row) {
            if (isset($catalog[$row['source']])) {
                $c['languages']['translations']['en_US'][$key] = [
                    'source' => $row['source'],
                    'value' => $catalog[$row['source']],
                ];
            }
        }
        return $c;
    }
    public static function value(array $c, string $key, string $source, string $locale): string
    {
        if ($locale === ($c['languages']['source_locale'] ?? 'de_DE')) {
            return $source;
        }
        $entry = $c['languages']['translations'][$locale][$key] ?? null;
        return is_array($entry) && ($entry['source'] ?? null) === $source && ($entry['value'] ?? '') !== ''
            ? $entry['value']
            : $source;
    }
    public static function apply(array $c, ?string $locale = null): array
    {
        $c = self::normalize($c);
        $locale ??= I18n::current();
        $base = $c;
        $get = static fn($key, $value) => self::value($base, $key, (string) $value, $locale);
        foreach (
            [
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
            ]
            as $key
        ) {
            $c['settings'][$key] = $get('settings:' . $key, $c['settings'][$key] ?? '');
            if (str_ends_with($key, '_url') && $c['settings'][$key] === ($base['settings'][$key] ?? '')) {
                $c['settings'][$key] = self::pageUrl($c['settings'][$key], $locale);
            }
        }
        if (isset($c['legal'])) {
            foreach (Legal::textSchema() as $key => $definition) {
                $c['legal'][$key] = $get('legal:' . $key, $c['legal'][$key] ?? '');
            }
            $c['legal']['additional_info'] = $get(
                'legal:additional_info',
                $c['legal']['additional_info'] ?? '',
            );
        }
        foreach ($c['offers'] as &$o) {
            if (!Catalog::supports($o)) {
                continue;
            }
            foreach (
                [
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
                ]
                as $key
            ) {
                $o[$key] = $get('offer:' . $o['id'] . ':' . $key, $o[$key] ?? '');
            }
            $o['detail_url'] = $get('offer:' . $o['id'] . ':detail_url', $o['detail_url'] ?? '');
            $o['category_label'] = $get('category:' . $o['category'], $o['category_label']);
            $o['image']['alt'] = $get('offer:' . $o['id'] . ':image_alt', $o['image']['alt'] ?? '');
            foreach ($o['facts'] ?? [] as $i => $fact) {
                $o['facts'][$i] = $get('offer:' . $o['id'] . ':fact:' . $i, $fact);
            }
            foreach ($o['features'] as $i => &$feature) {
                $feature = $get('offer:' . $o['id'] . ':feature:' . $i, $feature);
            }
            unset($feature);
        }
        unset($o);
        foreach ($c['fields'] as &$f) {
            foreach (['label', 'placeholder'] as $key) {
                $f[$key] = $get('field:' . $f['id'] . ':' . $key, $f[$key]);
            }
            $f['option_labels'] = [];
            foreach ($f['options'] as $value) {
                $f['option_labels'][] = $get('field:' . $f['id'] . ':option:' . rawurlencode($value), $value);
            }
        }
        unset($f);
        $c['locale'] = $locale;
        return $c;
    }
    public static function pageUrl(string $url, string $locale): string
    {
        if (!$url || !function_exists('pll_get_post') || !function_exists('pll_languages_list')) {
            return $url;
        }
        $id = url_to_postid($url);
        if (!$id) {
            return $url;
        }
        $slugs = pll_languages_list(['fields' => 'slug']);
        $locales = pll_languages_list(['fields' => 'locale']);
        foreach ($locales as $i => $candidate) {
            if (strtolower(substr($candidate, 0, 2)) === substr($locale, 0, 2)) {
                $translated = pll_get_post($id, $slugs[$i]);
                return $translated ? get_permalink($translated) : $url;
            }
        }
        return $url;
    }
}
