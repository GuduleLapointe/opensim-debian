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
     * Nick: TitleCased, alphanumerics only (e.g. "Olivier's grid" -> "OliviersGrid").
     */
    public static function nick(string $name): string
    {
        $ascii = self::ascii($name);
        $title = ucwords(strtolower($ascii));

        return preg_replace('/[^A-Za-z0-9]/', '', $title) ?? '';
    }

    /**
     * Slug: lowercase, ASCII, non-alphanumerics collapsed to single hyphens
     * (e.g. "Olivier's grid" -> "olivier-s-grid").
     */
    public static function slug(string $name): string
    {
        $ascii = strtolower(self::ascii($name));
        $slug = preg_replace('/[^a-z0-9]+/', '-', $ascii) ?? '';

        return trim($slug, '-');
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
