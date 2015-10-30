#!/bin/bash

DEBUG=yes

export PATH=$PATH:$(dirname "$0")
which os-helpers | grep -q helper || exit 1
. $(which os-helpers)

if [ ! -d $ETC ]
then
	log  "create /etc/opensim"
	sudo mkdir $ETC || end 3 could not mkdir etc
	sudo chown $USER:$USER $ETC || sudo chown $USER $ETC || end 3 could not set etc user
fi

log "loading submodules"
git submodule init
git submodule update

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

mkdir -p $ETC/robust-available $ETC/robust-enabled $ETC/simulator-available $ETC/simulator-enabled || end 6
mkdir -p $LIB $CACHE $OSLOG || end 6 lib cache oslos

cd "$ETC"
echo "## Configuration de Robust"

hostname=$(hostname -f)
echo "$PGM: choose your server address"
(
ip addr show | grep "inet " | cut -d "/" -f 1 | sed "s/.*inet *//"
echo $hostname 
) | sed "s/^/   /"

. "$(which ini_parser)" || end 2 could not launch ini parser

read -p "ConfigName [Robust] " ConfigName
[ "$ConfigName" ] || ConfigName=Robust

default=$OSBIN/Robust.HG.ini
config="$ETC/robust-available/$ConfigName.ini"
enable="$ETC/robust-enabled/$ConfigName.ini"

#cat "$config" | sed '/\[Launch]/,/^\[/!d' | sed "$ d" > $TMP.ini
cat "$config" | sed '/\[GridInfoService]/,/^\[/!d' | sed "$ d" | inigrep "gridname|gridnick" >> $TMP.ini
ini_parser "$TMP.ini" || continue
ini_section_GridInfoService

read -p "Grid name (long) [$gridname] " newgridname
[ ! "$newgridname" ] && newgridname=$gridname
echo "gridname $newgridname" >> $TMP.vars
read -p "Grid name (short)  [$gridnick] " newgridnick
[ ! "$newgridnick" ] && newgridnick=$gridnick
echo "gridnick $newgridnick" >> $TMP.vars

read -p "myshost [$myhost] " newhost
[ "$newhost" ] || newhost=$myhost
read -p "mydb [$mydb] " newdb
[ "$newdb" ] || newdb=$mydb
read -p "myuser [$myuser] " newuser
[ "$newuser" ] || newuser=$myuser
read -p "mypass [$mypass] " newpass
[ "$newpass" ] || newpass=$mypass


read -p "BaseURL [$hostname] " BaseURL
[ "$BaseURL" ] || BaseURL=$hostname
echo "$BaseURL" | grep -q "^https*://" || BaseURL="http://$BaseURL"
echo "BaseURL $BaseURL" >> $TMP.vars


read -p "Type the base url [http://$hostname] " BaseURL
[ "$BaseURL" ] || BaseURL=$hostname
echo "$BaseURL" | grep -q "^https*://" || BaseURL="http://$BaseURL"
echo "BaseURL $BaseURL" >> $TMP.vars

read -p "PublicPort [8002] " PublicPort
echo "$" | grep -q "^[0-9][0-9]*$" || PublicPort=8002
echo "PublicPort $PublicPort" >> $TMP.vars

read -p "PrivatePort [8003] " PrivatePort
echo "$PrivatePort" | grep -q "^[0-9][0-9]*$" || PrivatePort=8003
echo "PrivatePort $PrivatePort" >> $TMP.vars

echo "Logfile $OSLOG/$ConfigName.log" >> $TMP.vars
echo "ConsoleHistoryFile $OSLOG/RobustConsoleHistory.txt" >> $TMP.vars
echo "ConfigDirectory $VAR/Config" >> $TMP.vars
echo "RegistryLocation $VAR/Registry" >> $TMP.vars
echo "MapTileDirectory $CACHE/maptiles" >> $TMP.vars
echo "TilesStoragePath $CACHE/maptiles" >> $TMP.vars
echo "BaseDirectory $CACHE/bakes" >> $TMP.vars
echo "BinDir $OSBIN" >> $TMP.vars
echo "ConnectionString Data Source=$newhost;Database=$newdb;User ID=$newuser;Password=$newpass;Old Guids=true;" >> $TMP.vars

(
	echo "[Launch]"
	echo "  BinDir = "$OSBIN";"
	echo "  Executable = \"Robust.exe\""
	echo "  logfile = \"$OSLOG/$ConfigName.log\""
	echo "  ConsolePrompt = \"$ConfigName ($hostname:$PublicPort/$PrivatePort) \""
) > $TMP.Robust.HG.ini
cat $default >> $TMP.Robust.HG.ini

printf "inigrep \"" > $TMP.inigrep

for var in HomeURI TilesStoragePath BinDir Executable logfile ConsolePrompt MapTileDirectory SearchURL DestinationGuide AvatarPicker welcome economy about register help password 
do
	sed -i -e "s%^\([[:blank:]]*\);;* *$var *=%\\1$var =%" $TMP.Robust.HG.ini
	printf "$var =|" >> $TMP.inigrep
done
unset IFS
cat $TMP.vars | while read var value
do
	log "adding $var=$value"
	sed -i "s%\([[:blank:];]*$var\) *=.*%\\1 = \"$value\"%" $TMP.Robust.HG.ini
	printf "$var =|" >> $TMP.inigrep
done
echo  "^no more args\" $TMP.Robust.HG.ini" >> $TMP.inigrep

echo
echo "## Main values after changes"
. $TMP.inigrep 2>/dev/null

if [ -f "$config" ]
then
	read -p "File $config exists, override? (y/N)" yesno 
else
	read -p "Save in $config file? (y/N)" yesno 
fi
[ "$yesno" = "y" ] || end Aborted

mv $TMP.Robust.HG.ini "$ETC/robust-available/$ConfigName.ini" \
	&& echo "$config saved"
[ ! -f "$enable" ] && ln -s "$config" "$enable"

cat $ETC/Robust.exe.config \
	| sed "s%\(<file value=\"\)Robust%\\1$OSLOG/$ConfigName%" \
	> "$ETC/$ConfigName.logconfig"

if [ ! -f "$ETC/opensim.conf" ]
then
	echo "myhost=$newhost
mydb=$newdb
myuser=$newuser
mypass=$newpass" > "$ETC/opensim.conf"
fi
	
end ""

if [ ! -f "$ETC/robust-available/Robust.HG.ini" ]
then
	inigrep "BaseURL =|127.0.0.1|PublicPort =|8002|PrivatePort =|8003|\"\.\"|RegistryLocation|ConfigDirectory|ConsolePrompt" \
		$default 2>/dev/null \
		> "$ETC/robust-available/Robust.HG.ini"
fi
cat "$ETC/robust-available/Robust.HG.ini"

end ""
