# The tools work with any OpenSim installation

## Need

`opensim-tools` should serve an install made with the kit, and as well one made by hand (`/opt/osgrid`, `~/diva`, `~/opensim/opensim-0.9.3.0/bin`...), by making `/etc/opensim/opensim.conf` a reference to where the user's own core, configs, data and logs are.

## Known

- `Config` already reads `opensim.conf` profiles (`DefaultProfile`, `EtcRoot`, `CoreDirectory`, `DataRoot`, ...) from `~/.config/opensim`, `~/.opensim.conf`, `/etc/opensim`; the packages create the profiles of the cores they install (a dpkg trigger), and the README says an existing install in `/opt/opensim` keeps working through its own `opensim.conf`.
- The launcher finds the instances in `$etc/robust.d` and `$etc/opensim.d` (and the older `-enabled` folders); the grids and simulators the setup makes live in `etc/grids/<nick>/`.

## Proposed

1. **Profiles for any install**: `opensim profile add <name> --core DIR --etc DIR [--data DIR --logs DIR --cache DIR --user USER]` (and `list`, `remove`, `default`) writes a section of `opensim.conf`; the setup menu of the core offers it too.
2. **No assumption in the scripts**: every path comes from the profile (a review of the scripts for hard-coded `/etc/opensim`, `/var/lib/opensim`, `/usr/share/opensim`; `grep` for them, one fix per file, with a test that runs the command in a temporary tree as `NextTest` does).
3. **Instances**: the launcher reads `instances` of the profile (the folders to look in), so a custom layout can be declared without moving files.
4. **Combined with `config-import.md`**: an imported grid is a profile whose files stay where they are.

## To decide

- The name and the keys of the profile (`EtcRoot` is used already).
- The default profile when several exist, and how `sudo -u` (system user) is chosen for a custom install: today `SystemUser` of `[Defaults]`.
