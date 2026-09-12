<?php
namespace OfferWeave;

final class OfferImage
{
    private const MIMES = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
        'image/avif',
        'image/svg+xml',
    ];

    public static function defaults(): array
    {
        return ['attachment_id' => 0, 'url' => '', 'alt' => '', 'decorative' => false];
    }

    public static function iconDefaults(): array
    {
        return array_replace(self::defaults(), ['decorative' => true]);
    }

    public static function validateIcon($raw): array
    {
        $image = self::validate($raw);
        // The adjacent key fact provides the accessible text.
        $image['alt'] = '';
        $image['decorative'] = true;
        return $image;
    }

    public static function publicIcons(array $offer): array
    {
        return array_map(
            static fn($icon) => self::publicImage(['image' => $icon, 'name' => ''], 'thumbnail'),
            $offer['fact_icons'] ?? [],
        );
    }

    public static function exportIcons(array $offer): array
    {
        return array_map(
            static fn($image) => array_replace(self::iconDefaults(), ['url' => $image['url']]),
            self::publicIcons($offer),
        );
    }

    private static function url($value): string
    {
        if (!is_string($value) || strlen($value) > 8000 || preg_match('/[\x00-\x20<>"\\\\]/', $value)) {
            throw new \DomainException(
                __('Please enter a valid direct image address without spaces.', 'offerweave'), // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Config::validate propagates image errors to Api JSON; admin escapes text.
            );
        }
        if ($value === '') {
            return '';
        }
        $parts = wp_parse_url($value);
        if (
            !$parts ||
            !in_array(strtolower($parts['scheme'] ?? ''), ['https', 'http'], true) ||
            empty($parts['host']) ||
            isset($parts['user']) ||
            isset($parts['pass']) ||
            preg_match('/\.svgz$/i', rawurldecode($parts['path'] ?? ''))
        ) {
            throw new \DomainException(
                // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Config::validate propagates image errors to Api JSON; admin escapes text.
                __(
                    'Please use an HTTP/HTTPS image address for JPEG, PNG, GIF, WebP, AVIF or SVG.',
                    'offerweave',
                ),
            );
        }
        $safe = esc_url_raw($value, ['https', 'http']);
        if (!$safe) {
            throw new \DomainException(__('The image address is invalid.', 'offerweave')); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Config::validate propagates image errors to Api JSON; admin escapes text.
        }
        return $safe;
    }

    private static function media(int $id): bool
    {
        return get_post_type($id) === 'attachment' &&
            get_post_status($id) !== 'trash' &&
            in_array(get_post_mime_type($id), self::MIMES, true);
    }

    private static function mediaSource(int $id, string $size): array|false
    {
        if (!self::media($id)) {
            return false;
        }
        if (get_post_mime_type($id) === 'image/svg+xml') {
            // WordPress owns upload permissions and SVG sanitization. SVGs need no raster derivatives.
            $url = wp_get_attachment_url($id);
            if (!$url) {
                return false;
            }
            $metadata = wp_get_attachment_metadata($id);
            return [$url, max(0, (int) ($metadata['width'] ?? 0)), max(0, (int) ($metadata['height'] ?? 0))];
        }
        return wp_get_attachment_image_src($id, $size);
    }

    public static function validate($raw): array
    {
        if (!is_array($raw)) {
            throw new \DomainException(__('Invalid offer image settings.', 'offerweave')); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Config::validate propagates image errors to Api JSON; admin escapes text.
        }
        $d = array_replace(self::defaults(), $raw);
        $id = Pricing::integer($d['attachment_id'], 0, PHP_INT_MAX, __('Media library ID', 'offerweave'));
        if (!is_bool($d['decorative']) || !is_string($d['alt']) || strlen($d['alt']) > 1600) {
            throw new \DomainException(
                __('Please use valid alternative text and an image switch value.', 'offerweave'), // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Config::validate propagates image errors to Api JSON; admin escapes text.
            );
        }
        $url = self::url($d['url']);
        if ($id) {
            if (!($source = self::mediaSource($id, 'large'))) {
                throw new \DomainException(
                    // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Config::validate propagates image errors to Api JSON; admin escapes text.
                    __(
                        'The selected media library image is missing or has an unsupported format. Please select it again or remove it from the offer.',
                        'offerweave',
                    ),
                );
            }
            $url = self::url($source[0]);
        }
        return [
            'attachment_id' => $id,
            'url' => $url,
            'alt' => sanitize_text_field($d['alt']),
            'decorative' => $d['decorative'],
        ];
    }

    public static function publicImage(array $offer, string $size = 'large'): array
    {
        $d = array_replace(self::defaults(), $offer['image'] ?? []);
        $result = ['url' => '', 'alt' => '', 'width' => 0, 'height' => 0, 'srcset' => ''];
        $mediaAlt = '';
        if ($d['attachment_id']) {
            if (!($source = self::mediaSource((int) $d['attachment_id'], $size))) {
                return $result;
            }
            $result['url'] = $source[0];
            $result['width'] = (int) $source[1];
            $result['height'] = (int) $source[2];
            $result['srcset'] =
                get_post_mime_type((int) $d['attachment_id']) === 'image/svg+xml'
                    ? ''
                    : (wp_get_attachment_image_srcset((int) $d['attachment_id'], $size) ?:
                    '');
            $mediaAlt = get_post_meta((int) $d['attachment_id'], '_wp_attachment_image_alt', true);
        } else {
            $result['url'] = $d['url'];
        }
        if ($result['url'] !== '') {
            $result['alt'] = $d['decorative'] ? '' : ($d['alt'] ?: ($mediaAlt ?: $offer['name']));
        }
        return $result;
    }

    public static function export(array $offer): array
    {
        $image = self::publicImage($offer);
        return [
            'attachment_id' => 0,
            'url' => $image['url'],
            'alt' => $image['alt'],
            'decorative' => $offer['image']['decorative'] ?? false,
        ];
    }
}
