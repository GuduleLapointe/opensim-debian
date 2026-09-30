<?php

declare(strict_types=1);

namespace OpenSim\Installer\Grid;

/**
 * Everything gathered to create (or reconfigure) one simulator of a grid,
 * with its first region, before anything is written.
 */
final class SimPlan
{
    // The grid it joins.
    public string $gridNick = '';
    public string $gridName = '';
    public string $gridDir = '';
    public bool $hypergrid = true;
    public string $baseHostname = 'localhost';
    public int $publicPort = 8002;
    public int $privatePort = 8003;
    /** The core it runs from. */
    public string $coreDirectory = '';

    // The simulator.
    public string $simName = '';
    /** Name of the instance, of its config file and of its database. */
    public string $slug = '';
    public int $httpPort = 8012;
    /** 'rest' (remote console through its port) or 'screen' (a session to attach on this machine). */
    public string $consoleMode = 'rest';
    /** Its port, in the block of the simulator (x4); kept even when the console is a screen one. 0: no block. */
    public int $consolePort = 0;
    public string $consoleUser = '';
    public string $consolePass = '';

    // Its own database.
    public string $dbHost = 'localhost';
    public string $dbName = '';
    public string $dbUser = 'opensim';
    public string $dbPass = '';

    // Its folders.
    public string $dataDirectory = '';
    public string $cacheDirectory = '';
    public string $logsDirectory = '';

    // Its estate, whose owner is an account of the grid.
    public string $estateName = '';
    public string $estateOwner = '';
    /** The owner does not exist yet: the account to create in the grid. */
    public bool $createOwner = false;
    public string $ownerPassword = '';
    public string $ownerEmail = '';

    // Its first region.
    public bool $createRegion = true;
    public string $regionName = '';
    public string $regionUuid = '';
    public string $regionLocation = '1000,1000';
    public int $regionPort = 8011;
    public int $regionSize = 256;

    /** Only a region is added to a simulator already configured. */
    public bool $regionOnly = false;

    // What to do once written, asked before anything is written.
    public bool $enable = true;
    public bool $start = true;

    public function binDir(): string
    {
        return $this->coreDirectory . '/bin';
    }

    /** The config of the simulator. */
    public function iniPath(): string
    {
        return "{$this->gridDir}/sims/{$this->slug}.ini";
    }

    /** Where its regions are described, one file each. */
    public function regionsDir(): string
    {
        return "{$this->gridDir}/sims/{$this->slug}/regions";
    }

    public function regionIni(): string
    {
        $file = preg_replace('/[^A-Za-z0-9._-]/', '', $this->regionName) ?: 'region';

        return "{$this->regionsDir()}/$file.ini";
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

    /** The database settings, to ask questions of the database. */
    public function databasePlan(): GridPlan
    {
        $plan = new GridPlan();
        $plan->dbHost = $this->dbHost;
        $plan->dbName = $this->dbName;
        $plan->dbUser = $this->dbUser;
        $plan->dbPass = $this->dbPass;

        return $plan;
    }

    /** The plan as plain values, to hand it to the process that writes it. */
    public function toArray(): array
    {
        return get_object_vars($this);
    }

    /** @param array<string,mixed> $values */
    public static function fromArray(array $values): self
    {
        $plan = new self();
        foreach ($values as $name => $value) {
            if (property_exists($plan, $name)) {
                $plan->$name = $value;
            }
        }

        return $plan;
    }
}
