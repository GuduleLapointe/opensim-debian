<?php

declare(strict_types=1);

namespace OpenSim\Installer;

/**
 * Thin wrapper around shell commands. Phase 2 only uses the read-only probes.
 */
final class System
{
    public static function commandExists(string $bin): bool
    {
        [$code, $out] = self::capture('command -v ' . escapeshellarg($bin));

        return $code === 0 && trim($out) !== '';
    }

    /**
     * Run a command, capturing stdout (stderr discarded).
     *
     * @return array{0:int,1:string} [exitCode, output]
     */
    public static function capture(string $cmd): array
    {
        $out = [];
        $code = 0;
        exec($cmd . ' 2>/dev/null', $out, $code);

        return [$code, implode("\n", $out)];
    }
}
