#!/bin/bash

export PATH=$PATH:$(dirname $(dirname "$0"))/bin

BASEDIR=$(dirname $(dirname $(realpath "$0")))
. $BASEDIR/lib/os-helpers || exit 1
[ "$OSBIN" ] || exit 1
[ "$ETC" ] || exit 1

PGM=$(basename "$0")
TMP=/tmp/$PGM.$$
echo
[ "$1" ] && gridname="$@"
readvar gridname && [ "${gridname}" != "" ] || end $? "gridname cannot be empty"

gridnick=$(echo $gridname | tr "[:upper:]" "[:lower:]" | sed -r -e 's/(\W)/\L\1/g' -e 's/[^[:alnum:] _-]//g' -e 's/(^|[ _-])(\w)/\U\2/g')
readvar gridnick && [ "${gridnick}" != "" ] || end $? "gridnick cannot be empty"
gridslug=$(echo $gridnick | tr [:upper:] [:lower:])

GridDir=$ETC/grids/${gridnick}
readvar GridDir && [ "${GridDir}" != "" ] || end $? "GridDir cannot be empty"
[ -d "$GridDir" ] || mkdir "$GridDir" || end $?

RobustOutput=$GridDir/Robust.HG.ini
readvar RobustOutput && [ "${RobustOutput}" != "" ] || end $? "RobustOutput cannot be empty"

if [ -f "$RobustOutput" ]
then
  cp $RobustOutput ${RobustOutput}~
  cleanupIni $RobustOutput > $TMP.clean.ini && mv $TMP.clean.ini $RobustOutput
  echo "Grid config file exists, updating"
  update=yes
  crudget $RobustOutput Launch
  crudget $RobustOutput Const
  crudget $RobustOutput Startup
  # [ "$PublicPort" ] || PublicPort=$(crudini  --get $RobustOutput Const PublicPort)
  # [ "$PrivatePort" ] || PrivatePort=$(crudini  --get $RobustOutput Const PrivatePort)
  # cat $TMP.get
  echo PublicPort $PublicPort
  # crudget $RobustOutput Startup
  savedPublicPort=$PublicPort
  savedPrivatePort=$PrivatePort
fi

BinDir=${BinDir:-$(ls -drt /opt/opensim/core/opensim-[0-9]*/bin | sort | tail -1)}
readvar BinDir && [ "${BinDir}" != "" ] || end $? "BinDir cannot be empty"
ls -d "$BinDir" >/dev/null || end $?

BaseHostname=${BaseHostname:-$(hostname -f)}
readvar BaseHostname && [ "${BaseHostname}" != "" ] || end $? "BaseHostname cannot be empty"
# readvar BaseHostname && [ "${BaseHostname}" != "" ] || end $? "BaseHostname cannot be empty"

(
find . $ETC/grids/*/sims/*/regions -name "*.ini" 2>/dev/null \
  | grep -v "#" \
  | xargs egrep "^[[:blank:];]*(InternalPort|PublicPort|PrivatePort|http_listener_port)[[:blank:]]*=" 2>/dev/null \
  | cut -d "=" -f 2 | sed "s/[^0-9]//g"
netstat -tulpn 2>/dev/null | grep LISTEN | sed -r -e "s/^[^:]*[0-9]*:+//" -e "s/[[:blank:]].*//"
) | sort -u | sort -n >> $TMP.inuse

PublicPort=${PublicPort:-$(nextfreeports 8002)}
readvar PublicPort && [ "${PublicPort}" != "" ] || end $? "PublicPort cannot be empty"
if [ "$update" != "yes" -o "$PublicPort" != "$savedPublicPort" ]
then
  grep -q "^$PublicPort$" $TMP.inuse && end 1 "Port $PublicPort is already in use, try $(nextfreeports $(($PublicPort + 1))) next time?"
fi
echo $PublicPort >> $TMP.inuse

