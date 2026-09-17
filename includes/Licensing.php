<?php
namespace OfferWeave;

/** Free's local edition label and optional purchase links. No account or license checks. */
final class Licensing
{
    public static function boot(): void
    {
        require_once OFFERWEAVE_DIR . 'includes/PurchasePage.php';
        PurchasePage::boot();
    }

    public static function purchaseUrl(): string
    {
        return admin_url('admin.php?page=offerweave-pro');
    }

    public static function status(): array
    {
        return [
            'build' => 'free',
            // This describes local purchase-page availability, not an external connection.
            'configured' => true,
            'pro' => false,
            'services_active' => false,
            'account_url' => 'https://customers.freemius.com/store/19833/login/',
            'upgrade_url' => self::purchaseUrl(),
            'purchase_url' => self::purchaseUrl(),
        ];
    }
}
