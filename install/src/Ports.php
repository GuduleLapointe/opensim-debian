<?php

declare(strict_types=1);

namespace OpenSim\Installer;

/**
 * The next free ports, shared by the setup and `opensim next port`: a port is
 * "in use" if it appears (even commented) in any OpenSim config .ini under the
 * known roots — as InternalPort / PublicPort / PrivatePort / http_listener_port
 * / console_port — or if it is currently bound (netstat). next()/nextFree()
 * then return the first free port(s) at or above $min.
 *
 * The ports of an instance are a block of ten, the same wherever it runs, so an
 * instance can move to another machine without changing any port. The console
 * (REST) is always x4. Robust has a public port (x2) and a private one (x3):
 * 8002, 8003 and 8004. A simulator has one HTTP port, the first of its block, and
 * its regions take the others: 9000, its console 9004, its regions 9001, 9002,
 * 9003 and 9005-9009, the next simulator 9010... nextBlock() returns the first
 * free block, where "in use" includes what the caller knows of other machines (the
 * regions registered in the grid).
 */
final class Ports
{
    /**
     * Where the simulators of a grid are looked for: the thousand above the grid, in
     * its hundred, which is where the users are used to find them (Robust on 8002:
     * simulators from 9000; a grid on 8102: from 9100).
     */
    public static function simulatorsFrom(int $gridPublicPort): int
    {
        return intdiv($gridPublicPort + 1000, 100) * 100;
    }

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
     * The block a simulator belongs to, from its HTTP port: x0 (the convention) or x2
     * (the way simulators were first made), null for any other port.
     */
    public static function simulatorBlock(int $httpPort): ?int
    {
        return in_array($httpPort % 10, [0, 2], true) ? intdiv($httpPort, 10) * 10 : null;
    }

    /**
     * The next free port for a region of a simulator, in its block: x1 to x3 then x5 to x9
     * (x4 is the console). A simulator on x2 keeps what it had: x5 to x9, then x3.
     * Null when the block has none left, or the port is not one of a block.
     *
     * @param list<int> $exclude
     */
    public static function nextRegion(int $httpPort, array $exclude = []): ?int
    {
        $base = self::simulatorBlock($httpPort);
        if ($base === null) {
            return null;
        }

        $inUse = self::inUse($exclude);
        foreach ($httpPort % 10 === 0 ? [1, 2, 3, 5, 6, 7, 8, 9] : [5, 6, 7, 8, 9, 3] as $offset) {
            if (!in_array($base + $offset, $inUse, true)) {
                return $base + $offset;
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
                if (
                    $file->isFile() &&
                    str_ends_with($path, '.ini') &&
                    !str_contains($path, '#') &&
                    !self::isDefaults($path)
                ) {
                    $files[] = $path;
                }
            }
        }

        return $files;
    }

    /**
     * Whether a file is a copy of what OpenSim ships, which describes no instance: its
     * OpenSimDefaults.ini sets http_listener_port = 9000 for the simulator nobody configured.
     */
    private static function isDefaults(string $path): bool
    {
        return in_array(basename($path), ['OpenSim.ini', 'OpenSimDefaults.ini'], true) ||
            str_contains($path, '/config-include/');
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
