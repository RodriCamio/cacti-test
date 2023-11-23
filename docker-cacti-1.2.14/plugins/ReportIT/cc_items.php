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
include_once(REPORTIT_BASE_PATH . '/lib_int/funct_validate.php');
include_once(REPORTIT_BASE_PATH . '/lib_int/funct_online.php');
include_once(REPORTIT_BASE_PATH . '/lib_int/funct_shared.php');
include_once(REPORTIT_BASE_PATH . '/lib_int/funct_html.php');
include_once(REPORTIT_BASE_PATH . '/lib_int/const_runtime.php');
include_once(REPORTIT_BASE_PATH . '/lib_int/const_items.php');

/* ======== Validation ======== */
		safeguard_xss();
/* ============================ */

if (!isset($_REQUEST["action"])) {
	$_REQUEST["action"] = "";
}

switch ($_REQUEST["action"]) {
	case 'save':
		save();
		break;
	default:
		include_once(CACTI_INCLUDE_PATH . "/top_header.php");
		standard();
		include_once(CACTI_INCLUDE_PATH . "/bottom_footer.php");
		break;
}

function save(){

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var_request("id"));
	/* ==================================================== */

	/* ==================== checkpoint ==================== */
	my_report($_REQUEST["id"]);
	locked(my_template($_REQUEST["id"]));
	/* ==================================================== */

	/* search all checkboxes and put them into array $rrd_ids */
	foreach($_POST as $key => $value) {
		if(strstr($key, 'chk_')) $rrd_ids[] = substr($key, 4);
	}

	/* check default settings and build SQL syntax for saving*/
	if(isset($rrd_ids)) {
		$enable_tmz	= read_config_option('reportit_use_tmz');
		$tmz		= ($enable_tmz) ? "'GMT'" : "'".date('T')."'";
		$columns	= '';
		$values		= '';
		$rrd 		= '';

		/* load data item presets */
		$sql = "SELECT * FROM reportit_presets WHERE id = {$_REQUEST['id']}";
		$presets = db_fetch_row($sql);
		if(sizeof($presets)>0) {
		      $presets['report_id'] = $_REQUEST["id"];
			foreach($presets as $key => $value) {
				$columns .= ', ' .$key;
				if($key != 'id') $values .= (",\"" . mysql_real_escape_string($value) . "\"");
			}
		}else {
			$columns = ' id, report_id';
			$values .= ", \"{$_REQUEST["id"]}\"";
		}

		foreach($rrd_ids as $rd) {
			$rrd .= "($rd $values),";
		}

		$rrd = substr($rrd, 0, strlen($rrd)-1);
		$columns = substr($columns, 1);

		/* save */
		$sql = "INSERT INTO reportit_data_items ($columns) VALUES $rrd";
		db_execute($sql);

		/* reset report */
		reset_report($_REQUEST['id']);
	}

	/* return to standard form */
	header("Location: cc_items.php?id={$_REQUEST['id']}");
}


