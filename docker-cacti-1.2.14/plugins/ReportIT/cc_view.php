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

$guest_account = true;

include_once('./include/auth.php');
include_once(REPORTIT_BASE_PATH . '/lib_int/funct_validate.php');
include_once(REPORTIT_BASE_PATH . '/lib_int/funct_shared.php');
include_once(REPORTIT_BASE_PATH . '/lib_int/funct_html.php');
include_once(REPORTIT_BASE_PATH . '/lib_int/funct_online.php');
include_once(REPORTIT_BASE_PATH . '/lib_int/funct_export.php');
include_once(REPORTIT_BASE_PATH . '/lib_int/const_view.php');

/* ======== Validation ======== */
		safeguard_xss();
/* ============================ */
if (!isset($_REQUEST["action"])) {
	$_REQUEST["action"] = "";
}

switch ($_REQUEST["action"]) {
	case 'show_report':
		inc_top_header();
		show_report();
		include_once(CACTI_INCLUDE_PATH . "/bottom_footer.php");
		break;
	case 'show_graphs':
		inc_top_header();
		show_graphs();
		include_once(CACTI_INCLUDE_PATH . "/bottom_footer.php");
		break;
	case 'show_graph_overview':
		show_graph_overview();
		break;
	case 'export':
		inc_top_header();
		show_export_wizard(true);
		include_once(CACTI_INCLUDE_PATH . "/bottom_footer.php");
		break;
	case 'actions':
		export();
		break;
	default:
		inc_top_header();
		standard();
		include_once(CACTI_INCLUDE_PATH . "/bottom_footer.php");
		break;
}


function export() {
	global $config, $export_formats;

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var_request("id"));
	input_validate_input_number(get_request_var_request("page"));
	input_validate_input_number(get_request_var_request("data_source"));
	input_validate_input_number(get_request_var_request("measurand"));
	input_validate_input_number(get_request_var_request("limit"));
	input_validate_input_number(get_request_var_request("archive"));
	input_validate_input_number(get_request_var("subhead"));
	input_validate_input_number(get_request_var("summary"));
	input_validate_input_key(get_request_var_request('drp_action'), $export_formats);
	/* ==================================================== */

	/* clean up search string */
	if (isset($_REQUEST["filter"])) {
		$_REQUEST["filter"] = sanitize_search_string(get_request_var_request("filter"));
	}

	/* clean up sort_column */
	if (isset($_REQUEST["sort"])) {
		$_REQUEST["sort"] = sanitize_search_string(get_request_var_request("sort"));
	}

	/* clean up sort_direction string */
	if (isset($_REQUEST["mode"])) {
		$_REQUEST["mode"] = sanitize_search_string(get_request_var_request("mode"));
	}

	/* remember these search fields in session vars so we don't have to keep passing them around */
	$id = (read_graph_config_option('reportit_view_filter') == 'on') ? $_REQUEST['id'] : '';
	load_current_session_value("page", "sess_reportit_show_{$id}_current_page", "1");
	load_current_session_value("sort", "sess_reportit_show_{$id}_sort", "a.id");
	load_current_session_value("mode", "sess_reportit_show_{$id}_mode", "ASC");
	load_current_session_value("data_source", "sess_reportit_show_{$id}_data_source", "-1");
	load_current_session_value("measurand", "sess_reportit_show_{$id}_measurand", "-1");
	load_current_session_value("filter", "sess_reportit_show_{$id}_filter", "");
	load_current_session_value("info", "sess_reportit_show_{$id}_info", "-2");
	load_current_session_value("limit", "sess_reportit_show_{$id}_limit", "0");
	load_current_session_value("archive", "sess_reportit_show_{$id}_archive", "-1");
	load_current_session_value("subhead", "sess_reportit_show_{$id}_subhead", "0");
	load_current_session_value("summary", "sess_reportit_show_{$id}_summary", "0");

	/* form the 'where' clause for our main sql query */
	$table = ($_REQUEST['archive'] != -1)? 'a' : 'c';
	$affix = 	"WHERE {$table}.name_cache LIKE '%%{$_REQUEST["filter"]}%%'".
				" ORDER BY " . $_REQUEST['sort'] . " " . $_REQUEST['mode'];

	/* limit the number of rows */
	$limitation = $_REQUEST['limit']*(-5);
	if($limitation > 0 ) $affix .=" LIMIT 0," . $limitation;

	/* get informations about the archive if it exists */
	$archive = info_xml_archive($_REQUEST['id']);

	/* load report archive and fill up report cache if requested*/
	if($_REQUEST['archive'] != -1) {
		cache_xml_file($_REQUEST['id'], $_REQUEST['archive']);
		$cache_id = $_REQUEST['id'] . '_' . $_REQUEST['archive'];
	}

	/* load report data */
	$data = ($_REQUEST['archive'] == -1)
		? get_prepared_report_data($_REQUEST['id'],'export', $affix)
		: get_prepared_archive_data($cache_id, 'export', $affix);

	/* call export function */
	$export_function = "export_to_" . $_REQUEST['drp_action'];
	$output	= $export_function($data);

	$content_type = strtolower($_REQUEST['drp_action']);
	if ($_REQUEST['drp_action'] == 'SML') {
		$_REQUEST['drp_action'] = 'xml';
		$content_type = 'vnd.ms-excel';
	}

	/* create filename */
	$filename = str_replace("<report_id>", $_REQUEST['id'], read_config_option('reportit_exp_filename') . ".{$_REQUEST['drp_action']}");
	$filename = strtolower($filename);

	/* configure data header */
	header("Cache-Control: public");
	header("Content-Description: File Transfer");
	header("Content-Type: application/$content_type");
	header("Content-Disposition: attachment; filename=\"" . $filename . "\"");
	print $output;
}


