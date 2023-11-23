<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2007 The Cacti Group                                      |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 |                                                                         |
 | This program is distributed in the hope that it will be useful,         |
 | but WITHOUT ANY WARRANTY; without even the implied warranty of          |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the           |
 | GNU General Public License for more details.                            |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDTool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/


chdir('../../');

include("./include/auth.php");

include_once($config['base_path'] . '/plugins/routerconfigs/functions.php');

include_once($config['base_path'] . '/plugins/routerconfigs/Text/Diff.php');
include_once($config['base_path'] . '/plugins/routerconfigs/Text/Diff/Renderer.php');
include_once($config['base_path'] . '/plugins/routerconfigs/Text/Diff/Renderer/table.php');

$file1 = '/home/configs/backups/BCS/IDF1/BCSIDF1.3550RTR-2008-02-20-0001';
$file2 = '/home/configs/backups/BCS/IDF1/BCSIDF1.3550RTR-2008-02-19-0001';



include_once("./include/top_header.php");

input_validate_input_number(get_request_var("device1"));
input_validate_input_number(get_request_var("device2"));

input_validate_input_number(get_request_var("file1"));
input_validate_input_number(get_request_var("file2"));

$devices = db_fetch_assoc("SELECT id, directory, hostname FROM plugin_routerconfigs_devices ORDER BY hostname");

$default = '';
if (isset($devices[0]['id'])) {
	$default = $devices[0]['id'];
}

$device1 = $default;
if (isset($_GET['device1'])) {
	$device1 = $_GET['device1'];
}
$device2 = $default;
if (isset($_GET['device2'])) {
	$device2 = $_GET['device2'];
}

$file1 = '';
if (isset($_GET['file1'])) {
	$file1 = $_GET['file1'];
}
$file2 = '';
if (isset($_GET['file2'])) {
	$file2 = $_GET['file2'];
}

$files = array();
$files2 = array();

if ($device1 != '') {
	$files1 = db_fetch_assoc("SELECT id, directory, filename FROM plugin_routerconfigs_backups WHERE device = $device1 ORDER BY directory, filename DESC");
}
if ($device2 != '') {
	$files2 = db_fetch_assoc("SELECT id, directory, filename FROM plugin_routerconfigs_backups WHERE device = $device2 ORDER BY directory, filename DESC");
}
display_tabs ();
print "<form name=form1>";
html_start_box("", "100%", $colors["header"], "1", "center", "");
html_header(array('File', 'File'));

form_alternate_row_color($colors["alternate"],$colors["light"],0);

print "<td width='50%'><select id=device1 name=device1 onChange='changeDeviceA()'>";
foreach ($devices as $f) {
	print '<option value=' . $f['id'] . ($device1 == $f['id'] ? ' selected' : '') . '>' . $f['directory'] . '/' . $f['hostname'] . '</option>';
}
print "</select><br>";
print "<select id=file1 name=file1 onChange='changeFileForm()'><option value=0></option>";
foreach ($files1 as $f) {
	print '<option value=' . $f['id'] . ($file1 == $f['id'] ? ' selected' : '') . '>' . $f['filename'] . '</option>';
}
print "</select></td>";

print "<td width='50%'><select id=device2 name=device2 onChange='changeDeviceB()'>";
foreach ($devices as $f) {
	print '<option value=' . $f['id'] . ($device2 == $f['id'] ? ' selected' : '') . '>' . $f['directory'] . '/' . $f['hostname'] . '</option>';
}
print "</select><br>";

print "<select id=file2 name=file2 onChange='changeFileForm()'><option value=0></option>";
foreach ($files2 as $f) {
	print '<option value=' . $f['id'] . ($file2 == $f['id'] ? ' selected' : '') . '>' . $f['filename'] . '</option>';
}
print "</select></td></tr>";

html_end_box(false);

