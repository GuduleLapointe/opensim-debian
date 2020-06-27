#!/bin/bash

# Utility to move an existing OpenSimulator instance from a remote server
# - run the script from the new host
# - run the script as regular user (the one using opensim)
# - local user must have ssh access to remote server
# - local user must have sudo rights
# - remote server must allow mysql connection from local machine
# - mysql host is set to localhost.
#####################################################################
# DO NOT RUN IF NEW HOST IS ALREADY MYSQL HOST FOR REMOTE SIMULATOR #
#####################################################################
# - some parameters in $ETC/opensim.conf will replace the imported ones:
#     OSBINDIR
#     EstateConnectionString
#     GLBSpecificConnectionString
#     GridCommon

# Copyright 2015 Olivier van Helden <olivier@van-helden.net>
# Released under GNU Affero GPL v3.0 license
#    http://www.gnu.org/licenses/agpl-3.0.html

BASEDIR=$(dirname $(dirname $(realpath "$0")))
. $BASEDIR/lib/os-helpers || exit 1
BINDIR=$BASEDIR/bin

[ "$OSBIN" ] || exit 1
[ "$ETC" ] || exit 1
DEBUG=yes

trap 'rm -f $TMP*; rm -rf $TMPDIR' EXIT
TMPDIR=$(mktemp -d -p $BASEDIR/var || end $?)
TMP=$TMPDIR/$PGM.$$

log TMPDIR $TMPDIR
log TMP $TMP

[ "$2" ] || end 1 "usage $PGM <source host> <simulator 1> [<simulator 2] [...]"
log BASEDIR $BASEDIR
log ETC $ETC
log BINDIR $BINDIR
log OSBINDIR $OSBINDIR
log OSBIN $OSBIN
lr=$(printf "\n\b")
clr="\33[2K\r"
source=$1; shift

echo "$source" | egrep -q "[[:alnum:]-]\.[[:alnum:]-]" || end $? "$source: need a fully qualified host name"
check=$(ping  -c1 $source 2>&1 >/dev/null) || end $? "${check/ping: /}"

log get local config from $ETC
log OSBINDIR $OSBINDIR
log Include-Common ${Include_Common}

