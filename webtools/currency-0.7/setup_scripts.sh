#!/bin/bash

#
# Setup helper scripts for OpenSim 
#                                            by Fumi.Iseki
#

LANG=C
COMMAND="$0"

ALL_SCRIPT="NO"
SYMBL_LINK="YES"
ONLY_DWNLD="NO"

SHOW_HELP="NO"


while [ $# != 0 ]; do
    if   [ "$1" = "-a" -o "$1" = "--all" ]; then
        ALL_SCRIPT='YES' 
    elif [ "$1" = "-c" -o "$1" = "--copy" ]; then
        SYMBL_LINK="NO"
    elif [ "$1" = "-d" -o "$1" = "--download" ]; then
        ONLY_DWNLD="YES"
    elif [ "$1" = "-h" -o "$1" = "--help" ]; then
        SHOW_HELP="YES"
    fi 

    shift
done


if [ "$SHOW_HELP" = "YES" ]; then
    echo
    echo "usage... $COMMAND [-c/--copy] [-d/--download] [-a/--all] [-h/--help]"
    echo "-c or --copy     : not symbolic link but copy files"
    echo "-d or --download : download only"
    echo "-a or --all      : treat all scripts include optional scripts"
    echo "-h or --help     : show this help"
    echo
    exit 0
fi




if [ "$SYMBL_LINK" = "NO" ]; then
    if [ -f include/config.php -a ! -L include/config.php ]; then 
        mv -f include/config.php 'config.php.temp.$$$'
    fi
    rm -rf helper
    rm -rf include
fi

mkdir -p helper/sql
mkdir -p include

if [ "$SYMBL_LINK" = "NO" ]; then
    if [ -f 'config.php.temp.$$$' -a ! -L 'config.php.temp.$$$' ]; then 
        mv -f 'config.php.temp.$$$' include/config.php
    fi
fi



########################################################################
# Basic Scripts

# download Junk BoX Library
if [ -d jbxl ]; then
    svn update jbxl
else
    svn co http://www.nsl.tuis.ac.jp/svn/php/jbxl/trunk jbxl
fi

if [ "$ONLY_DWNLD" = "NO" ]; then
    if   [ "$SYMBL_LINK" = "YES" ]; then
        ln -sf ../jbxl/jbxl_tools.php include/jbxl_tools.php
        ln -sf ../jbxl/tools.func.php include/tools.func.php
    elif [ "$SYMBL_LINK" = "NO" ]; then
        cp -puf jbxl/jbxl_tools.php include/jbxl_tools.php
        cp -puf jbxl/tools.func.php include/tools.func.php
    fi
fi


# download flotsam_XmlRpcGroup
if [ -d flotsam_XmlRpcGroup ]; then
    svn update flotsam_XmlRpcGroup
else
    svn co http://www.nsl.tuis.ac.jp/svn/opensim/flotsam_XmlRpcGroup/trunk flotsam_XmlRpcGroup
fi

if [ "$ONLY_DWNLD" = "NO" ]; then
    if   [ "$SYMBL_LINK" = "YES" ]; then
        ln -sf ../flotsam_XmlRpcGroup/xmlgroups.php helper/xmlgroups.php
        ln -sf ../flotsam_XmlRpcGroup/xmlgroups_config.php helper/xmlgroups_config.php
        ln -sf ../flotsam_XmlRpcGroup/xmlrpc.php helper/xmlrpc.php
        ln -sf ../flotsam_XmlRpcGroup/xmlrpci.php helper/xmlrpci.php
        if [ -d helper/phpxmlrpclib ]; then
            rm -rf helper/phpxmlrpclib
        fi
        ln -sf ../flotsam_XmlRpcGroup/phpxmlrpclib helper/phpxmlrpclib
        ln -sf ../../flotsam_XmlRpcGroup/sql/groups.sql helper/sql/groups.sql
    elif [ "$SYMBL_LINK" = "NO" ]; then
        cp -puf flotsam_XmlRpcGroup/xmlgroups.php helper/xmlgroups.php
        cp -puf flotsam_XmlRpcGroup/xmlgroups_config.php helper/xmlgroups_config.php
        cp -puf flotsam_XmlRpcGroup/xmlrpc.php helper/xmlrpc.php
        cp -puf flotsam_XmlRpcGroup/xmlrpci.php helper/xmlrpci.php
        cp -Rpdf flotsam_XmlRpcGroup/phpxmlrpclib helper/phpxmlrpclib
        cp -puf flotsam_XmlRpcGroup/sql/groups.sql helper/sql/groups.sql
    fi
fi


# download opensim.helper
if [ -d opensim.helper ]; then
    svn update opensim.helper
else
    svn co http://www.nsl.tuis.ac.jp/svn/opensim/opensim.helper/trunk opensim.helper
fi

if [ "$ONLY_DWNLD" = "NO" ]; then
    if   [ "$SYMBL_LINK" = "YES" ]; then
        ln -sf ../opensim.helper/currency.php helper/currency.php
        ln -sf ../opensim.helper/helpers.php helper/helpers.php
        ln -sf ../opensim.helper/landtool.php helper/landtool.php
        ln -sf ../opensim.helper/offline.php helper/offline.php
        ln -sf ../../opensim.helper/sql/offline.sql helper/sql/offline.sql
    elif [ "$SYMBL_LINK" = "NO" ]; then
        cp -puf opensim.helper/currency.php helper/currency.php
        cp -puf opensim.helper/helpers.php helper/helpers.php
        cp -puf opensim.helper/landtool.php helper/landtool.php
        cp -puf opensim.helper/offline.php helper/offline.php
        cp -puf opensim.helper/sql/offline.sql helper/sql/offline.sql
    fi
fi


# download opensim.phplib
if [ -d opensim.phplib ]; then
    svn update opensim.phplib
else
    svn co http://www.nsl.tuis.ac.jp/svn/opensim/opensim.phplib/trunk opensim.phplib
fi

if [ "$ONLY_DWNLD" = "NO" ]; then
    if   [ "$SYMBL_LINK" = "YES" ]; then
        ln -sf ../opensim.phplib/env.mysql.php include/env.mysql.php
        ln -sf ../opensim.phplib/mysql.func.php include/mysql.func.php
        ln -sf ../opensim.phplib/opensim.mysql.php include/opensim.mysql.php
    elif [ "$SYMBL_LINK" = "NO" ]; then
        cp -puf opensim.phplib/env.mysql.php include/env.mysql.php
        cp -puf opensim.phplib/mysql.func.php include/mysql.func.php
        cp -puf opensim.phplib/opensim.mysql.php include/opensim.mysql.php
    fi
fi



########################################################################
# Optional Scripts

if [ "$ALL_SCRIPT" = "YES" ]; then

# download opensim.modules
if [ -d opensim.modules ]; then
    svn update opensim.modules
else
    svn co http://www.nsl.tuis.ac.jp/svn/opensim/opensim.modules/trunk opensim.modules
fi

if [ "$ONLY_DWNLD" = "NO" ]; then
    if   [ "$SYMBL_LINK" = "YES" ]; then
        ln -sf ../opensim.modules/Messaging/php/mute.php helper/mute.php
        ln -sf ../opensim.modules/Profile/php/profile.php  helper/profile.php
        ln -sf ../opensim.modules/Profile/php/profile_config.php helper/profile_config.php
        ln -sf ../opensim.modules/Search/php/parser.php helper/parser.php
        ln -sf ../opensim.modules/Search/php/query.php  helper/query.php
        ln -sf ../opensim.modules/Search/php/register.php helper/register.php
        ln -sf ../opensim.modules/Search/php/search_config.php helper/search_config.php
        ln -sf ../../opensim.modules/Messaging/sql/mute.sql helper/sql/mute.sql
        ln -sf ../../opensim.modules/Profile/sql/osprofile.sql helper/sql/osprofile.sql
        ln -sf ../../opensim.modules/Search/sql/ossearch.sql  helper/sql/ossearch.sql
   elif [ "$SYMBL_LINK" = "NO" ]; then
        cp -puf opensim.modules/Messaging/php/mute.php helper/mute.php
        cp -puf opensim.modules/Profile/php/profile.php  helper/profile.php
        cp -puf opensim.modules/Profile/php/profile_config.php helper/profile_config.php
        cp -puf opensim.modules/Search/php/parser.php helper/parser.php
        cp -puf opensim.modules/Search/php/query.php  helper/query.php
        cp -puf opensim.modules/Search/php/register.php helper/register.php
        cp -puf opensim.modules/Search/php/search_config.php helper/search_config.php
        cp -puf opensim.modules/Messaging/sql/mute.sql helper/sql/mute.sql
        cp -puf opensim.modules/Profile/sql/osprofile.sql helper/sql/osprofile.sql
        cp -puf opensim.modules/Search/sql/ossearch.sql  helper/sql/ossearch.sql
    fi
fi

fi  # ALL_SCRIPT



########################################################################
#

if [ "$ONLY_DWNLD" = "NO" ]; then
    if   [ "$SYMBL_LINK" = "YES" ]; then
        ln -sf ../config/cron.php include/cron.php
        ln -sf ../config/env_interface.php include/env_interface.php
        ln -sf ../config/index.html helper/index.html
        ln -sf ../config/index.html include/index.html
        if [ ! -f include/config.php ]; then 
            ln -sf ../config/config.php include/config.php
        fi
    elif [ "$SYMBL_LINK" = "NO" ]; then
        cp -puf config/cron.php include/cron.php
        cp -puf config/env_interface.php include/env_interface.php
        cp -puf config/index.html helper/index.html
        cp -puf config/index.html include/index.html
        if [ ! -f include/config.php ]; then 
            cp -puf config/config.php include/config.php
        fi
    fi
fi

