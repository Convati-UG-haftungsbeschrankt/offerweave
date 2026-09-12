<?php
namespace OfferWeave;

/** Configurable outgoing documents. The website remains an enquiry workflow. */
final class Legal
{
    public static function defaults(): array
    {
        return [
            'audience' => 'unspecified',
            'document_type' => 'non_binding',
            'show_catalog_notice' => false,
            'acceptance_days' => 14,
            ...array_fill_keys(array_keys(self::textSchema()), ''),
            'provider_name' => '',
            'provider_address' => '',
            'provider_contact' => '',
            'provider_register' => '',
            'provider_representatives' => '',
            'provider_vat_id' => '',
            'additional_info' => '',
            'imprint_url' => '',
            'review_days' => 0,
        ];
    }

    public static function textSchema(): array
    {
        return [
            'non_binding_heading' => [
                'mode' => 'non_binding',
                'label' => __('Information block heading', 'offerweave'),
                'default' => __('Enquiry and provider information', 'offerweave'),
            ],
            'non_binding_notice' => [
                'mode' => 'non_binding',
                'label' => __('Document notice', 'offerweave'),
                'default' => self::notice(),
            ],
            'non_binding_date_text' => [
                'mode' => 'non_binding',
                'label' => __('Price date text', 'offerweave'),
                'default' => __(
                    'Price calculation dated [price_date]. This is not an acceptance deadline or a price reservation.',
                    'offerweave',
                ),
            ],
            'non_binding_review_text' => [
                'mode' => 'non_binding',
                'label' => __('Recalculation date text', 'offerweave'),
                'default' => __(
                    'A new calculation is recommended from [review_date]; special promotions may end earlier.',
                    'offerweave',
                ),
            ],
            'binding_heading' => [
                'mode' => 'binding',
                'label' => __('Information block heading', 'offerweave'),
                'default' => __('Offer and provider information', 'offerweave'),
            ],
            'binding_notice' => [
                'mode' => 'binding',
                'label' => __('Document notice', 'offerweave'),
                'default' => __(
                    'This is a binding offer for the services and prices listed. A contract is concluded when your acceptance without changes reaches us within the stated acceptance period. This document is not an order confirmation.',
                    'offerweave',
                ),
            ],
            'binding_date_text' => [
                'mode' => 'binding',
                'label' => __('Offer date text', 'offerweave'),
                'default' => __('Offer dated [price_date].', 'offerweave'),
            ],
            'binding_acceptance_text' => [
                'mode' => 'binding',
                'label' => __('Acceptance deadline text', 'offerweave'),
                'default' => __(
                    'You may accept this offer until [acceptance_date], 23:59 ([timezone]). Please send your acceptance to the provider contact listed below.',
                    'offerweave',
                ),
            ],
        ];
    }

    public static function binding(array $snapshot): bool
    {
        return ($snapshot['legal']['document_type'] ?? 'non_binding') === 'binding';
    }

    public static function documentTitle(array $snapshot): string
    {
        return self::binding($snapshot)
            ? __('Binding offer', 'offerweave')
            : __('Non-binding price summary', 'offerweave');
    }

    private static function template(array $legal, string $key, array $values = []): string
    {
        $text = trim($legal[$key] ?? '') ?: self::textSchema()[$key]['default'];
        return strtr($text, $values);
    }

