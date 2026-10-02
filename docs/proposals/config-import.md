# Configuration import from `Robust.ini` and `OpenSim.ini`

## Need

Use the tools of the kit (setup menus, `opensim` commands, bulk avatars) on a grid that was not made by the kit, **without changing its files**, and later move it to the layout of the kit if wanted (full import, a next step of the roadmap).

## Known

- The kit reads what it needs from the files of a grid it made (`GridInfo::parse`, the `<nick>.conf` of the grid, the files of `sims/`); a grid it only knows by address is described in `<nick>.conf` with `Remote = true` (`GridInfo::loadRemote`).
- The inputs of an existing install are `Robust.ini` or `Robust.HG.ini` (with its includes, `[Const]` variables, `${Const|...}` expansions), `OpenSim.ini`, `GridCommon.ini`, `Regions/*.ini`.

## Proposed

Two modes, one reader:

1. **Reference** (no change to the install): `opensim import <path-to-Robust.ini> [--sim <path-to-OpenSim.ini>...]` writes a description of the grid in the layout of the kit (`/etc/opensim/grids/<nick>/<nick>.conf` with `Imported = true` and the path of the original files) and nothing else. The setup menus and the commands then see the grid: its simulators and regions (read from the files), its database (for accounts and bulk creation), its ports (so the free-port search avoids them).
2. **Full import** (later, see roadmap): copies the files, normalizes them to the architecture of the kit, moves data. Reuses the reader.

The reader: expands `[Const]` and `${Const|...}` the way OpenSim does, follows `Include-*` and `ConfigDirectory`, understands the keys the kit needs (database, ports, console, `GridInfoService`, regions) and ignores the others; read-only, with the rights of the user who runs it (a file it cannot read is said, not guessed).

## To decide

- Where an imported grid lives for the launcher (`opensim start` of an instance that is not ours: through its own ini and its own core, `opensim.conf` as a reference only, see `installation-agnostic.md`).
- What the menus may do on an imported grid (probably only reading and the actions that do not write its files).
