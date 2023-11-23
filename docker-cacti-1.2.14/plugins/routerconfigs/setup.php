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

function routerconfigs_version () {
	return array(
		'name'     => 'routerconfigs',
		'version'  => '0.5',
		'longname' => 'Router Configs',
		'author'   => 'Jimmy Conner',
		'webpage' =>  'http://cactiusers.org',
		'email'    => 'jimmy@sqmail.org',
		'url'      => 'http://cactiusers.org/cacti/versions.php'
	);
}

function plugin_routerconfigs_install () {
	api_plugin_register_hook('routerconfigs', 'config_arrays',        'routerconfigs_config_arrays',        'setup.php');
	api_plugin_register_hook('routerconfigs', 'draw_navigation_text', 'routerconfigs_draw_navigation_text', 'setup.php');
	api_plugin_register_hook('routerconfigs', 'config_settings',      'routerconfigs_config_settings',      'setup.php');
	api_plugin_register_hook('routerconfigs', 'poller_bottom',        'routerconfigs_poller_bottom',        'setup.php');
	api_plugin_register_hook('routerconfigs', 'page_head',            'routerconfigs_page_head',            'setup.php');

	api_plugin_register_realm('routerconfigs', 'router-devices.php,router-accounts.php,router-backups.php,router-compare.php', 'Plugin -> Router Configs', 1);

	routerconfigs_setup_table_new();
}

function plugin_routerconfigs_uninstall () {
	db_execute("DROP TABLE plugin_routerconfigs_accounts");
	db_execute("DROP TABLE plugin_routerconfigs_backups");
	db_execute("DROP TABLE plugin_routerconfigs_devices");
	db_execute("DROP TABLE plugin_routerconfigs_devicetypes");
}

function plugin_routerconfigs_upgrade() {
	/* Here we will upgrade to the newest version */
	routerconfigs_check_upgrade();
	return false;
}

function plugin_routerconfigs_version() {
	return routerconfigs_version();
}

