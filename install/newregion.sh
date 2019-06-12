#!/bin/bash

DEBUG=no

BASEDIR=$(dirname $(dirname $(realpath "$0")))
. $BASEDIR/lib/os-helpers || exit 1

log "ETC $ETC"
log "OSBIN $OSBIN"
log "OSBINDIR $OSBINDIR"

which uuidgen >/dev/null || end $? "this scripts depends on uuidgen"

[ "$2" ] && SimName=$1 && shift
[ "$1" ] && RegionName=$1 && shift
[ ! "$SimName" ] && SimName=$RegionName

readvar SimName
SimMachineName=$(echo "$SimName" | tr "[:upper:]" "[:lower:]")
screen -x $SimMachineName -X stuff "config save $TMP.sim.ini\n" || end $? "Simulator $sim must be running"
sleep 0.1
crudget $TMP.sim.ini Startup
[ "$regionload_regionsdir" ] || end $? "region dir $regionload_regionsdir not found"

readvar RegionName
grep -l "\[$RegionName\]" $regionload_regionsdir/*.ini 2>/dev/null && end 1 "Region $RegionName already set"

RegionIni=$(echo $RegionName.ini | sed "s/[^a-zA-Z0-9\._-]//g")
RegionIniPath="$regionload_regionsdir/$RegionIni"
log region ini $RegionIniPath
# log "new region $RegionName in sim $SimName ($RegionIni)"
[ -f "$RegionIniPath" ] && cp "$RegionIniPath" $TMP.region.ini

InternalPort=$(nextfreeports)
readvar InternalPort
nextfreeports $InternalPort | grep -q "^$InternalPort" || end $? "Port $InternalPort already used by an OpenSim instance"
netstat -an | egrep ":$InternalPort[[:blank:]].*LISTEN" && end 1 "Port $InternalPort already in use"
[ $InternalPort -gt 9000 -a $InternalPort -lt 10000 ] \
|| yesno "Port $InternalPort is outside the usual 9000-9999 range, are you sure?" \
|| end $? Cancelled
# crudini --set $TMP.region.ini "$RegionName" RegionUUID $(uuidgen)
crudini --set $TMP.region.ini "$RegionName" InternalPort $InternalPort

echo "Last locations on this server"
ls -rt $DATA/*/regions/*.ini 2>/dev/null | while read file
do
grep -h Location $file | sed "s/.*= */  /"
done | tail -20
readvar Location
echo "$Location" | grep -q "^[0-9][0-9]*,[0-9][0-9]*$" || end $? "Invalid location, use format x,y (e.g. 8000,8000)"
crudini --set $TMP.region.ini "$RegionName" Location $Location

cat $TMP.region.ini
cp $TMP.region.ini $RegionIniPath || end $?

errormessage="could not complete the task, connect to $SimMachineName console and finish by hand"

osConsole $SimMachineName "create region \"$RegionName\" $RegionIni\n\n\n" || end $? something happened

osConsoleWait 10 $SimMachineName "Do you wish to join region $RegionName" \
&& osConsole $SimMachineName "\n" && osConsole $SimMachineName "\n" \
|| osConsoleWait 5 $SimMachineName "New estate name" && osConsole $SimMachineName "Mainland" \

osConsoleWait 1 $SimMachineName "Estate owner first name" && readvar EstateOwnerFirstName && osConsole $SimMachineName "$EstateOwnerFirstName"
osConsoleWait 1 $SimMachineName "Estate owner last name" && readvar EstateOwnerLastName && osConsole $SimMachineName "$EstateOwnerLastName"

osConsoleWait $instance "INITIALIZATION COMPLETE FOR $RegionName - LOGINS ENABLED" || end $? "Something occured"

# screen -x $SimMachineName -X stuff "create region \"$RegionName\" $RegionIni\n" || end $? something happened
end
