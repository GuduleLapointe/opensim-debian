<?php

declare(strict_types=1);

namespace OpenSim\Installer\Grid;

use OpenSim\Installer\Config;
use OpenSim\Installer\Elevated;
use OpenSim\Installer\Ports;
use OpenSim\Installer\Setup\SetupFile;
use OpenSim\Installer\SetupFailed;
use OpenSim\Installer\System;
use OpenSim\Installer\TextFile;
use OpenSim\Installer\Ui\InstallerUi;
use OpenSim\Installer\Web\HelpersConfig;
use OpenSim\Installer\Web\Services;
use OpenSim\Installer\Web\Snippets;

/**
 * Configure (or reconfigure) one grid on top of an installed framework.
 *
 * Reusable flow driven through an InstallerUi: the setup hub calls run() with a
 * grid nick to modify, or null to create a new one. Returns to the caller when
 * done (never exits the process). Phase 2a: gather only.
 */
final class NewGrid
{
    public function __construct(private InstallerUi $ui) {}

    /** @return ?string the nick of the grid configured, null when nothing was */
    public function run(?string $modifyNick = null): ?string
    {
        $profile = (new Config())->profile();
        if ($profile === [] || ($profile['EtcRoot'] ?? '') === '') {
            $this->ui->error(_('No installed framework found. Install an OpenSim core first.'));

            return null;
        }

        $plan = $this->gather($profile, $modifyNick);
        if ($plan === null) {
            return null; // abandoned
        }

        // Nothing is written before the database is known to work. A problem of
        // access leaves the error on screen with one line to try again (the
        // settings can be changed); a creation that failed ends the setup.
        $database = new Database($this->ui);
        while (($result = $database->ensure($plan)) !== Database::OK) {
            if ($result === Database::ABORT) {
                $this->ui->error(_('Stopped, nothing was changed: OpenSim cannot run without its database.'));

                throw new SetupFailed('database');
            }
            if (!$this->ui->confirm(_('Try again (the database settings can be changed)?'), true)) {
                $this->ui->note(_('Stopped, nothing was changed: OpenSim cannot run without its database.'));

                return null;
            }
            $this->askDatabase($plan, [
                'dbHost' => $plan->dbHost,
                'dbName' => $plan->dbName,
                'dbUser' => $plan->dbUser,
                'dbPass' => $plan->dbPass,
            ]);
        }

        $this->showPlan($plan);
        if (!$this->ui->confirm(sprintf(_("Apply this configuration to grid '%s'?"), $plan->gridNick), true)) {
            $this->ui->note(_('Aborted — nothing changed.'));

            return null;
        }

        // What to do once written is asked here, while the user is at the
        // keyboard: writing may be done by another process
        $plan->enable = $this->ui->confirm(sprintf(_("Enable grid '%s' (link into robust.d)?"), $plan->gridNick), true);
        $plan->start = $plan->enable && $this->ui->confirm(sprintf(_("Start grid '%s' now?"), $plan->gridNick), true);

        $this->write($plan, $profile);

        return $plan->gridNick;
    }

    /**
     * Write the grid as the user who owns the install. The questions, and the
     * database (with the rights of the user who started the setup, whatever
     * they are), are handled by this process; only the files and the
     * instances belong to the system user of the install (the packages). A
     * process of that user does the writing, unless this one already is that
     * user, or root, or the install has none.
     */
    private function write(GridPlan $plan, array $profile): void
    {
        if (!Elevated::needed($profile)) {
            $this->apply($plan, $profile);

            return;
        }

        Elevated::run(
            $this->ui,
            '--apply-grid',
            ['plan' => $plan->toArray(), 'profile' => $profile],
            $profile['SystemUser'],
        );
    }

