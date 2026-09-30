<?php

declare(strict_types=1);

namespace OpenSim\Installer\Grid;

use OpenSim\Installer\Config;
use OpenSim\Installer\Console;
use OpenSim\Installer\Elevated;
use OpenSim\Installer\Ports;
use OpenSim\Installer\SetupFailed;
use OpenSim\Installer\System;
use OpenSim\Installer\Ui\InstallerUi;

/**
 * Create (or reconfigure) one simulator of a grid, with its first region.
 *
 * Same shape as NewGrid: the questions, and the database with the rights of
 * the user who started the setup, are handled here; the files and the
 * instances belong to the system user of the install (see Elevated).
 *
 * A simulator has its own database (its regions, its estates) and an estate
 * whose owner is an account of the grid: OpenSimulator cannot create it, so
 * the account is chosen among the grid's, or created in the grid first.
 */
final class NewSim
{
    public function __construct(private InstallerUi $ui)
    {
    }

    /**
     * @param ?string $gridNick the grid it joins, asked when there are several and none is given
     * @param ?string $simName  the simulator to modify, asked when none is given
     */
    public function run(?string $gridNick = null, ?string $simName = null): void
    {
        $profile = (new Config())->profile();
        $etcRoot = $profile['EtcRoot'] ?? '';
        if ($etcRoot === '') {
            $this->ui->error('No installed framework found. Install an OpenSim core first.');

            return;
        }

        $grid = $this->grid($profile, $gridNick);
        if ($grid === null) {
            return;
        }

        $database = new Database($this->ui);
        $plan = $this->gather($grid, $profile, $database, $simName);
        if ($plan === null) {
            return;
        }

        // Nothing is written before the database of the simulator works
        $check = $plan->databasePlan();
        while (($result = $database->ensure($check)) !== Database::OK) {
            if ($result === Database::ABORT) {
                $this->ui->error('Stopped, nothing was changed: OpenSim cannot run without its database.');

                throw new SetupFailed('database');
            }
            if (!$this->ui->confirm('Try again (the database settings can be changed)?', true)) {
                $this->ui->note('Stopped, nothing was changed: OpenSim cannot run without its database.');

                return;
            }
            $this->askDatabase($plan, $grid);
            $check = $plan->databasePlan();
        }

        $this->showPlan($plan);
        if (!$this->ui->confirm("Apply this configuration to simulator '{$plan->simName}'?", true)) {
            $this->ui->note('Aborted — nothing changed.');

            return;
        }

        $plan->enable = $this->ui->confirm("Enable simulator '{$plan->simName}' (link into opensim.d)?", true);
        $plan->start = $plan->enable && $this->ui->confirm("Start simulator '{$plan->simName}' now?", true);

        if (!Elevated::needed($profile)) {
            $this->apply($plan, $profile);

            return;
        }
        Elevated::run($this->ui, '--apply-sim', ['plan' => $plan->toArray(), 'profile' => $profile], $profile['SystemUser']);
    }

    /**
     * Add a region to a simulator already configured: loaded at once when the
     * simulator runs, else the simulator is started.
     */
    public function addRegion(string $gridNick, string $simName): void
    {
        $profile = (new Config())->profile();
        $grid = $this->grid($profile, $gridNick);
        if ($grid === null) {
            return;
        }

        $plan = new SimPlan();
        $plan->gridNick = $grid->nick;
        $plan->gridName = $grid->name;
        $plan->gridDir = $grid->dir;
        $plan->hypergrid = $grid->hypergrid;
        $plan->logsDirectory = $grid->logsDirectory;
        $plan->simName = $simName;
        $plan->slug = GridInfo::instanceName($grid->nick . '_' . $simName);
        $plan->regionOnly = true;
        $plan->createRegion = true;
        if (!is_file($plan->iniPath())) {
            $this->ui->error("Simulator '$simName' is not configured.");

            return;
        }
        $plan->httpPort = (int) (GridInfo::parse($plan->iniPath())['httpPort'] ?? 0);

        $this->askRegion($plan, $grid, new Database($this->ui));
        if (is_file($plan->regionIni())) {
            $this->ui->error("A region is already described in {$plan->regionIni()}.");

            return;
        }
        $this->ui->note("Region {$plan->regionName} at {$plan->regionLocation}, port {$plan->regionPort}, for simulator '$simName'.");
        if (!$this->ui->confirm("Add region '{$plan->regionName}'?", true)) {
            $this->ui->note('Aborted — nothing changed.');

            return;
        }
        $plan->start = $this->ui->confirm('Load it now (starting the simulator when it is not running)?', true);

        if (!Elevated::needed($profile)) {
            $this->apply($plan, $profile);

            return;
        }
        Elevated::run($this->ui, '--apply-sim', ['plan' => $plan->toArray(), 'profile' => $profile], $profile['SystemUser']);
    }

