<?php
namespace OfferWeave;

/** Free package implementation. All functionality in this file is available without an upgrade. */
final class Catalog
{
    public static function supports(array $offer): bool
    {
        return ($offer['kind'] ?? 'fixed') === 'fixed' &&
            empty($offer['selection_parent']) &&
            empty($offer['use_variants']) &&
            empty($offer['surcharges']) &&
            empty($offer['component_ids']);
    }
    public static function upgrade(array $config): array
    {
        $config = LegacyCatalogMigration::convert($config);
        foreach ($config['offers'] as &$offer) {
            if (!self::supports($offer)) {
                continue;
            }
            $offer = array_replace(Defaults::offer(), $offer);
            foreach (
                [
                    'description',
                    'eyebrow',
                    'badge',
                    'category_label',
                    'price_unit',
                    'unit_help_text',
                    'price_note',
                    'content_heading',
                    'detail_url',
                ]
                as $key
            ) {
                $offer[$key] ??= '';
            }
            $offer['facts'] ??= [];
            $offer['fact_icons'] ??= [];
            $offer['features'] ??= [];
            foreach ($offer['fact_icons'] as &$icon) {
                if (is_string($icon) && array_key_exists($icon, Design::factIcons())) {
                    $icon = OfferImage::iconDefaults();
                }
            }
            unset($icon);
        }
        unset($offer);
        return $config;
    }
    public static function input(array $input, array $config): array
    {
        if (!isset($input['offer_id']) && isset($input['sku'])) {
            $input['offer_id'] = $input['sku'];
        }
        if (is_string($input['offer_id'] ?? null)) {
            $input['offer_id'] = $config['legacy_item_map'][$input['offer_id']] ?? $input['offer_id'];
        }
        return $input;
    }
}