    /** The writing itself: run as the system user of the install, root, or the user of an install without one. */
    public function apply(GridPlan $plan, array $profile): void
    {
        $etcRoot = $profile['EtcRoot'];
        $this->makeDirs($plan);
        $conf = (new GridConf())->write($plan);
        $this->ui->note(sprintf(_("Wrote %s"), $conf));
        $this->writeRobust($plan);
        $this->writeHelpers($plan);
        $this->copyConfigInclude($plan);

        $logConfig = (new LogConfig())->write($plan);
        if ($logConfig !== null) {
            $this->ui->note(sprintf(_("Wrote %s"), $logConfig));
        }

        // What was asked is kept, to make the same grid again from a file (opensim setup --file)
        SetupFile::record($plan->gridDir, static fn(array $data): array => SetupFile::withGrid($data, $plan));

        $systemUser = $profile['SystemUser'] ?? '';
        $this->giveToSystemUser($systemUser, [$plan->etcDirectory, $plan->dataDirectory, $plan->cacheDirectory], true);

        if ($plan->enable) {
            if (GridState::enable($etcRoot, $plan->gridNick)) {
                $this->giveToSystemUser($systemUser, [GridState::link($etcRoot, $plan->gridNick)], false);
                $this->ui->note(sprintf(_('Enabled: %s'), GridState::link($etcRoot, $plan->gridNick)));
                if ($plan->start) {
                    $this->startGrid($plan);
                }
            } else {
                $this->ui->warn(_('Could not enable the grid (Robust config missing).'));
            }
        }

        $this->ui->note(sprintf(_("Grid '%s' configured."), $plan->gridName));
        if ($plan->helpers) {
            $this->ui->note(
                sprintf(
                    _("Web site: the helpers are in %s, the placeholder site in /usr/share/opensim-web/html (when the opensim-web package is installed). The configuration for your web server (Caddy, nginx or Apache): opensim web snippet <caddy|nginx|apache> --grid %s"),
                    Snippets::WEBROOT,
                    $plan->gridNick,
                ),
            );
        }
    }

    /**
     * When the setup runs as root for an install with a system user (the
     * packages), what it creates belongs to that user, who runs the
     * instances. Nothing to do otherwise.
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
     * Start the grid and report whether it came up, by the result of the
     * launcher (a screen session is per user: the setup may not be the one
     * running the instances). A grid that does not start ends the setup.
     */
    private function startGrid(GridPlan $plan): void
    {
        $opensim = dirname(__DIR__, 3) . '/bin/opensim';
        $nick = $plan->gridNick;

        [$code, $said] = System::runShown(System::arg($opensim) . ' restart ' . System::arg($nick));
        // The launcher waits for the ready signal in the console. After its
        // delay it says the instance is still starting, and succeeds: a
        // healthy Robust is ready within a minute, so that is a failure too.
        $pending = $code === 0 && str_contains($said, 'still starting');
        if ($code === 0 && !$pending) {
            $this->ui->note(sprintf(_("Grid '%s' is running."), $nick));

            return;
        }

        $this->ui->error(
            $pending
                ? sprintf(_("Grid '%s' is configured but was not ready after two minutes. Try: %s -v start %s"), $nick, $opensim, $nick)
                : sprintf(_("Grid '%s' is configured but did not start. Try: %s -v start %s"), $nick, $opensim, $nick),
        );
        $log = $plan->logsDirectory . '/' . $plan->gridSlug . '_robust.log';
        if (is_file($log)) {
            $this->ui->note(_('Recent log:'));
            System::run('tail -n 30 ' . System::arg($log));
        }

        throw new SetupFailed("Grid '$nick' did not start.");
    }

    private function makeDirs(GridPlan $plan): void
    {
        $dirs = [
            $plan->dataDirectory,
            "{$plan->dataDirectory}/fsassets",
            "{$plan->dataDirectory}/fsassets/data",
            "{$plan->dataDirectory}/maptiles",
            "{$plan->dataDirectory}/registry",
            $plan->cacheDirectory,
            "{$plan->cacheDirectory}/bakes",
            "{$plan->cacheDirectory}/fsassets",
            "{$plan->cacheDirectory}/fsassets/tmp",
            "{$plan->cacheDirectory}/maptiles",
            $plan->etcDirectory,
            "{$plan->etcDirectory}/assets",
            "{$plan->etcDirectory}/config-include",
            "{$plan->etcDirectory}/sims",
            "{$plan->etcDirectory}/inventory",
            "{$plan->etcDirectory}/robust-include",
            $plan->logsDirectory,
        ];
        foreach ($dirs as $dir) {
            if ($dir !== '' && !is_dir($dir)) {
                @mkdir($dir, 0o755, true);
            }
        }
    }

    private function writeRobust(GridPlan $plan): void
    {
        $path = $plan->robustIni();
        if (is_file($path)) {
            @copy($path, "$path~"); // backup
        }
        file_put_contents($path, (new RobustConfig())->generate($plan));
        $this->ui->note(sprintf(_("Wrote %s"), $path));
    }

