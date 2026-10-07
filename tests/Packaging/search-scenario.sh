#!/usr/bin/env bash
# The search of a grid, as the simulators and the viewers use it, on the grid of the scenario: its Robust and its simulator
# are running, its accounts exist, its helpers are the ones of the packages. Sourced by package-scenario.sh (STOP_AFTER=search
# stops the scenario after it, to run only this), in the test container.
#
# Two kinds of simulator: one that only tells its snapshot (search/sim-fake.php, with the files collector-*.xml in the
# format OpenSimulator writes), to know what the tables must hold, and the real simulator of the grid.
# The searches are the calls of the OpenSimSearch module to query.php, with the keys it sends (search/search-check.php, from
# the contract checked against the source of the module). The search of people is not the module's: the core asks the
# user accounts service of Robust, which is asked here the same way.

[[ $(type -t check) == function ]] || source /test/scenario-lib.sh

db=testgrid_robust
sql() { mysql -BN "$db" -e "$1"; }
site=http://localhost:8090
helpers=$site/helpers
null_key=00000000-0000-0000-0000-000000000000
# The searches of one group, their lines, and a failure of any of them is one of the scenario
queries() { # group
    local out
    out=$(php /test/search/search-check.php "$1" "$helpers/query.php" 2>&1) || FAILED=1
    echo "$out"
}
# What a simulator does when it starts, and when it stops, to the search. Registering answers at once: the snapshot is read
# a moment later (register.php lets the simulator go on starting), so what follows waits for it
register() { # port, online|offline, host
    curl -s -m 90 -o /dev/null "$helpers/register.php?host=${3:-127.0.0.1}&port=$1&service=$2"
}

# The tables start empty, whatever a former run left (not the regions of Robust, which are another table)
for table in hostsregister allparcels parcels parcelsales popularplaces objects events regionsregister; do
    sql "DELETE FROM $table" 2>/dev/null || true
done
sql "DELETE FROM classifieds WHERE classifieduuid LIKE 'eeeeeeee-%'" 2>/dev/null || true
pkill -f 'php -S 127.0.0.1:8090' 2>/dev/null || true
pkill -f 'php -S 127.0.0.1:9990' 2>/dev/null || true

ts "search: the site of the helpers, and a simulator that only tells its snapshot"
mkdir -p /tmp/fakesim
(cd /var/www/html && OPENSIM_GRID=testgrid PHP_CLI_SERVER_WORKERS=4 setsid -f php -S 127.0.0.1:8090 index.php >/tmp/php-search.log 2>&1)
(cd /test/search && setsid -f php -S 127.0.0.1:9990 sim-fake.php >/tmp/fakesim.log 2>&1)
cp /test/search/collector-1-fixture.xml /tmp/fakesim/collector.xml
wait_check 20 "the site of the helpers answers" "[ \"\$(curl -s -o /dev/null -w '%{http_code}' $helpers/motd.php)\" = 200 ]"
wait_check 20 "the simulator tells its snapshot" "curl -s 'http://127.0.0.1:9990/?method=collector' | grep -q '<regiondata>'"
check "register.php without a host and a port is refused" "[ \"\$(curl -s -o /dev/null -w '%{http_code}' $helpers/register.php)\" = 400 ]"
regions=$(sql "SHOW TABLES LIKE 'regionsregister'")
regions=${regions:-regions} # in the database of Robust, the table of the regions of the search has another name
check "the search tables are made by the first request" "[ \"\$(sql \"SHOW TABLES\" | grep -cE '^(hostsregister|parcels|allparcels|parcelsales|popularplaces|objects|events|classifieds|$regions)\$')\" = 9 ]"

