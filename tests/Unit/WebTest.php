<?php
/**
 * The web side of a grid: where its services are, its Helpers.ini, what the web server needs.
 */

use OpenSim\Installer\Web\HelpersConfig;
use OpenSim\Installer\Web\Services;
use OpenSim\Installer\Web\Snippets;

describe('Services', function () {
    test('are under /helpers by default', function () {
        $services = new Services();

        expect($services->path('query'))->toBe('/helpers/query.php');
        expect($services->path('currency'))->toBe('/helpers/currency.php');
        expect($services->url('https://play.example.org/', 'offline'))->toBe(
            'https://play.example.org/helpers/offline.php',
        );
    });

    test('follow the path of the economy', function () {
        $services = new Services('helper/');

        expect($services->path('currency'))->toBe('/helper/currency.php');
        expect($services->path('query.php'))->toBe('/helper/query.php');
        expect($services->path('guide'))->toBe('/helper/guide.php');
    });

    test('have the path of an address of the Robust config, with its constants', function () {
        expect(Services::pathOf('${Const|WebURL}/helper/'))
            ->toBe('/helper')
            ->and(Services::pathOf('https://play.example.org/currency'))
            ->toBe('/currency')
            ->and(Services::pathOf('${Const|WebURL}'))
            ->toBe('/');
    });
});

describe('Helpers.ini', function () {
    test('is a template that sets nothing: every setting is commented out, none is one of the official files', function () {
        $text = HelpersConfig::template();

        expect($text)->toContain('[Helpers]');
        expect($text)->toContain(';; GuideSource = "destinations.txt"');
        expect($text)->toContain(';; SearchConnectionString = ');
        // What has its place in OpenSim.ini or Robust.ini is not copied there
        expect($text)->not->toMatch('/^\s*(grid_name|login_uri|web_url|gridname|ConnectionString)\b/m');
        expect($text)->not->toMatch('/^\[(robust_db|Urls)\]/m');
    });

    test('is the name of the file, in the folder of the grid', function () {
        expect(HelpersConfig::path('/etc/opensim/grids/alpha/'))->toBe('/etc/opensim/grids/alpha/Helpers.ini');
    });

    test('gives the web address and the path of the helpers from the Robust config, the economy being their path', function () {
        $dir = test_tmp() . '/helpers-ini-' . bin2hex(random_bytes(4));
        mkdir($dir);
        file_put_contents(
            "$dir/Robust.HG.ini",
            "[Const]\n    WebURL = \"https://play.example.org\"\n[GridInfoService]\n    economy = \"\${Const|WebURL}/helper\"\n",
        );

        expect(HelpersConfig::webUrl($dir))
            ->toBe('https://play.example.org')
            ->and(HelpersConfig::basePath($dir))
            ->toBe('/helper')
            ->and(HelpersConfig::services($dir)->path('currency'))
            ->toBe('/helper/currency.php');

        $bare = test_tmp() . '/helpers-ini-' . bin2hex(random_bytes(4));
        mkdir($bare);
        expect(HelpersConfig::basePath($bare))->toBe('/helpers')->and(HelpersConfig::webUrl($bare))->toBe('');
    });
});

describe('Snippets for the web server', function () {
    test('send what is not a file to index.php', function () {
        $caddy = Snippets::render('caddy', 'alpha');
        $nginx = Snippets::render('nginx', 'alpha');
        $apache = Snippets::render('apache', 'alpha');

        expect($caddy)
            ->toContain('root * /var/www/html')
            ->and($caddy)
            ->toContain('try_files {path} /index.php')
            ->and($nginx)
            ->toContain('try_files $uri $uri/ /index.php?$query_string')
            ->and($apache)
            ->toContain('FallbackResource /index.php');
    });

    test('hand a script that is not a file to the router, nginx included', function () {
        expect(Snippets::render('nginx', 'alpha'))
            ->toContain('try_files $uri /index.php?$query_string;')
            ->toContain('include fastcgi_params;')
            ->not->toContain('snippets/fastcgi-php.conf');
    });

    test('have the grid in the PHP environment', function () {
        expect(Snippets::render('caddy', 'alpha'))
            ->toContain('env OPENSIM_GRID alpha')
            ->and(Snippets::render('nginx', 'alpha'))
            ->toContain('fastcgi_param OPENSIM_GRID alpha;')
            ->and(Snippets::render('apache', 'alpha'))
            ->toContain('SetEnv OPENSIM_GRID alpha');
    });

    test('are complete, for the site of the web URL', function () {
        $url = 'https://play.example.org';

        expect(Snippets::render('caddy', 'alpha', webUrl: $url))
            ->toContain("play.example.org {\n\troot * /var/www/html")
            ->and(Snippets::render('nginx', 'alpha', webUrl: $url))
            ->toContain("server {\n\tlisten 80;")
            ->toContain('server_name play.example.org;')
            ->and(Snippets::render('apache', 'alpha', webUrl: $url))
            ->toContain("<VirtualHost *:80>\n\tServerName play.example.org")
            ->toContain('</VirtualHost>');
    });

    test('follow the scheme and the port of the web URL', function () {
        expect(Snippets::render('caddy', 'alpha', webUrl: 'http://play.example.org:8080'))
            ->toContain('http://play.example.org:8080 {')
            ->and(Snippets::render('caddy', 'alpha', webUrl: 'https://play.example.org:8443'))
            ->toContain('play.example.org:8443 {')
            ->and(Snippets::render('caddy', 'alpha', webUrl: 'https://play.example.org:443'))
            ->toContain("play.example.org {\n")
            ->and(Snippets::render('nginx', 'alpha', webUrl: 'http://play.example.org:8080'))
            ->toContain('listen 8080;')
            ->and(Snippets::render('apache', 'alpha', webUrl: 'http://play.example.org:8080'))
            ->toContain('<VirtualHost *:8080>');
    });

    test('answer any host when the grid has no web URL', function () {
        expect(Snippets::render('caddy', 'alpha'))
            ->toContain("\n:80 {\n")
            ->and(Snippets::render('nginx', 'alpha'))
            ->not->toContain('server_name')
            ->and(Snippets::render('apache', 'alpha'))
            ->not->toContain('ServerName');
    });

    test('take another root and socket', function () {
        expect(Snippets::render('nginx', 'alpha', '/srv/site', '/run/php/php8.3-fpm.sock'))
            ->toContain('root /srv/site;')
            ->toContain('unix:/run/php/php8.3-fpm.sock');
    });

    test('refuse an unknown server', function () {
        expect(fn() => Snippets::render('lighttpd', 'alpha'))->toThrow(InvalidArgumentException::class);
    });
});

describe('Pages', function () {
    test('have a default path', function () {
        expect((new Services())->path('home'))
            ->toBe('/')
            ->and((new Services())->path('welcome'))
            ->toBe('/welcome')
            ->and((new Services())->path('health'))
            ->toBe('/health');
    });
});