PrivatePort=${PrivatePort:-$(nextfreeports $(($PublicPort + 1)))}
readvar PrivatePort && [ "${PrivatePort}" != "" ] || end $? "PrivatePort cannot be empty"
if [ "$update" != "yes" -o "$PublicPort" != "$savedPublicPort" ]
then
  grep -q "^$PrivatePort$" $TMP.inuse && end 1 "Port $PrivatePort is already in use, try $(nextfreeports $(($PrivatePort + 1))) next time?"
fi
echo $PrivatePort >> $TMP.inuse

WebURL="https://$BaseHostname"
readvar WebURL && [ "${WebURL}" != "" ] || end $? "WebURL cannot be empty"

Executable="Robust.exe"
LogConfig="/opt/opensim/var/data/$gridnick/Robust.exe.config"
LogFile="/opt/opensim/var/logs/$gridnick.log"

crudget $RobustOutput DatabaseService

echo "$ConnectionString" | tr " ;" "\n" | grep [a-z].*= > $TMP.db
. $TMP.db || end $?

[ "$Source" != "" ] && db_host=$Source || db_host="localhost"
db_name=$(echo ${gridnick}_robust | tr [:upper:] [:lower:])
[ "$ID"  != "" ] && db_user=$ID || db_user="opensim"
db_pass="$Password"

readvar db_host && [ "${db_host}" != "" ] || end $? "db_host cannot be empty"
readvar db_name && [ "${db_name}" != "" ] || end $? "db_name cannot be empty"
readvar db_user && [ "${db_user}" != "" ] || end $? "db_user cannot be empty"
readvar db_pass && [ "${db_pass}" != "" ] || end $? "db_pass cannot be empty"

testDatabaseConnection $db_host $db_name $db_user "$db_pass" \
|| end $? "Could not connect to database $db_name with given credentials"

printf "\n$PGM: Building config\n\n" >&2

cat <<EOF >$TMP.launch.ini
;; Generated by $0 $(date)
[Launch]
  ;; Parameters used by opensim-debian scripts.
  ;; You can safely delete Launch section if you use another launching process
  BinDir = "$BinDir"
  Executable = "Robust.exe"
  LogConfig = "$LogConfig"
  LogFile = "$LogFile"
  ConsolePrompt = "$gridname ($BaseHostname:$PublicPort)"
  ;; End of Launch section

EOF

EtcDirectory="/opt/opensim/etc/grids/$gridnick"
DataDirectory="/opt/opensim/var/data/$gridnick"
CacheDirectory="/opt/opensim/var/cache/$gridnick"
LogsDirectory="/opt/opensim/var/logs"
economyURL="\${Const|WebURL}/helpers"

cat <<EOF >$TMP.thisgrid.ini || end $? "could not create $TMP.thisgrid.ini"
[Const]
  BaseURL = "http://$BaseHostname"
  WebURL = "$WebURL"
  PublicPort = $PublicPort
  PrivatePort = $PrivatePort
  BinDirectory = "$BinDir"
  EtcDirectory = "$EtcDirectory"
  DataDirectory = "$DataDirectory"
  CacheDirectory = "$CacheDirectory"
  LogsDirectory = "$LogsDirectory"
[Startup]
  PIDFile = "\${Const|CacheDirectory}/$gridnick.pid"
  RegistryLocation = "\${Const|DataDirectory}/registry"
  ConfigDirectory = "\${Const|EtcDirectory}/robust-include"
  ConsoleHistoryFile = "\${Const|LogsDirectory}/$gridnick.RobustConsoleHistory.txt"
[Hypergrid]
  HomeURI = "\${Const|BaseURL}:\${Const|PublicPort}"
  GatekeeperURI = "\${Const|BaseURL}:\${Const|PublicPort}"
[DatabaseService]
  ConnectionString = "Data Source=$db_host;Database=$db_name;User ID=$db_user;Password=$db_pass;Old Guids=true;"
