# OpenSim Kit

![Stable](https://img.shields.io/github/release/GuduleLapointe/opensim-debian?label=stable&color=green&include_prerelease)
![GitHub Tag](https://img.shields.io/github/tag/GuduleLapointe/opensim-debian?label=latest&include_prereleases)
![GitHub commits since latest release](https://img.shields.io/github/commits-since/GuduleLapointe/opensim-debian/latest?label=dev)
![PHP](https://img.shields.io/badge/PHP-8.1+-7884bf)
[![License](https://img.shields.io/badge/license-AGPL--3.0-552b55)](LICENSE)
![GitHub Downloads (all assets, all releases)](https://img.shields.io/github/downloads/GuduleLapointe/opensim-debian/total)
[![Donate](https://img.shields.io/badge/-Donate-yellow)](https://magiiic.org/donate/)

This is an framework to facilitate installation and use of OpenSim with Debian or other *nix flavors.

https://www.speculoos.world/opensim-debian-installation-framework/

## Installation

```bash
./install/install.sh
opensim setup
```

- create basic directory structure, download OpenSim and other needed libraries
- read Robust default configuration, ask a few questions and build a working configuration in etc/robust-d

## Features

`opensim start`

- Start all instances, first in etc/robust-enabled, then in etc/simulators-enabled

`opensim start instance1 [instance2] [...]`

- Start only specified instances
  The config will be the first match in etc/robust.d/<name>.ini or etc/opensim.d/<name>.ini

`opensim stop [now] [instance1] [instance2] [...]`

`opensim restart [now] [instance1] [instance2] [...]`

- Stop/Restart all instances or matching instances
- If first parameter is "now", stops the simulator immediately, otherwise send reminders to leave during 2 minutes then stop

`opensim status`

- Show active instances, per instance and global memory and cpu usage

## Installation from packages (Debian, Ubuntu)

The kit and the OpenSimulator distributions come from the Magiiic apt repository. Add it once:

```bash
curl -fsSL https://apt.magiiic.com/magiiic-packaging.asc | sudo gpg --dearmor -o /usr/share/keyrings/magiiic-packaging.gpg
echo "deb [signed-by=/usr/share/keyrings/magiiic-packaging.gpg] https://apt.magiiic.com stable main" | sudo tee /etc/apt/sources.list.d/magiiic.list
sudo apt update
```

Then install the kit, the tools with the latest OpenSimulator:

```bash
sudo apt install opensim-kit
```

- `opensim-tools`: the tools, in `/usr/share/opensim-tools`, and the `opensim` command. They work with the packaged OpenSimulator releases or any other one.
- `opensim-<version>`, e.g. `opensim-0.9.3.0`: an OpenSimulator release, in `/usr/share/opensim/<version>`. Several releases can be installed side by side, with or without the tools.
- `opensim`: the latest release
- `opensim-<version>-<module>`, e.g. `opensim-0.9.3.0-gloebit`, `opensim-0.9.3.0-opensimsearch`: a module for a release. Installing it does not enable it. It works with any build of the same version.
- `opensim-unstable`: OpenSimulator built from the development branch, in `/usr/share/opensim/unstable`
- `opensim-helpers-search`: the web part of the OpenSimSearch module (the search service the viewers query), in `/usr/share/opensim-helpers/search`, to be served by a web server. Nothing is enabled by installing it, see its `README.Debian`.
- `opensim-kit`: the tools, the latest release, its essential modules and a database server. The other modules are in the repository, not installed automatically.

OpenSim and Robust cannot run without a database. The packages of OpenSimulator accept MySQL/MariaDB or PostgreSQL (and SQLite for regions), on this machine or another one; the setup of this kit works with MySQL/MariaDB (`opensim-kit` installs the server). The setup runs as the user who starts it, with that user's own rights on the database (or as root, `sudo opensim setup`): only the writing of the files and the start of the instances are done as the system user of the install, through `sudo -u`, as every other command. Before writing anything, it checks the account can log in, then that it can use the database. The account of the grid is always tried with its own credentials only, as OpenSimulator will, whatever is in the `~/.my.cnf` of the user. Only what is missing is created, one statement at a time, with an administrator account of the database server: the setup uses the access the user already has (root, or a database account of their own that may create accounts and databases, e.g. in their `~/.my.cnf`, or sudo without a password), and otherwise asks for the credentials of an administrator account, on this machine or another one, kept in memory for the session only. A problem of access can be tried again; a creation that fails ends the setup with the commands to run from an administrator account. A database prepared by someone else, with the credentials, needs none of it.

The files are at the usual places: config in `/etc/opensim`, data in `/var/lib/opensim`, logs in `/var/log/opensim`, cache in `/var/cache/opensim`. The instances run as the `opensim` account: the `opensim` command switches to it, through sudo.

OpenSimulator 0.9.3 needs the .NET 8 runtime and the native library `libgdiplus`, which the packages install (a region stops at once without it, the launcher says so). Ubuntu installs the runtime with the package; on Debian, install it (and `libgdiplus`, for an install that did not come from the packages) with:

```bash
sudo opensim install-dotnet
```

Create a grid with the setup wizard, then start it:

```bash
opensim setup
sudo systemctl start opensim
```

Then add a simulator to the grid, from the same wizard (`Sim`, `Create a new simulator`): it has its own database and its first region, and the owner of its estate is an account of the grid, chosen or created on the way (in the console of Robust, which is started for that if needed). The wizard writes the config of the simulator in `/etc/opensim/grids/<grid>/sims/`, links it into `/etc/opensim/opensim.d`, starts it and checks that its region is online in the grid. More regions are added to a simulator from the same menu, loaded at once when it runs.

The ports of an instance are a block of ten, the same on any machine, so a simulator can move to another one without changing any port: the public port ends with 2, the private one with 3 (Robust only: a simulator has none of its own, so it is one more port for what it may need, SSL, XML-RPC or one more region), the REST console with 4, the rest is for more services or, for a simulator, its regions. Robust takes 8002, 8003 and 8004, the first simulator 8012, 8014 and 8015 to 8019, the next one 8022... The next free block is chosen looking at the config files of the machine, the ports in use, and the regions the grid knows on its other machines. `opensim ports` tells what the enabled instances listen on and who has to reach it: the public ports (and the regions, in UDP, which are the ones the viewers send to) for everyone, the private port of Robust (which has no protection of its own) and the consoles only for the machines that need them. `opensim ports --publish` gives the `-p` options of a container, `--ufw` the rules of a firewall.

The console of an instance is one of two kinds, asked by the wizard (remote by default). The remote one (REST) is reached through a port, x4 of the block of the instance, with a user and a password the wizard makes: it gives every right on the instance, so it works the same from this machine, from a container or from another machine, and its port is only published to the machines that need it (`opensim ports --publish` keeps it to the local one). The other is a screen session, attached on the machine that runs the instance.

`opensim console <instance>` (`screen` works too) attaches the screen session, or opens a prompt on the remote console, which `Ctrl-D` leaves. `opensim command <instance> <text>` sends a single command, or the lines of its standard input with `-`, from a script. For an instance of another machine or a container, by its address: `OPENSIM_REST_PASSWORD=<password> opensim rest --url http://host:8024 --user admin -- show info`. A remote console controls an instance that runs: it can stop it, but not start it again (that is for the service, or the container), nor change its files.

The `opensim` service starts the enabled instances at boot. Package upgrades never restart them.

An existing install, e.g. in `/opt/opensim`, keeps working with the packaged tools without moving anything: its `opensim.conf` gives the locations. To run it with the service, set its account with `sudo systemctl edit opensim` (`User=` and `Group=` in a `[Service]` section).

## Installation from git

```shell
git clone --recursive https://github.com/GuduleLapointe/opensim-debian.git opensim-kit
cd opensim-kit
composer install   # PHP libraries and bash-tools
export PATH=$PATH:$PWD/bin
opensim setup
```

Answer the common setting questions.

Configuration should be working as is, but you will probably want to adjust

- ./etc/opensim/opensim.conf
  (main database configuration)
- ./etc/opensim/robust.d/*.ini (robust settings)
- ./etc/opensim/opensim.d/*.ini (simulators settings)

```shell
opensim start
```

To enable bash completion:

```shell
sudo apt update
sudo apt install bash-completion
sudo ln -s /opt/opensim-debian/lib/bash_completion.d/opensim /etc/bash_completion.d/
```

## Main motivation

In a software application, particularly a complicate one like OpenSim, some
thing should never be stored at the same place. Essentially, there is a place
for static files (executables, libraries), a place for preferences, and a place
for data created by the application (permanent or temporary).

This way, you can

- easily update the software without touching preferences and data
- backup the data without duplicating the software
- avoid duplicating the application if you need to run several instances...

So, we reorganised the files and folders, matching the general Linux standards.

- The whole thing is stored in /opt/opensim-debian (could become
  /usr/share/opensim if we make a package), refferred as OSDDIR below
- Scripts and utilities are in /OSDDIR/bin/
- The main code (latest stable OpenSim release) is located in /OSDDIR/core/opensim (no, not in bin, because they are not directly executable on all OSes, and they rely on lot of other files around them)
- Preferences are read from /OSDDIR/etc/ /etc/ and ~/etc/, each one overriding the precedent
- Cache is stored in /OSDDIR/var/cache
- Logs in /OSDDIR/var/logs
- Databases (if using sqlite) should be store in var/db (but we don't use
  sqlite, so this could be added or not later)
- Git clone and other works in progress should go in dev/

It was important to achieve this without altering the main OpenSim code.
So we created some scripts which:

- read the preferences in etc/
- looks for instances to start in etc/robust.d and etc/opensim.d
- tells OpenSim where to save data, cache and logs

We have developed and used this setup for several years in Speculoos Grid
and wanted to share. Although this was working for us, we don't push the whole
thing as is, as we want to make sure the methods are as globals as they can.
