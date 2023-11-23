<?php

chdir('../../');
include("./include/auth.php");
include_once("./include/global_arrays.php");
include_once("./plugins/intropage/include/functions.php");


// set default page
if (isset ($_GET["default"]) && $_GET["default"] == "true")		{

    if (isset ($_GET["how"]))	{
	$_GET["how"] = intval ($_GET["how"]);
    
        if ($_GET["how"] >= 1 && $_GET["how"] <= 5 )
	db_execute ("update user_auth set login_opts = ". $_GET["how"] . " where id = " . $_SESSION["sess_user_id"]);
    }
}



$lopts = db_fetch_cell('SELECT login_opts FROM user_auth WHERE id=' . $_SESSION['sess_user_id']);
if ($lopts == 1 || $lopts == 2 || $lopts == 3 || $lopts == 4)   // = separated tab
    include_once("./plugins/intropage/include/general_header.php");


display_information();

// for users without console
intropage_display_hint();


if ($lopts == 1 || $lopts == 2 || $lopts == 3 || $lopts == 4)   // = separated tab
    include("./include/bottom_footer.php");

?>