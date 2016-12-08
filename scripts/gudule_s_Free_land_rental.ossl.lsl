// Gudule's free land rental
// Version: 1.2.1
// Author: Gudule Lapointe gudule@speculoos.world
// Licence:  GNU Affero General Public License

// Allow to rent land for free, requiring the user to click
// regularly on the rental panel to keep the parcel.
// The land is technically sold as with viewer buy/send land so
// the user has full ownership and full control on his parcel,
// no need of the group trick. Expired or abandonned land is sold
// back to the vendor owner.

// IMPORTANT:
// - Disallow land join,split and resell in the Estate settings
// - The vendor HAS TO BE outside the rented land. Place it at 1 meter
// of the rented land border (outside) and make sure the yellow
// positioning mark is inside.

// User configurable variables:
integer DEBUG = FALSE;    // set to TRUE to see debug info
vector parcelDistance = <0,2,0>; // distance between the rental sign and the parcel (allow to place the sign outside the parcel)
integer checkerLink = 4;

// named texture have to be placed in the prim inventory:
string texture_expired = TEXTURE_BLANK;          // lease is expired
string texture_unleased = "texture_unleased";     // leased signage, large size
string texture_busy = TEXTURE_BLANK;       // busy signage
string texture_leased = TEXTURE_BLANK;               // leased  - in use

vector SIZE_UNLEASED = <1,0.2,0.5>;        // the signs size when unrented
vector SIZE_LEASED = <1,0.2,0.125>;        // the signs size when rented (it will shrink)

string configFile = ".config";
string statusFile = "~status";

// End of user configurable variables

// Put this in the Description of the sign prim or you will get default ones
// PERIOD,MAXPERIOD,PRIMMAX,IS_RENEWABLE,RENTWARNING,GRACEPERIOD
//
// PERIOD: rental and renewal period in days
// MAXPERIOD: the maximum total rental, renewals included
// PRIMMAX (obsolete, calculated automatically)
// IS_RENEWABLE: if set to 1, or TRUE, user can renew, if set to 0, the user cannot renew the same plot
// RENTWARNING: when to send an IM warning for renewal, in number of days before the lease expiration (if IS_RENEWABLE)
// GRACEPERIOD: number of days allowed to miss claiming before it really expires

// Copyright (C) 2016  Gudule Lapointe gudule@speculoos.world
// Based "No Money Rental (Vendor).lsl" script from Outworldz website

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


// Default config info if you didn't change the description when this script is first executed
//string  initINFO = "1,7,100,1,3,1"; // daily rental, max 7 days
//string  initINFO = "7,28,100,1,3,1"; // daily rental, max 1 month
string  initINFO = "30,3650,100,1,7,7"; // montly rental, max 10 years
string  DEBUGINFO  = "0.041,1,100,1,0.0104,1"; // Default config info in debug mode if you didn't change the description when this script is first executed (1 hour rental, max 1 day, warn 4h before, grace 1 day)
//string  debugINFO = "0.00347222,0.00694455,100,1,0.00138889, 0.00138889";  //fast timers (5 minutes rental, max 10 min, warn 2 min before, grace 1 minute))
// Debug config info, 5 minutes per claim, 10 minutes max, 100 prims, 2 minute warning, grace period 1 minutes

//
// don't muck below this, code begins.
//
// The  Description ( config info) of the sign is stored into these variables:

float PERIOD;     // DAYS  lease is claimed
integer PRIMMAX;    // number of prims
float MAXPERIOD;  // maximum length in days
float RENTWARNING ; //Day allowed to renew earlier
float GRACEPERIOD ; // Days allowed to miss payment

list my_data;
integer MY_STATE = 0;  // 0 is unleased, 1 = leased
string LEASER = "";    // name of lessor
key LEASERID;          // their UUID

integer LEASED_UNTIL; // unix time stamp
integer IS_RENEWABLE = FALSE; // can they renew?
integer DAYSEC = 86400;         // a constant
integer SENT_WARNING = FALSE;    // did they get an im?
integer SENT_PRIMWARNING = FALSE;    // did they get an im about going over prim count?
integer listener;    // ID for active listener
key touchedKey ;     // the key of whoever touched us last (not necessarily the renter)

