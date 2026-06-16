<?php

declare(strict_types=1);

namespace OpenSim\Installer\Grid;

use OpenSim\Installer\Config;
use OpenSim\Installer\System;
use OpenSim\Installer\Ui\InstallerUi;

/**
 * Configure (or reconfigure) one grid on top of an installed framework.
 *
 * Reusable flow driven through an InstallerUi: the setup hub calls run() with a
 * grid nick to modify, or null to create a new one. Returns to the caller when
 * done (never exits the process). Phase 2a: gather only.
 */
final class NewGrid
{
    public function __construct(private InstallerUi $ui)
    {
    }

    public function run(?string $modifyNick = null): void
    {
        $profile = (new Config())->profile();
        if ($profile === [] || ($profile['EtcRoot'] ?? '') === '') {
            $this->ui->error('No installed framework found. Install an OpenSim core first.');

            return;
        }

        $plan = $this->gather($profile, $modifyNick);
        if ($plan === null) {
            return; // abandoned
        }

        $this->showPlan($plan);
        $this->ui->note('Phase 2a OK — parameters gathered, nothing created yet.');
    }

    private function gather(array $profile, ?string $modifyNick): ?GridPlan
    {
        $required = static fn (string $v): ?string => trim($v) === '' ? 'This field is required.' : null;
        $numeric = static fn (string $v): ?string => ctype_digit(trim($v)) ? null : 'Enter a port number.';

        $etcRoot = $profile['EtcRoot'];

        // Identify the grid and load its existing config, if any.
        if ($modifyNick !== null) {
            $nick = $modifyNick;
            $gridDir = "$etcRoot/grids/$nick";
            $existing = $this->findExisting($gridDir);
            $current = $existing !== null ? $this->parseExisting($existing) : [];
            $name = $this->ui->text('Grid name', $current['gridName'] ?? ucfirst($nick), $required);
        } else {
            $name = $this->ui->text('Grid name', $this->defaultName(), $required);
            $nick = $this->ui->text('Grid nick (alphanumeric)', Slug::nick($name), $required);
            $gridDir = "$etcRoot/grids/$nick";
            $existing = $this->findExisting($gridDir);
            $current = [];
            if ($existing !== null) {
                $action = $this->ui->choose(
                    "Grid '$nick' is already configured ($existing).",
                    ['modify' => 'Modify its settings', 'abandon' => 'Abandon, keep it unchanged'],
                    'modify',
                );
                if ($action === 'abandon') {
                    $this->ui->note('Left unchanged.');

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
        $plan->enableHypergrid = $this->ui->confirm('Enable Hypergrid?', $hgDefault);

        // Core selection (multi-version aware).
        $coreRoot = $profile['CoreRoot'] ?? '';
        $cores = [];
        foreach (glob("$coreRoot/opensim-*/bin/OpenSim.exe") ?: [] as $exe) {
            $dir = dirname($exe, 2);
            $cores[$dir] = basename($dir);
        }
        if ($cores === []) {
            $this->ui->error("No OpenSim core found under $coreRoot.");

            return null;
        }
        $plan->coreDirectory = $this->ui->choose('OpenSim core to run this grid', $cores, $profile['CoreDirectory'] ?? null);
        $plan->binDir = $plan->coreDirectory . '/bin';

        $defaultHost = $current['baseHostname'] ?? (trim(System::capture('hostname -f')[1]) ?: 'localhost');
        $plan->baseHostname = $this->ui->text('Base hostname', $defaultHost, $required);
        $plan->publicPort = (int) $this->ui->text('Public port', (string) ($current['publicPort'] ?? $this->nextPort(8002)), $numeric);
        $plan->privatePort = (int) $this->ui->text('Private port', (string) ($current['privatePort'] ?? $this->nextPort($plan->publicPort + 1)), $numeric);
        $plan->webUrl = $this->ui->text('Web URL', $current['webUrl'] ?? "https://{$plan->baseHostname}", $required);

        // Database: reuse a found password, otherwise generate one (never changeme).
        $foundPass = $current['dbPass'] ?? '';
        $defaultPass = ($foundPass !== '' && $foundPass !== 'changeme') ? $foundPass : $this->randomPassword();

        $plan->dbHost = $this->ui->text('Database host', $current['dbHost'] ?? ($profile['DataSource'] ?? 'localhost'), $required);
        $plan->dbName = $this->ui->text('Database name', $current['dbName'] ?? (strtolower($nick) . '_robust'), $required);
        $plan->dbUser = $this->ui->text('Database user', $current['dbUser'] ?? 'opensim', $required);
        $plan->dbPass = $this->ui->text('Database password', $defaultPass, $required);

        return $plan;
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

    /** Extract current settings from an existing Robust config (tolerant regex). */
    private function parseExisting(string $path): array
    {
        $text = (string) file_get_contents($path);
        $current = [];

        $grab = static function (string $key, string $text): ?string {
            return preg_match('/^\s*' . $key . '\s*=\s*"?([^"\n]+?)"?\s*$/im', $text, $m) ? trim($m[1]) : null;
        };

        foreach (['gridName' => 'gridname', 'baseHostname' => 'BaseHostname', 'webUrl' => 'WebURL'] as $field => $key) {
            $value = $grab($key, $text);
            if ($value !== null) {
                $current[$field] = $value;
            }
        }
        if (($p = $grab('PublicPort', $text)) !== null && ctype_digit($p)) {
            $current['publicPort'] = (int) $p;
        }
        if (($p = $grab('PrivatePort', $text)) !== null && ctype_digit($p)) {
            $current['privatePort'] = (int) $p;
        }
        if (preg_match('/Data Source=([^;"\s]*);Database=([^;"\s]*);User ID=([^;"\s]*);Password=([^;"]*?);/i', $text, $m)) {
            $current['dbHost'] = $m[1];
            $current['dbName'] = $m[2];
            $current['dbUser'] = $m[3];
            $current['dbPass'] = $m[4];
        }

        return $current;
    }

    private function nextPort(int $from): string
    {
        $out = trim(System::capture(dirname(__DIR__, 3) . '/bin/nextfreeports ' . $from)[1]);

        return $out !== '' ? $out : (string) $from;
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
            "  Core:        {$plan->coreDirectory}",
            "  Hostname:    {$plan->baseHostname}",
            "  Ports:       public {$plan->publicPort} / private {$plan->privatePort}",
            "  Web URL:     {$plan->webUrl}",
            "  Database:    {$plan->dbName} @ {$plan->dbHost} (user {$plan->dbUser})",
            "  Robust ini:  {$plan->robustIni()}",
            "  Grid dir:    {$plan->etcDirectory}",
        ];
        $this->ui->note("Grid plan:\n" . implode("\n", $lines));
    }
}
