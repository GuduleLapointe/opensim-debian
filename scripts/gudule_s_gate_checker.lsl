//
// Diva Canto diva@metaverseink.com
//

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

cleanup()
{
    llRemoveInventory("Gudule's Gate checker 0.2");
    llRemoveInventory("Gudule's Gate checker 0.3");
    llRemoveInventory("Gudule's Gate checker 0.3.1");
    llRemoveInventory("Gudule's Gate checker 0.3.2");
    llRemoveInventory("Gudule's Gate checker 0.3.3");
}

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


default
{
    state_entry()
    {
        cleanup();
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
