<?php

declare(strict_types=1);

namespace OpenSim\Installer\Grid;

use OpenSim\Installer\Ini;
use OpenSim\Installer\TextFile;

/**
 * The architecture files a grid shares between its simulators (its
 * config-include folder), made usable by simulators run from a read-only core.
 *
 * A simulator reads one config file and the files it includes after it, each
 * one overriding what came before, and the included paths are relative to the
 * core (where the files are only examples). So, in the copies of the grid:
 *  - the includes are absolute, pointing at the copies;
 *  - the SQLite storage included by default, which would override the database
 *    of the simulator, is off;
 *  - the asset cache goes to the cache folder of the simulator, not the core.
 *
 * Idempotent: what is already done is left as it is, so it also repairs the
 * copies of a grid made before, and keeps the changes made by hand.
 */
final class GridShared
{
    /**
     * The files of the folder of a grid (and of a preset) that come from the distribution, relative to the folder: the main
     * config and its defaults, and the architecture files of config-include.
     *
     * @return list<string>
     */
    public static function files(bool $hypergrid): array
    {
        return [
            'OpenSimDefaults.ini',
            'OpenSim.ini',
            'config-include/' . ($hypergrid ? 'GridHypergrid.ini' : 'Grid.ini'),
            'config-include/GridCommon.ini',
            'config-include/FlotsamCache.ini',
            'config-include/osslDefaultEnable.ini',
            'config-include/osslEnable.ini',
        ];
    }

    /**
     * Make the folder of a grid, or of a preset, whole: every file of the distribution (the core) merged with what the folder
     * has and with the same file of the folders in $over (the preset of the kit), the same way every time, so a setting new in
     * the distribution is always there and what was chosen stays, then our own corrections (patch()), the files kept apart as
     * the distribution has them (an include stays an include). A file is written when what it would be differs from what it is.
     *
     * @param  list<string> $over folders whose files are laid over the distribution, the last winning
     * @param  list<string> $extra files besides those of files(), relative paths (the ini files a preset has)
     * @return list<string> the files written
     */
    public function merge(string $dir, string $binDir, bool $hypergrid, array $over = [], array $extra = []): array
    {
        $written = [];
        foreach (array_values(array_unique(array_merge(self::files($hypergrid), $extra))) as $file) {
            $dest = "$dir/$file";
            $source = $this->source($binDir, $file);
            $layers = array_values(array_filter($over, static fn(string $root): bool => is_file("$root/$file")));
            if ($source === null && $layers === []) {
                is_file($dest) && TextFile::clean($dest) && $written[] = $dest;

                continue;
            }
            $ini = Ini::layered($source, is_file($dest) ? $dest : null);
            foreach ($layers as $root) {
                $ini->overlay(TextFile::read("$root/$file"));
            }
            $text = $this->patched($ini->toString(), $dir, $file);
            if (is_file($dest)) {
                if (TextFile::read($dest) === $text) {
                    continue;
                }
                @copy($dest, "$dest~");
            }
            is_dir(dirname($dest)) || mkdir(dirname($dest), 0o755, true);
            file_put_contents($dest, $text);
            $written[] = $dest;
        }

        return $written;
    }

    /**
     * Our corrections of a file of a folder, in memory: what the distribution assumes (paths relative to the core, which is
     * read-only, a storage of its own) made what the folder needs.
     */
    private function patched(string $text, string $dir, string $file): string
    {
        foreach ($this->patches($dir, $file) as $pattern => $replacement) {
            $text = (string) preg_replace($pattern, $replacement, $text);
        }
        if ($file === 'config-include/osslEnable.ini') {
            $ini = new Ini($text);
            $ini->get('OSSL', 'AllowOSFunctions') === 'true' || $ini->set('OSSL', 'AllowOSFunctions', 'true');
            $text = $ini->toString();
        }

        return $text;
    }

