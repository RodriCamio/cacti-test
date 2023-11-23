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
include_once($config['base_path'] . '/plugins/routerconfigs/functions.php');

$ds_actions = array(1 => "Delete");

$action = "";
if (isset($_POST['action'])) {
	$action = $_POST['action'];
} else if (isset($_GET['action'])) {
	$action = $_GET['action'];
}

if (isset($_POST['password'])) {
	$password = $_POST['password'];
}else{
	$password = "";
}

if (isset($_POST['username'])) {
	$username = $_POST['username'];
}else{
	$username = "";
}

$account_edit = array(
	"name" => array(
		"method" => "textbox",
		"friendly_name" => "Name",
		"description" => "Give this account a meaningful name that will be displayed.",
		"value" => "|arg1:name|",
		"max_length" => "64",
		),
	"username" => array(
		"method" => "textbox",
		"friendly_name" => "Username",
		"description" => "The username that will be used for authenication.",
		"value" => "|arg1:username|",
		"max_length" => "64",
		),
	"password" => array(
		"method" => "textbox_password",
		"friendly_name" => "Password",
		"description" => "The password used for authenication.",
		"value" => "|arg1:password|",
		"default" => '',
		"max_length" => "64",
		"size" => "30"
		),
	"enablepw" => array(
		"method" => "textbox_password",
		"friendly_name" => "Enable Password",
		"description" => "Your Enable Password, if required.",
		"value" => "|arg1:enable_pw|",
		"default" => '',
		"max_length" => "64",
		"size" => "30"
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
	case 'actions':
		actions_accounts();
		break;
	case 'save':
		save_accounts ();
		break;
	case 'edit':
		include_once("./include/top_header.php");
		display_tabs ();
		edit_accounts();
		include_once("./include/bottom_footer.php");
		break;
	default:
		include_once("./include/top_header.php");
		display_tabs ();
		show_accounts ();
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
	print "<td bgcolor='#DFDFDF' nowrap='nowrap' width='" . (strlen('Backups') * 9) . "' align='center' class='tab'>
			<span class='textHeader'><a href='router-backups.php?device='>Backups</a></span>
			</td>\n
			<td width='1'></td>\n";
	print "<td bgcolor='silver' nowrap='nowrap' width='" . (strlen('Authentication') * 9) . "' align='center' class='tab'>
			<span class='textHeader'><a href='router-accounts.php'>Authentication</a></span>
			</td>\n
			<td width='1'></td>\n";
	print "<td bgcolor='#DFDFDF' nowrap='nowrap' width='" . (strlen('Compare') * 9) . "' align='center' class='tab'>
			<span class='textHeader'><a href='router-compare.php'>Compare</a></span>
			</td>\n
			<td width='1'></td>\n";
	print "<td></td>\n</tr></table>\n";
}

function actions_accounts () {
	global $colors, $ds_actions, $config;

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var_post('drp_action'));
	/* ==================================================== */

	if (isset($_POST["selected_items"])) {
		$selected_items = unserialize(stripslashes($_POST["selected_items"]));
		if ($_POST["drp_action"] == "1") {

			for ($i=0; $i<count($selected_items); $i++) {
				/* ================= input validation ================= */
				input_validate_input_number($selected_items[$i]);
				/* ==================================================== */
				db_execute("DELETE FROM plugin_routerconfigs_accounts WHERE id = " . $selected_items[$i]);
			}
		}
		header("Location: router-accounts.php");
		exit;
	}


	/* setup some variables */
	$account_list  = "";
	$account_array = array();

	/* loop through each of the accounts selected on the previous page and get more info about them */
	while (list($var,$val) = each($_POST)) {
		if (ereg("^chk_([0-9]+)$", $var, $matches)) {
			/* ================= input validation ================= */
			input_validate_input_number($matches[1]);
			/* ==================================================== */

			$account_list .= "<li>" . db_fetch_cell("select name from plugin_routerconfigs_accounts where id=" . $matches[1]) . "</li>";
			$account_array[] = $matches[1];
		}
	}

	include_once("./include/top_header.php");
	//display_tabs ();

	html_start_box("<strong>" . $ds_actions{$_POST["drp_action"]} . "</strong>", "60%", $colors["header_panel"], "3", "center", "");

	print "<form action='router-accounts.php' method='post'>\n";

	if (sizeof($account_array)) {
		if ($_POST["drp_action"] == "1") { /* Delete */
			print "	<tr>
					<td colspan='2' class='textArea' bgcolor='#" . $colors["form_alternate1"]. "'>
						<p>When you click 'Continue' the following account(s) will  be deleted.</p>
						<p><ul>$account_list</ul></p>
					</td>
					</tr>";
		}
		$save_html = "<input type='button' value='Cancel' onClick='window.history.back()'>&nbsp;<input type='submit' value='Continue' title='Delete Device(s)'>";
	}else{
		print "<tr><td bgcolor='#" . $colors["form_alternate1"]. "'><span class='textError'>You must select at least one device.</span></td></tr>\n";
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

function save_accounts () {
	/* ================= input validation ================= */
	input_validate_input_number(get_request_var_post("id"));
	/* ==================================================== */

	if (isset($_POST['id'])) {
		$save['id'] = $_POST['id'];
	} else {
		$save['id'] = '';
	}

	$save['name'] = sql_sanitize($_POST['name']);
	$save['username'] = sql_sanitize($_POST['username']);

	if ($_POST['password'] == $_POST['password_confirm']) {
		if ($_POST['password'] != '') {
			$save['password'] = plugin_routerconfigs_encode($_POST['password']);
		} else if ($save['id'] < 1) {
			raise_message(4);
		}
	} else {
		// Passwords are not the same!
		raise_message(4);
	}

	if ($_POST['enablepw'] == $_POST['enablepw_confirm']) {
		if ($_POST['enablepw'] != '') {
			$save['enablepw'] = plugin_routerconfigs_encode($_POST['enablepw']);
		}
	} else {
		// Passwords are not the same!
		raise_message(4);
	}

	$id = sql_save($save, "plugin_routerconfigs_accounts", "id");

	if (is_error_message()) {
		header("Location: router-accounts.php?action=edit&id=" . (empty($id) ? $_POST["id"] : $id));
		exit;
	}
	header("Location: router-accounts.php");
	exit;
}

function edit_accounts () {
	global $account_edit, $colors;

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var("id"));
	/* ==================================================== */

	$account = array();
	if (!empty($_GET["id"])) {
		$account = db_fetch_row("SELECT * FROM plugin_routerconfigs_accounts WHERE id=" . $_GET["id"], FALSE);
		$account['password'] = '';
		$header_label = "[edit: " . $account["name"] . "]";
	}else{
		$header_label = "[new]";
	}

	html_start_box("<strong>Account:</strong> $header_label", "100%", $colors["header"], "3", "center", "");
	draw_edit_form(array(
		"config" => array("form_name" => "chk"),
		"fields" => inject_form_variables($account_edit, $account)
		)
	);

	html_end_box();
	form_save_button("router-accounts.php");
}

function show_accounts () {
	global $action, $host, $username, $password, $command;
	global $colors, $config, $ds_actions;

	load_current_session_value("page", "sess_wmi_accounts_current_page", "1");
	$num_rows = 30;

	$sql = "SELECT * FROM plugin_routerconfigs_accounts limit " . ($num_rows*($_REQUEST["page"]-1)) . ", $num_rows";
	$result = db_fetch_assoc($sql);

	define("MAX_DISPLAY_PAGES", 21);
	$total_rows = db_fetch_cell("SELECT COUNT(*) FROM plugin_routerconfigs_accounts");
	$url_page_select = get_page_list($_REQUEST["page"], MAX_DISPLAY_PAGES, $num_rows, $total_rows, "router-accounts.php?");

	html_start_box("", "100%", $colors["header"], "4", "center", "");
	if ($total_rows > 0) {
		$nav = "<tr bgcolor='#" . $colors["header"] . "'>
				<td colspan='10'>
					<table width='100%' cellspacing='0' cellpadding='0' border='0'>
						<tr>
							<td align='left' class='textHeaderDark'>
								<strong>&lt;&lt; "; if ($_REQUEST["page"] > 1) { $nav .= "<a class='linkOverDark' href='router-accounts.php?page=" . ($_REQUEST["page"]-1) . "'>"; } $nav .= "Previous"; if ($_REQUEST["page"] > 1) { $nav .= "</a>"; } $nav .= "</strong>
							</td>\n
							<td align='center' class='textHeaderDark'>
								Showing Rows " . (($num_rows*($_REQUEST["page"]-1))+1) . " to " . ((($total_rows < $num_rows) || ($total_rows < ($num_rows*$_REQUEST["page"]))) ? $total_rows : ($num_rows*$_REQUEST["page"])) . " of $total_rows [$url_page_select]
							</td>\n
							<td align='right' class='textHeaderDark'>
								<strong>"; if (($_REQUEST["page"] * $num_rows) < $total_rows) { $nav .= "<a class='linkOverDark' href='router-accounts.php?page=" . ($_REQUEST["page"]+1) . "'>"; } $nav .= "Next"; if (($_REQUEST["page"] * $num_rows) < $total_rows) { $nav .= "</a>"; } $nav .= " &gt;&gt;</strong>
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
	html_header_checkbox(array('Description', 'Username', 'Devices'));

	$c=0;
	$i=0;
	if (sizeof($result)) {
		foreach ($result as $row) {
			$count = db_fetch_cell("SELECT count(account) FROM plugin_routerconfigs_devices WHERE account = " . $row['id']);
			form_alternate_row_color($colors["alternate"],$colors["light"],$i); $i++;
			print '<td><a href="router-accounts.php?&action=edit&id=' . $row['id'] . '">' . $row['name'] . '</a></td>';
			print '<td>' . $row['username'] . '</td>';
			print '<td><a href="router-devices.php?account=' . $row['id'] . '">' . $count . '</a></td>';

			print '<td style="' . get_checkbox_style() . '" width="1%" align="right">';
			print '<input type="checkbox" style="margin: 0px;" name="chk_' . $row["id"] . '" title="' . $row["name"] . '"></td>';
			print "</tr>";
		}
	}else{
		form_alternate_row_color($colors["alternate"],$colors["light"],$i); $i++;
		print '<td colspan="10">No Router Accounts Found</td>';
		print '</tr>';
	}

	html_end_box(false);
	draw_actions_dropdown($ds_actions);

	print "&nbsp;&nbsp;&nbsp;<input type='button' value='Add' onClick='cactiReturnTo(\"router-accounts.php?action=edit\")'>";
}