for sim in $@
do
  log transfering simulator $sim from $source
  mkdir $TMPDIR/$sim && cd $TMPDIR/$sim || end $?

  log get remote config file $source:$ETC/opensim.d/$sim.ini
  scp $source:$ETC/opensim.d/$sim.ini $TMP.ini || end $?
  cleanupIni $TMP.ini > $sim.remote.ini

  log get remote db config
  crudget $sim.remote.ini DatabaseService ConnectionString \
  | tr ";" "\n" | grep = | grep -v "Old Guids" \
  | sed -e "s/^Data Source/REMOTEDBHOST/" -e "s/^Database/REMOTEDBNAME/" -e "s/^User ID/REMOTEDBUSER/" -e "s/^Password/REMOTEDBPASS/" \
  | tee $TMP.remotedb.ini && . $TMP.remotedb.ini || end $?

  if [ -e $ETC/opensim.d/$sim.ini ]
  then
    log 1 "$ETC/opensim.d/$sim.ini already exists"
    if ! yesno "Replace with remote config?"
    then
      yesno "Erase other data and replace with remote content?" || end $?
      cp $ETC/opensim.d/$sim.ini $TMP.ini
    fi
  fi
  cleanupIni $TMP.ini > $sim.ini

  log get local db config
  crudget $sim.ini DatabaseService ConnectionString \
  | tr ";" "\n" | grep = | grep -v "Old Guids" \
  | sed -e "s/^Data Source/DBHOST/" -e "s/^Database/DBNAME/" -e "s/^User ID/DBUSER/" -e "s/^Password/DBPASS/" \
  | tee $sim.$$.db.ini && . $sim.$$.db.ini || end $?

  [ "$OSBINDIR" ] && crudini --set $sim.ini Launch BinDir "$OSBINDIR"
  [ "$EstateConnectionString" ] && crudini --set $sim.ini DatabaseService EstateConnectionString "\"$EstateConnectionString\""
  [ "$GLBSpecificConnectionString" ] && crudini --set $sim.ini Gloebit GLBSpecificConnectionString "\"$GLBSpecificConnectionString\""
  [ "$GridCommon" ] && crudini --set $sim.ini Includes Include-Common "$GridCommon"

  SimName=$(crudget $sim.ini Launch SimName)
  # just make sure SimName is lowercase and without spaces
  $SimName=$(echo $SimName | sed "s/[^[:alnum:]_]//g" | tr [:upper:] [:lower:])

  ## Forget that stuff, simname **is** the MachineName
  # log check if MachineName is present and valid
  # MachineName==$(crudget $sim.ini Launch MachineName)
  # [ "$MachineName" ] \
  # && MachineName=$(echo $MachineName | sed "s/[^[:alnum:]_]//g" ) \
  # || MachineName=$(echo $SimName | sed "s/[^[:alnum:]_]//g" | tr [:upper:] [:lower:]) \
  # && [ "$MachineName" ] \
  # && crudini --set $sim.ini Launch MachineName $MachineName \
  # || end $? MachineName $MachineName empty
  # log MachineName $MachineName

  # log rough and dirty LogConfig fix
  crudini --set $sim.ini Launch LogConfig "$(crudget $GridCommon Const DataDirectory | sed -e "s#\${Launch|SimName}#${SimName}#g" -e "s#\${Launch|MachineName}#${SimName}#g")/config/log.config"
  # crudini --set $sim.ini Launch LogConfig "$(crudget $GridCommon Const DataDirectory | sed -e "s#\${Launch|SimName}#${MachineName}#g" -e "s#\${Launch|MachineName}#${MachineName}#g")/config/log.config"

  log "Creating user $DBUSER (if not exists)"
  echo "CREATE USER IF NOT EXISTS $DBUSER IDENTIFIED BY '$DBPASS'" | sudo mysql -BN
  if [ "$(echo "SHOW DATABASES LIKE '$DBNAME'" | sudo mysql -BN)" ]
  then
    log 1 Database $DBNAME already exists
    if ! yesno "Erase all data and replace with remote content?"
    then
      read -p "Enter a new database name or press Ctrl-C to cancel: " DBNAME
      if [ "$(echo "SHOW DATABASES LIKE '$DBNAME'" | sudo mysql -BN)" ]
      then
        end $? second attemp not better, db $DBNAME also exists
      fi
    fi
  fi
  echo "CREATE DATABASE IF NOT EXISTS $DBNAME;
  GRANT ALL ON $DBNAME.* TO $DBUSER;" | sudo mysql -BN
  [ "$(echo "SHOW DATABASES like '$DBNAME'" | sudo mysql -BN)" ] && log Database $DBNAME created || end 1 "Could not create db $DBNAME"

  echo "CREATE TABLE test_$PGM_$$ (t integer); DROP TABLE test_$PGM_$$;" | mysql -u$DBUSER -p"$DBPASS" $DBNAME \
  || end $? error accessing local database
  # log test local db connection
  # log test remote db connection

  # create local directories and sync non cache ones
  # section=
  # egrep -v "http:|https:|^Include|OutboundDisallowForUserScripts" $sim.live.ini \
  # | inigrep / | sed "s/ *=.*//" | while read line
  # do
  #   echo $line | grep -q "\[" && section=$(echo $line | sed "s/[^[:alpha:]]//g") && continue
  #   echo "$section $line"
  # done | sort -u

  log get remote live config
  ssh $source "screen -x $sim -X stuff 'config save /tmp/$sim.live.$$.ini$lr'" || end $? "Is remote simulator up and running?"
  scp $source:/tmp/$sim.live.$$.ini ./$sim.live.ini || end $? "Could not get live config. Is remote simulator up and running?"

  log 1 stop remote simulator
  ssh $source "$BINDIR/opensim stop now $sim" || end $?
  ssh -t $source "screen -x $sim"

  log "dump remote db to local one"
  log ssh $source "$BINDIR/opensim stop $sim"
  DUMPSIZE=$(mysql --skip-column-names -h$source -u$REMOTEDBUSER -p"$REMOTEDBPASS" $REMOTEDBNAME <<< "
  SELECT ROUND(SUM(data_length + index_length) * 0.5, 0)
  FROM information_schema.TABLES WHERE table_schema='$REMOTEDBNAME';
  ")
  mysqldump -h$source -u$REMOTEDBUSER -p"$REMOTEDBPASS" --hex-blob $REMOTEDBNAME \
  | pv -pbae --size ${DUMPSIZE} \
  | mysql -u$DBUSER -p"$DBPASS" $DBNAME \
  || end $?

  log calculating local config log $GridCommon to grid.ini
  cp $sim.ini $sim.local.ini
  cleanupIni $GridCommon > $TMP.ini
  crudini --merge $sim.local.ini < $TMP.ini
  cat $sim.local.ini | tr "$ :/\"" "\n" | grep '{.*|.*}' | sort -u | tr "{|}" " " \
  | while read section variable
  do
    value=$(crudget $sim.local.ini $section $variable)
    # echo "  $section->$variable = $value"
    sed -i "s#\${$section|$variable}#$value#g" $sim.local.ini || end $?
  done

  echo "AssetCache CacheDirectory
Const CacheDirectory
Const DataDirectory
Const LogsDirectory
Const ConfigDirectory
DataSnapshot snapshot_cache_directory
GridService MapTileDirectory
Startup regionload_regionsdir" | while read section param
  do
    dst=$(crudget $sim.local.ini $section $param)
    [ "$dst" ] && mkdir -p "$dst"

    src=$(crudget $sim.live.ini $section $param)
    [ "$src" ] || continue
    echo $section $param $src $dst | egrep -qi "cache" && continue # exclude cache
    echo "/$src/" | egrep -qi "/$sim/" || continue # exclude shared
    log sync $source:$src/ to $dst/
    rsync --progress -Waz $source:"$src"/ "$dst"/ || end $?
  done
  regionload_regionsdir=$(crudget $sim.local.ini Startup regionload_regionsdir)
  [ "$regionload_regionsdir" ] ||end $? regionload_regionsdir not set
  find $regionload_regionsdir -name "*.ini" | grep -q . || end $? "no region found in $regionload_regionsdir"

  log "activate $ETC/opensim.d/$sim.ini"
  mkdir -p $ETC/opensim.d
  cp $sim.ini $ETC/opensim.d/$sim.ini

  log starting local simulator $sim
  opensim start $sim || end $?

  log "deactivate $sim on source"
  ssh $source "mv $ETC/opensim.d/$sim.ini $ETC/opensim.d/$sim.transfered" || end $?
  log "We should be good now"
done

[ $errors ] && end errors $errors
# ssh herbie $BINDIR/opensim status
