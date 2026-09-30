<?php

declare(strict_types=1);

namespace OpenSim\Installer\Grid;

/**
 * What the grid knows of its regions, from the database of its Robust: their
 * ports and their places, whatever machine they run on. It lets a new
 * simulator avoid the ports and the places of the ones that run elsewhere, so
 * that it can move to another machine without changing any of them.
 *
 * Read with the account of the grid; when the database cannot be read from here
 * (a simulator on another machine, with no access to it), only the local files
 * are known.
 */
final class GridRegistry
{
    public function __construct(private Database $database)
    {
    }

    /**
     * The ports the registered regions use: their own (UDP, the one viewers
     * connect to) and the one of their HTTP listener, which is in their URI.
     *
     * @return list<int>|null null when the registry cannot be read
     */
    public function ports(GridInfo $grid): ?array
    {
        $rows = $this->database->select($grid->databasePlan(), 'SELECT serverPort, serverURI FROM regions');
        if ($rows === null) {
            return null;
        }

        $ports = [];
        foreach ($rows as $row) {
            [$internal, $uri] = array_pad(explode("\t", $row), 2, '');
            if (ctype_digit(trim($internal))) {
                $ports[] = (int) $internal;
            }
            if (preg_match('#:(\d+)/?\s*$#', $uri, $m)) {
                $ports[] = (int) $m[1];
            }
        }

        return array_values(array_unique($ports));
    }

    /**
     * The places taken, "x,y" in regions, by the registered regions and by the
     * ones described in the local files of the grid.
     *
     * @return array<string,true>
     */
    public function locations(GridInfo $grid): array
    {
        $used = [];
        $rows = $this->database->select($grid->databasePlan(), "SELECT CONCAT(locX DIV 256, ',', locY DIV 256) FROM regions");
        foreach ($rows ?? [] as $row) {
            $used[trim($row)] = true;
        }
        foreach (glob("{$grid->dir}/sims/*/regions/*.ini") ?: [] as $file) {
            if (preg_match_all('/^\s*Location\s*=\s*(\d+)\s*,\s*(\d+)/m', (string) file_get_contents($file), $m, PREG_SET_ORDER)) {
                foreach ($m as $match) {
                    $used["{$match[1]},{$match[2]}"] = true;
                }
            }
        }

        return $used;
    }

    /** The first place, from 1000,1000, that no region holds. */
    public function nextLocation(GridInfo $grid): string
    {
        $used = $this->locations($grid);
        for ($x = 1000; ; $x++) {
            if (!isset($used["$x,1000"])) {
                return "$x,1000";
            }
        }
    }
}
