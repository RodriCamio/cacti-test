<?php
/*
 ex: set tabstop=4 shiftwidth=4 autoindent:
 +-------------------------------------------------------------------------+
 | Copyright (C) 2011 The Cacti Group                                      |
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
 | This code is designed, written, and usersettingsained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

chdir('../../');
include_once('./include/global.php');

if (!isset($_SESSION) || !isset($_SESSION['sess_user_id']) || $_SESSION['sess_user_id'] < 1) {
	header("Location: ../../index.php\n\n");
	exit;
}

if (!isset($_REQUEST['action'])) $_REQUEST['action'] = '';

switch ($_REQUEST['action']) {
	case 'save':
		form_save();
		break;
	default:
		include_once('./include/top_header.php');
		show_user_settings();
		include_once('./include/bottom_footer.php');
		break;
}


function form_save() {
	if (isset($_POST["save_component"])) {
		/* ================= input validation ================= */
		input_validate_input_number(get_request_var_post('num_rows_graph'));
		input_validate_input_number(get_request_var_post('max_title_graph'));
		input_validate_input_number(get_request_var_post('max_data_query_field_length'));
		input_validate_input_number(get_request_var_post('default_graphs_new_dropdown'));
		input_validate_input_number(get_request_var_post('num_rows_data_query'));
		input_validate_input_number(get_request_var_post('num_rows_data_source'));
		input_validate_input_number(get_request_var_post('max_title_data_source'));
		input_validate_input_number(get_request_var_post('num_rows_device'));
		input_validate_input_number(get_request_var_post('num_rows_log'));
		input_validate_input_number(get_request_var_post('log_refresh_interval'));
		input_validate_input_number(get_request_var_post('title_size'));
		input_validate_input_number(get_request_var_post('legend_size'));
		input_validate_input_number(get_request_var_post('axis_size'));
		input_validate_input_number(get_request_var_post('unit_size'));
		/* ==================================================== */

		$save['id'] = intval($_SESSION['sess_user_id']);
		$save['num_rows_graph'] = $_POST['num_rows_graph'];
		$save['max_title_graph'] = $_POST['max_title_graph'];
		$save['max_data_query_field_length'] = $_POST['max_data_query_field_length'];
		$save['default_graphs_new_dropdown'] = $_POST['default_graphs_new_dropdown'];
		$save['num_rows_data_query'] = $_POST['num_rows_data_query'];
		$save['num_rows_data_source'] = $_POST['num_rows_data_source'];
		$save['max_title_data_source'] = $_POST['max_title_data_source'];
		$save['num_rows_device'] = $_POST['num_rows_device'];
		$save['num_rows_log'] = $_POST['num_rows_log'];
		$save['log_refresh_interval'] = $_POST['log_refresh_interval'];
		$save['title_size'] = $_POST['title_size'];
		$save['title_font'] =  trim(str_replace(array("'", '"'), '', $_POST['title_font']));
		$save['legend_size'] = $_POST['legend_size'];
		$save['legend_font'] =  trim(str_replace(array("'", '"'), '', $_POST['legend_font']));
		$save['axis_size'] = $_POST['axis_size'];
		$save['axis_font'] =  trim(str_replace(array("'", '"'), '', $_POST['axis_font']));
		$save['unit_size'] = $_POST['unit_size'];
		$save['unit_font'] =  trim(str_replace(array("'", '"'), '', $_POST['unit_font']));

		if ($save['id'] > 0) {
			if (!is_error_message()) {
				$id = sql_save($save, 'plugin_usersettings');
				if ($id) {
					raise_message(1);
				} else {
					raise_message(2);
				}
			}
		}
		header("Location: usersettings.php\n\n");
		exit;
	}
}

