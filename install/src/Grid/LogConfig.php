<?php

declare(strict_types=1);

namespace OpenSim\Installer\Grid;

/**
 * Generates the per-grid log4net config (Robust.exe.config) from the core's
 * model, pointing its appenders at this grid's log files. It is a config, so it
 * lives in EtcDirectory (the bash script wrote it under DataDirectory, which was
 * a mistake). Ported from the commented-out logic in install.sh / bin/opensim.
 */
final class LogConfig
{
    public function write(GridPlan $plan): ?string
    {
        $model = $plan->binDir . '/Robust.exe.config';
        if (!is_file($model)) {
            return null;
        }

        $xml = (string) file_get_contents($model);
        $logBase = $plan->logsDirectory . '/' . $plan->gridSlug . '_robust';

        // Point the file appenders at this grid's log files.
        $xml = (string) preg_replace('/(<file value=")[^"]*Robust\.log(")/i', '${1}' . $logBase . '.log${2}', $xml);
        $xml = (string) preg_replace('/(<file value=")[^"]*RobustStats\.log(")/i', '${1}' . $logBase . '.Stats.log${2}', $xml);

        $dest = $plan->etcDirectory . '/Robust.exe.config';
        @mkdir(dirname($dest), 0o755, true);
        file_put_contents($dest, $xml);

        return $dest;
    }
}
