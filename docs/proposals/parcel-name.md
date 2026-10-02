# Naming the parcel of a new region

A problem for another day: the current way works (a restart offered when the setup ends), and a real solution is a project of its own (a patch or a module). This keeps what is known and the ideas.

## Known

- OpenSim names the default parcel of a region "Your Parcel": the initial value of `LandData._name` (`OpenSim/Framework/LandData.cs`), with no setting and no console command.
- A running region does not read its land again from its database, and writes its own copy back when a parcel changes.
- Today: the setup renames it in the `land` table of the simulator database while the simulator is stopped (`Actions::nameParcels`), which costs a restart, offered when the setup ends (`pending-restarts.txt`).

## Ideas

1. **An OSSL script** that names the parcel (`osSetParcelDetails`, `PARCEL_DETAILS_NAME`), run once by an object in the region. Lighter than it looks, and it could carry other customizations of a new region (the music, the media, the flags of the parcel).
   - The grid has an avatar by design, the owner of the grid and of the estate, and one or two technical avatars made by default: the script can belong to one of them.
   - The object can be put in the region **by the database** (`inventoryitems`, `prims`/`primshapes`/`primitems` of the region store) or **with an OAR** loaded by the console (`load oar`), the way a start of a region loads its content.
   - If an avatar has to be online to run it: `pCampBot`, in the distribution, connects virtual avatars; it can connect the owner, with the script in its inventory. Its inventory is itself written in the database, as w4os does to give items to the avatars it creates (see `bulk-avatars.md`).
   - The console may also run scripts (to explore: `script` commands of the region console, the `[XEngine]` options).
2. **A patch upstream or a module of the kit** (like `opensimsearch` or `gloebit`), e.g. a setting `DefaultParcelName`: the clean fix, a project of its own; whether it is useful enough to maintain is for debate.

## Explored (from memory of the core, to verify against the packaged core)

- **The console cannot run LSL**: `command-script FILE` runs console commands, not scripts; the `scripts` and `xengine` commands of the region console only act on scripts already in objects (show, start, stop, suspend, resume), they neither compile nor install one. The other way round exists (`osConsoleCommand` from OSSL), which does not help here.
- **pCampBot** logs virtual avatars in through libopenmetaverse and plays *behaviours* (physics, grab, sit, teleport, chat, create, inventory...). None edits a parcel, and rezzing a prim with a script from the inventory is not one either: it needs a new behaviour, so a patch of the core tools, not a configuration. Not worth it for a name.
- **An OAR carrying only the parcel** looks the best lead: an OAR is a tar.gz with `archive.xml`, `landdata/*.xml` (one file per parcel: name, owner, bitmap, flags...), no object. `load oar --merge --force-parcels --skip-assets FILE` (to confirm: the exact options of the packaged core) would replace the parcel of a running region, without a restart. The kit already knows the parcel (the `land` table of the simulator), so it can write the OAR: same data, new name, a few lines of PHP (`PharData`). Open questions: what happens to the objects and terrain in merge mode (nothing expected), and whether the parcel owner has to exist (it does, it is the estate owner).
- **A script in an object** (idea 1) is the heaviest: it needs an object in the region, so an OAR with a prim and its script, or rows written in the region store; and OSSL permissions for `osSetParcelDetails`. Only worth it if the script has to do more than the name.

Next step when taking it up: try the OAR with a parcel only on a test region, with `--force-parcels`, and see whether the name changes live and survives a restart.

## Now

Keep the restart. The solution stays a stopgap until then.
