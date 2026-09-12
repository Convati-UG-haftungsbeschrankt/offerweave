<?php
namespace OfferWeave;

/** Free package implementation. All functionality in this file is available without an upgrade. */
final class Pricing
{
    public static function integer($value, int $min, int $max, string $label): int
    {
        if (is_bool($value) || !(is_int($value) || (is_string($value) && preg_match('/^\d+$/D', $value)))) {
            // translators: Numeric placeholders are counts or limits; string placeholders are field labels or email addresses.
            throw new \DomainException(sprintf(__('%s: Please enter a whole number.', 'offerweave'), $label)); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Calculation/validation reaches Api JSON or guarded stored-data consumers.
        }
        if ($value < $min || $value > $max) {
            throw new \DomainException(
                // translators: 1: field label, 2: minimum integer, 3: maximum integer.
                sprintf(__('%1$s: Allowed range: %2$d to %3$d.', 'offerweave'), $label, $min, $max), // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Calculation/validation reaches Api JSON or guarded stored-data consumers.
            );
        }
        return (int) $value;
    }
    public static function unitName(array $o, string $type, int $n): string
    {
        $singular = $o[$type . '_unit_singular'] ?? '';
        $plural = $o[$type . '_unit_plural'] ?? '';
        $custom = $n === 1 ? ($singular ?: $plural) : ($plural ?: $singular);
        if ($custom !== '') {
            return $custom;
        }
        return $type === 'group'
            ? _n('group', 'groups', $n, 'offerweave')
            : _n('participant', 'participants', $n, 'offerweave');
    }
    public static function countUnit(array $o, string $type, int $n): string
    {
        return I18n::number($n, 0) . ' ' . self::unitName($o, $type, $n);
    }
    public static function money(int $cents): string
    {
        return Currency::format($cents);
    }
    public static function quote(array $config, array $items, ?int $now = null): array
    {
        $currency = Currency::config($config);
        return Currency::run(
            $currency,
            static fn() => Currency::stamp(self::calculateFree($config, $items, $now), $currency),
        );
    }
    private static function calculateFree(array $config, array $items, ?int $now): array
    {
        $config = Catalog::upgrade($config);
        if (!array_is_list($items) || count($items) > 30) {
            throw new \DomainException(__('The selection may contain at most 30 items.', 'offerweave')); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Calculation/validation reaches Api JSON or guarded stored-data consumers.
        }
        $catalog = array_column($config['offers'], null, 'id');
        $lines = $ids = $terms = [];
        $once = $monthly = 0;
        foreach ($items as $raw) {
            if (!is_array($raw)) {
                throw new \DomainException(__('Invalid item.', 'offerweave')); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Calculation/validation reaches Api JSON or guarded stored-data consumers.
            }
            $raw = Catalog::input($raw, $config);
            $id = $raw['line_id'] ?? '';
            if (!is_string($id) || !preg_match('/^[a-zA-Z0-9_-]{1,64}$/D', $id) || isset($ids[$id])) {
                throw new \DomainException(__('Invalid or duplicate item ID.', 'offerweave')); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Calculation/validation reaches Api JSON or guarded stored-data consumers.
            }
            $ids[$id] = true;
            $sku = $raw['offer_id'] ?? '';
            if (
                !is_string($sku) ||
                !isset($catalog[$sku]) ||
                !Catalog::supports($catalog[$sku]) ||
                !$catalog[$sku]['enabled'] ||
                !$catalog[$sku]['catalog_visible'] ||
                $catalog[$sku]['kind'] !== 'fixed'
            ) {
                throw new \DomainException(
                    // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Calculation/validation reaches Api JSON or guarded stored-data consumers.
                    __(
                        'A selected offer is no longer available. Please update your selection.',
                        'offerweave',
                    ),
                );
            }
            $o = $catalog[$sku];
            $quantity = self::integer($raw['quantity'] ?? 1, 1, 100000, __('Quantity', 'offerweave'));
            $amount = $quantity * $o['base_cents'];
            $months = $o['period'] === 'month' ? $o['term_months'] : 0;
            $details = [
                !empty($o['input_unit_singular']) || !empty($o['input_unit_plural'])
                    ? self::countUnit($o, 'input', $quantity)
                    : $quantity . (' ' . __('× offer', 'offerweave')),
            ];
            if ($o['duration_minutes']) {
                $details[] = $o['duration_minutes'] . (' ' . __('minutes per unit', 'offerweave'));
            }
            if ($months) {
                $monthly += $amount;
                $terms[$months] = ($terms[$months] ?? 0) + $amount;
            } else {
                $once += $amount;
            }
            $lines[] = [
                'input' => ['line_id' => $id, 'offer_id' => $sku, 'quantity' => $quantity],
                'offer_id' => $sku,
                'name' => $o['name'],
                'kind' => 'fixed',
                'details' => $details,
                'amount_cents' => $amount,
                'regular_cents' => $amount,
                'discount_cents' => 0,
                'promotion' => null,
                'promotion_details' => [],
                'period' => $o['period'],
                'term_months' => $months,
                'term_cents' => $months ? $amount * $months : null,
                'custom' => false,
                'travel_open' => false,
                'breakdown' => [
                    ['label' => $quantity . ' × ' . self::money($o['base_cents']), 'cents' => $amount],
                ],
            ];
        }
        ksort($terms);
        $termRows = [];
        foreach ($terms as $months => $amount) {
            $termRows[] = [
                'months' => $months,
                'monthly_cents' => $amount,
                'total_cents' => $months * $amount,
            ];
        }
        $quote = Tax::apply(
            [
                'items' => $lines,
                'summary' => [
                    'once_cents' => $once,
                    'monthly_cents' => $monthly,
                    'terms' => $termRows,
                    'comparison_cents' => count($termRows) === 1 ? $once + $termRows[0]['total_cents'] : null,
                    'comparison_months' => count($termRows) === 1 ? $termRows[0]['months'] : null,
                    'has_custom' => false,
                    'has_travel' => false,
                    'priced_count' => count($lines),
                ],
            ],
            $config,
        );
        return SelectionRules::apply($quote, $config);
    }
}