    /**
     * The writing itself: run as the system user of the install, root, or the
     * user of an install without one.
     *
     * @param array<string,mixed> $profile
     */
    public function apply(SimPlan $plan, array $profile): void
    {
        $etcRoot = $profile['EtcRoot'];
        $grid = GridInfo::load($profile, $plan->gridNick);
        if ($grid === null) {
            $this->ui->error("Grid '{$plan->gridNick}' not found.");

            throw new SetupFailed('grid');
        }

        if ($plan->regionOnly) {
            $this->applyRegion($plan, $profile, $grid);

            return;
        }

        // First, while nothing is written: the account the estate needs
        if ($plan->createOwner) {
            $accounts = new GridAccounts(new Database($this->ui), $this->ui);
            if (!$accounts->create($grid, $plan->estateOwner, $plan->ownerPassword, $plan->ownerEmail)) {
                throw new SetupFailed('account');
            }
            $this->ui->note("Account {$plan->estateOwner} created in the grid '{$grid->nick}'.");
        }

        foreach ([
            $plan->dataDirectory, "{$plan->dataDirectory}/registry",
            $plan->cacheDirectory, "{$plan->cacheDirectory}/maptiles",
            $plan->regionsDir(), $plan->logsDirectory,
        ] as $dir) {
            if ($dir !== '' && !is_dir($dir)) {
                @mkdir($dir, 0o755, true);
            }
        }

        foreach ((new GridShared())->prepare($plan->gridDir, $plan->binDir(), $plan->hypergrid) as $file) {
            $this->ui->note("Wrote $file");
        }
        $this->ui->note('Wrote ' . (new SimConfig())->write($plan));
        if ($plan->createRegion) {
            $region = (new SimConfig())->writeRegion($plan);
            $this->ui->note($region !== null ? "Wrote $region" : "Region {$plan->regionName} already described, left as it is.");
        }

        $systemUser = $profile['SystemUser'] ?? '';
        $this->giveToSystemUser($systemUser, [$plan->gridDir, $plan->dataDirectory, $plan->cacheDirectory], true);

        if ($plan->enable) {
            if (SimState::enable($etcRoot, $plan->slug, $plan->iniPath())) {
                $this->giveToSystemUser($systemUser, [SimState::link($etcRoot, $plan->slug)], false);
                $this->ui->note('Enabled: ' . SimState::link($etcRoot, $plan->slug));
                if ($plan->start) {
                    $this->startSim($plan, $grid);
                }
            } else {
                $this->ui->warn('Could not enable the simulator (config missing).');
            }
        }

        $this->ui->note("Simulator '{$plan->simName}' configured.");
    }

    /** Write the region, and load it in the simulator (started when it is not running). */
    private function applyRegion(SimPlan $plan, array $profile, GridInfo $grid): void
    {
        @mkdir($plan->regionsDir(), 0o755, true);
        $region = (new SimConfig())->writeRegion($plan);
        $this->ui->note($region !== null ? "Wrote $region" : "Region {$plan->regionName} already described, left as it is.");
        $this->giveToSystemUser($profile['SystemUser'] ?? '', [$plan->regionsDir()], true);
        if (!$plan->start) {
            return;
        }

        $file = basename($plan->regionIni());
        if (Console::send($plan->slug, "create region \"{$plan->regionName}\" $file\n")) {
            if (!$this->registered($plan, $grid)) {
                $this->failed($plan, "Region {$plan->regionName} did not register in the grid '{$grid->nick}'.");
            }
            $this->ui->note("Region {$plan->regionName} is online.");

            return;
        }
        $this->startSim($plan, $grid);
    }

