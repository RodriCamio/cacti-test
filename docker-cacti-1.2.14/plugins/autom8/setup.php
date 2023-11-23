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

define("AUTOM8_OP_NONE", 0);
define("AUTOM8_OP_CONTAINS", 1);
define("AUTOM8_OP_CONTAINS_NOT", 2);
define("AUTOM8_OP_BEGINS", 3);
define("AUTOM8_OP_BEGINS_NOT", 4);
define("AUTOM8_OP_ENDS", 5);
define("AUTOM8_OP_ENDS_NOT", 6);
define("AUTOM8_OP_MATCHES", 7);
define("AUTOM8_OP_MATCHES_NOT", 8);
define("AUTOM8_OP_LT", 9);
define("AUTOM8_OP_LE", 10);
define("AUTOM8_OP_GT", 11);
define("AUTOM8_OP_GE", 12);
define("AUTOM8_OP_UNKNOWN", 13);
define("AUTOM8_OP_NOT_UNKNOWN", 14);
define("AUTOM8_OP_EMPTY", 15);
define("AUTOM8_OP_NOT_EMPTY", 16);
define("AUTOM8_OP_REGEXP", 17);
define("AUTOM8_OP_NOT_REGEXP", 18);

define("AUTOM8_OPER_NULL", 0);
define("AUTOM8_OPER_AND", 1);
define("AUTOM8_OPER_OR", 2);
define("AUTOM8_OPER_LEFT_BRACKET", 3);
define("AUTOM8_OPER_RIGHT_BRACKET", 4);

define("AUTOM8_TREE_ITEM_TYPE_STRING", "0");

define("AUTOM8_RULE_TYPE_GRAPH_MATCH", 1);
define("AUTOM8_RULE_TYPE_GRAPH_ACTION", 2);
define("AUTOM8_RULE_TYPE_TREE_MATCH", 3);
define("AUTOM8_RULE_TYPE_TREE_ACTION", 4);

# pseudo table name required as long as Data Query XML resides in files
define("AUTOM8_RULE_TABLE_XML", "XML");

define("AUTOM8_ACTION_GRAPH_DUPLICATE", 1);
define("AUTOM8_ACTION_GRAPH_ENABLE", 2);
define("AUTOM8_ACTION_GRAPH_DISABLE", 3);
define("AUTOM8_ACTION_GRAPH_DELETE", 99);
define("AUTOM8_ACTION_TREE_DUPLICATE", 1);
define("AUTOM8_ACTION_TREE_ENABLE", 2);
define("AUTOM8_ACTION_TREE_DISABLE", 3);
define("AUTOM8_ACTION_TREE_DELETE", 99);

# define a debugging level specific to AUTOM8
define('AUTOM8_DEBUG', read_config_option("autom8_log_verbosity"), true);
/**
 * plugin_autom8_install	- Initialize the plugin and setup all hooks
 */
function plugin_autom8_install () {

	# graph setup all arrays needed for automation
	api_plugin_register_hook('autom8', 'config_arrays', 'autom8_config_arrays', 'setup.php');
	# graph setup all forms needed for automation
	api_plugin_register_hook('autom8', 'config_form', 'autom8_config_form', 'setup.php');
	api_plugin_register_hook('autom8', 'config_settings', 'autom8_config_settings', 'setup.php');
	# graph provide navigation texts
	api_plugin_register_hook('autom8', 'draw_navigation_text', 'autom8_draw_navigation_text', 'setup.php');

	# hook for creating data queries
	api_plugin_register_hook('autom8', 'run_data_query', 'autom8_hook_data_query', 'autom8_functions.php');
	# hook for creating graph templates
	api_plugin_register_hook('autom8', 'add_graph_template_to_host', 'autom8_hook_graph_template', 'autom8_functions.php');
	# hook for creating host tree items
	api_plugin_register_hook('autom8', 'api_device_new', 'autom8_hook_device_create_tree', 'autom8_functions.php');
	# hook for creating graph tree items
	api_plugin_register_hook('autom8', 'create_complete_graph_from_template', 'autom8_hook_graph_create_tree', 'autom8_functions.php');

	# device hook: Add a new dropdown Action for Device Management
	api_plugin_register_hook('autom8', 'device_action_array', 'autom8_device_action_array', 'autom8_actions.php');
	# device hook: Device Management Action dropdown selected: prepare the list of devices for a confirmation request
	api_plugin_register_hook('autom8', 'device_action_prepare', 'autom8_device_action_prepare', 'autom8_actions.php');
	# device hook: Device Management Action dropdown selected: execute list of device
	api_plugin_register_hook('autom8', 'device_action_execute', 'autom8_device_action_execute', 'autom8_actions.php');

	# graph hook: Add a new dropdown Action for Graph Management
	api_plugin_register_hook('autom8', 'graphs_action_array', 'autom8_graph_action_array', 'autom8_actions.php');
	# graph hook: Graph Management Action dropdown selected: prepare the list of graphs for a confirmation request
	api_plugin_register_hook('autom8', 'graphs_action_prepare', 'autom8_graph_action_prepare', 'autom8_actions.php');
	# graph hook: Graph Management Action dropdown selected: execute list of graphs
	api_plugin_register_hook('autom8', 'graphs_action_execute', 'autom8_graph_action_execute', 'autom8_actions.php');

	autom8_setup_table ();
}

/**
 * plugin_autom8_uninstall	- Do any extra Uninstall stuff here
 */
function plugin_autom8_uninstall () {
	// Do any extra Uninstall stuff here
}

/**
 * plugin_autom8_check_config	- Here we will check to ensure everything is configured
 */
