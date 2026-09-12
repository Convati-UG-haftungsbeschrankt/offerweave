<?php
namespace OfferWeave;

/** Free package implementation. All functionality in this file is available without an upgrade. */
final class Defaults
{
    public static function offer(): array
    {
        return [
            'id' => '',
            'name' => 'Neues Angebot',
            'image' => ['attachment_id' => 0, 'url' => '', 'alt' => '', 'decorative' => false],
            'enabled' => true,
            'catalog_visible' => true,
            'category_label' => '',
            'category' => 'angebote',
            'kind' => 'fixed',
            'description' => '',
            'eyebrow' => '',
            'badge' => '',
            'facts' => [],
            'fact_icons' => [],
            'price_unit' => '',
            'unit_help_enabled' => false,
            'unit_help_text' => '',
            'input_unit_singular' => '',
            'input_unit_plural' => '',
            'price_note' => '',
            'content_heading' => '',
            'detail_url' => '',
            'features' => [],
            'period' => 'once',
            'term_months' => 12,
            'show_term_total' => true,
            'term_label' => '',
            'term_total_label' => '',
            'duration_minutes' => 0,
            'base_cents' => 0,
            'tax_rate_bps' => null,
        ];
    }
    public static function config(): array
    {
        return apply_filters('offerweave_default_config', self::neutral());
    }
    public static function neutral(): array
    {
        $fields = [];
        foreach (
            [
                ['name', 'Name', 'text', true],
                ['company', 'Unternehmen', 'text', true],
                ['email', 'E-Mail', 'email', true],
                ['phone', 'Telefon', 'tel', false],
                ['preferred_date', 'Gewünschter Zeitraum', 'text', false],
                ['message', 'Nachricht', 'textarea', false],
            ]
            as [$id, $label, $type, $required]
        ) {
            $fields[] = [
                'id' => $id,
                'label' => $label,
                'type' => $type,
                'required' => $required,
                'enabled' => true,
                'placeholder' => '',
                'options' => [],
            ];
        }
        return [
            'schema_version' => 2,
            'surcharges' => [],
            'tax' => Tax::defaults(),
            'selection_rules' => SelectionRules::defaults(),
            'legacy_item_map' => [],
            'promotions' => [],
            'offers' => [],
            'fields' => $fields,
            'settings' => [
                'currency' => 'EUR',
                'brand' => 'Ihr Unternehmen',
                'accent' => '#0b60c6',
                'surface' => '#f1f5f9',
                'radius' => 6,
                'notification_email' => '',
                'reply_field' => 'email',
                'selection_url' => '',
                'privacy_url' => '',
                'privacy_text' =>
                    'Wir verwenden Ihre Angaben zur Bearbeitung Ihrer unverbindlichen Angebotsanfrage.',
                'input_scope' => 'shared',
                'quantity_label' => '',
                'scope_help' => '',
                'individual_quantity_label' => '',
                'intro' => 'Wählen Sie die passenden Angebote und den gewünschten Umfang.',
                'submit_label' => 'Unverbindliches Angebot anfordern',
                'success_text' => 'Ihre Anfrage wurde gespeichert. Wir melden uns zeitnah bei Ihnen.',
                'captcha_provider' => 'none',
                'captcha_site_key' => '',
                'captcha_secret' => '',
                'rate_limit' => 10,
                'min_seconds' => 2,
                'retention_days' => 0,
                'delete_on_uninstall' => false,
            ],
        ];
    }
}