vector parcelPos;
integer parcelArea;
list parcelDetails;


debug(string data)
{
    if (DEBUG)
        llOwnerSay("DEBUG: " + data);
}

integer dialogActiveFlag ;    // true when we have up a dialog box, used by the timer to clear out the listener if no response is given
dialog()
{
    llListenRemove(listener);
    integer channel = llCeil(llFrand(1000000)) + 100000 * -1; // negative channel # cannot be typed
    listener = llListen(channel,"","","");
    if(MY_STATE == 0)
    {
        llDialog(touchedKey,"Do you wish to claim this parcel?",["Yes","-","No"],channel);
    } else {
        llDialog(touchedKey,
        "Leased until " + Unix2PST_PDT(LEASED_UNTIL)
        + ". Abandon land?"
        ,["Abandon","-","No"],channel);
    }
    llSetTimerEvent(30);
    llSetText("",<1,0,0>, 1.0);
    //llInstantMessage(LEASERID,"Your parcel is ready.\n" + get_rentalbox_url());

    dialogActiveFlag  = TRUE;
}

string get_rentalbox_info()
{
    return llGetRegionName()  + " @ " + (string)parcelPos + " (Leaser: \"" + LEASER + "\", Expire: " + timespan(LEASED_UNTIL - llGetUnixTime()) + ")";
}
string get_rentalbox_url()
{
    return "secondlife://" + strReplace(osGetGridGatekeeperURI(), "http://", "") + "/" + llGetRegionName() + "/";
    // + (string)parcelPos.x + "," + (string)parcelPos.y + "," + (string)parcelPos.z;
}

string timespan(integer time)
{
    integer days = time / DAYSEC;
    integer curtime = (time / DAYSEC) - (time % DAYSEC);
    integer hours = curtime / 3600;
    integer minutes = (curtime % 3600) / 60;
    integer seconds = curtime % 60;

    return (string)llAbs(days) + " days, " + (string)llAbs(hours) + " hours, "
        + (string)llAbs(minutes) + " minutes, " + (string)llAbs(seconds) + " seconds";

}

load_data()
{
    integer len;
    my_data = llCSV2List(llGetObjectDesc());

    if (llStringLength(llGetObjectDesc()) < 5) // SL does not allow blank description
    {
        my_data = llCSV2List(initINFO);
    }
    else if (DEBUG)
    {
        my_data = llCSV2List(DEBUGINFO);    // 5 minute fast timers
    }

    len = llGetListLength(my_data);

    PERIOD = (float) llList2String(my_data,0);
    debug("PERIOD FROM DESCRIPTION " + (string) PERIOD);

    MAXPERIOD = (float) llList2String(my_data,1);
    debug("MAXPERIOD FROM DESCRIPTION " + (string) MAXPERIOD);

    //PRIMMAX = (integer) llList2String(my_data,2);
    PRIMMAX = llGetParcelMaxPrims(parcelPos, FALSE);
    debug("PRIMMAX FROM DESCRIPTION : " + (string) PRIMMAX);

    IS_RENEWABLE = (integer) llList2String(my_data, 3);
    debug("IS_RENEWABLE FROM DESCRIPTION : " + (string) IS_RENEWABLE);

    RENTWARNING = (float) llList2String(my_data, 4);
    debug("RENTWARNING FROM DESCRIPTION : " + (string) RENTWARNING);

    GRACEPERIOD = (float) llList2String(my_data, 5);
    debug("GRACEPERIOD FROM DESCRIPTION : " + (string) GRACEPERIOD);

    MY_STATE = (integer) llList2String(my_data, 6);
    debug("MY_STATE FROM DESCRIPTION : " + (string) MY_STATE);

    LEASER = llList2String(my_data, 7);
    debug("LEASER FROM DESCRIPTION : " + (string) LEASER);

    LEASERID = (key) llList2String(my_data, 8);
    debug("LEASERID FROM DESCRIPTION : " + (string) LEASERID);

    LEASED_UNTIL = (integer) llList2String(my_data, 9);
    debug("LEASED_UNTIL FROM DESCRIPTION : " + (string) LEASED_UNTIL);

    SENT_WARNING = (integer) llList2String(my_data, 10);
    debug("SENT_WARNING FROM DESCRIPTION : " + (string) SENT_WARNING);

}

