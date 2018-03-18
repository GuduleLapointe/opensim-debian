// Gudule's HGBoard (based on Jeff Kelley's HGBoard)
// Version 2018.8
// (c) The owner of Avatar Jeff Kelley, 2010
// (c) Gudule Lapointe 2016

// This script is licensed under Creative Commons BY-NC-SA
// See <http://creativecommons.org/licenses/by-nc-sa/3.0/>
// You may not use this work for commercial purposes.
// You must attribute the work to the authors, Jeff Kelley and Gudule Lapointe
// You may distribute, alter, transform, or build upon this work
// as long as you do not delete the name of the original authors.

// More info after the configurable parameters

integer TEXTURE_HEIGHT = 512; // a square of 2: 256, 512, 1024...
integer TEXTURE_WIDTH = 256; // a square of 2: 256, 512, 1024...
string FONT_NAME = "Impact"; // Depends on installed system fonts
string FONT_COLOR = "Black";
integer FONT_SIZE = 18; // Depends on TEXTURE_HEIGHT
integer COLUMNS = 1;
integer ROWS = 12;

integer PADDING_LEFT = 14;
integer PADDING_TOP = 8;

string validCellColor   = "White";
string invalCellColor   = "IndianRed";
string localCellColor   = "Green";
string emptyCellColor   = "transparent";
string cellBorderColor  = "transparent"; // "White";
integer cellBorderSize  = 5; // "White";
string backgroundColor  = "transparent"; // "Gray";

// At the moment, we support three datasources:
//  http://example.com/url  A web file
//  card://cardname         A notecard in the objects's inventory
//  card://uuid/cardname    A notecard from the LSL server 'uuid'
//                          (requires a server script, not included)
// string datasource = "card://Destinations";
// string datasource = "card://e511d6c0-7588-4157-a684-8ca5f685a077/Destinations";
// string datasource = "http://my_web_server/path_to_file/Destinations";
// Pseudo source "desc://" reads datasource setting from prim description.
//
// Datasource format for notecard or web page:
//  grid|region|global location|url|landing point
// Examples:
//  Speculoos|||speculoos.world:8002
//  Speculoos|Grand Place|5000,5000|speculoos.world:8002|128,128,25
// Grid name and url are mandatory, other values are optional.
// Lines with "|" but no values are treated as separators

string datasource = "desc://";
integer AUTO_REFRESH = 3600; // for http datasource, set zero to disable

integer DISPLAY_SIDE = -1; // touch active only on this side.
//                            If set to -1, all sides are active

// End of configurable parameters

// 2016 Additions:
//  - read datasource url from prim description
//  - global location check (4096 rule) is disabled by default
//  - disable location check anyway if no location given
//  - fix bad detection of empty cells
//  - allow different texture height and widh (must still be 256,512 or 1024)
//  - allow disabling touched face check (now disabled by default)
//  - correct "outside touch" detection if not root prim or non linked
//  - filters local gatekeeper from destination
//  - transparent background and border
//  - border size
//  - padding
//      backgroundColor = "transparent" sets the prim alpha
//      validCellColor = "transparent" makes cell backround same as backgroundColor
//      cellBorderColor = "transparent" disables drawing border, cellBorderSize  is still used to set margin between cells
//      cellBorderSize = size of the cells border or margin
//  - PADDING_TOP and PADDING_LEFT set the margin inside the cells

integer DEBUG_ON = FALSE;
DEBUG(string message) {if (DEBUG_ON) llOwnerSay(message);}


///////////////////////////////////////////////////////////////////
// Part 1 : Datasource
///////////////////////////////////////////////////////////////////

string datasorceData;   // The content of the datasource

