// Gudule's Gate checker
// Version: 0.3.5
// Copyright Gudule Lapointe 2011,2012 - Speculoos.net
// Original script by Diva Canto diva@metaverseink.com

string address;
string destination;
string on_message = "Step in to teleport";
string off_message = "(Offline)";
string checking_message = "Checking status...";
string myGatekeeper;
key http_request_id;
key StatusQuery;
integer fromTouch = 0;

integer internalUpdateChannel = 17;

integer check()
{
    string last_destination = destination;
    destination = llGetObjectDesc();
    if(llStringTrim(destination, STRING_TRIM) == "") {
        return 0;
    }
    destination = llUnescapeURL(destination);
    destination = strReplace(destination, "http://", "");
    destination = strReplace(destination, "secondlife://", "");

    string destinationParse = strReplace(destination, ":", " ");
    destination = strReplace(destination, myGatekeeper + ":", "");
    if(llSubStringIndex(destination, "<") > 0)
        destination = llStringTrim(llGetSubString(destination, 0, llSubStringIndex(destination, "<") - 1), STRING_TRIM);

    list addr_list = llParseString2List(destinationParse, [" "], []);
    string host = llList2String(addr_list, 0);
    string port = llList2String(addr_list, 1);
    string url = "http://" + host + ":" + port + "/get_grid_info";

    llSetText("", <1,1,1>, 1);
    http_request_id = llHTTPRequest(url, [], "");
    return 0; // the address didn't change
}

string strReplace(string str, string search, string replace) {
    return llDumpList2String(llParseStringKeepNulls((str),[search],[]),replace);
}

upgrade() {
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

default
{
    state_entry()
    {
        upgrade();
//        if(llStringTrim(llGetObjectDesc(), STRING_TRIM) != "")
//        {
            myGatekeeper = strReplace(osGetGridGatekeeperURI(), "http://", "");
            llSetTimerEvent(3);
//        }
    }

    timer()
    {
        fromTouch = 0;
        llMessageLinked(LINK_THIS, internalUpdateChannel, "unknown", "");
        check();
        llSetTimerEvent(3600);
    }

    touch_start(integer n)
    {
        check();
        fromTouch = 1;
        integer changed_addr = check();
        if (changed_addr == 1)
            llMessageLinked(LINK_THIS, internalUpdateChannel, "change_address", "");
    }

    link_message(integer sender_num, integer channel, string str, key id)
    {
        if(channel == internalUpdateChannel)
        {
            if(str == "reset") {
                llResetScript();
            }
        }
    }

    http_response(key request_id, integer status, list metadata, string body)
    {
        if (request_id == http_request_id)
        {
            if (fromTouch == 1)
            {
//                llSay(0, address + " status code: " + (string)status);
                fromTouch = 0;
            }
            if (status == 200)
            {
                llMessageLinked(LINK_THIS, internalUpdateChannel, "unknown", "");
//                llMessageLinked(LINK_THIS, internalUpdateChannel, "gridonline", "");
                StatusQuery = llRequestSimulatorData(destination, DATA_SIM_STATUS);

//                llSetText(destination + "\n" + on_message + "\n\n", <1, 0.6,0>, 1);
            }
            else if (status == 499)
            {
                llMessageLinked(LINK_THIS, internalUpdateChannel, "down", "");
//                llSetText(destination + "\n" + off_message + "\n\n", <1, 0.6,0>, 1);
            }
            else
            {
                llMessageLinked(LINK_THIS, internalUpdateChannel, "unknown", "");
//                llSetText(destination + "\n" + off_message + "\n\n", <1, 0.6,0>, 1);
            }
        }
    }
    dataserver(key queryId, string data)
    {
        if (queryId == StatusQuery) {
            StatusQuery = "";
            llMessageLinked(LINK_THIS, internalUpdateChannel, data, "");
        }
    }
}
