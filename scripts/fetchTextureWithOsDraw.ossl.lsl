// fetchTextureWidhOsDraw
// Version: 1.3.12
// Author: Gudule Lapointe gudule@speculoos.world
//
// Create a texture from given text, using osSetDynamicTextureData
// set of functions (hence "osdraw" in name)
// "Fetch" is ketp in name by reference to my initial script, using an external
// web browser generator.

//  Slave mode (default):
//    Style parameters can be updated by another script with a link message:
//      llMessageLinked(LINK_THIS, internalUpdateChannel, val, (key)var);
//    The update script should anwser to "status request" message
//    "var" can be any of
//      fontname, fontsize, lineheight, fontcolor, backgroundcolor,
//      texturewidth, textureheigth, margin, position, croptofit
//    Other values are ignored
//
//  Standalone mode (fallback)
//    If "standalone" variable is set to TRUE or if no answer received after
//    sendig a "status request" message, this script falls back to standalone
//    The object name is used as text and default style values are used.


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

integer updateTextureOnStartup = TRUE;
integer standalone = FALSE; // set FALSE to control only from the script, TRUE to receive instructions from external script like ComboReadPrefs. If no instruction is received after the status request, it will fallback to TRUE.

string fontName = "Default"; // Depends on the fonts available on your simulator's computer

integer fontSize = 24;
integer lineHeight = 24;
string textColor = "black";
string backgroundColor = "white"; // "transparent" or color name

key inactiveTexture = TEXTURE_TRANSPARENT;
key otherTexture = TEXTURE_BLANK;

integer textureWidth = 512;
integer textureHeight = 512;
integer margin = 24;
string position = "center";
integer cropToFit = FALSE;

string textGenURL = "http://www.example.com/cgi-bin/your-generator.php?string=";
integer internalUpdateChannel = 17;

// Parameters below should not be modified
// I mean it

integer statusReceived = FALSE;
integer startY;
key httpRequest;
string text;

string  dynTextureDynamicID="";
string  dynTextureType="image";
string  dynTextureOptions="";
integer dynTextureRefresh = 0;

string cropText(string in, string fontname, integer fontsize,integer width)
{
    if(!cropToFit) return in;
    integer i;
    integer trimmed = FALSE;
    string suffix="";

    for(;llStringLength(in)>0;in=llGetSubString(in,0,-2)) {
        if(trimmed) suffix="...";
        vector extents = osGetDrawStringSize("vector",in+suffix,fontname,fontsize);

        if(extents.x<=width) {
                return in+suffix;
        }

        trimmed = TRUE;
    }

    return "";
}

cleanup()
{
        llRemoveInventory("fetchTextureFromTextgen 0.7");
        llRemoveInventory("fetchTextureWithOsDraw 1.0");
        llRemoveInventory("fetchTextureWithOsDraw 1.1");
        llRemoveInventory("fetchTextureWithOsDraw 1.2");
        llRemoveInventory("fetchTextureWithOsDraw 1.2 Transparent");
        llRemoveInventory("fetchTextureWithOsDraw 1.3");
        llRemoveInventory("fetchTextureWithOsDraw 1.3 direction sign");
        llRemoveInventory("fetchTextureWithOsDraw 1.3.1");
        llRemoveInventory("fetchTextureWithOsDraw 1.3.1 Transparent");
        llRemoveInventory("fetchTextureWithOsDraw 1.3.2");
        llRemoveInventory("fetchTextureWithOsDraw 1.3.2 Transparent");
}

