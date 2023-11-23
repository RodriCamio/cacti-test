<?php
	$document_id_to_show=1;

// This sample is more complex. It takes data from the Cacti database that was created
// by Jimmy Conner's Docs plugin, and uses it as the content for a page. This gives
// you a basic 'CMS-like' functionality, with Docs providing the editing, and SuperLinks
// providing the access-control and presentation to your users.
//
// To use it, you will need to make one copy of this file for each Docs document you wish
// to show your users. Add them all as tabs or menu-items, and then change the line at the
// top of this file to the document id for that Docs document. You can find this at the
// end of the URL when you edit a document in Docs.

// The main purpose of this sample is to show how you might use SuperLinks for more than
// just static pages of information. The database queries could just as easily be looking
// at another database in another application...

/////////////////////////////////////////////////////////////

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

if(! isset($plugin_hooks['config_arrays']['docs']))
{
	print "This page relies on both the SuperLinks and Docs plugins. The Docs plugin is available from <a href=\"http://cactiusers.org/\">http://cactiusers.org/</a><hr>";
}
else
{

$SQL = "select * from plugin_docs where id=".intval($document_id_to_show);

 $queryrows = db_fetch_assoc($SQL);

        if(count($queryrows)!=1)
        {
                print "The document is not defined in Docs. Is the ID correct?";
        }
	else
	{
		print "<h1>". $queryrows[0]['title']. "</h1>";
		print "<h3>Last updated ".date(DATE_RFC822,$queryrows[0]['updated'])."</h3>";
		print $queryrows[0]['data'];
	}
}

?>