save_data()
{
    debug("Data saved in description");
    my_data =  [(string)PERIOD, (string)MAXPERIOD, (string)PRIMMAX, (string)IS_RENEWABLE, (string) RENTWARNING, (string) GRACEPERIOD, (string)MY_STATE, (string)LEASER, (string) LEASERID,  (string)LEASED_UNTIL, (string)SENT_WARNING  ];
    llSetObjectDesc(llList2CSV(my_data));
    initINFO = llList2CSV(my_data);   // for debugging in LSL Editor.
    DEBUGINFO = initINFO;  // for debugging in fast mode

    load_data() ;    // to print it in case of debug
}

reclaimParcel()
{
    LEASER="";
    LEASERID=NULL_KEY;
    list rules =[
        PARCEL_DETAILS_NAME, parcelArea + " sqm parcel for rent",
        PARCEL_DETAILS_DESC, "Free rental; "
        + parcelArea + " sqm; "
        + PRIMMAX + " prims allowed. "
        + "Click the rental sign to claim this land.",
        PARCEL_DETAILS_OWNER, llGetOwner(),
        PARCEL_DETAILS_GROUP, llList2Key(llGetObjectDetails(llGetKey(), [OBJECT_GROUP]), 0),
        PARCEL_DETAILS_CLAIMDATE, 0];
    osSetParcelDetails(parcelPos, rules);
    save_data();
}

string strReplace(string str, string search, string replace) {
    return llDumpList2String(llParseStringKeepNulls((str),[search],[]),replace);
}



// Convert Unix Time to SLT, identifying whether it is currently PST or PDT (i.e. Daylight Saving aware)
// Omei Qunhua December 2013

list weekdays = ["Sun", "Mon", "Tue", "Wed", "Thu", "Fri", "Sat"];

string Unix2PST_PDT(integer insecs)
{
    string str = Convert(insecs - (3600 * 8) );   // PST is 8 hours behind GMT
    if (llGetSubString(str, -3, -1) == "PDT")     // if the result indicates Daylight Saving Time ...
        str = Convert(insecs - (3600 * 7) );      // ... Recompute at 1 hour later
    return str;
}

// This leap year test is correct for all years from 1901 to 2099 and hence is quite adequate for Unix Time computations
integer LeapYear(integer year)
{
    return !(year & 3);
}

integer DaysPerMonth(integer year, integer month)
{
    if (month == 2)      return 28 + LeapYear(year);
    return 30 + ( (month + (month > 7) ) & 1);           // Odd months up to July, and even months after July, have 31 days
}

string Convert(integer insecs)
{
    integer w; integer month; integer daysinyear;
    integer mins = insecs / 60;
    integer secs = insecs % 60;
    integer hours = mins / 60;
    mins = mins % 60;
    integer days = hours / 24;
    hours = hours % 24;
    integer DayOfWeek = (days + 4) % 7;    // 0=Sun thru 6=Sat

    integer years = 1970 +  4 * (days / 1461);
    days = days % 1461;                  // number of days into a 4-year cycle

    @loop;
    daysinyear = 365 + LeapYear(years);
    if (days >= daysinyear)
    {
        days -= daysinyear;
        ++years;
        jump loop;
    }
    ++days;
    //for (w = month = 0; days > w; )
    w = 0;
    month = 0;
    do {
        days -= w;
        w = DaysPerMonth(years, ++month);
    } while (days > w);
    string str =  ((string) years + "-" + llGetSubString ("0" + (string) month, -2, -1) + "-" + llGetSubString ("0" + (string) days, -2, -1) + " " +
    llGetSubString ("0" + (string) hours, -2, -1) + ":" + llGetSubString ("0" + (string) mins, -2, -1) );

    integer LastSunday = days - DayOfWeek;
    string PST_PDT = " PST";                  // start by assuming Pacific Standard Time
    // Up to 2006, PDT is from the first Sunday in April to the last Sunday in October
    // After 2006, PDT is from the 2nd Sunday in March to the first Sunday in November
    if (years > 2006 && month == 3  && LastSunday >  7)     PST_PDT = " PDT";
    if (month > 3)                                          PST_PDT = " PDT";
    if (month > 10)                                         PST_PDT = " PST";
    if (years < 2007 && month == 10 && LastSunday > 24)     PST_PDT = " PST";
    return (llList2String(weekdays, DayOfWeek) + " " + str + PST_PDT);
}

