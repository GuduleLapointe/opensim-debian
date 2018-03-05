// Message of the Day
// Version: 1.1.0
// Copyright (c)2016  Gudule Lapointe. GNU Affero General Public License
// Please keep author and license info when copying.

// Read text from a notecard and write it on one or seeveral faces.
// Notecard is chosen randomly and changed regularly

integer updateDelay = 1800; // 1 hour
integer randomText = TRUE;
list messages;
integer messagesCount;
integer messageIndex;

// Begin text drawing library

string fontName = "Bookman"; // any font installed on simulator host
integer fontSize = 24;
integer lineHeight = 24;
integer margin = 24;
string fontColor = "DarkSlateGray"; // use .NET color names
string backgroundColor = "transparent"; // or "transparent"
string position = "left"; // Horizontal alignment. Can be "center". anything else is interpreted as "left" for now.
integer cropToFit = FALSE;
integer textureWidth = 512; // must be power of 2: 128, 256, 512...
integer textureHeight = 256; // must be power of 2: 128, 256, 512...
list textureSides = [ 1 ];
key backgroundTexture = "eb0f537d-886f-4f09-9e85-b8d36bc01f5a";
//key backgroundTexture;

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
    } else if (position == "center"){
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
    integer preProcessFace = llGetLinkNumberOfSides(LINK_THIS) - 1;
    integer blend = FALSE;
    if (backgroundTexture != NULL_KEY)
    {
        llSetTexture(backgroundTexture, -1);
//        llSetLinkTexture(LINK_THIS, backgroundTexture, preProcessFace);
        blend = TRUE;
    }
    //osSetDynamicTextureDataBlendFace("", "vector", commandList, "width:"+(string)textureWidth+",height:"+(string)textureHeight+",bgcolor:" + backgroundColor + ",alpha:"+alpha, TRUE, 2, 0, 255, preProcessFace);
    //key preProcessUUID = llList2Key(llGetLinkPrimitiveParams(LINK_THIS, [PRIM_TEXTURE, preProcessFace]),0);
    integer i = 0;
    do
    {
        integer face=llList2Integer(textureSides, i);
        //llSetLinkTexture(LINK_THIS, preProcessUUID, face);
        osSetDynamicTextureDataBlendFace("", "vector", commandList, "width:"+(string)textureWidth+",height:"+(string)textureHeight+",bgcolor:" + backgroundColor + ",alpha:"+alpha, TRUE, 2, 0, 255, face);
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

refreshText()
{
    string message;
    string notecard = llGetInventoryName(INVENTORY_NOTECARD,messageIndex);
    if(notecard != "")
    message = osGetNotecard(notecard);
    else if(llGetObjectDesc() != "")
    message = llGetObjectDesc();
    else
    message = "No message";
    message = strReplace(message, "\\n", "\n");
    if(randomText) messageIndex = (integer)llFrand(messagesCount);
    else messageIndex = (messageIndex + 1) % messagesCount;
    drawText(message);
}

default
{
    state_entry()
    {
        messages=[];
        integer n = 0;
        do
        {
            string message = osGetNotecard(llGetInventoryName(INVENTORY_NOTECARD, n));
            messages += [ message ];
            n++;
        }
        while (n < llGetInventoryNumber(INVENTORY_NOTECARD));
        messagesCount = llGetListLength(messages);
        if(randomText) messageIndex = (integer)llFrand(messagesCount);
        else messageIndex = 0;
        refreshText();
        if(messagesCount > 1)
        llSetTimerEvent(updateDelay);
    }
    changed(integer change)
    {
        if(change & CHANGED_INVENTORY) llResetScript();
    }
    timer()
    {
        refreshText();
    }
    touch(integer index)
    {
        refreshText();
    }
}
