// Products and Updates Vendor
// Version 1.0.0
// (c) Gudule Lapointe 2016

// DONE:
//  - Give content for free (done)
//  - Allow subscribe/unsubscribe for Updates
//  - Detect content changes and update version number
//  - Auto detect name and write it on texture
//  - Send new updates when content has changed
// TODO:
//  - check items permissions and warn if NC/NT
//  - Admin menu (restart, force push, clean up)
//  - Send only to online users, or notify only online users
//      -> so maintain a list of up-to-date users
//  - Allow owner to postpone update sending
//  - Allow selling content for money
//      -> so maintain a list of buyers (elligible for updates)

// This script is licensed under Creative Commons BY-NC-SA
// See <http://creativecommons.org/licenses/by-nc-sa/3.0/>
// You may not use this work for commercial purposes.
// You must attribute the work to the authors (Gudule Lapointe).
// You may distribute, alter, transform, or build upon this work
// as long as you do not delete the name of the original authors.

list buttonsDefault = ["Get items"];
list buttonsAdmin = [
    "Refresh", "Restart", "Empty",
    "+Major", "+Minor", "+Patch"
    ];
list ignore = [ "~subscribers", "~inventory" ];
list inventoryItems;
list inventoryDetails;
string inventoryVersion;
string inventoryKey;
integer INV_STRIDE = 2;
integer INV_NAME = 0;
integer INV_KEY = 1;
list subscribers;
list subscribersKeys;
integer SUBS_STRIDE = 4;
integer SUBS_KEY = 0;
integer SUBS_NAME = 1;
integer SUBS_SEEN = 2;
//integer SUBS_NAME = 1;
integer chat_channel;
string vendorName;
string vendorNameSuffix = " Vendor";
string currentState;
// Begin text drawing library
string fontName = "01 Digit";
integer fontSize = 32;
integer lineHeight = 32;
integer margin = 24;
string fontColor;// = "DarkBlue";
string backgroundColor;
float glow;// = "Cyan";
string activeColor = "DarkBlue";
string activeBackground = "Cyan";
float activeGlow = 0.0;
string inactiveColor = "DarkGray";
string inactiveBackground = "LightGray";
float inactiveGlow = 0.0;
string position = "center";
integer cropToFit = TRUE;
integer textureWidth = 512;
integer textureHeight = 64;
list textureSides = [ 1 ];
//string otherTexture = TEXTURE_BLANK;

string configFile = ".config";
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
            if(var == "fontName")  fontName = val;
            else if(var == "fontSize") fontSize = (integer)val;
            else if(var == "lineHeight") lineHeight = (integer)val;
            else if(var == "margin") margin = (integer)val;
            //else if(var == "fontColor") fontColor = val;
            //else if(var == "backgroundColor") backgroundColor = val;
            else if(var == "activeColor") activeColor = val;
            else if(var == "activeBackground") activeBackground = val;
            else if(var == "activeGlow") activeGlow = (float)val;
            else if(var == "inactiveColor") inactiveColor = val;
            else if(var == "inactiveBackground") inactiveBackground = val;
            else if(var == "inactiveGlow") inactiveGlow = (float)val;
            else if(var == "position") position = val;
            else if(var == "cropToFit") cropToFit = (integer)val;
            else if(var == "textureWidth") textureWidth = (integer)val;
            else if(var == "textureHeight") textureHeight = (integer)val;
            else if(var == "textureSides") textureSides = llParseString2List(val, ",", "");
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
    integer drawFontSize = fontSize;
    vector extents;
    extents = osGetDrawStringSize("vector",text,fontName,fontSize);
    if(extents.x > drawWidth)
    {
        if(cropToFit)
        {
            text = cropText(text, fontName, fontSize, drawWidth);
        } else {
            drawFontSize = (integer)(drawFontSize * drawWidth / extents.x);
        }
    } else {
        x += (integer)((drawWidth - extents.x) / 2);
    }
    extents = osGetDrawStringSize("vector",text,fontName,drawFontSize);
//    extents = osGetDrawStringSize("vector",text,fontName,fontSize);
//    if(extents.y > drawHeight)
//    {
//        textureHeight = (integer)extents.y + 2*margin;
//    } else {
        y += (integer)((drawHeight - extents.y) / 2);
//    }
    commandList = osSetPenColor(commandList, fontColor);
    commandList = osSetFontName(commandList, fontName);
    commandList = osSetFontSize(commandList, drawFontSize);
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
// End text trawing library

debug(string message)
{
    llOwnerSay("/me (" + llGetScriptName() + "): " + message);
}
string strReplace(string str, string search, string replace) {
    return llDumpList2String(llParseStringKeepNulls((str),[search],[]),replace);
}

