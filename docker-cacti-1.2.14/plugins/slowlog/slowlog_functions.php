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

function get_cacti_tables() {
	$databases = db_fetch_assoc("SHOW DATABASES");
	$tables    = "";
	if (sizeof($databases)) {
	foreach($databases as $db) {
		if ($db["Database"] == "information_schema" || $db["Database"] == "mysql") {
			// Skip
		}else{
			$tables .= (strlen($tables) ? " ":"") . implode(" ", array_rekey(db_fetch_assoc("SHOW TABLES FROM " . $db["Database"]), "Tables_in_" . $db["Database"], "Tables_in_" . $db["Database"]));
		}
	}
	}

	return $tables;
}

function import_logfile($logfile, $description = 'Imported using import_log utility', $length = 8192, $table_names = "", $usecacti = false) {
	global $cnn_id, $config;

	ini_set("max_execution_time", 0);

	if ($table_names == "" && $usecacti) {
		$table_names = get_cacti_tables();
	}

	if (file_exists($logfile)) {
		// suck the log through a straw
		$entries    = file($logfile);

		// denotes that the log beginning has been found
		$start      = false;

		// sql related variables
		$records    = array();
		$sql_prefix = "INSERT INTO plugin_slowlog_details (logid, date, user, host, ip_address, query_time, lock_time, rows_sent, rows_examined, query) VALUES ";

		if (sizeof($entries)) {
			// variables related to each slowlog entry
			$date          = 0;
			$user          = '';
			$host          = '';
			$ip            = '';
			$query         = '';
			$query_time    = 0;
			$lock_time     = 0;
			$rows_sent     = 0;
			$rows_examined = 0;
			$start_time    = 0;
			$lines         = 0;

			foreach($entries as $l) {
				if ($start && substr($l,0,1) != "#") {
					$query_start = true;
				}

				if (substr_count($l, "# Time:")) {
					// we are a good log, let's make an entry
					if (!$start) {
						$save['logid']        = 0;
						$save['description']  = $description;
						$save['import_date']  = date("Y-m-d H:i:s");
						$save['import_lines'] = 0;
						$save['start_time']   = date("Y-m-d H:i:s");
						$save['end_time']     = date("Y-m-d H:i:s");
						$logid = sql_save($save, 'plugin_slowlog', 'logid');

						if ($logid == 0) {
							echo "FATAL: Can not import due to error creating parent record in 'plugin_slowlog'\n";
							exit -1;
						}

						$start = true;
					}else{
						if ($query != '') {
							$records[] = "($logid, '" . $date . "', '$user', '$host', '$ip', $query_time, $lock_time, $rows_sent, $rows_examined, " . $cnn_id->qstr(substr($query, 0, $length)) . ")";
						}
					}

					$query = '';
					$p1    = explode(" ", $l);
					$date  = "20" . substr($p1[2], 0, 2) . "-" . substr($p1[2], 2, 2) . "-" . substr($p1[2], 4, 2) . " " . $p1[3];

					if ($start_time == 0) {
						$start_time = $date;
					}

					$query_start = false;
				}elseif (substr_count($l, "# User@Host:")) {
					if ($query_start) {
						if ($query != '') {
							$records[] = "($logid, '" . $date . "', '$user', '$host', '$ip', $query_time, $lock_time, $rows_sent, $rows_examined, " . $cnn_id->qstr(substr($query, 0, $length)) . ")";
						}
						$query     = '';
					}

					$p1    = explode(":", $l);
					$p2    = explode("@", $p1[1]);
					$user1 = explode("[", trim($p2[0]));
					$user  = $user1[0];
					$host1 = explode("[", trim($p2[1]));
					$host  = trim($host1[0]);
					$ip    = str_replace("]", "", trim($host1[1]));
					$query_start = false;
				}elseif (substr_count($l, "# Query_time:")) {
					$p1            = explode(":", $l);
					$qt1           = explode(" ", trim($p1[1]));
					$query_time    = trim($qt1[0]);
					$lt1           = explode(" ", trim($p1[2]));
					$lock_time     = trim($lt1[0]);
					$rs1           = explode(" ", trim($p1[3]));
					$rows_sent     = trim($rs1[0]);
					$rows_examined = trim($p1[4]);
				}elseif ($start) {
					if (!substr_count($l, "/*!32311 LOCAL */")) {
						$query .= $l;
					}else{
						$query  = '';
					}
				}

				if (sizeof($records) > 1) {
					// turn the records array into a string
					$sql_data = implode(",", $records);
					$lines   += sizeof($records);

					// insert the records
					db_execute($sql_prefix . $sql_data);

					// reinitialize the records array
					$records = array();
				}
			}

			if ($query != '') {
				$records[] = "($logid, '" . $date . "', '$user', '$host', '$ip', $query_time, $lock_time, $rows_sent, $rows_examined, " . $cnn_id->qstr(substr($query,0,$length)) . ")";

				// turn the records array into a string
				$sql_data = implode(",", $records);
				$lines   += sizeof($records);

				// insert the records
				db_execute($sql_prefix . $sql_data);
			}

			// perform table name analysis
			if (strlen($table_names)) {
				$tables = explode(" ", $table_names);
				if (sizeof($tables) < 3) {
					$tables = explode("\n", $table_names);
				}
				foreach($tables as $t) {
					db_execute("INSERT INTO plugin_slowlog_tables (logid, table_name) VALUES ($logid, '$t')");

					db_execute("INSERT INTO plugin_slowlog_details_tables (logid, logentry, table_name)
						SELECT '$logid' AS logid, logentry, '$t' AS table_name
						FROM plugin_slowlog_details
						WHERE logid=$logid
						AND (query LIKE '%FROM $t %'
						OR query LIKE '%FROM ($t,%'
						OR query LIKE '%FROM (%,$t)%'
						OR query LIKE '%JOIN $t %'
						OR query LIKE '%`$t`%'
						OR query LIKE '%UPDATE $t %'
						OR query LIKE '%INTO $t %'
						OR query LIKE '%FROM $t')");
				}
			}

			$values = db_fetch_row("SELECT count(*) AS import_lines, MIN(date) AS start_time, MAX(date) AS end_time
				FROM plugin_slowlog_details
				WHERE logid=$logid");

			// update statistics
			if (sizeof($values)) {
				db_execute("UPDATE plugin_slowlog
					SET import_lines=" . $values["import_lines"] . ",
					start_time='" . $values["start_time"] ."',
					end_time='" . $values["end_time"] ."'
					WHERE logid=$logid");
			}

			// perform  method analysis
			$methods = db_fetch_assoc("SELECT * FROM plugin_slowlog_methods ORDER BY method");

			foreach($methods as $row) {
				if ($row["method"] != "OTHERS") {
					db_execute("INSERT INTO plugin_slowlog_details_methods (logid, logentry, methodid)
						SELECT '$logid' AS logid, logentry, '" . $row["methodid"] . "' AS methodid
						FROM plugin_slowlog_details
						WHERE logid=$logid AND query LIKE '%" . $row["query"] . "%'");
				}else{
					$sql_where = "WHERE logid=$logid";

					foreach($methods as $method) {
						$sql_where .= " AND query NOT LIKE '%" . $method["query"] . "%'";
					}

					db_execute("INSERT INTO plugin_slowlog_details_methods (logid, logentry, methodid)
						SELECT '$logid' AS logid, logentry, " . $row["methodid"] . " AS methodid
						FROM plugin_slowlog_details
						$sql_where");
				}
			}
		}
	}else{
		echo "FATAL: Can not find file '$file'\n";
		exit -1;
	}
}

function slowlog_tabs() {
	global $config;

	/* present a tabbed interface */
	$tabs = array(
		"select" => "Summary",
		"methods" => "By Method",
		"tables" => "By Table",
		"details" => "Details");

	if (isset($_REQUEST["logentry"])) {
		$tabs = array_merge($tabs, array("query" => "Query"));
	}

	/* set the default tab */
	$current_tab = $_REQUEST["action"];

	if ($current_tab == "select") {
		unset($_REQUEST["logid"]);
	}

	/* draw the tabs */
	print "<table class='report' width='100%' cellspacing='0' cellpadding='3' align='center'><tr>\n";

	if (sizeof($tabs)) {
		foreach (array_keys($tabs) as $tab_short_name) {
			print "<td style='padding:3px 10px 2px 5px;background-color:" . (($tab_short_name == $current_tab) ? "silver;" : "#DFDFDF;") .
				"white-space:nowrap;'" .
				" nowrap width='1%'" .
				"' align='center' class='tab'>
				<span class='textHeader'><a href='" . $config['url_path'] .
				"plugins/slowlog/slowlog.php?" .
				"action=" . $tab_short_name .
				(isset($_REQUEST["logid"]) ? "&logid=" . $_REQUEST["logid"]:"") .
				(isset($_REQUEST["logentry"]) ? "&logentry=" . $_REQUEST["logentry"]:"") .
				"'>$tabs[$tab_short_name]</a></span>
			</td>\n
			<td width='1'></td>\n";

			if (!isset($_REQUEST["logid"])) break;
		}
	}
	print "<td></td><td></td>\n</tr></table>\n";
}

/* slowlog_header_sort - draws a header row suitable for display inside of a box element.  When
     a user selects a column header, the collback function "filename" will be called to handle
     the sort the column and display the altered results.
   @arg $header_items - an array containing a list of column items to display.  The
        format is similar to the html_header, with the exception that it has three
        dimensions associated with each element (db_column => display_text, default_sort_order)
   @arg $sort_column - the value of current sort column.
   @arg $sort_direction - the value the current sort direction.  The actual sort direction
        will be opposite this direction if the user selects the same named column.
   @arg $jsprefix - a prefix to properly apply the sort direction to the right page */
function slowlog_header_sort($header_items, $sort_column, $sort_direction, $jsprefix, $last_item_colspan = 1) {
	global $colors;

	/* reverse the sort direction */
	if ($sort_direction == "ASC") {
		$new_sort_direction = "DESC";
	}else{
		$new_sort_direction = "ASC";
	}

	?>
	<script type="text/javascript">
	<!--
	function sortMe(sort_column, sort_direction) {
		strURL = '?<?php print (strlen($jsprefix) ? $jsprefix:"");?>';
		strURL = strURL + '&sort_direction='+sort_direction;
		strURL = strURL + '&sort_column='+sort_column;
		document.location = strURL;
	}
	-->
	</script>
	<?php

	print "<tr bgcolor='#" . $colors["header_panel"] . "'>\n";

	$i = 1;
	foreach ($header_items as $db_column => $display_array) {
		/* by default, you will always sort ascending, with the exception of an already sorted column */
		if ($sort_column == $db_column) {
			$direction = $new_sort_direction;
			$display_text=$display_array[0] . "**";
			if (is_array($display_array[1])) {
				$align=" align='" . $display_array[1][1] . "'";
			}else{
				$align=" align='left'";
			}
		}else{
			$display_text = $display_array[0];
			if (is_array($display_array[1])) {
				$align     = "align='" . $display_array[1][1] . "'";
				$direction = $display_array[1][0];
			}else{
				$align     = " align='left'";
				$direction = $display_array[1];
			}
		}

		if (($db_column == "") || (substr_count($db_column, "nosort"))) {
			print "<th style='display:block;' $align " . ((($i+1) == count($header_items)) ? "colspan='$last_item_colspan' " : "") . "class='textSubHeaderDark'>" . $display_text . "</th>\n";
		}else{
			print "<th $align " . ((($i) == count($header_items)) ? "colspan='$last_item_colspan'>" : ">");
			print "<span style='cursor:pointer;display:block;' class='textSubHeaderDark' onClick='sortMe(\"" . $db_column . "\", \"" . $direction . "\")'>" . $display_text . "</span>";
			print "</th>\n";
		}

		$i++;
	}

	print "</tr>\n";
}

?>
