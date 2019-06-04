#!/bin/bash

firstport=9010
DEBUG=yes

BASEDIR=$(dirname $(dirname $(realpath "$0")))
. $BASEDIR/lib/os-helpers || exit 1

[ "$1" ] && SimName=$1 && shift
readvar SimName
[ ! "$SimName" ] && end 1
MachineName=$(echo "$SimName" | tr [:upper:] [:lower:] | sed "s/ //g")
log MachineName $MachineName

log "ETC $ETC"
log "OSBIN $OSBIN"
log "OSBINDIR $OSBINDIR"
echo "# Available ROBUST servers"
ls $ETC/robust.d/*.ini 2>/dev/null

log "Local Robust Config"
RobustConfig=$(ls $ETC/robust.d/*.ini 2>/dev/null | head -1)
readvar RobustConfig
crudget $RobustConfig Launch
BinDir=$bindir
crudget $RobustConfig GridInfoService
GridName=$gridname
GridNick=$gridnick

readvar BinDir
[ -d "$BinDir" ] || end $? "$BinDir directory does not exist"
[ -f "$BinDir/OpenSim.exe" ] || end $? "$BinDir not an OpenSim bin directory"

if [ -f "$ETC/$GridNick.OpenSim.ini" ]
then
  log "Using $ETC/$GridNick.OpenSim.ini"
  crudget $RobustConfig Launch
  BinDir=$bindir
  GridNick=$gridnick
else
  log "Building OpenSim.ini for $GridNick grid"

  for section in Const Startup Network DatabaseService Modules Includes
  do
    echo "[$section]" >> $TMP.OpenSim.ini
    echo >> $TMP.OpenSim.ini
  done

  for ini in $BinDir/OpenSim.ini.example $BinDir/OpenSim.ini $ETC/OpenSim.ini $BASEDIR/install/OpenSim.Tweaks.ini
  do
    # [ ! -f "$ini" ] && log "skipping $ini, not found" && continue
    crudmerge $TMP.OpenSim.ini $ini || end $?
    OpenSimIniFound=true
  done
  [ "$OpenSimIniFound" ] || end 1 "Could not find an OpenSim.ini base"
  crudget $RobustConfig Const
  crudini --set $TMP.OpenSim.ini Const BinDirectory "$BinDir"
  crudini --set $TMP.OpenSim.ini Const GridName "$gridname"
  crudini --set $TMP.OpenSim.ini Const BaseHostname "$(basename $baseurl)"
  crudini --set $TMP.OpenSim.ini Const BaseURL "$baseurl"
  crudini --set $TMP.OpenSim.ini Const PublicPort $publicport
  crudini --set $TMP.OpenSim.ini Const PrivatePort $privateport
  crudini --set $TMP.OpenSim.ini Const CacheDirectory "$CACHE/$MachineName"
  crudini --set $TMP.OpenSim.ini Const DataDirectory "$DATA/$MachineName"
  crudini --set $TMP.OpenSim.ini Const LogsDirectory "$LOGS"

  crudini --set $TMP.OpenSim.ini Includes Include-Common "$ETC/config-include/GridCommon.ini"

  [ -f "$ETC/$GridNick.Gloebit.ini" ] \
  && crudini --set $TMP.OpenSim.ini Startup EconomyModule Gloebit

  [ -f "$ETC/osslEnable.ini" ] \
  || [ -f "$ETC/$GridNick.osslEnable.ini" ] \
  && crudini --set $TMP.OpenSim.ini Includes IncludeDASHosslEnable = "$ETC/osslEnable.ini"

  # We remove some confussing values that are defaults anyway
  crudini --del $TMP.OpenSim.ini ClientStack.LindenCaps Cap_GetTexture
  crudini --del $TMP.OpenSim.ini ClientStack.LindenCaps Cap_GetMesh
  crudini --del $TMP.OpenSim.ini ClientStack.LindenCaps Cap_AvatarPickerSearch
  crudini --del $TMP.OpenSim.ini ClientStack.LindenCaps Cap_GetDisplayNames

  log "Saving as $ETC/$GridNick.OpenSim.ini"
  cleanupIni4Prod $TMP.OpenSim.ini
  cp "$TMP.OpenSim.ini" "$ETC/$GridNick.OpenSim.ini"
fi

if [ -f "$ETC/config-include/GridCommon.ini" ]
then
  log "Using $ETC/config-include/GridCommon.ini"
else
  log "Building config-include/GridCommon.ini"
  for section in Const Startup Network DatabaseService Modules Includes
  do
    echo "[$section]" >> $TMP.GridCommon.ini
    echo >> $TMP.GridCommon.ini
  done
  [ -f "$BinDir/config-include/GridCommon.ini.example" ] || end $?

  for ini in $BinDir/config-include/GridCommon.ini.example $BASEDIR/install/GridCommon.Tweaks.ini
  do
    # [ ! -f "$ini" ] && log "skipping $ini, not found" && continue
    crudmerge $TMP.GridCommon.ini $ini || end $?
  done
  crudini --del $TMP.GridCommon.ini DatabaseService IncludeDASHStorage

  cleanupIni4Prod $TMP.GridCommon.ini \
  && mkdir -p "$ETC/config-include/" \
  && cp $TMP.GridCommon.ini "$ETC/config-include/GridCommon.ini" \
  && log "saved as $ETC/config-include/GridCommon.ini" \
  || end $?
fi

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
crudini --set $TMP.ini Launch SimName "$SimName"
crudini --set $TMP.ini Launch BinDir "$BinDir"
crudini --set $TMP.ini Launch Executable '"OpenSim.exe"'
crudini --set $TMP.ini Launch LogConfig "$DATA/$MachineName/$MachineName.logconfig"

crudget $TMP.ini Network
[ "$http_listener_port" ] || http_listener_port=$(nextfreeports $firstport)
readvar http_listener_port
crudini --set $TMP.ini Network http_listener_port "$http_listener_port"

log "getting db settings"
# inigrep Include_ $TMP.ini
GridMachineName=$(echo "$GridNick" | tr [:upper:] [:lower:])

crudget $TMP.ini DatabaseService || end $?
if [ "$connectionstring" ]
then
  DatabaseName=$(echo "$connectionstring;" | sed "s/.*Database=//" | cut -d ';' -f 1)
  [ ! "$DatabaseName" -o "$DatabaseName" = "opensim" ] && DatabaseName="os_$GridMachineName_$MachineName"
else
  crudget $RobustConfig DatabaseService || end $? get DatabaseService failed
  DatabaseName="os_$GridMachineName_$MachineName"
fi
DatabaseHost=$(echo "$connectionstring;" | sed "s/.*Data Source=//" | cut -d ';' -f 1)
[ ! "$DatabaseHost" ] && DatabaseHost=localhost
DatabaseUser=$(echo "$connectionstring;" | sed "s/.*User ID=//" | cut -d ';' -f 1)
[ ! "$DatabaseUser" ] && DatabaseUser=opensim
DatabasePassword=$(echo "$connectionstring;" | sed "s/.*Password=//" | cut -d ';' -f 1)
[ "$DatabasePassword" = "****" ] && DatabasePassword=
readvar DatabaseHost DatabaseName DatabaseUser DatabasePassword
[ ! "$DatabasePassword" ] && DatabasePassword=$(randomPassword) && echo "Random password generated $DatabasePassword"

testDatabaseConnection $DatabaseHost $DatabaseName $DatabaseUser "$DatabasePassword" \
|| end $?

ConnectionString="Data Source=$DatabaseHost;Database=$DatabaseName;User ID=$DatabaseUser;Password=$DatabasePassword;Old Guids=true;"
crudini --set $TMP.ini DatabaseService StorageProvider "OpenSim.Data.MySQL.dll"
crudini --set $TMP.ini DatabaseService ConnectionString "\"$ConnectionString\""

crudini --del $TMP.ini Architecture Include-Architecture
# crudini --set $TMP.ini Architecture IncludeDASHArchitecture '"${Const|BinDirectory}/config-include/GridHypergrid.ini"'
crudini --set $TMP.ini Startup ConfigDirectory "$ETC"

crudini --set $TMP.ini Includes IncludeDASHCommon "$ETC/$GridNick.OpenSim.ini"

[ -f "$ETC/$GridNick.Vivox.ini" ] \
&& crudini --set $TMP.ini Includes Include-Voice "$ETC/$GridNick.Vivox.ini" \
|| [ -f "$ETC/Vivox.ini" ] \
&& crudini --set $TMP.ini Includes Include-Voice "$ETC/Vivox.ini" \

# crudini --set $TMP.ini  Includes Include-osslEnable "/etc/opensim/osslEnable.ini"


# crudget $TMP.ini Const
# [ "$baseurl" ] || crudini --set $TMP.ini Const BaseURL "$BaseURL"
# [ "$publicport" ] || crudini --set $TMP.ini Const PublicPort "$PublicPort"
# [ "$privateport" ] || crudini --set $TMP.ini Const PrivatePort "$PrivatePort"
# [ "$gridname" ] || crudini --set $TMP.ini Const GridName "$GridName"
#
# [ "$bindirectory" ] || crudini --set $TMP.ini Const BinDirectory "\${Launch|BinDir}"
#
# crudget $TMP.ini Startup
# [ "$consoleprompt" ] || crudini --set $TMP.ini Startup ConsolePrompt "\${Launch|SimName} (\R) "
# [ "$regionload_regionsdir" ] || crudini --set $TMP.ini Startup regionload_regionsdir '"${Const|DataDirectory}/${Launch|SimName}/regions"'
# [ "$economymodule" ] || crudini --set $TMP.ini Startup EconomyModule "Gloebit"
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

cleanupIni4Prod $TMP.ini \
&& cp $TMP.ini $ETC/opensim.d/$MachineName.ini

for folder in $CACHE/$MachineName $DATA/$MachineName $LOGS
do
  [ -e "$folder" ] && continue
  mkdir -p "$folder" && log created folder $folder || end $? couild not create $folder
done
