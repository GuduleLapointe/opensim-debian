// Region initialization script of the OpenSim kit.
//
// An object holding this script runs it once in a new region, as the owner of the estate. It reads the name of
// the region and of its parcel itself, nothing has to be written for a given region. Customize it freely:
// it is the place for whatever a new region should have from the start (parcel name, music, media, flags).
// It uses OSSL (osSetParcelDetails), enabled in the standard config of the kit simulators ([OSSL] section).

// Named by OpenSim when the region has no land data yet
string DEFAULT_PARCEL_NAME = "Your Parcel";

init()
{
    // The land of a new region is one parcel the size of the region: any point of it will do
    vector center = <llGetRegionSizeX() / 2.0, llGetRegionSizeY() / 2.0, 0.0>;

    list current = llGetParcelDetails(center, [PARCEL_DETAILS_NAME]);
    if (llList2String(current, 0) == DEFAULT_PARCEL_NAME)
    {
        osSetParcelDetails(center, [PARCEL_DETAILS_NAME, llGetRegionName()]);
    }

    // Add your own region setup here
}

default
{
    state_entry()
    {
        init();
        llOwnerSay("Region initialized");
        // The object has done its job: the copy rezzed in the region goes, the original stays in the library
        llDie();
    }
}
