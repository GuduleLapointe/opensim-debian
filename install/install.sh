#!/bin/bash

# Copyright 2015 Olivier van Helden <olivier@van-helden.net>
# Released under GNU Affero GPL v3.0 license
#    http://www.gnu.org/licenses/agpl-3.0.html

which apt > /dev/null || end $? "Depends to apt installation tool, you must install it"
which crudini > /dev/null || sudo apt install crudini || end $? "Depends to crudini ini file parsers, you must install it"
which pv > /dev/null || sudo apt install pv || end $? "Depends to crudini ini file parsers, you must install it"

echo "Initialize submodules" >&2
git submodule init
git submodule update
# git submodule update --remote

OSDOWNLOADPAGE=http://opensimulator.org/dist
DEBUG=yes
#AUTOMATIC=yes


BASEDIR=$(dirname $(dirname $(realpath "$0")))
. $BASEDIR/lib/os-helpers || exit 1
# . $CONTRIB/bash-helpers/ini_parser || (echo "Missing ini_parser librarie" >&2; exit 2 )
trap 'rm -f $TMP*' EXIT

# End of user configurable data



log checking preferences
if [ ! -d "$ETC" ]
then
  log No preferences folder, createing one
  for etc in $BASEDIR/etc /etc/$OPENSIM ~/etc/$OPENSIM
  do
    mkdir "$etc" 2>/dev/null && ETC=$etc && break
  done
  [ ! "$ETC" ] && end 1 "Could not create preferences folder"
fi
log "Preferences folder is $ETC"

sudo apt update && sudo apt upgrade -y || exit $?

log checking mono
if ! (dpkg --get-selections mono-complete | cut -f 1 | grep -q "^mono-complete$")
then
  log 1 "Mono is required to run OpenSimulator"
  yesno "Install mono?" || end $? "Mono installation cancelled"
  sudo apt install mono-complete \
  || end $? "Mono installation failed"
fi

log "## Checking standard directories"
for dir in $LIB $SRC $VAR $CACHE $DATA $ETC \
  $ETC/opensim.d $ETC/robust.d $ETC/grids $DATA $CACHE $VAR/logs $VAR/tmp
do
  [ -d "$dir" ] && continue
  mkdir -p "$dir" \
    && log "Created $dir" \
    || end $? "Could not create $dir"
done

log "Looking for OpenSimulator binaries"
if [ ! -f "$OSBIN" ]
then
  log "OpenSim binaries missing, lets fix that"
  if yesno "Download latest official release?"
  then
    log "Downloading latest official release"
    OSDOWNLOAD=$( curl -s $OSDOWNLOADPAGE/ | tr " " "\n" \
    | grep 'href="opensim.*tar.gz' | grep -v source | cut -d '"' -f 2  | tail -1)
    [ "$OSDOWNLOAD" != "" ] && OSDOWNLOAD=$OSDOWNLOADPAGE/$OSDOWNLOAD \
    || end 1 could not find a release to download

    mkdir -p "$SRC" || end $? "Could not create $SRC"
    tar=$(basename "$OSDOWNLOAD")
    if [ ! -f "$SRC/$tar" ]
    then
      log "loading OpenSimulator supported release"
      log "$OSDOWNLOAD"
      wget -nd -P "$SRC" "$OSDOWNLOAD" \
      || end $? Error $? while downloading OpenSim
    fi
    log "unpacking OpenSimulator"
    cd "$CORE" \
    && log extracting OpenSimulator archive to "$CORE/$OPENSIM" \
    && tar xvfz "$SRC/$tar" \
    || end $? Error $? while unpacking OpenSim
    OSDIR=$CORE/$(basename $OSDOWNLOAD .tar.gz)
    [ -d "$OSDIR" ] || end 1 "unexpectedly didn't find $OSDIR"
    OSBINDIR=$OSDIR/bin
    [ -d "$OSBINDIR" ] || end 1 "unexpectedly didn't find $OSBINDIR"
    OSBIN=$OSBINDIR/OpenSim.exe
    [ -f "$OSBIN" ] || end 1 "unexpectedly didn't find $OSBIN"
  else
    if yesno "Download development version?"
    then
      log 1 "TODO: Download and build development version"
    else
      log 1 "OpenSimulator core is not installed"
    fi
  fi