function standard() {
	global $colors, $config, $link_array;

	$myId	= my_id();
	$tmz	= (read_config_option('reportit_show_tmz') == 'on') ? '('.date('T').')' : '';
	$affix	= ' WHERE a.last_run!=0';

	/* ================= input validation ================= */
		input_validate_input_number(get_request_var_request("type"));
		input_validate_input_number(get_request_var_request("page"));
	/* ==================================================== */

	/* ===================== checkpoint =================== */
		$session_max_rows = get_valid_max_rows();
	/* ==================================================== */

	/* clean up search string */
	if (isset($_REQUEST["filter"])) {
		$_REQUEST["filter"] = sanitize_search_string(get_request_var_request("filter"));
	}

	/* clean up sort_column */
	if (isset($_REQUEST["sort"])) {
		$_REQUEST["sort"] = sanitize_search_string(get_request_var_request("sort"));
	}

	/* clean up sort_direction string */
	if (isset($_REQUEST["mode"])) {
		$_REQUEST["mode"] = sanitize_search_string(get_request_var_request("mode"));
	}

	/* if the user pushed the 'clear' button */
	if (isset($_REQUEST["clear_x"])) {
		kill_session_var("sess_reportit_view_filter");
		kill_session_var("sess_reportit_view_current_page");
		kill_session_var("sess_reportit_view_sort");
		kill_session_var("sess_reportit_view_mode");
		kill_session_var("sess_reportit_view_type");

		unset($_REQUEST["filter"]);
		unset($_REQUEST["page"]);
		unset($_REQUEST["sort"]);
		unset($_REQUEST["mode"]);
		unset($_REQUEST["type"]);
	}

	/* remember these search fields in session vars so we don't have to keep passing them around */
	load_current_session_value("filter", "sess_reportit_view_filter", "");
	load_current_session_value("page", "sess_reportit_view_current_page", "1");
	load_current_session_value("sort", "sess_reportit_view_sort", "id");
	load_current_session_value("mode", "sess_reportit_view_mode", "ASC");
	load_current_session_value("type", "sess_reportit_view_type", "0");


	/* form the 'where' clause for our main sql query */
	if (strlen($_REQUEST["filter"])) {
		$affix .= " AND a.description like '%%" . $_REQUEST["filter"] . "%%'";
	}else{
		$affix .= "";
	}

	if ($_REQUEST["type"] == "-1") {
		/* show all public reports */
		$affix .= " AND a.public=1";
	}elseif ($_REQUEST["type"] == "0") {
		/* show only user's reports */
		$affix .= " AND a.user_id=$myId";
	}

	$sql = "SELECT COUNT(a.id) FROM reportit_reports as a $affix";
	$total_rows = db_fetch_cell("$sql");

	$sql = "SELECT a.*, b.description AS template_description
			FROM reportit_reports AS a
			INNER JOIN reportit_templates AS b
			ON b.id = a.template_id $affix
			ORDER BY " . $_REQUEST['sort'] . " " . $_REQUEST['mode'] .
			" LIMIT " . ($session_max_rows*($_REQUEST["page"]-1)) . "," . $session_max_rows;

	$report_list 	= db_fetch_assoc($sql);
	strip_slashes($report_list);

	/* generate page list */
	$url_page_select = html_custom_page_list($_REQUEST["page"], MAX_DISPLAY_PAGES, $session_max_rows, $total_rows, "cc_view.php?");

	$nav = "<tr bgcolor='#6CA6CD' >
		<td colspan='6'>
			<table width='100%' cellspacing='0' cellpadding='0' border='0'>
				<tr>
					<td align='left'>
						<strong>&laquo; "; if ($_REQUEST["page"] > 1) { $nav .= "<a style='color:FFFF00' href='cc_view.php?page=" . ($_REQUEST["page"]-1) . "'>"; } $nav .= "Previous"; if ($_REQUEST["page"] > 1) { $nav .= "</a>"; } $nav .= "</strong>
					</td>\n
					<td align='center' class='textSubHeaderDark'>
						Showing Rows " . (($session_max_rows*($_REQUEST["page"]-1))+1) . " to " . ((($total_rows < $session_max_rows) || ($total_rows < ($session_max_rows*$_REQUEST["page"]))) ? $total_rows : ($session_max_rows*$_REQUEST["page"])) . " of $total_rows [$url_page_select]
					</td>\n
					<td align='right'>
						<strong>"; if (($_REQUEST["page"] * $session_max_rows) < $total_rows) { $nav .= "<a style='color:yellow' href='cc_view.php?page=" . ($_REQUEST["page"]+1) . "'>"; } $nav .= "Next"; if (($_REQUEST["page"] * $session_max_rows) < $total_rows) { $nav .= "</a>"; } $nav .= " &raquo;</strong>
					</td>\n
				</tr>
			</table>
		</td>
		</tr>\n";

	/* start with HTML output */
	html_start_box("<b>Reports</b> [$total_rows]", REPORTIT_WIDTH, $colors["header"], "2", "center", "");

	include(REPORTIT_BASE_PATH . '/lib_int/inc_report_view_filter_table.php');
	html_end_box();

	html_start_box("", REPORTIT_WIDTH, $colors["header"], "3", "center", "");
	print $nav;

	$desc_array = array('Description', 'Owner', 'Template', "Period $tmz from - to", "Last run $tmz / Runtime [s]");
	html_header(html_sorted_with_arrows( $desc_array, $link_array, 'cc_view.php'));

	/* check version of Cacti -> necessary to support 0.8.6k and lower versions as well*/
	$new_version = check_cacti_version(14);

	$i = 0;

	// Build report list
	if (sizeof($report_list) > 0) {
		foreach($report_list as $report) {
			if ($new_version) form_alternate_row_color($colors["alternate"], $colors["light"], $i, $report["id"]);
			else form_alternate_row_color($colors["alternate"], $colors["light"], $i); $i++;
			?>
			<td>
				<a class='linkEditMain' href="cc_view.php?action=show_report&id=<?php print $report["id"];?>">
					<?php print $report["description"];?>
				</a>
			</td>
			<td>
			<?php
				$ownerId = $report['user_id'];
				print other_name($ownerId);
			?>
			</td>
			<td>
			<?php print $report['template_description'];?>
			</td>
			<td>
			<?php print (date(config_date_format(), strtotime($report['start_date'])) . " - " . date(config_date_format(), strtotime($report['end_date'])));?>
			</td>
			<td>
			<?php
				list($date, $time) = explode(' ', $report['last_run']);
				print (date(config_date_format(), strtotime($date)) . '&nbsp;' . $time . '&nbsp;&nbsp;/&nbsp;' . $report['runtime']);
			?>
			</td>
			</tr>
			<?php
		}
		if ($total_rows > $session_max_rows) print $nav;
	}else {
		print "<tr><td><em>No reports</em></td></tr>\n";
	}

	html_end_box();
}



