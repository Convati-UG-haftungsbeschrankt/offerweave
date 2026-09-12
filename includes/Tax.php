<?php
namespace OfferWeave;

/** Net inputs, integer basis points, half-up VAT per line and billing period. */
final class Tax
{
    public static function defaults(): array
    {
        return ['enabled' => false, 'default_rate_bps' => 1900, 'display' => 'net'];
    }

    public static function validate($raw): array
    {
        if (!is_array($raw)) {
            throw new \DomainException(__('Invalid tax settings.', 'offerweave')); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Api JSON or guarded stored-data consumers; no direct exception output.
        }
        $t = array_replace(self::defaults(), $raw);
        if (!is_bool($t['enabled']) || !in_array($t['display'], ['net', 'gross'], true)) {
            throw new \DomainException(__('Invalid tax settings.', 'offerweave')); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Api JSON or guarded stored-data consumers; no direct exception output.
        }
        return [
            'enabled' => $t['enabled'],
            'default_rate_bps' => self::rate($t['default_rate_bps']),
            'display' => $t['display'],
        ];
    }

    public static function rate($value): int
    {
        return Pricing::integer($value, 0, 10000, __('VAT rate (basis points)', 'offerweave'));
    }

    public static function normalize(array $c): array
    {
        $c['tax'] = self::validate($c['tax'] ?? []);
        foreach ($c['offers'] as &$o) {
            $o['tax_rate_bps'] ??= null;
        }
        unset($o);
        return $c;
    }

    public static function vat(int $net, int $rate): int
    {
        return intdiv($net * $rate + 5000, 10000);
    }

    public static function rateLabel(int $rate): string
    {
        return sprintf(
            /* translators: %s: formatted VAT percentage. */
            __('VAT %s%%', 'offerweave'),
            rtrim(rtrim(I18n::number($rate / 100, 2), '0'), I18n::current() === 'de_DE' ? ',' : '.'),
        );
    }

    private static function bucket(): array
    {
        return ['net_cents' => 0, 'vat_cents' => 0, 'gross_cents' => 0, 'rates' => []];
    }

    private static function add(array &$bucket, array $line, int $multiplier = 1): void
    {
        foreach (['net_cents', 'vat_cents', 'gross_cents'] as $key) {
            $bucket[$key] += $line[$key] * $multiplier;
        }
        if ($bucket['gross_cents'] > 9007199254740991) {
            throw new \DomainException(
                __('The calculated total is too large. Please reduce the selection.', 'offerweave'), // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Api JSON or guarded stored-data consumers; no direct exception output.
            );
        }
        $rate = $line['rate_bps'];
        $bucket['rates'][$rate] ??= ['rate_bps' => $rate, 'net_cents' => 0, 'vat_cents' => 0];
        $bucket['rates'][$rate]['net_cents'] += $line['net_cents'] * $multiplier;
        $bucket['rates'][$rate]['vat_cents'] += $line['vat_cents'] * $multiplier;
    }

    private static function group(string $label, array $bucket): array
    {
        ksort($bucket['rates']);
        $bucket['rates'] = array_values($bucket['rates']);
        $rows = [
            ['label' => __('Net subtotal', 'offerweave'), 'cents' => $bucket['net_cents'], 'kind' => 'net'],
        ];
        foreach ($bucket['rates'] as $rate) {
            $rows[] = [
                'label' => sprintf(
                    /* translators: 1: VAT rate label, 2: formatted net amount. */
                    __('%1$s on %2$s net', 'offerweave'),
                    self::rateLabel($rate['rate_bps']),
                    Pricing::money($rate['net_cents']),
                ),
                'cents' => $rate['vat_cents'],
                'kind' => 'vat',
            ];
        }
        $rows[] = [
            'label' => __('Total incl. VAT', 'offerweave'),
            'cents' => $bucket['gross_cents'],
            'kind' => 'gross',
        ];
        return ['label' => $label, ...$bucket, 'rows' => $rows];
    }

