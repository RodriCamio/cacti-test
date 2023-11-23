<?php
chdir('../../');
include("./include/auth.php");

$_SESSION['custom']=false;
include("./plugins/ntop/general_header.php");

$host = read_config_option("ntop_url");

if (isset($_SERVER['HTTP_HOST']))
	$serverip = $_SERVER['HTTP_HOST'];
else
	$serverip = '';

if ($host == '' && $serverip != '') {
	$host = "http://$serverip:3000/";
} else {
	$host = str_replace("|SERVERIP|",$serverip,$host);
}

if ($host != '') {
	print "<iframe src='" . $host . "' width='100%' height='100%' frameborder=0></iframe>";
} else {
	print "<center>Please set the NTop URL setting.</center>";
}

?>