readFile(string source) {
    if(source=="desc://") source=llGetObjectDesc();
    if(source=="") source="card://" + llGetInventoryName(INVENTORY_NOTECARD, 0);
    if(source=="card://") {
        llOwnerSay("datasource not set or invalid");
        return;
    }
    //destinations=["Empty destinations"];
    if(firstRun) llOwnerSay ("Reading data from "+ source);
    list parse = llParseString2List (source, ["/"],[]);
    string s0 = llList2String (parse, 0);
    string s1 = llList2String (parse, 1);
    string s2 = llList2String (parse, 2);

    // Web server: fall through http_response event

    if (s0 == "http:") sendHTTPRequest (source);

    // LSL server : fall through dataserver event
    // If no UUID : fall through dataserver event

    if (s0 == "card:")
        if (isUUID(s1)) osMessageObject ((key)s1, "READ "+s2);
        else            readNotecard (s1);
}

integer isUUID (string s) {
    list parse = llParseString2List (s, ["-"],[]);
    return ((llStringLength (llList2String (parse, 0)) == 8)
        &&  (llStringLength (llList2String (parse, 1)) == 4)
        &&  (llStringLength (llList2String (parse, 2)) == 4)
        &&  (llStringLength (llList2String (parse, 3)) == 4)
        &&  (llStringLength (llList2String (parse, 4)) == 12));
}

///////////////////
// Notecard reader
///////////////////

// Since osGetNotecard has Threat Level = VeryHigh
// we stick to the old, slow, clumsy notecard reader

string ncName;      // Name of the notecard to be read
key ncQueryID;      // id of dataserver queries
integer ncLine;     // Current line being read

readNotecard (string name) {
    ncName = name;
    ncLine = 0;
    ncQueryID = llGetNotecardLine(ncName, ncLine++);  // request first line
}

addtoNotecard (string data) {
    datasorceData += data+"\n";
    ncQueryID = llGetNotecardLine(ncName, ncLine++);  // Request next line
}

///////////////////
// Http stuff
///////////////////

key httpQueryId;

sendHTTPRequest (string url) {
    httpQueryId = llHTTPRequest(url,
        [ HTTP_METHOD,  "GET", HTTP_MIMETYPE,"text/plain;charset=utf-8" ], "");
}

string URI2hostport (string uri) {
    list parse = llParseString2List (uri, [":"], []);
    return llList2String (parse, 0)
    +":" + llList2String (parse, 1);
}


///////////////////////////////////////////////////////////////////
// Part 2 : Parsing & accessors
///////////////////////////////////////////////////////////////////

list destinations;      // Strided list for destinations

integer NAME_IDX = 0;   // Index of region name in list
integer COOR_IDX = 1;   // Index of grid coordinates in list
integer HURL_IDX = 2;   // Index of hypergrid url in list
integer HGOK_IDX = 3;   // Index of validity flag in list
integer LAND_IDX = 4;   // Index of landing point in list
integer N_FIELDS = 5;   // Total number of fields
integer checkgloc = FALSE;

parseFile (string data) {
    list lines = llParseString2List (data,["\n"],[]);
    integer nl = llGetListLength (lines);
    integer i; for (i=0;i<nl;i++)
        parseLine (llList2String(lines,i));
}

parseLine (string line) {
    if (line == "") return; // Ignore empty lines

    if (llGetSubString (line,0,1) == "//") {  // Is this a comment?
        if(firstRun) llOwnerSay ("   " + line);
        return;
    }

    list parse  = llParseStringKeepNulls (line, ["|"],[]);
    string grid = llList2String (parse, 0); // Grid name
    string name = llList2String (parse, 1); // Region name
    string gloc = llList2String (parse, 2); // Coordinates
    string hurl = llList2String (parse, 3); // HG url
    string coor = llList2String (parse, 4); // Landing

    // Parse and check grid location

    parse = llParseString2List (gloc, [","],[]);
    integer ok = TRUE; // assuming ok if no gloc set
    if(checkgloc && gloc != "") {
    integer xloc = llList2Integer (parse, 0);  // X grid location
    integer yloc = llList2Integer (parse, 1);  // Y grid location

    vector hisLoc = <xloc, yloc, 0>;
    vector ourLoc = llGetRegionCorner()/256;
    ok =( llAbs(llFloor(hisLoc.x - ourLoc.x)) < 4096 )
        &&  ( llAbs(llFloor(hisLoc.y - ourLoc.y)) < 4096 )
        &&  ( hisLoc != ourLoc);
    } else {
        ok = TRUE;
    }
//    if(!ok) llOwnerSay("gloc: " + gloc);
    // Parse and check landing point

    parse = llParseString2List (coor, [","],[]);
    integer xland = llList2Integer (parse, 0);  // X landing point
    integer yland = llList2Integer (parse, 1);  // Y landing point
    integer zland = llList2Integer (parse, 2);  // Z landing point

    vector land = <xland, yland, zland>;
    if (land == ZERO_VECTOR) land = <128,128,20>;

    // Note: grid and region names merged into one field
    destinations += [grid+" "+name, gloc, hurl, ok, land];
}

