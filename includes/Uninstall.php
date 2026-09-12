<?php
namespace OfferWeave;

if (!defined('ABSPATH')) {
    exit();
}

/** Shared cleanup for the WordPress uninstaller and Freemius' after_uninstall hook. */
final class Uninstall
{
    public static function run(): void
    {
        // Every edition owns the same data. Never clean up while any edition is active.
        $active = array_unique(
            array_merge(
                (array) get_option('active_plugins', []),
                array_keys((array) get_site_option('active_sitewide_plugins', [])),
            ),
        );
        foreach ($active as $plugin) {
            if (!is_string($plugin) || !is_file(WP_PLUGIN_DIR . '/' . $plugin)) {
                continue;
            }
            $data = get_file_data(WP_PLUGIN_DIR . '/' . $plugin, ['domain' => 'Text Domain']);
            // Actual active paths also cover manually renamed edition directories.
            if (($data['domain'] ?? '') === 'offerweave') {
                return;
            }
        }
        $config = get_option('offerweave_config', get_option('cqb_config'));
        wp_clear_scheduled_hook('cqb_retention');
        wp_clear_scheduled_hook('offerweave_retention');
        // Retain requests unless the administrator explicitly selected deletion on uninstall.
        if (empty($config['settings']['delete_on_uninstall'])) {
            return;
        }
        global $wpdb;
        $wpdb->query($wpdb->prepare('DROP TABLE IF EXISTS %i', $wpdb->prefix . 'offerweave_requests'));
        $wpdb->query($wpdb->prepare('DROP TABLE IF EXISTS %i', $wpdb->prefix . 'cqb_requests'));
        wp_cache_delete('last_changed', 'offerweave_requests');
        foreach (
            [
                'cqb_config',
                'cqb_config_v1_backup',
                'cqb_db_version',
                'offerweave_prefix_migration',
                'offerweave_before_independent_prices_v1',
                'offerweave_independent_prices_revision',
                'offerweave_config',
                'offerweave_config_v1_backup',
                'offerweave_db_version',
                'offerweave_owner_before_cards_v1',
                'offerweave_owner_cards_revision',
                'offerweave_owner_before_variants_v1',
                'offerweave_owner_variants_revision',
            ]
            as $option
        ) {
            delete_option($option);
        }
        // The legacy convati1_* options and request table are intentionally independent.
    }
}
