<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2006-2010 Reinhard Scheck aka gandalf                     |
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
 */


function display_matching_hosts($rule, $rule_type, $url) {
	global $colors, $device_actions, $item_rows;
	#print "<pre>"; print "Post:"; print_r($_POST); print("Get: "); print_r($_GET); print("Request: "); print_r($_REQUEST); print "</pre>";

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var_request("host_template_id"));
	input_validate_input_number(get_request_var_request("hpage"));
	input_validate_input_number(get_request_var_request("host_status"));
	input_validate_input_number(get_request_var_request("host_rows"));
	/* ==================================================== */

	/* clean up search string */
	if (isset($_REQUEST["filter"])) {
		$_REQUEST["filter"] = sanitize_search_string(get_request_var_request("filter"));
	}

	/* clean up sort_column */
	if (isset($_REQUEST["sort_column"])) {
		$_REQUEST["sort_column"] = sanitize_search_string(get_request_var_request("sort_column"));
	}

	/* clean up search string */
	if (isset($_REQUEST["sort_direction"])) {
		$_REQUEST["sort_direction"] = sanitize_search_string(get_request_var_request("sort_direction"));
	}

	/* if the user pushed the 'clear' button */
	if (isset($_REQUEST["clear_x"])) {
		kill_session_var("sess_autom8_device_current_page");
		kill_session_var("sess_autom8_device_filter");
		kill_session_var("sess_autom8_device_host_template_id");
		kill_session_var("sess_autom8_host_status");
		kill_session_var("sess_autom8_host_rows");
		kill_session_var("sess_autom8_host_sort_column");
		kill_session_var("sess_autom8_host_sort_direction");

		unset($_REQUEST["hpage"]);
		unset($_REQUEST["filter"]);
		unset($_REQUEST["host_template_id"]);
		unset($_REQUEST["host_status"]);
		unset($_REQUEST["host_rows"]);
		unset($_REQUEST["sort_column"]);
		unset($_REQUEST["sort_direction"]);
	}

	if ((!empty($_SESSION["sess_autom8_host_status"])) && (!empty($_REQUEST["host_status"]))) {
		if ($_SESSION["sess_autom8_host_status"] != $_REQUEST["host_status"]) {
			$_REQUEST["hpage"] = 1;
		}
	}

	/* remember these search fields in session vars so we don't have to keep passing them around */
	load_current_session_value("hpage", "sess_autom8_device_current_page", "1");
	load_current_session_value("filter", "sess_autom8_device_filter", "");
	load_current_session_value("host_template_id", "sess_autom8_device_host_template_id", "-1");
	load_current_session_value("host_status", "sess_autom8_host_status", "-1");
	load_current_session_value("host_rows", "sess_autom8_host_rows", read_config_option("num_rows_device"));
	load_current_session_value("sort_column", "sess_autom8_host_sort_column", "description");
	load_current_session_value("sort_direction", "sess_autom8_host_sort_direction", "ASC");

	/* if the number of rows is -1, set it to the default */
	if ($_REQUEST["host_rows"] == -1) {
		$_REQUEST["host_rows"] = read_config_option("num_rows_device");
	}

	?>
	<script type="text/javascript">
	<!--

	function applyViewDeviceFilterChange(objForm) {
		strURL = '<?php print $url;?>' + '&host_status=' + objForm.host_status.value;
		strURL = strURL + '&host_template_id=' + objForm.host_template_id.value;
		strURL = strURL + '&host_rows=' + objForm.host_rows.value;
		strURL = strURL + '&filter=' + objForm.filter.value;
		document.location = strURL;
	}

	-->
	</script>
	<?php

	print "<form method='post' name='form_autom8_host' action='" . htmlspecialchars($url) . "'>";
	html_start_box("<strong>Eligible Hosts</strong>", "100%", $colors["header"], "3", "center", "");

	?>
	<tr bgcolor="<?php print $colors["panel"];?>">
		<td>
			<table width="100%" cellpadding="0" cellspacing="0">
				<tr>
					<td nowrap style='white-space: nowrap;' width="50">
						Type:&nbsp;
					</td>
					<td width="1">
						<select name="host_template_id" onChange="applyViewDeviceFilterChange(document.form_autom8_host)">
							<option value="-1"<?php if (get_request_var_request("host_template_id") == "-1") {?> selected<?php }?>>Any</option>
							<option value="0"<?php if (get_request_var_request("host_template_id") == "0") {?> selected<?php }?>>None</option>
							<?php
							$host_templates = db_fetch_assoc("select id,name from host_template order by name");

							if (sizeof($host_templates) > 0) {
							foreach ($host_templates as $host_template) {
								print "<option value='" . $host_template["id"] . "'"; if (get_request_var_request("host_template_id") == $host_template["id"]) { print " selected"; } print ">" . $host_template["name"] . "</option>\n";
							}
							}
							?>
						</select>
					</td>
					<td nowrap style='white-space: nowrap;' width="50">
						&nbsp;Status:&nbsp;
					</td>
					<td width="1">
						<select name="host_status" onChange="applyViewDeviceFilterChange(document.form_autom8_host)">
							<option value="-1"<?php if (get_request_var_request("host_status") == "-1") {?> selected<?php }?>>Any</option>
							<option value="-3"<?php if (get_request_var_request("host_status") == "-3") {?> selected<?php }?>>Enabled</option>
							<option value="-2"<?php if (get_request_var_request("host_status") == "-2") {?> selected<?php }?>>Disabled</option>
							<option value="-4"<?php if (get_request_var_request("host_status") == "-4") {?> selected<?php }?>>Not Up</option>
							<option value="3"<?php if (get_request_var_request("host_status") == "3") {?> selected<?php }?>>Up</option>
							<option value="1"<?php if (get_request_var_request("host_status") == "1") {?> selected<?php }?>>Down</option>
							<option value="2"<?php if (get_request_var_request("host_status") == "2") {?> selected<?php }?>>Recovering</option>
							<option value="0"<?php if (get_request_var_request("host_status") == "0") {?> selected<?php }?>>Unknown</option>
						</select>
					</td>
					<td nowrap>
						&nbsp;<input type="image" src="../../images/button_go.gif" alt="Go" align="middle">
						<input type="image" src="../../images/button_clear.gif" name="clear" alt="Clear" align="middle">
					</td>
				</tr>
				<tr>
					<td nowrap style='white-space: nowrap;' width="20">
						Search:&nbsp;
					</td>
					<td width="1">
						<input type="text" name="filter" size="30" value="<?php print get_request_var_request("filter");?>">
					</td>
					<td nowrap style='white-space: nowrap;' width="50">
						&nbsp;Rows per Page:&nbsp;
					</td>
					<td width="1">
						<select name="host_rows" onChange="applyViewDeviceFilterChange(document.form_autom8_host)">
							<option value="-1"<?php if (get_request_var_request("host_rows") == "-1") {?> selected<?php }?>>Default</option>
							<?php
							if (sizeof($item_rows) > 0) {
							foreach ($item_rows as $key => $value) {
								print "<option value='" . $key . "'"; if (get_request_var_request("host_rows") == $key) { print " selected"; } print ">" . $value . "</option>\n";
							}
							}
							?>
						</select>
					</td>
				</tr>
			</table>
		</td>
	</tr>
	<?php

	html_end_box(false);
	form_hidden_box("hpage", '1', '');

	/* form the 'where' clause for our main sql query */
	if (strlen(get_request_var_request("filter"))) {
		$sql_where = "WHERE (host.hostname LIKE '%%" . get_request_var_request("filter") . "%%' OR host.description LIKE '%%" . get_request_var_request("filter") . "%%' OR host_template.name LIKE '%%" . get_request_var_request("filter") . "%%')";
	}else{
		$sql_where = "";
	}

	if (get_request_var_request("host_status") == "-1") {
		/* Show all items */
	}elseif (get_request_var_request("host_status") == "-2") {
		$sql_where .= (strlen($sql_where) ? " and host.disabled='on'" : "where host.disabled='on'");
	}elseif (get_request_var_request("host_status") == "-3") {
		$sql_where .= (strlen($sql_where) ? " and host.disabled=''" : "where host.disabled=''");
	}elseif (get_request_var_request("host_status") == "-4") {
		$sql_where .= (strlen($sql_where) ? " and (host.status!='3' or host.disabled='on')" : "where (host.status!='3' or host.disabled='on')");
	}else {
		$sql_where .= (strlen($sql_where) ? " and (host.status=" . get_request_var_request("host_status") . " AND host.disabled = '')" : "where (host.status=" . get_request_var_request("host_status") . " AND host.disabled = '')");
	}

	if (get_request_var_request("host_template_id") == "-1") {
		/* Show all items */
	}elseif (get_request_var_request("host_template_id") == "0") {
		$sql_where .= (strlen($sql_where) ? " and host.host_template_id=0" : "where host.host_template_id=0");
	}elseif (!empty($_REQUEST["host_template_id"])) {
		$sql_where .= (strlen($sql_where) ? " and host.host_template_id=" . get_request_var_request("host_template_id") : "where host.host_template_id=" . get_request_var_request("host_template_id"));
	}

	html_start_box("", "100%", $colors["header"], "3", "center", "");

	$host_graphs       = array_rekey(db_fetch_assoc("SELECT host_id, count(*) as graphs FROM graph_local GROUP BY host_id"), "host_id", "graphs");
	$host_data_sources = array_rekey(db_fetch_assoc("SELECT host_id, count(*) as data_sources FROM data_local GROUP BY host_id"), "host_id", "data_sources");

	/* build magic query, for matching hosts JOIN tables host and host_template */
	$sql_query = "SELECT " .
		"host.id AS host_id, " .
		"host.hostname, " .
		"host.description, " .
		"host.disabled, " .
		"host.status, " .
		"host_template.name AS host_template_name " .
		"FROM host " .
		"LEFT JOIN host_template ON (host.host_template_id = host_template.id) ";
	#	$hosts = db_fetch_assoc($sql_query);
	#	print "<pre>Hosts: $sql_query<br>"; print_r($hosts); print "</pre>";

	/* get the WHERE clause for matching hosts */
	if (strlen($sql_where)) {
		$sql_filter = " AND (" . build_matching_objects_filter($rule["id"], $rule_type) . ")";
	} else {
		$sql_filter = " WHERE (" . build_matching_objects_filter($rule["id"], $rule_type) .")";
	}

	/* now we build up a new query for counting the rows */
	$rows_query = $sql_query . $sql_where . $sql_filter;
	$total_rows = sizeof(db_fetch_assoc($rows_query));
	#print "<pre>Rows Query: $rows_query<br>Total Rows: "; print($total_rows); print "</pre>";

	$sortby = get_request_var_request("sort_column");
	if ($sortby=="hostname") {
		$sortby = "INET_ATON(hostname)";
	}

	$sql_query = $rows_query .
		" ORDER BY " . $sortby . " " . get_request_var_request("sort_direction") .
		" LIMIT " . (get_request_var_request("host_rows")*(get_request_var_request("hpage")-1)) . "," . get_request_var_request("host_rows");
	$hosts = db_fetch_assoc($sql_query);
	#print "<pre>Host Query: " . $sql_query; print "</pre>";
	
	/* generate page list */
	$url_page_select = get_page_list(get_request_var_request("hpage"), MAX_DISPLAY_PAGES, get_request_var_request("host_rows"), $total_rows, $url . "&filter=" . get_request_var_request("filter") . "&host_template_id=" . get_request_var_request("host_template_id") . "&host_status=" . get_request_var_request("host_status"), "hpage");

	$nav = "<tr bgcolor='#" . $colors["header"] . "'>
			<td colspan='11'>
				<table width='100%' cellspacing='0' cellpadding='0' border='0'>
					<tr>
						<td align='left' class='textHeaderDark'>
							<strong>&lt;&lt; "; if (get_request_var_request("hpage") > 1) { $nav .= "<a class='linkOverDark' href='" . htmlspecialchars($url . "&filter=" . get_request_var_request("filter") . "&host_template_id=" . get_request_var_request("host_template_id") . "&host_status=" . get_request_var_request("host_status") . "&hpage=" . (get_request_var_request("hpage")-1)) . "'>"; } $nav .= "Previous"; if (get_request_var_request("hpage") > 1) { $nav .= "</a>"; } $nav .= "</strong>
						</td>\n
						<td align='center' class='textHeaderDark'>
							Showing Rows " . ((get_request_var_request("host_rows")*(get_request_var_request("hpage")-1))+1) . " to " . ((($total_rows < read_config_option("num_rows_device")) || ($total_rows < (get_request_var_request("host_rows")*get_request_var_request("hpage")))) ? $total_rows : (get_request_var_request("host_rows")*get_request_var_request("hpage"))) . " of $total_rows [$url_page_select]
						</td>\n
						<td align='right' class='textHeaderDark'>
							<strong>"; if ((get_request_var_request("hpage") * get_request_var_request("host_rows")) < $total_rows) { $nav .= "<a class='linkOverDark' href='" . htmlspecialchars($url . "&filter=" . get_request_var_request("filter") . "&host_template_id=" . get_request_var_request("host_template_id") . "&host_status=" . get_request_var_request("host_status") . "&hpage=" . (get_request_var_request("hpage")+1)) . "'>"; } $nav .= "Next"; if ((get_request_var_request("hpage") * get_request_var_request("host_rows")) < $total_rows) { $nav .= "</a>"; } $nav .= " &gt;&gt;</strong>
						</td>\n
					</tr>
				</table>
			</td>
		</tr>\n";

	print $nav;

	$display_text = array(
		"description" => array("Description", "ASC"),
		"hostname" => array("Hostname", "ASC"),
		"status" => array("Status", "ASC"),
		"host_template_name" => array("Host Template Name", "ASC"),
		"id" => array("ID", "ASC"),
		"nosort1" => array("Graphs", "ASC"),
		"nosort2" => array("Data Sources", "ASC"),
	);

	html_header_sort_url($display_text, get_request_var_request("sort_column"), get_request_var_request("sort_direction"), 1, $url);

	$i = 0;
	if (sizeof($hosts) > 0) {
		foreach ($hosts as $host) {
			form_alternate_row_color($colors["alternate"], $colors["light"], $i, 'line' . $host["host_id"]); $i++;
			form_selectable_cell("<a class='linkEditMain' href='" . htmlspecialchars("../../host.php?action=edit&id=" . $host["host_id"]) . "'>" .
				(strlen(get_request_var_request("filter")) ? preg_replace("/(" . preg_quote(get_request_var_request("filter")) . ")/i", "<span style='background-color: #F8D93D;'>\\1</span>", $host["description"]) : $host["description"]) . "</a>", $host["host_id"]);
			form_selectable_cell((strlen(get_request_var_request("filter")) ? preg_replace("/(" . preg_quote(get_request_var_request("filter")) . ")/i", "<span style='background-color: #F8D93D;'>\\1</span>", $host["hostname"]) : $host["hostname"]), $host["host_id"]);
			form_selectable_cell(get_colored_device_status(($host["disabled"] == "on" ? true : false), $host["status"]), $host["host_id"]);
			form_selectable_cell((strlen(get_request_var_request("filter")) ? preg_replace("/(" . preg_quote(get_request_var_request("filter")) . ")/i", "<span style='background-color: #F8D93D;'>\\1</span>", $host["host_template_name"]) : $host["host_template_name"]), $host["host_id"]);
			form_selectable_cell(round(($host["host_id"]), 2), $host["host_id"]);
			form_selectable_cell((isset($host_graphs[$host["host_id"]]) ? $host_graphs[$host["host_id"]] : 0), $host["host_id"]);
			form_selectable_cell((isset($host_data_sources[$host["host_id"]]) ? $host_data_sources[$host["host_id"]] : 0), $host["host_id"]);
			form_end_row();
		}

		/* put the nav bar on the bottom as well */
		print $nav;
	}else{
		print "<tr><td><em>No Hosts</em></td></tr>";
	}
	html_end_box(true);

	print "</form>\n";
}



