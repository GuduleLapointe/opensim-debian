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
