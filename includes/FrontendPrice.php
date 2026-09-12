<?php
namespace OfferWeave;

/** Free package implementation. All functionality in this file is available without an upgrade. */
final class FrontendPrice
{
    public static function money(int $amount, string $currency, bool $compact = false): string
    {
        $text = Currency::format($amount, $currency);
        if ($compact && Currency::digits($currency) && $amount % Currency::factor($currency) === 0) {
            $text = str_replace(
                (I18n::current() === 'de_DE' ? ',' : '.') . str_repeat('0', Currency::digits($currency)),
                '',
                $text,
            );
        }
        return esc_html($text);
    }
    public static function fixed(array $offer): bool
    {
        return true;
    }
    public static function unitHelp(array $line, ?array $offer): string
    {
        if (empty($offer['unit_help_enabled']) || trim($offer['unit_help_text'] ?? '') === '') {
            return '';
        }
        $text = str_replace(
            '{quantity}',
            I18n::number($line['input']['quantity'] ?? 1, 0),
            $offer['unit_help_text'],
        );
        $text = str_replace(['{participants}', '{groups}', '{group_size}'], '—', $text);
        return '<small class="cqb-unit-help">' . esc_html($text) . '</small>';
    }
    public static function render(
        array $config,
        ?array $line,
        ?array $offer = null,
        string $view = '',
        bool $detailed = false,
        bool $split = false,
    ): string {
        if (!$line) {
            return '<span class="cqb-note">' . esc_html__('Choose scope', 'offerweave') . '</span>';
        }
        $currency = $line['currency'] ?? Currency::config($config);
        $styled = false;
        $money = static fn($value) => self::money($value, $currency);
        $unit = $line['period'] === 'month' ? __('/ month', 'offerweave') : __('one-time', 'offerweave');
        if ($offer && $line['period'] !== 'month' && $offer['price_unit']) {
            /* translators: Number of groups. */
            $unit = $offer['price_unit'];
            if (($line['input']['quantity'] ?? 1) > 1) {
                /* translators: Number of units. */
                $unit = sprintf(__('for %d units', 'offerweave'), $line['input']['quantity']);
            }
        }
        $taxLabel = isset($line['tax'])
            ? sprintf(
                $line['tax']['display'] === 'gross'
                    ? /* translators: Tax rate as a localized number. */
                    __('incl. %s%% VAT', 'offerweave')
                    : /* translators: Tax rate as a localized number. */
                    __('plus %s%% VAT', 'offerweave'),
                rtrim(rtrim(I18n::number($line['tax']['rate_bps'] / 100, 2), '0'), ',.'),
            )
            : __('net, plus VAT', 'offerweave');
        $text =
            $line['amount_cents'] === null
                ? '<strong>' . esc_html__('On request', 'offerweave') . '</strong>'
                : '<div class="cqb-price-core"><div class="cqb-price-main"><strong class="cqb-current-price">' .
                    $money(Tax::amount($line)) .
                    '</strong><span class="cqb-price-unit">' .
                    esc_html(
                        $styled || (!empty($offer['price_unit']) && $line['period'] !== 'month')
                            ? $unit
                            : ($line['period'] === 'month'
                                ? __('per month', 'offerweave')
                                : __('one-time', 'offerweave')),
                    ) .
                    '</span></div><small class="cqb-tax-label">' .
                    esc_html($taxLabel) .
                    '</small></div>';
        $text .= self::unitHelp($line, $offer);
        if (!empty($offer['price_note'])) {
            $text .= '<small class="cqb-price-note">' . esc_html($offer['price_note']) . '</small>';
        }
        if ($line['period'] === 'month') {
            $label =
                trim($offer['term_label'] ?? '') !== ''
                    ? str_replace('{months}', (string) $line['term_months'], $offer['term_label'])
                    : $line['term_months'] .
                        ' ' .
                        _x('month term', 'Original label: Monate Laufzeit', 'offerweave');
            $text .= '<small class="cqb-term-label">' . esc_html($label) . '</small>';
            if ($line['term_cents'] !== null && ($offer['show_term_total'] ?? true)) {
                $text .=
                    '<small class="cqb-term-total">' .
                    $money(Tax::amount($line, 'term_cents')) .
                    ' ' .
                    esc_html(
                        trim($offer['term_total_label'] ?? '') ?: __('for the entire term', 'offerweave'),
                    ) .
                    '</small>';
            }
        }
        return $text;
    }
    public static function taxRows(array $rows, bool $monthly, string $currency): string
    {
        $out = '<dl class="cqb-tax-rows">';
        foreach ($rows as $row) {
            $out .=
                '<div data-tax-kind="' .
                esc_attr($row['kind']) .
                '"><dt>' .
                esc_html($row['label']) .
                '</dt><dd>' .
                self::money($row['cents'], $currency) .
                ($monthly ? ' ' . esc_html__('/ month', 'offerweave') : '') .
                '</dd></div>';
        }
        return $out . '</dl>';
    }
    public static function breakdown(array $line): string
    {
        if (empty($line['breakdown'])) {
            return '';
        }
        $out =
            '<details class="cqb-breakdown"><summary>' .
            esc_html(
                isset($line['tax'])
                    ? __('Show net price calculation', 'offerweave')
                    : __('Show price calculation', 'offerweave'),
            ) .
            '</summary>';
        foreach ($line['breakdown'] as $row) {
            $out .=
                '<div><span>' .
                esc_html($row['label']) .
                '</span><strong>' .
                self::money($row['cents'], $line['currency']) .
                '</strong></div>';
        }
        return $out . '</details>';
    }
}
