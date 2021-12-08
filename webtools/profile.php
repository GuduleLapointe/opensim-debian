<?php

include("databaseinfo.php");

//
// Search DB
//
mysql_connect ($DB_HOST, $DB_USER, $DB_PASSWORD);
mysql_select_db ($DB_NAME);

#
#  Copyright (c)Melanie Thielker (http://opensimulator.org/)
#

###################### No user serviceable parts below #####################

$zeroUUID = "00000000-0000-0000-0000-000000000000";

#
# The XMLRPC server object
#

$xmlrpc_server = xmlrpc_server_create();

#
# Classifieds
#

# Avatar Classifieds Request

xmlrpc_server_register_method($xmlrpc_server, "avatarclassifiedsrequest",
        "avatarclassifiedsrequest");

function avatarclassifiedsrequest($method_name, $params, $app_data)
{
    $req            = $params[0];

    $uuid           = $req['uuid'];


    $result = mysql_query("SELECT * FROM classifieds WHERE ".
            "creatoruuid = '". mysql_real_escape_string($uuid) ."'");

    $data = array();

    while (($row = mysql_fetch_assoc($result)))
    {
        $data[] = array(
                "classifiedid" => $row["classifieduuid"],
                "name" => $row["name"]);
    }

    $response_xml = xmlrpc_encode(array(
        'success' => True,
        'data' => $data
    ));

    print $response_xml;
}

# Classifieds Update

xmlrpc_server_register_method($xmlrpc_server, "classified_update",
        "classified_update");

function classified_update($method_name, $params, $app_data)
{
    global $zeroUUID;

    $req            = $params[0];

    $classifieduuid = $req['classifiedUUID'];
    $creator        = $req['creatorUUID'];
    $category       = $req['category'];
    $name           = $req['name'];
    $description    = $req['description'];
    $parceluuid     = $req['parcelUUID'];
    $parentestate   = $req['parentestate'];
    $snapshotuuid   = $req['snapshotUUID'];
    $simname        = $req['sim_name'];
    $parcelname     = $req['parcelname'];
    $globalpos      = $req['globalpos'];
    $classifiedflag = $req['classifiedFlags'];
    $priceforlist   = $req['classifiedPrice'];

    // Check if we already have this one in the database
    $check = mysql_query("SELECT COUNT(*) FROM classifieds WHERE ".
            "classifieduuid = '". mysql_real_escape_string($classifieduuid) ."'");

    while ($row = mysql_fetch_row($check))
    {
        $ready = $row[0];
    }

    // Doing some late checking
    // Should be done by the module but let's see what happens when
    // I do it here

    if ($parcelname == "")
        $parcelname = "Unknown";

    if ($parceluuid == "")
        $parceluuid = $zeroUUID;

    if ($description == "")
        $description = "No Description";

    //If PG, Mature, and Adult flags are all 0 assume PG and set bit 2.
    //This works around what might be a viewer bug regarding the flags.
    //The ossearch query.php file expects bit 2 set for any PG listing.
    if (($classifiedflag & 76) == 0)
        $classifiedflag |= 4;

    if ($ready == 0)
    {
        //Renew Weekly flag is 32 (1 << 5)
        if (($classifiedflag & 32) == 0)
        {
            $creationdate = time();
            $expirationdate = time() + (7 * 24 * 60 * 60);
        }
        else
        {
            $creationdate = time();
            $expirationdate = time() + (52 * 7 * 24 * 60 * 60);
        }

        $sql = "INSERT INTO classifieds VALUES ".
            "('". mysql_real_escape_string($classifieduuid) ."',".
            "'". mysql_real_escape_string($creator) ."',".
            "". mysql_real_escape_string($creationdate) .",".
            "". mysql_real_escape_string($expirationdate) .",".
            "'". mysql_real_escape_string($category) ."',".
            "'". mysql_real_escape_string($name) ."',".
            "'". mysql_real_escape_string($description) ."',".
            "'". mysql_real_escape_string($parceluuid) ."',".
            "". mysql_real_escape_string($parentestate) .",".
            "'". mysql_real_escape_string($snapshotuuid) ."',".
            "'". mysql_real_escape_string($simname) ."',".
            "'". mysql_real_escape_string($globalpos) ."',".
            "'". $parcelname ."',".
            "". mysql_real_escape_string($classifiedflag) .",".
            "". mysql_real_escape_string($priceforlist) .")";
    }
    else
    {
        $expirationdate = $creationdate + (52 * 7 * 24 * 60 * 60);

        $sql = "UPDATE classifieds SET ".
            "`creatoruuid`='". mysql_real_escape_string($creator)."',".
            "`expirationdate`=". mysql_real_escape_string($expirationdate).",".
            "`category`='". mysql_real_escape_string($category)."',".
            "`name`='". mysql_real_escape_string($name)."',".
            "`description`='". mysql_real_escape_string($description)."',".
            "`parceluuid`='". mysql_real_escape_string($parceluuid)."',".
            "`parentestate`=". mysql_real_escape_string($parentestate).",".
            "`snapshotuuid`='". mysql_real_escape_string($snapshotuuid)."',".
            "`simname`='". mysql_real_escape_string($simname)."',".
            "`posglobal`='". mysql_real_escape_string($globalpos)."',".
            "`parcelname`='". $parcelname."',".
            "`classifiedflags`=". mysql_real_escape_string($classifiedflag).",".
            "`priceforlisting`=". mysql_real_escape_string($priceforlist).
            " WHERE ".
            "`classifieduuid`='". mysql_real_escape_string($classifieduuid)."'";
    }

    // Create a new record for this classified
    $result = mysql_query($sql);

    $response_xml = xmlrpc_encode(array(
        'success' => $result,
        'errorMessage' => mysql_error()
    ));

    print $response_xml;
}

