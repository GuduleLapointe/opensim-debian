# Users created get the default region as home

## Known

- Robust sets the home of a new account itself: `UserAccountService.CreateUser` takes the first of `GridService.GetDefaultRegions()` and calls `GridUserService.SetHome` (position `<128,128,0>`, look-at `<0,1,0>`); without a default region it logs "Unable to set home for account" and leaves it empty.
- A default region is a region that registered with the `DefaultRegion` flag (see the landing region of `opensim setup`). Robust reads the `Region_<name>` keys of its config once, when it starts.
- So an account made **after** the default region is registered has its home right. One made **before** has none, and the user gets an error on login until they set it from the viewer.
- The setup makes the owner of the estate **before** the first region exists (the region needs its estate, the estate needs its owner): that account is the one without home, the others are fine.

## Proposed

1. After the first region registered (with Robust restarted for its flags), set the home of the estate owner in the grid database, in the table `GridUser`, **with the names as OpenSim creates them** (the schema is the one of the migrations, see below; the table and column names are case-sensitive for the scripts, whatever the server of the operator does): `UserID` = the principal id of the account, `HomeRegionID` = the id of the default region, `HomePosition` = `<128,128,0>`, `HomeLookAt` = `<0,1,0>`; an `INSERT ... ON DUPLICATE KEY UPDATE` on `UserID` (the primary key).
2. Any account made later, through `create user` in the console of Robust, is right as is.
3. A test that an account created **after** the default region has its home set (a `GridUser` row with the id of that region): in the packaging scenario, after the first region is online and before and after a second account.

## Schema (OpenSim/Data/MySQL/Resources/GridUserStore.migrations)

`GridUser`: `UserID` varchar(255) primary key, `HomeRegionID` char(36), `HomePosition` char(64), `HomeLookAt` char(64), `LastRegionID`, `LastPosition`, `LastLookAt`, `Online` char(5) default 'false', `Login` char(16), `Logout` char(16). The other tables of the grid are named the same way (`UserAccounts`, `auth`, `inventoryfolders`, `inventoryitems`, `regions`...): a full setup in the scenario container gives the complete list as created, which is the reference, rather than a live server that may ignore the case.
