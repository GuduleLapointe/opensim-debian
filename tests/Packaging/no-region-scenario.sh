#!/usr/bin/env bash
# A simulator without a region waits at its first start for the answers of a region to create, on a console nobody reads, and
# never comes up: the setup does not start it, the launcher refuses it and says why, and it starts once a region is added.
# Sourced by package-scenario.sh (STOP_AFTER=no-region stops the scenario after it), in the test container, on the grid the
# previous sections used.

[[ $(type -t check) == function ]] || source /test/scenario-lib.sh

instance=testgrid_empty_sim
running() { pgrep -f "OpenSim.dll -inifile=/etc/opensim/opensim.d/$instance.ini" >/dev/null; }
driver() { (cd /var/lib/opensim && TEST_GRID=testgrid TEST_SIM=EmptySim TEST_ADMIN_USER=dbroot TEST_ADMIN_PASSWORD=adminpw "$@" runuser -u opensim -- php /test/newsim-driver.php 2>&1; echo "exit code: $?"); }

ts "no region: a simulator made without one"
driver env TEST_NO_REGION=1 TEST_START=1 >/tmp/emptysim.out
check "the setup says it has no region yet, and does not start it" "grep -q 'exit code: 0' /tmp/emptysim.out && grep -q 'has no region yet' /tmp/emptysim.out && ! running"
opensim start $instance >/tmp/emptysim-start.out 2>&1
code=$?
check "the launcher refuses it and says why, nothing is left running" "[ $code != 0 ] && grep -q 'has no region to load' /tmp/emptysim-start.out && ! running"

ts "no region: a region added to it"
driver env TEST_ADD_REGION=EmptyRegion >/tmp/emptysim-add.out
check "the region is added and the simulator is up with it" "grep -q 'exit code: 0' /tmp/emptysim-add.out && grep -q 'region EmptyRegion is online' /tmp/emptysim-add.out && running"

ts "no region: the grid is back as it was"
opensim stop now $instance >/dev/null 2>&1
rm -rf /etc/opensim/grids/testgrid/sims/$instance* /etc/opensim/opensim.d/$instance.ini /var/lib/opensim/data/testgrid/$instance /var/cache/opensim/testgrid/$instance
mysql -e "DROP DATABASE IF EXISTS $instance; DELETE FROM testgrid_robust.regions WHERE regionName = 'EmptyRegion'"