# Classifieds Delete

xmlrpc_server_register_method($xmlrpc_server, "classified_delete",
        "classified_delete");

function classified_delete($method_name, $params, $app_data)
{
    $req            = $params[0];

    $classifieduuid = $req['classifiedID'];

    $result = mysql_query("DELETE FROM classifieds WHERE ".
            "classifieduuid = '".mysql_real_escape_string($classifieduuid) ."'");

    $response_xml = xmlrpc_encode(array(
        'success' => True,
        'data' => $data
    ));

    print $response_xml;
}

#
# Picks
#

# Avatar Picks Request

xmlrpc_server_register_method($xmlrpc_server, "avatarpicksrequest",
        "avatarpicksrequest");

function avatarpicksrequest($method_name, $params, $app_data)
{
    $req            = $params[0];

    $uuid           = $req['uuid'];

    $data = array();

    $result = mysql_query("SELECT `pickuuid`,`name` FROM userpicks WHERE ".
            "creatoruuid = '". mysql_real_escape_string($uuid) ."'");

    while (($row = mysql_fetch_assoc($result)))
    {
        $data[] = array(
                "pickid" => $row["pickuuid"],
                "name" => $row["name"]);
    }

    $response_xml = xmlrpc_encode(array(
        'success' => True,
        'data' => $data
    ));

    print $response_xml;
}

# Request Picks for User

xmlrpc_server_register_method($xmlrpc_server, "pickinforequest",
        "pickinforequest");

function pickinforequest($method_name, $params, $app_data)
{
    $req            = $params[0];

    $uuid           = $req['avatar_id'];
    $pick           = $req['pick_id'];

    $data = array();

    $result = mysql_query("SELECT * FROM userpicks WHERE ".
            "creatoruuid = '". mysql_real_escape_string($uuid) ."' AND ".
            "pickuuid = '". mysql_real_escape_string($pick) ."'");

    $row = mysql_fetch_assoc($result);
    if ($row != False)
    {
        if ($row["description"] == null || $row["description"] == "")
            $row["description"] = "No description given";

        $data[] = array(
                "pickuuid" => $row["pickuuid"],
                "creatoruuid" => $row["creatoruuid"],
                "toppick" => $row["toppick"],
                "parceluuid" => $row["parceluuid"],
                "name" => $row["name"],
                "description" => $row["description"],
                "snapshotuuid" => $row["snapshotuuid"],
                "user" => $row["user"],
                "originalname" => $row["originalname"],
                "simname" => $row["simname"],
                "posglobal" => $row["posglobal"],
                "sortorder"=> $row["sortorder"],
                "enabled" => $row["enabled"]);
    }

    $response_xml = xmlrpc_encode(array(
        'success' => True,
        'data' => $data
    ));

    print $response_xml;
}

