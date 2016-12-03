// Gudule's free land rental
// Version: 1.0.1
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
// - The vendor HAS TO BE outside the rented land. Place it at 1m
// of the rented land border. The script uses parcelDistance to
// calculate the actual rented parcel. Pay attention to the the sign
// orientation.

// User configurable variables:
integer debug = FALSE;    // set to TRUE to see debug info
vector parcelDistance = <0,2,0>; // distance between the rental sign and the parcel (allow to place the sign outside the parcel)

// named texture have to be placed in the prim inventory:
string texture_expired = TEXTURE_BLANK;          // lease is expired
string texture_unleased = "texture_unleased";     // leased signage, large size
string texture_busy = TEXTURE_BLANK;       // busy signage
string texture_leased = TEXTURE_BLANK;               // leased  - in use

vector SIZE_UNLEASED = <1,0.2,0.5>;        // the signs size when unrented
vector SIZE_LEASED = <0.2,0.2,0.2>;        // the signs size when rented (it will shrink)

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

string  debugINFO  = "0.041,1,100,1,0.0104,1"; // Default config info in debug mode if you didn't change the description when this script is first executed (1 hour rental, max 1 day, warn 4h before, grace 1 day)
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


DEBUG(string data)
{
    if (debug)
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
    else if (debug)
    {
        my_data = llCSV2List(debugINFO);    // 5 minute fast timers
    }

    len = llGetListLength(my_data);

    PERIOD = (float) llList2String(my_data,0);
    DEBUG("PERIOD FROM DESCRIPTION " + (string) PERIOD);

    MAXPERIOD = (float) llList2String(my_data,1);
    DEBUG("MAXPERIOD FROM DESCRIPTION " + (string) MAXPERIOD);

    //PRIMMAX = (integer) llList2String(my_data,2);
    PRIMMAX = llGetParcelMaxPrims(parcelPos, FALSE);
    DEBUG("PRIMMAX FROM DESCRIPTION : " + (string) PRIMMAX);

    IS_RENEWABLE = (integer) llList2String(my_data, 3);
    DEBUG("IS_RENEWABLE FROM DESCRIPTION : " + (string) IS_RENEWABLE);

    RENTWARNING = (float) llList2String(my_data, 4);
    DEBUG("RENTWARNING FROM DESCRIPTION : " + (string) RENTWARNING);

    GRACEPERIOD = (float) llList2String(my_data, 5);
    DEBUG("GRACEPERIOD FROM DESCRIPTION : " + (string) GRACEPERIOD);

    MY_STATE = (integer) llList2String(my_data, 6);
    DEBUG("MY_STATE FROM DESCRIPTION : " + (string) MY_STATE);

    LEASER = llList2String(my_data, 7);
    DEBUG("LEASER FROM DESCRIPTION : " + (string) LEASER);

    LEASERID = (key) llList2String(my_data, 8);
    DEBUG("LEASERID FROM DESCRIPTION : " + (string) LEASERID);

    LEASED_UNTIL = (integer) llList2String(my_data, 9);
    DEBUG("LEASED_UNTIL FROM DESCRIPTION : " + (string) LEASED_UNTIL);

    SENT_WARNING = (integer) llList2String(my_data, 10);
    DEBUG("SENT_WARNING FROM DESCRIPTION : " + (string) SENT_WARNING);

}

save_data()
{
    DEBUG("Data saved in description");
    my_data =  [(string)PERIOD, (string)MAXPERIOD, (string)PRIMMAX, (string)IS_RENEWABLE, (string) RENTWARNING, (string) GRACEPERIOD, (string)MY_STATE, (string)LEASER, (string) LEASERID,  (string)LEASED_UNTIL, (string)SENT_WARNING  ];
    llSetObjectDesc(llList2CSV(my_data));
    initINFO = llList2CSV(my_data);   // for debugging in LSL Editor.
    debugINFO = initINFO;  // for debugging in fast mode

    load_data() ;    // to print it in case of debug
}

