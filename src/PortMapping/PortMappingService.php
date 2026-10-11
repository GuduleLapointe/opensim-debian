<?php


namespace OpenSimKit\PortMapping;

use OpenSim\Installer\Grid\GridInfo;
use OpenSim\Installer\InstancePorts;

/**
 * The port mapping of the instances (Robust, simulators): their ports made reachable from outside before they start, closed
 * after they stop.
 *
 * What the grid asks is in the `[Grid]` of its config (`<grid>/<grid>.conf`):
 *
 *     PortMappingModule = upnpc
 *     PortMappingEnabled = true
 *
 * The ports are the ones of `opensim ports <instance>`, all of them, nothing chosen here. What an instance has mapped is kept
 * in `<state dir>/<instance>` (the module on the first line, then `port proto` per line): what is mapped is known at once, and
 * the module is called only to map or unmap. A simulator of a grid of this machine is mapped only when its grid is.
 */
final class PortMappingService
{
    /** The modules by the name `PortMappingModule` gives them. */
    private const MODULES = ['upnpc' => UpnpcModule::class];

    /** @var \Closure(string):list<array{port:int,proto:string}> */
    private \Closure $ports;

    /** @var array<string,\Closure():ModuleInterface> */
    private array $modules;

    /**
     * @param \Closure(string,bool):void $log tells what is done, the second argument is true for a problem
     * @param ?\Closure(string):list<array{port:int,proto:string}> $ports the ports of an instance, by its config file
     * @param array<string,\Closure():ModuleInterface> $modules more modules, or others instead of these
     */
    public function __construct(
        private readonly string $stateDir,
        private readonly \Closure $log,
        ?\Closure $ports = null,
        array $modules = [],
    ) {
        $this->ports = $ports ?? static fn(string $ini): array => InstancePorts::of($ini);
        $this->modules =
            $modules +
            array_map(
                static fn(string $class): \Closure => static fn(): ModuleInterface => new $class(),
                self::MODULES,
            );
    }

    /**
     * Before an instance starts: its ports mapped when its grid asks for it, and the address its regions announce set. The
     * instance starts in any case, without the mapping when it could not be made.
     *
     * @param string $instance the name of the instance
     * @param string $ini its config file
     */
    public function start(string $instance, string $ini): void
    {
        $ini = realpath($ini) ?: $ini;
        $grid = $this->grid($ini);
        $module = $grid['enabled'] ? $grid['module'] : '';

        if ($module === '') {
            // Not asked: what is still mapped is closed (the stop has done it when it ran), and the regions announce what
            // they would without port mapping, whatever they announced before
            is_file($this->state($instance)) && $this->release($instance, $ini, $grid['module']);
            $this->announce($grid, 'SYSTEMIP');

            return;
        }

        $host = '';
        $problem = '';
        if (!$this->ready($instance, $grid) || !$this->map($instance, $ini, $module)) {
            $problem = 'port mapping failed';
        } elseif ($grid['simulator']) {
            $host = $this->module($module)?->address() ?? '';
            $problem = $host === '' ? 'public address unknown' : '';
        }
        if ($problem !== '') {
            $this->say($instance, "$problem, starting without port mapping", true);
            // Only what was mapped is closed: nothing was, when the grid was not ready
            is_file($this->state($instance)) && $this->release($instance, $ini, $module);
        }
        $this->announce($grid, $host !== '' ? $host : 'SYSTEMIP');
    }

    /**
     * After an instance stopped: what it has mapped is closed, whatever the grid asks now, with the ports it has now and the
     * ones it had.
     */
    public function stop(string $instance, string $ini): void
    {
        $ini = realpath($ini) ?: $ini;
        $this->release($instance, $ini, $this->grid($ini)['module']);
    }

    /**
     * What the config of the grid of an instance says, and where the instance is.
     *
     * @return array{module:string,enabled:bool,remote:bool,nick:string,simulator:bool,regions:string}
     */
    private function grid(string $ini): array
    {
        $dir = dirname($ini);
        $simulator = basename($dir) === 'sims';
        $dir = $simulator ? dirname($dir) : $dir;
        $nick = basename($dir);
        $settings = array_change_key_case(\OpenSim_Config::read("$dir/$nick.conf")['Grid'] ?? [], CASE_LOWER);
        $flag = static fn(string $key): bool => in_array(
            strtolower(trim((string) ($settings[$key] ?? ''))),
            ['true', 'yes', '1', 'on'],
            true,
        );
        $regions = '';
        if ($simulator) {
            $startup = array_change_key_case(\OpenSim_Config::read($ini)['Startup'] ?? [], CASE_LOWER);
            $regions = rtrim((string) ($startup['regionload_regionsdir'] ?? ''), '/');
        }

        return [
            'module' => strtolower(trim((string) ($settings['portmappingmodule'] ?? ''))),
            'enabled' => $flag('portmappingenabled'),
            'remote' => $flag('remote'),
            'nick' => $nick,
            'simulator' => $simulator,
            'regions' => $regions,
        ];
    }

