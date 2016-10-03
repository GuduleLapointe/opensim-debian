// comboReadPrefs
string version = "1.8.1";
// Author: Gudule Lapointe gudule@speculoos.world
//
// Read preferences from a notecard and send values to internalChannel
// for other scripts
// Default preferences file is set by prefsFile variable, but if not present
// look if there is only one notecard and use it instead.

// Copyright (C) 2010-2016  Gudule Lapointe gudule@speculoos.world
//
// This program is free software: you can redistribute it and/or modify
// it under the terms of the GNU Affero General Public License as published
// by the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// This program is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU Affero General Public License for more details.
//
// You should have received a copy of the GNU Affero General Public License
// along with this program.  If not, see <http://www.gnu.org/licenses/>.

integer prefsChannel=17;
string prefsFile = "!Combo-preferences";
integer currentLine;
key requestID;

integer setObjectName = FALSE;
string objectBaseName = "Object";
string objectDesc = "";
string objectName;

list allowedVars = []; // don't use for now, avoid hardcoded values

debug(string message)
{
    llOwnerSay("/me DEBUG: " + message);
}
integer setPrefsFile() {
    if(llGetInventoryType(prefsFile) == INVENTORY_NOTECARD) {
//        debug("default prefs file " + prefsFile);
        return TRUE;
    } else if (llGetInventoryNumber(INVENTORY_NOTECARD) == 1) {
        prefsFile = llGetInventoryName(INVENTORY_NOTECARD, 0);
//        debug("prefs file set to " + prefsFile);
        return TRUE;
    } else {
//        debug ("no notecard found");
        prefsFile = "";
        return FALSE;
    }
}

parsePrefs()
{
    if (! setPrefsFile()) return;
    if(llGetInventoryType(prefsFile) == INVENTORY_NOTECARD)
    {
        llMessageLinked(LINK_THIS, 17, prefsFile, (key)prefsFile);
        currentLine = 0;
        objectName="";
        requestID = llGetNotecardLine(prefsFile, currentLine);
        llSetTimerEvent(5);
    }
}

parsePrefsLine(string line)
{
    list parsedLine;
    string var;
    string val;
    parsedLine = llParseString2List(line, ["="], []);
    if(llGetListLength(parsedLine) == 1)
        parsedLine = llParseString2List(line, [" "], []);
    if(llGetListLength(parsedLine) > 1)
    {
        var = llStringTrim(llList2String(parsedLine, 0), STRING_TRIM);
        val =  llStringTrim(llDumpList2String(
            llList2ListStrided(parsedLine, 1, -1, 1),
            " "
            ), STRING_TRIM);
//        if(llListFindList(allowedVars, [var]) != -1)
//        {
//            debug("the var " + var + " is allowed, passing " + val);
            llMessageLinked(LINK_THIS, 17, val, (key)var);
            if(llToLower(var) == "advertisername")
                objectName = (string)val;
//        }
    }
}

default {
    state_entry() {
        parsePrefs();
    }

    changed(integer change)
    {
        if (change & (CHANGED_INVENTORY | CHANGED_ALLOWED_DROP))
        {
            parsePrefs();
        }
    }

    timer()
    {
        string originalName = objectBaseName + " " + version + " (" + llGetRegionName() + ")";
        if(objectName == "")
        {
            string objectOwner = llKey2Name(llGetOwner());
            objectName = originalName;
        }
        if(setObjectName)
        {
            llSetObjectName(objectName);
            llSetObjectDesc(objectDesc + ". " + objectBaseName + " " + version + " by Gudule Lapointe");
        }
        llSetTimerEvent(0);
    }

    link_message(integer sender_num, integer channel, string str, key id)
    {
        if(channel == 0)
        {
            if (str == "reset") {
                llResetScript();
            }
        } else if (channel == -1 && str == "status request")
        {
            llResetScript();
        }
    }

    dataserver(key answerID, string answerData) {
        if (answerID == requestID) {
            if (answerData != EOF) {
                parsePrefsLine(answerData);
                ++currentLine;
                requestID = llGetNotecardLine(prefsFile, currentLine);
            }
        }
    }
}
