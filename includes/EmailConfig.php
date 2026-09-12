<?php
namespace OfferWeave;

/** Free package implementation. All functionality in this file is available without an upgrade. */
final class EmailConfig
{
    public static function defaults(): array
    {
        return ['mode' => 'off', 'from_name' => '', 'from_email' => '', 'reply_to' => ''];
    }
    public static function normalize(array $config): array
    {
        $config['email'] = array_replace(self::defaults(), $config['email'] ?? []);
        return $config;
    }
    private static function text($v, int $max, bool $header = false): string
    {
        if (!is_string($v) || strlen($v) > $max) {
            throw new \DomainException(__('An email text is invalid or too long.', 'offerweave')); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Config validation reaches Api JSON; admin escapes message/issue text.
        }
        if ($header && preg_match('/[\r\n\x00-\x1f\x7f]/', $v)) {
            throw new \DomainException(__('Email headers must not contain line breaks.', 'offerweave')); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Config validation reaches Api JSON; admin escapes message/issue text.
        }
        return $header ? sanitize_text_field($v) : sanitize_textarea_field($v);
    }
    public static function validate($raw, array $fields, array $previous = []): array
    {
        if (!is_array($raw)) {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Validation travels through Api::error JSON; the admin notice and issue renderers escape text at output.
            throw new \DomainException(__('Invalid email settings.', 'offerweave'));
        }
        $out = self::defaults();
        $out['mode'] = $raw['mode'] ?? 'off';
        if (!in_array($out['mode'], ['off', 'automatic', 'complete', 'manual'], true)) {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Validation travels through Api::error JSON; the admin notice and issue renderers escape text at output.
            throw new \DomainException(__('Invalid email settings.', 'offerweave'));
        }
        foreach (['from_name', 'from_email', 'reply_to'] as $key) {
            $out[$key] = self::text($raw[$key] ?? '', 190, true);
            if ($key !== 'from_name' && $out[$key] !== '' && !is_email($out[$key])) {
                // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Validation travels through Api::error JSON; the admin notice and issue renderers escape text at output.
                throw new \DomainException(__('Invalid email recipient.', 'offerweave'));
            }
        }
        return array_replace($previous, $out);
    }
    public static function snapshot(array $email): array
    {
        return array_replace(self::defaults(), array_intersect_key($email, self::defaults()));
    }
    public static function redact(array $email): array
    {
        $email['smtp_password_set'] = !empty($email['smtp_password_encrypted']);
        unset($email['smtp_password_encrypted']);
        $email['smtp_password'] = '';
        return $email;
    }
}