key avatarName2Key(string userName)
{
    list nameParts = llParseString2List(userName, " ", "");
    string firstName=llList2String(nameParts, 0);
    string lastName=llList2String(nameParts, 1);
    return osAvatarName2Key(firstName, lastName);
}
string firstName(string name)
{
    list parts = llParseString2List(strReplace(name, ".", " "), " ", "");
    return llList2String(parts, 0);
}
string subscriberName(key id)
{
    integer idx = llListFindList(subscribersKeys, id) * SUBS_STRIDE;
    return llList2String(subscribers, idx + SUBS_NAME);
}
list loadList(integer listStride, string fileName)
{
    if(llGetInventoryKey(fileName) == NULL_KEY) return [];
    list list2load;
    list lines = llParseString2List(osGetNotecard(fileName), "\n", "");
    integer l = 0;
    do
    {
        string line=llStringTrim(llList2String(lines, l), STRING_TRIM);
        if(llGetSubString(line, 0, 1) != "//")
        {
            list items = llParseString2List(line, ["|"], []);
            do items += [""];
            while (llGetListLength(items) < listStride);
            if(llGetListLength(items) > listStride)
            items=llList2List(items, 0, listStride-1);
            list2load += items;
        }
        l++;
    }
    while (l < llGetListLength(lines));
    return list2load;
}
loadSubscribersList()
{
    subscribers = loadList(SUBS_STRIDE, "~subscribers");
    subscribersKeys = llList2ListStrided(subscribers, 0, -1, SUBS_STRIDE);
}
saveList(list list2save, integer listStride, string fileName)
{
    //debug("saving " + llGetListLength(list2save) + " lines in " + fileName);
    string contents = "// DO NOT MODIFY THIS FILE\n";
    integer i=0;
    do
    {
        string line = llDumpList2String(llList2List(list2save, i, i + listStride-1), "|");
        contents += line + "\n";
        //debug("adding line " + line);
        i=i+listStride;
    }
    while (i < llGetListLength(list2save));
    //if (llGetInventoryKey("~subscribers" != NULL_KEY));
    llRemoveInventory(fileName);
    osMakeNotecard(fileName, contents);
}
saveSubscribersList()
{
    saveList(subscribers, SUBS_STRIDE, "~subscribers");
    loadSubscribersList();
}

loadItemsList()
{
    if(llGetInventoryKey("~inventory")==NULL_KEY)
    {
        inventoryItems = [ "Not initialized" ];
        inventoryDetails = [ "Not initialized" ];
        inventoryKey = llGenerateKey();
        inventoryVersion = "0.1.0-0";
        vendorName = llGetScriptName();
    } else {
        inventoryDetails = loadList(INV_STRIDE, "~inventory");
        inventoryKey = llList2Key(inventoryDetails, INV_KEY);
        inventoryVersion = llList2Key(inventoryDetails, INV_NAME);
        inventoryDetails = llList2List(inventoryDetails, INV_STRIDE,-1);
        inventoryItems = llList2ListStrided(inventoryDetails, 0,-1, INV_STRIDE);
    }
    debug("Version " + inventoryVersion + " (" + inventoryKey + ")");
    checkItemList();
}
checkItemList()
{
    list newInventoryItems;
    list newinventoryDetails;

    integer i;
    do
    {
        string item = llGetInventoryName(INVENTORY_ALL,i);
        if(llListFindList(ignore, item) == -1)
        {
            //debug(llGetInventoryKey(item) + " " + item);
            newInventoryItems += item;
            newinventoryDetails += [ item, llGetInventoryKey(item) ];
        }
    }
    while(++i<llGetInventoryNumber(INVENTORY_ALL));
    if(llDumpList2String(newinventoryDetails, "|") != llDumpList2String(inventoryDetails, "|")) {
        inventoryItems=newInventoryItems;
        inventoryDetails=newinventoryDetails;
        saveItemsList();
        if(llGetListLength(subscribers) > 0)
        {
            debug("pushing updates in 10 seconds");
            llSetTimerEvent(10);
        }
        else debug("nobody likes " + vendorName);
    }
}
saveItemsList()
{
    if(inventoryVersion == "") inventoryVersion = "0.0.1";
    else inventoryVersion = incrementVersion(inventoryVersion, 0);
    inventoryKey = llGenerateKey();
    debug("Inventory has changed, saving new version " + inventoryVersion + " " + inventoryKey);
    saveList([inventoryVersion,inventoryKey] + inventoryDetails, INV_STRIDE, "~inventory");
    if(llGetListLength(inventoryItems) > 0)
    {
        state default;
    }
    else
    {
        state empty;
        debug("already in empty state");
    }

}