fi
[ ! "$OSBINDIR" ] && OSBINDIR=$OSDIR/bin

export OSBINDIR

#cd "$OSBIN" || end 2 could not cd to $OSBIN
#(
#find -name "*.ini"
#find -name "*.ini.example"
#find -name "*.config"
#) | sed "s%\./%%" | sed "s/\.example$//" | sort -u | while read file
#do
#	[ -f "$ETC/$file" ] && continue
#	folder="$(dirname "$ETC/$file")"
#	[ -d "$folder" ] || mkdir -p "$folder" || end 4 could not create $folder
#	cp $OSBIN/$file $ETC/$file 2>/dev/null \
#		|| cp $OSBIN/$file.example $ETC/$file 2>/dev/null \
#		|| end 4 could not copy $file
#done

# CACHE=$VAR/cache
# DATA=$VAR/data
#
# OpenSimBinDirectory=$OSBINDIR
# readvar OpenSimBinDirectory

if yesno "Create Robust config?"
then
  user=$(getent passwd $USER | cut -d : -f 5 | cut -d , -f 1 | cut -d " " -f 1 | grep -i [a-z] || echo "$USER" | sed -r -e 's/(\W)/\L\1/g' -e 's/(^|[ _-])(\w)/\U\2/g')
  newgrid.sh "${user}s Grid" || end $?

  # log setting defaults
  # crudini --set $TMP.new.ini Launch BinDir "\"$OpenSimBinDirectory\""
  # crudini --set $TMP.new.ini Launch Executable "\"Robust.exe\#"
  # cleanupIni $OSBINDIR/Robust.HG.ini.example > $TMP.defaults.ini
  # crudmerge $TMP.new.ini $TMP.defaults.ini
  # crudmerge $TMP.new.ini $BASEDIR/install/Robust.Tweaks.ini
  # crudini --set $TMP.new.ini DatabaseService ConnectionString "\"Data Source=localhost;Database=os_$(hostname -s);User ID=opensim;Password=password;Old Guids=true;\""
  #
  # log "## Choose robust config"
  #
  # RobustConfig=$(
  #   (
  #   ls $ETC/robust.d/*.ini 2>/dev/null
  #   # ls $ETC/robust-enabled/*.ini
  # 	# ls $ETC/robust-available/*.ini
  #   # ls $ETC/opensim.d/Robust*.ini $ETC/opensim.d/robust*.ini
  # 	# echo "$ETC/robust.d/NewRobust.ini"
  #   ) | head -1
  # )
  # if [ "$RobustConfig" ]
  # then
  #   log 1 "Please choose the Robust .ini file location"
  #   log 1 "  If present, it will be read, and overriden after settings completion"
  #   log 2 "  If not present, it will be created"
  #   readvar RobustConfig
  #   #read -e -p "$PGM: Robust config file: " -i $RobustConfig RobustConfig
  #   [ "$RobustConfig" ] || end 1 "You have to choose a file"
  #   RobustName=$(basename $RobustConfig .ini)
  #   cleanupIni $RobustConfig > $TMP.current.ini
  #
  #   log merging current config to defaults
  #   crudmerge $TMP.new.ini $TMP.current.ini
  # fi
  #
  # [ ! "$GridName" ] && GridName=$(titlecase $(hostname -s | cut -d "." -f 1))
  # readvar GridName
  # crudini --set $TMP.new.ini GridInfoService GridName "\"$GridName\""
  # [ ! "$GridNick" ] && GridNick=$(echo $GridName | sed "s/ //g")
  # readvar GridNick
  # crudini --set $TMP.new.ini GridInfoService GridNick "\"$GridNick\""
  #
  # [ ! "$RobustName" ] && RobustName=$(echo "$GridName" | sed "s/ //g")
  # # RobustName=$(titlecase $(hostname -s | cut -d "." -f 1))
  # # readvar RobustName
  # [ ! "$RobustConfig" ] && RobustConfig=$ETC/robust.d/$RobustName.ini
  # # [ ! -f "$RobustConfig" ] &&  touch $RobustConfig
  #
  # MachineName=$(echo "$GridNick" | tr "[:upper:]" "[:lower:]")
  # log "MachineName $MachineName"
  #
  # log 1 "## General settings"
  # eval $(crudini --get --format=sh $TMP.new.ini Const \
  # | sed -e "s/baseurl/BaseURL/" -e "s/publicport/PublicPort/" -e "s/privateport/PrivatePort/" \
  # -e "s/cachedirectory/CacheDirectory/" -e "s/datadirectory/DataDirectory/" \
  # -e "s/\"//g"
  # )
  # # ini.parse $TMP.new.ini
  # # ini.section.Const  || end $? broken at Const
  # # eval $(crudini --get --format=sh $TMP.new.ini Const)
  # # BaseURL=$baseurl
  # # PublicPort=$publicport
  # # PrivatePort=$privateport
  # [ "$BaseURL" = "" ] && BaseURL=http://$(hostname -f)
  # echo "$BaseURL" | grep -q "127\.0\.0\." && BaseURL="http://$(hostname -f)"
  # echo "$BaseURL" | grep -q "^https*://" || BaseURL="http://$BaseURL"
  # log BaseURL: $BaseURL
  # BaseURL=$(echo "$BaseURL" | sed "s/\"//")
  # PublicPort=$(echo "$PublicPort" | sed "s/\"//")
  # PrivatePort=$(echo "$PrivatePort" | sed "s/\"//")
  # readvar BaseURL PublicPort PrivatePort
  # crudini --set $TMP.new.ini Const BaseURL "\"$BaseURL\""
  # crudini --set $TMP.new.ini Const PublicPort "$PublicPort"
  # crudini --set $TMP.new.ini Const PrivatePort "$PrivatePort"
  # crudini --set $TMP.new.ini Const CacheDirectory "\"$CACHE/$MachineName\""
  # crudini --set $TMP.new.ini Const DataDirectory "\"$DATA/$MachineName\""
  #
  # hostname=$(echo "$BaseURL" | sed "s%.*://%%" | cut -d "/" -f 1)
  # log hostname $hostname
  # ## Database configuration
  # log 1 "## Database configuration"
  # # ini.parse $TMP.new.ini
  # # grep -A5 DatabaseService $TMP.new.ini
  # eval $(crudini --get --format=sh $TMP.new.ini DatabaseService \
  # | sed -e "s/storageprovider/StorageProvider/" -e "s/connectionstring/ConnectionString/" \
  # -e "s/\"//g"
  # )
  #
  # # ini.merge DatabaseService $tmpIni $TMP.db  $RobustConfig || end $? "ini merge failed"
  # log ConnectionString $ConnectionString
  # DatabaseHost=$(echo "$ConnectionString;" | sed "s/.*Data Source=//" | cut -d ';' -f 1)
  # DatabaseName=$(echo "$ConnectionString;" | sed "s/.*Database=//" | cut -d ';' -f 1)
  # DatabaseUser=$(echo "$ConnectionString;" | sed "s/.*User ID=//" | cut -d ';' -f 1)
  # DatabasePassword=$(echo "$ConnectionString;" | sed "s/.*Password=//" | cut -d ';' -f 1)
  #
  # readvar DatabaseHost DatabaseName DatabaseUser DatabasePassword
  #
  # testDatabaseConnection $DatabaseHost $DatabaseName $DatabaseUser "$DatabasePassword" \
  # || end $?
  #
  # ConnectionString="Data Source=$DatabaseHost;Database=$DatabaseName;User ID=$DatabaseUser;Password=$DatabasePassword;Old Guids=true;"
  # crudini --set $TMP.new.ini DatabaseService ConnectionString "\"$ConnectionString\""
  # log "ConnectionString $ConnectionString"
  #
  # log "## LoginService configuration"
  #
  # if [ -f "$ETC/$GridNick.Gloebit.ini" -o -f "$ETC/Gloebit.ini" ]
  # then
  #   Currency="G$"
  # else
  #   eval $(crudini --get --format=sh $TMP.new.ini LoginService \
  #   | sed -e "s/\"//g" \
  #   -e "s/currency/Currency/" -e "s/welcomemessage/WelcomeMessage/" -e "s/searchurl/SearchURL/" )
  # fi
  # readvar Currency  WelcomeMessage SearchURL
  # crudini --set $TMP.new.ini LoginService Currency "\"$Currency\""
  # crudini --set $TMP.new.ini LoginService WelcomeMessage "$WelcomeMessage"
  # crudini --set $TMP.new.ini LoginService SearchURL "$SearchURL"
  #
  # log "## GridService"
  # echo "[GridService]" > $TMP.regions
  # for flag in DefaultRegion DefaultHGRegion FallbackRegion #NoDirectLogin Persistent
  # do
  #   eval "$flag=\"$( (grep "$flag" $TMP.new.ini || echo Welcome) | sed "s/^Region_//" | cut -d= -f 1 | sed -e "s/_/ /g" -e "s/ *$//")\""
  #   readvar $flag
  #   regionvar=$(echo Region_${!flag} | sed "s/ /_/g")
  #   grep -q "^$regionvar *= *" $TMP.regions \
  #     && sed -i "s/^$regionvar *= *\"\(.*\)\"/$regionvar = \"\\1, $flag\"/" $TMP.regions \
  #     || echo "$regionvar = \"$flag\"" >> $TMP.regions
  # done
  # crudmerge $TMP.new.ini $TMP.regions
  #
  # ## Set robust name based on confif filename
  # # enable="$ETC/robust-enabled/$RobustName.ini"
  #
  # log "## Setting Launcher info"
  # crudini --set $TMP.new.ini Launch BinDir "\"$OSBINDIR\""
  # crudini --set $TMP.new.ini Launch Executable "\"Robust.exe\""
  # crudini --set $TMP.new.ini Launch LogFile "\"$LOGS/$MachineName.log\""
  # crudini --set $TMP.new.ini Launch ConsolePrompt "\"$RobustName ($hostname:$PublicPort)\""
  #
  # log "## Startup section"
  # crudini --set $TMP.new.ini Startup ConfigDirectory "$ETC/robust-include"
  # crudini --set $TMP.new.ini Startup PIDFile "\"\${Const|CacheDirectory}/$MachineName.pid\""
  # # crudini --set $TMP.new.ini Startup NoVerifyCertChain true
  # # crudini --set $TMP.new.ini Startup NoVerifyCertHostname true
  #
  # log "## Grid info"
  # crudini --set $TMP.new.ini GridInfoService GridName "\"$GridName\""
  # crudini --set $TMP.new.ini GridInfoService GridNick "\"$GridNick\""
  #
  # log "## Just for fun"
  # crudini --set $TMP.new.ini LibraryService LibraryName "\"$GridNick Library\""
  #
  #
  # log "## Checking $RobustNick directories"
  # for dir in \
  #   $DATA/$MachineName $DATA/$MachineName/fsassets \
  #   $CACHE/$MachineName/bakes $CACHE/$MachineName/fsassets $CACHE/$MachineName/maptiles \
  #   $CACHE/$MachineName/registry
  # do
  #   [ -d "$dir" ] && continue
  #   mkdir -p "$dir" \
  #     && log "Created $dir" \
  #     || end $? "Could not create $dir"
  # done
  #
  # crudini --get $TMP.new.ini > $TMP.sections
  # cat $TMP.sections | while read section
  # do
  #   crudini --get $TMP.new.ini $section | grep -qi [a-z] || crudini --del $TMP.new.ini "$section"
  # done
  #
  # echo
  # echo "# Generated configuration:"
  # echo
  # # cat $TMP.new.ini
  # # echo
  #
  # if [ -f "$RobustConfig" ]
  # then
  #     yesno "File $RobustConfig exists, override?" || end Aborted
  # else
  #     yesno "Save $RobustConfig file?" || end Aborted
  # fi
  # cp $TMP.new.ini $RobustConfig && echo "$RobustConfig saved"
  #
  # # [ ! -f "$enable" ] && ln -s "$RobustConfig" "$enable"
  # cat $OSBINDIR/Robust.exe.config \
  # | sed "s%\(<file value=\"\)Robust%\\1$LOGS/$RobustName%" \
  # > "$DATA/$RobustName.logconfig"
  #
  # # if [ ! -f "$ETC/opensim.conf" ]
  # # then
  # #   echo "myhost=$newhost
  # #   mydb=$newdb
  # #   myuser=$newuser
  # #   mypass=$newpass" > "$ETC/opensim.conf"
  # # fi
fi

end
