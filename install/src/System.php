<?php

declare(strict_types=1);

namespace OpenSim\Installer;

/**
 * Thin wrapper around shell commands.
 */
final class System
{
    public static function commandExists(string $bin): bool
    {
        [$code, $out] = self::capture('command -v ' . escapeshellarg($bin));

        return $code === 0 && trim($out) !== '';
    }

    /**
     * Run a command, capturing stdout (stderr discarded). Read-only probes.
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

    /**
     * Run a command with live output (stdout + stderr), returning its exit code.
     * Used for the apply phase (downloads, extraction, installs).
     */
    public static function run(string $cmd): int
    {
        $code = 0;
        passthru($cmd . ' 2>&1', $code);

        return $code;
    }

    /** Quote a value for safe use in a shell command. */
    public static function arg(string $value): string
    {
        return escapeshellarg($value);
    }
}
