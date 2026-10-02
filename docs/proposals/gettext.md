# Localization of the setup and the launcher

gettext is a requirement of the project (`ext-gettext`): the support has to be there, the translations are not a priority.

## Known

- The messages of the setup are English strings in the PHP code (`InstallerUi` calls: `note`, `warn`, `confirm`, labels) and of the launcher in the bash scripts (`log`, `end`, `echo`).
- opensim-helpers and opensim-engine have their locales (`locales/` in helpers); the engine requires gettext.

## Proposed

1. **PHP**: a function `__()` of the kit's namespace (`OpenSim\Installer\__`, a thin `gettext()` with the text domain `opensim-kit`, bound once in `install.php`), used for every string shown to the user; placeholders with `sprintf` (`__('Region %s is online.')`), plural with `ngettext`. No translation of what is not shown (config keys, commands, log markers the scripts grep for).
2. **bash**: `gettext -e "..."` (package `gettext-base`, to add to the dependencies of `opensim-tools`) through a `__` function of `os-helpers`, the same domain; messages the tests or other scripts parse stay English.
3. **Domain files**: `locales/opensim-kit.pot` extracted with `xgettext` (PHP and shell), `locales/<lang>/LC_MESSAGES/opensim-kit.po`, compiled by the packaging into `/usr/share/locale`; the `opensim` command binds the domain to the package folder.
4. **Conversion by steps**: the setup (`install/src`) first, then the launcher; one commit per file or group, the tests keep comparing the English text through the default locale (`LANG=C`).
5. **Not now**: translations. The `.pot` and the tools to update it (`composer run-script pot`) are enough for contributors.

## To decide

- Whether translators work in the repository (`.po` files) or on a service (Weblate).
- The source language of the strings: English as today.
