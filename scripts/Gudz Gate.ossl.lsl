// Gudule's Gate
// Version: 2.19
// Copyright Gudule Lapointe 2011,2012 - Speculoos.net
//
// Advanced teleport script, configurable in two modes: touch and pass through (aka blam gate).
// Destination can be read from landmark or from prim description
//
// WARNING: Depends of following functions in server's config:
//      osTeleportAgent, osGetGridGatekeeperURI, osGetInventoryDesc
//      (osGetInventoryLandmarkParams was relying on a custom OS build and has
//      is replaced by the use of landmark name and landmark desc)
//
// Configuration:
// useLandmark = TRUE
//      Reads destination from landmark
//      If no landmark, or read error, fallback to ObjectName method
//      Otherwise, saves the destination and name in prim properties
//
// useObjectName = TRUE
//      Reads destination from prim description field
//      Formated in the form
//          grid.url:port:Region Name
//          example: speculoos.co.uk:8002:Grand Place
//
// processCollision = TRUE
//      "Blam gate" effect (think about star gate)
//      Teleport occurs when avatar hits the object.
// processTouch = TRUE
//      Teleport occurs when avatar touches the object
// If both teleport methods are set to false, a clickable url is displayed in chat

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

integer useLandmark = TRUE;
integer useObjectName = TRUE;
integer processCollision = FALSE;
integer processTouch = TRUE;
string destinationHover = "";
integer deleteProcessedLandmark = FALSE;

vector colorThisRegion = <0,1,0>;
vector colorOtherRegion = <1,1,1>;
vector colorInactive = <0.5,0.5,0.5>;
vector colorEmpty = <1,1,1>;
vector colorOffline = <0.3,0.25,0.25>;

float alphaActive = 1.0;
float alphaEmpty = 0.0;
float alphaInactive = 0.4;

// Parameters below should not be modified
// I mean it

string internalUpdateMessage = "reset";
integer internalUpdateChannel = 17;
list destinations;
string destination;
string destinationName;
string destinationURL;
vector landingPoint = <128,128,30>;
vector landingLookAt = <1,1,1>;
vector colorOnline;
key agentBeingTransferred;
list lastFewAgents;
string message;
string myGatekeeper;
string gatekeeper;
string me;
integer showDebug = TRUE;

debug(string str)
{
    if(showDebug == TRUE)
    {
        llOwnerSay(me + str);
    }
}

performTeleport( key avatar )
{
    integer CurrentTime = llGetUnixTime();
    integer AgentIndex = llListFindList( lastFewAgents, [ avatar ] );
    if (AgentIndex != -1)
    {
        integer PreviousTime = llList2Integer ( lastFewAgents, AgentIndex+1 );
        if (PreviousTime >= (CurrentTime -5)) return;
        lastFewAgents = llDeleteSubList( lastFewAgents, AgentIndex, AgentIndex+1);
    }
    agentBeingTransferred=avatar;
    lastFewAgents += [ avatar, CurrentTime ];
    message = "Teleporting " + llKey2Name(avatar) + " to " + destinationName
    + " (" + destinationURL + ")";
    llWhisper(0, message);
    string uri = destination;
    vector landing;
    if(llSubStringIndex(uri, "<") > 0) {
        landing = (vector)llGetSubString(uri,
            llSubStringIndex(uri, "<"),
            llSubStringIndex(uri, ">")
            );
        uri = llStringTrim(llGetSubString(uri,
            0,
            llSubStringIndex(uri, "<") - 1
            ), STRING_TRIM);
    }
    if(landing == <0,0,0>) {
        landing = landingPoint;
    }
    osTeleportAgent( avatar, uri, landing, landingLookAt );
}

resetScript()
{
    if(llGetInventoryNumber(INVENTORY_LANDMARK) > 1 && deleteProcessedLandmark)
    {
        if(deleteProcessedLandmark)
            llRemoveInventory(llGetInventoryName(INVENTORY_LANDMARK, 0));
    }
    llResetScript();
}

string strReplace(string str, string search, string replace) {
    return llDumpList2String(llParseStringKeepNulls((str),[search],[]),replace);
}