function show_report() {
	global $colors, $config, $search, $t_limit, $add_info, $export_formats;

	$columns		= 1;
	$limitation		= 0;
	$num_of_sets	= 0;
	$affix			= '';
	$subhead		= '';
	$include_mea	= '';
	$cache_id		= '';
	$table			= '';
	$measurands		= array();
	$ds_description	= array();
	$report_summary	= array();
	$archive		= array();
	$additional		= array();
	$report_ds_alias= array();

	/* ================= Input validation ================= */
		input_validate_input_number(get_request_var_request("id"));
		input_validate_input_number(get_request_var_request("page"));
		input_validate_input_number(get_request_var_request("data_source"));
		input_validate_input_number(get_request_var_request("measurand"));
		input_validate_input_number(get_request_var_request("limit"));
		input_validate_input_number(get_request_var_request("archive"));
		input_validate_input_number(get_request_var("subhead"));
		input_validate_input_number(get_request_var("summary"));
	/* ==================================================== */

	/* ==================== checkpoint ==================== */
		my_report(get_request_var('id'), TRUE);
		$session_max_rows = get_valid_max_rows();
	/* ==================================================== */

	/* clean up search string */
	if (isset($_REQUEST["filter"])) {
		$_REQUEST["filter"] = sanitize_search_string(get_request_var_request("filter"));
	}

	/* clean up sort_column */
	if (isset($_REQUEST["sort"])) {
		$_REQUEST["sort"] = sanitize_search_string(get_request_var_request("sort"));
	}

	/* clean up sort_direction string */
	if (isset($_REQUEST["mode"])) {
		$_REQUEST["mode"] = sanitize_search_string(get_request_var_request("mode"));
	}

	/* if the user pushed the 'clear' button */
	$id = (read_graph_config_option('reportit_view_filter') == 'on') ? $_REQUEST['id'] : '';

	if (isset($_REQUEST["clear_x"])) {
		kill_session_var("sess_reportit_show_{$id}_current_page");
		kill_session_var("sess_reportit_show_{$id}_sort");
		kill_session_var("sess_reportit_show_{$id}_mode");
		kill_session_var("sess_reportit_show_{$id}_data_source");
		kill_session_var("sess_reportit_show_{$id}_measurand");
		kill_session_var("sess_reportit_show_{$id}_filter");
		kill_session_var("sess_reportit_show_{$id}_info");
		kill_session_var("sess_reportit_show_{$id}_limit");
		kill_session_var("sess_reportit_show_{$id}_archive");
		kill_session_var("sess_reportit_show_{$id}_subhead");
		kill_session_var("sess_reportit_show_{$id}_summary");

		unset($_REQUEST["page"]);
		unset($_REQUEST["sort"]);
		unset($_REQUEST["mode"]);
		unset($_REQUEST["data_source"]);
		unset($_REQUEST["measurand"]);
		unset($_REQUEST["filter"]);
		unset($_REQUEST["info"]);
		unset($_REQUEST["limit"]);
		unset($_REQUEST["archive"]);
		unset($_REQUEST["subhead"]);
		unset($_REQUEST["summary"]);
	}

	/* remember these search fields in session vars so we don't have to keep passing them around */
	load_current_session_value("page", "sess_reportit_show_{$id}_current_page", "1");
	load_current_session_value("sort", "sess_reportit_show_{$id}_sort", "a.id");
	load_current_session_value("mode", "sess_reportit_show_{$id}_mode", "ASC");
	load_current_session_value("data_source", "sess_reportit_show_{$id}_data_source", "-1");
	load_current_session_value("measurand", "sess_reportit_show_{$id}_measurand", "-1");
	load_current_session_value("filter", "sess_reportit_show_{$id}_filter", "");
	load_current_session_value("info", "sess_reportit_show_{$id}_info", "-2");
	load_current_session_value("limit", "sess_reportit_show_{$id}_limit", "0");
	load_current_session_value("archive", "sess_reportit_show_{$id}_archive", "-1");
	load_current_session_value("subhead", "sess_reportit_show_{$id}_subhead", "0");
	load_current_session_value("summary", "sess_reportit_show_{$id}_summary", "0");

	/* set up max number of rows */
	$num_of_rows = $session_max_rows;
	if($_REQUEST['subhead']) $num_of_rows = floor(0.5*$num_of_rows);
	if($_REQUEST['summary']) $num_of_rows -= 4;
	if($num_of_rows <= 10) 	 $num_of_rows = 10;

	/* form the 'where' clause for our main sql query */
	$table = ($_REQUEST['archive'] != -1)? 'a' : 'c';
	$affix = 	"WHERE {$table}.name_cache LIKE '%%{$_REQUEST["filter"]}%%'".
				" ORDER BY " . $_REQUEST['sort'] . " " . $_REQUEST['mode'];

	$limitation = $_REQUEST['limit']*(-5);
	if($limitation > 0 & $limitation < $num_of_rows) {
		$num_of_sets = $limitation;
		$end		 = $limitation;
	}else{
		$num_of_sets = $end = $num_of_rows;
		if($limitation > 0 & $num_of_sets*($_REQUEST["page"]-1)+$end > $limitation)
			$end -= - $limitation + $num_of_sets*($_REQUEST["page"]-1)+$end;
	}
	$affix .=" LIMIT " . ($num_of_sets*($_REQUEST["page"]-1)) . "," . $end;


	/* get informations about the archive if it exists */
	$archive = info_xml_archive($_REQUEST['id']);

	/* load report archive and fill up report cache if requested*/
	if($_REQUEST['archive'] != -1) {
		cache_xml_file($_REQUEST['id'], $_REQUEST['archive']);
		$cache_id = $_REQUEST['id'] . '_' . $_REQUEST['archive'];
	}

	/* load report data */
	$data = ($_REQUEST['archive'] == -1)
          ? get_prepared_report_data($_REQUEST['id'],'view', $affix)
          : get_prepared_archive_data($cache_id, 'view', $affix);

	/* get total number of rows (data items) */
	$source = ($_REQUEST['archive'] != -1)
				? 'reportit_tmp_' . $_REQUEST['id'] . '_' . $_REQUEST['archive'] . ' AS a'
				: 'reportit_results_' . $_REQUEST['id'] . ' AS a'.
				  ' INNER JOIN data_template_data AS c'.
				  ' ON c.local_data_id = a.id';
	$sql = 	"SELECT COUNT(a.id) FROM $source".
			" WHERE {$table}.name_cache LIKE '%%{$_REQUEST["filter"]}%%'";

	$total_rows = db_fetch_cell($sql);
	if($total_rows > $limitation && $limitation > 0) $total_rows = $limitation;

	$report_ds_alias = $data['report_ds_alias'];
	$report_data	= $data['report_data'];
	$report_results	= $data['report_results'];
	$mea			= $data['report_measurands'];
	$report_header	= $report_data['description'];

	/* create a report summary */
	if ($_REQUEST['summary']) {
		$report_summary[1]['Title'] = $report_data['description'];
		$report_summary[1]['Runtime'] = $report_data['runtime'] . 's';
		$report_summary[2]['Owner'] = $report_data['owner'];
		$report_summary[2]['Sliding Time Frame'] = ($report_data['sliding'] == 0) ? 'disabled' : 'enabled (' . strtolower($report_data['preset_timespan']) .')';
		$report_summary[3]['Last Run'] = $report_data['last_run'];
		$report_summary[3]['Scheduler'] = ($report_data['scheduled'] == 0) ? 'disabled' : 'enabled (' . $report_data['frequency'] . ')';
		$report_summary[4]['Period'] = $report_data['start_date'] . " - " . $report_data['end_date'];
		$report_summary[4]['Auto Generated RRD list'] = ($report_data['autorrdlist'] == 0)? 'disabled' : 'enabled';
	}

	/* extract result description */
	list($rs_description, $count_rs) = explode('-', $report_data['rs_def']);
	$rs_description = ($rs_description == '') ? FALSE : explode('|', $rs_description);
	if($rs_description !== FALSE) {
		foreach($rs_description as $key => $id) {
			if(!isset($data['report_measurands'][$id]['visible']) || $data['report_measurands'][$id]['visible'] == 0) {
				$count_rs--;
				unset($rs_description[$key]);
			}else {
				if($_REQUEST['data_source'] != -2)
					$measurands[$id] = $mea[$id]['abbreviation'];
			}
		}
		if($_REQUEST['measurand'] != -1) {
			if (in_array($_REQUEST['measurand'], $rs_description)) {
				$rs_description = array($_REQUEST['measurand']);
				$count_rs = 1;
				$count_ov = 0;
			}
		}
	}

	/* extract 'Overall' description */
	if (!isset($count_ov)) {
		list($ov_description, $count_ov) = explode('-', $report_data['sp_def']);
		$ov_description 	= ($ov_description == '') ? FALSE : explode('|', $ov_description);
		if($ov_description !== FALSE) {
			foreach($ov_description as $key => $id) {
				if(!isset($data['report_measurands'][$id]['visible']) || $data['report_measurands'][$id]['visible'] == 0) {
					$count_ov--;
					unset($ov_description[$key]);
				}else {
					If($_REQUEST['data_source'] == -1 || $_REQUEST['data_source'] == -2)
						$measurands[$id] = $mea[$id]['abbreviation'];
				}
			}
			if($_REQUEST['measurand'] != -1) {
				if (in_array($_REQUEST['measurand'], $ov_description)) {
					$ov_description = array($_REQUEST['measurand']);
					$count_ov = 1;
					$count_rs = 0;
				}
			}
		}
	}

	/* extract datasource description */
	if($count_rs > 0) {
		$ds_description 	= explode('|', $report_data['ds_description']);
		$columns += sizeof($ds_description)*$count_rs;
	}
	if($count_ov > 0) {
		$ds_description[-2] = 'overall';
		$columns += $count_ov;
	}

	/* save all data source names for the drop down menue.
	if available use the data source alias instead of the internal names */
	$data_sources = $ds_description;
	foreach($data_sources as $key => $value) {
		if(is_array($report_ds_alias) && array_key_exists($value, $report_ds_alias) && $report_ds_alias[$value] != '')
			$data_sources[$key] = $report_ds_alias[$value];
	}

	/* filter by data source */
	if ($_REQUEST['data_source'] != -1) {
		$ds_description = array($ds_description[$_REQUEST['data_source']]);
	}

	/* generate page list */
	$url_page_select = html_custom_page_list($_REQUEST["page"], MAX_DISPLAY_PAGES, $num_of_rows, $total_rows, "cc_view.php?action=show_report&id={$_REQUEST['id']}");
	$nav = "<tr bgcolor='#6CA6CD' >
		<td colspan='" . $columns . "'>
			<table width='100%' cellspacing='0' cellpadding='0' border='0'>
				<tr>
					<td align='left'>
						<strong>&laquo; "; if ($_REQUEST["page"] > 1) { $nav .= "<a style='color:FFFF00' href='cc_view.php?action=show_report&id=" . $_REQUEST['id'] . "&page=" . ($_REQUEST["page"]-1) . "'>"; } $nav .= "Previous"; if ($_REQUEST["page"] > 1) { $nav .= "</a>"; } $nav .= "</strong>
					</td>\n
					<td align='center' class='textSubHeaderDark'>
						Showing Rows " . (($num_of_rows*($_REQUEST["page"]-1))+1) . " to " . ((($total_rows < $num_of_rows) || ($total_rows < ($num_of_rows*$_REQUEST["page"]))) ? $total_rows : ($num_of_rows*$_REQUEST["page"])) . " of $total_rows [$url_page_select]
					</td>\n
					<td align='right'>
						<strong>"; if (($_REQUEST["page"] * $num_of_rows) < $total_rows) { $nav .= "<a style='color:yellow' href='cc_view.php?action=show_report&id=" . $_REQUEST['id'] . "&page=" . ($_REQUEST["page"]+1) . "'>"; } $nav .= "Next"; if (($_REQUEST["page"] * $num_of_rows) < $total_rows) { $nav .= "</a>"; } $nav .= " &raquo;</strong>
					</td>\n
				</tr>
			</table>
		</td>
		</tr>\n";

	/* graph view */
	$link = (read_config_option('reportit_graph') == 'on')? "./cc_view.php?action=show_graphs&id={$_REQUEST['id']}" : "";

	/* start HTML output */
	ob_start();
	html_custom_header_box($report_header, false, $link, "<img src='./images/bar.gif' alt='Graph View' border='0' align='top'>");
	include(REPORTIT_BASE_PATH . '/lib_int/inc_report_table_filter_table.php');
	html_end_box();

	if($_REQUEST['summary']) {
		html_graph_start_box(1, false);
		foreach($report_summary as $array) {
			echo "<tr>";
			foreach($array as $key => $value) {
				echo "<td><b>$key:</b></td></td><td align='left'>$value</td>";
			}
			echo "</tr>";
		}
		html_graph_end_box();
		echo "<br>";
	}

	html_report_start_box();

	print $nav;
	echo "<tr><td bgcolor='#E5E5E5'></td>";

	foreach($ds_description as $description) {
		$counter = ($description != 'overall') ? $count_rs : $count_ov;
		if(is_array($report_ds_alias) && array_key_exists($description, $report_ds_alias) && $report_ds_alias[$description] != '') {
				$description = $report_ds_alias[$description];
		}
		print "<th colspan='$counter' height='10' bgcolor='#E5E5E5'>$description</th>";
	}

	print "</tr><tr>
			<td class='textSubHeaderDark' align='left' valign='top'>
			<b><a class='textSubHeaderDark' href='cc_view.php?action=show_report&id={$_GET['id']}&sort=name_cache&mode="
                                . (($_REQUEST['sort'] == 'name_cache' && $_REQUEST['mode'] == 'ASC')
                                    ? "DESC'>"
                                    : "ASC'>") . "Data Description</a></b>
			<a title='ascending' href='cc_view.php?action=show_report&id={$_GET['id']}&sort=name_cache&mode=ASC'>"
			. (($_REQUEST['sort'] == 'name_cache' && $_REQUEST['mode'] == 'ASC')
                    ? "<img src='./images/red_arrow_up.gif' alt='ASC' border='0' align='absmiddle' title='arranged in ascending order'>"
                    : "<img src='./images/arrow_up.gif' alt='ASC' border='0' align='absmiddle' title='arrange in ascending order'>")
			. "</a><a title='descending' href='cc_view.php?action=show_report&id={$_GET['id']}&sort=name_cache&mode=DESC'>"
			. (($_REQUEST['sort'] == 'name_cache' && $_REQUEST['mode'] == 'DESC')
                    ? "<img src='./images/red_arrow_down.gif' alt='DESC' border='0' align='absmiddle' title='arranged in descending order'>"
                    : "<img src='./images/arrow_down.gif' alt='DESC' border='0' align='absmiddle' title='arrange in descending order'>")
			. "</a>
			</td>";

	foreach($ds_description as $datasource) {
		$name	= ($datasource != 'overall') ? $rs_description : $ov_description;
		if($name !== FALSE) {
			foreach($name as $id) {
				$var	= ($datasource != 'overall') ? $datasource.'__'.$id : 'spanned__'.$id;
				$title 	= $mea[$id]['description'];

				if($mea[$id]['visible']) {
					if (isset($_GET['mode'])){
						$sortorder = '&sort_direction=' . $_GET['mode'];
					} else {
						$sortorder = '';
					}
					print "<td class='textSubHeaderDark' align='right'>
							<strong title='$title'>
                                <a class='textSubHeaderDark' href='cc_view.php?action=show_report&id={$_GET['id']}&sort=$var&mode="
                                . (($_REQUEST['sort'] == $var && $_REQUEST['mode'] == 'ASC')
                                    ? "DESC'>"
                                    : "ASC'>")
                                . "{$mea[$id]['abbreviation']}&nbsp;[{$mea[$id]['unit']}]</a>&nbsp;<a title='ascending' href='cc_view.php?action=show_report&id={$_GET['id']}&sort=$var&mode=ASC'>"

							. (($_REQUEST['sort'] == $var && $_REQUEST['mode'] == 'ASC')
                                    ? "<img src='./images/red_arrow_up.gif' alt='ASC' border='0' align='absmiddle' title='arranged in ascending order'>"
                                    : "<img src='./images/arrow_up.gif' alt='ASC' border='0' align='absmiddle' title='arrange in ascending order'>")
			.                "</a><a title='descending'  href='cc_view.php?action=show_report&id={$_GET['id']}&sort=$var&mode=DESC'>"
							. (($_REQUEST['sort'] == $var && $_REQUEST['mode'] == 'DESC')
                                    ? "<img src='./images/red_arrow_down.gif' alt='DESC' border='0' align='absmiddle' title='arranged in descending order'>"
                                    : "<img src='./images/arrow_down.gif' alt='DESC' border='0' align='absmiddle' title='arrange in descending order'>")
			                 . "</a>
							</strong>";
				}
			}
		}
	}
	echo "</tr>";
	/* Set preconditions */
	$i = 0;
	if (sizeof($report_results) > 0) {
		foreach($report_results as $result) {
			form_alternate_row_color($colors["alternate"], $colors["light"], $i); $i++;
			print "<td " . (($_REQUEST['sort'] == 'name_cache') ? "bgcolor='#FFFACD'>" : ">") ."
					<a class='linkEditMain' href='cc_view.php?action=show_graph_overview&id={$_GET["id"]}&rrd={$result["id"]}&cache={$_REQUEST['archive']}'>
					{$result["name_cache"]}
					</a>";
			if($_REQUEST["subhead"] == 1) {
				$replace = array ($result['start_time'], $result['end_time'], $result['timezone'], $result['start_day'], $result['end_day']);
				$subhead = str_replace($search, $replace, $result['description']);
				print "<br>$subhead";
			}
			echo '</td>';

			foreach($ds_description as $datasource) {
				$name	= ($datasource != 'overall') ? $rs_description : $ov_description;

				foreach($name as $id) {
					$rounding	= $mea[$id]['rounding'];
					$data_type	= $mea[$id]['data_type'];
					$data_precision = $mea[$id]['data_precision'];
					$var		= ($datasource != 'overall') ? $datasource.'__'.$id : 'spanned__'.$id;
					$value		= $result[$var];
					$additional[$var]['values'][] = $value;
					$additional[$var]['rounding'] = $rounding;
					$additional[$var]['data_type'] = $data_type;
					$additional[$var]['data_precision'] = $data_precision;

					echo "<td align='right' " . (($_REQUEST['sort'] == $var) ? "bgcolor='#FFFACD'>" : ">");
					print get_unit($value, $rounding, $data_type, $data_precision);
					echo '</td>';
				}
			}
		}
	}else {
		print "<tr bgcolor='#F5F5F5'><td colspan='100'><em>No data items</em></td></tr>\n";
	}


	/* show additional informations if requested */
	switch ($_REQUEST["info"]) {
		case '-2':
			break;
		case '-1':
			echo "<tr></tr>";
			if(sizeof($additional)>0) {
				for($a=1; $a<5; $a++) {
					form_alternate_row_color("FFFACD", "FFFACD", $i); $i++;
					$description = $add_info[$a][0];
					$calc_fct = $add_info[$a][1];

					print "<td><strong>$description</strong></td>";
					foreach($additional as $array){
							print "<td align='right'>" . get_unit($calc_fct($array['values']), $array['rounding'], $array['data_type'], $array['data_precision']) . "</td>";
					}
				}
			}
			break;
		default:
			echo "<tr></tr>";
			if(sizeof($additional)>0) {
				form_alternate_row_color("FFFACD", "FFFACD", $i); $i++;
				$description = $add_info[$_REQUEST['info']][0];
				$calc_fct = $add_info[$_REQUEST['info']][1];

				print "<td><strong>$description</strong></td>";
				foreach($additional as $array){
						print "<td align='right'>" . get_unit($calc_fct($array['values']), $array['rounding'], $array['data_type'], $array['data_precision']) . "</td>";
				}
			}
			break;
	}

	if ($total_rows > $num_of_rows) {
		print $nav;
	}

	echo '</table><br>';
	echo '<form name="custom_dropdown" method="post">';
	draw_custom_actions_dropdown($export_formats, 'cc_view.php', 'single_export');
	echo '</form>';
	ob_end_flush();
}


