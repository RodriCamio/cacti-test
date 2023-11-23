<?php
/*
   +-------------------------------------------------------------------------+
   | Copyright (C) 2004-2012 The Cacti Group                                 |
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

include_once('./include/auth.php');
include_once($config["base_path"] . '/plugins/reportit/lib_int/funct_validate.php');
include_once($config["base_path"] . '/plugins/reportit/lib_int/funct_online.php');
include_once($config["base_path"] . '/plugins/reportit/lib_int/funct_shared.php');
include_once($config["base_path"] . '/plugins/reportit/lib_int/funct_html.php');
include_once($config["base_path"] . '/plugins/reportit/lib_int/const_runtime.php');
include_once($config["base_path"] . '/plugins/reportit/lib_int/const_rrdlist.php');

/* ======== Validation ======== */
	safeguard_xss('', true);
/* ============================ */

if (!isset($_REQUEST["action"])) {
	$_REQUEST["action"] = "";
}

switch ($_REQUEST["action"]) {
	case 'actions':
		form_actions();
		break;
	case 'save':
		form_save();
		break;
	case 'rrdlist_edit':
		include_once(CACTI_INCLUDE_PATH . "/top_header.php");
		rrdlist_edit();
		include_once(CACTI_INCLUDE_PATH . "/bottom_footer.php");
		break;
	default:
		include_once(CACTI_INCLUDE_PATH . "/top_header.php");
		standard();
		include_once(CACTI_INCLUDE_PATH . "/bottom_footer.php");
		break;
}


function form_save() {
	global $colors, $timezone, $shifttime, $shifttime2, $weekday;

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var_post('id'));
	input_validate_input_number(get_request_var_post('report_id'));
	input_validate_input_number(get_request_var_post('rrdlist_timezone'));
	input_validate_input_number(get_request_var_post('rrdlist_shifttime_start'));
	input_validate_input_number(get_request_var_post('rrdlist_shifttime_end'));
	input_validate_input_number(get_request_var_post('rrdlist_weekday_start'));
	input_validate_input_number(get_request_var_post('rrdlist_weekday_end'));

	locked(my_template(get_request_var_post('report_id')));
	/* ==================================================== */

	/* check start and end of shifttime */
	$a =  $_POST['rrdlist_shifttime_start'];
	$b = &$_POST['rrdlist_shifttime_end'];
	if($a == $b && $b == 0) $b = count($shifttime);

	/* prepare data array */
	$rrdlist_data['id']				= $_POST['id'];
	$rrdlist_data['report_id']		= $_POST['report_id'];
	$rrdlist_data['start_day']		= $weekday[$_POST['rrdlist_weekday_start']];
	$rrdlist_data['end_day']		= $weekday[$_POST['rrdlist_weekday_end']];
	$rrdlist_data['start_time']		= $shifttime[$_POST['rrdlist_shifttime_start']];
	$rrdlist_data['end_time']		= $shifttime2[$_POST['rrdlist_shifttime_end']];
	$rrdlist_data['description'] 	= mysql_real_escape_string($_POST['rrdlist_subhead']);

	if(isset($_POST['rrdlist_timezone'])) $rrdlist_data['timezone'] = $timezone[$_POST['rrdlist_timezone']];

	/* save settings */
	sql_save($rrdlist_data, "reportit_data_items", array('id', 'report_id'), false);

	/* reset report */
	reset_report($_POST['report_id']);

	/* return to list view */
	raise_message(1);
	header("Location: cc_rrdlist.php?&id={$_POST['report_id']}");
}