function plugin_autom8_check_config () {
	// Here we will check to ensure everything is configured
	autom8_check_upgrade ();
	return true;
}

/**
 * plugin_autom8_upgrade	- Here we will upgrade to the newest version
 */
function plugin_autom8_upgrade () {
	// Here we will upgrade to the newest version
	autom8_check_upgrade ();
	return true;
}

/**
 * plugin_autom8_version	- define version information
 */
function plugin_autom8_version () {
	return autom8_version();
}

/**
 * autom8_check_upgrade		- perform version upgrade
 */
function autom8_check_upgrade () {
	global $config, $database_default;
	include_once($config["library_path"] . "/database.php");
	// Let's only run this check if we are on a page that actually needs the data
	$files = array('autom8_graph_rules.php','autom8_graph_rules_items.php','autom8_tree_rules.php','autom8_tree_rules_items.php','plugins.php');
	if (isset($_SERVER['PHP_SELF']) && !in_array(basename($_SERVER['PHP_SELF']), $files))
	return;

	$version = autom8_version();
	$current = $version['version'];
	$old = db_fetch_cell("SELECT version FROM plugin_config WHERE directory='autom8'");
	if ($current != $old) {
		# stub for updating tables
		#$_columns = array_rekey(db_fetch_assoc("SHOW COLUMNS FROM <table>"), "Field", "Field");
		#if (!in_array("<new column>", $_columns)) {
		#	db_execute("ALTER TABLE <table> ADD COLUMN <new column> VARCHAR(40) NOT NULL DEFAULT '' AFTER <old column>");
		#}

		# new hooks
		api_plugin_register_hook('autom8', 'config_settings',       'autom8_config_settings', 'setup.php');
		if (api_plugin_is_enabled('autom8')) {
			# may sound ridiculous, but enables new hooks
			api_plugin_enable_hooks('autom8');
		}
		# register new version
		db_execute("UPDATE plugin_config SET " .
				"version='" . $version["version"] . "', " .
				"name='" . $version["longname"] . "', " .
				"author='" . $version["author"] . "', " .
				"webpage='" . $version["url"] . "' " .
				"WHERE directory='" . $version["name"] . "' ");
	}
}

/**
 * autom8_check_dependencies	- check plugin dependencies
 */
function autom8_check_dependencies() {
	global $plugins, $config;
	return true;
}

/**
 * autom8_version	- Version information (used by update plugin)
 */
function autom8_version () {

	return array( 'name' 	=> 'autom8',
				'version' 	=> '0.36',
				'longname'	=> 'Automate Cacti Tasks',
				'author'	=> 'Reinhard Scheck',
				'homepage'	=> 'http://docs.cacti.net/plugin:autom8',
				'email'		=> 'gandalf@cacti.net',
				'url'		=> 'http://docs.cacti.net/plugin:autom8'
	);
}

/**
 * autom8_draw_navigation_text	- Draw navigation texts
 * @param array $nav			- all current navigation texts
 * returns array				- updated navigation texts
 */
function autom8_draw_navigation_text ($nav) {
	// Displayed navigation text under the blue tabs of Cacti
	$nav["autom8_graph_rules.php:"] 			= array("title" => "Graph Rules", "mapping" => "index.php:", "url" => "autom8_graph_rules.php", "level" => "1");
	$nav["autom8_graph_rules.php:edit"] 		= array("title" => "(Edit)", "mapping" => "index.php:,autom8_graph_rules.php:", "url" => "", "level" => "2");
	$nav["autom8_graph_rules.php:actions"] 		= array("title" => "Actions", "mapping" => "index.php:,autom8_graph_rules.php:", "url" => "", "level" => "2");
	$nav["autom8_graph_rules.php:item_edit"]	= array("title" => "Graph Rule Items", "mapping" => "index.php:,autom8_graph_rules.php:,autom8_graph_rules.php:edit", "url" => "", "level" => "3");

	$nav["autom8_tree_rules.php:"] 				= array("title" => "Tree Rules", "mapping" => "index.php:", "url" => "autom8_tree_rules.php", "level" => "1");
	$nav["autom8_tree_rules.php:edit"] 			= array("title" => "(Edit)", "mapping" => "index.php:,autom8_tree_rules.php:", "url" => "", "level" => "2");
	$nav["autom8_tree_rules.php:actions"] 		= array("title" => "Actions", "mapping" => "index.php:,autom8_tree_rules.php:", "url" => "", "level" => "2");
	$nav["autom8_tree_rules.php:item_edit"]		= array("title" => "Tree Rule Items", "mapping" => "index.php:,autom8_tree_rules.php:,autom8_tree_rules.php:edit", "url" => "", "level" => "3");

	return $nav;
}

/**
 * autom8_error_handler	 	- PHP error handler
 * @param int $errno		- error id
 * @param string $errmsg	- error message
 * @param string $filename	- current filename
 * @param int $linenum		- line no of error
 * @param array $vars		- additional parameters
 */