    public static function apply(array $quote, array $config): array
    {
        $settings = self::validate($config['tax'] ?? []);
        if (!$settings['enabled']) {
            return $quote; // Preserve the old snapshot shape when tax is not configured.
        }
        $catalog = array_column($config['offers'], null, 'id');
        $once = $monthly = $comparison = self::bucket();
        $terms = [];
        foreach ($quote['items'] as &$line) {
            $rate = self::rate($catalog[$line['offer_id']]['tax_rate_bps'] ?? $settings['default_rate_bps']);
            $net = $line['amount_cents'];
            $vat = $net === null ? null : self::vat($net, $rate);
            $gross = $net === null ? null : $net + $vat;
            $isGross = $settings['display'] === 'gross';
            $regular = $line['regular_cents'];
            $regularDisplay =
                $regular === null ? null : $regular + ($isGross ? self::vat($regular, $rate) : 0);
            $display = $isGross ? $gross : $net;
            $line['tax'] = [
                'display' => $settings['display'],
                'rate_bps' => $rate,
                'rate_label' => self::rateLabel($rate),
                'net_cents' => $net,
                'vat_cents' => $vat,
                'gross_cents' => $gross,
                'display_cents' => $display,
                'regular_display_cents' => $regularDisplay,
                'discount_display_cents' => $display === null ? 0 : $regularDisplay - $display,
                'term_display_cents' =>
                    $display !== null && $line['term_months'] ? $display * $line['term_months'] : null,
                'price_label' => $isGross ? __('incl. VAT', 'offerweave') : __('net, plus VAT', 'offerweave'),
                'rows' => [],
            ];
            if ($net === null) {
                continue;
            }
            $line['tax']['rows'] = [
                ['label' => __('Net item value', 'offerweave'), 'cents' => $net, 'kind' => 'net'],
                ['label' => self::rateLabel($rate), 'cents' => $vat, 'kind' => 'vat'],
                ['label' => __('Gross item value', 'offerweave'), 'cents' => $gross, 'kind' => 'gross'],
            ];
            if ($line['period'] === 'month') {
                $months = $line['term_months'];
                $terms[$months] ??= self::bucket();
                self::add($monthly, $line['tax']);
                self::add($terms[$months], $line['tax'], $months);
                self::add($comparison, $line['tax'], $months);
            } else {
                self::add($once, $line['tax']);
                self::add($comparison, $line['tax']);
            }
        }
        unset($line);
        $s = &$quote['summary'];
        $groups = [];
        if ($s['priced_count']) {
            $groups[] = self::group(
                $s['has_custom'] || $s['has_travel']
                    ? __('Known one-time amount', 'offerweave')
                    : __('One-time', 'offerweave'),
                $once,
            );
        }
        if ($s['terms']) {
            $groups[] = self::group(__('Per month', 'offerweave'), $monthly);
        }
        ksort($terms);
        foreach ($terms as $months => $bucket) {
            $groups[] = self::group(
                /* translators: %d: number of months. */
                sprintf(__('Recurring services over %d months', 'offerweave'), $months),
                $bucket,
            );
        }
        if ($s['comparison_cents'] !== null) {
            $groups[] = self::group(
                sprintf(
                    /* translators: %d: number of months. */
                    __('Total over %d months including one-time services', 'offerweave'),
                    $s['comparison_months'],
                ),
                $comparison,
            );
        }
        $s['tax'] = ['display' => $settings['display'], 'groups' => $groups];
        return $quote;
    }

    public static function amount(array $line, string $key = 'amount_cents'): ?int
    {
        $map = [
            'amount_cents' => 'display_cents',
            'regular_cents' => 'regular_display_cents',
            'discount_cents' => 'discount_display_cents',
            'term_cents' => 'term_display_cents',
        ];
        return isset($line['tax']) ? $line['tax'][$map[$key]] : $line[$key];
    }

    public static function lineText(array $line): string
    {
        $rows = [];
        foreach ($line['tax']['rows'] ?? [] as $row) {
            $rows[] =
                $row['label'] .
                ': ' .
                Currency::format($row['cents'], $line['currency'] ?? 'EUR') .
                ($line['period'] === 'month' ? ' ' . __('/ month', 'offerweave') : '');
        }
        return implode("\n", $rows);
    }

    public static function summaryText(array $summary): string
    {
        $rows = [];
        foreach ($summary['tax']['groups'] as $group) {
            $rows[] = $group['label'];
            foreach ($group['rows'] as $row) {
                $rows[] =
                    $row['label'] . ': ' . Currency::format($row['cents'], $summary['currency'] ?? 'EUR');
            }
            $rows[] = '';
        }
        if ($summary['has_custom'] || $summary['has_travel']) {
            $rows[] = __(
                'Only known amounts are included. Outstanding services or travel costs and their VAT will be added.',
                'offerweave',
            );
        }
        return trim(implode("\n", $rows));
    }

    public static function note(array $summary, bool $binding = false): string
    {
        if ($binding) {
            return isset($summary['tax'])
                ? __(
                    'Net amounts, VAT and gross amounts are shown separately. VAT is rounded per item and billing period; totals add these rounded amounts.',
                    'offerweave',
                )
                : __('All amounts are net, excluding VAT.', 'offerweave');
        }
        return isset($summary['tax'])
            ? __(
                'Net amounts, VAT and gross amounts are shown separately. This price summary is non-binding. Term totals assume an unchanged scope. VAT is rounded per item and billing period; totals add these rounded amounts.',
                'offerweave',
            )
            : __(
                'All amounts are net, excluding VAT. This request is non-binding; outstanding services will be specified in the quote. Term totals assume an unchanged scope.',
                'offerweave',
            );
    }
}