function display_matching_graphs($rule, $rule_type, $url) {
	global $colors, $graph_actions, $item_rows;

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var_request("host_id"));
	input_validate_input_number(get_request_var_request("graph_rows"));
	input_validate_input_number(get_request_var_request("template_id"));
	input_validate_input_number(get_request_var_request("gpage"));
	/* ==================================================== */

	/* clean up search string */
	if (isset($_REQUEST["filter"])) {
		$_REQUEST["filter"] = sanitize_search_string(get_request_var_request("filter"));
	}

	/* clean up sort_column string */
	if (isset($_REQUEST["sort_column"])) {
		$_REQUEST["sort_column"] = sanitize_search_string(get_request_var_request("sort_column"));
	}

	/* clean up sort_direction string */
	if (isset($_REQUEST["sort_direction"])) {
		$_REQUEST["sort_direction"] = sanitize_search_string(get_request_var_request("sort_direction"));
	}

	/* if the user pushed the 'clear' button */
	if (isset($_REQUEST["clear_x"])) {
		kill_session_var("sess_autom8_graph_current_page");
		kill_session_var("sess_autom8_graph_filter");
		kill_session_var("sess_autom8_graph_sort_column");
		kill_session_var("sess_autom8_graph_sort_direction");
		kill_session_var("sess_autom8_graph_host_id");
		kill_session_var("sess_autom8_graph_rows");
		kill_session_var("sess_autom8_graph_template_id");

		unset($_REQUEST["gpage"]);
		unset($_REQUEST["filter"]);
		unset($_REQUEST["sort_column"]);
		unset($_REQUEST["sort_direction"]);
		unset($_REQUEST["host_id"]);
		unset($_REQUEST["graph_rows"]);
		unset($_REQUEST["template_id"]);
	}

	/* remember these search fields in session vars so we don't have to keep passing them around */
	load_current_session_value("gpage", "sess_autom8_graph_current_page", "1");
	load_current_session_value("filter", "sess_autom8_graph_filter", "");
	load_current_session_value("sort_column", "sess_autom8_graph_sort_column", "title_cache");
	load_current_session_value("sort_direction", "sess_autom8_graph_sort_direction", "ASC");
	load_current_session_value("host_id", "sess_autom8_graph_host_id", "-1");
	load_current_session_value("graph_rows", "sess_autom8_graph_rows", read_config_option("num_rows_graph"));
	load_current_session_value("template_id", "sess_autom8_graph_template_id", "-1");

	/* if the number of rows is -1, set it to the default */
	if (get_request_var_request("graph_rows") == -1) {
		$_REQUEST["graph_rows"] = read_config_option("num_rows_graph");
	}

	?>
	<script type="text/javascript">
	<!--

	function applyGraphsFilterChange(objForm) {
		strURL = <?php print $url;?>'&host_id=' + objForm.host_id.value;
		strURL = strURL + '&graph_rows=' + objForm.graph_rows.value;
		strURL = strURL + '&filter=' + objForm.filter.value;
		strURL = strURL + '&template_id=' + objForm.template_id.value;
		document.location = strURL;
	}

	-->
	</script>
	<?php

	print "<form method='post' name='form_autom8_graph' action='" . htmlspecialchars($url) . "'>";
	html_start_box("<strong>Eligible Graphs</strong>", "100%", $colors["header"], "3", "center", "");

	?>
	<tr bgcolor="<?php print $colors["panel"];?>">
		<td>
			<table cellpadding="1" cellspacing="0">
				<tr>
					<td nowrap style='white-space: nowrap;' width="50">
						Host:&nbsp;
					</td>
					<td width="1">
						<select name="host_id" onChange="applyGraphsFilterChange(document.form_autom8_graph)">
							<option value="-1"<?php if (get_request_var_request("host_id") == "-1") {?> selected<?php }?>>Any</option>
							<option value="0"<?php if (get_request_var_request("host_id") == "0") {?> selected<?php }?>>None</option>
							<?php
							if (read_config_option("auth_method") != 0) {
								/* get policy information for the sql where clause */
								$current_user = db_fetch_row("select * from user_auth where id=" . $_SESSION["sess_user_id"]);
								$sql_where = get_graph_permissions_sql($current_user["policy_graphs"], $current_user["policy_hosts"], $current_user["policy_graph_templates"]);

								$hosts = db_fetch_assoc("SELECT DISTINCT host.id, CONCAT_WS('',host.description,' (',host.hostname,')') as name
									FROM (graph_templates_graph,host)
									LEFT JOIN graph_local ON (graph_local.host_id=host.id)
									LEFT JOIN graph_templates ON (graph_templates.id=graph_local.graph_template_id)
									LEFT JOIN user_auth_perms ON ((graph_templates_graph.local_graph_id=user_auth_perms.item_id and user_auth_perms.type=1 and user_auth_perms.user_id=" . $_SESSION["sess_user_id"] . ") OR (host.id=user_auth_perms.item_id and user_auth_perms.type=3 and user_auth_perms.user_id=" . $_SESSION["sess_user_id"] . ") OR (graph_templates.id=user_auth_perms.item_id and user_auth_perms.type=4 and user_auth_perms.user_id=" . $_SESSION["sess_user_id"] . "))
									WHERE graph_templates_graph.local_graph_id=graph_local.id
									" . (empty($sql_where) ? "" : "and $sql_where") . "
									ORDER BY name");
							}else{
								$hosts = db_fetch_assoc("SELECT DISTINCT host.id, CONCAT_WS('',host.description,' (',host.hostname,')') as name
									FROM host
									ORDER BY name");
							}

							if (sizeof($hosts) > 0) {
							foreach ($hosts as $host) {
								print "<option value=' " . $host["id"] . "'"; if (get_request_var_request("host_id") == $host["id"]) { print " selected"; } print ">" . title_trim($host["name"], 40) . "</option>\n";
							}
							}
							?>
						</select>
					</td>
					<td nowrap style='white-space: nowrap;' width="50">
						&nbsp;Template:&nbsp;
					</td>
					<td width="1">
						<select name="template_id" onChange="applyGraphsFilterChange(document.form_autom8_graph)">
							<option value="-1"<?php if (get_request_var_request("template_id") == "-1") {?> selected<?php }?>>Any</option>
							<option value="0"<?php if (get_request_var_request("template_id") == "0") {?> selected<?php }?>>None</option>
							<?php
							if (read_config_option("auth_method") != 0) {
								$templates = db_fetch_assoc("SELECT DISTINCT graph_templates.id, graph_templates.name
									FROM (graph_templates_graph,graph_local)
									LEFT JOIN host ON (host.id=graph_local.host_id)
									LEFT JOIN graph_templates ON (graph_templates.id=graph_local.graph_template_id)
									LEFT JOIN user_auth_perms ON ((graph_templates_graph.local_graph_id=user_auth_perms.item_id and user_auth_perms.type=1 and user_auth_perms.user_id=" . $_SESSION["sess_user_id"] . ") OR (host.id=user_auth_perms.item_id and user_auth_perms.type=3 and user_auth_perms.user_id=" . $_SESSION["sess_user_id"] . ") OR (graph_templates.id=user_auth_perms.item_id and user_auth_perms.type=4 and user_auth_perms.user_id=" . $_SESSION["sess_user_id"] . "))
									WHERE graph_templates_graph.local_graph_id=graph_local.id
									AND graph_templates.id IS NOT NULL
									" . (empty($sql_where) ? "" : "AND $sql_where") . "
									ORDER BY name");
							}else{
								$templates = db_fetch_assoc("SELECT DISTINCT graph_templates.id, graph_templates.name
									FROM graph_templates
									ORDER BY name");
							}

							if (sizeof($templates) > 0) {
							foreach ($templates as $template) {
								print "<option value=' " . $template["id"] . "'"; if (get_request_var_request("template_id") == $template["id"]) { print " selected"; } print ">" . title_trim($template["name"], 40) . "</option>\n";
							}
							}
							?>
						</select>
					</td>
					<td width="120" nowrap style='white-space: nowrap;'>
						&nbsp;<input type="image" src="../../images/button_go.gif" alt="Go" align="middle">
						<input type="image" src="../../images/button_clear.gif" name="clear" alt="Clear" align="middle">
					</td>
				</tr>
				<tr>
					<td nowrap style='white-space: nowrap;' width="50">
						Search:&nbsp;
					</td>
					<td>
						<input type="text" name="filter" size="30" value="<?php print get_request_var_request("filter");?>">
					</td>
					<td nowrap style='white-space: nowrap;' width="50">
						&nbsp;Rows per Page:&nbsp;
					</td>
					<td width="1">
						<select name="graph_rows" onChange="applyGraphsFilterChange(document.form_autom8_graph)">
							<option value="-1"<?php if (get_request_var_request("graph_rows") == "-1") {?> selected<?php }?>>Default</option>
							<?php
							if (sizeof($item_rows) > 0) {
							foreach ($item_rows as $key => $value) {
								print "<option value='" . $key . "'"; if (get_request_var_request("graph_rows") == $key) { print " selected"; } print ">" . $value . "</option>\n";
							}
							}
							?>
						</select>
					</td>
				</tr>
			</table>
		</td>
	</tr>
	<?php

	html_end_box(false);
	form_hidden_box("gpage", '1', '');

	/* form the 'where' clause for our main sql query */
	if (strlen(get_request_var_request("filter"))) {
		$sql_where = "AND (graph_templates_graph.title_cache like '%%" . get_request_var_request("filter") . "%%'" .
			" OR graph_templates.name like '%%" . get_request_var_request("filter") . "%%')";
	}else{
		$sql_where = "";
	}

	if (get_request_var_request("host_id") == "-1") {
		/* Show all items */
	}elseif (get_request_var_request("host_id") == "0") {
		$sql_where .= " AND graph_local.host_id=0";
	}elseif (!empty($_REQUEST["host_id"])) {
		$sql_where .= " AND graph_local.host_id=" . get_request_var_request("host_id");
	}

	if (get_request_var_request("template_id") == "-1") {
		/* Show all items */
	}elseif (get_request_var_request("template_id") == "0") {
		$sql_where .= " AND graph_templates_graph.graph_template_id=0";
	}elseif (!empty($_REQUEST["template_id"])) {
		$sql_where .= " AND graph_templates_graph.graph_template_id=" . get_request_var_request("template_id");
	}

	/* get the WHERE clause for matching graphs */
	$sql_filter = build_matching_objects_filter($rule["id"], $rule_type);

	html_start_box("", "100%", $colors["header"], "3", "center", "");

	$total_rows = db_fetch_cell("SELECT " .
		"COUNT(graph_templates_graph.id) " .
		"FROM (graph_local,graph_templates_graph) " .
		"LEFT JOIN graph_templates ON (graph_local.graph_template_id=graph_templates.id) " .
		"LEFT JOIN host ON (graph_local.host_id = host.id) " .
		"LEFT JOIN host_template ON (host.host_template_id = host_template.id) " .
		"WHERE graph_local.id=graph_templates_graph.local_graph_id " .
		"$sql_where AND ($sql_filter)");

	$sql = "SELECT host.id AS host_id, " .
		"host.hostname, " .
		"host.description, " .
		"host.disabled, " .
		"host.status, " .
		"host_template.name AS host_template_name, " .
		"graph_templates_graph.id, " .
		"graph_templates_graph.local_graph_id, " .
		"graph_templates_graph.height, " .
		"graph_templates_graph.width, " .
		"graph_templates_graph.title_cache, " .
		"graph_templates.name " .
		"FROM (graph_local,graph_templates_graph) " .
		"LEFT JOIN graph_templates ON (graph_local.graph_template_id=graph_templates.id) " .
		"LEFT JOIN host ON (graph_local.host_id = host.id) " .
		"LEFT JOIN host_template ON (host.host_template_id = host_template.id) " .
		"WHERE graph_local.id=graph_templates_graph.local_graph_id " .
		" $sql_where AND $sql_filter " .
		" ORDER BY " . $_REQUEST["sort_column"] . " " . get_request_var_request("sort_direction") .
		" LIMIT " . (get_request_var_request("graph_rows")*(get_request_var_request("gpage")-1)) . "," . get_request_var_request("graph_rows");
	$graph_list = db_fetch_assoc($sql);
	#print "<pre>"; print($sql); print_r($graph_list); print "</pre>";

	/* generate page list */
	$url_page_select = get_page_list(get_request_var_request("gpage"), MAX_DISPLAY_PAGES, get_request_var_request("graph_rows"), $total_rows, $url . "&filter=" . get_request_var_request("filter") . "&host_id=" . get_request_var_request("host_id"), "gpage");

	$nav = "<tr bgcolor='#" . $colors["header"] . "'>
			<td colspan='7'>
				<table width='100%' cellspacing='0' cellpadding='0' border='0'>
					<tr>
						<td align='left' class='textHeaderDark'>
							<strong>&lt;&lt; "; if (get_request_var_request("gpage") > 1) { $nav .= "<a class='linkOverDark' href='" . htmlspecialchars($url . "&filter=" . get_request_var_request("filter") . "&host_id=" . get_request_var_request("host_id") . "&gpage=" . (get_request_var_request("gpage")-1)) . "'>"; } $nav .= "Previous"; if (get_request_var_request("gpage") > 1) { $nav .= "</a>"; } $nav .= "</strong>
						</td>\n
						<td align='center' class='textHeaderDark'>
							Showing Rows " . ((get_request_var_request("graph_rows")*(get_request_var_request("gpage")-1))+1) . " to " . ((($total_rows < get_request_var_request("graph_rows")) || ($total_rows < (get_request_var_request("graph_rows")*get_request_var_request("gpage")))) ? $total_rows : (get_request_var_request("graph_rows")*get_request_var_request("gpage"))) . " of $total_rows [$url_page_select]
						</td>\n
						<td align='right' class='textHeaderDark'>
							<strong>"; if ((get_request_var_request("gpage") * get_request_var_request("graph_rows")) < $total_rows) { $nav .= "<a class='linkOverDark' href='" . htmlspecialchars($url . "&filter=" . get_request_var_request("filter") . "&host_id=" . get_request_var_request("host_id") . "&gpage=" . (get_request_var_request("gpage")+1)) . "'>"; } $nav .= "Next"; if ((get_request_var_request("gpage") * get_request_var_request("graph_rows")) < $total_rows) { $nav .= "</a>"; } $nav .= " &gt;&gt;</strong>
						</td>\n
					</tr>
				</table>
			</td>
		</tr>\n";

	print $nav;

	$display_text = array(
		"description" => array("Host Description", "ASC"),
		"hostname" => array("Hostname", "ASC"),
		"host_template_name" => array("Host Template Name", "ASC"),
		"status" => array("Status", "ASC"),
		"title_cache" => array("Graph Title", "ASC"),
		"local_graph_id" => array("Graph ID", "ASC"),
		"name" => array("Graph Template Name", "ASC"),
		);

	html_header_sort_url($display_text, get_request_var_request("sort_column"), get_request_var_request("sort_direction"), 1, $url);

	$i = 0;
	if (sizeof($graph_list) > 0) {
		foreach ($graph_list as $graph) {
			$template_name = ((empty($graph["name"])) ? "<em>None</em>" : $graph["name"]);
			form_alternate_row_color($colors["alternate"], $colors["light"], $i, 'line' . $graph["local_graph_id"]); $i++;

			form_selectable_cell("<a class='linkEditMain' href='" . htmlspecialchars("../../host.php?action=edit&id=" . $graph["host_id"]) . "'>" .
				(strlen(get_request_var_request("filter")) ? reg_replace("/(" . preg_quote(get_request_var_request("filter")) . ")/i", "<span style='background-color: #F8D93D;'>\\1</span>", $graph["description"]) : $graph["description"]) . "</a>", $graph["host_id"]);
			form_selectable_cell((strlen(get_request_var_request("filter")) ? preg_replace("/(" . preg_quote(get_request_var_request("filter")) . ")/i", "<span style='background-color: #F8D93D;'>\\1</span>", $graph["hostname"]) : $graph["hostname"]), $graph["host_id"]);
			form_selectable_cell((strlen(get_request_var_request("filter")) ? preg_replace("/(" . preg_quote(get_request_var_request("filter")) . ")/i", "<span style='background-color: #F8D93D;'>\\1</span>", $graph["host_template_name"]) : $graph["host_template_name"]), $graph["host_id"]);
			form_selectable_cell(get_colored_device_status(($graph["disabled"] == "on" ? true : false), $graph["status"]), $graph["host_id"]);

			form_selectable_cell("<a class='linkEditMain' href='" . htmlspecialchars("../../graphs.php?action=graph_edit&id=" . $graph["local_graph_id"]) . "' title='" . htmlspecialchars($graph["title_cache"]) . "'>" . ((get_request_var_request("filter") != "") ? preg_replace("/(" . preg_quote(get_request_var_request("filter")) . ")/i", "<span style='background-color: #F8D93D;'>\\1</span>", title_trim($graph["title_cache"], read_config_option("max_title_graph"))) : title_trim($graph["title_cache"], read_config_option("max_title_graph"))) . "</a>", $graph["local_graph_id"]);
			form_selectable_cell($graph["local_graph_id"], $graph["local_graph_id"]);
			form_selectable_cell(((get_request_var_request("filter") != "") ? preg_replace("(" . preg_quote(get_request_var_request("filter")) . ")/i", "<span style='background-color: #F8D93D;'>\\1</span>", $template_name) : $template_name) . "</a>", $graph["local_graph_id"]);
			form_end_row();
		}

		/* put the nav bar on the bottom as well */
		print $nav;
	}else{
		print "<tr><td><em>No Graphs Found</em></td></tr>";
	}

	html_end_box(true);

	print "</form>\n";
}


function display_new_graphs($rule) {
	global $colors, $config;

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var_request("gpage"));
	/* ==================================================== */

	/* if the user pushed the 'clear' button */
	if (isset($_REQUEST["clear_x"])) {
		kill_session_var("sess_autom8_graph_current_page");

		unset($_REQUEST["gpage"]);
	}

	/* remember these search fields in session vars so we don't have to keep passing them around */
	load_current_session_value("gpage", "sess_autom8_graph_current_page", "1");

	$rule_items = array();
	$created_graphs = array();
	$created_graphs = get_created_graphs($rule);
	#print "<pre>Created Graphs: <br>"; print_r($created_graphs); print "</pre>";

	$total_rows = 0;
	$num_input_fields = 0;
	$num_visible_fields = 0;
	$row_limit = read_config_option("num_rows_device");

	/* if any of the settings changed, reset the page number */
	$changed = 0;
	$changed += check_changed("id",					"sess_autom8_graph_rule_id");
	$changed += check_changed("snmp_query_id",		"sess_autom8_graph_rule_snmp_query_id");

	if (!$changed) {
		$page = $_REQUEST["gpage"];
	}else{
		$page = 1;
	}

	$sql = "SELECT " .
		"snmp_query.id, " .
		"snmp_query.name, " .
		"snmp_query.xml_path " .
		"FROM snmp_query " .
		"WHERE snmp_query.id = " . $rule["snmp_query_id"];

	$snmp_query = db_fetch_row($sql);
	#print "<pre>SNMP Query: $sql<br>"; print_r($snmp_query); print "</pre>";


	/*
	 * determine number of input fields, if any
	 * for a dropdown selection
	 */
	$xml_array = get_data_query_array($rule["snmp_query_id"]);
	if ($xml_array != false) {
		/* loop through once so we can find out how many input fields there are */
		reset($xml_array["fields"]);
		while (list($field_name, $field_array) = each($xml_array["fields"])) {
			if ($field_array["direction"] == "input") {
				$num_input_fields++;

				if (!isset($total_rows)) {
					$sql = "SELECT " .
							"count(*) " .
							"FROM host_snmp_cache " .
							"WHERE snmp_query_id=" . $rule["snmp_query_id"] . " " .
							"AND field_name='$field_name'";
					$total_rows = db_fetch_cell($sql);
				}
			}
		}
	}
	if (!isset($total_rows)) {
		$total_rows = 0;
	}
	#print "<pre>Total Rows: $total_rows<br>"; print "</pre>";



	print "	<table width='100%' style='background-color: #" . $colors["form_alternate2"] . "; border: 1px solid #" . $colors["header"] . ";' align='center' cellpadding='3' cellspacing='0'>\n
		<tr>
			<td bgcolor='#" . $colors["header"] . "' colspan='" . ($num_input_fields+2) . "'>
				<table  cellspacing='0' cellpadding='0' width='100%' >
					<tr>
						<td class='textHeaderDark'>
							<strong>Data Query</strong> [" . $snmp_query["name"] . "]
						</td>
					</tr>
				</table>
			</td>
		</tr>";


	if ($xml_array != false) {
		$html_dq_header = "";
		$snmp_query_indexes = array();
		$sql = "SELECT * " .
			"FROM plugin_autom8_graph_rule_items " .
			"WHERE rule_id=" . $rule["id"] .
			" ORDER BY sequence";
		$rule_items = db_fetch_assoc($sql);
		#print "<pre>Items: $sql<br>"; print_r($rule_items); print "</pre>";


		/*
		 * main sql
		 */
		if (isset($xml_array["index_order_type"])) {
			$sql_order = build_sort_order($xml_array["index_order_type"], "autom8_host");
			#print "<pre>Order: $sql_order<br>";print "</pre>";
			$sql_query = build_data_query_sql($rule) . " " . $sql_order;
		} else {
			$sql_query = build_data_query_sql($rule);
		}
		#print "<pre>Query: $sql_query<br>";print "</pre>";
		$sql_filter	= build_rule_item_filter($rule_items, "a.");
		#print "<pre>Filter: $sql_filter<br>";print "</pre>";

		/* now we build up a new query for counting the rows */
		$rows_query = "SELECT * FROM (" . $sql_query . ") AS a WHERE (" . $sql_filter . ")";
		$total_rows = sizeof(db_fetch_assoc($rows_query));
		if ($total_rows < (get_request_var_request("graph_rule_rows")*(get_request_var_request("gpage")-1))+1) {
			$_REQUEST["gpage"] = 1;
		}
		#print "<pre>Rows Query: $rows_query<br>";print "</pre>";
		$sql_query = $rows_query . " LIMIT " . ($row_limit*(get_request_var_request("gpage")-1)) . "," . $row_limit;
		#print "<pre>SQL Query: $sql_query<br>";print "</pre>";
		$snmp_query_indexes = db_fetch_assoc($sql_query);
		#print "<pre>SNMP Query Indexes: "; print_r($snmp_query_indexes); print "</pre>";



		/*
		 * nav bar magic
		 */
		#if ($total_rows > $row_limit) {
			/* generate page list */
			$url_page_select = get_page_list(get_request_var_request("gpage"), MAX_DISPLAY_PAGES, $row_limit, $total_rows, "autom8_graph_rules.php?action=edit&id=" . $rule["id"], "gpage");

			$nav = "<tr bgcolor='#" . $colors["header"] . "'>
					<td colspan='11'>
						<table width='100%' cellspacing='0' cellpadding='0' border='0'>
							<tr>
								<td align='left' class='textHeaderDark'>
									<strong>&lt;&lt; "; if (get_request_var_request("gpage") > 1) { $nav .= "<a class='linkOverDark' href='" . htmlspecialchars("autom8_graph_rules.php?action=edit&id=" . $rule["id"] . "&gpage=" . (get_request_var_request("gpage")-1)) . "'>"; } $nav .= "Previous"; if (get_request_var_request("gpage") > 1) { $nav .= "</a>"; } $nav .= "</strong>
								</td>\n
								<td align='center' class='textHeaderDark'>
									Showing Rows " . ((get_request_var_request("graph_rule_rows")*(get_request_var_request("gpage")-1))+1) . " to " . ((($total_rows < read_config_option("num_rows_device")) || ($total_rows < (get_request_var_request("graph_rule_rows")*get_request_var_request("gpage")))) ? $total_rows : (get_request_var_request("graph_rule_rows")*get_request_var_request("gpage"))) . " of $total_rows [$url_page_select]
								</td>\n
								<td align='right' class='textHeaderDark'>
									<strong>"; if ((get_request_var_request("gpage") * get_request_var_request("graph_rule_rows")) < $total_rows) { $nav .= "<a class='linkOverDark' href='" . htmlspecialchars("autom8_graph_rules.php?action=edit&id=" . $rule["id"] . "&gpage=" . (get_request_var_request("gpage")+1)) . "'>"; } $nav .= "Next"; if ((get_request_var_request("gpage") * get_request_var_request("graph_rule_rows")) < $total_rows) { $nav .= "</a>"; } $nav .= " &gt;&gt;</strong>
								</td>\n
							</tr>
						</table>
					</td>
				</tr>\n";

			print $nav;
		#}


		/*
		 * print the Data Query table's header
		 * number of fields has to be dynamically determined
		 * from the Data Query used
		 */
		# we want to print the host name as the first column
		$new_fields["autom8_host"] = array("name" => "Hostname", "direction" => "input");
		$new_fields["status"] = array("name" => "Host Status", "direction" => "input");
		$xml_array["fields"] = $new_fields + $xml_array["fields"];
		reset($xml_array["fields"]);

		$field_names = get_field_names($rule["snmp_query_id"]);
		array_unshift($field_names, array("field_name" => "status"));
		array_unshift($field_names, array("field_name" => "autom8_host"));
		#print "<pre>Fields:<br>"; print_r($xml_array); print_r($field_names); print "</pre>";

		while (list($field_name, $field_array) = each($xml_array["fields"])) {
			if ($field_array["direction"] == "input") {
				foreach($field_names as $row) {
					if ($row["field_name"] == $field_name) {
						$html_dq_header .= "<td height='1'><strong><font color='#" . $colors["header_text"] . "'>" . $field_array["name"] . "</font></strong></td>\n";
						break;
					}
				}
			}
		}

		if (!sizeof($snmp_query_indexes)) {
			print "<tr bgcolor='#" . $colors["form_alternate1"] . "'><td>There are no matching rows for this Filter Rule.</td></tr>\n";
		}else{
			print "<tr bgcolor='#" . $colors["header_panel"] . "'>" . $html_dq_header . "</tr>\n";
		}


		/*
		 * list of all entries
		 */
		$row_counter    = 0;
		$fields         = array_rekey($field_names, "field_name", "field_name");
		if (sizeof($snmp_query_indexes) > 0) {
			foreach($snmp_query_indexes as $row) {
				#$query_row = $snmp_query["id"] . "_" . encode_data_query_index($row["snmp_index"]);
				#print "<tr id='line$query_row' bgcolor='#" . (($row_counter % 2 == 0) ? "ffffff" : $colors["light"]) . "'>"; $i++;
				print "<tr id='line$row_counter' bgcolor='#" . (($row_counter % 2 == 0) ? "ffffff" : $colors["light"]) . "'>";

				# mark rows
				if (isset($created_graphs{$row["host_id"]}{$row["snmp_index"]})) {
					$style = ' style="color: grey"';
				} else {
					$style = ' style="color: black"';
				}
				$column_counter = 0;
				reset($xml_array["fields"]);
				while (list($field_name, $field_array) = each($xml_array["fields"])) {
					if ($field_array["direction"] == "input") {
						if (in_array($field_name, $fields)) {
							if (isset($row[$field_name])) {
								if ($field_name == "status") {
									form_selectable_cell(get_colored_device_status(($row["disabled"] == "on" ? true : false), $row["status"]), "status");
								} else {
									print "<td><span id='text$row_counter" . "_" . $column_counter . "' $style>" . $row[$field_name] . "</span></td>";
								}
							}else{
								print "<td><span id='text$row_counter" . "_" . $column_counter . "' $style></span></td>";
							}
							$column_counter++;
						}
					}
				}
				print "</tr>\n";
				$row_counter++;
			}
		}
		if ($total_rows > $row_limit) {print $nav;}

	} else {
		print "<tr bgcolor='#" . $colors["form_alternate1"] . "'><td colspan='2' style='color: red; font-size: 12px; font-weight: bold;'>Error in data query.</td></tr>\n";
	}

	print "</table>";
	print "<br>";

}


function display_matching_trees ($rule_id, $rule_type, $item, $url) {
	global $autom8_tree_header_types;
	global $colors, $device_actions, $item_rows;
	#print "<pre>"; print "Post:"; print_r($_POST); print("Get: "); print_r($_GET); print("Request: "); print_r($_REQUEST); print "</pre>";
	autom8_log(__FUNCTION__ . " called: $rule_id/$rule_type", false, "AUTOM8 TRACE", POLLER_VERBOSITY_MEDIUM);
	
	/* ================= input validation ================= */
	input_validate_input_number(get_request_var_request("host_template_id"));
	input_validate_input_number(get_request_var_request("tpage"));
	input_validate_input_number(get_request_var_request("host_status"));
	input_validate_input_number(get_request_var_request("host_rows"));
	/* ==================================================== */

	/* clean up search string */
	if (isset($_REQUEST["filter"])) {
		$_REQUEST["filter"] = sanitize_search_string(get_request_var_request("filter"));
	}

	/* clean up sort_column */
	if (isset($_REQUEST["sort_column"])) {
		$_REQUEST["sort_column"] = sanitize_search_string(get_request_var_request("sort_column"));
	}

	/* clean up search string */
	if (isset($_REQUEST["sort_direction"])) {
		$_REQUEST["sort_direction"] = sanitize_search_string(get_request_var_request("sort_direction"));
	}

	/* if the user pushed the 'clear' button */
	if (isset($_REQUEST["clear_x"])) {
		kill_session_var("sess_autom8_tree_current_page");
		kill_session_var("sess_autom8_tree_filter");
		kill_session_var("sess_autom8_tree_host_template_id");
		kill_session_var("sess_autom8_tree_host_status");
		kill_session_var("sess_autom8_tree_rows");
		kill_session_var("sess_autom8_tree_sort_column");
		kill_session_var("sess_autom8_tree_sort_direction");

		unset($_REQUEST["tpage"]);
		unset($_REQUEST["filter"]);
		unset($_REQUEST["host_template_id"]);
		unset($_REQUEST["host_status"]);
		unset($_REQUEST["host_rows"]);
		unset($_REQUEST["sort_column"]);
		unset($_REQUEST["sort_direction"]);
	}

	if ((!empty($_SESSION["sess_autom8_host_status"])) && (!empty($_REQUEST["host_status"]))) {
		if ($_SESSION["sess_autom8_host_status"] != $_REQUEST["host_status"]) {
			$_REQUEST["tpage"] = 1;
		}
	}

	/* remember these search fields in session vars so we don't have to keep passing them around */
	load_current_session_value("tpage", "sess_autom8_tree_current_page", "1");
	load_current_session_value("filter", "sess_autom8_tree_filter", "");
	load_current_session_value("host_template_id", "sess_autom8_tree_host_template_id", "-1");
	load_current_session_value("host_status", "sess_autom8_tree_host_status", "-1");
	load_current_session_value("host_rows", "sess_autom8_tree_rows", read_config_option("num_rows_device"));
	load_current_session_value("sort_column", "sess_autom8_tree_sort_column", "description");
	load_current_session_value("sort_direction", "sess_autom8_tree_sort_direction", "ASC");

	/* if the number of rows is -1, set it to the default */
	if ($_REQUEST["host_rows"] == -1) {
		$_REQUEST["host_rows"] = read_config_option("num_rows_device");
	}

	?>
	<script type="text/javascript">
	<!--

	function applyViewDeviceFilterChange(objForm) {
		strURL = '<?php print $url;?>' + '&host_status=' + objForm.host_status.value;
		strURL = strURL + '&host_template_id=' + objForm.host_template_id.value;
		strURL = strURL + '&host_rows=' + objForm.host_rows.value;
		strURL = strURL + '&filter=' + objForm.filter.value;
		document.location = strURL;
	}

	-->
	</script>
	<?php

	print "<form method='post' name='form_autom8_tree' action='" . htmlspecialchars($url) . "'>";
	html_start_box("<strong>Matching Items</strong>", "100%", $colors["header"], "3", "center", "");

	?>
	<tr bgcolor="<?php print $colors["panel"];?>">
		<td>
			<table width="100%" cellpadding="0" cellspacing="0">
				<tr>
					<td nowrap style='white-space: nowrap;' width="50">
						Type:&nbsp;
					</td>
					<td width="1">
						<select name="host_template_id" onChange="applyViewDeviceFilterChange(document.form_autom8_tree)">
							<option value="-1"<?php if (get_request_var_request("host_template_id") == "-1") {?> selected<?php }?>>Any</option>
							<option value="0"<?php if (get_request_var_request("host_template_id") == "0") {?> selected<?php }?>>None</option>
							<?php
							$host_templates = db_fetch_assoc("select id,name from host_template order by name");

							if (sizeof($host_templates) > 0) {
							foreach ($host_templates as $host_template) {
								print "<option value='" . $host_template["id"] . "'"; if (get_request_var_request("host_template_id") == $host_template["id"]) { print " selected"; } print ">" . $host_template["name"] . "</option>\n";
							}
							}
							?>
						</select>
					</td>
					<td nowrap style='white-space: nowrap;' width="50">
						&nbsp;Status:&nbsp;
					</td>
					<td width="1">
						<select name="host_status" onChange="applyViewDeviceFilterChange(document.form_autom8_tree)">
							<option value="-1"<?php if (get_request_var_request("host_status") == "-1") {?> selected<?php }?>>Any</option>
							<option value="-3"<?php if (get_request_var_request("host_status") == "-3") {?> selected<?php }?>>Enabled</option>
							<option value="-2"<?php if (get_request_var_request("host_status") == "-2") {?> selected<?php }?>>Disabled</option>
							<option value="-4"<?php if (get_request_var_request("host_status") == "-4") {?> selected<?php }?>>Not Up</option>
							<option value="3"<?php if (get_request_var_request("host_status") == "3") {?> selected<?php }?>>Up</option>
							<option value="1"<?php if (get_request_var_request("host_status") == "1") {?> selected<?php }?>>Down</option>
							<option value="2"<?php if (get_request_var_request("host_status") == "2") {?> selected<?php }?>>Recovering</option>
							<option value="0"<?php if (get_request_var_request("host_status") == "0") {?> selected<?php }?>>Unknown</option>
						</select>
					</td>
					<td nowrap>
						&nbsp;<input type="image" src="../../images/button_go.gif" alt="Go" align="middle">
						<input type="image" src="../../images/button_clear.gif" name="clear" alt="Clear" align="middle">
					</td>
				</tr>
				<tr>
					<td nowrap style='white-space: nowrap;' width="20">
						Search:&nbsp;
					</td>
					<td width="1">
						<input type="text" name="filter" size="30" value="<?php print get_request_var_request("filter");?>">
					</td>
					<td nowrap style='white-space: nowrap;' width="50">
						&nbsp;Rows per Page:&nbsp;
					</td>
					<td width="1">
						<select name="host_rows" onChange="applyViewDeviceFilterChange(document.form_autom8_tree)">
							<option value="-1"<?php if (get_request_var_request("host_rows") == "-1") {?> selected<?php }?>>Default</option>
							<?php
							if (sizeof($item_rows) > 0) {
							foreach ($item_rows as $key => $value) {
								print "<option value='" . $key . "'"; if (get_request_var_request("host_rows") == $key) { print " selected"; } print ">" . $value . "</option>\n";
							}
							}
							?>
						</select>
					</td>
				</tr>
			</table>
		</td>
	</tr>
	<?php

	html_end_box(false);
	form_hidden_box("tpage", '1', '');

	/* build magic query, for matching hosts JOIN tables host and host_template */
	$leaf_type = db_fetch_cell("SELECT leaf_type FROM plugin_autom8_tree_rules WHERE id=" . $rule_id);
	if ($leaf_type == TREE_ITEM_TYPE_HOST) {
		$sql_tables = "FROM host " .
			"LEFT JOIN host_template ON (host.host_template_id = host_template.id) ";

		$sql_where = "WHERE 1=1 ";
	} elseif ($leaf_type == TREE_ITEM_TYPE_GRAPH) {
		$sql_tables = "FROM host " .
			"LEFT JOIN host_template ON (host.host_template_id = host_template.id) " .
			"LEFT JOIN graph_local ON (host.id = graph_local.host_id) " .
			"LEFT JOIN graph_templates ON (graph_local.graph_template_id=graph_templates.id) " .
			"LEFT JOIN graph_templates_graph ON (graph_local.id = graph_templates_graph.local_graph_id) ";

		$sql_where = "WHERE graph_templates_graph.local_graph_id > 0 ";
	}

	/* form the 'where' clause for our main sql query */
	if (strlen(get_request_var_request("filter"))) {
		$sql_where .= " AND (host.hostname LIKE '%%" . get_request_var_request("filter") . "%%' OR host.description LIKE '%%" . get_request_var_request("filter") . "%%' OR host_template.name LIKE '%%" . get_request_var_request("filter") . "%%')";
	}

	if (get_request_var_request("host_status") == "-1") {
		/* Show all items */
	}elseif (get_request_var_request("host_status") == "-2") {
		$sql_where .= " AND host.disabled='on'";
	}elseif (get_request_var_request("host_status") == "-3") {
		$sql_where .= " AND host.disabled=''";
	}elseif (get_request_var_request("host_status") == "-4") {
		$sql_where .= " AND (host.status!='3' or host.disabled='on')";
	}else {
		$sql_where .= " AND (host.status=" . get_request_var_request("host_status") . " AND host.disabled = '')";
	}

	if (get_request_var_request("host_template_id") == "-1") {
		/* Show all items */
	}elseif (get_request_var_request("host_template_id") == "0") {
		$sql_where .= " AND host.host_template_id=0";
	}elseif (!empty($_REQUEST["host_template_id"])) {
		$sql_where .= " AND host.host_template_id=" . get_request_var_request("host_template_id");
	}

	/* get the WHERE clause for matching hosts */
	$sql_filter = build_matching_objects_filter($rule_id, AUTOM8_RULE_TYPE_TREE_MATCH);

	/*
	 * display list of matching items ------------------------------------------------------------------------
	 */
	$templates = array();
	$sql_field = $item["field"] . " AS source ";

	/* now we build up a new query for counting the rows */
	$rows_query = "SELECT host.id AS host_id, " .
				"host.hostname, " .
				"host.description, " .
				"host.disabled, " .
				"host.status, " .
				"host_template.name AS host_template_name, " .
				$sql_field . $sql_tables . $sql_where . " AND (" . $sql_filter . ")";
	$total_rows = sizeof(db_fetch_assoc($rows_query));
	#print "<pre>Rows Query: $rows_query<br>Total Rows: "; print($total_rows); print "</pre>";

	$sortby = get_request_var_request("sort_column");
	if ($sortby=="host.hostname") {
		$sortby = "INET_ATON(host.hostname)";
	}

	$sql_query = $rows_query .
		" ORDER BY " . $sortby . " " . get_request_var_request("sort_direction") .
		" LIMIT " . (get_request_var_request("host_rows")*(get_request_var_request("tpage")-1)) . "," . get_request_var_request("host_rows");
	$templates = db_fetch_assoc($sql_query);
	#print "<pre>Items: "; print_r($templates); print "</pre>";
	autom8_log(__FUNCTION__ . " templates sql: $sql_query", false, "AUTOM8 TRACE", POLLER_VERBOSITY_MEDIUM);
	
	/* generate page list */
	$url_page_select = get_page_list(get_request_var_request("tpage"), MAX_DISPLAY_PAGES, get_request_var_request("host_rows"), $total_rows, $url . "&filter=" . get_request_var_request("filter") . "&host_template_id=" . get_request_var_request("host_template_id") . "&host_status=" . get_request_var_request("host_status"), "tpage");

	$nav = "<tr bgcolor='#" . $colors["header"] . "'>
			<td colspan='11'>
				<table width='100%' cellspacing='0' cellpadding='0' border='0'>
					<tr>
						<td align='left' class='textHeaderDark'>
							<strong>&lt;&lt; "; if (get_request_var_request("tpage") > 1) { $nav .= "<a class='linkOverDark' href='" . htmlspecialchars($url . "&filter=" . get_request_var_request("filter") . "&host_template_id=" . get_request_var_request("host_template_id") . "&host_status=" . get_request_var_request("host_status") . "&tpage=" . (get_request_var_request("tpage")-1)) . "'>"; } $nav .= "Previous"; if (get_request_var_request("tpage") > 1) { $nav .= "</a>"; } $nav .= "</strong>
						</td>\n
						<td align='center' class='textHeaderDark'>
							Showing Rows " . ((get_request_var_request("host_rows")*(get_request_var_request("tpage")-1))+1) . " to " . ((($total_rows < read_config_option("num_rows_device")) || ($total_rows < (get_request_var_request("host_rows")*get_request_var_request("tpage")))) ? $total_rows : (get_request_var_request("host_rows")*get_request_var_request("tpage"))) . " of $total_rows [$url_page_select]
						</td>\n
						<td align='right' class='textHeaderDark'>
							<strong>"; if ((get_request_var_request("tpage") * get_request_var_request("host_rows")) < $total_rows) { $nav .= "<a class='linkOverDark' href='" . htmlspecialchars($url . "&filter=" . get_request_var_request("filter") . "&host_template_id=" . get_request_var_request("host_template_id") . "&host_status=" . get_request_var_request("host_status") . "&tpage=" . (get_request_var_request("tpage")+1)) . "'>"; } $nav .= "Next"; if ((get_request_var_request("tpage") * get_request_var_request("host_rows")) < $total_rows) { $nav .= "</a>"; } $nav .= " &gt;&gt;</strong>
						</td>\n
					</tr>
				</table>
			</td>
		</tr>\n";


	html_start_box("", "100%", $colors["header"], "3", "center", "");
	print $nav;

	$display_text = array(
		"description" => array("Description", "ASC"),
		"hostname" => array("Hostname", "ASC"),
		"host_template_name" => array("Host Template Name", "ASC"),
		"status" => array("Status", "ASC"),
		"source" => array($item["field"], "ASC"),
		"result" => array("Result", "ASC"),
	);

	html_header_sort_url($display_text, get_request_var_request("sort_column"), get_request_var_request("sort_direction"), 1, $url);

	$i = 0;
	if (sizeof($templates) > 0) {
		foreach ($templates as 	$template) {
			autom8_log(__FUNCTION__ . " template: " . serialize($template), false, "AUTOM8 TRACE", POLLER_VERBOSITY_MEDIUM);
			$replacement = autom8_string_replace($item["search_pattern"], $item["replace_pattern"], $template["source"]);
			/* build multiline <td> entry */

			$repl = "";
			for ($j=0; sizeof($replacement); $j++) {
				if ($j > 0) {
					$repl .= "<br>";
					$repl .= str_pad("", $j*3, "-") . "&nbsp;" . array_shift($replacement);
				} else {
					$repl  = array_shift($replacement);
				}
			}
			autom8_log(__FUNCTION__ . " replacement: $repl", false, "AUTOM8 TRACE", POLLER_VERBOSITY_MEDIUM);
			
			form_alternate_row_color($colors["alternate"], $colors["light"], $i, 'line' . $template["host_id"]); $i++;
			form_selectable_cell("<a class='linkEditMain' href='" . htmlspecialchars("../../host.php?action=edit&id=" . $template["host_id"]) . "'>" .
				(strlen(get_request_var_request("filter")) ? preg_replace("/(" . preg_quote(get_request_var_request("filter")) . ")/i", "<span style='background-color: #F8D93D;'>\\1</span>", $template["description"]) : $template["description"]) . "</a>", $template["host_id"]);
			form_selectable_cell((strlen(get_request_var_request("filter")) ? preg_replace("/(" . preg_quote(get_request_var_request("filter")) . ")/i", "<span style='background-color: #F8D93D;'>\\1</span>", $template["hostname"]) : $template["hostname"]), $template["host_id"]);
			form_selectable_cell((strlen(get_request_var_request("filter")) ? preg_replace("/(" . preg_quote(get_request_var_request("filter")) . ")/i", "<span style='background-color: #F8D93D;'>\\1</span>", $template["host_template_name"]) : $template["host_template_name"]), $template["host_id"]);
			form_selectable_cell(get_colored_device_status(($template["disabled"] == "on" ? true : false), $template["status"]), $template["host_id"]);
			form_selectable_cell($template["source"], $template["host_id"]);
			form_selectable_cell($repl, $template["host_id"]);
			form_end_row();
		}

		/* put the nav bar on the bottom as well */
		print $nav;
	}else{
		print "<tr><td><em>No Items</em></td></tr>";
	}
	html_end_box(true);

	print "</form>\n";
}


function display_match_rule_items($title, $rule_id, $rule_type, $module) {
	global $colors, $autom8_op_array, $autom8_oper, $autom8_tree_header_types;

	$items = db_fetch_assoc("SELECT * " .
					"FROM plugin_autom8_match_rule_items " .
					"WHERE rule_id=" . $rule_id .
					" AND rule_type=" . $rule_type .
					" ORDER BY sequence");

	html_start_box("<strong>$title</strong>", "100%", $colors["header"], "3", "center", $module . "?action=item_edit&id=" . $rule_id . "&rule_type=" . $rule_type);

	print "<tr bgcolor='#" . $colors["header_panel"] . "'>";
	DrawMatrixHeaderItem("Item",$colors["header_text"],1);
	DrawMatrixHeaderItem("Sequence",$colors["header_text"],1);
	DrawMatrixHeaderItem("Operation",$colors["header_text"],1);
	DrawMatrixHeaderItem("Field",$colors["header_text"],1);
	DrawMatrixHeaderItem("Operator",$colors["header_text"],1);
	DrawMatrixHeaderItem("Pattern",$colors["header_text"],1);
	DrawMatrixHeaderItem("&nbsp;",$colors["header_text"],2);
	print "</tr>";

	$i = 0;
	if (sizeof($items) > 0) {
		foreach ($items as $item) {
			#print "<pre>"; print_r($item); print "</pre>";
			$operation = ($item["operation"] != 0) ? $autom8_oper{$item["operation"]} : "&nbsp;";

			form_alternate_row_color($colors["alternate"],$colors["light"],$i); $i++;
			$form_data = '<td><a class="linkEditMain" href="' . htmlspecialchars($module . "?action=item_edit&id=" . $rule_id. "&item_id=" . $item["id"] . "&rule_type=" . $rule_type) . '">Item#' . $i . '</a></td>';
			$form_data .= '<td>' . 	$item["sequence"] . '</td>';
			$form_data .= '<td>' . 	$operation . '</td>';
			$form_data .= '<td>' . 	$item["field"] . '</td>';
			$form_data .= '<td>' . 	((isset($item["operator"]) && $item["operator"] > 0) ? $autom8_op_array["display"]{$item["operator"]} : "") . '</td>';
			$form_data .= '<td>' . 	$item["pattern"] . '</td>';
			$form_data .= '<td><a href="' . htmlspecialchars($module . '?action=item_movedown&item_id=' . $item["id"] . '&id=' . $rule_id .	'&rule_type=' . $rule_type) .
							'"><img src="../../images/move_down.gif" border="0" alt="Move Down"></a>' .
							'<a	href="' . htmlspecialchars($module . '?action=item_moveup&item_id=' . $item["id"] .	'&id=' . $rule_id .	'&rule_type=' . $rule_type) .
							'"><img src="../../images/move_up.gif" border="0" alt="Move Up"></a>' . '</td>';
			$form_data .= '<td align="right"><a href="' . htmlspecialchars($module . '?action=item_remove&item_id=' . $item["id"] .	'&id=' . $rule_id .	'&rule_type=' . $rule_type) .
							'"><img src="../../images/delete_icon.gif" border="0" width="10" height="10" alt="Delete"></a>' . '</td></tr>';
			print $form_data;
		}
	} else {
		print "<tr><td><em>No Rule Items</em></td></tr>\n";
	}

	html_end_box(true);

}


function display_graph_rule_items($title, $rule_id, $rule_type, $module) {
	global $colors, $autom8_op_array, $autom8_oper, $autom8_tree_header_types;

	$items = db_fetch_assoc("SELECT * " .
					"FROM plugin_autom8_graph_rule_items " .
					"WHERE rule_id=" . $rule_id .
					" ORDER BY sequence");

	html_start_box("<strong>$title</strong>", "100%", $colors["header"], "3", "center", $module . "?action=item_edit&id=" . $rule_id . "&rule_type=" . $rule_type);

	print "<tr bgcolor='#" . $colors["header_panel"] . "'>";
	DrawMatrixHeaderItem("Item",$colors["header_text"],1);
	DrawMatrixHeaderItem("Sequence",$colors["header_text"],1);
	DrawMatrixHeaderItem("Operation",$colors["header_text"],1);
	DrawMatrixHeaderItem("Field",$colors["header_text"],1);
	DrawMatrixHeaderItem("Operator",$colors["header_text"],1);
	DrawMatrixHeaderItem("Pattern",$colors["header_text"],1);
	DrawMatrixHeaderItem("&nbsp;",$colors["header_text"],2);
	print "</tr>";

	$i = 0;
	if (sizeof($items) > 0) {
		foreach ($items as $item) {
			#print "<pre>"; print_r($item); print "</pre>";
			$operation = ($item["operation"] != 0) ? $autom8_oper{$item["operation"]} : "&nbsp;";

			form_alternate_row_color($colors["alternate"],$colors["light"],$i); $i++;
			$form_data = '<td><a class="linkEditMain" href="' . htmlspecialchars($module . "?action=item_edit&id=" . $rule_id. "&item_id=" . $item["id"] . "&rule_type=" . $rule_type) . '">Item#' . $i . '</a></td>';
			$form_data .= '<td>' . 	$item["sequence"] . '</td>';
			$form_data .= '<td>' . 	$operation . '</td>';
			$form_data .= '<td>' . 	$item["field"] . '</td>';
			$form_data .= '<td>' . 	(($item["operator"] > 0 || $item["operator"] == "") ? $autom8_op_array["display"]{$item["operator"]} : "") . '</td>';
			$form_data .= '<td>' . 	$item["pattern"] . '</td>';
			$form_data .= '<td><a href="' . htmlspecialchars($module . '?action=item_movedown&item_id=' . $item["id"] . '&id=' . $rule_id .	'&rule_type=' . $rule_type) .
							'"><img src="../../images/move_down.gif" border="0" alt="Move Down"></a>' .
							'<a	href="' . htmlspecialchars($module . '?action=item_moveup&item_id=' . $item["id"] .	'&id=' . $rule_id .	'&rule_type=' . $rule_type) .
							'"><img src="../../images/move_up.gif" border="0" alt="Move Up"></a>' . '</td>';
			$form_data .= '<td align="right"><a href="' . htmlspecialchars($module . '?action=item_remove&item_id=' . $item["id"] .	'&id=' . $rule_id .	'&rule_type=' . $rule_type) .
							'"><img src="../../images/delete_icon.gif" border="0" width="10" height="10" alt="Delete"></a>' . '</td></tr>';
			print $form_data;
		}
	} else {
		print "<tr><td><em>No Rule Items</em></td></tr>\n";
	}

	html_end_box(true);

}


function display_tree_rule_items($title, $rule_id, $item_type, $rule_type, $module) {
	global $colors, $autom8_tree_header_types, $tree_sort_types, $host_group_types;

	$items = db_fetch_assoc("SELECT * " .
					"FROM plugin_autom8_tree_rule_items " .
					"WHERE rule_id=" . $rule_id .
					" ORDER BY sequence");
	#print "<pre>"; print_r($items); print "</pre>";

	html_start_box("<strong>$title</strong>", "100%", $colors["header"], "3", "center", $module . "?action=item_edit&id=" . $rule_id . "&rule_type=" . $rule_type);

	print "<tr bgcolor='#" . $colors["header_panel"] . "'>";
	DrawMatrixHeaderItem("Item",$colors["header_text"],1);
	DrawMatrixHeaderItem("Sequence",$colors["header_text"],1);
	DrawMatrixHeaderItem("Field Name",$colors["header_text"],1);
	DrawMatrixHeaderItem("Sorting Type",$colors["header_text"],1);
	DrawMatrixHeaderItem("Propagate Changes",$colors["header_text"],1);
	DrawMatrixHeaderItem("Search Pattern",$colors["header_text"],1);
	DrawMatrixHeaderItem("Replace Pattern",$colors["header_text"],1);
	DrawMatrixHeaderItem("&nbsp;",$colors["header_text"],2);
	print "</tr>";

	$i = 0;
	if (sizeof($items) > 0) {
		foreach ($items as $item) {
			#print "<pre>"; print_r($item); print "</pre>";
			$field_name = ($item["field"] === AUTOM8_TREE_ITEM_TYPE_STRING) ? $autom8_tree_header_types[AUTOM8_TREE_ITEM_TYPE_STRING] : $item["field"];

			form_alternate_row_color($colors["alternate"],$colors["light"],$i); $i++;
			$form_data = '<td><a class="linkEditMain" href="' . htmlspecialchars($module . "?action=item_edit&id=" . $rule_id. "&item_id=" . $item["id"] . "&rule_type=" . $rule_type) . '">Item#' . $i . '</a></td>';
			$form_data .= '<td>' . 	$item["sequence"] . '</td>';
			$form_data .= '<td>' . 	$field_name . '</td>';
			$form_data .= '<td>' . 	$tree_sort_types{$item["sort_type"]} . '</td>';
			$form_data .= '<td>' . 	($item["propagate_changes"] ? "Yes" : "No") . '</td>';
			$form_data .= '<td>' . 	$item["search_pattern"] . '</td>';
			$form_data .= '<td>' . 	$item["replace_pattern"] . '</td>';
			$form_data .= '<td><a href="' . htmlspecialchars($module . '?action=item_movedown&item_id=' . $item["id"] . '&id=' . $rule_id .	'&rule_type=' . $rule_type) .
							'"><img src="../../images/move_down.gif" border="0" alt="Move Down"></a>' .
							'<a	href="' . htmlspecialchars($module . '?action=item_moveup&item_id=' . $item["id"] .	'&id=' . $rule_id .	'&rule_type=' . $rule_type) .
							'"><img src="../../images/move_up.gif" border="0" alt="Move Up"></a>' . '</td>';
			$form_data .= '<td align="right"><a href="' . htmlspecialchars($module . '?action=item_remove&item_id=' . $item["id"] .	'&id=' . $rule_id .	'&rule_type=' . $rule_type) .
							'"><img src="../../images/delete_icon.gif" border="0" width="10" height="10" alt="Delete"></a>' . '</td></tr>';
			print $form_data;
		}
	} else {
		print "<tr><td><em>No Rule Items</em></td></tr>\n";
	}

	html_end_box(true);

}


function duplicate_autom8_graph_rules($_id, $_title) {
	global $fields_autom8_graph_rules_edit1, $fields_autom8_graph_rules_edit2, $fields_autom8_graph_rules_edit3;

	$rule = db_fetch_row("SELECT * FROM plugin_autom8_graph_rules WHERE id=$_id");
	$match_items = db_fetch_assoc("SELECT * FROM plugin_autom8_match_rule_items WHERE rule_id=$_id AND rule_type=" . AUTOM8_RULE_TYPE_GRAPH_MATCH);
	$rule_items = db_fetch_assoc("SELECT * FROM plugin_autom8_graph_rule_items WHERE rule_id=$_id");

	$fields_autom8_graph_rules_edit = $fields_autom8_graph_rules_edit1 + $fields_autom8_graph_rules_edit2 + $fields_autom8_graph_rules_edit3;
	$save = array();
	reset($fields_autom8_graph_rules_edit);
	while (list($field, $array) = each($fields_autom8_graph_rules_edit)) {
		if (!preg_match("/^hidden/", $array["method"])) {
			$save[$field] = $rule[$field];
		}
	}

	/* substitute the title variable */
	$save["name"] = str_replace("<rule_name>", $rule["name"], $_title);
	/* create new rule */
	$save["enabled"] = "";	# no new rule accidentally taking action immediately
	$save["id"] = 0;
	$rule_id = sql_save($save, "plugin_autom8_graph_rules");

	/* create new match items */
	if (sizeof($match_items) > 0) {
		foreach ($match_items as $match_item) {
			$save = $match_item;
			$save["id"] = 0;
			$save["rule_id"] = $rule_id;
			$match_item_id = sql_save($save, "plugin_autom8_match_rule_items");
		}
	}

	/* create new rule items */
	if (sizeof($rule_items) > 0) {
		foreach ($rule_items as $rule_item) {
			$save = $rule_item;
			$save["id"] = 0;
			$save["rule_id"] = $rule_id;
			$rule_item_id = sql_save($save, "plugin_autom8_graph_rule_items");
		}
	}
}


function duplicate_autom8_tree_rules($_id, $_title) {
	global $fields_autom8_tree_rules_edit1, $fields_autom8_tree_rules_edit2, $fields_autom8_tree_rules_edit3;

	$rule = db_fetch_row("SELECT * FROM plugin_autom8_tree_rules WHERE id=$_id");
	$match_items = db_fetch_assoc("SELECT * FROM plugin_autom8_match_rule_items WHERE rule_id=$_id AND rule_type=" . AUTOM8_RULE_TYPE_TREE_MATCH);
	$rule_items = db_fetch_assoc("SELECT * FROM plugin_autom8_tree_rule_items WHERE rule_id=$_id");

	$fields_autom8_tree_rules_edit = $fields_autom8_tree_rules_edit1 + $fields_autom8_tree_rules_edit2 + $fields_autom8_tree_rules_edit3;
	$save = array();
	reset($fields_autom8_tree_rules_edit);
	while (list($field, $array) = each($fields_autom8_tree_rules_edit)) {
		if (!preg_match("/^hidden/", $array["method"])) {
			$save[$field] = $rule[$field];
		}
	}

	/* substitute the title variable */
	$save["name"] = str_replace("<rule_name>", $rule["name"], $_title);
	/* create new rule */
	$save["enabled"] = "";	# no new rule accidentally taking action immediately
	$save["id"] = 0;
	$rule_id = sql_save($save, "plugin_autom8_tree_rules");

	/* create new match items */
	if (sizeof($match_items) > 0) {
		foreach ($match_items as $rule_item) {
			$save = $rule_item;
			$save["id"] = 0;
			$save["rule_id"] = $rule_id;
			$rule_item_id = sql_save($save, "plugin_autom8_match_rule_items");
		}
	}

	/* create new action rule items */
	if (sizeof($rule_items) > 0) {
		foreach ($rule_items as $rule_item) {
			$save = $rule_item;
			/* make sure, that regexp is correctly masked */
			$save["search_pattern"] = mysql_real_escape_string($rule_item["search_pattern"]);
			$save["replace_pattern"] = mysql_real_escape_string($rule_item["replace_pattern"]);
			$save["id"] = 0;
			$save["rule_id"] = $rule_id;
			$rule_item_id = sql_save($save, "plugin_autom8_tree_rule_items");
		}
	}
}


function build_data_query_sql($rule) {
	autom8_log(__FUNCTION__ . " called: " . serialize($rule), false, "AUTOM8 TRACE", POLLER_VERBOSITY_MEDIUM);
	$sql_query = "";

	$field_names = get_field_names($rule["snmp_query_id"]);
	$sql_query  = "SELECT host.hostname AS autom8_host, host_id, host.disabled, host.status, snmp_query_id, snmp_index ";
	$num_visible_fields = sizeof($field_names);
	$i = 0;
	if (sizeof($field_names) > 0) {
		foreach($field_names as $column) {
			$field_name = $column["field_name"];
			$sql_query .= ", MAX(CASE WHEN field_name='$field_name' THEN field_value ELSE NULL END) AS '$field_name'";
			$i++;
		}
	}

	/* take matching hosts into account */
	$sql_where = build_matching_objects_filter($rule["id"], AUTOM8_RULE_TYPE_GRAPH_MATCH);

	/* build magic query, for matching hosts JOIN tables host and host_template */
	$sql_query .= " FROM host_snmp_cache " .
					"LEFT JOIN host ON (host_snmp_cache.host_id=host.id) " .
					"LEFT JOIN host_template ON (host.host_template_id=host_template.id) " .
					"WHERE snmp_query_id=" . $rule["snmp_query_id"] .
					" AND (" . $sql_where . ") " .
					" GROUP BY host_id, snmp_query_id, snmp_index ";

	#print "<pre>"; print $sql_query; print"</pre>";
	autom8_log(__FUNCTION__ . " returns: " . $sql_query, false, "AUTOM8 TRACE", POLLER_VERBOSITY_MEDIUM);
	return $sql_query;
}

function build_matching_objects_filter($rule_id, $rule_type) {
	autom8_log(__FUNCTION__ . " called rule id: $rule_id", false, "AUTOM8 TRACE", POLLER_VERBOSITY_MEDIUM);
	
	$sql_filter = "";

	/* create an SQL which queries all host related tables in a huge join
	 * this way, we may add any where clause that might be added via
	 *  "Eligible Hosts" match
	 */
	$sql = "SELECT * " .
		"FROM plugin_autom8_match_rule_items " .
		"WHERE rule_id=" . $rule_id .
		" AND rule_type=" . $rule_type .
		" ORDER BY sequence";
	$rule_items = db_fetch_assoc($sql);
	#print "<pre>Items: $sql<br>"; print_r($rule_items); print "</pre>";

	if (sizeof($rule_items)) {
		#	$sql_order = build_sort_order($xml_array["index_order_type"], "autom8_host");
		#	$sql_query = build_data_query_sql($rule);
		$sql_filter	= build_rule_item_filter($rule_items);
		#	print "SQL Query: " . $sql_query . "<br>";
		#	print "SQL Filter: " . $sql_filter . "<br>";
	} else {
		/* force empty result set if no host matching rule item present */
		$sql_filter = " (1 != 1)";
	}

	autom8_log(__FUNCTION__ . " returns: " . $sql_filter, false, "AUTOM8 TRACE", POLLER_VERBOSITY_MEDIUM);
	return $sql_filter;
}

function build_rule_item_filter($autom8_rule_items, $prefix = "") {
	global $autom8_op_array, $autom8_oper;
	autom8_log(__FUNCTION__ . " called: " . serialize($autom8_rule_items) . ", prefix: $prefix", false, "AUTOM8 TRACE", POLLER_VERBOSITY_MEDIUM);
	
	$sql_filter = "";
	if(sizeof($autom8_rule_items)) {
		$sql_filter = " ";

		foreach($autom8_rule_items as $autom8_rule_item) {
			# AND|OR|(|)
			if ($autom8_rule_item["operation"] != AUTOM8_OPER_NULL) {
				$sql_filter .= " " . $autom8_oper[$autom8_rule_item["operation"]];
			}
			# right bracket ")" does not come with a field
			if ($autom8_rule_item["operation"] == AUTOM8_OPER_RIGHT_BRACKET) {
				continue;
			}
			# field name
			if ($autom8_rule_item["field"] != "") {
				$sql_filter .= (" " . $prefix . $autom8_rule_item["field"]);
				#
				$sql_filter .= " " . $autom8_op_array["op"][$autom8_rule_item["operator"]] . " ";
				if ($autom8_op_array["binary"][$autom8_rule_item["operator"]]) {
					$sql_filter .= ("'" . $autom8_op_array["pre"][$autom8_rule_item["operator"]]  . mysql_real_escape_string($autom8_rule_item["pattern"]) . $autom8_op_array["post"][$autom8_rule_item["operator"]] . "'");
				}
			}
		}
	}
	autom8_log(__FUNCTION__ . " returns: " . $sql_filter, false, "AUTOM8 TRACE", POLLER_VERBOSITY_MEDIUM);
	return $sql_filter;
}

/*
 * build_sort_order
 * @arg $index_order	sort order given by e.g. xml_array[index_order_type]
 * @arg $default_order	default order if any
 * return				sql sort order string
 */
function build_sort_order($index_order, $default_order = "") {
	autom8_log(__FUNCTION__ . " called: $index_order/$default_order", false, "AUTOM8 TRACE", POLLER_VERBOSITY_MEDIUM);
	
	$sql_order = $default_order;

	/* determine the sort order */
	if (isset($index_order)) {
		if ($index_order == "numeric") {
			$sql_order .= ", CAST(snmp_index AS unsigned)";
		}else if ($index_order == "alphabetic") {
			$sql_order .= ", snmp_index";
		}else if ($index_order == "natural") {
			$sql_order .= ", INET_ATON(snmp_index)";
		}
	}

	/* if ANY order is requested */
	if (strlen($sql_order)) {
		$sql_order = "ORDER BY " . $sql_order;
	}

	autom8_log(__FUNCTION__ . " returns: $sql_order", false, "AUTOM8 TRACE", POLLER_VERBOSITY_MEDIUM);
	return $sql_order;
}

/**
 * get an array of hosts matching a host_match rule
 * @param array $rule		- rule
 * @param int $rule_type	- rule type
 * @param string $sql_where - additional where clause
 * @return array			- array of matching hosts
 */
function get_matching_hosts($rule, $rule_type, $sql_where='') {
	autom8_log(__FUNCTION__ . " called: " . serialize($rule) . " type: " . $rule_type, false, "AUTOM8 TRACE", POLLER_VERBOSITY_MEDIUM);
	
	/* build magic query, for matching hosts JOIN tables host and host_template */
	$sql_query = "SELECT " .
		"host.id AS host_id, " .
		"host.hostname, " .
		"host.description, " .
		"host.disabled, " .
		"host.status, " .
		"host_template.name AS host_template_name " .
		"FROM host " .
		"LEFT JOIN host_template ON (host.host_template_id = host_template.id) ";

	/* get the WHERE clause for matching hosts */
	$sql_filter = " WHERE (" . build_matching_objects_filter($rule["id"], $rule_type) .")";
	if (strlen($sql_where)) {
		$sql_filter .= " AND " . $sql_where;
	}

	return db_fetch_assoc($sql_query . $sql_filter);
}


/**
 * get an array of graphs matching a graph_match rule
 * @param array $rule		- rule
 * @param int $rule_type	- rule type
 * @param string $sql_where - additional where clause
 * @return array			- matching graphs
 */
function get_matching_graphs($rule, $rule_type, $sql_where='') {
	autom8_log(__FUNCTION__ . " called: " . serialize($rule) . " type: " . $rule_type, false, "AUTOM8 TRACE", POLLER_VERBOSITY_MEDIUM);

	$sql_query = "SELECT host.id AS host_id, " .
		"host.hostname, " .
		"host.description, " .
		"host.disabled, " .
		"host.status, " .
		"host_template.name AS host_template_name, " .
		"graph_templates_graph.id, " .
		"graph_templates_graph.local_graph_id, " .
		"graph_templates_graph.height, " .
		"graph_templates_graph.width, " .
		"graph_templates_graph.title_cache, " .
		"graph_templates.name " .
		"FROM (graph_local,graph_templates_graph) " .
		"LEFT JOIN graph_templates ON (graph_local.graph_template_id=graph_templates.id) " .
		"LEFT JOIN host ON (graph_local.host_id = host.id) " .
		"LEFT JOIN host_template ON (host.host_template_id = host_template.id) ";
	
	/* get the WHERE clause for matching graphs */
	$sql_filter = "WHERE graph_local.id=graph_templates_graph.local_graph_id " .
					" AND " . build_matching_objects_filter($rule["id"], $rule_type);

	if (strlen($sql_where)) {
		$sql_filter .= " AND " . $sql_where;
	}

	return db_fetch_assoc($sql_query . $sql_filter);
}



/*
 * get_created_graphs
 * @arg $rule		provide snmp_query_id, graph_type_id
 * return			all graphs that have already been created for the given selection
 */
function get_created_graphs($rule) {
	autom8_log(__FUNCTION__ . " called: " . serialize($rule), false, "AUTOM8 TRACE", POLLER_VERBOSITY_MEDIUM);
	
	$sql = "SELECT " .
				"snmp_query_graph.id " .
				"FROM snmp_query_graph " .
				"WHERE snmp_query_graph.snmp_query_id=" . $rule["snmp_query_id"] . " " .
				"AND snmp_query_graph.id=" . $rule["graph_type_id"];

	$snmp_query_graph_id = db_fetch_cell($sql);
	#print "<pre>SNMP Query Graph: $sql<br>"; print_r($snmp_query_graph_id); print "</pre>";

	/* take matching hosts into account */
	$sql_where = build_matching_objects_filter($rule["id"], AUTOM8_RULE_TYPE_GRAPH_MATCH);

	/* build magic query, for matching hosts JOIN tables host and host_template */
	$sql = "SELECT DISTINCT " .
				"data_local.host_id, " .
				"data_local.snmp_index " .
				"FROM (data_local,data_template_data) " .
				"LEFT JOIN host ON (data_local.host_id=host.id) " .
				"LEFT JOIN host_template ON (host.host_template_id=host_template.id) " .
				"LEFT JOIN data_input_data ON (data_template_data.id=data_input_data.data_template_data_id) " .
				"LEFT JOIN data_input_fields ON (data_input_data.data_input_field_id=data_input_fields.id) " .
				"WHERE data_local.id=data_template_data.local_data_id " .
				"AND data_input_fields.type_code='output_type' " .
				"AND data_input_data.value='" . $snmp_query_graph_id . "' " .
				"AND (" . $sql_where . ")";
	$graphs = db_fetch_assoc($sql);
	# rearrange items to ease indexed access
	$items = array();
	if(sizeof($graphs)) {
		foreach ($graphs as $graph) {
			$items{$graph["host_id"]}{$graph["snmp_index"]} = $graph["snmp_index"];
		}
	}
	#print "<pre>"; print $sql . "<br>"; print "</pre>";
	#print "<pre>"; print_r($graphs); print "</pre>";
	#print "<pre>"; print_r($items); print "</pre>";

	return $items;

}


function get_query_fields($table, $excluded_fields) {
#	include(dirname(__FILE__)."/../../include/global.php");
#	include_once($config["base_path"]."/lib/database.php");
	autom8_log(__FUNCTION__ . " called", false, "AUTOM8 TRACE", POLLER_VERBOSITY_MEDIUM);
	
	$sql = "SHOW COLUMNS FROM " . $table;
	$fields = array_rekey(db_fetch_assoc($sql), "Field", "Type");
	#print "<pre>"; print_r($fields); print "</pre>";
	# remove unwanted entries
	$fields = array_minus($fields, $excluded_fields);

	# now reformat entries for use with draw_edit_form
	if (sizeof($fields)) {
		foreach ($fields as $key => $value) {
			# we want to know later which table was selected
			$new_key = $table . "." . $key;
			# give the user a hint abou the data type of the column
			$new_fields[$new_key] = strtoupper($table) . ": " . $key . ' - ' . $value;
		}
	}
	#print "<pre>"; print_r($new_fields); print "</pre>";
	return $new_fields;
}


/*
 * get_field_names
 * @arg $snmp_query_id	snmp query id
 * return				all field names for that snmp query, taken from snmp_cache
 */
function get_field_names($snmp_query_id) {
	autom8_log(__FUNCTION__ . " called: $snmp_query_id", false, "AUTOM8 TRACE", POLLER_VERBOSITY_MEDIUM);
	
	/* get the unique field values from the database */
	$sql = "SELECT DISTINCT " .
						"field_name " .
						"FROM host_snmp_cache " .
						"WHERE snmp_query_id=" . $snmp_query_id;
	$field_names = db_fetch_assoc($sql);
	#print "<pre>Field Names: $sql<br>"; print_r($field_names); print "</pre>";
	return db_fetch_assoc($sql);
}

function array_to_list($array, $sql_column) {
	/* if the last item is null; pop it off */
	if ((empty($array{count($array)-1})) && (sizeof($array) > 1)) {
		array_pop($array);
	}

	if (count($array) > 0) {
		$sql = "(";

		for ($i=0;($i<count($array));$i++) {
			$sql .=  $array[$i][$sql_column];

			if (($i+1) < count($array)) {
				$sql .= ",";
			}
		}

		$sql .= ")";

		autom8_log(__FUNCTION__ . " returns: $sql", false, "AUTOM8 TRACE", POLLER_VERBOSITY_MEDIUM);
		return $sql;
	}
}

function array_minus($big_array, $small_array) {

	# remove all unwanted fields
	if (sizeof($small_array)) {
		foreach($small_array as $exclude) {
			if (array_key_exists($exclude, $big_array)) {
				unset($big_array[$exclude]);
			}
		}
	}

	return $big_array;
}


function autom8_string_replace($search, $replace, $target) {
	$repl = preg_replace("/" . $search . "/i", $replace, $target);
	return preg_split('/\\\\n/', $repl, -1, PREG_SPLIT_NO_EMPTY);
}


function global_item_edit($rule_id, $rule_item_id, $rule_type) {
	global $config, $colors, $fields_autom8_match_rule_item_edit, $fields_autom8_graph_rule_item_edit;
	global $fields_autom8_tree_rule_item_edit, $autom8_tree_header_types;
	global $autom8_op_array;

	switch ($rule_type) {
		case AUTOM8_RULE_TYPE_GRAPH_MATCH:
			$title = "Host Match Rule";
			$item_table = "plugin_autom8_match_rule_items";
			$sql_and = " AND rule_type=" . $rule_type;
			$tables = array ("host", "host_templates");
			$autom8_rule = db_fetch_row("SELECT * " .
							"FROM plugin_autom8_graph_rules " .
							"WHERE id=" . $rule_id);

			$_fields_rule_item_edit = $fields_autom8_match_rule_item_edit;
			$query_fields  = get_query_fields("host_template", array("id", "hash"));
			$query_fields += get_query_fields("host", array("id", "host_template_id"));
			$_fields_rule_item_edit["field"]["array"] = $query_fields;
			$module = "autom8_graph_rules.php";
			break;

		case AUTOM8_RULE_TYPE_GRAPH_ACTION:
			$title = "Create Graph Rule";
			$tables = array(AUTOM8_RULE_TABLE_XML);
			$item_table = "plugin_autom8_graph_rule_items";
			$sql_and = "";
			$autom8_rule = db_fetch_row("SELECT * " .
							"FROM plugin_autom8_graph_rules " .
							"WHERE id=" . $rule_id);

			$_fields_rule_item_edit = $fields_autom8_graph_rule_item_edit;
			$xml_array = get_data_query_array($autom8_rule["snmp_query_id"]);
			reset($xml_array["fields"]);
			$fields = array();
			if(sizeof($xml_array)) {
				foreach($xml_array["fields"] as $key => $value) {
					# ... work on all input fields
					if(isset($value["direction"]) && (strtolower($value["direction"]) == 'input')) {
						$fields[$key] = $key . ' - ' . $value["name"];
					}
				}
				$_fields_rule_item_edit["field"]["array"] = $fields;
			}
			$module = "autom8_graph_rules.php";
			break;

		case AUTOM8_RULE_TYPE_TREE_MATCH:
			$item_table = "plugin_autom8_match_rule_items";
			$sql_and = " AND rule_type=" . $rule_type;
			$autom8_rule = db_fetch_row("SELECT * " .
							"FROM plugin_autom8_tree_rules " .
							"WHERE id=" . $rule_id);
			$_fields_rule_item_edit = $fields_autom8_match_rule_item_edit;
			$query_fields  = get_query_fields("host_template", array("id", "hash"));
			$query_fields += get_query_fields("host", array("id", "host_template_id"));

			if ($autom8_rule["leaf_type"] == TREE_ITEM_TYPE_HOST) {
				$title = "Host Match Rule";
				$tables = array ("host", "host_templates");
				#print "<pre>"; print_r($query_fields); print "</pre>";
			} elseif ($autom8_rule["leaf_type"] == TREE_ITEM_TYPE_GRAPH) {
				$title = "Graph Match Rule";
				$tables = array ("host", "host_templates");
				# add some more filter columns for a GRAPH match
				$query_fields += get_query_fields("graph_templates", array("id", "hash"));
				$query_fields += array("graph_templates_graph.title" => "GRAPH_TEMPLATES_GRAPH: title - varchar(255)");
				$query_fields += array("graph_templates_graph.title_cache" => "GRAPH_TEMPLATES_GRAPH: title_cache - varchar(255)");
				#print "<pre>"; print_r($query_fields); print "</pre>";
			}
			$_fields_rule_item_edit["field"]["array"] = $query_fields;
			$module = "autom8_tree_rules.php";
			break;

		case AUTOM8_RULE_TYPE_TREE_ACTION:
			$item_table = "plugin_autom8_tree_rule_items";
			$sql_and = "";
			$autom8_rule = db_fetch_row("SELECT * " .
							"FROM plugin_autom8_tree_rules " .
							"WHERE id=" . $rule_id);

			$_fields_rule_item_edit = $fields_autom8_tree_rule_item_edit;
			$query_fields  = get_query_fields("host_template", array("id", "hash"));
			$query_fields += get_query_fields("host", array("id", "host_template_id"));

			/* list of allowed header types depends on rule leaf_type
			 * e.g. for a Host Rule, only Host-related header types make sense
			 */
			if ($autom8_rule["leaf_type"] == TREE_ITEM_TYPE_HOST) {
				$title = "Create Tree Rule (Host)";
				$tables = array ("host", "host_templates");
				#print "<pre>"; print_r($query_fields); print "</pre>";
			} elseif ($autom8_rule["leaf_type"] == TREE_ITEM_TYPE_GRAPH) {
				$title = "Create Tree Rule (Graph)";
				$tables = array ("host", "host_templates");
				# add some more filter columns for a GRAPH match
				$query_fields += get_query_fields("graph_templates", array("id", "hash"));
				$query_fields += array("graph_templates_graph.title" => "GRAPH_TEMPLATES_GRAPH: title - varchar(255)");
				$query_fields += array("graph_templates_graph.title_cache" => "GRAPH_TEMPLATES_GRAPH: title_cache - varchar(255)");
				#print "<pre>"; print_r($query_fields); print "</pre>";
			}
			$_fields_rule_item_edit["field"]["array"] = $query_fields;
			$module = "autom8_tree_rules.php";
			break;

	}

	if (!empty($rule_item_id)) {
		$autom8_item = db_fetch_row("SELECT * " .
							"FROM " . $item_table .
							" WHERE id=" . $rule_item_id .
							$sql_and);
		#print "<pre>"; print_r($autom8_item); print "</pre>";

		$header_label = "[edit rule item for $title: " . $autom8_rule['name'] . "]";
	}else{
		$header_label = "[new rule item for $title: " . $autom8_rule['name'] . "]";
		$autom8_item = array();
		$autom8_item["sequence"] = get_sequence('', 'sequence', $item_table, 'rule_id=' . $rule_id . $sql_and);
	}

	print "<form method='post' action='" . $module . "' name='form_autom8_global_item_edit'>";
	html_start_box("<strong>Rule Item</strong> $header_label", "100%", $colors["header"], "3", "center", "");
	#print "<pre>"; print_r($_POST); print_r($_GET); print_r($_REQUEST); print "</pre>";
	#print "<pre>"; print_r($_fields_rule_item_edit); print "</pre>";

	draw_edit_form(array(
		"config" => array("no_form_tag" => true),
		"fields" => inject_form_variables($_fields_rule_item_edit, (isset($autom8_item) ? $autom8_item : array()), (isset($autom8_rule) ? $autom8_rule : array()))
	));

	html_end_box();
}


/* html_header_sort_url - draws a header row suitable for display inside of a box element.  When
     a user selects a column header, the collback function "filename" will be called to handle
     the sort the column and display the altered results.
   @arg $header_items - an array containing a list of column items to display.  The
        format is similar to the html_header, with the exception that it has three
        dimensions associated with each element (db_column => display_text, default_sort_order)
   @arg $sort_column - the value of current sort column.
   @arg $sort_direction - the value the current sort direction.  The actual sort direction
        will be opposite this direction if the user selects the same named column.
   @arg $last_item_colspan - the TD 'colspan' to apply to the last cell in the row */
function html_header_sort_url($header_items, $sort_column, $sort_direction, $last_item_colspan = 1, $url = "") {
	global $colors;
	static $rand_id = 0;

	if ($url == "") {$url = $_SERVER["PHP_SELF"];}
	if (strpos($url, "?", 1) == 0) {
		$url .= "?";
	} else {
		$url .= "&";
	}

	/* reverse the sort direction */
	if ($sort_direction == "ASC") {
		$new_sort_direction = "DESC";
	}else{
		$new_sort_direction = "ASC";
	}

	print "<tr bgcolor='#" . $colors["header_panel"] . "'>\n";

	$i = 1;
	foreach ($header_items as $db_column => $display_array) {
		/* by default, you will always sort ascending, with the exception of an already sorted column */
		if ($sort_column == $db_column) {
			$direction = $new_sort_direction;
			$display_text = $display_array[0] . "**";
		}else{
			$display_text = $display_array[0];
			$direction = $display_array[1];
		}

		if (($db_column == "") || (substr_count($db_column, "nosort"))) {
			print "<td " . ((($i+1) == count($header_items)) ? "colspan='$last_item_colspan' " : "") . "class='textSubHeaderDark'>" . $display_text . "</td>\n";
		}else{
			print "<td " . ((($i) == count($header_items)) ? "colspan='$last_item_colspan'>" : ">");
			print "<a class='textSubHeaderDark' href='" . htmlspecialchars($url . "sort_column=" . $db_column . "&sort_direction=" . $direction) . "'>" . $display_text . "</a>";
			print "</td>\n";
		}

		$i++;
	}

	print "</tr>\n";
}

/* autom8_log - logs a string to Cacti's log file or optionally to the browser
   @arg $string - the string to append to the log file
   @arg $output - (bool) whether to output the log line to the browser using pring() or not
   @arg $environ - (string) tell's from where the script was called from */
function autom8_log($string, $output = false, $environ="AUTOM8", $level=POLLER_VERBOSITY_NONE) {
	# if current verbosity >= level of current message, print it
	if (AUTOM8_DEBUG >= $level) {
		cacti_log($string, $output, $environ);
	}
}
?>
