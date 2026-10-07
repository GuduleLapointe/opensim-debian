#!/usr/bin/env bash
# Configuring a grid that exists changes the values the setup manages and nothing else: what the operator added (settings,
# sections) stays, and so does the key of the MoneyServer. Sourced by package-scenario.sh (STOP_AFTER=configure stops the
# scenario after it), in the test container, on the grid the previous sections used.

[[ $(type -t check) == function ]] || source /test/scenario-lib.sh

grid=/etc/opensim/grids/testgrid
robust=$grid/Robust.HG.ini

ts "configure: the settings of the operator and the key of the grid before"
cp -p $robust /tmp/Robust.HG.ini.configure
printf '\n[Custom]\n    KeepMe = robust\n' >>$robust
ini_set $robust LoginService WelcomeMessage '"Hello, <USERNAME>, from the operator"'
# The web search given to one of the two settings that say it
ini_set $robust GridInfoService search '"https://play.example.org/search"'
key=$(grep -m1 '^MoneyScriptAccessKey' $grid/MoneyServer.ini)
printf '\n[Custom]\nKeepMe = money\n' >>$grid/MoneyServer.ini
# Edited as root here, the files stay the ones of the system user
chown opensim:www-data $robust $grid/MoneyServer.ini && chmod 640 $robust $grid/MoneyServer.ini

ts "configure: the grid configured again"
(cd /var/lib/opensim && TEST_MODIFY=testgrid TEST_GRID=Testgrid TEST_NO_ENABLE= runuser -u opensim -- php /test/newgrid-driver.php >/tmp/configure.out 2>&1
    echo "exit code: $?" >>/tmp/configure.out)
check "the setup ends well" "grep -q 'exit code: 0' /tmp/configure.out"
check "the settings and the sections of the operator are still in Robust.HG.ini, the file before beside it" "grep -q 'KeepMe = robust' $robust && grep -q 'Hello, <USERNAME>, from the operator' $robust && [ -f $robust~ ]"
check "the web search given to one of the two settings is given to the other, and kept in the first" "grep -qE '^[[:space:]]*SearchURL[[:space:]]*=[[:space:]]*\"https://play.example.org/search\"' $robust && grep -qE '^[[:space:]]*search[[:space:]]*=[[:space:]]*\"https://play.example.org/search\"' $robust"
check "the key of the MoneyServer and what the operator added to its file are kept" "grep -qF '$key' $grid/MoneyServer.ini && grep -q 'KeepMe = money' $grid/MoneyServer.ini"
check "the files keep the mode and the group of the web server" "[ \"\$(stat -c '%a %G' $robust $grid/MoneyServer.ini | sort -u)\" = '640 www-data' ]"

ts "configure: the grid is back as it was"
cp -p /tmp/Robust.HG.ini.configure $robust
sed -i '/^\[Custom\]/,$d' $grid/MoneyServer.ini
