#!/bin/sh

VER=""
if [ "$1" != "" ]; then
	VER="_"$1
fi


echo "=========================="
echo "NSL_MODULES$VER"
echo "=========================="


NMDIR=`pwd`
rm -f bin/*.dll


MUTEMOD="NSLModules.Messaging.MuteList.dll"

cd OpenSim.NSLModules$VER
./runprebuild.sh
nant clean
nant
cd $NMDIR

if [ -f bin/$MUTEMOD ]; then
	cp bin/$MUTEMOD ../bin
fi



#
# Other External Modules
#

# OS Profile
PROFDIR="N"
PROFMOD="OpenSimProfile.Modules.dll"

if [ -d ../opensim.osprofile ]; then
	cd ../opensim.osprofile
	PROFDIR="Y"
elif [ -d ../osprofile ]; then
	cd ../osprofile
	PROFDIR="Y"
fi

if [ "$PROFDIR" = "Y" ]; then
	./build.sh
	cp bin/$PROFMOD $NMDIR/bin
	cd $NMDIR
fi


# OS Search
SRCHDIR="N"
SRCHMOD="OpenSimSearch.Modules.dll"

if [ -d ../opensim.ossearch ]; then
	cd ../opensim.ossearch
	SRCHDIR="Y"
elif [ -d ../ossearch ]; then
	cd ../ossearch
	SRCHDIR="Y"
fi

if [ $SRCHDIR = "Y" ]; then
	./build.sh
	cp bin/$SRCHMOD $NMDIR/bin
	cd $NMDIR
fi



#
#
#
echo  
ls -l bin/*.dll
echo

