# OpenSimulator Kit

![Stable](https://img.shields.io/github/release/GuduleLapointe/opensim-debian?label=stable&color=green&include_prerelease)
![GitHub Tag](https://img.shields.io/github/tag/GuduleLapointe/opensim-debian?label=latest&include_prereleases)
![GitHub commits since latest release](https://img.shields.io/github/commits-since/GuduleLapointe/opensim-debian/latest?label=dev)
![PHP](https://img.shields.io/badge/PHP-8.2+-7884bf)
[![License](https://img.shields.io/badge/license-AGPL--3.0-552b55)](LICENSE)
![GitHub Downloads (all assets, all releases)](https://img.shields.io/github/downloads/GuduleLapointe/opensim-debian/total)
[![Donate](https://img.shields.io/badge/-Donate-yellow)](https://magiiic.org/donate/)

This is an framework to facilitate installation and use of OpenSim with Debian or other *nix flavors.

https://www.speculoos.world/opensim-debian-installation-framework/

## Installation

**From packages (recommended)**

```bash
## Add the Magiiic APT repository (once)
curl -fsSL https://apt.magiiic.com/magiiic-packaging.asc | sudo gpg --dearmor -o /usr/share/keyrings/magiiic-packaging.gpg
echo "deb [signed-by=/usr/share/keyrings/magiiic-packaging.gpg] https://apt.magiiic.com stable main" | sudo tee /etc/apt/sources.list.d/magiiic.list
sudo apt update

## Install the packages
sudo apt install opensim-kit
opensim setup
```

See [INSTALLATION.md](INSTALLATION.md) for more details and advanced installation options.

**From source (advanced)**

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
