## Changelog

### 3.0.0-beta.1

First release as packages. A grid can be created with the wizard, started and run; simulators and regions are still added by hand.

- new Debian and Ubuntu packages, from the Magiiic apt repository: `opensim-kit` (the tools, the latest OpenSimulator, its essential modules, a database server), `opensim-tools`, `opensim-<version>` (an OpenSimulator release, several side by side), `opensim`, `opensim-unstable`, and the modules `opensim-<version>-opensimsearch` and `-gloebit`, installed but not enabled
- new standard layout: releases in `/usr/share/opensim`, config in `/etc/opensim`, data in `/var/lib/opensim`, logs in `/var/log/opensim`, cache in `/var/cache/opensim`, instances run by the `opensim` account
- new `opensim` service, starts the enabled instances at boot, stopped with the package, never restarted by an upgrade
- new `opensim setup` wizard, creates a grid and its Robust configuration, checks then prepares its database step by step (account, database, rights), and can enable and start the grid, with an exit code that tells whether it runs
- new the setup uses the database rights of the user who starts it, no sudo needed; an administrator account is asked only when something has to be created and the user has no access to do it
- new `opensim install-dotnet`, installs the .NET 8 runtime OpenSimulator 0.9.3 needs where the distribution does not provide it
- new `opensim start` waits until Robust has loaded every service its configuration lists, names the ones that failed, and exits with an error when an instance does not start
- update the `opensim` command runs as the system user of the install, through sudo, for every command but `setup`
- update an existing install (e.g. `/opt/opensim`) keeps working with the packaged tools, its `opensim.conf` gives the locations
- update the tools use [bash-tools](https://github.com/magicoli/bash-tools) 1.0.4 or later, as a package or through composer
- update the version of the kit restarts at 3.0.0

Not covered yet: creating simulators and regions from the setup, several Robust instances, and running the modules with Robust, which is not tested.