[AssetService]
  BaseDirectory = "\${Const|DataDirectory}/fsassets/data"
  SpoolDirectory = "\${Const|CacheDirectory}/fsassets/tmp"
  AssetLoaderArgs = "\${Const|EtcDirectory}/assets/AssetSets.xml"
[GridService]
  Region_Welcome = "DefaultRegion, DefaultHGRegion, FallbackRegion, Persistent"
  MapTileDirectory = "\${Const|CacheDirectory}/maptiles"
[LibraryService]
  LibraryName = "${gridname} Library"
  DefaultLibrary = "\${Const|EtcDirectory}/inventory/Libraries.xml"
[LoginService]
  DestinationGuide = "\${Const|WebURL}/guide"
[MapImageService]
  TilesStoragePath = "\${Const|DataDirectory}/maptiles"
[GridInfoService]
  gridname = "${gridname}"
  gridnick = "${gridnick}"
  welcome = "\${Const|WebURL}"
  ;welcome = "\${Const|WebURL}/splash"
  ;about = "\${Const|WebURL}/about"
  ;register = "\${Const|WebURL}/register"
  ;help = "\${Const|WebURL}/support"
  ;password = "\${Const|WebURL}/password"
  ;economy = "$economyURL"
  ;search = "\${Const|WebURL}/helpers/query.php"
  ;message = "\${Const|WebURL}/helpers/offline.php"
  ;GridStatus = "\${Const|WebURL}/GridStatus"
  ;GridStatusRSS = "\${Const|WebURL}/GridStatusRSS"
[BakedTextureService]
  BaseDirectory = "\${Const|CacheDirectory}/bakes"
EOF

cp $BinDir/Robust.HG.ini.example $TMP.Robust.ini \
&& sed -i "s/^[[:blank:]]*//" $TMP.thisgrid.ini  $TMP.Robust.ini \
&& crudini --merge $TMP.Robust.ini < $TMP.thisgrid.ini \
&& cat $TMP.launch.ini $TMP.Robust.ini > $TMP.ini \
|| exit $?

cleanupIni $TMP.ini > $TMP.Robust.ini
uncomment UserProfilesServiceConnector $TMP.Robust.ini
crudini --set $TMP.Robust.ini  UserProfilesService Enabled true

if [ -f "$RobustOutput" ]
then
  diff "$RobustOutput" $TMP.Robust.ini  &&
  echo "No change in $RobustOutput" && exit

  if yesno -y "Apply changes?"
  then
    mv $RobustOutput $RobustOutput~ \
    && cp $TMP.Robust.ini  $RobustOutput \
    && echo "$RobustOutput modified" \
    || end $? "apply changes failed"
  else
    echo "$RobustOutput left unchanged"
  fi
else
  cp $TMP.Robust.ini  $RobustOutput \
  && echo "$RobustOutput file created" \
  || end $? "create file $RobustOutput failed"
fi

for directory in \
  /opt/opensim/var/ \
  /opt/opensim/var/cache \
  /opt/opensim/var/data \
  /opt/opensim/var/run \
  /opt/opensim/var/run/crashes \
  $CacheDirectory \
  $CacheDirectory/bakes \
  $CacheDirectory/fsassetcache \
  $CacheDirectory/fsassets \
  $CacheDirectory/fsassets/tmp \
  $CacheDirectory/maptiles \
  $DataDirectory \
  $DataDirectory/fsassets \
  $DataDirectory/fsassets/data \
  $DataDirectory/maptiles \
  $DataDirectory/registry \
  $EtcDirectory \
  $EtcDirectory/assets \
  $EtcDirectory/config-include \
  $EtcDirectory/sims \
  $EtcDirectory/inventory \
  $EtcDirectory/robust-include \
  $LogsDirectory
do
 [ -e "$directory" ] && cd "$directory" && continue
 mkdir "$directory" && log "created directory $directory" || end $? "could not create $directory"
done

