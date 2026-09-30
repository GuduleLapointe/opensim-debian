<?php

declare(strict_types=1);

namespace OpenSim\Installer;

use OpenSim\Installer\Grid\GridInfo;
use OpenSim\Installer\Grid\GridState;
use OpenSim\Installer\Grid\NewGrid;
use OpenSim\Installer\Grid\NewSim;
use OpenSim\Installer\Grid\SimState;
use OpenSim\Installer\Ui\InstallerUi;

/**
 * Setup hub: a fixed dashboard of layers — core, grid, sim — each showing its
 * current state and drilling down to "select another / add new" when chosen.
 * Rows keep stable positions; a layer without its prerequisite is shown but
 * not actionable (no grid without a core, no sim without a grid). The cursor
 * lands on the most relevant (deepest incomplete) layer.
 *
 * The flow talks only to an InstallerUi, so a web frontend can render the same
 * dashboard later.
 */
final class Hub
{
    private ?string $activeGrid = null;

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

            $this->activeGrid ??= $grids[0] ?? null;
            if ($this->activeGrid !== null && !in_array($this->activeGrid, $grids, true)) {
                $this->activeGrid = $grids[0] ?? null;
            }
            $sims = $this->activeGrid !== null ? $this->sims($etcRoot, $this->activeGrid) : [];

            $options = [
                'core' => 'OpenSim core ' . ($cores !== [] ? "[$activeCore]" : '(install)'),
                'grid' => 'Grid ' . ($cores === [] ? '(needs a core)' : ($grids !== [] ? "[{$this->activeGrid}]" : '(create new)')),
                'sim' => 'Sim ' . ($cores === [] ? '(needs a core)' : '(' . $this->simState($sims) . ')'),
                'quit' => 'Quit',
            ];

            $default = $cores === [] ? 'core' : ($grids === [] ? 'grid' : 'sim');

            $choice = $this->ui->choose('OpenSim — setup', $options, $default);

            switch ($choice) {
                case 'core':
                    $this->coreMenu($cores);
                    break;
                case 'grid':
                    $cores === [] ? $this->ui->warn('Install an OpenSim core first.') : $this->gridMenu($grids, $etcRoot);
                    break;
                case 'sim':
                    $cores === [] ? $this->ui->warn('Install an OpenSim core first.') : $this->simMenu($sims, $etcRoot);
                    break;
                case 'quit':
                    return;
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

    private function gridMenu(array $grids, string $etcRoot): void
    {
        $options = [];
        foreach ($grids as $nick) {
            $options[$nick] = GridInfo::isRemote($etcRoot, $nick)
                ? "$nick (Robust on another machine)"
                : $nick . (GridState::isEnabled($etcRoot, $nick) ? '' : ' (disabled)');
        }
        $options['new'] = 'Create a new grid';
        $options['back'] = 'Back';

        $choice = $this->ui->choose('Grid', $options, $grids === [] ? 'new' : ($this->activeGrid ?? 'new'));
        if ($choice === 'back') {
            return;
        }
        if ($choice === 'new') {
            (new NewGrid($this->ui))->run(null);

            return;
        }
        $this->activeGrid = $choice;
        if (GridInfo::isRemote($etcRoot, $choice)) {
            $this->ui->note("Grid '$choice' runs on another machine: only its simulators are set up here (Sim).");

            return;
        }
        $this->gridActions($choice, $etcRoot);
    }

    private function gridActions(string $nick, string $etcRoot): void
    {
        $enabled = GridState::isEnabled($etcRoot, $nick);
        $choice = $this->ui->choose("Grid: $nick", [
            'reconfigure' => 'Reconfigure',
            'toggle' => $enabled ? 'Disable' : 'Enable',
            'back' => 'Back',
        ], 'reconfigure');

        switch ($choice) {
            case 'reconfigure':
                (new NewGrid($this->ui))->run($nick);
                break;
            case 'toggle':
                if ($enabled) {
                    GridState::disable($etcRoot, $nick);
                    $this->ui->note("Disabled $nick.");
                } elseif (GridState::enable($etcRoot, $nick)) {
                    $this->ui->note("Enabled $nick.");
                } else {
                    $this->ui->warn("Cannot enable $nick (no Robust config).");
                }
                break;
        }
    }

    private function simMenu(array $sims, string $etcRoot): void
    {
        // No grid here: the simulator joins one whose Robust is elsewhere (asked by the wizard)
        if ($this->activeGrid === null) {
            (new NewSim($this->ui))->run(null);

            return;
        }

        $options = [];
        foreach ($sims as $slug) {
            $options[$slug] = $slug . (SimState::isEnabled($etcRoot, $slug) ? '' : ' (disabled)');
        }
        $options['new'] = 'Create a new simulator';
        $options['other'] = 'Create a simulator in another grid';
        $options['back'] = 'Back';

        $choice = $this->ui->choose("Simulators of {$this->activeGrid}", $options, $sims === [] ? 'new' : 'back');
        if ($choice === 'back') {
            return;
        }
        if ($choice === 'new' || $choice === 'other') {
            (new NewSim($this->ui))->run($choice === 'new' ? $this->activeGrid : null);

            return;
        }
        $this->simActions($choice, $etcRoot);
    }

    private function simActions(string $slug, string $etcRoot): void
    {
        $enabled = SimState::isEnabled($etcRoot, $slug);
        $choice = $this->ui->choose("Simulator: $slug", [
            'region' => 'Add a region',
            'reconfigure' => 'Reconfigure',
            'toggle' => $enabled ? 'Disable' : 'Enable',
            'back' => 'Back',
        ], 'back');

        switch ($choice) {
            case 'region':
                (new NewSim($this->ui))->addRegion($this->activeGrid, $this->simName($slug));
                break;
            case 'reconfigure':
                // The name typed at creation is not kept: the slug names it
                (new NewSim($this->ui))->run($this->activeGrid, $this->simName($slug));
                break;
            case 'toggle':
                if ($enabled) {
                    SimState::disable($etcRoot, $slug);
                    $this->ui->note("Disabled $slug.");
                } elseif (SimState::enable($etcRoot, $slug, "$etcRoot/grids/{$this->activeGrid}/sims/$slug.ini")) {
                    $this->ui->note("Enabled $slug.");
                } else {
                    $this->ui->warn("Cannot enable $slug (no config).");
                }
                break;
        }
    }

    /** The name of a simulator from the name of its instance: what follows the grid nick. */
    private function simName(string $slug): string
    {
        $prefix = GridInfo::instanceName((string) $this->activeGrid) . '_';

        return str_starts_with($slug, $prefix) ? substr($slug, strlen($prefix)) : $slug;
    }

    private function simState(array $sims): string
    {
        return match (count($sims)) {
            0 => 'create new',
            1 => $sims[0],
            default => count($sims) . ' sims',
        };
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
