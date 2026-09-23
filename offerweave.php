<?php
/**
 * Plugin Name: OfferWeave
 * Description: Offer cards with images, quantity-based fixed prices, request forms and standard customer emails.
 * Version: 2.35.16
 * Requires at least: 6.5
 * Requires PHP: 8.1
 * Author: NovaPlug
 * License: GPL-3.0-only
 * Text Domain: offerweave
 */
if (!defined('ABSPATH')) {
    exit();
}
if (!class_exists('OfferWeave\\RuntimeGuard', false)) {
    require_once __DIR__ . '/includes/RuntimeGuard.php';
}

// All editions share one data store. Users explicitly deactivate the old edition first.
if (!class_exists('OfferWeave\\Activation', false)) {
    require_once __DIR__ . '/includes/Activation.php';
}
$offerweave_basename = plugin_basename(__FILE__);
$offerweave_editions = [
    'offerweave-owner/offerweave.php',
    'offerweave-premium/offerweave.php',
    'offerweave/offerweave.php',
];
$offerweave_activation_file = __FILE__;
register_activation_hook(__FILE__, static function ($networkWide = false) use ($offerweave_activation_file) {
    \OfferWeave\Activation::assertAvailable($offerweave_activation_file, (bool) $networkWide);
    if (!class_exists('OfferWeave\\Store')) {
        return;
    }
    \OfferWeave\Store::install();
    if (get_option('offerweave_config') === false) {
        add_option('offerweave_config', \OfferWeave\Defaults::config(), '', 'no');
    }
    if (!wp_next_scheduled('offerweave_retention')) {
        wp_schedule_event(time() + 3600, 'daily', 'offerweave_retention');
    }
});
// Header-based fallback also handles imported states and renamed old editions.
if (!\OfferWeave\RuntimeGuard::available(__FILE__, $offerweave_editions)) {
    return;
}
if (
    (defined('OFFERWEAVE_DIR') && is_file(OFFERWEAVE_DIR . 'offerweave.php')) ||
    class_exists('OfferWeave\\Store', false)
) {
    // The activation sandbox has loaded another edition. The registered guard
    // refuses this activation before WordPress changes its active plugin list.
    return;
}
unset($offerweave_basename, $offerweave_editions, $offerweave_activation_file);
// The actual old plugin must be inactive before its shared data is migrated.
// Unrelated plugins may use short CQB constants; those never identify an edition.
$offerweave_previous_plugin = 'convati-quote-builder/convati-quote-builder.php';
if (
    in_array($offerweave_previous_plugin, (array) get_option('active_plugins', []), true) ||
    isset(((array) get_site_option('active_sitewide_plugins', []))[$offerweave_previous_plugin])
) {
    register_activation_hook(__FILE__, static function () {
        wp_die(
            esc_html__(
                'Please deactivate Convati Quote Builder before activating OfferWeave. Settings and requests will be preserved.',
                'offerweave',
            ),
        );
    });
    add_action('admin_notices', static function () {
        if (current_user_can('manage_options')) {
            echo '<div class="notice notice-warning"><p><strong>OfferWeave:</strong> ' .
                esc_html__(
                    'Please deactivate the previous Convati Quote Builder version. OfferWeave will then use the existing settings and requests.',
                    'offerweave',
                ) .
                '</p></div>';
        }
    });
    return;
}
unset($offerweave_previous_plugin);
define('OFFERWEAVE_VERSION', '2.35.16');
define('OFFERWEAVE_DIR', plugin_dir_path(__FILE__));
define('OFFERWEAVE_URL', plugin_dir_url(__FILE__));
foreach (
    [
        'Licensing',
        'Uninstall',
        'Edition',
        'I18n',
        'Translations',
        'Defaults',
        'SelectionRules',
        'Design',
        'EmailConfig',
        'OfferImage',
        'LegacyCatalogMigration',
        'Catalog',
        'Currency',
        'Pricing',
        'Tax',
        'Legal',
        'ConfigValidation',
        'Config',
        'StorageMigration',
        'Store',
        'Spam',
        'Mailer',
        'EmailView',
        'MailTransport',
        'Api',
        'FrontendDesign',
        'FrontendPrice',
        'FrontendState',
        'FrontendRequest',
        'FrontendView',
        'Frontend',
        'Handbook',
        'Admin',
    ]
    as $offerweave_class
) {
    require_once OFFERWEAVE_DIR . 'includes/' . $offerweave_class . '.php';
}

\OfferWeave\Licensing::boot(require OFFERWEAVE_DIR . 'release-config.php');
/* DISTRIBUTION_BOOT */

register_deactivation_hook(__FILE__, static function () {
    wp_clear_scheduled_hook('offerweave_retention');
});
add_action('offerweave_retention', [\OfferWeave\Store::class, 'retention']);
add_action('plugins_loaded', static function () {
    \OfferWeave\StorageMigration::run();
    if (get_option('offerweave_db_version') !== '2') {
        \OfferWeave\Store::install();
    }
});
add_action('init', [\OfferWeave\Config::class, 'upgradeStored'], 10);
add_action('rest_api_init', [\OfferWeave\Api::class, 'register']);
add_action('init', [\OfferWeave\Frontend::class, 'register'], 30);
\OfferWeave\FrontendState::register();
add_action('admin_menu', [\OfferWeave\Admin::class, 'menu']);
add_action('admin_enqueue_scripts', [\OfferWeave\Admin::class, 'assets']);
