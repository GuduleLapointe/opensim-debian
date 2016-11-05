// Archive Box
// Version: 1.0
// @ 2016 Gudule Lapointe
// Portions of code (unpack on wear) taken from another script from unknown author

// This script is licensed under Creative Commons BY-NC-SA
// See <http://creativecommons.org/licenses/by-nc-sa/3.0/>
// You may not use this work for commercial purposes.
// You must attribute the work to the author, Gudule Lapointe
// You may distribute, alter, transform, or build upon this work
// as long as you do not delete the name of the original authors.

string suffix = " (boxed)";

integer publicUse = FALSE;

string fontName = "Impact";
integer fontSize = 48;
integer lineHeight = 48;
string textColor = "black";
string backgroundColor = "white";

integer textureWidth = 512;
integer textureHeight = 512;
integer margin = 24;
string position = "center";
key otherTexture = TEXTURE_BLANK;

debug(string message)
{
    return; // comment this line to enable debugging;
    llOwnerSay("/me DEBUG: " + message);
}
checkInventory()
{
    string scriptName = llGetScriptName();
    string currentName = llGetLinkName(LINK_THIS);
    string newName;
    string textureName;

    integer     num = llGetInventoryNumber(INVENTORY_ALL);
    if(num == 1 && currentName != scriptName)
    {
        debug("empty box, setting name to script: " + scriptName);
        newName = scriptName;
        llSetLinkPrimitiveParams(LINK_THIS, [ PRIM_NAME, newName]);
        drawTexture(textureName);
    } else if ( num > 1 && (
        currentName == scriptName
         ||  currentName == "Object"
         ||  currentName == "Primitive"
         ||  currentName == " "
         ))
    {
        newName = llGetInventoryName(INVENTORY_ALL, 0);
        if (newName == scriptName)
            newName = llGetInventoryName(INVENTORY_ALL, 1);
        debug("found content, setting name to first item seen " + newName);
        llSetLinkPrimitiveParams(LINK_THIS, [ PRIM_NAME, newName + suffix]);
    } else {
        debug("name already set, no change needed");
        return;
    }
    drawTexture(newName);
}

drawTexture(string text)
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
        fontSize = (integer)(fontSize * drawWidth / extents.x);
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
        otherTexture = TEXTURE_TRANSPARENT;
    }
    llSetTexture(otherTexture, ALL_SIDES);

    osSetDynamicTextureData("", "vector", commandList, "width:"+(string)textureWidth+",height:"+(string)textureHeight+",alpha:"+alpha, 0);
//    llSetTexture(otherTexture, 0);
//    llSetTexture(otherTexture, 5);
}

Unpack()
{
    list        inventory;
    string      name;
    integer     num = llGetInventoryNumber(INVENTORY_ALL);
    string      text ="Unpacking...";
    integer     i;
    key         user = llGetOwner();//Set to llDetectedKey(0); to allow anyone to use
    if(publicUse) user = llDetectedKey(0);

    for (i = 0; i < num; ++i) {
        name = llGetInventoryName(INVENTORY_ALL, i);
        if(llGetInventoryPermMask(name, MASK_OWNER) & PERM_COPY)
            inventory += name;
        else
            llInstantMessage(user, "Cannot give asset \""+name+"\", owner lacks copy permission");
        llSetText(text + (string)((integer)(((i + 1.0) / num) * 100))+ "%", <1, 1, 1>, 1.0);
    }

    //chew off the end off the text message.
    text = llGetSubString(llGetObjectName(), 0, -llStringLength(" (wear to unpack)")-1);

    //we don't want to give them this script
    i = llListFindList(inventory, [llGetScriptName()]);
    if(~i)//if this script isn't found then we shouldn't try and remove it
        inventory = llDeleteSubList(inventory, i, i);

    // don't give the pose
    i = llListFindList(inventory, ["{ix} Bag Holding Pose"]);
    if (~i)
        inventory = llDeleteSubList(inventory, i, i);

    if (llGetListLength(inventory) < 1)
        llSay(0, "No items to offer.");
    else {
        llGiveInventoryList(user, text, inventory);
        llSetText("",<1,1,1>,1);

        name = "Your new "+ text +" can be found in your inventory, in a folder called '"+ text +"'. Drag it onto your avatar to wear it!";
        if(user == llGetOwner())
            llOwnerSay(name);
        else
            llInstantMessage(user, name);
    }
}
init()
{
    llSetText("", <1,1,1>, 1);
    checkInventory();
}
default
{
    state_entry()
    {
        init();
    }
    on_rez(integer start_param)
    {
        init();
    }
    changed(integer change) {
        if (change & CHANGED_INVENTORY) init();
    }


    attach (key id)
    {
        if (id!=NULL_KEY)
        {
            llRequestPermissions(id,PERMISSION_TRIGGER_ANIMATION);
            Unpack();
        } else {
            llSetText("", <1,1,1>, 1);
        }
    }

    run_time_permissions (integer perm)
    {
        if (perm & PERMISSION_TRIGGER_ANIMATION) {
            string animation=llGetInventoryName(INVENTORY_ANIMATION,0);
            llStartAnimation(animation);
        }
    }

    touch_end(integer num_detected)
    {
        if (llDetectedKey(0)!=llGetOwner()) return;

        Unpack();
    }
}