    /** A simulator is mapped when the Robust of its grid is, unless the grid is on another machine. */
    private function ready(string $instance, array $grid): bool
    {
        return !$grid['simulator'] || $grid['remote'] || is_file($this->state(GridInfo::instanceName($grid['nick'])));
    }

    /** Map the ports of an instance, unless they are mapped already. False when one cannot be, nothing is left mapped then. */
    private function map(string $instance, string $ini, string $module): bool
    {
        $mapper = $this->module($module);
        if ($mapper === null) {
            $this->say($instance, "unknown port mapping module '$module'", true);

            return false;
        }
        $ports = ($this->ports)($ini);
        if ($ports === []) {
            return false;
        }
        $wanted = array_map(static fn(array $p): string => "{$p['port']} {$p['proto']}", $ports);
        $state = $this->state($instance);
        if (is_file($state)) {
            $kept = file($state, FILE_IGNORE_NEW_LINES) ?: [];
            if (array_shift($kept) === $module && $kept === $wanted) {
                return true;
            }
            $this->release($instance, $ini, $module);
        }

        $this->say($instance, 'mapping ports ' . self::format($ports) . " ($module), this can take a moment");
        is_dir($this->stateDir) || mkdir($this->stateDir, 0o755, true);
        if (!$mapper->prepare() || file_put_contents($state, "$module\n") === false) {
            return false;
        }
        foreach ($ports as $p) {
            if (!$mapper->open($p['port'], $p['proto'])) {
                $this->release($instance, $ini, $module);

                return false;
            }
            file_put_contents($state, "{$p['port']} {$p['proto']}\n", FILE_APPEND);
        }

        return true;
    }

    /**
     * Close what an instance has mapped: the ports it has now and the ones the state keeps, with the module of the state, else
     * the given one. Nothing is done when no module is known.
     */
    private function release(string $instance, string $ini, string $module): void
    {
        $state = $this->state($instance);
        $kept = is_file($state) ? (file($state, FILE_IGNORE_NEW_LINES) ?: []) : [];
        $module = $kept !== [] ? array_shift($kept) : $module;
        $mapper = $module !== '' ? $this->module($module) : null;
        if ($mapper !== null) {
            $list = [];
            foreach ($kept as $line) {
                [$port, $proto] = array_pad(explode(' ', trim($line), 2), 2, '');
                if (ctype_digit($port) && $proto !== '') {
                    $list["$port $proto"] = ['port' => (int) $port, 'proto' => $proto];
                }
            }
            foreach (($this->ports)($ini) as $p) {
                $list["{$p['port']} {$p['proto']}"] = $p;
            }
            usort($list, static fn(array $a, array $b): int => [$a['port'], $a['proto']] <=> [$b['port'], $b['proto']]);
            if ($list !== []) {
                $this->say($instance, 'closing ports ' . self::format($list) . " ($module)");
                $mapper->prepare();
                foreach ($list as $p) {
                    $mapper->close($p['port'], $p['proto']);
                }
            }
        }
        is_file($state) && unlink($state);
    }

    /** The regions of a simulator announce this address (SYSTEMIP when they have no public one). */
    private function announce(array $grid, string $host): void
    {
        if (!$grid['simulator'] || $grid['regions'] === '') {
            return;
        }
        foreach (glob($grid['regions'] . '/*.ini') ?: [] as $file) {
            $text = (string) file_get_contents($file);
            $new = preg_replace('/^([ \t]*ExternalHostName[ \t]*=)[^\r\n]*/mi', '${1} ' . $host, $text);
            if ($new !== null && $new !== $text) {
                file_put_contents($file, $new);
            }
        }
    }

    private function module(string $name): ?ModuleInterface
    {
        return isset($this->modules[$name]) ? $this->modules[$name]() : null;
    }

    private function state(string $instance): string
    {
        return rtrim($this->stateDir, '/') . '/' . $instance;
    }

    private function say(string $instance, string $message, bool $problem = false): void
    {
        ($this->log)("$instance: $message", $problem);
    }

    /** @param list<array{port:int,proto:string}> $ports */
    private static function format(array $ports): string
    {
        return implode(', ', array_map(static fn(array $p): string => "{$p['port']}/{$p['proto']}", $ports));
    }
}
