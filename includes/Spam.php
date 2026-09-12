<?php
namespace OfferWeave;

final class Spam
{
    public static function sign(string $value): string
    {
        // Versioned signing context remains stable for existing forms, request keys and encrypted references.
        return hash_hmac('sha256', $value, wp_salt('auth') . '|cqb|' . get_current_blog_id());
    }
    public static function formToken(): string
    {
        $data = time() . '.' . bin2hex(random_bytes(12));
        return $data . '.' . self::sign($data);
    }
    public static function validateForm($token, int $minimum): void
    {
        if (
            !is_string($token) ||
            !preg_match('/^(\d+)\.([a-f0-9]{24})\.([a-f0-9]{64})$/D', $token, $m) ||
            !hash_equals(self::sign($m[1] . '.' . $m[2]), $m[3])
        ) {
            throw new \DomainException(__('Please reload the request form.', 'offerweave')); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Api::submit catches this message for JSON; frontend uses textContent.
        }
        $elapsed = time() - (int) $m[1];
        if ($elapsed < $minimum) {
            throw new \DomainException(
                __('Please check your details and submit the request again in a few seconds.', 'offerweave'), // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Api::submit catches this message for JSON; frontend uses textContent.
            );
        }
        if ($elapsed > 7200) {
            throw new \DomainException(
                __('The form has expired. Please reload; your selection will be preserved.', 'offerweave'), // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Api::submit catches this message for JSON; frontend uses textContent.
            );
        }
    }
    public static function limit(string $scope, int $max, int $seconds): bool
    {
        // Never trust user-supplied forwarded IP headers. Only a salted, short-lived identifier is stored.
        $address = isset($_SERVER['REMOTE_ADDR'])
            ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR']))
            : '';
        $address = filter_var($address, FILTER_VALIDATE_IP) !== false ? $address : 'local';
        $key = 'offerweave_rl_' . substr(self::sign($scope . '|' . $address), 0, 40);
        $oldScope = str_starts_with($scope, 'offerweave_customer_')
            ? 'ow_customer_' . substr($scope, strlen('offerweave_customer_'))
            : $scope;
        $oldKey = 'cqb_rl_' . substr(self::sign($oldScope . '|' . $address), 0, 40);
        $state = StorageMigration::transient($key, $oldKey, $seconds);
        if (!is_array($state)) {
            $state = ['count' => 0, 'until' => time() + $seconds];
        }
        if ($state['count'] >= $max) {
            return false;
        }
        ++$state['count'];
        set_transient($key, $state, max(1, $state['until'] - time()));
        return true;
    }
    public static function captcha(array $s, $token): void
    {
        if ($s['captcha_provider'] === 'none') {
            return;
        }
        if (!is_string($token) || $token === '' || strlen($token) > 10000) {
            throw new \DomainException(__('Please complete the CAPTCHA check.', 'offerweave')); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Api::submit catches this message for JSON; frontend uses textContent.
        }
        $turnstile = $s['captcha_provider'] === 'turnstile';
        $url = $turnstile
            ? 'https://challenges.cloudflare.com/turnstile/v0/siteverify' // phpcs:ignore PluginCheck.CodeAnalysis.Offloading.OffloadedContent -- Optional Turnstile token-verification API, not an offloaded asset; service disclosed in readme.txt.
            : 'https://api.hcaptcha.com/siteverify';
        $body = ['secret' => $s['captcha_secret'], 'response' => $token];
        if (!$turnstile) {
            $body['sitekey'] = $s['captcha_site_key'];
        }
        $result = wp_remote_post($url, ['timeout' => 10, 'body' => $body]);
        if (is_wp_error($result) || wp_remote_retrieve_response_code($result) !== 200) {
            throw new \DomainException(
                __('The CAPTCHA service is currently unavailable. Please try again.', 'offerweave'), // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Api::submit catches this message for JSON; frontend uses textContent.
            );
        }
        $data = json_decode(wp_remote_retrieve_body($result), true);
        $host = wp_parse_url(home_url(), PHP_URL_HOST);
        if (
            !is_array($data) ||
            empty($data['success']) ||
            ($data['hostname'] ?? '') !== $host ||
            ($turnstile && !in_array($data['action'] ?? '', ['offerweave_request', 'cqb_request'], true))
        ) {
            throw new \DomainException(
                __('The CAPTCHA check failed or expired. Please confirm again.', 'offerweave'), // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Api::submit catches this message for JSON; frontend uses textContent.
            );
        }
    }
}
