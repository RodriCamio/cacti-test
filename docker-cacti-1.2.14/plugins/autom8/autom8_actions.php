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

/**
 * Setup the new dropdown action for Device Management
 * @arg $action		actions to be performed from dropdown
 */
function autom8_device_action_array($action) {
	$action['plugin_autom8_device'] = 'Apply Autom8 Rules to Device(s)';
	return $action;
}


function autom8_device_action_prepare($save) {
	# globals used
	global $config, $colors;
	include_once($config['base_path'] . "/plugins/autom8/autom8_utilities.php");
	autom8_log("autom8_device_action_prepare called", true, "AUTOM8 TRACE", POLLER_VERBOSITY_MEDIUM);

	/* suppress warnings */
	error_reporting(0);

	/* install own error handler */
	set_error_handler("autom8_error_handler");

	# it's our turn
	if ($save["drp_action"] == "plugin_autom8_device") { /* autom8 */
		/* find out which (if any) hosts have been checked, so we can tell the user */
		if (isset($save["host_array"])) {
			#print "<pre>";print_r($save["host_array"]);print "</pre>";

			/* list affected hosts */
			print "<tr>";
			print "<td class='textArea' bgcolor='#" . $colors["form_alternate1"] . "'>" .
				"<p>Are you sure you want to apply <strong>Autom8 Rules</strong> to the following hosts?</p><ul>" .
			$save["host_list"] . "</ul></td>";
			print "</tr>";
		}
	}

	/* restore original error handler */
	restore_error_handler();
	return $save;			# required for next hook in chain
}

/**
 * autom8_device_action_execute - execute the device action
 * @arg $action				action to be performed
 * return				-
 *  */
function autom8_device_action_execute($action) {
	global $config;
	include_once($config['base_path'] . "/plugins/autom8/autom8_functions.php");
	include_once($config['base_path'] . "/plugins/autom8/autom8_utilities.php");
	autom8_log(__FUNCTION__ . " called", true, "AUTOM8 TRACE", POLLER_VERBOSITY_MEDIUM);

	/* suppress warnings */
	error_reporting(0);

	/* install own error handler */
	set_error_handler("autom8_error_handler");

	# it's our turn
	if ($action == "plugin_autom8_device") { /* autom8 */
		autom8_log(__FUNCTION__ . " called, action: " . $action, true, "AUTOM8 TRACE", POLLER_VERBOSITY_MEDIUM);
		/* find out which (if any) hosts have been checked, so we can tell the user */
		if (isset($_POST["selected_items"])) {
			$selected_items = unserialize(stripslashes($_POST["selected_items"]));
			autom8_log(__FUNCTION__ . ", items: " . $_POST["selected_items"], true, "AUTOM8 TRACE", POLLER_VERBOSITY_MEDIUM);

			/* work on all selected hosts */
			for ($i=0;($i<count($selected_items));$i++) {
				/* ================= input validation ================= */
				input_validate_input_number($selected_items[$i]);
				/* ==================================================== */

				$data = array();
				$data["host_id"] = $selected_items[$i];
				autom8_log(__FUNCTION__ ." Host[" . $data["host_id"] . "]", true, "AUTOM8 TRACE", POLLER_VERBOSITY_MEDIUM);
				/* select all graph templates associated with this host, but exclude those where
				*  a graph already exists (table graph_local has a known entry for this host/template) */
				$sql = "SELECT " .
						"graph_templates.id, " .
						"graph_templates.name " .
						"FROM (graph_templates,host_graph) " .
						"WHERE graph_templates.id=host_graph.graph_template_id " .
						"AND host_graph.host_id=" . $selected_items[$i] . " " .
						"AND graph_templates.id NOT IN (" .
							"SELECT graph_local.graph_template_id FROM graph_local WHERE host_id=$selected_items[$i]" .
						")";
				$graph_templates = db_fetch_assoc($sql);
				autom8_log(__FUNCTION__ ." Host[" . $data["host_id"] . "], sql: " . $sql, true, "AUTOM8 TRACE", POLLER_VERBOSITY_MEDIUM);

				/* create all graph template graphs */
				foreach ($graph_templates as $graph_template) {
					$data["graph_template_id"] = $graph_template["id"];
					autom8_log(__FUNCTION__ ." Host[" . $data["host_id"] . "], graph: " . $data["graph_template_id"], true, "AUTOM8 TRACE", POLLER_VERBOSITY_MEDIUM);
					execute_graph_template($data);
				}

				unset($data["graph_template_id"]);

				/* all associated data queries */
				$data_queries = db_fetch_assoc("SELECT " .
									"snmp_query.id, " .
									"snmp_query.name, " .
									"host_snmp_query.reindex_method " .
									"FROM (snmp_query,host_snmp_query) " .
									"WHERE snmp_query.id=host_snmp_query.snmp_query_id " .
									"AND host_snmp_query.host_id=" . $selected_items[$i]);

				/* create all data query graphs */
				foreach ($data_queries as $data_query) {
					$data["snmp_query_id"] = $data_query["id"];
					autom8_log(__FUNCTION__ ." Host[" . $data["host_id"] . "], dq: " . $data["snmp_query_id"], true, "AUTOM8 TRACE", POLLER_VERBOSITY_MEDIUM);
					execute_data_query($data);
				}

				/* now handle tree rules for that host */
				autom8_log(__FUNCTION__ ." Host[" . $data["host_id"] . "], create_tree for host: " . $selected_items[$i], true, "AUTOM8 TRACE", POLLER_VERBOSITY_MEDIUM);
				execute_device_create_tree(array("id" => $selected_items[$i]));

			}
		}
	}

	/* restore original error handler */
	restore_error_handler();
	autom8_log(__FUNCTION__ . " exits", true, "AUTOM8 TRACE", POLLER_VERBOSITY_MEDIUM);
	return $action;
}