upgrade() {
    // disabled, as it behaves weird (existing copies delete the newly added)
    // let's check that later and add the version checking.
    return;
    // Self Upgrading function by Cron Stardust based upon work by Markov Brodsky and Jippen Faddoul.
    // If this code is used, these header lines MUST be kept.
    //Get the name of the script
    string self = llGetScriptName();
    string basename = self;

    // If there is a space in the name, find out if it's a copy number and correct the basename.
    if (llSubStringIndex(self, " ") >= 0) {
        // Get the section of the string that would match this RegEx: /[ ][0-9]+$/
        integer start = 2; // If there IS a version tail it will have a minimum of 2 characters.
        string tail = llGetSubString(self, llStringLength(self) - start, -1);
        while (llGetSubString(tail, 0, 0) != " ") {
            start++;
            tail = llGetSubString(self, llStringLength(self) - start, -1);
        }

        // If the tail is a positive, non-zero number then it's a version code to be removed from the basename.
        if ((integer)tail > 0) {
            basename = llGetSubString(self, 0, -llStringLength(tail) - 1);
        }
    }

    // Remove all other like named scripts.
    integer n = llGetInventoryNumber(INVENTORY_SCRIPT);
    while (n-- > 0) {
        string item = llGetInventoryName(INVENTORY_SCRIPT, n);

        // Remove scripts with same name (except myself, of course)
        if (item != self && 0 == llSubStringIndex(item, basename)) {
            // don't remove if alphabetic characters remain in the tail
            string tail = llStringTrim(llGetSubString(item,    llStringLength(basename), -1), STRING_TRIM);
            tail = llDumpList2String(llParseString2List(tail,[" ", "."],[]), "");
            if(tail == (integer)tail) {
                llRemoveInventory(item);
            }
        }
    }
    string my_string = "Un renard fou.  Regarde la lune..";
}

