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
    public function __construct(private InstallerUi $ui) {}

    /**
     * @param ?string $gridNick the grid it joins, asked when there are several and none is given
     * @param ?string $simName  the simulator to modify, asked when none is given
     */
    public function run(?string $gridNick = null, ?string $simName = null, bool $external = false): void
    {
        $profile = (new Config())->profile();
        $etcRoot = $profile['EtcRoot'] ?? '';
        if ($etcRoot === '') {
            $this->ui->error('No installed framework found. Install an OpenSim core first.');

            return;
        }

        $grid = $this->grid($profile, $gridNick, $external);
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
        Elevated::run(
            $this->ui,
            '--apply-sim',
            ['plan' => $plan->toArray(), 'profile' => $profile],
            $profile['SystemUser'],
        );
    }

    /**
     * Add a region to a simulator already configured: loaded at once when the
     * simulator runs, else the simulator is started.
     */
    public function addRegion(string $gridNick, string $simName): void
    {
        $profile = (new Config())->profile();
        $grid = $this->grid($profile, $gridNick);
        $plan = $grid === null ? null : $this->regionPlan($grid, $simName);
        if ($plan === null) {
            return;
        }

        $this->askRegion($plan, $grid, new Database($this->ui));
        if (is_file($plan->regionIni())) {
            $this->ui->error("A region is already described in {$plan->regionIni()}.");

            return;
        }
        $this->ui->note(
            "Region {$plan->regionName} at {$plan->regionLocation}, port {$plan->regionPort}, for simulator '$simName'.",
        );
        if (!$this->ui->confirm("Add region '{$plan->regionName}'?", true)) {
            $this->ui->note('Aborted — nothing changed.');

            return;
        }
        $plan->start = $this->ui->confirm('Load it now (starting the simulator when it is not running)?', true);

        if (!Elevated::needed($profile)) {
            $this->apply($plan, $profile);

            return;
        }
        Elevated::run(
            $this->ui,
            '--apply-sim',
            ['plan' => $plan->toArray(), 'profile' => $profile],
            $profile['SystemUser'],
        );
    }

    /** The plan of a region of a simulator already configured, null when it is not. */
    private function regionPlan(GridInfo $grid, string $simName): ?SimPlan
    {
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

            return null;
        }
        $plan->httpPort = (int) (GridInfo::parse($plan->iniPath())['httpPort'] ?? 0);
        // The address the regions of this simulator already announce
        foreach (glob("{$plan->regionsDir()}/*.ini") ?: [] as $file) {
            if (preg_match('/^\s*ExternalHostName\s*=\s*(\S+)/m', (string) file_get_contents($file), $m)) {
                $plan->externalHost = $m[1];
                break;
            }
        }

        return $plan;
    }

    /**
     * Change the place and the port of a region that exists: its name and its UUID
     * are its identity in the grid, they stay. Taken into account when the simulator
     * starts again.
     */
    public function reconfigureRegion(string $gridNick, string $simName, string $regionName): void
    {
        $profile = (new Config())->profile();
        $grid = $this->grid($profile, $gridNick);
        $plan = $grid === null ? null : $this->regionPlan($grid, $simName);
        if ($plan === null) {
            return;
        }
        $region = RegionState::list($plan->regionsDir())[$regionName] ?? null;
        if ($region === null || !$region['enabled']) {
            $this->ui->warn("Region '$regionName' is disabled or unknown: enable it first.");

            return;
        }
        $current = RegionState::values($region['file']);

        $plan->regionName = $regionName;
        $plan->regionUuid = $current['RegionUUID'] ?? self::uuid();
        $plan->regionSize = (int) ($current['SizeX'] ?? 256);
        $plan->externalHost = $current['ExternalHostName'] ?? $plan->externalHost;
        $plan->overwriteRegion = true;
        $plan->start = false;

        $numeric = static fn(string $v): ?string => ctype_digit(trim($v)) ? null : 'Enter a port number.';
        $plan->regionLocation = $this->askLocation($grid, new Database($this->ui), $current['Location'] ?? null);
        $plan->regionPort = (int) $this->ui->text(
            'Region port',
            $current['InternalPort'] ?? (string) $plan->regionPort,
            $numeric,
        );

        if (
            !$this->ui->confirm(
                "Change region '$regionName' to {$plan->regionLocation}, port {$plan->regionPort}?",
                true,
            )
        ) {
            $this->ui->note('Aborted — nothing changed.');

            return;
        }
        if (!Elevated::needed($profile)) {
            $this->apply($plan, $profile);
        } else {
            Elevated::run(
                $this->ui,
                '--apply-sim',
                ['plan' => $plan->toArray(), 'profile' => $profile],
                $profile['SystemUser'],
            );
        }
        $this->ui->note("Restart the simulator '$simName' to apply it: opensim restart {$plan->slug}");
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
        if ($plan->remoteGrid !== null) {
            $this->ui->note('Wrote ' . (new GridConf())->writeRemote($plan->remoteGrid));
        }
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

        foreach (
            [
                $plan->dataDirectory,
                "{$plan->dataDirectory}/registry",
                $plan->cacheDirectory,
                "{$plan->cacheDirectory}/maptiles",
                $plan->regionsDir(),
                $plan->logsDirectory,
            ]
            as $dir
        ) {
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
            $this->ui->note(
                $region !== null ? "Wrote $region" : "Region {$plan->regionName} already described, left as it is.",
            );
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
        $this->ui->note(
            $region !== null ? "Wrote $region" : "Region {$plan->regionName} already described, left as it is.",
        );
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

        // A region registers with the grid when it starts: the grid has to run (when
        // it is ours: a Robust on another machine is its administrator's)
        if (!$grid->remote) {
            [$code] = System::runShown(System::arg($opensim) . ' start ' . System::arg($grid->nick));
            if ($code !== 0) {
                $this->failed(
                    $plan,
                    "The grid '{$grid->nick}' is not running, and a simulator cannot start without it. Enable and start it first: $opensim -v start {$grid->nick}",
                );
            }
        }

        [$code, $said] = System::runShown(System::arg($opensim) . ' restart ' . System::arg($plan->slug));
        $pending = $code === 0 && str_contains($said, 'still starting');
        if ($code === 0 && !$pending) {
            if ($plan->createRegion && !$grid->remote && !$this->registered($plan, $grid)) {
                $this->failed(
                    $plan,
                    "Simulator '{$plan->simName}' runs, but the region {$plan->regionName} did not register in the grid '{$grid->nick}'.",
                );
            }
            $this->ui->note(
                "Simulator '{$plan->simName}' is running" .
                    ($plan->createRegion
                        ? ($grid->remote
                            ? ", region {$plan->regionName} started: see the grid, on its map, for its registration."
                            : ", region {$plan->regionName} is online.")
                        : '.'),
            );

            return;
        }

        $this->failed(
            $plan,
            $pending
                ? "Simulator '{$plan->simName}' is configured but was not ready after two minutes. Try: $opensim -v start {$plan->slug}"
                : "Simulator '{$plan->simName}' is configured but did not start. Try: $opensim -v start {$plan->slug}",
        );
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
            $rows = $database->select(
                $grid->databasePlan(),
                "SELECT 1 FROM regions WHERE regionName = '{$plan->regionName}'",
            );
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
        if (
            $user === '' ||
            !function_exists('posix_geteuid') ||
            posix_geteuid() !== 0 ||
            posix_getpwnam($user) === false
        ) {
            return;
        }
        foreach ($paths as $path) {
            if (is_link($path) || file_exists($path)) {
                System::run(
                    'chown -h' . ($recursive ? 'R' : '') . ' ' . System::arg($user) . ': ' . System::arg($path),
                );
            }
        }
    }

    /**
     * The grid the simulator joins: the given one, the only one, or a choice among
     * the known ones and "another grid", whose Robust is on another machine. With
     * no grid at all, the question is the address of that Robust.
     */
    private function grid(array $profile, ?string $nick, bool $external = false): ?GridInfo
    {
        $etcRoot = $profile['EtcRoot'];
        if ($external) {
            return $this->remoteGrid($profile);
        }
        if ($nick === null) {
            $known = [];
            foreach (glob("$etcRoot/grids/*", GLOB_ONLYDIR) ?: [] as $dir) {
                $name = basename($dir);
                if (GridState::robustIni($etcRoot, $name) !== null) {
                    $known[$name] = $name;
                } elseif (GridInfo::isRemote($etcRoot, $name)) {
                    $known[$name] = "$name (Robust on another machine)";
                }
            }
            if ($known === []) {
                return $this->remoteGrid($profile);
            }
            $known['+'] = 'Another grid, whose Robust is on another machine';
            $nick = $this->ui->choose('Grid of the simulator', $known, (string) array_key_first($known));
            if ($nick === '+') {
                return $this->remoteGrid($profile);
            }
        }

        $grid = GridInfo::load($profile, $nick);
        if ($grid === null) {
            $this->ui->error("Grid '$nick' is not known here.");
        }

        return $grid;
    }

    /**
     * A grid whose Robust runs elsewhere (another machine, another container): only
     * what a simulator needs to join it is asked, and kept for the next ones. The
     * grid tells its name and nick itself.
     */
    private function remoteGrid(array $profile): ?GridInfo
    {
        $required = static fn(string $v): ?string => trim($v) === '' ? 'This field is required.' : null;
        $numeric = static fn(string $v): ?string => ctype_digit(trim($v)) ? null : 'Enter a port number.';
        $etcRoot = $profile['EtcRoot'];

        $this->ui->note(
            'The simulator joins a grid whose Robust server runs on another machine (or in another container): it needs its address and its ports.',
        );
        $address = trim($this->ui->text('Address of the grid (host:port)', '', $required));
        $parts = parse_url(preg_match('#^https?://#', $address) ? $address : "http://$address") ?: [];
        $host = (string) ($parts['host'] ?? $address);
        $public = (int) ($parts['port'] ?? 8002);

        $said = $this->gridSays($host, $public);
        if ($said === []) {
            $this->ui->warn(
                "The grid did not answer at http://$host:$public/get_grid_info: check its address and its public port, or go on with what you know of it.",
            );
        }
        $name = trim($this->ui->text('Grid name', $said['gridname'] ?? ucfirst(explode('.', $host)[0]), $required));
        // Letters and digits only, as typed (nick() would lower what is already a nick)
        $nick = (string) preg_replace(
            '/[^A-Za-z0-9]/',
            '',
            $this->ui->text('Grid nick (alphanumeric)', $said['gridnick'] ?? Slug::nick($name), $required),
        );
        if ($nick === '') {
            $nick = Slug::nick($name);
        }

        if (GridState::robustIni($etcRoot, $nick) !== null) {
            $this->ui->error(
                "A grid of this machine already has the nick '$nick': it is its own Robust, not another one.",
            );

            return null;
        }
        if (GridInfo::isRemote($etcRoot, $nick)) {
            $this->ui->note("Grid '$nick' is already known here, its description is kept.");

            return GridInfo::load($profile, $nick);
        }

        $private = (int) $this->ui->text(
            'Private port of the grid (only for its simulators, and this machine is one)',
            (string) ($public + 1),
            $numeric,
        );
        $hypergrid = $this->ui->confirm('Does the grid use Hypergrid?', true);

        $grid = new GridInfo();
        $grid->remote = true;
        $grid->nick = $nick;
        $grid->name = $name;
        $grid->slug = Slug::slug($name);
        $grid->dir = "$etcRoot/grids/$nick";
        $grid->hypergrid = $hypergrid;
        $grid->baseHostname = $host;
        $grid->publicPort = $public;
        $grid->privatePort = $private;
        $grid->coreDirectory = $profile['CoreDirectory'] ?? '';
        $grid->dataDirectory = ($profile['DataRoot'] ?? '') . "/$nick";
        $grid->cacheDirectory = ($profile['CacheRoot'] ?? '') . "/$nick";
        $grid->logsDirectory = $profile['LogsRoot'] ?? '';

        return $grid;
    }

    /**
     * What a grid says of itself at its public port (get_grid_info).
     *
     * @return array<string,string> gridname, gridnick..., empty when it does not answer
     */
    private function gridSays(string $host, int $port): array
    {
        $body = @file_get_contents(
            "http://$host:$port/get_grid_info",
            false,
            stream_context_create(['http' => ['timeout' => 5]]),
        );
        $said = [];
        foreach (['gridname', 'gridnick'] as $key) {
            if ($body !== false && preg_match("#<$key>([^<]+)</$key>#", $body, $m)) {
                $said[$key] = trim(html_entity_decode($m[1], ENT_QUOTES | ENT_XML1));
            }
        }

        return $said;
    }

    private function gather(GridInfo $grid, array $profile, Database $database, ?string $simName): ?SimPlan
    {
        $required = static fn(string $v): ?string => trim($v) === '' ? 'This field is required.' : null;
        $numeric = static fn(string $v): ?string => ctype_digit(trim($v)) ? null : 'Enter a port number.';

        $plan = new SimPlan();
        $plan->gridNick = $grid->nick;
        $plan->gridName = $grid->name;
        $plan->gridDir = $grid->dir;
        $plan->hypergrid = $grid->hypergrid;
        $plan->baseHostname = $grid->baseHostname;
        $plan->publicPort = $grid->publicPort;
        $plan->privatePort = $grid->privatePort;
        $plan->logsDirectory = $grid->logsDirectory;
        // A remote grid not kept yet is written with the simulator
        $plan->remoteGrid = $grid->remote && !is_file("{$grid->dir}/{$grid->nick}.conf") ? $grid->describe() : null;

        $plan->simName =
            $simName ??
            trim(
                $this->ui->text(
                    'Simulator name',
                    '',
                    static fn(string $v): ?string => preg_match('/^[A-Za-z0-9][A-Za-z0-9 _-]*$/', trim($v))
                        ? null
                        : 'Letters, digits, spaces, _ and - only.',
                ),
            );
        $plan->slug = GridInfo::instanceName($grid->nick . '_' . $plan->simName);

        $current = [];
        if (is_file($plan->iniPath())) {
            // Named by the caller: it is the one to modify, no need to ask
            $action =
                $simName !== null
                    ? 'modify'
                    : $this->ui->choose(
                        "Simulator '{$plan->simName}' is already configured ({$plan->iniPath()}).",
                        [
                            'modify' => 'Modify its settings (its regions are kept)',
                            'abandon' => 'Abandon, keep it unchanged',
                        ],
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
        $plan->coreDirectory = $this->ui->choose(
            'OpenSim core to run this simulator',
            $cores,
            $grid->coreDirectory !== '' ? $grid->coreDirectory : null,
        );

        // The ports of a simulator are a block of ten, the first free one from where the
        // simulators of the grid start (9000 for Robust on 8002), on this machine and among
        // the regions the grid knows on the others: its HTTP port is the first one, the
        // console is x4, the regions take the others
        $foreign = (new GridRegistry($database))->ports($grid) ?? [];
        $block = Ports::nextBlock(Ports::simulatorsFrom($grid->publicPort), $foreign);
        $plan->httpPort = (int) $this->ui->text(
            'Simulator HTTP port',
            (string) ($current['httpPort'] ?? $block),
            $numeric,
        );
        $base = Ports::simulatorBlock($plan->httpPort);
        $plan->consolePort = $base !== null ? $base + 4 : 0;

        // What the regions announce as their address, the viewers connect to it: the
        // public name of this machine. SYSTEMIP is the address of its first interface,
        // which is not the one of the world behind a NAT or in a container.
        $plan->externalHost = trim(
            $this->ui->text(
                'Public address of this machine, for the regions (SYSTEMIP: its first interface)',
                $current['externalHost'] ?? 'SYSTEMIP',
                static fn(string $v): ?string => trim($v) === '' ? 'This field is required.' : null,
            ),
        );

        $this->askConsole($plan, $current);

        // Its own database: the account of the grid, a database of its own
        $this->askDatabase($plan, $grid, [
            'dbHost' => $current['dbHost'] ?? $grid->dbHost,
            'dbName' => $current['dbName'] ?? $plan->slug,
            'dbUser' => $current['dbUser'] ?? $grid->dbUser,
            'dbPass' => $current['dbPass'] ?? ($grid->dbPass !== '' ? $grid->dbPass : self::password(20)),
        ]);

        $plan->estateName = trim(
            $this->ui->text('Estate name', $current['estateName'] ?? "{$grid->name} Estate", $required),
        );
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
     * The console of the simulator: remote (REST, through its port x4) or a screen
     * session. What an existing config has is kept, password included.
     *
     * @param array<string,string|int> $current
     */
    private function askConsole(SimPlan $plan, array $current): void
    {
        $plan->consoleMode = $this->ui->choose(
            'Console of the simulator',
            [
                'rest' => 'Remote REST console (recommended)',
                'screen' => 'Screen session (on this machine)',
            ],
            isset($current['consoleUser']) || $current === [] ? 'rest' : 'screen',
        );
        if ($plan->consoleMode !== 'rest') {
            return;
        }

        if ($plan->consolePort === 0) {
            $plan->consolePort = (int) $this->ui->text(
                'Console port',
                (string) ($current['consolePort'] ?? Ports::next($plan->httpPort + 1)),
                static fn(string $v): ?string => ctype_digit(trim($v)) ? null : 'Enter a port number.',
            );
        } else {
            $plan->consolePort = (int) ($current['consolePort'] ?? $plan->consolePort);
        }
        // As the helpers make theirs: 12 lower case letters, 32 letters and digits; the host
        // clients reach it by is the public address of the machine, when it is known
        $plan->consoleHost =
            (string) ($current['consoleHost'] ?? ($plan->externalHost !== 'SYSTEMIP' ? $plan->externalHost : ''));
        $plan->consoleUser = (string) ($current['consoleUser'] ?? self::letters(12));
        $plan->consolePass = (string) ($current['consolePass'] ?? self::password(32));
    }

    private static function letters(int $length): string
    {
        $letters = '';
        for ($i = 0; $i < $length; $i++) {
            $letters .= chr(random_int(97, 122));
        }

        return $letters;
    }

    private static function password(int $length = 24): string
    {
        $alphabet = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $password = '';
        for ($i = 0; $i < $length; $i++) {
            $password .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        return $password;
    }

    /**
     * The database settings, with these defaults (those of the grid's account
     * when none is given).
     *
     * @param ?array{dbHost:string,dbName:string,dbUser:string,dbPass:string} $defaults
     */
    private function askDatabase(SimPlan $plan, GridInfo $grid, ?array $defaults = null): void
    {
        $required = static fn(string $v): ?string => trim($v) === '' ? 'This field is required.' : null;
        $defaults ??= [
            'dbHost' => $plan->dbHost,
            'dbName' => $plan->dbName,
            'dbUser' => $plan->dbUser,
            'dbPass' => $plan->dbPass,
        ];

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
        $name = static fn(string $v): ?string => GridAccounts::validName(trim($v))
            ? null
            : 'First and last name, e.g. Jane Doe.';
        $accounts = $grid->remote ? null : (new GridAccounts($database, $this->ui))->names($grid);

        if ($accounts === null && $grid->remote) {
            $this->ui->note(
                "The estate is owned by an account of the grid '{$grid->nick}', which has to exist already (its administrator makes it on its Robust).",
            );
            $plan->estateOwner = trim($this->ui->text('Estate owner (an account of the grid)', $current ?? '', $name));

            return true;
        }
        if ($accounts === null) {
            // The database of the grid cannot be read: its account is taken on trust
            $this->ui->warn(
                "The accounts of the grid '{$grid->nick}' cannot be read: the owner must be an existing account.",
            );
            $plan->estateOwner = trim($this->ui->text('Estate owner (an account of the grid)', $current ?? '', $name));

            return true;
        }

        $choices = array_combine($accounts, $accounts);
        if ($choices !== []) {
            $choices['+'] = 'Create a new account in the grid';
            $choice = $this->ui->choose(
                'Estate owner',
                $choices,
                $current !== null && isset($choices[$current]) ? $current : array_key_first($choices),
            );
            if ($choice !== '+') {
                $plan->estateOwner = $choice;

                return true;
            }
        } else {
            $this->ui->note(
                "The grid '{$grid->nick}' has no account yet: the owner of the estate is the first one to create.",
            );
        }

        $plan->estateOwner = trim($this->ui->text('Name of the new account (First Last)', '', $name));
        if (
            $this->ui->confirm(
                "Account {$plan->estateOwner} does not exist yet, create it in the grid '{$grid->nick}'?",
                true,
            ) === false
        ) {
            $this->ui->note('Aborted — nothing changed.');

            return false;
        }
        $plan->createOwner = true;
        $plan->ownerPassword = $this->ui->secret(
            'Password of the new account',
            static fn(string $v): ?string => preg_match('/^[^"\r\n]{6,}$/', $v)
                ? null
                : 'At least 6 characters, without double quote.',
        );
        $plan->ownerEmail = trim(
            $this->ui->text(
                'Email of the new account',
                '',
                static fn(string $v): ?string => preg_match('/^[^\s"\'\\\\]+@[^\s"\'\\\\]+$/', trim($v))
                    ? null
                    : 'An email address.',
            ),
        );

        return true;
    }

    private function askRegion(SimPlan $plan, GridInfo $grid, Database $database): void
    {
        // A region name is unique in the grid: the same name registered twice stops the simulator
        $taken = (new GridRegistry($database))->names($grid);
        $name = static function (string $v) use ($taken, $grid): ?string {
            if (($problem = RegionName::problem($v)) !== null) {
                return $problem;
            }
            if (
                isset($taken[strtolower(trim($v))]) ||
                ($grid->remote && RobustGrid::hasRegion($grid->baseHostname, $grid->privatePort, trim($v)) === true)
            ) {
                return 'The grid has a region of this name already.';
            }

            return null;
        };
        $numeric = static fn(string $v): ?string => ctype_digit(trim($v)) ? null : 'Enter a port number.';

        // The default region of a grid run from this machine is the first one created: until it
        // exists nobody can log in. Its name is the grid's to give (changed in the grid setup)
        if ($grid->defaultRegionDue($taken)) {
            $plan->regionName = $grid->defaultRegion;
            $this->ui->note(
                "This region is the default region of the grid, '{$plan->regionName}': it comes first, nobody can log in without it. Another name is set in the setup of the grid.",
            );
        } else {
            $plan->regionName = trim($this->ui->text('Region name', $plan->simName, $name));
        }
        $plan->regionUuid = self::uuid();
        $plan->regionLocation = $this->askLocation($grid, $database);
        $plan->regionPort = (int) $this->ui->text(
            'Region port',
            (string) $this->nextRegionPort($plan, $grid, $database),
            $numeric,
        );
    }

    /**
     * The place of the region: the one asked (the first place of a grid by default),
     * or the free place nearest to it by the rule of the grid, the free blocks it
     * leaves between its regions.
     */
    private function askLocation(GridInfo $grid, Database $database, ?string $current = null): string
    {
        $known = (new GridRegistry($database))->locations($grid);
        // A region that moves frees the place it holds
        $ignored =
            $current !== null && ($place = LocationFinder::parse($current)) !== null
                ? LocationFinder::key($place[0], $place[1])
                : null;
        if ($ignored !== null) {
            unset($known[$ignored]);
        }
        $validate = static fn(string $v): ?string => LocationFinder::parse($v) === null
            ? 'Use x,y (e.g. 1000,1000).'
            : null;

        while (true) {
            [$x, $y] =
                LocationFinder::parse(
                    $this->ui->text(
                        'Region location (x,y)',
                        $current ?? implode(',', LocationFinder::FIRST),
                        $validate,
                    ),
                ) ?? LocationFinder::FIRST;
            [$freeX, $freeY] = $this->freePlace($grid, $known, $x, $y, $ignored);
            if ($freeX === $x && $freeY === $y) {
                return "$x,$y";
            }
            $why =
                $grid->regionSpacing > 0
                    ? "taken or too close to a region (the grid leaves {$grid->regionSpacing} free block(s) between them)"
                    : 'taken';
            $this->ui->note("The place $x,$y is $why: the nearest free one is $freeX,$freeY.");
            if ($this->ui->confirm("Use $freeX,$freeY?", true)) {
                return "$freeX,$freeY";
            }
        }
    }

    /**
     * The free place nearest to a place, by the rule of the grid (see Places).
     *
     * @param array<string,true> $known the places taken that are known here
     * @return array{0:int,1:int}
     */
    private function freePlace(GridInfo $grid, array $known, int $x, int $y, ?string $ignored = null): array
    {
        $unreachable = false;
        $place = Places::nearestFree($grid, $known, $x, $y, $ignored, $unreachable);
        if ($unreachable) {
            $this->ui->warn(
                "The grid does not answer at {$grid->baseHostname}:{$grid->privatePort}: its regions are not known here, ask its owner which place is free.",
            );
        }

        return $place;
    }

    /** The port of a region: the next one of the block of its simulator, else the next free one. */
    private function nextRegionPort(SimPlan $plan, GridInfo $grid, Database $database): int
    {
        $taken = array_merge((new GridRegistry($database))->ports($grid) ?? [], [$plan->httpPort]);

        return Ports::nextRegion($plan->httpPort, $taken) ?? Ports::next($plan->httpPort + 1, $taken);
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
            '  Console:     ' .
            ($plan->consoleMode === 'rest'
                ? "remote, port {$plan->consolePort}, user {$plan->consoleUser}"
                : 'screen session'),
            "  Database:    {$plan->dbName} @ {$plan->dbHost} (user {$plan->dbUser})",
            "  Estate:      {$plan->estateName}, owner {$plan->estateOwner}" .
            ($plan->createOwner ? ' (account to create)' : ''),
        ];
        if ($plan->createRegion) {
            $lines[] = "  Region:      {$plan->regionName} at {$plan->regionLocation}, port {$plan->regionPort}";
        }
        $lines[] = "  Config:      {$plan->iniPath()}";
        $this->ui->note("Simulator plan:\n" . implode("\n", $lines));
    }
}
