# Users created get the default region as home

## Known

- Robust sets the home of a new account itself: `UserAccountService.CreateUser` takes the first of `GridService.GetDefaultRegions()` and calls `GridUserService.SetHome`; without a default region it logs "Unable to set home for account" and leaves it empty.
- A default region is a region that registered with the `DefaultRegion` flag (see the landing region of `opensim setup`). Robust reads the `Region_<name>` keys of its config once, when it starts.
- So an account made **after** the default region is registered has its home right. One made **before** has none, and the user gets an error on login until they set it from the viewer.
- The setup makes the owner of the estate **before** the first region exists (the region needs its estate, the estate needs its owner): that account is the one without home.

## Proposed

1. After the first region registered (with Robust restarted for its flags), set the home of the estate owner in the grid database: a row in the `GridUser` table (`UserID`, `HomeRegionID`, `HomePosition`, `HomeLookAt`), by the account of the grid, as the setup already reads `UserAccounts` and `regions`. The UserID of the table is the principal id of the account.
2. Any account made later, through `create user` in the console of Robust, is right as is: nothing to do.
3. Bulk creation (see `bulk-avatars.md`) goes through the same console, after the default region exists.

## To check on a real grid

- The exact columns and defaults of `GridUser` for the OpenSim version (0.9.3: `HomePosition` as `<128,128,0>`).
- That a login with that row gives the right home (a test with a viewer, by hand).
