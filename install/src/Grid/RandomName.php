<?php

declare(strict_types=1);

namespace OpenSim\Installer\Grid;

use Nubs\RandomNameGenerator\Alliteration;

/**
 * A name made of an adjective and a noun that begin the same ("Gentle Gnat"), to propose when
 * there is nothing better to propose.
 */
final class RandomName
{
    /**
     * A name the caller accepts (not taken, for instance), transliterated to ASCII: the words come
     * from lists that have an accent. Empty when none was found, the caller proposes nothing then.
     *
     * @param \Closure(string):?string $problem returns why a name is refused, null when it is fine
     */
    public static function make(\Closure $problem): string
    {
        $generator = new Alliteration();
        for ($i = 0; $i < 50; $i++) {
            $name = Slug::ascii($generator->getName());
            if ($problem($name) === null) {
                return $name;
            }
        }

        return '';
    }
}
