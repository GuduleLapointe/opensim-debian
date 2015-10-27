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
  /usr/share/opensim if we make a package)
  - Scripts and utilities are in bin/
  - The main code (latest stable OpenSim release) is located in lib/opensim
  (yes, in lib, not in bin, because they are not directly executable on all OSes,
  and they rely on lot of other files around them)
  - Preferences are read from etc/ /etc/ and ~/etc/ )
  - Cache is stored in var/cache
  - Logs in var/log
  - Databases (if using sqlite) should be store in var/db (but we don't use
  sqlite, so this could be done later)
  - If using other stable release(s) that the one included, they should be
  stored in share/
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

Note: the project is not complete, these instructions will not work yet, but
this shows you the way it works.

```shell
git clone https://github.com/magicoli/opensim-debian.git
sudo mv opensim-debian /opt/
export PATH=$PATH:/opt/opensim-debian/bin
```

Adjust 
  - /opt/opensim-debian/etc/OpenSim.ini 
  - the simulators in /opt/opensim-debian/etc/opensim-enabled 

```shell
opensim start
```
