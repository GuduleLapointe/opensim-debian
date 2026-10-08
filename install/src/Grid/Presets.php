<?php

declare(strict_types=1);

namespace OpenSim\Installer\Grid;

use OpenSim\Installer\Ini;
use OpenSim\Installer\TextFile;

/**
 * The presets of the grids a simulator can join: for each grid, a folder with the structure of the folder of a grid
 * (<etc>/grids/<grid>), holding only the files that have settings of that grid. Any file may be there, none is required.
 *
 * grids/ is for the grids configured on this machine, presets/ for the base of the grids that are elsewhere. The presets of
 * the kit are in /usr/share (share/presets); the simulator that joins a grid by a preset gets it copied into <etc>/presets,
 * where the simulators read it, and where the administrator can change it (not the point) or add their own presets.
 */
final class Presets
{
    /** @var list<string> the folders that hold presets, the first that has one wins */
    private array $roots;

    /** @param list<string> $roots the folders of presets, the first is the one of the machine (where they are copied and read); empty: <etc>/presets and the ones of the kit */
    public function __construct(array $roots = [], string $etcRoot = '/etc/opensim')
    {
        $this->roots = $roots !== [] ? $roots : ["$etcRoot/presets", dirname(__DIR__, 3) . '/share/presets'];
    }

    /**
     * The presets there are, by name (the name of their folder, the nick of the grid they make: speculoos).
     *
     * @return array<string,string> name => name of the grid
     */
    public function names(): array
    {
        $names = [];
        foreach ($this->roots as $root) {
            foreach (glob("$root/*", GLOB_ONLYDIR) ?: [] as $dir) {
                $name = basename($dir);
                if (!isset($names[$name]) && $this->path($name) !== null) {
                    $names[$name] = $this->describe($name)['name'] ?? $name;
                }
            }
        }
        ksort($names);

        return $names;
    }

    /** The folder of a preset, null when there is none of that name. */
    public function path(string $name): ?string
    {
        if (!self::valid($name)) {
            return null;
        }
        foreach ($this->roots as $root) {
            if (is_dir("$root/$name")) {
                return "$root/$name";
            }
        }

        return null;
    }

    /**
     * A setting of a preset, from the first of its files that says it.
     *
     * @param list<string> $files relative to the folder of the preset
     */
    private function setting(string $name, string $section, string $key, array $files = ['config-include/GridCommon.ini', 'OpenSim.ini']): ?string
    {
        foreach ($files as $file) {
            $path = $this->path($name) . "/$file";
            if (is_file($path) && ($value = (new Ini(TextFile::read($path)))->get($section, $key)) !== null) {
                return $value;
            }
        }

        return null;
    }

    /**
     * What the setup needs to know of the grid, from the preset: its gatekeeper (host and public port), the port of its
     * private services, its name, the nick it gets here.
     *
     * @return array{name:string,nick:string,host:string,publicPort:int,privatePort:int,hypergrid:bool}|null null when the preset does not say where the grid is
     */
    public function describe(string $name): ?array
    {
        if ($this->path($name) === null) {
            return null;
        }
        $gatekeeper = self::address($this->setting($name, 'Hypergrid', 'GatekeeperURI') ?? $this->setting($name, 'Messaging', 'Gatekeeper') ?? '');
        if ($gatekeeper === null) {
            return null;
        }
        $private = self::address($this->setting($name, 'GridService', 'GridServerURI') ?? '');

        return [
            'name' => $this->setting($name, 'DataSnapshot', 'gridname') ?? $name,
            'nick' => (string) preg_replace('/[^A-Za-z0-9]/', '', $name),
            'host' => $gatekeeper['host'],
            'publicPort' => $gatekeeper['port'],
            'privatePort' => $private['port'] ?? $gatekeeper['port'] + 1,
            'hypergrid' => $this->setting($name, 'Hypergrid', 'GatekeeperURI') !== null,
        ];
    }

    /**
     * The files of the preset that a simulator includes, in the folder of the machine (see install()), in the order they win.
     * Only those the preset has.
     *
     * @return array<string,string> name of the include => path
     */
    public function files(string $name): array
    {
        $files = [];
        foreach (['GridCommon' => 'config-include/GridCommon.ini', 'OpenSim' => 'OpenSim.ini'] as $include => $file) {
            foreach ($this->roots as $root) {
                if (self::valid($name) && is_file("$root/$name/$file")) {
                    $files[$include] = "{$this->roots[0]}/$name/$file";
                    break;
                }
            }
        }

        return $files;
    }

    /**
     * Copy a preset of the kit into the folder of the machine: every file it has that the machine does not (what is there is
     * the administrator's, and a preset of their own has nothing to copy).
     *
     * @return list<string> the files written
     */
    public function install(string $name): array
    {
        $written = [];
        if (!self::valid($name)) {
            return $written;
        }
        foreach (array_slice($this->roots, 1) as $root) {
            if (!is_dir("$root/$name")) {
                continue;
            }
            $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator("$root/$name", \FilesystemIterator::SKIP_DOTS));
            foreach ($files as $file) {
                $relative = substr($file->getPathname(), strlen("$root/$name") + 1);
                $dest = "{$this->roots[0]}/$name/$relative";
                if (!$file->isFile() || is_file($dest)) {
                    continue;
                }
                is_dir(dirname($dest)) || mkdir(dirname($dest), 0o755, true);
                $copied = preg_match('/\.(ini|json|txt|conf|xml|config)$/i', $relative) === 1
                    ? TextFile::copy($file->getPathname(), $dest)
                    : copy($file->getPathname(), $dest);
                if ($copied) {
                    $written[] = $dest;
                }
            }
        }
        sort($written);

        return $written;
    }

    private static function valid(string $name): bool
    {
        return preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]*$/', $name) === 1;
    }

    /** Host and port of an address (http://host:port), the port of the scheme when it has none. */
    private static function address(string $url): ?array
    {
        $parts = parse_url(preg_match('#^[a-z]+://#i', $url) ? $url : "http://$url") ?: [];
        if (empty($parts['host'])) {
            return null;
        }

        return [
            'host' => (string) $parts['host'],
            'port' => (int) ($parts['port'] ?? (($parts['scheme'] ?? 'http') === 'https' ? 443 : 80)),
        ];
    }
}
