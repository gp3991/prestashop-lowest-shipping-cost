<?php
/**
 * Lowest Shipping Cost - PrestaShop 9 module.
 *
 * @author    Recruitment Task
 * @copyright 2026 Recruitment Task
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */
declare(strict_types=1);

namespace PrestaShop\Module\LowestShippingCost\Configuration;

use Configuration;

/**
 * Reads and persists the module settings. Injected into the Symfony
 * configuration controller, it keeps the controller thin and isolates all
 * Configuration access (and cache invalidation) in one place.
 *
 * Keys mirror the LSC_* constants on the legacy module class on purpose: the
 * same values are read by the front-office hook.
 */
class ConfigurationData
{
    public const KEY_DEST_MODE = 'LSC_DEST_MODE';
    public const KEY_ALL_GROUPS = 'LSC_ALL_GROUPS';
    public const KEY_SKIP_EXTERNAL = 'LSC_SKIP_EXTERNAL';
    public const KEY_SHOW_DETAILS = 'LSC_SHOW_DETAILS';
    public const KEY_CACHE_VERSION = 'LSC_CACHE_VERSION';

    public const DEST_VISITOR = 'visitor';
    public const DEST_DEFAULT_COUNTRY = 'default';
    public const DEST_GLOBAL = 'global';

    /**
     * @return array<string, mixed> form data
     */
    public function getConfiguration(): array
    {
        return [
            'dest_mode' => $this->sanitizeDestMode((string) Configuration::get(self::KEY_DEST_MODE)),
            'all_groups' => (int) Configuration::get(self::KEY_ALL_GROUPS),
            'skip_external' => (int) Configuration::get(self::KEY_SKIP_EXTERNAL),
            'show_details' => (int) Configuration::get(self::KEY_SHOW_DETAILS),
        ];
    }

    /**
     * @param array<string, mixed> $data form data
     */
    public function saveConfiguration(array $data): void
    {
        Configuration::updateValue(self::KEY_DEST_MODE, $this->sanitizeDestMode((string) ($data['dest_mode'] ?? '')));
        Configuration::updateValue(self::KEY_ALL_GROUPS, (int) ($data['all_groups'] ?? 0));
        Configuration::updateValue(self::KEY_SKIP_EXTERNAL, (int) ($data['skip_external'] ?? 0));
        Configuration::updateValue(self::KEY_SHOW_DETAILS, (int) ($data['show_details'] ?? 0));

        // Any settings change must invalidate the front-office result cache.
        Configuration::updateValue(
            self::KEY_CACHE_VERSION,
            (int) Configuration::get(self::KEY_CACHE_VERSION) + 1
        );
    }

    private function sanitizeDestMode(string $value): string
    {
        $allowed = [self::DEST_VISITOR, self::DEST_DEFAULT_COUNTRY, self::DEST_GLOBAL];

        return in_array($value, $allowed, true) ? $value : self::DEST_VISITOR;
    }
}
