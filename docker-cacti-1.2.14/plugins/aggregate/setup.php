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

# define a debugging level specific to AUTOM8
define('AGGREGATE_DEBUG', read_config_option("aggregate_log_verbosity"), true);
define("AGGREGATE_LOG_NONE", 1);
define("AGGREGATE_LOG_FUNCTIONS", 2);
define("AGGREGATE_LOG_DEBUG", 3);

function plugin_aggregate_install () {
	# setup all arrays needed for aggregation
	api_plugin_register_hook('aggregate', 'config_arrays',   'aggregate_config_arrays',   'setup.php');
	api_plugin_register_hook('aggregate', 'config_settings', 'aggregate_config_settings', 'setup.php');

	# setup all forms needed for aggregation
	api_plugin_register_hook('aggregate', 'config_form', 'aggregate_config_form', 'setup.php');

	# provide navigation texts
	api_plugin_register_hook('aggregate', 'draw_navigation_text', 'aggregate_draw_navigation_text', 'setup.php');

	# don't show aggregate graphs from main cacti graphs page
	api_plugin_register_hook('aggregate', 'graphs_sql_where',      'aggregate_graphs_sql_where',      'aggregate.php');

	# add jQuery support as it's a dependency
	api_plugin_register_hook('aggregate', 'page_head', 'aggregate_page_head', 'setup.php');

	# add hooks for graph management
	api_plugin_register_hook('aggregate', 'graphs_action_array',   'aggregate_graphs_action_array',   'setup.php');
	api_plugin_register_hook('aggregate', 'graphs_action_prepare', 'aggregate_graphs_action_prepare', 'aggregate.php');
	api_plugin_register_hook('aggregate', 'graphs_action_execute', 'aggregate_graphs_action_execute', 'aggregate.php');

	# add hooks for graph template management
	api_plugin_register_hook('aggregate', 'graph_templatss_action_array',   'aggregate_graph_templatss_action_array',   'setup.php');
	api_plugin_register_hook('aggregate', 'graph_templatss_action_prepare', 'aggregate_graph_templatss_action_prepare', 'aggregate.php');
	api_plugin_register_hook('aggregate', 'graph_templatss_action_execute', 'aggregate_graph_templatss_action_execute', 'aggregate.php');

	aggregate_setup_table_new ();
}

function plugin_aggregate_uninstall () {
	// Do any extra Uninstall stuff here
}

function plugin_aggregate_check_config () {
	// Here we will check to ensure everything is configured
	aggregate_check_upgrade ();
	return true;
}

function plugin_aggregate_upgrade () {
	// Here we will upgrade to the newest version
	aggregate_check_upgrade ();
	return true;
}

function plugin_aggregate_version () {
	return aggregate_version();
}

function aggregate_check_upgrade () {
	global $config, $database_default;
	include_once($config["library_path"] . "/database.php");

	// Let's only run this check if we are on a page that actually needs the data
	$files = array('aggregate_templates.php', 'aggregate_templates_items.php', 'color_templates.php', 'color_templates_items.php', 'plugins.php');
	if (isset($_SERVER['PHP_SELF']) && !in_array(basename($_SERVER['PHP_SELF']), $files)) {
		return;
	}

	$version = aggregate_version ();
	$current = $version['version'];
	$old     = db_fetch_cell("SELECT version FROM plugin_config WHERE directory='aggregate'");
	if ($current != $old) {

		aggregate_upgrade_1_0 ();

		db_execute("UPDATE plugin_config SET " .
			"version='" . $version["version"] . "', " .
			"name='" . $version["longname"] . "', " .
			"author='" . $version["author"] . "', " .
			"webpage='" . $version["url"] . "' " .
			"WHERE directory='" . $version["name"] . "' ");
	}
}

function aggregate_check_dependencies() {
	global $plugins, $config;
	return true;
}


