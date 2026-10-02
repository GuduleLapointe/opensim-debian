# Bulk avatar creation

## Need

Create many accounts from a list given by a third party, on a grid made by the kit or not (see `config-import.md`: the existing configuration is used as is).

## Decided

- **Not through the console of Robust**: too slow and too fragile for hundreds of accounts, and no live answer is needed for each one (unlike the regions, where it is). The accounts are written **directly in the database of the grid**, as w4os does (at least in its older releases, to check in its code: <https://github.com/GuduleLapointe/w4os>).
- Two formats to start from, the ideal ones for us: **CSV** and **JSON**. A concrete proposal is then submitted to the users for their feedback.

## Known: what `create user` does

From `UserAccountService.CreateUser` and `XInventoryService.CreateUserInventory` (OpenSim master), to reproduce in SQL:

1. Refuse a name that exists (first + last, compared without the case).
2. `UserAccounts`: `PrincipalID` (a new UUID), `ScopeID` (zero UUID), `FirstName`, `LastName`, `Email`, `ServiceURLs` (`HomeURI`, `InventoryServerURI`, `AssetServerURI`, empty values), `Created` (unix time), `UserLevel` 0, `UserFlags` 0, `UserTitle` '', `active` 1.
3. `auth`: `UUID` = the principal id, `passwordSalt` (32 characters), `passwordHash` = `MD5(MD5(password) + ':' + salt)` (the scenario tests exactly that), `webLoginKey` '', `accountType` 'UserAccount'.
4. The home, if a default region exists (see `home-region.md`): a `GridUser` row.
5. The inventory (`inventoryfolders`): a root folder "My Inventory" (type 8), then the system folders under it: Animations, Body Parts, Calling Cards (with "Friends" and "All" under it), Clothing, Current Outfit, Favorites, Gestures, Landmarks, Lost And Found, Notecards, Objects, Photo Album, Scripts, Sounds, Textures, Trash, Settings, Materials, each with its folder type number (`FolderType` of the core), `version` 1.
6. The default appearance (when `CreateDefaultAvatarEntries` is on in the grid): in `inventoryitems`, "Default Eyes", "Default Shape", "Default Skin", "Default Hair" (the default assets of `AvatarWearable`), in "Body Parts", with a link in "Current Outfit" for each; the avatar itself is in the table `Avatars`, written by the avatar service.

The table and column names are the ones of the migrations (`OpenSim/Data/MySQL/Resources`), **case-sensitive for the scripts** (`UserAccounts`, `auth`, `GridUser`, `inventoryfolders`, `inventoryitems`, `Avatars`): a full setup in the scenario container gives the exact list, the reference, as the server of an operator may ignore the case and give a false answer.

## Proposed

- `opensim users import <file> [--grid NICK]`, one account per line or entry; CSV (`first,last,email,password`, header line) and JSON (a list of objects with the same keys), the extension tells.
- No password given: one is generated, written in a result file (mode 600), never on the screen.
- A **dry run** by default (what would be created, the names taken, the errors), `--apply` to write; one transaction per account, so a failure leaves nothing half made; the result is a table (created, skipped, failed, why) and the file for whoever sent the list.
- Reuses the database class of the setup (`Database`, the credentials of the grid), the code that knows the schema in one place (`Grid\GridAccounts`).
- **Inventory items** (w4os gives items to the avatars it creates by writing `inventoryitems`): the same code gives an item to an account, which also serves the script of `parcel-name.md`.
- **Check on a grid before relying on it**: the rows written must be read back by Robust as the ones it writes itself (a login, the appearance, the inventory shown), and the folder type numbers and default asset ids taken from the core of the grid.

## To decide

- The third-party formats really in use (an example of the list, with the feedback).
- Whether the list can give a password hash (an import from another grid) or always a password.
- The fields to keep when the list has more (title, home region, user level, items).
