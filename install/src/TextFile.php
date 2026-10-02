<?php

declare(strict_types=1);

namespace OpenSim\Installer;

/**
 * Text files that come from the OpenSim sources, brought to Unix line endings:
 * some of its configuration files are shipped with Windows ones, which show as
 * ^M in everything made from them.
 */
final class TextFile
{
    /** The text with Unix line endings. */
    public static function unix(string $text): string
    {
        return str_contains($text, "\r") ? str_replace(["\r\n", "\r"], "\n", $text) : $text;
    }

    /** The content of a file, with Unix line endings. */
    public static function read(string $path): string
    {
        return self::unix((string) file_get_contents($path));
    }

    /** Copy a text file, with Unix line endings. */
    public static function copy(string $source, string $dest): bool
    {
        return is_file($source) && @file_put_contents($dest, self::read($source)) !== false;
    }

    /** Bring a file to Unix line endings in place, nothing else in it is touched. True when it was changed. */
    public static function clean(string $path): bool
    {
        $text = is_file($path) ? (string) file_get_contents($path) : '';
        if (!str_contains($text, "\r")) {
            return false;
        }

        return @file_put_contents($path, self::unix($text)) !== false;
    }
}