function aggregate_setup_table_new () {
	global $config, $database_default;
	include_once($config["library_path"] . "/database.php");

	/* list all tables */
	$result = db_fetch_assoc("show tables from `" . $database_default . "`") or die (mysql_error());
	$tables = array();
	foreach($result as $index => $arr) {
		foreach ($arr as $t) {
			$tables[] = $t;
		}
	}
	/* V064 -> V065 tables were renamed */
	if (in_array('plugin_color_templates', $tables)) {
		db_execute("RENAME TABLE $database_default.`plugin_color_templates`  TO $database_default.`plugin_aggregate_color_templates`");
	}
	if (in_array('plugin_color_templates_item', $tables)) {
		db_execute("RENAME TABLE $database_default.`plugin_color_templates_item`  TO $database_default.`plugin_aggregate_color_template_items`");
	}

	$data = array();
	$data['columns'][] = array('name' => 'color_template_id', 'type' => 'mediumint(8)', 'unsigned' => 'unsigned', 'NULL' => false, 'auto_increment' => true);
	$data['columns'][] = array('name' => 'name', 'type' => 'varchar(255)', 'NULL' => false, 'default' => '');
	$data['primary']   = 'color_template_id';
	$data['keys'][]    = ''; # lib/plugins.php _requires_ keys!
	$data['type']      = 'MyISAM';
	$data['comment']   = 'Color Templates';
	api_plugin_db_table_create ('aggregate', 'plugin_aggregate_color_templates', $data);

	$sql[] = "INSERT INTO `plugin_aggregate_color_templates` " .
			"(`color_template_id`, `name`) " .
			"VALUES " .
			"(1, 'Yellow: light -> dark, 4 colors'), " .
			"(2, 'Red: light yellow > dark red, 8 colors'), " .
			"(3, 'Red: light -> dark, 16 colors'), " .
			"(4, 'Green: dark -> light, 16 colors');";

	$data = array();
	$data['columns'][] = array('name' => 'color_template_item_id', 'type' => 'int(12)', 'unsigned' => 'unsigned', 'NULL' => false, 'auto_increment' => true);
	$data['columns'][] = array('name' => 'color_template_id', 'type' => 'mediumint(8)', 'unsigned' => 'unsigned', 'NULL' => false, 'default' => 0);
	$data['columns'][] = array('name' => 'color_id', 'type' => 'mediumint(8)', 'unsigned' => 'unsigned', 'NULL' => false, 'default' => 0);
	$data['columns'][] = array('name' => 'sequence', 'type' => 'mediumint(8)', 'unsigned' => 'unsigned', 'NULL' => false, 'default' => 0);
	$data['primary']   = 'color_template_item_id';
	$data['keys'][]    = ''; # lib/plugins.php _requires_ keys!
	$data['type']      = 'MyISAM';
	$data['comment']   = 'Color Items for Color Templates';
	api_plugin_db_table_create ('aggregate', 'plugin_aggregate_color_template_items', $data);

	$sql[] = "INSERT INTO `plugin_aggregate_color_template_items` " .
			"(`color_template_item_id`, `color_template_id`, `color_id`, `sequence`) " .
			"VALUES " .
			"(1, 1, 4, 1), " .
			"(2, 1, 24, 2), " .
			"(3, 1, 98, 3), " .
			"(4, 1, 25, 4), " .
			"" .
			"(5, 2, 25, 1), " .
			"(6, 2, 29, 2), " .
			"(7, 2, 30, 3), " .
			"(8, 2, 31, 4), " .
			"(9, 2, 33, 5), " .
			"(10, 2, 35, 6), " .
			"(11, 2, 41, 7), " .
			"(12, 2, 9, 8), " .
			"" .
			"(13, 3, 15, 1), " .
			"(14, 3, 31, 2), " .
			"(15, 3, 28, 3), " .
			"(16, 3, 8, 4), " .
			"(17, 3, 34, 5), " .
			"(18, 3, 33, 6), " .
			"(19, 3, 35, 7), " .
			"(20, 3, 41, 8), " .
			"(21, 3, 36, 9), " .
			"(22, 3, 42, 10), " .
			"(23, 3, 44, 11), " .
			"(24, 3, 48, 12), " .
			"(25, 3, 9, 13), " .
			"(26, 3, 49, 14), " .
			"(27, 3, 51, 15), " .
			"(28, 3, 52, 16), " .
			"" .
			"(29, 4, 76, 1), " .
			"(30, 4, 84, 2), " .
			"(31, 4, 89, 3), " .
			"(32, 4, 17, 4), " .
			"(33, 4, 86, 5), " .
			"(34, 4, 88, 6), " .
			"(35, 4, 90, 7), " .
			"(36, 4, 94, 8), " .
			"(37, 4, 96, 9), " .
			"(38, 4, 93, 10), " .
			"(39, 4, 91, 11), " .
			"(40, 4, 22, 12), " .
			"(41, 4, 12, 13), " .
			"(42, 4, 95, 14), " .
			"(43, 4, 6, 15), " .
			"(44, 4, 92, 16);";

	# now run all SQL commands
	if (!empty($sql)) {
		for ($a = 0; $a < count($sql); $a++) {
			$result = db_execute($sql[$a]);
		}
	}

	aggregate_upgrade_1_0 ();
}