    /**
     * Start the simulator and report whether it came up: the launcher's
     * answer, then, for a new region, its registration in the grid.
     */
    private function startSim(SimPlan $plan, GridInfo $grid): void
    {
        $opensim = dirname(__DIR__, 3) . '/bin/opensim';

        [$code, $said] = System::runShown(System::arg($opensim) . ' restart ' . System::arg($plan->slug));
        $pending = $code === 0 && str_contains($said, 'still starting');
        if ($code === 0 && !$pending) {
            if ($plan->createRegion && !$this->registered($plan, $grid)) {
                $this->failed($plan, "Simulator '{$plan->simName}' runs, but the region {$plan->regionName} did not register in the grid '{$grid->nick}'.");
            }
            $this->ui->note("Simulator '{$plan->simName}' is running" . ($plan->createRegion ? ", region {$plan->regionName} is online." : '.'));

            return;
        }

        $this->failed($plan, $pending
            ? "Simulator '{$plan->simName}' is configured but was not ready after two minutes. Try: $opensim -v start {$plan->slug}"
            : "Simulator '{$plan->simName}' is configured but did not start. Try: $opensim -v start {$plan->slug}");
    }

    private function failed(SimPlan $plan, string $message): never
    {
        $this->ui->error($message);
        $log = $plan->logsDirectory . '/' . $plan->slug . '.log';
        if (is_file($log)) {
            $this->ui->note('Recent log:');
            System::run('tail -n 30 ' . System::arg($log));
        }

        throw new SetupFailed($message);
    }

