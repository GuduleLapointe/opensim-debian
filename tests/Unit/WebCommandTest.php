<?php
/**
 * `opensim web`: the web side of a grid, from libexec/web.php.
 */

use OpenSim\Installer\Grid\GridPlan;
use OpenSim\Installer\Grid\RobustConfig;

/**
 * Run libexec/web.php as a user whose setup is a temporary tree.
 *
 * @param list<string> $arguments What follows `opensim web`.
 * @param string $home The home of that user.
 * @return array{0:int,1:string,2:string} Exit status, output, errors.
 */
function web_run(array $arguments, string $home): array
{
    $root = dirname(__DIR__, 2);
    $process = proc_open(
        [PHP_BINARY, "$root/libexec/web.php", ...$arguments],
        [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes,
        $root,
        ['HOME' => $home, 'PATH' => getenv('PATH')],
    );
    $output = stream_get_contents($pipes[1]);
    $errors = stream_get_contents($pipes[2]);

    return [proc_close($process), (string) $output, trim((string) $errors)];
}

/**
 * A setup with the given grids, the first one with a helpers.ini.
 *
 * @param list<string> $nicks
 * @param string $helpers The content of the helpers.ini of the first grid.
 * @return string The home of the user.
 */
function web_home(array $nicks = ['alpha'], string $helpers = ''): string
{
    $root = sys_get_temp_dir() . '/web-' . bin2hex(random_bytes(4));
    $etc = "$root/etc";
    mkdir("$root/home", 0o755, true);
    file_put_contents(
        "$root/home/.opensim.conf",
        "[Defaults]\nDefaultProfile = 0.9.3.0\n\n[0.9.3.0]\nEtcRoot = $etc\n",
    );
    foreach ($nicks as $i => $nick) {
        mkdir("$etc/grids/$nick", 0o755, true);
        file_put_contents("$etc/grids/$nick/Robust.HG.ini", "[Const]\n    WebURL = \"https://$nick.example.org\"\n");
        if ($i === 0 && $helpers !== '') {
            file_put_contents("$etc/grids/$nick/helpers.ini", $helpers);
        }
    }

    return "$root/home";
}

describe('opensim web', function () {
    test('tells where the services are, by default under /helpers', function () {
        [$status, $output] = web_run([], web_home());

        expect($status)->toBe(0);
        expect($output)->toContain('Grid:      alpha');
        expect($output)->toContain('https://alpha.example.org/helpers/query.php');
        expect($output)->toContain('https://alpha.example.org/helpers/currency.php');
    });

    test('follows the path and the urls of the grid', function () {
        [, $output] = web_run(
            ['show'],
            web_home(['alpha'], "[Helpers]\npath = \"/helper\"\n[Urls]\nsearch = \"/search\"\n"),
        );

        expect($output)->toContain('https://alpha.example.org/helper/currency.php');
        expect($output)->toContain('https://alpha.example.org/search');
        expect($output)->not->toContain('/helpers/');
    });

    test('writes the snippet of the web server with the aliases the grid needs', function () {
        [$status, $output] = web_run(
            ['snippet', 'caddy'],
            web_home(['alpha'], "[Helpers]\npath = \"/helper\"\n[Urls]\nguide = \"/guide\"\n"),
        );

        expect($status)->toBe(0);
        expect($output)->toContain('handle /helper/* {');
        expect($output)->toContain("handle /guide {\n\troot * /usr/share/opensim-helpers\n\trewrite * /guide.php");
        expect($output)->toContain('env OPENSIM_GRID alpha');
    });

    test('asks which grid when there are several, and refuses a server it does not write', function () {
        $home = web_home(['alpha', 'beta']);

        [$status, , $errors] = web_run(['show'], $home);
        expect($status)->toBe(2);
        expect($errors)->toContain('several grids');

        [$status, $output] = web_run(['snippet', 'nginx', '--grid', 'beta'], $home);
        expect($status)->toBe(0);
        expect($output)->toContain('fastcgi_param OPENSIM_GRID beta;');

        [$status] = web_run(['snippet', 'lighttpd', '--grid=beta'], $home);
        expect($status)->toBe(2);
    });

    test('says when there is no grid', function () {
        [$status, , $errors] = web_run([], web_home([]));

        expect($status)->toBe(2);
        expect($errors)->toContain('no grid of this machine');
    });
});

describe('Robust config with the helpers', function () {
    /** The Robust config a plan generates, from a minimal example. */
    function web_robust(GridPlan $plan): string
    {
        $bin = sys_get_temp_dir() . '/web-robust-' . bin2hex(random_bytes(4));
        mkdir($bin);
        file_put_contents(
            "$bin/Robust.HG.ini.example",
            "[Const]\n[GridInfoService]\n    ;economy = \${Const|BaseURL}:8002/\n    ;search = x\n",
        );
        $plan->binDir = $bin;

        return (new RobustConfig())->generate($plan);
    }

    test('tells the viewers where the services are', function () {
        $plan = new GridPlan();
        $plan->baseHostname = 'localhost';
        $plan->helpers = true;
        $plan->helpersPath = '/helper';
        $plan->helpersUrls = ['search' => '/search'];

        $ini = web_robust($plan);

        expect($ini)->toContain('economy = "${Const|WebURL}/helper/currency.php"');
        expect($ini)->toContain('search = "${Const|WebURL}/search"');
        expect($ini)->toContain('message = "${Const|WebURL}/helper/offline.php"');
    });

    test('tells nothing of them when the grid has none', function () {
        $plan = new GridPlan();
        $plan->baseHostname = 'localhost';

        expect(web_robust($plan))->not->toMatch('/^\s*economy\s*=/m');
    });
});
