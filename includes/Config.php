<?php
namespace OfferWeave;

/** Free package implementation. All functionality in this file is available without an upgrade. */
final class Config
{
    public const OPTION = 'offerweave_config';

    public static function get(): array
    {
        $stored = get_option(self::OPTION);
        $stored = is_array($stored) ? apply_filters('offerweave_stored_config', $stored) : Defaults::config();
        $stored = array_replace(Defaults::neutral(), $stored);
        $stored['settings'] = array_replace(Defaults::neutral()['settings'], $stored['settings'] ?? []);
        return SelectionRules::normalize(
            Legal::normalize(
                Tax::normalize(Translations::normalize(EmailConfig::normalize(Catalog::upgrade($stored)))),
            ),
        );
    }
    public static function upgradeStored(): void
    {
        $stored = get_option(self::OPTION);
        if (!is_array($stored) || ($stored['schema_version'] ?? null) !== 1) {
            return;
        }
        try {
            $next = LegacyCatalogMigration::convert($stored);
        } catch (\DomainException $error) {
            return; // Preserve the original and allow the admin API to report invalid legacy data.
        }
        add_option('offerweave_config_v1_backup', $stored, '', false);
        update_option(self::OPTION, $next, false);
    }
    public static function project(array $config): array
    {
        $config = Legal::normalize($config);
        $config['offers'] = array_values(array_filter($config['offers'], [Catalog::class, 'supports']));
        foreach ($config['offers'] as &$offer) {
            $offer = array_intersect_key(array_replace(Defaults::offer(), $offer), Defaults::offer());
        }
        unset($offer);
        $config['settings'] = array_intersect_key($config['settings'], Defaults::neutral()['settings']);
        $config['email'] = EmailConfig::snapshot($config['email'] ?? []);
        unset(
            $config['design'],
            $config['add_design'],
            $config['category_design'],
            $config['promotions'],
            $config['surcharges'],
        );
        return $config;
    }
    public static function runtime(): array
    {
        return self::project(self::get());
    }
    public static function preview(array $raw): array
    {
        return self::editable($raw);
    }
    public static function editable(array $raw): array
    {
        return self::validate($raw, self::get());
    }
    public static function import(array $raw): array
    {
        $old = self::get();
        foreach (['design', 'add_design', 'category_design', 'promotions', 'surcharges'] as $key) {
            if (!empty($raw[$key])) {
                throw new \DomainException(
                    // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Validation travels through Api::error JSON; the admin notice and issue renderers escape text at output.
                    __(
                        'This file contains extension settings that Free cannot import. Import it in its original edition. No settings were changed.',
                        'offerweave',
                    ),
                );
            }
        }
        if (!is_array($raw['settings'] ?? [])) {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- API JSON validation error; the editor escapes the message.
            throw new \DomainException(__('Invalid settings.', 'offerweave'));
        }
        foreach (array_diff_key($raw['settings'] ?? [], Defaults::neutral()['settings']) as $value) {
            if (!in_array($value, [null, false, '', [], 0], true)) {
                throw new \DomainException(
                    // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Api::adminValidate returns a WP_Error as JSON; admin transfer catch sends its message to offerweave_Dialogs.notice, which uses textContent.
                    __(
                        'This file contains extension settings that Free cannot import. Import it in its original edition. No settings were changed.',
                        'offerweave',
                    ),
                );
            }
        }
        $email = $raw['email'] ?? [];
        if (!is_array($email)) {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Validation travels through Api::error JSON; the admin notice and issue renderers escape text at output.
            throw new \DomainException(__('Invalid email settings.', 'offerweave'));
        }
        if (
            array_diff_key($email, EmailConfig::defaults(), [
                'smtp_password' => true,
                'smtp_password_set' => true,
            ])
        ) {
            throw new \DomainException(
                // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Validation travels through Api::error JSON; the admin notice and issue renderers escape text at output.
                __(
                    'This file contains extension settings that Free cannot import. Import it in its original edition. No settings were changed.',
                    'offerweave',
                ),
            );
        }
        foreach ($raw['offers'] ?? [] as $offer) {
            if (
                !is_array($offer) ||
                !Catalog::supports($offer) ||
                !empty($offer['design']) ||
                !empty($offer['variant_groups'])
            ) {
                // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Validation travels through Api::error JSON; the admin notice and issue renderers escape text at output.
                throw new \DomainException(__('This configuration format is not supported.', 'offerweave'));
            }
            foreach (array_diff_key($offer, Defaults::offer()) as $value) {
                if (!in_array($value, [null, false, '', [], 0], true)) {
                    throw new \DomainException(
                        // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Validation travels through Api::error JSON; the admin notice and issue renderers escape text at output.
                        __(
                            'This file contains extension settings that Free cannot import. Import it in its original edition. No settings were changed.',
                            'offerweave',
                        ),
                    );
                }
            }
        }
        $raw['configuration_source'] = 'import';
        $next = self::validate($raw, $old);
        $schema = Translations::schema($next);
        foreach ($raw['languages']['translations'] ?? [] as $entries) {
            foreach ($entries as $key => $entry) {
                if (!isset($schema[$key]) && !empty($entry['value'])) {
                    throw new \DomainException(
                        // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Validation travels through Api::error JSON; the admin notice and issue renderers escape text at output.
                        __(
                            'This file contains extension settings that Free cannot import. Import it in its original edition. No settings were changed.',
                            'offerweave',
                        ),
                    );
                }
            }
        }
        return $next;
    }
    private static function text($v, int $max = 500, bool $multiline = false): string
    {
        $length = is_string($v) ? ConfigValidation::length($v) : null;
        if ($length === null || $length > $max * 4) {
            throw new \DomainException(__('A text field is invalid or too long.', 'offerweave')); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Validation reaches Api JSON; admin escapes messages and issue metadata.
        }
        return $multiline ? sanitize_textarea_field($v) : sanitize_text_field($v);
    }
    private static function id($v): string
    {
        if (!is_string($v) || !preg_match('/^[a-z][a-z0-9_-]{0,63}$/D', $v)) {
            throw new \DomainException(
                // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Validation reaches Api JSON; admin escapes messages and issue metadata.
                __(
                    'IDs must start with a lowercase letter and may only contain a–z, 0–9, _ and -.',
                    'offerweave',
                ),
            );
        }
        return $v;
    }
    private static function flag($v): bool
    {
        if (!is_bool($v)) {
            throw new \DomainException(__('Invalid switch value.', 'offerweave')); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Validation reaches Api JSON; admin escapes messages and issue metadata.
        }
        return $v;
    }
    private static function choice($v, array $allowed): string
    {
        if (!in_array($v, $allowed, true)) {
            throw new \DomainException(__('Invalid configuration choice.', 'offerweave')); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Validation reaches Api JSON; admin escapes messages and issue metadata.
        }
        return $v;
    }
    private static function list($v, int $max): array
    {
        if (!is_array($v) || !array_is_list($v) || count($v) > $max) {
            throw new \DomainException(__('Invalid list or too many entries.', 'offerweave')); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Validation reaches Api JSON; admin escapes messages and issue metadata.
        }
        return $v;
    }
    private static function url($v): string
    {
        $v = self::text($v, 2000);
        if ($v === '') {
            return '';
        }
        $safe = esc_url_raw($v, ['http', 'https']);
        if (!$safe || !wp_parse_url($safe, PHP_URL_HOST)) {
            throw new \DomainException(__('Please use a valid HTTP/HTTPS address.', 'offerweave')); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Validation reaches Api JSON; admin escapes messages and issue metadata.
        }
        return $safe;
    }
    public static function validate(array $raw, ?array $previous = null): array
    {
        $previous ??= [];
        if ($previous && Currency::config($raw) !== Currency::config($previous)) {
            $previous = Currency::redenominate($previous, Currency::config($raw));
        }
        if (($raw['schema_version'] ?? 2) !== 2) {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Validation travels through Api::error JSON; the admin notice and issue renderers escape text at output.
            throw new \DomainException(__('This configuration format is not supported.', 'offerweave'));
        }
        $c = array_replace(Defaults::neutral(), $previous);
        $c['schema_version'] = 2;
        $c['offers'] = [];
        $c['fields'] = [];
        if (isset($raw['configuration_source'])) {
            $c['configuration_source'] = self::choice($raw['configuration_source'], ['import']);
        }
        $oldOffers = array_column($previous['offers'] ?? [], null, 'id');
        $seen = [];
        foreach (self::list($raw['offers'] ?? [], 150) as $offer) {
            if (!is_array($offer)) {
                // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Validation travels through Api::error JSON; the admin notice and issue renderers escape text at output.
                throw new \DomainException(__('Invalid offer.', 'offerweave'));
            }
            $id = self::id($offer['id'] ?? null);
            if (isset($seen[$id])) {
                // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Validation travels through Api::error JSON; the admin notice and issue renderers escape text at output.
                throw new \DomainException(__('Duplicate offer ID:', 'offerweave') . ' ' . $id);
            }
            $seen[$id] = true;
            if (isset($oldOffers[$id]) && !Catalog::supports($oldOffers[$id])) {
                $c['offers'][] = $oldOffers[$id];
                continue;
            }
            if (!Catalog::supports($offer)) {
                // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Validation travels through Api::error JSON; the admin notice and issue renderers escape text at output.
                throw new \DomainException(__('This configuration format is not supported.', 'offerweave'));
            }
            $d = array_replace(Defaults::offer(), $offer);
            $o = [
                'id' => $id,
                'name' => self::text($d['name'], 120),
                'enabled' => self::flag($d['enabled']),
                'image' => OfferImage::validate($d['image']),
                'catalog_visible' => self::flag($d['catalog_visible']),
                'category_label' => self::text($d['category_label'], 120),
                'category' => self::id($d['category']),
                'kind' => self::choice($d['kind'], ['fixed']),
                'description' => self::text($d['description'], 600, true),
                'eyebrow' => self::text($d['eyebrow'], 80),
                'detail_url' => self::url($d['detail_url']),
                'badge' => self::text($d['badge'], 80),
                'price_unit' => self::text($d['price_unit'], 60),
                'unit_help_enabled' => self::flag($d['unit_help_enabled']),
                'unit_help_text' => self::text($d['unit_help_text'], 160, true),
                'input_unit_singular' => self::text($d['input_unit_singular'], 60),
                'input_unit_plural' => self::text($d['input_unit_plural'], 60),

                'price_note' => self::text($d['price_note'], 160),
                'content_heading' => self::text($d['content_heading'], 80),
                'facts' => array_map(fn($f) => self::text($f, 120), self::list($d['facts'], 4)),
                'features' => [],
                'period' => self::choice($d['period'], ['once', 'month']),
                'show_term_total' => self::flag($d['show_term_total']),
                'term_label' => self::text($d['term_label'], 160),
                'term_total_label' => self::text($d['term_total_label'], 160),
                'tax_rate_bps' => $d['tax_rate_bps'] === null ? null : Tax::rate($d['tax_rate_bps']),
            ];
            $icons = self::list($d['fact_icons'], 4);
            $o['fact_icons'] = [];
            foreach ($icons as $index => $icon) {
                try {
                    $o['fact_icons'][] = OfferImage::validateIcon($icon);
                } catch (\DomainException $e) {
                    throw new ConfigValidationError([
                        // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Validation reaches Api JSON; admin escapes messages and issue metadata.
                        ConfigValidation::offerIssue(
                            $raw['offers'],
                            $id, // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Validation reaches Api JSON; admin escapes messages and issue metadata.
                            'fact_icons.' . $index, // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Validation reaches Api JSON; admin escapes messages and issue metadata.
                            /* translators: %d: one-based key fact position. */
                            sprintf(__('Key fact %d: icon', 'offerweave'), $index + 1), // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Validation reaches Api JSON; admin escapes messages and issue metadata.
                            $e->getMessage(), // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Validation reaches Api JSON; admin escapes messages and issue metadata.
                        ),
                    ]);
                }
            }
            $o['fact_icons'] = array_slice(
                array_pad($o['fact_icons'], count($o['facts']), OfferImage::iconDefaults()),
                0,
                count($o['facts']),
            );
            if (!$o['name']) {
                throw new \DomainException(__('Each offer needs a name.', 'offerweave')); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Validation reaches Api JSON; admin escapes messages and issue metadata.
            }
            foreach (self::list($d['features'], 12) as $f) {
                $feature = self::text($f, 240);
                if (trim($feature) !== '') {
                    $o['features'][] = $feature;
                }
            }

            foreach (
                ['term_months' => [1, 60], 'duration_minutes' => [0, 1440], 'base_cents' => [0, 100000000]]
                as $key => [$min, $max]
            ) {
                $o[$key] = Pricing::integer($d[$key], $min, $max, $key);
            }
            // Unknown stored extension fields are retained, never interpreted or accepted from edits.
            $c['offers'][] = array_replace($oldOffers[$id] ?? [], $o);
        }
        foreach ($oldOffers as $id => $offer) {
            if (!isset($seen[$id]) && !Catalog::supports($offer)) {
                $c['offers'][] = $offer;
            }
        }
        $c['legacy_item_map'] = $previous['legacy_item_map'] ?? [];
        $seen = [];
        foreach (self::list($raw['fields'] ?? [], 40) as $f) {
            if (!is_array($f)) {
                throw new \DomainException(__('Invalid form field.', 'offerweave')); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Validation reaches Api JSON; admin escapes messages and issue metadata.
            }
            $id = self::id($f['id'] ?? null);
            if (isset($seen[$id])) {
                throw new \DomainException(__('Duplicate form field ID:', 'offerweave') . ' ' . $id); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Validation reaches Api JSON; admin escapes messages and issue metadata.
            }
            $seen[$id] = true;
            $field = [
                'id' => $id,
                'label' => self::text($f['label'] ?? '', 160),
                'type' => self::choice($f['type'] ?? 'text', [
                    'text',
                    'email',
                    'tel',
                    'number',
                    'date',
                    'textarea',
                    'select',
                    'checkbox',
                ]),
                'required' => self::flag($f['required'] ?? false),
                'enabled' => self::flag($f['enabled'] ?? true),
                'placeholder' => self::text($f['placeholder'] ?? '', 240),
                'options' => [],
            ];
            if (!$field['label']) {
                throw new \DomainException(__('Each form field needs a label.', 'offerweave')); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Validation reaches Api JSON; admin escapes messages and issue metadata.
            }
            foreach (self::list($f['options'] ?? [], 40) as $option) {
                $field['options'][] = self::text($option, 160);
            }
            $field['options'] = array_values(
                array_unique(array_filter($field['options'], fn($v) => $v !== '')),
            );
            if ($field['type'] === 'select' && !$field['options']) {
                throw new \DomainException(__('A selection field needs at least one option.', 'offerweave')); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Validation reaches Api JSON; admin escapes messages and issue metadata.
            }
            $c['fields'][] = $field;
        }
        $s = $raw['settings'] ?? [];
        if (!is_array($s)) {
            throw new \DomainException(__('Invalid settings.', 'offerweave')); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Validation reaches Api JSON; admin escapes messages and issue metadata.
        }
        $s = array_replace(Defaults::neutral()['settings'], $s);
        // Keep existing extension settings opaque; incoming unknown values are never writable.
        $clean = array_diff_key($previous['settings'] ?? [], Defaults::neutral()['settings']);
        $clean['currency'] = Currency::validate($s['currency']);
        $clean['input_scope'] = self::choice($s['input_scope'], ['shared', 'offer']);
        foreach (
            [
                'brand' => 120,
                'privacy_text' => 2000,
                'intro' => 1000,
                'quantity_label' => 120,
                'scope_help' => 1000,
                'individual_quantity_label' => 120,
                'submit_label' => 120,
                'success_text' => 1000,
                'captcha_site_key' => 300,
            ]
            as $key => $max
        ) {
            $clean[$key] = self::text($s[$key], $max, true);
        }
        foreach (['accent', 'surface'] as $key) {
            if (!is_string($s[$key]) || !preg_match('/^#[0-9a-fA-F]{6}$/D', $s[$key])) {
                throw new \DomainException(
                    __('Please specify colors as six-digit hex values.', 'offerweave'), // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Validation reaches Api JSON; admin escapes messages and issue metadata.
                );
            }
            $clean[$key] = $s[$key];
        }
        $email = self::text($s['notification_email'], 190);
        if ($email !== '' && !is_email($email)) {
            throw new \DomainException(__('Invalid email recipient.', 'offerweave')); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Validation reaches Api JSON; admin escapes messages and issue metadata.
        }
        $clean['notification_email'] = $email;
        $clean['reply_field'] = self::id($s['reply_field']);
        $reply = array_values(
            array_filter(
                $c['fields'],
                fn($f) => $f['id'] === $clean['reply_field'] &&
                    $f['enabled'] &&
                    $f['required'] &&
                    $f['type'] === 'email',
            ),
        );
        if (!$reply) {
            throw new \DomainException(
                __('The reply address must be an active, required email field.', 'offerweave'), // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Validation reaches Api JSON; admin escapes messages and issue metadata.
            );
        }
        foreach (['selection_url', 'privacy_url'] as $key) {
            $clean[$key] = self::url($s[$key]);
        }
        foreach (
            [
                'radius' => [0, 24],
                'rate_limit' => [1, 100],
                'min_seconds' => [0, 30],
                'retention_days' => [0, 3650],
            ]
            as $key => [$min, $max]
        ) {
            $clean[$key] = Pricing::integer($s[$key], $min, $max, $key);
        }
        $clean['delete_on_uninstall'] = self::flag($s['delete_on_uninstall']);
        $clean['captcha_provider'] = self::choice($s['captcha_provider'], ['none', 'hcaptcha', 'turnstile']);
        $secret = self::text($s['captcha_secret'], 500);
        if ($secret === '' && empty($s['clear_captcha_secret'])) {
            $secret = $previous['settings']['captcha_secret'] ?? '';
        }
        if (!empty($s['clear_captcha_secret'])) {
            $secret = '';
        }
        $clean['captcha_secret'] = $secret;
        if ($clean['captcha_provider'] !== 'none' && (!$secret || !$clean['captcha_site_key'])) {
            throw new \DomainException(
                __('CAPTCHA protection requires a site key and a secret key.', 'offerweave'), // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Validation reaches Api JSON; admin escapes messages and issue metadata.
            );
        }
        $c['settings'] = $clean;
        $c['tax'] = Tax::validate($raw['tax'] ?? []);
        $c['selection_rules'] = SelectionRules::validate($raw['selection_rules'] ?? [], $c['offers']);
        $c['legal'] = Legal::validate($raw['legal'] ?? []);
        if ($c['legal']['audience'] === 'consumer') {
            if (!$c['tax']['enabled']) {
                throw new \DomainException(
                    // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Validation reaches Api JSON; admin escapes messages and issue metadata.
                    __(
                        'For consumers, enable tax calculation and configure the applicable rates. Use an explicit zero rate only where appropriate.',
                        'offerweave',
                    ),
                );
            }
            $c['tax']['display'] = 'gross';
        }
        $c['email'] = EmailConfig::validate($raw['email'] ?? [], $c['fields'], $previous['email'] ?? []);
        $c['languages'] = Translations::validate(
            $raw['languages'] ?? ($previous['languages'] ?? Translations::normalize($c)['languages']),
            $c,
        );
        return $c;
    }
    public static function publicConfig(?array $c = null, ?int $now = null): array
    {
        $c = self::project(Catalog::upgrade($c ?? self::get()));
        $now = $now ?? time();
        $settings = array_intersect_key(
            $c['settings'],
            array_flip([
                'brand',
                'currency',
                'accent',
                'surface',
                'radius',
                'selection_url',
                'privacy_url',
                'privacy_text',
                'intro',
                'input_scope',
                'quantity_label',
                'scope_help',
                'individual_quantity_label',

                'submit_label',
                'success_text',
                'captcha_provider',
                'captcha_site_key',
            ]),
        );
        $settings['currency'] ??= 'EUR';
        $offers = array_values(array_filter($c['offers'], fn($o) => $o['enabled']));
        foreach ($offers as &$offer) {
            $offer['image'] = OfferImage::publicImage($offer);
            $offer['fact_icons'] = OfferImage::publicIcons($offer);
        }
        unset($offer);
        return [
            'schema_version' => 2,
            'legacy_item_map' => $c['legacy_item_map'],
            'tax' => Tax::validate($c['tax'] ?? []),
            'selection_rules' => $c['selection_rules'] ?? SelectionRules::defaults(),
            'legal' => Legal::publicInfo($c),
            'offers' => $offers,
            'fields' => array_values(array_filter($c['fields'], fn($f) => $f['enabled'])),
            'settings' => $settings,
            'locale' => $c['locale'] ?? I18n::current(),
        ];
    }
    public static function revision(array $c, ?int $now = null): string
    {
        return hash('sha256', wp_json_encode(self::publicConfig($c, $now)));
    }
    public static function adminConfig(): array
    {
        return self::redact(self::get());
    }
    public static function redact(array $c): array
    {
        $c['settings']['captcha_secret_set'] = !empty($c['settings']['captcha_secret']);
        $c['settings']['captcha_secret'] = '';
        $c['email'] = EmailConfig::redact($c['email'] ?? EmailConfig::defaults());
        return $c;
    }
    public static function export(): array
    {
        $c = self::get();
        $c['settings']['captcha_secret'] = '';
        $c['settings']['captcha_site_key'] = '';
        $c['settings']['captcha_provider'] = 'none';
        $c['settings']['notification_email'] = '';
        $c['settings']['selection_url'] = '';
        $c['settings']['privacy_url'] = '';
        // Export preserved extension settings without executing them or exporting credentials.
        $c['email'] = EmailConfig::redact($c['email']);
        unset($c['email']['smtp_password_set']);
        $c['email']['mode'] = 'off';
        $c['email']['from_email'] = '';
        $c['email']['reply_to'] = '';
        foreach ($c['languages']['translations'] as &$translations) {
            unset($translations['settings:selection_url'], $translations['settings:privacy_url']);
        }
        unset($translations);
        foreach ($c['offers'] as &$offer) {
            $offer['image'] = OfferImage::export($offer);
            $offer['fact_icons'] = OfferImage::exportIcons($offer);
        }
        unset($offer);
        return $c;
    }
}
