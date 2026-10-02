<?php

declare(strict_types=1);

namespace OpenSim\Installer;

/**
 * The instances that have to restart to take into account what the setup did, and why: a text
 * file, one `<instance> <reason>` per line, kept until they restart (the setup offers it when it
 * ends). Written by the process that writes the files of the install, so the file is theirs.
 */
final class PendingRestarts
{
    /** @param array<string,mixed> $profile */
    public static function path(array $profile): string
    {
        $root = (string) ($profile['DataRoot'] ?? '');

        return $root === '' ? '' : "$root/pending-restarts.txt";
    }

    /** @param array<string,mixed> $profile */
    public static function add(array $profile, string $instance, string $reason): void
    {
        $path = self::path($profile);
        $line = "$instance " . trim((string) preg_replace('/\s+/', ' ', $reason));
        if ($path === '' || in_array($line, self::lines($path), true)) {
            return;
        }
        if (!is_dir(dirname($path))) {
            @mkdir(dirname($path), 0o755, true);
        }
        @file_put_contents($path, "$line\n", FILE_APPEND);
    }

    /**
     * @param array<string,mixed> $profile
     * @return list<array{instance:string,reason:string}>
     */
    public static function read(array $profile): array
    {
        $entries = [];
        foreach (self::lines(self::path($profile)) as $line) {
            [$instance, $reason] = array_pad(explode(' ', $line, 2), 2, '');
            $entries[] = ['instance' => $instance, 'reason' => $reason];
        }

        return $entries;
    }

    /** @param array<string,mixed> $profile */
    public static function clear(array $profile): void
    {
        $path = self::path($profile);
        if ($path !== '' && is_file($path)) {
            @unlink($path);
        }
    }

    /** @return list<string> */
    private static function lines(string $path): array
    {
        if ($path === '' || !is_file($path)) {
            return [];
        }

        return array_values(array_filter(array_map('trim', file($path, FILE_IGNORE_NEW_LINES) ?: [])));
    }
}