    public static function validate($raw): array
    {
        if (!is_array($raw)) {
            throw new \DomainException(__('Invalid provider and enquiry settings.', 'offerweave')); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Api JSON or guarded stored-data consumers; no direct exception output.
        }
        $c = array_replace(self::defaults(), $raw);
        if (!in_array($c['audience'], ['unspecified', 'business', 'consumer'], true)) {
            throw new \DomainException(__('Invalid customer audience.', 'offerweave')); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Api JSON or guarded stored-data consumers; no direct exception output.
        }
        if (!in_array($c['document_type'], ['non_binding', 'binding'], true)) {
            throw new \DomainException(__('Invalid document type.', 'offerweave')); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Api JSON or guarded stored-data consumers; no direct exception output.
        }
        $out = ['audience' => $c['audience'], 'document_type' => $c['document_type']];
        if (!is_bool($c['show_catalog_notice'])) {
            throw new \DomainException(__('Invalid switch value.', 'offerweave')); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Config validation reaches guarded API JSON, never direct HTML output.
        }
        $out['show_catalog_notice'] = $c['show_catalog_notice'];
        $out['acceptance_days'] = Pricing::integer(
            $c['acceptance_days'],
            1,
            365,
            __('Acceptance period in days', 'offerweave'),
        );
        foreach (
            array_diff(array_keys(self::defaults()), [
                'audience',
                'document_type',
                'show_catalog_notice',
                'review_days',
                'acceptance_days',
            ])
            as $key
        ) {
            if (!is_string($c[$key]) || strlen($c[$key]) > 8000) {
                throw new \DomainException(
                    __('A provider information field is invalid or too long.', 'offerweave'), // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Api JSON or guarded stored-data consumers; no direct exception output.
                );
            }
            $out[$key] = sanitize_textarea_field($c[$key]);
        }
        foreach (self::textSchema() as $key => $definition) {
            preg_match_all('/\[[^\]\r\n]*\]/', $out[$key], $matches);
            foreach ($matches[0] as $token) {
                if (
                    !in_array(
                        $token,
                        ['[price_date]', '[review_date]', '[acceptance_date]', '[timezone]'],
                        true,
                    )
                ) {
                    throw new \DomainException(
                        __('Unknown document text placeholder:', 'offerweave') . ' ' . $token, // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Api JSON or guarded stored-data consumers; no direct exception output.
                    );
                }
            }
        }
        if (
            $out['binding_acceptance_text'] !== '' &&
            (!str_contains($out['binding_acceptance_text'], '[acceptance_date]') ||
                !str_contains($out['binding_acceptance_text'], '[timezone]'))
        ) {
            throw new \DomainException(
                // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Api JSON or guarded stored-data consumers; no direct exception output.
                __(
                    'The acceptance deadline text must contain [acceptance_date] and [timezone].',
                    'offerweave',
                ),
            );
        }
        if ($out['imprint_url'] !== '') {
            $out['imprint_url'] = esc_url_raw($out['imprint_url'], ['https', 'http']);
            if (!$out['imprint_url'] || !wp_parse_url($out['imprint_url'], PHP_URL_HOST)) {
                throw new \DomainException(
                    __('Please enter a valid provider information URL.', 'offerweave'), // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Api JSON or guarded stored-data consumers; no direct exception output.
                );
            }
        }
        $out['review_days'] = Pricing::integer(
            $c['review_days'],
            0,
            365,
            __('Days until a new calculation is recommended', 'offerweave'),
        );
        return $out;
    }

    public static function normalize(array $c): array
    {
        $c['legal'] = self::validate($c['legal'] ?? []);
        if ($c['legal']['audience'] === 'consumer') {
            $c['tax']['display'] = 'gross';
        }
        return $c;
    }

    public static function notice(): string
    {
        return __(
            'This price summary is non-binding and subject to confirmation. It is not a binding offer or an order confirmation. Neither your enquiry nor this email concludes a contract. Scope, dates, availability and any outstanding costs must be agreed separately before commissioning.',
            'offerweave',
        );
    }

    public static function audience(array $legal): string
    {
        return ($legal['audience'] ?? '') === 'business'
            ? __(
                'These services are offered exclusively to business customers acting in their commercial or professional capacity.',
                'offerweave',
            )
            : '';
    }

