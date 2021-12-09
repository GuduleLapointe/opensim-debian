#!/bin/sh

if [ ! -d ../opensim.ossearch ]; then
    (cd .. && svn co http://www.nsl.tuis.ac.jp/svn/opensim/opensim.ossearch/trunk opensim.ossearch)
else
    (cd ../opensim.ossearch && svn update)
fi


if [ ! -d ../opensim.osprofile ]; then
    (cd .. && svn co http://www.nsl.tuis.ac.jp/svn/opensim/opensim.osprofile/trunk opensim.osprofile)
else
    (cd ../opensim.osprofile && svn update)
fi
