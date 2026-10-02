<?php

declare(strict_types=1);

namespace OpenSim\Installer\Grid;

/**
 * The name of a region: what is accepted, and how Robust finds its flags in
 * its config.
 */
final class RegionName
{
    /** The reason a name is refused, null when it is fine. */
    public static function problem(string $name): ?string
    {
        return preg_match('/^[A-Za-z0-9][A-Za-z0-9 ._-]{0,49}$/', trim($name)) === 1
            ? null
            : 'Letters, digits, spaces, . _ and - only.';
    }

    /** The key of [GridService] giving the flags of a region: Robust looks for its name with the spaces replaced by underscores. */
    public static function configKey(string $name): string
    {
        return 'Region_' . str_replace(' ', '_', trim($name));
    }

    /** The name a key of [GridService] stands for (an underscore of the key may be a space of the name). */
    public static function fromConfigKey(string $key): string
    {
        return str_replace('_', ' ', (string) preg_replace('/^Region_/', '', $key));
    }

    /**
     * Whether a region of this name is among those known, the way Robust
     * compares them (a space and an underscore are the same character).
     *
     * @param array<string,mixed> $known the names as keys
     */
    public static function isIn(string $name, array $known): bool
    {
        $same = static fn(string $value): string => strtolower(str_replace(' ', '_', trim($value)));
        $wanted = $same($name);
        foreach (array_keys($known) as $other) {
            if ($same((string) $other) === $wanted) {
                return true;
            }
        }

        return false;
    }
}
