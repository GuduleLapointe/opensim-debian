<?php

declare(strict_types=1);

namespace OpenSim\Installer\Grid;

use OpenSim\Installer\Ini;

/**
 * The roles of a region in the grid, given by Robust to a region when it
 * registers, from a key of [GridService]: Region_<Name> = "DefaultRegion, ...".
 */
final class RegionFlags
{
    public const DEFAULT = 'DefaultRegion';
    public const DEFAULT_HG = 'DefaultHGRegion';
    public const FALLBACK = 'FallbackRegion';

    /**
     * The roles a grid can give, in the order they are offered: the default
     * region, the one of the visitors of other grids (Hypergrid only), the
     * one visitors fall back to.
     *
     * @return list<string>
     */
    public static function roles(bool $hypergrid): array
    {
        return $hypergrid ? [self::DEFAULT, self::DEFAULT_HG, self::FALLBACK] : [self::DEFAULT, self::FALLBACK];
    }

    /**
     * The roles the regions of a Robust config have, the commented examples left out.
     *
     * @return list<string>
     */
    public static function found(string $text): array
    {
        $found = [];
        if (preg_match_all('/^\s*Region_[^\s=]+\s*=\s*"?([^"\r\n]*)"?\s*$/m', $text, $m)) {
            foreach ($m[1] as $value) {
                foreach (explode(',', $value) as $flag) {
                    $found[trim($flag)] = true;
                }
            }
        }

        return array_keys($found);
    }

    /**
     * Give roles to a region in the Robust config, the ones it has already kept. The
     * line goes where the config shows an example of it, and a role given to another
     * region is not asked twice.
     *
     * @param list<string> $roles
     */
    public static function give(string $robustIni, string $region, array $roles): void
    {
        $ini = Ini::load($robustIni);
        $key = RegionName::configKey($region);
        $flags = array_filter(array_map('trim', explode(',', (string) $ini->get('GridService', $key))));
        $flags = array_values(array_unique([...$flags, ...$roles, 'Persistent']));
        $ini->set('GridService', $key, '"' . implode(', ', $flags) . '"', 'Region_');
        $ini->save($robustIni);
    }
}