# Picks Update

xmlrpc_server_register_method($xmlrpc_server, "picks_update",
        "picks_update");

function picks_update($method_name, $params, $app_data)
{
    global $zeroUUID;

    $req            = $params[0];

    $pickuuid       = $req['pick_id'];
    $creator        = $req['creator_id'];
    $toppick        = $req['top_pick'];
    $name           = $req['name'];
    $description    = $req['desc'];
    $parceluuid     = $req['parcel_uuid'];
    $snapshotuuid   = $req['snapshot_id'];
    $user           = $req['user'];
    $simname        = $req['sim_name'];
    $posglobal      = $req['pos_global'];
    $sortorder      = $req['sort_order'];
    $enabled        = $req['enabled'];

    if ($parceluuid == "")
        $parceluuid = $zeroUUID;

    if ($description == "")
        $description = "No Description";

    // Check if we already have this one in the database
    $check = mysql_query("SELECT COUNT(*) FROM userpicks WHERE ".
            "pickuuid = '". mysql_real_escape_string($pickuuid) ."'");

    $row = mysql_fetch_row($check);

    if ($row[0] == 0)
    {
        if ($user == null || $user == "")
            $user = "Unknown";

        //The original parcel name is the same as the name of the
        //profile pick when a new profile pick is being created.
        $original = $name;

        $query = "INSERT INTO userpicks VALUES ".
            "('". mysql_real_escape_string($pickuuid) ."',".
            "'". mysql_real_escape_string($creator) ."',".
            "'". mysql_real_escape_string($toppick) ."',".
            "'". mysql_real_escape_string($parceluuid) ."',".
            "'". mysql_real_escape_string($name) ."',".
            "'". mysql_real_escape_string($description) ."',".
            "'". mysql_real_escape_string($snapshotuuid) ."',".
            "'". mysql_real_escape_string($user) ."',".
            "'". mysql_real_escape_string($original) ."',".
            "'". mysql_real_escape_string($simname) ."',".
            "'". mysql_real_escape_string($posglobal) ."',".
            "'". mysql_real_escape_string($sortorder) ."',".
            "'". mysql_real_escape_string($enabled) ."')";
    }
    else
    {
        $query = "UPDATE userpicks SET " .
            "parceluuid = '". mysql_real_escape_string($parceluuid) . "', " .
            "name = '". mysql_real_escape_string($name) . "', " .
            "description = '". mysql_real_escape_string($description) . "', " .
            "snapshotuuid = '". mysql_real_escape_string($snapshotuuid) . "' WHERE ".
            "pickuuid = '". mysql_real_escape_string($pickuuid) ."'";
    }

    $result = mysql_query($query);
    if ($result != False)
        $result = True;

    $response_xml = xmlrpc_encode(array(
        'success' => $result,
        'errorMessage' => mysql_error()
    ));

    print $response_xml;
}

# Picks Delete

xmlrpc_server_register_method($xmlrpc_server, "picks_delete",
        "picks_delete");

function picks_delete($method_name, $params, $app_data)
{
    $req            = $params[0];

    $pickuuid       = $req['pick_id'];

    $result = mysql_query("DELETE FROM userpicks WHERE ".
            "pickuuid = '".mysql_real_escape_string($pickuuid) ."'");

    if ($result != False)
        $result = True;

    $response_xml = xmlrpc_encode(array(
        'success' => $result,
        'errorMessage' => mysql_error()
    ));

    print $response_xml;
}

#
# Notes
#

# Avatar Notes Request


xmlrpc_server_register_method($xmlrpc_server, "avatarnotesrequest",
        "avatarnotesrequest");

function avatarnotesrequest($method_name, $params, $app_data)
{
    $req            = $params[0];

    $uuid           = $req['avatar_id'];
    $targetuuid     = $req['uuid'];

    $result = mysql_query("SELECT notes FROM usernotes WHERE ".
            "useruuid = '". mysql_real_escape_string($uuid) ."' AND ".
            "targetuuid = '". mysql_real_escape_string($targetuuid) ."'");

    $row = mysql_fetch_row($result);
    if ($row == False)
        $notes = "";
    else
        $notes = $row[0];

    $data[] = array(
            "targetid" => $targetuuid,
            "notes" => $notes);

    $response_xml = xmlrpc_encode(array(
        'success' => True,
        'data' => $data
    ));

    print $response_xml;
}

