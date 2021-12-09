<?php



/*

 * Copyright (c) 2007, 2008 Contributors, http://opensimulator.org/

 * See CONTRIBUTORS for a full list of copyright holders.

 *

 * See LICENSE for the full licensing terms of this file.

 *

*/



switch($_SESSION[page])

{

    case '':

        include("./sites/home.php");

        return;

    case 'home':

        include("./sites/home.php");

        return;

    case 'change':

        include("./sites/changeacc.php");

        return;

    case 'forgotpass':

        include("./sites/forgotpw.php");

        return;

    case 'pwreset':

        include("./sites/pwreset.php");

        return;

    case 'activate':

        include("./sites/activate.php");

        return;

    case 'gridstatus':

        include("./sites/news/gridnews.php");

        return;

    case 'gridstatushistory':

        include("./sites/news/newshistory.php");

        return;

    case 'smodul':

        include("./sites/sitemodul.php");

        return;

    case 'create':

        include("./sites/create.php");

        return;

    case 'transactions':

        include("./sites/transactions.php");

        return;

    case 'regions':

        include("./sites/region_list.php");

        return;

    case 'map':

        include("./sites/map.php");

        return;

    // Additions for Search

    case 'events':

        include("./sites/events.php");

        return;

    case 'make-events':

        include("./sites/make-events.php");

        return;

    case 'save-events':

        include("./sites/save-events.php");

        return;

    case 'classifieds':

        include("./sites/classifieds.php");

        return;

    // End Additions

    case 'logout':

        include("./sites/logout.php");

        return;

    default:

        include("./sites/sitemodul.php");

        return;





}

?>