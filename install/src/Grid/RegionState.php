<?php

declare(strict_types=1);

namespace OpenSim\Installer\Grid;

/**
 * The regions of a simulator, one file each in its regions folder, named after
 * the region. A region is "enabled" while its file ends in .ini, the only ones
 * the simulator loads; disabling it renames the file to .ini.disabled, which
 * keeps its description (and its UUID) for later.
 */
final class RegionState
{
    private const DISABLED = '.disabled';

    /**
     * The regions of a folder, by name.
     *
     * @return array<string,array{file:string,enabled:bool}>
     */
    public static function list(string $regionsDir): array
    {
        $regions = [];
        $files = array_merge(glob("$regionsDir/*.ini") ?: [], glob("$regionsDir/*.ini" . self::DISABLED) ?: []);
        sort($files);
        foreach ($files as $file) {
            if (preg_match_all('/^\s*\[([^\]]+)\]/m', (string) file_get_contents($file), $m)) {
                foreach ($m[1] as $name) {
                    $regions[trim($name)] = ['file' => $file, 'enabled' => !str_ends_with($file, self::DISABLED)];
                }
            }
        }
        ksort($regions);

        return $regions;
    }

    /** Stop the simulator from loading the region at its next start; the new path of the file, null if it cannot be renamed. */
    public static function disable(string $file): ?string
    {
        if (str_ends_with($file, self::DISABLED)) {
            return $file;
        }

        return @rename($file, $file . self::DISABLED) ? $file . self::DISABLED : null;
    }

    /** Have the simulator load the region at its next start; the new path of the file, null if it cannot be renamed. */
    public static function enable(string $file): ?string
    {
        if (!str_ends_with($file, self::DISABLED)) {
            return $file;
        }
        $enabled = substr($file, 0, -strlen(self::DISABLED));

        return !is_file($enabled) && @rename($file, $enabled) ? $enabled : null;
    }

    /**
     * What a region file holds of the region.
     *
     * @return array<string,string>
     */
    public static function values(string $file): array
    {
        $values = [];
        $text = (string) file_get_contents($file);
        foreach (['RegionUUID', 'Location', 'SizeX', 'InternalPort', 'ExternalHostName'] as $key) {
            if (preg_match('/^\s*' . $key . '\s*=\s*"?([^"\r\n]*?)"?\s*$/m', $text, $m)) {
                $values[$key] = trim($m[1]);
            }
        }

        return $values;
    }
}
