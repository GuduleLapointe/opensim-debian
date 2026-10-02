<?php

declare(strict_types=1);

namespace OpenSim\Installer;

/**
 * The language of the setup: gettext, the domain `opensim-kit`, the catalogs in `locales/<lang>/LC_MESSAGES/` of the
 * kit (compiled from the `.po` files when the package is built). The strings are English, in the code: `_('Text')`, and
 * `sprintf(_('Region %s is online.'), $name)` for a text with values, so a translator sees the whole sentence.
 * The language is the one of the environment (LANGUAGE, LC_ALL, LC_MESSAGES, LANG); without a catalog for it the
 * English text is shown.
 */
final class I18n
{
    public const DOMAIN = 'opensim-kit';

    /** Bind the domain, once per process, from the entry point. */
    public static function init(?string $dir = null): void
    {
        if (!function_exists('bindtextdomain')) {
            return;
        }
        setlocale(LC_MESSAGES, '');
        bindtextdomain(self::DOMAIN, $dir ?? dirname(__DIR__, 2) . '/locales');
        bind_textdomain_codeset(self::DOMAIN, 'UTF-8');
        textdomain(self::DOMAIN);
    }
}
