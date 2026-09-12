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
        update_option('offerweave_db_version', '2', false);
        wp_cache_delete('last_changed', 'offerweave_requests');
    }
    public static function get(int $id): ?array
    {
        global $wpdb;
        $r = $wpdb->get_row($wpdb->prepare('SELECT * FROM %i WHERE id=%d', self::table(), $id), ARRAY_A);
        return $r ?: null;
    }
    public static function byKey(string $key): ?array
    {
        global $wpdb;
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
        $result = $wpdb->update(self::table(), ['status' => $status], ['id' => $id], ['%s'], ['%d']);
        if ($result !== false) {
            wp_cache_delete('last_changed', 'offerweave_requests');
        }
        return $result !== false;
    }
    public static function delete(int $id): bool
    {
        global $wpdb;
        $result = $wpdb->delete(self::table(), ['id' => $id], ['%d']);
        if ($result !== false) {
            wp_cache_delete('last_changed', 'offerweave_requests');
        }
        return $result !== false;
    }
    public static function claimMail(int $id): bool
    {
        global $wpdb;
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
