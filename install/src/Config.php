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

    /**
     * Write the plan into $etcRoot/opensim.conf (additive: other profiles are
     * preserved), link it from ~/.config, and return the canonical path.
     */
    public function write(Plan $plan): string
    {
        $path = rtrim($plan->etcRoot, '/') . '/opensim.conf';
        $data = is_file($path) ? (parse_ini_file($path, true, INI_SCANNER_RAW) ?: []) : [];

        $profile = $this->profileValues($plan);
        $current = $data['Defaults']['DefaultProfile'] ?? null;

        if ($plan->makeDefault || $current === null) {
            // [Defaults] = pointer + a mirror of this profile's values.
            $data['Defaults'] = ['DefaultProfile' => $plan->profile] + $profile;
        } else {
            // Keep the existing default; just ensure the pointer stays first.
            $data['Defaults'] = ['DefaultProfile' => $current] + ($data['Defaults'] ?? []);
        }
        $data[$plan->profile] = $profile;

        $this->save($path, $data);
        $this->link($path);

        return $path;
    }

    /** @return array<string,string> */
    private function profileValues(Plan $plan): array
    {
        return [
            'DirectoryLayout' => $plan->layout,
            'OpensimVersion' => $plan->version,
            'InstallPath' => $plan->installPath,
            'CoreRoot' => $plan->coreRoot,
            'CoreDirectory' => $plan->coreDirectory,
            'EtcRoot' => $plan->etcRoot,
            'VarRoot' => $plan->varRoot,
            'LogsRoot' => $plan->logsRoot,
            'CacheRoot' => $plan->cacheRoot,
            'DataRoot' => $plan->dataRoot,
            'Runtime' => $plan->runtime,
        ];
    }

    /** @param array<string,array<string,string>> $data */
    private function save(string $path, array $data): void
    {
        $ordered = [];
        if (isset($data['Defaults'])) {
            $ordered['Defaults'] = $data['Defaults']; // always first
        }
        foreach ($data as $section => $values) {
            if ($section !== 'Defaults') {
                $ordered[$section] = $values;
            }
        }

        $out = '';
        foreach ($ordered as $section => $values) {
            $out .= "[$section]\n";
            foreach ($values as $key => $value) {
                $out .= "$key = $value\n";
            }
            $out .= "\n";
        }

        @mkdir(dirname($path), 0o755, true);
        file_put_contents($path, $out);
    }

    private function link(string $path): void
    {
        $home = getenv('HOME') ?: '';
        if ($home === '') {
            return;
        }
        $link = "$home/.config/opensim/opensim.conf";
        @mkdir(dirname($link), 0o755, true);
        if (is_link($link) || is_file($link)) {
            @unlink($link);
        }
        @symlink($path, $link);
    }
}
