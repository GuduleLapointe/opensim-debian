<?php

declare(strict_types=1);

namespace OpenSim\Installer;

use OpenSim\Installer\Grid\GridInfo;
use OpenSim\Installer\Grid\GridState;
use OpenSim\Installer\Grid\NewGrid;
use OpenSim\Installer\Grid\NewSim;
use OpenSim\Installer\Grid\RegionState;
use OpenSim\Installer\Grid\SimConfig;
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
 * Back goes up one level, Quit leaves the setup from any of them. A grid whose Robust is on another machine is only a
 * list of simulators here.
 *
 * The flow talks only to an InstallerUi, so a web frontend can render the same
 * screens later.
 */
final class Hub
{
    public function __construct(private InstallerUi $ui) {}

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
                $name = $this->ui->entity($nick);
                $options["grid:$nick"] = GridInfo::isRemote($etcRoot, $nick)
                    ? "$name (Robust on another machine)"
                    : $name . (GridState::isEnabled($etcRoot, $nick) ? '' : ' [disabled]');
            }
            $options['add'] = 'Add grid';
            $options['quit'] = 'Quit';

            $default = $cores === [] ? 'core' : ($grids === [] ? 'add' : "grid:{$grids[0]}");
            $choice = $this->ui->choose('OpenSim — setup', $options, $default);

            $quit = match (true) {
                $choice === 'quit' => true,
                $choice === 'core' => $this->coreMenu($cores),
                $cores === [] => $this->ui->warn('Install an OpenSim core first.') ?? false,
                $choice === 'add' => $this->addGrid(),
                default => $this->gridScreen(substr($choice, strlen('grid:'))),
            };
            if ($quit) {
                $this->offerRestarts();

                return;
            }
        }
    }

    /** What the setup did that needs instances to restart: shown when it ends, to restart them or leave them. */
    private function offerRestarts(): void
    {
        $profile = (new Config())->profile();
        $etcRoot = $profile['EtcRoot'] ?? '';
        $pending = PendingRestarts::read($profile);
        if ($pending === []) {
            return;
        }

        $lines = [];
        $running = false;
        foreach ($pending as $entry) {
            $lines[] = "  {$entry['instance']}: {$entry['reason']}";
            $running = $running || Console::running("$etcRoot/opensim.d/{$entry['instance']}.ini");
        }
        $this->ui->note("To take these changes into account, restart:\n" . implode("\n", $lines));
        if (!$this->ui->confirm('Restart them now?', true)) {
            $this->ui->note('They stay listed in ' . PendingRestarts::path($profile) . ': the setup offers them again next time.');

            return;
        }
        // A simulator that runs warns its users and waits, or restarts at once
        $warn = $running && $this->ui->confirm('Warn the users of the simulators first (it takes two minutes)?', false);
        if (!$this->act('restart', ['warn' => $warn])) {
            $this->ui->warn('Some instances did not restart: see above, they stay listed.');
        }
    }

    /**
     * What a menu does to the install, by the system user of the install.
     *
     * @param array<string,mixed> $args
     */
    private function act(string $op, array $args): bool
    {
        $profile = (new Config())->profile();
        if (!Elevated::needed($profile)) {
            return (new Actions($this->ui))->perform($op, $args, $profile);
        }
        try {
            Elevated::run(
                $this->ui,
                '--apply-action',
                ['op' => $op, 'args' => $args, 'profile' => $profile],
                $profile['SystemUser'],
            );
        } catch (SetupFailed) {
            return false;
        }

        return true;
    }

    /** @return bool whether to quit the setup */
    private function coreMenu(array $cores): bool
    {
        $options = [];
        foreach ($cores as $name) {
            $options[$name] = $name;
        }
        $options['new'] = 'Install a new version';
        $options['back'] = 'Back';
        $options['quit'] = 'Quit';

        $choice = $this->ui->choose('OpenSim core', $options, 'new');
        if ($choice === 'new') {
            (new Installer($this->ui))->run();
        } elseif ($choice !== 'back' && $choice !== 'quit') {
            (new Config())->setDefaultProfile($choice);
        }

        return $choice === 'quit';
    }

    /**
     * A grid run from this machine, or one run elsewhere, of which only its simulators are set up
     * here. What is configured is where the setup goes next: its screen, ready to add a simulator.
     *
     * @return bool whether to quit the setup
     */
    private function addGrid(): bool
    {
        $choice = $this->ui->choose(
            'Add a grid',
            [
                'new' => 'Create grid on this machine',
                'external' => 'Connect to an external grid',
                'back' => 'Back',
                'quit' => 'Quit',
            ],
            'new',
        );

        switch ($choice) {
            case 'new':
                $nick = (new NewGrid($this->ui))->run(null);

                return $nick !== null && $this->gridScreen($nick);
            case 'external':
                $made = (new NewSim($this->ui))->run(null, null, true);

                return $made !== null && $this->simScreen($made[0], $made[1]);
            default:
                return $choice === 'quit';
        }
    }

    /** @return bool whether to quit the setup */
    private function gridScreen(string $nick): bool
    {
        while (true) {
            $etcRoot = (new Config())->profile()['EtcRoot'] ?? '';
            $remote = GridInfo::isRemote($etcRoot, $nick);
            $enabled = GridState::isEnabled($etcRoot, $nick);
            $sims = $this->sims($etcRoot, $nick);

            $options = [];
            foreach ($sims as $slug) {
                $options["sim:$slug"] =
                    $this->ui->entity($this->simTitle($etcRoot, $nick, $slug)) .
                    (SimState::isEnabled($etcRoot, $slug) ? '' : ' [disabled]');
            }
            $options['addsim'] = 'Add simulator';
            if (!$remote) {
                $options['configure'] = 'Configure grid';
                $options['toggle'] = $enabled ? 'Disable this grid' : 'Enable this grid';
            }
            $options['back'] = 'Back';
            $options['quit'] = 'Quit';

            $choice = $this->ui->choose(
                'Grid: ' . $this->ui->entity($nick) . ($remote ? ' (Robust on another machine)' : ''),
                $options,
                $sims !== [] ? "sim:{$sims[0]}" : 'addsim',
            );
            if ($choice === 'back' || $choice === 'quit') {
                return $choice === 'quit';
            }
            switch (true) {
                case $choice === 'configure':
                    (new NewGrid($this->ui))->run($nick);
                    break;
                case $choice === 'addsim':
                    $made = (new NewSim($this->ui))->run($nick);
                    if ($made !== null && $this->simScreen($made[0], $made[1])) {
                        return true;
                    }
                    break;
                case $choice === 'toggle':
                    if ($this->act($enabled ? 'grid-disable' : 'grid-enable', ['nick' => $nick])) {
                        $this->ui->note(($enabled ? 'Disabled' : 'Enabled') . " $nick.");
                    }
                    break;
                default:
                    if ($this->simScreen($nick, substr($choice, strlen('sim:')))) {
                        return true;
                    }
            }
        }
    }

    /**
     * A simulator and its regions. Adding a region is the usual next step, so it is the one
     * proposed, also after a region was added.
     *
     * @return bool whether to quit the setup
     */
    private function simScreen(string $nick, string $slug): bool
    {
        while (true) {
            $etcRoot = (new Config())->profile()['EtcRoot'] ?? '';
            $enabled = SimState::isEnabled($etcRoot, $slug);
            $regions = RegionState::list("$etcRoot/grids/$nick/sims/$slug/regions");

            $options = [];
            foreach ($regions as $name => $region) {
                $options["region:$name"] = $this->ui->entity($name) . ($region['enabled'] ? '' : ' [disabled]');
            }
            $options['addregion'] = 'Add region';
            $options['reconfigure'] = 'Configure sim';
            $options['toggle'] = $enabled ? 'Disable this simulator' : 'Enable this simulator';
            $options['back'] = 'Back';
            $options['quit'] = 'Quit';

            $choice = $this->ui->choose(
                'Simulator: ' . $this->ui->entity($this->simTitle($etcRoot, $nick, $slug)),
                $options,
                'addregion',
            );
            if ($choice === 'back' || $choice === 'quit') {
                return $choice === 'quit';
            }
            switch (true) {
                case $choice === 'reconfigure':
                    (new NewSim($this->ui))->run($nick, $this->simName($nick, $slug));
                    break;
                case $choice === 'addregion':
                    (new NewSim($this->ui))->addRegion($nick, $this->simName($nick, $slug));
                    break;
                case $choice === 'toggle':
                    $done = $this->act(
                        $enabled ? 'sim-disable' : 'sim-enable',
                        ['slug' => $slug, 'ini' => "$etcRoot/grids/$nick/sims/$slug.ini"],
                    );
                    if ($done) {
                        $this->ui->note(($enabled ? 'Disabled' : 'Enabled') . " $slug.");
                    }
                    break;
                default:
                    if ($this->regionScreen($nick, $slug, substr($choice, strlen('region:')))) {
                        return true;
                    }
            }
        }
    }

    /** @return bool whether to quit the setup */
    private function regionScreen(string $nick, string $slug, string $name): bool
    {
        while (true) {
            $etcRoot = (new Config())->profile()['EtcRoot'] ?? '';
            $region = RegionState::list("$etcRoot/grids/$nick/sims/$slug/regions")[$name] ?? null;
            if ($region === null) {
                return false;
            }

            $choice = $this->ui->choose(
                'Region: ' . $this->ui->entity($name),
                [
                    'reconfigure' => 'Reconfigure',
                    'toggle' => $region['enabled'] ? 'Disable this region' : 'Enable this region',
                    'back' => 'Back',
                    'quit' => 'Quit',
                ],
                'back',
            );

            switch ($choice) {
                case 'reconfigure':
                    (new NewSim($this->ui))->reconfigureRegion($nick, $this->simName($nick, $slug), $name);
                    break;
                case 'toggle':
                    $done = $this->act($region['enabled'] ? 'region-disable' : 'region-enable', [
                        'file' => $region['file'],
                        'name' => $name,
                        'instance' => $slug,
                    ]);
                    if ($done) {
                        $this->ui->note(
                            ($region['enabled'] ? 'Disabled' : 'Enabled') .
                                " $name: the simulator takes it into account when it restarts.",
                        );
                    }
                    break;
                default:
                    return $choice === 'quit';
            }
        }
    }

    /** The name of a simulator as it was given, else (its config has no mention of it) as its instance name tells it. */
    private function simTitle(string $etcRoot, string $nick, string $slug): string
    {
        return SimConfig::readName("$etcRoot/grids/$nick/sims/$slug.ini") ?? $this->simName($nick, $slug);
    }

    /** The name of a simulator from the name of its instance: what follows the grid nick. */
    private function simName(string $nick, string $slug): string
    {
        $etcRoot = (new Config())->profile()['EtcRoot'] ?? '';
        if (($name = SimConfig::readName("$etcRoot/grids/$nick/sims/$slug.ini")) !== null) {
            return $name;
        }
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