for file in \
  OpenSimDefaults.ini \
  OpenSim.ini \
  config-include/GridHypergrid.ini \
  config-include/GridCommon.ini \
  config-include/FlotsamCache.ini \
  config-include/osslDefaultEnable.ini \
  config-include/osslEnable.ini
  # config-include/StandaloneCommon.ini \
do
  [ -f "$EtcDirectory/$file" ] && continue
  [ -f "$BinDir/${file}.example" ] \
  && original=$BinDir/${file}.example \
  || original=$BinDir/${file}
  if echo "$file" | grep -q "\.ini$"
  then
    # cleanupIni $original > $TMP.merge  || end $? "error cleaning up $original"
    # crudmerge $EtcDirectory/OpenSim.ini $TMP.merge || end $? "error merging $original"
    # [ "$file" != "OpenSim.ini" ] && cleanupIni $original > $EtcDirectory/$file && continue || end $? "error copying up $original"
    cleanupIni $original > $EtcDirectory/$file && continue || end $? "error copying up $original"
  else
    cp $original $EtcDirectory/$file && continue || end $?
  fi
done

crudmerge $EtcDirectory/OpenSim.ini $EtcDirectory/config-include/GridHypergrid.ini || end $? "error merging $_"
crudmerge $EtcDirectory/OpenSim.ini $EtcDirectory/config-include/GridCommon.ini || end $? "error merging $_"

for folder in assets inventory
do
  [ ! -d "$EtcDirectory/$folder" ] || yesno -y "Update $folder folder from distro (keep local changes)?" || continue
  rsync -Waz --delete $BinDir/$folder/ $EtcDirectory/tmp.$$.$folder/ || end $?
  [ -d "$EtcDirectory/$folder/" ] && rsync -Waz $EtcDirectory/$folder/ $EtcDirectory/tmp.$$.$folder/
  rsync -Waz --delete $EtcDirectory/tmp.$$.$folder/ $EtcDirectory/$folder/ || end $?
  rm -rf $EtcDirectory/tmp.$$.$folder/
done

# uncomment ConsolePrompt $EtcDirectory/OpenSim.ini
# uncomment regionload_regionsdir $EtcDirectory/OpenSim.ini
# # uncomment regionload_webserver_url $EtcDirectory/OpenSim.ini
# uncomment RegistryLocation $EtcDirectory/OpenSim.ini
# uncomment ConsoleHistoryFile $EtcDirectory/OpenSim.ini
# uncomment crash_dir $EtcDirectory/OpenSim.ini
# uncomment PIDFile $EtcDirectory/OpenSim.ini
# # uncomment emailmodule $EtcDirectory/OpenSim.ini
# uncomment MapImageModule $EtcDirectory/OpenSim.ini
# uncomment DefaultEstateName $EtcDirectory/OpenSim.ini
# uncomment DisableFacelights $EtcDirectory/OpenSim.ini

cat << EOF | sed -e "s/^[[:blank:]]*//" > $TMP.OpenSim.tweaks
[Const]
  BinDirectory = "$BinDir"
  gridname = "$gridname"
  BaseHostname = "$BaseHostname"
  PublicPort = $PublicPort
  PrivatePort = $PrivatePort
  CacheDirectory = "$CacheDirectory/\${Launch|simslug}"
  DataDirectory = "$DataDirectory/\${Launch|simslug}"
  EtcDirectory = "$EtcDirectory"
  LogsDirectory = "$LogsDirectory"

[Startup]
  ConsolePromp = "\${Launch|SimName}@\${Const|gridname} (\R) "
  ConfigDirectory = "\${Const|EtcDirectory}/config-include"
  regionload_regionsdir = "\${Const|EtcDirectory}/sims/\${Launch|simslug}/regions"
  registryLocation = "\${Const|DataDirectory}/registry"
  ConsoleHistoryFile = "\${Const|LogsDirectory}/\${Launch|simslug}.OpenSimConsoleHistory.txt"
  crash_dir = "/opt/opensim/var/run/crashes"
  PIDFile = "/opt/opensim/var/run/\${Const|gridname}.\${Launch|simslug}.pid"
  MapImageModule = "Warp3DImageModule"

