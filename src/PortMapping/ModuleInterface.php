<?php

namespace OpenSimKit\PortMapping;

/**
 * A way to make the ports of an instance reachable from outside (a NAT router), named by `PortMappingModule` in the
 * `[Grid]` of the config of the grid.
 */
interface ModuleInterface
{
    /** Get what the module needs before it opens ports. False when it cannot work. */
    public function prepare(): bool;

    public function open(int $port, string $proto): bool;

    public function close(int $port, string $proto): bool;

    /** The public address of this machine, empty when it is not known. */
    public function address(): string;
}
