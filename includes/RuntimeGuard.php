<?php
namespace OfferWeave;

/** Separate from Activation because an older edition may have loaded that class already. */
final class RuntimeGuard
{
    /** Inspect actual active plugin headers before loading shared runtime classes. */
    public static function available(string $file, array $priority): bool
    {
        $current = plugin_basename($file);
        $active = array_merge(
            (array) get_option('active_plugins', []),
            array_keys((array) get_site_option('active_sitewide_plugins', [])),
        );
        $conflicts = [];
        foreach (array_unique($active) as $basename) {
            if (
                !is_string($basename) ||
                $basename === $current ||
                !is_file(WP_PLUGIN_DIR . '/' . $basename)
            ) {
                continue;
            }
            $header = get_file_data(WP_PLUGIN_DIR . '/' . $basename, ['domain' => 'Text Domain']);
            if ($header['domain'] === 'offerweave') {
                $conflicts[] = $basename;
            }
        }
        $version = get_file_data($file, ['version' => 'Version'])['version'];
        foreach ($conflicts as $basename) {
            $other = get_file_data(WP_PLUGIN_DIR . '/' . $basename, ['version' => 'Version'])['version'];
            if ($other !== '' && $version !== '' && version_compare($other, $version, '<')) {
                // Old bootstraps cannot recognize today's prefixed symbols. Never load
                // our classes first and provoke a later redeclaration in that old code.
                if (!has_action('admin_notices', [self::class, 'olderEditionNotice'])) {
                    add_action('admin_notices', [self::class, 'olderEditionNotice']);
                    add_action('network_admin_notices', [self::class, 'olderEditionNotice']);
                }
                return false;
            }
        }
        $rank = array_search(plugin_basename($file), $priority, true);
        $rank = $rank === false ? count($priority) : $rank;
        foreach ($conflicts as $basename) {
            $otherRank = array_search($basename, $priority, true);
            if ($otherRank !== false && $otherRank < $rank) {
                return false;
            }
        }
        return true;
    }

    public static function olderEditionNotice(): void
    {
        if (!current_user_can('activate_plugins')) {
            return;
        }
        echo '<div class="notice notice-warning"><p>' .
            esc_html__(
                'An older OfferWeave edition is still active. Deactivate it under Plugins, then activate the current edition. For network installations, also check individual sites. Your offers, settings and requests are preserved.',
                'offerweave',
            ) .
            '</p></div>';
    }
}
