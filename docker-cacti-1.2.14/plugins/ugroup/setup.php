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

function plugin_ugroup_install () {
	api_plugin_register_hook('ugroup', 'config_arrays',              'ugroup_config_arrays',        'setup.php');
	api_plugin_register_hook('ugroup', 'config_settings',            'ugroup_config_settings',      'setup.php');
	api_plugin_register_hook('ugroup', 'draw_navigation_text',       'ugroup_draw_navigation_text', 'setup.php');
	api_plugin_register_hook('ugroup', 'login_process',              'ugroup_login_process',        'setup.php');
	api_plugin_register_hook('ugroup', 'login_realms_exist',         'ugroup_login_realms_exist',   'setup.php');
	api_plugin_register_hook('ugroup', 'login_realms',               'ugroup_login_realms',         'setup.php');
	api_plugin_register_hook('ugroup', 'auth_realm_authorized',      'ugroup_realm_authorized',     'setup.php');
	api_plugin_register_hook('ugroup', 'auth_console_authorized',    'ugroup_console_authorized',   'setup.php');
	api_plugin_register_hook('ugroup', 'auth_user_realms',           'ugroup_user_realms',          'setup.php');
	api_plugin_register_hook('ugroup', 'auth_host_array',            'ugroup_host_array',           'setup.php');
	api_plugin_register_hook('ugroup', 'auth_templates_array',       'ugroup_templates_array',      'setup.php');
	api_plugin_register_hook('ugroup', 'auth_graphs_array',          'ugroup_graphs_array',         'setup.php');
	api_plugin_register_hook('ugroup', 'auth_tree_hierarchy_array',  'ugroup_tree_hier_array',      'setup.php');
	api_plugin_register_hook('ugroup', 'auth_login_options',         'ugroup_login_options',        'setup.php');
	api_plugin_register_hook('ugroup', 'auth_graph_tree_allowed',    'ugroup_tree_allowed',         'setup.php');
	api_plugin_register_hook('ugroup', 'auth_graph_list_allowed',    'ugroup_list_allowed',         'setup.php');
	api_plugin_register_hook('ugroup', 'auth_graph_preview_allowed', 'ugroup_preview_allowed',      'setup.php');

	api_plugin_register_realm('ugroup', 'ugroup.php', 'Plugin -> Manage User Groups', 1);

	ugroup_setup_table_new();
}

