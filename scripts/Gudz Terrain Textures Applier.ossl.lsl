// Terrain Textures Applier
// Version 1.0

// (c) Gudule Lapointe 2018

// This script is licensed under Creative Commons BY-NC-SA
// See <http://creativecommons.org/licenses/by-nc-sa/3.0/>
// You may not use this work for commercial purposes.
// You must attribute the work to the authors,
//  Olivier van Helden and Gudule Lapointe.
// You may distribute, alter, transform, or build upon this work
// as long as you do not delete the name of the original authors.

// You should not need to change the parameters here.
// Rez a prism and apply following settings to get a 5 squares prim
//      Path Cut Begin: 0.2
//      Path Cut End: 0.8
//      Hollow: 70.0
//      Rotation Z: 180.0 (orientation needed for HUD attachment)
//      X-Size: 0.01 (bellow it can create some weird effects)
//      Y-Size: 0.5 (for example)
//      Z-Size: 0.9 (0.18 the Y-Sise or slightly less than one fifth)
// You now have a line of five squares. I keep the first one to put the names
// of the setting, the other ones are the four terrain ground.
//
// Use prim editor "Select Face" tool to apply the desired textures in the
// same order as in Region Manager to 2nd, 3d, 4th and 5th visible faces.
//
// Now you have two options:
//
// 1* Put the script in the object. That's it, you have a ground applier.
//    Click anywhere to update the terrain.
//
// 2° Duplicate the 5-squares prim, aling them in front on another prim and
//    link them all, put one single script in the background prim.
//    Now you have a multi-set terrain ground applier.
//    Click on a 5-squares prim will update the terrain accordingly.

integer DEBUG = FALSE;

vector COLOR_ACTIVE = <0.0,1.0,0.0>;
key TEXTURE_ACTIVE = TEXTURE_BLANK;
vector COLOR_INACTIVE = <1.0,1.0,1.0>;
key TEXTURE_INACTIVE = TEXTURE_TRANSPARENT;
vector COLOR_BUSY = <1.0,0.5,0.3>;
key TEXTURE_BUSY = TEXTURE_BLANK;

integer SIDE_STATUS = 3;
integer SIDE_GROUND0 = 7;
integer SIDE_GROUND1 = 4;
integer SIDE_GROUND2 = 6;
integer SIDE_GROUND3 = 1;

key GROUND0;
key GROUND1;
key GROUND2;
key GROUND3;

integer selectedLink = -99;

debug(string message) {
    if(DEBUG == FALSE) return;
    llOwnerSay(message);
}

resetStatus() {
    integer count = llGetObjectPrimCount(llGetKey());
    if (count == 1) {
        resetStatus(LINK_THIS);
    } else {
        resetStatus(LINK_ALL_OTHERS);
        //integer link = 0;
        //while(link <= count)
        //{
        //    if(link != llGetLinkNumber()) {
        //        resetStatus(link);
        //    } else {
        //        debug("ignoring button " + link);
        //    }
        //    link++;
        //}
    }
}
resetStatus(integer link)
{
    debug("resetting button " + link);
    llSetLinkColor(link, COLOR_INACTIVE, SIDE_STATUS);
    llSetLinkTexture(link, TEXTURE_INACTIVE, SIDE_STATUS);
}
setStatusWorking(integer link) {
    llSetLinkColor(link, COLOR_BUSY, SIDE_STATUS);
    llSetLinkTexture(link, TEXTURE_BUSY, SIDE_STATUS);
}
setStatusActive(integer link) {
    llSetLinkColor(link, COLOR_ACTIVE, SIDE_STATUS);
    llSetLinkTexture(link, TEXTURE_ACTIVE, SIDE_STATUS);
}

string setTerrainTexture(integer groundLevel, integer matchingSide)
{
    llSetLinkColor(selectedLink, COLOR_BUSY, matchingSide);
    key texture = llList2Key(llGetLinkPrimitiveParams(selectedLink, [PRIM_TEXTURE, matchingSide]), 0);
    llOwnerSay("Setting ground " + groundLevel + " to " + texture + " (side " + matchingSide + ")");
    osSetTerrainTexture(groundLevel, texture);
    llSetLinkColor(selectedLink, COLOR_INACTIVE, matchingSide);
    return texture;
}

default
{
    state_entry()
    {
        debug("/me loading");
        resetStatus();
        state ready;
    }
}
state ready
{
    state_entry()
    {
        debug("/me ready");
        if(selectedLink != -99) setStatusActive(selectedLink);
    }
    changed(integer change)
    {
        if(change & CHANGED_INVENTORY) llResetScript();
        if(change & CHANGED_TEXTURE) llResetScript();
        if(change & CHANGED_LINK) llResetScript();
    }
    touch(integer index)
    {
        integer link = llDetectedLinkNumber(0);
        key toucher = llDetectedKey(0);
        if (toucher == llGetOwner())
        {
            if(llGetObjectPrimCount(llGetKey())==1 || link != llGetLinkNumber())
            {
                if(selectedLink != -99) resetStatus(selectedLink);
                selectedLink = link;
                llInstantMessage(toucher, "Changing terrain to " + llGetLinkName(selectedLink) + " (setting " + link + "). It will take a while, go get a coffee");
                state updatingTerrain;
            }
        } else {
            llInstantMessage(toucher, "/me can only be processed by its owner");
        }
    }
}

state updatingTerrain
{
    state_entry()
    {
        debug("updating terrain");
        resetStatus();
        setStatusWorking(selectedLink);
        setTerrainTexture(0, SIDE_GROUND0);
        setTerrainTexture(1, SIDE_GROUND1);
        setTerrainTexture(2, SIDE_GROUND2);
        setTerrainTexture(3, SIDE_GROUND3);
        llOwnerSay("All set");
        state ready;
    }
}