checkValidPosition()
{
    debug("checking position");
    vector currentPos = llGetPos() + parcelDistance * llGetRot();
    if(parcelPos != currentPos)
    {
        debug("position was " + (string)parcelPos + " and is now " + (string)currentPos);
        state waiting;
    }
    debug("checking marker");
    currentPos = llList2Vector(llGetLinkPrimitiveParams(checkerLink, [ PRIM_POS_LOCAL ]), 0);
    if(currentPos != <0,0,0>)
    {
        debug("marker is out: " + (string)currentPos);
        state waiting;
    }
}

string fontName = "Impact";
integer fontSize = 36;
integer lineHeight = 36;
integer margin = 24;
string textColor = "Black";
string backgroundColor = "White";
string position = "center";
integer cropToFit = TRUE;
integer textureWidth = 512;
integer textureHeight = 64;
list textureSides = [ 1,3 ];
//string otherTexture = TEXTURE_BLANK;

getConfig()
{
    if(llGetInventoryType(configFile) != INVENTORY_NOTECARD) {
        debug("no config file, using defaults");
        return;
    }
    list lines = llParseString2List(osGetNotecard(configFile), "\n", "");
    integer count = llGetListLength(lines);
    integer i = 0;
    do
    {
        string line = llStringTrim(llList2String(lines, i), STRING_TRIM);
        if(llGetSubString(line, 0, 1) != "//"
        && llSubStringIndex(line, "=") > 0 )
        {

            list params = llParseString2List(line, ["="], []);
            string var = llStringTrim(llList2String(params, 0), STRING_TRIM);
            string val = llStringTrim(llList2String(params, 1), STRING_TRIM);
            debug(var + "=" + val);
            if(var == "fontName")  fontName = val;
            else if(var == "fontSize") fontSize = (integer)val;
            else if(var == "lineHeight") lineHeight = (integer)val;
            else if(var == "margin") margin = (integer)val;
            else if(var == "textColor") textColor = val;
            else if(var == "backgroundColor") backgroundColor = val;
            else if(var == "position") position = val;
            else if(var == "cropToFit") cropToFit = (integer)val;
            else if(var == "textureWidth") textureWidth = (integer)val;
            else if(var == "textureHeight") textureHeight = (integer)val;
            else if(var == "textureSides") textureSides = llParseString2List(val, ",", "");

            else if(var == "parcelDistance") parcelDistance = (vector)val;
            else if(var == "checkerLink") checkerLink = (integer)val;
            else if(var == "texture_expired") texture_expired = val;
            else if(var == "texture_unleased") texture_unleased = val;
            else if(var == "texture_busy") texture_busy = val;
            else if(var == "SIZE_UNLEASED") SIZE_UNLEASED = (vector)val;
            else if(var == "SIZE_LEASED") SIZE_LEASED = (vector)val;
        }
        i++;
    }
    while (i < count);
}

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

drawText(string text)
{
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
        //otherTexture = TEXTURE_TRANSPARENT;
    }
    integer i = 0;
    do
    {
        integer face=llList2Integer(textureSides, i);
        osSetDynamicTextureDataBlendFace("", "vector", commandList, "width:"+(string)textureWidth+",height:"+(string)textureHeight+",bgcolor:" + backgroundColor + ",alpha:"+alpha, FALSE, 2, 0, 255, face);
        i++;
    }
    while (i < llGetListLength(textureSides));
}

