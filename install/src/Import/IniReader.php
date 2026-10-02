<?php

declare(strict_types=1);

namespace OpenSim\Installer\Import;

/**
 * Reads the configuration of an OpenSimulator or Robust install the way the core does, to port it: the file, the
 * files it includes (`Include-*` keys, the `ConfigDirectory` of Robust), `${Const|Name}` references. Read only.
 *
 * Values are as written (quotes removed), references kept: what is ported is the file of the user, not its
 * evaluation. `expand()` gives the evaluated values, to read what the setup asks (a port, a hostname).
 */
final class IniReader
{
    /**
     * @param list<string> $seen the files already read (an include loop ends)
     * @return array<string,array<string,string>> section => key => value, the files included read in place
     */
    public static function load(string $path, array $seen = []): array
    {
        $real = realpath($path) ?: $path;
        if (in_array($real, $seen, true) || !is_readable($path)) {
            return [];
        }
        $seen[] = $real;

        return self::parse((string) file_get_contents($path), dirname($path), $seen, $path);
    }

    /**
     * A config given as text: includes are looked for from $dir, none when it is empty.
     *
     * @param list<string> $seen
     * @return array<string,array<string,string>>
     */
    public static function parse(string $text, string $dir = '', array $seen = [], string $path = ''): array
    {
        $ini = [];
        $section = '';
        foreach (explode("\n", str_replace("\r", '', $text)) as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === ';' || $line[0] === '#') {
                continue;
            }
            if (preg_match('/^\[([^\]]+)\]/', $line, $m)) {
                $section = trim($m[1]);
                $ini[$section] ??= [];
                continue;
            }
            if (!preg_match('/^([^=]+?)\s*=\s*(.*)$/', $line, $m)) {
                continue;
            }
            $key = trim($m[1]);
            $value = trim($m[2]);
            if (preg_match('/^"(.*)"\s*(?:[;#].*)?$/', $value, $quoted)) {
                $value = $quoted[1];
            }
            if (stripos($key, 'Include-') === 0 && $value !== '') {
                // Kept, to tell what the file includes (a standalone architecture, for instance)
                $ini[$section][$key] = $value;
                if ($dir !== '') {
                    $ini = self::merge($ini, self::include($value, $dir, $seen));
                }
                continue;
            }
            $ini[$section][$key] = $value;
        }

        // Robust reads the *.ini files of its ConfigDirectory too
        $directory = $ini['Startup']['ConfigDirectory'] ?? '';
        if ($dir !== '' && $directory !== '' && !str_contains($directory, '${')) {
            $directory = str_starts_with($directory, '/') ? $directory : "$dir/$directory";
            foreach (glob(rtrim($directory, '/') . '/*.ini') ?: [] as $file) {
                $ini = self::merge($ini, self::load($file, $seen));
            }
        }

        return $ini;
    }

    /**
     * The values with the references of [Const] replaced by their value (a few levels).
     *
     * @param array<string,array<string,string>> $ini
     * @return array<string,array<string,string>>
     */
    public static function expand(array $ini): array
    {
        $const = $ini['Const'] ?? [];
        $replace = static function (string $value) use (&$const): string {
            for ($i = 0; $i < 5 && str_contains($value, '${Const|'); $i++) {
                $value = (string) preg_replace_callback(
                    '/\$\{Const\|([^}]+)\}/',
                    static fn(array $m): string => $const[$m[1]] ?? $m[0],
                    $value,
                );
            }

            return $value;
        };
        foreach ($const as $key => $value) {
            $const[$key] = $replace($value);
        }
        foreach ($ini as $section => $values) {
            foreach ($values as $key => $value) {
                $ini[$section][$key] = $replace($value);
            }
        }

        return $ini;
    }

    /**
     * @param array<string,array<string,string>> $a
     * @param array<string,array<string,string>> $b what comes later wins
     * @return array<string,array<string,string>>
     */
    private static function merge(array $a, array $b): array
    {
        foreach ($b as $section => $values) {
            $a[$section] = array_merge($a[$section] ?? [], $values);
        }

        return $a;
    }

    /**
     * An included file: relative to the file that includes it, or to the folder above (OpenSimulator looks
     * from its bin folder, where `config-include/` is).
     *
     * @param list<string> $seen
     * @return array<string,array<string,string>>
     */
    private static function include(string $value, string $dir, array $seen): array
    {
        foreach (str_starts_with($value, '/') ? [$value] : ["$dir/$value", dirname($dir) . "/$value"] as $candidate) {
            if (is_file($candidate)) {
                return self::load($candidate, $seen);
            }
        }

        return [];
    }
}
