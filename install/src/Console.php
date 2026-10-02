<?php

declare(strict_types=1);

namespace OpenSim\Installer;

/**
 * The console of a running instance, through the launcher (which acts as the
 * user running the instances, whoever calls it).
 */
final class Console
{
    /** Whether an instance runs, from the config file it was started with (the link in opensim.d or robust.d). */
    public static function running(string $ini): bool
    {
        [$code] = System::capture('pgrep -f -- ' . System::arg("-inifile=$ini( |\$)"));

        return $code === 0;
    }

    /**
     * Send lines to the console of an instance. An empty line answers a
     * prompt with its default.
     *
     * @return bool false when the instance is not running
     */
    public static function send(string $instance, string $lines): bool
    {
        $opensim = dirname(__DIR__, 2) . '/bin/opensim';
        $process = proc_open(
            [$opensim, 'command', $instance, '-'],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
        );
        if (!is_resource($process)) {
            return false;
        }
        fwrite($pipes[0], $lines);
        fclose($pipes[0]);
        stream_get_contents($pipes[1]);
        stream_get_contents($pipes[2]);

        return proc_close($process) === 0;
    }
}