ts "search: a simulator registers, and the helpers read its snapshot"
register 9990 online
wait_check 60 "the snapshot is read: the regions of the simulator are known" "[ \"\$(sql \"SELECT COUNT(*) FROM $regions WHERE url='http://127.0.0.1:9990/'\")\" = 3 ]"
check "the simulator is in the register, it is not asked again before its snapshot expires" "[ \"\$(sql \"SELECT COUNT(*) FROM hostsregister WHERE host='127.0.0.1' AND port=9990 AND failcounter=0 AND nextcheck > UNIX_TIMESTAMP() + 3000\")\" = 1 ]"
check "its three regions are known, by the address of the simulator" "[ \"\$(sql \"SELECT GROUP_CONCAT(regionname ORDER BY regionname) FROM $regions WHERE url='http://127.0.0.1:9990/'\")\" = Alpha,Beta,Gamma ]"
check "the owner of a region is the one of its estate" "[ \"\$(sql \"SELECT CONCAT(owner, '|', ownerUUID) FROM $regions WHERE regionname='Alpha'\")\" = 'Alice Owner|aaaaaaaa-0000-4000-8000-000000000001' ]"
check "every parcel is known, the ones shown in search have their own list" "[ \"\$(sql \"SELECT COUNT(*) FROM allparcels\")\" = 6 ] && [ \"\$(sql \"SELECT COUNT(*) FROM parcels WHERE regionUUID IN (SELECT regionUUID FROM $regions WHERE url='http://127.0.0.1:9990/')\")\" = 5 ]"
check "the land for sale has its price and its area" "[ \"\$(sql \"SELECT CONCAT(saleprice, '|', area, '|', parcelname) FROM parcelsales\")\" = '500|2048|Sale Meadow' ]"
check "the popular places are the parcels shown in search that are not for sale" "[ \"\$(sql \"SELECT GROUP_CONCAT(name ORDER BY name) FROM popularplaces\")\" = 'Adult Club,Garden of Alpha,Group Hall,Mature Lounge' ]"
check "the objects of the parcels are known" "[ \"\$(sql \"SELECT GROUP_CONCAT(name ORDER BY name) FROM objects\")\" = '120/130/25,130/128/26' ]"
check "the address of the gatekeeper is kept for the parcels" "[ \"\$(sql \"SELECT COUNT(*) FROM parcels WHERE gatekeeperURL='http://127.0.0.1:8002/'\")\" = 5 ]"
# One parcel has a picture and a group: the next ones must not get them
check "a parcel has the picture and the group it tells, and only it" "[ \"\$(sql \"SELECT COUNT(*) FROM parcels WHERE imageUUID='cccccccc-0000-4000-8000-000000000001'\")\" = 1 ] &&
    [ \"\$(sql \"SELECT COUNT(*) FROM allparcels WHERE groupUUID='bbbbbbbb-0000-4000-8000-000000000001'\")\" = 1 ] &&
    [ \"\$(sql \"SELECT has_picture FROM popularplaces WHERE name='Group Hall'\")\" = 1 ] && [ \"\$(sql \"SELECT SUM(has_picture) FROM popularplaces\")\" = 1 ]"

ts "search: the searches of the viewers"
sed "s/{{regions}}/$regions/" /test/search/search-tables-fixture.sql | mysql "$db"
check "the events, the classifieds and the parcels for the pages are in the tables" "[ \"\$(sql 'SELECT COUNT(*) FROM events')\" = 4 ] && [ \"\$(sql 'SELECT COUNT(*) FROM classifieds')\" = 3 ] && [ \"\$(sql \"SELECT COUNT(*) FROM parcels WHERE parcelname LIKE 'Paging Plot%'\")\" = 120 ]"
for group in places popular land events classifieds; do
    ts "search: $group"
    queries $group
done

ts "search: the simulator tells another snapshot"
cp /test/search/collector-2-fixture.xml /tmp/fakesim/collector.xml
register 9990 online
wait_check 60 "the new snapshot is read" "[ \"\$(sql \"SELECT COUNT(*) FROM allparcels WHERE parcelname LIKE '%renewed%'\")\" = 1 ]"
queries changed

ts "search: the simulator stops"
register 9990 offline
check "the register has not the simulator, its regions and its parcels are forgotten" "[ \"\$(sql \"SELECT COUNT(*) FROM hostsregister WHERE port=9990\")\" = 0 ] &&
    [ \"\$(sql \"SELECT COUNT(*) FROM $regions WHERE url='http://127.0.0.1:9990/'\")\" = 0 ] && [ \"\$(sql 'SELECT COUNT(*) FROM allparcels')\" = 0 ] &&
    [ \"\$(sql 'SELECT COUNT(*) FROM popularplaces')\" = 0 ] && [ \"\$(sql 'SELECT COUNT(*) FROM objects')\" = 0 ]"
queries gone

# A simulator that cannot be reached is counted, and asked again later, more and more late; after ten failures it is forgotten
ts "search: a simulator that cannot be reached"
register 9991 online
wait_check 60 "it is counted as unreachable and asked again later, not at the next run" "[ \"\$(sql \"SELECT CONCAT(failcounter, '|', nextcheck > UNIX_TIMESTAMP() + 1000) FROM hostsregister WHERE port=9991\")\" = '1|1' ]"
sql "UPDATE hostsregister SET failcounter = 10, nextcheck = 0, checked = 0 WHERE port = 9991"
curl -s -m 60 -o /dev/null "$helpers/parser.php"
wait_check 60 "after ten failures it is forgotten" "[ \"\$(sql \"SELECT COUNT(*) FROM hostsregister WHERE port=9991\")\" = 0 ]"

# The simulator of the grid: its snapshot is what OpenSimulator writes, read by the same parser. It answers one request a
# minute to an address (503 "try later" for the next ones): nothing asks for it before the parser. It registers with its
# own address, the one of its regions (the address of the machine), which unregistering looks for
ts "search: the simulator of the grid"
own_host=$(hostname -I | cut -d' ' -f1)
register 9000 online "$own_host"
wait_check 90 "it tells its snapshot, its config asks it to index its regions: both regions are known" "[ \"\$(sql \"SELECT COUNT(*) FROM $regions WHERE url LIKE '%:9000/'\")\" = 2 ]"
check "it is in the register, its regions are known with the owner of their estate and the name of the grid" "[ \"\$(sql \"SELECT COUNT(*) FROM hostsregister WHERE port=9000 AND failcounter=0\")\" = 1 ] &&
    [ \"\$(sql \"SELECT GROUP_CONCAT(regionname ORDER BY regionname) FROM $regions WHERE url LIKE '%:9000/'\")\" = Sim1,Sim1North ] &&
    [ \"\$(sql \"SELECT COUNT(DISTINCT owner) FROM $regions WHERE url LIKE '%:9000/' AND owner = 'Test Owner'\")\" = 1 ]"
register 9000 offline "$own_host"
check "it unregisters as well" "[ \"\$(sql \"SELECT COUNT(*) FROM hostsregister WHERE port=9000\")\" = 0 ] && [ \"\$(sql \"SELECT COUNT(*) FROM $regions WHERE url LIKE '%:9000/'\")\" = 0 ]"

# The simulator registers by itself when it starts, to the services of its [DataSnapshot] (the one of the setup is on https://localhost,
# which nothing serves here: another one is added, the one of this test), and tells the parcels shown in search (the flag
# ShowDirectory, 4096, in its land table, read when it starts)
ts "search: the simulator of the grid registers by itself, and tells a parcel shown in search"
sim_ini=/etc/opensim/grids/testgrid/sims/testgrid_sim1.ini
sed -i '/^DATA_SRV_Search/d' "$sim_ini" # (what a former run left)
cp "$sim_ini" /tmp/sim1.ini.search
# (an address of the grid on this site: the helpers do not answer an address that has another host name)
sed -i "/^\[DataSnapshot\]/a DATA_SRV_Search = \"http://localhost:8090/helpers/register.php\"" "$sim_ini"
mysql testgrid_sim1 -e "UPDATE land SET LandFlags = LandFlags | 4096 WHERE Name = 'Sim1'"
opensim stop now testgrid_sim1 >/dev/null 2>&1
opensim start testgrid_sim1 >/dev/null 2>&1
wait_check 180 "it registers when it starts: its regions and its parcel shown in search are known" "[ \"\$(sql \"SELECT COUNT(*) FROM parcels WHERE parcelname='Sim1'\")\" = 1 ]"
sim=$(sim_pid) # restarted: what the scenario compares it with later is this one
check "it is in the register, its regions are known" "[ \"\$(sql \"SELECT COUNT(*) FROM hostsregister WHERE port=9000\")\" = 1 ] &&
    [ \"\$(sql \"SELECT GROUP_CONCAT(regionname ORDER BY regionname) FROM $regions WHERE url LIKE '%:9000/'\")\" = Sim1,Sim1North ]"
check "the simulator tells only the parcel that has the flag, its other parcel is not told" "[ \"\$(sql \"SELECT GROUP_CONCAT(parcelname) FROM allparcels\")\" = Sim1 ]"
queries sim
register 9000 offline "$own_host"
cp /tmp/sim1.ini.search "$sim_ini"

# The search of people is made by the core: it asks the user accounts service of Robust (the accounts of the list made by
# opensim import are there)
ts "search: people"
people() { curl -s -d "VERSIONMIN=0&VERSIONMAX=0&METHOD=getaccounts&ScopeID=$null_key&query=$1" http://127.0.0.1:8003/accounts; }
accounts() { people "$1" | grep -o '<FirstName' | wc -l | tr -d ' '; }
check "people: a first name finds its accounts" "[ \"\$(accounts Bulk)\" = 2 ]"
check "people: a first name and a last name find one" "[ \"\$(accounts 'Bulk One')\" = 1 ] && people 'Bulk One' | grep -q '<LastName>One<'"
check "people: nobody is found for a name nobody has" "[ \"\$(accounts Nobodyhere)\" = 0 ]"

# The box "show in search" of a profile: the core keeps it nowhere, the module tells it to the search, which writes it where the
# profile service keeps it (a profile that exists, from a simulator of the grid: here the machine itself)
ts "search: the show in search box of a profile"
profile=bbbbbbbb-0000-4000-8000-0000000000bb
sql "DELETE FROM userprofile WHERE useruuid IN ('$profile', 'cccccccc-0000-4000-8000-0000000000cc')"
sql "INSERT INTO userprofile (useruuid, profilePartner, profileAllowPublish, profileMaturePublish, profileURL, profileWantToMask, profileWantToText, profileSkillsMask, profileSkillsText, profileLanguages, profileImage, profileAboutText, profileFirstImage, profileFirstText) VALUES ('$profile', '$null_key', 0, 1, '', 0, '', 0, '', '', '$null_key', '', '$null_key', '')"
# (the two avatars are in the grid, with their secure session, as Robust keeps it when they are connected)
sql "DELETE FROM Presence WHERE UserID IN ('$profile', 'cccccccc-0000-4000-8000-0000000000cc')"
sql "INSERT INTO Presence (UserID, RegionID, SessionID, SecureSessionID) VALUES ('$profile', '$null_key', 'dddddddd-0000-4000-8000-0000000000d1', 'eeeeeeee-0000-4000-8000-0000000000e1'), ('cccccccc-0000-4000-8000-0000000000cc', '$null_key', 'dddddddd-0000-4000-8000-0000000000d2', 'eeeeeeee-0000-4000-8000-0000000000e2')"
queries publish
check "profile box: the profile is published, not mature, as told, and not by a session that is not its avatar's" "[ \"\$(sql \"SELECT CONCAT(profileAllowPublish, profileMaturePublish) FROM userprofile WHERE useruuid='$profile'\")\" = 10 ]"
check "profile box: no profile was made for an avatar that had none" "[ \"\$(sql \"SELECT COUNT(*) FROM userprofile WHERE useruuid='cccccccc-0000-4000-8000-0000000000cc'\")\" = 0 ]"
sql "DELETE FROM userprofile WHERE useruuid='$profile'"
sql "DELETE FROM Presence WHERE UserID IN ('$profile', 'cccccccc-0000-4000-8000-0000000000cc')"

pkill -f 'php -S 127.0.0.1:8090' || true
pkill -f 'php -S 127.0.0.1:9990' || true
