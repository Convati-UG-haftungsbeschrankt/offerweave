<?php
namespace OfferWeave;

/** Optional SDK account integration; Free features and the local purchase page work without an account. */
final class Licensing
{
    private static array $release = [];
    private static $sdk = null;

    public static function boot(array $release): void
    {
        self::$release = $release;
        require_once OFFERWEAVE_DIR . 'includes/PurchasePage.php';
        PurchasePage::boot();
        if (!self::configured()) {
            return;
        }
        // The SDK replaces submenus while showing its optional first-run connection page.
        // Re-register the existing purchase menu afterwards, including before opt-in/skip.
        remove_action('admin_menu', [PurchasePage::class, 'menu'], 99);
        add_action('admin_menu', [PurchasePage::class, 'menu'], PHP_INT_MAX);
        // Keep verified HTTPS outside the original, unmodified SDK (as in 2.35.12).
        add_filter('http_request_args', [self::class, 'httpsArguments'], PHP_INT_MAX, 2);
        add_filter('pre_http_request', [self::class, 'httpsRequest'], PHP_INT_MAX, 3);
        add_action('requests-requests.before_redirect', [self::class, 'httpsRedirect'], PHP_INT_MAX, 5);
        require_once OFFERWEAVE_DIR . 'vendor/freemius/start.php';
        self::$sdk = fs_dynamic_init([
            'id' => self::$release['product_id'],
            'slug' => 'offerweave',
            'premium_slug' => 'offerweave-premium',
            'type' => 'plugin',
            'public_key' => self::$release['public_key'],
            'is_premium' => false,
            'premium_suffix' => 'Pro',
            'has_paid_plans' => true,
            'has_premium_version' => true,
            'is_org_compliant' => true,
            'enable_anonymous' => true,
            'is_live' => true,
            'menu' => ['slug' => 'offerweave', 'support' => false, 'pricing' => false],
        ]);
        self::$sdk->add_filter('plugin_icon', static function () {
            return OFFERWEAVE_DIR . 'assets/brand/offerweave-logo.png';
        });
        // Returning the local URL even for the SDK's null menu probe avoids registering
        // its embedded pricing renderer. Account price links use the same existing page.
        self::$sdk->add_filter('pricing_url', [self::class, 'purchaseUrl']);
        self::$sdk->add_action('after_uninstall', [Uninstall::class, 'run']);
        do_action('offerweave_fs_loaded');
    }

    private static function freemiusApi(string $url): bool
    {
        return in_array(
            strtolower((string) wp_parse_url($url, PHP_URL_HOST)),
            ['api.freemius.com', 'sandbox-api.freemius.com'],
            true,
        );
    }

    /** Keep the WordPress CA bundle (or the site's explicit CA file) and verify the server. */
    public static function httpsArguments(array $args, string $url): array
    {
        if (self::freemiusApi($url)) {
            $args['sslverify'] = true;
        }
        return $args;
    }

    /** Upgrade legacy SDK HTTP retries before transport; failed TLS must stay a failed request. */
    public static function httpsRequest($pre, array $args, string $url)
    {
        if ($pre !== false || !self::freemiusApi($url)) {
            return $pre;
        }
        if (strtolower((string) wp_parse_url($url, PHP_URL_SCHEME)) === 'http') {
            // Scheme is not part of the SDK's path-based request signature. Preserve body,
            // headers and method, including for an old persisted api_force_http setting.
            return wp_remote_request(set_url_scheme($url, 'https'), self::httpsArguments($args, $url));
        }
        return $pre;
    }

    /** Requests follows redirects internally, without running pre_http_request again. */
    public static function httpsRedirect(&$location, $headers, $data, &$options, $response): void
    {
        if (empty($options['offerweave_verified_api']) && !self::freemiusApi($response->url)) {
            return;
        }
        $options['offerweave_verified_api'] = true;
        if (strtolower((string) wp_parse_url($location, PHP_URL_SCHEME)) === 'http') {
            $location = set_url_scheme($location, 'https');
        }
    }

    public static function configured(): bool
    {
        return is_string(self::$release['product_id'] ?? null) &&
            preg_match('/^[1-9][0-9]*$/D', self::$release['product_id']) &&
            is_string(self::$release['public_key'] ?? null) &&
            preg_match('/^pk_[a-zA-Z0-9]{16,128}$/D', self::$release['public_key']);
    }

    public static function sdk()
    {
        return self::$sdk;
    }

    public static function purchaseUrl(): string
    {
        return admin_url('admin.php?page=offerweave-pro');
    }

    public static function status(): array
    {
        return [
            'build' => 'free',
            'configured' => self::configured(),
            'pro' => false,
            'services_active' => false,
            'account_url' =>
                self::$sdk && self::$sdk->is_registered()
                    ? self::$sdk->get_account_url()
                    : 'https://customers.freemius.com/store/19833/login/',
            'upgrade_url' => self::purchaseUrl(),
            'purchase_url' => self::purchaseUrl(),
        ];
    }
}