    public static function snapshot(array $config, ?int $now = null): array
    {
        $legal = self::validate($config['legal'] ?? []);
        $date = new \DateTimeImmutable('@' . ($now ?? time()));
        $date = $date->setTimezone(wp_timezone());
        $binding = $legal['document_type'] === 'binding';
        $priceDate = $date->format('Y-m-d');
        $reviewDate =
            !$binding && $legal['review_days']
                ? $date->modify('+' . $legal['review_days'] . ' days')->format('Y-m-d')
                : '';
        $end = $binding
            ? $date->modify('+' . $legal['acceptance_days'] . ' days')->setTime(23, 59, 59)
            : null;
        $values = [
            '[price_date]' => $priceDate,
            '[review_date]' => $reviewDate,
            '[acceptance_date]' => $end ? $end->format('Y-m-d') : '',
            '[timezone]' => wp_timezone()->getName(),
        ];
        $mode = $legal['document_type'];
        return [
            ...$legal,
            'text_version' => 1,
            'heading' => self::template($legal, $mode . '_heading', $values),
            'notice' => self::template($legal, $mode . '_notice', $values),
            'audience_notice' => self::audience($legal),
            'price_date' => $priceDate,
            'review_date' => $reviewDate,
            'acceptance_date' => $end ? $end->format('Y-m-d') : '',
            'acceptance_ends_at' => $end ? $end->getTimestamp() : null,
            'timezone' => wp_timezone()->getName(),
            'price_date_text' => self::template($legal, $mode . '_date_text', $values),
            'review_date_text' => $reviewDate
                ? self::template($legal, 'non_binding_review_text', $values)
                : '',
            'acceptance_text' => $binding ? self::template($legal, 'binding_acceptance_text', $values) : '',
        ];
    }

    public static function publicInfo(array $c): array
    {
        $l = self::validate($c['legal'] ?? []);
        // A calculator response is never the outgoing binding offer itself.
        return [
            ...$l,
            'notice' =>
                $l['document_type'] === 'binding'
                    ? __(
                        'The website calculation and your enquiry do not conclude a contract. The provider may subsequently send you a separate binding offer.',
                        'offerweave',
                    )
                    : self::notice(),
            'audience_notice' => self::audience($l),
        ];
    }

    public static function mail(array $message, array $snapshot): array
    {
        return I18n::run(I18n::snapshot($snapshot), static function () use ($message, $snapshot) {
            // Do not invent historical provider details or a validity date for legacy requests.
            $l = $snapshot['legal'] ?? [];
            $lines = [$l['notice'] ?? self::notice()];
            if (!empty($l['audience_notice'])) {
                $lines[] = $l['audience_notice'];
            }
            if (isset($l['text_version'])) {
                foreach (['price_date_text', 'review_date_text', 'acceptance_text'] as $key) {
                    if (!empty($l[$key])) {
                        $lines[] = $l[$key];
                    }
                }
            }
            if (!isset($l['text_version']) && !empty($l['price_date'])) {
                $lines[] = sprintf(
                    /* translators: %s: formatted date of the saved price calculation. */
                    __(
                        'Price calculation dated %s. This is not an acceptance deadline or a price reservation.',
                        'offerweave',
                    ),
                    $l['price_date'],
                );
            }
            if (!isset($l['text_version']) && !empty($l['review_date'])) {
                $lines[] = sprintf(
                    /* translators: %s: formatted date recommended for a new calculation. */
                    __(
                        'A new calculation is recommended from %s; special promotions may end earlier.',
                        'offerweave',
                    ),
                    $l['review_date'],
                );
            }
            foreach (
                [
                    'provider_name',
                    'provider_address',
                    'provider_contact',
                    'provider_register',
                    'provider_representatives',
                    'provider_vat_id',
                    'additional_info',
                ]
                as $key
            ) {
                if (!empty($l[$key])) {
                    $lines[] = $l[$key];
                }
            }
            if (!empty($l['imprint_url'])) {
                $lines[] = $l['imprint_url'];
            }
            $text = implode("\n\n", $lines);
            $block =
                '<div class="offerweave-enquiry-information" style="max-width:760px;margin:20px auto;padding:20px;font:14px/1.6 Arial,sans-serif;color:#17263b;background:#ffffff;border:1px solid #d5dfe9"><h2 style="font-size:18px">' .
                esc_html($l['heading'] ?? __('Enquiry and provider information', 'offerweave')) .
                '</h2><p>' .
                nl2br(esc_html($text)) .
                '</p></div>';
            $message['html'] = str_contains($message['html'], '</body>')
                ? str_replace('</body>', $block . '</body>', $message['html'])
                : $message['html'] . $block;
            $message['text'] .= "\n\n" . (isset($l['text_version']) ? $l['heading'] . "\n\n" : '') . $text;
            return $message;
        });
    }
}