function autom8_error_handler($errno, $errmsg, $filename, $linenum, $vars) {

	$errno = $errno & error_reporting();
	# return if error handling disabled by @
	if($errno == 0) return;
	# define constants not available with PHP 4
	if(!defined('E_STRICT'))            define('E_STRICT', 2048);
	if(!defined('E_RECOVERABLE_ERROR')) define('E_RECOVERABLE_ERROR', 4096);

	if (read_config_option("log_verbosity") >= POLLER_VERBOSITY_HIGH) {
		/* define all error types */
		$errortype = array(
		E_ERROR             => 'Error',
		E_WARNING           => 'Warning',
		E_PARSE             => 'Parsing Error',
		E_NOTICE            => 'Notice',
		E_CORE_ERROR        => 'Core Error',
		E_CORE_WARNING      => 'Core Warning',
		E_COMPILE_ERROR     => 'Compile Error',
		E_COMPILE_WARNING   => 'Compile Warning',
		E_USER_ERROR        => 'User Error',
		E_USER_WARNING      => 'User Warning',
		E_USER_NOTICE       => 'User Notice',
		E_STRICT            => 'Runtime Notice',
		E_RECOVERABLE_ERROR => 'Catchable Fatal Error'
		);

		/* create an error string for the log */
		$err = "ERRNO:'"  . $errno   . "' TYPE:'"    . $errortype[$errno] .
			"' MESSAGE:'" . $errmsg  . "' IN FILE:'" . $filename .
			"' LINE NO:'" . $linenum . "'";

		/* let's ignore some lesser issues */
		if (substr_count($errmsg, "date_default_timezone")) return;
		if (substr_count($errmsg, "Only variables")) return;

		/* log the error to the Cacti log */
		#autom8_log("PROGERR: " . $err, false, "AUTOM8");
		print("PROGERR: " . $err . "<br><pre>");# print_r($vars); print("</pre>");

		# backtrace, if available
		if (function_exists('debug_backtrace')) {
			//print "backtrace:\n";
			$backtrace = debug_backtrace();
			array_shift($backtrace);
			foreach($backtrace as $i=>$l) {
				print "[$i] in function <b>{$l['class']}{$l['type']}{$l['function']}</b>";
				if($l['file']) print " in <b>{$l['file']}</b>";
				if($l['line']) print " on line <b>{$l['line']}</b>";
				print "\n";
			}
		}
		if (isset($GLOBALS['error_fatal'])) {
			if($GLOBALS['error_fatal'] & $errno) die('fatal');
		}
	}

	return;
}

/**
 * autom8_config_arrays	- Setup arrays needed for this plugin
 */