# Avatar Notes Update

xmlrpc_server_register_method($xmlrpc_server, "avatar_notes_update",
        "avatar_notes_update");

function avatar_notes_update($method_name, $params, $app_data)
{
    $req            = $params[0];

    $uuid           = $req['avatar_id'];
    $targetuuid     = $req['target_id'];
    $notes          = $req['notes'];

    // Check if we already have this one in the database

    $check = mysql_query("SELECT COUNT(*) FROM usernotes WHERE ".
            "useruuid = '". mysql_real_escape_string($uuid) ."' AND ".
            "targetuuid = '". mysql_real_escape_string($targetuuid) ."'");

    $row = mysql_fetch_row($check);

    if ($row[0] == 0)
    {
        // Create a new record for this avatar note
        $result = mysql_query("INSERT INTO usernotes VALUES ".
            "('". mysql_real_escape_string($uuid) ."',".
            "'". mysql_real_escape_string($targetuuid) ."',".
            "'". mysql_real_escape_string($notes) ."')");
    }
    else if ($notes == "")
    {
        // Delete the record for this avatar note
        $result = mysql_query("DELETE FROM usernotes WHERE ".
            "useruuid = '". mysql_real_escape_string($uuid) ."' AND ".
            "targetuuid = '". mysql_real_escape_string($targetuuid) ."'");
    }
    else
    {
        // Update the existing record
        $result = mysql_query("UPDATE usernotes SET ".
            "notes = '". mysql_real_escape_string($notes) ."' WHERE ".
            "useruuid = '". mysql_real_escape_string($uuid) ."' AND ".
            "targetuuid = '". mysql_real_escape_string($targetuuid) ."'");
    }

    $response_xml = xmlrpc_encode(array(
        'success' => True
    ));

    print $response_xml;
}

# Profile bits

xmlrpc_server_register_method($xmlrpc_server, "avatar_properties_request",
        "avatar_properties_request");

function avatar_properties_request($method_name, $params, $app_data)
{
    global $zeroUUID;

    $req            = $params[0];

    $uuid           = $req['avatar_id'];

    $result = mysql_query("SELECT * FROM userprofile WHERE ".
            "useruuid = '". mysql_real_escape_string($uuid) ."'");
    $row = mysql_fetch_assoc($result);

    if ($row != False)
    {
        $data[] = array(
                "ProfileUrl" => $row["profileURL"],
                "Image" => $row["profileImage"],
                "AboutText" => $row["profileAboutText"],
                "FirstLifeImage" => $row["profileFirstImage"],
                "FirstLifeAboutText" => $row["profileFirstText"],
                "Partner" => $row["profilePartner"],

                //Return interest data along with avatar properties
                "wantmask"   => $row["profileWantToMask"],
                "wanttext"   => $row["profileWantToText"],
                "skillsmask" => $row["profileSkillsMask"],
                "skillstext" => $row["profileSkillsText"],
                "languages"  => $row["profileLanguages"]);
    }
    else
    {
        //Insert empty record for avatar.
        //FIXME: Should this only be done when asking for ones own profile?
        $sql = "INSERT INTO userprofile VALUES ( ".
                "'". mysql_real_escape_string($uuid) ."', ".
                "'$zeroUUID', 0, 0, '', 0, '', 0, '', '', ".
                "'$zeroUUID', '', '$zeroUUID', '')";
        $result = mysql_query($sql);

        $data[] = array(
                "ProfileUrl" => "",
                "Image" => $zeroUUID,
                "AboutText" => "",
                "FirstLifeImage" => $zeroUUID,
                "FirstLifeAboutText" => "",
                "Partner" => $zeroUUID,

                "wantmask"   => 0,
                "wanttext"   => "",
                "skillsmask" => 0,
                "skillstext" => "",
                "languages"  => "");
    }

    $response_xml = xmlrpc_encode(array(
        'success' => True,
        'data' => $data
    ));

    print $response_xml;
}

xmlrpc_server_register_method($xmlrpc_server, "avatar_properties_update",
        "avatar_properties_update");

