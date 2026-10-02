# Roadmap

## Fixes

- confirm on ubuntu 24.04 (ursull) that the kit runs with its own bash-tools: `debug: command not found` came from an outdated copy in composer global, found instead of the 1.0.4 one; bash-tools is now a non-dev dependency, bundled in the `opensim-tools` package and loaded first
- dependencies packages must be added to the release assets in their own repositories
- users created should have home set to DefaultRegion. Users get an error on login until they set home manually in the viewer
- opensim status "down" count should not be displayed when none of the instances are down
- a new region is named in the database while its simulator is stopped, which costs a restart of the simulator (TODO in `Actions::nameParcels`): look for a better way to name its parcel
- try to open ports if firewall is active

## Next steps

- write INSTALLATION.md, the detailed instructions the README refers to
- localize the setup and the launcher with gettext (a requirement of the project), their messages are still English strings
- add/update bash completion
- build ready to use zip packages for simple download, added to the release assets alongside the source code and apt package
- add simplified installation script, detecting the platform and installing with the appropriate method (zip package or apt package), with a one-line command like `curl -sSL https://raw.githubusercontent.com/GuduleLapointe/opensim-kit/refs/heads/master/install.sh | bash`
- add opensim-helpers package and examples of configuration as alias/subfolder in caddy, nginx and apache2
- add basic ready to use website
- add ready to use default avatars
- add configuration import from Robust.ini or OpenSim.ini (normalizing to OpenSimKit architecture)
- add import procedure for existing grids/simulators
- add default Inventory package
- add docker/podman installation support in setup
- add nat/port forwarding instructions or presets (with standard tools or third-party provides like ngrok, cloudflare...)
- rename composer packages to match github account GuduleLapointe

## New features

`opensim enable <instance>` and `opensim disable <instance>`

- Command-line equivalents of the setup menus, to enable or disable a grid, a simulator or a region from a script (the setup does it from its screens)

`opensim online`

- Show who's on line

Could be great
--------------

- Memory/CPU usage monitoring.
  Notify admin and/or restart Sim above given thresolds.
  Thinking twice about previous notify thing: maybe it's better to let a
  dedicated monitoring tool handle that.
