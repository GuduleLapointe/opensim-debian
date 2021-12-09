#!/bin/sh

if [ -d ../../Aurora ]; then
	# for Aurora-Sim
	mono ../../bin/Prebuild.exe /target vs2008 /targetframework v3_5
else
	# for OpenSim
	mono ../../bin/Prebuild.exe /target nant
fi
