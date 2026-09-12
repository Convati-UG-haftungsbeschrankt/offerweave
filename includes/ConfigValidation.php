<?php
namespace OfferWeave;

/** Administrator-facing metadata contains locations and limits, never the failed value. */
final class ConfigValidationError extends \DomainException
{
    public function __construct(public readonly array $issues)
    {
        parent::__construct($issues[0]['location'] . ': ' . $issues[0]['message']);
    }
}
final class ConfigValidation
{
    public static function offerIssue(
        array $offers,
        string $id,
        string $field,
        string $label,
        string $message,
        string $componentId = '',
    ): array {
        $index = array_search($id, array_column($offers, 'id'), true);
        $offer = $offers[$index];
        return [
            'path' => 'offers.' . $index . '.' . $field,
            'tab' => 'offers',
            'offer_id' => $id,
            'component_id' => $componentId,
            'label' => $label,
            'location' => __('Offers', 'offerweave') . ' → ' . $offer['name'] . ' (' . $id . ') → ' . $label,
            'message' => $message,
        ];
    }
    public static function limits(): array
    {
        // Preserve every formerly accepted string: former byte ceilings become code-point ceilings.
        return [
            'offers' => [
                'name' => 480,
                'category_label' => 480,
                'description' => 2400,
                'eyebrow' => 320,
                'badge' => 320,
                'price_unit' => 240,
                'unit_help_text' => 640,
                'input_unit_singular' => 240,
                'input_unit_plural' => 240,

                'price_note' => 640,
                'term_label' => 640,
                'term_total_label' => 640,
                'content_heading' => 320,
                'detail_url' => 8000,
                'facts' => 480,
                'features' => 960,
            ],
            'fields' => ['label' => 640, 'placeholder' => 960, 'options' => 640],
            'settings' => [
                'brand' => 480,
                'privacy_text' => 8000,
                'intro' => 4000,
                'quantity_label' => 480,
                'scope_help' => 4000,
                'individual_quantity_label' => 480,

                'submit_label' => 480,
                'success_text' => 4000,
                'captcha_site_key' => 1200,
                'captcha_secret' => 2000,
                'notification_email' => 760,
                'selection_url' => 8000,
                'privacy_url' => 8000,
            ],
        ];
    }
    public static function length(string $value): ?int
    {
        $length = preg_match_all('/./us', $value);
        return $length === false ? null : $length;
    }
}
