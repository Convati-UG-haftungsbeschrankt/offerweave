<?php
namespace OfferWeave;

/** WordPress gettext with a scoped locale for REST calls and saved customer emails. */
final class I18n
{
    public const LOCALES = ['de_DE', 'en_US'];
    private static ?string $forced = null;

    public static function canonical(string $locale): string
    {
        return str_starts_with(strtolower($locale), 'de') ? 'de_DE' : 'en_US';
    }
    public static function current(): string
    {
        return self::canonical(self::locale());
    }
    public static function website(): string
    {
        if (function_exists('pll_current_language')) {
            $locale = pll_current_language('locale');
            if (is_string($locale) && $locale !== '') {
                return self::canonical($locale);
            }
        }
        return self::canonical(get_locale());
    }
    public static function locale(): string
    {
        return self::$forced ?? determine_locale();
    }
    public static function websiteLocale(): string
    {
        $locale = function_exists('pll_current_language') ? pll_current_language('locale') : null;
        return is_string($locale) && $locale !== '' ? $locale : get_locale();
    }
    private static function scripts(): array
    {
        static $scripts;
        return $scripts ??= json_decode(file_get_contents(OFFERWEAVE_DIR . 'languages/scripts.json'), true);
    }
    public static function handles(): array
    {
        return array_keys(self::scripts());
    }
    private static function messages(array $data): array
    {
        return $data['locale_data']['offerweave'] ?? ($data['locale_data']['messages'] ?? []);
    }
    public static function run(string $locale, callable $fn)
    {
        if (
            !preg_match('/^[a-z]{2,3}(?:_[A-Za-z0-9]+)*$/', $locale) ||
            (!in_array($locale, self::LOCALES, true) &&
                !in_array($locale, [determine_locale(), self::websiteLocale()], true))
        ) {
            throw new \DomainException(__('Unsupported language.', 'offerweave')); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- REST catches invalid requested locales; render locales are canonicalized.
        }
        $previous = self::$forced;
        self::$forced = $locale;
        $switched = switch_to_locale($locale);
        if (!$switched && determine_locale() !== $locale) {
            // Core cannot switch to a language without an installed Core language pack.
            // Keep the requested content language, with WordPress' English source strings.
            $switched = switch_to_locale('en_US');
        }
        try {
            return $fn();
        } finally {
            if ($switched) {
                restore_previous_locale();
            }
            self::$forced = $previous;
        }
    }
    public static function requested(\WP_REST_Request $request): string
    {
        $locale = $request->get_param('locale');
        if ($locale === null) {
            return self::websiteLocale();
        }
        if (!is_string($locale) || !in_array($locale, self::LOCALES, true)) {
            throw new \DomainException(__('Unsupported language.', 'offerweave')); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- REST catches invalid requested locales; render locales are canonicalized.
        }
        return $locale === self::website() ? self::websiteLocale() : $locale;
    }
    public static function snapshot(array $snapshot): string
    {
        $locale = $snapshot['locale'] ?? 'de_DE';
        return is_string($locale) && in_array($locale, self::LOCALES, true) ? $locale : 'de_DE';
    }
    public static function htmlLanguage(?string $locale = null): string
    {
        return ($locale ?? self::current()) === 'de_DE' ? 'de' : 'en';
    }
    public static function number(float $number, int $decimals = 0): string
    {
        return number_format(
            $number,
            $decimals,
            self::current() === 'de_DE' ? ',' : '.',
            self::current() === 'de_DE' ? '.' : ',',
        );
    }
    public static function date(int $timestamp, bool $time = false): string
    {
        return wp_date((self::current() === 'de_DE' ? 'd.m.Y' : 'Y-m-d') . ($time ? ' H:i' : ''), $timestamp);
    }
    public static function jed(string $locale, string $handle = 'offerweave-frontend'): array
    {
        if (
            !preg_match('/^[a-z]{2,3}(?:_[A-Za-z0-9]+)*$/', $locale) ||
            !in_array($handle, self::handles(), true)
        ) {
            return [];
        }
        $registered = wp_script_is($handle, 'registered');
        if (!$registered) {
            $spec = self::scripts()[$handle];
            wp_register_script(
                $handle,
                OFFERWEAVE_URL . $spec['file'],
                ['wp-i18n'],
                OFFERWEAVE_VERSION,
                true,
            );
        }
        try {
            return self::run($locale, static function () use ($handle): array {
                wp_set_script_translations($handle, 'offerweave');
                $data = json_decode(load_script_textdomain($handle, 'offerweave') ?: '{}', true);
                return self::messages(is_array($data) ? $data : []);
            });
        } finally {
            if (!$registered) {
                wp_deregister_script($handle);
            }
        }
    }
}
