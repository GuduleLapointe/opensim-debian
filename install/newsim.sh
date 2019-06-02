#!/bin/bash

firstport=9010
DEBUG=yes

BASEDIR=$(dirname $(dirname $(realpath "$0")))
. $BASEDIR/lib/os-helpers || exit 1

echo "ETC $ETC"
echo "OSBIN $OSBIN"
echo "OSBINDIR $OSBINDIR"

echo "# Available ROBUST servers"
ls $ETC/robust.d/*.ini 2>/dev/null

crudget() {
  [ "$2" ] || return $?
  eval $(crudini --get --format=sh $1 $2  | sed -e "s/'\"/'/" -e "s/\"'/'/")
}
crudmerge() {
  [ -f "$2" ] || continue
  log merging $2 in $1
  cleanupIni $2 | sed "s/^Include-/IncludeDASH/" > $TMP.merge.ini
  crudini --merge $1 <$TMP.merge.ini || end $? $2 Merge failed
  # sed -i "s/^Include-/IncludeDASH/" $TMP.ini
}

RobustConfig=$(ls $ETC/robust.d/*.ini 2>/dev/null | head -1)
readvar RobustConfig
crudget $RobustConfig Launch
BinDir=$bindir

# crudget $TMP.ini Launch
# [ ! "$BinDir" ] && BinDir=$bindir
# [ ! "$BinDir" ] && BinDir=$OSBINDIR
readvar BinDir
[ -d "$BinDir" ] || end $? "$BinDir directory does not exist"
[ -f "$BinDir/OpenSim.exe" ] || end $? "$BinDir not an OpenSim bin directory"

for section in Launch Const Network DatabaseService Modules Includes
do
  echo "[$section]" >> $TMP.ini
  echo >> $TMP.ini
done

# [ -f $BinDir/config-include/GridCommon.ini.example ] || end $? Missing $BinDir/config-include/GridCommon.ini.example
# crudmerge $TMP.ini $ETC/GridCommon.ini || end $?

[ "$1" ] && SimName=$1 && shift
readvar SimName
[ ! "$SimName" ] && end 1
MachineName=$(echo "$SimName" | tr [:upper:] [:lower:] | sed "s/ //g")
log MachineName $MachineName

for ini in $BinDir/OpenSim.ini.example $BinDir/OpenSim.ini.example $ETC/OpenSim.ini $Bin
do
  [ ! -f "$ini" ] && log "skipping $ini, not found" && continue
  crudmerge $TMP.ini $ini || end $?
  OpenSimIniFound=true
done
[ "$OpenSimIniFound" ] || end 1 "Could not find an OpenSim.ini base"

ls $ETC/*.d/$MachineName.ini 2>/dev/null \
|| ls $ETC/*.d/$SimName.ini 2>/dev/null \
&& {
  log 1 "There is already a config for $SimName"
  if yesno "Proceed and replace the config?"
  then
    log "merging $ETC/opensim.d/$MachineName.ini"
    crudmerge $TMP.ini $ETC/opensim.d/$MachineName.ini || end $?
  else
    end 1 "current $SimName config left untouched"
  fi
}
crudini --set $TMP.ini Launch SimName "\"$SimName\""
crudini --set $TMP.ini Launch BinDir "\"$BinDir\""
crudini --set $TMP.ini Launch Executable '"OpenSim.exe"'
crudini --set $TMP.ini Launch LogConfig "$DATA/$MachineName/$MachineName.logconfig"

crudget $RobustConfig Const
crudini --set $TMP.ini Const BaseHostname "\"$(echo $baseurl | cut -d/ -f 3)\""
crudini --set $TMP.ini Const BaseURL "\"$baseurl\""
crudini --set $TMP.ini Const PublicPort $publicport
crudini --set $TMP.ini Const PrivatePort $privateport

crudget $RobustConfig GridInfoService
GridName="$gridname"
crudini --set $TMP.ini Const GridName "\"$gridname\""

crudini --set $TMP.ini Const BinDirectory "\"$BinDir\""
crudini --set $TMP.ini Const CacheDirectory "\"$CACHE/$MachineName\""
crudini --set $TMP.ini Const DataDirectory "\"$DATA/$MachineName\""
crudini --set $TMP.ini Const LogsDirectory "\"$LOGS\""

crudget $TMP.ini Network
[ "$http_listener_port" ] || http_listener_port=$(nextfreeports 9010)
readvar http_listener_port
crudini --set $TMP.ini Network http_listener_port "$http_listener_port"
[ "$externalhostnamegorlsl" ] || crudini --set $TMP.ini Network ExternalHostNameForLSL "\"$(hostname -f)\""

# crudini --set $TMP.ini Startup ConsolePrompt "\"\${Launch|SimName} (\R) \""

log "getting db settings"
# inigrep Include_ $TMP.ini
crudget $TMP.ini DatabaseService || end $?
if [ "$connectionstring" ]
then
  DatabaseName=$(echo "$connectionstring;" | sed "s/.*Database=//" | cut -d ';' -f 1)
  [ ! "$DatabaseName" -o "$DatabaseName" = "opensim" ] && DatabaseName="os_$MachineName"
else
  crudget $RobustConfig DatabaseService || end $? get DatabaseService failed
  DatabaseName="os_$MachineName"
fi
DatabaseHost=$(echo "$connectionstring;" | sed "s/.*Data Source=//" | cut -d ';' -f 1)
[ ! "$DatabaseHost" ] && DatabaseHost=localhost
DatabaseUser=$(echo "$connectionstring;" | sed "s/.*User ID=//" | cut -d ';' -f 1)
[ ! "$DatabaseUser" ] && DatabaseUser=opensim
DatabasePassword=$(echo "$connectionstring;" | sed "s/.*Password=//" | cut -d ';' -f 1)
[ "$DatabasePassword" = "****" ] && DatabasePassword=
readvar DatabaseHost DatabaseName DatabaseUser DatabasePassword
[ ! "$DatabasePassword" ] && DatabasePassword=$(randomPassword) && echo "Random password generated $DatabasePassword"
ConnectionString="Data Source=$DatabaseHost;Database=$DatabaseName;User ID=$DatabaseUser;Password=$DatabasePassword;Old Guids=true;"
# echo "ConnectionString $ConnectionString"

log "Testing database connection"
echo "" | mysql -h$DatabaseHost -u$DatabaseUser -p$DatabasePassword $DatabaseName
if [ $? -ne 0 ]
then
  echo "" | mysql -h$DatabaseHost -u$DatabaseUser -p$DatabasePassword
  if [ $? -ne 0 ]
  then
    echo "user not ok either, add it manually before trying to launch the sim"
  else
    if yesno "Create database $DatabaseName and grant rights to $DatabaseUser?"
    then
      echo "CREATE DATABASE $DatabaseName; GRANT ALL ON $DatabaseName.* TO $DatabaseUser;" | sudo mysql \
      && log "Database created, checking access again"
      echo "" | mysql -h$DatabaseHost -u$DatabaseUser -p$DatabasePassword $DatabaseName \
      && log "Database OK" || log $? "Could not create db $DatabaseName or grant privileges to $DatabaseUser, check before launching"
    fi
  fi
fi
echo Connection OK


crudini --del $TMP.ini DatabaseService IncludeDASHStorage
crudini --set $TMP.ini DatabaseService StorageProvider "\"OpenSim.Data.MySQL.dll\""
crudini --set $TMP.ini DatabaseService ConnectionString "\"$ConnectionString\""

crudini --del $TMP.ini Architecture Include-Architecture
crudini --set $TMP.ini Architecture IncludeDASHArchitecture '"${Const|BinDirectory}/config-include/GridHypergrid.ini"'
crudini --set $TMP.ini Includes Include-Common "\"$ETC/GridCommon.ini\""

# crudget $TMP.ini Const
# [ "$baseurl" ] || crudini --set $TMP.ini Const BaseURL "\"$BaseURL\""
# [ "$publicport" ] || crudini --set $TMP.ini Const PublicPort "$PublicPort"
# [ "$privateport" ] || crudini --set $TMP.ini Const PrivatePort "$PrivatePort"
# [ "$gridname" ] || crudini --set $TMP.ini Const GridName "$GridName"
#
# [ "$bindirectory" ] || crudini --set $TMP.ini Const BinDirectory "\"\${Launch|BinDir}\""
#
# crudget $TMP.ini Startup
# [ "$consoleprompt" ] || crudini --set $TMP.ini Startup ConsolePrompt "\"\${Launch|SimName} (\R) \""
# [ "$configdirectory" ] || crudini --set $TMP.ini Startup ConfigDirectory '"${Const|DataDirectory}/${Launch|SimName}"'
# [ "$regionload_regionsdir" ] || crudini --set $TMP.ini Startup regionload_regionsdir '"${Const|DataDirectory}/${Launch|SimName}/regions"'
# [ "$economymodule" ] || crudini --set $TMP.ini Startup EconomyModule "Gloebot"
# [ "$drawprimonmaptile" ] || crudini --set $TMP.ini Startup DrawPrimOnMapTile "true"
#
# [ "$physical_prim" ] || crudini --set $TMP.ini Startup physical_prim "true"
# [ "$meshing" ] || crudini --set $TMP.ini Startup meshing "Meshmerizer"
# [ "$physics" ] || crudini --set $TMP.ini Startup physics "BulletSim"
# [ "$storage_prim_inventories" ] || crudini --set $TMP.ini Startup storage_prim_inventories "true"
# [ "$cachesculptmaps" ] || crudini --set $TMP.ini Startup CacheSculptMaps "false"
#

#
# crudget $TMP.ini NPC
# [ "$enabled" ] || crudini --set $TMP.ini NPC Enabled "true"

#
#
# log http_listener_port $http_listener_port

sed -i "s/IncludeDASH/Include-/" $TMP.ini
crudini --get $TMP.ini > $TMP.sections
cat $TMP.sections | while read section
do
  crudini --get $TMP.ini $section | grep -qi [a-z] || crudini --del $TMP.ini "$section"
done

cp $TMP.ini $ETC/opensim.d/$MachineName.ini

for folder in $CACHE/$MachineName $DATA/$MachineName $LOGS
do
  [ -e "$folder" ] && continue
  mkdir -p "$folder" && log created folder $folder || end $? couild not create $folder
done
