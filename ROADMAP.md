# Roadmap as of 31/05/2019


## Fixes

- try to open ports if firewall is active

## New features

- try to open nat ports if behind a router

`newsimulator SimName`

-  create initial config for simulator SimName.
-  Will use general configs from Robust.
-  Will be placed in etc/simulators-available and enabled by default

`opensim enable SimName`

`opensim disable SimName`

-  Enable or disable instance

`opensim online`

- Show who's on line


Could be great
--------------

- Memory/CPU usage monitoring.
  Notify admin and/or restart Sim above given thresolds.
  Thinking twice about previous notify thing: maybe it's better to let a
  dedicated monitoring tool handle that.
