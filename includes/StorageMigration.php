<?php
namespace OfferWeave;

/** Idempotent migration of the former Quote Builder storage, without changing its values. */
final class StorageMigration
{
    public static function run(): void
    {
        if ((int) get_option('offerweave_prefix_migration') === 1) {
            return;
        }
        global $wpdb;
        foreach (['config', 'config_v1_backup', 'db_version'] as $suffix) {
            $old = get_option('cqb_' . $suffix);
            if (get_option('offerweave_' . $suffix) === false && $old !== false) {
                add_option('offerweave_' . $suffix, $old, '', false);
                if (get_option('offerweave_' . $suffix) === false) {
                    throw new \RuntimeException(
                        'OfferWeave settings migration failed. Original data was retained.',
                    );
                }
            }
        }
        $oldTable = $wpdb->prefix . 'cqb_requests';
        $newTable = $wpdb->prefix . 'offerweave_requests';
        if (!self::tableExists($newTable) && self::tableExists($oldTable)) {
            // Rename preserves row IDs, unique request keys, snapshots and delivery status atomically.
            if (defined('DB_ENGINE') && DB_ENGINE === 'sqlite') {
                self::renameSqliteTable($oldTable, $newTable);
            } else {
                $wpdb->query($wpdb->prepare('RENAME TABLE %i TO %i', $oldTable, $newTable));
            }
            if (!self::tableExists($newTable)) {
                throw new \RuntimeException(
                    'OfferWeave request-table migration failed. Original data was retained.',
                );
            }
        }
        $cronMigrated = true;
        // If both exist, the new store is authoritative. Keep the old table untouched for recovery.
        foreach (_get_cron_array() ?: [] as $timestamp => $hooks) {
            foreach ($hooks['cqb_retention'] ?? [] as $event) {
                $args = $event['args'] ?? [];
                $current = wp_get_scheduled_event('offerweave_retention', $args, (int) $timestamp);
                $ok =
                    $current ?:
                    ($event['schedule']
                        ? wp_schedule_event(
                            (int) $timestamp,
                            $event['schedule'],
                            'offerweave_retention',
                            $args,
                            true,
                        )
                        : wp_schedule_single_event((int) $timestamp, 'offerweave_retention', $args, true));
                if ($ok && !is_wp_error($ok)) {
                    if (!wp_unschedule_event((int) $timestamp, 'cqb_retention', $args)) {
                        $cronMigrated = false;
                    }
                } else {
                    $cronMigrated = false;
                }
            }
        }
        if ($cronMigrated && self::tableExists($newTable)) {
            update_option('offerweave_prefix_migration', 1, false);
        }
    }
    private static function renameSqliteTable(string $oldTable, string $newTable): void
    {
        global $wpdb;
        if ($wpdb->query('START TRANSACTION') === false) {
            throw new \RuntimeException('OfferWeave could not start the request-table migration.');
        }
        try {
            // SQLite's WordPress adapter takes a write lock for this transaction.
            // A concurrent upgrader may have completed while this request waited.
            if (!self::tableExists($newTable)) {
                $schema = $wpdb->get_row($wpdb->prepare('SHOW CREATE TABLE %i', $oldTable), ARRAY_N);
                $prefix = $wpdb->prepare('CREATE TABLE %i', $oldTable);
                if (
                    !is_array($schema) ||
                    !is_string($schema[1] ?? null) ||
                    !str_starts_with($schema[1], $prefix . ' (')
                ) {
                    throw new \RuntimeException(
                        'OfferWeave could not read the original request-table schema.',
                    );
                }
                // Preserve all columns, indexes and the existing auto-increment counter.
                $create = $wpdb->prepare('CREATE TABLE %i', $newTable) . substr($schema[1], strlen($prefix));
                // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Database-generated SHOW CREATE TABLE schema; only its verified leading table identifier is replaced using wpdb::prepare(%i). No request data enters this DDL.
                if ($wpdb->query($create) === false || !self::tableExists($newTable)) {
                    throw new \RuntimeException('OfferWeave could not create the migrated request table.');
                }
                if (
                    $wpdb->query($wpdb->prepare('INSERT INTO %i SELECT * FROM %i', $newTable, $oldTable)) ===
                    false
                ) {
                    throw new \RuntimeException('OfferWeave could not copy the original requests.');
                }
                $offset = 0;
                do {
                    $original = $wpdb->get_results(
                        $wpdb->prepare(
                            'SELECT * FROM %i ORDER BY id LIMIT 200 OFFSET %d',
                            $oldTable,
                            $offset,
                        ),
                        ARRAY_A,
                    );
                    if ($wpdb->last_error !== '') {
                        throw new \RuntimeException('OfferWeave could not verify the original requests.');
                    }
                    $copied = $wpdb->get_results(
                        $wpdb->prepare(
                            'SELECT * FROM %i ORDER BY id LIMIT 200 OFFSET %d',
                            $newTable,
                            $offset,
                        ),
                        ARRAY_A,
                    );
                    if ($wpdb->last_error !== '' || $original !== $copied) {
                        throw new \RuntimeException('OfferWeave request-table verification failed.');
                    }
                    $offset += 200;
                } while (count($original) === 200);
                if ($wpdb->query($wpdb->prepare('DROP TABLE %i', $oldTable)) === false) {
                    throw new \RuntimeException('OfferWeave could not finish the request-table migration.');
                }
            }
            if ($wpdb->query('COMMIT') === false) {
                throw new \RuntimeException('OfferWeave could not commit the request-table migration.');
            }
        } catch (\Throwable $error) {
            $wpdb->query('ROLLBACK');
            throw new \RuntimeException(
                'OfferWeave request-table migration failed. Original data was retained.',
                0,
                // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Argument 3 is the previous Throwable, not output; the message in argument 1 is fixed. Escaping would destroy the required exception type.
                $error,
            );
        }
    }
    private static function tableExists(string $table): bool
    {
        global $wpdb;
        return $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table))) === $table;
    }
    public static function transient(string $key, string $oldKey, int $maximumTtl)
    {
        $value = get_transient($key);
        if ($value !== false) {
            return $value;
        }
        $value = get_transient($oldKey);
        if ($value !== false) {
            $expires = (int) get_option('_transient_timeout_' . $oldKey);
            $ttl = $expires ? max(1, min($maximumTtl, $expires - time())) : $maximumTtl;
            set_transient($key, $value, $ttl);
        }
        return $value;
    }
}