function standard() {

	global $colors, $config, $rrdlist_actions, $link_array;

	$subhead 	= '';
	$enable_tmz	= read_config_option('reportit_use_tmz');

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var_request('id'));
	input_validate_input_number(get_request_var_request("page"));
	/* ==================================================== */

	/* ==================== checkpoint ==================== */
	my_report($_REQUEST['id']);
	locked(my_template($_REQUEST['id']));
	$session_max_rows = get_valid_max_rows();
	/* ==================================================== */

	/* clean up search string */
	if (isset($_REQUEST["filter"])) {
		$_REQUEST["filter"] = sanitize_search_string(get_request_var_request("filter"));
	}

	/* clean up sort_column */
	if (isset($_REQUEST["sort"])) {
		$_REQUEST["sort"] = sanitize_search_string(get_request_var("sort"));
	}

	/* clean up sort_direction string */
	if (isset($_REQUEST["mode"])) {
		$_REQUEST["mode"] = sanitize_search_string(get_request_var("mode"));
	}

	/* if the user pushed the 'clear' button */
	if (isset($_REQUEST["clear_x"])) {
		kill_session_var("sess_reportit_rl_filter");
		kill_session_var("sess_reportit_rl_current_page");
		kill_session_var("sess_reportit_rl_sort");
		kill_session_var("sess_reportit_rl_mode");

		unset($_REQUEST["filter"]);
		unset($_REQUEST["page"]);
		unset($_REQUEST["sort"]);
		unset($_REQUEST["mode"]);
	}

	/* remember these search fields in session vars so we don't have to keep passing them around */
	load_current_session_value("filter", "sess_reportit_rl_filter", "");
	load_current_session_value("page", "sess_reportit_rl_current_page", "1");
	load_current_session_value("sort", "sess_reportit_rl_sort", "id");
	load_current_session_value("mode", "sess_reportit_rl_mode", "ASC");

	/* form the 'where' clause for our main sql query */
	if (strlen($_REQUEST["filter"])) {
		$affix = " WHERE b.name_cache like '%%" . $_REQUEST["filter"] . "%%' AND a.report_id = {$_REQUEST['id']}";
	}else{
		$affix = " WHERE a.report_id = {$_REQUEST['id']}";
	}

	$sql = "SELECT COUNT(a.id) FROM reportit_data_items AS a
    		LEFT JOIN data_template_data as b
			ON b.local_data_id = a.id $affix";
	$total_rows = db_fetch_cell("$sql");


	$sql = "SELECT a.*, b.name_cache FROM reportit_data_items AS a
			LEFT JOIN data_template_data AS b
			ON b.local_data_id = a.id $affix
			ORDER BY " . $_REQUEST['sort'] . " " . $_REQUEST['mode'] .
			" LIMIT " . ($session_max_rows*($_REQUEST["page"]-1)) . "," . $session_max_rows;
	$rrdlist = db_fetch_assoc($sql);
	strip_slashes($rrdlist);

	$report_data	= db_fetch_row('SELECT * FROM reportit_reports WHERE id=' . $_REQUEST['id']);
	strip_slashes($report_data);

	$header_label	= "<b>Data Items </b>[Report:
						<a style='color:yellow' href='cc_reports.php?action=report_edit&id={$_REQUEST['id']}'>
						{$report_data['description']}</a>
						] [$total_rows]";

	/* define subheader description */
	$description = array('Description', 'Subhead', 'Shifttime (from - to)', 'Weekdays (from - to)', 'Time Zone');

	if(!$enable_tmz) {
		$description = array_values($description);
		$link_array = array_values($link_array);
		unset($description[array_search('Time Zone', $description)]);
		unset($link_array[array_search('timezone', $link_array)]);
	}

	/* generate page list */
	$url_page_select = html_custom_page_list($_REQUEST["page"], MAX_DISPLAY_PAGES, $session_max_rows, $total_rows, "cc_rrdlist.php?id={$_REQUEST['id']}");

	$columns = sizeof($link_array);

	$nav = "<tr bgcolor='#6CA6CD' >
		<td colspan='" . $columns . "'>
			<table width='100%' cellspacing='0' cellpadding='0' border='0'>
				<tr>
					<td align='left'>
						<strong>&laquo; "; if ($_REQUEST["page"] > 1) { $nav .= "<a style='color:FFFF00' href='cc_rrdlist.php?id=" . "{$_REQUEST['id']}&page=" . ($_REQUEST["page"]-1) . "'>"; } $nav .= "Previous"; if ($_REQUEST["page"] > 1) { $nav .= "</a>"; } $nav .= "</strong>
					</td>\n
					<td align='center' class='textSubHeaderDark'>
						Showing Rows " . (($session_max_rows*($_REQUEST["page"]-1))+1) . " to " . ((($total_rows < $session_max_rows) || ($total_rows < ($session_max_rows*$_REQUEST["page"]))) ? $total_rows : ($session_max_rows*$_REQUEST["page"])) . " of $total_rows [$url_page_select]
					</td>\n
					<td align='right'>
						<strong>"; if (($_REQUEST["page"] * $session_max_rows) < $total_rows) { $nav .= "<a style='color:yellow' href='cc_rrdlist.php?id=" . "{$_REQUEST['id']}&page=" . ($_REQUEST["page"]+1) . "'>"; } $nav .= "Next"; if (($_REQUEST["page"] * $session_max_rows) < $total_rows) { $nav .= "</a>"; } $nav .= " &raquo;</strong>
					</td>\n
				</tr>
			</table>
		</td>
		</tr>\n";

	/* start with HTML output */
	html_start_box("$header_label", REPORTIT_WIDTH, $colors["header"], "2", "center", "cc_items.php?&id=" . $_REQUEST['id'] );
	include($config["base_path"] . '/plugins/reportit/lib_int/inc_search_field_filter_table.php');
	html_end_box();

	html_start_box("", REPORTIT_WIDTH, $colors["header"], "3", "center", "");
	print $nav;

	html_header_checkbox(html_sorted_with_arrows( array_values($description), array_values($link_array), 'cc_rrdlist.php', $_REQUEST['id']));

	$i = 0;

	if (sizeof($rrdlist) > 0) {
		foreach($rrdlist as $rrd) {
			form_alternate_row_color($colors["alternate"], $colors["light"], $i, $rrd["id"]); $i++;
                if($rrd['name_cache'] == NULL) {
                    print '<td><strong><font color="red">Does not exist anymore</font></strong></td>';
                }else {
                    ?>
                        <td>
                            <a class='linkEditMain'	href="cc_rrdlist.php?action=rrdlist_edit&id=<?php print $rrd['id'];?>&report_id=<?php print $_REQUEST['id'];?>">
                                <?php print $rrd['name_cache'];?>
                            </a>
                        </td>
					<?php
				}
						$subhead = htmlentities($rrd['description']);
						print "<td>$subhead</td>";
					?>
				<td><?php print "{$rrd['start_time']}" . " - " . "{$rrd['end_time']}";?>
				</td>
				<td><?php print "{$rrd['start_day']}" . " - " . "{$rrd['end_day']}";?>
				</td>
				<?php if($enable_tmz) print "<td>{$rrd['timezone']}</td>";?>
				<td style="<?php print get_checkbox_style();?>"
					width="1%"
					align="right"><input
					type='checkbox'
					style='margin: 0px;'
					name='chk_<?php print $rrd["id"];?>'
					title="Select">
				</td>
			</tr>
			<?php
		}
		if ($total_rows > $session_max_rows) print $nav;
	}else {
		print "<tr><td><em>No data items have been selected. Click \"Add\".</em></td></tr>\n";
	}

	/*remember report id */
	$form_array = array('id' => array('method' => 'hidden_zero', 'value' => $_REQUEST['id']));
	draw_edit_form(array('config' => array(),'fields' => $form_array));

	html_end_box(true);
	draw_custom_actions_dropdown($rrdlist_actions,'cc_reports.php');
}