/**
 * Setup the new dropdown action for Graph Management
 * @arg $action		actions to be performed from dropdown
 */
function autom8_graph_action_array($action) {
	$action['plugin_autom8_graph'] = 'Apply Autom8 Rules to Graph(s)';
	return $action;
}


function autom8_graph_action_prepare($save) {
	# globals used
	global $config, $colors;
	include_once($config['base_path'] . "/plugins/autom8/autom8_utilities.php");
	autom8_log("autom8_graph_action_prepare called", true, "AUTOM8 TRACE", POLLER_VERBOSITY_MEDIUM);

	/* suppress warnings */
	error_reporting(0);

	/* install own error handler */
	set_error_handler("autom8_error_handler");
	# it's our turn
	if ($save["drp_action"] == "plugin_autom8_graph") { /* autom8 */
		autom8_log("autom8_graph_action_prepare called: " . $save["drp_action"], true, "AUTOM8 TRACE", POLLER_VERBOSITY_MEDIUM);
		/* find out which (if any) hosts have been checked, so we can tell the user */
		if (isset($save["graph_array"])) {
			#print "<pre>";print_r($save["graph_array"]);print "</pre>";

			/* list affected graphs */
			print "<tr>";
			print "<td class='textArea' bgcolor='#" . $colors["form_alternate1"] . "'>" .
				"<p>Are you sure you want to apply <strong>Autom8 Rules</strong> to the following graphs?</p><ul>" .
			$save["graph_list"] . "</ul></td>";
			print "</tr>";
		}
	}

	/* restore original error handler */
	restore_error_handler();
	return $save;			# required for next hook in chain
}

/**
 * perform autom8_graph_action_execute action
 * @arg $action				action to be performed
 * return				-
 *  */
function autom8_graph_action_execute($action) {
	global $config;
	include_once($config['base_path'] . "/plugins/autom8/autom8_functions.php");
	include_once($config['base_path'] . "/plugins/autom8/autom8_utilities.php");
	autom8_log("autom8_graph_action_execute called", true, "AUTOM8 TRACE", POLLER_VERBOSITY_MEDIUM);

	/* suppress warnings */
	error_reporting(0);

	/* install own error handler */
	set_error_handler("autom8_error_handler");

	# it's our turn
	if ($action == "plugin_autom8_graph") { /* autom8 */
		autom8_log("autom8_graph_action_execute called: " . $action, true, "AUTOM8 TRACE", POLLER_VERBOSITY_MEDIUM);
		/* find out which (if any) hosts have been checked, so we can tell the user */
		if (isset($_POST["selected_items"])) {
			$selected_items = unserialize(stripslashes($_POST["selected_items"]));

			/* work on all selected graphs */
			for ($i=0;($i<count($selected_items));$i++) {
				/* ================= input validation ================= */
				input_validate_input_number($selected_items[$i]);
				/* ==================================================== */

				$data = array();
				$data["id"] = $selected_items[$i];
				autom8_log("autom8_graph_action_execute graph: " . $data["id"], true, "AUTOM8 TRACE", POLLER_VERBOSITY_MEDIUM);
				/* now handle tree rules for that graph */
				execute_graph_create_tree($data);

			}
		}
	}

	/* restore original error handler */
	restore_error_handler();
	return $action;
}
?>
