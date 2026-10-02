# Localization of the setup and the launcher

gettext is a requirement of the project (`ext-gettext`): the support has to be there, the translations are not a priority.

## Known

- The messages of the setup are English strings in the PHP code (`InstallerUi` calls: `note`, `warn`, `confirm`, labels) and of the launcher in the bash scripts (`log`, `end`, `echo`).
- opensim-helpers and opensim-engine have their locales (`locales/` in helpers); the engine requires gettext.

## Decided

- **Files in the project**: `.po` files (gettext) or `.json` files (the Laravel way, the key is the English text), in the repository itself. No external translation service, at least for now.
- **The source language is English.**

## Proposed

1. **PHP**: a function `__()` of the kit's namespace (`OpenSim\Installer\__`, a thin layer over `gettext()` with the text domain `opensim-kit`, bound once in `install.php`), used for every string shown to the user; placeholders with `sprintf` (`__('Region %s is online.')`), plural with `ngettext`. What is not shown (config keys, commands, markers the scripts grep for) is not translated.
2. **bash**: `gettext` (package `gettext-base`, to add to the dependencies of `opensim-tools`) through a `__` function of `os-helpers`, same domain; messages that the tests or other scripts parse stay English.
3. **Domain files**: `locales/opensim-kit.pot` extracted with `xgettext` (PHP and shell), `locales/<lang>/LC_MESSAGES/opensim-kit.po`, compiled by the packaging into `/usr/share/locale`; `.json` if the Laravel way is chosen for the PHP side (a loader of the kit reads it, with the same domain naming); the choice is made once for the whole project.
4. **Conversion by steps**: the setup (`install/src`) first, then the launcher, one commit per file or group; the tests keep comparing the English text through the default locale (`LANG=C`).
5. **Not now**: translations. The `.pot` and the command to update it are enough for contributors.

## To decide

- `.po` (gettext, the same as helpers and engine) or `.json` (Laravel): the same method for all the libraries is simpler.
