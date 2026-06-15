#!/usr/bin/env bash

# Copyright 2015 Olivier van Helden <olivier@van-helden.net>
# Released under GNU Affero GPL v3.0 license
#    http://www.gnu.org/licenses/agpl-3.0.html

set -euo pipefail

OSDOWNLOADPAGE=http://opensimulator.org/dist

BASEDIR=$(dirname $(dirname $(realpath "$0")))
. $BASEDIR/libexec/os-helpers || exit 1
trap 'rm -f $TMP*' EXIT

crudget $TMP.conf Defaults

log "Config loaded
  OpenSim version:   ${OpensimVersion:-}
  Layout:            ${DirectoryLayout:-}
  Install base:      ${InstallPath:-}
  Core Directory:    ${CoreDirectory:-}
  Etc Directory:     ${EtcRoot:-}
  Var Directory:     ${VarRoot:-}
  Data Directory:    ${DataRoot:-}
  Cache Directory:   ${CacheRoot:-}
  Logs Directory:    ${LogsRoot:-}
  Sources:           ${SourcesDirectory:-}"

# Package manager abstraction
case "$(uname -s)" in
Darwin)
	pkg_install() { brew install "$@"; }
	pkg_update() { brew update && brew upgrade; }
	pkg_check() { brew list "$1" &>/dev/null; }
	which brew >/dev/null || end 1 "Homebrew required on macOS: https://brew.sh"
	;;
Linux)
	which apt-get >/dev/null ||
		end 1 "Unsupported Linux distribution (no apt-get)"
	pkg_install() { sudo apt-get install -y "$@"; }
	pkg_update() { sudo apt-get update && sudo apt-get upgrade -y; }
	pkg_check() { dpkg -l "$1" 2>/dev/null | grep -q "^ii"; }
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

# # Python venv + crudini (isolated from system Python)
# . $BASEDIR/libexec/venv-setup || end $? "Could not set up Python venv"
# log "using $(which python3)"
# require crudini

echo "Initialize submodules" >&2
git submodule update --init

yesno "Update system packages?" && {
	pkg_update || log $? "System update failed, continuing anyway"
}

# Runtime (mono vs .NET, and the exact .NET version) depends on the OpenSim
# version and is read from the extracted binaries, so it is installed further
# down, once the core is unpacked.

# --- OpenSim version selection (no download yet) ---
echo ""
log "Fetching OpenSimulator release list from $OSDOWNLOADPAGE ..."
_releases=$(curl -s "$OSDOWNLOADPAGE/" |
	grep -o 'href="opensim-[0-9][^"]*\.tar\.gz"' |
	grep -v source |
	cut -d'"' -f2 |
	sort -r)
[ -n "$_releases" ] || end 1 "Could not fetch release list from $OSDOWNLOADPAGE"

echo ""
echo "OpenSimulator version to install:"
i=1
while IFS= read -r _f; do
	_name=$(basename "$_f" .tar.gz)
	if [ -d "${CoreRoot:-}/$_name" ] || [ -d "$BASEDIR/core/$_name" ]; then
		printf "  %2d) %s  (installed)\n" "$i" "$_name"
	else
		printf "  %2d) %s\n" "$i" "$_name"
	fi
	i=$((i + 1))
done <<<"$_releases"
unset _name
echo "   d) Development version (build from source — not yet implemented)"
echo "   s) Skip (install OpenSim manually later)"
echo ""
read -p "  Version [1]: " _vchoice
_vchoice=${_vchoice:-1}

case "$_vchoice" in
d | D)
	OpensimVersion=dev
	OSDOWNLOAD=
	;;
s | S)
	OpensimVersion=
	OSDOWNLOAD=
	;;
[0-9]*)
	_vsel=$(echo "$_releases" | sed -n "${_vchoice}p")
	[ -n "$_vsel" ] || end 1 "Invalid choice: $_vchoice"
	OpensimVersion=$(basename "$_vsel" .tar.gz | sed 's/^opensim-//')
	OSDOWNLOAD="$OSDOWNLOADPAGE/$_vsel"
	;;
