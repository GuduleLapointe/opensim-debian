OpenSim Debian Distribution
===========================
Version: 2.0.0

This is an framework to facilitate installation and use of OpenSim with Debian.

https://www.speculoos.world/opensim-debian-installation-framework/

Features
--------

`./install/install.sh`

- create basic directory structure, download OpenSim and other needed libraries
- read Robust default configuration, ask a few questions and build a working configuration in etc/robust-d

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


Installation
------------

```shell
git clone --recursive https://git.magiiic.com/opensimulator/opensim-debian.git
# use --recursive to download git submodules
# if you don't use recursive, they will be downloaded during installation
sudo mv opensim-debian /opt/
export PATH=$PATH:/opt/opensim-debian/bin
cd /opt/opensim-debian
./install/install.sh
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

Main motivation
---------------

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
