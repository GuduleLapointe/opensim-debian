Roadmap as of 31/10/2015

Working
-------

$ ./lib/install.sh

- create basic directory structure, download OpenSim and other needed libraries
- read Robust default configuration, ask a few questions and build a working configuration in etc/robust-available

$ opensim start

- Start all instances, first in etc/robust-enabled, then in etc/simulators-enabled

$ opensim start example1 example2 ...

- Start only example1 and example2 instances.
  The config will be the first match in etc/*-enabled/<name>.ini

Planned
-------

Planned work contains functionalities we already have in our devel
environment. They are not included in this repository because 
we still need to test their general usability and stability.

$ opensim stop [instance1] [instance2] [...]

$ opensim restart [instance1] [instance2] [...]

-  Stop/Restart all instances or matching instances

$ newsimulator SimName
  create initial config for simulator SimName.
  Will use general configs from Robust.
  Will be placed in etc/simulators-available and enabled by default

$ opensim enable SimName

$ opensim disable SimName

-  Enable or disable instance, by adding or removing soft link in the 
  corresponding *-enabled folder

$ opensim online
 
- Show who's on line

$ opensim status

- Show open instances

Could be great
--------------

- Bash completion, that would be so cool
- Memory/CPU usage monitoring. 
  Notify admin and/or restart Sim above given thresolds