*) end 1 "Invalid choice: $_vchoice" ;;
esac
unset _releases _f _vchoice _vsel i

# --- Runtime gate (discriminating) -------------------------------------------
# A valid runtime for the chosen version is required to go any further, so we
# decide it now, before planning or downloading anything. The mono/.NET split
# is by OpenSim version; for .NET the exact version is re-verified against the
# binaries at launch (bin/opensim). Update dnMajor if a future OpenSim targets
# a newer .NET.

# Install the .NET runtime <major> into the active dotnet root (so the existing
# 'dotnet' finds it), or ~/.dotnet if none is installed yet.
installDotnet() {
	local major=$1 dir p _sudo
	if p=$(command -v dotnet 2>/dev/null); then
		dir=$(dirname "$(realpath "$p" 2>/dev/null || echo "$p")")
	else
		dir="$HOME/.dotnet"
	fi
	[ -w "$dir" ] && _sudo="" || _sudo="sudo"
	log "Installing .NET $major runtime into $dir"
	curl -fsSL https://builds.dotnet.microsoft.com/dotnet/scripts/v1/dotnet-install.sh >"$TMP.dotnet-install.sh" ||
		end $? "Could not fetch dotnet-install.sh"
	$_sudo bash "$TMP.dotnet-install.sh" --runtime dotnet --channel "$major.0" --install-dir "$dir" ||
		end $? ".NET $major runtime installation failed"
	export DOTNET_ROOT="$dir"
	export PATH="$dir:$PATH"
}

if [ -z "$OpensimVersion" ] || [ "$OpensimVersion" = "dev" ]; then
	: # no specific release selected yet; the runtime is checked at launch
elif version_ge "$OpensimVersion" 0.9.3.0; then
	dnMajor=8 # OpenSim 0.9.3.x targets .NET 8 (net8.0)
	installed=$(dotnet --list-runtimes 2>/dev/null |
		grep -o 'Microsoft.NETCore.App [0-9.]*' | awk '{print $2}' || true)
	compatible=$(echo "$installed" | awk -F. -v m=$dnMajor 'NF && $1>=m{print; exit}' || true)
	if echo "$installed" | grep -q "^$dnMajor\."; then
		log ".NET $dnMajor installed; OpenSim $OpensimVersion will use it"
	elif [ -n "$compatible" ]; then
		# A newer .NET is present: runs via roll-forward, or install native.
		echo ""
		echo "OpenSim $OpensimVersion targets .NET $dnMajor."
		echo "Installed: $(echo $installed | tr '\n' ' ')"
		echo "It can run as-is on your newer .NET via roll-forward (no install),"
		echo "or you can install the native .NET $dnMajor (closer to the tested setup)."
		echo "  1) Use roll-forward, no install (default)"
		echo "  2) Install native .NET $dnMajor"
		read -p "  Choice [1]: " _dnchoice
		[ "${_dnchoice:-1}" = "2" ] && installDotnet "$dnMajor" ||
			log "OpenSim will run on the installed .NET via roll-forward"
	else
		# No usable .NET (none, or only older than the target): hard requirement.
		[ -n "$installed" ] &&
			echo "Installed .NET ($(echo $installed | tr '\n' ' ')) is older than the required $dnMajor."
		yesno -y "OpenSim $OpensimVersion needs .NET $dnMajor; install it now?" &&
			installDotnet "$dnMajor" ||
			end 1 "OpenSim $OpensimVersion cannot run without .NET $dnMajor"
	fi
else
	# OpenSim < 0.9.3.0 runs on Mono.
	if which mono >/dev/null 2>&1; then
		log "mono present: $(mono --version | head -1)"
	else
		yesno -y "OpenSim $OpensimVersion needs Mono; install it now?" &&
			pkg_install mono-complete ||
			end 1 "OpenSim $OpensimVersion cannot run without Mono"
	fi
fi

# --- Layout selection ---
echo ""
echo "Installation layout:"
echo "  1) Flat      — OpenSim's default layout, all files in core directory"
echo "  2) Bundled   — Organized structure under a single directory: /opt/opensim, ~/opensim, ..."
echo "  3) System    — Standard Linux paths: /etc/opensim, /var/lib/opensim, /usr/local/share/opensim"
echo ""

