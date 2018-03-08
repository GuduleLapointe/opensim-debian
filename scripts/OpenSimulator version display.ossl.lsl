// OpenSimulator version display
// Version 1.0.1

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

// Shows memory in human readable format, with a maximum number of decimals
// correctly rounded
// Allow display as hover text or texture, as well as public announce
// Texture display requires fetchTextureWithOsDraw or fetchTextureFromTextgen
// script present in the same object and using the same internalUpdateChannel;

integer decimalPrecision = 3;

integer showHoverText = FALSE;
integer showTexture = TRUE;
integer sayPublic = FALSE;
integer internalUpdateChannel = 17;

string strReplace(string str, string search, string replace) {
    return llDumpList2String(llParseStringKeepNulls((str),[search],[]),replace);
}
string strReplaceUntilNoMatch(string str, string search, string replace) {
    string source = str;
    string replaced = strReplace(source, search, replace);
    while(source != replaced) {
        source = replaced;
        replaced = strReplace(source, search, replace);
    }
    return replaced;
}
string formatDecimal(float number, integer precision)
{
    float factor = llPow(10, precision);
    number = (float)llRound(number * factor) / factor;
    string formatted = (string)number;
    integer keep = llSubStringIndex(formatted, ".") + precision;
    formatted = llGetSubString(formatted, 0, keep);
    
    return formatted;
}

string formatMemory(string mem, integer precision) {
    float floatMem;
    string unit;
    string formatted = "";
    floatMem = (float)mem;
    floatMem = 123;
    list units = [ "PB", "TB", "GB", "MB", "KB", "B" ];
    integer count = llGetListLength(units);
    integer i;
    for(i = 0; i < count; ++i) {
        integer power = (integer)((count - i - 1)*3);
        float factor = llPow(10, power);
        if(floatMem >= factor) {
            floatMem = floatMem / factor;
            unit = llList2String(units, i);
            jump found;
        }
    }
    @found;
    
    formatted = formatDecimal(floatMem, precision) + " " + unit + " (" + mem + "B)";
    return formatted;
}

GenStats()
{
    display("Loading");
    string text;
    string memoryUsed;

//    memoryUsed = (string)osGetSimulatorMemory();
//    text += "Memory Use: " + formatMemory(memoryUsed, decimalPrecision);
    text = strReplaceUntilNoMatch(osGetSimulatorVersion(), "  ", " ");
    display(text);
}

display(string text) {
    if(showHoverText) {
        llSetText(text, <0.0,1.0,0.0>, 1.0 );
    } else {
        llSetText("", <0.0,1.0,0.0>, 1.0 );
    }
    if(showTexture) {
        llMessageLinked(LINK_THIS, internalUpdateChannel, text, (key)"forceText");
    }
    if(sayPublic) {
        llSay(0, text);
    }
}
 
default
{
    state_entry() // display @ start
    {
        GenStats();
    }
 
    touch(integer num) // refresh on touch
    {
        GenStats();
    }

    changed(integer change)
    {
    if (change & CHANGED_INVENTORY)
        {
            GenStats();
        }
        if (change & CHANGED_REGION)
        {
            GenStats();
        }
        if(change & CHANGED_REGION_START)
        {
            GenStats();
        }
    }
}