function ugroup_tree_allowed($default) {
	$value = db_fetch_cell("SELECT MAX(show_tree)
		FROM user_auth_group 
		INNER JOIN user_auth_group_members
		ON user_auth_group.id=user_auth_group_members.user_id
		WHERE user_auth_group.enabled='on' AND show_tree=2 AND user_id=" . $_SESSION["sess_user_id"]);

	if ($value == '2') {
		return true;
	}else{
		return $default;
	}
}

function ugroup_list_allowed($default) {
	$value = db_fetch_cell("SELECT MAX(show_list)
		FROM user_auth_group 
		INNER JOIN user_auth_group_members
		ON user_auth_group.id=user_auth_group_members.user_id
		WHERE user_auth_group.enabled='on' AND show_list=2 AND user_id=" . $_SESSION["sess_user_id"]);

	if ($value == '2') {
		return true;
	}else{
		return $default;
	}
}

function ugroup_preview_denied($default) {
	$value = db_fetch_cell("SELECT MAX(show_preview) 
		FROM user_auth_group 
		INNER JOIN user_auth_group_members
		ON user_auth_group.id=user_auth_group_members.user_id
		WHERE user_auth_group.enabled='on' AND show_preview=2 AND user_id=" . $_SESSION["sess_user_id"]);

	if ($value == '2') {
		return true;
	}else{
		return $default;
	}
}

function ugroup_login_options($default) {
	$value = db_fetch_cell("SELECT login_opts 
		FROM user_auth_group 
		INNER JOIN user_auth_group_members
		ON user_auth_group.id=user_auth_group_members.group_id
		WHERE user_id=" . $_SESSION["sess_user_id"] . "
		LIMIT 1");

	if (empty($value)) {
		return $default;
	}else{
		switch ($value) {
		case '1': /* referer */
			header("Location: " . sanitize_uri($_POST["ref"])); break;
			break;
		case '2': /* default console page */
			header("Location: index.php"); break;
			break;
		case '3': /* default graph page */
			header("Location: graph_view.php"); break;
			break;
		default:
			api_plugin_hook_function('login_options_navigate', $user['login_opts']);
			break;
		}
	}

	return $default;
}

function ugroup_tree_hier_array($options_array = false) {
	/* graph permissions */
	if (read_config_option("auth_method") == 0) {
		return false;
	}

	/* setup the authentication join */
	$sql_join = "LEFT JOIN (
			SELECT type, user_auth.id AS user_id, item_id,
			policy_graphs, policy_hosts, policy_graph_templates
			FROM user_auth
			INNER JOIN user_auth_perms
			ON user_auth.id=user_auth_perms.user_id
			WHERE id=" . $_SESSION["sess_user_id"] . "
			UNION
			SELECT uagp.type, uagm.user_id, uagp.item_id,
			policy_graphs, policy_hosts, policy_graph_templates
			FROM user_auth_group_perms AS uagp
			INNER JOIN user_auth_group_members AS uagm
			ON uagp.group_id=uagm.group_id
			INNER JOIN user_auth_group AS uag
			ON uag.id=uagp.group_id
			WHERE uag.enabled='on' AND uagm.user_id=" . $_SESSION["sess_user_id"] . ") AS user_auth_perms
		ON ((graph_local.id=user_auth_perms.item_id AND user_auth_perms.type=1)
		OR (host.id=user_auth_perms.item_id AND user_auth_perms.type=3)
		OR (graph_templates.id=user_auth_perms.item_id AND user_auth_perms.type=4))";

	$hier_sql = "SELECT
		graph_tree_items.id,
		graph_tree_items.title,
		graph_tree_items.local_graph_id,
		graph_tree_items.rra_id,
		graph_tree_items.host_id,
		graph_tree_items.order_key,
		graph_templates_graph.height,
		graph_templates_graph.width,
		graph_templates_graph.title_cache AS graph_title,
		CONCAT_WS('',host.description,' (',host.hostname,')') AS hostname,
		graph_local.snmp_index,
		settings_tree.status
		FROM graph_tree_items
		LEFT JOIN graph_templates_graph 
		ON (graph_tree_items.local_graph_id=graph_templates_graph.local_graph_id AND graph_tree_items.local_graph_id>0)
		LEFT JOIN settings_tree 
		ON (graph_tree_items.id=settings_tree.graph_tree_item_id AND settings_tree.user_id=" . $options_array['user_id'] . ")
		LEFT JOIN host 
		ON (graph_tree_items.host_id=host.id)
		JOIN graph_local 
		ON (graph_templates_graph.local_graph_id=graph_local.id)
        LEFT JOIN graph_templates 
		ON (graph_templates.id=graph_local.graph_template_id)
		$sql_join
		WHERE graph_tree_items.graph_tree_id=" . $options_array['tree_id'] . "
		AND graph_tree_items.order_key LIKE '" . $options_array['search_key'] . "%'
		$sql_where
		ORDER BY graph_tree_items.order_key";

	$hierarchy = db_fetch_assoc($hier_sql);

	return array('hierarchy' => $hierarchy);
}

function ugroup_graphs_array($options_array = false) {
	$current_user = db_fetch_row("SELECT * FROM user_auth WHERE id=" . $_SESSION["sess_user_id"]);

	$sql_or = ""; $sql_where = ""; $sql_ujoin = ""; $sql_gjoin = "";

	$sql_ujoin = "INNER JOIN host AS h
		ON (h.id=gl.host_id)
		INNER JOIN graph_templates AS gt
		ON (gt.id=gl.graph_template_id)
		LEFT JOIN (
			SELECT '0' AS grp_id, type, user_auth.id AS user_id, item_id,
			policy_graphs, policy_hosts, policy_graph_templates
			FROM user_auth
			LEFT JOIN user_auth_perms 
			ON user_auth.id=user_auth_perms.user_id
			WHERE id IN (" . $_SESSION["sess_user_id"] . ", NULL)) AS uap
		ON ((gl.id=uap.item_id AND uap.type=1)
		OR (h.id=uap.item_id AND uap.type=3) 
		OR (gt.id=uap.item_id AND uap.type=4)
		OR (item_id!=gl.id AND policy_graphs=2)
		OR (item_id!=h.id AND policy_hosts=2)
		OR (item_id!=gt.id AND policy_graph_templates=2)
		OR (user_id=" . $_SESSION["sess_user_id"] . "))";

	$sql_gjoin = "INNER JOIN host AS h
		ON (h.id=gl.host_id)
		INNER JOIN graph_templates AS gt
		ON (gt.id=gl.graph_template_id)
		LEFT JOIN (
			SELECT uag.id AS grp_id, uagp.type, uagm.user_id, uagp.item_id,
			policy_graphs, policy_hosts, policy_graph_templates
			FROM user_auth_group AS uag
			INNER JOIN user_auth_group_members AS uagm
			ON uag.id=uagm.group_id
			LEFT JOIN user_auth_group_perms AS uagp
			ON uagm.group_id=uagp.group_id
			WHERE uag.enabled='on' AND uagm.user_id IN (" . $_SESSION["sess_user_id"] . ", NULL)) AS uap
		ON ((gl.id=uap.item_id AND uap.type=1)
		OR (h.id=uap.item_id AND uap.type=3) 
		OR (gt.id=uap.item_id AND uap.type=4)
		OR (item_id!=gl.id AND policy_graphs=2)
		OR (item_id!=h.id AND policy_hosts=2)
		OR (item_id!=gt.id AND policy_graph_templates=2)
		OR (user_id=" . $_SESSION["sess_user_id"] . "))";

	$sql_where = "WHERE (((policy_graphs=2 AND type=1 AND local_graph_id=item_id) OR (policy_graphs=1 AND type=1 AND item_id!=local_graph_id))
		OR ((policy_hosts=2 AND type=3 AND host_id=item_id) OR (policy_hosts=1 AND type=3 AND item_id!=host_id))
		OR ((policy_graph_templates=2 AND type=4 AND gt.id=item_id) OR (policy_graph_templates=1 AND type=4 AND item_id!=gt.id))
		OR (item_id IS NULL AND (policy_graphs!=2 AND policy_hosts!=2 AND policy_graph_templates!=2)))";

	if (isset($options_array['host_id'])) {
		$sql_where .= " AND gl.host_id=" . $options_array['host_id'];
	}elseif (isset($_REQUEST['host_id']) && $_REQUEST['host_id'] > 0) {
		$sql_where .= " AND gl.host_id=" . get_request_var_request("host_id");
	}

	if (isset($options_array['graph_template_id'])) {
		$sql_where .= " AND gl.graph_template_id=" . $options_array['graph_template_id'];
	}elseif (isset($_REQUEST['graph_template_id']) && $_REQUEST['graph_template_id'] > 0) {
		$sql_where .= " AND gl.graph_template_id=" . get_request_var_request('graph_template_id');
	}

	if (isset($options_array['data_query_id'])) {
		$sql_where .= " AND gl.snmp_query_id=" . $options_array['data_query_id'];
	}

	if (isset($options_array['data_query_index'])) {
		$sql_where .= " AND gl.snmp_index='" . $options_array['data_query_index'] . "'";
	}

	if (isset($options_array['local_graph_id'])) {
		$sql_where .= " AND gl.id='" . $options_array['local_graph_id'] . "'";
	}

	if (isset($_REQUEST['filter']) && strlen($_REQUEST['filter'])) {
		$sql_where .= " AND gtg.title_cache LIKE '%%" . $_REQUEST['filter'] . "%%'";
	}

	/* the user select a bunch of graphs of the 'list' view and wants them displayed here */
	if (isset($_REQUEST["style"])) {
		if (get_request_var_request("style") == "selective") {
			/* process selected graphs */
			if (! empty($_REQUEST["graph_list"])) {
				foreach (explode(",",$_REQUEST["graph_list"]) as $item) {
					$graph_list[$item] = 1;
				}
			}else{
				$graph_list = array();
			}

			if (! empty($_REQUEST["graph_add"])) {
				foreach (explode(",",$_REQUEST["graph_add"]) as $item) {
					$graph_list[$item] = 1;
				}
			}

			/* remove items */
			if (! empty($_REQUEST["graph_remove"])) {
				foreach (explode(",",$_REQUEST["graph_remove"]) as $item) {
					unset($graph_list[$item]);
				}
			}

			$i = 0;
			foreach ($graph_list as $item => $value) {
				$graph_array[$i] = $item;
				$i++;
			}

			if ((isset($graph_array)) && (sizeof($graph_array) > 0)) {
				/* build sql string including each graph the user checked */
				$sql_or = "AND " . array_to_sql_or($graph_array, "gtg.local_graph_id");

				/* clear the filter vars so they don't affect our results */
				$_REQUEST["filter"]  = "";
				$_REQUEST["host_id"] = "0";

				$set_rra_id = empty($rra_id) ? read_graph_config_option("default_rra_id") : get_request_var("rra_id");
			}
		}
	}

	$sql_ubase = "(SELECT
		grp_id,
		policy_graphs AS pg,
		policy_hosts AS ph,
		policy_graph_templates AS pgt,
		user_id,
		item_id,
		type,
		gtg.local_graph_id,
		h.id AS host_id,
		gt.id AS gti,
		gtg.height,
		gtg.width,
		gtg.title_cache,
		gl.snmp_index
		FROM graph_templates_graph AS gtg
		LEFT JOIN graph_local AS gl ON gtg.local_graph_id=gl.id
		$sql_ujoin
		$sql_where
		$sql_or) AS user_graphs";

	$sql_gbase = "(SELECT
		grp_id,
		policy_graphs AS pg,
		policy_hosts AS ph,
		policy_graph_templates AS pgt,
		user_id,
		item_id,
		type,
		gtg.local_graph_id,
		h.id AS host_id,
		gt.id AS gti,
		gtg.height,
		gtg.width,
		gtg.title_cache,
		gl.snmp_index
		FROM graph_templates_graph AS gtg
		LEFT JOIN graph_local AS gl ON gtg.local_graph_id=gl.id
		$sql_gjoin
		$sql_where
		$sql_or) AS group_graphs";

	$total_rows = count(db_fetch_assoc("SELECT local_graph_id FROM $sql_ubase UNION SELECT local_graph_id FROM $sql_gbase"));

	if (isset($_REQUEST['rows'])) {
		$limit = "LIMIT " . ($_REQUEST["rows"]*($_REQUEST["page"]-1)) . "," . $_REQUEST["rows"];
	}else{
		$limit = "";
	}

	$sql = "SELECT DISTINCT local_graph_id, title_cache, height, width, snmp_index FROM $sql_ubase UNION SELECT DISTINCT local_graph_id, title_cache, height, width, snmp_index FROM $sql_gbase
		GROUP BY local_graph_id
		ORDER BY title_cache
		$limit";

	//echo $sql;

	$graphs = db_fetch_assoc($sql);

	return array('total_rows' => $total_rows, 'graphs' => $graphs);
}

