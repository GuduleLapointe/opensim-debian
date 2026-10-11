<?php


namespace OpenSim\Installer\Grid;

use OpenSim\Installer\Ini;
use OpenSim\Installer\TextFile;

/**
 * The presets of the grids a simulator can join: for each grid, a folder with the structure of the folder of a grid
 * (<etc>/grids/<grid>), holding only the files that have settings of that grid. Any file may be there, none is required.
 *
 * grids/ is for the grids configured on this machine, presets/ for the base of the grids that are elsewhere. The presets of
 * the kit are in /usr/share (share/presets). Joining a grid by a preset makes the folder <etc>/presets/<name> a complete one,
 * as the bin folder of OpenSimulator the administrator knows, and usable without the kit: each ini of the distribution
 * merged with the one of the preset (and what the administrator already set there), the same layers as every file the setup
 * makes (see Ini::layered()), the same every time, so that a new setting of the distribution is always taken. The settings
 * the preset gives to the simulators themselves come from its OpenSim.ini (settings()), and the services of the grid from its
 * config-include, which the simulators include as they do for a grid of this machine.
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

    /** The folder of a preset on the machine (<etc>/presets/<name>), where it is made complete and where the simulators read it. */
    public function dir(string $name): string
    {
        return "{$this->roots[0]}/$name";
    }

    /**
     * Make the folder of the preset on the machine whole, by the procedure of the folder of any grid (GridShared::merge()):
     * every file of the distribution merged with the same file of the preset of the kit and with what the machine already has,
     * each file apart (an include stays an include), our corrections of the paths. The same every time, so a new setting of the
     * distribution is always taken.
     *
     * @param  string $binDir the bin folder of the core, the distribution
     * @return list<string> the files that changed
     */
    public function install(string $name, string $binDir, bool $hypergrid = true): array
    {
        if (!self::valid($name)) {
            return [];
        }
        $dir = $this->dir($name);
        $before = self::snapshot($dir);
        $over = array_values(array_filter(array_map(static fn(string $root): string => "$root/$name", array_slice($this->roots, 1)), 'is_dir'));

        // What is not an ini to merge: the library the simulators read, the other files of the preset of the kit
        is_dir("$dir/inventory") || !is_dir("$binDir/inventory") || self::copyTree("$binDir/inventory", "$dir/inventory", false);
        foreach ($over as $root) {
            self::copyTree($root, $dir, true);
        }
        $extra = array_unique(array_merge(self::filesOf($dir), ...array_map(self::filesOf(...), $over)));
        (new GridShared())->merge($dir, $binDir, $hypergrid, $over, $extra);
        (new GridShared())->prepare($dir, $binDir, $hypergrid);

        $after = self::snapshot($dir);

        return array_keys(array_filter($after, static fn(string $hash, string $path): bool => ($before[$path] ?? null) !== $hash, ARRAY_FILTER_USE_BOTH));
    }

    /**
     * What the preset gives to the simulators themselves: the settings of its OpenSim.ini that its distribution does not have
     * or has with another value, as a text laid over the config of each simulator (the OpenSim.ini of the folder is a whole
     * one, with the settings of a simulator on its own, it is not included).
     */
    public function settings(string $name, string $binDir): string
    {
        $path = $this->dir($name) . '/OpenSim.ini';
        if (!is_file($path)) {
            return '';
        }
        $default = is_file("$binDir/OpenSim.ini") ? "$binDir/OpenSim.ini" : (is_file("$binDir/OpenSim.ini.example") ? "$binDir/OpenSim.ini.example" : null);

        return Ini::load($path)->differences(Ini::layered($default, null));
    }

    /** @return list<string> the ini files of a folder, relative paths */
    private static function filesOf(string $dir): array
    {
        if (!is_dir($dir)) {
            return [];
        }
        $files = [];
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)) as $file) {
            if ($file->isFile() && preg_match('/\.ini$/i', $file->getFilename()) === 1) {
                $files[] = substr($file->getPathname(), strlen($dir) + 1);
            }
        }

        return $files;
    }

    /** @return array<string,string> path => hash of every file of a folder */
    private static function snapshot(string $dir): array
    {
        $files = [];
        if (is_dir($dir)) {
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)) as $file) {
                if ($file->isFile()) {
                    $files[$file->getPathname()] = (string) md5_file($file->getPathname());
                }
            }
        }
        ksort($files);

        return $files;
    }

    /**
     * Copy the files of a folder that another does not have (an ini is merged apart, not copied).
     *
     * @param bool $skipIni leave the ini files, which the merge makes
     */
    private static function copyTree(string $from, string $to, bool $skipIni): void
    {
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($from, \FilesystemIterator::SKIP_DOTS)) as $file) {
            $relative = substr($file->getPathname(), strlen($from) + 1);
            if (!$file->isFile() || is_file("$to/$relative") || ($skipIni && preg_match('/\.ini$/i', $relative) === 1)) {
                continue;
            }
            is_dir(dirname("$to/$relative")) || mkdir(dirname("$to/$relative"), 0o755, true);
            copy($file->getPathname(), "$to/$relative");
        }
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
