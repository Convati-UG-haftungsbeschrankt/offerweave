<?php
namespace OfferWeave;

/** Bundled, offline documentation; contains no site-specific configuration or remote embeds. */
final class Handbook
{
    public static function menu(): void
    {
        add_submenu_page(
            'offerweave',
            __('Documentation', 'offerweave'),
            __('Documentation', 'offerweave'),
            'manage_options',
            'offerweave-docs',
            [self::class, 'page'],
        );
    }

    public static function page(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to access this page.', 'offerweave'));
        }
        $language = str_starts_with(determine_locale(), 'de') ? 'de' : 'en';
        $manifest = json_decode((string) file_get_contents(OFFERWEAVE_DIR . 'handbook/manifest.json'), true);
        // NonceVerification.Recommended: this GET selects an offline handbook chapter only;
        // it changes no saved data and performs no privileged action requiring a nonce.
        // Access is capability-checked above; the value is type-checked, sanitized and
        // allowlisted below, then used only as a fragment in a fixed local URL escaped at output.
        $chapter =
            isset($_GET['chapter']) && is_string($_GET['chapter'])
                ? sanitize_key(wp_unslash($_GET['chapter']))
                : 'start';
        if (!in_array($chapter, $manifest['chapters'] ?? [], true)) {
            $chapter = 'start';
        }
        $url =
            OFFERWEAVE_URL .
            'handbook/' .
            $language .
            '/index.html?v=' .
            rawurlencode(OFFERWEAVE_VERSION) .
            '#' .
            $chapter;
        echo '<div class="wrap ow-handbook-page"><h1>OfferWeave – ' .
            esc_html__('Documentation', 'offerweave') .
            '</h1><p>' .
            esc_html__(
                'The handbook is included locally. Search, switch languages or print chapters directly in the reader.',
                'offerweave',
            ) .
            '</p><p><a class="button" href="' .
            esc_url($url) .
            '" target="_blank" rel="noopener">' .
            esc_html__('Open handbook in a new tab', 'offerweave') .
            '</a></p><iframe style="display:block;width:100%;height:calc(100vh - 220px);min-height:550px;border:1px solid #ccd5e4;border-radius:8px;background:white" title="' .
            esc_attr__('OfferWeave handbook', 'offerweave') .
            '" src="' .
            esc_url($url) .
            '"></iframe></div>';
    }
}