DirectoryLayout=${DirectoryLayout:-1}
# read -p "  Layout [1]: " DirectoryLayout
readvar DirectoryLayout

## Set install base directory
case "${DirectoryLayout:-1}" in
3 | system | debian)
	DirectoryLayout=system
	BaseInstallPath=/usr/local/share/opensim
	;;
2 | bundled)
	DirectoryLayout=bundled
	BaseInstallPath=/opt/opensim
	readvar BaseInstallPath
	;;
1 | flat)
	DirectoryLayout=flat
	BaseInstallPath=$BASEDIR/core
	readvar BaseInstallPath
	;;
*)
	end 1 "Invalid layout choice"
	;;
esac

InstallPath=$BaseInstallPath
log "InstallPath=$InstallPath"

case "${DirectoryLayout:-1}" in
system)
	EtcRoot=/etc/opensim
	VarRoot=/var/lib/opensim
	CoreRoot=/usr/local/share/opensim
	CoreDirectory=$CoreRoot/opensim-$OpensimVersion
	LogsRoot=/var/log/opensim
	CacheRoot=/var/cache/opensim
	DataRoot=/var/lib/opensim/data
	;;
bundled)
	EtcRoot=$InstallPath/etc
	VarRoot=$InstallPath/var
	CoreRoot=$InstallPath/core
	CoreDirectory=$CoreRoot/opensim-$OpensimVersion
	LogsRoot=$InstallPath/var/logs
	CacheRoot=$InstallPath/var/cache
	DataRoot=$InstallPath/var/data
	;;
flat)
	CoreRoot=$InstallPath/opensim-$OpensimVersion
	CoreDirectory=$CoreRoot
	EtcRoot=$CoreRoot/bin
	VarRoot=$CoreRoot/bin
	LogsRoot=$CoreRoot/bin
	CacheRoot=$CoreRoot/bin
	DataRoot=$CoreRoot/bin
	;;
*)
	end 1 "Invalid layout choice"
	;;
esac

SourcesDirectory=${SourcesDirectory:-$BASEDIR/src}

# Write the single config file. [Defaults] holds the shared, version-independent
# locations and the default version; each installed version gets its own
# [opensim-X.Y.Z] section (additive -- other versions are left untouched).
# crudini --set updates only the listed keys.
writeOpensimConf() {
	_conf="$EtcRoot/$CONF"
	version_ge "$OpensimVersion" 0.9.3.0 && _rt=dotnet || _rt=mono

	crudini --set "$_conf" Defaults DirectoryLayout "$DirectoryLayout"
	crudini --set "$_conf" Defaults CoreRoot "$CoreRoot"
	crudini --set "$_conf" Defaults EtcRoot "$EtcRoot"
	crudini --set "$_conf" Defaults VarRoot "$VarRoot"
	crudini --set "$_conf" Defaults LogsRoot "$LogsRoot"
	crudini --set "$_conf" Defaults CacheRoot "$CacheRoot"
	crudini --set "$_conf" Defaults DataRoot "$DataRoot"

	# This version's own section; other [opensim-*] sections are preserved.
	crudini --set "$_conf" "opensim-$OpensimVersion" CoreDirectory "$CoreDirectory"
	crudini --set "$_conf" "opensim-$OpensimVersion" Runtime "$_rt"

	# Default version: set it if none yet, otherwise ask before changing it.
	_cur=$(crudini --get "$_conf" Defaults Version 2>/dev/null || true)
	if [ -z "$_cur" ]; then
		crudini --set "$_conf" Defaults Version "$OpensimVersion"
	elif [ "$_cur" != "$OpensimVersion" ] &&
		yesno "Make $OpensimVersion the default version (current default: $_cur)?"; then
		crudini --set "$_conf" Defaults Version "$OpensimVersion"
	else
		log "Default version kept: ${_cur:-$OpensimVersion}"
	fi

	# Predictable per-user path -> the canonical file in EtcRoot.
	mkdir -p "$HOME/.config/opensim"
	ln -sfn "$_conf" "$HOME/.config/opensim/$CONF"
	log "Config written: $_conf (linked from ~/.config/opensim/$CONF)"
	unset _conf _rt _cur
}

