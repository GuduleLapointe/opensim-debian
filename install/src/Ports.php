<?php

declare(strict_types=1);

namespace OpenSim\Installer;

/**
 * PHP port of bin/nextfreeports — shared, not installer-specific (the launcher
 * port and others will reuse it). Same logic as the bash script: a port is
 * "in use" if it appears (even commented) in any OpenSim config .ini under the
 * known roots — as InternalPort / PublicPort / PrivatePort / http_listener_port
 * / console_port — or if it is currently bound (netstat). next()/nextFree()
 * then return the first free port(s) at or above $min.
 *
 * The ports of an instance are a block of ten, the same wherever it runs, so a
 * simulator can move to another machine without changing any port:
 *   x2 public, x3 private (Robust; a simulator has none of its own, it is one
 *   more port for what it may need: SSL, XML-RPC, one more region), x4 console
 *   (REST), x5-x9 more services or, for a simulator, the ports of its regions.
 * Robust takes 8002 (public), 8003 (private), 8004 (console), the first simulator
 * 8012, 8013, 8014 and 8015-8019, the next one 8022... nextBlock() returns the
 * first free block, where "in use" includes what the caller knows of other
 * machines (the regions registered in the grid).
 */
final class Ports
{
    public static function next(int $min = 9010, array $exclude = []): int
    {
        return self::nextFree($min, 1, $exclude)[0];
    }

    /**
     * The first block of ports (ten from a multiple of ten, at or above $min)
     * where none is in use.
     *
     * @param list<int> $exclude ports to treat as used: those of other machines
     */
    public static function nextBlock(int $min = 9010, array $exclude = []): int
    {
        $inUse = self::inUse($exclude);
        for ($base = (int) (ceil($min / 10) * 10); ; $base += 10) {
            $free = true;
            for ($port = $base; $port < $base + 10; $port++) {
                if (in_array($port, $inUse, true)) {
                    $free = false;
                    break;
                }
            }
            if ($free) {
                return $base;
            }
        }
    }

    /** Whether a port is free: not in a config file, not bound here, not in the list. */
    public static function isFree(int $port, array $exclude = []): bool
    {
        return !in_array($port, self::inUse($exclude), true);
    }

    /**
     * The first free port of a block, from its offset.
     *
     * @param list<int> $exclude
     */
    public static function inBlock(int $base, int $from = 5, array $exclude = []): ?int
    {
        $inUse = self::inUse($exclude);
        for ($port = $base + $from; $port < $base + 10; $port++) {
            if (!in_array($port, $inUse, true)) {
                return $port;
            }
        }

        return null;
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
            if (
                preg_match_all(
                    '/^[\s;]*(?:InternalPort|PublicPort|PrivatePort|http_listener_port|http_listener_sslport|console_port|ConsolePort)\s*=\s*"?(\d+)/im',
                    $text,
                    $m,
                )
            ) {
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
                new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
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
