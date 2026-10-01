<?php

declare(strict_types=1);

namespace OpenSim\Installer;

use OpenSim\Installer\Grid\GridInfo;
use OpenSim\Installer\Grid\GridState;
use OpenSim\Installer\Grid\NewGrid;
use OpenSim\Installer\Grid\NewSim;
use OpenSim\Installer\Grid\RegionState;
use OpenSim\Installer\Grid\SimState;
use OpenSim\Installer\Ui\InstallerUi;

/**
 * Setup hub: screens one level deeper each time, from the home to a region:
 *
 *   home          the core, the grids, add a grid
 *   grid          configure, its simulators, add a simulator, enable or disable
 *   simulator     reconfigure, enable or disable, its regions, add a region
 *   region        reconfigure, enable or disable
 *
 * Back goes up one level. A grid whose Robust is on another machine is only a
 * list of simulators here.
 *
 * The flow talks only to an InstallerUi, so a web frontend can render the same
 * screens later.
 */
final class Hub
{
    public function __construct(private InstallerUi $ui)
    {
    }

    public function run(): void
    {
        while (true) {
            $config = new Config();
            $cores = $config->profiles();
            $activeCore = $config->defaultProfile();
            $etcRoot = $config->profile()['EtcRoot'] ?? '';
            $grids = $this->grids($etcRoot);

            $options = ['core' => 'OpenSim core ' . ($cores !== [] ? "[$activeCore]" : '(install)')];
            foreach ($grids as $nick) {
                $options["grid:$nick"] = GridInfo::isRemote($etcRoot, $nick)
                    ? "$nick (Robust on another machine)"
                    : $nick . (GridState::isEnabled($etcRoot, $nick) ? '' : ' [disabled]');
            }
            $options['add'] = 'Add grid';
            $options['quit'] = 'Quit';

            $default = $cores === [] ? 'core' : ($grids === [] ? 'add' : "grid:{$grids[0]}");
            $choice = $this->ui->choose('OpenSim — setup', $options, $default);

            if ($choice === 'quit') {
                return;
            }
            if ($choice === 'core') {
                $this->coreMenu($cores);
            } elseif ($cores === []) {
                $this->ui->warn('Install an OpenSim core first.');
            } elseif ($choice === 'add') {
                $this->addGrid();
            } else {
                $this->gridScreen(substr($choice, strlen('grid:')));
            }
        }
    }

    private function coreMenu(array $cores): void
    {
        $options = [];
        foreach ($cores as $name) {
            $options[$name] = $name;
        }
        $options['new'] = 'Install a new version';
        $options['back'] = 'Back';

        $choice = $this->ui->choose('OpenSim core', $options, 'new');
        if ($choice === 'back') {
            return;
        }
        if ($choice === 'new') {
            (new Installer($this->ui))->run();

            return;
        }
        (new Config())->setDefaultProfile($choice);
    }

    /** A grid run from this machine, or one run elsewhere, of which only its simulators are set up here. */
    private function addGrid(): void
    {
        $choice = $this->ui->choose('Add a grid', [
            'new' => 'A new grid, run from this machine',
            'external' => 'A grid run elsewhere (its Robust is on another machine)',
            'back' => 'Back',
        ], 'new');

        match ($choice) {
            'new' => (new NewGrid($this->ui))->run(null),
            'external' => (new NewSim($this->ui))->run(null),
            default => null,
        };
    }

    private function gridScreen(string $nick): void
    {
        while (true) {
            $etcRoot = (new Config())->profile()['EtcRoot'] ?? '';
            $remote = GridInfo::isRemote($etcRoot, $nick);
            $enabled = GridState::isEnabled($etcRoot, $nick);
            $sims = $this->sims($etcRoot, $nick);

            $options = [];
            if (!$remote) {
                $options['configure'] = 'Configure';
            }
            foreach ($sims as $slug) {
                $options["sim:$slug"] = $slug . (SimState::isEnabled($etcRoot, $slug) ? '' : ' [disabled]');
            }
            $options['addsim'] = 'Add simulator';
            if (!$remote) {
                $options['toggle'] = $enabled ? 'Disable' : 'Enable';
            }
            $options['back'] = 'Back';

            $choice = $this->ui->choose("Grid: $nick" . ($remote ? ' (Robust on another machine)' : ''), $options, $sims !== [] ? "sim:{$sims[0]}" : 'addsim');
            if ($choice === 'back') {
                return;
            }
            switch (true) {
                case $choice === 'configure':
                    (new NewGrid($this->ui))->run($nick);
                    break;
                case $choice === 'addsim':
                    (new NewSim($this->ui))->run($nick);
                    break;
                case $choice === 'toggle':
                    if ($enabled) {
                        GridState::disable($etcRoot, $nick);
                        $this->ui->note("Disabled $nick.");
                    } elseif (GridState::enable($etcRoot, $nick)) {
                        $this->ui->note("Enabled $nick.");
                    } else {
                        $this->ui->warn("Cannot enable $nick (no Robust config).");
                    }
                    break;
                default:
                    $this->simScreen($nick, substr($choice, strlen('sim:')));
            }
        }
    }

