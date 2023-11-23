<?php
$guest_account = true;

chdir(dirname(__FILE__).'/../../');
include_once("./include/auth.php");
$cacti_base = dirname(__FILE__)."/..". DIRECTORY_SEPARATOR."..";

$superlinks_contentdir = realpath(dirname(__FILE__).DIRECTORY_SEPARATOR.'content');
$superlinks_tabdir = realpath(dirname(__FILE__).DIRECTORY_SEPARATOR.'tab_images');

$action = "";
if (isset($_POST['action'])) { 
	$action = $_POST['action'];
} elseif (isset($_GET['action'])) {
	$action = $_GET['action'];
}

$_SESSION['custom']=false;

$pageid = 0;
if (isset($_GET['id'])) { 
	$pageid=$_GET['id']; 
}

$page = db_fetch_row("SELECT DISTINCT
	id,
	title,
	style,
	contentfile
	FROM (superlinks_pages, superlinks_auth)
	WHERE superlinks_pages.id=superlinks_auth.pageid
	AND id=" . $pageid . "
	AND (userid=" . $_SESSION["sess_user_id"] . " OR userid=0)
	ORDER BY sortorder, id");

if (!isset($page)) {
	print "Either the page is not defined, or you do not have permission to view it.";
} else {
	global $superlinks_nav;

	unset ($refresh);
	if ($page["style"] == "TAB") {
		$superlinks_nav["superlinks.php:"]["title"] = $page["title"];
		$superlinks_nav["superlinks.php:"]["mapping"] = "";
		include_once("./plugins/superlinks/general_header.php");
	}else{
		$superlinks_nav["superlinks.php:"]["title"]   = $page["title"];
		$superlinks_nav["superlinks.php:"]["mapping"] = "index.php:";
		include_once("./include/top_header.php");
	}

	if (preg_match('/^((((ht|f)tp(s?))\:\/\/){1}\S+)/i',$page['contentfile'])) {
		print '<iframe id="slcontent" src="' . $page['contentfile'] . '" frameborder="0"></iframe>';
	} else {
		print '<div id="slcontent">';

		$my_file = $config["base_path"] . "/plugins/superlinks/content/" . $page['contentfile'];

		if (file_exists($my_file)) {
			@include_once($my_file);
		} else {
			print '<h1>The File Does not appear to exist!!</h1>';
		}

		print '</div>';
	}
}

include($config['base_path'] . "/include/bottom_footer.php");