# --- Summary + confirm ---
cat <<EOF

Installation plan:
  OpenSim version:   ${OpensimVersion:-skipped}
  Layout:            $DirectoryLayout
  Install base:      $InstallPath
  Core Directory:    $CoreDirectory
  Etc Directory:     $EtcRoot
  Var Directory:     $VarRoot
  Data Directory:    $DataRoot
  Cache Directory:   $CacheRoot
  Logs Directory:    $LogsRoot
  Sources:           $SourcesDirectory

EOF

yesno -y "Create directories and proceed?" || end 0 "Aborted"

# --- Create directories, owned by current user ---
for dir in \
	"$EtcRoot" "$EtcRoot/opensim.d" "$EtcRoot/robust.d" "$EtcRoot/grids" \
	"$SourcesDirectory" "$CoreRoot" "$CoreDirectory" \
	"$VarRoot" "$LogsRoot" "$CacheRoot" "$DataRoot"; do
	[ -d "$dir" ] && continue
	[ "$VERBOSE" = "yes" ] && v="-v" || v=
	sudo install $v -d -o "$USER" "$dir" || end $? "Could not create $dir"
done

# --- Write the config for this installation ---
writeOpensimConf

# --- Download and extract OpenSim ---

if [ -n "$OSDOWNLOAD" ]; then
	_tar_name=$(basename "$OSDOWNLOAD")
	_tar_path=$SourcesDirectory/$_tar_name
	# if [ -n "$OSDOWNLOAD" ] && [ ! -f "${OpenSimExe:-}" ]; then
	if [ -f "$_tar_path" ]; then
		log "Using previous download $_tar_path"
	else
		log "Downloading $OSDOWNLOAD"
		wget -nd -P "$SourcesDirectory" "$OSDOWNLOAD" ||
			end $? "Error downloading OpenSim"
	fi
	[ -f "$_tar_path" ] || end $? "Unexpected: $_tar_path not found"

	log "Unpacking $_tar_name to $CoreRoot"

	pv "$_tar_path" | tar xzf - -C "$CoreRoot" ||
		end $? "Error unpacking OpenSim"

	log "OpenSim installed: $CoreDirectory"
	unset _tar_name
	unset _tar_path
fi

##
# Last checks
#
[ -d "$CoreDirectory" ] || end 1 "Unexpected: $CoreDirectory not found"
[ -d "$CoreDirectory/bin" ] || end 1 "Unexpected: $CoreDirectory/bin not found"
[ -f "$CoreDirectory/bin/OpenSim.exe" ] || end 1 "Unexpected: $CoreDirectory/bin/OpenSim.exe not found"

##
# Launch new grid config
#
if yesno -y "Create Robust config?"; then
	# user=$(getent passwd $USER | cut -d : -f 5 | cut -d , -f 1 | cut -d " " -f 1 | grep -i [a-z] || echo "$USER" | sed -r -e 's/(\W)/\L\1/g' -e 's/(^|[ _-])(\w)/\U\2/g')
	$BASEDIR/libexec/newgrid || end $?
	# "${user}s Grid"
fi

end

##
# What follows is old code
# Keep until transition is finished, only for reference
#

#cd "$OpenSimExe" || end 2 could not cd to $OpenSimExe
#(
#find -name "*.ini"
#find -name "*.ini.example"
#find -name "*.config"
#) | sed "s%\./%%" | sed "s/\.example$//" | sort -u | while read file
#do
#	[ -f "$EtcRoot/$file" ] && continue
#	folder="$(dirname "$EtcRoot/$file")"
#	[ -d "$folder" ] || mkdir -p "$folder" || end 4 could not create $folder
#	cp $OpenSimExe/$file $EtcRoot/$file 2>/dev/null \
#		|| cp $OpenSimExe/$file.example $EtcRoot/$file 2>/dev/null \
#		|| end 4 could not copy $file
#done

# CacheRoot=$VarRoot/cache
# DataRoot=$VarRoot/data
#
# OpenSimBinDirectory=$BinDirectory
# readvar OpenSimBinDirectory