readLandmark() {
    if (useLandmark && llGetInventoryName(INVENTORY_LANDMARK, 0)) {
        string landmark = llGetInventoryName(INVENTORY_LANDMARK, 0);
        readLandmark(landmark);
    }
}
readLandmark(string landmark) {
    if (useLandmark && llGetInventoryName(INVENTORY_LANDMARK, 0)) {
        string description = osGetInventoryDesc(landmark);
        string info = landmark;
        if(llGetSubString(info, 0, 2) == "HG "){
            info = llGetSubString(info, 3, -1) + "'";
        }
        string parcel;
        string region;
        vector position;
        integer index;
        index = llSubStringIndex(info, ",");
        if(index > 0) {
            parcel = llStringTrim(llGetSubString(info, 0, index - 1), STRING_TRIM);
            info = llStringTrim(llGetSubString(info, index + 1, -1), STRING_TRIM);
        }
        index = llSubStringIndex(info, "(");
        if(index > 0) {
            region = llStringTrim(llGetSubString(info, 0, index - 1), STRING_TRIM);
            position = llStringTrim(llGetSubString(info, index + 1, llSubStringIndex(info, ")") - 1), STRING_TRIM);
        }
        if(parcel != region && parcel != "Your Parcel") {
            destinationName = parcel;
        } else {
            destinationName = region;
            parcel = "";
        }

        index = llSubStringIndex(description, "@");
        if(index > 0) {
            gatekeeper = strReplace(llStringTrim(llGetSubString(description, index + 1, -1), STRING_TRIM), "http://", "");
            description = llStringTrim(llGetSubString(description, 0, index -1),  STRING_TRIM);
        }
        if(!gatekeeper |! region) {
            debug("missing something");
            return;
        }

        destination = gatekeeper + ":" + region;
        if(position != <0,0,0>) destination += " " + (string)position;

        destinations += [ destinationName, parcel, region, position, gatekeeper, description ];
        llSetObjectName(destinationName);
        llSetObjectDesc(destination);

        if(deleteProcessedLandmark)
            llRemoveInventory(landmark);
    }
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
        if (change & CHANGED_INVENTORY)
            resetScript();
    }
    state_entry()
    {
        upgrade();
        colorOnline=colorOtherRegion;
        me = "/me (" + llGetScriptName() + "): ";
        myGatekeeper = strReplace(osGetGridGatekeeperURI(), "http://", "");
        llSetAlpha(alphaActive, ALL_SIDES);
        llSetColor(colorInactive, ALL_SIDES);
        readLandmark();
        if(destination == "" && useObjectName) {
            if(llGetObjectName() != "" & llGetObjectName() != "Primitive")
            {
                destinationName = llGetObjectName();
                if(llGetObjectDesc() != "")
                {
                    destination = llGetObjectDesc();
                } else {
                    destination = destinationName;
                }
            } else if(llGetObjectDesc() != "") {
                destination = llGetObjectDesc();
                destinationName = destination;
            }
            destinationURL = "secondlife://"
                + strReplace(llEscapeURL(
                    strReplace(destination, "http://", "")
                ), "%3A", ":"    ) + "/";
        }
        destination = strReplace(destination, myGatekeeper + ":", "");
        destinationURL = strReplace(destinationURL, myGatekeeper + ":", "");

        if(destination == "" || destination == " ") {
                state empty;
        }
        if(~llSubStringIndex(destinationURL, "ERROR_"))
        {
            destinationURL = "secondlife://"
                + strReplace(llEscapeURL(destination), "%3A", ":"    ) + "/";
        }
        llMessageLinked(LINK_THIS, internalUpdateChannel, "reset", NULL_KEY);
        llSetText(destinationHover, <255,255,255>, 1);
        llSetStatus(STATUS_PHANTOM, FALSE);
        if(destination == llGetRegionName())
        {
            colorOnline = colorThisRegion;
        } else {
            colorOnline = colorOtherRegion;
        }
        llSetColor(colorOnline, ALL_SIDES);
        if(llGetOwner() != (key)llGetParcelDetails(llGetPos(),[PARCEL_DETAILS_OWNER]))
        {
            state inactive;
        } else {
            message = destinationName + " gate activated ("+ destinationURL + ")";
//            llWhisper(0, message);
        }
    }

    link_message(integer sender_num, integer channel, string str, key id)
    {
        if(channel == internalUpdateChannel)
        {
            if (str == "up") {
                llSetColor(colorOnline, ALL_SIDES);
                llSetAlpha(alphaActive, ALL_SIDES);
            } else if(str == "down") {
                llSetColor(colorOffline, ALL_SIDES);
                llSetAlpha(alphaInactive, ALL_SIDES);
            } else if (str == "unknown") {
                llSetColor(colorOffline, ALL_SIDES);
                llSetAlpha(alphaInactive, ALL_SIDES);
            } else {
                llSetColor(colorOnline, ALL_SIDES);
                llSetAlpha(alphaInactive, ALL_SIDES);
            }
        }
    }

    touch_start(integer num_detected)
    {
        key avatar = llDetectedKey(0);
        if(processTouch)
        {
            if(avatar != agentBeingTransferred)
            {
                performTeleport(avatar);
            }
            llSetTimerEvent(3);
        } else if(processCollision) {
            message = "Cross the gate to jump to " + destinationName;
            if(destinationURL != "")
            {
                message = message + "or click this link " + destinationURL;
            }
            llInstantMessage(avatar, message);
        } else {
            message = "Click " + destinationURL + "to jump to " + destinationName;
            llInstantMessage(avatar, message);
        }
    }
    collision_start(integer num_detected)
    {
        if(processCollision)
        {
            key avatar = llDetectedKey(0);
            llSetStatus(STATUS_PHANTOM, TRUE);
            if(avatar != agentBeingTransferred)
            {
                performTeleport(avatar);
            }
            llSetTimerEvent(3);
        }
    }
    timer()
    {
        agentBeingTransferred="";
        llSetTimerEvent(0);
        if(processCollision) {
            llSetStatus(STATUS_PHANTOM, FALSE);
        }
    }
}

state empty
{
    on_rez(integer start_param)
    {
        resetScript();
    }
    state_entry()
    {
        llSetColor(colorEmpty, ALL_SIDES);
        llSetAlpha(alphaEmpty, ALL_SIDES);
        llSetObjectName(" ");
        llMessageLinked(LINK_THIS, internalUpdateChannel, "reset", NULL_KEY);
    }
    changed(integer change)
    {
        if(change & CHANGED_OWNER)
            resetScript();
        if (change & CHANGED_REGION_START)
            resetScript();
        if (change & CHANGED_INVENTORY)
            resetScript();
    }
}
state inactive
{
    on_rez(integer start_param)
    {
        resetScript();
    }
    state_entry()
    {
        llSetColor(colorInactive, ALL_SIDES);
        llSetAlpha(alphaInactive, ALL_SIDES);
        debug("Teleport can only work if object is owned by parcel owner");
    }
    touch_start(integer num_detected)
    {
        llInstantMessage(llDetectedKey(0), "Gate is disabled. Teleport can only work if object is owned by parcel owner");
    }
    changed(integer change)
    {
        if(change & CHANGED_OWNER)
            resetScript();
        if (change & CHANGED_REGION_START)
            resetScript();
        if (change & CHANGED_INVENTORY)
            resetScript();
    }
}
