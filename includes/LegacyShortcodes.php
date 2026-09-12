<?php
namespace OfferWeave;

/** Read-only aliases for content saved before the OfferWeave prefix was introduced. */
final class LegacyShortcodes
{
    public static function register(callable $render, callable $previousCategory): void
    {
        $aliases = [
            'cqb_builder' => ['builder', null],
            'cqb_catalog' => ['catalog', null],
            'cqb_request' => ['request', null],
            'cqb_phishing' => ['catalog', 'phishing'],
            'cqb_workshops' => ['catalog', 'workshops'],
            'cqb_modules' => ['catalog', 'previous'],
            'rq_request' => ['request', null],
        ];
        foreach ($aliases as $tag => [$view, $category]) {
            if (shortcode_exists($tag)) {
                continue; // Never replace a third-party declaration with a short legacy name.
            }
            add_shortcode($tag, static function ($attributes) use (
                $render,
                $previousCategory,
                $view,
                $category,
            ) {
                $attributes = (array) $attributes;
                if ($category !== null) {
                    $attributes['category'] = $category === 'previous' ? $previousCategory() : $category;
                }
                return $render($view, $attributes);
            });
        }
    }
}