function aggregate_upgrade_1_0 () {

	$data = array();
	$data['columns'][] = array('name' => 'id'					, 'type' => 'int(10)'		, 'unsigned' => 'unsigned', 'NULL' => false, 'auto_increment' => true);
	$data['columns'][] = array('name' => 'name'					, 'type' => 'VARCHAR(64)'	, 							'NULL' => false);
	$data['columns'][] = array('name' => 'graph_template_id'	, 'type' => 'int(10)'		, 'unsigned' => 'unsigned', 'NULL' => false);
	$data['columns'][] = array('name' => 'gprint_prefix'		, 'type' => 'VARCHAR(64)'	, 							'NULL' => false);
	$data['columns'][] = array('name' => 'graph_type'			, 'type' => 'INTEGER'		, 'unsigned' => 'unsigned', 'NULL' => false);
	$data['columns'][] = array('name' => 'total'				, 'type' => 'INTEGER'		, 'unsigned' => 'unsigned', 'NULL' => false);
	$data['columns'][] = array('name' => 'total_type'			, 'type' => 'INTEGER'		, 'unsigned' => 'unsigned', 'NULL' => false);
	$data['columns'][] = array('name' => 'total_prefix'			, 'type' => 'VARCHAR(64)'	,							'NULL' => false);
	$data['columns'][] = array('name' => 'order_type'			, 'type' => 'INTEGER'		, 'unsigned' => 'unsigned', 'NULL' => false);
	$data['columns'][] = array('name' => 'created'				, 'type' => 'TIMESTAMP'		,							'NULL' => false);
	$data['columns'][] = array('name' => 'user_id'				, 'type' => 'INTEGER'		, 'unsigned' => 'unsigned', 'NULL' => false);
	$data['primary']   = 'id';
	$data['keys'][]    = array('name' => 'graph_template_id'	, 'columns' => 'graph_template_id');
	$data['keys'][]    = array('name' => 'user_id'				, 'columns' => 'user_id');
	$data['type']      = 'MyISAM';
	$data['comment']   = 'Template Definitions for Aggregate Graphs';
	api_plugin_db_table_create ('aggregate', 'plugin_aggregate_graph_templates', $data);

	$data = array();
	$data['columns'][] = array('name' => 'aggregate_template_id', 'type' => 'int(10)'		, 'unsigned' => 'unsigned', 'NULL' => false);
	$data['columns'][] = array('name' => 'graph_templates_item_id', 'type' => 'int(10)'		, 'unsigned' => 'unsigned', 'NULL' => false);
	$data['columns'][] = array('name' => 'sequence'				, 'type' => 'mediumint(8)'	, 'unsigned' => 'unsigned', 'NULL' => false, 'default' => 0);
	$data['columns'][] = array('name' => 'color_template'		, 'type' => 'int(11)'		, 'unsigned' => 'unsigned', 'NULL' => false);
	$data['columns'][] = array('name' => 'item_skip'			, 'type' => 'CHAR(2)'		, 							'NULL' => false);
	$data['columns'][] = array('name' => 'item_total'			, 'type' => 'CHAR(2)'		, 							'NULL' => false);
	$data['primary']   = 'aggregate_template_id`,`graph_templates_item_id';
	$data['keys'][]    = '';
	$data['type']      = 'MyISAM';
	$data['comment']   = 'Aggregate Template Graph Items';
	api_plugin_db_table_create ('aggregate', 'plugin_aggregate_graph_templates_item', $data);

	$data = array();
	$data['columns'][] = array('name' => 'id'					, 'type' => 'int(10)'		, 'unsigned' => 'unsigned', 'NULL' => false, 'auto_increment' => true);
	$data['columns'][] = array('name' => 'aggregate_template_id', 'type' => 'int(10)'		, 'unsigned' => 'unsigned', 'NULL' => false);
	$data['columns'][] = array('name' => 'template_propogation'	, 'type' => 'CHAR(2)'		, 							'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'local_graph_id'		, 'type' => 'int(10)'		, 'unsigned' => 'unsigned', 'NULL' => false);
	$data['columns'][] = array('name' => 'title_format'			, 'type' => 'VARCHAR(128)'	, 							'NULL' => false);
	$data['columns'][] = array('name' => 'graph_template_id'	, 'type' => 'int(10)'		, 'unsigned' => 'unsigned', 'NULL' => false);
	$data['columns'][] = array('name' => 'gprint_prefix'		, 'type' => 'VARCHAR(64)'	, 							'NULL' => false);
	$data['columns'][] = array('name' => 'graph_type'			, 'type' => 'INTEGER'		, 'unsigned' => 'unsigned', 'NULL' => false);
	$data['columns'][] = array('name' => 'total'				, 'type' => 'INTEGER'		, 'unsigned' => 'unsigned', 'NULL' => false);
	$data['columns'][] = array('name' => 'total_type'			, 'type' => 'INTEGER'		, 'unsigned' => 'unsigned', 'NULL' => false);
	$data['columns'][] = array('name' => 'total_prefix'			, 'type' => 'VARCHAR(64)'	,							'NULL' => false);
	$data['columns'][] = array('name' => 'order_type'			, 'type' => 'INTEGER'		, 'unsigned' => 'unsigned', 'NULL' => false);
	$data['columns'][] = array('name' => 'created'				, 'type' => 'TIMESTAMP'		,							'NULL' => false);
	$data['columns'][] = array('name' => 'user_id'				, 'type' => 'INTEGER'		, 'unsigned' => 'unsigned', 'NULL' => false);
	$data['primary']   = 'id';
	$data['keys'][]    = array('name' => 'aggregate_template_id', 'columns' => 'aggregate_template_id');
	$data['keys'][]    = array('name' => 'local_graph_id'		, 'columns' => 'local_graph_id');
	$data['keys'][]    = array('name' => 'title_format'			, 'columns' => 'title_format');
	$data['keys'][]    = array('name' => 'user_id'				, 'columns' => 'user_id');
	$data['type']      = 'MyISAM';
	$data['comment']   = 'Aggregate Graph Definitions';
	api_plugin_db_table_create ('aggregate', 'plugin_aggregate_graphs', $data);

	$data = array();
	$data['columns'][] = array('name' => 'aggregate_graph_id'	, 'type' => 'int(10)'		, 'unsigned' => 'unsigned', 'NULL' => false);
	$data['columns'][] = array('name' => 'local_graph_id'		, 'type' => 'int(10)'		, 'unsigned' => 'unsigned', 'NULL' => false);
	$data['columns'][] = array('name' => 'sequence'				, 'type' => 'mediumint(8)'	, 'unsigned' => 'unsigned', 'NULL' => false, 'default' => 0);
	$data['primary']   = 'aggregate_graph_id`,`local_graph_id';
	$data['keys'][]    = '';
	$data['type']      = 'MyISAM';
	$data['comment']   = 'Aggregate Graph Items';
	api_plugin_db_table_create ('aggregate', 'plugin_aggregate_graphs_items', $data);

	$data = array();
	$data['columns'][] = array('name' => 'aggregate_graph_id', 'type' => 'int(10)'			, 'unsigned' => 'unsigned', 'NULL' => false);
	$data['columns'][] = array('name' => 'graph_templates_item_id', 'type' => 'int(10)'		, 'unsigned' => 'unsigned', 'NULL' => false);
	$data['columns'][] = array('name' => 'sequence'				, 'type' => 'mediumint(8)'	, 'unsigned' => 'unsigned', 'NULL' => false, 'default' => 0);
	$data['columns'][] = array('name' => 'color_template'		, 'type' => 'int(11)'		, 'unsigned' => 'unsigned', 'NULL' => false);
	$data['columns'][] = array('name' => 'item_skip'			, 'type' => 'CHAR(2)'		, 							'NULL' => false);
	$data['columns'][] = array('name' => 'item_total'			, 'type' => 'CHAR(2)'		, 							'NULL' => false);
	$data['primary']   = 'aggregate_graph_id`,`graph_templates_item_id';
	$data['keys'][]    = '';
	$data['type']      = 'MyISAM';
	$data['comment']   = 'Aggregate Graph Graph Items';
	api_plugin_db_table_create ('aggregate', 'plugin_aggregate_graphs_graph_item', $data);

	/* TODO should this go in a seperate upgrade function? */
	/* Create table holding aggregate template graph params */
	$data = array();
	$data['columns'][] = array('name' => 'aggregate_template_id', 'type' => 'int(10)'		, 'unsigned' => 'unsigned', 'NULL' => false);
	$data['columns'][] = array('name' => 't_image_format_id'	, 'type' => 'char(2)'		, 												'default' => '');
	$data['columns'][] = array('name' => 'image_format_id'		, 'type' => 'tinyint(1)'	, 							'NULL' => false,	'default' => 0);
	$data['columns'][] = array('name' => 't_height'				, 'type' => 'char(2)'		, 												'default' => '');
	$data['columns'][] = array('name' => 'height'				, 'type' => 'mediumint(8)'	, 							'NULL' => false,	'default' => 0);
	$data['columns'][] = array('name' => 't_width'				, 'type' => 'char(2)'		, 												'default' => '');
	$data['columns'][] = array('name' => 'width'				, 'type' => 'mediumint(8)'	, 							'NULL' => false,	'default' => 0);
	$data['columns'][] = array('name' => 't_upper_limit'		, 'type' => 'char(2)'		, 												'default' => '');
	$data['columns'][] = array('name' => 'upper_limit'			, 'type' => 'varchar(20)'	, 							'NULL' => false,	'default' => 0);
	$data['columns'][] = array('name' => 't_lower_limit'		, 'type' => 'char(2)'		, 												'default' => '');
	$data['columns'][] = array('name' => 'lower_limit'			, 'type' => 'varchar(20)'	, 							'NULL' => false,	'default' => 0);
	$data['columns'][] = array('name' => 't_vertical_label'		, 'type' => 'char(2)'		, 												'default' => '');
	$data['columns'][] = array('name' => 'vertical_label'		, 'type' => 'varchar(200)'	, 												'default' => '');
	$data['columns'][] = array('name' => 't_slope_mode'			, 'type' => 'char(2)'		, 												'default' => '');
	$data['columns'][] = array('name' => 'slope_mode'			, 'type' => 'char(2)'		, 												'default' => 'on');
	$data['columns'][] = array('name' => 't_auto_scale'			, 'type' => 'char(2)'		, 												'default' => '');
	$data['columns'][] = array('name' => 'auto_scale'			, 'type' => 'char(2)'		, 												'default' => '');
	$data['columns'][] = array('name' => 't_auto_scale_opts'	, 'type' => 'char(2)'		, 												'default' => '');
	$data['columns'][] = array('name' => 'auto_scale_opts'		, 'type' => 'tinyint(1)'	, 							'NULL' => false,	'default' => 0);
	$data['columns'][] = array('name' => 't_auto_scale_log'		, 'type' => 'char(2)'		, 												'default' => '');
	$data['columns'][] = array('name' => 'auto_scale_log'		, 'type' => 'char(2)'		, 												'default' => '');
	$data['columns'][] = array('name' => 't_scale_log_units'	, 'type' => 'char(2)'		, 												'default' => '');
	$data['columns'][] = array('name' => 'scale_log_units'		, 'type' => 'char(2)'		, 												'default' => '');
	$data['columns'][] = array('name' => 't_auto_scale_rigid'	, 'type' => 'char(2)'		, 												'default' => '');
	$data['columns'][] = array('name' => 'auto_scale_rigid'		, 'type' => 'char(2)'		, 												'default' => '');
	$data['columns'][] = array('name' => 't_auto_padding'		, 'type' => 'char(2)'		, 												'default' => '');
	$data['columns'][] = array('name' => 'auto_padding'			, 'type' => 'char(2)'		, 												'default' => '');
	$data['columns'][] = array('name' => 't_base_value'			, 'type' => 'char(2)'		, 												'default' => '');
	$data['columns'][] = array('name' => 'base_value'			, 'type' => 'mediumint(8)'	, 							'NULL' => false,	'default' => 0);
	$data['columns'][] = array('name' => 't_grouping'			, 'type' => 'char(2)'		, 												'default' => '');
	$data['columns'][] = array('name' => 'grouping'				, 'type' => 'char(2)'		, 							'NULL' => false,	'default' => '');
	$data['columns'][] = array('name' => 't_export'				, 'type' => 'char(2)'		, 												'default' => '');
	$data['columns'][] = array('name' => 'export'				, 'type' => 'char(2)'		, 												'default' => '');
	$data['columns'][] = array('name' => 't_unit_value'			, 'type' => 'char(2)'		, 												'default' => '');
	$data['columns'][] = array('name' => 'unit_value'			, 'type' => 'varchar(20)'	, 												'default' => '');
	$data['columns'][] = array('name' => 't_unit_exponent_value', 'type' => 'char(2)'		, 												'default' => '');
	$data['columns'][] = array('name' => 'unit_exponent_value'	, 'type' => 'varchar(5)'	, 							'NULL' => false,	'default' => '');
	$data['primary']   = 'aggregate_template_id';
	$data['keys'][]    = '';
	$data['type']      = 'MyISAM';
	$data['comment']   = 'Aggregate Template Graph Data';
	api_plugin_db_table_create ('aggregate', 'plugin_aggregate_graph_templates_graph', $data);

	/* TODO should this go in a seperate upgrade function? */
	/* Add cfed and graph_type override columns to aggregate tables */
	$columns = array();
	$columns[] = array('name' => 't_graph_type_id', 'type' => 'char(2)', 'default' => '', 'after' => 'color_template');
	$columns[] = array('name' => 'graph_type_id', 'type' => 'tinyint(3)', 'NULL' => false, 'default' => 0, 'after' => 't_graph_type_id');
	$columns[] = array('name' => 't_cdef_id', 'type' => 'char(2)', 'default' => '', 'after' => 'graph_type_id');
	$columns[] = array('name' => 'cdef_id', 'type' => 'mediumint(8)',  'unsigned' => true, 'NULL' => true, 'after' => 't_cdef_id');
	foreach(array('plugin_aggregate_graphs_graph_item', 'plugin_aggregate_graph_templates_item') as $table) {
		foreach($columns as $column) {
			api_plugin_db_add_column('aggregate', $table, $column);
		}
	}
}

/**
 * Version information (used by update plugin)
 */
function aggregate_version () {
	return array(
		'name'     => 'aggregate',
		'version'  => '1.01',
		'longname' => 'Create Aggregate Graphs',
		'author'   => 'Reinhard Scheck',
		'homepage' => 'http://docs.cacti.net/plugin:aggregate',
		'email'    => 'gandalf@cacti.net',
		'url'      => 'http://docs.cacti.net/plugin:aggregate'
	);
}

/**
 * Draw navigation texts
 * @arg $nav		all navigation texts
 */
function aggregate_draw_navigation_text ($nav) {
	// Displayed navigation text under the blue tabs of Cacti
	$nav["color_templates.php:"]                = array("title" => "Color Templates", "mapping" => "index.php:", "url" => "color_templates.php", "level" => "1");
	$nav["color_templates.php:template_edit"]   = array("title" => "(Edit)", "mapping" => "index.php:,color_templates.php:", "url" => "", "level" => "2");
	$nav["color_templates.php:actions"]         = array("title" => "Actions", "mapping" => "index.php:,color_templates.php:", "url" => "", "level" => "2");
	$nav["color_templates_items.php:item_edit"] = array("title" => "Color Template Items", "mapping" => "index.php:,color_templates.php:,color_templates.php:template_edit", "url" => "", "level" => "3");
	$nav["aggregate_templates.php:"]            = array("title" => "Aggregate Templates", "mapping" => "index.php:", "url" => "aggregate_templates.php", "level" => "1");
	$nav["aggregate_templates.php:edit"]        = array("title" => "(Edit)", "mapping" => "index.php:,aggregate_templates.php:", "url" => "", "level" => "2");
	$nav["aggregate_templates.php:actions"]     = array("title" => "Actions", "mapping" => "index.php:,aggregate_templates.php:", "url" => "", "level" => "2");
	$nav["aggregate_graphs.php:"]               = array("title" => "Aggregate Graphs", "mapping" => "index.php:", "url" => "aggregate_graphs.php", "level" => "1");
	$nav["aggregate_graphs.php:edit"]           = array("title" => "(Edit)", "mapping" => "index.php:,aggregate_graphs.php:", "url" => "", "level" => "2");
	$nav["aggregate_graphs.php:actions"]        = array("title" => "Actions", "mapping" => "index.php:,aggregate_graphs.php:", "url" => "", "level" => "2");
	$nav["aggregate_items.php:"]                = array("title" => "Aggregate Items", "mapping" => "index.php:", "url" => "aggregate_items.php", "level" => "1");
	$nav["aggregate_items.php:item_edit"]       = array("title" => "(Edit)", "mapping" => "index.php:,aggregate_graphs.php:,aggregate_items.php:", "url" => "", "level" => "2");
	$nav["aggregate_items.php:actions"]         = array("title" => "Actions", "mapping" => "index.php:,aggregate_items.php:", "url" => "", "level" => "2");

	return $nav;
}

/**
 * Setup the new dropdown action for Graph Management
 * @arg $action		actions to be performed from dropdown
 */
function aggregate_graphs_action_array($action) {
	$action['plugin_aggregate'] = 'Create Aggregate Graph';
	$action['plugin_aggregate_template'] = 'Create Aggregate from Template';
	return $action;
}

/**
 * Setup the new dropdown action for Graph Template Management
 * @arg $action		actions to be performed from dropdown
 */
function aggregate_graph_templates_action_array($action) {
	$action['plugin_aggregate'] = 'Create Aggregate Template';
	return $action;
}

/**
 * Setup forms needed for this plugin
 */
function aggregate_config_form () {
	# globals defined for use with Color Templates
	global $struct_aggregate, $struct_aggregate_template, $struct_aggregate_graph;
	global $struct_color_template, $struct_color_template_item;
	global $fields_color_template_template_edit, $help_file;
	global $agg_graph_types, $agg_totals, $agg_totals_type, $agg_order_types;
	global $config;

	# unless a hook for 'global_constants' is available, all DEFINEs go here
	define("AGGREGATE_GRAPH_TYPE_KEEP", 0);
	define("AGGREGATE_GRAPH_TYPE_KEEP_STACKED", 50);

	define("AGGREGATE_TOTAL_NONE", 1);
	define("AGGREGATE_TOTAL_ALL", 2);
	define("AGGREGATE_TOTAL_ONLY", 3);

	define("AGGREGATE_TOTAL_TYPE_SIMILAR", 1);
	define("AGGREGATE_TOTAL_TYPE_ALL", 2);

	define("AGGREGATE_ORDER_NONE", 1);
	define("AGGREGATE_ORDER_DS_GRAPH", 2);
	define("AGGREGATE_ORDER_GRAPH_DS", 3);

	$agg_graph_types = array(
		AGGREGATE_GRAPH_TYPE_KEEP 	=> "Keep Graph Types",
		AGGREGATE_GRAPH_TYPE_KEEP_STACKED => "Keep Type and STACK",
		GRAPH_ITEM_TYPE_STACK		=> "Convert to AREA/STACK Graph",
		GRAPH_ITEM_TYPE_LINE1 		=> "Convert to LINE1 Graph",
		GRAPH_ITEM_TYPE_LINE2 		=> "Convert to LINE2 Graph",
		GRAPH_ITEM_TYPE_LINE3 		=> "Convert to LINE3 Graph",
	);

	$agg_totals = array(
		AGGREGATE_TOTAL_NONE 		=> "No Totals",
		AGGREGATE_TOTAL_ALL	 		=> "Print all Legend Items",
		AGGREGATE_TOTAL_ONLY 		=> "Print totaling Legend Items Only",
	);

	$agg_totals_type = array(
		AGGREGATE_TOTAL_TYPE_SIMILAR=> "Total Similar Data Sources",
		AGGREGATE_TOTAL_TYPE_ALL 	=> "Total All Data Sources",
	);

	$agg_order_types = array(
		AGGREGATE_ORDER_NONE => "No Reordering",
		AGGREGATE_ORDER_DS_GRAPH => "Data Source, Graph",
		#AGGREGATE_ORDER_GRAPH_DS => "Graph, Data Source",
	);

	$help_file = $config['url_path'] . "/plugins/aggregate/aggregate_manual.pdf";

	# ------------------------------------------------------------
	# Main Aggregate Parameters
	# ------------------------------------------------------------
	/* file: aggregate.php */
	$struct_aggregate = array(
		"title_format" => array(
			"friendly_name" => "Title",
			"description" => "The new Title of the aggregated Graph.",
			"method" => "textbox",
			"max_length" => "255",
			"value" => "|arg1:title_format|",
		),
		"gprint_prefix" => array(
			"friendly_name" => "Prefix",
			"description" => "A Prefix for all GPRINT lines to distinguish e.g. different hosts.",
			"method" => "textbox",
			"max_length" => "255",
			"value" => "|arg1:gprint_prefix|",
		),
		"aggregate_graph_type" => array(
			"friendly_name" => "Graph Type",
			"description" => "Use this Option to create e.g. STACKed graphs." . "<br>" .
				"AREA/STACK: 1st graph keeps AREA/STACK items, others convert to STACK" . "<br>" .
				"LINE1: all items convert to LINE1 items" . "<br>" .
				"LINE2: all items convert to LINE2 items" . "<br>" .
				"LINE3: all items convert to LINE3 items",
			"method" => "drop_array",
			"value" => "|arg1:aggregate_graph_type|",
			"array" => $agg_graph_types,
			"default" => GRAPH_ITEM_TYPE_STACK,
		),
		"aggregate_total" => array(
			"friendly_name" => "Totaling",
			"description" => "Please check those Items that shall be totaled in the 'Total' column, when selecting any totaling option here.",
			"method" => "drop_array",
			"value" => "|arg1:aggregate_total|",
			"array" => $agg_totals,
			"default" => AGGREGATE_TOTAL_NONE
		),
		"aggregate_total_type" => array(
			"friendly_name" => "Total Type",
			"description" => "Which type of totaling shall be performed.",
			"method" => "drop_array",
			"value" => "|arg1:aggregate_total_type|",
			"array" => $agg_totals_type,
			"default" => AGGREGATE_TOTAL_TYPE_SIMILAR
		),
		"aggregate_total_prefix" => array(
			"friendly_name" => "Prefix for GPRINT Totals",
			"description" => "A Prefix for all <strong>totaling</strong> GPRINT lines.",
			"method" => "textbox",
			"max_length" => "255",
			"value" => "|arg1:aggregate_total_prefix|",
		),
		"aggregate_order_type" => array(
			"friendly_name" => "Reorder Type",
			"description" => "Reordering of Graphs.",
			"method" => "drop_array",
			"value" => "|arg1:aggregate_order_type|",
			"array" => $agg_order_types,
			"default" => AGGREGATE_ORDER_NONE,
		),
		"graph_template_id" => array(
			"method" => "hidden",
			"value" => "|arg1:graph_template_id|",
			"default" => 0
		)
	);

	$struct_aggregate_graph = array(
		"spacer0" => array(
			"friendly_name" => "General Settings",
			"method" => "spacer"
		),
		"title_format" => array(
			"friendly_name" => "Graph Name",
			"description" => "Please name this Aggregate Graph.",
			"method" => "textbox",
			"max_length" => "255",
			"value" => "|arg1:title_format|",
		),
		"template_propogation" => array(
			"friendly_name" => "Propogation Enabled",
			"description" => "Is this to carry the template?",
			"method" => "checkbox",
			"default" => "",
			"value" => "|arg1:template_propogation|"
		),
		"spacer1" => array(
			"friendly_name" => "Aggregate Graph Settings",
			"method" => "spacer"
		),
		"gprint_prefix" => array(
			"friendly_name" => "Prefix",
			"description" => "A Prefix for all GPRINT lines to distinguish e.g. different hosts.  You may use both Host as well as Data Query replacement variables in this prefix.",
			"method" => "textbox",
			"max_length" => "255",
			"value" => "|arg1:gprint_prefix|",
		),
		"graph_type" => array(
			"friendly_name" => "Graph Type",
			"description" => "Use this Option to create e.g. STACKed graphs." . "<br>" .
				"AREA/STACK: 1st graph keeps AREA/STACK items, others convert to STACK" . "<br>" .
				"LINE1: all items convert to LINE1 items" . "<br>" .
				"LINE2: all items convert to LINE2 items" . "<br>" .
				"LINE3: all items convert to LINE3 items",
			"method" => "drop_array",
			"value" => "|arg1:graph_type|",
			"array" => $agg_graph_types,
			"default" => GRAPH_ITEM_TYPE_STACK,
		),
		"total" => array(
			"friendly_name" => "Totaling",
			"description" => "Please check those Items that shall be totaled in the 'Total' column, when selecting any totaling option here.",
			"method" => "drop_array",
			"value" => "|arg1:total|",
			"array" => $agg_totals,
			"default" => AGGREGATE_TOTAL_NONE,
			"on_change" => "changeTotals()",
		),
		"total_type" => array(
			"friendly_name" => "Total Type",
			"description" => "Which type of totaling shall be performed.",
			"method" => "drop_array",
			"value" => "|arg1:total_type|",
			"array" => $agg_totals_type,
			"default" => AGGREGATE_TOTAL_TYPE_SIMILAR,
			"on_change" => "changeTotalsType()",
		),
		"total_prefix" => array(
			"friendly_name" => "Prefix for GPRINT Totals",
			"description" => "A Prefix for all <strong>totaling</strong> GPRINT lines.",
			"method" => "textbox",
			"max_length" => "255",
			"value" => "|arg1:total_prefix|",
		),
		"order_type" => array(
			"friendly_name" => "Reorder Type",
			"description" => "Reordering of Graphs.",
			"method" => "drop_array",
			"value" => "|arg1:order_type|",
			"array" => $agg_order_types,
			"default" => AGGREGATE_ORDER_NONE,
		),
		"id" => array(
			"method" => "hidden",
			"value" => "|arg1:id|",
			"default" => 0
		),
		"local_graph_id" => array(
			"method" => "hidden",
			"value" => "|arg1:local_graph_id|",
			"default" => 0
		),
		"aggregate_template_id" => array(
			"method" => "hidden",
			"value" => "|arg1:aggregate_template_id|",
			"default" => 0
		),
		"graph_template_id" => array(
			"method" => "hidden",
			"value" => "|arg1:graph_template_id|",
			"default" => 0
		)
	);

	$struct_aggregate_template = array(
		"spacer0" => array(
			"friendly_name" => "General Settings",
			"method" => "spacer"
		),
		"name" => array(
			"friendly_name" => "Aggregate Template Name",
			"description" => "Please name this Aggregate Template.",
			"method" => "textbox",
			"max_length" => "255",
			"value" => "|arg1:name|",
		),
		"graph_template_id" => array(
			"friendly_name" => "Source Graph Template",
			"description" => "The Graph Template that this Aggregate Template is based upon.",
			"method" => "drop_sql",
			"value" => "|arg1:graph_template_id|",
			"sql" => "SELECT id, name FROM graph_templates ORDER BY name",
			"default" => 0,
			"none_value" => "None"
		),
		"spacer1" => array(
			"friendly_name" => "Aggregate Template Settings",
			"method" => "spacer"
		),
		"gprint_prefix" => array(
			"friendly_name" => "Prefix",
			"description" => "A Prefix for all GPRINT lines to distinguish e.g. different hosts.  You may use both Host as well as Data Query replacement variables in this prefix.",
			"method" => "textbox",
			"max_length" => "255",
			"value" => "|arg1:gprint_prefix|",
		),
		"graph_type" => array(
			"friendly_name" => "Graph Type",
			"description" => "Use this Option to create e.g. STACKed graphs." . "<br>" .
				"AREA/STACK: 1st graph keeps AREA/STACK items, others convert to STACK" . "<br>" .
				"LINE1: all items convert to LINE1 items" . "<br>" .
				"LINE2: all items convert to LINE2 items" . "<br>" .
				"LINE3: all items convert to LINE3 items",
			"method" => "drop_array",
			"value" => "|arg1:graph_type|",
			"array" => $agg_graph_types,
			"default" => GRAPH_ITEM_TYPE_STACK,
		),
		"total" => array(
			"friendly_name" => "Totaling",
			"description" => "Please check those Items that shall be totaled in the 'Total' column, when selecting any totaling option here.",
			"method" => "drop_array",
			"value" => "|arg1:total|",
			"array" => $agg_totals,
			"default" => AGGREGATE_TOTAL_NONE,
			"on_change" => "changeTotals()",
		),
		"total_type" => array(
			"friendly_name" => "Total Type",
			"description" => "Which type of totaling shall be performed.",
			"method" => "drop_array",
			"value" => "|arg1:total_type|",
			"array" => $agg_totals_type,
			"default" => AGGREGATE_TOTAL_TYPE_SIMILAR,
			"on_change" => "changeTotalsType()",
		),
		"total_prefix" => array(
			"friendly_name" => "Prefix for GPRINT Totals",
			"description" => "A Prefix for all <strong>totaling</strong> GPRINT lines.",
			"method" => "textbox",
			"max_length" => "255",
			"value" => "|arg1:total_prefix|",
		),
		"order_type" => array(
			"friendly_name" => "Reorder Type",
			"description" => "Reordering of Graphs.",
			"method" => "drop_array",
			"value" => "|arg1:order_type|",
			"array" => $agg_order_types,
			"default" => AGGREGATE_ORDER_NONE,
		),
		"_graph_template_id" => array(
			"method" => "hidden",
			"value" => "|arg1:graph_template_id|",
			"default" => 0
		)
	);

	# ------------------------------------------------------------
	# Color Templates
	# ------------------------------------------------------------
	/* file: color_templates.php, action: template_edit */
	$struct_color_template = array(
		"title" => array(
			"friendly_name" => "Title",
			"method" => "textbox",
			"max_length" => "255",
			"default" => "",
			"description" => "The name of this Color Template."
		)
	);

	/* file: color_templates.php, action: item_edit */
	$struct_color_template_item = array(
		"color_id" => array(
			"friendly_name" => "Color",
			"method" => "drop_color",
			"default" => "0",
			"description" => "A nice Color",
			"value" => "|arg1:color_id|",
		)
	);

	/* file: color_templates.php, action: template_edit */
	$fields_color_template_template_edit = array(
		"name" => array(
			"method" => "textbox",
			"friendly_name" => "Name",
			"description" => "A useful name for this Template.",
			"value" => "|arg1:name|",
			"max_length" => "255",
		)
	);
}

/**
 * aggregate_config_settings	- configuration settings for this plugin
 */
function aggregate_config_settings () {
	global $tabs, $settings, $config, $agg_log_verbosity;

	$agg_log_verbosity = array(
		AGGREGATE_LOG_NONE			=> "No AGGREGATE logging",
		AGGREGATE_LOG_FUNCTIONS		=> "Log function calls",
		AGGREGATE_LOG_DEBUG			=> "Log everything",
	);

	/* check for an upgrade */
	plugin_aggregate_check_config();

	if (isset($_SERVER['PHP_SELF']) && basename($_SERVER['PHP_SELF']) != 'settings.php')
		return;

	$temp = array(
		"aggregate_header" => array(
			"friendly_name" => "AGGREGATE",
			"method" => "spacer",
		),
		"aggregate_log_verbosity" => array(
			"friendly_name" => "Poller Logging Level for AGGREGATE",
			"description" => "What level of detail do you want sent to the log file. WARNING: Leaving in any other status than NONE or LOW can exaust your disk space rapidly.",
			"method" => "drop_array",
			"default" => AGGREGATE_LOG_NONE,
			"array" => $agg_log_verbosity,
		)
	);

	/* create a new Settings Tab, if not already in place */
	if (!isset($tabs["misc"])) {
		$tabs["misc"] = "Misc";
	}

	/* and merge own settings into it */
	if (isset($settings["misc"])) {
		$settings["misc"] = array_merge($settings["misc"], $temp);
	}else{
		$settings["misc"] = $temp;
	}
}

/**
 * Setup arrays needed for this plugin
 */
function aggregate_config_arrays () {
	# globals changed
	global $user_auth_realms, $user_auth_realm_filenames;
	global $menu;

	aggregate_check_upgrade ();

	# register all php modules required for this plugin
	api_plugin_register_realm('aggregate', 'color_templates.php,color_templates_items.php,aggregate_templates.php,aggregate_graphs.php,aggregate_items.php', 'Plugin -> Aggregate Administrator', 1);

	# menu titles
	$menu["Management"]['plugins/aggregate/aggregate_graphs.php'] = "Aggregate Graphs";
	$menu["Templates"]['plugins/aggregate/aggregate_templates.php'] = "Aggregate Templates";
	$menu["Templates"]['plugins/aggregate/color_templates.php'] = "Color Templates";
}

function aggregate_page_head() {
	global $config;
	if (substr_count($_SERVER['PHP_SELF'], "aggregate") || basename($_SERVER['PHP_SELF']) == "graphs.php") {
		print "<script type='text/javascript' src='" . $config['url_path'] . "plugins/aggregate/js/jquery.min.js'></script>\n";
	}
}

?>
