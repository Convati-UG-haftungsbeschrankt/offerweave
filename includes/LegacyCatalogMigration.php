<?php
namespace OfferWeave;

/** Structural v1 data migration only: no pricing, designer or extension execution. */
final class LegacyCatalogMigration
{
    public static function convert(array $config): array
    {
        if (($config['schema_version'] ?? null) === 2) {
            return $config;
        }
        if (($config['schema_version'] ?? null) !== 1) {
            throw new \DomainException(__('This configuration format is not supported.', 'offerweave')); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Api JSON or guarded stored-data consumers; no direct exception output.
        }
        $offers = $config['offers'] ?? [];
        $old = $config['modules'] ?? [];
        if (
            !is_array($offers) ||
            !array_is_list($offers) ||
            count($offers) > 100 ||
            !is_array($old) ||
            !array_is_list($old) ||
            count($old) > 50
        ) {
            throw new \DomainException(__('Invalid legacy offer catalog.', 'offerweave')); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Api JSON or guarded stored-data consumers; no direct exception output.
        }
        $used = [];
        foreach ($offers as $o) {
            if (!is_array($o) || !is_string($o['id'] ?? null)) {
                throw new \DomainException(__('Invalid legacy offer.', 'offerweave')); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Api JSON or guarded stored-data consumers; no direct exception output.
            }
            $used[$o['id']] = true;
        }
        $map = [];
        foreach ($old as $m) {
            if (
                !is_array($m) ||
                !is_string($m['id'] ?? null) ||
                !preg_match('/^[a-z][a-z0-9_-]{0,63}$/D', $m['id']) ||
                isset($map[$m['id']])
            ) {
                throw new \DomainException(__('Invalid or duplicate legacy module ID.', 'offerweave')); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Api JSON or guarded stored-data consumers; no direct exception output.
            }
            $id = $m['id'];
            $n = 1;
            while (isset($used[$id])) {
                $id = 'component-' . substr($m['id'], 0, 48) . '-' . $n++;
            }
            $map[$m['id']] = $id;
            $used[$id] = true;
        }
        foreach ($offers as &$o) {
            $ids = $o['module_ids'] ?? [];
            if (!is_array($ids) || !array_is_list($ids) || count($ids) > 50) {
                throw new \DomainException(__('Invalid legacy package assignment.', 'offerweave')); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Api JSON or guarded stored-data consumers; no direct exception output.
            }
            $o['component_ids'] = array_map(static function ($id) use ($map) {
                if (!is_string($id) || !isset($map[$id])) {
                    throw new \DomainException(
                        __('A legacy package component is missing from the catalog.', 'offerweave'), // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Api JSON or guarded stored-data consumers; no direct exception output.
                    );
                }
                return $map[$id];
            }, $ids);
            $o['min_components'] = $o['min_modules'] ?? 2;
            $o['selection_mode'] = 'group';
            $o['catalog_visible'] = true;
            $o['category_label'] =
                [
                    'phishing' => 'Phishing',
                    'workshops' => 'Workshops',
                    'module' => 'Module',
                    'angebote' => 'Angebote',
                ][$o['category'] ?? ''] ?? '';
            if (($o['kind'] ?? '') === 'modules') {
                $o['kind'] = 'selection';
            }
            unset($o['module_ids'], $o['min_modules']);
        }
        unset($o);
        foreach ($old as $m) {
            $offers[] = array_replace(Defaults::offer(), [
                'id' => $map[$m['id']],
                'name' => $m['name'] ?? '',
                'description' => $m['description'] ?? '',
                'duration_minutes' => Pricing::integer(
                    $m['minutes'] ?? 0,
                    1,
                    1440,
                    __('Previous duration', 'offerweave'),
                ),
                'base_cents' => $m['cents'] ?? 0,
                'enabled' => $m['enabled'] ?? true,
                'category' => 'angebote',
                'category_label' => 'Angebote',
                'catalog_visible' => false,
                'kind' => 'fixed',
                'period' => 'once',
            ]);
        }
        $config['schema_version'] = 2;
        $config['offers'] = $offers;
        $config['legacy_item_map'] = $map;
        $config['promotions'] ??= [];
        unset($config['modules']);
        return $config;
    }
}
