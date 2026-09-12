<?php
namespace OfferWeave;

/** Free package implementation. All functionality in this file is available without an upgrade. */
final class FrontendDesign
{
    public static function resolve(array $config, ?array $offer = null, string $view = ''): array
    {
        return Design::defaults($config['settings'] ?? []);
    }
    public static function attributes(array $values, array $extra = []): string
    {
        $defaults = Design::defaults();
        foreach (['accent', 'card_bg'] as $key) {
            if (!is_string($values[$key] ?? null) || !preg_match('/^#[0-9a-f]{6}$/iD', $values[$key])) {
                $values[$key] = $defaults[$key];
            }
        }
        $radius = is_int($values['card_radius'] ?? null) ? max(0, min(24, $values['card_radius'])) : 6;
        return ' style="' .
            esc_attr(
                '--cqb-accent:' .
                    $values['accent'] .
                    ';--cqb-surface:' .
                    $values['card_bg'] .
                    ';--cqb-radius:' .
                    $radius .
                    'px;',
            ) .
            '"';
    }
    public static function icon(string $name): string
    {
        $paths = [
            'people' =>
                '<circle cx="9" cy="7" r="3"/><path d="M3 21v-3a6 6 0 0 1 12 0v3M16 4a3 3 0 0 1 0 6m2 4a6 6 0 0 1 3 5v2"/>',
            'mail' => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 6 9 7 9-7"/>',
            'key' => '<circle cx="15" cy="8" r="5"/><path d="m11 12-8 8v2h4v-3h3v-3l3-3"/>',
            'laptop' => '<path d="M5 16V5h14v11M2 16h20l-2 4H4Z"/>',
            'monitor' => '<rect x="3" y="3" width="18" height="13" rx="1"/><path d="M12 16v5m-4 0h8"/>',
            'scan' => '<path d="M8 3H3v5m13-5h5v5M3 16v5h5m13-5v5h-5M8 10h.01M16 10h.01M8 15q4 4 8 0"/>',
            'alert' =>
                '<path d="M7 17V9a5 5 0 0 1 10 0v8m-12 0h14v4H5ZM12 1v1M3 5l2 1m16-1-2 1M2 12h2m16 0h2"/>',
            'sparkles' => '<path d="m12 3 3 6 6 3-6 3-3 6-3-6-6-3 6-3ZM20 2v4m-2-2h4"/>',
            'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 6v6l4 2"/>',
            'check' => '<path d="m5 12 4 4L19 6"/>',
            'calendar' => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4m8-4v4"/>',
            'location' =>
                '<path d="M19 10c0 5-7 11-7 11S5 15 5 10a7 7 0 0 1 14 0Z"/><circle cx="12" cy="10" r="2"/>',
            'info' => '<circle cx="12" cy="12" r="9"/><path d="M12 11v6m0-10h.01"/>',
            'star' => '<path d="m12 3 3 6 7 1-5 5 1 7-6-3-6 3 1-7-5-5 7-1Z"/>',
            'circle' => '<circle cx="12" cy="12" r="5"/>',
            'layers' => '<path d="m12 3 10 5-10 5L2 8Zm-10 9 10 5 10-5M2 16l10 5 10-5"/>',
        ];
        // Only these bundled literal paths can be emitted; uploaded SVGs remain external images.
        return isset($paths[$name])
            ? '<svg class="cqb-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' .
                    $paths[$name] .
                    '</svg>'
            : '';
    }
}