string incrementVersion(string semVersion, integer level)
{
    list semParts=llParseString2List(semVersion, ["-"], " ");
    string version = llList2String(semParts, 0);
    integer build = llList2Integer(semParts, 1);
    if(level == 0)
    {
            debug("new version " + version + "-" + (string)(build + 1));
            return version + "-" + (string)(build + 1);
    }
    list parts=llParseString2List(version, ["."], " ");
    integer levels = llGetListLength(parts);
    if(levels < level)
    do
    {
        parts = ["0"] + parts;
        levels = llGetListLength(parts);
    }
    while (levels <3);
    parts = llListReplaceList(parts, llList2Integer(parts, levels - level) + 1, levels - level, levels - level);
    if(level > 1)
    {
        integer l = levels - 1;
        do
        {
            parts = llListReplaceList(parts, 0,l,l);
            l--;
        }
        while (levels - l < level);
    }
    return llDumpList2String(parts,".");
}
giveContent(list recipients)
{
    debug("distribute content to " + llGetListLength(recipients) + " avies");
    integer i=0;
    do
    {
        key recipientKey=llList2Key(recipients, i);
        giveContent(recipientKey);
        i++;
    }
    while (i < llGetListLength(recipients));
}
giveContent(key recipientKey)
{
    string online = osGetAgentIP(recipientKey);
    string name = llList2String(subscribers, llListFindList(subscribersKeys, recipientKey) * SUBS_STRIDE + SUBS_NAME);
    debug("give content to " + name + " (" + online + ")");
    //if(online) debug("give content to " + recipientKey);
    //else
    llGiveInventoryList(recipientKey,llGetObjectName() + " " + inventoryVersion,inventoryItems);
    llInstantMessage(recipientKey, "Your product " + llGetObjectName() + " has been updated. You received version " + inventoryVersion + " in your inventory.");
}
integer getSubscription(key avatar)
{
    if(llListFindList(subscribersKeys, avatar) == -1) return FALSE;
    else return TRUE;
}
subscribe(key avatar, string name)
{
    if(getSubscription(avatar)) return;
    subscribers += [ avatar, name,  llGetUnixTime() ];
    subscribersKeys = llList2ListStrided(subscribers, 0, -1, SUBS_STRIDE);
    saveSubscribersList();
    llInstantMessage(avatar, "Thank you, " + firstName(name) + ". You are now subscribed to " + llGetObjectName() + " updates. When a new version is released, you will receive it in your inventory. To unsubscribe, click again on this vendor.");
}
unsubscribe(key avatar, string name)
{
    if(!getSubscription(avatar)) return;
    integer idx = llListFindList(subscribersKeys, avatar) *  SUBS_STRIDE;
    debug("removing avatar indx " + idx);
    subscribers = llListReplaceList(subscribers, [], idx, idx + SUBS_STRIDE - 1);
    subscribersKeys = llList2ListStrided(subscribers, 0, -1, SUBS_STRIDE);
    saveSubscribersList();
    llInstantMessage(avatar, "You are now unsubscribed from " + llGetObjectName() + " updates, " + firstName(name) + ". You will not receive the automatic updates anymore.");
}

default
{
    state_entry()
    {
        debug("entering default state ");
        // + llGetAgentLanguage(llGetOwner()));
        chat_channel = 0x80000000 | ((integer)("0x"+(string)llGetKey()));
        getConfig();
        ignore += [llGetScriptName(), configFile];
        loadItemsList();
        loadSubscribersList();
        llListen(chat_channel, "", "", "");
        if(llGetLinkName(LINK_THIS) == llGetScriptName()
        || llGetLinkName(LINK_THIS) == " ") {
            vendorName = llList2String(inventoryItems,0);
            debug("reset vendor name to " + vendorName);
        }
        else
        {
            vendorName = strReplace(llGetLinkName(LINK_THIS), vendorNameSuffix, "");
            debug("use prim to set vendor name " + vendorName);
        }
        llSetLinkPrimitiveParamsFast(LINK_THIS, [PRIM_NAME, vendorName + vendorNameSuffix, PRIM_GLOW, activeGlow]);
        fontColor = activeColor;
        backgroundColor = activeBackground;
        drawText(vendorName);
        llWhisper(0, "/me ready");
    }
    touch(integer index)
    {
        list buttons = buttonsDefault;
        key avatar = llDetectedKey(0);
        integer subscribed = getSubscription(avatar);
        if(subscribed) buttons+=["Unsubscr."];
        else buttons+=["Subscribe"];
        llDialog(avatar, "Update vendor", buttons, chat_channel);
    }
    listen(integer channel, string name, key id, string message)
    {
        if(channel == chat_channel)
        {
            if(message == "Subscribe") subscribe(id, name);
            else if(message == "Unsubscr.") unsubscribe(id, name);
            else if(message == "Get items") giveContent(id);
        }
    }
    changed(integer change)
    {
        if(change & CHANGED_INVENTORY)
        {
            checkItemList();
        }
    }
    timer()
    {
        debug("push updates now");
        llSetTimerEvent(0);
        giveContent(subscribersKeys);
    }
    dataserver(key query_id, string data)
    {        if((integer)data == TRUE)
        debug(query_id + " Online");
        else
        debug(query_id + "Offline");
    }
}

state empty
{
    state_entry()
    {
        debug("entering empty state");
        vendorName = llGetScriptName();
        llSetLinkPrimitiveParamsFast(LINK_THIS, [PRIM_NAME, vendorName, PRIM_GLOW, inactiveGlow]);
        fontColor = inactiveColor;
        backgroundColor = inactiveBackground;
        glow = inactiveGlow;
        drawText("EMPTY");
    }
    changed(integer change)
    {
        if(change & CHANGED_INVENTORY)
        {
            checkItemList();
        }
    }
}