///////////////////
// List accessors
///////////////////

string dst_name (integer n) {   // Get name for destination n
    return llList2String (destinations, N_FIELDS*n +NAME_IDX);
}

string dst_coord (integer n) {  // Get coords for destination n
    return llList2String (destinations, N_FIELDS*n +COOR_IDX);
}

vector dst_landp (integer n) {  // Get landing point for destination n
    return llList2Vector (destinations, N_FIELDS*n +LAND_IDX);
}

string dst_hgurl (integer n) {  // Get hypergrid url for destination n
    return llList2String (destinations, N_FIELDS*n +HURL_IDX);
}

integer dst_valid (integer n) { // Get validity flag for destination n
    return llList2Integer (destinations, N_FIELDS*n +HGOK_IDX);
}


////////////////////////////////////////////////////////////
// Part 3 : Drawing
////////////////////////////////////////////////////////////

string drawList;
string localGatekeeper;
string localHGurl;

displayBegin() {
    //if(validCellColor == "transparent") {
//        llSetTexture(TEXTURE_TRANSPARENT, ALL_SIDES);
    //} else {
        //llSetTexture(TEXTURE_BLANK, ALL_SIDES);
    //}
    drawList = "";
}

displayEnd() {
    integer alpha = 255;
    if(backgroundColor == "transparent") alpha = 0;
    osSetDynamicTextureData    ( "", "vector", drawList,
        "width:"+(string)(TEXTURE_WIDTH)
        +",height:"+(string)TEXTURE_HEIGHT
        +",alpha:"+(string)alpha
        +",bgcolor:"+(string)backgroundColor
        , 0);
}

drawCell (integer x, integer y) {
    integer CELL_HEIGHT = (TEXTURE_HEIGHT - cellBorderSize) / ROWS;
    integer CELL_WIDHT  = (TEXTURE_WIDTH - cellBorderSize) / COLUMNS;
    integer xOffset = (TEXTURE_WIDTH-cellBorderSize-CELL_WIDHT*COLUMNS)/2;
    integer yOffset = (TEXTURE_HEIGHT-cellBorderSize-CELL_HEIGHT*ROWS)/2;
    integer xTopLeft    = xOffset + x*CELL_WIDHT;
    integer yTopLeft    = yOffset + y*CELL_HEIGHT;

    // Draw grid

    if(cellBorderColor != "transparent")
    {
        drawList = osSetPenColor   (drawList, cellBorderColor);
        drawList = osMovePen       (drawList, xTopLeft, yTopLeft);
        drawList = osDrawRectangle (drawList, CELL_WIDHT, CELL_HEIGHT);
    }
    integer index = (y+x*ROWS);
    string  cellName  = dst_name(index);
    integer cellValid = dst_valid(index);
    string hurl = dst_hgurl (index);

    string cellBbackground;
    if (cellName == "") cellBbackground = emptyCellColor;   else
    if (hurl == "") cellBbackground = emptyCellColor;   else
    if (cellValid && hurl == localHGurl) cellBbackground = localCellColor;
    else if(cellValid) cellBbackground = validCellColor;
    else cellBbackground = invalCellColor;

    // Fill background

    if(cellBbackground != "transparent") {
        drawList = osSetPenColor         (drawList, cellBbackground);
        drawList = osMovePen             (drawList, xTopLeft+cellBorderSize, yTopLeft+cellBorderSize);
        drawList = osDrawFilledRectangle (drawList, CELL_WIDHT-cellBorderSize, CELL_HEIGHT-cellBorderSize);
    }
    xTopLeft += PADDING_LEFT ;  // Center text in cell
    yTopLeft += PADDING_TOP ;  // Center text in cell
    drawList = osSetPenColor (drawList, FONT_COLOR);
    drawList = osMovePen     (drawList, xTopLeft, yTopLeft);
    drawList = osDrawText    (drawList, cellName);
}

