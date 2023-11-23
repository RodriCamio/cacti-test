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


$ds_actions = array(1 => 'Backup', 2 => "Delete");

$action = "";
if (isset($_POST['action'])) {
	$action = $_POST['action'];
} else if (isset($_GET['action'])) {
	$action = $_GET['action'];
}

$acc = array('None');
$accounts = db_fetch_assoc("SELECT id, name FROM plugin_routerconfigs_accounts ORDER BY name", false);
if (!empty($accounts)) {
	foreach ($accounts as $a) {
		$acc[$a['id']] = $a['name'];
	}
}

$dtypes = array('Auto-Detect');
$dtypesarr = db_fetch_assoc("SELECT id, name FROM plugin_routerconfigs_devicetypes ORDER BY name", false);
if (!empty($dtypes)) {
	if (sizeof($dtypesarr)) {
	foreach ($dtypesarr as $a) {
		$dtypes[$a['id']] = $a['name'];
	}
	}
}

$account_edit = array(
	'enabled' => array(
		'method' => 'checkbox',
		'friendly_name' => 'Enable Device',
		'description' => 'Uncheck this box to disabled this device from being backed up.',
		'value' => '|arg1:enabled|',
		'default' => '',
		"form_id" => false
		),
	"hostname" => array(
		"method" => "textbox",
		"friendly_name" => "Description",
		"description" => "Name of this device (will be used for config saving and SVN if no hostname is present in config).",
		"value" => "|arg1:hostname|",
		"max_length" => "128",
		),
	"ipaddress" => array(
		"method" => "textbox",
		"friendly_name" => "IP Address",
		"description" => "This is the IP Address used to communicate with the device.",
		"value" => "|arg1:ipaddress|",
		"max_length" => "128",
		),
	"directory" => array(
		"method" => "textbox",
		"friendly_name" => "Directory",
		"description" => "This is the relative directory structure used to store the configs.",
		"value" => "|arg1:directory|",
		"max_length" => "255",
		),
	"schedule" => array(
		"method" => "drop_array",
		"friendly_name" => "Schedule",
		"description" => "How often to Backup this device.",
		"value" => "|arg1:schedule|",
		"default" => 1,
		"array" => array(1 => 'Daily', 7 => 'Weekly', 10 => 'Monthly'),
		),
	"devicetype" => array(
		"method" => "drop_array",
		"friendly_name" => "Device Type",
		"description" => "Choose the type of device that the router is.",
		"value" => "|arg1:devicetype|",
		"default" => 0,
		"array" => $dtypes,
		),
	"account" => array(
		"method" => "drop_array",
		"friendly_name" => "Authenication Account",
		"description" => "Choose an account to use to Login to the router",
		"value" => "|arg1:account|",
		"default" => 0,
		"array" => $acc,
		),
	"id" => array(
		"method" => "hidden_zero",
		"value" => "|arg1:id|"
		),
	"action" => array(
		"method" => "hidden_zero",
		"value" => "edit"
		)
	);





switch ($action) {
	case 'viewdebug':
		plugin_routerconfigs_view_device_debug();
		break;
	case 'viewconfig':
		view_device_config();
		break;
	case 'actions':
		actions_devices();
		break;
	case 'save':
		save_devices ();
		break;
	case 'edit':
		include_once("./include/top_header.php");
		display_tabs ();
		edit_devices();
		include_once("./include/bottom_footer.php");
		break;
	default:
		include_once("./include/top_header.php");
		display_tabs ();
		show_devices ();
		include_once("./include/bottom_footer.php");
		break;
}

function plugin_routerconfigs_view_device_debug () {
	global $colors;
	/* ================= input validation ================= */
	input_validate_input_number(get_request_var("id"));
	/* ==================================================== */
	$device = array();
	if (!empty($_GET["id"])) {
		$device = db_fetch_row("SELECT * FROM plugin_routerconfigs_devices WHERE id=" . $_GET["id"], FALSE);
	}

	if (isset($device['id'])) {
		include_once("./include/top_header.php");
		display_tabs ();
		html_start_box("", "100%", $colors["header"], "4", "center", "");
		form_alternate_row_color($colors["alternate"],$colors["light"],0);
		print '<td><h2>Debug for ' . $device['hostname'] . ' (' . $device['ipaddress'] . ')<br><br>';
		print '</h1><textarea rows=36 cols=120>';
		print base64_decode($device['debug']);
		print '</textarea></td></tr>';
		html_end_box(false);
	} else {
		header("Location: router-devices.php");
		exit;
	}
}

