#!/usr/bin/env bash

# Copyright 2015 Olivier van Helden <olivier@van-helden.net>
# Released under GNU Affero GPL v3.0 license
#    http://www.gnu.org/licenses/agpl-3.0.html

OSDOWNLOADPAGE=http://opensimulator.org/dist
DEBUG=yes
#AUTOMATIC=yes

BASEDIR=$(dirname $(dirname $(realpath "$0")))
. $BASEDIR/libexec/os-helpers || exit 1
trap 'rm -f $TMP*' EXIT

# Package manager abstraction
case "$(uname -s)" in
  Darwin)
    pkg_install() { brew install "$@"; }
    pkg_update()  { brew update && brew upgrade; }
    pkg_check()   { brew list "$1" &>/dev/null; }
    which brew >/dev/null || end 1 "Homebrew required on macOS: https://brew.sh"
    ;;
  Linux)
    which apt-get >/dev/null \
      || end 1 "Unsupported Linux distribution (no apt-get)"
    pkg_install() { sudo apt-get install -y "$@"; }
    pkg_update()  { sudo apt-get update && sudo apt-get upgrade -y; }
    pkg_check()   { dpkg -l "$1" 2>/dev/null | grep -q "^ii"; }
    ;;
  *)
    end 1 "Unsupported OS: $(uname -s)"
    ;;
esac

# Tool dependencies (ask before installing each missing tool)
if ! which pv >/dev/null; then
	yesno "Install pv?" && pkg_install pv || end $? "Could not install pv"
fi
if ! which screen >/dev/null; then
	yesno "Install screen?" && pkg_install screen || end $? "Could not install screen"
fi

# Python venv + crudini (isolated from system Python)
. $BASEDIR/libexec/venv-setup || end $? "Could not set up Python venv"
log "using $(which python3)"
require crudini

echo "Initialize submodules" >&2
git submodule update --init

yesno "Update system packages?" && {
  pkg_update || log $? "System update failed, continuing anyway"
}

# Runtime: mono for OpenSim < 0.9.3, dotnet for >= 0.9.3
# Both can coexist; install what's missing based on the target version.
log "Checking runtimes"
if which mono >/dev/null 2>&1; then
	log "mono installed $(mono --version)"
else
  	yesno "Install Mono (required for OpenSim < 0.9.3)?" && {
    	pkg_install mono-complete || end $? "Mono installation failed"
    }
fi

if which dotnet >/dev/null 2>&1; then
	log "dotnet installed $(dotnet --version)"
else
  	yesno "Install .NET runtime (required for OpenSim >= 0.9.3)?" && {
    # Universal installer from Microsoft — works on Linux and macOS
    curl -fsSL https://builds.dotnet.microsoft.com/dotnet/scripts/v1/dotnet-install.sh \
      | bash -s -- --runtime dotnet --channel LTS \
      || end $? ".NET runtime installation failed"
    # dotnet-install.sh installs to ~/.dotnet by default; add to PATH if needed
    export DOTNET_ROOT="$HOME/.dotnet"
    export PATH="$PATH:$DOTNET_ROOT:$DOTNET_ROOT/tools"
  }
fi

# --- OpenSim version selection (no download yet) ---
echo ""
log "Fetching OpenSimulator release list from $OSDOWNLOADPAGE ..."
_releases=$(curl -s "$OSDOWNLOADPAGE/" \
  | grep -o 'href="opensim-[0-9][^"]*\.tar\.gz"' \
  | grep -v source \
  | cut -d'"' -f2 \
  | sort -r)
[ -n "$_releases" ] || end 1 "Could not fetch release list from $OSDOWNLOADPAGE"

echo ""
echo "OpenSimulator version to install:"
i=1
while IFS= read -r _f; do
  printf "  %2d) %s\n" "$i" "$(basename "$_f" .tar.gz)"
  i=$((i+1))
done <<< "$_releases"
echo "   d) Development version (build from source — not yet implemented)"
echo "   s) Skip (install OpenSim manually later)"
echo ""
read -p "  Version [1]: " _vchoice
_vchoice=${_vchoice:-1}

case "$_vchoice" in
  d|D)
    OSVERSION=dev
    OSDOWNLOAD=
    ;;
  s|S)
    OSVERSION=
    OSDOWNLOAD=
    ;;
  [0-9]*)
    _vsel=$(echo "$_releases" | sed -n "${_vchoice}p")
    [ -n "$_vsel" ] || end 1 "Invalid choice: $_vchoice"
    OSVERSION=$(basename "$_vsel" .tar.gz | sed 's/^opensim-//')
    OSDOWNLOAD="$OSDOWNLOADPAGE/$_vsel"
    ;;
  *) end 1 "Invalid choice: $_vchoice" ;;
esac
unset _releases _f _vchoice _vsel i

# --- Layout selection ---
echo ""
echo "Installation layout:"
echo "  1) System    — Standard Linux paths: /etc/opensim, /var/lib/opensim, /usr/share/opensim"
echo "  2) Bundled   — Organized structure under a single directory: /opt/opensim, ~/opensim, ..."
echo "  3) Flat      — OpenSim's default layout, all files in core directory"
echo ""
read -p "  Layout [1]: " _layout_choice

## Set install base directory
case "${_layout_choice:-1}" in
  1|system|debian)
    LAYOUT=debian
    BaseInstallPath=/usr/share/opensim
    ;;
  2|bundled)
    LAYOUT=bundled
    BaseInstallPath=/opt/opensim
    readvar BaseInstallPath
    ;;
  3|flat)
    LAYOUT=flat
    BaseInstallPath=$BASEDIR/core
    readvar BaseInstallPath
    ;;
  *)
    end 1 "Invalid layout choice"
    ;;
