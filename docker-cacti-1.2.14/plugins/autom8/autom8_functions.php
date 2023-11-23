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
 * hook executed for a data query
 * @param $data	- data passed from the hook
 */
function autom8_hook_data_query($data) {
	global $config;
	include_once($config["base_path"]."/plugins/autom8/autom8_utilities.php");
	autom8_log(__FUNCTION__ . " called: " . serialize($data), false, "AUTOM8 TRACE", POLLER_VERBOSITY_MEDIUM);
	
	if (read_config_option("autom8_graphs_enabled") == '') {
		autom8_log(__FUNCTION__ . " Host[" . $data["host_id"] . "] - skipped: Graph Creation Switch is: " . (read_config_option("autom8_graphs_enabled") == "" ? "off" : "on") . " data query: " . $data["snmp_query_id"], false, "AUTOM8 TRACE", POLLER_VERBOSITY_MEDIUM);
		return;
	}
	execute_data_query($data);
}


/**
 * hook executed for a graph template
 * @param $data - data passed from hook
 */
function autom8_hook_graph_template($data) {
	global $config;
	include_once($config["base_path"]."/plugins/autom8/autom8_utilities.php");
	autom8_log(__FUNCTION__ . " called: " . serialize($data), false, "AUTOM8 TRACE", POLLER_VERBOSITY_MEDIUM);
	
	if (read_config_option("autom8_graphs_enabled") == '') {
		autom8_log(__FUNCTION__ . " Host[" . $data["host_id"] . "] - skipped: Graph Creation Switch is: " . (read_config_option("autom8_graphs_enabled") == "" ? "off" : "on") . " graph template: " . $data["graph_template_id"], false, "AUTOM8 TRACE", POLLER_VERBOSITY_MEDIUM);
		return;
	}
	execute_graph_template($data);
}


/**
 * hook executed for a new device on a tree
 * @param $data - data passed from hook
 */
function autom8_hook_device_create_tree($data) {
	global $config;
	include_once($config["base_path"]."/plugins/autom8/autom8_utilities.php");
	autom8_log(__FUNCTION__ . " called: " . serialize($data), false, "AUTOM8 TRACE", POLLER_VERBOSITY_MEDIUM);
	
	if (read_config_option("autom8_tree_enabled") == '') {
		autom8_log(__FUNCTION__ . " Host[" . $data["id"] . "] - skipped: Tree Creation Switch is: " . (read_config_option("autom8_tree_enabled") == "" ? "off" : "on"), false, "AUTOM8 TRACE", POLLER_VERBOSITY_MEDIUM);
		return;
	}
	execute_device_create_tree($data);
}


/**
 * hook executed for a new graph on a tree
 * @param $data - data passed from hook
 */
function autom8_hook_graph_create_tree($data) {
	global $config;
	include_once($config["base_path"]."/plugins/autom8/autom8_utilities.php");
	autom8_log(__FUNCTION__ . " called: " . serialize($data), false, "AUTOM8 TRACE", POLLER_VERBOSITY_MEDIUM);
	
	if (read_config_option("autom8_tree_enabled") == '') {
		autom8_log(__FUNCTION__ . " Graph[" . $data["id"] . "] - skipped: Tree Creation Switch is: " . (read_config_option("autom8_tree_enabled") == "" ? "off" : "on"), false, "AUTOM8 TRACE", POLLER_VERBOSITY_MEDIUM);
		return;
	}
	execute_graph_create_tree($data);
}

/**
 * run rules for a data query
 * @param $data - data passed from hook
 */
