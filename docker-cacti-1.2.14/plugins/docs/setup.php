<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2007 The Cacti Group                                      |
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

define("DOCS_PERM_ADMIN", 0);
define("DOCS_PERM_USER",  1);
define("DOCS_PERM_NONE",  2);

function plugin_docs_install () {
	api_plugin_register_hook('docs', 'config_arrays',         'docs_config_arrays',        'setup.php');
	api_plugin_register_hook('docs', 'config_settings',       'docs_config_settings',      'setup.php');
	api_plugin_register_hook('docs', 'body_style',            'docs_body_style',           'setup.php');
	api_plugin_register_hook('docs', 'page_head',             'docs_page_head',            'setup.php');
	api_plugin_register_hook('docs', 'draw_navigation_text',  'docs_draw_navigation_text', 'setup.php');
	api_plugin_register_hook('docs', 'top_header_tabs',       'docs_show_tab',             'setup.php');
	api_plugin_register_hook('docs', 'top_graph_header_tabs', 'docs_show_tab',             'setup.php');
	api_plugin_register_hook('docs', 'top_graph_refresh',     'docs_top_graph_refresh',    'setup.php');

	api_plugin_register_realm('docs', 'docs_view.php', 'Plugin -> Cacti Documents Viewer', 1);
	api_plugin_register_realm('docs', 'docs.php', 'Plugin -> Cacti Documents Manager', 1);

	docs_setup_table_new ();
}

function docs_version () {
	return array( 
		'name' 		=> 'docs',
		'version' 	=> '0.4',
		'longname'	=> 'Documents',
		'author'	=> 'Jimmy Conner',
		'homepage'	=> 'http://www.cacti.net',
		'email'		=> 'jimmy@sqmail.org',
		'url'		=> 'http://www.cacti.net'
	);
}

function plugin_docs_uninstall () {
	/* Do any extra Uninstall stuff here */
	db_execute("DROP TABLE plugin_docs"); 
}

function plugin_docs_check_config () {
	/* Here we will check to ensure everything is configured */
	docs_check_upgrade ();
	return true;
}

function plugin_docs_upgrade () {
	/* Here we will upgrade to the newest version */
	docs_check_upgrade ();
	return false;
}

function plugin_docs_version () {
	return docs_version();
}

