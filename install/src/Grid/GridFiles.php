<?php


namespace OpenSim\Installer\Grid;

use OpenSim\Installer\Config;
use OpenSim\Installer\SetupFailed;

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

    /**
     * The text of a file of the grid. These files belong to the system user and are not readable by anybody else, while the
     * setup runs as the person who started it: when it cannot read a file, the system user reads it for it. A file that does
     * not exist is an empty text; one that exists and cannot be read is an error, never an empty text (the setup would take
     * the simulator for one that has no port).
     *
     * @param ?callable(string):?string $elevated reads a file as the system user, null when it cannot (the default: sudo cat)
     * @throws SetupFailed when the file exists and nobody the setup can be reads it
     */
    public static function read(string $path, ?callable $elevated = null): string
    {
        if (is_readable($path) && ($text = file_get_contents($path)) !== false) {
            return $text;
        }
        if (!file_exists($path)) {
            return '';
        }
        $text = ($elevated ?? self::sudoRead(...))($path);
        if ($text === null) {
            fwrite(
                STDERR,
                sprintf(
                    _('opensim: cannot read %s, it is for the system user only (mode 640): run the setup with sudo.'),
                    $path,
                ) . "\n",
            );

            throw new SetupFailed("cannot read $path");
        }

        return $text;
    }

    /** The content of a file read as the system user of the install, null when there is none, or when it cannot (no sudo). */
    private static function sudoRead(string $path): ?string
    {
        $user = (string) ((new Config())->profile()['SystemUser'] ?? '');
        $me = function_exists('posix_geteuid') ? (posix_getpwuid(posix_geteuid())['name'] ?? '') : '';
        if ($user === '' || $me === $user || $me === 'root') {
            return null;
        }
        // A password is asked on the terminal when there is one, else sudo must not need it
        $command = ['sudo', ...(function_exists('posix_isatty') && posix_isatty(STDIN) ? [] : ['-n']), '-H', '-u', $user, 'cat', '--', $path];
        $process = @proc_open($command, [0 => STDIN, 1 => ['pipe', 'w'], 2 => ['file', '/dev/null', 'w']], $pipes);
        if (!is_resource($process)) {
            return null;
        }
        $text = (string) stream_get_contents($pipes[1]);
        fclose($pipes[1]);

        return proc_close($process) === 0 ? $text : null;
    }
}
