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

## Now

Keep the restart. The solution stays a stopgap until then.
