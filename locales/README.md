# Translations

The setup is written in English and uses gettext (domain `opensim-kit`). Translations are `locales/<lang>.po` files, compiled to `.mo` by `packaging/build`.

- `locales/update-pot` regenerates `opensim-kit.pot` from the PHP sources and merges it into the existing `.po` files. `locales/update-pot fr` starts a new language.
- Wrap new user-facing strings in `_()`, and use `sprintf(_('... %s'), $value)` for dynamic parts.
