<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2010 The Cacti Group                                 |
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
include_once("./lib/utility.php");
include_once("./plugins/slowlog/slowlog_functions.php");
include_once("./plugins/slowlog/lib/open-flash-chart.php");

define("MAX_DISPLAY_PAGES", 21);

$actions = array(
	1 => "Delete"
	);

/* set default action */
if (!isset($_REQUEST["action"])) { $_REQUEST["action"] = ""; }

/* correct for a cancel button */
if (isset($_REQUEST["cancel"])) { $_REQUEST["action"] = ""; }

switch ($_REQUEST["action"]) {
	case 'save':
	case 'import':
		form_save();

		break;
	case 'actions':
		form_actions();

		break;
	case 'viewmethods':
		slowlog_viewchart("methods");

		break;
	case 'viewtables':
		slowlog_viewchart("tables");

		break;
	case 'edit':
		include_once("./plugins/slowlog/top_general_header.php");
		slowlog_import();
		include_once("./include/bottom_footer.php");

		break;
	case 'methods':
		include_once("./plugins/slowlog/top_general_header.php");
		slowlog_view_methods();
		include_once("./include/bottom_footer.php");

		break;
	case 'tables':
		include_once("./plugins/slowlog/top_general_header.php");
		slowlog_view_tables();
		include_once("./include/bottom_footer.php");

		break;
	case 'details':
		include_once("./plugins/slowlog/top_general_header.php");
		slowlog_view_details();
		include_once("./include/bottom_footer.php");

		break;
	case 'query':
		include_once("./plugins/slowlog/top_general_header.php");
		slowlog_view_query();
		include_once("./include/bottom_footer.php");

		break;
	default:
		include_once("./plugins/slowlog/top_general_header.php");
		slowlog_view();
		include_once("./include/bottom_footer.php");

		break;
}

/* --------------------------
    The Save Function
   -------------------------- */

function form_save() {
	if (isset($_POST["save_component_slowlog"])) {
		$logid = api_slowlog_save($_POST["logid"], $_POST["description"], $_POST["length"]);

		header("Location: slowlog.php?action=methods&logid=" . (empty($logid) ? $_POST["logid"] : $logid));
	}

	if (isset($_POST["save_component_import"])) {
		if (($_FILES["import_file"]["tmp_name"] != "none") && ($_FILES["import_file"]["tmp_name"] != "")) {
			/* file upload */
			$csv_data = file($_FILES["import_file"]["tmp_name"]);

			/* obtain debug information if it's set */
			$debug_data = import_logfile($_FILES["import_file"]["tmp_name"], $_POST["description"], $_POST["length"], $_POST["table_names"], $_POST["usecacti"]);
			if(sizeof($debug_data) > 0) {
				$_SESSION["import_debug_info"] = $debug_data;
			}
		}else{
			header("Location: slowlog.php"); exit;
		}

		header("Location: slowlog.php");
	}
}

/* ------------------------
    The "actions" function
   ------------------------ */

function form_actions() {
	global $colors, $config, $actions;

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var_post('drp_action'));
	/* ==================================================== */

	/* if we are to save this form, instead of display it */
	if (isset($_POST["selected_items"])) {
		$selected_items = unserialize(stripslashes($_POST["selected_items"]));

		if ($_POST["drp_action"] == "1") { /* delete */
			for ($i=0; $i<count($selected_items); $i++) {
				/* ================= input validation ================= */
				input_validate_input_number($selected_items[$i]);
				/* ==================================================== */

				api_slowlog_remove($selected_items[$i]);
			}
		}

		header("Location: slowlog.php");
		exit;
	}

	/* setup some variables */
	$slowlog_list = ""; $slowlog_array = array();

	/* loop through each of the host templates selected on the previous page and get more info about them */
	while (list($var,$val) = each($_POST)) {
		if (ereg("^chk_([0-9]+)$", $var, $matches)) {
			/* ================= input validation ================= */
			input_validate_input_number($matches[1]);
			/* ==================================================== */

			$slowlog_info    = db_fetch_cell("SELECT CONCAT_WS('', description, ' - ', import_date, '') FROM plugin_slowlog WHERE logid=" . $matches[1]);
			$slowlog_list   .= "<li>" . $slowlog_info . "</li>";
			$slowlog_array[] = $matches[1];
		}
	}

	include_once("./plugins/slowlog/top_general_header.php");

	html_start_box("<strong>" . $actions{$_POST["drp_action"]} . "</strong>", "60%", $colors["header_panel"], "3", "center", "");

	print "<form action='slowlog.php' method='post'>\n";

	if ($_POST["drp_action"] == "1") { /* delete */
		print "	<tr>
				<td class='textArea' bgcolor='#" . $colors["form_alternate1"]. "'>
					<p>Are you sure you want to delete the following MySQL Slow Log entries?</p>
					<p><ul>$slowlog_list</ul></p>";
					print "</td></tr>
				</td>
			</tr>\n
			";
	}

	if (!isset($slowlog_array)) {
		print "<tr><td bgcolor='#" . $colors["form_alternate1"]. "'><span class='textError'>You must select at least one Slowlog record.</span></td></tr>\n";
		$save_html = "";
	}else{
		$save_html = "<input type='submit' name='save' value='Yes'>";
	}

	print "	<tr>
		<td colspan='2' align='right' bgcolor='#eaeaea'>
			<input type='hidden' name='action' value='actions'>
			<input type='hidden' name='selected_items' value='" . (isset($slowlog_array) ? serialize($slowlog_array) : '') . "'>
			<input type='hidden' name='drp_action' value='" . $_POST["drp_action"] . "'>" . (strlen($save_html) ? "
			<input type='submit' name='cancel' value='No'>
			$save_html" : "<input type='submit' name='cancel' value='Return'>") . "
		</td>
	</tr>";

	html_end_box();

	include_once("./include/bottom_footer.php");
}

