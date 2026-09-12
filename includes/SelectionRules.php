<?php
namespace OfferWeave;

/** Request eligibility is separate from price calculation and adding draft items. */
final class SelectionRules
{
    public static function defaults(): array
    {
        return [
            'enabled' => false,
            'scope' => 'all',
            'offer_ids' => [],
            'min_net_cents' => 0,
            'min_distinct' => 0,
            'match' => 'all',
        ];
    }
    public static function normalize(array $c): array
    {
        $c['selection_rules'] = self::validate($c['selection_rules'] ?? [], $c['offers']);
        return $c;
    }
    public static function validate($raw, array $offers): array
    {
        if (!is_array($raw)) {
            throw new \DomainException(__('Invalid request conditions.', 'offerweave')); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Api JSON or guarded stored-data consumers; no direct exception output.
        }
        $r = array_replace(self::defaults(), $raw);
        if (
            !is_bool($r['enabled']) ||
            !in_array($r['scope'], ['all', 'offers'], true) ||
            !in_array($r['match'], ['all', 'any'], true) ||
            !is_array($r['offer_ids']) ||
            !array_is_list($r['offer_ids']) ||
            count($r['offer_ids']) > 150
        ) {
            throw new \DomainException(__('Invalid request conditions.', 'offerweave')); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Api JSON or guarded stored-data consumers; no direct exception output.
        }
        $r['min_net_cents'] = Pricing::integer(
            $r['min_net_cents'],
            0,
            100000000,
            __('Minimum request value', 'offerweave'),
        );
        $r['min_distinct'] = Pricing::integer(
            $r['min_distinct'],
            0,
            150,
            __('Minimum number of different offers', 'offerweave'),
        );
        $known = array_column($offers, null, 'id');
        foreach ($r['offer_ids'] as $id) {
            if (!is_string($id) || !isset($known[$id])) {
                throw new \DomainException(
                    __('A request condition refers to a missing offer.', 'offerweave'), // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Api JSON or guarded stored-data consumers; no direct exception output.
                );
            }
        }
        $r['offer_ids'] = array_values(array_unique($r['offer_ids']));
        if (
            $r['enabled'] &&
            ((!$r['min_net_cents'] && !$r['min_distinct']) || ($r['scope'] === 'offers' && !$r['offer_ids']))
        ) {
            throw new \DomainException(
                // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Api JSON or guarded stored-data consumers; no direct exception output.
                __(
                    'Choose at least one condition and, where applicable, the offers that activate it.',
                    'offerweave',
                ),
            );
        }
        return array_intersect_key($r, self::defaults());
    }
    public static function apply(array $quote, array $config): array
    {
        $r = $config['selection_rules'] ?? self::defaults();
        if (!$r['enabled']) {
            return $quote;
        }
        $ids = $triggers = [];
        $known = 0;
        foreach ($quote['items'] as $line) {
            $parts = $line['input']['component_ids'] ?? [];
            $ids = array_merge($ids, $parts ?: [$line['offer_id']]);
            $triggers = array_merge($triggers, [$line['offer_id']], $parts);
            if ($line['amount_cents'] !== null) {
                $known += $line['amount_cents'] * ($line['term_months'] ?: 1);
            }
        }
        if ($r['scope'] === 'offers' && !array_intersect($r['offer_ids'], $triggers)) {
            return $quote;
        }
        $count = count(array_unique($ids));
        $checks = $messages = [];
        if ($r['min_net_cents']) {
            $checks[] = $known >= $r['min_net_cents'];
            if ($known < $r['min_net_cents']) {
                $messages[] = sprintf(
                    /* translators: 1: minimum net amount, 2: remaining net amount, both formatted with currency. */
                    __('The selection must reach %1$s net. Another %2$s net is required.', 'offerweave'),
                    Pricing::money($r['min_net_cents']),
                    Pricing::money($r['min_net_cents'] - $known),
                );
            }
        }
        if ($r['min_distinct']) {
            $checks[] = $count >= $r['min_distinct'];
            if ($count < $r['min_distinct']) {
                $messages[] = sprintf(
                    /* translators: %d: minimum number of different offers. */
                    __(
                        'Select at least %d different offers. Repeated quantities do not increase this count.',
                        'offerweave',
                    ),
                    $r['min_distinct'],
                );
            }
        }
        $eligible = $r['match'] === 'all' ? !in_array(false, $checks, true) : in_array(true, $checks, true);
        $quote['request_requirement'] = [
            'eligible' => $eligible,
            'known_net_cents' => $known,
            'distinct_count' => $count,
            'min_net_cents' => $r['min_net_cents'],
            'min_distinct' => $r['min_distinct'],
            'match' => $r['match'],
            'message' => $eligible
                ? ''
                : implode(
                    $r['match'] === 'any' ? ' ' . __('Alternatively:', 'offerweave') . ' ' : ' ',
                    $messages,
                ),
        ];
        return $quote;
    }
}