[Map]
  MapImageModule = "Warp3DImageModule"

[Permissions]
  simple_build_permissions = true

[Estates]
  DefaultEstateName = "$gridname Estate"

[SMTP]
  enabled = true
  host_domain_header_from = "$BaseHostname"
  SMTP_SERVER_HOSTNAME = "mail.$BaseHostname"
  SMTP_SERVER_PORT = 587
  SMTP_SERVER_LOGIN = "facteur@$BaseHostname"
  SMTP_SERVER_PASSWORD = "password"

[Network]
  ; ConsoleUser = "Test"
  ; ConsolePass = "secret"
  ; console_port = 0

[XMLRPC]
  ;XmlRpcRouterModule = "XmlRpcRouterModule"
  ;XmlRpcPort = 20800

[ClientStack.LindenUDP]
  DisableFacelights = true

[Messaging]
  OfflineMessageModule = "Offline Message Module V2"
  ; OfflineMessageURL = "\${Const|BaseURL}/Offline.php"
  OfflineMessageURL = "\${Const|PrivURL}:\${Const|PrivatePort}""
  StorageProvider = OpenSim.Data.MySQL.dll
  MuteListModule = MuteListModule

[RemoteAdmin]
  ; enabled = false
  ; port = 0
  ; access_password = ""
  ; enabled_methods = all

[DataSnapshot]
  index_sims = true
  ; data_exposure = minimum
  gridname = "$gridname"
  ; default_snapshot_period = 1200
  snapshot_cache_directory = "\$Const|DataDirectory/\${Launch|simslug}/DataSnapshot"
  ;; New way of specifying data services, one per service
  ;DATA_SRV_MISearch = "http://metaverseink.com/cgi-bin/register.py"
  ;DATA_SRV_MISearch = "\${Const|WebURL}/helpers/register"

[Economy]
  ; economymodule = BetaGridLikeMoneyModule
  ; economy = "$economyURL"
  ; PriceUpload = 0
  ; PriceGroupCreate = 0

[OSSL]
  Include-osslDefaultEnable = "$EtcDirectory/config-include/osslDefaultEnable.ini"

[Groups]
  Enabled = true
  Module = "Groups Module V2"
  ServicesConnectorModule = "Groups HG Service Connector"
  LocalService = remote
  GroupsServerURI = "\${Const|PrivURL}:\${Const|PrivatePort}"
  MessagingModule = "Groups Messaging Module V2"
  MessageOnlineUsersOnly = true
  NoticesEnabled = true

[NPC]
  AllowSenseAsAvatar = true

[Terrain]
  ; InitialTerrain = "pinhead-island"

[UserProfiles]
  ProfileServiceURL = "\${Const|BaseURL}:\${Const|PublicPort}"
  AllowUserProfileWebURLs = true

EOF

# sed -i "s/^[[:blank:]]*Include-Architecture/; Include-Architecture/" $EtcDirectory/OpenSim.ini
# sed -i "s/^;[[:blank:]]*\(Include-Architecture.*GridHypergrid\)/\\1/" $EtcDirectory/OpenSim.ini
# sed -i "s/\(\[Architecture\]\):; \\1:" $EtcDirectory/OpenSim.ini
# sed -i "s/^[^;]*\(\[Include\]\):; \\1:" $EtcDirectory/OpenSim.ini

# for var in $(grep "^[^;]*=" $TMP.OpenSim.tweaks | cut -d = -f 1)
# do
#   [ "$var" = "Enabled" ] && continue
#   [ "$var" = "enabled" ] && continue
#   [ "$var" = "Include-Architecture" ] && continue
#   uncomment $var $EtcDirectory/OpenSim.ini
# done

crudmerge $EtcDirectory/OpenSim.ini $TMP.OpenSim.tweaks

