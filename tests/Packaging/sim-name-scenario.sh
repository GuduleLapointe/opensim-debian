#!/usr/bin/env bash
# Add region, from the menu of a grid (not from a simulator), in a new simulator: the setup proposes the name of the region
# for the simulator made for it. Sourced by package-scenario.sh (STOP_AFTER=sim-name stops the scenario after it), in the
# test container, on the grid the previous sections used.

[[ $(type -t check) == function ]] || source /test/scenario-lib.sh

instance=testgrid_named_region

ts "sim name: a region added to the grid, in a new simulator"
(cd /var/lib/opensim && TEST_GRID=testgrid TEST_REGION_TO_GRID=1 TEST_ADD_REGION='Named Region' TEST_DEFAULT_SIM_NAME=1 TEST_ADMIN_USER=dbroot TEST_ADMIN_PASSWORD=adminpw runuser -u opensim -- php /test/newsim-driver.php >/tmp/simname.out 2>&1
    echo "exit code: $?" >>/tmp/simname.out)
check "the name proposed for the simulator is the one of the region" "grep -q 'text: Simulator name -> Named Region' /tmp/simname.out"
check "the simulator is made with that name, and the region is in it" "grep -q 'exit code: 0' /tmp/simname.out && [ -f /etc/opensim/grids/testgrid/sims/$instance.ini ] && grep -q '^\[Named Region\]' /etc/opensim/grids/testgrid/sims/$instance/regions/*.ini"

ts "sim name: the grid is back as it was"
opensim stop now $instance >/dev/null 2>&1
rm -rf /etc/opensim/grids/testgrid/sims/$instance* /etc/opensim/opensim.d/$instance.ini /var/lib/opensim/data/testgrid/$instance /var/cache/opensim/testgrid/$instance
mysql -e "DROP DATABASE IF EXISTS $instance; DELETE FROM testgrid_robust.regions WHERE regionName = 'Named Region'"
