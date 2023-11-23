<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2011 The Cacti Group                                 |
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

include_once($config['base_path'] . "/plugins/aggregate/aggregate_functions.php");

/** 
 * Create or update aggregate graph.
 * Save all graph definitions, but omit graph items. Wipe out host_id and graph_template_id.
 *
 * @param int $local_graph_id        - ID of an already existing aggregate graph.
 * @param int $graph_template_id     - ID of the corresponding graph_teamplate.
 * @param string $graph_title        - Title for new graph.
 * @param int $aggregate_template_id - ID of aggregate template (0 if no template).
 * @param array $new_data            - Key/value pairs with new graph data.
 *
 * @return int ID of the new graph.
 */
function aggregate_graph_save($_local_graph_id, $_graph_template_id, $_graph_title, $_aggregate_template_id = 0, $graph_data = array()) {
	/* suppress warnings */
	error_reporting(E_ALL);

	/* install own error handler */
	set_error_handler("aggregate_error_handler");

	aggregate_log(__FUNCTION__ . " local_graph: " . $_local_graph_id . " template: " . $_graph_template_id . " graph title: " . $_graph_title . " aggregate template: " . $_aggregate_template_id, true, "AGGREGATE", AGGREGATE_LOG_FUNCTIONS);

	/* store basic graph info */
	$local_graph_id = aggregate_graph_local_save($_local_graph_id);

	/* store extra graph data */
	$graph_templates_graph_id = aggregate_graph_templates_graph_save($local_graph_id, $_graph_template_id, $_graph_title, $_aggregate_template_id, $graph_data);

	/* restore original error handler */
	restore_error_handler();

	/* return the id of the newly inserted graph */
	return $local_graph_id;
}


/**
 * Creates or updates basic aggregate graph data in graph_local.
 *
 * @param int $id - ID of existing aggregate graph if updating or 0 if creating a new one.
 *
 * @return int ID of graph.
 */
function aggregate_graph_local_save($id = 0) {
	aggregate_log(__FUNCTION__ . " local_graph: " . $id, true, "AGGREGATE", AGGREGATE_LOG_FUNCTIONS);

	/* create or update entry: graph_local */
	$local_graph["id"]                = (isset($id) ? $id : 0);
	$local_graph["graph_template_id"] = 0;  # no templating
	$local_graph["host_id"]           = 0;  # no host to be referred to
	$local_graph["snmp_query_id"]     = 0;  # no templating
	$local_graph["snmp_index"]        = ''; # no templating, may hold string data

	return sql_save($local_graph, "graph_local");
}


/** 
 * Create or update aggregate graphs data in graph_templates_graph.
 * Graph must already exist in graph_local eg. local_graph_id must never be 0
 *
 * @param int $local_graph_id        - ID of graph.
 * @param int $graph_template_id     - Graph template this graph is based on. 
 * @param string $graph_title        - Title of graph. Used only for new graphs.
 * @param int $aggregate_template_id - ID of aggregate template this graph is based on (0 if not aggregate template based).
 * @param array $new_data            - Key/value pairs with new graph data.
 * 
 * @return int ID of record in graph_templates_graph
 */
function aggregate_graph_templates_graph_save($local_graph_id, $graph_template_id, $graph_title = "", $aggregate_template_id = 0, $new_data = array()) {
	aggregate_log(__FUNCTION__ . " local_graph: " . $local_graph_id . " template: " . $graph_template_id . " title: " . $graph_title . " aggregate template: ". $aggregate_template_id, true, "AGGREGATE", AGGREGATE_LOG_FUNCTIONS);

	/* base graph must exist */
	if ($local_graph_id < 1) {
		return 0;
	}

	$graph_data = array();
	$existing_data = db_fetch_row("SELECT * FROM graph_templates_graph WHERE local_graph_id=$local_graph_id");
	$template_data = db_fetch_row("SELECT * FROM graph_templates_graph WHERE graph_template_id=$graph_template_id AND local_graph_id=0");

	if ($aggregate_template_id > 0) {
		/* override selected fields from template data with aggregate template data */
		$aggregate_data = db_fetch_row("SELECT * FROM plugin_aggregate_graph_templates_graph WHERE aggregate_template_id=$aggregate_template_id");
		foreach ($aggregate_data as $field => $value) {
			if (substr($field, 0, 2) == 't_' && $value == 'on') {
				$value_field_name = substr($field, 2);
				$template_data[$value_field_name] = $aggregate_data[$value_field_name];
			}
		}
	}

	if (sizeof($existing_data) == 0) {
		/* this is a new graph, use template data */
		$graph_data = $template_data;

		$graph_data["id"]          = 0;
		$graph_data["title"]       = $graph_title;
		$graph_data["title_cache"] = $graph_title;
	}elseif ($aggregate_template_id > 0) {
		/* this graph exists and is templated from aggregate template,
		 * use template data */
		$graph_data = $template_data;

		$graph_data["id"]          = $existing_data["id"];
		$graph_data["title"]       = $existing_data["title"];
		$graph_data["title_cache"] = $existing_data["title_cache"];
	}else {
		/* this is an existing graph and not templated from aggregate,
		 * re-use its old data */
		$graph_data = $existing_data;
	}

	if ($aggregate_template_id == 0) {
		/* now use new data */
		$graph_data = array_merge($graph_data, $new_data);
	}

	/* safety check - don't allow empty titles */
	if ($graph_title != "") {
		$graph_data["title"] = $graph_title;
		$graph_data["title_cache"] = $graph_title;
	}

	$graph_data["local_graph_id"]                = $local_graph_id;
	$graph_data["local_graph_template_graph_id"] = 0; # no templating
	$graph_data["graph_template_id"]             = 0; # no templating

	$graph_templates_graph_id = sql_save($graph_data, "graph_templates_graph");

	/* update title cache */
	if (!empty($graph_templates_graph_id)) {
		update_graph_title_cache($local_graph_id);
	}

	return $graph_templates_graph_id;
}


/** aggregate_graphs_insert_graph_items	- inserts all graph items of an existing graph
 * @param int $_new_graph_id			- id of the new graph
 * @param int $_old_graph_id			- id of the old graph
 * @param int $_graph_template_id		- template id of the old graph if the old graph is 0
 * @param int $_skip					- graph items to be skipped, array starts at 1
 * @param bool $_hr						- graph items that should have a <HR>
 * @param int $_graph_item_sequence		- sequence number of the next graph item to be inserted
 * @param int $_selected_graph_index	- index of current graph to be inserted
 * @param array $_color_templates		- the color templates to be used
 * @param array $_graph_item_types		- graph_type_ids to override types from original graph item
 * @param array $_cdefs					- cdef_ids to override cdef from original graph item
 * @param int $_graph_type				- conversion to AREA/STACK or LINE required?
 * @param int $_gprint_prefix			- prefix for the legend line
 * @param int $_total					- Totalling: graph items AND/OR legend
 * @param int $_total_type				- Totalling: SIMILAR/ALL data sources
 * @param array $member_graph			- Totalling: Used for determining the con function id
 * @return int							- id of the next graph item to be inserted
 *  */
