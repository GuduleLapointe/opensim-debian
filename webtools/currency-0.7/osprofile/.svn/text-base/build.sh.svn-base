#!/bin/sh

VER=""
if [ "$1" != "" ]; then
	VER="_"$1
fi

echo "=========================="
echo "OpenSimProfile$VER"
echo "=========================="

cd OpenSimProfile$VER
./runprebuild.sh
nant clean
nant

if [ -f ../bin/OpenSimProfile.Modules.dll ]; then
	cp -f ../bin/OpenSimProfile.Modules.dll ../../bin/
fi

