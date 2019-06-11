#!/bin/bash

firstport=9010
DEBUG=no

BASEDIR=$(dirname $(dirname $(realpath "$0")))
. $BASEDIR/lib/os-helpers || exit 1

log "ETC $ETC"
log "OSBIN $OSBIN"
log "OSBINDIR $OSBINDIR"

[ "$2" ] && GridNick=$1 && shift
[ "$1" ] && SimName=$1 && shift

ls $ETC/robust.d/*.ini 2>/dev/null | while read ini
do
  echo "$(basename $ini .ini) $ini" >> $TMP.grids
done
ls $ETC/*.OpenSim.ini 2>/dev/null  | while read ini
do
  nick=$(basename $ini .OpenSim.ini)
  grep -q "^$nick[[:blank:]]" $TMP.grids 2>/dev/null && continue
  echo "$nick $ini" >> $TMP.grids
done
[ -f $TMP.grids ] && defaultGrid=$(head -1 $TMP.grids | cut -d " " -f 1)
[ -f $TMP.grids ] && echo "Known grids:"
cat $TMP.grids | sed "s:^:  :"
log default grid $defaultGrid

[ ! "$GridNick" ] && GridNick="$defaultGrid"
readvar GridNick
RobustConfig=$(grep "^$GridNick[[:blank:]]" $TMP.grids | cut -d " " -f 2)
if [ "$RobustConfig" ]
then
  log robust config $RobustConfig
  grep -q "\[GridInfoService\]" $RobustConfig
  if [ $? -eq 0 ]
  then
    log "robust type ini file"
    crudget $RobustConfig Launch
    BinDir=$bindir
    crudget $RobustConfig GridInfoService
    GridName=$gridname
    GridNick=$gridnick
  else
    log "simulator type ini file"
    crudget $RobustConfig Const
    GridName=$gridname
    curl -s $baseurl:$publicport/get_grid_info > $TMP.gridinfo
    which xmlstarlet >/dev/null \
     && GridNick=$(xmlstarlet sel -t -m /gridinfo -v gridnick -nl < $TMP.gridinfo)
  fi
  GridMachineName=$(echo "$GridNick" | tr [:upper:] [:lower:])
else
  end 1 "No robust config found, manual setup not implemented"
fi
log grid name $GridName
log grid nick $GridNick
log GridMachineName $GridMachineName

readvar SimName
[ ! "$SimName" ] && end 1
MachineName=$(echo "$SimName" | tr [:upper:] [:lower:] | sed "s/ //g")
log MachineName $MachineName

log "list of core opensim directories"
find "$CORE" -name OpenSim.exe | xargs dirname | grep "bin$" > $TMP.list
grep -v "/opensim-[0-9\.]*/" $TMP.list > $TMP.osdirs
grep "/opensim-[0-9\.]*/" $TMP.list >> $TMP.osdirs
defaultOSDir=$(tail -1 $TMP.osdirs)
log default OSDIR $defaultOSDir
echo "Known OpenSimulator distributions"
cat $TMP.osdirs | sed "s:^:  :"
[ ! "$BinDir" ] && BinDir=$defaultOSDir

readvar BinDir
[ -d "$BinDir" ] || end $? "$BinDir directory does not exist"
[ -f "$BinDir/OpenSim.exe" ] || end $? "$BinDir not an OpenSim bin directory"

if [ -f "$ETC/$GridNick.OpenSim.ini" ]
then
  log "Using $ETC/$GridNick.OpenSim.ini but we already know what we need"
  # crudget $RobustConfig Const
  # BinDir=$bindirectory
  # GridName=$GridName
  # GridMachineName=$(echo "$GridNick" | tr [:upper:] [:lower:])
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
  crudini --set $TMP.OpenSim.ini Const CacheDirectory "$CACHE/$GridNick"
  crudini --set $TMP.OpenSim.ini Const DataDirectory "$DATA/\${Launch|SimName}"
  crudini --set $TMP.OpenSim.ini Const LogsDirectory "$LOGS"

  crudini --set $TMP.OpenSim.ini Includes Include-Common "$ETC/config-include/GridCommon.ini"

  [ -f "$ETC/$GridNick.Gloebit.ini" ] \
  && crudini --set $TMP.OpenSim.ini Startup EconomyModule Gloebit

  [ -f "$ETC/osslEnable.ini" ] \
  || [ -f "$ETC/$GridNick.osslEnable.ini" ] \
  && crudini --set $TMP.OpenSim.ini Includes Include_osslEnable = "$ETC/osslEnable.ini"

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
  crudini --del $TMP.GridCommon.ini DatabaseService Include_Storage

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

crudget $TMP.ini DatabaseService || end $?
if [ "$connectionstring" ]
then
  DatabaseName=$(echo "$connectionstring;" | sed "s/.*Database=//" | cut -d ';' -f 1)
  [ ! "$DatabaseName" -o "$DatabaseName" = "opensim" ] && DatabaseName="os_${GridMachineName}_${MachineName}"
else
  crudget $RobustConfig DatabaseService || end $? get DatabaseService failed
  DatabaseName="os_${GridMachineName}_${MachineName}"
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

crudini --set $TMP.ini Startup ConfigDirectory "$ETC"

crudini --set $TMP.ini Includes Include_Common "$ETC/$GridNick.OpenSim.ini"

[ -f "$ETC/$GridNick.Vivox.ini" ] \
&& crudini --set $TMP.ini Includes Include-Voice "$ETC/$GridNick.Vivox.ini" \
|| [ -f "$ETC/Vivox.ini" ] \
&& crudini --set $TMP.ini Includes Include-Voice "$ETC/Vivox.ini" \


crudini --del $TMP.ini Architecture
crudini --set $TMP.ini Architecture Include_Architecture '"${Const|BinDirectory}/config-include/GridHypergrid.ini"'

cleanupIni4Prod $TMP.ini \
&& cp $TMP.ini $ETC/opensim.d/$MachineName.ini

for folder in $CACHE/$GridNick $DATA/$MachineName $LOGS
do
  [ -e "$folder" ] && continue
  mkdir -p "$folder" && log created folder $folder || end $? couild not create $folder
done