    /**
     * The helpers.ini of the grid: what opensim-helpers needs, so the web server does not have to read the Robust
     * config. What the operator changed in it is kept. Readable by the group of the web server when this process
     * can give it, else by everybody who can reach the folder of the grid.
     */
    private function writeHelpers(GridPlan $plan): void
    {
        if (!$plan->helpers) {
            return;
        }
        $path = HelpersConfig::path($plan->gridDir);
        $text = HelpersConfig::render(
            is_file($path) ? TextFile::read($path) : '',
            [
                'gridName' => $plan->gridName,
                'loginUri' => "http://{$plan->baseHostname}:{$plan->publicPort}",
                'webUrl' => $plan->webUrl,
                'mailSender' => '',
                'dbHost' => $plan->dbHost,
                'dbName' => $plan->dbName,
                'dbUser' => $plan->dbUser,
                'dbPass' => $plan->dbPass,
            ],
            $plan->helpersPath,
        );
        file_put_contents($path, $text);
        $group = function_exists('posix_getgrnam') ? posix_getgrnam('www-data') : false;
        if ($group !== false && @chgrp($path, $group['gid'])) {
            chmod($path, 0o640);
        } else {
            chmod($path, 0o644);
            $this->ui->warn(sprintf(_("%s holds the database password and is readable by every user of this machine: give it to the group of your web server (chgrp, then chmod 640)."), $path));
        }
        $this->ui->note(sprintf(_("Wrote %s"), $path));
    }

    private function copyConfigInclude(GridPlan $plan): void
    {
        $files = [
            'OpenSimDefaults.ini',
            'OpenSim.ini',
            'config-include/GridHypergrid.ini',
            'config-include/GridCommon.ini',
            'config-include/FlotsamCache.ini',
            'config-include/osslDefaultEnable.ini',
            'config-include/osslEnable.ini',
        ];
        foreach ($files as $file) {
            $dest = "{$plan->etcDirectory}/$file";
            if (is_file($dest)) {
                TextFile::clean($dest); // local changes are kept, only the line endings are made Unix ones
                continue;
            }
            $src = is_file("{$plan->binDir}/{$file}.example")
                ? "{$plan->binDir}/{$file}.example"
                : "{$plan->binDir}/$file";
            if (!is_file($src)) {
                continue;
            }
            @mkdir(dirname($dest), 0o755, true);
            TextFile::copy($src, $dest);
        }
        // Ready for the simulators that will join the grid
        (new GridShared())->prepare($plan->etcDirectory, $plan->binDir, $plan->enableHypergrid);
        $this->ui->note(_('Copied config-include defaults.'));
    }