function ugroup_templates_array() {
	$current_user = db_fetch_row("SELECT * FROM user_auth WHERE id=" . $_SESSION["sess_user_id"]);

	if ($current_user["policy_graph_templates"] == "1") {
		$sql_where = "WHERE user_auth_perms.user_id IS NULL";
	}elseif ($current_user["policy_graph_templates"] == "2") {
		$sql_where = "WHERE user_auth_perms.user_id IS NOT NULL AND user_auth_perms.user_id=" . $_SESSION["sess_user_id"];
	}

	$sql = "SELECT DISTINCT graph_templates.id, graph_templates.name
		FROM graph_templates
		INNER JOIN graph_local
		ON graph_local.graph_template_id=graph_templates.id
		INNER JOIN host
		ON graph_local.host_id=host.id
		LEFT JOIN user_auth_perms
		ON graph_templates.id=user_auth_perms.item_id 
		AND user_auth_perms.type=4 
		$sql_where
		UNION
		SELECT DISTINCT graph_templates.id, graph_templates.name
		FROM graph_templates
		INNER JOIN graph_local
		ON graph_local.graph_template_id=graph_templates.id
		INNER JOIN host
		ON graph_local.host_id=host.id
		INNER JOIN (SELECT policy_graph_templates, type, user_id, item_id
			FROM user_auth_group_perms AS uagp
			INNER JOIN user_auth_group_members AS uagm
			ON uagp.group_id=uagm.group_id
			INNER JOIN user_auth_group AS uag
			ON uag.id=uagp.group_id
			WHERE uag.enabled='on'
			AND " . ugroup_template_sql($_SESSION["sess_user_id"]) . ") AS user_auth_perms 
		ON (graph_templates.id=user_auth_perms.item_id)";

	$sql_where = "WHERE (((policy_graph_templates=1 AND type=4 AND user_id IS NULL) OR (policy_graph_templates=2 AND type=4 AND user_id IS NOT NULL))
		OR (item_id IS NULL))";

	//cacti_log(str_replace("\n", " ", str_replace("\t"," ", str_replace(" ", " ", $sql))));

	$template_list = db_fetch_assoc("$sql $sql_where ORDER BY name");

	if (sizeof($template_list)) { 
		return $template_list;
	}else{
		return false;
	}
}

