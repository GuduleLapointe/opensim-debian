<?php

declare(strict_types=1);

namespace OpenSim\Installer;

/**
 * Reader for the single opensim.conf (phase 2 only needs the default profile;
 * phase 3 will add writing).
 */
final class Config
{
    public function path(): ?string
    {
        $home = getenv('HOME') ?: '';
        $candidates = array_filter([
            $home !== '' ? "$home/.config/opensim/opensim.conf" : null,
            $home !== '' ? "$home/.opensim.conf" : null,
            '/etc/opensim/opensim.conf',
            dirname(__DIR__, 2) . '/config/opensim.conf',
        ]);

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    public function defaultProfile(): ?string
    {
        $path = $this->path();
        if ($path === null) {
            return null;
        }

        $ini = @parse_ini_file($path, true, INI_SCANNER_RAW);

        return $ini['Defaults']['DefaultProfile'] ?? null;
    }
}
