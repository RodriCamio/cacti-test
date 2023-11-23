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

$action = "";
if (isset($_POST['action'])) {
	$action = $_POST['action'];
} else if (isset($_GET['action'])) {
	$action = $_GET['action'];
}

switch ($action) {
	case 'viewconfig':
		view_device_config();
		break;
	default:
		include_once("./include/top_header.php");
		display_tabs ();
		show_devices ();
		include_once("./include/bottom_footer.php");
		break;
}

function display_tabs () {
	/* draw the categories tabs on the top of the page */
	print "<table class='tabs' width='100%' cellspacing='0' cellpadding='3' align='center'><tr>\n";
	print "<td bgcolor='#DFDFDF' nowrap='nowrap' width='" . (strlen('Devices') * 9) . "' align='center' class='tab'>
			<span class='textHeader'><a href='router-devices.php'>Devices</a></span>
			</td>\n
			<td width='1'></td>\n";
	print "<td bgcolor='silver' nowrap='nowrap' width='" . (strlen('Backups') * 9) . "' align='center' class='tab'>
			<span class='textHeader'><a href='router-backups.php?device='>Backups</a></span>
			</td>\n
			<td width='1'></td>\n";
	print "<td bgcolor='#DFDFDF' nowrap='nowrap' width='" . (strlen('Authentication') * 9) . "' align='center' class='tab'>
			<span class='textHeader'><a href='router-accounts.php'>Authentication</a></span>
			</td>\n
			<td width='1'></td>\n";
	print "<td bgcolor='#DFDFDF' nowrap='nowrap' width='" . (strlen('Compare') * 9) . "' align='center' class='tab'>
			<span class='textHeader'><a href='router-compare.php'>Compare</a></span>
			</td>\n
			<td width='1'></td>\n";
	print "<td></td>\n</tr></table>\n";
}