debug(string text) {
    llOwnerSay("/me debug: " + text);
}
fetchTexture()
{
    if(standalone) {
        text = llGetObjectName();
    }
    if(llStringTrim(text,STRING_TRIM) =="")
    {
        llSetTexture(inactiveTexture, ALL_SIDES);
        return;
    }

    string commandList = "";
    integer x = margin;
    integer y = margin;
    integer drawWidth = textureWidth - 2*margin;
    integer drawHeight = textureHeight - 2*margin;
    vector extents;
    extents = osGetDrawStringSize("vector",text,fontName,fontSize);
    if(extents.x > drawWidth)
    {
        if(cropToFit)
        {
            text = cropText(text, fontName, fontSize, drawWidth);
        } else {
            fontSize = (integer)(fontSize * drawWidth / extents.x);
        }
    } else {
        x += (integer)((drawWidth - extents.x) / 2);
    }
    extents = osGetDrawStringSize("vector",text,fontName,fontSize);
//    extents = osGetDrawStringSize("vector",text,fontName,fontSize);
//    if(extents.y > drawHeight)
//    {
//        textureHeight = (integer)extents.y + 2*margin;
//    } else {
        y += (integer)((drawHeight - extents.y) / 2);
//    }
    commandList = osSetPenColor(commandList, textColor);
    commandList = osSetFontName(commandList, fontName);
    commandList = osSetFontSize(commandList, fontSize);
    commandList = osMovePen(commandList, x, y);
    commandList = osDrawText(commandList, text);

    integer alpha = 256;
    if(backgroundColor == "transparent")
    {
        alpha = 0;
        otherTexture = TEXTURE_TRANSPARENT;
    }
    llSetTexture(otherTexture, ALL_SIDES);

    osSetDynamicTextureData("", "vector", commandList, "width:"+(string)textureWidth+",height:"+(string)textureHeight+",bgcolor:" + backgroundColor + ",alpha:"+alpha, 0);
/*
    forget this for now
    llSetTexture(otherTexture, 0);
    llSetTexture(otherTexture, 5);
*/
}

oldFetchTexture(string text)
{

    string fetchedTexture = osSetDynamicTextureURL(
        dynTextureDynamicID,
        dynTextureType,
        textGenURL + llEscapeURL(text) + "&color=" + textColor + "&background=" + backgroundColor,
        dynTextureOptions,
        dynTextureRefresh
        );
}

resetScript()
{
    llResetScript();
}

integer boolean(string val)
{
    if(llToUpper(val) == "TRUE" | (integer)val == TRUE)
    {
        return TRUE;
    }
    return FALSE;
}

processLinkMessage(integer sender_num, integer channel, string str, key id)
{
    integer timerDelay=3;
    if(channel == internalUpdateChannel)
    {
        llSetTimerEvent(timerDelay);
        string var = llToLower((string)id);
        string val = str;
        if(var == "fontname") fontName = (string)val;
        if(var == "fontsize") fontSize = (integer)val;
        if(var == "lineheight") lineHeight = (integer)val;
        if(var == "textcolor") textColor = (string)val;
        if(var == "backgroundcolor") backgroundColor = (string)val;
        if(var == "texturewidth") textureWidth = (integer)val;
        if(var == "texturewidth") textureWidth = (integer)val;
        if(var == "margin") margin = (integer)val;
        if(var == "position") position = (string)val;
        if(var == "croptofit") cropToFit = boolean(val);
        if(var == "text" || var == "forcetext") text = (string)val;
        llSetTimerEvent(timerDelay);
    }
}

statusRequest() {
    statusReceived = FALSE;
    llMessageLinked(LINK_SET, -1, "status request", "");
    llSetTimerEvent(1);
}

default
{
    state_entry()
    {
        if(updateTextureOnStartup) {
            statusRequest();
        }
        cleanup();
    }
    on_rez(integer start_param)
    {
        resetScript();
    }
    timer()
    {
        llSetTimerEvent(0);
        if(! statusReceived) {
            standalone = TRUE;
        }
        fetchTexture();
    }

    changed(integer change)
    {
        if(change & CHANGED_OWNER)
            resetScript();
        if (change & CHANGED_REGION_START)
            resetScript();
        if (change & CHANGED_INVENTORY && standalone)
            resetScript();
    }

    link_message(integer sender, integer channel, string message, key id)
    {
        if(message == "status request") return; // own initial request
        if(channel == internalUpdateChannel)
        {
            if (message == "reset") {
                llResetScript();
            } else {
                statusReceived = TRUE;
                processLinkMessage(sender, channel, message, id);
            }
        }
    }
}
