<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2007 The Cacti Group                                 |
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

/* ================= input validation ================= */
input_validate_input_number(get_request_var_request("local_graph_id"));
/* ==================================================== */

/* clean up method string */
if (isset($_REQUEST["method"])) {
	$_REQUEST["method"] = sanitize_search_string(get_request_var("method"));
}

/* clean up dryrun string */
if (isset($_REQUEST["dryrun"])) {
	$_REQUEST["dryrun"] = sanitize_search_string(get_request_var("dryrun"));
}

if (spikekill_authorized()) {
	$local_data_ids = db_fetch_assoc("SELECT DISTINCT
		data_template_rrd.local_data_id
		FROM graph_templates_item
		LEFT JOIN data_template_rrd ON (graph_templates_item.task_item_id=data_template_rrd.id)
		WHERE graph_templates_item.local_graph_id=" . $_REQUEST["local_graph_id"]);

	$results = "";
	if (sizeof($local_data_ids)) {
	foreach($local_data_ids as $local_data_id) {
		$data_source_path = get_data_source_path($local_data_id["local_data_id"], true);

		if (strlen($data_source_path)) {
			cacti_log(read_config_option("path_php_binary") . " -q " . $config["base_path"] . "/plugins/spikekill/removespikes.php " .
				" -R=" . $data_source_path . (isset($_REQUEST["dryrun"]) ? " --dryrun" : "") .
				" -M=" . $_REQUEST["method"] .
				" --html", false);
			$results .= shell_exec(read_config_option("path_php_binary") . " -q " . $config["base_path"] . "/plugins/spikekill/removespikes.php " .
				" -R=" . $data_source_path . (isset($_REQUEST["dryrun"]) ? " --dryrun" : "") .
				" -M=" . $_REQUEST["method"] .
				" --html");
		}
	}
	}

	echo $_REQUEST["local_graph_id"] . "!!!!" . $results . "!!!!" . $_REQUEST["src"];
}