function show_graph_overview() {

	/* ================= Input validation ================= */
		input_validate_input_number(get_request_var("id"));
		input_validate_input_number(get_request_var("rrd"));
		input_validate_input_number(get_request_var("cache"));
	/* ==================================================== */

	/* load report archive and fill up report cache if requested*/
	if($_REQUEST['cache'] != -1) {
		cache_xml_file($_REQUEST['id'], $_REQUEST['cache']);
		$cache_id = $_REQUEST['id'] . '_' . $_REQUEST['cache'];
	}

	/* load report data */
	$data = ($_REQUEST['cache'] == -1)
		? get_prepared_report_data($_REQUEST['id'],'view')
		: get_prepared_archive_data($cache_id, 'view');
	$report_data	= $data['report_data'];

	$sql = "SELECT DISTINCT c.local_graph_id
			 FROM 			data_template_data 		AS a
			 INNER JOIN 	data_template_rrd 		AS b
			 ON 			b.local_data_id 		= a.local_data_id
			 INNER JOIN 	graph_templates_item 	AS c
			 ON 			c.task_item_id 			= b.id
			 WHERE 			a.local_data_id 		= {$_GET['rrd']}";
	$local_graph_id = db_fetch_cell($sql);

	$start	= strtotime($report_data['start_date']);
	$end	= strtotime($report_data['end_date'] . ' 23:59:59');
	header("Location: ../../graph.php?action=zoom&local_graph_id=$local_graph_id&rra_id=0&graph_start=$start&graph_end=$end");
}


