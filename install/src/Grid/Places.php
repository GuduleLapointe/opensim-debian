<?php

declare(strict_types=1);

namespace OpenSim\Installer\Grid;

/**
 * Where a region can go in a grid: the free place nearest to the one asked, by
 * the rule of the grid. What the setup of a simulator and `opensim next location`
 * both use.
 */
final class Places
{
    /**
     * The free place nearest to a place. A grid on another machine is asked what
     * its regions take around the place, in a wider area as long as the answer
     * could hide a nearer place.
     *
     * @param array<string,true> $known the places taken that are known here
     * @param ?string $ignored a place to leave out of what the grid answers (the one a region moves from)
     * @param bool $unreachable set when a grid on another machine does not answer
     * @return array{0:int,1:int}
     */
    public static function nearestFree(
        GridInfo $grid,
        array $known,
        int $x,
        int $y,
        ?string $ignored = null,
        bool &$unreachable = false,
    ): array {
        $gap = $grid->regionSpacing;
        if (!$grid->remote) {
            return LocationFinder::nearestFree($known, $x, $y, $gap);
        }

        for ($radius = 20; ; $radius *= 2) {
            $asked = RobustGrid::locations($grid->baseHostname, $grid->privatePort, $x, $y, $radius);
            if ($asked === null) {
                $unreachable = true;

                return LocationFinder::nearestFree($known, $x, $y, $gap);
            }
            if ($ignored !== null) {
                unset($asked[$ignored]);
            }
            [$freeX, $freeY] = LocationFinder::nearestFree($known + $asked, $x, $y, $gap);
            if (max(abs($freeX - $x), abs($freeY - $y)) + $gap <= $radius || $radius >= 320) {
                return [$freeX, $freeY];
            }
        }
    }
}