crudini --set $EtcDirectory/config-include/FlotsamCache.ini AssetCache CacheDirectory "\${Const|CacheDirectory}/assetcache"

sed -i "s/^[[:blank:]]*\(Include-Storage\)/; \\1/" $EtcDirectory/config-include/GridCommon.ini
sed -i "s/^;[[:blank:]]*\(StorageProvider.*MySQL\)/\\1/" $EtcDirectory/config-include/GridCommon.ini
crudini --set $EtcDirectory/config-include/GridCommon.ini DatabaseService StorageProvider "OpenSim.Data.MySQL.dll"
# sed -i "s/^;[[:blank:]]*\(ConnectionString.*Data Source=localhost;\)/\\1/" $EtcDirectory/config-include/GridCommon.ini
# crudini --set $EtcDirectory/config-include/GridCommon.ini DatabaseService ConnectionString "\"Data Source=$db_host;Database=${gridslug}_\${Launch|SimName};User ID=$db_user;Password=$db_pass;Old Guids=true;\""
sed -i "s/^;[[:blank:]]*\(EstateConnectionString\)/\\1/" $EtcDirectory/config-include/GridCommon.ini
crudini --set $EtcDirectory/config-include/GridCommon.ini DatabaseService EstateConnectionString "\"Data Source=$db_host;Database=$db_name;User ID=$db_user;Password=$db_pass;Old Guids=true;\""
uncomment GatekeeperURI $EtcDirectory/config-include/GridCommon.ini
crudini --set $EtcDirectory/config-include/GridCommon.ini DatabaseService EstateConnectionString "\"Data Source=$db_host;Database=$db_name;User ID=$db_user;Password=$db_pass;Old Guids=true;\""

crudini --set $EtcDirectory/config-include/GridCommon.ini Modules Include-FlotsamCache '"${Const|EtcDirectory}/config-include/FlotsamCache.ini"'
crudini --set $EtcDirectory/config-include/GridCommon.ini AssetService AssetLoaderArgs '"${Const|EtcDirectory}/assets/AssetSets.xml"'
crudini --set $EtcDirectory/config-include/GridHypergrid.ini Includes Include-Common '"${Const|EtcDirectory}/config-include/GridCommon.ini"'

crudini --set $EtcDirectory/config-include/osslDefaultEnable.ini OSSL Include-osslEnable "\"$EtcDirectory/config-include/osslEnable.ini\""

uncomment AllowOSFunctions $EtcDirectory/config-include/osslEnable.ini
crudini --set $EtcDirectory/config-include/osslEnable.ini OSSL AllowOSFunctions true
uncomment PermissionErrorToOwner $EtcDirectory/config-include/osslEnable.ini
crudini --set $EtcDirectory/config-include/osslEnable.ini OSSL PermissionErrorToOwner true
crudini --set $EtcDirectory/config-include/osslEnable.ini OSSL osslParcelO '"PARCEL_OWNER,"'
crudini --set $EtcDirectory/config-include/osslEnable.ini OSSL osslParcelOG '"PARCEL_GROUP_MEMBER,PARCEL_OWNER,"'


crudini --del $EtcDirectory/OpenSim.ini Architecture
crudini --set $EtcDirectory/OpenSim.ini Architecture Include-Architecture "\${Const|EtcDirectory}/config-include/GridHypergrid.ini"

crudmerge $EtcDirectory/OpenSim.ini $EtcDirectory/config-include/GridHypergrid.ini
crudmerge $EtcDirectory/OpenSim.ini $EtcDirectory/config-include/GridCommon.ini

sed -i "s/^[[:blank:]]*\(Include-Storage\)/; \\1/" $EtcDirectory/OpenSim.ini

startupIni="$ETC/robust.d/$gridnick.ini"
[ -e "$startupIni" ] || ln -frs "$RobustOutput" "$startupIni" && ls "$startupIni" || end $?

rm -f $TMP.ini

if yesno -y "(re)start $gridname?"
then
  opensim restart $gridnick
  end $?
fi

end