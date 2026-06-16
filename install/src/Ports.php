<?php

declare(strict_types=1);

namespace OpenSim\Installer;

/**
 * PHP port of bin/nextfreeports — shared, not installer-specific (the launcher
 * port and others will reuse it). Same logic as the bash script: a port is
 * "in use" if it appears (even commented) in any OpenSim config .ini under the
 * known roots — as InternalPort / PublicPort / PrivatePort / http_listener_port
 * — or if it is currently bound (netstat). next()/nextFree() then return the
 * first free port(s) at or above $min.
 */
final class Ports
{
    public static function next(int $min = 9010, array $exclude = []): int
    {
        return self::nextFree($min, 1, $exclude)[0];
    }

    /**
     * @param list<int> $exclude
     * @return list<int>
     */
    public static function nextFree(int $min = 9010, int $count = 1, array $exclude = []): array
    {
        $inUse = self::inUse($exclude);

        $found = [];
        for ($port = $min; count($found) < $count; $port++) {
            if (!in_array($port, $inUse, true)) {
                $found[] = $port;
            }
        }

        return $found;
    }

    /**
     * @param list<int> $exclude
     * @return list<int>
     */
    private static function inUse(array $exclude): array
    {
        $ports = array_map('intval', $exclude);

        $profile = (new Config())->profile();
        foreach (self::configFiles($profile['EtcRoot'] ?? '', $profile['DataRoot'] ?? '') as $file) {
            $text = (string) @file_get_contents($file);
            if (preg_match_all('/^[\s;]*(?:InternalPort|PublicPort|PrivatePort|http_listener_port)\s*=\s*"?(\d+)/im', $text, $m)) {
                foreach ($m[1] as $p) {
                    $ports[] = (int) $p;
                }
            }
        }

        foreach (self::boundPorts() as $p) {
            $ports[] = $p;
        }

        return array_values(array_unique($ports));
    }

    /** @return list<string> every .ini under the OpenSim config roots */
    private static function configFiles(string $etcRoot, string $dataRoot): array
    {
        $base = dirname(__DIR__, 2);
        $roots = array_filter(["$base/etc", "$base/config", $etcRoot, $dataRoot]);

        $files = [];
        foreach ($roots as $root) {
            if (!is_dir($root)) {
                continue;
            }
            $it = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
            );
            foreach ($it as $file) {
                $path = $file->getPathname();
                if ($file->isFile() && str_ends_with($path, '.ini') && !str_contains($path, '#')) {
                    $files[] = $path;
                }
            }
        }

        return $files;
    }

    /** @return list<int> ports currently bound, from netstat (BSD '.port' and Linux ':port') */
    private static function boundPorts(): array
    {
        [, $out] = System::capture('netstat -an');
        $ports = [];
        if (preg_match_all('/[.:](\d+)\s/', $out, $m)) {
            foreach ($m[1] as $p) {
                $ports[] = (int) $p;
            }
        }

        return $ports;
    }
}