function autom8_config_arrays () {
	# globals changed
	global $menu;
	global $autom8_op_array, $autom8_oper;
	global $autom8_tree_item_types, $autom8_tree_header_types;

	if (function_exists('api_plugin_register_realm')) {
		# register all php modules required for this plugin
		api_plugin_register_realm('autom8', 'autom8_graph_rules.php,autom8_graph_rules_items.php,autom8_tree_rules.php,autom8_tree_rules_items.php', 'Plugin Automate -> Maintain Automation Rules', 1);
	}

	# menu titles
	$menu["Templates"]['plugins/autom8/autom8_graph_rules.php'] = "Graph Rules";
	$menu["Templates"]['plugins/autom8/autom8_tree_rules.php'] = "Tree Rules";

	# operators for use with SQL/pattern matching
	$autom8_op_array = array(
		'display' => array(
	AUTOM8_OP_NONE			=> 'None',
	AUTOM8_OP_CONTAINS		=> 'contains',
	AUTOM8_OP_CONTAINS_NOT	=> 'does not contain',
	AUTOM8_OP_BEGINS		=> 'begins with',
	AUTOM8_OP_BEGINS_NOT	=> 'does not begin with',
	AUTOM8_OP_ENDS			=> 'ends with',
	AUTOM8_OP_ENDS_NOT		=> 'does not end with',
	AUTOM8_OP_MATCHES		=> 'matches',
	AUTOM8_OP_MATCHES_NOT	=> 'does not match with',
	AUTOM8_OP_LT			=> 'is less than',
	AUTOM8_OP_LE			=> 'is less than or equal',
	AUTOM8_OP_GT			=> 'is greater than',
	AUTOM8_OP_GE			=> 'is greater than or equal',
	AUTOM8_OP_UNKNOWN		=> 'is unknown',
	AUTOM8_OP_NOT_UNKNOWN	=> 'is not unknown',
	AUTOM8_OP_EMPTY			=> 'is empty',
	AUTOM8_OP_NOT_EMPTY		=> 'is not empty',
	AUTOM8_OP_REGEXP		=> 'matches regular expression',
	AUTOM8_OP_NOT_REGEXP	=> 'does not match regular expression',
	),
		'op' => array(
	AUTOM8_OP_NONE			=> '',
	AUTOM8_OP_CONTAINS		=> 'LIKE',
	AUTOM8_OP_CONTAINS_NOT	=> 'NOT LIKE',
	AUTOM8_OP_BEGINS		=> 'LIKE',
	AUTOM8_OP_BEGINS_NOT	=> 'NOT LIKE',
	AUTOM8_OP_ENDS			=> 'LIKE',
	AUTOM8_OP_ENDS_NOT		=> 'NOT LIKE',
	AUTOM8_OP_MATCHES		=> '<=>',
	AUTOM8_OP_MATCHES_NOT	=> '<>',
	AUTOM8_OP_LT			=> '<',
	AUTOM8_OP_LE			=> '<=',
	AUTOM8_OP_GT			=> '>',
	AUTOM8_OP_GE			=> '>=',
	AUTOM8_OP_UNKNOWN		=> 'IS NULL',
	AUTOM8_OP_NOT_UNKNOWN	=> 'IS NOT NULL',
	AUTOM8_OP_EMPTY			=> "LIKE ''",
	AUTOM8_OP_NOT_EMPTY		=> "NOT LIKE ''",
	AUTOM8_OP_REGEXP		=> 'REGEXP',
	AUTOM8_OP_NOT_REGEXP	=> 'NOT REGEXP',
	),
		'binary' => array(
	AUTOM8_OP_NONE			=> false,
	AUTOM8_OP_CONTAINS		=> true,
	AUTOM8_OP_CONTAINS_NOT	=> true,
	AUTOM8_OP_BEGINS		=> true,
	AUTOM8_OP_BEGINS_NOT	=> true,
	AUTOM8_OP_ENDS			=> true,
	AUTOM8_OP_ENDS_NOT		=> true,
	AUTOM8_OP_MATCHES		=> true,
	AUTOM8_OP_MATCHES_NOT	=> true,
	AUTOM8_OP_LT			=> true,
	AUTOM8_OP_LE			=> true,
	AUTOM8_OP_GT			=> true,
	AUTOM8_OP_GE			=> true,
	AUTOM8_OP_UNKNOWN		=> false,
	AUTOM8_OP_NOT_UNKNOWN	=> false,
	AUTOM8_OP_EMPTY			=> false,
	AUTOM8_OP_NOT_EMPTY		=> false,
	AUTOM8_OP_REGEXP		=> true,
	AUTOM8_OP_NOT_REGEXP	=> true,
	),
		'pre' => array(
	AUTOM8_OP_NONE			=> '',
	AUTOM8_OP_CONTAINS		=> '%',
	AUTOM8_OP_CONTAINS_NOT	=> '%',
	AUTOM8_OP_BEGINS		=> '',
	AUTOM8_OP_BEGINS_NOT	=> '',
	AUTOM8_OP_ENDS			=> '%',
	AUTOM8_OP_ENDS_NOT		=> '%',
	AUTOM8_OP_MATCHES		=> '',
	AUTOM8_OP_MATCHES_NOT	=> '',
	AUTOM8_OP_LT			=> '',
	AUTOM8_OP_LE			=> '',
	AUTOM8_OP_GT			=> '',
	AUTOM8_OP_GE			=> '',
	AUTOM8_OP_UNKNOWN		=> '',
	AUTOM8_OP_NOT_UNKNOWN	=> '',
	AUTOM8_OP_EMPTY			=> '',
	AUTOM8_OP_NOT_EMPTY		=> '',
	AUTOM8_OP_REGEXP		=> '',
	AUTOM8_OP_NOT_REGEXP	=> '',
	),
		'post' => array(
	AUTOM8_OP_NONE			=> '',
	AUTOM8_OP_CONTAINS		=> '%',
	AUTOM8_OP_CONTAINS_NOT	=> '%',
	AUTOM8_OP_BEGINS		=> '%',
	AUTOM8_OP_BEGINS_NOT	=> '%',
	AUTOM8_OP_ENDS			=> '',
	AUTOM8_OP_ENDS_NOT		=> '',
	AUTOM8_OP_MATCHES		=> '',
	AUTOM8_OP_MATCHES_NOT	=> '',
	AUTOM8_OP_LT			=> '',
	AUTOM8_OP_LE			=> '',
	AUTOM8_OP_GT			=> '',
	AUTOM8_OP_GE			=> '',
	AUTOM8_OP_UNKNOWN		=> '',
	AUTOM8_OP_NOT_UNKNOWN	=> '',
	AUTOM8_OP_EMPTY			=> '',
	AUTOM8_OP_NOT_EMPTY		=> '',
	AUTOM8_OP_REGEXP		=> '',
	AUTOM8_OP_NOT_REGEXP	=> '',
	),
	);

	$autom8_oper = array(
	AUTOM8_OPER_NULL			=> '',
	AUTOM8_OPER_AND				=> 'AND',
	AUTOM8_OPER_OR				=> 'OR',
	AUTOM8_OPER_LEFT_BRACKET 	=> '(',
	AUTOM8_OPER_RIGHT_BRACKET 	=> ')',
	);

	$autom8_tree_item_types  = array(
	TREE_ITEM_TYPE_GRAPH => "Graph",
	TREE_ITEM_TYPE_HOST => "Host"
	);

	$autom8_tree_header_types  = array(
	AUTOM8_TREE_ITEM_TYPE_STRING => "Fixed String",
	);

}

/**
 * autom8_config_form	- Setup forms needed for this plugin
 */