setTexture(string texture, list faces)
{
    integer i = 0;
    do
    {
        integer face=llList2Integer(faces, i);
        llSetTexture(texture,face);
        i++;
    }
    while (i < llGetListLength(faces));
}
setTexture(string texture, integer face)
{
        llSetTexture(texture,face);
}

integer firstLaunch = TRUE;

default
{
    state_entry()
    {
        parcelPos = llGetPos() + parcelDistance * llGetRot();
        parcelArea = llList2Integer(llGetParcelDetails(parcelPos, [PARCEL_DETAILS_AREA]),0);
        checkValidPosition();

        getConfig();
        load_data();
        llSetScale(SIZE_LEASED);
        setTexture(texture_expired,textureSides);
        load_data();
        if(firstLaunch)
        {
            firstLaunch = FALSE;
            llWhisper(0,"Activating...");
            if (MY_STATE == 0)
            state unleased;
            else if (MY_STATE == 1)
            state leased;
        }
        llOwnerSay("Click this rental box to activate after configuring the DESCRIPTION.");
        llSetText("DISABLED",<0,0,0>, 1.0);
    }

    touch_start(integer total_number)
    {
        touchedKey = llDetectedKey(0);
        if (touchedKey == llGetOwner())
        {
            llSay(0,"Activating...");
            load_data();
            if (MY_STATE == 0)
                state unleased;
            else if (MY_STATE == 1)
                state leased;
        }
    }
    on_rez(integer start_param)
    {
        debug("rez (from default)");
        state waiting;
    }
    changed(integer change)
    {
        if(change & CHANGED_LINK)
        {
            debug("CHANGED_LINK (from default)");
            state waiting;
        }
    }
}

state unleased
{
    state_entry()
    {
        debug("state unleased");
        load_data();
        if (MY_STATE !=0 || PERIOD == 0)
        {
            debug("MY_STATE:" + (string) MY_STATE);
            debug("PERIOD:" + (string) PERIOD);
            debug("IS_RENEWABLE:" + (string) IS_RENEWABLE);
            llOwnerSay("Returning to default. Data is not correct.");
            state default;
        }

        llSetScale(SIZE_UNLEASED);

        //Blank texture
        setTexture(TEXTURE_BLANK,textureSides);

        setTexture(texture_unleased,textureSides);
        //llOwnerSay("Lease script is unleased");
        llSetText("",<1,0,0>, 1.0);
        reclaimParcel();
        llWhisper(0,"Ready for rental");
    }

    listen(integer channel, string name, key id, string message)
    {
        dialogActiveFlag = FALSE;
        llSetTimerEvent(0);
        llListenRemove(listener);

        load_data();

        if (message == "Yes")
        {
            llInstantMessage(touchedKey,"Thanks for claiming this spot! Please wait a few moments...");
            MY_STATE = 1;
            LEASER = llKey2Name(touchedKey);
            string shortName = llStringTrim(strReplace( llList2String(llParseStringKeepNulls(LEASER,["@"],[]), 0), ".", " "), STRING_TRIM);
            LEASERID = touchedKey;
            LEASED_UNTIL = llGetUnixTime() + (integer) (DAYSEC * PERIOD);
            debug("Remaining time:" +  timespan(llGetUnixTime()-LEASED_UNTIL));

            SENT_WARNING = FALSE;
            save_data();
            llInstantMessage(llGetOwner(), "NEW CLAIM -" +  get_rentalbox_info());
            list rules =[
                PARCEL_DETAILS_NAME, shortName + "'s land",
                PARCEL_DETAILS_DESC, LEASER + "'s land; "
                    + parcelArea + " sqm; "
                    + PRIMMAX + " prims allowed.",
                PARCEL_DETAILS_OWNER, LEASERID,
                PARCEL_DETAILS_GROUP, NULL_KEY,
                PARCEL_DETAILS_CLAIMDATE, 0];
            osSetParcelDetails(parcelPos, rules);
            llSetText("",<1,0,0>, 1.0);
            llInstantMessage(LEASERID,"Your parcel is ready.\n"
            + get_rentalbox_url());
            state leased;
        }
    }

    touch_start(integer total_number)
    {
        touchedKey = llDetectedKey(0);
        if(touchedKey == llGetOwner()) checkValidPosition();

        debug("touch event in unleased");
        load_data();
        llSay(0,"Claim Info");

        llSay(0, "Available for "  + (string)PERIOD + " days ");
        //llSay(0, "Initial Min. Lease Time: " + (string)PERIOD  + " days");
        llSay(0, "Max Lease Length: " + (string)MAXPERIOD + " days");
        llSay(0, "Max Prims: " + (string)PRIMMAX);

        if(llGetInventoryNumber(INVENTORY_NOTECARD) > 0 ) {
            llGiveInventory(touchedKey,llGetInventoryName(INVENTORY_NOTECARD,0));
            llSay(0, "Please read the covenant before renting");
        }
        dialog();
    }

    // clear out the channel listener, the menu timed out
    timer()
    {
        dialogActiveFlag = FALSE;
        llListenRemove(listener);
    }
    on_rez(integer start_param)
    {
        debug("rez (from unleased)");
        state waiting;
    }
    changed(integer change)
    {
        if(change & CHANGED_LINK)
        {
            debug("CHANGED_LINK (from unleased)");
            state waiting;
        }
    }
}