function execute_data_query($data) {
	global $config;
	include_once($config["base_path"]."/plugins/autom8/autom8_utilities.php");
	autom8_log(__FUNCTION__ . " called: " . serialize($data), false, "AUTOM8 TRACE", POLLER_VERBOSITY_MEDIUM);
	
	$host_id = $data["host_id"];
	$snmp_query_id = $data["snmp_query_id"];
	autom8_log(__FUNCTION__ . " Host[" . $data["host_id"] . "] - start - data query: $snmp_query_id", false, "AUTOM8 TRACE", POLLER_VERBOSITY_MEDIUM);

	# get all related rules for that data query that are enabled
	$sql = "SELECT " .
				"plugin_autom8_graph_rules.id, " .
				"plugin_autom8_graph_rules.name, " .
				"plugin_autom8_graph_rules.snmp_query_id, " .
				"plugin_autom8_graph_rules.graph_type_id " .
				"FROM plugin_autom8_graph_rules " .
				"WHERE snmp_query_id=$snmp_query_id " .
				"AND enabled='on' ";
	$rules = db_fetch_assoc($sql);
	autom8_log(__FUNCTION__ . " Host[" . $data["host_id"] . "] - sql: $sql - found: " . sizeof($rules), false, "AUTOM8 TRACE", POLLER_VERBOSITY_MEDIUM);
	
	if (!sizeof($rules)) return;

	# now walk all rules and create graphs
	if (sizeof($rules)) {
		foreach ($rules as $rule) {
			autom8_log(__FUNCTION__ . " Host[" . $data["host_id"] . "] - rule=" . $rule["id"] . " name: " . $rule["name"], false, "AUTOM8 TRACE", POLLER_VERBOSITY_MEDIUM);

			/* build magic query, for matching hosts JOIN tables host and host_template */
			$sql_query = "SELECT " .
				"host.id AS host_id, " .
				"host.hostname, " .
				"host.description, " .
				"host_template.name AS host_template_name " .
				"FROM host " .
				"LEFT JOIN host_template ON (host.host_template_id = host_template.id) ";
			#	$hosts = db_fetch_assoc($sql_query);
			#	print "<pre>Hosts: $sql_query<br>"; print_r($hosts); print "</pre>";

			/* get the WHERE clause for matching hosts */
			$sql_filter = build_matching_objects_filter($rule["id"], AUTOM8_RULE_TYPE_GRAPH_MATCH);

			/* now we build up a new query for counting the rows */
			$rows_query = $sql_query . " WHERE (" . $sql_filter . ") AND host.id=" . $host_id;
			$hosts = db_fetch_assoc($rows_query);
			autom8_log(__FUNCTION__ . " Host[" . $data["host_id"] . "] - create sql: $rows_query matches:" . sizeof($hosts), false, "AUTOM8 TRACE", POLLER_VERBOSITY_MEDIUM);
			if (!sizeof($hosts)) { continue; }

			create_dq_graphs($host_id, $snmp_query_id, $rule);
		}
	}
}


/**
 * run rules for a graph template
 * @param $data - data passed from hook
 */
