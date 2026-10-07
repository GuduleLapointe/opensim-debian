#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * opensim enable|disable: enable or disable a grid or a simulator by the name of its instance, the
 * same action as the menus of the setup (see Actions). An enabled instance is started with the others
 * by `opensim start` and at boot; disabling it does not stop it.
 *
 *   opensim enable <instance>...
 *   opensim disable <instance>...
 *
 * And with a feature after the grid, what the feature needs in the files of the grid (see Features):
 *
 *   opensim enable|disable <grid> search [<url prefix>]
 *   opensim enable|disable <grid> economy [beta|gloebit] [<url prefix>]
 *   opensim enable|disable <grid> profiles
 */

require __DIR__ . '/../vendor/autoload.php';

use OpenSim\Installer\Actions;
use OpenSim\Installer\Archive\Instances;
use OpenSim\Installer\Config;
use OpenSim\Installer\Grid\Features;
use OpenSim\Installer\Grid\GridInfo;
use OpenSim\Installer\Grid\GridState;
use OpenSim\Installer\Grid\SimState;
use OpenSim\Installer\Ui\QuietUi;

$verb = $argv[1] ?? '';
$names = array_slice($argv, 2);
const USAGE = "usage: opensim enable|disable <instance>...\n" .
    "       opensim enable|disable <grid> search [<url prefix>]\n" .
    "       opensim enable|disable <grid> economy [beta|gloebit] [<url prefix>]\n" .
    "       opensim enable|disable <grid> profiles\n";
if (!in_array($verb, ['enable', 'disable'], true) || $names === []) {
    fwrite(STDERR, USAGE);
    exit(2);
}

$profile = (new Config())->profile();
$etc = $profile['EtcRoot'] ?? '';

// A feature of a grid: the grid, then the word of the feature
if (isset($names[1]) && Features::isFeature($names[1])) {
    $nick = null;
    foreach (Instances::grids($etc) as $candidate) {
        if (strcasecmp($candidate, $names[0]) === 0) {
            $nick = $candidate;
        }
    }
    $grid = $nick === null ? null : GridInfo::load($profile, $nick);
    if ($grid === null) {
        fwrite(STDERR, "$verb: no grid called {$names[0]}\n");
        exit(1);
    }
    $feature = strtolower($names[1]);
    $options = [];
    $more = array_slice($names, 2);
    if ($feature === Features::ECONOMY && isset($more[0]) && !str_contains($more[0], '/')) {
        $options['money'] = strtolower(array_shift($more));
    }
    if (count($more) > 1 || ($more !== [] && $feature === Features::PROFILES)) {
        fwrite(STDERR, USAGE);
        exit(2);
    }
    if ($more !== []) {
        $options['prefix'] = $more[0];
    }

    $features = new Features($grid, $profile);
    $result = $verb === 'enable' ? $features->enable($feature, $options) : $features->disable($feature);
    if ($result['error'] !== null) {
        fwrite(STDERR, "$verb: {$result['error']}\n");
        exit(1);
    }
    $state = $verb . 'd';
    $what = $feature . ($feature === Features::ECONOMY && $verb === 'enable' ? ' (' . ($options['money'] ?? 'beta') . ')' : '');
    $touched = $result['sims'];
    if ($touched === [] && !$result['grid'] && !$result['robust']) {
        echo "nothing to change: $what is already $state for {$grid->nick}" .
            ($features->simulators() === [] ? ' (the grid has no simulator on this machine)' : '') . "\n";
    } else {
        // What the simulators and Robust read at their start; the OpenSim.ini of the grid is read by the helpers each time
        $restart = $touched;
        if ($result['robust']) {
            array_unshift($restart, GridInfo::instanceName($grid->nick));
        }
        $parts = array_filter([
            $touched === [] ? null : count($touched) . ' simulator' . (count($touched) === 1 ? '' : 's'),
            $result['robust'] ? 'Robust' : null,
            $result['grid'] ? 'the OpenSim.ini of the grid' : null,
        ]);
        $changed = count($parts) > 1 ? implode(', ', array_slice($parts, 0, -1)) . ' and ' . end($parts) : (string) reset($parts);
        echo "$what $state for {$grid->nick}: $changed changed" .
            ($restart === [] ? '' : ", restart to apply: opensim restart " . implode(' ', $restart)) . "\n";
    }
    foreach ($result['notes'] as $note) {
        echo "note: $note\n";
    }
    exit(0);
}

// The grids and the simulators the setup made, by the name of their instance
$instances = [];
foreach (glob("$etc/grids/*", GLOB_ONLYDIR) ?: [] as $dir) {
    $nick = basename($dir);
    if (GridState::robustIni($etc, $nick) !== null) {
        $instances[GridInfo::instanceName($nick)] = [
            'kind' => 'grid',
            'args' => ['nick' => $nick],
            'enabled' => GridState::isEnabled($etc, $nick),
        ];
    }
    foreach (SimState::names($dir) as $slug) {
        $instances[$slug] = [
            'kind' => 'simulator',
            'args' => ['slug' => $slug, 'ini' => "$dir/sims/$slug.ini"],
            'enabled' => SimState::isEnabled($etc, $slug),
        ];
    }
}

$actions = new Actions(new QuietUi());
$failed = false;
foreach ($names as $name) {
    $instance = $instances[GridInfo::instanceName($name)] ?? null;
    if ($instance === null) {
        fwrite(
            STDERR,
            "$verb: no grid or simulator called $name" .
                ($instances === [] ? '' : ' (known: ' . implode(', ', array_keys($instances)) . ')') .
                "\n",
        );
        $failed = true;
        continue;
    }

    $enable = $verb === 'enable';
    if ($instance['enabled'] === $enable) {
        echo "{$instance['kind']} $name is already {$verb}d\n";
        continue;
    }
    if ($actions->perform($instance['kind'] === 'grid' ? "grid-$verb" : "sim-$verb", $instance['args'], $profile)) {
        echo "{$instance['kind']} $name {$verb}d" . ($enable ? ": opensim start $name" : '') . "\n";
    } else {
        $failed = true;
    }
}

exit($failed ? 1 : 0);

