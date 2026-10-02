# Configuration import from `Robust.ini` and `OpenSim.ini`

## Need

Use the tools of the kit (setup menus, `opensim` commands, bulk avatars) on a grid that was not made by the kit, **without changing its files**. Only the configuration is ported for now: the full import (data, assets) comes later.

## Decided

Detect in the original config the values that `opensim setup` asks (the name and nick of the grid, the hostname, the ports, the database, the console, the regions of the simulators...), build a **standard config** with them, valid for the current version of the core, and inject into it the **other values that differ** from the defaults of the original, so the custom settings of the user are kept.

## Known

- The kit already reads what it needs from the files of a grid it made (`GridInfo::parse`, `<nick>.conf`, `sims/`), and describes a grid it only knows by address (`Remote = true`, `GridInfo::loadRemote`).
- The setup generates a Robust config by overlaying its values on the example of the core (`RobustConfig::generate`, `Ini`), which keeps the comments and the defaults; a simulator config the same way (`SimConfig`).
- Inputs: `Robust.ini` or `Robust.HG.ini` (with its `Include-*`, `[Const]` and `${Const|...}` expansions), `OpenSim.ini`, `GridCommon.ini`, `Regions/*.ini`.

## Proposed

1. **Reader** (read-only, the rights of the user who runs it): expands `[Const]` and `${Const|...}` as OpenSim does, follows the includes, and returns the settings of the setup (a `GridPlan`/`SimPlan` filled from the files, one question less for each value found).
2. **Standard config**: the plan goes through the generators of the kit (`RobustConfig`, `SimConfig`, the shared architecture files), for the core chosen.
3. **Customizations**: the original is compared with the example of **its own** version of the core (the `.example` files of its `bin/` folder, when they are there); what differs and is not already set by the plan is injected into the new config (section and key, the original value), with a comment of where it comes from. Keys that the current core does not know are listed, not injected.
4. **Output**: a report (what was found, what was injected, what was left out and why), written next to the new config; nothing of the original is changed.
5. The result is a grid of the kit (`/etc/opensim/grids/<nick>/...`) that can be started in place of the original, or compared with it.

## Next

A first working version, to test on real grids (the author's, to see what works, what is missing). The full import (databases, assets, other data) is a later step.
