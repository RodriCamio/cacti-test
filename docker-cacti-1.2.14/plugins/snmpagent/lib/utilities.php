<?php
/*
   +-------------------------------------------------------------------------+
   | Copyright (C) 2004-2014 The Cacti Group                                 |
   |                                                                         |
   | This program is free software; you can redistribute it and/or           |
   | modify it under the terms of the GNU General Public License             |
   | as published by the Free Software Foundation; either version 2          |
   | of the License, or (at your option) any later version.                  |
   |                                                                         |
   | This program is snmpagent in the hope that it will be useful,           |
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

/**
 * snmpagent_utilities_run_cache()
 *
 * @param mixed $p
 * @return
 */
function snmpagent_utilities_run_cache($rebuild=false) {
	global $colors;

	define("MAX_DISPLAY_PAGES", 21);
	$mibs = db_fetch_assoc("SELECT DISTINCT mib FROM plugin_snmpagent_cache");
	$registered_mibs = array();
	if($mibs && $mibs >0) {
		foreach($mibs as $mib) { $registered_mibs[] = $mib["mib"]; }
	}

	/* ================= input validation ================= */

	if(!in_array(get_request_var_request("mib"), $registered_mibs) && get_request_var_request("mib") != '-1' && get_request_var_request("mib") != "") {
		die_html_input_error();
	}

	input_validate_input_number(get_request_var_request("page"));
	/* ==================================================== */

	/* clean up search filter */
	if (isset($_REQUEST["filter"])) {
		$_REQUEST["filter"] = sanitize_search_string(get_request_var("filter"));
	}

	/* if the user pushed the 'clear' button */
	if (isset($_REQUEST["clear_snmpagent_x"])) {
		kill_session_var("sess_snmpagent_cache_mib");
		kill_session_var("sess_snmpagent_cache_current_page");
		kill_session_var("sess_snmpagent_cache_filter");
		unset($_REQUEST["mib"]);
		unset($_REQUEST["page"]);
		unset($_REQUEST["filter"]);
	}

	/* reset the current page if the user changed the mib filter*/
	if(isset($_SESSION["sess_snmpagent_cache_mib"]) && get_request_var_request("mib") != $_SESSION["sess_snmpagent_cache_mib"]) {
		kill_session_var("sess_snmpagent_cache_current_page");
		unset($_REQUEST["page"]);
	}

	/* remember these search fields in session vars so we don't have to keep passing them around */
	load_current_session_value("page", "sess_snmpagent_cache_current_page", "1");
	load_current_session_value("mib", "sess_snmpagent_cache_mib", "-1");
	load_current_session_value("filter", "sess_snmpagent_cache_filter", "");

	$_REQUEST['page_referrer'] = 'view_snmpagent_cache';
	load_current_session_value('page_referrer', 'page_referrer', 'view_snmpagent_cache');

	?>
		<script type="text/javascript">
		<!--

	function applyViewSNMPAgentCacheFilterChange(objForm) {
		strURL = '?mib=' + objForm.mib.value;
		strURL = strURL + '&filter=' + objForm.filter.value;
		strURL = strURL + '&action=view_snmpagent_cache';
		document.location = strURL;
	}

	-->
	</script>
		<?php

	if($rebuild) {
		snmpagent_cache_rebuilt();
	}

	html_start_box("<strong>SNMPAgent Cache Items</strong>", "100%", $colors["header"], "3", "center", "");

	?>
		<tr bgcolor="#<?php print $colors["panel"];?>">
			<td>
			<form name="form_snmpagent_cache" action="utilities.php">
				<table cellpadding="0" cellspacing="0">
					<tr>
						<td nowrap style='white-space: nowrap;' width="50">
							MIB:&nbsp;
						</td>
						<td width="1">
							<select name="mib" onChange="applyViewSNMPAgentCacheFilterChange(document.form_snmpagent_cache)">
								<option value="-1"<?php if (get_request_var_request("mib") == "-1") {?> selected<?php }?>>Any</option>
								<?php

	if (sizeof($mibs) > 0) {
		foreach ($mibs as $mib) {
			print "<option value='" . $mib["mib"] . "'"; if (get_request_var_request("mib") == $mib["mib"]) { print " selected"; } print ">" . $mib["mib"] . "</option>\n";
		}
	}
								?>
							</select>
						</td>
						<td nowrap style='white-space: nowrap;' width="50">
							&nbsp;Search:&nbsp;
						</td>
						<td width="1">
							<input type="text" name="filter" size="20" value="<?php print htmlspecialchars(get_request_var_request("filter"));?>">
						</td>
						<td nowrap style='white-space: nowrap;'>
							&nbsp;<input type="submit" name="go" value="Go" title="Set/Refresh Filters">
							<input type="submit" name="clear_snmpagent_x" value="Clear" title="Clear Fitlers">
						</td>
					</tr>
				</table>
				<input type='hidden' name='page' value='1'>
				<input type='hidden' name='action' value='view_snmpagent_cache'>
			</form>
			</td>
		</tr>
		<?php

	html_end_box();

	$sql_where = "";

	/* filter by host */
	if (get_request_var_request("mib") == "-1") {
		/* Show all items */
	}elseif (!empty($_REQUEST["mib"])) {
		$sql_where .= " AND plugin_snmpagent_cache.mib='" . get_request_var_request("mib") . "'";
	}

	/* filter by search string */
	if (get_request_var_request("filter") != "") {
		$sql_where .= " AND (`oid` LIKE '%%" . get_request_var_request("filter") . "%%'
			OR `name` LIKE '%%" . get_request_var_request("filter") . "%%'
			OR `mib` LIKE '%%" . get_request_var_request("filter") . "%%'
			OR `max-access` LIKE '%%" . get_request_var_request("filter") . "%%')";
	}
	$sql_where .= ' ORDER by `oid`';
	html_start_box("", "100%", $colors["header"], "3", "center", "");

	$total_rows = db_fetch_cell("SELECT COUNT(*) FROM plugin_snmpagent_cache WHERE 1 $sql_where");

	$snmp_cache_sql = "SELECT * FROM plugin_snmpagent_cache WHERE 1 $sql_where LIMIT " . (read_config_option("num_rows_data_source")*(get_request_var_request("page")-1)) . "," . read_config_option("num_rows_data_source");
	$snmp_cache = db_fetch_assoc($snmp_cache_sql);

	/* generate page list */
	$url_page_select = get_page_list(get_request_var_request("page"), MAX_DISPLAY_PAGES, read_config_option("num_rows_data_source"), $total_rows, "utilities.php?action=view_snmpagent_cache&mib=" . get_request_var_request("mib") . "&filter=" . get_request_var_request("filter"));

	$nav = "<tr bgcolor='#" . $colors["header"] . "'>
			<td colspan='7'>
				<table width='100%' cellspacing='0' cellpadding='0' border='0'>
					<tr>
						<td align='left' class='textHeaderDark'>
							<strong>&lt;&lt; "; if (get_request_var_request("page") > 1) { $nav .= "<a class='linkOverDark' href='" . htmlspecialchars("utilities.php?action=view_snmpagent_cache&mib=" . get_request_var_request("mib") . "&filter=" . get_request_var_request("filter") . "&page=" . (get_request_var_request("page")-1)) . "'>"; } $nav .= "Previous"; if (get_request_var_request("page") > 1) { $nav .= "</a>"; } $nav .= "</strong>
						</td>\n
						<td align='center' class='textHeaderDark'>
							Showing Rows " . ((read_config_option("num_rows_data_source")*(get_request_var_request("page")-1))+1) . " to " . ((($total_rows < read_config_option("num_rows_data_source")) || ($total_rows < (read_config_option("num_rows_data_source")*get_request_var_request("page")))) ? $total_rows : (read_config_option("num_rows_data_source")*get_request_var_request("page"))) . " of $total_rows [$url_page_select]
						</td>\n
						<td align='right' class='textHeaderDark'>
							<strong>"; if ((get_request_var_request("page") * read_config_option("num_rows_data_source")) < $total_rows) { $nav .= "<a class='linkOverDark' href='" . htmlspecialchars("utilities.php?action=view_snmpagent_cache&mib=" . get_request_var_request("mib") . "&filter=" . get_request_var_request("filter") . "&page=" . (get_request_var_request("page")+1)) . "'>"; } $nav .= "Next"; if ((get_request_var_request("page") * read_config_option("num_rows_data_source")) < $total_rows) { $nav .= "</a>"; } $nav .= " &gt;&gt;</strong>
						</td>\n
					</tr>
				</table>
			</td>
		</tr>\n";

	print $nav;

	html_header( array( "OID", "Name", "MIB", "Kind", "Max-Access", "Value") );

	$i = 0;
	if (sizeof($snmp_cache) > 0) {
		foreach ($snmp_cache as $item) {

			$oid = (strlen(get_request_var_request("filter")) ? (preg_replace("/(" . preg_quote(get_request_var_request("filter"), "/") . ")/i", "<span style='background-color: #F8D93D;'>\\1</span>", $item["oid"])) : $item["oid"]);
			$name = (strlen(get_request_var_request("filter")) ? (preg_replace("/(" . preg_quote(get_request_var_request("filter"), "/") . ")/i", "<span style='background-color: #F8D93D;'>\\1</span>", $item["name"])): $item["name"]);
			$mib = (strlen(get_request_var_request("filter")) ? (preg_replace("/(" . preg_quote(get_request_var_request("filter"), "/") . ")/i", "<span style='background-color: #F8D93D;'>\\1</span>", $item["mib"])): $item["mib"]);

			$max_access = (strlen(get_request_var_request("filter")) ? (preg_replace("/(" . preg_quote(get_request_var_request("filter"), "/") . ")/i", "<span style='background-color: #F8D93D;'>\\1</span>", $item["max-access"])) : $item["max-access"]);

			form_alternate_row_color($colors["alternate"], $colors["light"], $i, 'line' . $item["oid"]); $i++;
			form_selectable_cell( $oid, $item["oid"]);
			if($item["description"]) {
				$description = '';
				$lines = preg_split( '/\r\n|\r|\n/', $item['description']);
				foreach($lines as $line) {
					$description .= addslashes(trim($line)) . '<br>';
				}
				print '<td><a href="#" onMouseOut="hideTooltip(snmpagentTooltip)" onMouseMove="showTooltip(event, snmpagentTooltip, \'' . $item["name"] . '\', \'' . $description . '\')">' . $name . '</a></td>';
			}else {
				print "<td>$name</td>";
			}
			form_selectable_cell( $mib, $item["oid"]);
			form_selectable_cell( $item["kind"], $item["oid"]);
			form_selectable_cell( $max_access, $item["oid"]);
			form_selectable_cell( (in_array($item["kind"], array("Scalar", "Column Data")) ? $item["value"] : "n/a"), $item["oid"]);
			form_end_row();
		}
	}

	print $nav;

	html_end_box();

	/* as long as we are not running 0.8.9 don't make any use of jQuery */
	?>
	<div style="display:none" id="snmpagentTooltip"></div>
	<script language="javascript" type="text/javascript" >
		function showTooltip(e, div, title, desc) {
			div.style.display = 'inline';
			div.style.position = 'fixed';
			div.style.backgroundColor = '#EFFCF0';
			div.style.border = 'solid 1px grey';
			div.style.padding = '10px';
			div.innerHTML = '<b>' + title + '</b><div style="padding-left:10; padding-right:5"><pre>' + desc + '</pre></div>';
			div.style.left = e.clientX + 15 + 'px';
			div.style.top = e.clientY + 15 + 'px';
		}

		function hideTooltip(div) {
			div.style.display = 'none';
		}

	</script>

	<?php
}

