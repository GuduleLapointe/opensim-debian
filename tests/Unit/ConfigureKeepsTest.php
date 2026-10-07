<?php
/**
 * Configuring a grid or a simulator that exists changes the values the setup manages and nothing else: what the operator
 * added (settings, sections), and what the setup only proposes, stay.
 */

use OpenSim\Installer\Grid\GridPlan;
use OpenSim\Installer\Grid\RobustConfig;
use OpenSim\Installer\Grid\SimConfig;
use OpenSim\Installer\Grid\SimPlan;

describe('Configuring a grid that exists', function () {
    /** A grid plan over a folder that has the Robust.HG.ini.example of a distribution. */
    $plan = function (): GridPlan {
        $root = test_tmp() . '/configure-' . bin2hex(random_bytes(4));
        mkdir("$root/bin", 0o755, true);
        mkdir("$root/grid", 0o755, true);
        file_put_contents(
            "$root/bin/Robust.HG.ini.example",
            "[Const]\n    BaseHostname = \"127.0.0.1\"\n[LoginService]\n    WelcomeMessage = \"Welcome to OpenSim!\"\n    SearchURL = \"\"\n[LibraryService]\n    LibraryName = \"OpenSim Library\"\n[NewThing]\n    FromTheDistribution = \"yes\"\n",
        );
        $plan = new GridPlan();
        $plan->binDir = "$root/bin";
        $plan->gridDir = "$root/grid";
        $plan->gridName = 'Alpha';
        $plan->gridNick = 'alpha';
        $plan->baseHostname = 'play.example.org';
        $plan->webUrl = 'https://play.example.org';
        $plan->etcDirectory = "$root/grid";
        $plan->dataDirectory = "$root/data";
        $plan->cacheDirectory = "$root/cache";
        $plan->logsDirectory = "$root/logs";

        return $plan;
    };

    test('tells the viewers the web search of the helpers when the grid has helpers and says nothing of it, and keeps what it says', function () use ($plan) {
        $plan = $plan();
        $plan->helpers = true;
        $plan->helpersPath = '/helpers';
        $robust = new RobustConfig();

        $text = $robust->generate($plan);

        expect($text)->toContain('search = "${Const|WebURL}/search"')->toContain('SearchURL = "${Const|WebURL}/search"');

        // The grid has its own: the setup leaves it
        file_put_contents($plan->robustIni(), str_replace('search = "${Const|WebURL}/search"', 'search = "https://other.example.org/q"', $text));
        $kept = $robust->generate($plan);

        expect($kept)->toContain('search = "https://other.example.org/q"')->not->toContain('search = "${Const|WebURL}/search"');
    });

    test('gives the search of the web to both settings that say it when only one is given, and keeps two that differ', function () use ($plan) {
        $plan = $plan();
        $robust = new RobustConfig();

        // Nothing given: nothing made up
        $text = $robust->generate($plan);
        expect($text)->not->toMatch('/^\s*search\s*=/m');

        // search only: SearchURL of the login service gets it
        file_put_contents($plan->robustIni(), str_replace('[GridInfoService]', "[GridInfoService]\n    search = \"https://play.example.org/search\"", $text));
        $both = $robust->generate($plan);
        expect($both)->toContain('SearchURL = "https://play.example.org/search"')->and($both)->toContain('search = "https://play.example.org/search"');

        // SearchURL only: search gets it
        file_put_contents($plan->robustIni(), str_replace('SearchURL = ""', 'SearchURL = "https://play.example.org/find"', $text));
        $both = $robust->generate($plan);
        expect($both)->toContain('SearchURL = "https://play.example.org/find"')->and($both)->toContain('search = "https://play.example.org/find"');

        // Two values: the operator's, none is replaced
        file_put_contents($plan->robustIni(), str_replace(['SearchURL = ""', '[GridInfoService]'], ['SearchURL = "https://a.example.org/"', "[GridInfoService]\n    search = \"https://b.example.org/\""], $text));
        $kept = $robust->generate($plan);
        expect($kept)->toContain('SearchURL = "https://a.example.org/"')->and($kept)->toContain('search = "https://b.example.org/"');
    });

    test('changes the values the setup manages and keeps the settings and the sections of the operator, and the ones the distribution has', function () use ($plan) {
        $plan = $plan();
        $robust = new RobustConfig();
        file_put_contents($plan->robustIni(), $robust->generate($plan));
        // The operator adds a setting, a section, and changes what the setup only proposes
        file_put_contents(
            $plan->robustIni(),
            str_replace(
                'WelcomeMessage = "Welcome to Alpha, <USERNAME>!"',
                'WelcomeMessage = "Hello, <USERNAME>"',
                (string) file_get_contents($plan->robustIni()),
            ) . "\n[Gloebit]\nGLBEnvironment = sandbox\n",
        );
        // The grid is configured again with another host
        $plan->baseHostname = 'new.example.org';
        $text = $robust->generate($plan);

        expect($text)
            ->toContain('BaseHostname = "new.example.org"')
            ->not->toContain('BaseHostname = "play.example.org"')
            ->toContain('WelcomeMessage = "Hello, <USERNAME>"')
            ->toContain("[Gloebit]\nGLBEnvironment = sandbox")
            ->toContain('FromTheDistribution = "yes"');
    });

    test('starts from the distribution, and proposes what it proposes, for a grid that has nothing yet', function () use ($plan) {
        $text = (new RobustConfig())->generate($plan());

        expect($text)->toContain('WelcomeMessage = "Welcome to Alpha, <USERNAME>!"')->toContain('LibraryName = "Alpha Library"');
    });
});