    private function gather(array $profile, ?string $modifyNick): ?GridPlan
    {
        $required = static fn(string $v): ?string => trim($v) === '' ? 'This field is required.' : null;
        $numeric = static fn(string $v): ?string => ctype_digit(trim($v)) ? null : _('Enter a port number.');

        $etcRoot = $profile['EtcRoot'];

        // Identify the grid and load its existing config, if any.
        if ($modifyNick !== null) {
            $nick = $modifyNick;
            $gridDir = "$etcRoot/grids/$nick";
            $existing = $this->findExisting($gridDir);
            $current = $existing !== null ? $this->parseExisting($existing) : [];
            $name = $this->ui->text(_('Grid name'), $current['gridName'] ?? ucfirst($nick), $required);
        } else {
            $name = $this->ui->text(_('Grid name'), $this->defaultName(), $required);
            $nick = $this->ui->text(_('Grid nick (snake_case)'), Slug::nick($name), $required);
            $gridDir = "$etcRoot/grids/$nick";
            $existing = $this->findExisting($gridDir);
            $current = [];
            if ($existing !== null) {
                $action = $this->ui->choose(
                    sprintf(_("Grid '%s' is already configured (%s)."), $nick, $existing),
                    ['modify' => _('Modify its settings'), 'abandon' => _('Abandon, keep it unchanged')],
                    'modify',
                );
                if ($action === 'abandon') {
                    $this->ui->note(_('Left unchanged.'));

                    return null;
                }
                $current = $this->parseExisting($existing);
            }
        }

        $plan = new GridPlan();
        $plan->gridName = $name;
        $plan->gridNick = $nick;
        $plan->gridSlug = Slug::slug($name);
        $plan->gridDir = $gridDir;
        $plan->etcDirectory = $gridDir;
        $plan->dataDirectory = "{$profile['DataRoot']}/$nick";
        $plan->cacheDirectory = "{$profile['CacheRoot']}/$nick";
        $plan->logsDirectory = $profile['LogsRoot'] ?? '';

        // Hypergrid — after the existence check; default from the existing file.
        $hgDefault = $existing !== null ? str_contains(basename($existing), '.HG.') : true;
        $plan->enableHypergrid = $this->ui->confirm(_('Enable Hypergrid?'), $hgDefault);

        // The rule that places the regions: free blocks between them
        $plan->regionSpacing = (int) $this->ui->text(
            _('Free blocks between regions (0: side by side)'),
            '0',
            static fn(string $v): ?string => ctype_digit(trim($v)) && (int) $v <= 50
                ? null
                : _('A number of blocks, 0 to 50.'),
        );

        // Core selection (multi-version aware).
        $coreRoot = $profile['CoreRoot'] ?? '';
        $cores = Cores::list($coreRoot);
        if ($cores === []) {
            $this->ui->error(sprintf(_("No OpenSim core found under %s."), $coreRoot));

            return null;
        }
        $plan->coreDirectory = $this->ui->choose(
            _('OpenSim core to run this grid'),
            $cores,
            $profile['CoreDirectory'] ?? null,
        );
        $plan->binDir = $plan->coreDirectory . '/bin';

        $defaultHost = $current['baseHostname'] ?? (trim(System::capture('hostname -f')[1]) ?: 'localhost');
        $plan->baseHostname = $this->ui->text(_('Base hostname'), $defaultHost, $required);
        // The ports of an instance are a block of ten, the first free one (see Ports):
        // public ends with 2, private with 3, the console with 4
        $block = Ports::nextBlock(8000);
        $plan->publicPort = (int) $this->ui->text(
            _('Public port'),
            (string) ($current['publicPort'] ?? $block + 2),
            $numeric,
        );
        $plan->privatePort = (int) $this->ui->text(
            _('Private port'),
            (string) ($current['privatePort'] ??
                ($plan->publicPort % 10 === 2 ? $plan->publicPort + 1 : Ports::next($plan->publicPort + 1))),
            $numeric,
        );
        $plan->webUrl = $this->ui->text(_('Web URL'), $current['webUrl'] ?? "https://{$plan->baseHostname}", $required);
        $this->askHelpers($plan, $gridDir);

        $this->askConsole($plan, $current, $numeric);

        // Database: reuse a found password, otherwise generate one (never changeme).
        $foundPass = $current['dbPass'] ?? '';
        $this->askDatabase($plan, [
            'dbHost' => $current['dbHost'] ?? ($profile['DataSource'] ?? 'localhost'),
            'dbName' => $current['dbName'] ?? strtolower($nick) . '_robust',
            'dbUser' => $current['dbUser'] ?? 'opensim',
            'dbPass' => $foundPass !== '' && $foundPass !== 'changeme' ? $foundPass : $this->randomPassword(),
        ]);

        return $plan;
    }

    /**
     * The helpers of the grid: the economy, the search and the offline messages the viewers use, served by the web
     * site with opensim-helpers (see `opensim web`). Where they are on the web site is the operator's choice, the
     * paths of each service too (helpers.ini).
     */
    private function askHelpers(GridPlan $plan, string $gridDir): void
    {
        $existing = HelpersConfig::read($gridDir);
        $plan->helpers = $this->ui->confirm(
            _('Serve the economy and the search of the grid with opensim-helpers?'),
            $existing !== [] || is_dir(Snippets::WEBROOT),
        );
        if (!$plan->helpers) {
            return;
        }
        $plan->helpersUrls = $existing['Urls'] ?? [];
        // The path is not asked for now: what the grid already has, else /helpers
        $plan->helpersPath = Services::normalize($existing['Helpers']['path'] ?? HelpersConfig::DEFAULT_PATH);
        $this->ui->note(
            sprintf(_('Helpers URL: %s (the viewers add the name of the script)'), rtrim($plan->webUrl, '/') . $plan->helpersPath),
        );
    }

