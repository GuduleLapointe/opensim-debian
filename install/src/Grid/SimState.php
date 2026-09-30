<?php

declare(strict_types=1);

namespace OpenSim\Installer\Grid;

/**
 * Simulator enablement: a simulator is "enabled" when its config is linked
 * into EtcRoot/opensim.d, where the launcher discovers it (after the enabled
 * grids, so Robust is up before the simulators start).
 */
final class SimState
{
    public static function link(string $etcRoot, string $slug): string
    {
        return "$etcRoot/opensim.d/$slug.ini";
    }

    public static function isEnabled(string $etcRoot, string $slug): bool
    {
        $link = self::link($etcRoot, $slug);

        return is_link($link) || is_file($link);
    }

    public static function enable(string $etcRoot, string $slug, string $ini): bool
    {
        if (!is_file($ini)) {
            return false;
        }
        $link = self::link($etcRoot, $slug);
        @mkdir(dirname($link), 0o755, true);
        if (is_link($link) || is_file($link)) {
            @unlink($link);
        }

        return @symlink($ini, $link);
    }

    public static function disable(string $etcRoot, string $slug): void
    {
        $link = self::link($etcRoot, $slug);
        if (is_link($link) || is_file($link)) {
            @unlink($link);
        }
    }

    /**
     * The simulators of a grid: names of the config files in its sims folder.
     *
     * @return list<string>
     */
    public static function names(string $gridDir): array
    {
        $names = [];
        foreach (glob("$gridDir/sims/*.ini") ?: [] as $file) {
            $names[] = basename($file, '.ini');
        }
        sort($names);

        return $names;
    }
}
