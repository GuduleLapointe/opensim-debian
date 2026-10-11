<?php

namespace OpenSim\Installer\Grid;

/**
 * The name proposed for a simulator: the one of the region it is made for, as the first simulator of a grid has the one of its
 * landing region; a name of the grid's own when there is no region to be named after, else a random one.
 */
final class SimName
{
    /**
     * @param ?string $regionName the region the simulator is for, null when there is none
     * @param ?string $fallback   the name to give when there is no region, null for a random one
     * @param \Closure(string):?string $problem returns why a name is refused (not valid, taken), null when it is fine
     */
    public static function suggest(?string $regionName, ?string $fallback, \Closure $problem): string
    {
        if ($regionName !== null) {
            // An accent is not refused, it is transliterated (Noël becomes Noel)
            $name = Slug::ascii(trim($regionName));
            if ($name !== '' && $problem($name) === null) {
                return $name;
            }
        } elseif ($fallback !== null) {
            return $fallback;
        }

        return Slug::ascii(trim(RandomName::make($problem)));
    }
}