function autom8_config_form () {
	# globals referred by forms
	global $tree_sort_types, $host_group_types;
	# globals defined for use with Rules
	global $fields_autom8_match_rule_item_edit;
	global $fields_autom8_graph_rule_item_edit, $fields_autom8_graph_rules_edit1, $fields_autom8_graph_rules_edit2, $fields_autom8_graph_rules_edit3;
	global $fields_autom8_tree_rule_item_edit, $fields_autom8_tree_rules_edit1, $fields_autom8_tree_rules_edit2, $fields_autom8_tree_rules_edit3;
	global $autom8_op_array, $autom8_oper;
	global $autom8_tree_item_types, $autom8_tree_header_types;

	# ------------------------------------------------------------
	# Autom8 Rules
	# ------------------------------------------------------------
	/* file: autom8_graph_rules.php, autom8_tree_rules.php, action: edit */
	$fields_autom8_match_rule_item_edit = array(
	"operation" => array(
		"method" => "drop_array",
		"friendly_name" => "Operation",
		"description" => "Logical operation to combine rules.",
		"array" => $autom8_oper,
		"value" => "|arg1:operation|",
		"on_change" => "toggle_operation()",
		),
	"field" => array(
		"method" => "drop_array",
		"friendly_name" => "Field Name",
		"description" => "The Field Name that shall be used for this Rule Item.",
		"array" => array(),			# to be filled dynamically
		"value" => "|arg1:field|",
		"none_value" => "None",
		),
	"operator" => array(
		"method" => "drop_array",
		"friendly_name" => "Operator",
		"description" => "Operator.",
		"array" => $autom8_op_array["display"],
		"value" => "|arg1:operator|",
		"on_change" => "toggle_operator()",
		),
	"pattern" => array(
		"method" => "textbox",
		"friendly_name" => "Matching Pattern",
		"description" => "The Pattern to be matched against.",
		"value" => "|arg1:pattern|",
		"size" => "50",
		"max_length" => "255",
		),
	"sequence" => array(
		"method" => "view",
		"friendly_name" => "Sequence",
		"description" => "Sequence.",
		"value" => "|arg1:sequence|",
		),
	);

	/* file: autom8_graph_rules.php, action: edit */
	$fields_autom8_graph_rule_item_edit = array(
	"operation" => array(
		"method" => "drop_array",
		"friendly_name" => "Operation",
		"description" => "Logical operation to combine rules.",
		"array" => $autom8_oper,
		"value" => "|arg1:operation|",
		"on_change" => "toggle_operation()",
		),
	"field" => array(
		"method" => "drop_array",
		"friendly_name" => "Field Name",
		"description" => "The Field Name that shall be used for this Rule Item.",
		"array" => array(),			# later to be filled dynamically
		"value" => "|arg1:field|",
		"none_value" => "None",
		),
	"operator" => array(
		"method" => "drop_array",
		"friendly_name" => "Operator",
		"description" => "Operator.",
		"array" => $autom8_op_array["display"],
		"value" => "|arg1:operator|",
		"on_change" => "toggle_operator()",
		),
	"pattern" => array(
		"method" => "textbox",
		"friendly_name" => "Matching Pattern",
		"description" => "The Pattern to be matched against.",
		"value" => "|arg1:pattern|",
		"size" => "50",
		"max_length" => "255",
		),
	"sequence" => array(
		"method" => "view",
		"friendly_name" => "Sequence",
		"description" => "Sequence.",
		"value" => "|arg1:sequence|",
		),
	);

	$fields_autom8_graph_rules_edit1 = array(
	"name" => array(
		"method" => "textbox",
		"friendly_name" => "Name",
		"description" => "A useful name for this Rule.",
		"value" => "|arg1:name|",
		"max_length" => "255",
		"size" => "60"
	),
	"snmp_query_id" => array(
		"method" => "drop_sql",
		"friendly_name" => "REQUIRED: Data Query",
		"description" => "Choose a Data Query to apply to this rule.",
		"value" => "|arg1:snmp_query_id|",
		"on_change" => "applySNMPQueryIdChange(document.form_autom8_rule_edit)",
		"sql" => "SELECT id, name FROM snmp_query ORDER BY name"
	),
	);

	$fields_autom8_graph_rules_edit2 = array(
	"graph_type_id" => array(
		"method" => "drop_sql",
		"friendly_name" => "REQUIRED: Graph Type",
		"description" => "Choose any of the available Graph Types to apply to this rule.",
		"value" => "|arg1:graph_type_id|",
		"on_change" => "applySNMPQueryTypeChange(document.form_autom8_rule_edit)",
		"sql" => "SELECT " .
					"snmp_query_graph.id, " .
					"snmp_query_graph.name " .
					"FROM snmp_query_graph " .
					"LEFT JOIN graph_templates " .
					"ON (snmp_query_graph.graph_template_id=graph_templates.id) " .
					"WHERE snmp_query_graph.snmp_query_id=|arg1:snmp_query_id| " .
					"ORDER BY snmp_query_graph.name"
		),
		);

	$fields_autom8_graph_rules_edit3 = array(
	"enabled" => array(
		"method" => "checkbox",
		"friendly_name" => "Enable Rule",
		"description" => "Check this box to enable this rule.",
		"value" => "|arg1:enabled|",
		"default" => "",
		"form_id" => false
	),
	);

	/* file: autom8_tree_rules.php, action: edit */
	$fields_autom8_tree_rules_edit1 = array(
	"name" => array(
		"method" => "textbox",
		"friendly_name" => "Name",
		"description" => "A useful name for this Rule.",
		"value" => "|arg1:name|",
		"max_length" => "255",
		"size" => "60"
	),
	"tree_id" => array(
		"method" => "drop_sql",
		"friendly_name" => "REQUIRED: Tree",
		"description" => "Choose a Tree for the new Tree Items.",
		"value" => "|arg1:tree_id|",
		"on_change" => "applyTreeChange(document.form_autom8_tree_rule_edit)",
		"sql" => "SELECT id, name FROM graph_tree ORDER BY name"
	),
	"leaf_type" => array(
		"method" => "drop_array",
		"friendly_name" => "REQUIRED: Leaf Item Type",
		"description" => "The Item Type that shall be dynamically added to the tree.",
		"value" => "|arg1:leaf_type|",
		"on_change" => "applyItemTypeChange(document.form_autom8_tree_rule_edit)",
		"array" => $autom8_tree_item_types
	),
	"host_grouping_type" => array(
		"method" => "drop_array",
		"friendly_name" => "Graph Grouping Style",
		"description" => "Choose how graphs are grouped when drawn for this particular host on the tree.",
		"array" => $host_group_types,
		"value" => "|arg1:host_grouping_type|",
		"default" => HOST_GROUPING_GRAPH_TEMPLATE,
		),
	"rra_id" => array(
		"method" => "drop_sql",
		"friendly_name" => "Round Robin Archive",
		"description" => "Choose a round robin archive to control how this graph is displayed.",
		"value" => "|arg1:rra_id|",
		"sql" => "SELECT id,name FROM rra ORDER BY timespan",
		),
	);

	$fields_autom8_tree_rules_edit2 = array(
	"tree_item_id" => array(
		"method" => "drop_tree",
		"friendly_name" => "Optional: Sub-Tree Item",
		"description" => "Choose a Sub-Tree Item to hook in." . "<br>" .
						"Make sure, that it is still there when this rule is executed!",
		"tree_id" => "|arg1:tree_id|",
		"value" => "|arg1:tree_item_id|",
	),
	);

	$fields_autom8_tree_rules_edit3 = array(
	"enabled" => array(
		"method" => "checkbox",
		"friendly_name" => "Enable Rule",
		"description" => "Check this box to enable this rule.",
		"value" => "|arg1:enabled|",
		"default" => "",
		"form_id" => false
	),
	);

	$fields_autom8_tree_rule_item_edit = array(
	"field" => array(
		"method" => "drop_array",
		"friendly_name" => "Header Type",
		"description" => "Choose an Object to build a new Subheader.",
		"array" => array(),			# later to be filled dynamically
		"value" => "|arg1:field|",
		"none_value" => $autom8_tree_header_types[AUTOM8_TREE_ITEM_TYPE_STRING],
		"on_change" => "applyHeaderChange()",
	),
	"sort_type" => array(
		"method" => "drop_array",
		"friendly_name" => "Sorting Type",
		"description" => "Choose how items in this tree will be sorted.",
		"value" => "|arg1:sort_type|",
		"default" => TREE_ORDERING_NONE,
		"array" => $tree_sort_types,
		),
	"propagate_changes" => array(
		"method" => "checkbox",
		"friendly_name" => "Propagate Changes",
		"description" => "Propagate all options on this form (except for 'Title') to all child 'Header' items.",
		"value" => "|arg1:propagate_changes|",
		"default" => "",
		"form_id" => false
		),
	"search_pattern" => array(
		"method" => "textbox",
		"friendly_name" => "Matching Pattern",
		"description" => "The String Pattern (Regular Expression) to match against." . "<br>" .
							"Enclosing '/' must <strong>NOT</strong> be provided!",
		"value" => "|arg1:search_pattern|",
		"size" => "50",
		"max_length" => "255",
	),
	"replace_pattern" => array(
		"method" => "textbox",
		"friendly_name" => "Replacement Pattern",
		"description" => "The Replacement String Pattern for use as a Tree Header." . "<br>" .
							"Refer to a Match by e.g. <strong>\${1}</strong> for the first match!",
		"value" => "|arg1:replace_pattern|",
		"size" => "50",
		"max_length" => "255",
	),
	"sequence" => array(
		"method" => "view",
		"friendly_name" => "Sequence",
		"description" => "Sequence.",
		"value" => "|arg1:sequence|",
	),
	);

}

