<?php
/*
 ex: set tabstop=4 shiftwidth=4 autoindent:
 +-------------------------------------------------------------------------+
 | Copyright (C) 2011 The Cacti Group                                      |
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
 | This code is designed, written, and usersettingsained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

function plugin_usersettings_version () {
	return array( 
		'name' 	=> 'usersettings',
		'version' 	=> '0.1',
		'longname'	=> 'User Settings',
		'author'	=> 'The Cacti Group',
		'homepage'	=> 'http://cacti.net',
		'url'		=> 'http://cactiusers.org/cacti/versions.php'
		);
}

function plugin_usersettings_install () {
	api_plugin_register_hook('usersettings', 'config_arrays', 'plugin_usersettings_config_arrays', 'setup.php');
	api_plugin_register_hook('usersettings', 'draw_navigation_text', 'plugin_usersettings_draw_navigation_text', 'setup.php');
	api_plugin_register_realm('usersettings', 'usersettings.php', 'User Settings', 1);
	db_execute("UPDATE plugin_realms SET id = -92 WHERE plugin = 'usersettings'");
	plugin_usersettings_setup_database ();
}

function plugin_usersettings_uninstall () {

}

function plugin_usersettings_check_config () {
	return true;
}

function plugin_usersettings_upgrade () {
	return false;
}

function usersettings_version () {
	return plugin_usersettings_version();
}

function plugin_usersettings_config_arrays () {
	global $menu;
	if (isset($_SESSION) && isset($_SESSION['sess_user_id'])) {
		$id = intval($_SESSION['sess_user_id']);
		if ($id > 0) {
			$temp = $menu["Utilities"]['logout.php'];
			unset($menu["Utilities"]['logout.php']);
			$menu["Utilities"]['plugins/usersettings/usersettings.php'] = "User Settings";
			$menu["Utilities"]['logout.php'] = $temp;
			$usettings = db_fetch_row("SELECT * FROM plugin_usersettings WHERE id = $id");
			if (isset($usettings['id'])) {
				foreach ($usettings as $u => $v) {
					if ($u != 'id') {
						$_SESSION['sess_config_array'][$u] = $v;
					}
				}
			}
		}
	}
}

function plugin_usersettings_draw_navigation_text ($nav) {
	$nav["usersettings.php:"] = array("title" => "User Settings", "mapping" => "index.php:", "url" => "usersettings.php", "level" => "1");
	return $nav;
}

function plugin_usersettings_setup_database () {
	$data = array();
	$data['columns'][] = array('name' => 'id', 'type' => "smallint(6)", 'NULL' => true);
	$data['columns'][] = array('name' => 'num_rows_graph', 'type' => "smallint(6)", 'NULL' => true, 'default' => '30');
	$data['columns'][] = array('name' => 'max_title_graph', 'type' => "smallint(6)", 'NULL' => true, 'default' => '80');
	$data['columns'][] = array('name' => 'max_data_query_field_length', 'type' => "smallint(6)", 'NULL' => true, 'default' => '15');
	$data['columns'][] = array('name' => 'default_graphs_new_dropdown', 'type' => "smallint(6)", 'NULL' => true, 'default' => '-2');
	$data['columns'][] = array('name' => 'num_rows_data_query', 'type' => "smallint(6)", 'NULL' => true, 'default' => '30');
	$data['columns'][] = array('name' => 'num_rows_data_source', 'type' => "smallint(6)", 'NULL' => true, 'default' => '30');
	$data['columns'][] = array('name' => 'max_title_data_source', 'type' => "smallint(6)", 'NULL' => true, 'default' => '30');
	$data['columns'][] = array('name' => 'num_rows_device', 'type' => "smallint(6)", 'NULL' => true, 'default' => '30');
	$data['columns'][] = array('name' => 'num_rows_log', 'type' => "smallint(6)", 'NULL' => true, 'default' => '500');
	$data['columns'][] = array('name' => 'log_refresh_interval', 'type' => "smallint(6)", 'NULL' => true, 'default' => '60');
	$data['columns'][] = array('name' => 'title_size', 'type' => "smallint(6)", 'NULL' => true, 'default' => '10');
	$data['columns'][] = array('name' => 'title_font', 'type' => "varchar(100)", 'NULL' => true);
	$data['columns'][] = array('name' => 'legend_size', 'type' => "smallint(6)", 'NULL' => true, 'default' => '8');
	$data['columns'][] = array('name' => 'legend_font', 'type' => "varchar(100)", 'NULL' => true);
	$data['columns'][] = array('name' => 'axis_size', 'type' => "smallint(6)", 'NULL' => true, 'default' => '7');
	$data['columns'][] = array('name' => 'axis_font', 'type' => "varchar(100)", 'NULL' => true);
	$data['columns'][] = array('name' => 'unit_size', 'type' => "smallint(6)", 'NULL' => true, 'default' => '7');
	$data['columns'][] = array('name' => 'unit_font', 'type' => "varchar(100)", 'NULL' => true);
	$data['primary'] = 'id';
	$data['type'] = 'MyISAM';
	api_plugin_db_table_create ($plugin, 'plugin_usersettings', $data);
}