state leased
{
    state_entry()
    {
        setTexture(texture_busy,textureSides);
        debug("Leased mode");
        debug((string)llGetUnixTime());

        load_data();
        if (MY_STATE != 1 || PERIOD == 0 || LEASER == "")
        {
            debug("MY_STATE:" + (string) MY_STATE);
            debug("PERIOD:" + (string) PERIOD);
            debug("LEASER:" + (string) LEASER);

            MY_STATE = 0;
            save_data();
            llOwnerSay("Returning to unleased. Data was not correct.");
            state unleased;
        }
        llSetScale(SIZE_LEASED);
        string parcelName = (string)llGetParcelDetails(parcelPos, [PARCEL_DETAILS_NAME]);
        drawText(parcelName);

        debug("Remaining time:" +  timespan(llGetUnixTime()-LEASED_UNTIL));

        llSetTimerEvent(1); //check now
        llWhisper(0,"Ready");
    }

    listen(integer channel, string name, key id, string message)
    {
        debug("listen event in leased");
        dialogActiveFlag = FALSE;
        if (message == "Yes")
        {
            load_data();
            if (MY_STATE != 1 || PERIOD == 0 || LEASER == "")
            {
                debug("MY_STATE:" + (string) MY_STATE);
                debug("PERIOD:" + (string) PERIOD);
                debug("LEASER:" + (string) LEASER);

                MY_STATE = 0;
                save_data();
                llSay(0,"Returning to unleased. Data is not correct.");
                state unleased;
            }
            else if (IS_RENEWABLE)
            {
                integer timeleft = LEASED_UNTIL - llGetUnixTime();

                debug("Remaining time:" +  timespan(llGetUnixTime()-LEASED_UNTIL));
                debug("DAYSEC:" + (string) DAYSEC);
                debug("timeleft:" + (string) timeleft);
                debug("MAXPERIOD:" + (string) MAXPERIOD);

                if (DAYSEC + timeleft > MAXPERIOD * DAYSEC)
                {
                    llSay(0,"Sorry, you can not claim more than the max time");
                }
                else
                {
                    debug("Leased");
                    SENT_WARNING = FALSE;
                    LEASED_UNTIL += (integer) PERIOD;
                    debug("Leased until " + (string)LEASED_UNTIL );
                    save_data();
                    llSetScale(SIZE_LEASED);
                    //setTexture(texture_leased,textureSides);
                    llSetText("",<1,0,0>, 1.0);
                    llInstantMessage(llGetOwner(), "Renewed: " + get_rentalbox_info());
                }
            }
            else
            {
                llSay(0,"Sorry you can not renew at this time.");
            }
        } else if (message == "Abandon") {
            reclaimParcel();
            llInstantMessage(LEASERID, "You abandonned your land, it has been reset to the estate owner. Please cleanup the parcel. Objects owned by you on the parcel will be returned soon.");
            MY_STATE = 0;
            save_data();
            state unleased;
        }
    }

    timer()
    {
        if (dialogActiveFlag)
        {
            dialogActiveFlag = FALSE;
            llListenRemove(listener);
            return;
        }


        if(!DEBUG)
            llSetTimerEvent(900); //15 minute checks
        else
            llSetTimerEvent(30); // 30  second checks for

        debug("timer event in leased");

        load_data();

        if (MY_STATE != 1 || PERIOD == 0 || LEASER == "")
        {
            debug("MY_STATE:" + (string) MY_STATE);
            debug("PERIOD:" + (string) PERIOD);
            debug("LEASER:" + (string) LEASER);

            MY_STATE = 0;
            save_data();
            llSay(0,"Returning to unleased. Data is not correct.");
            state unleased;
        }

        integer count = llGetParcelPrimCount(parcelPos,PARCEL_COUNT_TOTAL, FALSE);

        if (count -1  > PRIMMAX && !SENT_PRIMWARNING) // no need to countthe sign, too.
        {
            llInstantMessage(LEASERID, get_rentalbox_info() + " There are supposed to be no more than " + (string)PRIMMAX
                + " prims rezzed, yet there are "
                +(string) count + " prims rezzed on this parcel. Plese remove the excess.");
            llInstantMessage(llGetOwner(),  get_rentalbox_info() + " There are supposed to be no more than " + (string)PRIMMAX
                + " prims rezzed, yet there are "
                +(string) count + " prims rezzed on this parcel, warning sent to " + LEASER );
            SENT_PRIMWARNING = TRUE;
        } else {
            SENT_PRIMWARNING = FALSE;
        }



        debug("Remaining time:" +  timespan(llGetUnixTime()-LEASED_UNTIL));

        if (IS_RENEWABLE)
        {

            debug( (string) LEASED_UNTIL + " > " + (string) llGetUnixTime());

            debug( "RENTWARNING * DAYSEC " + (string) (RENTWARNING * DAYSEC));

            if (LEASED_UNTIL > llGetUnixTime() && LEASED_UNTIL - llGetUnixTime() < RENTWARNING * DAYSEC)
            {
                debug("Claim must be renewed");
                setTexture(texture_expired,textureSides);
                llSetText("Claim must be renewed!",<1,0,0>, 1.0);
            }
            else if (LEASED_UNTIL < llGetUnixTime()  && llGetUnixTime() - LEASED_UNTIL < GRACEPERIOD * DAYSEC)
            {
                if (!SENT_WARNING)
                {
                    debug("sending warn");
                    llInstantMessage(LEASERID, "Your claim needs to be renewed, please go to your parcel " + get_rentalbox_url() + " and touch the sign to claim it again! - " + get_rentalbox_info());
                    llInstantMessage(llGetOwner(), "CLAIM DUE - " + get_rentalbox_info());
                    SENT_WARNING = TRUE;
                    save_data();
                }
                setTexture(texture_expired,textureSides);
                llSetText("CLAIM IS PAST DUE!",<1,0,0>, 1.0);
            }
            else if (LEASED_UNTIL < llGetUnixTime())
            {
                debug("expired");
                //llInstantMessage(LEASERID, "Your claim has expired. Please clean up the space or contact the space owner.");
                //vector signPos=llGetPos();
                //llSetPos(parcelPos);
                //llReturnObjectsByOwner(LEASERID,  OBJECT_RETURN_PARCEL_OWNER);
                llInstantMessage(LEASERID, "Your claim has expired. Please cleanup the parcel. Objects owned by you on the parcel will be returned soon.");
                llInstantMessage(llGetOwner(), "CLAIM EXPIRED: CLEANUP! -  " + get_rentalbox_info());
                reclaimParcel();
                MY_STATE = 0;
                save_data();
                state unleased;
            }
        }
        else if (LEASED_UNTIL < llGetUnixTime())
        {
            llInstantMessage(llGetOwner(), "CLAIM EXPIRED: CLEANUP! -  " + get_rentalbox_info());
            debug("TIME EXPIRED. RETURNING TO DEFAULT");
            reclaimParcel();
            MY_STATE = 0;
            save_data();
            state unleased;
            //state default;
        }
    }

    touch_start(integer total_number)
    {
        debug("touch event in leased");
        touchedKey = llDetectedKey(0);
        if(touchedKey == llGetOwner()) checkValidPosition();

        load_data();

        if (MY_STATE != 1 || PERIOD == 0 || LEASER == "" )
        {
            debug("MY_STATE:" + (string) MY_STATE);
            debug("PERIOD:" + (string) PERIOD);
            debug("LEASER:" + (string) LEASER);

            MY_STATE = 0;
            save_data();
            llSay(0,"Returning to unleased. Data is not correct.");
            state unleased;
        }


        llSay(0,"Space currently rented by " + LEASER);

        if(LEASED_UNTIL < llGetUnixTime())
        llSay(0,"Claim due since " + timespan(llGetUnixTime()-LEASED_UNTIL));

        // same as money
        if (touchedKey == LEASERID && IS_RENEWABLE)
        {
            string parcelName = (string)llGetParcelDetails(parcelPos, [PARCEL_DETAILS_NAME]);
            drawText(parcelName);

            LEASED_UNTIL = llGetUnixTime() + (integer) (DAYSEC * PERIOD);
            llSay(0, "Renewed until " + Unix2PST_PDT(LEASED_UNTIL));
            dialog();
        } else {
            llSay(0, "Leased until " + Unix2PST_PDT(LEASED_UNTIL));
        }

        // same as money
        if (touchedKey == LEASERID && !IS_RENEWABLE)
        {
             llSay(0,"The parcel cannot be claimed again");
        }
    }
    on_rez(integer start_param)
    {
        debug("rez (from leased)");
        state waiting;
    }
    changed(integer change)
    {
        if(change & CHANGED_LINK)
        {
            debug("CHANGED_LINK (from leased)");
            state waiting;
        }
    }
}

