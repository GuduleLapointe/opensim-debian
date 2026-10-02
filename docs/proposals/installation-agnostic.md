# The tools work with any OpenSim installation

## Need

`opensim-tools` should serve an install made with the kit and one made by hand (`/opt/osgrid`, `~/diva`, `~/opensim/opensim-0.9.3.0/bin`...), by making `/etc/opensim/opensim.conf` a reference to where the user's own core, configs, data and logs are.

## Known

- `EtcRoot` is not an OpenSimulator notion: it is the kit's, so the kit decides what it means. The profile has to tell the **base directories**: of the core, of the config files, of the data (and logs and cache). In an install by the book, the three are the same, `/path/to/opensim/bin`.
- `Config` reads the `opensim.conf` profiles (`DefaultProfile`, `EtcRoot`, `CoreDirectory`, `DataRoot`...) from `~/.config/opensim`, `~/.opensim.conf`, `/etc/opensim`; the packages create the profiles of the cores they install (a dpkg trigger).
- The launcher finds the instances in `$etc/robust.d` and `$etc/opensim.d`; the setup keeps the grids and simulators it makes in `etc/grids/<nick>/`.

## Proposed

1. **A profile per install, and per grid**: the profile of an existing install gives its base directories (core, ini, data), the defaults of the grids of that install follow it; nothing is assumed from the layout of the kit. A grid has its own profile (its defaults come from it), as a grid that was not made by the kit is only an install that exists.
2. **The default profile has one use**: the defaults of a **new** grid (where to put it, which core); it does not apply to a grid that already exists, which follows its own install.
3. **`opensim profile add <name> --core DIR --etc DIR [--data DIR]`** (and `list`, `remove`) writes a section of `opensim.conf`; the setup offers it for the core menu too. With only the base directory (`/path/to/opensim/bin` for all three) in the by-the-book case.
4. **No assumption in the scripts**: every path comes from the profile (review for `/etc/opensim`, `/var/lib/opensim`, `/usr/share/opensim` hard-coded; one fix per file, with a test that runs the command in a temporary tree, as `NextTest` does).
5. **Together with `config-import.md`**: an imported grid is a grid whose files, and so whose profile, stay where they are.

## To decide

- The keys of the profile beyond the three directories (the system user, the instances folders).
- How `sudo -u` (the system user) is chosen for a custom install: today `SystemUser` of `[Defaults]`; per profile is more natural.
