<h1>Who has which rights?</h1>

<?php
/////////////////////////////////////////////////////////////
//
// Shows a list of all known Cacti rights, and which users have them
//
//
// include the appropriate config file, depending on the Cacti version

$cacti_base = dirname(__FILE__)."..". DIRECTORY_SEPARATOR."..";

if( file_exists($cacti_base."/include/global.php") )
{
	include_once($cacti_base."/include/global.php");
}
elseif (file_exists($cacti_base."/include/config.php") )
{
        include_once($cacti_base."/include/config.php");
}

include_once($config["library_path"] . "/database.php");

foreach ($user_auth_realms as $id=>$desc)
{
	print "<h3>$id - $desc</h3>";

	# https://support2.network-i.net/cacti/user_admin.php?action=user_edit&id=10
	$SQL = "select * from user_auth_realm,user_auth where realm_id=$id and user_auth.id=user_auth_realm.user_id order by username";

	$queryrows = db_fetch_assoc($SQL);
        foreach ($queryrows as $user)
	{
		$id = $user['id'];
		print "<a href=\"../../user_admin.php?action=user_edit&id=$id\">".$user['username']."</a>&nbsp; ";
	}

}

?>
<hr />