function snmpagent_utilities_run_eventlog(){
	global $config, $colors;

	define("MAX_DISPLAY_PAGES", 21);

	$severity_levels = array(
		EVENT_SEVERITY_LOW => 'LOW',
		EVENT_SEVERITY_MEDIUM => 'MEDIUM',
		EVENT_SEVERITY_HIGH => 'HIGH',
		EVENT_SEVERITY_CRITICAL => 'CRITICAL'
	);

	$severity_colors = array(
		EVENT_SEVERITY_LOW => '#00FF00',
		EVENT_SEVERITY_MEDIUM => '#FFFF00',
		EVENT_SEVERITY_HIGH => '#FF0000',
		EVENT_SEVERITY_CRITICAL => '#FF00FF'
	);

	$receivers = db_fetch_assoc("SELECT DISTINCT manager_id, hostname FROM plugin_snmpagent_notifications_log INNER JOIN plugin_snmpagent_managers ON plugin_snmpagent_managers.id = plugin_snmpagent_notifications_log.manager_id");

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var_request("receiver"));

	if(!in_array(get_request_var_request("severity"), array_keys($severity_levels)) && get_request_var_request("severity") != '-1' && get_request_var_request("severity") != "") {
		die_html_input_error();
	}
	input_validate_input_number(get_request_var_request("page"));
	/* ==================================================== */

	/* clean up search filter */
	if (isset($_REQUEST["filter"])) {
		$_REQUEST["filter"] = sanitize_search_string(get_request_var("filter"));
	}

	if (isset($_REQUEST["purge_snmpagent__logs_x"])) {
		db_execute("TRUNCATE table plugin_snmpagent_notifications_log;");
		/* reset filters */
		$_REQUEST["clear_snmpagent__logs_x"] = true;
	}

	/* if the user pushed the 'clear' button */
	if (isset($_REQUEST["clear_snmpagent__logs_x"])) {
		kill_session_var("sess_snmpagent__logs_receiver");
		kill_session_var("sess_snmpagent__logs_severity");
		kill_session_var("sess_snmpagent__logs_current_page");
		kill_session_var("sess_snmpagent__logs_filter");
		unset($_REQUEST["severity"]);
		unset($_REQUEST["page"]);
		unset($_REQUEST["filter"]);
	}

	/* reset the current page if the user changed the severity */
	if(isset($_SESSION["sess_snmpagent__logs_severity"]) && get_request_var_request("severity") != $_SESSION["sess_snmpagent__logs_severity"]) {
		kill_session_var("sess_snmpagent__logs_current_page");
		unset($_REQUEST["page"]);
	}

	/* remember these search fields in session vars so we don't have to keep passing them around */
	load_current_session_value("receiver", "sess_snmpagent__logs_receiver", "-1");
	load_current_session_value("page", "sess_snmpagent__logs_current_page", "1");
	load_current_session_value("severity", "sess_snmpagent__logs_severity", "-1");
	load_current_session_value("filter", "sess_snmpagent__logs_filter", "");

	?>
	<script type="text/javascript">
	<!--
	function applyViewSNMPAgentCacheFilterChange(objForm) {
		strURL = '?severity=' + objForm.severity.value;
		strURL = strURL + '&receiver=' + objForm.receiver.value;
		strURL = strURL + '&filter=' + objForm.filter.value;
		strURL = strURL + '&action=view_snmpagent_events';
		document.location = strURL;
	}
	-->
	</script>

	<?php
	html_start_box("<strong>SNMPAgent Notification Log</strong>", "100%", $colors["header"], "3", "center", "");
	?>
	<tr bgcolor="#<?php print $colors["panel"];?>">
		<td>
			<form name="form_snmpagent_notifications" action="utilities.php">
				<table cellpadding="0" cellspacing="0">
					<tr>
						<td nowrap style='white-space: nowrap; text-align:right;' width="50">
							Severity:&nbsp;
						</td>
						<td width="1">
							<select name="severity" onChange="applyViewSNMPAgentCacheFilterChange(document.form_snmpagent_notifications)">
								<option value="-1"<?php if (get_request_var_request("severity") == "-1") {?> selected<?php }?>>Any</option>
								<?php
								foreach ($severity_levels as $level => $name) {
									print "<option value='" . $level . "'"; if (get_request_var_request("severity") == $level) { print " selected"; } print ">" . $name . "</option>\n";
								}
								?>
							</select>
						</td>
						<td nowrap style='white-space: nowrap; text-align:right;' width="70">
							Receiver:&nbsp;
						</td>
						<td width="1">
							<select name="receiver" onChange="applyViewSNMPAgentCacheFilterChange(document.form_snmpagent_notifications)">
								<option value="-1"<?php if (get_request_var_request("receiver") == "-1") {?> selected<?php }?>>Any</option>
								<?php
								foreach ($receivers as $receiver) {
									print "<option value='" . $receiver["manager_id"] . "'"; if (get_request_var_request("receiver") == $receiver["manager_id"]) { print " selected"; } print ">" . $receiver["hostname"] . "</option>\n";
								}
								?>
							</select>
						</td>
						<td nowrap style='white-space: nowrap; text-align:right;' width="70">
							&nbsp;Search:&nbsp;
						</td>
						<td width="1">
							<input type="text" name="filter" size="20" value="<?php print htmlspecialchars(get_request_var_request("filter"));?>">
						</td>
						<td nowrap style='white-space: nowrap;'>
							&nbsp;<input type="submit" name="go" value="Go" title="Set/Refresh Filters">
							<input type="submit" name="clear_snmpagent__logs_x" value="Clear" title="Clear Filters">
							<input type="submit" name="purge_snmpagent__logs_x" value="Purge" title="Purge Notification Log">
						</td>
					</tr>
				</table>
				<input type='hidden' name='page' value='1'>
				<input type='hidden' name='action' value='view_snmpagent_events'>
			</form>
		</td>
	</tr>
	<?php

	html_end_box();

	$sql_where = " 1"; //plugin_snmpagent_notifications_log.manager_id='" . $id . "'";

	/* filter by severity */
	if(get_request_var_request("receiver") != "-1") {
		$sql_where .= " AND plugin_snmpagent_notifications_log.manager_id='" . get_request_var_request("receiver") . "'";
	}

	/* filter by severity */
	if (get_request_var_request("severity") == "-1") {
	/* Show all items */
	}elseif (!empty($_REQUEST["severity"])) {
		$sql_where .= " AND plugin_snmpagent_notifications_log.severity='" . get_request_var_request("severity") . "'";
	}

	/* filter by search string */
	if (get_request_var_request("filter") != "") {
		$sql_where .= " AND (`varbinds` LIKE '%%" . get_request_var_request("filter") . "%%')";
	}
	$sql_where .= ' ORDER by `time` DESC';
	$sql_query = "SELECT plugin_snmpagent_notifications_log.*, plugin_snmpagent_managers.hostname, plugin_snmpagent_cache.description FROM plugin_snmpagent_notifications_log
					 INNER JOIN plugin_snmpagent_managers ON plugin_snmpagent_managers.id = plugin_snmpagent_notifications_log.manager_id
					 LEFT JOIN plugin_snmpagent_cache ON plugin_snmpagent_cache.name = plugin_snmpagent_notifications_log.notification
					 WHERE $sql_where LIMIT " . (read_config_option("num_rows_data_source")*(get_request_var_request("page")-1)) . "," . read_config_option("num_rows_data_source");

	/* print checkbox form for validation */
	print "<form name='chk' method='post' action='managers.php'>\n";
	html_start_box("", "100%", $colors["header"], "3", "center", "");

	$total_rows = db_fetch_cell("SELECT COUNT(*) FROM plugin_snmpagent_notifications_log WHERE $sql_where");

	$logs = db_fetch_assoc($sql_query);
	/* generate page list */
	$url_page_select = get_page_list(get_request_var_request("page"), MAX_DISPLAY_PAGES, read_config_option("num_rows_data_source"), $total_rows, "utilities.php?action=view_snmpagent_events&severity=". get_request_var_request("severity")."&receiver=". get_request_var_request("receiver")."&filter=" . get_request_var_request("filter"));

	$nav = "<tr bgcolor='#" . $colors["header"] . "'>
	<td colspan='7'>
		<table width='100%' cellspacing='0' cellpadding='0' border='0'>
			<tr>
				<td align='left' class='textHeaderDark'>
					<strong>&lt;&lt; "; if (get_request_var_request("page") > 1) { $nav .= "<a class='linkOverDark' href='" . htmlspecialchars("utilities.php?action=view_snmpagent_events&severity=". get_request_var_request("severity")."&receiver=". get_request_var_request("receiver")."&filter=" . get_request_var_request("filter") . "&page=" . (get_request_var_request("page")-1)) . "'>"; } $nav .= "Previous"; if (get_request_var_request("page") > 1) { $nav .= "</a>"; } $nav .= "</strong>
				</td>\n
				<td align='center' class='textHeaderDark'>
					Showing Rows " . ((read_config_option("num_rows_data_source")*(get_request_var_request("page")-1))+1) . " to " . ((($total_rows < read_config_option("num_rows_data_source")) || ($total_rows < (read_config_option("num_rows_data_source")*get_request_var_request("page")))) ? $total_rows : (read_config_option("num_rows_data_source")*get_request_var_request("page"))) . " of $total_rows [$url_page_select]
				</td>\n
				<td align='right' class='textHeaderDark'>
					<strong>"; if ((get_request_var_request("page") * read_config_option("num_rows_data_source")) < $total_rows) { $nav .= "<a class='linkOverDark' href='" . htmlspecialchars("utilities.php?action=view_snmpagent_events&severity=". get_request_var_request("severity")."&receiver=". get_request_var_request("receiver") . "&filter=" . get_request_var_request("filter") . "&page=" . (get_request_var_request("page")+1)) . "'>"; } $nav .= "Next"; if ((get_request_var_request("page") * read_config_option("num_rows_data_source")) < $total_rows) { $nav .= "</a>"; } $nav .= " &gt;&gt;</strong>
				</td>\n
			</tr>
		</table>
	</td>
	</tr>\n";

	print $nav;

	html_header( array(" ", "Time", "Receiver", "Notification", "Varbinds" ), true );
	$i = 0;
	if (sizeof($logs) > 0) {
		foreach ($logs as $item) {
			$varbinds = (strlen(get_request_var_request("filter")) ? (preg_replace("/(" . preg_quote(get_request_var_request("filter"), "/") . ")/i", "<span style='background-color: #F8D93D;'>\\1</span>", $item["varbinds"])): $item["varbinds"]);
			form_alternate_row_color($colors["alternate"], $colors["light"], $i, 'line' . $item['id']); $i++;
			print "<td title='Severity Level: " . $severity_levels[ $item["severity"] ] . "' style='width:10px;background-color: " . $severity_colors[ $item["severity"] ] . ";border-top:1px solid white;border-bottom:1px solid white;'></td>";
			print "<td style='white-space: nowrap;'>" . date( "Y/m/d H:i:s", $item["time"]) . "</td>";
			print "<td>" . $item["hostname"] . "</td>";
			if($item["description"]) {
				$description = '';
				$lines = preg_split( '/\r\n|\r|\n/', $item['description']);
				foreach($lines as $line) {
					$description .= addslashes(trim($line)) . '<br>';
				}
				print '<td><a href="#" onMouseOut="hideTooltip(snmpagentTooltip)" onMouseMove="showTooltip(event, snmpagentTooltip, \'' . $item["notification"] . '\', \'' . $description . '\')">' . $item["notification"] . '</a></td>';
			}else {
				print "<td>{$item["notification"]}</td>";
			}
			print "<td>$varbinds</td>";
			form_end_row();
		}
		print $nav;
	}else{
		print "<tr><td><em>No SNMP Notification Log Entries</em></td></tr>";
	}

	html_end_box();
	?>
	<div style="display:none" id="snmpagentTooltip"></div>
	<script language="javascript" type="text/javascript" >
	function showTooltip(e, div, title, desc) {
		div.style.display = 'inline';
		div.style.position = 'fixed';
		div.style.backgroundColor = '#EFFCF0';
		div.style.border = 'solid 1px grey';
		div.style.padding = '10px';
		div.innerHTML = '<b>' + title + '</b><div style="padding-left:10; padding-right:5"><pre>' + desc + '</pre></div>';
		div.style.left = e.clientX + 15 + 'px';
		div.style.top = e.clientY + 15 + 'px';
		}

		function hideTooltip(div) {
			div.style.display = 'none';
		}
		function highlightStatus(selectID){
			if (document.getElementById('status_' + selectID).value == 'ON') {
			document.getElementById('status_' + selectID).style.backgroundColor = 'LawnGreen';
		}else {
			document.getElementById('status_' + selectID).style.backgroundColor = 'OrangeRed';
		}
	}
	</script>
	<?php
}
?>