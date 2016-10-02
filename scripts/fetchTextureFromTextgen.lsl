// fetchTextureFromTextgen
// Version: 0.7
// Author: Gudule Lapointe gudule@speculoos.world
//
// Create a texture by using an external web graphics generator.
//
// WARNING: set a valid textGenURL value below, otherwise it won't work.

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

integer updateTexture = TRUE;
string textGenURL = "http://www.example.com/cgi-bin/your-generator.php?string=";

integer internalUpdateChannel = 17;

string textColor = "black";
string textBackground = "white";

// Parameters below should not be modified
// I mean it

string  dynTextureDynamicID="";
string  dynTextureType="image";
string  dynTextureOptions="";
integer dynTextureRefresh = 0;

fetchTexture(string text)
{
    
    string fetchedTexture = osSetDynamicTextureURL(
        dynTextureDynamicID,
        dynTextureType,
        textGenURL + llEscapeURL(text) + "&color=" + textColor + "&background=" + textBackground,
        dynTextureOptions,
        dynTextureRefresh
        );
}

resetScript()
{
    llResetScript();
}

default
{
    on_rez(integer start_param)
    {
        resetScript();
    }
    changed(integer change)
    {
        if(change & CHANGED_OWNER)
            resetScript();
        if (change & CHANGED_REGION_START)
            resetScript();
//        if (change & CHANGED_INVENTORY)
//            resetScript();
    }
    state_entry()
    {
        if(updateTexture) {
            fetchTexture(llGetObjectName());   
        }
    }
    
    link_message(integer sender_num, integer channel, string str, key id)
    {
        if(channel == internalUpdateChannel)
        {
            if (str == "reset") {
                llResetScript();
            }
        }
    }
}
