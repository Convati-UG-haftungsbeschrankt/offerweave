<?php
namespace OfferWeave;

if (!defined('ABSPATH')) {
    exit();
}

require_once __DIR__ . '/PurchaseView.php';

/** Local upgrade page with plain external links; no remote assets, requests or installer. */
final class PurchasePage extends PurchaseView
{
    private static string $hook = '';

    public static function boot(): void
    {
        add_action('admin_menu', [self::class, 'menu'], 99);
        add_action('admin_enqueue_scripts', [self::class, 'assets']);
    }

    public static function url(): string
    {
        return Licensing::purchaseUrl();
    }

    public static function menu(): void
    {
        self::$hook = (string) add_submenu_page(
            'offerweave',
            __('OfferWeave Pro plans', 'offerweave'),
            __('Pro plans', 'offerweave'),
            'manage_options',
            'offerweave-pro',
            [self::class, 'render'],
        );
    }

    public static function assets(string $hook): void
    {
        if (self::$hook !== '' && $hook === self::$hook) {
            wp_enqueue_style(
                'offerweave-purchase',
                OFFERWEAVE_URL . 'assets/purchase.css',
                [],
                OFFERWEAVE_VERSION,
            );
        }
    }

    public static function checkoutUrl(int $sites): string
    {
        return 'https://checkout.freemius.com/plugin/39020/plan/64877/licenses/' .
            $sites .
            '/currency/eur/?billing_cycle=12';
    }

    public static function accountUrl(): string
    {
        return 'https://customers.freemius.com/store/19833/login/';
    }
}
