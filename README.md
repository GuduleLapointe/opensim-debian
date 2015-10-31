OpenSim Debian Distribution
===========================

This is an framework to facilitate installation and use of OpenSim with Debian.

Main motivation
---------------

In a software application, particularly a complicate one like OpenSim, some
thing should **never** be stored at the same place. Essentially, there is a place
for static files (executables, libraries), a place for preferences, and a place
for data created by the application (permanent or temporary).

This way, you can
  - easily update the software without touching preferences and date
  - backup the data without duplicating everytime the software
  - avoid duplicating the application if you need to run several instances...

So, we reorganised the files and folders, matching the general Linux standards.
  - The whole thing is stored in /opt/opensim-debian (could become
  /usr/share/opensim if we make a package), refferred as OSDDIR below
  - Scripts and utilities are in /OSDDIR/bin/
  - The main code (latest stable OpenSim release) is located in /OSDDIR/lib/opensim (yes, in lib, not in bin, because they are not directly executable on all OSes, and they rely on lot of other files around them)
  - Preferences are read from /OSDDIR/etc/ /etc/ and ~/etc/, each one overriding the precedent
  - Cache is stored in /OSDDIR/cache
  - Logs in /OSDDIR/logs
  - Databases (if using sqlite) should be store in var/db (but we don't use
  sqlite, so this could be fixed later)
  - If using other stable release(s) that the one included, they should be
  stored in share/ to avoid them being overriding by updates
  - Git clone and other works in progress should go in dev/

It was important to achieve this without altering the main OpenSim code.
So we created some scripts which: 
  - read the preferences in etc/
  - looks for instances to start in etc/simulator-enabled
  - tells OpenSim where to save data, cache and logs

We have developed and used this setup for 5 years in Speculoos Grid 
and wanted to share. Although this was working for us, we don't push the whole
thing as is, as we want to make sure the methods are as globals as they can.

Installation
------------

```shell
git clone --recursive https://github.com/GuduleLapointe/opensim-debian.git
# use --recursive to download git submodules
# if you don't use recursive, they will be downloaded during installation
sudo mv opensim-debian /opt/
export PATH=$PATH:/opt/opensim-debian/bin
cd /opt/opensim-debian
./lib/install.sh
```

Configuration should be working as is, but you will probably want to adjust
  - /etc/opensim/opensim.conf 
  - /etc/opensim/robust-available/Robust.ini
  - /etc/opensim/simulator-available/*.ini 

```shell
opensim start
```
