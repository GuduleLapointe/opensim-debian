# Roadmap

## Fixes

- confirm on ubuntu 24.04 (ursull), with the next package and an outdated bash-tools left in composer global, that `opensim setup` no longer ends with `debug: command not found` (the scripts now load the bash-tools of the kit before the one of the `PATH`, see `libexec/load-helpers`)
- dependencies packages must be added to the release assets in their own repositories
- opensim status "down" count should not be displayed when none of the instances are down
- a new region is named in the database while its simulator is stopped, which costs a restart of the simulator (TODO in `Actions::nameParcels`): look for a better way to name its parcel
- try to open ports if firewall is active

## Critical improvements

- bulk avatar creation (from a list provided by a third-party source; format to be defined, likely CSV or JSON). This is a genuine, long-awaited user request. This feature relies on the ability to use the current host configuration—unchanged—regardless of the installation method employed (with or without OpenSimKit).
- test `opensim import` on real grids (the config of a grid or of a simulator, a setup file, a list of accounts: what works, what is missing)
- add opensim-helpers package and examples of configuration as alias/subfolder in caddy, nginx and apache2
- add docker/podman installation support in setup
- rename composer packages to match github account GuduleLapointe

## Next steps

- add full import procedure for existing grids/simulators (normalized config + db + assets and other data)
- add/update bash completion
- build ready to use zip packages for simple download, added to the release assets alongside the source code and apt package
- add simplified installation script, detecting the platform and installing with the appropriate method (zip package or apt package), with a one-line command like `curl -sSL https://raw.githubusercontent.com/GuduleLapointe/opensim-kit/refs/heads/master/install.sh | bash`
- add basic ready to use website
- add ready to use default avatars
- add default Inventory package
- add nat/port forwarding instructions or presets (with standard tools or third-party provides like ngrok, cloudflare...)
- make most opensim-tools features installation-agnostic (support both opensim-kit and custom/standard opensim installations) : `/etc/opensim/opensim.conf` becomes only a reference pointing to the user's actual core installation and configuration files location (e.g. `/opt/osgrid`, `~/diva`, `~/opensim/opensim-0.9.3.0/bin`...)

- test the OpenSimSearch module from end to end again (a region registers, is indexed and found) with the `opensim-helpers` package once it exists: the test written for the former `opensim-manfredaabye-helpers` package is gone with it

## Less urgent

- localize the setup and the launcher with gettext (a requirement of the project), their messages are still English strings
- localize helpers
- `opensim online`: show who's online

## Could be great

- Memory/CPU usage monitoring. Notify admin and/or restart Sim above given thresolds. Thinking twice about previous notify thing: maybe it's better to suggest a dedicated monitoring tool handle that.
