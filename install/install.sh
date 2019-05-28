#!/bin/bash

# Copyright 2015 Olivier van Helden <olivier@van-helden.net>
# Released under GNU Affero GPL v3.0 license
#    http://www.gnu.org/licenses/agpl-3.0.html

OSDOWNLOADPAGE=http://opensimulator.org/dist
DEBUG=yes
#AUTOMATIC=yes

# End of user configurable data

BASEDIR=$(dirname $(dirname $(realpath "$0")))
. $BASEDIR/lib/os-helpers || exit 1
. $CONTRIB/bash-helpers/ini_parser || end 2 "Missing ini_parser librarie"

trap 'rm -f $TMP*' EXIT

log "Initialize submodules"
git submodule init
git submodule update
# git submodule update --remote

if [ ! -d "$ETC" ]
then
  log No preferences folder, trying to create one
  for etc in $BASEDIR/etc /etc/$OPENSIM ~/etc/$OPENSIM
  do
    mkdir "$etc" 2>/dev/null && ETC=$etc && break
  done
  [ ! "$ETC" ] && end 1 "Could not create preferences folder"
fi
log "Preferences folder is $ETC"

log "Looking for OpenSimulator binaries"
if [ ! -f "$OSBIN/OpenSim.exe" ]
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

CACHE=$VAR/cache
DATA=$VAR/data

log "Checking standard directories presence"
for dir in $LIB $SRC $VAR $CACHE $DATA $ETC \
  $ETC/opensim.d $ETC/robust.d \
  $DATA/config $DATA/fsassets-data \
  $CACHE/bakes $CACHE/datasnapshot $CACHE/fsassets-tmp $CACHE/maptiles $CACHE/Registry \
  $DATA/regions \
  $VAR/logs $VAR/tmp
do
  [ -d "$dir" ] && continue
  mkdir -p "$dir" \
    && log "Created $dir" \
    || end $? "Could not create $dir"
done

log "end for now we'll see the configuration steps later"

