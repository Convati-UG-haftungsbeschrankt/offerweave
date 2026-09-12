<?php
namespace OfferWeave;

/** Free package implementation. All functionality in this file is available without an upgrade. */
final class FrontendState
{
    private static ?string $id = null;
    private static ?array $data = null;
    private static array $specs = [];
    private static array $discovered = [];
    private static array $failure = [];
    private const TTL = 7200;

    public static function cookieName(): string
    {
        return 'offerweave_selection_' . get_current_blog_id();
    }
    public static function register(): void
    {
        add_action('template_redirect', [self::class, 'prepare'], 0);
        add_filter('the_content', [self::class, 'discover'], 8);
    }
    public static function discover(string $content): string
    {
        $document = hash('sha256', $content);
        if (isset(self::$discovered[$document])) {
            return $content;
        }
        self::$discovered[$document] = true;
        if (!preg_match_all('/' . get_shortcode_regex() . '/s', $content, $matches, PREG_SET_ORDER)) {
            return $content;
        }
        foreach ($matches as $match) {
            $tag = $match[2];
            if ($tag !== 'offerweave' && !str_starts_with($tag, 'offerweave_')) {
                continue;
            }
            $atts = shortcode_parse_atts($match[3]);
            $spec = Frontend::specForTag($tag, is_array($atts) ? $atts : []);
            if ($spec) {
                self::$specs[] = $spec;
            }
        }
        return $content;
    }
    public static function scope(array $spec): string
    {
        unset($spec['id']);
        ksort($spec);
        return substr(hash('sha256', wp_json_encode($spec)), 0, 24);
    }
    private static function readId(): string
    {
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Only an exact 32-character hexadecimal identifier is accepted below.
        $cookie = wp_unslash($_COOKIE[self::cookieName()] ?? '');
        return is_string($cookie) && preg_match('/^[a-f0-9]{32}$/D', $cookie) ? $cookie : '';
    }
    private static function key(): string
    {
        return 'offerweave_selection_' . hash('sha256', self::id());
    }
    public static function id(): string
    {
        if (self::$id === null) {
            self::$id = self::readId();
        }
        return self::$id;
    }
    private static function establish(): void
    {
        if (self::id() || headers_sent()) {
            return;
        }
        self::$id = bin2hex(random_bytes(16));
        setcookie(self::cookieName(), self::$id, [
            'expires' => time() + self::TTL,
            'path' => '/',
            'secure' => is_ssl(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        $_COOKIE[self::cookieName()] = self::$id;
    }
    private static function privateResponse(): void
    {
        if (!defined('DONOTCACHEPAGE')) {
            // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- Established WordPress cache-plugin compatibility flag.
            define('DONOTCACHEPAGE', true);
        }
        if (!headers_sent()) {
            nocache_headers();
            header('Cache-Control: no-store, private');
        }
    }
    public static function prepare(): void
    {
        if (is_admin() || wp_doing_ajax()) {
            return;
        }
        $post = get_queried_object();
        if ($post instanceof \WP_Post) {
            self::discover($post->post_content);
        }
        $body = self::postedBody();
        if (self::$specs || self::id() || $body !== null) {
            self::privateResponse();
        }
        // Verify a POST against the session that issued its form before assigning a new cookie.
        if ($body !== null) {
            try {
                if (!is_array($body) || strlen(wp_json_encode($body)) > 65536) {
                    // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Plain-text domain error; the PHP renderer escapes HTML and JavaScript uses textContent at the final output.
                    throw new \DomainException(__('The request is too large.', 'offerweave'));
                }
                $spec = self::verify($body['offerweave_context'] ?? null);
                self::establish();
                $result = I18n::run(
                    $spec['locale'] === I18n::website() ? I18n::websiteLocale() : $spec['locale'],
                    static fn() => self::action($spec, $body),
                );
                self::save();
                if (empty($result['error']) && ($body['offerweave_enhanced'] ?? '') !== '1') {
                    wp_safe_redirect(
                        remove_query_arg(['offerweave_message'], self::pageUrl()) .
                            '#offerweave-' .
                            self::scope($spec),
                        303,
                    );
                    exit();
                }
                self::$failure = !empty($result['error']) ? $result : [];
            } catch (\DomainException $error) {
                self::$failure = ['error' => $error->getMessage()];
            }
        } elseif (self::$specs || self::id()) {
            self::establish();
        }
    }
    /** Old open forms are accepted only after proving they were issued by this plugin. */
    private static function postedBody(): ?array
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Presence detection only; new and legacy forms must pass verify() before any action or state mutation.
        if (!isset($_POST['offerweave_action']) && !isset($_POST['ow_action'], $_POST['ow_context'])) {
            return null;
        }
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Session HMAC, expiry and same-origin validation below (legacy) and in prepare() (new); action validates individual values.
        $body = wp_unslash($_POST);
        if (isset($body['offerweave_action'])) {
            return $body;
        }
        // Do not intercept another plugin's short field names or set private headers for them.
        if (strlen(wp_json_encode($body)) > 65536) {
            return null;
        }
        try {
            self::verify($body['ow_context']);
        } catch (\DomainException $error) {
            return null;
        }
        $normalized = $body;
        foreach (
            ['action', 'context', 'operation', 'inputs', 'shared', 'offer', 'line', 'enhanced', 'legacy']
            as $key
        ) {
            if (array_key_exists('ow_' . $key, $body)) {
                $normalized['offerweave_' . $key] = $body['ow_' . $key];
                unset($normalized['ow_' . $key]);
            }
        }
        foreach ($body as $key => $value) {
            if (str_starts_with((string) $key, 'cqb_field_')) {
                $normalized['offerweave_field_' . substr($key, 10)] = $value;
                unset($normalized[$key]);
            }
        }
        return $normalized;
    }
    public static function pageUrl(): string
    {
        $request =
            isset($_SERVER['REQUEST_URI']) && is_string($_SERVER['REQUEST_URI'])
                ? sanitize_url(wp_unslash($_SERVER['REQUEST_URI']))
                : '/';
        // Reconstruct on this site's origin; never accept a client-provided redirect destination.
        $origin = wp_parse_url(home_url());
        return ($origin['scheme'] ?? 'https') .
            '://' .
            $origin['host'] .
            (isset($origin['port']) ? ':' . $origin['port'] : '') .
            '/' .
            ltrim($request, '/');
    }
    public static function data(): array
    {
        if (self::$data === null) {
            $stored = self::id()
                ? StorageMigration::transient(
                    self::key(),
                    'ow_selection_' . hash('sha256', self::id()),
                    self::TTL,
                )
                : false;
            self::$data = is_array($stored) ? $stored : ['cart' => [], 'scopes' => [], 'flash' => ''];
        }
        return self::$data;
    }
    public static function state(array $spec): array
    {
        $data = self::data();
        return array_replace(
            ['cart' => $data['cart'], 'message' => $data['flash'] ?? '', 'failure' => self::$failure],
            $data['scopes'][self::scope($spec)] ?? [],
        );
    }
    public static function save(): void
    {
        if (self::id()) {
            set_transient(self::key(), self::data(), self::TTL);
        }
    }
    public static function seal(array $spec): string
    {
        $payload = base64_encode(wp_json_encode(['spec' => $spec, 'until' => time() + self::TTL]));
        return $payload . '.' . Spam::sign('view|' . self::id() . '|' . $payload);
    }
    public static function verify($token, bool $origin = true): array
    {
        if ($origin) {
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Origin is parsed and compared exactly to this site's scheme, host and effective port below.
            $source = wp_unslash($_SERVER['HTTP_ORIGIN'] ?? ($_SERVER['HTTP_REFERER'] ?? ''));
            $parts = is_string($source) ? wp_parse_url($source) : false;
            $site = wp_parse_url(home_url());
            if (
                !$parts ||
                ($parts['scheme'] ?? '') !== $site['scheme'] ||
                ($parts['host'] ?? '') !== $site['host'] ||
                ($parts['port'] ?? (($parts['scheme'] ?? '') === 'https' ? 443 : 80)) !==
                    ($site['port'] ?? ($site['scheme'] === 'https' ? 443 : 80))
            ) {
                // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Plain-text domain error; the PHP renderer escapes HTML and JavaScript uses textContent at the final output.
                throw new \DomainException(__('The request could not be verified.', 'offerweave'));
            }
        }
        if (!is_string($token) || strlen($token) > 8192) {
            throw new \DomainException(
                // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Plain-text domain error; the PHP renderer escapes HTML and JavaScript uses textContent at the final output.
                __('Please reload the form; your selection will be preserved.', 'offerweave'),
            );
        }
        $parts = explode('.', $token);
        if (
            count($parts) !== 2 ||
            !hash_equals(Spam::sign('view|' . self::id() . '|' . $parts[0]), $parts[1])
        ) {
            throw new \DomainException(
                // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Plain-text domain error; the PHP renderer escapes HTML and JavaScript uses textContent at the final output.
                __('Please reload the form; your selection will be preserved.', 'offerweave'),
            );
        }
        $value = json_decode(base64_decode($parts[0], true) ?: '', true);
        if (
            !is_array($value) ||
            !is_array($value['spec'] ?? null) ||
            !is_int($value['until'] ?? null) ||
            $value['until'] < time() ||
            $value['until'] > time() + self::TTL
        ) {
            throw new \DomainException(
                // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Plain-text domain error; the PHP renderer escapes HTML and JavaScript uses textContent at the final output.
                __('The form has expired. Please reload; your selection will be preserved.', 'offerweave'),
            );
        }
        return $value['spec'];
    }
    public static function formOpen(array $spec, string $id, string $class = ''): string
    {
        return '<form id="' .
            esc_attr($id) .
            '" class="ow-server-form ' .
            esc_attr($class) .
            '" method="post" action="' .
            esc_url(self::pageUrl()) .
            '"><input type="hidden" name="offerweave_context" value="' .
            esc_attr(self::seal($spec)) .
            '"><input type="hidden" name="offerweave_operation" value="' .
            bin2hex(random_bytes(16)) .
            '">';
    }
    private static function integer($value, int $min, int $max): int
    {
        return Pricing::integer($value, $min, $max, __('Scope', 'offerweave'));
    }
    public static function parseInputs(array $body, array $allowed): array
    {
        $inputs = $body['offerweave_inputs'] ?? [];
        if (!is_array($inputs) || count($inputs) > 200) {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Caught in FrontendState::prepare; FrontendView uses esc_html and the enhanced error fallback uses textContent.
            throw new \DomainException(__('Invalid selection.', 'offerweave'));
        }
        $out = [];
        foreach ($inputs as $id => $raw) {
            if (!is_string($id) || !in_array($id, $allowed, true) || !is_array($raw)) {
                // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Caught in FrontendState::prepare; FrontendView uses esc_html and the enhanced error fallback uses textContent.
                throw new \DomainException(__('Invalid selection.', 'offerweave'));
            }
            $out[$id] = [];
            if (isset($raw['count'])) {
                $out[$id]['count'] = self::integer($raw['count'], 1, 100000);
            }
        }
        return $out;
    }
    public static function action(array $spec, array $body): array
    {
        if (!Spam::limit('selection', 180, 60)) {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Plain-text domain error; the PHP renderer escapes HTML and JavaScript uses textContent at the final output.
            throw new \DomainException(__('Too many requests. Please try again later.', 'offerweave'));
        }
        if ((int) get_option('ow_selection_lock_' . hash('sha256', self::id())) >= time()) {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Caught in FrontendState::prepare; FrontendView uses esc_html and the enhanced error fallback uses textContent.
            throw new \DomainException(__('The selection changed. Please try again.', 'offerweave'));
        }
        $lock = 'offerweave_selection_lock_' . hash('sha256', self::id());
        $deadline = time() + 90;
        if (!add_option($lock, $deadline, '', false)) {
            $old = (int) get_option($lock);
            if ($old < time()) {
                delete_option($lock);
            }
            if ($old >= time() || !add_option($lock, $deadline, '', false)) {
                // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Plain-text domain error; the PHP renderer escapes HTML and JavaScript uses textContent at the final output.
                throw new \DomainException(__('The selection changed. Please try again.', 'offerweave'));
            }
        }
        try {
            self::$data = null;
            self::data();
            $result = self::applyAction($spec, $body);
            if (empty($result['error']) && ($body['offerweave_action'] ?? '') !== 'update') {
                self::$data['completed'][$body['offerweave_operation']] = [
                    'proof' => self::operationProof($body),
                    'result' => $result,
                ];
                self::$data['completed'] = array_slice(self::$data['completed'], -64, null, true);
            }
            self::save();
            return $result;
        } finally {
            delete_option($lock);
        }
    }
    private static function operationProof(array $body): string
    {
        unset($body['offerweave_enhanced']);
        ksort($body);
        return hash('sha256', wp_json_encode($body));
    }
    private static function applyAction(array $spec, array $body): array
    {
        $config = Translations::apply(Config::runtime());
        $action = $body['offerweave_action'] ?? '';
        if (!empty($spec['preview']) || ($spec['actions'] === 'false' && $action !== 'update')) {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Plain-text domain error; the PHP renderer escapes HTML and JavaScript uses textContent at the final output.
            throw new \DomainException(__('Actions are disabled in the design preview.', 'offerweave'));
        }
        $scope = self::scope($spec);
        $operation = $body['offerweave_operation'] ?? '';
        if (!is_string($operation) || !preg_match('/^[a-f0-9]{32}$/D', $operation)) {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Plain-text domain error; the PHP renderer escapes HTML and JavaScript uses textContent at the final output.
            throw new \DomainException(__('The request could not be verified.', 'offerweave'));
        }
        if (isset(self::$data['completed'][$operation])) {
            $completed = self::$data['completed'][$operation];
            if (!hash_equals($completed['proof'], self::operationProof($body))) {
                throw new \DomainException(
                    // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Plain-text domain error; the PHP renderer escapes HTML and JavaScript uses textContent at the final output.
                    __('Please reload the form; your selection will be preserved.', 'offerweave'),
                );
            }
            return $completed['result'];
        }
        $state = self::state($spec);
        self::$data['flash'] = '';
        if ($action === 'import') {
            if (!empty(self::$data['initialized']) || self::$data['cart']) {
                return [];
            }
            $items = json_decode(
                is_string($body['offerweave_legacy'] ?? null) ? $body['offerweave_legacy'] : '',
                true,
            );
            if (!is_array($items) || !array_is_list($items) || count($items) > 30) {
                // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Plain-text domain error; the PHP renderer escapes HTML and JavaScript uses textContent at the final output.
                throw new \DomainException(__('Invalid selection.', 'offerweave'));
            }
            $skipped = 0;
            foreach ($items as $raw) {
                try {
                    if (!is_array($raw)) {
                        throw new \DomainException('Invalid item');
                    }
                    $raw['line_id'] = bin2hex(random_bytes(16));
                    $quote = Pricing::quote($config, [$raw]);
                    self::$data['cart'][] = $quote['items'][0]['input'];
                } catch (\DomainException $error) {
                    ++$skipped;
                }
            }
            self::$data['initialized'] = true;
            if ($skipped) {
                self::$data['flash'] = __(
                    'Unavailable items from your previous selection were not imported. Please review your selection.',
                    'offerweave',
                );
            }
            return [];
        }
        self::$data['initialized'] = true;
        if (in_array($action, ['update', 'add', 'save-line'], true)) {
            $public = Config::publicConfig($config);
            $allowed = array_column(
                array_filter(
                    $public['offers'],
                    static fn($o) => (!$spec['offer'] || $o['id'] === $spec['offer']) &&
                        (!$spec['category'] || $o['category'] === $spec['category']),
                ),
                'id',
            );
            if ($action === 'save-line') {
                $allowed = array_column($public['offers'], 'id');
            }
            $inputs = self::parseInputs($body, $allowed);
            $state['inputs'] = array_replace($state['inputs'] ?? [], $inputs);
            if (isset($body['offerweave_shared'])) {
                $shared = $body['offerweave_shared'];
                if (!is_array($shared)) {
                    // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Plain-text domain error; the PHP renderer escapes HTML and JavaScript uses textContent at the final output.
                    throw new \DomainException(__('Invalid selection.', 'offerweave'));
                }
                if (isset($shared['count'])) {
                    $state['shared']['count'] = self::integer($shared['count'], 1, 100000);
                }
            }
            self::$data['scopes'][$scope] = array_intersect_key($state, array_flip(['inputs', 'shared']));
            self::$data['scopes'] = array_slice(self::$data['scopes'], -32, null, true);
            while (
                count(self::$data['scopes']) > 1 &&
                strlen(wp_json_encode(self::$data['scopes'])) > 262144
            ) {
                array_shift(self::$data['scopes']);
            }
            if ($action === 'update') {
                return [];
            }
            if ($spec['actions'] === 'false') {
                // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Plain-text domain error; the PHP renderer escapes HTML and JavaScript uses textContent at the final output.
                throw new \DomainException(__('Actions are disabled in the design preview.', 'offerweave'));
            }
            $id = $body['offerweave_offer'] ?? '';
            $offer = null;
            foreach ($public['offers'] as $o) {
                if ($o['id'] === $id) {
                    $offer = $o;
                }
            }
            if (!$offer || !in_array($id, $allowed, true)) {
                // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Plain-text domain error; the PHP renderer escapes HTML and JavaScript uses textContent at the final output.
                throw new \DomainException(__('This offer is currently unavailable.', 'offerweave'));
            }
            if ($action === 'save-line') {
                $config['settings']['input_scope'] = 'offer';
                $spec = array_replace($spec, ['view' => 'catalog', 'offer' => $id, 'category' => '']);
            }
            $view = new FrontendView($config, $spec, $state);
            $input = $view->input($offer);
            $old = null;
            if ($action === 'save-line') {
                foreach (self::$data['cart'] as $index => $item) {
                    if ($item['line_id'] === ($body['offerweave_line'] ?? '')) {
                        $old = $index;
                    }
                }
                if ($old === null) {
                    // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Plain-text domain error; the PHP renderer escapes HTML and JavaScript uses textContent at the final output.
                    throw new \DomainException(__('The selection changed. Please try again.', 'offerweave'));
                }
                $input['line_id'] = self::$data['cart'][$old]['line_id'];
            }
            if ($old === null && count(self::$data['cart']) >= 30) {
                // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Plain-text domain error; the PHP renderer escapes HTML and JavaScript uses textContent at the final output.
                throw new \DomainException(__('Your selection may contain at most 30 items.', 'offerweave'));
            }
            $input['line_id'] =
                $old !== null ? self::$data['cart'][$old]['line_id'] : bin2hex(random_bytes(16));
            $quote = Pricing::quote($config, [$input]);
            if ($old === null) {
                self::$data['cart'][] = $quote['items'][0]['input'];
            } else {
                self::$data['cart'][$old] = $quote['items'][0]['input'];
            }
            self::$data['flash'] = $offer['name'] . ' ' . __('was added to your selection.', 'offerweave');
            return [];
        }
        if ($action === 'remove') {
            self::$data['cart'] = array_values(
                array_filter(
                    self::$data['cart'],
                    static fn($item) => $item['line_id'] !== ($body['offerweave_line'] ?? ''),
                ),
            );
            return [];
        }
        if ($action === 'submit') {
            $fields = [];
            foreach (Config::publicConfig($config)['fields'] as $field) {
                $value = $body['offerweave_field_' . $field['id']] ?? '';
                $fields[$field['id']] = $field['type'] === 'checkbox' ? $value !== '' : $value;
            }
            $request = new \WP_REST_Request('POST', '/' . Api::NS . '/requests');
            $request->set_header('Content-Type', 'application/json');
            $request->set_body(
                wp_json_encode([
                    'items' => self::$data['cart'],
                    'fields' => $fields,
                    'request_key' => $body['request_key'] ?? '',
                    'nonce' => $body['nonce'] ?? '',
                    'form_token' => $body['form_token'] ?? '',
                    'signature' => $body['signature'] ?? '',
                    'expires' => $body['expires'] ?? 0,
                    'captcha_token' => $body['captcha_token'] ?? '',
                    'website' => $body['website'] ?? '',
                ]),
            );
            $response = Api::submit($request);
            if (is_wp_error($response)) {
                return ['error' => $response->get_error_message(), 'fields' => $fields];
            }
            $result = $response->get_data();
            if (!empty($result['saved'])) {
                self::$data['cart'] = [];
                self::$data['flash'] =
                    $result['message'] . ' ' . __('Reference: #', 'offerweave') . $result['reference'];
            }
            return $result;
        }
        // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Plain-text domain error; the PHP renderer escapes HTML and JavaScript uses textContent at the final output.
        throw new \DomainException(__('Invalid selection.', 'offerweave'));
    }
}
