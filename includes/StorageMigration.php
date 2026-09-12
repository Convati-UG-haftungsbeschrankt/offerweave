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
                // DirectQuery / NoCaching: this schema migration must actually rename the
                // former own table; a cache operation cannot perform DDL. Both names are
                // plugin-generated and prepared with %i. Run only when old exists/new does not.
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
        // DirectQuery / NoCaching: begin the SQLite adapter's real migration transaction.
        // This fixed command has no input values or cacheable result; the adapter's write
        // lock protects the copy/verification/drop sequence from a concurrent migration.
        if ($wpdb->query('START TRANSACTION') === false) {
            throw new \RuntimeException('OfferWeave could not start the request-table migration.');
        }
        try {
            // SQLite's WordPress adapter takes a write lock for this transaction.
            // A concurrent upgrader may have completed while this request waited.
            if (!self::tableExists($newTable)) {
                // DirectQuery / NoCaching / SchemaChange: SHOW CREATE TABLE only reads
                // the current own-table definition; it does not change the schema despite
                // the generic warning. %i prepares the name. Read inside the transaction
                // rather than caching a definition that may predate another migration.
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
                // DirectQuery / NoCaching: execute the verified own-table DDL in this
                // migration transaction; no cached value can create a table. The nearby
                // SchemaChange diagnostic on the failure message refers to this creation;
                // that exception text itself executes no SQL. Confirm the table exists below.
                // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Database-generated SHOW CREATE TABLE schema; only its verified leading table identifier is replaced using wpdb::prepare(%i). No request data enters this DDL.
                if ($wpdb->query($create) === false || !self::tableExists($newTable)) {
                    throw new \RuntimeException('OfferWeave could not create the migrated request table.');
                }
                // DirectQuery / NoCaching: copy rows between the two own tables in the
                // migration transaction. Both identifiers use %i and no submitted values
                // enter the SQL. This is a required write, followed by uncached verification.
                if (
                    $wpdb->query($wpdb->prepare('INSERT INTO %i SELECT * FROM %i', $newTable, $oldTable)) ===
                    false
                ) {
                    throw new \RuntimeException('OfferWeave could not copy the original requests.');
                }
                $offset = 0;
                do {
                    // DirectQuery / NoCaching: read the source rows inside the transaction
                    // for exact migration comparison. A cached page could hide lost/changed
                    // rows. Own-table identifier and offset use %i/%d; each page is bounded.
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
                    // DirectQuery / NoCaching: read the just-copied destination rows from
                    // the database, not an older cache, before allowing source deletion.
                    // Prepared identifier/offset and identical ordering make pages comparable.
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
                // DirectQuery / NoCaching / SchemaChange: drop only the former own table
                // after every copied page matches. The fixed migration identifier uses %i;
                // this is real DDL within the SQLite adapter transaction, not a cached read.
                if ($wpdb->query($wpdb->prepare('DROP TABLE %i', $oldTable)) === false) {
                    throw new \RuntimeException('OfferWeave could not finish the request-table migration.');
                }
            }
            // DirectQuery / NoCaching: actually commit the checked migration transaction.
            // This fixed transaction command has no request input and no cacheable result.
            if ($wpdb->query('COMMIT') === false) {
                throw new \RuntimeException('OfferWeave could not commit the request-table migration.');
            }
        } catch (\Throwable $error) {
            // DirectQuery / NoCaching: issue a real rollback on migration failure so the
            // adapter can retain the original data. This fixed command cannot be cached.
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
        // DirectQuery / NoCaching: migration guards must observe the current database
        // before/after DDL, including a concurrent upgrader's work. The own-table LIKE
        // pattern is escaped then prepared with %s; a cached existence flag would be stale.
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