if yesno "Create Robust config?"
then
  log "## Choose robust config"

  default=$OSBIN/Robust.HG.ini.example

  robustconfig=$(
    (
    ls $ETC/robust.d/*.ini
    ls $ETC/robust-enabled/*.ini
  	ls $ETC/robust-available/*.ini
    ls $ETC/opensim.d/Robust*.ini $ETC/opensim.d/robust*.ini
  	echo "$ETC/robust.d/NewRobust.ini"
    ) | head -1
  )
  #robustconfig=$ETC/robust-available/$( (ls $ETC/robust-enabled | grep "\.ini$" || echo "Robust.ini" ) | sort -n | head -1)
  #[ ! "$robustconfig" ] && robustconfig=Robust.ini

  log 1 "Please choose the Robust .ini file location"
  log 1 "  If present, it will be read, and overriden after settings completion"
  log 2 "  If not present, it will be created"
  readvar robustconfig
  #read -e -p "$PGM: Robust config file: " -i $robustconfig robustconfig
  [ "$robustconfig" ] || end 1 "You have to choose a file"

  ## Database configuration
  log 1 "## Database configuration"
  cat >> $TMP.db <<EOF
  [DatabaseService]
  ConnectionString = "Data Source=localhost;Database=opensim;User ID=opensim;Password=password;Old Guids=true;"
EOF
  ini.merge DatabaseService $default $TMP.db  $robustconfig
  DatabaseHost=$(echo "$ConnectionString;" | sed "s/.*Data Source=//" | cut -d ';' -f 1)
  DatabaseName=$(echo "$ConnectionString;" | sed "s/.*Database=//" | cut -d ';' -f 1)
  DatabaseUser=$(echo "$ConnectionString;" | sed "s/.*User ID=//" | cut -d ';' -f 1)
  DatabasePassword=$(echo "$ConnectionString;" | sed "s/.*Password=//" | cut -d ';' -f 1)
  readvar DatabaseHost DatabaseName DatabaseUser DatabasePassword

  cat >> $TMP.installdefault <<EOF
  [DatabaseService]
  ConnectionString = "Data Source=$DatabaseHost;Database=$DatabaseName;User ID=$DatabaseUser;Password=$DatabasePassword;Old Guids=true;"
EOF
  ini.merge DatabaseService $robustconfig $TMP.installdefault $default
  echo "ConnectionString $ConnectionString"

  ## Set robust name based on confif filename
  robustname=$(basename $robustconfig .ini)
  enable="$ETC/robust-enabled/$robustname.ini"

  log 1 "## General settings"
  cat > $TMP.installdefault <<EOF
  [Const]
  BaseURL = "http://$(hostname -f)"
EOF

  ini.merge Const $default $TMP.installdefault $robustconfig
  readvar BaseURL PublicPort PrivatePort
  echo "$BaseURL" | grep -q "^https*://" || BaseURL="http://$BaseURL"

  hostname=$(echo "$BaseURL" | sed "s%.*://%%" | cut -d "/" -f 1)

  log "## Setting Launcher info"
  cat >> $TMP.installdefault <<EOF
  [Launch]
     BinDir = "$OSBIN"
     Executable = "Robust.exe"
     LogFile = "$LOGS/$robustname.log"
     ConsolePrompt = "$robustname ($hostname:$PublicPort)"
EOF
  ini.merge Launch $TMP.installdefault $robustconfig

  log "## Startup section"
  cat >> $TMP.installdefault <<EOF
  [Startup]
  RegistryLocation=$VAR/Registry
  ConfigDirectory=$VAR/Config
  ConsoleHistoryFile=$LOGS/$ConsoleHistoryFile
  ConfigDirectory=$VAR/Config
EOF
  ini.merge Startup $default $TMP.installdefault $robustconfig
  ini.write Launch >> $TMP.ini
  ini.write Const >> $TMP.ini
  ini.write Startup >> $TMP.ini
  ini.write DatabaseService >> $TMP.ini

  log "## Grid info"
  cat >> $TMP.installdefault <<EOF
  [GridInfoService]
     GridName = "$(ucfirst $hostname) (Powered by opensim-debian)"
     GridNick = "$(ucfirst $hostname)"
     welcome = "\${Const|BaseURL}:\${Const|PublicPort}/welcome"
     ; economy = "\${Const|BaseURL}:\${Const|PublicPort}/economy"
     ; about = "\${Const|BaseURL}/about/"
     ; register = "\${Const|BaseURL}/register"
     ; help = "\${Const|BaseURL}/help"
     ; password = "\${Const|BaseURL}/password"
EOF
  ini.merge GridInfoService $default $TMP.installdefault $robustconfig
  readvar GridName GridNick
  ini.write GridInfoService >> $TMP.ini

  cat >> $TMP.installdefault <<EOF
  [GridService]
      MapTileDirectory = "$CACHE/maptiles"
  [MapImageService]
      TilesStoragePath = "$CACHE/maptiles"
  [BakedTextureService]
      BaseDirectory = "$CACHE/bakes"
  [LoginService]
      SearchURL = "\${Const|BaseURL}:\${Const|PublicPort}/";
  [UserProfilesService]
      Enabled = true
EOF
  for section in GridService LoginService UserProfilesService MapImageService BakedTextureService
  do
      ini.merge $section $default $TMP.installdefault $robustconfig
      ini.write $section >> $TMP.ini
  done

  echo
  echo "# Generated configuration:"
  echo
  cat $TMP.ini
  echo

  if [ -f "$robustconfig" ]
  then
      yesno "File $robustconfig exists, override?" || end Aborted
  else
      yesno "Save $robustconfig file?" || end Aborted
  fi
  mv $TMP.ini "$robustconfig" \
  && echo "$robustconfig saved"
  end
  [ ! -f "$enable" ] && ln -s "$robustconfig" "$enable"
  cat $ETC/Robust.exe.config \
  | sed "s%\(<file value=\"\)Robust%\\1$LOGS/$robustname%" \
  > "$ETC/$robustname.logconfig"

  if [ ! -f "$ETC/opensim.conf" ]
  then
    echo "myhost=$newhost
    mydb=$newdb
    myuser=$newuser
    mypass=$newpass" > "$ETC/opensim.conf"
  fi
fi

end