# log setting defaults
# crudini --set $TMP.new.ini Launch BinDir "\"$OpenSimBinDirectory\""
# crudini --set $TMP.new.ini Launch Executable "\"Robust.exe\#"
# cleanupIni $BinDirectory/Robust.HG.ini.example > $TMP.defaults.ini
# crudmerge $TMP.new.ini $TMP.defaults.ini
# crudmerge $TMP.new.ini $BASEDIR/install/Robust.Tweaks.ini
# crudini --set $TMP.new.ini DatabaseService ConnectionString "\"Data Source=localhost;Database=os_$(hostname -s);User ID=opensim;Password=password;Old Guids=true;\""
#
# log "## Choose robust config"
#
# RobustConfig=$(
#   (
#   ls $EtcRoot/robust.d/*.ini 2>/dev/null
#   # ls $EtcRoot/robust-enabled/*.ini
# 	# ls $EtcRoot/robust-available/*.ini
#   # ls $EtcRoot/opensim.d/Robust*.ini $EtcRoot/opensim.d/robust*.ini
# 	# echo "$EtcRoot/robust.d/NewRobust.ini"
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
# [ ! "$RobustConfig" ] && RobustConfig=$EtcRoot/robust.d/$RobustName.ini
# # [ ! -f "$RobustConfig" ] &&  touch $RobustConfig
#
# MachineName=$(echo "$GridNick" | tr "[:upper:]" "[:lower:]")
# log "MachineName $MachineName"
#
# log 1 "## General settings"
# eval $(crudini --get --format=sh $TMP.new.ini Const \
# | sed -e "s/baseurl/BaseURL/" -e "s/publicport/PublicPort/" -e "s/privateport/PrivatePort/" \
# -e "s/cachedirectory/CacheRoot/" -e "s/datadirectory/DataRoot/" \
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
# crudini --set $TMP.new.ini Const CacheRoot "\"$CacheRoot/$MachineName\""
# crudini --set $TMP.new.ini Const DataRoot "\"$DataRoot/$MachineName\""
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
# if [ -f "$EtcRoot/$GridNick.Gloebit.ini" -o -f "$EtcRoot/Gloebit.ini" ]
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
#     && sed -i~ "s/^$regionvar *= *\"\(.*\)\"/$regionvar = \"\\1, $flag\"/" $TMP.regions \
#     || echo "$regionvar = \"$flag\"" >> $TMP.regions
# done
# crudmerge $TMP.new.ini $TMP.regions
#
# ## Set robust name based on confif filename
# # enable="$EtcRoot/robust-enabled/$RobustName.ini"
#
# log "## Setting Launcher info"
# crudini --set $TMP.new.ini Launch BinDir "\"$BinDirectory\""
# crudini --set $TMP.new.ini Launch Executable "\"Robust.exe\""
# crudini --set $TMP.new.ini Launch LogFile "\"$LogsRoot/$MachineName.log\""
# crudini --set $TMP.new.ini Launch ConsolePrompt "\"$RobustName ($hostname:$PublicPort)\""
#
# log "## Startup section"
# crudini --set $TMP.new.ini Startup ConfigDirectory "$EtcRoot/robust-include"
# crudini --set $TMP.new.ini Startup PIDFile "\"\${Const|CacheRoot}/$MachineName.pid\""
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
#   $DataRoot/$MachineName $DataRoot/$MachineName/fsassets \
#   $CacheRoot/$MachineName/bakes $CacheRoot/$MachineName/fsassets $CacheRoot/$MachineName/maptiles \
#   $CacheRoot/$MachineName/registry
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
# cat $BinDirectory/Robust.exe.config \
# | sed "s%\(<file value=\"\)Robust%\\1$LogsRoot/$RobustName%" \
# > "$DataRoot/$RobustName.logconfig"
#
# # if [ ! -f "$EtcRoot/opensim.ini" ]
# # then
# #   echo "myhost=$newhost
# #   mydb=$newdb
# #   myuser=$newuser
# #   mypass=$newpass" > "$EtcRoot/opensim.ini"
# # fi
