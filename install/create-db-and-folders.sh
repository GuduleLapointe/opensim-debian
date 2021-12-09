#!/bin/bash

# Usage:     create-db-and-folders.sh [-v] file.ini

# - check .ini file
# - merge temporary with includes and expand ${XXX/YYY} variables
# - chekc presence of known folders and databases
# - try to create them if missing

# Author: Olivier van Helden <olivier@van-helden.net>
# Licence: AGPLv3
# Source: https://git.magiiic.com/opensimulator/opensim-debian
# Environment: OpenSimulator server
# Depends: os-helpers, bash-helpers/helpers, crudini

BASEDIR=$(dirname $(dirname $(realpath "$0")))
. $BASEDIR/lib/os-helpers || exit 1

varPattern="[$]\{[[:alnum:]_-]+\|[[:alnum:]_]+}"

[ "$1" ] || end $? "usage: $PGM file.ini"

# ini=$1
cleanupIni $1 > $TMP.ini
iniExpandVariables $TMP.ini

executable=$(crudget $TMP.ini Launch Executable)
if [ "$executable" != "Robust.exe" ]
then
  iniMergeAndForget $TMP.ini Includes Include-Common
  iniMergeAndForget $TMP.ini Modules Include-FlotsamCache
  iniMergeAndForget $TMP.ini OSSL Include-osslEnable
  iniMergeAndForget $TMP.ini Architecture Include-Architecture
  crudini --del $TMP.ini Includes Include-Common
  addons=$(crudget $TMP.ini Modules Include-modules)
  # echo addons: $addons
  ls $addons 2>/dev/null | while read addonini
  do
    log merging addon $addonini
    crudmerge $thisini $addonini
  done
  crudini --del $TMP.ini Modules Include-modules
  # inigrep "^[[:blank:]]*Include" $TMP.ini
fi

echo "AssetCache CacheDirectory
AssetService BaseDirectory
AssetService SpoolDirectory
BakedTextureService BaseDirectory
Const CacheDirectory
Const ConfigDirectory
Const DataDirectory
Const LogsDirectory
DataSnapshot snapshot_cache_directory
GridService MapTileDirectory
Launch LogConfig
MapImageService TilesStoragePath
Startup ConsoleHistoryFile
Startup regionload_regionsdir
Startup RegistryLocation" | while read section variable
do
  dir=$(crudget $TMP.ini $section $variable 2>/dev/null | sed -E "s:/[[:alnum:]_-]+\.[[:alnum:]\._-]+$::")
  echo "$dir"
  dirname "$dir"
done | sort -u | while read dir
do
  [ "$dir" ] || continue
  [ -d "$dir" ] && log $dir exists && continue
  log "$dir missing"
  echo $dir >> $TMP.directories
done

if [ -f $TMP.directories ]
then
  echo "$PGM: will create $(cat $TMP.directories | wc -l) directories:"
  cat $TMP.directories | sed "s/^/  /"
  yesno "Proceed?" || end Cancelled
  cat $TMP.directories | while read dir
  do
    mkdir $dir && echo "$dir created" || end $? Error creating $dir
  done
else
  log "no directory missing"
fi

{
(
crudget $TMP.ini DatabaseService ConnectionString
crudget $TMP.ini DatabaseService EstateConnectionString
crudget $TMP.ini AssetService ConnectionString
crudget $TMP.ini UserProfilesService ConnectionString
crudget $TMP.ini Includes GLBSpecificConnectionString
crudget $TMP.ini Gloebit GLBSpecificConnectionString
) 2>/dev/null| sort -u | while read connectionstring
do
  DatabaseName=$(echo "$connectionstring;" | sed "s/.*Database=//" | cut -d ';' -f 1)
  DatabaseHost=$(echo "$connectionstring;" | sed "s/.*Data Source=//" | cut -d ';' -f 1)
  [ ! "$DatabaseHost" ] && log 1 could not fetch DatabaseHost && continue
  DatabaseUser=$(echo "$connectionstring;" | sed "s/.*User ID=//" | cut -d ';' -f 1)
  [ ! "$DatabaseUser" ] && log 1 could not fetch DatabaseUser && continue
  DatabasePassword=$(echo "$connectionstring;" | sed "s/.*Password=//" | cut -d ';' -f 1)
  [ ! "$DatabasePassword" ] && log 1 could not fetch DatabasePassword && continue
  testDatabaseConnection $DatabaseHost $DatabaseName $DatabaseUser "$DatabasePassword" \
  || end $? Error $? during database or db user creation
done
} 3<&0
# cat $TMP.ini | inigrep Connection
# cat $common
