<?php

// header("image/gif");

include_once(dirname(__FILE__).DIRECTORY_SEPARATOR."/tablib.php");

$alttext = $_REQUEST['alt'];
$file = $_REQUEST['file'];
$root = $_REQUEST['root'];
    
// look for preshrunk images, if these are Superlinks tabs
if(preg_match("/superlinks\/tab_images\/(.*)/",$file,$matches)) {
	$f = $matches[1];
	// make sure it's not already a small image
	if(preg_match("/^(red|tab)_/",$f)) {
		$newurl = preg_replace("/tab_images\//","tab_images/s_",$file);
	} else {
		$newurl = $file;
	}
	header("Location: $root/$newurl");
	exit();
} else {
	// look for preshrunk images, if these are standard Cacti tabs, too
	if(preg_match("/^images\/([^\/]+)$/",$file,$matches)) {
		$base = $matches[1];
		if(file_exists("tab_images/shrunk/".$base)) {
			$newurl = "tab_images/shrunk/".$base;
			header("Location: $newurl");
			exit();
		}
	}
        
	// now we're going to have to get creative - make a small image for
	// other plugins using their alttext to get the text for the tab
	$key = md5("$file - $alttext");

	# print "$key $file - $alttext";
     
	$superlinks_tabdir = realpath(dirname(__FILE__).DIRECTORY_SEPARATOR.'tab_images');
   
	if (substr_count($file, "down")) { 
		$tabfile = "auto_".$key."_down.gif";
	}else{
		$tabfile = "auto_".$key.".gif";
	}
	$tabfilefull = $superlinks_tabdir.DIRECTORY_SEPARATOR.$tabfile;

	if(!file_exists($tabfilefull)) {
		// note - because we're always using blue here, we're killing plugin-supplied red tabs
		// I can't think of a way around that
		if (substr_count($file, "down")) { 
			$tab = tabimage($alttext,"blank-tab-red-small.gif",1);
		}else{
			$tab = tabimage($alttext,"blank-tab-blue-small.gif",1);
		}
		imagegif($tab,$tabfilefull);
		imagedestroy($tab);
	}

	$url = $root."/plugins/superlinks/tab_images/".$tabfile;

	header("Location: $url");

}