function docs_check_upgrade () {
	global $config, $database_default;
	include_once($config["library_path"] . "/database.php");
	include_once($config["library_path"] . "/functions.php");

	// Let's only run this check if we are on a page that actually needs the data
	$files = array('plugins.php', 'docs.php', 'docs_view.php');
	if (isset($_SERVER['PHP_SELF']) && !in_array(basename($_SERVER['PHP_SELF']), $files)) {
		return;
	}

	$current = docs_version();
	$current = $current['version'];
	$old     = db_fetch_cell("SELECT version FROM plugin_config WHERE directory='docs'");

	if ($current != $old) {
		/* update realms for old versions */
		if ($old < "0.3" || $old == '') {
			db_execute("ALTER TABLE plugin_docs MODIFY COLUMN creator INT UNSIGNED not NULL default '0'");
			db_execute("ALTER TABLE plugin_docs MODIFY COLUMN updatedby INT UNSIGNED not NULL default '0'");
			db_execute("ALTER TABLE plugin_docs ADD COLUMN published CHAR(2) NOT NULL DEFAULT '' AFTER updated");

			/* get the realm id's and change from old to new */
			$admin  = db_fetch_cell("SELECT id FROM plugin_realms WHERE file='docs.php'")+100;
			$user   = db_fetch_cell("SELECT id FROM plugin_realms WHERE file='docs_view.php'")+100;
			$users  = db_fetch_assoc("SELECT * FROM user_auth_realm WHERE realm_id='190'");

			if (sizeof($users)) {
				foreach($users as $u) {
					/* add new realms */
					db_execute("INSERT INTO user_auth_realm
						(realm_id, user_id) VALUES ($admin, " . $u["user_id"] . ")
						ON DUPLICATE KEY UPDATE realm_id=VALUES(realm_id)");
					db_execute("INSERT INTO user_auth_realm
						(realm_id, user_id) VALUES ($user, " . $u["user_id"] . ")
						ON DUPLICATE KEY UPDATE realm_id=VALUES(realm_id)");

					/* remove legacy realm */
					db_execute("DELETE FROM user_auth_realm
						WHERE user_id=" . $u["user_id"] . "
						AND realm_id=190");
				}
			}
		}
		db_execute("UPDATE plugin_config SET version='$current' WHERE directory='docs'");
	}
}

function docs_check_dependencies() {
	global $plugins, $config;
	return true;
}

function docs_setup_table_new () {
	global $config, $database_default;

	include_once($config["library_path"] . "/database.php");

	$tables = array();
	$sql    = array();
	$result = db_fetch_assoc("SHOW TABLES FROM `" . $database_default . "`");

	if (sizeof($result)) {
		foreach($result as $index => $arr) {
			foreach ($arr as $t) {
				$tables[] = $t;
			}
		}

		if (!in_array('plugin_docs', $tables)) {
			$sql[] = "CREATE TABLE `plugin_docs` (
				`id` int(11) NOT NULL AUTO_INCREMENT,
				`title` varchar(255) default '',
				`type` INT(10) unsigned not null default '0',
				`link` varchar(255) default '',
				`mimetype` varchar(50) default '',
				`data` LONGBLOB,
				`updated` int(11) NOT NULL default '0',
				`creator` varchar(255) NOT NULL default '',
				`updatedby` varchar(255) NOT NULL default '',
				PRIMARY KEY  (`id`)
				) ENGINE=MyISAM;";
		}else{
			$columns = db_fetch_assoc("SHOW COLUMNS FROM plugin_docs");

			$found = false;
			if (sizeof($columns)) {
			foreach($columns as $column) {
				if ($column["Title"] == 'link') {
					$found = true;
					break;
				}
			}
			}

			if (!$found) {
				db_execute("ALTER TABLE plugin_docs ADD COLUMN `link` VARCHAR(255) default '' AFTER `title`");
			}

			$found = false;
			if (sizeof($columns)) {
			foreach($columns as $column) {
				if ($column["Title"] == 'type') {
					$found = true;
					break;
				}
			}
			}

			if (!$found) {
				db_execute("ALTER TABLE plugin_docs ADD COLUMN `type` INT(10) unsigned not null default '0' AFTER `title`");
			}

			$found = false;
			if (sizeof($columns)) {
			foreach($columns as $column) {
				if ($column["Title"] == 'mime-type') {
					$found = true;
					break;
				}
			}
			}

			if (!$found) {
				db_execute("ALTER TABLE plugin_docs ADD COLUMN `mimetype` varchar(50) default '' AFTER `link`");
			}
		}

		if (!empty($sql)) {
			for ($a = 0; $a < count($sql); $a++) {
				$result = db_execute($sql[$a]);
			}
		}
	}
}

function docs_page_head() {
	global $config;

	if (basename($_SERVER["PHP_SELF"]) == "docs.php" || basename($_SERVER["PHP_SELF"]) == "docs_view.php") {
		if ((substr_count($_SERVER["REQUEST_URI"], "action=edit")) ||
			(substr_count($_SERVER["REQUEST_URI"], "action=view"))) {
			print "\t<script type=\"text/javascript\" src=\"" . $config['url_path'] . "plugins/docs/docs.js\"></script>";
			print "\t<script type=\"text/javascript\" src=\"" . $config['url_path'] . "plugins/docs/jscripts/jquery/jquery.min.js\"></script>";
			print "<link type='text/css' rel='stylesheet' href='" . $config['url_path'] . "plugins/docs/jscripts/tiny_mce/themes/advanced/skins/default/ui.css'>";
			print "<link type='text/css' rel='stylesheet' href='" . $config['url_path'] . "plugins/docs/jscripts/tiny_mce/plugins/inlinepopups/skins/clearlooks2/window.css'>";
		}
	}
}

function docs_body_style() {
	global $config;

	if (basename($_SERVER["PHP_SELF"]) == "docs.php" || basename($_SERVER["PHP_SELF"]) == "docs_view.php") {
		if (substr_count($_SERVER["REQUEST_URI"], "action=edit") || substr_count($_SERVER["REQUEST_URI"], "action=view")) {
			print "onResize=\"docsResize()\" onLoad=\"docsResize()\" ";
		}
	}
}

function docs_config_arrays() {
	global $menu, $user_auth_realms, $user_auth_realm_filenames, $messages;
	$menu["Management"]['plugins/docs/docs.php'] = "Documentation";

	$messages['file_type']['message'] = "Save Failed.  Invalid document type";
	$messages['file_type']['type'] = "error";
}

function docs_draw_navigation_text ($nav) {
	$nav["docs.php:"] = array("title" => "Documents", "mapping" => "index.php:", "url" => "docs.php", "level" => "1");
	$nav["docs.php:edit"] = array("title" => "Edit", "mapping" => "docs.php:", "url" => "docs.php", "level" => "2");
	$nav["docs.php:actions"] = array("title" => "Delete", "mapping" => "docs.php:", "url" => "docs.php", "level" => "2");
	$nav["docs_view.php:"] = array("title" => "Documentation", "mapping" => "", "url" => "docs_view.php", "level" => "0");
	$nav["docs_view.php:view"] = array("title" => "View Document", "mapping" => "docs_view.php:", "url" => "docs_view.php:,docs_view.php:view", "level" => "1");

	return $nav;
}

function docs_admin() {
	if ($_SESSION["sess_docs_level"] == DOCS_PERM_ADMIN) {
		return true;
	}else{
		return false;
	}
}

function docs_authorized() {
	if ($_SESSION["sess_docs_level"] == DOCS_PERM_ADMIN || $_SESSION["sess_docs_level"] == DOCS_PERM_USER) {
		return true;
	}else{
		return false;
	}
}

function docs_show_tab() {
	global $config;

	if (!isset($_SESSION["sess_docs_level"])) {
		$_SESSION["sess_docs_level"] = docs_permissions();
	}

	if ($_SESSION["sess_docs_level"] == DOCS_PERM_ADMIN || $_SESSION["sess_docs_level"] == DOCS_PERM_USER) {
		if (substr_count($_SERVER["REQUEST_URI"], "docs_view.php")) {
			print '<a href="' . $config['url_path'] . 'plugins/docs/docs_view.php"><img src="' . $config['url_path'] . 'plugins/docs/images/tab_docs_down.gif" alt="Document Viewer" align="absmiddle" border="0"></a>';
		}else{
			print '<a href="' . $config['url_path'] . 'plugins/docs/docs_view.php"><img src="' . $config['url_path'] . 'plugins/docs/images/tab_docs.gif" alt="Document Viewer" align="absmiddle" border="0"></a>';
		}
	}
}

function docs_permissions() {
	$admin_realm = db_fetch_cell("SELECT id FROM plugin_realms WHERE file LIKE '%docs.php%'") + 100;
	$user_realm  = db_fetch_cell("SELECT id FROM plugin_realms WHERE file LIKE '%docs_view.php%'") + 100;

	if (sizeof(db_fetch_assoc("SELECT realm_id
		FROM user_auth_realm
		WHERE user_id=" . $_SESSION["sess_user_id"] . "
		AND realm_id=$admin_realm"))) {
		return DOCS_PERM_ADMIN;
	}elseif (sizeof(db_fetch_assoc("SELECT realm_id
		FROM user_auth_realm
		WHERE user_id=" . $_SESSION["sess_user_id"] . "
		AND realm_id=$user_realm"))) {
		return DOCS_PERM_USER;
	}else{
		return DOCS_PERM_NONE;
	}
}

function docs_top_graph_refresh ($refresh) {
	if ((!substr_count(basename($_SERVER['PHP_SELF']), 'docs.php')) &&
		(!substr_count(basename($_SERVER['PHP_SELF']), 'docs_view.php'))) {
		return $refresh;
	}

	$r = get_request_var_request("refresh");
	if ($r == '' or $r < 1) return $refresh;
	return $r;
}

function docs_config_settings() {
	global $tabs, $settings;

	$tabs["misc"] = "Misc";

	$temp = array(
		"docs_header" => array(
			"friendly_name" => "Documentation Settings",
			"method" => "spacer",
			),
        "docs_safe_extensions" => array(
			"friendly_name" => "Safe Extensions",
			"description" => "A comma delimited list of allowable and safe file extensions that can be viewed inside your browser safely. To return to the defaults, simply clear the values below.",
			"method" => "textarea",
			"default" => "txt, csv, htm, html, xml, pdf, swf, flv, avi, jpg, jpeg, gif, png",
			"textarea_cols" => 80,
			"textarea_rows" => 3,
			"class" => "textAreaNotes"
			),
        "docs_unsafe_extensions" => array(
			"friendly_name" => "Unsafe Extensions",
			"description" => "A comma delimited list of allowable and safe file extensions that must be viewed outside the browser. To return to the defaults, simply clear the values below.",
			"method" => "textarea",
			"default" => "pps, doc, vis, docx, xlsx, pptx, xls, rtf, ppt, wmv, vsd, mov",
			"textarea_cols" => 80,
			"textarea_rows" => 3,
			"class" => "textAreaNotes"
			),
	);

	if (isset($settings["misc"])) {
		$settings["misc"] = array_merge($settings["misc"], $temp);
	}else {
		$settings["misc"] = $temp;
	}
}

?>
