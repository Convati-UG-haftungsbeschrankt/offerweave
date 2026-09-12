<?php
namespace OfferWeave;

final class Api
{
    public const NS = 'offerweave/v1';
    public static function register(): void
    {
        $public = '__return_true';
        $admin = fn() => current_user_can('manage_options');
        foreach (
            [
                ['/session', 'GET', 'session', $public],
                ['/quote', 'POST', 'preview', $public],
                ['/requests', 'POST', 'submit', $public],
                ['/admin/config', 'GET', 'adminGet', $admin],
                ['/admin/config', 'POST', 'adminSave', $admin],
                ['/admin/validate', 'POST', 'adminValidate', $admin],
                ['/admin/export', 'GET', 'adminExport', $admin],
                ['/admin/defaults', 'GET', 'adminDefaults', $admin],
                ['/admin/price-preview', 'POST', 'adminPricePreview', $admin],
                ['/admin/requests', 'GET', 'adminRequests', $admin],
                ['/admin/email-preview', 'POST', 'adminRequestEmail', $admin],
                ['/admin/requests/(?P<id>\d+)', 'POST', 'adminRequest', $admin],
            ]
            as [$route, $methods, $method, $permission]
        ) {
            register_rest_route(self::NS, $route, [
                'methods' => $methods,
                'callback' => static function (\WP_REST_Request $request) use ($method, $route) {
                    try {
                        if (str_starts_with($route, '/admin/')) {
                            return I18n::run(I18n::locale(), fn() => self::$method($request));
                        }
                        return I18n::run(I18n::requested($request), fn() => self::$method($request));
                    } catch (\DomainException $error) {
                        return self::error($error);
                    }
                },
                'permission_callback' => $permission,
            ]);
        }
    }
    private static function response(array $data, int $status = 200): \WP_REST_Response
    {
        $r = new \WP_REST_Response($data, $status);
        $r->header('Cache-Control', 'no-store, private');
        return $r;
    }
    private static function error(
        string|\DomainException $message,
        int $status = 400,
        string $code = 'offerweave_invalid',
    ): \WP_Error {
        $data = ['status' => $status];
        if ($message instanceof ConfigValidationError) {
            $code = 'offerweave_config_invalid';
            $data['issues'] = $message->issues;
        }
        return new \WP_Error(
            $code,
            $message instanceof \DomainException ? $message->getMessage() : $message,
            $data,
        );
    }
    private static function body(\WP_REST_Request $r, int $max = 65536): array
    {
        if (strlen((string) $r->get_body()) > $max) {
            throw new \DomainException(__('The request is too large.', 'offerweave')); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Api endpoint catch returns JSON; frontend/admin escape the message.
        }
        $data = $r->get_json_params();
        if (!is_array($data)) {
            throw new \DomainException(__('Invalid request.', 'offerweave')); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Api endpoint catch returns JSON; frontend/admin escape the message.
        }
        return $data;
    }
    public static function session(): \WP_REST_Response
    {
        $now = time();
        $config = Translations::apply(Config::runtime());
        return self::response([
            'config' => Config::publicConfig($config, $now),
            'revision' => Config::revision($config, $now),
            'server_time' => $now,
            'next_transition' => null,
            'nonce' => wp_create_nonce('offerweave_submit'),
            'form_token' => Spam::formToken(),
        ]);
    }
    public static function quoteResult(array $config, array $items): array
    {
        return self::quoted($config, $items);
    }
    private static function quoted(array $c, array $items): array
    {
        $now = time();
        $quote = Pricing::quote($c, $items, $now);
        $revision = Config::revision($c, $now);
        $expires = $now + 1800;
        $signature = Spam::sign($revision . '|' . $expires . '|' . wp_json_encode($quote));
        return ['quote' => $quote, 'revision' => $revision, 'expires' => $expires, 'signature' => $signature];
    }
    public static function preview(\WP_REST_Request $r)
    {
        if (!Spam::limit('preview', 180, 60)) {
            return self::error(__('Too many calculations. Please wait a moment.', 'offerweave'), 429);
        }
        try {
            $b = self::body($r);
            if (!is_array($b['items'] ?? null)) {
                throw new \DomainException(__('The selection is invalid.', 'offerweave'));
            }
            return self::response(self::quoted(Translations::apply(Config::runtime()), $b['items']));
        } catch (\DomainException $e) {
            return self::error($e->getMessage());
        }
    }
    private static function fields(array $c, $values): array
    {
        if (!is_array($values)) {
            throw new \DomainException(__('Please complete the form.', 'offerweave')); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Api endpoint catch returns JSON; frontend/admin escape the message.
        }
        $result = [];
        $email = '';
        foreach ($c['fields'] as $f) {
            if (!$f['enabled']) {
                continue;
            }
            $v = $values[$f['id']] ?? '';
            if ($f['type'] === 'checkbox') {
                if (!in_array($v, [true, false, '', 0, 1, '0', '1'], true)) {
                    throw new \DomainException($f['label'] . __(': Invalid value.', 'offerweave')); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Api endpoint catch returns JSON; frontend/admin escape the message.
                }
                $checked = in_array($v, [true, 1, '1'], true);
                if ($f['required'] && !$checked) {
                    throw new \DomainException(__('Please confirm:', 'offerweave') . ' ' . $f['label']); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Api endpoint catch returns JSON; frontend/admin escape the message.
                }
                $v = $checked ? __('Yes', 'offerweave') : __('No', 'offerweave');
            } else {
                if (!is_string($v) || strlen($v) > ($f['type'] === 'textarea' ? 16000 : 1200)) {
                    throw new \DomainException(
                        $f['label'] . __(': Invalid or excessively long value.', 'offerweave'), // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Api endpoint catch returns JSON; frontend/admin escape the message.
                    );
                }
                $v = trim($f['type'] === 'textarea' ? sanitize_textarea_field($v) : sanitize_text_field($v));
                if ($f['required'] && $v === '') {
                    throw new \DomainException(__('Please complete:', 'offerweave') . ' ' . $f['label']); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Api endpoint catch returns JSON; frontend/admin escape the message.
                }
                if ($v !== '' && $f['type'] === 'email' && !is_email($v)) {
                    throw new \DomainException(
                        __('Please enter a valid email address:', 'offerweave') . ' ' . $f['label'], // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Api endpoint catch returns JSON; frontend/admin escape the message.
                    );
                }
                if ($v !== '' && $f['type'] === 'select' && !in_array($v, $f['options'], true)) {
                    throw new \DomainException(__('Invalid selection:', 'offerweave') . ' ' . $f['label']); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Api endpoint catch returns JSON; frontend/admin escape the message.
                }
                if ($v !== '' && $f['type'] === 'number' && !preg_match('/^-?\d{1,9}(\.\d{1,4})?$/D', $v)) {
                    throw new \DomainException(
                        __('Please enter a valid number:', 'offerweave') . ' ' . $f['label'], // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Api endpoint catch returns JSON; frontend/admin escape the message.
                    );
                }
                if ($v !== '' && $f['type'] === 'date') {
                    if (
                        !preg_match('/^(\d{4})-(\d{2})-(\d{2})$/D', $v, $m) ||
                        !checkdate((int) $m[2], (int) $m[3], (int) $m[1])
                    ) {
                        throw new \DomainException(__('Invalid date:', 'offerweave') . ' ' . $f['label']); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Api endpoint catch returns JSON; frontend/admin escape the message.
                    }
                }
            }
            if ($f['id'] === $c['settings']['reply_field']) {
                $email = $v;
            }
            $rawValue = $f['type'] === 'checkbox' ? ($checked ? '1' : '0') : $v;
            if ($f['type'] === 'select' && $v !== '') {
                $index = array_search($v, $f['options'], true);
                $v = $f['option_labels'][$index] ?? $v;
            }
            $result[] = [
                'id' => $f['id'],
                'label' => $f['label'],
                'type' => $f['type'],
                'value' => $v,
                'raw_value' => $rawValue,
            ];
        }
        if (!$email || !is_email($email)) {
            throw new \DomainException(__('A valid reply address is required.', 'offerweave')); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Api endpoint catch returns JSON; frontend/admin escape the message.
        }
        return [$result, $email];
    }
    private static function sameSubmission(array $row, array $quote, array $fields): bool
    {
        $snapshot = json_decode($row['payload'], true);
        if (!is_array($snapshot) || !isset($snapshot['quote']['items'], $snapshot['fields'])) {
            return false;
        }
        if (
            I18n::snapshot($snapshot) !== I18n::current() ||
            Currency::snapshot($snapshot) !== ($quote['currency'] ?? 'EUR')
        ) {
            return false;
        }
        $values = static function ($list) {
            $map = [];
            foreach ($list as $field) {
                $value = $field['raw_value'] ?? $field['value'];
                if (($field['type'] ?? '') === 'checkbox') {
                    $value = in_array($value, ['Ja', 'Yes', '1', 1, true], true) ? '1' : '0';
                }
                $map[$field['id']] = $value;
            }
            ksort($map);
            return $map;
        };
        // Ignore associative-key order and normalize old persisted selection inputs.
        $config = Config::get();
        $inputs = static function ($items) use ($config) {
            return array_map(static function ($input) use ($config) {
                $input = Catalog::input($input, $config);
                ksort($input);
                return $input;
            }, array_column($items, 'input'));
        };
        return $inputs($snapshot['quote']['items']) === $inputs($quote['items']) &&
            $values($snapshot['fields']) === $values($fields);
    }
    public static function submit(\WP_REST_Request $r)
    {
        try {
            $b = self::body($r);
            $c = Translations::apply(Config::runtime());
            $s = $c['settings'];
            if (
                !is_string($b['nonce'] ?? null) ||
                (!wp_verify_nonce($b['nonce'], 'offerweave_submit') &&
                    !wp_verify_nonce($b['nonce'], 'cqb_submit'))
            ) {
                return self::error(
                    __('Please reload the form; your selection will be preserved.', 'offerweave'),
                    403,
                    'offerweave_nonce',
                );
            }
            $key = $b['request_key'] ?? '';
            if (!is_string($key) || !preg_match('/^[a-f0-9-]{32,64}$/D', $key)) {
                throw new \DomainException(__('Invalid request identifier.', 'offerweave'));
            }
            if (!is_array($b['items'] ?? null) || !$b['items']) {
                throw new \DomainException(__('Please select at least one service.', 'offerweave'));
            }
            if (($b['website'] ?? '') !== '') {
                throw new \DomainException(__('The request could not be verified.', 'offerweave'));
            }
            [$fields, $email] = self::fields($c, $b['fields'] ?? null);
            $now = time();
            $quote = Pricing::quote($c, $b['items'], $now);
            if (isset($quote['request_requirement']) && !$quote['request_requirement']['eligible']) {
                return self::error(
                    $quote['request_requirement']['message'],
                    400,
                    'offerweave_selection_requirements',
                );
            }
            $revision = Config::revision($c, $now);
            $fingerprint = hash('sha256', wp_json_encode([$quote, $fields, $revision]));
            $keyHash = Spam::sign('request|' . $key);
            $existing = Store::byKey($keyHash);
            if (is_wp_error($existing)) {
                return $existing;
            }
            if ($existing) {
                if (!self::sameSubmission($existing, $quote, $fields)) {
                    return self::error(
                        __(
                            'This request identifier has already been used for different details. Please recalculate your selection.',
                            'offerweave',
                        ),
                        409,
                        'offerweave_key_used',
                    );
                }
                return self::response([
                    'saved' => true,
                    'reference' => (int) $existing['id'],
                    'message' => self::savedMessage((int) $existing['id'], $s['success_text']),
                    'repeated' => true,
                ]);
            }
            Spam::validateForm($b['form_token'] ?? null, $s['min_seconds']);
            $expires = Pricing::integer(
                $b['expires'] ?? 0,
                1,
                PHP_INT_MAX,
                __('Price revision', 'offerweave'),
            );
            $expected = Spam::sign($revision . '|' . $expires . '|' . wp_json_encode($quote));
            if (
                $expires < time() ||
                $expires > time() + 1800 ||
                !is_string($b['signature'] ?? null) ||
                !hash_equals($expected, $b['signature'])
            ) {
                return self::error(
                    __(
                        'The prices or your selection have changed. Please review the updated summary and submit again.',
                        'offerweave',
                    ),
                    409,
                    'offerweave_price_changed',
                );
            }
            if (!Spam::limit('submit', $s['rate_limit'], 600)) {
                return self::error(__('Too many requests. Please try again later.', 'offerweave'), 429);
            }
            Spam::captcha($s, $b['captcha_token'] ?? '');
            $recipient = $s['notification_email'] ?: get_option('admin_email');
            if (!is_email($recipient)) {
                return self::error(
                    __(
                        'The request recipient has not been configured yet. Please contact the provider directly.',
                        'offerweave',
                    ),
                    503,
                );
            }
            $snapshot = [
                'schema_version' => 1,
                'currency' => Currency::config($c),
                'locale' => I18n::current(),
                'brand' => $s['brand'],
                'revision' => $revision,
                'quote' => $quote,
                'fields' => $fields,
                'created_at' => gmdate('Y-m-d H:i:s'),
                'email' => EmailConfig::snapshot($c['email']),
                'legal' => Legal::snapshot($c),
            ];
            $id = Store::create($keyHash, $fingerprint, $email, $recipient, $snapshot);
            if (!$id) {
                // A concurrent retry may have won the unique request-key insert.
                $existing = Store::byKey($keyHash);
                if (is_wp_error($existing)) {
                    return $existing;
                }
                if ($existing && self::sameSubmission($existing, $quote, $fields)) {
                    return self::response([
                        'saved' => true,
                        'reference' => (int) $existing['id'],
                        'message' => self::savedMessage((int) $existing['id'], $s['success_text']),
                        'repeated' => true,
                    ]);
                }
                return self::error(
                    __(
                        'The request could not be saved. Your selection will be preserved. Please try again.',
                        'offerweave',
                    ),
                    503,
                    'offerweave_storage',
                );
            }
            $adminMail = Mailer::send($id);
            $customerMail = Mailer::sendCustomer($id);
            return self::response(
                [
                    'saved' => true,
                    'reference' => $id,
                    'message' => self::savedMessage(
                        $id,
                        $s['success_text'],
                        is_wp_error($adminMail) || is_wp_error($customerMail),
                    ),
                ],
                201,
            );
        } catch (\DomainException $e) {
            return self::error($e->getMessage());
        }
    }
    public static function adminGet(): \WP_REST_Response
    {
        return self::response(Config::adminConfig());
    }
    public static function adminExport(): \WP_REST_Response
    {
        return self::response(Config::export());
    }
    public static function adminDefaults(): \WP_REST_Response
    {
        return self::response([
            'config' => Config::redact(
                Translations::defaults(EmailConfig::normalize(Design::normalize(Defaults::config()))),
            ),
            'translation_schema' => array_values(Translations::schema(Config::get())),
            'polylang_active' => function_exists('pll_current_language'),
            'edition' => Licensing::status(),
            'test_email' => wp_get_current_user()->user_email,
            'offer' => Defaults::offer(),
            'timezone' => wp_timezone_string(),
            'today' => wp_date('Y-m-d'),
        ]);
    }
    /** Draft calculation only: no request, signature, mail or saved configuration is created. */
    public static function adminPricePreview(\WP_REST_Request $r)
    {
        try {
            $body = self::body($r, 524288);
            if (!is_array($body['config'] ?? null) || !is_array($body['items'] ?? null)) {
                throw new \DomainException(__('Invalid preview configuration.', 'offerweave'));
            }
            $config = Config::preview($body['config']);
            // Preview an unpublished draft without making it publicly available.
            $ids = array_column($body['items'], 'offer_id');
            foreach ($config['offers'] as &$offer) {
                if (in_array($offer['id'], $ids, true)) {
                    $offer['enabled'] = true;
                    $offer['catalog_visible'] = true;
                }
            }
            unset($offer);
            return self::response(['quote' => Pricing::quote(Edition::effective($config), $body['items'])]);
        } catch (\DomainException $e) {
            return self::error($e);
        }
    }
    public static function adminValidate(\WP_REST_Request $r)
    {
        try {
            return self::response(Config::redact(Config::import(self::body($r, 524288))));
        } catch (\DomainException $e) {
            return self::error($e);
        }
    }
    public static function adminSave(\WP_REST_Request $r)
    {
        try {
            $config = Config::editable(self::body($r, 524288));
            return self::persistConfig($config);
        } catch (\DomainException $e) {
            return self::error($e);
        }
    }
    private static function persistConfig(array $config)
    {
        $stored = get_option(Config::OPTION);
        if (is_array($stored) && ($stored['schema_version'] ?? null) === 1) {
            add_option('offerweave_config_v1_backup', $stored, '', 'no');
        }
        update_option(Config::OPTION, $config, false);
        if (get_option(Config::OPTION) !== $config) {
            return self::error(__('The configuration could not be saved.', 'offerweave'), 503);
        }
        return self::response(['saved' => true, 'config' => Config::adminConfig()]);
    }
    public static function adminRequests(\WP_REST_Request $r)
    {
        $listing = Store::listing(
            sanitize_text_field($r->get_param('q') ?? ''),
            max(1, min(100000, (int) $r->get_param('page'))),
        );
        return is_wp_error($listing) ? $listing : self::response($listing);
    }
    public static function adminRequestEmail(\WP_REST_Request $r)
    {
        try {
            $body = self::body($r, 524288);
            $id = Pricing::integer(
                $body['request_id'] ?? null,
                1,
                PHP_INT_MAX,
                __('Request reference', 'offerweave'),
            );
            $row = Store::get($id);
            if (is_wp_error($row)) {
                return $row;
            }
            if (!$row) {
                return self::error(__('Request not found.', 'offerweave'), 404);
            }
            $snapshot = json_decode($row['payload'], true);
            $snapshot['created_at'] ??= $row['created_at'];
            return self::response(EmailView::render($id, $snapshot, $body['channel'] ?? 'customer'));
        } catch (\DomainException $error) {
            return self::error($error);
        }
    }
    private static function savedMessage(int $id, string $text, bool $mailReadFailed = false): string
    {
        $row = Store::get($id);
        // Saving was already confirmed. A later read failure must not turn this into
        // an unsaved response or imply a confirmed email outcome.
        if ($mailReadFailed || is_wp_error($row)) {
            return $text .
                ' ' .
                __(
                    'Your request remains saved. Email delivery could not be fully verified. Please contact the provider if needed.',
                    'offerweave',
                );
        }
        $status = $row['customer_mail_status'] ?? 'disabled';
        return $text .
            match ($status) {
                'sent' => ' ' .
                    __(
                        'Your price summary has been handed to the mail system. Please also check your spam folder.',
                        'offerweave',
                    ),
                'failed' => ' ' .
                    __(
                        'Your request remains saved. The confirmation email could not be sent at this time.',
                        'offerweave',
                    ),
                'review' => ' ' .
                    __('The price summary will be reviewed before the email is sent.', 'offerweave'),
                default => '',
            };
    }
    public static function adminRequest(\WP_REST_Request $r)
    {
        try {
            $id = (int) $r['id'];
            $b = self::body($r);
            $row = Store::get($id);
            if (is_wp_error($row)) {
                return $row;
            }
            if (!$row) {
                return self::error(__('Request not found.', 'offerweave'), 404);
            }
            switch ($b['action'] ?? '') {
                case 'delete':
                    return Store::delete($id)
                        ? self::response(['deleted' => true])
                        : self::error(__('Deletion failed.', 'offerweave'), 503);
                case 'resend':
                    $sent = Mailer::send($id);
                    if (is_wp_error($sent)) {
                        return $sent;
                    }
                    return $sent
                        ? self::response(['sent' => true])
                        : self::error(
                            __(
                                'Email delivery was not confirmed or is already being processed. Please check the email status.',
                                'offerweave',
                            ),
                            502,
                        );
                case 'customer_send':
                    $sent = Mailer::sendCustomer($id, true);
                    if (is_wp_error($sent)) {
                        return $sent;
                    }
                    return $sent
                        ? self::response(['sent' => true])
                        : self::error(
                            __(
                                'Customer email delivery was not confirmed or is already being processed. Please check its separate email status.',
                                'offerweave',
                            ),
                            502,
                        );
                case 'status':
                    if (!in_array($b['status'] ?? '', ['new', 'in_progress', 'offered', 'closed'], true)) {
                        throw new \DomainException(__('Invalid processing status.', 'offerweave'));
                    }
                    return Store::status($id, $b['status'])
                        ? self::response(['saved' => true])
                        : self::error(__('Saving failed.', 'offerweave'), 503);
            }
            throw new \DomainException(__('Unknown action.', 'offerweave'));
        } catch (\DomainException $e) {
            return self::error($e->getMessage());
        }
    }
}