function execute_graph_template($data) {
	global $config;
	include_once($config["base_path"]."/plugins/autom8/autom8_utilities.php");
	include_once($config["base_path"]."/lib/template.php");
	include_once($config["base_path"]."/lib/api_automation_tools.php");
	include_once($config["base_path"]."/lib/utility.php");

	$host_id = $data["host_id"];
	$graph_template_id = $data["graph_template_id"];
	$dataSourceId = "";
	autom8_log(__FUNCTION__ . " called: Host[" . $data["host_id"] . "] - graph template: $graph_template_id", false, "AUTOM8 TRACE", POLLER_VERBOSITY_MEDIUM);

	# are there any input fields?
	if ($graph_template_id > 0) {
		$input_fields = getInputFields($graph_template_id);
		if (sizeof($input_fields)) {
			# do nothing for such graph templates
			return;
		}
	}

	# graph already present?
	$existsAlready = db_fetch_cell("SELECT id FROM graph_local WHERE graph_template_id=$graph_template_id AND host_id=$host_id");

	if ((isset($existsAlready)) && ($existsAlready > 0)) {
		$dataSourceId  = db_fetch_cell("SELECT
			data_template_rrd.local_data_id
			FROM graph_templates_item, data_template_rrd
			WHERE graph_templates_item.local_graph_id = " . $existsAlready . "
			AND graph_templates_item.task_item_id = data_template_rrd.id
			LIMIT 1");

		autom8_log(__FUNCTION__ . " Host[" . $data["host_id"] . "] Not Adding Graph - this graph already exists - graph-id: ($existsAlready) - data-source-id: ($dataSourceId)", false, "AUTOM8");
		return;
	}else{
		# input fields are not supported
		$suggested_values = array();
		$returnArray = create_complete_graph_from_template($graph_template_id, $host_id, "", $suggested_values);

		$dataSourceId = '';
		if (sizeof($returnArray)) {
			if (isset($returnArray["local_data_id"])) {
				foreach($returnArray["local_data_id"] as $item) {
					push_out_host($host_id, $item);

					if (strlen($dataSourceId)) {
						$dataSourceId .= ", " . $item;
					}else{
						$dataSourceId = $item;
					}
				}
			autom8_log("Host[" . $data["host_id"] . "] Graph Added - graph-id: (" . $returnArray["local_graph_id"] . ") - data-source-ids: ($dataSourceId)", false, "AUTOM8");
			}
		} else {
			autom8_log("Host[" . $data["host_id"] . "] ERROR: graph-id: (" . $returnArray["local_graph_id"] . ") without data sources", false, "AUTOM8");
		}

	}
}

/**
 * run rules for a new device in a tree
 * @param $data - data passed from hook
 */
function execute_device_create_tree($data) {
	global $config;
	include_once($config['base_path'] . "/plugins/autom8/autom8_utilities.php");

	/* the $data array holds all information about the host we're just working on
	 * even if we selected multiple hosts, the calling code will scan through the list
	 * so we only have a single host here
	 */
	
	$host_id = $data["id"];
	autom8_log(__FUNCTION__ . " Host[$host_id] called", false, "AUTOM8 TRACE", POLLER_VERBOSITY_MEDIUM);

	/* 
	 * find all active Tree Rules
	 * checking whether a specific rule matches the selected host
	 * has to be done later
	 */
	$sql = "SELECT " .
				"plugin_autom8_tree_rules.id, " .
				"plugin_autom8_tree_rules.name, " .
				"plugin_autom8_tree_rules.tree_id, " .
				"plugin_autom8_tree_rules.tree_item_id, " .
				"plugin_autom8_tree_rules.leaf_type, " .
				"plugin_autom8_tree_rules.host_grouping_type, " .
				"plugin_autom8_tree_rules.rra_id " .
				"FROM plugin_autom8_tree_rules " .
				"WHERE enabled='on' " .
				"AND leaf_type=" . TREE_ITEM_TYPE_HOST;
	$rules = db_fetch_assoc($sql);
	autom8_log(__FUNCTION__ . " Host[$host_id], matching rule sql: $sql matches: " . sizeof($rules), false, "AUTOM8 TRACE", POLLER_VERBOSITY_MEDIUM);
	
	/* now walk all rules 
	 */
	if (sizeof($rules)) {
		foreach ($rules as $rule) {
			autom8_log(__FUNCTION__ . " Host[$host_id], active rule: " . $rule["id"] . " name: " . $rule["name"] . " type: " . $rule["leaf_type"], false, "AUTOM8 TRACE", POLLER_VERBOSITY_MEDIUM);
			
			/* does the rule apply to the current host?
			 * test "eligible objects" rule items */
			$matches = get_matching_hosts($rule, AUTOM8_RULE_TYPE_TREE_MATCH, 'host.id=' . $host_id);
			autom8_log(__FUNCTION__ . " Host[$host_id], matching hosts: " . serialize($matches), false, "AUTOM8 TRACE", POLLER_VERBOSITY_MEDIUM);
			
			/* if the rule produces a match, we will have to create all required tree nodes */
			if (sizeof($matches)) {
				/* create the bunch of header nodes */
				$parent = create_all_header_nodes($host_id, $rule);
				autom8_log(__FUNCTION__ . " Host[$host_id], parent: " . $parent, false, "AUTOM8 TRACE", POLLER_VERBOSITY_MEDIUM);
				/* now that all rule items have been executed, add the item itself */
				$node = create_device_node($host_id, $parent, $rule);
				autom8_log(__FUNCTION__ . " Host[$host_id], node: " . $node, false, "AUTOM8 TRACE", POLLER_VERBOSITY_MEDIUM);
			}
		}
	}
}


/**
 * run rules for a new graph on a tree
 * @param $data - data passed from hook
 */
function execute_graph_create_tree($data) {
	global $config;
	include_once($config['base_path'] . "/plugins/autom8/autom8_utilities.php");

	/* the $data array holds all information about the graph we're just working on
	 * even if we selected multiple graphs, the calling code will scan through the list
	 * so we only have a single graph here
	 */
	
	$graph_id = $data["id"];
	autom8_log(__FUNCTION__ . " Graph[" . $graph_id . "] called, data: " . serialize($data), false, "AUTOM8 TRACE", POLLER_VERBOSITY_MEDIUM);

	/*
	 * find all active Tree Rules
	 * checking whether a specific rule matches the selected graph
	 * has to be done later
	 */
	$sql = "SELECT " .
		"plugin_autom8_tree_rules.id, " .
		"plugin_autom8_tree_rules.name, " .
		"plugin_autom8_tree_rules.tree_id, " .
		"plugin_autom8_tree_rules.tree_item_id, " .
		"plugin_autom8_tree_rules.leaf_type, " .
		"plugin_autom8_tree_rules.host_grouping_type, " .
		"plugin_autom8_tree_rules.rra_id " .
		"FROM plugin_autom8_tree_rules " .
		"WHERE enabled='on' " .
		"AND leaf_type=" . TREE_ITEM_TYPE_GRAPH;
	$rules = db_fetch_assoc($sql);
	autom8_log(__FUNCTION__ . " Graph[" . $graph_id . "], Matching rule sql: $sql matches: " . sizeof($rules), false, "AUTOM8 TRACE", POLLER_VERBOSITY_MEDIUM);
	
	/* now walk all rules
	 */
	if (sizeof($rules)) {			
		foreach ($rules as $rule) {
			autom8_log(__FUNCTION__ . " Graph[" . $graph_id . "], active rule: " . $rule["id"] . " name: " . $rule["name"] . " type: " . $rule["leaf_type"], false, "AUTOM8 TRACE", POLLER_VERBOSITY_MEDIUM);
			
			/* does this rule apply to the current graph?
			 * test "eligible objects" rule items */
			$matches = get_matching_graphs($rule, AUTOM8_RULE_TYPE_TREE_MATCH, 'graph_local.id=' . $graph_id);
			autom8_log(__FUNCTION__ . " Graph[" . $graph_id . "], Matching graphs: " . serialize($matches), false, "AUTOM8 TRACE", POLLER_VERBOSITY_MEDIUM);
			
			/* if the rule produces a match, we will have to create all required tree nodes */
			if (sizeof($matches)) {
				/* create the bunch of header nodes */
				$parent = create_all_header_nodes($graph_id, $rule);
				autom8_log(__FUNCTION__ . " Graph[" . $graph_id . "], Parent: " . $parent, false, "AUTOM8 TRACE", POLLER_VERBOSITY_MEDIUM);
				/* now that all rule items have been executed, add the item itself */
				$node = create_graph_node($graph_id, $parent, $rule);
				autom8_log(__FUNCTION__ . " Graph[" . $graph_id . "], Node: " . $node, false, "AUTOM8 TRACE", POLLER_VERBOSITY_MEDIUM);
			}
		}
	}
}

/**
 * create all graphs for a data query
 * @param int $host_id			- host id
 * @param int $snmp_query_id	- snmp query id
 * @param array $rule			- matching rule
 */
function create_dq_graphs($host_id, $snmp_query_id, $rule) {
	global $config, $autom8_op_array, $autom8_oper;
	include_once($config["base_path"]."/plugins/autom8/autom8_utilities.php");
	include_once($config["base_path"]."/lib/template.php");
	autom8_log(__FUNCTION__ . " Host[" . $host_id . "] - snmp query: $snmp_query_id - rule: " . $rule['name'], false, "AUTOM8 TRACE", POLLER_VERBOSITY_MEDIUM);

	$snmp_query_array = array();
	$snmp_query_array["snmp_query_id"]       = $rule["snmp_query_id"];
	$snmp_query_array["snmp_index_on"]       = get_best_data_query_index_type($host_id, $rule["snmp_query_id"]);
	$snmp_query_array["snmp_query_graph_id"] = $rule["graph_type_id"];

	# get all rule items
	$autom8_rule_items = db_fetch_assoc("SELECT * " .
		"FROM plugin_autom8_graph_rule_items " .
		"WHERE rule_id=" . $rule["id"] .
		" ORDER BY sequence");
	# and all matching snmp_indices from snmp_cache

	/* get the unique field values from the database */
	$sql = "SELECT DISTINCT " .
			"field_name " .
			"FROM host_snmp_cache " .
			"WHERE snmp_query_id=" . $snmp_query_id;

	$field_names = db_fetch_assoc($sql);
	#print "<pre>Field Names: $sql<br>"; print_r($field_names); print "</pre>";

	/* build magic query */
	$sql_query  = "SELECT host_id, snmp_query_id, snmp_index";

	$num_visible_fields = sizeof($field_names);
	$i = 0;
	if (sizeof($field_names) > 0) {
		foreach($field_names as $column) {
			$field_name = $column["field_name"];
			$sql_query .= ", MAX(CASE WHEN field_name='$field_name' THEN field_value ELSE NULL END) AS '$field_name'";
			$i++;
		}
	}

	$sql_query .= " FROM host_snmp_cache " .
					"WHERE snmp_query_id=" . $snmp_query_id . " " .
					"AND host_id=" . $host_id . " " .
					"GROUP BY snmp_query_id, snmp_index";

#	$sql_filter	= "";
#	if(sizeof($autom8_rule_items)) {
#		$sql_filter = " WHERE";
#		foreach($autom8_rule_items as $autom8_rule_item) {
#			# AND|OR
#			if ($autom8_rule_item["operation"] != AUTOM8_OPER_NULL) {
#				$sql_filter .= " " . $autom8_oper[$autom8_rule_item["operation"]];
#			}
#			# right bracket ")" does not come with a field
#			if ($autom8_rule_item["operation"] == AUTOM8_OPER_RIGHT_BRACKET) {
#				continue;
#			}
#			# field name
#			if ($autom8_rule_item["field"] != "") {
#				$sql_filter .= (" a." . $autom8_rule_item["field"]);
#				#
#				$sql_filter .= " " . $autom8_op_array["op"][$autom8_rule_item["operator"]] . " ";
#				if ($autom8_op_array["binary"][$autom8_rule_item["operator"]]) {
#					$sql_filter .= ("'" . $autom8_op_array["pre"][$autom8_rule_item["operator"]]  . mysql_real_escape_string($autom8_rule_item["pattern"]) . $autom8_op_array["post"][$autom8_rule_item["operator"]] . "'");
#				}
#			}
#		}
#	}
	$sql_filter = build_rule_item_filter($autom8_rule_items, " a.");
	if (sizeof($sql_filter)) $sql_filter = " WHERE" . $sql_filter;

	/* add the additional filter settings to the original data query.
	 IMO it's better for the MySQL server to use the original one
	 as an subquery which requires MySQL v4.1(?) or higher */
	$sql_query = "SELECT * FROM (" . $sql_query	. ") as a $sql_filter";

	/* fetch snmp indices */
	#	print $sql_query . "\n";
	$snmp_query_indexes = db_fetch_assoc($sql_query);

	# now create the graphs
	if (sizeof($snmp_query_indexes)) {
		$graph_template_id = db_fetch_cell("SELECT graph_template_id FROM snmp_query_graph WHERE id=" . $rule["graph_type_id"]);
		autom8_log(__FUNCTION__ . " Host[" . $host_id . "] - graph template: $graph_template_id", false, "AUTOM8 TRACE", POLLER_VERBOSITY_MEDIUM);

		foreach ($snmp_query_indexes as $snmp_index) {
			$snmp_query_array["snmp_index"] = $snmp_index["snmp_index"];
			autom8_log(__FUNCTION__ . " Host[" . $host_id . "] - checking index: " . $snmp_index["snmp_index"], false, "AUTOM8 TRACE", POLLER_VERBOSITY_MEDIUM);

			$sql = "SELECT DISTINCT data_local.id FROM data_local "
			. " LEFT JOIN data_template_data ON (data_local.id=data_template_data.local_data_id) "
			. " LEFT JOIN data_input_data ON (data_template_data.id=data_input_data.data_template_data_id) "
			. " LEFT JOIN data_input_fields ON (data_input_data.data_input_field_id=data_input_fields.id) "
			. " LEFT JOIN snmp_query_graph ON (data_input_data.value=snmp_query_graph.id) "
			. " WHERE data_input_fields.type_code='output_type' "
			. " AND snmp_query_graph.id=" . $rule["graph_type_id"]
			. " AND data_local.host_id=" . $host_id
			. " AND data_local.snmp_query_id=" . $rule["snmp_query_id"]
			. " AND data_local.snmp_index='" . $snmp_query_array["snmp_index"] . "'";

			$existsAlready = db_fetch_cell($sql);
			if (isset($existsAlready) && $existsAlready > 0) {
				autom8_log(__FUNCTION__ . " Host[" . $host_id . "] Not Adding Graph - this graph already exists - DS[$existsAlready]", false, "AUTOM8");
				continue;
			}

			$empty = array(); /* Suggested Values are not been implemented */

			$return_array = create_complete_graph_from_template($graph_template_id, $host_id, $snmp_query_array, $empty);

			if (sizeof($return_array) && array_key_exists("local_graph_id", $return_array) && array_key_exists("local_data_id", $return_array)) {
				$data_source_id = db_fetch_cell("SELECT
						data_template_rrd.local_data_id
						FROM graph_templates_item, data_template_rrd
						WHERE graph_templates_item.local_graph_id = " . $return_array["local_graph_id"] . "
						AND graph_templates_item.task_item_id = data_template_rrd.id
						LIMIT 1");

				foreach($return_array["local_data_id"] as $item) {
					push_out_host($host_id, $item);

					if (strlen($data_source_id)) {
						$data_source_id .= ", " . $item;
					}else{
						$data_source_id = $item;
					}
				}

				autom8_log(__FUNCTION__ . " Host[" . $host_id . "] Graph Added - graph-id: (" . $return_array["local_graph_id"] . ") - data-source-ids: ($data_source_id)", false, "AUTOM8");
			} else {
				autom8_log(__FUNCTION__ . " Host[" . $host_id . "] WARNING: Graph Not Added", false, "AUTOM8");
			}
		}

	}
}


/* create_all_header_nodes - walk across all tree rule items
 * 					- get all related rule items
 * 					- take header type into account
 * 					- create (multiple) header nodes
 *
 * @arg $item_id	id of the host/graph we're working on
 * @arg $rule		the rule we're working on
 * returns			the last tree item that was hooked into the tree
 */
function create_all_header_nodes ($item_id, $rule) {
	global $config, $autom8_tree_header_types;
	include_once($config["base_path"]."/plugins/autom8/autom8_utilities.php");

	# get all related rules that are enabled
	$sql = "SELECT * " .
				"FROM plugin_autom8_tree_rule_items " .
				"WHERE plugin_autom8_tree_rule_items.rule_id=" . $rule["id"] .
				" ORDER BY sequence";
	$tree_items = db_fetch_assoc($sql);
	autom8_log(__FUNCTION__ . " called: Item $item_id sql: $sql matches: " . sizeof($tree_items) . " items", false, "AUTOM8 TRACE", POLLER_VERBOSITY_MEDIUM);

	/* start at the given tree item
	 * it may be worth verifying existance of this entry
	 * in case it was selected once but then deleted
	 */
	$parent_tree_item_id = $rule["tree_item_id"];
	# now walk all rules and create tree nodes
	if (sizeof($tree_items)) {

		/* build magic query, 
		 * for matching hosts JOIN tables host and host_template */
		if ($rule["leaf_type"] == TREE_ITEM_TYPE_HOST) {
			$sql_tables = "FROM host " .
				"LEFT JOIN host_template ON (host.host_template_id = host_template.id) ";

			$sql_where = "WHERE host.id=". $item_id . " ";
		} elseif ($rule["leaf_type"] == TREE_ITEM_TYPE_GRAPH) {
			/* graphs require a different set of tables to be joined */
			$sql_tables = "FROM host " .
				"LEFT JOIN host_template ON (host.host_template_id = host_template.id) " .
				"LEFT JOIN graph_local ON (host.id = graph_local.host_id) " .
				"LEFT JOIN graph_templates ON (graph_local.graph_template_id=graph_templates.id) " .
				"LEFT JOIN graph_templates_graph ON (graph_local.id = graph_templates_graph.local_graph_id) ";

			$sql_where = "WHERE graph_local.id=" . $item_id . " ";
		}

		/* get the WHERE clause for matching hosts */
		$sql_filter = build_matching_objects_filter($rule["id"], AUTOM8_RULE_TYPE_TREE_MATCH);
		
		foreach ($tree_items as $tree_item) {
			if ($tree_item["field"] === AUTOM8_TREE_ITEM_TYPE_STRING) {
				# for a fixed string, use the given text
				$sql = "";
				$target = $autom8_tree_header_types[AUTOM8_TREE_ITEM_TYPE_STRING];
			} else {
				$sql_field = $tree_item["field"] . " AS source ";

				/* now we build up a new query for counting the rows */
				$sql = "SELECT " .
				$sql_field .
				$sql_tables .
				$sql_where . " AND (" . $sql_filter . ")";
				$target = db_fetch_cell($sql);
			}

			autom8_log(__FUNCTION__ . " Item $item_id - sql: $sql matches: " . $target, false, "AUTOM8 TRACE", POLLER_VERBOSITY_MEDIUM);
			$parent_tree_item_id = create_multi_header_node($target, $rule, $tree_item, $parent_tree_item_id);
		}
	}

	return $parent_tree_item_id;
}


/* create_multi_header_node - work on a single header item
 * 							- evaluate replacement rule
 * 							- this may return an array of new header items
 * 							- walk that array to create all header items for this single rule item
 * @arg $target		string (name) of the object; e.g. host_template.name
 * @arg $rule		rule
 * @arg $tree_item	rule item; replacement_pattern may result in multi-line replacement
 * @arg $parent_tree_item_id	parent tree item id
 * returns 			id of the header that was hooked in
 */
function create_multi_header_node($object, $rule, $tree_item, $parent_tree_item_id){
	global $config;
	include_once($config["base_path"]."/plugins/autom8/autom8_utilities.php");
	autom8_log(__FUNCTION__ . " - object: '" . $object . "', Header: '" . $tree_item["search_pattern"] . "', parent: " . $parent_tree_item_id, false, "AUTOM8 TRACE", POLLER_VERBOSITY_MEDIUM);
	
	if ($tree_item["field"] === AUTOM8_TREE_ITEM_TYPE_STRING) {
		$parent_tree_item_id = create_header_node($tree_item["search_pattern"], $rule, $tree_item, $parent_tree_item_id);
		autom8_log(__FUNCTION__ . " called - object: '" . $object . "', Header: '" . $tree_item["search_pattern"] . "', hooked at: " . $parent_tree_item_id, false, "AUTOM8 TRACE", POLLER_VERBOSITY_MEDIUM);
	} else {
		$replacement = autom8_string_replace($tree_item["search_pattern"], $tree_item["replace_pattern"], $object);
		/* build multiline <td> entry */
		#print "<pre>"; print_r($replacement); print "</pre>";

		for ($j=0; sizeof($replacement); $j++) {
			$title = array_shift($replacement);
			$parent_tree_item_id = create_header_node($title, $rule, $tree_item, $parent_tree_item_id);
			autom8_log(__FUNCTION__ . " - object: '" . $object . "', Header: '" . $title . "', hooked at: " . $parent_tree_item_id, false, "AUTOM8 TRACE", POLLER_VERBOSITY_MEDIUM);
		}
	}
	return $parent_tree_item_id;
}


/**
 * create a single tree header node
 * @param string $title				- graph title
 * @param array $rule				- rule
 * @param array $item				- item
 * @param int $parent_tree_item_id	- parent item id
 * @return int						- id of new item
 */
function create_header_node($title, $rule, $item, $parent_tree_item_id) {
	global $config;
	include_once($config["base_path"]."/plugins/autom8/autom8_utilities.php");
	include_once($config["base_path"]."/lib/api_tree.php");

	$id = 0;				# create a new entry
	$local_graph_id = 0;	# headers don't need no graph_id
	$rra_id = 0;			# nor an rra_id
	$host_id = 0;			# or a host_id
	$propagate = ($item["propagate_changes"] != '');

	$new_item = api_tree_item_save($id, $rule["tree_id"], TREE_ITEM_TYPE_HEADER, $parent_tree_item_id,
										$title, $local_graph_id, $rra_id,
										$host_id, $rule["host_grouping_type"], $item["sort_type"], $propagate);

	if (isset($new_item) && $new_item > 0) {
		autom8_log(__FUNCTION__ . " Parent[" . $parent_tree_item_id . "] Tree Item added - id: (" . $new_item . ") Title: (" .$title . ")", false, "AUTOM8");
	} else {
		autom8_log(__FUNCTION__ . " WARNING: Parent[" . $parent_tree_item_id . "] Tree Item not added", false, "AUTOM8");
	}

	return $new_item;
}


/**
 * add a device to the tree
 * @param int $host_id	- host id
 * @param int $parent	- parent id
 * @param array $rule 	- rule
 * @return int			- id of new item
 */
function create_device_node($host_id, $parent, $rule) {
	global $config;
	include_once($config["base_path"]."/plugins/autom8/autom8_utilities.php");
	include_once($config["base_path"]."/lib/api_tree.php");

	$id = 0;				# create a new entry
	$local_graph_id = 0;	# hosts don't need no graph_id
	$title = '';			# nor a title
	$sort_type = 0;			# nor a sort type
	$propagate = false;		# nor a propagation flag

	$new_item = api_tree_item_save($id, $rule["tree_id"], TREE_ITEM_TYPE_HOST, $parent,
										$title, $local_graph_id, $rule["rra_id"],
										$host_id, $rule["host_grouping_type"], $sort_type, $propagate);

	if (isset($new_item) && $new_item > 0) {
		autom8_log(__FUNCTION__ . " Host[" . $host_id . "] Tree Item added - id: (" . $new_item . ")", false, "AUTOM8");
	} else {
		autom8_log(__FUNCTION__ . " WARNING: Host[" . $host_id . "] Tree Item not added", false, "AUTOM8");
	}

	return $new_item;
}


/**
 * add a device to the tree
 * @param int $graph_id	- graph id
 * @param int $parent	- parent id
 * @param array $rule	- rule
 * @return int			- id of new item
 */
function create_graph_node($graph_id, $parent, $rule) {
	global $config;
	include_once($config["base_path"]."/plugins/autom8/autom8_utilities.php");
	include_once($config["base_path"]."/lib/api_tree.php");

	$id = 0;				# create a new entry
	$host_id = 0;			# graphs don't need no host_id
	$title = '';			# nor a title
	$sort_type = 0;			# nor a sort type
	$propagate = false;		# nor a propagation flag

	$new_item = api_tree_item_save($id, $rule["tree_id"], TREE_ITEM_TYPE_GRAPH, $parent,
										$title, $graph_id, $rule["rra_id"],
										$host_id, $rule["host_grouping_type"], $sort_type, $propagate);

	if (isset($new_item) && $new_item > 0) {
		autom8_log(__FUNCTION__ . " Graph[" . $graph_id . "] Tree Item added - id: (" . $new_item . ")", false, "AUTOM8");
	} else {
		autom8_log(__FUNCTION__ . " Graph[" . $graph_id . "] WARNING: Tree Item not added", false, "AUTOM8");
	}

	return $new_item;
}

?>