    /**
     * @return array<string,string> pattern => replacement, for a file of the folder
     */
    private function patches(string $dir, string $file): array
    {
        $include = "$dir/config-include";
        $patches = match ($file) {
            'config-include/GridHypergrid.ini', 'config-include/Grid.ini' => [
                '/^(\s*Include-Common\s*=\s*)"?config-include\/GridCommon\.ini"?/m' => '$1"' . $include . '/GridCommon.ini"',
            ],
            'config-include/GridCommon.ini' => [
                '/^(\s*Include-Storage\s*=.*)$/m' => ';$1',
                '/^(\s*Include-FlotsamCache\s*=\s*)"?config-include\/FlotsamCache\.ini"?/m' => '$1"' . $include . '/FlotsamCache.ini"',
                '/^(\s*MapTileDirectory\s*=\s*)"?\.\/maptiles"?[ \t]*$/m' => '$1"${Const|CacheDirectory}/maptiles"',
            ],
            'config-include/osslDefaultEnable.ini' => [
                '/^(\s*Include-osslEnable\s*=\s*)"?config-include\/osslEnable\.ini"?/m' => '$1"' . $include . '/osslEnable.ini"',
            ],
            'config-include/FlotsamCache.ini' => [
                '/^(\s*CacheDirectory\s*=\s*)\.?\/?assetcache[ \t]*$/m' => '$1"${Const|CacheDirectory}/assetcache"',
            ],
            default => [],
        };
        // The assets and the library the folder has are read from it, not from the core
        if (str_starts_with($file, 'config-include/')) {
            if (is_file("$dir/assets/AssetSets.xml")) {
                $patches['/^(\s*AssetLoaderArgs\s*=\s*)"?\.?\/?assets\/AssetSets\.xml"?[ \t]*$/m'] = '$1"' . $dir . '/assets/AssetSets.xml"';
            }
            if (is_file("$dir/inventory/Libraries.xml")) {
                $patches['/^(\s*DefaultLibrary\s*=\s*)"?\.?\/?inventory\/Libraries\.xml"?[ \t]*$/m'] = '$1"' . $dir . '/inventory/Libraries.xml"';
            }
        }

        return $patches;
    }

    /**
     * Copy what is missing from the core and patch it.
     *
     * @return list<string> the files written
     */
    public function prepare(string $gridDir, string $binDir, bool $hypergrid): array
    {
        $dir = "$gridDir/config-include";
        $architecture = $hypergrid ? 'GridHypergrid.ini' : 'Grid.ini';
        $written = [];

        foreach (
            [$architecture, 'GridCommon.ini', 'FlotsamCache.ini', 'osslDefaultEnable.ini', 'osslEnable.ini']
            as $file
        ) {
            $dest = "$dir/$file";
            if (is_file($dest)) {
                if (TextFile::clean($dest)) {
                    $written[] = $dest;
                }
            } else {
                $source = $this->source($binDir, "config-include/$file");
                if ($source === null) {
                    continue;
                }
                is_dir($dir) || mkdir($dir, 0o755, true);
                if (TextFile::copy($source, $dest)) {
                    $written[] = $dest;
                }
            }
        }

        // The same corrections as merge(), for the files that are only copied
        foreach (self::files($hypergrid) as $file) {
            if (!str_starts_with($file, 'config-include/') || !is_file("$gridDir/$file")) {
                continue;
            }
            $written[] = $this->patch("$gridDir/$file", $this->patches($gridDir, $file));
        }
        $written[] = $this->enableOssl("$dir/osslEnable.ini");
        $written = array_values(array_filter($written));

        return array_values(array_unique($written));
    }

    /**
     * OSSL is part of the standard config: the functions are on, with the permissions of the defaults of the
     * core (osSetParcelDetails, which the region initialization script needs, is for the owner and the
     * managers of the estate).
     *
     * @return ?string the path when written
     */
    private function enableOssl(string $path): ?string
    {
        if (!is_file($path)) {
            return null;
        }
        $ini = Ini::load($path);
        $before = $ini->toString();
        if ($ini->get('OSSL', 'AllowOSFunctions') !== 'true') {
            $ini->set('OSSL', 'AllowOSFunctions', 'true');
        }
        if ($ini->toString() === $before) {
            return null;
        }
        $ini->save($path);

        return $path;
    }

    /** The file in the core (relative to its bin folder), as it is or as an example. */
    private function source(string $binDir, string $file): ?string
    {
        foreach (["$binDir/$file", "$binDir/$file.example"] as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * Apply replacements to a file, written only when something changed.
     *
     * @param array<string,string> $replacements pattern => replacement
     * @return ?string the path when written
     */
    private function patch(string $path, array $replacements): ?string
    {
        if (!is_file($path)) {
            return null;
        }
        $before = GridFiles::read($path);
        $after = $before;
        foreach ($replacements as $pattern => $replacement) {
            $after = (string) preg_replace($pattern, $replacement, $after);
        }
        if ($after === $before) {
            return null;
        }
        file_put_contents($path, $after);

        return $path;
    }
}
