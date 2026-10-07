<?php
/**
 * Ini: the config file of OpenSimulator edited without losing its comments, and without leaving two values for a key.
 */

use OpenSim\Installer\Ini;

describe('Ini set', function () {
    test('replaces the key a section has, not the commented example that comes before it', function () {
        $ini = new Ini(
            <<<'INI'
            [DatabaseService]
                ;StorageProvider = "OpenSim.Data.PGSQL.dll"
                ;ConnectionString = "Server=localhost;Database=opensim;User Id=opensim; password=***;"

                StorageProvider = "OpenSim.Data.MySQL.dll"
                ConnectionString = "Data Source=localhost;Database=opensim;User ID=opensim;Password=*****;"

            [Other]
            INI
        );

        $ini->set('DatabaseService', 'ConnectionString', '"Data Source=localhost;Database=mine;User ID=u;Password=p;"');
        $text = $ini->toString();

        // One active value, the example of PostgreSQL still a comment
        expect(preg_match_all('/^\s*ConnectionString\s*=/m', $text))->toBe(1);
        expect($text)->toContain(';ConnectionString = "Server=localhost');
        expect($text)->toContain('ConnectionString = "Data Source=localhost;Database=mine;');
        expect($text)->not->toContain('Password=*****');
    });

    test('uncomments an example when the section has no active key', function () {
        $ini = new Ini("[Network]\n    ;ConsolePort = 0\n");

        $ini->set('Network', 'ConsolePort', '8004');

        expect($ini->toString())->toContain('ConsolePort = 8004')->not->toContain(';ConsolePort');
    });
});

describe('Ini in layers', function () {
    $distribution = <<<'INI'
        ;; the distribution
        [LoginService]
            ; shown at login
            WelcomeMessage = "Welcome to OpenSim!"
            ;SearchURL = ""
            NewInThisVersion = "default"

        [Hypergrid]
            GatekeeperURI = "http://localhost:8002"
        INI;

    test('keep every setting of the distribution, with what the grid has set over it', function () use ($distribution) {
        $dir = test_tmp() . '/layers-' . bin2hex(random_bytes(4));
        mkdir($dir);
        file_put_contents("$dir/dist.ini", $distribution);
        file_put_contents("$dir/grid.ini", "[LoginService]\nWelcomeMessage = \"Mine\"\nSearchURL = \"http://search\"\n[Custom]\nKeepMe = yes\n; a comment of the operator\n");

        $text = Ini::layered("$dir/dist.ini", "$dir/grid.ini")->toString();

        // What the grid set, the example it activated, a section of its own; a setting new in the distribution is there
        expect($text)
            ->toContain('WelcomeMessage = "Mine"')
            ->not->toContain('Welcome to OpenSim!')
            ->toContain('SearchURL = "http://search"')
            ->not->toContain(';SearchURL')
            ->toContain("[Custom]\nKeepMe = yes")
            ->toContain('NewInThisVersion = "default"')
            ->toContain('; shown at login')
            ->toContain('GatekeeperURI = "http://localhost:8002"');
    });

    test('are the distribution itself for a grid that has nothing yet, and what a file that is the same gives unchanged', function () use ($distribution) {
        $dir = test_tmp() . '/layers-' . bin2hex(random_bytes(4));
        mkdir($dir);
        file_put_contents("$dir/dist.ini", $distribution);

        expect(Ini::layered("$dir/dist.ini", null)->toString())
            ->toBe($distribution)
            ->and(Ini::layered("$dir/dist.ini", "$dir/dist.ini")->toString())
            ->toBe($distribution);
    });

    test('count a key given twice in a section once, the first, as OpenSimulator reads it', function () {
        $ini = new Ini("[A]\none = base\n");
        $ini->overlay("[A]\none = first\none = second\n");

        expect($ini->toString())->toContain('one = first')->not->toContain('second');
    });
});