function rrdlist_edit() {
	global $colors, $timezone, $shifttime, $shifttime2, $weekday;

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var('id'));
	input_validate_input_number(get_request_var('report_id'));
	/* ==================================================== */

	/* ==================== checkpoint ==================== */
	my_report($_GET['report_id']);
	locked(my_template($_GET['report_id']));
	/* ==================================================== */

	$enable_tmz = read_config_option('reportit_use_tmz');

	$sql = "SELECT a.*, b.name_cache FROM reportit_data_items AS a
			LEFT JOIN data_template_data AS b
			ON b.local_data_id=a.id
			WHERE a.id = {$_GET['id']} AND report_id = {$_GET['report_id']}";
	$rrdlist_data = db_fetch_row($sql);
	strip_slashes($rrdlist_data);

	$header_label = '[edit: ' . $rrdlist_data['name_cache'] . ']';

	/* start with HTML output */
	html_start_box("<strong>Data Object</strong> $header_label", REPORTIT_WIDTH, $colors["header"], "2", "center", "");

	$form_array = array('rrdlist_header1'		=> array(
	'friendly_name'		=> 'General',
	'method'			=> 'spacer'
	),
	'rrdlist_subhead'	=> array(
	'friendly_name'		=> 'Subhead (optional)',
	'method' 			=> "textarea",
	'textarea_rows'		=> "2",
	'textarea_cols'		=> "45",
	'default'			=> '',
	'description'		=> "Define an additional subhead that should be on display under the interface description.<br>
							Following variables will be supported (without quotes): '|t1|' '|t2|' '|tmz|' '|d1|' '|d2|'",
	'value'				=> $rrdlist_data['description'])
	);
	if($enable_tmz)		{
		$rrdlist_timezone		=  array(
		'friendly_name'		=> 'Time Zone',
		'method'			=> 'drop_array',
		'default'			=> '17',
		'description'		=> 'Select the time zone your following shifttime informations will be based on.',
		'value'				=> array_search($rrdlist_data['timezone'], $timezone),
		'array'				=> $timezone);
		$form_array['rrdlist_timezone'] = $rrdlist_timezone;
	}
	draw_edit_form(array(
		'config' => array(),
		'fields' => $form_array
		));
	html_end_box();


	html_start_box("", REPORTIT_WIDTH, $colors["header"], "2", "center", "");

	$shift_array = array(
	'rrdlist_header2'	=> array(
	'friendly_name'		=> 'Working Time',
	'method'			=> 'spacer',
	),
	'id'				=> array(
	'method'			=> 'hidden_zero',
	'value'				=> $_GET['id']
	),
	'report_id'			=> array(
	'method'			=> 'hidden_zero',
	'value'				=> $_GET['report_id']
	),
	'rrdlist_shifttime_start'	=> array(
	'friendly_name'		=> 'From',
	'method'			=> 'drop_array',
	'default'			=> '0',
	'description'		=> 'The startpoint of duration you want to analyse',
	'value'				=> array_search($rrdlist_data['start_time'], $shifttime),
	'array'				=> $shifttime
	),
	'rrdlist_shifttime_end'		=> array(
	'friendly_name'		=> 'To',
	'method'			=> 'drop_array',
	'default'			=> '287',
	'description'		=> 'The end of analysing time.',
	'value'				=> array_search($rrdlist_data['end_time'], $shifttime2),
	'array'				=> $shifttime2
	));
	draw_edit_form(array(
	'config' => array(),
	'fields' => $shift_array
	));
	html_end_box();


	html_start_box('', REPORTIT_WIDTH, $colors["header"], "2", "center", "");

	$weekday_array = array(
	'rrdlist_header3'	=> array(
	'friendly_name'		=> 'Working Days',
	'method'			=> 'spacer',
	),
	'rrdlist_weekday_start'		=> array(
	'friendly_name'		=> 'From',
	'method'			=> 'drop_array',
	'description'		=> 'Define the band of days where shift STARTS!',
	'value'				=> array_search($rrdlist_data['start_day'], $weekday),
	'array'				=> $weekday
	),
	'rrdlist_weekday_end'		=> array(
	'friendly_name'		=> 'To',
	'method'			=> 'drop_array',
	'description'		=> 'Example: For a nightshift from Mo(22:30) till Sat(06:30) define Monday to Friday',
	'value'				=> array_search($rrdlist_data['end_day'], $weekday),
	'array'				=> $weekday
	));
	draw_edit_form(array(
	'config' => array(),
	'fields' => $weekday_array
	));
	html_end_box();

	form_save_button('cc_rrdlist.php?&id='. $_GET['report_id']);
}