function show_user_settings () {
	global $colors, $item_rows, $log_tail_lines, $page_refresh_interval;
        html_start_box("<strong>User Settings</strong> ", "100%", $colors["header"], "3", "center", "");
	$id = intval($_SESSION['sess_user_id']);

	$usettings = db_fetch_row('SELECT * FROM plugin_usersettings WHERE id = ' . $id);
	if (!isset($usettings['id'])) {
		db_execute("INSERT INTO plugin_usersettings (id) VALUE ($id)");
		$usettings = db_fetch_row('SELECT * FROM plugin_usersettings WHERE id = ' . $id);
	}

	print "<form name='maint' action=usersettings.php method=post><input type='hidden' name='save' value='save'>";
	$form_array = array(
		"graphmgmt_header" => array(
			"friendly_name" => "Graph Management",
			"method" => "spacer",
			),
		"num_rows_graph" => array(
			"friendly_name" => "Rows Per Page",
			"description" => "The number of rows to display on a single page for graph management.",
			"method" => "drop_array",
			"default" => "30",
			"array" => $item_rows,
			"value" => $usettings['num_rows_graph']
			),
		"max_title_graph" => array(
			"friendly_name" => "Maximum Title Length",
			"description" => "The maximum number of characters to display for a graph title.",
			"method" => "textbox",
			"default" => "80",
			"max_length" => "10",
			"size" => "5",
			"value" => $usettings['max_title_graph']
			),
		"dataqueries_header" => array(
			"friendly_name" => "Data Queries",
			"method" => "spacer",
			),
		"max_data_query_field_length" => array(
			"friendly_name" => "Maximum Field Length",
			"description" => "The maximum number of characters to display for a data query field.",
			"method" => "textbox",
			"default" => "15",
			"max_length" => "10",
			"size" => "5",
			"value" => $usettings['max_data_query_field_length']
			),
		"graphs_new_header" => array(
			"friendly_name" => "Graph Creation",
			"method" => "spacer",
			),
		"default_graphs_new_dropdown" => array(
			"friendly_name" => "Default Dropdown Selector",
			"description" => "When creating graphs, how would you like the page to appear by default",
			"method" => "drop_array",
			"default" => "-2",
			"array" => array("-2" => "All Types", "-1" => "By Template/Data Query"),
			"value" => $usettings['default_graphs_new_dropdown']
			),
		"num_rows_data_query" => array(
			"friendly_name" => "Data Query Graph Rows",
			"description" => "The maximum number Data Query rows to place on a page per Data Query.  This applies to the 'New Graphs' page.",
			"method" => "drop_array",
			"default" => "30",
			"array" => $item_rows,
			"value" => $usettings['num_rows_data_query']
			),
		"datasources_header" => array(
			"friendly_name" => "Data Sources",
			"method" => "spacer",
			),
		"num_rows_data_source" => array(
			"friendly_name" => "Rows Per Page",
			"description" => "The number of rows to display on a single page for data sources.",
			"method" => "drop_array",
			"default" => "30",
			"array" => $item_rows,
			"value" => $usettings['num_rows_data_source']
			),
		"max_title_data_source" => array(
			"friendly_name" => "Maximum Title Length",
			"description" => "The maximum number of characters to display for a data source title.",
			"method" => "textbox",
			"default" => "45",
			"max_length" => "10",
			"size" => "5",
			"value" => $usettings['max_title_data_source']
			),
		"devices_header" => array(
			"friendly_name" => "Devices",
			"method" => "spacer",
			),
		"num_rows_device" => array(
			"friendly_name" => "Rows Per Page",
			"description" => "The number of rows to display on a single page for devices.",
			"method" => "drop_array",
			"default" => "30",
			"array" => $item_rows,
			"value" => $usettings['num_rows_device']
			),
		"logmgmt_header" => array(
			"friendly_name" => "Log Management",
			"method" => "spacer",
			),
		"num_rows_log" => array(
			"friendly_name" => "Default Log File Tail Lines",
			"description" => "How many lines of the Cacti log file to you want to tail, by default.",
			"method" => "drop_array",
			"default" => 500,
			"array" => $log_tail_lines,
			"value" => $usettings['num_rows_log']
			),
		"log_refresh_interval" => array(
			"friendly_name" => "Log File Tail Refresh",
			"description" => "How many often do you want the Cacti log display to update.",
			"method" => "drop_array",
			"default" => 60,
			"array" => $page_refresh_interval,
			"value" => $usettings['log_refresh_interval']
			),
		"fonts_header" => array(
			"friendly_name" => "Default RRDtool 1.2 Fonts",
			"method" => "spacer",
			),
		"title_size" => array(
			"friendly_name" => "Title Font Size",
			"description" => "The size of the font used for Graph Titles",
			"method" => "textbox",
			"default" => "10",
			"max_length" => "10",
			"size" => "5",
			"value" => $usettings['title_size']
			),
		"title_font" => array(
			"friendly_name" => "Title Font File",
			"description" => "The font to use for Graph Titles" . "<br/>" .
							"For RRDtool 1.2, the path to the True Type Font File." . "<br/>" .
							"For RRDtool 1.3 and above, the font name conforming to the pango naming convention:" . "<br/>" .
							'You can to use the full Pango syntax when selecting your font: The font name has the form "[FAMILY-LIST] [STYLE-OPTIONS] [SIZE]", where FAMILY-LIST is a comma separated list of families optionally terminated by a comma, STYLE_OPTIONS is a whitespace separated list of words where each WORD describes one of style, variant, weight, stretch, or gravity, and SIZE is a decimal number (size in points) or optionally followed by the unit modifier "px" for absolute size. Any one of the options may be absent.',
			"method" => "font",
			"max_length" => "100",
			"value" => $usettings['title_font']
			),
		"legend_size" => array(
			"friendly_name" => "Legend Font Size",
			"description" => "The size of the font used for Graph Legend items",
			"method" => "textbox",
			"default" => "8",
			"max_length" => "10",
			"size" => "5",
			"value" => $usettings['legend_size']
			),
		"legend_font" => array(
			"friendly_name" => "Legend Font File",
			"description" => "The font file to be used for Graph Legend items",
			"method" => "font",
			"max_length" => "100",
			"value" => $usettings['legend_font']
			),
		"axis_size" => array(
			"friendly_name" => "Axis Font Size",
			"description" => "The size of the font used for Graph Axis",
			"method" => "textbox",
			"default" => "7",
			"max_length" => "10",
			"size" => "5",
			"value" => $usettings['axis_size']
			),
		"axis_font" => array(
			"friendly_name" => "Axis Font File",
			"description" => "The font file to be used for Graph Axis items",
			"method" => "font",
			"max_length" => "100",
			"value" => $usettings['axis_font']
			),
		"unit_size" => array(
			"friendly_name" => "Unit Font Size",
			"description" => "The size of the font used for Graph Units",
			"method" => "textbox",
			"default" => "7",
			"max_length" => "10",
			"size" => "5",
			"value" => $usettings['unit_size']
			),
		"unit_font" => array(
			"friendly_name" => "Unit Font File",
			"description" => "The font file to be used for Graph Unit items",
			"method" => "font",
			"max_length" => "100",
			"value" => $usettings['unit_font']
			),
			"save_component" => array(
				"method" => "hidden",
				"value" => "1"
			)
		);
	
		draw_edit_form(
			array(
				'config' => array(
					'no_form_tag' => true
					),
				'fields' => $form_array
				)
		);
	
		html_end_box();

		form_save_button('usersettings.php', 'save');
}





