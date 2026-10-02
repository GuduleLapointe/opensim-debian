# Bulk avatar creation

## Need

Create many accounts from a list given by a third party, on a grid made by the kit or not (see `config-import.md`: the existing configuration is used as is).

## Known

- An account is made by the `create user` command of the console of Robust (`opensim command <grid> ...`, or the REST console), which also creates the inventory, the default avatar and, with a default region, the home (see `home-region.md`). The setup does it for the owner of an estate (`GridAccounts`), from the console.
- The REST console is the natural way for a grid that is not ours: `opensim rest --url http://HOST:PORT --user USER`.
- A name (first, last) must be unique, passwords are typed in the console as they are given (no escape), the email is optional.

## Proposed

- `opensim users import <file> [--grid NICK | --url URL]`, one account per line or entry.
- **Format**: CSV (`first,last,email,password`, header line, `;` accepted) and JSON (a list of objects with the same keys); the extension tells. No password given: one is generated and written in a result file (mode 600), never on the screen.
- **Behaviour**: a dry run first by default (what would be created, the names already taken, the errors), `--apply` to do it; names compared without the case; an existing account is skipped, not changed; the result is a table (created, skipped, failed, why) and a file to give to whoever sent the list.
- **Safety**: slow enough for the console (one command at a time, each answer read), stops at the first error unless `--keep-going`, resumable (the result file tells where it stopped).
- **Where**: a class in the kit (`Grid\GridAccounts` has the code of the console commands) and the command in `libexec/users.php`, with the PHP library of the REST console doing the talking.

## To decide

- The third-party formats really in use (to see an example of the list).
- Whether the password is imposed by the list (hashes?) or always chosen here.
- Which fields to keep when the list has more (title, home region, level).