describe('Configuring a simulator that exists', function () {
    test('changes what the setup manages, keeps what the operator added', function () {
        $dir = test_tmp() . '/sim-' . bin2hex(random_bytes(4));
        mkdir("$dir/sims", 0o755, true);
        $plan = new SimPlan();
        $plan->gridDir = $dir;
        $plan->gridNick = 'alpha';
        $plan->gridName = 'Alpha';
        $plan->slug = 'alpha_sim1';
        $plan->simName = 'Sim1';
        $plan->baseHostname = 'play.example.org';
        $plan->httpPort = 9000;
        $config = new SimConfig();

        $config->write($plan);
        file_put_contents($plan->iniPath(), (string) file_get_contents($plan->iniPath()) . "\n[Gloebit]\nGLBEnvironment = sandbox\n");
        $plan->httpPort = 9010;
        $config->write($plan);
        $text = (string) file_get_contents($plan->iniPath());

        expect($text)
            ->toContain('http_listener_port = 9010')
            ->not->toContain('http_listener_port = 9000')
            ->toContain("[Gloebit]\nGLBEnvironment = sandbox")
            ->toContain(';; Generated by opensim setup: simulator Sim1 of the grid Alpha')
            ->and(SimConfig::readName($plan->iniPath()))
            ->toBe('Sim1');
    });

    test('has the profile service of the grid, and the search module and its registration when the grid has helpers', function () {
        $dir = test_tmp() . '/sim-' . bin2hex(random_bytes(4));
        mkdir("$dir/sims", 0o755, true);
        $make = function (bool $helpers) use ($dir) {
            $plan = new SimPlan();
            $plan->gridDir = $dir;
            $plan->gridNick = 'alpha';
            $plan->gridName = 'Alpha';
            $plan->slug = $helpers ? 'alpha_with' : 'alpha_without';
            $plan->simName = 'Sim';
            $plan->baseHostname = 'play.example.org';
            $plan->httpPort = 9000;
            if ($helpers) {
                $plan->searchUrl = 'https://play.example.org/helpers/query.php';
                $plan->registerUrl = 'https://play.example.org/helpers/register.php';
            }
            (new SimConfig())->write($plan);

            return (string) file_get_contents($plan->iniPath());
        };

        expect($make(true))
            ->toContain("[Search]\nModule = OpenSimSearch\nSearchURL = \"https://play.example.org/helpers/query.php\"")
            ->toContain('DATA_SRV_Alpha = "https://play.example.org/helpers/register.php"')
            ->toContain("[UserProfiles]\nProfileServiceURL = \"\${Const|BaseURL}:\${Const|PublicPort}\"")
            ->and($make(false))
            ->not->toContain('[Search]')
            ->toContain('ProfileServiceURL');
    });
});