print "</form><br><br>";
?>
<script type="text/javascript">
	<!--
	function changeDeviceA () {
		strURL = '?device1=' + document.getElementById('device1').value;
		strURL = strURL + '&device2=' + document.getElementById('device2').value;
		strURL = strURL + '&file1=';
		strURL = strURL + '&file2=' + document.getElementById('file2').value;
		document.location = strURL;
	}

	function changeDeviceB () {
		strURL = '?device1=' + document.getElementById('device1').value;
		strURL = strURL + '&device2=' + document.getElementById('device2').value;
		strURL = strURL + '&file1=' + document.getElementById('file1').value;
		strURL = strURL + '&file2=';
		document.location = strURL;
	}

	function changeFileForm () {
		strURL = '?device1=' + document.getElementById('device1').value;
		strURL = strURL + '&device2=' + document.getElementById('device2').value;
		strURL = strURL + '&file1=' + document.getElementById('file1').value;
		strURL = strURL + '&file2=' + document.getElementById('file2').value;
		document.location = strURL;
	}
	-->
</script>
<?php

if (isset($file1) && strlen($file1) > 0) {
	$device1 = db_fetch_row("SELECT * FROM plugin_routerconfigs_backups WHERE id = $file1");
}else{
	$device1 = array();
}
if (isset($file2) && strlen($file2) > 0) {
	$device2 = db_fetch_row("SELECT * FROM plugin_routerconfigs_backups WHERE id = $file2");
}else{
	$device2 = array();
}

if (isset($device1['id']) && isset($device2['id'])) {
	/* Load the lines of each file. */
	$lines1 = explode("\n", $device1['config']);
	$lines2 = explode("\n", $device2['config']);

	/* Create the Diff object. */
	$diff = new Text_Diff('auto', array($lines1, $lines2));

	/* Output the diff in unified format. */
	$renderer = new Text_Diff_Renderer_table();

	$text = $renderer->render($diff);

	html_start_box("", "100%", $colors["header"], "1", "center", "");
	html_header(array('<strong>' . $device1['directory'] . '/' . $device1['filename'] . '</strong>', '', '<strong>' . $device2['directory'] . '/' . $device2['filename'] . '</strong>'));
	print "<tr bgcolor=#6d88ad height=1><td width='50%'></td><td width=1></td><td width='50%'></td></tr>";
	if (trim($text) == '') {

		print "<tr><td colspan=3><center>There are no Changes</center></td></tr>";
	} else {
		$text = str_replace("\n", '<br>', $text);
		$text = str_replace("</td></tr>", "</td></tr>\n", $text);

		echo $text;
	}
	html_end_box(false);

	print "<br><br>";
}

include_once("./include/bottom_footer.php");


function display_tabs () {
	/* draw the categories tabs on the top of the page */
	print "<table class='tabs' width='100%' cellspacing='0' cellpadding='3' align='center'><tr>\n";
	print "<td bgcolor='#DFDFDF' nowrap='nowrap' width='" . (strlen('Devices') * 9) . "' align='center' class='tab'>
			<span class='textHeader'><a href='router-devices.php'>Devices</a></span>
			</td>\n
			<td width='1'></td>\n";
	print "<td bgcolor='#DFDFDF' nowrap='nowrap' width='" . (strlen('Backups') * 9) . "' align='center' class='tab'>
			<span class='textHeader'><a href='router-backups.php?device='>Backups</a></span>
			</td>\n
			<td width='1'></td>\n";
	print "<td bgcolor='#DFDFDF' nowrap='nowrap' width='" . (strlen('Authentication') * 9) . "' align='center' class='tab'>
			<span class='textHeader'><a href='router-accounts.php'>Authentication</a></span>
			</td>\n
			<td width='1'></td>\n";
	print "<td bgcolor='silver' nowrap='nowrap' width='" . (strlen('Compare') * 9) . "' align='center' class='tab'>
			<span class='textHeader'><a href='router-compare.php'>Compare</a></span>
			</td>\n
			<td width='1'></td>\n";
	print "<td></td>\n</tr></table>\n";

}