    /** Whether the grid knows the region, waiting a little for it to tell. */
    private function registered(SimPlan $plan, GridInfo $grid): bool
    {
        if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9 ._-]*$/', $plan->regionName)) {
            return true; // cannot be looked for
        }
        $database = new Database($this->ui);
        for ($i = 0; $i < 60; $i++) {
            $rows = $database->select($grid->databasePlan(), "SELECT 1 FROM regions WHERE regionName = '{$plan->regionName}'");
            if ($rows === null) {
                return true; // the grid database cannot be read: not a reason to call it a failure
            }
            if ($rows !== []) {
                return true;
            }
            sleep(1);
        }

        return false;
    }

    /**
     * When the setup runs as root for an install with a system user (the
     * packages), what it creates belongs to that user, who runs the instances.
     *
     * @param list<string> $paths
     */
    private function giveToSystemUser(string $user, array $paths, bool $recursive): void
    {
        if ($user === '' || !function_exists('posix_geteuid') || posix_geteuid() !== 0 || posix_getpwnam($user) === false) {
            return;
        }
        foreach ($paths as $path) {
            if (is_link($path) || file_exists($path)) {
                System::run('chown -h' . ($recursive ? 'R' : '') . ' ' . System::arg($user) . ': ' . System::arg($path));
            }
        }
    }

    /** The grid the simulator joins: the given one, the only one, or a choice. */
    private function grid(array $profile, ?string $nick): ?GridInfo
    {
        $etcRoot = $profile['EtcRoot'];
        if ($nick === null) {
            $nicks = [];
            foreach (glob("$etcRoot/grids/*", GLOB_ONLYDIR) ?: [] as $dir) {
                if (GridState::robustIni($etcRoot, basename($dir)) !== null) {
                    $nicks[] = basename($dir);
                }
            }
            if ($nicks === []) {
                $this->ui->error('Create a grid first.');

                return null;
            }
            $nick = count($nicks) === 1
                ? $nicks[0]
                : $this->ui->choose('Grid of the simulator', array_combine($nicks, $nicks), $nicks[0]);
        }

        $grid = GridInfo::load($profile, $nick);
        if ($grid === null) {
            $this->ui->error("Grid '$nick' has no Robust config.");
        }

        return $grid;
    }

    private function gather(GridInfo $grid, array $profile, Database $database, ?string $simName): ?SimPlan
    {
        $required = static fn (string $v): ?string => trim($v) === '' ? 'This field is required.' : null;
        $numeric = static fn (string $v): ?string => ctype_digit(trim($v)) ? null : 'Enter a port number.';

        $plan = new SimPlan();
        $plan->gridNick = $grid->nick;
        $plan->gridName = $grid->name;
        $plan->gridDir = $grid->dir;
        $plan->hypergrid = $grid->hypergrid;
        $plan->baseHostname = $grid->baseHostname;
        $plan->publicPort = $grid->publicPort;
        $plan->privatePort = $grid->privatePort;
        $plan->logsDirectory = $grid->logsDirectory;

        $plan->simName = $simName ?? trim($this->ui->text('Simulator name', '', static fn (string $v): ?string => preg_match('/^[A-Za-z0-9][A-Za-z0-9 _-]*$/', trim($v)) ? null : 'Letters, digits, spaces, _ and - only.'));
        $plan->slug = GridInfo::instanceName($grid->nick . '_' . $plan->simName);

        $current = [];
        if (is_file($plan->iniPath())) {
            // Named by the caller: it is the one to modify, no need to ask
            $action = $simName !== null ? 'modify' : $this->ui->choose(
                "Simulator '{$plan->simName}' is already configured ({$plan->iniPath()}).",
                ['modify' => 'Modify its settings (its regions are kept)', 'abandon' => 'Abandon, keep it unchanged'],
                'modify',
            );
            if ($action === 'abandon') {
                $this->ui->note('Left unchanged.');

                return null;
            }
            $current = GridInfo::parse($plan->iniPath());
        }

        $plan->dataDirectory = "{$grid->dataDirectory}/{$plan->slug}";
        $plan->cacheDirectory = "{$grid->cacheDirectory}/{$plan->slug}";

        $cores = Cores::list($profile['CoreRoot'] ?? '');
        if ($cores === []) {
            $this->ui->error('No OpenSim core found.');

            return null;
        }
        $plan->coreDirectory = $this->ui->choose('OpenSim core to run this simulator', $cores, $grid->coreDirectory !== '' ? $grid->coreDirectory : null);

        $plan->httpPort = (int) $this->ui->text(
            'Simulator HTTP port',
            (string) ($current['httpPort'] ?? Ports::next(intdiv($grid->privatePort, 10) * 10 + 10)),
            $numeric,
        );

        // Its own database: the account of the grid, a database of its own
        $this->askDatabase($plan, $grid, [
            'dbHost' => $current['dbHost'] ?? $grid->dbHost,
            'dbName' => $current['dbName'] ?? $plan->slug,
            'dbUser' => $current['dbUser'] ?? $grid->dbUser,
            'dbPass' => $current['dbPass'] ?? $grid->dbPass,
        ]);

        $plan->estateName = trim($this->ui->text('Estate name', $current['estateName'] ?? "{$grid->name} Estate", $required));
        if (!$this->askOwner($plan, $grid, $database, $current['estateOwner'] ?? null)) {
            return null;
        }

        $hasRegions = (glob("{$plan->regionsDir()}/*.ini") ?: []) !== [];
        $plan->createRegion = !$hasRegions && $this->ui->confirm('Create the first region of this simulator?', true);
        if ($plan->createRegion) {
            $this->askRegion($plan, $grid, $database);
        }

        return $plan;
    }

    /**
     * The database settings, with these defaults (those of the grid's account
     * when none is given).
     *
     * @param ?array{dbHost:string,dbName:string,dbUser:string,dbPass:string} $defaults
     */
    private function askDatabase(SimPlan $plan, GridInfo $grid, ?array $defaults = null): void
    {
        $required = static fn (string $v): ?string => trim($v) === '' ? 'This field is required.' : null;
        $defaults ??= ['dbHost' => $plan->dbHost, 'dbName' => $plan->dbName, 'dbUser' => $plan->dbUser, 'dbPass' => $plan->dbPass];

        $plan->dbHost = $this->ui->text('Database host', $defaults['dbHost'], $required);
        $plan->dbName = $this->ui->text('Database name', $defaults['dbName'], $required);
        $plan->dbUser = $this->ui->text('Database user', $defaults['dbUser'], $required);
        // An account keeps its password for the whole session: the one of the grid's account is proposed
        $password = Database::recall($plan->dbHost, $plan->dbUser) ?? $defaults['dbPass'];
        $plan->dbPass = $this->ui->text('Database password', $password, $required);
        Database::remember($plan->dbHost, $plan->dbUser, $plan->dbPass);
    }

    /**
     * The owner of the estate: an account of the grid, chosen or created.
     *
     * @return bool false when abandoned
     */
    private function askOwner(SimPlan $plan, GridInfo $grid, Database $database, ?string $current): bool
    {
        $name = static fn (string $v): ?string => GridAccounts::validName(trim($v)) ? null : 'First and last name, e.g. Jane Doe.';
        $accounts = (new GridAccounts($database, $this->ui))->names($grid);

        if ($accounts === null) {
            // The database of the grid cannot be read: its account is taken on trust
            $this->ui->warn("The accounts of the grid '{$grid->nick}' cannot be read: the owner must be an existing account.");
            $plan->estateOwner = trim($this->ui->text('Estate owner (an account of the grid)', $current ?? '', $name));

            return true;
        }

        $choices = array_combine($accounts, $accounts);
        if ($choices !== []) {
            $choices['+'] = 'Create a new account in the grid';
            $choice = $this->ui->choose('Estate owner', $choices, $current !== null && isset($choices[$current]) ? $current : array_key_first($choices));
            if ($choice !== '+') {
                $plan->estateOwner = $choice;

                return true;
            }
        } else {
            $this->ui->note("The grid '{$grid->nick}' has no account yet: the owner of the estate is the first one to create.");
        }

        $plan->estateOwner = trim($this->ui->text('Name of the new account (First Last)', '', $name));
        if ($this->ui->confirm("Account {$plan->estateOwner} does not exist yet, create it in the grid '{$grid->nick}'?", true) === false) {
            $this->ui->note('Aborted — nothing changed.');

            return false;
        }
        $plan->createOwner = true;
        $plan->ownerPassword = $this->ui->secret('Password of the new account', static fn (string $v): ?string => preg_match('/^[^\s"\'\\\\]{6,}$/', $v) ? null : 'At least 6 characters, no space, quote or backslash.');
        $plan->ownerEmail = trim($this->ui->text('Email of the new account', '', static fn (string $v): ?string => preg_match('/^[^\s"\'\\\\]+@[^\s"\'\\\\]+$/', trim($v)) ? null : 'An email address.'));

        return true;
    }

    private function askRegion(SimPlan $plan, GridInfo $grid, Database $database): void
    {
        $name = static fn (string $v): ?string => preg_match('/^[A-Za-z0-9][A-Za-z0-9 ._-]{0,49}$/', trim($v)) ? null : 'Letters, digits, spaces, . _ and - only.';
        $location = static fn (string $v): ?string => preg_match('/^\d+,\d+$/', trim($v)) ? null : 'Use x,y (e.g. 1000,1000).';
        $numeric = static fn (string $v): ?string => ctype_digit(trim($v)) ? null : 'Enter a port number.';

        $plan->regionName = trim($this->ui->text('Region name', $plan->simName, $name));
        $plan->regionUuid = self::uuid();
        $plan->regionLocation = str_replace(' ', '', $this->ui->text('Region location (x,y)', $this->nextLocation($grid, $database), $location));
        $plan->regionPort = (int) $this->ui->text('Region port', (string) Ports::next($plan->httpPort + 1, [$plan->httpPort]), $numeric);
    }

    /** The first location, from 1000,1000, that no region of the grid holds. */
    private function nextLocation(GridInfo $grid, Database $database): string
    {
        $used = [];
        $rows = $database->select($grid->databasePlan(), "SELECT CONCAT(locX DIV 256, ',', locY DIV 256) FROM regions");
        foreach ($rows ?? [] as $row) {
            $used[trim($row)] = true;
        }
        foreach (glob("{$grid->dir}/sims/*/regions/*.ini") ?: [] as $file) {
            if (preg_match_all('/^\s*Location\s*=\s*(\d+)\s*,\s*(\d+)/m', (string) file_get_contents($file), $m, PREG_SET_ORDER)) {
                foreach ($m as $match) {
                    $used["{$match[1]},{$match[2]}"] = true;
                }
            }
        }

        for ($x = 1000; ; $x++) {
            if (!isset($used["$x,1000"])) {
                return "$x,1000";
            }
        }
    }

    /** A random UUID (version 4). */
    private static function uuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }

    private function showPlan(SimPlan $plan): void
    {
        $lines = [
            "  Simulator:   {$plan->simName}  ({$plan->slug})",
            "  Grid:        {$plan->gridName}  ({$plan->gridNick})",
            "  Core:        {$plan->coreDirectory}",
            "  HTTP port:   {$plan->httpPort}",
            "  Database:    {$plan->dbName} @ {$plan->dbHost} (user {$plan->dbUser})",
            "  Estate:      {$plan->estateName}, owner {$plan->estateOwner}" . ($plan->createOwner ? ' (account to create)' : ''),
        ];
        if ($plan->createRegion) {
            $lines[] = "  Region:      {$plan->regionName} at {$plan->regionLocation}, port {$plan->regionPort}";
        }
        $lines[] = "  Config:      {$plan->iniPath()}";
        $this->ui->note("Simulator plan:\n" . implode("\n", $lines));
    }
}