function display_tabs () {
	/* draw the categories tabs on the top of the page */
	print "<table class='tabs' width='100%' cellspacing='0' cellpadding='3' align='center'><tr>\n";
	print "<td bgcolor='silver' nowrap='nowrap' width='" . (strlen('Devices') * 9) . "' align='center' class='tab'>
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
	/* ==================================================== */
	$device = array();
	if (!empty($_GET["id"])) {
		$device = db_fetch_row("SELECT plugin_routerconfigs_backups.*, plugin_routerconfigs_devices.hostname, plugin_routerconfigs_devices.ipaddress
					 FROM plugin_routerconfigs_devices,plugin_routerconfigs_backups
					 WHERE plugin_routerconfigs_backups.device = plugin_routerconfigs_devices.id AND plugin_routerconfigs_backups.device=" . $_GET["id"]
					. " ORDER BY btime DESC", FALSE);
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
		header("Location: router-devices.php");
		exit;
	}
}

function actions_devices () {
	global $colors, $ds_actions, $config;

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var_post('drp_action'));
	/* ==================================================== */

	if (isset($_POST["selected_items"])) {
		$selected_items = unserialize(stripslashes($_POST["selected_items"]));
		if ($_POST["drp_action"] == "2") {

			for ($i=0; $i<count($selected_items); $i++) {
				/* ================= input validation ================= */
				input_validate_input_number($selected_items[$i]);
				/* ==================================================== */
				db_execute("DELETE FROM plugin_routerconfigs_devices WHERE id = " . $selected_items[$i]);
			}
		}
		header("Location: router-devices.php");
		exit;
	}

	/* setup some variables */
	$account_list = "";
	$account_array = array();

	/* loop through each of the devices selected on the previous page and get more info about them */
	while (list($var,$val) = each($_POST)) {
		if (ereg("^chk_([0-9]+)$", $var, $matches)) {
			/* ================= input validation ================= */
			input_validate_input_number($matches[1]);
			/* ==================================================== */

			$account_list .= "<li>" . db_fetch_cell("select hostname from plugin_routerconfigs_devices where id=" . $matches[1]) . "</li>";
			$account_array[] = $matches[1];
		}
	}

	if (sizeof($account_array)) {
		if ($_POST["drp_action"] == "1") { /* Backup */
			ini_set("max_execution_time", 0);
			ini_set("memory_limit", "256M");
			foreach ($account_array as $id) {
				$device = db_fetch_assoc("SELECT * FROM plugin_routerconfigs_devices WHERE id = $id");
				 plugin_routerconfigs_download_config($device[0]);
			}
			header("Location: router-devices.php");
			exit;
		}
	}

	include_once("./include/top_header.php");

	html_start_box("<strong>" . $ds_actions{$_POST["drp_action"]} . "</strong>", "60%", $colors["header_panel"], "3", "center", "");

	print "<form action='router-devices.php' method='post'>\n";

	if (sizeof($account_array)) {
		if ($_POST["drp_action"] == "2") { /* Delete */
			print "	<tr>
					<td colspan='2' class='textArea' bgcolor='#" . $colors["form_alternate1"]. "'>
						<p>When you click 'Continue', the following device(s) will be deleted.</p>
						<p><ul>$account_list</ul></p>
					</td>
					</tr>";
		}
		$save_html = "<input type='button' value='Cancel' onClick='window.history.back()'>&nbsp;<input type='submit' value='Continue' title='Delete Device(s)'>";
	}else{
		print "<tr><td bgcolor='#" . $colors["form_alternate1"]. "'><span class='textError'>You must select at least one query.</span></td></tr>\n";
		$save_html = "<input type='button' value='Return' onClick='window.history.back()'>";
	}

	print "	<tr>
			<td colspan='2' align='right' bgcolor='#eaeaea'>
				<input type='hidden' name='action' value='actions'>
				<input type='hidden' name='selected_items' value='" . (isset($account_array) ? serialize($account_array) : '') . "'>
				<input type='hidden' name='drp_action' value='" . $_POST["drp_action"] . "'>
				$save_html
			</td>
		</tr>
		";

	html_end_box();

	include_once("./include/bottom_footer.php");


}