function routerconfigs_check_upgrade() {
	global $config, $database_default;
	include_once($config["library_path"] . "/database.php");
	include_once($config["library_path"] . "/functions.php");

	// Let's only run this check if we are on a page that actually needs the data
	$files = array('plugins.php');
	if (isset($_SERVER['PHP_SELF']) && !in_array(basename($_SERVER['PHP_SELF']), $files)) {
		return;
	}

	$current = routerconfigs_version();
	$current = $current['version'];
	$old     = db_fetch_cell("SELECT version FROM plugin_config WHERE directory='routerconfigs'");

	if ($current != $old) {
		/* update realms for old versions */
		if ($old < "0.2") {
			api_plugin_register_realm('routerconfigs', 'router-devices.php,router-accounts.php,router-backups.php,router-compare.php', 'Plugin -> Router Configs', 1);

			/* get the realm id's and change from old to new */
			$user  = db_fetch_cell("SELECT id FROM plugin_realms WHERE file='router-devices.php'");
			if ($user >  0) {
				$users = db_fetch_assoc("SELECT user_id FROM user_auth_realm WHERE realm_id=86");
				if (sizeof($users)) {
				foreach($users as $u) {
					db_execute("INSERT INTO user_auth_realm
						(realm_id, user_id) VALUES ($user, " . $u["user_id"] . ")
						ON DUPLICATE KEY UPDATE realm_id=VALUES(realm_id)");
					db_execute("DELETE FROM user_auth_realm
						WHERE user_id=" . $u["user_id"] . "
						AND realm_id=$user");
				}
				}
			}
		}
		db_execute("UPDATE plugin_config SET version='$current' WHERE directory='routerconfigs'");
	}
}

function routerconfigs_check_dependencies() {
	global $plugins, $config;
	return true;
}

function routerconfigs_setup_table_new() {
	db_execute("CREATE TABLE IF NOT EXISTS plugin_routerconfigs_accounts (
		id int(12) NOT NULL auto_increment,
		`name` varchar(64) NOT NULL,
		username varchar(64) NOT NULL,
		`password` varchar(256) NOT NULL,
		`enablepw` varchar(256) NOT NULL,
		PRIMARY KEY  (id)) ENGINE=MyISAM;");

	db_execute("CREATE TABLE plugin_routerconfigs_backups (
		id int(12) NOT NULL auto_increment,
		btime int(18) NOT NULL,
		device int(12) NOT NULL,
		`directory` varchar(255) NOT NULL,
		filename varchar(255) NOT NULL,
		config longtext NOT NULL,
		lastchange int(24) NOT NULL,
		username varchar(128) NOT NULL,
		PRIMARY KEY  (id),
		KEY btime (btime),
		KEY device (device),
		KEY `directory` (`directory`),
		KEY lastchange (lastchange)) ENGINE=MyISAM;");

	db_execute("CREATE TABLE plugin_routerconfigs_devices (
		id int(12) NOT NULL auto_increment,
		enabled varchar(2) NOT NULL default 'on',
		ipaddress varchar(128) NOT NULL,
		hostname varchar(255) NOT NULL,
		`directory` varchar(255) NOT NULL,
		account int(12) NOT NULL,
		lastchange int(24) NOT NULL,
		username varchar(128) NOT NULL,
		schedule int(6) NOT NULL default 1,
		lasterror varchar(255) NOT NULL,
		lastbackup int(18) NOT NULL,
		lastattempt int(18) NOT NULL,
		devicetype int(12) NOT NULL,
		debug text NOT NULL,
		PRIMARY KEY  (id),
		KEY enabled (enabled),
		KEY schedule (schedule),
		KEY ipaddress (ipaddress),
		KEY account (account),
		KEY lastbackup (lastbackup),
		KEY lastchange (lastchange),
		KEY lastattempt (lastattempt),
		KEY devicetype (devicetype)) ENGINE=MyISAM;");

	db_execute("CREATE TABLE plugin_routerconfigs_devicetypes (
		id int(12) NOT NULL auto_increment,
		`name` varchar(128) NOT NULL,
		username varchar(64) NOT NULL default 'sername:',
		`password` varchar(128) NOT NULL default 'assword:',
		copytftp varchar(64) NOT NULL default 'copy tftp run',
		version varchar(64) NOT NULL default 'show version',
		confirm varchar(64) NOT NULL default '',
		forceconfirm int(1) NOT NULL,
		PRIMARY KEY  (id)) ENGINE=MyISAM;");

	db_execute("REPLACE INTO plugin_routerconfigs_devicetypes 
		(id, name, username, password, copytftp, version, confirm, forceconfirm) 
		VALUES 
		(1, 'Cisco IOS', 'sername:', 'password:', 'copy run tftp', 'show version', 'y', 0),
		(2, 'Cisco CatOS', 'sername:', 'password:', 'copy config tftp', '', 'y', 1);");
}

function routerconfigs_page_head () {
	global $config;
	if (strpos($_SERVER['PHP_SELF'], 'router-compare.php')) {
		print '<link rel="stylesheet" type="text/css" href="' . $config['url_path'] . "plugins/routerconfigs/diff.css\">\n";
	}
}

function routerconfigs_poller_bottom () {
	global $config;

	$running = read_config_option('plugin_routerconfigs_running');
	if ($running == 1)
		return;

	/* Check for the polling interval, only valid with the Multipoller patch */
	$poller_interval = read_config_option("poller_interval");
	if (!isset($poller_interval)) {
		$poller_interval = 300;
	}
	$h = date('G', time());
	$s = date('i', time()) * 60;
	if ($h == 0 && $s < $poller_interval) {
		$command_string = trim(read_config_option("path_php_binary"));
		if (trim($command_string) == '')
			$command_string = "php";
		$extra_args = ' -q ' . $config['base_path'] . '/plugins/routerconfigs/router-download.php';
		exec_background($command_string, $extra_args);
	} else if ($s < $poller_interval){
		$t = time();
		$devices = db_fetch_assoc("SELECT * FROM plugin_routerconfigs_devices WHERE enabled = 'on' AND ($t - (schedule * 86400)) - 3600 > lastbackup AND $t - lastattempt > 1800", false);

		if (!empty($devices)) {
			$command_string = trim(read_config_option("path_php_binary"));
			if (trim($command_string) == '')
				$command_string = "php";
			$extra_args = ' -q ' . $config['base_path'] . '/plugins/routerconfigs/router-redownload.php';
			exec_background($command_string, $extra_args);
		}
	}
}

function routerconfigs_config_settings () {
	global $tabs, $settings, $config;
	$tabs["misc"] = "Misc";

	$temp = array(
		"routerconfigs_header" => array(
			"friendly_name" => "Router Configs",
			"method" => "spacer",
			),
		"routerconfigs_tftpserver" => array(
			"friendly_name" => "TFTP Server IP",
			"description" => "Must be an IP pointing to your Cacti server.",
			"method" => "textbox",
			"max_length" => 255,
			),
		"routerconfigs_backup_path" => array(
			"friendly_name" => "Backup Directory Path",
			"description" => "The path to where your Configs will be backed up, it must be the path that the local TFTP Server writes to.",
			"method" => "dirpath",
			"max_length" => 255,
			'default' => $config['base_path'] . '/plugins/routerconfigs/backups'
			),
		"routerconfigs_email" => array(
			"friendly_name" => "Email Address",
			"description" => "Email address to send the nightly backup email to.  Commna deliminate any extra email addresses.",
			"method" => "textbox",
			"size" => 40,
			"max_length" => 255,
			"default" => ''
			),
		"routerconfigs_from" => array(
			"friendly_name" => "From Address",
			"description" => "Email address the nightly backup will be sent from.",
			"method" => "textbox",
			"size" => 40,
			"max_length" => 255,
			"default" => ''
			),
		"routerconfigs_retention" => array(
			"friendly_name" => "Retention Period",
			"description" => "The number of days to retain old backups.",
			"method" => "textbox",
			"size" => 5,
			"max_length" => 3,
			"default" => '30'
			),
		);
	if (isset($settings["misc"]))
		$settings["misc"] = array_merge($settings["misc"], $temp);
	else
		$settings["misc"]=$temp;
}

function routerconfigs_config_arrays () {
	global $menu;

	$temp = $menu["Utilities"]['logout.php'];
	unset($menu["Utilities"]['logout.php']);
	$menu["Utilities"]['plugins/routerconfigs/router-devices.php'] = "Router Configs";
	$menu["Utilities"]['logout.php'] = $temp;
}

function routerconfigs_draw_navigation_text ($nav) {
	$nav["router-devices.php:"] = array("title" => "Router Devices", "mapping" => "index.php:", "url" => "router-devices.php", "level" => "1");
	$nav["router-devices.php:edit"] = array("title" => "Router Devices", "mapping" => "index.php:", "url" => "router-devices.php", "level" => "1");
	$nav["router-devices.php:actions"] = array("title" => "Router Devices", "mapping" => "index.php:", "url" => "router-devices.php", "level" => "1");
	$nav["router-devices.php:viewconfig"] = array("title" => "Router Devices", "mapping" => "index.php:", "url" => "router-devices.php", "level" => "1");
	$nav["router-devices.php:viewdebug"] = array("title" => "Router Devices", "mapping" => "index.php:", "url" => "router-devices.php", "level" => "1");

	$nav["router-backups.php:"] = array("title" => "Router Backups", "mapping" => "index.php:", "url" => "router-backups.php", "level" => "1");
	$nav["router-backups.php:edit"] = array("title" => "Router Backups", "mapping" => "index.php:", "url" => "router-backups.php", "level" => "1");
	$nav["router-backups.php:actions"] = array("title" => "Router Backups", "mapping" => "index.php:", "url" => "router-backups.php", "level" => "1");
	$nav["router-backups.php:viewconfig"] = array("title" => "Router Backups", "mapping" => "index.php:", "url" => "router-backups.php", "level" => "1");

	$nav["router-accounts.php:"] = array("title" => "Router Accounts", "mapping" => "index.php:", "url" => "router-accounts.php", "level" => "1");
	$nav["router-accounts.php:edit"] = array("title" => "Router Accounts", "mapping" => "index.php:", "url" => "router-accounts.php", "level" => "1");
	$nav["router-accounts.php:actions"] = array("title" => "Router Accounts", "mapping" => "index.php:", "url" => "router-accounts.php", "level" => "1");

	$nav["router-compare.php:"] = array("title" => "Router Config Compare", "mapping" => "index.php:", "url" => "router-compare.php", "level" => "1");
	return $nav;
}

