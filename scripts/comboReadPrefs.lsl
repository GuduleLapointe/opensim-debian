// comboReadPrefs
// Version: 1.7.1
// Author: Gudule Lapointe gudule@speculoos.world
//
// Read preferences from a notecard and send values to internalChannel
// for other scripts

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
string version = "2.1";
string prefsFile = "!Combo-preferences";
integer currentLine;
key requestID;

integer setObjectName = FALSE;
string objectName = "NPC Vendor";
string objectDesc = "Touch for rental information";

string newline="\n\t\t\t";
integer rentRate;
integer groupRebate;
integer restrictToGroup;
integer tipsPercent;
string advertiserName;

list allowedVars = ["rentRate", "groupRebate", "tipsPercent", "advertiserName", "restrictToGroup"];

parsePrefs()
{
    llMessageLinked(LINK_THIS, 17, prefsFile, (key)"prefsFile");
//    prefsFile = "!Combo-preferences";
    if(llGetInventoryType(prefsFile) == INVENTORY_NOTECARD)
    {
        currentLine = 0;
        advertiserName="";
        requestID = llGetNotecardLine(prefsFile, currentLine);
//    llSay(0, "preferences parsed");
        llSetTimerEvent(5);
    }
}

parsePrefsLine(string line)
{
    list parsedLine;
    string var;
    string val;
    parsedLine = llParseString2List(line, [" "], []);
    if(llGetListLength(parsedLine) > 1)
    {
        var = llList2String(parsedLine, 0);
        val = llDumpList2String(
            llList2ListStrided(parsedLine, 1, -1, 1),
            " "
            );
//        llSay(0, "var " + var + " = \"" + val + "\"");

        if(llListFindList(allowedVars, [var]) != -1) 
        {
//            llSay(0, "the var " + var + " is allowed, passing " + val);
            llMessageLinked(LINK_THIS, 17, val, (key)var);
            if(llToLower(var) == "advertisername")
                advertiserName = (string)val;
        }
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
        string originalName = objectName + " " + version + " (" + llGetRegionName() + ")";
        if(advertiserName == "")
        {
            string objectOwner = llKey2Name(llGetOwner()); 
            advertiserName = originalName;
        }
        if(setObjectName)
        {
            llSetObjectName(advertiserName);
            llSetObjectDesc(objectDesc + ". " + objectName + " " + version + " by Gudule Lapointe");
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