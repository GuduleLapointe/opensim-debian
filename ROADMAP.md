# Roadmap as of 31/05/2019

## Fixes

- discard PendingRestartsTest warning
- README.md should include a brief installation guide, including apt package (recommended) or cloning the repository. Detailed instructions should be in INSTALLATION.md if relevant.
- dependencies packages must be added to the release assets in their own repositories
- users created should have home set to DefaultRegion. Users get an error on login until they set home manually in the viewer
- opensim status "down" count should not be displayed when none of the instances are down
- try to open ports if firewall is active

## Next steps

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

## New features

- try to open nat ports if behind a router

`newsimulator SimName`

- create initial config for simulator SimName.
- Will use general configs from Robust.
- Will be placed in etc/simulators-available and enabled by default

`opensim enable SimName`

`opensim disable SimName`

- Enable or disable instance

`opensim online`

- Show who's on line

Could be great
--------------

- Memory/CPU usage monitoring.
  Notify admin and/or restart Sim above given thresolds.
  Thinking twice about previous notify thing: maybe it's better to let a
  dedicated monitoring tool handle that.
