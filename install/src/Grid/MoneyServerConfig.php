<?php

namespace OpenSim\Installer\Grid;

use OpenSim\Installer\Ini;
use OpenSim\Installer\TextFile;

/**
 * The MoneyServer.ini of a grid, beside Robust.ini and OpenSim.ini: the MoneyServer reads only its own file, the helpers read
 * the key they share with it (MoneyScriptAccessKey, [MoneyServer]). It is the original file of the MoneyServer, whole, like
 * the other files of the grid are the ones of the distribution: the original, over it what the grid has set, and the key,
 * made at random once when the grid has none.
 */
final class MoneyServerConfig
{
    public const FILE = 'MoneyServer.ini';

    public static function path(string $gridDir): string
    {
        return rtrim($gridDir, '/') . '/' . self::FILE;
    }

    /** The original MoneyServer.ini (the last version of contrib/MoneyServer), null when this install does not have it. */
    public static function original(?string $root = null): ?string
    {
        $found = glob(($root ?? dirname(__DIR__, 3) . '/contrib/MoneyServer') . '/*/' . self::FILE) ?: [];
        natsort($found);

        return $found === [] ? null : end($found);
    }

    /**
     * The text of the MoneyServer.ini of a grid: the original, then what the grid has set, then the key if it has none.
     *
     * @return string|null null when there is no original to start from (nothing is made up instead)
     */
    public static function render(string $gridDir, ?string $original = null, ?string $key = null): ?string
    {
        $original ??= self::original();
        if ($original === null || !is_file($original)) {
            return null;
        }
        $ini = Ini::layered($original, self::path($gridDir));
        $given = $ini->get('MoneyServer', 'MoneyScriptAccessKey');
        if ($given === null || trim($given) === '') {
            $ini->set('MoneyServer', 'MoneyScriptAccessKey', '"' . ($key ?? bin2hex(random_bytes(16))) . '"');
        }

        return $ini->toString();
    }

    /**
     * Write it, what the grid had kept beside it (MoneyServer.ini~) when it changes. The file may hold the key: 640.
     *
     * @return string|null the file written, null when nothing had to be
     */
    public static function write(string $gridDir, ?string $original = null): ?string
    {
        $text = self::render($gridDir, $original);
        $path = self::path($gridDir);
        if ($text === null || (is_file($path) && TextFile::read($path) === $text)) {
            return null;
        }
        if (is_file($path)) {
            @copy($path, "$path~");
        }
        file_put_contents($path, $text);
        chmod($path, 0o640);

        return $path;
    }

    /** The file of a grid that has none (a package that is installed adds what is missing), nothing for one that has it. */
    public static function ensure(string $gridDir): ?string
    {
        return is_file(self::path($gridDir)) ? null : self::write($gridDir);
    }
}