esac

INSTALLPATH=$BaseInstallPath
log "INSTALLPATH=$INSTALLPATH"

case "${_layout_choice:-1}" in
  1|system|debian)
    LAYOUT=debian
    ETC=/etc/opensim
    VAR=/var/lib/opensim
    CORE_BASE=/usr/share/opensim
    CORE=$CORE_BASE/opensim-$OSVERSION
    LOGS=/var/log/opensim
    CACHE=/var/cache/opensim
    DATA=/var/lib/opensim/data
    ;;
  2|bundled)
    LAYOUT=bundled
    ETC=$INSTALLPATH/etc
    VAR=$INSTALLPATH/var
    CORE_BASE=$INSTALLPATH/core
    CORE=$CORE_BASE/opensim-$OSVERSION
    LOGS=$INSTALLPATH/var/logs
    CACHE=$INSTALLPATH/var/cache
    DATA=$INSTALLPATH/var/data
    ;;
  3|flat)
    LAYOUT=flat
    CORE_BASE=$INSTALLPATH/opensim-$OSVERSION
    CORE=$CORE_BASE
    ETC=$CORE_BASE/bin
    VAR=$CORE_BASE/bin
    LOGS=$CORE_BASE/bin
    CACHE=$CORE_BASE/bin
    DATA=$CORE_BASE/bin
    ;;
  *)
    end 1 "Invalid layout choice"
    ;;
esac

# --- Save config to repo (gitignored) so Deployer can read it ---
mkdir -p "$BASEDIR/config"

cat > "$BASEDIR/config/install.ini" <<CONF
# Generated by install.sh — $(date +"%Y-%m-%d %H:%M")
LAYOUT='$LAYOUT'
OSVERSION='${OSVERSION:-}'
OSDOWNLOAD='${OSDOWNLOAD:-}'
INSTALLPATH='${INSTALLPATH:-}'
ETC='$ETC'
VAR='$VAR'
CORE_BASE='$CORE_BASE'
LOGS='$LOGS'
CACHE='$CACHE'
DATA='$DATA'
CONF
log "Install preferences saved to $BASEDIR/config/install.ini"

# --- Summary + confirm ---
cat <<EOF

Installation plan:
  OpenSim version: ${OSVERSION:-skipped}
  Layout:          $LAYOUT
  INSTALLPATH:     $INSTALLPATH
  CORE:            $CORE
  ETC:             $ETC
  VAR:             $VAR
  DATA:            $DATA
  CACHE:           $CACHE
  LOGS:            $LOGS

EOF

yesno -y "Create directories and proceed?" || end 0 "Aborted"
end DEBUG

# --- Create ETC and write paths.ini ---
mkdir -p "$ETC" 2>/dev/null || sudo mkdir -p "$ETC" || end $? "Could not create $ETC"
{
  echo "# Generated by install.sh — layout: $LAYOUT"
  echo "INSTALLPATH='$INSTALLPATH'"
  echo "CORE='$CORE'"
  echo "ETC='$ETC'"
  echo "VAR='$VAR'"
  echo "LOGS='$LOGS'"
  echo "CACHE='$CACHE'"
  echo "DATA='$DATA'"
} | sudo tee "$ETC/paths.ini" > /dev/null
log "Wrote $ETC/paths.ini"

# --- Create standard directories ---
for dir in $SRC $VAR $CACHE $DATA \
  $ETC/opensim.d $ETC/robust.d $ETC/grids $VAR/logs $VAR/tmp
do
  [ -d "$dir" ] && continue
  mkdir -p "$dir" 2>/dev/null || sudo mkdir -p "$dir" || end $? "Could not create $dir"
  log "Created $dir"
done

# --- Download and extract OpenSim ---
if [ -n "$OSDOWNLOAD" ] && [ ! -f "$OSBIN" ]; then
  log "Downloading $OSDOWNLOAD"
  mkdir -p "$SRC" 2>/dev/null || sudo mkdir -p "$SRC" || end $? "Could not create $SRC"
  _tar=$(basename "$OSDOWNLOAD")
  if [ -f "$SRC/$_tar" ]; then
    log "Already downloaded: $SRC/$_tar"
  else
    wget -nd -P "$SRC" "$OSDOWNLOAD" \
      || end $? "Error downloading OpenSim"
  fi
  log "Unpacking to $CORE_BASE"
  mkdir -p "$CORE_BASE" 2>/dev/null || sudo mkdir -p "$CORE_BASE" || end $? "Could not create $CORE_BASE"
  pv "$SRC/$_tar" | sudo tar xzf - -C "$CORE_BASE" \
    || end $? "Error unpacking OpenSim"
  OSDIR=$CORE_BASE/$(basename "$OSDOWNLOAD" .tar.gz)
  [ -d "$OSDIR" ]    || end 1 "Unexpected: $OSDIR not found"
  OSBINDIR=$OSDIR/bin
  [ -d "$OSBINDIR" ] || end 1 "Unexpected: $OSBINDIR not found"
  OSBIN=$OSBINDIR/OpenSim.exe
  [ -f "$OSBIN" ]    || end 1 "Unexpected: $OSBIN not found"
  log "OpenSim installed: $OSDIR"
  unset _tar
fi
[ -z "$OSBINDIR" ] && [ -n "$OSDIR" ] && OSBINDIR=$OSDIR/bin

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
  $BASEDIR/libexec/newgrid "${user}s Grid" || end $?

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
