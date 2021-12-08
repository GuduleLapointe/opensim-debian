#!/bin/sh

find . -name "*~"|xargs rm -f 

rm -f bin/*.dll

rm -f OpenSim.NSLModules/Messaging/MuteList/NSLModules.Messaging.MuteList.dll.build