function standard() {
	global $colors, $config, $link_array;

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var_request("id"));
	input_validate_input_number(get_request_var_request("page"));
	input_validate_input_number(get_request_var_request("host"));
	/* ==================================================== */

	/* ==================== checkpoint ==================== */
	my_report($_REQUEST["id"]);
	locked(my_template($_REQUEST["id"]));
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
		kill_session_var("sess_reportit_items_filter");
		kill_session_var("sess_reportit_items_current_page");
		kill_session_var("sess_reportit_items_sort");
		kill_session_var("sess_reportit_items_mode");
		kill_session_var("sess_reportit_items_host");

		unset($_REQUEST["filter"]);
		unset($_REQUEST["page"]);
		unset($_REQUEST["sort"]);
		unset($_REQUEST["mode"]);
		unset($_REQUEST["host"]);
	}

	/* remember these search fields in session vars so we don't have to keep passing them around */
	load_current_session_value("filter", "sess_reportit_items_filter", "");
	load_current_session_value("page", "sess_reportit_items_current_page", "1");
	load_current_session_value("sort", "sess_reportit_items_sort", "id");
	load_current_session_value("mode", "sess_reportit_items_mode", "ASC");
	load_current_session_value("host", "sess_reportit_items_host", "-1");


	$report_data	= db_fetch_row('SELECT * FROM reportit_reports WHERE id=' . $_REQUEST['id']);
	strip_slashes($report_data);

	$current_owner 	= db_fetch_row('SELECT * from user_auth where id=' . $report_data["user_id"]);
	$sql_where 		= get_graph_permissions_sql($current_owner["policy_graphs"], $current_owner["policy_hosts"], $current_owner["policy_graph_templates"]);

	/* load filter settings of that report template this report relies on */
	$sql = "SELECT b.pre_filter, b.data_template_id FROM reportit_reports AS a
			INNER JOIN reportit_templates AS b
				ON a.template_id = b.id
			WHERE a.id={$_REQUEST['id']}";
	$template_filter = db_fetch_assoc($sql);

	/* start building the SQL syntax */
	/* filter all RRDs which are not in RRD table and match with filter settings */
	$sql = "SELECT DISTINCT a.local_data_id AS id, a.name_cache	FROM data_template_data AS a
			LEFT JOIN reportit_data_items AS b
				ON a.local_data_id = b.id AND b.report_id = {$_REQUEST['id']}
			LEFT JOIN data_local AS c
				ON c.id = a.local_data_id
			LEFT JOIN host AS d
				ON d.id = c.host_id";

	/* use additional filter for graph permissions if necessary */
	if (read_config_option("auth_method") != 0 & $report_data['graph_permission'] == 1) {
		$sql_join = " LEFT JOIN graph_local ON (c.id = graph_local.host_id)
					  LEFT JOIN graph_templates ON (graph_templates.id=graph_local.graph_template_id)
					  LEFT JOIN graph_templates_graph ON (graph_templates_graph.local_graph_id=graph_local.id)
					  LEFT JOIN user_auth_perms ON ((graph_templates_graph.local_graph_id=user_auth_perms.item_id and user_auth_perms.type=1 and user_auth_perms.user_id=" . $report_data["user_id"] . ") OR (d.id=user_auth_perms.item_id and user_auth_perms.type=3 and user_auth_perms.user_id=" . $report_data["user_id"] . ") OR (graph_templates.id=user_auth_perms.item_id and user_auth_perms.type=4 and user_auth_perms.user_id=" . $report_data["user_id"] . "))";
		$sql .= $sql_join;
	}

	/* apply Host Template Id filter, if selected in report configuration*/
	if ($report_data['host_template_id'] != 0) {
		$sql .= " LEFT JOIN host_template AS e ON e.id = d.host_template_id";
	}

	/* check Data Template Id filter */
	$sql .= " WHERE b.id IS NULL
				AND a.local_data_id != '0'
				AND a.data_template_id =" . $template_filter['0']['data_template_id'];

	/* check pre-filter settings of the report template */
	if ($template_filter['0']['pre_filter'] != '') {
		$sql .= " AND a.name_cache LIKE '" . $template_filter['0']['pre_filter'] ."'";
	}

	/* check host filter defined by form */
	if ($_REQUEST["host"] == "-1") {
		/* filter nothing */
	}elseif (!empty($_REQUEST["host"])) {
		/* show only data items of selected host */
		$sql .= " AND c.host_id =" . $_REQUEST['host'];
	}

	/* check text filter defined by form */
	if (strlen($_REQUEST["filter"])) {
		$sql .= " AND a.name_cache LIKE '%%" . $_REQUEST['filter'] . "%%'";
	}

	/* check for the specific Host Template Id, if Host Template Id filter has been applied */
	if ($report_data['host_template_id'] != 0) {
		$sql .= " AND e.id = " . $report_data['host_template_id'];
	}
	/* check Data Source Filter, if defined in report configuration*/
	if ($report_data['data_source_filter'] != '') {
		$sql .= " AND a.name_cache LIKE '{$report_data['data_source_filter']}'";
	}
	/* use additional where clause for graph permissions if necessary */
	if (read_config_option("auth_method") != 0 & $report_data['graph_permission'] == 1) {
		$sql .= " AND $sql_where ";
	}

	$total_rows = db_fetch_cell(str_replace('DISTINCT a.local_data_id AS id, a.name_cache','COUNT(DISTINCT a.local_data_id)',$sql));

	/* apply sorting functionality and limitation */
	$sql .= " ORDER BY " . $_REQUEST['sort'] . " " . $_REQUEST['mode'] .
			" LIMIT " . ($session_max_rows*($_REQUEST["page"]-1)) . "," . $session_max_rows;

	$rrdlist = db_fetch_assoc($sql);

	/* generate page list */
	$url_page_select = html_custom_page_list($_REQUEST["page"], MAX_DISPLAY_PAGES, $session_max_rows, $total_rows, "cc_items.php?id={$_REQUEST['id']}");

	$nav = "<tr bgcolor='#6CA6CD' >
		<td colspan='2'>
			<table width='100%' cellspacing='0' cellpadding='0' border='0'>
				<tr>
					<td align='left'>
						<strong>&laquo; "; if ($_REQUEST["page"] > 1) { $nav .= "<a style='color:FFFF00' href='cc_items.php?id=" . $_REQUEST['id'] . "&page=" . ($_REQUEST["page"]-1) . "'>"; } $nav .= "Previous"; if ($_REQUEST["page"] > 1) { $nav .= "</a>"; } $nav .= "</strong>
					</td>\n
					<td align='center' class='textSubHeaderDark'>
						Showing Rows " . (($session_max_rows*($_REQUEST["page"]-1))+1) . " to " . ((($total_rows < $session_max_rows) || ($total_rows < ($session_max_rows*$_REQUEST["page"]))) ? $total_rows : ($session_max_rows*$_REQUEST["page"])) . " of $total_rows [$url_page_select]
					</td>\n
					<td align='right'>
						<strong>"; if (($_REQUEST["page"] * $session_max_rows) < $total_rows) { $nav .= "<a style='color:yellow' href='cc_items.php?id=" . $_REQUEST['id'] . "&page=" . ($_REQUEST["page"]+1) . "'>"; } $nav .= "Next"; if (($_REQUEST["page"] * $session_max_rows) < $total_rows) { $nav .= "</a>"; } $nav .= " &raquo;</strong>
					</td>\n
				</tr>
			</table>
		</td>
		</tr>\n";

	$header_label	= "[add to report:
						<a style='color:yellow' href='cc_reports.php?action=report_edit&id={$_REQUEST['id']}'>" .
						$report_data['description'] . "</a>]";

	/* show the Host Template Description in the header, if Host Template Id filter was set */
	$ht_desc = db_fetch_cell('SELECT name FROM host_template WHERE id=' . $report_data['host_template_id']);
	if (!strlen($ht_desc)) $ht_desc = 'none';

	/* show the Data Source Filter in the heaser, if it has been defined */
	$ds_desc = $report_data['data_source_filter'];
	if (!strlen($ds_desc)) $ds_desc = 'none';


	/* start with HTML output */
	html_start_box("<strong>Data Objects </strong>$header_label", REPORTIT_WIDTH, $colors["header"], "2", "center", "");
	include(REPORTIT_BASE_PATH . '/lib_int/inc_data_items_filter_table.php');
	html_end_box();

	html_start_box("", REPORTIT_WIDTH, $colors["header"], "3", "center", "");
	print $nav;
	$desc_array = array('Description');
	html_header_checkbox(html_sorted_with_arrows( $desc_array, $link_array, 'cc_items.php', $_REQUEST['id']));


	//Set preconditions
	$i = 0;

	/* Necessary to support Cacti 0.8.6k and lower versions as well */
	$new_version = check_cacti_version(14);

	if (sizeof($rrdlist) > 0) {
		foreach($rrdlist as $rrd) {
			if ($new_version) form_alternate_row_color($colors["alternate"], $colors["light"], $i, $rrd["id"]);
			else form_alternate_row_color($colors["alternate"], $colors["light"], $i); $i++;
			?>
				<td><?php print $rrd['name_cache'];?></td>
				<td style="<?php print get_checkbox_style();?>" width="1%" align="right">
					<input type='checkbox' style='margin: 0px;' name='chk_<?php print $rrd["id"];?>' title="Select">
				</td>
			</tr>
			<?php
		}
		if ($total_rows > $session_max_rows) print $nav;
	}else {
		print "<tr><td><em>No data items</em></td></tr>\n";
	};

	/*remember report id */
	$form_array = array('id' => array('method' => 'hidden_zero', 'value' => $_REQUEST['id']));
	draw_edit_form(array('config' => array(),'fields' => $form_array));

	html_end_box(true);
	form_save_button("cc_rrdlist.php?&id={$_REQUEST['id']}", "", "");
}

?>