drawTable() {
    displayBegin();
    drawList = osSetPenSize  (drawList, 1);
    drawList = osSetFontSize (drawList, FONT_SIZE);
    drawList = osSetFontName (drawList, FONT_NAME);

    if(backgroundColor != "transparent") {
        drawList = osMovePen     (drawList, 0, 0);
        drawList = osSetPenColor (drawList, backgroundColor);
        drawList = osDrawFilledRectangle (drawList, TEXTURE_WIDTH, TEXTURE_HEIGHT);
    }
    integer x; integer y;
    for (x=0; x<COLUMNS; x++)
        for (y=0; y<ROWS; y++)
            drawCell (x, y);

    displayEnd();
}

integer getCellClicked(vector point) {
    integer y = (ROWS-1) - llFloor(point.y*ROWS); // Top to bottom
    integer x = llFloor(point.x*COLUMNS);         // Left to right
    integer index = (y+x*ROWS);
    return index;
}


///////////////////////////////////////////////////////////////////
// Part 4 : Action routnes: when clicked, when http test succeed
///////////////////////////////////////////////////////////////////

string CLICK_SOUND = "clickSound";      // Sound to play when board clicked
string TELPT_SOUND = "teleportSound";   // Sound to play when teleported
integer JUMP_DELAY = 2;                 // Time to wait before teleport

string hippo_url;   // URL for http check
string telep_url;   // For osTeleportAgent
key    telep_key;   // For osTeleportAgent
vector telep_land;  // For osTeleportAgent


integer action (integer index, key who) {
    string name = dst_name  (index);
    string gloc = dst_coord (index);
    vector land = dst_landp (index);
    string hurl = dst_hgurl (index);
    integer ok  = dst_valid (index);

    if (name == "") return FALSE; // Empty cell
    if (hurl == "") return FALSE; // Empty cell
    if (!ok)
    {
        llInstantMessage(who, "This region is too far ("+gloc+")");
        return FALSE; // Incompatible region
    }
    if(gloc != "")
    {
        llInstantMessage(who, "You have selected "+name+", location "+gloc);
    }
    else {
        llInstantMessage(who, "You have selected "+name);
    }
    // Préparer les globales avant de sauter

    telep_key  = who;   // Pass to postaction
    telep_url  = hurl;  // Pass to postaction
    hippo_url = "http://"+URI2hostport(telep_url);   // Pass to http check
    telep_url  = strReplace(telep_url, "http://", "");
    telep_url  = strReplace("http://" + telep_url, localGatekeeper + ":", "");
    telep_url  = strReplace(telep_url, "http://", "");
    // filter local gatekeeper to allow local jumps to HG local address
    telep_land = land;  // Pass to postaction

    DEBUG ("Region name:   " +name +" "+gloc+" (Check="+(string)ok+")");
    DEBUG ("Landing point: " +(string)land);
    DEBUG ("Hypergrid Url: " +hurl);
    DEBUG ("Hippochek url: " +hippo_url);

    llTriggerSound (CLICK_SOUND, 1.0);
    return TRUE;
}

