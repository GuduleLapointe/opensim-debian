#!/usr/bin/env bash
# The economy of a grid, on the grid of the scenario: what the viewer and the simulators ask currency.php and landtool.php of
# the helpers (economy/economy-check.php makes the calls, as they do, and checks the answers and what the helpers wrote in the
# database): with the economy of the helpers alone, with a money server in the simulators (economy/region-fake.php is the
# simulator and its money module, the helpers call the region of the avatar), with the settings of the grid, and for the
# providers that sell currency (Gloebit, Podex). The tables of the economy (balances, transactions) are made by the money server or the Gloebit module,
# not by the helpers or the packages, so the test creates them. Sourced by package-scenario.sh (STOP_AFTER=economy stops the scenario after it),
# in the test container.

[[ $(type -t check) == function ]] || source /test/scenario-lib.sh

db=testgrid_robust
ini=$helpers_ini
helpers=http://127.0.0.1:8090/helpers
sql() { mysql -BN "$db" -e "$1"; }
account() { sql "SELECT PrincipalID FROM UserAccounts WHERE FirstName='$1' AND LastName='$2'"; }
calls() { # group
    local out
    out=$(DB=$db AGENT=$agent FRIEND=$friend SECURE=$secure FRIEND_SECURE=$friend_secure SESSION=$session REGION=$region SECRET=$secret REGION2=$region2 SECRET2=$secret2 HELPER_URL=$helper_url SCRIPT_KEY=${script_key:-} php /test/economy/economy-check.php "$1" "$helpers" 2>&1) || FAILED=1
    echo "$out"
}
start_region() {
    rm -rf /tmp/fakeregion
    (cd /test/economy && DB=$db PHP_CLI_SERVER_WORKERS=2 setsid -f php -S 127.0.0.1:8099 region-fake.php >/tmp/php-fakeregion.log 2>&1)
    wait_check 20 "the fake region answers" "curl -s -o /dev/null -m 5 -X POST --data '<x/>' http://127.0.0.1:8099/"
    rm -rf /tmp/fakeregion
}
stop_region() { pkill -f 'php .*-S 127.0.0.1:809[9]' 2>/dev/null || true; }

agent=$(account Bulk One)
friend=$(account Bulk Two)
secure=22222222-2222-4222-8222-222222222222
friend_secure=44444444-4444-4444-8444-444444444444
session=11111111-1111-4111-8111-111111111111
friend_session=33333333-3333-4333-8333-333333333333
region=$(sql "SELECT uuid FROM regions WHERE regionName='Sim1'")
secret=$(sql "SELECT regionSecret FROM regions WHERE regionName='Sim1'")
region2=$(sql "SELECT uuid FROM regions WHERE regionName='Sim1North'")
secret2=$(sql "SELECT regionSecret FROM regions WHERE regionName='Sim1North'")
home_region=99999999-0000-4000-8000-0000000000aa
helper_url=https://localhost/helpers/currency.php
script_key=

ts "economy: the site of the helpers, a region with a money module, two avatars in it"
cp -p $ini /tmp/helpers.ini.economy
pkill -f 'php .*-S 127.0.0.1:809[0]' 2>/dev/null || true
(cd /var/www/html && OPENSIM_GRID=testgrid PHP_CLI_SERVER_WORKERS=4 setsid -f php -S 127.0.0.1:8090 index.php >/tmp/php-economy.log 2>&1)
wait_check 20 "the site of the helpers answers" "[ \"\$(curl -s -o /dev/null -w '%{http_code}' $helpers/motd.php)\" = 200 ]"
start_region
# The region of the avatars: a copy of one of the grid that is where the fake one is
mysql "$db" -e "
    CREATE TEMPORARY TABLE r SELECT * FROM regions WHERE uuid = '$region';
    UPDATE r SET uuid = '$home_region', regionName = 'Money Test', regionHandle = 1, locX = 1, locY = 1, serverIP = '127.0.0.1', serverHttpPort = 8099, serverURI = 'http://127.0.0.1:8099/';
    INSERT INTO regions SELECT * FROM r"
for who in "$agent $session $secure" "$friend $friend_session $friend_secure"; do
    set -- $who
    sql "DELETE FROM Presence WHERE UserID = '$1'; INSERT INTO Presence (UserID, RegionID, SessionID, SecureSessionID) VALUES ('$1', '$region', '$2', '$3')"
    sql "DELETE FROM GridUser WHERE UserID = '$1'; INSERT INTO GridUser (UserID, HomeRegionID, LastRegionID, Online, Login, Logout) VALUES ('$1', '$region', '$home_region', 'True', '0', '0')"
done
check "the avatars are in a region that has a money module" "[ \"\$(sql \"SELECT COUNT(*) FROM Presence p JOIN GridUser g ON g.UserID = p.UserID JOIN regions r ON r.uuid = g.LastRegionID WHERE p.UserID IN ('$agent', '$friend') AND r.serverHttpPort = 8099\")\" = 2 ]"

ts "economy: the helpers alone (no money server, the default of a grid)"
sql "DROP TABLE IF EXISTS transactions; DROP TABLE IF EXISTS balances;
    CREATE TABLE transactions (sourceId char(36), destId char(36), amount int, flags int, aggregatePermInventory int, aggregatePermNextOwner int, description varchar(255), transactionType int, timeOccurred int, RegionGenerated char(36), ipGenerated varchar(64))"
calls plain

ts "economy: the settings of the grid"
cfg currency_script_key "key-of-the-grid"
cfg currency_rate 4
script_key=key-of-the-grid
calls settings
script_key=
sed -i '/^currency_script_key/d;/^currency_rate /d' $ini

ts "economy: with a money server in the simulators"
cfg currency_use_moneyserver true
sql "DROP TABLE transactions;
    CREATE TABLE transactions (UUID char(36), sender char(36), receiver char(36), amount int, objectUUID char(36), regionHandle bigint unsigned, type int, time int, secure char(36), status int, description varchar(255));
    CREATE TABLE balances (user char(36) PRIMARY KEY, balance int, status int);
    INSERT INTO balances VALUES ('$agent', 700, 0), ('$friend', 0, 0)"
rm -f /tmp/fakeregion/calls.log
calls moneyserver

ts "economy: with a region that does not answer"
stop_region
calls moneyserver-down

ts "economy: Gloebit"
cfg currency_provider gloebit
calls gloebit

ts "economy: Podex"
cfg currency_provider podex
calls podex

ts "economy: the grid is back as it was"
cp -p /tmp/helpers.ini.economy $ini
sql "DROP TABLE transactions; DROP TABLE balances; DELETE FROM regions WHERE uuid = '$home_region'; DELETE FROM Presence WHERE UserID IN ('$agent', '$friend'); DELETE FROM GridUser WHERE UserID IN ('$agent', '$friend')"
pkill -f 'php .*-S 127.0.0.1:809[0]' 2>/dev/null || true
