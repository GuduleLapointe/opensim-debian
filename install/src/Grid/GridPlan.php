<?php

declare(strict_types=1);

namespace OpenSim\Installer\Grid;

/**
 * Everything gathered to (re)configure one grid, before anything is written.
 */
final class GridPlan
{
    public string $gridName = '';
    public string $gridNick = '';
    public string $gridSlug = '';
    public bool $enableHypergrid = true;

    // Selected core (multi-version aware).
    public string $coreDirectory = '';
    public string $binDir = '';

    public string $baseHostname = '';
    public int $publicPort = 8002;
    public int $privatePort = 8003;
    public string $webUrl = '';

    // Database.
    public string $dbHost = 'localhost';
    public string $dbName = '';
    public string $dbUser = 'opensim';
    public string $dbPass = 'changeme';

    // Derived per-grid locations (filled from the active install profile).
    public string $gridDir = '';
    public string $etcDirectory = '';
    public string $dataDirectory = '';
    public string $cacheDirectory = '';
    public string $logsDirectory = '';

    public function robustIni(): string
    {
        return $this->gridDir . '/' . ($this->enableHypergrid ? 'Robust.HG.ini' : 'Robust.ini');
    }

    public function connectionString(): string
    {
        return sprintf(
            'Data Source=%s;Database=%s;User ID=%s;Password=%s;Old Guids=true;',
            $this->dbHost,
            $this->dbName,
            $this->dbUser,
            $this->dbPass,
        );
    }
}
