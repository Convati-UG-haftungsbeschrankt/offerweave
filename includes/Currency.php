<?php
namespace OfferWeave;

/** ISO monetary units; legacy *_cents keys contain integer minor units of the selected currency. */
final class Currency
{
    private static string $current = 'EUR';

    public static function catalog(): array
    {
        static $data;
        return $data ??= json_decode(
            file_get_contents(__DIR__ . '/currencies.json'),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
    }

    public static function validate($code): string
    {
        if (!is_string($code) || !isset(self::catalog()[$code])) {
            throw new \DomainException(__('Please choose a supported currency.', 'offerweave')); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- API JSON; admin escapes validation errors.
        }
        return $code;
    }

    public static function config(array $config): string
    {
        return self::validate($config['settings']['currency'] ?? 'EUR');
    }

    public static function snapshot(array $snapshot): string
    {
        return self::validate($snapshot['quote']['currency'] ?? ($snapshot['currency'] ?? 'EUR'));
    }

    public static function digits(?string $code = null): int
    {
        return self::catalog()[self::validate($code ?? self::$current)]['digits'];
    }

    public static function factor(?string $code = null): int
    {
        return 10 ** self::digits($code);
    }

    public static function run(string $code, callable $callback)
    {
        $before = self::$current;
        self::$current = self::validate($code);
        try {
            return $callback();
        } finally {
            self::$current = $before;
        }
    }

    public static function format(int $amount, ?string $code = null): string
    {
        $code = self::validate($code ?? self::$current);
        $definition = self::catalog()[$code];
        $language = I18n::current() === 'de_DE' ? 'de' : 'en';
        // Preserve existing EUR output, including its regular space, for old documents.
        $pattern = $code === 'EUR' && $language === 'de' ? '{amount} €' : $definition[$language]['pattern'];
        return ($amount < 0 ? '-' : '') .
            str_replace(
                '{amount}',
                I18n::number(abs($amount) / self::factor($code), $definition['digits']),
                $pattern,
            );
    }

    /** Preserve numeric prices when comparing protected fields across an edition/currency change. */
    public static function redenominate(array $config, string $next): array
    {
        $from = self::factor(self::config($config));
        $to = self::factor(self::validate($next));
        $convert = static function (array &$object, string $key, int $maximum = 100000000) use (
            $from,
            $to,
        ): void {
            if (!isset($object[$key])) {
                return;
            }
            $value = $object[$key] * $to;
            if (!is_int($value) || $value % $from !== 0 || intdiv($value, $from) > $maximum) {
                throw new \DomainException(
                    // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Config/REST returns this error as JSON; the editor escapes it.
                    __(
                        'The currency change cannot preserve every amount exactly within its input limit.',
                        'offerweave',
                    ),
                );
            }
            $object[$key] = intdiv($value, $from);
        };
        foreach ($config['offers'] as &$offer) {
            foreach (
                ['base_cents', 'onsite_cents', 'travel_km_cents', 'travel_hour_cents', 'minimum_cents']
                as $key
            ) {
                $convert($offer, $key, $key === 'travel_km_cents' ? 100000 : 100000000);
            }
            foreach (['tiers', 'bands'] as $key) {
                if (!isset($offer[$key])) {
                    continue;
                }
                foreach ($offer[$key] as &$row) {
                    $convert($row, 'cents');
                }
                unset($row);
            }
            foreach (array_keys($offer['component_prices'] ?? []) as $key) {
                $convert($offer['component_prices'], (string) $key);
            }
            $variantGroups = $offer['variant_groups'] ?? [];
            foreach ($variantGroups as &$group) {
                foreach ($group['options'] as &$option) {
                    if ($option['basis'] !== 'percent') {
                        $convert($option, 'value');
                    }
                }
                unset($option);
            }
            unset($group);
            if (isset($offer['variant_groups'])) {
                $offer['variant_groups'] = $variantGroups;
            }
        }
        unset($offer);
        foreach ($config['promotions'] as &$promotion) {
            if ($promotion['kind'] !== 'percent') {
                $convert($promotion, 'value');
            }
        }
        unset($promotion);
        foreach ($config['surcharges'] ?? [] as $index => $charge) {
            if ($charge['basis'] !== 'percent') {
                $convert($config['surcharges'][$index], 'value');
            }
        }
        $convert($config['selection_rules'], 'min_net_cents');
        $config['settings']['currency'] = $next;
        return $config;
    }

    public static function stamp(array $quote, string $code): array
    {
        $quote['currency'] = $quote['summary']['currency'] = self::validate($code);
        foreach ($quote['items'] as &$item) {
            $item['currency'] = $code;
        }
        unset($item);
        return $quote;
    }
}
