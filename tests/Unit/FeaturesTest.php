<?php

/**
 * `opensim enable|disable <grid> search|economy|profiles`: the settings of a feature in the files of the simulators of the grid
 * (and in Robust's for what it tells), from libexec/toggle.php, run as a user whose setup is a temporary tree.
 */

/**
 * Run libexec/toggle.php as a user whose setup is a temporary tree.
 *
 * @param list<string> $arguments What follows `opensim`.
 * @return array{0:int,1:string,2:string} Exit status, output, errors.
 */
function features_run(array $arguments, string $home): array
{
    $root = dirname(__DIR__, 2);
    $process = proc_open(
        [PHP_BINARY, "$root/libexec/toggle.php", ...$arguments],
        [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes,
        $root,
        ['HOME' => $home, 'PATH' => getenv('PATH')],
    );
    $output = stream_get_contents($pipes[1]);
    $errors = stream_get_contents($pipes[2]);

    return [proc_close($process), trim((string) $output), trim((string) $errors)];
}

/**
 * A setup with a grid that has helpers (its web address in Robust), two simulators, one of them registered to another directory.
 *
 * @return array{0:string,1:string} The home of the user, the folder of the grid.
 */
function features_tree(bool $helpers = true): array
{
    $root = test_tmp() . '/features-' . bin2hex(random_bytes(4));
    $etc = "$root/etc";
    $grid = "$etc/grids/alpha";
    mkdir("$root/home", 0o755, true);
    mkdir("$grid/sims", 0o755, true);
    file_put_contents(
        "$root/home/.opensim.conf",
        "[Defaults]\nDefaultProfile = 0.9.3.0\nEnabledModules = opensimsearch gloebit\n\n[0.9.3.0]\nEtcRoot = $etc\nCoreDirectory = $root/core/0.9.3.0\n",
    );
    file_put_contents(
        "$grid/Robust.HG.ini",
        "[Const]\n" . ($helpers ? "WebURL = \"https://play.example.org\"\n" : '') . "[GridInfoService]\ngridname = \"Alpha World\"\n" .
            "[UserProfilesService]\n    Enabled = false\n" .
            "[ServiceConnectors]\n    ;UserProfilesServiceConnector = \"\${Const|PublicPort}/OpenSim.Server.Handlers.dll:UserProfilesConnector\"\n",
    );
    file_put_contents("$grid/alpha.conf", "[Grid]\nGridName = \"Alpha World\"\nGridNick = alpha\n");
    file_put_contents("$grid/sims/alpha_one.ini", "[Const]\nBaseURL = \"http://play.example.org\"\nPublicPort = 8002\n[Startup]\nkeep = me\n");
    file_put_contents(
        "$grid/sims/alpha_two.ini",
        "[Const]\nBaseURL = \"http://play.example.org\"\nPublicPort = 8002\n[DataSnapshot]\nindex_sims = true\nDATA_SRV_2do = \"https://2do.directory/helpers/register.php\"\n",
    );

    return ["$root/home", $grid];
}

/** What a simulator ini of the tree has. */
function features_sim(string $grid, string $sim): string
{
    return (string) file_get_contents("$grid/sims/$sim.ini");
}

describe('opensim enable <grid> search', function () {
    test('gives the simulators the module and the registration at the address of the helpers, and says what to restart', function () {
        [$home, $grid] = features_tree();

        [$status, $output] = features_run(['enable', 'alpha', 'search'], $home);

        expect($status)
            ->toBe(0)
            ->and($output)
            ->toContain('search enabled for alpha: 2 simulators changed, restart to apply: opensim restart alpha_one alpha_two')
            ->and(features_sim($grid, 'alpha_one'))
            ->toContain("[Search]\nModule = OpenSimSearch\nSearchURL = \"https://play.example.org/helpers/query.php\"")
            ->toContain('index_sims = true')
            ->toContain('gridname = "Alpha World"')
            ->toContain('DATA_SRV_Alpha = "https://play.example.org/helpers/register.php"')
            // What the operator has stays, and the other directory too
            ->toContain("[Startup]\nkeep = me")
            ->and(features_sim($grid, 'alpha_two'))
            ->toContain('DATA_SRV_2do = "https://2do.directory/helpers/register.php"')
            ->toContain('DATA_SRV_Alpha = "https://play.example.org/helpers/register.php"');
    });

    test('changes nothing the second time, and says it', function () {
        [$home] = features_tree();
        features_run(['enable', 'alpha', 'search'], $home);

        [$status, $output] = features_run(['enable', 'alpha', 'search'], $home);

        expect($status)->toBe(0)->and($output)->toContain('nothing to change: search is already enabled for alpha');
    });

    test('takes the address it is given for the services', function () {
        [$home, $grid] = features_tree();

        features_run(['enable', 'alpha', 'search', 'https://search.example.net/h/'], $home);

        expect(features_sim($grid, 'alpha_one'))
            ->toContain('SearchURL = "https://search.example.net/h/query.php"')
            ->toContain('DATA_SRV_Alpha = "https://search.example.net/h/register.php"');
    });

    test('is refused with a reason for a grid without helpers, and for what is not an address', function () {
        [$home, $grid] = features_tree(false);

        [$status, , $errors] = features_run(['enable', 'alpha', 'search'], $home);
        [$bad, , $badErrors] = features_run(['enable', 'alpha', 'search', 'nonsense'], $home);

        expect($status)
            ->toBe(1)
            ->and($errors)
            ->toContain('has no helpers: give the address of its search service')
            ->and($bad)
            ->toBe(1)
            ->and($badErrors)
            ->toContain("'nonsense' is not an address")
            ->and(features_sim($grid, 'alpha_one'))
            ->not->toContain('[Search]');
    });

    test('says when the module is not enabled for the install', function () {
        [$home] = features_tree();
        file_put_contents("$home/.opensim.conf", str_replace('EnabledModules = opensimsearch gloebit', 'EnabledModules = gloebit', (string) file_get_contents("$home/.opensim.conf")));

        [, $output] = features_run(['enable', 'alpha', 'search'], $home);

        expect($output)->toContain('note: the module opensimsearch is not in EnabledModules');
    });
});

describe('opensim disable <grid> search', function () {
    test('takes the search of the grid out of the simulators, and keeps the registration to another directory', function () {
        [$home, $grid] = features_tree();
        features_run(['enable', 'alpha', 'search'], $home);

        [$status, $output] = features_run(['disable', 'alpha', 'search'], $home);

        expect($status)
            ->toBe(0)
            ->and($output)
            ->toContain('search disabled for alpha: 2 simulators changed')
            ->and(features_sim($grid, 'alpha_one'))
            ->not->toContain('SearchURL')
            ->not->toContain('DATA_SRV_Alpha')
            // Nobody to register to: the simulator no longer indexes
            ->not->toContain('index_sims')
            ->and(features_sim($grid, 'alpha_two'))
            ->not->toContain('DATA_SRV_Alpha')
            ->toContain('DATA_SRV_2do')
            ->toContain('index_sims = true');
    });
});

describe('opensim enable <grid> economy', function () {
    test('is the beta module by default, with the sale of land and things', function () {
        [$home, $grid] = features_tree();

        [$status, $output] = features_run(['enable', 'alpha', 'economy'], $home);

        expect($status)
            ->toBe(0)
            ->and($output)
            ->toContain('economy (beta) enabled for alpha: 2 simulators changed')
            ->and(features_sim($grid, 'alpha_one'))
            ->toContain("[Economy]\neconomymodule = BetaGridLikeMoneyModule\nSellEnabled = true");
    });

    test('can be Gloebit: the module, the sandbox, the address of the economy in Robust, and the keys left to the operator', function () {
        [$home, $grid] = features_tree();

        [$status, $output] = features_run(['enable', 'alpha', 'economy', 'gloebit'], $home);

        expect($status)
            ->toBe(0)
            ->and($output)
            ->toContain('economy (gloebit) enabled for alpha: 2 simulators and Robust changed, restart to apply: opensim restart alpha alpha_one alpha_two')
            ->toContain('note: the keys of your Gloebit application are yours to give: GLBKey, GLBSecret, GLBOwnerName')
            ->and(features_sim($grid, 'alpha_one'))
            ->toContain('economymodule = Gloebit')
            ->toContain("[Gloebit]\nEnabled = true\nGLBEnvironment = sandbox")
            ->not->toContain('GLBKey')
            ->and((string) file_get_contents("$grid/Robust.HG.ini"))
            ->toContain('economy = "https://play.example.org/helpers"');
    });

    test('takes the address of the economy it is given, and refuses a module it does not know', function () {
        [$home, $grid] = features_tree();

        features_run(['enable', 'alpha', 'economy', 'gloebit', 'https://money.example.net/h'], $home);
        [$status, , $errors] = features_run(['enable', 'alpha', 'economy', 'bitcoin'], $home);

        expect((string) file_get_contents("$grid/Robust.HG.ini"))
            ->toContain('economy = "https://money.example.net/h"')
            ->and($status)
            ->toBe(1)
            ->and($errors)
            ->toContain("unknown money module 'bitcoin'");
    });

    test('goes back to the beta module of OpenSimulator when it is disabled, and Gloebit is off', function () {
        [$home, $grid] = features_tree();
        features_run(['enable', 'alpha', 'economy', 'gloebit'], $home);

        features_run(['disable', 'alpha', 'economy'], $home);

        expect(features_sim($grid, 'alpha_one'))
            ->not->toContain('economymodule')
            ->toContain("[Gloebit]\nEnabled = false");
    });
});

describe('opensim enable <grid> profiles', function () {
    test('gives the simulators the profile service of Robust, and turns it on in Robust', function () {
        [$home, $grid] = features_tree();

        [$status, $output] = features_run(['enable', 'alpha', 'profiles'], $home);

        expect($status)
            ->toBe(0)
            ->and($output)
            ->toContain('profiles enabled for alpha: 2 simulators and Robust changed, restart to apply: opensim restart alpha alpha_one alpha_two')
            ->and(features_sim($grid, 'alpha_one'))
            ->toContain("[UserProfiles]\nProfileServiceURL = \"\${Const|BaseURL}:\${Const|PublicPort}\"")
            ->and((string) file_get_contents("$grid/Robust.HG.ini"))
            ->toContain('Enabled = true')
            ->toContain('UserProfilesServiceConnector = "${Const|PublicPort}/OpenSim.Server.Handlers.dll:UserProfilesConnector"')
            ->not->toContain(';UserProfilesServiceConnector');
    });

    test('is turned off by disable, in the simulators and in Robust', function () {
        [$home, $grid] = features_tree();
        features_run(['enable', 'alpha', 'profiles'], $home);

        features_run(['disable', 'alpha', 'profiles'], $home);

        expect(features_sim($grid, 'alpha_one'))
            ->not->toContain('ProfileServiceURL')
            ->and((string) file_get_contents("$grid/Robust.HG.ini"))
            ->toContain("[UserProfilesService]\n    Enabled = false");
    });
});

describe('opensim enable with a name that is not a feature', function () {
    test('is still the enabling of an instance, and a grid that does not exist is said', function () {
        [$home] = features_tree();

        [$status, , $errors] = features_run(['enable', 'nowhere', 'search'], $home);

        expect($status)->toBe(1)->and($errors)->toContain('no grid called nowhere');
        [$bad, , $usage] = features_run(['enable', 'alpha', 'profiles', 'https://x.example.org'], $home);
        expect($bad)->toBe(2)->and($usage)->toContain('usage: opensim enable|disable');
    });
});
