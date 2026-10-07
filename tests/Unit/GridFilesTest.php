<?php

/**
 * The files of a grid belong to the system user (mode 640) and the setup runs as the person who started it: it reads them
 * through the system user, and never takes a file it cannot read for an empty one (a simulator without a port became a
 * region on port 1).
 */

use OpenSim\Installer\Grid\GridFiles;
use OpenSim\Installer\SetupFailed;

/** A file nobody but its owner and root reads, in a folder of its own. */
function locked_file(string $text): string
{
    $dir = test_tmp() . '/gridfiles-' . bin2hex(random_bytes(4));
    mkdir($dir);
    $file = "$dir/sim.ini";
    file_put_contents($file, $text);
    chmod($file, 0o000);

    return $file;
}

function unlock(string $file): void
{
    chmod($file, 0o600);
    unlink($file);
    rmdir(dirname($file));
}

describe('Reading a file of the grid', function () {
    test('gives its text, and an empty text for a file that does not exist', function () {
        $file = test_tmp() . '/gridfiles-' . bin2hex(random_bytes(4)) . '.ini';
        file_put_contents($file, "[Network]\nhttp_listener_port = 9120\n");

        expect(GridFiles::read($file))
            ->toBe("[Network]\nhttp_listener_port = 9120\n")
            ->and(GridFiles::read("$file.missing"))
            ->toBe('');
        unlink($file);
    });

    test('is done by the system user when the person cannot read it', function () {
        $file = locked_file("http_listener_port = 9120\n");
        $asked = '';
        $text = GridFiles::read($file, function (string $path) use (&$asked): string {
            $asked = $path;

            return "http_listener_port = 9120\n";
        });
        unlock($file);

        expect($text)->toBe("http_listener_port = 9120\n");
        expect($asked)->toBe($file);
    })->skip(fn() => posix_geteuid() === 0, 'root reads everything');

    test('is an error when nobody can read it, not an empty text', function () {
        $file = locked_file("http_listener_port = 9120\n");

        try {
            expect(fn() => GridFiles::read($file, fn() => null))->toThrow(SetupFailed::class);
        } finally {
            unlock($file);
        }
    })->skip(fn() => posix_geteuid() === 0, 'root reads everything');
});
