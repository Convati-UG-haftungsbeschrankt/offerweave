<?php
namespace OfferWeave;

final class Admin
{
    private static string $requestsHook = '';
    public static function legacyRoute(): void
    {
        $page =
            isset($_GET['page']) && is_string($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
        if ($page === 'cqb' && current_user_can('manage_options')) {
            wp_safe_redirect(admin_url('admin.php?page=offerweave'));
            exit();
        }
    }
    public static function menu(): void
    {
        add_menu_page(
            'OfferWeave',
            'OfferWeave',
            'manage_options',
            'offerweave',
            [self::class, 'page'],
            OFFERWEAVE_URL . 'assets/brand/offerweave-logo.png',
            58,
        );
        self::$requestsHook = (string) add_submenu_page(
            'offerweave',
            __('Requests', 'offerweave'),
            __('Requests', 'offerweave'),
            'manage_options',
            'offerweave-requests',
            [self::class, 'page'],
        );
        Handbook::menu();
    }
    public static function assets(string $hook): void
    {
        wp_enqueue_style(
            'offerweave-admin-brand',
            OFFERWEAVE_URL . 'assets/admin-brand.css',
            [],
            OFFERWEAVE_VERSION,
        );
        $requests = self::$requestsHook !== '' && $hook === self::$requestsHook;
        if ($hook !== 'toplevel_page_offerweave' && !$requests) {
            return;
        }
        wp_enqueue_script(
            'offerweave-money',
            OFFERWEAVE_URL . 'assets/money.js',
            [],
            OFFERWEAVE_VERSION,
            true,
        );
        wp_enqueue_style('offerweave-admin', OFFERWEAVE_URL . 'assets/admin.css', [], OFFERWEAVE_VERSION);
        wp_enqueue_script(
            'offerweave-admin-dialogs',
            OFFERWEAVE_URL . 'assets/admin-dialogs.js',
            ['wp-i18n'],
            OFFERWEAVE_VERSION,
            true,
        );
        if (!$requests) {
            wp_enqueue_media();
            wp_enqueue_script(
                'offerweave-admin-images',
                OFFERWEAVE_URL . 'assets/admin-images.js',
                ['media-views', 'wp-i18n'],
                OFFERWEAVE_VERSION,
                true,
            );
            wp_enqueue_script(
                'offerweave-admin-languages',
                OFFERWEAVE_URL . 'assets/admin-languages.js',
                ['wp-i18n', 'offerweave-admin-dialogs'],
                OFFERWEAVE_VERSION,
                true,
            );
            wp_enqueue_script(
                'offerweave-admin-offers',
                OFFERWEAVE_URL . 'assets/admin-offers.js',
                ['wp-i18n'],
                OFFERWEAVE_VERSION,
                true,
            );
        }
        wp_enqueue_script(
            'offerweave-admin',
            OFFERWEAVE_URL . 'assets/admin-free.js',
            $requests
                ? ['wp-i18n', 'offerweave-admin-dialogs', 'offerweave-money']
                : array_merge([
                    'offerweave-money',
                    'offerweave-admin-images',
                    'offerweave-admin-languages',
                    'offerweave-admin-offers',
                    'wp-i18n',
                    'offerweave-admin-dialogs',
                ]),
            OFFERWEAVE_VERSION,
            true,
        );
        foreach (
            [
                'offerweave-admin-images',
                'offerweave-admin-languages',
                'offerweave-admin-offers',
                'offerweave-admin-dialogs',
                'offerweave-admin',
            ]
            as $handle
        ) {
            wp_set_script_translations($handle, 'offerweave');
        }
        wp_add_inline_script(
            'offerweave-admin',
            'window.offerweave_admin=' .
                wp_json_encode(
                    [
                        'screen' => $requests ? 'requests' : 'configuration',
                        'api' => rest_url(Api::NS . '/'),
                        'nonce' => wp_create_nonce('wp_rest'),
                        'assets' => OFFERWEAVE_URL . 'assets/',
                        'version' => OFFERWEAVE_VERSION,
                        'currencies' => Currency::catalog(),
                        'docsUrl' => admin_url('admin.php?page=offerweave-docs'),
                        'edition' => Licensing::status(),
                        'features' => Edition::catalog(),
                        'locale' => I18n::current(),
                        'textLimits' => ConfigValidation::limits(),
                        'legalDefaults' => Legal::defaults(),
                        'legalTexts' => Legal::textSchema(),
                        'wpHooks' => includes_url('js/dist/hooks.min.js'),
                        'wpI18n' => includes_url('js/dist/i18n.min.js'),
                    ],
                    JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT,
                ) .
                ';',
            'before',
        );
    }
    public static function page(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to access this page.', 'offerweave'));
        }
        $requests = get_current_screen()?->id === self::$requestsHook;
        echo '<div class="wrap cqb-admin" id="offerweave-admin"><header class="qb-top"><div class="qb-brand"><span class="qb-brand-mark" aria-hidden="true"><img src="' .
            esc_url(OFFERWEAVE_URL . 'assets/brand/offerweave-logo.png') .
            '" alt="" width="76" height="76"></span><h1>OfferWeave' .
            ($requests ? ' – ' . esc_html__('Requests', 'offerweave') : '') .
            '</h1></div></header><p role="status">' .
            ($requests
                ? esc_html__('Loading requests …', 'offerweave')
                : esc_html__('Loading configuration …', 'offerweave')) .
            '</p></div>';
    }
    public static function legacyNotice(): void
    {
        if (!current_user_can('manage_options') || !defined('CONVATI1_VER')) {
            return;
        }
        echo '<div class="notice notice-warning"><p><strong>OfferWeave:</strong> ' .
            esc_html__('The original Convati Quote plugin is also active. First use the new', 'offerweave') .
            ' <code>offerweave</code>' .
            esc_html__('shortcodes on a test page. To switch', 'offerweave') .
            ' <code>[rq_request]</code> ' .
            esc_html__('the original plugin must be deactivated. Its data will be preserved.', 'offerweave') .
            '</p></div>';
    }
}