function view_device_config() {
	global $colors;
	/* ================= input validation ================= */
	input_validate_input_number(get_request_var("id"));
	input_validate_input_number(get_request_var("device"));
	/* ==================================================== */
	$device = array();
	if (!empty($_GET["id"])) {
		$device = db_fetch_row("SELECT plugin_routerconfigs_backups.*, plugin_routerconfigs_devices.hostname, plugin_routerconfigs_devices.ipaddress
					 FROM plugin_routerconfigs_devices,plugin_routerconfigs_backups
					 WHERE plugin_routerconfigs_backups.device = plugin_routerconfigs_devices.id AND plugin_routerconfigs_backups.id=" . $_GET["id"], FALSE);
	}

	if (isset($device['id'])) {
		include_once("./include/top_header.php");
		display_tabs ();
		html_start_box("", "100%", $colors["header"], "4", "center", "");
		form_alternate_row_color($colors["alternate"],$colors["light"],0);
		print '<td><h2>Router Config for ' . $device['hostname'] . ' (' . $device['ipaddress'] . ')<br><br>';
		print 'Backup from ' . date('M j Y H:i:s', $device['btime']) . '<br>';
		print 'File: ' . $device['directory'] . '/' . $device['filename'];
		print '</h1><textarea rows=36 cols=120>';
		print $device['config'];
		print '</textarea></td></tr>';
		html_end_box(false);
	} else {
		header("Location: router-backups.php");
		exit;
	}
}

function show_devices () {
	global $action, $device, $colors, $config;

	load_current_session_value("page", "sess_routerconfigs_backups_current_page", "1");

	input_validate_input_number(get_request_var("page"));

	$device = '';
	if (isset($_GET['device'])) {
		input_validate_input_number(get_request_var("device"));
		$device = $_GET['device'];
		if (isset($_SESSION['routerconfigs_backups_device']) && $_SESSION['routerconfigs_backups_device'] != $device) {
			$page = 1;
			$_REQUEST["page"] = 1;
		}
		$_SESSION['routerconfigs_backups_device'] = $device;

	} else if (isset($_SESSION['routerconfigs_backups_device']) && $_SESSION['routerconfigs_backups_device'] != '') {
		$device = $_SESSION['routerconfigs_backups_device'];
	}

	$num_rows = 30;

	$sql = "SELECT plugin_routerconfigs_devices.hostname,plugin_routerconfigs_devices.ipaddress, plugin_routerconfigs_backups.id,plugin_routerconfigs_backups.username,plugin_routerconfigs_backups.lastchange,
		 plugin_routerconfigs_backups.btime, plugin_routerconfigs_backups.device, plugin_routerconfigs_backups.directory, plugin_routerconfigs_backups.filename
		 FROM plugin_routerconfigs_devices,plugin_routerconfigs_backups
		 WHERE plugin_routerconfigs_devices.id = plugin_routerconfigs_backups.device " . ($device != '' ? " AND plugin_routerconfigs_backups.device = $device " : '') . "
		 ORDER BY plugin_routerconfigs_backups.btime DESC limit " . ($num_rows*($_REQUEST["page"]-1)) . ", $num_rows";

	$result = db_fetch_assoc($sql);

	define("MAX_DISPLAY_PAGES", 21);
	$total_rows = db_fetch_cell("SELECT COUNT(*) FROM plugin_routerconfigs_backups" . ($device != '' ? " WHERE device = $device " : '') );
	$url_page_select = get_page_list($_REQUEST["page"], MAX_DISPLAY_PAGES, $num_rows, $total_rows, "router-backups.php?");

	html_start_box("", "100%", $colors["header"], "4", "center", "");
	if ($total_rows > 0) {
		$nav = "<tr bgcolor='#" . $colors["header"] . "'>
				<td colspan='10'>
					<table width='100%' cellspacing='0' cellpadding='0' border='0'>
						<tr>
							<td align='left' class='textHeaderDark'>
								<strong>&lt;&lt; "; if ($_REQUEST["page"] > 1) { $nav .= "<a class='linkOverDark' href='router-backups.php?page=" . ($_REQUEST["page"]-1) . "'>"; } $nav .= "Previous"; if ($_REQUEST["page"] > 1) { $nav .= "</a>"; } $nav .= "</strong>
							</td>\n
							<td align='center' class='textHeaderDark'>
								Showing Rows " . (($num_rows*($_REQUEST["page"]-1))+1) . " to " . ((($total_rows < $num_rows) || ($total_rows < ($num_rows*$_REQUEST["page"]))) ? $total_rows : ($num_rows*$_REQUEST["page"])) . " of $total_rows [$url_page_select]
							</td>\n
							<td align='right' class='textHeaderDark'>
								<strong>"; if (($_REQUEST["page"] * $num_rows) < $total_rows) { $nav .= "<a class='linkOverDark' href='router-backups.php?page=" . ($_REQUEST["page"]+1) . "'>"; } $nav .= "Next"; if (($_REQUEST["page"] * $num_rows) < $total_rows) { $nav .= "</a>"; } $nav .= " &gt;&gt;</strong>
							</td>\n
						</tr>
					</table>
				</td>
			</tr>\n";
	}else{
		$nav = "<tr bgcolor='#" . $colors["header"] . "'>
				<td colspan='10'>
					<table width='100%' cellspacing='0' cellpadding='0' border='0'>
						<tr>
							<td align='center' class='textHeaderDark'>
								No Rows Found
							</td>\n
						</tr>
					</table>
				</td>
			</tr>\n";
	}

	print $nav;
	html_header(array('Hostname', '', 'Directory','Filename', 'Backup Time', 'Last Change', 'Changed By'));

	$r = db_fetch_assoc("SELECT device, id FROM plugin_routerconfigs_backups ORDER BY btime ASC");
	$latest = array();
	if (count($r)) {
		foreach ($r as $s) {
			$latest[$s['device']] = $s['id'];
		}
	}
	$c=0;
	$i=0;
	if (sizeof($result)) {
		foreach ($result as $row) {
			form_alternate_row_color($colors["alternate"],$colors["light"],$i); $i++;
			print '<td><a href="router-devices.php?&action=edit&id=' . $row['device'] . '">' . $row['hostname'] . '</a></td>';
			print "<td><a href='router-backups.php?action=viewconfig&id=" . $row['id'] . "'>View Config</a> - <a href='router-compare.php?device1=" . $row['device'] . '&device2=' . $row['device'] . '&file1=' . $row['id'] . '&file2=' . $latest[$row['device']] . "'>Compare</a></td>";
			print '<td>' . $row['directory'] . '</td>';
			print '<td>' . $row['filename'] . '</td>';
			print '<td>' . date('M j Y H:i:s', $row['btime']) . '</td>';
			if ($row['lastchange'] > 0) {
				print '<td>' . date('M j Y H:i:s', $row['lastchange']) . '</td>';
			} else {
				print '<td> </td>';
			}
			print '<td>' . $row['username'] . '</td>';

			print "</tr>";
		}

		print $nav;
	}else{
		form_alternate_row_color($colors["alternate"],$colors["light"],$i); $i++;
		print '<td colspan="10">No Router Backups Found</td>';
		print '</tr>';
	}

	html_end_box(false);


}
