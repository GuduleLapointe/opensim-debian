#!/usr/bin/env bash
# The setup run by a person, as on an installed machine: the files of the grids belong to the system user (mode 640) and the
# person cannot read them. A person allowed to use sudo for the system user adds a region (its port is in the block of its
# simulator, it is online); a person who is not is told at once, before any question. Sourced by package-scenario.sh
# (STOP_AFTER=person stops the scenario after it), in the test container, on the grid the previous sections used.

[[ $(type -t check) == function ]] || source /test/scenario-lib.sh

regions=/etc/opensim/grids/testgrid/sims/testgrid_sim1/regions

ts "person: a region added by a user who cannot read the files of the grid"
id plainuser >/dev/null 2>&1 || useradd -m -s /bin/bash plainuser
echo 'plainuser ALL=(opensim) NOPASSWD: ALL' >/etc/sudoers.d/plainuser && chmod 440 /etc/sudoers.d/plainuser
check "the user cannot read the files of the grid" "! runuser -u plainuser -- cat $regions/Sim1.ini >/dev/null 2>&1"
(cd /var/lib/opensim && TEST_GRID=testgrid TEST_SIM=Sim1 TEST_ADD_REGION=PersonRegion TEST_ADMIN_USER=dbroot TEST_ADMIN_PASSWORD=adminpw runuser -u plainuser -- php /test/newsim-driver.php >/tmp/person.out 2>&1
    echo "exit code: $?" >>/tmp/person.out)
check "the region is added and online" "grep -q 'exit code: 0' /tmp/person.out && grep -q 'Region PersonRegion is online' /tmp/person.out"
check "its port is in the block of its simulator, not 1" "grep -qE '^InternalPort = 900[3-9]' $regions/PersonRegion.ini"

ts "person: a user who cannot use sudo for the system user"
id sudoless >/dev/null 2>&1 || useradd -m -s /bin/bash sudoless
start=$SECONDS
runuser -u sudoless -- opensim setup </dev/null >/tmp/sudoless.out 2>&1
code=$?
check "the setup stops at once and says why" "[ $code = 1 ] && [ $((SECONDS - start)) -lt 10 ] && grep -q 'needs sudo' /tmp/sudoless.out"

ts "person: the grid is back as it was"
rm -f $regions/PersonRegion.ini
mysql -e "DELETE FROM testgrid_robust.regions WHERE regionName = 'PersonRegion'"
