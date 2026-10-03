<?php

declare(strict_types=1);

namespace OpenSim\Installer\Grid;

/**
 * Derives a grid's nick and slug from its display name. Replaces the external
 * webnormalize helper with a self-contained PHP implementation.
 */
final class Slug
{
    /**
     * Nick: snake_case, lowercase letters, digits and underscores, the words kept apart so that
     * "The Rapist" (the_rapist) is not "Therapist" (therapist) (e.g. "Olivier's grid" -> "oliviers_grid").
     */
    public static function nick(string $name): string
    {
        return self::slug($name);
    }

    /**
     * Slug: the same, snake_case. An apostrophe is dropped, not a separator (so "Olivier's"
     * is "oliviers"), any other run of characters is one underscore.
     */
    public static function slug(string $name): string
    {
        // A name written in camel case keeps its words apart too (OliviersGrid)
        $ascii = strtolower((string) preg_replace('/([a-z0-9])([A-Z])/', '$1_$2', self::ascii($name)));
        $ascii = str_replace(["'", "\u{2019}"], '', $ascii);
        $slug = preg_replace('/[^a-z0-9]+/', '_', $ascii) ?? '';

        return trim($slug, '_');
    }

    /** Transliterate to ASCII (intl when available, iconv as a fallback): "Joyeux Noël" -> "Joyeux Noel". */
    public static function ascii(string $value): string
    {
        if (class_exists(\Transliterator::class)) {
            $t = \Transliterator::create('Any-Latin; Latin-ASCII');
            if ($t !== null) {
                $out = $t->transliterate($value);
                if ($out !== false) {
                    return $out;
                }
            }
        }

        $out = @iconv('UTF-8', 'ASCII//TRANSLIT', $value);

        return $out !== false ? $out : $value;
    }
}
