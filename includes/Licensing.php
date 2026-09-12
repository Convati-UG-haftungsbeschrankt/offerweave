<?php
namespace OfferWeave;

/** Public build metadata and the official SDK; never an option, URL or REST unlock. */
final class Licensing
{
    private static array $release = [];
    private static $sdk = null;

    public static function boot(array $release): void
    {
        self::$release = $release;
        if (!self::configured()) {
            return;
        }
        // The SDK is shared with other plugins and may already have defined its constants.
        // Enforce verified HTTPS at the WordPress boundary instead of modifying vendor code.
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
            'is_premium' => self::premiumBuild(),
            'premium_suffix' => 'Pro',
            'has_paid_plans' => true,
            'has_premium_version' => true,
            'is_org_compliant' => true,
            'enable_anonymous' => true,
            'is_live' => true,
            'menu' => ['slug' => 'offerweave', 'support' => false],
        ]);
        self::$sdk->add_filter('plugin_icon', static function () {
            return OFFERWEAVE_DIR . 'assets/brand/offerweave-logo.png';
        });
        self::$sdk->add_action('after_uninstall', [Uninstall::class, 'run']);
        if (self::$release['product_id'] === '39020') {
            require_once OFFERWEAVE_DIR . 'includes/PurchasePage.php';
            PurchasePage::boot();
        }
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

    public static function premiumBuild(): bool
    {
        return (self::$release['edition'] ?? 'free') === 'pro';
    }

    public static function sdk()
    {
        return self::$sdk;
    }

    /** Installed GPL code remains usable independently of commercial service status. */
    public static function entitled(): bool
    {
        return self::premiumBuild();
    }

    /** Local view of the paid service term; official downloads are authorized by Freemius. */
    public static function servicesEntitled(): bool
    {
        // A subscription cancellation stops renewal, not the already paid license term.
        // Do not use can_use_premium_code(): it also admits trials and retained expired features.
        if (!self::premiumBuild() || !self::configured() || self::$sdk === null || !self::$sdk->is_paying()) {
            return false;
        }
        $license = self::$sdk->_get_license();
        if (!($license instanceof \FS_Plugin_License) || $license->is_first_payment_pending()) {
            return false;
        }
        if ($license->is_lifetime()) {
            return true;
        }
        // Enforce the known UTC end even offline, at the boundary, and in long-running requests.
        // This is read-only: expiration must never rewrite saved Pro configuration or requests.
        $end = \DateTimeImmutable::createFromFormat(
            '!Y-m-d H:i:s',
            (string) $license->expiration,
            new \DateTimeZone('UTC'),
        );
        return $end !== false &&
            $end->format('Y-m-d H:i:s') === $license->expiration &&
            $end->getTimestamp() > time();
    }

    /** Local plan comparison; independent of optional SDK account opt-in. */
    public static function purchaseUrl(): string
    {
        if (!self::configured() || self::$release['product_id'] !== '39020' || self::servicesEntitled()) {
            return '';
        }
        return admin_url('admin.php?page=offerweave-pro');
    }

    public static function status(): array
    {
        return [
            'build' => self::premiumBuild() ? 'pro' : 'free',
            'configured' => self::configured(),
            'pro' => Edition::pro(),
            'services_active' => self::servicesEntitled(),
            'account_url' => self::$sdk ? self::$sdk->get_account_url() : '',
            'upgrade_url' => self::$sdk ? self::$sdk->get_upgrade_url() : '',
            'purchase_url' => self::purchaseUrl(),
        ];
    }
}