/**
 * autom8_config_settings	- configuration settings for this plugin
 */
function autom8_config_settings () {
	global $tabs, $settings, $config, $logfile_verbosity;

	/* check for an upgrade */
	plugin_autom8_check_config();

	if (isset($_SERVER['PHP_SELF']) && basename($_SERVER['PHP_SELF']) != 'settings.php')
		return;

	$temp = array(
		"autom8_header" => array(
			"friendly_name" => "Autom8",
			"method" => "spacer",
		),
		"autom8_log_verbosity" => array(
			"friendly_name" => "Poller Logging Level for AUTOM8",
			"description" => "What level of detail do you want sent to the log file. WARNING: Leaving in any other status than NONE or LOW can exaust your disk space rapidly.",
			"method" => "drop_array",
			"default" => POLLER_VERBOSITY_LOW,
			"array" => $logfile_verbosity,
			),
		"autom8_graphs_enabled" => array(
			"method" => "checkbox",
			"friendly_name" => "Enable Autom8 Graph Creation",
			"description" => "When disabled, Autom8 will not actively create any Graph." . "<br>" .
				"This will be useful when fiddeling around with Hosts to avoid creating new Graphs each time you save an object" . "<br>" .
				"Invoking Rules manually will still be possible.",
			"default" => "",
		),
		"autom8_tree_enabled" => array(
			"method" => "checkbox",
			"friendly_name" => "Enable Autom8 Tree Item Creation",
			"description" => "When disabled, Autom8 will not actively create any Tree Item." . "<br>" .
				"This will be useful when fiddeling around with Hosts and Graphs to avoid creating new Tree Entries each time you save an object" . "<br>" .
				"Invoking Rules manually will still be possible.",
			"default" => "",
		),
	);

	/* create a new Settings Tab, if not already in place */
	if (!isset($tabs["misc"])) {
		$tabs["misc"] = "Misc";
	}

	/* and merge own settings into it */
	if (isset($settings["misc"]))
		$settings["misc"] = array_merge($settings["misc"], $temp);
	else
		$settings["misc"] = $temp;
}


/*
 * autom8_setup_table - Setup database tables needed for this plugin
 */