    /**
     * The console of the grid: remote (REST, through its port x4: reachable from
     * another machine or a container) or a screen session to attach here. What an
     * existing config has is kept, password included.
     *
     * @param array<string,string|int> $current
     */
    private function askConsole(GridPlan $plan, array $current, \Closure $numeric): void
    {
        $plan->consoleMode = $this->ui->choose(
            _('Console of the grid'),
            [
                'rest' => _('Remote REST console (recommended)'),
                'screen' => _('Screen session (on this machine)'),
            ],
            isset($current['consoleUser']) || $current === [] ? 'rest' : 'screen',
        );
        if ($plan->consoleMode !== 'rest') {
            return;
        }

        $base = $plan->publicPort % 10 === 2 ? $plan->publicPort - 2 : 0;
        $plan->consolePort = (int) $this->ui->text(
            _('Console port'),
            (string) ($current['consolePort'] ?? ($base > 0 ? $base + 4 : Ports::next($plan->privatePort + 1))),
            $numeric,
        );
        // As the helpers make theirs: 12 lower case letters, 32 letters and digits
        $plan->consoleHost = (string) ($current['consoleHost'] ?? $plan->baseHostname);
        $plan->consoleUser = (string) ($current['consoleUser'] ?? $this->randomLetters(12));
        $plan->consolePass = (string) ($current['consolePass'] ?? $this->randomPassword(32));
    }

    /**
     * The database settings, with these defaults.
     *
     * @param array{dbHost:string,dbName:string,dbUser:string,dbPass:string} $defaults
     */
    private function askDatabase(GridPlan $plan, array $defaults): void
    {
        $required = static fn(string $v): ?string => trim($v) === '' ? 'This field is required.' : null;

        $plan->dbHost = $this->ui->text(_('Database host'), $defaults['dbHost'], $required);
        $plan->dbName = $this->ui->text(_('Database name'), $defaults['dbName'], $required);
        $plan->dbUser = $this->ui->text(_('Database user'), $defaults['dbUser'], $required);
        // An account keeps its password for the whole session, entered or
        // generated once: an attempt started again proposes the same one
        $password = Database::recall($plan->dbHost, $plan->dbUser) ?? $defaults['dbPass'];
        $plan->dbPass = $this->ui->text(_('Database password'), $password, $required);
        Database::remember($plan->dbHost, $plan->dbUser, $plan->dbPass);
    }

    /** Existing Robust config for a grid (HG preferred), or null. */
    private function findExisting(string $gridDir): ?string
    {
        foreach (['Robust.HG.ini', 'Robust.ini'] as $file) {
            if (is_file("$gridDir/$file")) {
                return "$gridDir/$file";
            }
        }

        return null;
    }

    /** Default grid name: the short hostname, capitalised (amy.magiiic.com -> Amy). */
    private function defaultName(): string
    {
        [, $host] = System::capture('hostname -s');
        $short = trim($host);
        if ($short === '') {
            [, $fqdn] = System::capture('hostname -f');
            $short = strtok(trim($fqdn), '.') ?: 'My';
        }

        return ucfirst($short);
    }

    /** Extract current settings from an existing Robust config. */
    private function parseExisting(string $path): array
    {
        return GridInfo::parse($path);
    }

    private function randomLetters(int $length): string
    {
        $letters = '';
        for ($i = 0; $i < $length; $i++) {
            $letters .= chr(random_int(97, 122));
        }

        return $letters;
    }

    private function randomPassword(int $length = 20): string
    {
        $alphabet = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $password = '';
        for ($i = 0; $i < $length; $i++) {
            $password .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        return $password;
    }

    private function showPlan(GridPlan $plan): void
    {
        $lines = [
            "  Grid:        {$plan->gridName}  ({$plan->gridNick})",
            '  Hypergrid:   ' . ($plan->enableHypergrid ? 'yes' : 'no'),
            '  Regions:     ' .
            ($plan->regionSpacing === 0 ? 'side by side' : "{$plan->regionSpacing} free block(s) between them"),
            "  Core:        {$plan->coreDirectory}",
            "  Hostname:    {$plan->baseHostname}",
            "  Ports:       public {$plan->publicPort} / private {$plan->privatePort}",
            '  Console:     ' .
            ($plan->consoleMode === 'rest'
                ? "remote, port {$plan->consolePort}, user {$plan->consoleUser}"
                : 'screen session'),
            "  Web URL:     {$plan->webUrl}",
            '  Helpers:     ' . ($plan->helpers ? "served at {$plan->webUrl}{$plan->helpersPath}" : 'not served by this web site'),
            "  Database:    {$plan->dbName} @ {$plan->dbHost} (user {$plan->dbUser})",
            "  Robust ini:  {$plan->robustIni()}",
            "  Grid dir:    {$plan->etcDirectory}",
        ];
        $this->ui->note(_('Grid plan:') . "\n" . implode("\n", $lines));
    }
}
