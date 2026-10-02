# Naming the parcel of a new region

## Known

- OpenSim names the default parcel of a region "Your Parcel": it is the initial value of `LandData._name` (`OpenSim/Framework/LandData.cs`), with no setting and no console command to change it.
- A running region does not read its land again from its database, and writes its own copy back when a parcel changes.
- Today: the setup renames it in the `land` table of the simulator database while the simulator is stopped (`Actions::nameParcels`), which costs a restart, offered when the setup ends (`pending-restarts.txt`).

## Options

1. **Keep it** (restart offered at the end). Works, costs a restart per new region.
2. **Name the parcel from the region itself**: OpenSim has region modules and OSSL functions to rename a parcel (`osSetParcelDetails`, from a script run by an object in the region); the first region could receive a rez'd object that renames the parcel once. Needs an avatar or an NPC to rez it: too heavy.
3. **A patch upstream**: a `[LandManagement] DefaultParcelName = "${RegionName}"` setting, 3 lines in `LandManagementModule`. Takes time, depends on the project, but is the clean fix. To discuss with the OpenSimulator developers.
4. **A tiny region module of the kit** (`OpenSimKit.Modules.dll`, in the `opensim-<core>-<module>` packages) that names the parcel when the region creates it. A new .NET build to maintain for each release: only if more than this needs a module.

## Proposed

Keep 1 for now, open the discussion for 3 (the most useful for everybody), and look at 4 only if other things need a module of the kit (see `web.md`, search registration).