function form_actions() {
	global $colors, $rrdlist_actions, $config;

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var_post('drp_action'));
	/* ==================================================== */

	if (isset($_POST['selected_items'])) {
		$selected_items = unserialize(stripslashes($_POST['selected_items']));

		if ($_POST['drp_action'] == '1') { // Remove RRD from RRD table

			$sql = "SELECT id FROM reportit_data_items WHERE report_id = {$_POST['id']} AND " . array_to_sql_or($selected_items, 'id');
			$rrdlist_datas = db_fetch_assoc($sql);

			if (sizeof($rrdlist_datas) > 0) {
				foreach ($rrdlist_datas as $rrdlist_data) {
					db_execute("DELETE FROM reportit_data_items WHERE report_id = {$_POST['id']} AND id = {$rrdlist_data['id']}");
					//Reset report
					reset_report($_POST['id']);
				}
			}
		}elseif ($_POST['drp_action'] == '2') { //Copy RRD's reference settings to all other RRDs
			$reference_items = unserialize(stripslashes($_POST['reference_items']));
			$sql = "UPDATE reportit_data_items SET
					 start_day  = '{$reference_items[0]['start_day'] }',
					 end_day    = '{$reference_items[0]['end_day']   }',
					 start_time = '{$reference_items[0]['start_time']}',
					 end_time   = '{$reference_items[0]['end_time']  }',
					 timezone   = '{$reference_items[0]['timezone']  }'
					 WHERE report_id = {$_POST['id']}";

			db_execute($sql);

			//Reset report
			reset_report($_POST['id']);
		}
		header("Location: cc_rrdlist.php?id={$_POST['id']}");
		exit;
	}

	//Set preconditions
	$ds_list = ''; $i = 0;

	foreach($_POST as $key => $value) {

		if(strstr($key, 'chk_')) {
			//Fetch rrd id
			$id = substr($key, 4);
			$rrd_ids[] = $id;
			// ================= input validation =================
			input_validate_input_number($id);
			// ====================================================

            //Fetch rrd description
            $rrd_description     = db_fetch_cell("SELECT b.name_cache FROM reportit_data_items AS a
                                                    LEFT JOIN
                                                    data_template_data AS b
                                                    ON b.local_data_id = a.id
                                                    WHERE a.id=$id AND a.report_id = {$_POST['id']}");
			$ds_list[] = $rrd_description;
		}
	}

	include_once(CACTI_INCLUDE_PATH . '/top_header.php');
	html_start_box("<strong>" . $rrdlist_actions{$_POST["drp_action"]} . "</strong>", "60%", $colors["header_panel"], "2", "center", "");
	print "<form action='cc_rrdlist.php' method='post'>\n";

	if ($_POST['drp_action'] == '1') { //DELETE REPORT
		print "<tr>
					<td bgcolor='#" . $colors["form_alternate1"]. "'>
						<p>Are you sure you want to remove the following data items from the selection table?</p>";
		if(is_array($ds_list)) {
			print	"<p>List of selected data items:<br>";
			foreach($ds_list as $key => $value) {
				print	"&#160 |_Data Item : $value<br>";
			}
		}
		print "		</td>
				</tr>";

	}elseif ($_POST['drp_action'] == '2') { // Copy the settings from selected RRD to all
		//Select the first selected checkbox as reference. The others will be ignored.
		//Fetch first's settings

		if(isset($rrd_ids[0])) {

			$rrd_settings = db_fetch_assoc("SELECT b.name_cache, a.* FROM reportit_data_items AS a
											 LEFT JOIN data_template_data as b
											 ON b.local_data_id = a.id
											 WHERE a.id= {$rrd_ids[0]} AND a.report_id = {$_POST['id']}");
			print "<tr>
						<td bgcolor='#" . $colors['form_alternate1']. "'>
							<p>When you click \"yes\", the following settings will be copied to all the other data items of your selection table.</p>";
			print			"Selected data item as reference:";
			print			"<b><br>&#160 {$rrd_settings[0]['name_cache']}</b><p></p>";
			print			"Time Zone:<br>&#160 <b>{$rrd_settings[0]['timezone']}</b><p></p>";
			print			"Weekdays:<br>&#160 <b>{$rrd_settings[0]['start_day']} - {$rrd_settings[0]['end_day']}</b><p></p>";
			print			"Shifttime:<br>&#160 <b>{$rrd_settings[0]['start_time']} - {$rrd_settings[0]['end_time']}</b><p></p>";
			print		"</td>
				</tr>";
		}
	}

	if (!is_array($ds_list)) {
		print "<tr><td bgcolor='#" . $colors['form_alternate1']. "'><span class='textError'>You must select at least one report.</span></td></tr>\n";
		$save_html = "<input type='button' value='Cancel' onClick='window.history.back()'>";
	}else {
		$save_html = "<input type='button' value='Cancel' onClick='window.history.back()'>&nbsp;<input type='submit' value='Continue'>";
	}

	print "<tr>
				<td align='right' bgcolor='#eaeaea'>
					<input type='hidden' name='id' value='" . $_POST['id'] . "'>
					<input type='hidden' name='action' value='actions'>
					<input type='hidden' name='selected_items' value='" . (isset($rrd_ids) ? serialize($rrd_ids) : '') . "'>
					<input type='hidden' name='reference_items' value='" . (isset($rrd_settings) ? serialize($rrd_settings) : '') . "'>
					<input type='hidden' name='drp_action' value='" . $_POST["drp_action"] . "'>
					$save_html
				</td>
		</tr>";

	html_end_box();
	include_once($config["base_path"] . '/include/bottom_footer.php');}
	?>