function avatar_properties_update($method_name, $params, $app_data)
{
    $req            = $params[0];

    $uuid           = $req['avatar_id'];
    $profileURL     = $req['ProfileUrl'];
    $image          = $req['Image'];
    $abouttext      = $req['AboutText'];
    $firstlifeimage = $req['FirstLifeImage'];
    $firstlifetext  = $req['FirstLifeAboutText'];

    $result=mysql_query("UPDATE userprofile SET ".
            "profileURL='". mysql_real_escape_string($profileURL) ."', ".
            "profileImage='". mysql_real_escape_string($image) ."', ".
            "profileAboutText='". mysql_real_escape_string($abouttext) ."', ".
            "profileFirstImage='". mysql_real_escape_string($firstlifeimage) ."', ".
            "profileFirstText='". mysql_real_escape_string($firstlifetext) ."' ".
            "WHERE useruuid='". mysql_real_escape_string($uuid) ."'"
        );

    $response_xml = xmlrpc_encode(array(
        'success' => $result,
        'errorMessage' => mysql_error()
    ));

    print $response_xml;
}


// Profile Interests

xmlrpc_server_register_method($xmlrpc_server, "avatar_interests_update",
        "avatar_interests_update");

function avatar_interests_update($method_name, $params, $app_data)
{
    $req            = $params[0];

    $uuid           = $req['avatar_id'];
    $wanttext       = $req['wanttext'];
    $wantmask       = $req['wantmask'];
    $skillstext     = $req['skillstext'];
    $skillsmask     = $req['skillsmask'];
    $languages      = $req['languages'];

    $result = mysql_query("UPDATE userprofile SET ".
            "profileWantToMask = ". mysql_real_escape_string($wantmask) .",".
            "profileWantToText = '". mysql_real_escape_string($wanttext) ."',".
            "profileSkillsMask = ". mysql_real_escape_string($skillsmask) .",".
            "profileSkillsText = '". mysql_real_escape_string($skillstext) ."',".
            "profileLanguages = '". mysql_real_escape_string($languages) ."' ".
            "WHERE useruuid = '". mysql_real_escape_string($uuid) ."'"
        );

    $response_xml = xmlrpc_encode(array(
        'success' => True
    ));

    print $response_xml;
}

// User Preferences

xmlrpc_server_register_method($xmlrpc_server, "user_preferences_request",
        "user_preferences_request");

function user_preferences_request($method_name, $params, $app_data)
{
    $req            = $params[0];

    $uuid           = $req['avatar_id'];

    $result = mysql_query("SELECT imviaemail,visible,email FROM usersettings WHERE ".
            "useruuid = '". mysql_real_escape_string($uuid) ."'");

    $row = mysql_fetch_assoc($result);

    if ($row != False)
    {
        $data[] = array(
                "imviaemail" => $row["imviaemail"],
                "visible" => $row["visible"],
                "email" => $row["email"]);
    }
    else
    {
        //Insert empty record for avatar.
        //NOTE: The 'false' values here are enums defined in database
        $sql = "INSERT INTO usersettings VALUES ".
                "('". mysql_real_escape_string($uuid) ."', ".
                "'false', 'false', '')";
        $result = mysql_query($sql);

        $data[] = array(
                "imviaemail" => False,
                "visible" => False,
                "email" => "");
    }

    $response_xml = xmlrpc_encode(array(
        'success' => True,
        'data' => $data
    ));

    print $response_xml;
}

xmlrpc_server_register_method($xmlrpc_server, "user_preferences_update",
        "user_preferences_update");

function user_preferences_update($method_name, $params, $app_data)
{

    $req            = $params[0];

    $uuid           = $req['avatar_id'];
    $wantim         = $req['imViaEmail'];
    $directory      = $req['visible'];

    $result = mysql_query("UPDATE usersettings SET ".
            "imviaemail = '".mysql_real_escape_string($wantim) ."', ".
            "visible = '".mysql_real_escape_string($directory) ."' WHERE ".
            "useruuid = '". mysql_real_escape_string($uuid) ."'");

    $response_xml = xmlrpc_encode(array(
        'success' => True,
        'data' => $data
    ));

    print $response_xml;
}

#
# Process the request
#

$request_xml = file_get_contents("php://input");

xmlrpc_server_call_method($xmlrpc_server, $request_xml, '');
xmlrpc_server_destroy($xmlrpc_server);
?>