    private function simScreen(string $nick, string $slug): void
    {
        while (true) {
            $etcRoot = (new Config())->profile()['EtcRoot'] ?? '';
            $enabled = SimState::isEnabled($etcRoot, $slug);
            $regions = RegionState::list("$etcRoot/grids/$nick/sims/$slug/regions");

            $options = ['reconfigure' => 'Reconfigure', 'toggle' => $enabled ? 'Disable' : 'Enable'];
            foreach ($regions as $name => $region) {
                $options["region:$name"] = $name . ($region['enabled'] ? '' : ' [disabled]');
            }
            $options['addregion'] = 'Add region';
            $options['back'] = 'Back';

            $choice = $this->ui->choose("Simulator: $slug", $options, 'back');
            if ($choice === 'back') {
                return;
            }
            switch (true) {
                case $choice === 'reconfigure':
                    // The name typed at creation is not kept: the slug names it
                    (new NewSim($this->ui))->run($nick, $this->simName($nick, $slug));
                    break;
                case $choice === 'addregion':
                    (new NewSim($this->ui))->addRegion($nick, $this->simName($nick, $slug));
                    break;
                case $choice === 'toggle':
                    if ($enabled) {
                        SimState::disable($etcRoot, $slug);
                        $this->ui->note("Disabled $slug.");
                    } elseif (SimState::enable($etcRoot, $slug, "$etcRoot/grids/$nick/sims/$slug.ini")) {
                        $this->ui->note("Enabled $slug.");
                    } else {
                        $this->ui->warn("Cannot enable $slug (no config).");
                    }
                    break;
                default:
                    $this->regionScreen($nick, $slug, substr($choice, strlen('region:')));
            }
        }
    }

    private function regionScreen(string $nick, string $slug, string $name): void
    {
        while (true) {
            $etcRoot = (new Config())->profile()['EtcRoot'] ?? '';
            $region = RegionState::list("$etcRoot/grids/$nick/sims/$slug/regions")[$name] ?? null;
            if ($region === null) {
                return;
            }

            $choice = $this->ui->choose("Region: $name", [
                'reconfigure' => 'Reconfigure',
                'toggle' => $region['enabled'] ? 'Disable' : 'Enable',
                'back' => 'Back',
            ], 'back');

            switch ($choice) {
                case 'reconfigure':
                    (new NewSim($this->ui))->reconfigureRegion($nick, $this->simName($nick, $slug), $name);
                    break;
                case 'toggle':
                    $moved = $region['enabled'] ? RegionState::disable($region['file']) : RegionState::enable($region['file']);
                    if ($moved === null) {
                        $this->ui->warn("Cannot " . ($region['enabled'] ? 'disable' : 'enable') . " $name (permission, or a region of that name is there already).");
                    } else {
                        $this->ui->note(($region['enabled'] ? 'Disabled' : 'Enabled') . " $name: the simulator takes it into account when it starts.");
                    }
                    break;
                default:
                    return;
            }
        }
    }

    /** The name of a simulator from the name of its instance: what follows the grid nick. */
    private function simName(string $nick, string $slug): string
    {
        $prefix = GridInfo::instanceName($nick) . '_';

        return str_starts_with($slug, $prefix) ? substr($slug, strlen($prefix)) : $slug;
    }

    /** @return list<string> grid nicks under EtcRoot/grids */
    private function grids(string $etcRoot): array
    {
        return $this->dirNames($etcRoot === '' ? '' : "$etcRoot/grids");
    }

    /** @return list<string> instance names of the simulators of a grid */
    private function sims(string $etcRoot, string $grid): array
    {
        return $etcRoot === '' ? [] : SimState::names("$etcRoot/grids/$grid");
    }

    /** @return list<string> */
    private function dirNames(string $path): array
    {
        if ($path === '' || !is_dir($path)) {
            return [];
        }

        $names = [];
        foreach (glob("$path/*", GLOB_ONLYDIR) ?: [] as $dir) {
            $names[] = basename($dir);
        }

        return $names;
    }
}
