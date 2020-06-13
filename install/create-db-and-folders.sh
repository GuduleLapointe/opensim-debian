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

iniExpandVariables() {
  [ ! "$1" ] && log 1 iniExpandVariables no ini file specified && return
  thisini="$1"
  grep -q '${' $thisini || { log "no var in $thisini, skipping"; return; }
  log expanding $thisini
  sed -E "s/($varPattern)/\n\\1\n/g" $TMP.ini \
  | grep -E --color "$varPattern" | sed -e "s/[{}$]//g" -e "s/|/ /" \
  | sort -u | while read section variable
  do
    value=$(crudget $TMP.ini $section $variable)
    # echo $section $variable = $value
    echo "$value" | grep -q "/" \
    && sed -E -i "s%[$]\{$section\|$variable}%$value%g" $thisini \
    || sed -E -i "s/[$]\{$section\|$variable}/$value/g" $thisini
    # grep --color "$section|$variable" $thisini
    # grep $value $thisini
  done
}

mergeandforget() {
  [ ! "$3" ] && log 1 "usage mergeandforget ini section variable" && return 1
  thisini=$1
  section=$2
  variable=$3
  file=$(crudget $thisini $section $variable)
  [ ! -e "$file" ] && log 2 "file $file not found" && return 2

  log mergin $common
  crudmerge $thisini $file
  crudini --del $thisini $section $variable
  sed -i "s/Include_/Include-/" $thisini
  iniExpandVariables $thisini
}

# ini=$1
cleanupIni $1 > $TMP.ini
iniExpandVariables $TMP.ini

executable=$(crudget $TMP.ini Launch Executable)
if [ "$executable" != "Robust.exe" ]
then
  mergeandforget $TMP.ini Includes Include-Common
  mergeandforget $TMP.ini Modules Include-FlotsamCache
  mergeandforget $TMP.ini OSSL Include-osslEnable
  mergeandforget $TMP.ini Architecture Include-Architecture
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
