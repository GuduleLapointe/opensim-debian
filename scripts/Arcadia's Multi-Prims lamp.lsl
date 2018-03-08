// Arcadia's Multi-Prims lamp
// Version 1.2

// (c) Arcadia Aardvark
// Slight additions (c) Gudule Lapointe 2018

// Gudule Lapointe's additions:
//
//  - Handle multiple lamps in the same object (liked prims whose name is "lamp");
//  - Set the lamp prims color to current light color;
//  - Keep light color, intensity, radius and falloff after reset;
//  - More color choices (basic rainbow colors);
//  - Colors values in a list instead of hardcoded;
//  - Save automatic status via object description.

// This script is licensed under Creative Commons BY-NC-SA
// See <http://creativecommons.org/licenses/by-nc-sa/3.0/>
// You may not use this work for commercial purposes.
// You must attribute the work to the authors,
//    Arcadia Aardvark and Gudule Lapointe.
// You may distribute, alter, transform, or build upon this work
// as long as you do not delete the name of the original authors.

integer restrictToOwner = TRUE;
integer side = ALL_SIDES;

integer fono;
list Main = ["Colour", "Intensity", "EXIT", "ON", "OFF", "Auto ON"];
list Colour = [
"Aqua", <0.498, 0.859, 1.000>,
"Blue", <0.000, 0.455, 0.851>,
"Purple", <0.694, 0.051, 0.788>,

"Orange", <1.000, 0.522, 0.106>,
"Yellow", <1.000, 0.863, 0.000>,
"Green", <0.180, 0.800, 0.251>,

"Arcadia", <0.996, 0.890, 0.706>,
"White", <1.000, 1.000, 1.000>,
"Red", <1.000, 0.255, 0.212>
];
//list ColourSpec = ["<1,1,1>", "",  "",
//    "<0.996, 0.890, 0.706>", "<1.000, 0.846, 0.016>", "<0.586, 1.000, 0.990>"];
list Intensity = ["_", "_", "^", "Hi", "Med", "Low"];
list Auto = ["Auto Off", "_", "EXIT"];
integer opciones;
integer escuchador;
vector colour;
float intensity;
float radius;
float falloff;
float glow;
integer automatic;
integer random;
vector sun;
list lamps;

vector lookForLamps() {
    vector links;
    integer count = llGetObjectPrimCount(llGetKey());

    integer l;
    lamps=[];
    while (l <= count) {
        if(llGetLinkName(l) == "lamp")
        {
            lamps += l;
        }
        l++;
    }
    return links;
}

light_on (vector col, float int, float rad, float fall)
{
    integer length = llGetListLength(lamps);
    integer colorsCount = (integer)(llGetListLength(Colour) / 2);
    integer l;
    do
    {
        integer lamp = llList2Integer(lamps, l);
        vector trueColor = col;
        if(random == TRUE) {
            trueColor = llList2Vector(Colour, (integer)llFrand(colorsCount) * 2 + 1);
        }
        light_on_link (lamp, trueColor, int, rad, fall);
    } while(++l < length);
}
light_on_link (integer link, vector col, float int, float rad, float fall)
{
    glow = intensity * 0.4;
    llSetLinkPrimitiveParams
    (link, [
    PRIM_COLOR, side, col, 1.0,
    PRIM_GLOW, side, glow,
    PRIM_FULLBRIGHT, side, TRUE,
    PRIM_POINT_LIGHT, TRUE, col, int, rad, fall
    ]);
}

light_off ()
{
    integer l;
    integer length = llGetListLength(lamps);
    do {
        integer lamp = llList2Integer(lamps, l);
        light_off_link (llList2Integer(lamps, l));
    }
    while(++l < length);
}
light_off_link (integer link)
{
    llSetLinkPrimitiveParams
    (link, [
    PRIM_GLOW, side, 0.0,
    PRIM_FULLBRIGHT, side, FALSE,
    PRIM_POINT_LIGHT, FALSE, <0.0, 0.0, 0.0>, 0.0, 0.0, 0.0
    ]);
}

light_auto() {
    sun = llGetSunDirection();
    if (sun.z < 0)
    {
        light_on(colour, intensity, radius, falloff);
    }
    else
    {
        light_off();
    }
}

colorDialog(key id)
{
    llDialog(id,
    "Select the light tint:",
    ["Loop", "Random", "^"]
    + llList2ListStrided(Colour, 0, -1, 2),
    fono);
}

