<?php
namespace OfferWeave;

/** An edition switch is a WordPress user action, never an implicit deactivation. */
final class Activation
{
    public static function conflicts(string $file, bool $networkWide = false): array
    {
        $current = plugin_basename($file);
        $active = (array) get_option('active_plugins', []);
        $active = array_merge($active, array_keys((array) get_site_option('active_sitewide_plugins', [])));
        if ($networkWide && is_multisite()) {
            // A network-wide edition must not collide with a site-only edition on another site.
            $offset = 0;
            do {
                $sites = get_sites([
                    'fields' => 'ids',
                    'network_id' => get_current_network_id(),
                    'number' => 100,
                    'offset' => $offset,
                ]);
                foreach ($sites as $site) {
                    $active = array_merge($active, (array) get_blog_option($site, 'active_plugins', []));
                }
                $offset += 100;
            } while (count($sites) === 100);
        }
        $conflicts = [];
        foreach (array_unique($active) as $basename) {
            if (
                !is_string($basename) ||
                $basename === $current ||
                !is_file(WP_PLUGIN_DIR . '/' . $basename)
            ) {
                continue;
            }
            $data = get_file_data(WP_PLUGIN_DIR . '/' . $basename, ['domain' => 'Text Domain']);
            // Inspect actual active paths, so renamed edition folders are covered too.
            if ($data['domain'] === 'offerweave') {
                $conflicts[] = $basename;
            }
        }
        return $conflicts;
    }

    public static function assertAvailable(string $file, bool $networkWide = false): void
    {
        if (self::conflicts($file, $networkWide)) {
            wp_die(
                esc_html__(
                    'Another OfferWeave edition is active. Deactivate it under Plugins first, then activate this edition. For a network activation, also check individual sites. Your settings and requests are preserved.',
                    'offerweave',
                ),
                esc_html__('OfferWeave edition switch', 'offerweave'),
                ['back_link' => true],
            );
        }
    }
}