function api_slowlog_save($logid, $description, $length) {
	return true;

	$save["logid"]            = $logid;
	$save["description"]      = form_input_validate($description, "description", "", false, 3);

	$logid = 0;
	if (!is_error_message()) {
		$logid = sql_save($save, "slowlog", "logid");

		if ($logid) {
			raise_message(1);
		}else{
			raise_message(2);
		}
	}

	return $logid;
}

function api_slowlog_remove($logid) {
	db_execute("DELETE FROM plugin_slowlog WHERE logid=$logid");
	db_execute("DELETE FROM plugin_slowlog_details WHERE logid=$logid");
	db_execute("DELETE FROM plugin_slowlog_tables WHERE logid=$logid");
	db_execute("DELETE FROM plugin_slowlog_details_tables WHERE logid=$logid");
	db_execute("DELETE FROM plugin_slowlog_details_methods WHERE logid=$logid");
}

function slowlog_import() {
	global $colors, $config;

	?><form method="post" action="slowlog.php?action=import" enctype="multipart/form-data"><?php

	if ((isset($_SESSION["import_debug_info"])) && (is_array($_SESSION["import_debug_info"]))) {
		html_start_box("<strong>Import Results</strong>", "100%", "aaaaaa", "3", "center", "");

		print "<tr bgcolor='#" . $colors["form_alternate1"] . "'><td><p class='textArea'>Cacti has imported the following MySQL Slowlog Items:</p>";
		if (sizeof($_SESSION["import_debug_info"])) {
		foreach($_SESSION["import_debug_info"] as $import_result) {
			print "<tr bgcolor='#" . $colors["form_alternate1"] . "'><td>" . $import_result . "</td>";
			print "</tr>";
		}
		}

		html_end_box();

		kill_session_var("import_debug_info");
	}

	html_start_box("<strong>Import MySQL Slowlog</strong>", "100%", $colors["header"], "3", "center", "");

	$i=1;
	form_alternate_row_color($colors["form_alternate1"],$colors["form_alternate2"],$i);$i++;?>
		<td width='50%'><font class='textEditTitle'>Slowlog Description:</font><br>
			Please provide a description for this MySQL Slowlog file.
		</td>
		<td align='left'>
			<input type='textbox' size='50' value='New Slow Log' name='description'>
		</td>
	</tr><?php
	form_alternate_row_color($colors["form_alternate1"],$colors["form_alternate2"],$i);$i++;?>
		<td width='50%'><font class='textEditTitle'>Import MySQL Slowlog File</font><br>
			Please specify the location of the MySQL Slowlog file.
		</td>
		<td align='left'>
			<input type='file' size='50' name='import_file'>
		</td>
	</tr><?php
	form_alternate_row_color($colors["form_alternate1"],$colors["form_alternate2"],$i);?>
		<td width='50%'><font class='textEditTitle'>Max Query Length:</font><br>
			Only import the first X characters of the SQL Query from the MySQL Slow Query log.
		</td>
		<td align='left'>
			<select name='length'>
				<option value=99999>All</option>
				<option value=128>128 Chars</option>
				<option value=256>256 Chars</option>
				<option value=512>512 Chars</option>
				<option selected value=1024>1024 Chars</option>
				<option value=2048>2048 Chars</option>
				<option value=4096>4096 Chars</option>
				<option value=8192>8192 Chars</option>
				<option value=16384>16384 Chars</option>
			</select>
		</td>
	</tr><?php
	html_end_box(FALSE);
	html_start_box("<strong>Slowlog Table Names</strong>", "100%", $colors["header"], "3", "center", "");
	form_alternate_row_color($colors["form_alternate1"],$colors["form_alternate2"],$i);$i++;?>
		<td width='50%'><font class='textEditTitle'>Use This Cacti Database</font><br>
			Slowlog needs to know the tables names used in your slowlog.  If it's the Cacti database on this
			system, simply check the checkbox.  Otherwise, you will have to paste the output as show below under
			'Tables of Interest'.<br><br>
		<td align='left'>
			<input type='checkbox' name='usecacti'>
		</td>
	</tr><?php
	form_alternate_row_color($colors["form_alternate1"],$colors["form_alternate2"],$i);$i++;?>
		<td width='50%'><font class='textEditTitle'>Tables of Interest</font><br>
			Please provide a space delimited list of tables that you are interested in.  If you provide this list of tables
			the MySQL Slow Query Log will be scanned for these entries and more details statistics
			will be provided.  In Linux/UNIX, you may obtain a list of tables by using the following command:<br><br>
			echo `mysql -u<i><b>user</b></i> -p<i><b>password</b></i> -e "show tables" <i><b>database</b></i> | grep -v Tables_in`<br><br>The values of
			'<i><b>user</b></i>', '<i><b>password</b></i>', and '<i><b>database</b></i>' are replaced with your values.
		</td>
		<td align='left'>
			<textarea class='textAreaNotes' rows='10' cols='90' name='table_names'></textarea>
		</td>
	</tr><?php
	form_hidden_box("save_component_import","1","");
	html_end_box(FALSE);
	html_start_box("<strong>Upload Limits</strong>", "100%", $colors["header"], "3", "center", "");

	$upload_max_filesize = ini_get("upload_max_filesize");
	$post_max_size       = ini_get("post_max_size");
	form_alternate_row_color($colors["form_alternate1"],$colors["form_alternate2"],$i);$i++;?>
		<td width='80%'><font class='textEditTitle'>Max Upload Filesize</font><br>
			The maximum filesize your Apache server will allow to be uploaded is set to the value on
			the right.  Currently, you can not upload a file larger than this.  If you have MySQL Slow
			logs larger than this, you must alter the <i>php.ini</i> file associated with Apache,
			find the variable <b><i>upload_max_filesize</i></b> and increase the value.  After which you
			must restart Apache.
		</td>
		<td align='left'>
			<b><i><?php print $upload_max_filesize . " Bytes";?></i></b>
		</td><?php
	form_alternate_row_color($colors["form_alternate1"],$colors["form_alternate2"],$i);$i++;?>
		<td width='80%'><font class='textEditTitle'>Max Post Size</font><br>
			The maximum size you can post to the Apache server is set to the value on the right.
			If you have MySQL Slow logs larger than this value, you must alter the <i>php.ini</i> file
			associated with Apache, find the variable <b><i>post_max_size</i></b> and increase its value.
			After which you must restart Apache.
		</td>
		<td align='left'>
			<b><i><?php print $post_max_size . " Bytes";?></i></b>
		</td><?php

	html_end_box(FALSE);

	slowlog_save_button("return", "import");
}