function ugroup_host_array($hostarray) {
	$current_user = db_fetch_row("SELECT policy_hosts FROM user_auth WHERE id=" . $_SESSION["sess_user_id"]);

	if ($current_user["policy_hosts"] == "1") {
		$sql_where = "WHERE user_auth_perms.user_id IS NULL";
	}elseif ($current_user["policy_hosts"] == "2") {
		$sql_where = "WHERE user_auth_perms.user_id IS NOT NULL";
	}

	$sql = "SELECT DISTINCT host.id, " .
		($hostarray['type'] == "desc_w_host" ? "CONCAT_WS('',host.description,' (',host.hostname,')')":"host.description") . " as name,
		user_auth_perms.user_id
		FROM host
		LEFT JOIN user_auth_perms 
		ON (host.id=user_auth_perms.item_id 
		AND user_auth_perms.type=3 
		AND user_auth_perms.user_id=" . $_SESSION["sess_user_id"] . ")
		$sql_where
		UNION
		SELECT DISTINCT host.id, " .
		($hostarray['type'] == "desc_w_host" ? "CONCAT_WS('',host.description,' (',host.hostname,')')":"host.description") . " as name,
		user_auth_perms.user_id
		FROM host
		INNER JOIN (SELECT policy_hosts, type, user_id, item_id
			FROM user_auth_group_perms AS uagp
			INNER JOIN user_auth_group_members AS uagm
			ON uagp.group_id=uagm.group_id
			INNER JOIN user_auth_group AS uag
			ON uag.id=uagp.group_id
			WHERE uag.enabled='on'
			AND " . ugroup_host_sql($_SESSION["sess_user_id"]) . ") AS user_auth_perms 
		ON (host.id=user_auth_perms.item_id)";

	$sql_where = "WHERE (((policy_hosts=1 AND type=3 AND user_id IS NULL) OR (policy_hosts=2 AND type=3 AND user_id IS NOT NULL))
		OR (item_id IS NULL))";

	//cacti_log(str_replace("\n", " ", str_replace("\t"," ", str_replace(" ", " ", $sql))));

	$host_list = db_fetch_assoc("$sql $sql_where ORDER BY name");

	if (sizeof($host_list)) { 
		return $host_list;
	}else{
		return $hostarray;
	}
}

