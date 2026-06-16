<?php

declare(strict_types=1);

namespace OpenSim\Installer;

use OpenSim\Installer\Grid\NewGrid;
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
                'sim' => 'Sim ' . ($grids === [] ? '(needs a grid)' : '(' . $this->simState($sims) . ')'),
                'quit' => 'Quit',
            ];

            $default = $cores === [] ? 'core' : ($grids === [] ? 'grid' : 'sim');

            $choice = $this->ui->choose('OpenSim — setup', $options, $default);

            switch ($choice) {
                case 'core':
                    $this->coreMenu($cores);
                    break;
                case 'grid':
                    $cores === [] ? $this->ui->warn('Install an OpenSim core first.') : $this->gridMenu($grids);
                    break;
                case 'sim':
                    $grids === [] ? $this->ui->warn('Create a grid first.') : $this->simMenu($sims);
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

    private function gridMenu(array $grids): void
    {
        $options = [];
        foreach ($grids as $nick) {
            $options[$nick] = $nick;
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
        (new NewGrid($this->ui))->run($choice);
    }

    private function simMenu(array $sims): void
    {
        // Sim configuration is not ported yet.
        $this->ui->warn('Sim configuration is coming in a later phase.');
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

    /** @return list<string> sim names under EtcRoot/grids/<grid>/sims */
    private function sims(string $etcRoot, string $grid): array
    {
        return $this->dirNames($etcRoot === '' ? '' : "$etcRoot/grids/$grid/sims");
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