function save_devices () {
	/* ================= input validation ================= */
	input_validate_input_number(get_request_var_post("id"));
	input_validate_input_number(get_request_var_post("devicetype"));
	input_validate_input_number(get_request_var_post("schedule"));
	/* ==================================================== */

	if (isset($_POST['id'])) {
		$save['id'] = $_POST['id'];
	} else {
		$save['id'] = '';
	}


	if (isset($_POST['enabled'])) {
		$save['enabled'] = 'on';
	} else {
		$save['enabled'] = '';
	}

	$save['hostname'] = sql_sanitize($_POST['hostname']);
	$save['ipaddress'] = sql_sanitize($_POST['ipaddress']);
	$save['directory'] = sql_sanitize($_POST['directory']);
	$save['account'] = sql_sanitize($_POST['account']);
	$save['devicetype'] = sql_sanitize($_POST['devicetype']);
	$save['schedule'] = sql_sanitize($_POST['schedule']);

	$id = sql_save($save, 'plugin_routerconfigs_devices', 'id');

	if (is_error_message()) {
		header("Location: router-devices.php?action=edit&id=" . (empty($id) ? $_POST["id"] : $id));
		exit;
	}

	header("Location: router-devices.php");
	exit;

}

function edit_devices () {
	global $account_edit, $colors;

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var("id"));
	/* ==================================================== */

	$account = array();
	if (!empty($_GET["id"])) {
		$account = db_fetch_row("SELECT * FROM plugin_routerconfigs_devices WHERE id=" . $_GET["id"], FALSE);
		$account['password'] = '';
		$header_label = "[edit: " . $account["hostname"] . "]";
	}else{
		$header_label = "[new]";
	}

	html_start_box("<strong>Query:</strong> $header_label", "100%", $colors["header"], "3", "center", "");
	draw_edit_form(array(
		"config" => array("form_name" => "chk"),
		"fields" => inject_form_variables($account_edit, $account)
		)
	);

	html_end_box();
	form_save_button("router-devices.php");
}