reclaimParcel()
{
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

integer firstLaunch = TRUE;

default
{
    state_entry()
    {
        parcelPos = llGetPos() + parcelDistance * llGetRot();
        parcelArea = llList2Integer(llGetParcelDetails(parcelPos, [PARCEL_DETAILS_AREA]),0);
        load_data();
        llSetScale(SIZE_LEASED);
        llSetTexture(texture_expired,ALL_SIDES);
        load_data();
        if(firstLaunch)
        {
            firstLaunch = FALSE;
            llSay(0,"Activating...");
            if (MY_STATE == 0)
            state unleased;
            else if (MY_STATE == 1)
            state leased;
        }
        llOwnerSay("Click this rental box to activate after configuring the DESCRIPTION.");
        llSetText("DISABLED",<0,0,0>, 1.0);
    }

    on_rez(integer start_param)
    {
        load_data();
    }

    touch_start(integer total_number)
    {
        if (llDetectedKey(0) == llGetOwner())
        {
            load_data();

            llSay(0,"Activating...");
            if (MY_STATE == 0)
                state unleased;
            else if (MY_STATE == 1)
                state leased;
        }
    }
}

state unleased
{
    state_entry()
    {
        DEBUG("state unleased");
        load_data();
        if (MY_STATE !=0 || PERIOD == 0)
        {
            DEBUG("MY_STATE:" + (string) MY_STATE);
            DEBUG("PERIOD:" + (string) PERIOD);
            DEBUG("IS_RENEWABLE:" + (string) IS_RENEWABLE);
            llOwnerSay("Returning to default. Data is not correct.");
            state default;
        }

        llSetScale(SIZE_UNLEASED);

        //Blank texture
        llSetTexture(TEXTURE_BLANK,ALL_SIDES);

        llSetTexture(texture_unleased,1);
        llSetTexture(texture_unleased,3);
        //llOwnerSay("Lease script is unleased");
        llSetText("",<1,0,0>, 1.0);
        reclaimParcel();
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
            string shortName = llStringTrim(strReplace( llList2String(llParseStringKeepNulls(llKey2Name(touchedKey),["@"],[]), 0), ".", " "), STRING_TRIM);
            LEASERID = touchedKey;
            LEASED_UNTIL = llGetUnixTime() + (integer) (DAYSEC * PERIOD);
            DEBUG("Remaining time:" +  timespan(llGetUnixTime()-LEASED_UNTIL));

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
            state leased;
        }
    }

    touch_start(integer total_number)
    {
        DEBUG("touch event in unleased");
        load_data();
        llSay(0,"Claim Info");

        llSay(0, "Available for "  + (string)PERIOD + " days ");
        //llSay(0, "Initial Min. Lease Time: " + (string)PERIOD  + " days");
        llSay(0, "Max Lease Length: " + (string)MAXPERIOD + " days");
        llSay(0, "Max Prims: " + (string)PRIMMAX);

        touchedKey = llDetectedKey(0);
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
}

state leased
{
    state_entry()
    {
        llSetTexture(texture_busy,ALL_SIDES);
        DEBUG("Leased mode");
        DEBUG((string)llGetUnixTime());

        load_data();
        llSetScale(SIZE_LEASED);
        llSetText("",<1,0,0>, 1.0);
        if (MY_STATE != 1 || PERIOD == 0 || LEASER == "")
        {
            DEBUG("MY_STATE:" + (string) MY_STATE);
            DEBUG("PERIOD:" + (string) PERIOD);
            DEBUG("LEASER:" + (string) LEASER);

            MY_STATE = 0;
            save_data();
            llOwnerSay("Returning to unleased. Data was not correct.");
            state unleased;
        }
        llInstantMessage(LEASERID,"Your parcel is ready.\n"
        + get_rentalbox_url());

        DEBUG("Remaining time:" +  timespan(llGetUnixTime()-LEASED_UNTIL));

            llSetTimerEvent(1); //check now
    }

    listen(integer channel, string name, key id, string message)
    {
        DEBUG("listen event in leased");
        dialogActiveFlag = FALSE;
        if (message == "Yes")
        {
            load_data();
            if (MY_STATE != 1 || PERIOD == 0 || LEASER == "")
            {
                DEBUG("MY_STATE:" + (string) MY_STATE);
                DEBUG("PERIOD:" + (string) PERIOD);
                DEBUG("LEASER:" + (string) LEASER);

                MY_STATE = 0;
                save_data();
                llSay(0,"Returning to unleased. Data is not correct.");
                state unleased;
            }
            else if (IS_RENEWABLE)
            {
                integer timeleft = LEASED_UNTIL - llGetUnixTime();

                DEBUG("Remaining time:" +  timespan(llGetUnixTime()-LEASED_UNTIL));
                DEBUG("DAYSEC:" + (string) DAYSEC);
                DEBUG("timeleft:" + (string) timeleft);
                DEBUG("MAXPERIOD:" + (string) MAXPERIOD);

                if (DAYSEC + timeleft > MAXPERIOD * DAYSEC)
                {
                    llSay(0,"Sorry, you can not claim more than the max time");
                }
                else
                {
                    DEBUG("Leased");
                    SENT_WARNING = FALSE;
                    LEASED_UNTIL += (integer) PERIOD;
                    DEBUG("Leased until " + (string)LEASED_UNTIL );
                    save_data();
                    llSetScale(SIZE_LEASED);
                    llSetTexture(texture_leased,ALL_SIDES);
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


        if(!debug)
            llSetTimerEvent(900); //15 minute checks
        else
            llSetTimerEvent(30); // 30  second checks for

        DEBUG("timer event in leased");

        load_data();

        if (MY_STATE != 1 || PERIOD == 0 || LEASER == "")
        {
            DEBUG("MY_STATE:" + (string) MY_STATE);
            DEBUG("PERIOD:" + (string) PERIOD);
            DEBUG("LEASER:" + (string) LEASER);

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



        DEBUG("Remaining time:" +  timespan(llGetUnixTime()-LEASED_UNTIL));

        if (IS_RENEWABLE)
        {

            DEBUG( (string) LEASED_UNTIL + " > " + (string) llGetUnixTime());

            DEBUG( "RENTWARNING * DAYSEC " + (string) (RENTWARNING * DAYSEC));

            if (LEASED_UNTIL > llGetUnixTime() && LEASED_UNTIL - llGetUnixTime() < RENTWARNING * DAYSEC)
            {
                DEBUG("Claim must be renewed");
                llSetTexture(texture_expired,ALL_SIDES);
                llSetText("Claim must be renewed!",<1,0,0>, 1.0);
            }
            else if (LEASED_UNTIL < llGetUnixTime()  && llGetUnixTime() - LEASED_UNTIL < GRACEPERIOD * DAYSEC)
            {
                if (!SENT_WARNING)
                {
                    DEBUG("sending warn");
                    llInstantMessage(LEASERID, "Your claim needs to be renewed, please go to your parcel " + get_rentalbox_url() + " and touch the sign to claim it again! - " + get_rentalbox_info());
                    llInstantMessage(llGetOwner(), "CLAIM DUE - " + get_rentalbox_info());
                    SENT_WARNING = TRUE;
                    save_data();
                }
                llSetTexture(texture_expired,ALL_SIDES);
                llSetText("CLAIM IS PAST DUE!",<1,0,0>, 1.0);
            }
            else if (LEASED_UNTIL < llGetUnixTime())
            {
                DEBUG("expired");
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
            DEBUG("TIME EXPIRED. RETURNING TO DEFAULT");
            reclaimParcel();
            MY_STATE = 0;
            save_data();
            state unleased;
            //state default;
        }
    }

    touch_start(integer total_number)
    {
        DEBUG("touch event in leased");
        load_data();

        if (MY_STATE != 1 || PERIOD == 0 || LEASER == "" )
        {
            DEBUG("MY_STATE:" + (string) MY_STATE);
            DEBUG("PERIOD:" + (string) PERIOD);
            DEBUG("LEASER:" + (string) LEASER);

            MY_STATE = 0;
            save_data();
            llSay(0,"Returning to unleased. Data is not correct.");
            state unleased;
        }


        llSay(0,"Space currently rented by " + LEASER);

        if(LEASED_UNTIL < llGetUnixTime())
        llSay(0,"Claim due since " + timespan(llGetUnixTime()-LEASED_UNTIL));

        // same as money
        if (llDetectedKey(0) == LEASERID && IS_RENEWABLE)
        {
            touchedKey = llDetectedKey(0);
            LEASED_UNTIL = llGetUnixTime() + (integer) (DAYSEC * PERIOD);
            llSay(0, "Renewed until " + Unix2PST_PDT(LEASED_UNTIL));
            dialog();
        } else {
            llSay(0, "Leased until " + Unix2PST_PDT(LEASED_UNTIL));
        }

        // same as money
        if (llDetectedKey(0) == LEASERID && !IS_RENEWABLE)
        {
             llSay(0,"The parcel cannot be claimed again");
        }
    }
}