function autom8_setup_table () {
	global $config, $database_default;
	include_once($config["library_path"] . "/database.php");

	/* are my tables already present? */
	$sql	= "show tables from `" . $database_default . "`";
	$result = db_fetch_assoc($sql) or die (mysql_error());
	$tables = array();
	$sql 	= array();

	foreach($result as $index => $arr) {
		foreach($arr as $tbl) {
			$tables[] = $tbl;
		}
	}

	if (!in_array('plugin_autom8_match_rule_items', $tables)) {
		$data = array();
		$data['columns'][] = array('name' => 'id', 				'type' => 'mediumint(8)', 'unsigned' => 'unsigned', 'NULL' => false, 'auto_increment' => true);
		$data['columns'][] = array('name' => 'rule_id',		 	'type' => 'mediumint(8)', 'unsigned' => 'unsigned', 'NULL' => false, 'default' => 0);
		$data['columns'][] = array('name' => 'rule_type',		'type' => 'smallint(3)',  'unsigned' => 'unsigned', 'NULL' => false, 'default' => 0);
		$data['columns'][] = array('name' => 'sequence',		'type' => 'smallint(3)',  'unsigned' => 'unsigned', 'NULL' => false, 'default' => 0);
		$data['columns'][] = array('name' => 'operation', 		'type' => 'smallint(3)',  'unsigned' => 'unsigned', 'NULL' => false, 'default' => 0);
		$data['columns'][] = array('name' => 'field',	 		'type' => 'varchar(255)',  	                        'NULL' => false, 'default' => '');
		$data['columns'][] = array('name' => 'operator', 		'type' => 'smallint(3)',  'unsigned' => 'unsigned', 'NULL' => false, 'default' => 0);
		$data['columns'][] = array('name' => 'pattern', 		'type' => 'varchar(255)',                           'NULL' => false, 'default' => '');
		$data['primary'] = 'id';
		$data['keys'][] = '';
		$data['type'] = 'MyISAM';
		$data['comment'] = 'Autom8 Match Rule Items';
		api_plugin_db_table_create ('autom8', 'plugin_autom8_match_rule_items', $data);
	}

	$sql[] = "INSERT INTO `plugin_autom8_match_rule_items` " .
			"(`id`, `rule_id`, `rule_type`, `sequence`, `operation`, `field`, `operator`, `pattern`) " .
			"VALUES " .
			"(1, 1, 1, 1, 0, 'host.description', 14, ''), " .
			"(2, 1, 1, 2, 1, 'host.snmp_version', 12, '2'), " .
			"(3, 1, 3, 1, 0, 'host_template.name', 1, 'Linux'), " .
			"(4, 2, 1, 1, 0, 'host_template.name', 1, 'Linux'), " .
			"(5, 2, 1, 2, 1, 'host.snmp_version', 12, '2'), " .
			"(6, 2, 3, 1, 0, 'host_template.name', 1, 'SNMP'), " .
			"(7, 2, 3, 2, 1, 'graph_templates.name', 1, 'Traffic'); ";

	if (!in_array('plugin_autom8_graph_rules', $tables)) {
		$data = array();
		$data['columns'][] = array('name' => 'id', 				'type' => 'mediumint(8)', 'unsigned' => 'unsigned', 'NULL' => false, 'auto_increment' => true);
		$data['columns'][] = array('name' => 'name',	 		'type' => 'varchar(255)',                           'NULL' => false, 'default' => '');
		$data['columns'][] = array('name' => 'snmp_query_id', 	'type' => 'smallint(3)', 'unsigned' => 'unsigned', 'NULL' => false, 'default' => 0);
		$data['columns'][] = array('name' => 'graph_type_id',	'type' => 'smallint(3)', 'unsigned' => 'unsigned', 'NULL' => false, 'default' => 0);
		$data['columns'][] = array('name' => 'enabled', 		'type' => 'char(2)',								'NULL' => true,  'default' => '');
		$data['primary'] = 'id';
		$data['keys'][] = '';
		$data['type'] = 'MyISAM';
		$data['comment'] = 'Autom8 Graph Rules';
		api_plugin_db_table_create ('autom8', 'plugin_autom8_graph_rules', $data);
	}

	$sql[] = "INSERT INTO `plugin_autom8_graph_rules` " .
			"(`id`, `name`, `snmp_query_id`, `graph_type_id`, `enabled`) " .
			"VALUES " .
			"(1, 'Traffic 64 bit Server', 1, 14, ''), " .
			"(2, 'Traffic 64 bit Server Linux', 1, 14, ''), " .
			"(3, 'Disk Space', 8, 18, ''); ";

	if (!in_array('plugin_autom8_graph_rule_items', $tables)) {
		$data = array();
		$data['columns'][] = array('name' => 'id', 				'type' => 'mediumint(8)', 'unsigned' => 'unsigned', 'NULL' => false, 'auto_increment' => true);
		$data['columns'][] = array('name' => 'rule_id',		 	'type' => 'mediumint(8)', 'unsigned' => 'unsigned', 'NULL' => false, 'default' => 0);
		$data['columns'][] = array('name' => 'sequence',		'type' => 'smallint(3)', 'unsigned' => 'unsigned', 'NULL' => false, 'default' => 0);
		$data['columns'][] = array('name' => 'operation', 		'type' => 'smallint(3)', 'unsigned' => 'unsigned', 'NULL' => false, 'default' => 0);
		$data['columns'][] = array('name' => 'field',	 		'type' => 'varchar(255)',                           'NULL' => false, 'default' => '');
		$data['columns'][] = array('name' => 'operator', 		'type' => 'smallint(3)', 'unsigned' => 'unsigned', 'NULL' => false, 'default' => 0);
		$data['columns'][] = array('name' => 'pattern', 		'type' => 'varchar(255)',                           'NULL' => false, 'default' => '');
		$data['primary'] = 'id';
		$data['keys'][] = '';
		$data['type'] = 'MyISAM';
		$data['comment'] = 'Autom8 Graph Rule Items';
		api_plugin_db_table_create ('autom8', 'plugin_autom8_graph_rule_items', $data);
	}

	$sql[] = "INSERT INTO `plugin_autom8_graph_rule_items` " .
			"(`id`, `rule_id`, `sequence`, `operation`, `field`, `operator`, `pattern`) " .
			"VALUES " .
			"(1, 1, 1, 0, 'ifOperStatus', 7, 'Up'), " .
			"(2, 1, 2, 1, 'ifIP', 16, ''), " .
			"(3, 1, 3, 1, 'ifHwAddr', 16, ''), " .
			"(4, 2, 1, 0, 'ifOperStatus', 7, 'Up'), " .
			"(5, 2, 2, 1, 'ifIP', 16, ''), " .
			"(6, 2, 3, 1, 'ifHwAddr', 16, '')";


	if (!in_array('plugin_autom8_tree_rules', $tables)) {
		$data = array();
		$data['columns'][] = array('name' => 'id', 				'type' => 'mediumint(8)', 'unsigned' => 'unsigned', 'NULL' => false, 'auto_increment' => true);
		$data['columns'][] = array('name' => 'name',	 		'type' => 'varchar(255)',                           'NULL' => false, 'default' => '');
		$data['columns'][] = array('name' => 'tree_id',		 	'type' => 'smallint(3)', 'unsigned' => 'unsigned', 'NULL' => false, 'default' => 0);
		$data['columns'][] = array('name' => 'tree_item_id',	'type' => 'mediumint(8)', 'unsigned' => 'unsigned', 'NULL' => false, 'default' => 0);
		$data['columns'][] = array('name' => 'leaf_type',		'type' => 'smallint(3)', 'unsigned' => 'unsigned', 'NULL' => false, 'default' => 0);
		$data['columns'][] = array('name' => 'host_grouping_type',	'type' => 'smallint(3)', 'unsigned' => 'unsigned', 'NULL' => false, 'default' => 0);
		$data['columns'][] = array('name' => 'rra_id',			'type' => 'mediumint(8)', 'unsigned' => 'unsigned', 'NULL' => false, 'default' => 0);
		$data['columns'][] = array('name' => 'enabled', 		'type' => 'char(2)',								'NULL' => true,  'default' => '');
		$data['primary'] = 'id';
		$data['keys'][] = '';
		$data['type'] = 'MyISAM';
		$data['comment'] = 'Autom8 Tree Rules';
		api_plugin_db_table_create ('autom8', 'plugin_autom8_tree_rules', $data);
	}

	$sql[] = "INSERT INTO `plugin_autom8_tree_rules` " .
			"(`id`, `name`, `tree_id`, `tree_item_id`, `leaf_type`, `host_grouping_type`, `rra_id`, `enabled`) " .
			"VALUES " .
			"(1, 'New Device', 1, 0, 3, 1, 0, ''), " .
			"(2, 'New Graph',  1, 0, 2, 0, 1, '');";

	if (!in_array('plugin_autom8_tree_rule_items', $tables)) {
		$data = array();
		$data['columns'][] = array('name' => 'id', 				'type' => 'mediumint(8)', 	'unsigned' => 'unsigned', 	'NULL' => false, 	'auto_increment' => true);
		$data['columns'][] = array('name' => 'rule_id',		 	'type' => 'mediumint(8)', 	'unsigned' => 'unsigned', 	'NULL' => false, 	'default' => 0);
		$data['columns'][] = array('name' => 'sequence',		'type' => 'smallint(3)', 	'unsigned' => 'unsigned', 	'NULL' => false, 	'default' => 0);
		$data['columns'][] = array('name' => 'field',	 		'type' => 'varchar(255)',                           'NULL' => false, 'default' => '');
		$data['columns'][] = array('name' => 'rra_id',		 	'type' => 'mediumint(8)', 	'unsigned' => 'unsigned', 	'NULL' => false, 	'default' => 0);
		$data['columns'][] = array('name' => 'sort_type', 		'type' => 'smallint(3)', 	'unsigned' => 'unsigned', 	'NULL' => false, 	'default' => 0);
		$data['columns'][] = array('name' => 'propagate_changes',	'type' => 'char(2)', 								'NULL' => true, 	'default' => '');
		$data['columns'][] = array('name' => 'search_pattern',	'type' => 'varchar(255)',                      	    	'NULL' => false, 	'default' => '');
		$data['columns'][] = array('name' => 'replace_pattern',	'type' => 'varchar(255)',                       	    'NULL' => false, 	'default' => '');
		$data['primary'] = 'id';
		$data['keys'][] = '';
		$data['type'] = 'MyISAM';
		$data['comment'] = 'Autom8 Tree Rule Items';
		api_plugin_db_table_create ('autom8', 'plugin_autom8_tree_rule_items', $data);
	}

	$sql[] = "INSERT INTO `plugin_autom8_tree_rule_items` " .
			"(`id`, `rule_id`, `sequence`, `field`, `rra_id`, `sort_type`, `propagate_changes`, `search_pattern`, `replace_pattern`) " .
			"VALUES " .
			"(1, 1, 1, 'host_template.name', 0, 1, '', '^(.*)\\\\s*Linux\\\\s*(.*)$', '$\{1\}\\\\n$\{2\}'), " .
			"(2, 1, 2, 'host.hostname', 0, 1, '', '^(\\\\w*)\\\\s*(\\\\w*)\\\\s*(\\\\w*).*$', '$\{1\}\\\\n$\{2\}\\\\n$\{3\}'), " .
			"(3, 2, 1, '0', 0, 2, 'on', 'Traffic', ''), " .
			"(4, 2, 2, 'graph_templates_graph.title_cache', 0, 1, '', '^(.*)\\\\s*-\\\\s*Traffic -\\\\s*(.*)$', '$\{1\}\\\\n$\{2\}');";

	# now run all SQL commands
	if (!empty($sql)) {
		for ($a = 0; $a < count($sql); $a++) {
			$result = db_execute($sql[$a]);
		}
	}

}

?>
