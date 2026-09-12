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
        return $ok === false ? 0 : (int) $wpdb->insert_id;
    }
    public static function listing(string $search, int $page): array
    {
        global $wpdb;
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
        foreach ($rows as &$row) {
            unset($row['request_key'], $row['fingerprint']);
            $row['snapshot'] = json_decode($row['payload'], true);
            unset($row['payload']);
        }
        return [
            'rows' => $rows,
            'total' => $total,
            'page' => $page,
            'pages' => max(1, (int) ceil($total / 20)),
        ];
    }
    public static function status(int $id, string $status): bool
    {
        global $wpdb;
        return $wpdb->update(self::table(), ['status' => $status], ['id' => $id], ['%s'], ['%d']) !== false;
    }
    public static function delete(int $id): bool
    {
        global $wpdb;
        return $wpdb->delete(self::table(), ['id' => $id], ['%d']) !== false;
    }
    public static function claimMail(int $id): bool
    {
        global $wpdb;
        return $wpdb->query(
            $wpdb->prepare(
                "UPDATE %i SET mail_status='sending', mail_attempt_at=%s WHERE id=%d AND (mail_status<>'sending' OR mail_attempt_at<%s)",
                self::table(),
                current_time('mysql', true),
                $id,
                gmdate('Y-m-d H:i:s', time() - 300),
            ),
        ) === 1;
    }
    public static function mailResult(int $id, bool $ok): void
    {
        global $wpdb;
        $wpdb->update(
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
            $wpdb->query(
                $wpdb->prepare(
                    'DELETE FROM %i WHERE created_at<%s',
                    self::table(),
                    gmdate('Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS),
                ),
            );
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
        return $wpdb->query(
            $wpdb->prepare(
                "UPDATE %i SET customer_mail_status='sending',customer_mail_attempt_at=%s WHERE id=%d AND ((%d=1 AND customer_mail_status<>'sending') OR (%d=0 AND customer_mail_status='pending') OR (customer_mail_status='sending' AND customer_mail_attempt_at<%s))",
                self::table(),
                current_time('mysql', true),
                $id,
                (int) $manual,
                (int) $manual,
                gmdate('Y-m-d H:i:s', time() - 300),
            ),
        ) === 1;
    }
    public static function customerMailResult(int $id, string $status, string $error = ''): void
    {
        global $wpdb;
        $wpdb->update(
            self::table(),
            ['customer_mail_status' => $status, 'customer_mail_error' => $error],
            ['id' => $id],
            ['%s', '%s'],
            ['%d'],
        );
    }
}
