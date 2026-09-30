<?php

declare(strict_types=1);

namespace OpenSim\Installer\Grid;

/**
 * The OpenSimulator cores installed under a root (packaged releases, builds).
 */
final class Cores
{
    /**
     * OpenSim.exe in the releases, only OpenSim.dll in builds made on Linux.
     *
     * @return array<string,string> directory => name, in natural order
     */
    public static function list(string $coreRoot): array
    {
        $cores = [];
        $assemblies = array_merge(glob("$coreRoot/*/bin/OpenSim.exe") ?: [], glob("$coreRoot/*/bin/OpenSim.dll") ?: []);
        foreach ($assemblies as $assembly) {
            $dir = dirname($assembly, 2);
            $cores[$dir] = basename($dir);
        }
        ksort($cores, SORT_NATURAL);

        return $cores;
    }
}
