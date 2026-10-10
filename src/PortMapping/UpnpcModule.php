<?php

declare(strict_types=1);

namespace OpenSimKit\PortMapping;

/**
 * UPnP with the tools of miniupnpc: `upnpc` opens and closes the ports, `external-ip` gives the public address.
 */
final class UpnpcModule implements ModuleInterface
{
    private string $lan = '';

    /** @var \Closure(list<string>):array{0:int,1:string} */
    private \Closure $run;

    /** @param ?\Closure(list<string>):array{0:int,1:string} $run runs a command, gives its status and its output */
    public function __construct(?\Closure $run = null)
    {
        $this->run = $run ?? static function (array $command): array {
            $process = @proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
            if (!is_resource($process)) {
                return [127, ''];
            }
            $output = (string) stream_get_contents($pipes[1]);
            fclose($pipes[1]);
            fclose($pipes[2]);

            return [proc_close($process), $output];
        };
    }

    public function prepare(): bool
    {
        [$status, $output] = ($this->run)(['upnpc', '-s']);
        $this->lan = $status === 0 && preg_match('/^Local LAN ip address\s*:\s*(\S+)/m', $output, $m) ? $m[1] : '';

        return $this->lan !== '';
    }

    public function open(int $port, string $proto): bool
    {
        if ($this->lan === '') {
            return false;
        }

        return ($this->run)(['upnpc', '-a', $this->lan, (string) $port, (string) $port, $proto])[0] === 0;
    }

    public function close(int $port, string $proto): bool
    {
        return ($this->run)(['upnpc', '-d', (string) $port, $proto])[0] === 0;
    }

    public function address(): string
    {
        [$status, $output] = ($this->run)(['external-ip']);
        $address = trim($output);

        return $status === 0 && filter_var($address, FILTER_VALIDATE_IP) !== false ? $address : '';
    }
}