function show_graphs() {
	global $config, $colors, $graphs, $limit;

	$columns		= 1;
	$archive		= array();
	$affix			= "";
	$description	= "";
	$report_ds_alias= array();

	/* ================= Input validation ================= */
		input_validate_input_number(get_request_var("id"));
		input_validate_input_number(get_request_var_request("data_source"));
		input_validate_input_number(get_request_var_request("measurand"));
		input_validate_input_number(get_request_var_request("archive"));
		input_validate_input_number(get_request_var_request("type"));
		input_validate_input_number(get_request_var_request("limit"));
		input_validate_input_number(get_request_var("summary"));
	/* ==================================================== */

	/* ==================== Checkpoint ==================== */
		my_report(get_request_var('id'), TRUE);
		check_graph_support();
	/* ==================================================== */

	/* clean up search string */
	if (isset($_REQUEST["filter"])) {
		$_REQUEST["filter"] = sanitize_search_string(get_request_var_request("filter"));
	}

	/* if the user pushed the 'clear' button */
	$id = (read_graph_config_option('reportit_view_filter') == 'on') ? $_REQUEST['id'] : '';

	if (isset($_REQUEST["clear_x"])) {
		kill_session_var("sess_reportit_show_{$id}_data_source");
		kill_session_var("sess_reportit_show_{$id}_measurand");
		kill_session_var("sess_reportit_show_{$id}_filter");
		kill_session_var("sess_reportit_show_{$id}_archive");
		kill_session_var("sess_reportit_show_{$id}_type");
		kill_session_var("sess_reportit_show_{$id}_limit");
		kill_session_var("sess_reportit_show_{$id}_summary");

		unset($_REQUEST["data_source"]);
		unset($_REQUEST["measurand"]);
		unset($_REQUEST["filter"]);
		unset($_REQUEST["archive"]);
		unset($_REQUEST["type"]);
		unset($_REQUEST["limit"]);
		unset($_REQUEST["summary"]);
	}

	/* remember these search fields in session vars so we don't have to keep passing them around */
	load_current_session_value("data_source", "sess_reportit_show_{$id}_data_source", "-1");
	load_current_session_value("measurand", "sess_reportit_show_{$id}_measurand", "-1");
	load_current_session_value("filter", "sess_reportit_show_{$id}_filter", "");
	load_current_session_value("archive", "sess_reportit_show_{$id}_archive", "-1");
	load_current_session_value("type", "sess_reportit_show_{$id}_type", read_graph_config_option('reportit_g_default'));
	load_current_session_value("limit", "sess_reportit_show_{$id}_limit", "-2");
	load_current_session_value("summary", "sess_reportit_show_{$id}_summary", "0");

	/* form the 'where' clause for our main sql query */
	$affix .= " LIKE '%%" . $_REQUEST["filter"] . "%%'";

	/* get informations about the archive if it exists */
	$archive = info_xml_archive($_REQUEST['id']);

	/* load report archive and fill up report cache if requested*/
	if($_REQUEST['archive'] != -1) {
		cache_xml_file($_REQUEST['id'], $_REQUEST['archive']);
		$cache_id = $_REQUEST['id'] . '_' . $_REQUEST['archive'];
	}

	/* load report data */
	$data = ($_REQUEST['archive'] == -1)
			? get_prepared_report_data($_REQUEST['id'],'view', $affix)
			: get_prepared_archive_data($cache_id, 'view', $affix);

	$report_ds_alias	= $data['report_ds_alias'];
	$report_data		= $data['report_data'];
	$mea				= $data['report_measurands'];
	$report_header		= $report_data['description'];

	/* create a report summary */
	if ($_REQUEST['summary']) {
		$report_summary[1]['Title'] = $report_data['description'];
		$report_summary[1]['Runtime'] = $report_data['runtime'] . 's';
		$report_summary[2]['Owner'] = $report_data['owner'];
		$report_summary[2]['Sliding Time Frame'] = ($report_data['sliding'] == 0) ? 'disabled' : 'enabled (' . strtolower($report_data['preset_timespan']) .')';
		$report_summary[3]['Last Run'] = $report_data['last_run'];
		$report_summary[3]['Scheduler'] = ($report_data['scheduled'] == 0) ? 'disabled' : 'enabled (' . $report_data['frequency'] . ')';
		$report_summary[4]['Period'] = $report_data['start_date'] . " - " . $report_data['end_date'];
		$report_summary[4]['Auto Generated RRD list'] = ($report_data['autorrdlist'] == 0)? 'disabled' : 'enabled';
	}

	/* extract result description */
	list($rs_description, $count_rs) = explode('-', $report_data['rs_def']);
	$rs_description = ($rs_description == '') ? FALSE : explode('|', $rs_description);
	if($rs_description !== FALSE) {
		foreach($rs_description as $key => $id) {
			if(!isset($data['report_measurands'][$id]['visible']) || $data['report_measurands'][$id]['visible'] == 0) {
				$count_rs--;
				unset($rs_description[$key]);
			}else {
				if($_REQUEST['data_source'] != -2)
					$measurands[$id] = $mea[$id]['abbreviation'];
			}
		}
		if($_REQUEST['measurand'] != -1) {
			if (in_array($_REQUEST['measurand'], $rs_description)) {
				$rs_description = array($_REQUEST['measurand']);
				$count_rs = 1;
				$count_ov = 0;
			}
		}
	}

	/* extract 'Overall' description */
	if (!isset($count_ov)) {
		list($ov_description, $count_ov) = explode('-', $report_data['sp_def']);
		$ov_description 	= ($ov_description == '') ? FALSE : explode('|', $ov_description);
		if($ov_description !== FALSE) {
			foreach($ov_description as $key => $id) {
				if(!isset($data['report_measurands'][$id]['visible']) || $data['report_measurands'][$id]['visible'] == 0) {
					$count_ov--;
					unset($ov_description[$key]);
				}else {
					If($_REQUEST['data_source'] == -1 || $_REQUEST['data_source'] == -2)
						$measurands[$id] = $mea[$id]['abbreviation'];
				}
			}
			if($_REQUEST['measurand'] != -1) {
				if (in_array($_REQUEST['measurand'], $ov_description)) {
					$ov_description = array($_REQUEST['measurand']);
					$count_ov = 1;
					$count_rs = 0;
				}
			}
		}
	}

	/* extract datasource description */
	if($count_rs > 0) $ds_description = explode('|', $report_data['ds_description']);
	if($count_ov > 0) $ds_description[-2] = 'overall';

	/* save all data source name for drop down menue */
	$data_sources = $ds_description;
	foreach($data_sources as $key => $value) {
		if(is_array($report_ds_alias) && array_key_exists($value, $report_ds_alias) && $report_ds_alias[$value] != '')
			$data_sources[$key] = $report_ds_alias[$value];
	}

	/* filter by data source */
	if ($_REQUEST['data_source'] != -1) {
		$ds_description = array($ds_description[$_REQUEST['data_source']]);
	}

	/* Filter settings */
	$order = ($_REQUEST['limit'] < 0)? 'DESC' : 'ASC';
	$limitation = abs($_REQUEST['limit'])*5;

	//----- Start HTML output -----
	ob_start();
	html_custom_header_box($report_header, false, "./cc_view.php?action=show_report&id={$_REQUEST['id']}", "<img src='./images/tab.gif' alt='Tabular View' border='0' align='top'>");
	include_once(REPORTIT_BASE_PATH . '/lib_int/inc_report_graphs_filter_table.php');
	html_end_box();

	if($_REQUEST['summary']) {
		html_graph_start_box(1, false);
		foreach($report_summary as $array) {
			echo "<tr>";
			foreach($array as $key => $value) {
				echo "<td><b>$key:</b></td></td><td align='left'>$value</td>";
			}
			echo "</tr>";
		}
		html_graph_end_box();
		echo "<br>";
	}

	html_graph_start_box(3, false);
	foreach($ds_description as $datasource) {
		$description = (is_array($report_ds_alias) && array_key_exists($datasource, $report_ds_alias))
						? ($report_ds_alias[$datasource] != '')
							? $report_ds_alias[$datasource]
							: $datasource
						: $datasource;
		print "<tr bgcolor='#" . $colors["header_panel"] . "'><td colspan='3' class='textHeaderDark'><strong>Data Source:</strong> $description</td></tr>";

		$name	= ($datasource != 'overall') ? $rs_description : $ov_description;
		if($name !== FALSE) {
			foreach($name as $id) {
				$var			= ($datasource != 'overall') ? $datasource.'__'.$id : 'spanned__'.$id;
				$title 			= $mea[$id]['description'];
				$rounding		= $mea[$id]['rounding'];
				$unit			= $mea[$id]['unit'];
				$rounding		= $mea[$id]['rounding'];
				$data_type		= $mea[$id]['data_type'];
				$data_precision = $mea[$id]['data_precision'];
				$suffix			= " ORDER BY a.$var $order LIMIT 0, $limitation";

				if($mea[$id]['visible']) {
					if ($_REQUEST['archive'] == -1) {
						$sql = 	"SELECT a.*, b.*, c.name_cache FROM reportit_results_{$_REQUEST['id']} AS a
								 INNER JOIN reportit_data_items AS b
								 ON (b.id = a.id AND b.report_id = {$_REQUEST['id']})
								 INNER JOIN data_template_data AS c
								 ON c.local_data_id = a.id
								 WHERE c.name_cache ". $affix . $suffix;
					}else {
						$sql =	"SELECT * FROM reportit_tmp_{$_REQUEST['id']}_{$_REQUEST['archive']} AS a
								 WHERE a.name_cache ". $affix . $suffix;
					}

					$data = db_fetch_assoc($sql);
					echo "<tr bgcolor='#a9b7cb'><td colspan='3' class='textHeaderDark'><strong>Measurand:</strong> $title ({$mea[$id]['abbreviation']})</td></tr>";
					//echo "<tr valign='top'><td colspan='2'><a href='./cc_graphs.php?id={$_REQUEST['id']}&source=$var' style='border: 1px solid #bbbbbb;' alt='$title ({$mea[$id]['abbreviation']})'>hallo</a></td>";
					echo "<tr valign='top'><td colspan='2'><img src='./cc_graphs.php?id={$_REQUEST['id']}&source=$var' style='border: 1px solid #bbbbbb;' alt='$title ({$mea[$id]['abbreviation']})'></td>";
					echo "<td colspan='1' width='100%'>";
					if (count($data)>0) {
						html_report_start_box();
					html_header(array("Pos.","Description", "Results [$unit]"));
						$i = 0;
						foreach($data as $item){
							$value	= $item[$var];
							$title 	= "{$item['start_day']}&nbsp;-&nbsp;{$item['end_day']}&nbsp;&#10;{$item['start_time']}&nbsp;-&nbsp;{$item['end_time']} {$item['timezone']}";
							form_alternate_row_color($colors["alternate"], $colors["light"], $i); $i++;
							echo "<td title='$title'>$i</td>";
							echo "<td title='$title'>
										<a class='linkEditMain' href='cc_view.php?action=show_graph_overview&id={$_REQUEST['id']}&rrd={$item['id']}&cache={$_REQUEST['archive']}'>
										{$item['name_cache']}
										</a>
								  </td>";
							echo "<td title='$title' align='right'>";
							if($value == NULL) {
								print "NA";
							}elseif ($value == 0) {
								print $value;
							}else {
								print get_unit($value, $rounding, $data_type, $data_precision);
							}
							echo "</td>";
						}
						echo "</table>";

					}
					echo "</td></tr>";
				}
			}
		}
	}

	html_graph_end_box();
	ob_end_flush();
}