default
{
    on_rez(integer x)
    {
        llResetScript();
    }
    state_entry()
    {
        lookForLamps();
        fono = (integer)("0x" + llGetSubString((string)llGetKey(),-1,-8));
        list savedParams = llGetLinkPrimitiveParams(llList2Integer(lamps, 0), [PRIM_POINT_LIGHT]);
        //colour = llList2Vector(llGetLinkPrimitiveParams(llList2Integer(lamps, 0), [PRIM_COLOR, side]), 0);
        colour = llList2Vector(savedParams, 1);
        intensity = llList2Float(savedParams, 2);
        radius = llList2Float(savedParams, 3);
        falloff = llList2Float(savedParams, 4);
        glow = intensity * 0.4;
        if (llGetObjectDesc() == "automatic") {
            automatic = TRUE;
            light_auto();
        } else {
            automatic = FALSE;
        }
    }
    touch_start(integer total_number)
    {
        if(!restrictToOwner || llDetectedKey(0) == llGetOwner())
        {
            escuchador = llListen(fono, "", NULL_KEY, "");

            if (automatic == TRUE)
            {
                llDialog(llDetectedKey(0), "Automatic Mode ON", Auto, fono);
                opciones = 3;
            }
            else if (automatic == FALSE)
            {
                llDialog(llDetectedKey(0), "Colour = Light Tint\n Intensity = Light Brightness", Main, fono);
                llSetTimerEvent(30);
                opciones = 0;
            }
        }
    }
    timer()
    {
        if (automatic == FALSE)
        {
            llListenRemove(escuchador);
            llSetTimerEvent(0);
        }
        else if (automatic == TRUE)
        {
            llListenRemove(escuchador);
            light_auto();
        }
    }
    listen (integer channel, string name, key id, string xxx)
    {
        if (opciones == 0)
        {

            if (xxx == "Colour")
            {
                colorDialog(id);
                llSetTimerEvent(30);
                opciones = 1;
            }
            if (xxx == "Intensity")
            {
                llDialog(id, "Select the light intensity:", Intensity, fono);
                llSetTimerEvent(30);
                opciones = 2;
            }
            if (xxx == "ON")
            {
                light_on(colour, intensity, radius, falloff);
                llDialog(id, "Colour = Light Tint\n Intensity = Light Brightness", Main, fono);
                llSetTimerEvent(30);
                opciones = 0;
            }
            if (xxx == "OFF")
            {
                light_off();
                llDialog(id, "Colour = Light Tint\n Intensity = Light Brightness", Main, fono);
                llSetTimerEvent(30);
                opciones = 0;
            }
            if (xxx == "Auto ON")
            {
                automatic = TRUE;
                light_auto();
                if(llGetObjectDesc() == "") {
                    llSetObjectDesc("automatic");
                } else {
                    if(id == llGetOwner())
                    llInstantMessage(id, "The object description is not empty. Empty it if you want to save the automatic status");
                }
                opciones = 3;
                llDialog(id, "Automatic Mode ON", Auto, fono);
                llSetTimerEvent(60);
            }
            if (xxx == "EXIT")
            {
                llListenRemove(escuchador);
            }
        }
        else if (opciones == 1)
        {
            if (xxx == "Random")
            {
                random = TRUE;
                light_on(colour, intensity, radius, falloff);
                colorDialog(id);
            }
            else if (xxx == "Loop")
            {
                random = FALSE;
                llInstantMessage(id, xxx + " not yet implemented");
                colorDialog(id);
            }
            else if (xxx == " ")
            {
                colorDialog(id);
            }
            else if (xxx == "^")
            {
                opciones = 0;
                llDialog(id, "Colour = Light Tint\n Intensity = Light Brightness", Main, fono);
                llSetTimerEvent(30);
            }
            else
            {
                random = FALSE;
                integer colIdx = llListFindList(Colour, [xxx]);
                if(colIdx != -1)
                {
                    colour = llList2Vector(Colour, colIdx + 1);
                    light_on(colour, intensity, radius, falloff);
                }
                colorDialog(id);
                llSetTimerEvent(30);
            }
        }
        else if (opciones == 2)
        {
            if (xxx == "Hi")
            {
                intensity = 1.0;
                radius = 8.0;
                falloff = 0.0;
                light_on(colour, intensity, radius, falloff);
                opciones = 2;
                llDialog(id, "Select the light intensity:", Intensity, fono);
                llSetTimerEvent(30);
            }
            if (xxx == "Med")
            {
                intensity = 1.0;
                radius = 3.0;
                falloff = 1.0;
                light_on(colour, intensity, radius, falloff);
                opciones = 2;
                llDialog(id, "Select the light intensity:", Intensity, fono);
                llSetTimerEvent(30);
            }
            if (xxx == "Low")
            {
                intensity = 0.5;
                radius = 1.0;
                falloff = 1.0;
                light_on(colour, intensity, radius, falloff);
                opciones = 2;
                llDialog(id, "Select the light intensity:", Intensity, fono);
                llSetTimerEvent(30);
            }
            if (xxx == " ")
            {
                opciones = 2;
                llDialog(id, "Select the light intensity:", Intensity, fono);
                llSetTimerEvent(30);
            }
            if (xxx == "^")
            {
                opciones = 0;
                llDialog(id, "Colour = Light Tint\n Intensity = Light Brightness", Main, fono);
                llSetTimerEvent(30);
            }
        }
        else if (opciones == 3)
        {
            if (xxx == "Auto Off")
            {
                automatic = FALSE;
                if(llGetObjectDesc() == "automatic") {
                    llSetObjectDesc("");
                }
                opciones = 0;
                llDialog(id, "Colour = Light Tint\n Intensity = Light Brightness", Main, fono);
                llSetTimerEvent (30);
            }
            if (xxx == " ")
            {
                opciones = 3;
                llDialog(id, "Automatic Mode ON", Auto, fono);
            }
            if (xxx == "EXIT")
            {
                opciones = 3;
                llListenRemove(escuchador);
            }
        }
    }
}
