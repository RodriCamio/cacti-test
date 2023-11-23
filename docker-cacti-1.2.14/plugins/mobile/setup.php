<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2008 The Cacti Group                                      |
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

function mobile_version () {
	return array( 	'name' 		=> 'mobile',
			'version' 	=> '0.1',
			'longname'	=> 'Mobile Cacti',
			'author'	=> 'Jimmy Conner',
			'homepage'	=> 'http://cactiusers.org',
			'email'		=> 'jimmy@sqmail.org',
			'url'		=> 'http://versions.cactiusers.org/'
			);
}

function plugin_init_mobile() {
	global $plugin_hooks;
	$plugin_hooks['config_arrays']['mobile'] = 'plugin_mobile_loginbefore';
}

function plugin_mobile_loginbefore () {
	global $config;

	if (isset($_SERVER["argv"][0]) || !isset($_SERVER['REQUEST_METHOD'])  || !isset($_SERVER['REMOTE_ADDR'])) {
		return;
	}

	$browsers = array(
		'OpenWeb',
		'Windows CE',
		'NetFront',
		'Palm OS',
		'Blazer',
		'Elaine',
		'WAP',
		'Plucker',
		'AvantGo',
		'iPhone',
		'Mobile',
		'BlackBerry',
		'Opera Mobi',
		'Opera Mini',
		);

	foreach ($browsers as $b) {
		if (stristr($_SERVER['HTTP_USER_AGENT'], $b) !== FALSE) {

			print '<html><head><title>Cacti Mobile</title></head><body>
					<table align=center><tr><td><img src="' . $config['url_path'] . 'plugins/mobile/images/cacti_logo_small.gif"></td>
					<td><font size=4><b>Cacti Mobile</b></font></td>
					<td><img src="' . $config['url_path'] . 'plugins/mobile/images/cacti_logo_small.gif"></td>
					</tr></table><hr>';

			print '<strong>Hosts Down</strong><br>';
			print '<ul>';
			$hosts = db_fetch_assoc('SELECT * FROM host WHERE status < 2 AND disabled = ""');
			if (count($hosts)) {
				foreach ($hosts as $h) {
					print '<li>' . $h['description'] . ' (' . $h['hostname'] . ')';
				}
			} else {
				print '<li><i>None</i>';
			}
			print '</ul><br><br>';

			print '<b>Thresholds</b>';

			$thresholds = db_fetch_assoc('SELECT DISTINCT thold_data.*, data_template_data.name_cache, data_template_rrd.data_source_name
				FROM thold_data
				LEFT JOIN data_template_rrd ON data_template_rrd.id = thold_data.data_id
				LEFT JOIN data_template_data ON data_template_data.local_data_id = thold_data.rra_id 
				WHERE thold_alert > 0 AND thold_enabled = "on"
				ORDER BY name_cache ASC', FALSE);

			print '<ul>';
			if (count($thresholds)) {
				foreach($thresholds as $t) {
					print '<li>' . $t['name_cache'] . ' [' . $t['data_source_name'] . '] - Current: ' . $t['lastread'];
				}
			} else {
				print '<li><i>None</i>';
			}
			print '</ul>';

			exit;
		}
	}
}



