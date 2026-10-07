<?php
/**
 * The MoneyServer.ini of a grid: the original of the MoneyServer, whole, with what the grid set over it, and the key the
 * helpers share with the server made at random once.
 */

use OpenSim\Installer\Grid\MoneyServerConfig;

/** A folder for a grid. */
function money_grid(): string
{
    $dir = test_tmp() . '/money-' . bin2hex(random_bytes(4));
    mkdir($dir);

    return $dir;
}

describe('The MoneyServer.ini of a grid', function () {
    test('is made from the original of the MoneyServer, whole, with a key made at random where the MoneyServer reads it', function () {
        $original = MoneyServerConfig::original();
        $text = MoneyServerConfig::render(money_grid());

        expect($original)->not->toBeNull();
        expect($text)
            ->toMatch('/^\[MoneyServer\]/m')
            ->toMatch('/^MoneyScriptAccessKey = "[0-9a-f]{32}"$/m')
            // The setting of the original, and not the example everybody knows
            ->not->toContain('123456789"')
            // The whole file: its other settings and its comments are there, nothing is picked
            ->toContain('EnableForceTransfer = true')
            ->toContain('MoneyScriptIPaddress');
    });

    test('keeps what the grid has set, takes the settings the original has that it lacks, and keeps its own key', function () {
        $dir = money_grid();
        file_put_contents("$dir/MoneyServer.ini", "[MoneyServer]\nEnableForceTransfer = false\nMoneyScriptAccessKey = \"mine\"\n[Custom]\nKeepMe = yes\n");
        $text = MoneyServerConfig::render($dir);

        expect($text)
            ->toContain('EnableForceTransfer = false')
            ->not->toContain('EnableForceTransfer = true')
            ->toContain('MoneyScriptAccessKey = "mine"')
            ->toContain("[Custom]\nKeepMe = yes")
            // What the original has and the grid had not
            ->toContain('EnableHGAvatar');
    });

    test('is written once with the same key, kept beside its previous version when it changes, readable by its group only', function () {
        $dir = money_grid();
        $path = MoneyServerConfig::write($dir);
        $key = preg_match('/^MoneyScriptAccessKey = "(\w+)"/m', (string) file_get_contents($path), $m) ? $m[1] : null;

        expect($path)->toBe("$dir/MoneyServer.ini");
        expect(decoct(fileperms($path) & 0o777))->toBe('640');
        // Nothing changes the second time, the key is the same
        expect(MoneyServerConfig::write($dir))->toBeNull();
        expect(MoneyServerConfig::ensure($dir))->toBeNull();

        // The grid adds a section of its own: the file is made again with it, the same key, the previous one kept beside it
        file_put_contents($path, (string) file_get_contents($path) . "\n[Custom]\nKeepMe = yes\n");
        expect(MoneyServerConfig::write($dir))->toBe($path);
        expect((string) file_get_contents($path))->toContain('MoneyScriptAccessKey = "' . $key . '"')->toContain('KeepMe = yes');
        expect(file_exists("$path~"))->toBeTrue();
        expect(MoneyServerConfig::write($dir))->toBeNull();
    });

    test('is not made up when there is no original', function () {
        expect(MoneyServerConfig::render(money_grid(), money_grid() . '/missing.ini'))->toBeNull();
    });

    test('is added to a grid that has none by ensure, and left alone when it has one', function () {
        $dir = money_grid();

        expect(MoneyServerConfig::ensure($dir))->toBe("$dir/MoneyServer.ini");
        file_put_contents("$dir/MoneyServer.ini", "[MoneyServer]\nMoneyScriptAccessKey = \"mine\"\n");
        expect(MoneyServerConfig::ensure($dir))->toBeNull();
        expect((string) file_get_contents("$dir/MoneyServer.ini"))->toBe("[MoneyServer]\nMoneyScriptAccessKey = \"mine\"\n");
    });
});
