<?php
namespace OfferWeave;

/** Free package implementation. All functionality in this file is available without an upgrade. */
final class Edition
{
    public static function catalog(): array
    {
        static $catalog;
        return $catalog ??= json_decode(
            file_get_contents(__DIR__ . '/features.json'),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
    }
    public static function pro(): bool
    {
        return false;
    }
    public static function supports(string $feature): bool
    {
        foreach (self::catalog() as $row) {
            if ($row['id'] === $feature) {
                return $row['free'];
            }
        }
        return false;
    }
    public static function effective(array $config): array
    {
        return Config::project($config);
    }
}
