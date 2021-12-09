#!/bin/bash

firstport=9010
[ "$DEBUG" ] || DEBUG=no

BASEDIR=$(dirname $(dirname $(realpath "$0")))
. $BASEDIR/lib/os-helpers || exit 1

log "ETC $ETC"
log "OSBIN $OSBIN"
log "OSBINDIR $OSBINDIR"

[ "$2" ] && gridnick=$1 && shift
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
[ ! "$gridnick" ] && gridnick="$defaultGrid"
readvar gridnick
RobustConfig=$(grep "^$gridnick[[:blank:]]" $TMP.grids | cut -d " " -f 2)
if [ "$RobustConfig" ]
then
  log robust config $RobustConfig
  grep -q "\[GridInfoService\]" $RobustConfig
  if [ $? -eq 0 ]
  then
    log "robust type ini file"
    crudget $RobustConfig Const
    crudget $RobustConfig Launch
    # BinDir=$bindir
    crudget $RobustConfig GridInfoService
  else
    log "simulator type ini file"
    crudget $RobustConfig Const
    # gridname=$gridname
    curl -s $baseurl:$publicport/get_grid_info > $TMP.gridinfo
    which xmlstarlet >/dev/null \
     && gridnick=$(xmlstarlet sel -t -m /gridinfo -v gridnick -nl < $TMP.gridinfo)
  fi
  gridslug=$(echo "$gridnick" | tr [:upper:] [:lower:])
else
  end 1 "No robust config found, manual setup not implemented"
fi

firstport=$(( ( $PrivatePort / 10 + 1 ) * 10 ))

log grid name $gridname
log grid nick $gridnick
log gridslug $gridslug

readvar SimName

[ ! "$SimName" ] && end 1
simslug=$(echo "$SimName" | tr [:upper:] [:lower:] | sed "s/[^[:alnum:]]//g")
log simslug $simslug

log "list of core opensim directories"
# find "$CORE" -name OpenSim.exe | xargs dirname | grep "bin$" > $TMP.list
ls $BASEDIR/lib/opensim*/bin/OpenSim.exe \
$BASEDIR/core/opensim*/bin/OpenSim.exe \
$OSBINDIR/bin/OpenSim.exe | sed "s:/OpenSim.exe$::" | sort > $TMP.list

grep -v "/opensim-[0-9\.]*/" $TMP.list > $TMP.osdirs
grep "/opensim-[0-9\.]*/" $TMP.list >> $TMP.osdirs
defaultOSDir=$(tail -1 $TMP.osdirs)
log default OSDIR $defaultOSDir
echo "Found OpenSimulator distributions:"
cat $TMP.osdirs | sed "s:^:  :"
[ ! "$BinDir" ] && BinDir=$defaultOSDir

readvar BinDir
[ -d "$BinDir" ] || end $? "$BinDir directory does not exist"
[ -f "$BinDir/OpenSim.exe" ] || end $? "$BinDir not an OpenSim bin directory"

[ -f "$EtcDirectory/OpenSim.ini" ] \
&& log "Using $EtcDirectory/OpenSim.ini" \
|| end 1 "Configuration not found, use newgrid.sh to build $EtcDirectory/"

simConfigDir="$EtcDirectory/sims/$SimName"
simConfig="$EtcDirectory/sims/$SimName.ini"
ls $simConfig 2>/dev/null \
&& {
  log 1 "There is already a config for $SimName"
  if yesno "Proceed and replace the config?"
  then
    log "using $simConfig"
    cleanupIni $simConfig > $TMP.ini || end $?
    # crudmerge $TMP.ini $simConfig || end $?
  else
    end 1 "current $SimName config left untouched"
  fi
}
crudini --set $TMP.ini Launch SimName "$SimName"
crudini --set $TMP.ini Launch BinDir "$BinDir"
crudini --set $TMP.ini Launch Executable '"OpenSim.exe"'
crudini --set $TMP.ini Launch LogConfig "$simConfigDir/log.config"

echo CacheDirectory $CacheDirectory

crudget $TMP.ini Network
[ "$http_listener_port" ] || http_listener_port=$(nextfreeports $firstport)
readvar http_listener_port
crudini --set $TMP.ini Network http_listener_port "$http_listener_port"

log "getting db settings"
# inigrep Include_ $TMP.ini

crudget $TMP.ini DatabaseService 2>/dev/null || end $?
if [ "$ConnectionString" ]
then
  db_name=$(echo "$ConnectionString;" | sed "s/.*Database=//" | cut -d ';' -f 1)
  [ ! "$db_name" -o "$db_name" = "opensim" ] && db_name="${gridslug}_${simslug}"
else
  crudget $RobustConfig DatabaseService || end $? get DatabaseService failed
  db_name="${gridslug}_${simslug}"
fi
db_host=$(echo "$ConnectionString;" | sed "s/.*Data Source=//" | cut -d ';' -f 1)
[ ! "$db_host" ] && db_host=localhost
db_user=$(echo "$ConnectionString;" | sed "s/.*User ID=//" | cut -d ';' -f 1)
[ ! "$db_user" ] && db_user=opensim
db_pass=$(echo "$ConnectionString;" | sed "s/.*Password=//" | cut -d ';' -f 1)
[ "$db_pass" = "****" ] && db_pass=
[ ! "$db_pass" ] && db_pass=$(randomPassword) && echo "Generated random password $db_pass"
readvar db_host db_name db_user db_pass

testDatabaseConnection $db_host $db_name $db_user "$db_pass" \
|| end $?

ConnectionString="Data Source=$db_host;Database=$db_name;User ID=$db_user;Password=$db_pass;Old Guids=true;"
crudini --set $TMP.ini DatabaseService StorageProvider "OpenSim.Data.MySQL.dll"
crudini --set $TMP.ini DatabaseService ConnectionString "\"$ConnectionString\""

crudini --set $TMP.ini Includes Include "$EtcDirectory/OpenSim.ini"

[ -f "$EtcDirectory/Vivox.ini" ] \
&& crudini --set $TMP.ini Includes Include-Voice "$EtcDirectory/Vivox.ini" \

# crudini --del $TMP.ini Architecture
# crudini --set $TMP.ini Architecture Include_Architecture '"${Const|BinDirectory}/config-include/GridHypergrid.ini"'

for folder in \
  "$CacheDirectory/$SimName" \
  "$DataDirectory/$SimName" \
  "$simConfigDir" \
  "$simConfigDir/regions"
do
  [ -e "$folder" ] && continue
  mkdir "$folder" && log created folder $folder || end $? couild not create $folder
done

cleanupIni4Prod $TMP.ini \
&& cp $TMP.ini "$simConfig" \
&& ln -frs "$simConfig" "$ETC/opensim.d/$simslug.ini"

end $?
