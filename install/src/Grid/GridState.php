<?php

declare(strict_types=1);

namespace OpenSim\Installer\Grid;

/**
 * Grid enablement: a grid is "enabled" when its Robust config is linked into
 * EtcRoot/robust.d, where the launcher discovers it.
 */
final class GridState
{
    public static function robustIni(string $etcRoot, string $nick): ?string
    {
        foreach (['Robust.HG.ini', 'Robust.ini'] as $file) {
            $path = "$etcRoot/grids/$nick/$file";
            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    }

    public static function link(string $etcRoot, string $nick): string
    {
        return "$etcRoot/robust.d/$nick.ini";
    }

    public static function isEnabled(string $etcRoot, string $nick): bool
    {
        $link = self::link($etcRoot, $nick);

        return is_link($link) || is_file($link);
    }

    public static function enable(string $etcRoot, string $nick): bool
    {
        $target = self::robustIni($etcRoot, $nick);
        if ($target === null) {
            return false;
        }

        $link = self::link($etcRoot, $nick);
        @mkdir(dirname($link), 0o755, true);
        if (is_link($link) || is_file($link)) {
            @unlink($link);
        }

        return @symlink($target, $link);
    }

    public static function disable(string $etcRoot, string $nick): void
    {
        $link = self::link($etcRoot, $nick);
        if (is_link($link) || is_file($link)) {
            @unlink($link);
        }
    }
}
