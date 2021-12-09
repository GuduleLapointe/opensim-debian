#!/bin/sh

VER=""
if [ "$1" != "" ]; then
	VER="_"$1
fi

echo "=========================="
echo "OpenSimSearch$VER"
echo "=========================="

cd OpenSimSearch$VER
./runprebuild.sh
nant clean
nant

if [ -f ../bin/OpenSimSearch.Modules.dll ]; then
	cp -f ../bin/OpenSimSearch.Modules.dll ../../bin/
fi