postaction (integer success) {
    if (success) {
        llInstantMessage(telep_key, "Fasten your seat belt, we move!!!");
        llPlaySound (TELPT_SOUND, 1.0); llSleep (JUMP_DELAY);
        osTeleportAgent(telep_key, telep_url, telep_land, ZERO_VECTOR);
    } else {
        llInstantMessage(telep_key, "Sorry, destination grid looks unavailable");
    }
}

string strReplace(string str, string search, string replace) {
    return llDumpList2String(llParseStringKeepNulls((str),[search],[]),replace);
}

///////////////////////////////////////////////////////////////////
// State 1 : read the data and draw the board
///////////////////////////////////////////////////////////////////

integer firstRun = TRUE;

default {

    state_entry() {
        destinations = [];
        datasorceData = "";
        localGatekeeper = osGetGridGatekeeperURI();
        localHGurl = strReplace(localGatekeeper + ":" + llGetRegionName(), "http://", "");
        readFile (datasource);
    }

    // Handler for card:// datasource
    dataserver(key id, string data) {
        // Internal card reader
        if (id == ncQueryID) {
          if (data != EOF) addtoNotecard (data);
          else state ready; // File in datasorceData
        // External card server
        } else {
            datasorceData = data;
            state ready; // File in datasorceData
        }
    }

    // Handler fot http:// datasource
    http_response(key id,integer status, list meta, string body) {
        if (id != httpQueryId) return;
        datasorceData = body;
        state ready; // File in datasorceData
    }

    state_exit() {
        if(firstRun) llOwnerSay ("Done. Initializing board");
        parseFile(datasorceData);
        drawTable();
    }

}

///////////////////////////////////////////////////////////////////
// State 2 : running, we pass most of our time here
///////////////////////////////////////////////////////////////////

state ready {

    state_entry() {
        firstRun = FALSE;
        //llWhisper (0, "Ready");
        if(AUTO_REFRESH > 0)
        llSetTimerEvent ((integer)(AUTO_REFRESH * (0.9 + llFrand(0.2))));
    }

    touch_start (integer n) {
        key whoClick = llDetectedKey(0);
        vector point = llDetectedTouchST(0);
        integer face = llDetectedTouchFace(0);
        integer link = llDetectedLinkNumber(0);

        if (link != llGetLinkNumber())
            if (whoClick == llGetOwner()) llResetScript();
            else return;
        if (point == TOUCH_INVALID_TEXCOORD) return;
        if (face != DISPLAY_SIDE)
            if(DISPLAY_SIDE != -1)
            return;

        integer ok = action (getCellClicked(point), whoClick);
        if (!ok) return; // Incompatible grid coordinates

        integer USE_MAP = (llGetObjectDesc() == "usemap");

        // llMapDestination works only in touch events
        // We must invoke it here, bypassing http check
        // Return so osTeleportAgent will not be called

        if (USE_MAP) {
            llMapDestination (telep_url, telep_land, ZERO_VECTOR);
            return;
        }

        // Proceed to http check which
        // will chain to osTeleportAgent

        llWhisper (0, "Checking host. This may take up to 30s, please wait...");
        state hippos; // Perform HTTP check
    }

    changed(integer what) {
        if (what & CHANGED_REGION) llResetScript();
        if (what & CHANGED_INVENTORY) llResetScript();
        if (what & CHANGED_REGION_RESTART) llResetScript();
    }

    timer() {
        llSetTimerEvent (0);
        state default;
    }
    on_rez(integer start_param)
    {
        llSetTimerEvent (0);
        state default;
    }
}

///////////////////////////////////////////////////////////////////
// State 3 : HTTP host check
///////////////////////////////////////////////////////////////////

state hippos {

    state_entry() {
        DEBUG("checking " + hippo_url);
        sendHTTPRequest (hippo_url);
        llSetTimerEvent (60);
    }

    http_response(key id,integer status, list meta, string body) {
        DEBUG("got answer " + status);
        if (id == httpQueryId)
        postaction (status == 200);
        state ready;
    }

    timer() {
        DEBUG("Destination grid is offline");
        llSetTimerEvent (0);
        postaction (FALSE);
        state ready;
    }

}