function ugroup_graphs_sql($user) {
	return "(type=1 AND ((user_id IS NULL 
		AND policy_graphs=1) 
		OR (user_id IS NOT NULL 
		AND policy_graphs=2 
		AND user_id='$user')))";
}

function ugroup_host_sql($user) {
	return "(type=3 AND ((user_id IS NULL 
		AND policy_hosts=1) 
		OR (user_id IS NOT NULL 
		AND policy_hosts=2 
		AND user_id='$user')))";
}

function ugroup_template_sql($user) {
	return "(type=4 AND ((user_id IS NULL 
		AND policy_graph_templates=1) 
		OR (user_id IS NOT NULL 
		AND policy_graph_templates=2 
		AND user_id='$user')))";
}

function ugroup_tree_sql($user) {
	return "((user_id IS NULL 
		AND policy_trees=1) 
		OR (user_id IS NOT NULL 
		AND policy_trees=2 
		AND user_id='$user'))";
}

function ugroup_user_realms($user_realms) {
	$group_realms = array_rekey(db_fetch_assoc("SELECT uagr.realm_id
		FROM user_auth_group_realm AS uagr
		INNER JOIN user_auth_group_members AS uagm
		ON uagr.group_id=uagm.group_id
		INNER JOIN user_auth_group AS uag
		ON uag.id=uagr.group_id
		WHERE uag.enabled='on' AND uagm.user_id='" . $_SESSION["sess_user_id"] . "'"), "realm_id", "realm_id");

	if (sizeof($group_realms)) {
		$user_realms += $group_realms;
	}

	return $user_realms;
}

function ugroup_console_authorized($authorized) {
	if (empty($authorized)) {
		$authorized = ugroup_group_console_allowed($_SESSION["sess_user_id"]);
	}

	return $authorized;
}

function ugroup_group_console_allowed($id) {
	/* ================= input validation ================= */
	input_validate_input_number(get_request_var_request('id'));
	/* ==================================================== */

	return db_fetch_cell("SELECT uagr.realm_id
		FROM user_auth_group_realm AS uagr
		INNER JOIN user_auth_group_members AS uagm
		ON uagr.group_id=uagm.group_id
		INNER JOIN user_auth_group AS uag
		ON uag.id=uagr.group_id
		WHERE uagm.user_id='$id'
		AND uag.enabled='on' AND uagr.realm_id=8");
}

function ugroup_user_console_authorized($id) {
	/* ================= input validation ================= */
	input_validate_input_number(get_request_var_request('id'));
	/* ==================================================== */

	return db_fetch_cell("SELECT realm_id
		FROM user_auth_realm
		WHERE user_id='$id' AND realm_id=8");
}

function ugroup_realm_authorized($autharray) {
	if (isset($_SESSION["sess_user_id"])) {
		$authorized = db_fetch_cell("SELECT uagr.realm_id
                FROM user_auth_group_realm AS uagr
				INNER JOIN user_auth_group_members AS uagm
				ON uagr.group_id=uagm.group_id
				INNER JOIN user_auth_group AS uag
				ON uag.id=uagr.group_id
                WHERE uagm.user_id='" . $_SESSION["sess_user_id"] . "'
                AND uag.enabled='on' 
				AND uagr.realm_id='" . $autharray['realm_id'] . "'");

		if ($authorized) {
			$autharray['authorized'] = true;
		}
	}

	return $autharray;
}

function ugroup_version () {
	return array(
		'name'     => 'ugroup',
		'version'  => '0.3',
		'longname' => 'Multiple User Groups for Cacti',
		'author'   => 'The Cacti Group',
		'homepage' => 'http://www.cacti.net',
		'email'    => '',
		'url'      => 'http://www.cacti.net'
		);
}

function plugin_ugroup_uninstall () {
	db_execute("DROP TABLE IF EXISTS user_auth_group");
	db_execute("DROP TABLE IF EXISTS user_auth_group_members");
	db_execute("DROP TABLE IF EXISTS user_auth_group_realm");
	db_execute("DROP TABLE IF EXISTS user_auth_group_perms");
	db_execute("DROP TABLE IF EXISTS settings_graphs_group");
}

function plugin_ugroup_check_config () {
	/* Here we will check to ensure everything is configured */
	ugroup_check_upgrade();
	return true;
}

function plugin_ugroup_upgrade() {
	/* Here we will upgrade to the newest version */
	ugroup_check_upgrade();
	return false;
}

function plugin_ugroup_version() {
	return ugroup_version();
}

function ugroup_check_upgrade() {
	$files = array('plugins.php', 'ugroup.php');
	if (isset($_SERVER['PHP_SELF']) && !in_array(basename($_SERVER['PHP_SELF']), $files)) {
		return;
	}
}

function ugroup_check_dependencies() {
	return true;
}

function ugroup_setup_table_new() {
	db_execute("CREATE TABLE IF NOT EXISTS `user_auth_group` (
		`id` int(10) unsigned NOT NULL auto_increment,
		`name` varchar(20) NOT NULL,
		`description` varchar(255) NOT NULL default '',
		`graph_settings` varchar(2) DEFAULT NULL,
		`login_opts` tinyint(1) NOT NULL DEFAULT '1',
		`show_tree` varchar(2) DEFAULT 'on',
		`show_list` varchar(2) DEFAULT 'on',
		`show_preview` varchar(2) NOT NULL DEFAULT 'on',
		`policy_graphs` tinyint(1) unsigned NOT NULL DEFAULT '1',
		`policy_trees` tinyint(1) unsigned NOT NULL DEFAULT '1',
		`policy_hosts` tinyint(1) unsigned NOT NULL DEFAULT '1',
		`policy_graph_templates` tinyint(1) unsigned NOT NULL DEFAULT '1',
		`enabled` char(2) NOT NULL DEFAULT 'on',
		PRIMARY KEY (`id`))
		ENGINE=MyISAM
		COMMENT='Table that Contains User Groups';");

	db_execute("CREATE TABLE IF NOT EXISTS `user_auth_group_perms` (
		`group_id` mediumint(8) unsigned NOT NULL DEFAULT '0',
		`item_id` mediumint(8) unsigned NOT NULL DEFAULT '0',
		`type` tinyint(2) unsigned NOT NULL DEFAULT '0',
		PRIMARY KEY (`group_id`,`item_id`,`type`),
		KEY `group_id` (`group_id`,`type`))
		ENGINE=MyISAM
		COMMENT='Table that Contains User Group Permissions';");

	db_execute("CREATE TABLE IF NOT EXISTS `user_auth_group_realm` (
		`group_id` int(10) unsigned NOT NULL,
		`realm_id` int(10) unsigned NOT NULL,
		PRIMARY KEY  (`group_id`, `realm_id`),
		KEY `group_id` (`group_id`),
		KEY `realm_id` (`realm_id`))
		ENGINE=MyISAM
		COMMENT='Table that Contains User Group Realm Permissions';");

	db_execute("CREATE TABLE IF NOT EXISTS `user_auth_group_members` (
		`group_id` int(10) unsigned NOT NULL,
		`user_id` int(10) unsigned NOT NULL,
		PRIMARY KEY  (`group_id`, `user_id`),
		KEY `group_id` (`group_id`),
		KEY `realm_id` (`user_id`))
		ENGINE=MyISAM
		COMMENT='Table that Contains User Group Members';");

	db_execute("CREATE TABLE IF NOT EXISTS `settings_graphs_group` (
		`group_id` smallint(8) unsigned NOT NULL DEFAULT '0',
		`name` varchar(50) NOT NULL DEFAULT '',
		`value` varchar(255) NOT NULL DEFAULT '',
		PRIMARY KEY (`group_id`,`name`))
		ENGINE=MyISAM
		COMMENT='Stores the Default User Group Graph Settings';");
}

function ugroup_config_arrays() {
	global $menu, $domain_types, $auth_realms, $auth_methods;

	ugroup_check_upgrade();

	$menu2 = array ();
	foreach ($menu as $temp => $temp2 ) {
		if ($temp == 'Utilities') {
			$newtmp2 = array();
			foreach($temp2 as $uri => $name) {
				if ($uri == 'user_admin.php') {
					$newtmp2['plugins/ugroup/user_admin.php'] = 'User Management';
					$newtmp2['plugins/ugroup/ugroup.php'] = 'User Groups';
				}else{
					$newtmp2[$uri] = $name;
				}
			}
			$menu2[$temp] = $newtmp2;
		}else{
			$menu2[$temp] = $temp2;
		}
	}
	$menu = $menu2;
}

function ugroup_draw_navigation_text ($nav) {
	$nav["ugroup.php:"] = array("title" => "User Groups", "mapping" => "index.php:", "url" => "ugroup.php:", "level" => "1");
	$nav["ugroup.php:edit"] = array("title" => "(Edit)", "mapping" => "index.php:,ugroup.php:", "url" => "ugroup.php:edit", "level" => "2");
	$nav["ugroup.php:actions"] = array("title" => "(Actions)", "mapping" => "index.php:,ugroup.php:", "url" => "ugroup.php:edit", "level" => "2");
	$nav["user_admin.php:edit"] = array("title" => "(Edit)", "mapping" => "index.php:,user_admin.php:", "url" => "user_admin.php:edit", "level" => "2");

	return $nav;
}

function ugroup_config_settings () {
	global $tabs, $settings;

	$temp = array(
		"ugroup_header" => array(
			"friendly_name" => "User Group Permissions",
			"method" => "spacer",
			),
		"ugroup_realm_method" => array(
			"friendly_name" => "User Realm Permission Calculation Method",
			"description" => "How should User Group Realm permissions be applied.  There are two methods; <b>Overlay</b>,
			is where the User Group Realm permissions are applied first followed by the Users.  The resulting permissions
			are the most permissive of the two; <b>Replacement</b>, is where the User Group permissions replace
			that of the users.",
			"method" => "drop_array",
			"array" => array(1 => "Overlay", 2 => "Replacement"),
			"default" => 1
			),
		"ugroup_graph_method" => array(
			"friendly_name" => "User Graph Permission Calculation Method",
			"description" => "How should User Group Graph permissions be applied.  There are two methods; <b>Overlay</b>,
			is where the User Group Graph permissions are applied first followed by the Users.  The resulting permissions
			are the most permissive of the two; <b>Replacement</b>, is where the User Group Graph permissions replace
			that of the users.",
			"method" => "drop_array",
			"array" => array(1 => "Overlay", 2 => "Replacement"),
			"default" => 1
			),
		"ugroup_settings_method" => array(
			"friendly_name" => "User Permission Graph Setting Calculation Method",
			"description" => "How should User Group Fraph Setting permissions be applied.  There are two methods; <b>Overlay</b>,
			is where the User Group permissions are applied first followed by the Users.  The resulting permissions
			are the most permissive of the two; <b>Replacement</b>, where the User Group Graph Setting permissions replace
			that of the users.",
			"method" => "drop_array",
			"array" => array(1 => "Overlay", 2 => "Replacement"),
			"default" => 1
			)
		);

	if (isset($settings["authentication"])) {
		$settings["authentication"] = array_merge($settings["authentication"], $temp);
	}else {
		$settings["authentication"] = $temp;
	}
}

?>
