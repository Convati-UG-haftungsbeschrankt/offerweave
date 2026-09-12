<?php
namespace OfferWeave;

final class Store
{
    public static function table(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'offerweave_requests';
    }
    public static function install(): void
    {
        StorageMigration::run();
        global $wpdb;
        $table = self::table();
        $charset = $wpdb->get_charset_collate();
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta("CREATE TABLE $table (
            id bigint unsigned NOT NULL AUTO_INCREMENT,
            created_at datetime NOT NULL,
            request_key varchar(64) NOT NULL,
            fingerprint varchar(64) NOT NULL,
            contact_email varchar(190) NOT NULL,
            payload longtext NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'new',
            mail_to varchar(190) NOT NULL,
            mail_status varchar(20) NOT NULL DEFAULT 'pending',
            mail_error text NULL,
            mail_attempt_at datetime NULL,
            customer_mail_status varchar(20) NOT NULL DEFAULT 'disabled',
            customer_mail_error text NULL,
            customer_mail_attempt_at datetime NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY request_key (request_key),
            KEY created_at (created_at),
            KEY contact_email (contact_email)
        ) $charset;");
        self::assertInstalledSchema();
        update_option('offerweave_db_version', '2', false);
        wp_cache_delete('last_changed', 'offerweave_requests');
    }
    private static function assertInstalledSchema(): void
    {
        global $wpdb;
        // DirectQuery / NoCaching: dbDelta reports planned changes, not confirmed success.
        // Read the current own-table columns after DDL before marking installation complete.
        // A cached schema could hide a failed creation/alteration; %i prepares the fixed table.
        $columns = $wpdb->get_results($wpdb->prepare('SHOW COLUMNS FROM %i', self::table()), ARRAY_A);
        if ($wpdb->last_error !== '' || !is_array($columns)) {
            throw new \RuntimeException('OfferWeave could not verify the request-table columns.');
        }
        $fields = [];
        foreach ($columns as $column) {
            $fields[strtolower((string) ($column['Field'] ?? ''))] = $column;
        }
        $required = [
            'id',
            'created_at',
            'request_key',
            'fingerprint',
            'contact_email',
            'payload',
            'status',
            'mail_to',
            'mail_status',
            'mail_error',
            'mail_attempt_at',
            'customer_mail_status',
            'customer_mail_error',
            'customer_mail_attempt_at',
        ];
        if (
            array_diff($required, array_keys($fields)) ||
            !str_contains(strtolower((string) ($fields['id']['Extra'] ?? '')), 'auto_increment')
        ) {
            throw new \RuntimeException('OfferWeave request-table installation is incomplete.');
        }
        // DirectQuery / NoCaching: confirm the actual indexes after dbDelta, especially the
        // unique request key used for concurrent submissions. No metadata cache is maintained.
        // Prepared own-table name; additional columns/indexes and equivalent index names are allowed.
        $indexes = $wpdb->get_results($wpdb->prepare('SHOW INDEX FROM %i', self::table()), ARRAY_A);
        if ($wpdb->last_error !== '' || !is_array($indexes)) {
            throw new \RuntimeException('OfferWeave could not verify the request-table indexes.');
        }
        $groups = [];
        foreach ($indexes as $index) {
            $name = strtolower((string) ($index['Key_name'] ?? ''));
            $groups[$name]['columns'][(int) ($index['Seq_in_index'] ?? 0)] = strtolower(
                (string) ($index['Column_name'] ?? ''),
            );
            $groups[$name]['partial'] = ($groups[$name]['partial'] ?? false) || !empty($index['Sub_part']);
            $groups[$name]['non_unique'] =
                ($groups[$name]['non_unique'] ?? false) || (int) ($index['Non_unique'] ?? 1) !== 0;
        }
        $primary = $unique = $created = $email = false;
        foreach ($groups as $name => $index) {
            ksort($index['columns']);
            $names = array_values($index['columns']);
            if ($index['partial']) {
                continue;
            }
            $primary = $primary || ($name === 'primary' && !$index['non_unique'] && $names === ['id']);
            $unique = $unique || (!$index['non_unique'] && $names === ['request_key']);
            $created = $created || ($names[0] ?? '') === 'created_at';
            $email = $email || ($names[0] ?? '') === 'contact_email';
        }
        if (!$primary || !$unique || !$created || !$email) {
            throw new \RuntimeException('OfferWeave request-table indexes are incomplete.');
        }
    }
    public static function get(int $id): ?array
    {
        global $wpdb;
        // DirectQuery / NoCaching: this is an OfferWeave request row, not a WordPress post.
        // Read its current snapshot and delivery state; a cached row could outlive a mail claim
        // or status change. The plugin-owned table and integer ID use %i/%d placeholders.
        $r = $wpdb->get_row($wpdb->prepare('SELECT * FROM %i WHERE id=%d', self::table(), $id), ARRAY_A);
        return $r ?: null;
    }
    public static function byKey(string $key): ?array
    {
        global $wpdb;
        // DirectQuery / NoCaching: check the unique request key against the current own table
        // so retries can find a request inserted by another process. A cached miss is unsafe
        // for this idempotency lookup; both the identifier and key are prepared with %i/%s.
        $r = $wpdb->get_row(
            $wpdb->prepare('SELECT * FROM %i WHERE request_key=%s', self::table(), $key),
            ARRAY_A,
        );
        return $r ?: null;
    }
    public static function create(
        string $key,
        string $fingerprint,
        string $email,
        string $recipient,
        array $snapshot,
    ): int {
        global $wpdb;
        // DirectQuery: persist the request snapshot in the plugin-owned table, whose unique
        // request_key enforces deduplication. wpdb::insert formats every value explicitly;
        // the listing cache is invalidated below only after a successful database write.
        $ok = $wpdb->insert(
            self::table(),
            [
                'created_at' => current_time('mysql', true),
                'request_key' => $key,
                'fingerprint' => $fingerprint,
                'contact_email' => $email,
                'payload' => wp_json_encode($snapshot),
                'mail_to' => $recipient,
                'status' => 'new',
                'mail_status' => 'pending',
                'customer_mail_status' => self::customerInitialStatus($snapshot),
            ],
            ['%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s'],
        );
        if ($ok !== false) {
            wp_cache_delete('last_changed', 'offerweave_requests');
        }
        return $ok === false ? 0 : (int) $wpdb->insert_id;
    }
    public static function listing(string $search, int $page): array|\WP_Error
    {
        global $wpdb;
        // A generation keeps in-flight reads from repopulating a cache invalidated by a writer.
        // The normal WordPress cache group is blog-local, including on multisite.
        $page = max(1, $page);
        $generation = wp_cache_get_last_changed('offerweave_requests');
        $cacheKey =
            'listing:' . hash('sha256', self::table() . ':' . $search . ':' . $page . ':' . $generation);
        $cached = wp_cache_get($cacheKey, 'offerweave_requests');
        if (is_array($cached)) {
            return $cached;
        }
        $like = '%' . $wpdb->esc_like($search) . '%';
        $all = (int) ($search === '');
        // DirectQuery: pagination needs a count from the own request table. The LIKE value
        // is escaped and all identifiers/values are prepared. Count and rows share the
        // bounded, generation-keyed listing cache above/below; failed queries are not cached.
        $total = (int) $wpdb->get_var(
            $wpdb->prepare(
                'SELECT COUNT(*) FROM %i WHERE (%d=1 OR contact_email LIKE %s OR payload LIKE %s)',
                self::table(),
                $all,
                $like,
                $like,
            ),
        );
        $countFailed = $wpdb->last_error !== '';
        // DirectQuery: fetch one page of own request snapshots in the same search as the
        // count above. Table, search values and offset are prepared; limit is fixed at 20.
        // This result shares the real listing cache and its write-triggered invalidation.
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                'SELECT * FROM %i WHERE (%d=1 OR contact_email LIKE %s OR payload LIKE %s) ORDER BY id DESC LIMIT 20 OFFSET %d',
                self::table(),
                $all,
                $like,
                $like,
                ($page - 1) * 20,
            ),
            ARRAY_A,
        );
        if ($countFailed || $wpdb->last_error !== '' || !is_array($rows)) {
            return new \WP_Error(
                'offerweave_request_storage',
                __('Requests could not be loaded. Please try again.', 'offerweave'),
                ['status' => 503],
            );
        }
        foreach ($rows as &$row) {
            unset($row['request_key'], $row['fingerprint']);
            $row['snapshot'] = json_decode($row['payload'], true);
            unset($row['payload']);
        }
        $result = [
            'rows' => $rows,
            'total' => $total,
            'page' => $page,
            'pages' => max(1, (int) ceil($total / 20)),
        ];
        // Search results contain customer data: bound their lifetime, even after invalidation.
        wp_cache_set($cacheKey, $result, 'offerweave_requests', MINUTE_IN_SECONDS);
        return $result;
    }
    public static function status(int $id, string $status): bool
    {
        global $wpdb;
        // DirectQuery: update the workflow state on the own request row. wpdb::update
        // formats status/ID as %s/%d; successful writes invalidate the listing cache below.
        $result = $wpdb->update(self::table(), ['status' => $status], ['id' => $id], ['%s'], ['%d']);
        if ($result !== false) {
            wp_cache_delete('last_changed', 'offerweave_requests');
        }
        return $result !== false;
    }
    public static function delete(int $id): bool
    {
        global $wpdb;
        // DirectQuery: remove only the selected row from the plugin-owned request table.
        // wpdb::delete formats the ID as %d; the listing cache is invalidated on success.
        $result = $wpdb->delete(self::table(), ['id' => $id], ['%d']);
        if ($result !== false) {
            wp_cache_delete('last_changed', 'offerweave_requests');
        }
        return $result !== false;
    }
    public static function claimMail(int $id): bool
    {
        global $wpdb;
        // DirectQuery: a single conditional UPDATE claims delivery on the own request row.
        // A read-then-write sequence would race with another sender. Identifiers/values are
        // prepared, one affected row grants the claim, and the listing cache is invalidated.
        $result = $wpdb->query(
            $wpdb->prepare(
                "UPDATE %i SET mail_status='sending', mail_attempt_at=%s WHERE id=%d AND (mail_status<>'sending' OR mail_attempt_at<%s)",
                self::table(),
                current_time('mysql', true),
                $id,
                gmdate('Y-m-d H:i:s', time() - 300),
            ),
        );
        if ($result === 1) {
            wp_cache_delete('last_changed', 'offerweave_requests');
        }
        return $result === 1;
    }
    public static function mailResult(int $id, bool $ok): void
    {
        global $wpdb;
        // DirectQuery: persist the delivery result on the own request row so later reads
        // see the outcome. wpdb::update formats all values; success invalidates the listing
        // cache below. Stored error text is data and is escaped separately when displayed.
        $result = $wpdb->update(
            self::table(),
            [
                'mail_status' => $ok ? 'sent' : 'failed',
                'mail_error' => $ok
                    ? ''
                    : __(
                        'The mail system did not confirm sending. Please check the mail environment and try again.',
                        'offerweave',
                    ),
            ],
            ['id' => $id],
            ['%s', '%s'],
            ['%d'],
        );
        if ($result !== false) {
            wp_cache_delete('last_changed', 'offerweave_requests');
        }
    }
    public static function retention(): void
    {
        global $wpdb;
        try {
            $days = (int) (Config::get()['settings']['retention_days'] ?? 0);
        } catch (\DomainException $error) {
            // Invalid settings must neither break cron nor authorize deleting any requests.
            return;
        }
        if ($days > 0) {
            // DirectQuery: enforce the configured positive retention period on the own
            // request table. The table and UTC cutoff are prepared; no arbitrary table is
            // accepted. Deleting any rows invalidates the listing cache below.
            $result = $wpdb->query(
                $wpdb->prepare(
                    'DELETE FROM %i WHERE created_at<%s',
                    self::table(),
                    gmdate('Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS),
                ),
            );
            if ($result > 0) {
                wp_cache_delete('last_changed', 'offerweave_requests');
            }
        }
    }
    private static function customerInitialStatus(array $snapshot): string
    {
        $mode = $snapshot['email']['mode'] ?? 'off';
        if ($mode === 'off') {
            return 'disabled';
        }
        $open = $snapshot['quote']['summary']['has_custom'] || $snapshot['quote']['summary']['has_travel'];
        return $mode === 'manual' || ($mode === 'complete' && $open) ? 'review' : 'pending';
    }
    public static function claimCustomerMail(int $id, bool $manual = false): bool
    {
        global $wpdb;
        // DirectQuery: atomically claim customer delivery under the pending/manual/retry
        // conditions in this UPDATE. A cached status cannot authorize a claim. Table and
        // values are prepared; one affected row is required and invalidates the listing cache.
        $result = $wpdb->query(
            $wpdb->prepare(
                "UPDATE %i SET customer_mail_status='sending',customer_mail_attempt_at=%s WHERE id=%d AND ((%d=1 AND customer_mail_status<>'sending') OR (%d=0 AND customer_mail_status='pending') OR (customer_mail_status='sending' AND customer_mail_attempt_at<%s))",
                self::table(),
                current_time('mysql', true),
                $id,
                (int) $manual,
                (int) $manual,
                gmdate('Y-m-d H:i:s', time() - 300),
            ),
        );
        if ($result === 1) {
            wp_cache_delete('last_changed', 'offerweave_requests');
        }
        return $result === 1;
    }
    public static function customerMailResult(int $id, string $status, string $error = ''): void
    {
        global $wpdb;
        // DirectQuery: persist customer-delivery state/error on the own request row using
        // wpdb's %s/%d formats. Successful writes invalidate the listing cache; stored error
        // text is escaped at its eventual display, not treated as executable SQL or HTML here.
        $result = $wpdb->update(
            self::table(),
            ['customer_mail_status' => $status, 'customer_mail_error' => $error],
            ['id' => $id],
            ['%s', '%s'],
            ['%d'],
        );
        if ($result !== false) {
            wp_cache_delete('last_changed', 'offerweave_requests');
        }
    }
}