function slowlog_viewchart($chart_type) {
	global $colors, $config;

	include("./plugins/slowlog/lib/open-flash-chart-object.php");

	/* ================= input validation ================= */
	input_validate_input_number($_REQUEST["logid"]);
	/* ==================================================== */

	$id = $_REQUEST["logid"];

	$description = db_fetch_cell("SELECT description FROM plugin_slowlog WHERE logid=$id");

	if (isset($_SESSION["sess_slowlog_details_summary_" . $chart_type . "_" . $id])) {
		$details = $_SESSION["sess_slowlog_details_summary_" . $chart_type . "_" . $id];
		$tstats  = $_SESSION["sess_slowlog_details_bytable_" . $chart_type . "_" . $id];
	}else{
		$details = db_fetch_assoc("SELECT
				sm.method AS type,
				count(*) AS count,
				sum(query_time) AS query_time,
				sum(lock_time) AS lock_time,
				sum(rows_examined) AS rows_examined,
				sum(rows_sent) AS rows_sent
			FROM plugin_slowlog_details_methods AS dm
			INNER JOIN plugin_slowlog_details AS d
			ON dm.logid=d.logid AND dm.logentry=d.logentry
			INNER JOIN plugin_slowlog_methods AS sm
			ON dm.methodid=sm.methodid
			WHERE d.logid=$id
			GROUP BY sm.methodid
			ORDER BY count DESC");

		$_SESSION["sess_slowlog_details_summary_" . $chart_type . "_" . $id] = $details;

		$tstats = db_fetch_assoc("SELECT *
			FROM (
				SELECT table_name AS type,
					count(*) AS count,
					sum(query_time) AS query_time,
					sum(lock_time) AS lock_time,
					sum(rows_examined) AS rows_examined,
					sum(rows_sent) AS rows_sent
				FROM plugin_slowlog_details_tables AS dt
				INNER JOIN plugin_slowlog_details AS d
				ON dt.logid=d.logid AND dt.logentry=d.logentry
				WHERE d.logid=$id
				GROUP BY table_name
				UNION ALL
				SELECT 'others' AS type,
					count(*) AS count,
					sum(query_time) AS query_time,
					sum(lock_time) AS lock_time,
					sum(rows_examined) AS rows_examined,
					sum(rows_sent) AS rows_sent
				FROM plugin_slowlog_details AS d
				LEFT JOIN plugin_slowlog_details_tables AS dt
				ON dt.logid=d.logid AND dt.logentry=d.logentry
				WHERE dt.table_name IS NULL
				AND d.logid=$id
				GROUP BY table_name) AS fish
			ORDER BY count DESC LIMIT 10");

		$_SESSION["sess_slowlog_details_bytable_" . $chart_type . "_" . $id] = $tstats;
	}

	if (isset($_REQUEST["type"])) {
		$measure = $_REQUEST["type"];
	}else{
		$measure = 'count';
	}

	switch($measure) {
	case 'count':
		$unit = "Queries";
		$suffix = "Total Queries";
		break;
	case 'rows_sent':
		$unit = "Rows";
		$suffix = "Rows Returned";
		break;
	case 'rows_examined':
		$unit = "Rows";
		$suffix = "Rows Examined";
		break;
	case 'lock_time':
		$unit = "Seconds";
		$suffix = "Lock Seconds";
		break;
	case 'query_time':
		$unit = "Seconds";
		$suffix = "Query Seconds";
		break;
	}

	if ($chart_type == "tables") {
		$details = $tstats;
	}

	if (sizeof($details)) {
		$elements = array();
		$legend   = array();
		$maxvalue = 0;

		foreach($details as $entry) {
			if ($maxvalue < $entry[$measure]) {
				$maxvalue = $entry[$measure];
				$scaling  = slowlog_autoscale($entry[$measure]);
			}
		}

		$maxvalue  = slowlog_getmax($maxvalue);
		$autorange = slowlog_autoscale($maxvalue);
		$maxvalue  = $maxvalue / $autorange[0];

		$i = 0;
		foreach($details as $entry) {
			$elements[$i] = new bar_value(round($entry[$measure]/$autorange[0], 3));
			$elements[$i]->set_colour( slowlog_get_color() );
			$elements[$i]->set_tooltip( $unit . ": #val# " . $autorange[1]);
			$legend[]   = $entry['type'];
			$i++;
		}

		$bar = new bar_glass();
		$bar->set_values( $elements );
		$bar->set_on_click("clickme");

		$title = new title( $description . " (" . $suffix . ")");
		$title->set_style( "{font-size: 18px; color: #444444; text-align: center;}" );

		$x_axis_labels = new x_axis_labels();
		$x_axis_labels->set_size( 10 );
		$x_axis_labels->rotate(45);
		$x_axis_labels->set_labels( $legend );

		$x_axis = new x_axis();
		//$x_axis->set_3d( 3 );
		$x_axis->set_colours('#909090', '#909090');
		$x_axis->set_labels( $x_axis_labels );

		$y_axis = new y_axis();
		$y_axis->set_offset( true );
		$y_axis->set_colours('#909090', '#909090');
		$y_axis->set_range( 0, $maxvalue, $maxvalue/10);
		$y_axis->set_label_text( "#val# " . $autorange[1] );

		$chart = new open_flash_chart();
		$chart->set_title( $title );
		$chart->add_element( $bar );
		$chart->set_x_axis( $x_axis );
		$chart->add_y_axis( $y_axis );
		$chart->set_bg_colour( '#FEFEFE' );
		echo $chart->toString();
	}
}

function slowlog_getmax($value) {
	$value = round($value * 1.01, 0);

	$length  = strlen($value) - 2;
	$divisor = ("1" . str_repeat("0", $length));

	$temp = $value / $divisor;
	$temp = ceil($temp);

	return $temp * $divisor;
}

function slowlog_autoscale($value) {
	if ($value < 10000) {
		return  array(1, "");
	}elseif ($value < 1000000) {
		return array(1024, "K");
	}elseif ($value < 100000000) {
		return array(1048576, "M");
	}elseif ($value < 10000000000) {
		return array(1073741824, "G");
	}else{
		return array(1099511627776, "T");
	}
}

function slowlog_check_changed($request, $session) {
	if ((isset($_REQUEST[$request])) && (isset($_SESSION[$session]))) {
		if ($_REQUEST[$request] != $_SESSION[$session]) {
			return 1;
		}
	}
}

function slowlog_view_details() {
	global $colors, $config;

	/* ================= input validation ================= */
	input_validate_input_number($_REQUEST["logid"]);
	/* ==================================================== */

	/* clean up search string */
	if (isset($_REQUEST["filter"])) {
		$_REQUEST["filter"] = sanitize_search_string(get_request_var("filter"));
	}

	/* clean up sort_column */
	if (isset($_REQUEST["sort_column"])) {
		$_REQUEST["sort_column"] = sanitize_search_string(get_request_var("sort_column"));
	}

	/* clean up search string */
	if (isset($_REQUEST["sort_direction"])) {
		$_REQUEST["sort_direction"] = sanitize_search_string(get_request_var("sort_direction"));
	}

	/* if any of the settings changed, reset the page number */
	$changed = 0;
	$changed += slowlog_check_changed("filter", "sess_slowlog_details_filter");
	$changed += slowlog_check_changed("table", "sess_slowlog_details_table");
	$changed += slowlog_check_changed("rows", "sess_slowlog_details_rows");
	$changed += slowlog_check_changed("method", "sess_slowlog_details_method");

	if ($changed) {
		$_REQUEST["page"] = "1";
	}

	load_current_session_value("page", "sess_slowlog_details_current_page", "1");
	load_current_session_value("filter", "sess_slowlog_details_filter", "");
	load_current_session_value("table", "sess_slowlog_details_table", "-1");
	load_current_session_value("method", "sess_slowlog_details_method", "-1");
	load_current_session_value("rows", "sess_slowlog_details_rows", "-1");
	load_current_session_value("sort_column", "sess_slowlog_details_sort_column", "table_name");
	load_current_session_value("sort_direction", "sess_slowlog_details_sort_direction", "ASC");

	if ($_REQUEST["rows"] == -1) {
		$row_limit = read_config_option("num_rows_device");
	}elseif ($_REQUEST["rows"] == -2) {
		$row_limit = 999999;
	}else{
		$row_limit = $_REQUEST["rows"];
	}

	if (isset($_REQUEST["source"]) && $_REQUEST["source"] == "methods") {
		$method = $_SESSION["sess_slowlog_details_summary_methods_" . $_REQUEST["logid"]][$_REQUEST["index"]]["type"];
		$_REQUEST["method"] = db_fetch_cell("SELECT methodid FROM plugin_slowlog_methods WHERE method='$method'");
		$_REQUEST["table"]  = "-1";
		load_current_session_value("table", "sess_slowlog_details_table", "-1");
		load_current_session_value("method", "sess_slowlog_details_method", "-1");
	}elseif (isset($_REQUEST["source"]) && $_REQUEST["source"] == "tables") {
		$_REQUEST["table"]  = $_SESSION["sess_slowlog_details_bytable_tables_" . $_REQUEST["logid"]][$_REQUEST["index"]]["type"];

		if ($_REQUEST["table"] == "others") {
			$_REQUEST["table"] = -2;
		}
		$_REQUEST["method"] = "-1";
		load_current_session_value("table", "sess_slowlog_details_table", "-1");
		load_current_session_value("method", "sess_slowlog_details_method", "-1");
	}

	/* form the 'where' clause for our main sql query */
	if (strlen($_REQUEST["filter"])) {
		$sql_where = "WHERE (sld.host LIKE '%%" . $_REQUEST["filter"] . "%%' OR " .
			"sld.user LIKE '%%" . $_REQUEST["filter"] . "%%' OR " .
			"sld.query LIKE '%%" . $_REQUEST["filter"] . "%%' OR " .
			"sld.ip LIKE '%%" . $_REQUEST["filter"] . "%%')";
	}else{
		$sql_where = "";
	}

	if ($_REQUEST["method"] != "-1") {
		$sql_where .= (strlen($sql_where) ? " AND":"WHERE") . " slm.methodid='" . $_REQUEST["method"] . "'";
	}

	if ($_REQUEST["table"] != "-1" && $_REQUEST["table"] != "-2") {
		$sql_where .= (strlen($sql_where) ? " AND":"WHERE") . " sldt.table_name='" . $_REQUEST["table"] . "'";
	}elseif ($_REQUEST["table"] == "-2") {
		$sql_where .= (strlen($sql_where) ? " AND":"WHERE") . " sldt.table_name IS NULL";
	}

	$query = "SELECT DISTINCT sld.*, slm.method, sldt.table_name
		FROM plugin_slowlog_details AS sld
		LEFT JOIN plugin_slowlog_details_tables AS sldt
		ON sld.logid=sldt.logid AND sld.logentry=sldt.logentry
		LEFT JOIN plugin_slowlog_details_methods AS sldm
		ON sld.logid=sldm.logid AND sld.logentry=sldm.logentry
		INNER JOIN plugin_slowlog_methods AS slm
		ON sldm.methodid=slm.methodid
		$sql_where
		ORDER BY " . $_REQUEST["sort_column"] . " " . $_REQUEST["sort_direction"] . "
		LIMIT " . ($row_limit*($_REQUEST["page"]-1)) . "," . $row_limit;

	$results = db_fetch_assoc($query);

	$total_rows = db_fetch_cell("SELECT
		COUNT(*)
		FROM plugin_slowlog_details AS sld
		LEFT JOIN plugin_slowlog_details_tables AS sldt
		ON sld.logid=sldt.logid AND sld.logentry=sldt.logentry
		LEFT JOIN plugin_slowlog_details_methods AS sldm
		ON sld.logid=sldm.logid AND sld.logentry=sldm.logentry
		INNER JOIN plugin_slowlog_methods AS slm
		ON sldm.methodid=slm.methodid
		$sql_where");

	/* generate page list */
	$url_page_select = str_replace("&page", "?page", get_page_list($_REQUEST["page"], MAX_DISPLAY_PAGES, $row_limit, $total_rows, "slowlog.php?action=details&logid=" . $_REQUEST["logid"]));

	slowlog_tabs();
	html_start_box("<strong>MySQL SlowLog Details</strong>", "100%", $colors["header"], "3", "center", "");
	slowlog_details_filter();
	html_end_box();

	if ($total_rows > 0) {
		$nav = "<tr bgcolor='#" . $colors["header"] . "'>
				<td colspan='9'>
					<table width='100%' cellspacing='0' cellpadding='0' border='0'>
						<tr>
							<td align='left' class='textHeaderDark'>
								<strong>&lt;&lt; "; if ($_REQUEST["page"] > 1) { $nav .= "<a class='linkOverDark' href='slowlog.php?action=details&logid=" . $_REQUEST["logid"] . "&page=" . ($_REQUEST["page"]-1) . "'>"; } $nav .= "Previous"; if ($_REQUEST["page"] > 1) { $nav .= "</a>"; } $nav .= "</strong>
							</td>\n
							<td align='center' class='textHeaderDark'>
								Showing Rows " . (($row_limit*($_REQUEST["page"]-1))+1) . " to " . ((($total_rows < $row_limit) || ($total_rows < ($row_limit*$_REQUEST["page"]))) ? $total_rows : ($row_limit*$_REQUEST["page"])) . " of $total_rows [$url_page_select]
							</td>\n
							<td align='right' class='textHeaderDark'>
								<strong>"; if (($_REQUEST["page"] * $row_limit) < $total_rows) { $nav .= "<a class='linkOverDark' href='slowlog.php?action=details&logid=" . $_REQUEST["logid"] . "&page=" . ($_REQUEST["page"]+1) . "'>"; } $nav .= "Next"; if (($_REQUEST["page"] * $row_limit) < $total_rows) { $nav .= "</a>"; } $nav .= " &gt;&gt;</strong>
							</td>\n
						</tr>
					</table>
				</td>
			</tr>\n";
	}else{
		$nav = "<tr bgcolor='#" . $colors["header"] . "' class='noprint'>
					<td colspan='22'>
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

	html_start_box("", "100%", $colors["header"], "3", "center", "");

	$display_text = array(
		"table_name" => array("Table Name", "ASC"),
		"method" => array("Method", "ASC"),
		"date" => array("Date", "ASC"),
		"user" => array("User", "ASC"),
		"host" => array("Host", "ASC"),
		"query_time" => array("Query Time", array("DESC", "right")),
		"lock_time" => array("Lock Time", array("DESC", "right")),
		"rows_sent" => array("Send", array("DESC", "right")),
		"rows_examined" => array("Examined", array("DESC", "right")));

	$jsprefix = "action=details&logid=" . $_REQUEST["logid"];

	print $nav;
	slowlog_header_sort($display_text, $_REQUEST["sort_column"], $_REQUEST["sort_direction"], $jsprefix);

	$i = 0;
	if (sizeof($results) > 0) {
		foreach ($results as $r) {
			form_alternate_row_color($colors["alternate"],$colors["light"],$i); $i++;
				?>
				<td width=200>
					<a class='linkEditMain' href='slowlog.php?action=query&logid=<?php print $r["logid"];?>&logentry=<?php print $r["logentry"];?>'><?php print (strlen($r["table_name"]) ? $r["table_name"]:"<em>others</em>");?></a>
				</td>
				<td><?php print $r["method"];?></td>
				<td><?php print $r["date"];?></td>
				<td><?php print (strlen($_REQUEST["filter"]) ? preg_replace("/(" . preg_quote($_REQUEST["filter"]) . ")/i", "<span style='background-color: #F8D93D;'>\\1</span>", $r["user"]) : $r["user"]);?></td>
				<td><?php print (strlen($_REQUEST["filter"]) ? preg_replace("/(" . preg_quote($_REQUEST["filter"]) . ")/i", "<span style='background-color: #F8D93D;'>\\1</span>", $r["host"]) : $r["host"]);?></td>
				<td align='right'><?php print number_format($r["query_time"]);?></td>
				<td align='right'><?php print number_format($r["lock_time"]);?></td>
				<td align='right'><?php print number_format($r["rows_sent"]);?></td>
				<td align='right'><?php print number_format($r["rows_examined"]);?></td>
			</tr>
			<?php
		}

		/* put the nav bar on the bottom as well */
		print $nav;
	}else{
		print "<tr><td><em>No Slowlog Records</em></td></tr>";
	}
	html_end_box(false);
}

function slowlog_view_methods() {
	global $colors, $config;

	/* ================= input validation ================= */
	input_validate_input_number($_REQUEST["logid"]);
	/* ==================================================== */

	include_once("./plugins/slowlog/lib/open-flash-chart-object.php");

	$id = $_REQUEST["logid"];

	slowlog_tabs();
	html_start_box("<strong>MySQL SlowLog Results - By Method</strong>", "100%", $colors["header"], "3", "center", "");

	echo "<tr style='background-color:#F9F9F9;'><td align='center'>";
	echo "<div id='my_count'></div>";
	echo "</td>";
	echo "<td align='center'>";
	echo "<div id='my_query'></div>";
	echo "</td></tr>";
	echo "<tr style='background-color:#F9F9F9;'><td align='center'>";
	echo "<div id='my_examined'></div>";
	echo "</td>";
	echo "<td align='center'>";
	echo "<div id='my_sent'></div>";
	echo "</td></tr>";
	echo "<script type='text/javascript'>";
	echo "swfobject.embedSWF(\"open-flash-chart.swf\", \"my_count\", \"98%\", \"275\", \"9.0.0\", \"expressInstall.swf\", {\"data-file\":\"" . urlencode($config["url_path"] . "plugins/slowlog/slowlog.php?logid=" . $id . "&action=viewmethods&type=count") . "\", \"id\":\"my_count\"} );";
	echo "swfobject.embedSWF(\"open-flash-chart.swf\", \"my_query\", \"98%\", \"275\", \"9.0.0\", \"expressInstall.swf\", {\"data-file\":\"" . urlencode($config["url_path"] . "plugins/slowlog/slowlog.php?logid=" . $id . "&action=viewmethods&type=query_time") . "\", \"id\":\"my_query\"} );";
	echo "swfobject.embedSWF(\"open-flash-chart.swf\", \"my_examined\", \"98%\", \"275\", \"9.0.0\", \"expressInstall.swf\", {\"data-file\":\"" . urlencode($config["url_path"] . "plugins/slowlog/slowlog.php?logid=" . $id . "&action=viewmethods&type=rows_examined") . "\", \"id\":\"my_examined\"} );";
	echo "swfobject.embedSWF(\"open-flash-chart.swf\", \"my_sent\", \"98%\", \"275\", \"9.0.0\", \"expressInstall.swf\", {\"data-file\":\"" . urlencode($config["url_path"] . "plugins/slowlog/slowlog.php?logid=" . $id . "&action=viewmethods&type=rows_sent") . "\", \"id\":\"my_send\"} );";
	echo "function clickme(index) {
		 document.location='slowlog.php?action=details&source=methods&logid=$id&index='+index;
		 }";
	echo "</script>";

	html_end_box(false);
}

function slowlog_view_tables() {
	global $colors, $config;

	/* ================= input validation ================= */
	input_validate_input_number($_REQUEST["logid"]);
	/* ==================================================== */

	include_once("./plugins/slowlog/lib/open-flash-chart-object.php");

	$id = $_REQUEST["logid"];

	slowlog_tabs();
	html_start_box("<strong>MySQL SlowLog Results - By Table</strong>", "100%", $colors["header"], "3", "center", "");

	echo "<tr style='background-color:#F9F9F9;'><td align='center'>";
	echo "<div id='my_count'></div>";
	echo "</td>";
	echo "<td align='center'>";
	echo "<div id='my_query'></div>";
	echo "</td></tr>";
	echo "<tr style='background-color:#F9F9F9;'><td align='center'>";
	echo "<div id='my_examined'></div>";
	echo "</td>";
	echo "<td align='center'>";
	echo "<div id='my_sent'></div>";
	echo "</td></tr>";
	echo "<script type='text/javascript'>";
	echo "swfobject.embedSWF(\"open-flash-chart.swf\", \"my_count\", \"98%\", \"275\", \"9.0.0\", \"expressInstall.swf\", {\"data-file\":\"" . urlencode($config["url_path"] . "plugins/slowlog/slowlog.php?logid=" . $id . "&action=viewtables&type=count") . "\", \"id\":\"my_count\"} );";
	echo "swfobject.embedSWF(\"open-flash-chart.swf\", \"my_query\", \"98%\", \"275\", \"9.0.0\", \"expressInstall.swf\", {\"data-file\":\"" . urlencode($config["url_path"] . "plugins/slowlog/slowlog.php?logid=" . $id . "&action=viewtables&type=query_time") . "\", \"id\":\"my_query\"} );";
	echo "swfobject.embedSWF(\"open-flash-chart.swf\", \"my_examined\", \"98%\", \"275\", \"9.0.0\", \"expressInstall.swf\", {\"data-file\":\"" . urlencode($config["url_path"] . "plugins/slowlog/slowlog.php?logid=" . $id . "&action=viewtables&type=rows_examined") . "\", \"id\":\"my_examined\"} );";
	echo "swfobject.embedSWF(\"open-flash-chart.swf\", \"my_sent\", \"98%\", \"275\", \"9.0.0\", \"expressInstall.swf\", {\"data-file\":\"" . urlencode($config["url_path"] . "plugins/slowlog/slowlog.php?logid=" . $id . "&action=viewtables&type=rows_sent") . "\", \"id\":\"my_send\"} );";
	echo "function clickme(index) {
		 document.location='slowlog.php?action=details&source=tables&logid=$id&index='+index;
		 }";
	echo "</script>";

	html_end_box(false);
}

function slowlog_get_color($as_array = false) {
	static $position = 0;
	$pallette = array("#F23C2E", "#32599A", "#F18A47", "#AC9509", "#DAAC10");

	if ($as_array) {
		$position = 0;
		return $pallette;
	}else{
		$color = $pallette[$position % sizeof($pallette)];
		$position++;
		return $color;
	}
}

function slowlog_view_query() {
	global $config, $colors;

	$query_string = "SELECT *
		FROM plugin_slowlog_details AS sld
		WHERE logid=" . $_REQUEST["logid"] . " AND logentry=" . $_REQUEST["logentry"];

	$entry = db_fetch_row($query_string);

	slowlog_tabs();
	html_start_box("<strong>MySQL SlowLog Query Details</strong>", "100%", $colors["header"], "3", "center", "");

	$i = 0;
	form_alternate_row_color($colors["alternate"],$colors["light"],$i); $i++;
	echo "<td style='font-weight:bold;' width='10%'>Date:</td><td width='15%'>"  . $entry["date"] . "</td><td style='font-weight:bold;' width='10%'>User:</td><td width='15%'>" . $entry["user"] . "</td>";
	echo "<td style='font-weight:bold;' width='10%'>Host:</td><td width='15%'>" . $entry["host"] . "</td><td style='font-weight:bold;' width='10%'>IP:</td><td width='15%'>" . $entry["ip_address"] . "</td></tr>";
	form_alternate_row_color($colors["alternate"],$colors["light"],$i); $i++;
	echo "<td style='font-weight:bold;' width='10%'>Query Time:</td><td width='15%'>" . $entry["query_time"] . "</td><td style='font-weight:bold;' width='10%'>Lock Time:</td><td width='15%'>" . $entry["lock_time"] . "</td>";
	echo "<td style='font-weight:bold;' width='10%'>Rows Sent:</td><td width='15%'>" . $entry["rows_sent"] . "</td><td style='font-weight:bold;' width='10%'>Rows Examined:</td><td width='15%'>" . $entry["rows_examined"] . "</td></tr>";
	form_alternate_row_color($colors["alternate"],$colors["light"],$i); $i++;
	echo "<td style='font-weight:bold;' width='10%'>Query</td><td colspan=7>" . $entry["query"] . "</td></tr>";

	html_end_box(false);
}

function slowlog_view() {
	global $config, $colors, $actions;

	/* clean up search string */
	if (isset($_REQUEST["filter"])) {
		$_REQUEST["filter"] = sanitize_search_string(get_request_var("filter"));
	}

	/* clean up sort_column */
	if (isset($_REQUEST["sort_column"])) {
		$_REQUEST["sort_column"] = sanitize_search_string(get_request_var("sort_column"));
	}

	/* clean up search string */
	if (isset($_REQUEST["sort_direction"])) {
		$_REQUEST["sort_direction"] = sanitize_search_string(get_request_var("sort_direction"));
	}

	load_current_session_value("page", "sess_slowlog_current_page", "1");
	load_current_session_value("filter", "sess_slowlog_filter", "");
	load_current_session_value("sort_column", "sess_slowlog_sort_column", "description");
	load_current_session_value("sort_direction", "sess_slowlog_sort_direction", "ASC");

	/* form the 'where' clause for our main sql query */
	$sql_where    = "";
	if (strlen($_REQUEST["filter"])) {
		$sql_where = (strlen($sql_where) ? " AND ": "WHERE ") . "(description LIKE '%%" . $_REQUEST["filter"] . "%%')";
	}

	$query_string = "SELECT *
		FROM plugin_slowlog
		$sql_where
		ORDER BY " . $_REQUEST["sort_column"] . " " . $_REQUEST["sort_direction"];

	$entries = db_fetch_assoc($query_string);

	slowlog_tabs();
	html_start_box("<strong>MySQL SlowLog File Filters</strong>", "100%", $colors["header"], "3", "center", "slowlog.php?action=edit");
	filter();
	html_end_box();

	html_start_box("", "100%", $colors["header"], "3", "center", "");

	$display_text = array(
		"nosort" => array("Actions", ""),
		"description" => array("Description", "ASC"),
		"import_date" => array("Imported", "DESC"),
		"import_lines" => array("Lines", "DESC"),
		"start_time" => array("Start Date", "DESC"),
		"end_time" => array("End Date", "DESC"));

	html_header_sort_checkbox($display_text, $_REQUEST["sort_column"], $_REQUEST["sort_direction"]);

	$i = 0;
	if (sizeof($entries)) {
		foreach ($entries as $entry) {
			$html = "<a href='slowlog.php?action=methods&logid=" . $entry["logid"] . "'>
				<img src='images/view_methods.gif' border='0' align='absmiddle' title='View Methods'></a>
				<a href='slowlog.php?action=tables&logid=" . $entry["logid"] . "'>
				<img src='images/view_tables.gif' border='0' align='absmiddle' title='View Tables'></a>
				<a href='slowlog.php?action=details&logid=" . $entry["logid"] . "'>
				<img src='images/view_details.gif' border='0' align='absmiddle' title='View Details'></a>";

			form_alternate_row_color($colors["alternate"],$colors["light"],$i, 'line' . $entry["logid"]); $i++;
			form_selectable_cell($html, $entry["logid"], "70");
			form_selectable_cell((strlen($_REQUEST["filter"]) ? preg_replace("/(" . preg_quote($_REQUEST["filter"]) . ")/i", "<span style='background-color: #F8D93D;'>\\1</span>", $entry["description"]) : $entry["description"]), $entry["logid"]);
			form_selectable_cell($entry["import_date"], $entry["logid"]);
			form_selectable_cell(number_format($entry["import_lines"]), $entry["logid"]);
			form_selectable_cell($entry["start_time"], $entry["logid"]);
			form_selectable_cell($entry["end_time"], $entry["logid"]);
			form_checkbox_cell($entry["description"], $entry["logid"]);
			form_end_row();
		}
	}else{
		print "<tr><td><em>No MySQL Logs</em></td></tr>";
	}

	html_end_box(false);

	draw_actions_dropdown($actions);
}

/* slowlog_save_button - draws a (save|create) and cancel button at the bottom of
     an html edit form
   @arg $force_type - if specified, will force the 'action' button to be either
     'save' or 'create'. otherwise this field should be properly auto-detected */
function slowlog_save_button($cancel_action = "", $action = "save", $force_type = "", $key_field = "id") {
	global $config;

	if (substr_count($cancel_action, ".php")) {
		$caction = $cancel_action;
		$calt = "Return";
		$sname = "save";
		$salt = "Save";
	}else{
		$caction = $_SERVER['HTTP_REFERER'];
		$calt = "Cancel";
		if ((empty($force_type)) || ($cancel_action == "return")) {
			if ($action == "import") {
				$sname = "import";
				$salt  = "Import";
			}elseif (empty($_GET[$key_field])) {
				$sname = "create";
				$salt  = "Create";
			}else{
				$sname = "save";
				$salt  = "Save";
			}

			if ($cancel_action == "return") {
				$calt   = "Return";
				$action = "save";
			}else{
				$calt   = "Cancel";
			}
		}elseif ($force_type == "save") {
			$sname = "save";
			$salt  = "Save";
		}elseif ($force_type == "create") {
			$sname = "create";
			$salt  = "Create";
		}elseif ($force_type == "import") {
			$sname = "import";
			$salt  = "Import";
		}
	}
	?>
	<table align='center' width='100%' style='background-color: #ffffff; border: 1px solid #bbbbbb;'>
		<tr>
			<td bgcolor="#f5f5f5" align="right">
				<input type='hidden' name='action' value='<?php print $action;?>'>
				<input type='button' value='<?php print $calt;?>' onClick='window.location.assign("<?php print htmlspecialchars($caction);?>")' name='cancel'>
				<input type='submit' value='<?php print $salt;?>' name='<?php print $sname;?>'>
			</td>
		</tr>
	</table>
	</form>
	<?php
}

function filter() {
	global $colors, $config;
	?>
	<script type="text/javascript">
	<!--
	function applyFilterChange(objForm) {
		strURL = '?action=select&filter=' + objForm.filter.value;
		document.location = strURL;
	}
	function clearFilter(objForm) {
		strURL = '?action=select&filter=';
		document.location = strURL;
	}
	-->
	</script>
	<tr bgcolor="<?php print $colors["panel"];?>">
		<form name="summary">
		<td>
			<table cellpadding="1" cellspacing="0">
				<tr>
					<td nowrap style='white-space: nowrap;' width="50">
						&nbsp;Search:&nbsp;
					</td>
					<td width="1">
						<input type="text" name="filter" size="40" value="<?php print $_REQUEST["filter"];?>">
					</td>
					<td nowrap style='white-space: nowrap;'>
						&nbsp;<input class="button_go" type="button" onClick='applyFilterChange(document.summary)' name="go" value="Go" alt="Go" border="0" align="absmiddle">
						<input class="button_clear" type="button" onClick='clearFilter(document.summary)' name="clear" value="Clear" alt="Clear" border="0" align="absmiddle">
					</td>
				</tr>
			</table>
		</td>
		<input type='hidden' name='page' value='1'>
		</form>
	</tr>
	<?php
}

function slowlog_details_filter() {
	global $colors, $config, $item_rows;
	?>
	<script type="text/javascript">
	<!--
	function applyFilterChange(objForm) {
		strURL = '?action=details&logid=<?php print $_REQUEST["logid"];?>&filter=' + objForm.filter.value;
		strURL = strURL + '&rows=' + objForm.rows.value;
		strURL = strURL + '&method=' + objForm.method.value;
		strURL = strURL + '&table=' + objForm.table.value;

		document.location = strURL;
	}
	function clearFilter(objForm) {
		strURL = '?action=details&logid=<?php print $_REQUEST["logid"];?>&filter=';
		strURL = strURL + '&rows=-1';
		strURL = strURL + '&method=-1';
		strURL = strURL + '&table=-1';

		document.location = strURL;
	}
	-->
	</script>
	<tr bgcolor="<?php print $colors["panel"];?>">
		<form name="details">
		<td>
			<table cellpadding="1" cellspacing="0">
				<tr>
					<td width="50">
						&nbsp;Method:
					</td>
					<td width="1">
						<select name="method" onChange="applyFilterChange(document.details)">
						<option value="-1"<?php if ($_REQUEST["method"] == "-1") {?> selected<?php }?>>Any</option>
						<?php
						$methods = db_fetch_assoc("SELECT * FROM plugin_slowlog_methods ORDER BY method");
						if (sizeof($methods)) {
						foreach ($methods as $m) {
							print '<option value="' . $m["methodid"] . '"'; if ($_REQUEST["method"] == $m["methodid"]) { print " selected"; } print ">" . $m["method"] . "</option>";
						}
						}
						?>
					</td>
					<td width="50">
						&nbsp;Tables:
					</td>
					<td width="1">
						<select name="table" onChange="applyFilterChange(document.details)">
						<option value="-1"<?php if ($_REQUEST["table"] == "-1") {?> selected<?php }?>>Any</option>
						<option value="-2"<?php if ($_REQUEST["table"] == "-2") {?> selected<?php }?>>Others</option>
						<?php
						$tables = db_fetch_assoc("SELECT DISTINCT table_name FROM plugin_slowlog_details_tables
							WHERE logid=" . $_REQUEST["logid"] . "
							ORDER BY table_name");
						if (sizeof($tables)) {
						foreach ($tables as $t) {
							print '<option value="' . $t["table_name"] . '"'; if ($_REQUEST["table"] == $t["table_name"]) { print " selected"; } print ">" . $t["table_name"] . "</option>";
						}
						}
						?>
					</td>
					<td width="40">
						&nbsp;Rows:
					</td>
					<td width="1">
						<select name="rows" onChange="applyFilterChange(document.details)">
							<option value="-1"<?php if (get_request_var_request("rows") == "-1") {?> selected<?php }?>>Default</option>
							<?php
							if (sizeof($item_rows) > 0) {
							foreach ($item_rows as $key => $value) {
								print "<option value='" . $key . "'"; if (get_request_var_request("rows") == $key) { print " selected"; } print ">" . $value . "</option>\n";
							}
							}
							?>
						</select>
					</td>
				</tr>
			</table>
			<table cellpadding="1" cellspacing="0">
				<tr>
					<td width="50">
						&nbsp;Search:&nbsp;
					</td>
					<td width="1">
						<input type="text" name="filter" size="40" value="<?php print $_REQUEST["filter"];?>">
					</td>
					<td nowrap style='white-space: nowrap;'>
						&nbsp;<input class="button_go" type="button" onClick='applyFilterChange(document.details)' name="go" value="Go" alt="Go" border="0" align="absmiddle">
						<input class="button_clear" type="button" onClick='clearFilter(document.details)' name="clear" value="Clear" alt="Clear" border="0" align="absmiddle">
					</td>
				</tr>
			</table>
		</td>
		<input type='hidden' name='page' value='1'>
		</form>
	</tr>
	<?php
}

?>