function show_export_wizard($new=false){
	global $config,$colors, $export_formats;

	/* start-up sequence */
	if($new !== false) {
		$_SESSION['reportit']['export'] = array();

		/* save all report ids in $_SESSION */
		foreach($_POST as $key => $value) {
			if(strstr($key, 'chk_')) {
				$id = substr($key, 4);
				my_report($id, TRUE);
				$_SESSION['reportit']['export'][$id] = array();
				$_SESSION['reportit']['export'][$id]['ids'] = array();
			}
		}
	}

	$report_ids = $_SESSION['reportit']['export'];
	if(sizeof($report_ids) == 0) die('fehler');

	html_wizard_header('Export', 'cc_view.php');

	print "<tr>
			<td class='textArea' colspan='4' bgcolor='#" . $colors["form_alternate1"]. "'>
			<br>
			<p>Choose a template your report should depends on.<br>
			</p>
			<b>Available report templates</b><br>";
		form_dropdown('template', $export_formats, '', '', '', '', '');
		print "	 </td>
				</tr>\n";

	html_header(array('Description','<div align=\'right\'>Instances: available</div>','<div align=\'right\'>Instances: selected</div>',''));

	$ids = '';
	foreach($report_ids as $key => $value) {
		$ids .= "$key,";
	}
	$ids = substr($ids, 0, strlen($ids)-1);

	$sql = "SELECT id, description, scheduled, autoarchive FROM reportit_reports WHERE id IN ($ids)";
	$reports = db_fetch_assoc($sql);

	$i = 0;

	foreach ($reports as $report) {
		form_alternate_row_color($colors["alternate"],$colors["light"],$i); $i++;
		echo '<td>' . $report['description'] . '</td>';

		$archive = info_xml_archive($report['id']);
		if ($archive) {
			echo '<td align=\'right\'>' . sizeof($archive) . '</td>';
		}else {
			echo '<td><div align=\'right\'>1</div></td>';
		}
		echo '<td><div align=\'right\'>' . sizeof($_SESSION['reportit']['export'][$id]['ids']) . '</div></td>';

		echo '<td align="right">'
			."<a href=\"cc_reports.php?action=remove&id=$key\">"
			.'<img src="../../images/delete_icon.gif" width="10" height="10" border="0" alt="Delete"></a></td>';
	}

	html_end_box();
	html_custom_form_button("cc_view.php", "create", "id", false, "60%");
}