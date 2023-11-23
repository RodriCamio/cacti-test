<?php
/*
 ex: set tabstop=4 shiftwidth=4 autoindent:
 +-------------------------------------------------------------------------+
 | Copyright (C) 2008-2012 The Cacti Group                                 |
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


function plugin_logrotate_install () {
	api_plugin_register_hook('logrotate', 'config_settings', 'plugin_logrotate_config_settings', 'setup.php');
	api_plugin_register_hook('logrotate', 'poller_top', 'plugin_logrotate_poller_top', 'setup.php');
	set_config_option('plugin_logrotate_enabled', '');
	set_config_option('plugin_logrotate_retain', 7);
	set_config_option('plugin_logrotate_lastrun', time()-86400);
}

function plugin_logrotate_poller_top () {
	if (read_config_option('plugin_logrotate_enabled') == 'on') {
		if (date('G') == 0 && date('i') < 5 && (time() - read_config_option('plugin_logrotate_lastrun') > 3600)) {
			plugin_logrotate_rotatenow();
		}
	}
}

function plugin_logrotate_rotatenow () {
	global $config;
	$log = $config['base_path'] . '/log/cacti.log';
	set_config_option('plugin_logrotate_lastrun', time());
	clearstatcache();
	if (is_writable($config['base_path'] . '/log/') && is_writable($log)) {
		$perms = octdec(substr(decoct( fileperms($log) ), 2));
		$owner = fileowner($log);
		$group = filegroup($log);
		if ($owner !== FALSE) {
			$ext = date('Ymd');
			if (file_exists($log . '-' . $ext)) {
				$ext = date('YmdHis');
			}
			if (rename($log, $log . '-' . $ext)) {
				touch($log);
				chown($log, $owner);
				chgrp($log, $group);
				chmod($log, $perms);
				cacti_log('Cacti Log Rotation - Created Log cacti.log-' . $ext);
			} else {
				cacti_log('Cacti Log Rotation - ERROR: Could not rename cacti.log to ' . $log . '-' . $ext);
			}
		} else {
			cacti_log('Cacti Log Rotation - ERROR: Permissions issue.  Please check your log directory');
		}
	} else {
		cacti_log('Cacti Log Rotation - ERROR: Permissions issue.  Directory / Log not writable.');
	}
	plugin_logrotate_cleanold();
}

function plugin_logrotate_cleanold () {
	global $config;
	$dir = scandir($config['base_path'] . '/log/');
	$r = read_config_option('plugin_logrotate_retain');
	if ($r == '' || $r < 0) {
		$r = 7;
	}
	if ($r > 365) {
		$r = 365;
	}
	if ($r == 0) {
		return;
	}
	foreach ($dir as $d) {
		if (substr($d, 0, 10) == "cacti.log-" && strlen($d) >= 18) {
			$e = date('Ymd', time() - ($r * 86400));
			$f = substr($d, 10, 8);
			if ($f < $e) {
				if (is_writable($config['base_path'] . '/log/' . $d)) {
					@unlink($config['base_path'] . '/log/' . $d);
					cacti_log('Cacti Log Rotation - Purging Log : ' . $d);
				} else {
					cacti_log('Cacti Log Rotation - ERROR: Can not purge log : ' . $d);
				}
			}
		}
	}
	clearstatcache();
}

function plugin_logrotate_config_settings () {
	global $settings;
	$settings['poller']['plugin_logrotate_header'] = array(
			"friendly_name" => "Log Rotation",
			"method" => "spacer",
			);
	$settings['poller']['plugin_logrotate_enabled'] = array(
			"friendly_name" => "Rotate the Cacti Log Nightly",
			"description" => "This will rotate the Cacti Log every night at midnight.",
			"method" => "checkbox",
			"default" => '',
			);
	$settings['poller']['plugin_logrotate_retain'] = array(
			"friendly_name" => "Log Retention",
			"description" => "The number of days to retain old logs.  Use 0 to never remove any logs. (0-365)",
			"method" => "textbox",
			"default" => "7",
			"max_length" => 3,
			);
}

function plugin_logrotate_uninstall () {
}

function plugin_logrotate_check_config () {
	return true;
}

function plugin_logrotate_upgrade () {
	return false;
}

function logrotate_version () {
	return plugin_logrotate_version();
}

function plugin_logrotate_version () {
	return array(
		'name'     => 'logrotate',
		'version'  => '0.1',
		'longname' => 'Cacti Log Rotation',
		'author'   => 'Jimmy Conner',
		'homepage' => 'http://cactiusers.org',
		'email'    => 'jimmy@sqmail.org',
		'url'      => 'http://versions.cactiusers.org/'
	);
}
