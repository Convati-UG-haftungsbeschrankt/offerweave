<?php
namespace OfferWeave;

/** Free package implementation. All functionality in this file is available without an upgrade. */
final class Design
{
    public static function factIcons(): array
    {
        return [
            'none' => __('No icon', 'offerweave'),
            'clock' => __('Clock', 'offerweave'),
            'layers' => __('Layers', 'offerweave'),
            'mail' => __('Message', 'offerweave'),
            'people' => __('People', 'offerweave'),
            'check' => __('Check mark', 'offerweave'),
            'calendar' => __('Calendar', 'offerweave'),
            'location' => __('Location', 'offerweave'),
            'info' => __('Information', 'offerweave'),
            'star' => __('Star', 'offerweave'),
            'circle' => __('Circle', 'offerweave'),
            'key' => __('Key', 'offerweave'),
            'laptop' => __('Laptop', 'offerweave'),
            'monitor' => __('Screen', 'offerweave'),
            'scan' => __('Scan', 'offerweave'),
            'alert' => __('Alert', 'offerweave'),
            'sparkles' => __('Sparkles', 'offerweave'),
        ];
    }
    public static function schema(): array
    {
        return [
            'accent' => ['type' => 'color', 'default' => '#0b60c6'],
            'card_bg' => ['type' => 'color', 'default' => '#f1f5f9'],
            'card_radius' => ['type' => 'number', 'default' => 6, 'min' => 0, 'max' => 24],
        ];
    }
    public static function defaults(array $settings = []): array
    {
        return [
            'accent' => $settings['accent'] ?? '#0b60c6',
            'card_bg' => $settings['surface'] ?? '#f1f5f9',
            'card_radius' => $settings['radius'] ?? 6,
        ];
    }
    public static function normalize(array $config): array
    {
        return $config;
    }
}
