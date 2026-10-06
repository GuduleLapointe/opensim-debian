<?php

declare(strict_types=1);

namespace OpenSim\Installer\Grid;

/**
 * The files of the folder of a grid. Every ini file may hold a password (OpenSimulator is made so: the connection strings,
 * the keys of the modules), and the web server reads them (opensim-helpers read the official settings): so all of them are
 * readable by the owner and the group of the web server, nobody else.
 */
final class GridFiles
{
    /** The group of the web server on this machine, null when there is none. */
    public static function webGroup(): ?int
    {
        $group = function_exists('posix_getgrnam') ? posix_getgrnam('www-data') : false;

        return $group === false ? null : (int) $group['gid'];
    }

    /**
     * Mode 640 and the group of the web server for every ini file of a folder (the simulators', the includes, the regions').
     *
     * @return bool false when the files could not be given to the group of the web server (no such group, or not allowed to)
     */
    public static function protect(string $dir): bool
    {
        $gid = self::webGroup();
        $files = [];
        if (is_dir($dir)) {
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));
            foreach ($iterator as $file) {
                if ($file->isFile() && !$file->isLink() && str_ends_with(strtolower($file->getFilename()), '.ini')) {
                    $files[] = $file->getPathname();
                }
            }
        }
        $done = $gid !== null;
        foreach ($files as $file) {
            // Mode 640 in any case: nobody else reads what may hold a password
            if ($gid !== null && !@chgrp($file, $gid)) {
                $done = false;
            }
            chmod($file, 0o640);
        }

        return $done;
    }
}