function show_devices () {
	global $action, $host, $username, $password, $command;
	global $colors, $config, $ds_actions, $acc;

	input_validate_input_number(get_request_var("account"));
	$account = '';
	if (isset($_GET['account'])) {
		$account = $_GET['account'];
	}

	load_current_session_value("page", "sess_routerconfigs_devices_current_page", "1");
	$num_rows = 30;

	$sql = "SELECT * FROM plugin_routerconfigs_devices ";
	if ($account != '') {
		$sql .= " WHERE account = $account ";
	}
	$sql .= "ORDER BY hostname limit " . ($num_rows*($_REQUEST["page"]-1)) . ", $num_rows";
	$result = db_fetch_assoc($sql);

	define("MAX_DISPLAY_PAGES", 21);
	$total_rows = db_fetch_cell("SELECT COUNT(*) FROM plugin_routerconfigs_devices" . ($account != '' ? " WHERE account = $account" : ''));
	$url_page_select = get_page_list($_REQUEST["page"], MAX_DISPLAY_PAGES, $num_rows, $total_rows, "router-devices.php?" . ($account != '' ? "account=$account" : ''));

	html_start_box("", "100%", $colors["header"], "4", "center", "");

	if ($total_rows > 0) {
		$nav = "<tr bgcolor='#" . $colors["header"] . "'>
				<td colspan='11'>
					<table width='100%' cellspacing='0' cellpadding='0' border='0'>
						<tr>
							<td align='left' class='textHeaderDark'>
								<strong>&lt;&lt; "; if ($_REQUEST["page"] > 1) { $nav .= "<a class='linkOverDark' href='router-devices.php?page=" . ($_REQUEST["page"]-1) . ($account != '' ? "&account=$account" : '') . "'>"; } $nav .= "Previous"; if ($_REQUEST["page"] > 1) { $nav .= "</a>"; } $nav .= "</strong>
							</td>\n
							<td align='center' class='textHeaderDark'>
								Showing Rows " . (($num_rows*($_REQUEST["page"]-1))+1) . " to " . ((($total_rows < $num_rows) || ($total_rows < ($num_rows*$_REQUEST["page"]))) ? $total_rows : ($num_rows*$_REQUEST["page"])) . " of $total_rows [$url_page_select]
							</td>\n
							<td align='right' class='textHeaderDark'>
								<strong>"; if (($_REQUEST["page"] * $num_rows) < $total_rows) { $nav .= "<a class='linkOverDark' href='router-devices.php?page=" . ($_REQUEST["page"]+1) . ($account != '' ? "&account=$account" : '') . "'>"; } $nav .= "Next"; if (($_REQUEST["page"] * $num_rows) < $total_rows) { $nav .= "</a>"; } $nav .= " &gt;&gt;</strong>
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
	html_header_checkbox(array('', 'Hostname', 'Configs', 'IP Address', 'Directory','Last Backup', 'Last Change', 'Changed By', 'Enabled'));

	$c=0;
	$i=0;
	if (sizeof($result)) {
		foreach ($result as $row) {
			form_alternate_row_color($colors["alternate"],$colors["light"],$i); $i++;

			$total = db_fetch_cell("SELECT count(device) FROM plugin_routerconfigs_backups WHERE device=" . $row['id']);

			print '<td><a href="telnet://' . $row['ipaddress'] .'"><img border=0 src="images/telnet.jpeg" height=10 alt="Telnet" title="Telnet"></a>';
			if (file_exists($config['base_path'] . '/plugins/traceroute/tracenow.php')) {
				print ' <a href="' . $config['url_path'] . 'plugins/traceroute/tracenow.php?ip=' . $row['ipaddress'] .'"><img border=0 src="images/reddot.png" height=10 alt="Trace Route" title="Trace Route"></a>';
			}
			print ' <a href="router-devices.php?action=viewdebug&id=' . $row['id'] . '"><img border=0 src="images/feedback.jpg" height=10 alt="Router Debug Info" title="Router Debug Info"></a>';
			print '</td>';
			print '<td><a href="router-devices.php?&action=edit&id=' . $row['id'] . '">' . $row['hostname'] . '</a></td>';
			print "<td><a href='router-devices.php?action=viewconfig&id=" . $row['id'] . "'>Current</a> - <a href='router-backups.php?device=" . $row['id'] . "'>Backups ($total)</a></td>";
			print '<td>' . $row['ipaddress'] . '</td>';
			print '<td>' . $row['directory'] . '</td>';
			print '<td>' . ($row['lastbackup'] < 1 ? '' : date('M j Y H:i:s', $row['lastbackup'])) . '</td>';
			print '<td>' . ($row['lastchange'] < 1 ? '' : date('M j Y H:i:s', $row['lastchange'])) . '</td>';
			print '<td>' . $row['username'] . '</td>';
			print '<td>' . ($row['enabled'] == 'on' ? 'Yes' : '<font color=red><b>No</b></font>') . '</td>';


			print '<td style="' . get_checkbox_style() . '" width="1%" align="right">';
			print '<input type="checkbox" style="margin: 0px;" name="chk_' . $row["id"] . '" title="' . $row["hostname"] . '"></td>';
			print "</tr>";
		}
	}else{
		form_alternate_row_color($colors["alternate"],$colors["light"],$i); $i++;
		print '<td colspan="10">No Router Devices Found</td>';
		print '</tr>';
	}
	html_end_box(false);
	draw_actions_dropdown($ds_actions);

	print "&nbsp;&nbsp;&nbsp;<input type='button' value='Add' onClick='cactiReturnTo(\"router-devices.php?action=edit\")'>";

}