function aggregate_graphs_insert_graph_items($_new_graph_id, $_old_graph_id, $_graph_template_id,
	$_skip, $_graph_item_sequence, $_selected_graph_index, $_color_templates, $_graph_item_types, $_cdefs,
	$_graph_type, $_gprint_prefix, $_total, $_total_type = "", $member_graphs = "") {

	global $struct_graph_item, $graph_item_types, $config;
	include_once($config['base_path'] . "/plugins/aggregate/aggregate_functions.php");

	/* suppress warnings */
	error_reporting(E_ALL);

	/* install own error handler */
	set_error_handler("aggregate_error_handler");

	aggregate_log(__FUNCTION__ . " called. Insert example graph:$_old_graph_id Graph Template:$_graph_template_id into Graph:$_new_graph_id"
	. " at Sequence:$_graph_item_sequence Graph_No:$_selected_graph_index"
	. " Type Action: " . $_graph_type, true, "AGGREGATE", AGGREGATE_LOG_FUNCTIONS);
	aggregate_log(__FUNCTION__ . " skipping: " . serialize($_skip), true, "AGGREGATE", AGGREGATE_LOG_DEBUG);

	# take graph item data from old one
	if (!empty($_old_graph_id)) {
		$graph_items = db_fetch_assoc("SELECT *
			FROM graph_templates_item
			WHERE local_graph_id=$_old_graph_id
			ORDER BY sequence");

		$graph_local = db_fetch_row("SELECT host_id, graph_template_id, snmp_query_id, snmp_index
			FROM graph_local
			WHERE id=$_old_graph_id");
	}else{
		$graph_items = db_fetch_assoc("SELECT *
			FROM graph_templates_item
			WHERE local_graph_id=$_old_graph_id
			AND graph_template_id=$_graph_template_id
			ORDER BY sequence");

		$graph_local = array();
	}

	/* create new entry(s): graph_templates_item */
	$num_items = sizeof($graph_items);
	if ($num_items > 0) {
		# take care of items having a HR that shall be skipped
		$i = 0;
		for ($i; $i < $num_items; $i++) {
			# remember existing hard returns (array must start at 1 to match $skipped_items
			$_hr[$i+1] = ($graph_items[$i]["hard_return"] != "");
		}
		# move "skipped hard returns" to previous graph item
		$_hr = auto_hr($_skip, $_hr);

		# next entry will have to have a prepended text format
		$prepend    = true;
		$skip_graph = false;
		$make0_cdef = aggregate_cdef_make0();
		$i = 0;

		foreach ($graph_items as $graph_item) {
			# loop starts at 0, but $_skip starts at 1, so increment before comparing
			$i++;
			# go ahead, if this graph item has to be skipped
			if (isset($_skip[$i]) && !empty($_skip[$i])) {
				continue;
			}

			if ($_total == AGGREGATE_TOTAL_ONLY) {
				# if we only need the totalling legend, ...
				if (($graph_item["graph_type_id"] == GRAPH_ITEM_TYPE_GPRINT) || ($graph_item["graph_type_id"] == GRAPH_ITEM_TYPE_COMMENT)) {
					# and this is a legend entry (GPRINT, COMMENT), skip
					continue;
				} else {
					# this is a graph entry, remove text to make it disappear
					# do NOT skip!
					# we need this entry as a DEF
					# and as long as cacti does not provide for a "pure DEF" graph item type
					# we need this workaround
					$graph_item["text_format"] = "";
					# make sure, that this entry does not have a HR,
					# else a single colored mark will be drawn
					$graph_item["hard_return"] = "";
					$_hr[$i] = "";
					# make sure, that data of this item will be suppressed: make 0!
					$graph_item["cdef_id"] = $make0_cdef;

					# try to pick the best totaling cf id
					$graph_item['consolidation_function_id'] = db_fetch_cell("SELECT DISTINCT consolidation_function_id FROM graph_templates_item WHERE color_id>0 AND " . array_to_sql_or($member_graphs, "local_graph_id") . " LIMIT 1");
				}
			}

			# use all data from "old" graph ...
			$save = $graph_item;

			# now it's time for some "special purpose" processing
			# selected fields will need special treatment

			# take care of color changes only if not set to None
			if (isset($_color_templates[$i])) {
				if ($_color_templates[$i] > 0) {
					# get the size of the color templates array
					# if number of colored items exceed array size, use round robin
					$num_colors = db_fetch_cell("SELECT count(color_id)
						FROM plugin_aggregate_color_template_items
						WHERE color_template_id=" . $_color_templates[$i]);

					# templating required, get color for current graph item
					$sql = "SELECT color_id " . 
							"FROM plugin_aggregate_color_template_items " .
							"WHERE color_template_id=" . $_color_templates[$i] .
							" ORDER BY sequence " .
							"LIMIT " . ($_selected_graph_index % $num_colors) . ",1";
					$save["color_id"] = db_fetch_cell($sql);
				} else {
					/* set a color even if no color templating is required */
					$save["color_id"] = $graph_item["color_id"];
				}
			} /* else: no color templating defined, e.g. GPRINT entry */

			# do we want to override cdef of this item
			# certanly not if it was set to $make0_cdef above
			if ($_cdefs[$i] > 0 && $graph_item["cdef_id"] != $make0_cdef) {
				$save["cdef_id"] = $_cdefs[$i];
			}

			# take care of the graph_item_type
			# user may want to override types (ex. LINEx to AREA)
			# do this before we try start converting stuff to AREAs and such
			if ($_graph_item_types[$i] > 0)
				$save["graph_type_id"] = $_graph_item_types[$i];
			else
				$save["graph_type_id"] = $graph_item["graph_type_id"];

			/* change graph types, if requested */
			$save["graph_type_id"] = aggregate_change_graph_type(
										$_selected_graph_index,
										$save["graph_type_id"],
										$_graph_type);

			# new item text format required?
			if ($prepend && ($_total_type == AGGREGATE_TOTAL_TYPE_ALL)) {
				# pointless to add any data source item name here, cause ALL are totaled
				$save["text_format"] = $_gprint_prefix;
				# no more prepending until next line break is encountered
				$prepend = false;
			} elseif ($prepend && (strlen($save["text_format"]) > 0) && (strlen($_gprint_prefix) > 0)) {
				$save["text_format"] = substitute_host_data($_gprint_prefix . " " . $save["text_format"], "|", "|", (isset($graph_local["host_id"]) ? $graph_local["host_id"]:0));
				aggregate_log(__FUNCTION__ . " substituted:" . $save["text_format"], true, "AGGREGATE", AGGREGATE_LOG_DEBUG);

				/* if this is a data query graph type, try to substitute */
				if (isset($graph_local["snmp_query_id"]) && $graph_local["snmp_query_id"] > 0 && strlen($graph_local["snmp_index"]) > 0) {
					$save["text_format"] = substitute_snmp_query_data($save["text_format"], $graph_local["host_id"], $graph_local["snmp_query_id"], $graph_local["snmp_index"], read_config_option("max_data_query_field_length"));
					aggregate_log(__FUNCTION__ . " substituted:" . $save["text_format"] . " for " . $graph_local["host_id"] . "," . $graph_local["snmp_query_id"] . "," . $graph_local["snmp_index"], true, "AGGREGATE", AGGREGATE_LOG_DEBUG);
				}

				# no more prepending until next line break is encountered
				$prepend = false;
			}

			# <HR> wanted?
			if (isset($_hr[$i]) && $_hr[$i] > 0) {
				$save["hard_return"] = "on";
			}
			# if this item defines a line break, remember to prepend next line
			if (strlen($save["text_format"]) > 0) {
				$prepend = ($save["hard_return"] == "on");
			}

			# provide new sequence number
			$save["sequence"] = $_graph_item_sequence;
			aggregate_log(__FUNCTION__ . "  hard return: " . $save["hard_return"] . " sequence: " . $_graph_item_sequence, true, "AGGREGATE", AGGREGATE_LOG_DEBUG);

			$save["id"] 							= 0;
			$save["local_graph_template_item_id"]	= 0;	# disconnect this graph item from the graph template item
			$save["local_graph_id"] 				= (isset($_new_graph_id) ? $_new_graph_id : 0);
			$save["graph_template_id"] 				= 0;	# disconnect this graph item from the graph template
			$save["hash"]                           = '';   # remove any template attribs

			$graph_item_mappings{$graph_item["id"]} = sql_save($save, "graph_templates_item");

			$_graph_item_sequence++;
		}
	}

	/* restore original error handler */
	restore_error_handler();

	# return with next sequence number to be filled
	return $_graph_item_sequence;
}


/**
 * insert or update aggregate graph items in DB tables
 * @param array $items
 * @param string $table
 * @return bool ture if save was succesfull, false otherwise
 */
function aggregate_graph_items_save($items, $table) {
	$defaults = array();
	if ($table == 'plugin_aggregate_graphs_graph_item') {
		$defaults['aggregate_graph_id'] = null;
		$id_field = 'aggregate_graph_id';
	}elseif ($table == 'plugin_aggregate_graph_templates_item') {
		$defaults['aggregate_template_id'] = null;
		$id_field = 'aggregate_template_id';
	}else {
		return false;
	}
	$defaults['graph_templates_item_id'] = null;
	$defaults['sequence'] = 0;
	$defaults['color_template'] = 0;
	$defaults['t_graph_type_id'] = '';
	$defaults['graph_type_id'] = 0;
	$defaults['t_cdef_id'] = '';
	$defaults['cdef_id'] = 0;
	$defaults['item_skip'] = '';
	$defaults['item_total'] = '';

	$items_sql = array();
	foreach ($items as $item) {
		// substitute any missing fields with defaults
		$item = array_merge($defaults, $item);
		// remove any extra fields
		$item = array_intersect_key($item, $defaults);
		// without these graph item makes no sense
		if (!isset($item[$id_field]) || !isset($item['graph_templates_item_id'])) {
			return false;
		}
		// convert to partial SQL statement
		$items_sql[] .= sprintf(
			'(%d, %d, %d, %d, "%s", %d, "%s", %d, "%s", "%s")', 
			$item[$id_field],
			$item['graph_templates_item_id'],
			$item['sequence'],
			$item['color_template'],
			$item['t_graph_type_id'],
			$item['graph_type_id'],
			$item['t_cdef_id'],
			$item['cdef_id'],
			$item['item_skip'],
			$item['item_total']
		);
	}

	$sql = "INSERT INTO $table ";
	$sql .= "(".implode(", ", array_keys($defaults)).") VALUES ";
	$sql .= implode(", ", $items_sql);

	aggregate_log(__FUNCTION__ . " called. SQL: $sql", true, "AGGREGATE", AGGREGATE_LOG_DEBUG);

	/* remove all old items */
	db_execute("DELETE FROM $table WHERE ".$id_field."=".$items[0][$id_field]);
	if (db_execute($sql) == 1) {
		return true;
	}else {
		return false;
	}
}


/**
 * Validate extra graph parameters posted from graph edit form.
 * You can check for validation errors with cacti function is_error_message
 * @param array $posted      - values posted from form
 * @param bool $has_override - form had override checkboxes
 * @return array             - cleaned up graph parameters
 */
function aggregate_validate_graph_params($posted, $has_override = false) {
	$check_post_params = array(
		'image_format_id'     => array('type' => 'int',  'allow_empty' => false, 'default' => 0,  'regex' => ''),
		'height'              => array('type' => 'int',  'allow_empty' => false, 'default' => 0,  'regex' => '^[0-9]+$'),
		'width'               => array('type' => 'int',  'allow_empty' => false, 'default' => 0,  'regex' => '^[0-9]+$'),
		'upper_limit'         => array('type' => 'int',  'allow_empty' => true,  'default' => 0,  'regex' => '^(-?([0-9]+(\.[0-9]*)?|[0-9]*\.[0-9]+)([eE][+\-]?[0-9]+)?)|U$'),
		'lower_limit'         => array('type' => 'int',  'allow_empty' => true,  'default' => 0,  'regex' => '^(-?([0-9]+(\.[0-9]*)?|[0-9]*\.[0-9]+)([eE][+\-]?[0-9]+)?)|U$'),
		'vertical_label'      => array('type' => 'str',  'allow_empty' => true,  'default' => '', 'regex' => ''),
		'slope_mode'          => array('type' => 'bool', 'allow_empty' => true,  'default' => '', 'regex' => ''),
		'auto_scale'          => array('type' => 'bool', 'allow_empty' => true,  'default' => '', 'regex' => ''),
		'auto_scale_opts'     => array('type' => 'int',  'allow_empty' => false, 'default' => 0,  'regex' => ''),
		'auto_scale_log'      => array('type' => 'bool', 'allow_empty' => true,  'default' => '', 'regex' => ''),
		'scale_log_units'     => array('type' => 'bool', 'allow_empty' => true,  'default' => '', 'regex' => ''),
		'auto_scale_rigid'    => array('type' => 'bool', 'allow_empty' => true,  'default' => '', 'regex' => ''),
		'auto_padding'        => array('type' => 'bool', 'allow_empty' => true,  'default' => '', 'regex' => ''),
		'base_value'          => array('type' => 'int',  'allow_empty' => true,  'default' => 0,  'regex' => '^[0-9]+$'),
		'export'              => array('type' => 'bool', 'allow_empty' => true,  'default' => '', 'regex' => ''),
		'unit_value'          => array('type' => 'str',  'allow_empty' => true,  'default' => '', 'regex' => ''),
		'unit_exponent_value' => array('type' => 'int',  'allow_empty' => true,  'default' => '', 'regex' => '^-?[0-9]+$')
	);
	$params_new = array();

	/* validate posted form fields */
	foreach ($check_post_params as $field => $defs) {
		if ($has_override && !isset($posted['t_'.$field])) {
			/* override checkbox off - use default value */
			$params_new['t_'.$field] = '';
			$params_new[$field] = $defs['default'];
			continue;
		}
		if ($has_override) {
			/* override checkbox was on */
			$params_new['t_'.$field] = 'on';
		}
		/* validate value */
		if ($defs['type'] == 'bool') {
			$params_new[$field] = (isset($posted[$field])) ? 'on' : '';
		}else {
			$params_new[$field] = form_input_validate(htmlspecialchars($posted[$field]), $field, $defs['regex'], $defs['allow_empty'], 3);
		}
	}
	return $params_new;
}


/**
 * Populate grraph items array with posted values.
 * $graph_items array must be keyed on graph item id.
 * @param array $posted      - values posted from form
 * @param array $graph_items - reference to graph items array to update with form values
 *
 */
function aggregate_validate_graph_items($posted, &$graph_items) {
	while (list($var,$val) = each($posted)) {
		/* work on color_templates */
		if (preg_match("/^agg_color_([0-9]+)$/", $var, $matches)) {
			/* ================= input validation ================= */
			input_validate_input_number($matches[1]);
			/* ==================================================== */
			$graph_templates_item_id = str_replace('agg_color_', '', $var);
			$graph_items[$graph_templates_item_id]['color_template'] = $val;
		}
		/* work on checkboxed for skipping items */
		if (preg_match("/^agg_skip_([0-9]+)$/", $var, $matches)) {
			/* ================= input validation ================= */
			input_validate_input_number($matches[1]);
			/* ==================================================== */
			$graph_templates_item_id = str_replace('agg_skip_', '', $var);
			$graph_items[$graph_templates_item_id]['item_skip'] = $val;
		}
		/* work on checkboxed for totalling items */
		if (preg_match("/^agg_total_([0-9]+)$/", $var, $matches)) {
			/* ================= input validation ================= */
			input_validate_input_number($matches[1]);
			/* ==================================================== */
			$graph_templates_item_id = str_replace('agg_total_', '', $var);
			$graph_items[$graph_templates_item_id]['item_total'] = $val;
		}
	}
}


/**
 * cleanup of graph items of the new graph
 * @param int $base			- base graph id
 * @param int $aggregate	- graph id of aggregate
 * @param int $reorder		- type of reordering
 */
function aggregate_graphs_cleanup($base, $aggregate, $reorder) {
	global $config;
	include_once($config['base_path'] . "/plugins/aggregate/aggregate_functions.php");
	aggregate_log(__FUNCTION__ . " called. Base " . $base . " Aggregate " . $aggregate . " Reorder: " . $reorder, true, "AGGREGATE", AGGREGATE_LOG_FUNCTIONS);

	/* suppress warnings */
	error_reporting(E_ALL);

	/* install own error handler */
	set_error_handler("aggregate_error_handler");

	/* restore original error handler */
	restore_error_handler();
}


/**
 * reorder graph items
 * @param int $base			- base graph id
 * @param int $aggregate	- graph id of aggregate
 * @param int $reorder		- type of reordering
 */
function aggregate_reorder_ds_graph($base, $graph_template_id, $aggregate, $reorder) {
	global $config;
	include_once($config['base_path'] . "/plugins/aggregate/aggregate_functions.php");
	aggregate_log(__FUNCTION__ . " called. Base Graph " . $base . " Graph Template " . $graph_template_id . " Aggregate Graph " . $aggregate . " Reorder: " . $reorder, true, "AGGREGATE", AGGREGATE_LOG_FUNCTIONS);

	/* suppress warnings */
	error_reporting(E_ALL);

	/* install own error handler */
	set_error_handler("aggregate_error_handler");

	if ($reorder == AGGREGATE_ORDER_DS_GRAPH) {
		$new_seq = 1;
		/* get all different local_data_template_rrd_id's
		 * respecting the order that the aggregated graph has
		 */
		$sql_where = "WHERE gti.local_graph_id=$base" . ($base == 0 ? " AND gti.graph_template_id=$graph_template_id":"");
		$sql_id_column = ($base == 0 ? "id": "local_data_template_rrd_id");
		$sql = "SELECT DISTINCT dtr.$sql_id_column AS local_data_template_rrd_id " .
				"FROM data_template_rrd  AS dtr " .
				"LEFT JOIN graph_templates_item AS gti " .
				"ON (gti.task_item_id=dtr.id) " .
				$sql_where . 
				" ORDER BY gti.sequence";
		$ds_ids = db_fetch_assoc($sql);

		foreach($ds_ids as $ds_id) {
			aggregate_log( "local_data_template_rrd_id: " . $ds_id["local_data_template_rrd_id"], false, "AGGREGATE", AGGREGATE_LOG_DEBUG);
			/* get all different task_item_id's
			 * respecting the order that the aggregated graph has
			 */
			$sql = "SELECT gti.id, gti.task_item_id
				FROM graph_templates_item AS gti
				LEFT JOIN data_template_rrd AS dtr
				ON (gti.task_item_id=dtr.id)
				WHERE gti.local_graph_id=$aggregate
				AND dtr.local_data_template_rrd_id=" . $ds_id["local_data_template_rrd_id"] . "
				ORDER BY sequence";
			aggregate_log(__FUNCTION__ .  " sql: " . $sql, false, "AGGREGATE", AGGREGATE_LOG_DEBUG);
			$items = db_fetch_assoc($sql);

			foreach($items as $item) {
				# accumulate the updates to avoid interfering the next loops
				$updates[] = "UPDATE graph_templates_item SET sequence=" . $new_seq++ . " WHERE id=" . $item["id"];
			}
		}

		# now get all "empty" local_data_template_rrd_id's
		# = those graph items without associated data source (e.g. COMMENT)
		$sql = "SELECT id
			FROM graph_templates_item AS gti
			WHERE gti.local_graph_id=$aggregate
			AND gti.task_item_id=0
			ORDER BY sequence";
		aggregate_log($sql, false, "AGGREGATE", AGGREGATE_LOG_DEBUG);
		$empty_task_items = db_fetch_assoc($sql);
		# now add those "empty" one's to the end
		foreach($empty_task_items as $item) {
			# accumulate the updates to avoid interfering the next loops
			$updates[] = "UPDATE graph_templates_item SET sequence=" . $new_seq++ . " WHERE id=" . $item["id"];
		}
		# now run all updates
		if (sizeof($updates)) {
			foreach($updates as $update) {
				aggregate_log(__FUNCTION__ .  " update: " . $update, false, "AGGREGATE", AGGREGATE_LOG_DEBUG);
				db_execute($update);
			}
		}

	}

	/* restore original error handler */
	restore_error_handler();
}


/**
 * aggregate_graphs_action_execute	- perform aggregate_graph execute action
 * @param string $action			- action to be performed
 * @return $action					- action has to be passed top next plugin in chain
 *  */
function aggregate_graphs_action_execute($action) {
	global $config;

	if ($action != "plugin_aggregate" && $action != "plugin_aggregate_template") {
		/* pass action code to next plugin in chain */
		return $action;
	}
	/* aggregate */

	if (!isset($_POST["selected_items"]) || sizeof($_POST["selected_items"]) < 1) {
		return null;
	}

	/* get common info - not dependant on template/no template*/
	$local_graph_id = 0; // this will be a new graph
	$member_graphs  = unserialize(stripslashes($_POST["selected_items"]));
	$graph_title    = sql_sanitize(form_input_validate(htmlspecialchars($_POST["title_format"]), "title_format", "", true, 3));

	/* future plugin_aggregate_graphs entry */
	$ag_data = array();
	$ag_data['id'] = 0;
	$ag_data['title_format'] = $graph_title;
	$ag_data['user_id']      = $_SESSION['sess_user_id'];

	if ($action == 'plugin_aggregate') {
		if (!isset($_POST["aggregate_total_type"]))   $_POST["aggregate_total_type"]   = 0;
		if (!isset($_POST["aggregate_total"]))        $_POST["aggregate_total"]        = 0;
		if (!isset($_POST["aggregate_total_prefix"])) $_POST["aggregate_total_prefix"] = '';
		if (!isset($_POST["aggregate_order_type"]))   $_POST["aggregate_order_type"]   = 0;
	
		$item_no = form_input_validate(htmlspecialchars($_POST["item_no"]), "item_no", "^[0-9]+$", true, 3);

		$ag_data['aggregate_template_id'] = 0;
		$ag_data['template_propogation']  = '';
		$ag_data['graph_template_id']     = form_input_validate(htmlspecialchars($_POST["graph_template_id"]), "graph_template_id", "^[0-9]+$", true, 3);
		$ag_data['gprint_prefix']         = sql_sanitize(form_input_validate(htmlspecialchars($_POST["gprint_prefix"]), "gprint_prefix", "", true, 3));
		$ag_data['graph_type']            = form_input_validate(htmlspecialchars($_POST["aggregate_graph_type"]), "aggregate_graph_type", "^[0-9]+$", true, 3);
		$ag_data['total']                 = form_input_validate(htmlspecialchars($_POST["aggregate_total"]), "aggregate_total", "^[0-9]+$", true, 3);
		$ag_data['total_type']            = form_input_validate(htmlspecialchars($_POST["aggregate_total_type"]), "aggregate_total_type", "^[0-9]+$", true, 3);
		$ag_data['total_prefix']          = form_input_validate(htmlspecialchars($_POST["aggregate_total_prefix"]), "aggregate_total_prefix", "", true, 3);
		$ag_data['order_type']            = form_input_validate(htmlspecialchars($_POST["aggregate_order_type"]), "aggregate_order_type", "^[0-9]+$", true, 3);
	} else {
		$template_data = db_fetch_row("SELECT * FROM plugin_aggregate_graph_templates WHERE id=" . $_POST['aggregate_template_id']);

		$item_no = db_fetch_cell("SELECT COUNT(*) FROM plugin_aggregate_graph_templates_item WHERE aggregate_template_id=" . $_POST['aggregate_template_id']);

		$ag_data['aggregate_template_id'] = $_POST['aggregate_template_id'];
		$ag_data['template_propogation']  = 'on';
		$ag_data['graph_template_id']     = $template_data['graph_template_id'];
		$ag_data['gprint_prefix']         = $template_data['gprint_prefix'];
		$ag_data['graph_type']            = $template_data['graph_type'];
		$ag_data['total']                 = $template_data['total'];
		$ag_data['total_type']            = $template_data['total_type'];
		$ag_data['total_prefix']          = $template_data['total_prefix'];
		$ag_data['order_type']            = $template_data['order_type'];
	}

	/* create graph in cacti tables */
	$local_graph_id = aggregate_graph_save(
		$local_graph_id,
		$ag_data['graph_template_id'],
		$graph_title,
		$ag_data['aggregate_template_id']
	);

	$ag_data['local_graph_id'] = $local_graph_id;
	$aggregate_graph_id = sql_save($ag_data, 'plugin_aggregate_graphs');
	$ag_data['aggregate_graph_id'] = $aggregate_graph_id;

// 	/* save member graph info */
// 	$i = 1;
// 	foreach($member_graphs as $graph_id) {
// 		db_execute("INSERT INTO plugin_aggregate_graphs_items 
// 			(aggregate_graph_id, local_graph_id, sequence) 
// 			VALUES
// 			($aggregate_graph_id, $graph_id, $i)"
// 		);
// 		$i++;
// 	}

	/* save aggregate graph graph items */
	if ($action == 'plugin_aggregate') {
		/* get existing item ids and sequences from graph template */
		$graph_templates_items = array_rekey(
			db_fetch_assoc("SELECT id, sequence FROM graph_templates_item WHERE local_graph_id=0 AND graph_template_id=" . $ag_data['graph_template_id']),
			"id", array("sequence")
		);

		/* update graph template item values with posted values */
		aggregate_validate_graph_items($_POST, $graph_templates_items);

		$aggregate_graph_items = array();
		foreach ($graph_templates_items as $item_id => $data) {
			$item_new = array();
			$item_new['aggregate_graph_id'] = $aggregate_graph_id;
			$item_new['graph_templates_item_id'] = $item_id;

			$item_new['color_template'] = isset($data['color_template']) ? $data['color_template']:-1;
			$item_new['item_skip']      = isset($data['item_skip']) ? 'on':'';
			$item_new['item_total']     = isset($data['item_total']) ? 'on':'';
			$item_new['sequence']       = isset($data['sequence']) ? $data['sequence']:-1;

			$aggregate_graph_items[] = $item_new;
		}

		aggregate_graph_items_save($aggregate_graph_items, 'plugin_aggregate_graphs_graph_item');
	} else {
		$aggregate_graph_items = db_fetch_assoc("SELECT * FROM plugin_aggregate_graph_templates_item WHERE aggregate_template_id=" . $ag_data['aggregate_template_id']);
	}

	$attribs = $ag_data;
	$attribs['graph_title'] = $ag_data['title_format'];
	$attribs['reorder'] = $ag_data['order_type'];
	$attribs['item_no'] = $item_no;
	$attribs['color_templates'] = array();
	$attribs['skipped_items']   = array();
	$attribs['total_items']     = array();
	$attribs['graph_item_types']= array();
	$attribs['cdefs']           = array();
	foreach ($aggregate_graph_items as $item) {
		if (isset($item['color_template']) && $item['color_template'] > 0)
			$attribs['color_templates'][ $item['sequence'] ] = $item['color_template'];

		if (isset($item['item_skip']) && $item['item_skip'] == 'on')
			$attribs['skipped_items'][ $item['sequence'] ] = $item['sequence'];

		if (isset($item['item_total']) && $item['item_total'] == 'on')
			$attribs['total_items'][ $item['sequence'] ] = $item['sequence'];

		if (isset($item['cdef_id']) && isset($item['t_cdef_id']) && $item['t_cdef_id'] == 'on')
			$attribs['cdefs'][ $item['sequence'] ] = $item['cdef_id'];

		if (isset($item['graph_type_id']) && isset($item['t_graph_type_id']) && $item['t_graph_type_id'] == 'on')
			$attribs['graph_item_types'][ $item['sequence'] ] = $item['graph_type_id'];
	}

	/* create actual graph items */
	aggregate_create_update($local_graph_id, $member_graphs, $attribs);

	header("Location:" . $config["url_path"] . "plugins/aggregate/aggregate_graphs.php?action=edit&tab=details&id=$local_graph_id");
	exit;
}

/**
 * push_out_aggregates				- update all aggregates based upon the template
 * @param int aggregate_template_id	- the aggregate template id
 * @param int local_graph_id		- the specific aggregate graph to update
 *  */
function push_out_aggregates($aggregate_template_id, $local_graph_id = 0) {
	$attribs                    = array();
	$attribs['skipped_items']   = array();
	$attribs['total_items']     = array();
	$attribs['color_templates'] = array();
	$attribs['graph_item_types']= array();
	$attribs['cdefs']           = array();
	$member_graphs              = array();

	if ($local_graph_id > 0 && $aggregate_template_id == 0) {
		$id = db_fetch_cell("SELECT id FROM plugin_aggregate_graphs WHERE local_graph_id=$local_graph_id");
		$attribs['skipped_items'] = array_rekey(db_fetch_assoc("SELECT sequence
			FROM plugin_aggregate_graphs_graph_item
			WHERE item_skip='on' AND aggregate_graph_id=" . $id . " ORDER BY sequence"), "sequence", "sequence");

		$attribs['total_items'] = array_rekey(db_fetch_assoc("SELECT sequence
			FROM plugin_aggregate_graphs_graph_item
			WHERE item_total='on' AND aggregate_graph_id=" . $id . " ORDER BY sequence"), "sequence", "sequence");

		$attribs['color_templates'] = array_rekey(db_fetch_assoc("SELECT sequence, color_template
			FROM plugin_aggregate_graphs_graph_item
			WHERE color_template>=0 AND aggregate_graph_id=" . $id . " ORDER BY sequence"), "sequence", "color_template");

		$attribs['graph_item_types'] = array_rekey(db_fetch_assoc("SELECT sequence, graph_type_id
			FROM plugin_aggregate_graphs_graph_item
			WHERE t_graph_type_id='on' AND aggregate_graph_id=" . $id . " ORDER BY sequence"), "sequence", "graph_type_id");

		$attribs['cdefs'] = array_rekey(db_fetch_assoc("SELECT sequence, cdef_id
			FROM plugin_aggregate_graphs_graph_item
			WHERE t_cdef_id='on' AND aggregate_graph_id=" . $id . " ORDER BY sequence"), "sequence", "cdef_id");

		$attribs['aggregate_graph_id']   = $aggregate_template_id;
		$attribs['template_propogation'] = '';
		$template_data                   = db_fetch_row("SELECT * FROM plugin_aggregate_graphs WHERE id=" . $id);
		$attribs['graph_template_id']    = $template_data['graph_template_id'];
		$attribs['gprint_prefix']        = $template_data['gprint_prefix'];
		$attribs['graph_type']           = $template_data['graph_type'];
		$attribs['total']                = $template_data['total'];
		$attribs['total_type']           = $template_data['total_type'];
		$attribs['total_prefix']         = $template_data['total_prefix'];
		$attribs['reorder']              = $template_data['order_type'];
		$attribs['item_no']              = db_fetch_cell("SELECT COUNT(*) FROM plugin_aggregate_graphs_graph_item WHERE aggregate_graph_id=" . $id);
	}else{
		$attribs['skipped_items'] = array_rekey(db_fetch_assoc("SELECT sequence
			FROM plugin_aggregate_graph_templates_item
			WHERE item_skip='on' AND aggregate_template_id=" . $aggregate_template_id . " ORDER BY sequence"), "sequence", "sequence");

		$attribs['total_items'] = array_rekey(db_fetch_assoc("SELECT sequence
			FROM plugin_aggregate_graph_templates_item
			WHERE item_total='on' AND aggregate_template_id=" . $aggregate_template_id . " ORDER BY sequence"), "sequence", "sequence");

		$attribs['color_templates'] = array_rekey(db_fetch_assoc("SELECT sequence, color_template
			FROM plugin_aggregate_graph_templates_item
			WHERE color_template>=0 AND aggregate_template_id=" . $aggregate_template_id . " ORDER BY sequence"), "sequence", "color_template");

		$attribs['graph_item_types'] = array_rekey(db_fetch_assoc("SELECT sequence, graph_type_id
			FROM plugin_aggregate_graph_templates_item
			WHERE t_graph_type_id='on' AND aggregate_template_id=" . $aggregate_template_id . " ORDER BY sequence"), "sequence", "graph_type_id");

		$attribs['cdefs'] = array_rekey(db_fetch_assoc("SELECT sequence, cdef_id
			FROM plugin_aggregate_graph_templates_item
			WHERE t_cdef_id='on' AND aggregate_template_id=" . $aggregate_template_id . " ORDER BY sequence"), "sequence", "cdef_id");

		$attribs['aggregate_template_id'] = $aggregate_template_id;
		$template_data                    = db_fetch_row("SELECT * FROM plugin_aggregate_graph_templates WHERE id=" . $aggregate_template_id);
		$attribs['template_propogation']  = 'on';
		$attribs['graph_template_id']     = $template_data['graph_template_id'];
		$attribs['gprint_prefix']         = $template_data['gprint_prefix'];
		$attribs['graph_type']            = $template_data['graph_type'];
		$attribs['total']                 = $template_data['total'];
		$attribs['total_type']            = $template_data['total_type'];
		$attribs['total_prefix']          = $template_data['total_prefix'];
		$attribs['reorder']               = $template_data['order_type'];
		$attribs['item_no']               = db_fetch_cell("SELECT COUNT(*) FROM plugin_aggregate_graph_templates_item WHERE aggregate_template_id=" . $aggregate_template_id);
	}

	$aggregate_graphs = array();
	if ($local_graph_id > 0) {
		$aggregate_graphs[] = $local_graph_id;
	}else{
		$graphs = db_fetch_assoc("SELECT local_graph_id FROM plugin_aggregate_graphs WHERE aggregate_template_id=$aggregate_template_id");

		if (sizeof($graphs)) {
			foreach($graphs as $g) {
				$aggregate_graphs[] = $g['local_graph_id'];
			}
		}
	}

	if (sizeof($aggregate_graphs)) {
		foreach($aggregate_graphs as $ag) {
			$member_graphs = array();
			$graphs        = db_fetch_assoc("SELECT DISTINCT agi.local_graph_id 
				FROM plugin_aggregate_graphs AS ag
				INNER JOIN plugin_aggregate_graphs_items AS agi
				ON ag.id=agi.aggregate_graph_id
				WHERE ag.local_graph_id=$ag");

			/* remove all old graph items first */
			if ($ag > 0) {
				db_execute("DELETE FROM graph_templates_item WHERE local_graph_id=$ag");
			}

			if (sizeof($graphs)) {
				foreach($graphs as $mg) {
					$member_graphs[] = $mg['local_graph_id'];
				}

				aggregate_create_update($ag, $member_graphs, $attribs);
			}
		}
	}
}

/**
 * aggregate_create_update			- either create or update an aggregate based on criteria
 * @param int $local_graph_id		- the local graph id of the existing graph.  0 if one needs to be created
 * @param array $member_graphs		- the graphs that will be included in this aggregate
 * @return array $attribs			- the attributes for this new graph
 *  */
function aggregate_create_update(&$local_graph_id, $member_graphs, $attribs) {
	global $config;

	include_once($config['base_path'] . "/plugins/aggregate/aggregate_functions.php");
	aggregate_log(__FUNCTION__ . " called. Grapg id: $local_graph_id", true, "AGGREGATE", AGGREGATE_LOG_FUNCTIONS);

	/* suppress warnings */
	error_reporting(E_ALL);

	/* install own error handler */
	set_error_handler("aggregate_error_handler");

	if (sizeof($member_graphs)) {
		$graph_title          = (isset($attribs['graph_title']) ? $attribs['graph_title']:'');
		$aggregate_template   = (isset($attribs['aggregate_template_id']) ? $attribs['aggregate_template_id']:0);
		$graph_template_id    = (isset($attribs['graph_template_id']) ? $attribs['graph_template_id']:0);
		$aggregate_graph      = (isset($attribs['aggregate_graph_id']) ? $attribs['aggregate_graph_id']:0);
		$template_propogation = (isset($attribs['template_propogation']) ? $attribs['template_propogation']:'on');
		$gprint_prefix        = (isset($attribs['gprint_prefix']) ? $attribs['gprint_prefix']:'');
		$_graph_type          = (isset($attribs['graph_type']) ? $attribs['graph_type']:0);
		$_total               = (isset($attribs['total']) ? $attribs['total']:0);
		$_total_type          = (isset($attribs['total_type']) ? $attribs['total_type']:0);
		$_total_prefix        = (isset($attribs['total_prefix']) ? $attribs['total_prefix']:'');
		$_reorder             = (isset($attribs['reorder']) ? $attribs['reorder']:0);
		$item_no              = (isset($attribs['item_no']) ? $attribs['item_no']:0);
		$color_templates      = (is_array($attribs['color_templates']) ? $attribs['color_templates']:array());
		$graph_item_types     = (is_array($attribs['graph_item_types']) ? $attribs['graph_item_types']:array());
		$cdefs                = (is_array($attribs['cdefs']) ? $attribs['cdefs']:array());
		$skipped_items        = (is_array($attribs['skipped_items']) ? $attribs['skipped_items']:array());
		$total_items          = (is_array($attribs['total_items']) ? $attribs['total_items']:array());
		$example_graph_id     = 0;

		/* save the aggregate information */
		$save1 = array();
		if ($local_graph_id == 0) {
			# create new graph based on first graph selected
			$local_graph_id = aggregate_graph_save($example_graph_id, $graph_template_id, $graph_title, $aggregate_template);
			$save1['id']    = '';
			$new_aggregate  = true;
		}else{
			# update graph params of existing aggregate graph
			$local_graph_id = aggregate_graph_save($local_graph_id, $graph_template_id, $graph_title, $aggregate_template);
			$save1['id']    = db_fetch_cell("SELECT id FROM plugin_aggregate_graphs WHERE local_graph_id=$local_graph_id");
			$new_aggregate  = false;
		}

		$save1['aggregate_template_id'] = $aggregate_template;
		$save1['template_propogation']  = $template_propogation;

		if ($graph_title != '') {
			$save1['title_format'] = $graph_title;
		}

		$save1['local_graph_id']    = $local_graph_id;
		$save1['graph_template_id'] = $graph_template_id;
		$save1['gprint_prefix']     = $gprint_prefix;
		$save1['graph_type']        = $_graph_type;
		$save1['total']             = $_total;
		$save1['total_type']        = $_total_type;
		$save1['total_prefix']      = $_total_prefix;
		$save1['order_type']        = $_reorder;
		$save1['user_id']           = $_SESSION['sess_user_id'];
		$aggregate_graph_id         = sql_save($save1, 'plugin_aggregate_graphs');

		# sequence number of next graph item to be added, index starts at 1
		$next_item_sequence = 1;
		$j = 1; $i = 0;

		/* remove all old graph items first */
		if ($local_graph_id > 0) {
			db_execute("DELETE FROM graph_templates_item WHERE local_graph_id=$local_graph_id");
		}

		/* now add the graphs one by one to the newly created graph
		 * program flow is governed by
		 * - totalling
		 * - new graph type: convert graph to e.g. AREA
		 */
		# loop for all selected graphs
		foreach ($member_graphs as $graph_id) {
			# insert all graph items of selected graph
			# next items to be inserted have to be in sequence
			$next_item_sequence = aggregate_graphs_insert_graph_items(
				$local_graph_id,
				$graph_id,
				$graph_template_id,
				$skipped_items,
				$next_item_sequence,
				$i,
				$color_templates,
				$graph_item_types,
				$cdefs,
				$_graph_type,
				$gprint_prefix,
				$_total,
				"");

			db_execute("REPLACE INTO plugin_aggregate_graphs_items
				(aggregate_graph_id, local_graph_id, sequence)
				VALUES
				($aggregate_graph_id, " .  $graph_id . ", $j)");

			$j++; $i++;
		}

		aggregate_log(__FUNCTION__ . "  all items inserted, next item seq: " . $next_item_sequence . " selGraph: " . $i, true, "AGGREGATE", AGGREGATE_LOG_DEBUG);

		/* post processing for pure LINEx graphs
		 * if we convert to AREA/STACK, the function aggregate_graphs_insert_graph_items
		 * created a pure STACK graph (see comments in that function)
		 * so let's find out, if we have a pure STACK now ...
		 */
		if (aggregate_is_pure_stacked_graph($local_graph_id)) {
			/* ... and convert to AREA */
			aggregate_conditional_convert_graph_type($local_graph_id, GRAPH_ITEM_TYPE_STACK, GRAPH_ITEM_TYPE_AREA);
		}

		if (aggregate_is_stacked_graph($local_graph_id)) {
			/* reorder graph items, if requested
			 * for STACKed graphs, reorder before adding totals */
			aggregate_reorder_ds_graph(
				$example_graph_id,
				$graph_template_id,
				$local_graph_id,
				$_reorder);
		}

		/* special code to add totalling graph items */
		switch ($_total) {
			case AGGREGATE_TOTAL_NONE: # no totalling
				# do NOT add any totalling items
				break;

			case AGGREGATE_TOTAL_ALL: # any totalling option was selected ...
				$_graph_type = GRAPH_ITEM_TYPE_LINE1;

				/* add an empty line before total items */
				db_execute("INSERT INTO graph_templates_item 
					(local_graph_id, graph_type_id, consolidation_function_id, text_format, value, hard_return, gprint_id, sequence)
					VALUES ($local_graph_id, 1, 1, '', '', 'on', 2, ".$next_item_sequence++.")");

			case AGGREGATE_TOTAL_ONLY:
				# use the prefix for totalling GPRINTs as given by the user
				switch ($_total_type) {
					case AGGREGATE_TOTAL_TYPE_SIMILAR:
					case AGGREGATE_TOTAL_TYPE_ALL:
						$gprint_prefix = $_total_prefix;
					break;
				}

				# now skip all items, that are
					# - explicitely marked as skipped (based on $skipped_items)
					# - OR NOT marked as "totalling" items
				for ($k=1; $k<=$item_no; $k++) {
					aggregate_log(__FUNCTION__ . " old skip: " . (isset($skipped_items[$k]) ? $skipped_items[$k]:''), true, "AGGREGATE", AGGREGATE_LOG_DEBUG);

					# skip all items, that shall not be totalled
					if (!isset($total_items[$k])) $skipped_items[$k] = $k;

					aggregate_log(__FUNCTION__ . " new skip: " . (isset($skipped_items[$k]) ? $skipped_items[$k]:''), true, "AGGREGATE", AGGREGATE_LOG_DEBUG);
				}

				# add the "templating" graph to the new graph, honoring skipped, hr and color
				$foo = aggregate_graphs_insert_graph_items(
					$local_graph_id,
					$example_graph_id,
					$graph_template_id,
					$skipped_items,
					$next_item_sequence,
					$i,
					$color_templates,
					$graph_item_types,
					$cdefs,
					$_graph_type,			#TODO: user may choose LINEx instead of assuming LINE1
					$gprint_prefix,
					AGGREGATE_TOTAL_ALL,	# now add the totalling line(s)
					$_total_type);

				# now pay attention to CDEFs
				# next_item_sequence still points to the first totalling graph item
				aggregate_cdef_totalling(
					$local_graph_id,
					$next_item_sequence,
					$_total_type);
		}

		/* post processing for pure LINEx graphs
		 * if we convert to AREA/STACK, the function aggregate_graphs_insert_graph_items
		 * created a pure STACK graph (see comments in that function)
		 * so let's find out, if we have a pure STACK now ...
		 */
		if (aggregate_is_pure_stacked_graph($local_graph_id)) {
			/* ... and convert to AREA */
			aggregate_conditional_convert_graph_type($local_graph_id, GRAPH_ITEM_TYPE_STACK, GRAPH_ITEM_TYPE_AREA);
		}

		if (!aggregate_is_stacked_graph($local_graph_id)) {
			/* reorder graph items, if requested
			 * for non-STACKed graphs, we want to reorder the totals as well */
			aggregate_reorder_ds_graph(
				$example_graph_id,
				$graph_template_id,
				$local_graph_id,
				$_reorder);
		}
	}

	/* restore original error handler */
	restore_error_handler();
}

/**
 * aggregate_graphs_action_prepare	- perform aggregate_graph 				prepare action
 * @param array $save				- drp_action:	selected action from dropdown
 *									  graph_array:	graphs titles selected from graph management's list
 *									  graph_list:	graphs selected from graph management's list
 * @return array $save				- pass $save to next plugin in chain
 *  */
function aggregate_graphs_action_prepare($save) {
	# globals used
	global $colors, $config, $struct_aggregate, $help_file;

	if ($save["drp_action"] == "plugin_aggregate") { /* aggregate */
		include_once($config['base_path'] . "/plugins/aggregate/aggregate_functions.php");
		aggregate_log(__FUNCTION__ . "  called. Parameters: " . serialize($save), true, "AGGREGATE", AGGREGATE_LOG_FUNCTIONS);

		/* suppress warnings */
		error_reporting(E_ALL);

		/* install own error handler */
		set_error_handler("aggregate_error_handler");

		/* initialize return code and graphs array */
		$return_code    = false;
		$graphs         = array();
		$data_sources   = array();
		$graph_template = '';

		if (aggregate_get_data_sources($save, $data_sources, $graph_template)) {
			# close the html_start_box, because it is too small
			print "<td align='right' class='textHeaderDark' bgcolor='#6d88ad'><a class='linkOverDark' href='$help_file' target='_blank'><strong>[Click here for Help]</strong></a></td>";

			html_end_box();

			# provide a new prefix for GPRINT lines
			$gprint_prefix = "|host_hostname|";

			# open a new html_start_box ...
			html_start_box("", "100%", $colors["header_panel"], "3", "center", "");

			/* list affected graphs */
			print "<tr>";
			print "<td class='textArea' bgcolor='#" . $colors["form_alternate1"] . "'>
				<p>Are you sure you want to aggregate the following graphs?</p>
				<ul>" . $save["graph_list"] . "</ul>
			</td>\n";

			/* list affected data sources */
			if (sizeof($data_sources) > 0) {
				print "<td class='textArea' bgcolor='#" . $colors["form_alternate1"] . "'>" .
				"<p>The following data sources are in use by these graphs:</p><ul>";
				foreach ($data_sources as $data_source) {
					print "<li>" . $data_source["name_cache"] . "</li>\n";
				}
				print "</ul></td>\n";
			}
			print "</tr>\n";

			/* aggregate form */
			$_aggregate_defaults = array(
				"title_format" 	=> auto_title($save["graph_array"][0]),
				"graph_template_id" => $graph_template, 
				"gprint_prefix"	=> $gprint_prefix
			);

			draw_edit_form(array(
				"config" => array("no_form_tag" => true),
				"fields" => inject_form_variables($struct_aggregate, $_aggregate_defaults)
			));

			html_end_box();

			# draw all graph items of first graph, including a html_start_box
			draw_aggregate_graph_items_list(0, $graph_template);

			# again, a new html_start_box. Using the one from above would yield ugly formatted NO and YES buttons
			html_start_box("<strong>Please confirm</strong>", "100%", $colors["header"], "3", "center", "");

			# now everything is fine
			$return_code = true;

			?>
			<script type="text/javascript">
			<!--
			function changeTotals() {
				switch ($('#aggregate_total').val()) {
					case '<?php print AGGREGATE_TOTAL_NONE;?>':
						$('#aggregate_total_type').attr('disabled', 'disabled');
						$('#aggregate_total_prefix').attr('disabled', 'disabled');
						$('#aggregate_order_type').removeAttr('disabled');
						break;
					case '<?php print AGGREGATE_TOTAL_ALL;?>':
						$('#aggregate_total_type').removeAttr('disabled');
						$('#aggregate_total_prefix').removeAttr('disabled');
						$('#aggregate_order_type').removeAttr('disabled');
						changeTotalsType();
						break;
					case '<?php print AGGREGATE_TOTAL_ONLY;?>':
						$('#aggregate_total_type').removeAttr('disabled');
						$('#aggregate_total_prefix').removeAttr('disabled');
						$('#aggregate_order_type').attr('disabled', 'disabled');
						changeTotalsType();
						break;
				}
			}

			function changeTotalsType() {
				if (($('#aggregate_total_type').val() == <?php print AGGREGATE_TOTAL_TYPE_SIMILAR;?>)) {
					$('#aggregate_total_prefix').attr('value', 'Total');
				} else if (($('#aggregate_total_type').val() == <?php print AGGREGATE_TOTAL_TYPE_ALL;?>)) {
					$('#aggregate_total_prefix').attr('value', 'All Items');
				}
			}

			$().ready(function() {
				$('#aggregate_total').change(function() {
					changeTotals();
				});

				$('#aggregate_total_type').change(function() {
					changeTotalsType();
				});

				changeTotals();
			});
			-->
			</script>
			<?php
		}

		/* restore original error handler */
		restore_error_handler();

		return $return_code;
	}elseif ($save["drp_action"] == "plugin_aggregate_template") { /* aggregate template */
		include_once($config['base_path'] . "/plugins/aggregate/aggregate_functions.php");
		aggregate_log(__FUNCTION__ . "  called. Parameters: " . serialize($save), true, "AGGREGATE", AGGREGATE_LOG_FUNCTIONS);

		/* suppress warnings */
		error_reporting(E_ALL);

		/* install own error handler */
		set_error_handler("aggregate_error_handler");

		/* initialize return code and graphs array */
		$return_code    = false;
		$graphs         = array();
		$data_sources   = array();
		$graph_template = '';

		/* find out which (if any) data sources are being used by this graph, so we can tell the user */
		if (aggregate_get_data_sources($save, $data_sources, $graph_template)) {
			$aggregate_templates = db_fetch_assoc("SELECT id, name FROM plugin_aggregate_graph_templates WHERE graph_template_id=$graph_template ORDER BY name");

			if (sizeof($aggregate_templates)) {
				/* list affected graphs */
				print "<tr>
					<td colspan='2' class='textArea' bgcolor='#" . $colors["form_alternate1"] . "'>
						<p>Select the Aggregate Template to use and press 'Continue' to create your Aggregate Graph.  Otherwise press 'Cancel' to return.</p>
						<ul>" . $save["graph_list"] . "</ul>
					</td>
				</tr>\n";
				print "<tr>
					<td><strong>Graph Title:</strong></td>
					<td><input name='title_format' size='40'></td>
					</tr>
					<tr>
					<td><strong>Aggregate Template:</strong></td>
					<td>
						<select name='aggregate_template_id'>\n";
							html_create_list($aggregate_templates, "name", "id", $aggregate_templates[0]['id']);
				print "</select>
					</td>
				</tr>\n";

				html_end_box();

				# again, a new html_start_box. Using the one from above would yield ugly formatted NO and YES buttons
				html_start_box("<strong>Please confirm</strong>", "60%", $colors["header"], "3", "center", "");

				# now everything is fine
				$return_code = true;
			}else{
				/* present an error message as there are no templates */
				print "<tr>
					<td colspan='2' class='textArea' bgcolor='#" . $colors["form_alternate1"] . "'>
						<p>There are presently no Aggregate Templates defined for this Graph Template.  Please either first
						create an Aggregate Template for the selected Graph's Graph Template and try again, or 
						simply crease an un-templated Aggregate Graph.</p>
					</td>
				</tr>\n";

				html_end_box();

				# again, a new html_start_box. Using the one from above would yield ugly formatted NO and YES buttons
				html_start_box("<strong>Press 'Return' to return</strong>", "60%", $colors["header"], "3", "center", "");

				?>
				<script type='text/javascript'>
				$().ready(function() {
					$('#continue').hide();
					$('#cancel').attr('value', 'Return');
				});
				</script>
				<?php

				$return_code = false;
			}
		}

		/* restore original error handler */
		restore_error_handler();

		return $return_code;
	}else{
		/* pass action to next plugin in chain */
		return $save;
	}
}

function aggregate_get_data_sources($save, &$data_sources, &$graph_template) {
	global $colors;

	/* find out which (if any) data sources are being used by this graph, so we can tell the user */
	if (isset($save["graph_array"])) {
		# fetch all data sources for all selected graphs
		$data_sources = db_fetch_assoc("SELECT
			data_template_data.local_data_id,
			data_template_data.name_cache
			FROM (data_template_rrd,data_template_data,graph_templates_item)
			WHERE graph_templates_item.task_item_id=data_template_rrd.id
			AND data_template_rrd.local_data_id=data_template_data.local_data_id
			AND " . array_to_sql_or($save["graph_array"], "graph_templates_item.local_graph_id") . "
			AND data_template_data.local_data_id>0
			GROUP BY data_template_data.local_data_id
			ORDER BY data_template_data.name_cache");

		# verify, that only a single graph template is used, else
		# aggregate will look funny
		$sql = "SELECT DISTINCT graph_templates.id, graph_templates.name
			FROM graph_local
			LEFT JOIN graph_templates ON (graph_local.graph_template_id=graph_templates.id)
			WHERE (" . array_to_sql_or($save["graph_array"], "graph_local.id") . ") 
			AND graph_local.graph_template_id>0";
		$used_graph_templates = db_fetch_assoc($sql);

		if (sizeof($used_graph_templates) > 1) {
			# this is invalid! STOP
			print "<tr><td colspan='2' class='textArea' bgcolor='#" . $colors["form_alternate1"] . "'>" .
			"<p>The Graphs chosen for the Aggregate Graph below represent Graphs from multiple Graph Templates. 
			Aggregate does not support creating Aggregate Graphs from multiple Graph Templates.</p>";
			print "<ul>";
			foreach ($used_graph_templates as $graph_template) {
				print "<li>" . $graph_template["name"] . "</li>\n";
			}
			print "</ul></td></tr>";

			html_end_box();

			# again, a new html_start_box. Using the one from above would yield ugly formatted NO and YES buttons
			html_start_box("<strong>Press 'Return' to return and select different Graphs</strong>", "60%", $colors["header"], "3", "center", "");

			?>
			<script type='text/javascript'>
			$().ready(function() {
				$('#continue').hide();
				$('#cancel').attr('value', 'Return');
			});
			</script>
			<?php
		} elseif (sizeof($used_graph_templates) < 1) {
			/* selected graphs do not use templates */
			print "<tr><td colspan='2' class='textArea' bgcolor='#" . $colors["form_alternate1"] . "'>" .
			"<p>The Graphs chosen for the Aggregate Graph do not use Graph Templates. 
			Aggregate does not support creating Aggregate Graphs from non-templated graphs.</p>";
			print "</td></tr>";

			html_end_box();

			# again, a new html_start_box. Using the one from above would yield ugly formatted NO and YES buttons
			html_start_box("<strong>Press 'Return' to return and select different Graphs</strong>", "60%", $colors["header"], "3", "center", "");

			?>
			<script type='text/javascript'>
			$().ready(function() {
				$('#continue').hide();
				$('#cancel').attr('value', 'Return');
			});
			</script>
			<?php
		}else{
			$graph_template = $used_graph_templates[0]['id'];

			return true;
		}
	}

	return false;
}

/**
 * draw_aggregate_template_graph_items_list - draw graph item list
 *
 * @param int $_graph_template_id - id of the graph for which the items shall be listed
 # @param int $_object            - either the aggregate or aggregate_template
 */
function draw_aggregate_graph_items_list($_graph_id = 0, $_graph_template_id = 0, $_object = array()) {
	global $colors, $config;
	aggregate_log(__FUNCTION__ . "  called. graph: " . $_graph_id . " template: " . $_graph_template_id, true, "AGGREGATE", AGGREGATE_LOG_FUNCTIONS);

	include($config["include_path"] . "/global_arrays.php");

	if ($_graph_id == 0 && $_graph_template_id == 0) {
		return null;
	}

	/* fetch graph items */
	if ($_graph_id == 0) {
		$item_list_where = "gti.local_graph_id=0 AND graph_template_id=$_graph_template_id ";
	}
	if ($_graph_template_id == 0) {
		$item_list_where = "gti.local_graph_id=$_graph_id ";
	}
	$item_list = db_fetch_assoc("SELECT
		gti.id, gti.text_format, gti.value, gti.hard_return, gti.graph_type_id,
		gti.consolidation_function_id, cdef.name as cdef_name, colors.hex
		FROM graph_templates_item AS gti
		LEFT JOIN cdef ON (gti.cdef_id=cdef.id)
		LEFT JOIN colors ON (gti.color_id=colors.id)
		WHERE $item_list_where
		ORDER BY gti.sequence");

	/* fetch color templates */
	$color_templates = db_fetch_assoc("SELECT color_template_id, name FROM plugin_aggregate_color_templates ORDER BY name");

	$current_vals = array();
	$is_edit = false;
	$is_templated = false;
	if (sizeof($_object) > 0 && $_object["id"] > 0) {
		/* drawing items for existing aggregate graph/template */
		$is_edit =true;
		/* fetch existing item values */
		if (isset($_object['aggregate_template_id']) && $_object['aggregate_template_id'] == 0) {
			/* this is aggregate graph with no aggregate template */
			$current_vals = db_fetch_assoc("SELECT * FROM plugin_aggregate_graphs_graph_item WHERE aggregate_graph_id=" . $_object['id']);
			$item_editor_link_param = "aggregate_graph_id=".$_object['id']."&local_graph_id=".$_object['local_graph_id'];
			$is_templated = false;
		}elseif (isset($_object['aggregate_template_id'])) {
			/* this is aggregate graph from aggregate template */
			$current_vals = db_fetch_assoc("SELECT * FROM plugin_aggregate_graph_templates_item WHERE aggregate_template_id=" . $_object['aggregate_template_id']);
			$item_editor_link_param = "aggregate_template_id=".$_object['aggregate_template_id'];
			$is_templated = true;
		}else{
			/* this is aggregate template */
			$current_vals = db_fetch_assoc("SELECT * FROM plugin_aggregate_graph_templates_item WHERE aggregate_template_id=" . $_object['id']);
			$item_editor_link_param = "aggregate_template_id=".$_object['id'];
			$is_templated = true;
		}
		/* key results on item id */
		$current_vals = array_rekey(
			$current_vals, 
			"graph_templates_item_id",
			array("color_template", "item_skip", "item_total", "t_graph_type_id", "graph_type_id")
		);
	}
	
	# draw list of graph items
	html_start_box("<strong>Graph ".($is_templated ? "Template " : "")."Items</strong>", "100%", $colors["header"], "3", "center", "");

	# print column header
	print "<tr bgcolor='#" . $colors["header_panel"] . "'>";
	DrawMatrixHeaderItem("Graph Item",$colors["header_text"],1);
	DrawMatrixHeaderItem("Data Source",$colors["header_text"],1);
	DrawMatrixHeaderItem("Graph Item Type",$colors["header_text"],1);
	DrawMatrixHeaderItem("CF Type",$colors["header_text"],1);
	DrawMatrixHeaderItem("Item Color",$colors["header_text"],2);
	DrawMatrixHeaderItem("Color Template",$colors["header_text"],1);
	DrawMatrixHeaderItem("Skip",$colors["header_text"],1);
	DrawMatrixHeaderItem("Total",$colors["header_text"],1);
	print "</tr>";

	$group_counter = 0; $_graph_type_name = ""; $i = 0;
	$alternate_color_1 = $colors["alternate"]; $alternate_color_2 = $colors["alternate"];

	if (sizeof($item_list) > 0) {
		foreach ($item_list as $item) {
			/* graph grouping display logic */
			$this_row_style = ""; $use_custom_row_color = false; $hard_return = "";

			if ($graph_item_types{$item["graph_type_id"]} != "GPRINT") {
				$this_row_style = "font-weight: bold;"; $use_custom_row_color = true;

				if ($group_counter % 2 == 0) {
					$alternate_color_1 = "EEEEEE";
					$alternate_color_2 = "EEEEEE";
					$custom_row_color  = "D5D5D5";
				}else{
					$alternate_color_1 = $colors["alternate"];
					$alternate_color_2 = $colors["alternate"];
					$custom_row_color  = "D2D6E7";
				}

				$group_counter++;
			}

			/* values can be overriden in aggregate graph/template */
			if ($is_edit && $current_vals[$item['id']]['t_graph_type_id'] == "on") {
				$item['graph_type_id'] = $current_vals[$item['id']]['graph_type_id'];
			}

			/* alternating row color */
			if ($use_custom_row_color == false) {
				form_alternate_row_color($alternate_color_1,$alternate_color_2,$i);
			}else{
				print "<tr bgcolor='#$custom_row_color'>";
			}

			/* column "Graph Item" */
			print "<td><strong>";
			if ($is_edit == false) {
				/* no existing aggregate graph/template */
				print 'Item # ' . ($i+1);
			}elseif (isset($_object['template_propogation']) && $_object['template_propogation']) {
				/* existing aggregate graph with template propagation enabled */
				print 'Item # ' . ($i+1);
			}else {
				/* existing aggregate template or graph with no templating */
				/* create a link to graph item editor */
				print '<a href="aggregate_items.php?action=item_edit&'.$item_editor_link_param.'&id='.$item['id'].'">Item # ' . ($i+1) . '</a>';
			}
			print "</strong></td>\n";

			/* column "Data Source" */
			$_graph_type_name = $graph_item_types{$item["graph_type_id"]};
			switch (true) {
				case ereg("(AREA|STACK|GPRINT|LINE[123])", $_graph_type_name):
					$matrix_title = $item["text_format"];
					break;
				case ereg("(HRULE|VRULE)", $_graph_type_name):
					$matrix_title = "HRULE: " . $item["value"];
					break;
				case ereg("(COMMENT)", $_graph_type_name):
					$matrix_title = "COMMENT: " . $item["text_format"];
					break;
			}
			if ($item["hard_return"] == "on") {
				$hard_return = "<strong><font color=\"#FF0000\">&lt;HR&gt;</font></strong>";
			}
			print "<td style='$this_row_style'>" . htmlspecialchars($matrix_title) . $hard_return . "</td>\n";

			/* column "Graph Item Type" */
			print "<td style='$this_row_style'>" . $graph_item_types{$item["graph_type_id"]} . "</td>\n";

			/* column "CF Type" */
			print "<td style='$this_row_style'>" . $consolidation_functions{$item["consolidation_function_id"]} . "</td>\n";

			/* column "Item Color" */
			print "<td" . ((!empty($item["hex"])) ? " bgcolor='#" . $item["hex"] . "'" : "") . " width='1%'>&nbsp;</td>\n";
			print "<td style='$this_row_style'>" . $item["hex"] . "</td>\n";

			/* column "Color Template" */
			print "<td>";
			if (!empty($item["hex"])) {
				print "<select id='agg_color_" . $item['id'] ."' name='agg_color_" . $item['id'] ."'>";
				print "<option value='0' selected>None</option>\n";
				html_create_list($color_templates, "name", "color_template_id", ($is_edit && isset($current_vals[$item['id']]['color_template']) ? $current_vals[$item['id']]['color_template']:''));
				print "</select>\n";
			}
			print "</td>";

			/* column "Skip" */
			print "<td style='" . get_checkbox_style() ."' width='1%' align='center'>";
			print "<input id='agg_skip_" . $item['id'] . "' type='checkbox' style='margin: 0px;' name='agg_skip_" . $item['id'] . "' title='" . $item["text_format"] . "' " . ($is_edit && $current_vals[$item['id']]['item_skip'] == 'on' ? 'checked':'') . ">";
			print "</td>";

			/* column "Total" */
			print "<td style='" . get_checkbox_style() ."' width='1%' align='center'>";
			print "<input id='agg_total_" . ($item['id']) . "' type='checkbox' style='margin: 0px;' name='agg_total_" . ($item['id']) . "' title='" . $item["text_format"] . "' " . ($is_edit && $current_vals[$item['id']]['item_total'] == 'on' ? 'checked':'') . ">";
			print "</td>";

			print "</tr>";

			$i++;
		}
	}else{
		print "<tr bgcolor='#" . $colors["form_alternate2"] . "'><td colspan='7'><em>No Items</em></td></tr>";
	}

	html_end_box();
	form_hidden_box("item_no", sizeof($item_list), sizeof($item_list));
}


/**
 * draw graph configuration form so user can override some graph template parametes
 *
 * @param int $aggregate_template_id - aggregate graph template beeing edited
 * @param int $graph_template_id     - graph template this aggregate template is based on
 */
function draw_aggregate_template_graph_config($aggregate_template_id, $graph_template_id) {
	global $colors, $struct_graph;

	html_start_box("<strong>Graph Configuration</strong>", "100%", $colors["header"], "3", "center", "");
	$aggregate_templates_graph = db_fetch_row("SELECT * FROM plugin_aggregate_graph_templates_graph WHERE aggregate_template_id=" . $aggregate_template_id);
	$graph_templates_graph = db_fetch_row("SELECT * FROM graph_templates_graph WHERE graph_template_id=" . $graph_template_id);

		$form_array = array();

		while (list($field_name, $field_array) = each($struct_graph)) {
			if ($field_name == "title")
				continue;

			$form_array += array($field_name => $struct_graph[$field_name]);

			/* value from graph template or aggregate graph template 
			(based on value of t_$field_name of aggregate_template_graph) */
			if (isset($aggregate_templates_graph) && $aggregate_templates_graph["t_".$field_name] == "on")
				$value = $aggregate_templates_graph[$field_name];
			else
				$value = $graph_templates_graph[$field_name];

			$form_array[$field_name]["value"] = $value;
			$form_array[$field_name]["sub_checkbox"] = array(
				"name" => "t_" . $field_name,
				"friendly_name" => "Override this Value<br>",
				"value" => (isset($aggregate_templates_graph) ? $aggregate_templates_graph{"t_" . $field_name} : ""),
				"on_change" => "toggleFieldEnabled(this);"
			);
		}

		draw_edit_form(
			array(
				"config" => array("no_form_tag" => true),
				"fields" => $form_array
				)
			);

		/* some javascript do dinamically disable non-overriden fields */
?>
<script language="JavaScript">
<!--
$().ready(function() {
	setFieldsDisabled();
});

// disable all items with sub-checkboxes except
// where sub-checkbox checked
function setFieldsDisabled() {
	$('tr[id*="row_"]').each(function() {
		fieldName = this.id.substr(4);
		cbName = 't_'+fieldName;
		if ($('#'+cbName).size() > 0) {
			console.log($('#'+cbName));
			$('#'+fieldName).attr('disabled', !$('#'+cbName).attr('checked'));
		}
	});
}

// enable or disable form field based on state of corresponding checkbox
function toggleFieldEnabled(cb) {
	prefix = 't_';
	if (cb.name.substr(0,prefix.length) == prefix) {
		fieldName = cb.name.substr(prefix.length);
		$('#'+fieldName).attr('disabled', !cb.checked);
	}
}

-->
</script>
<?php
		html_end_box(false);
}


function aggregate_graphs_sql_where($sql_where) {
	$sql_where .= " AND (graph_local.host_id!=0 AND graph_local.graph_template_id!=0)";
	return $sql_where;
}

?>