state waiting
{
    state_entry()
    {
        debug("entering wait state");
        integer positionConfirmed = TRUE;
        positionConfirmed = FALSE;
        llSetLinkPrimitiveParamsFast(checkerLink, [
        PRIM_POS_LOCAL, parcelDistance,
        PRIM_COLOR, ALL_SIDES, <1,1,0>, 0.75,
        PRIM_GLOW, ALL_SIDES, 0.05,
        PRIM_SIZE, <0.25,0.25,5>
        ]);
        integer channel = llCeil(llFrand(1000000)) + 100000 * -1; // negative channel # cannot be typed
        listener = llListen(channel,"","","");
        llDialog(llGetOwner(),
        "WARNING:\n"
        + "Place the vendor OUTSIDE the rented parcel, but make sure the YELLOW MARK stays INSIDE the rented parcel. Then click the Checked button.",
        ["Checked"],
        channel);
        //
    }
    listen(integer channel, string name, key id, string message)
    {
        if(id == llGetOwner() && message == "Checked")
        {
            debug("verified");
            llSetLinkPrimitiveParamsFast(checkerLink, [
            PRIM_POS_LOCAL, <0,0,0>,
            PRIM_COLOR, ALL_SIDES, <1,1,1>, 0.0,
            PRIM_GLOW, ALL_SIDES, 0.00,
            PRIM_SIZE, <0.01,0.01,0.1>
            ]);
            llSleep(5);
            firstLaunch = FALSE;
            state default;
        }
    }
    on_rez(integer start_param)
    {
        debug("rez (from waiting)");
        state waiting;
    }
    changed(integer change)
    {
        if(change & CHANGED_LINK)
        {
            debug("CHANGED_LINK (from waiting)");
            state waiting;
        }
    }
}
