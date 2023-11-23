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

function plugin_remote_install () {
	api_plugin_register_hook('remote', 'config_settings',      'remote_config_settings',      'setup.php');
	api_plugin_register_hook('remote', 'remote_launch',        'remote_launch',               'setup.php');
	api_plugin_register_hook('remote', 'remote_link',          'remote_link',                 'setup.php');
	api_plugin_register_hook('remote', 'page_head',            'remote_page_head',            'setup.php');

	api_plugin_register_realm('remote', 'remote.php', 'Plugin -> Host Remote Console', 1);

	remote_setup_table_new ();
}

function remote_version () {
	return array(
		'name'     => 'Remote Console',
		'version'  => '0.1',
		'longname' => 'Host Remote Console Utility',
		'author'   => 'The Cacti Group',
		'homepage' => 'http://www.cacti.net',
		'email'    => '',
		'url'      => 'http://www.cacti.net'
	);
}

function plugin_remote_uninstall () {
	/* Do any extra Uninstall stuff here */
}

function plugin_remote_check_config () {
	/* Here we will check to ensure everything is configured */
	remote_check_upgrade ();
	return true;
}

function plugin_remote_upgrade () {
	/* Here we will upgrade to the newest version */
	remote_check_upgrade ();
	return false;
}

function plugin_remote_version () {
	return remote_version();
}

function remote_check_upgrade () {
	/* Let's only run this check if we are on a page
	   that actually needs the data */
}

function remote_check_dependencies() {
	global $plugins, $config;
	return true;
}

function remote_setup_table_new () {
	/* nothing to do */
}

function remote_page_head() {
	global $config;

	print "<script type='text/javascript' src='" . $config['url_path'] . "plugins/remote/remote.js'></script>\n";
}

function remote_link($hostinfo) {
	global $config;

	$info = remote_process_hostinfo($hostinfo);

	if (sizeof($info)) {
		print "<img border='0' title='Remote Console to Host' style='cursor:pointer;' alt='Remote' src='" . $config["url_path"] . "plugins/remote/images/remote.gif' onClick='javascript:remote_launcher(\"" . htmlspecialchars($config["url_path"] . "plugins/remote/remote.php?user=" . $info["user"] . "&transport=" . $info["transport"] . "&host=" . $info["hostname"]) . "\", \"" . $info["window"] . "\", " . $info["width"] . ", " . $info["height"] . ")'>\n";
	}
	return $hostinfo;
}

function remote_process_hostinfo($hostinfo) {
	global $config;

	$info = array();

	if (!is_array($hostinfo)) {
		return $info;
	}else{
		if (!isset($hostinfo["transport"])) {
			return $info;
		}else{
			$transport = $hostinfo["transport"];

			if ($transport != "telnet" && $transport != "ssh") {
				return $info;
			}
		}

		if (!isset($hostinfo["hostname"])) {
			return $info;
		}else{
			$hostname = $hostinfo["hostname"];
		}

		if (!isset($hostinfo["remote_window"])) {
			$width_height = explode("x", read_config_option("remote_window"));
		}else{
			$width_height = explode("x", $hostinfo["remote_window"]);
		}
		$width        = $width_height[0];
		$height       = $width_height[1];

		if (!isset($hostinfo["user"])) {
			$user = "";
		}else{
			$user = $hostinfo["user"];
		}

		$window_name = str_replace("_", "", str_replace(".", "", str_replace(" ", "", $hostname)));
		$window_name = clean_up_name($hostname);

		$info["hostname"]  = $hostname;
		$info["transport"] = $transport;
		$info["height"]    = $height;
		$info["width"]     = $width;
		$info["user"]      = $user;
		$info["window"]    = $window_name;
	}

	return $info;
}

function remote_launch($hostinfo) {
	global $config;

	$info = remote_process_hostinfo($hostinfo);

	if (sizeof($info)) {
		print "<a href=\'javascript:remote_launcher(&quot;" . $config["url_path"] . "plugins/remote/remote.php?user=" . $info["user"] . "&transport=" . $info["transport"] . "&host=" . $info["hostname"] . "&quot;, &quot;" . $info["hostname"] . "&quot;, $width, $height)\'><strong>, Remote to Console to Host</strong></a>";
	}

	return $hostinfo;
}

function remote_config_settings () {
	global $tabs, $settings;

	$tabs["remote"] = "Remote Console";

	$temp = array(
		"remote_header" => array(
			"friendly_name" => "Host Remote Console Configuration",
			"method" => "spacer",
			),
		"remote_window" => array(
			"friendly_name" => "Window Size",
			"description" => "The size of the Remote Console Window.",
			"method" => "drop_array",
			"array" => array(
				"640x480"   => "640x480",
				"800x600"   => "800x600",
				"1024x768"  => "1024x768",
				"1280x1024" => "1280x1024")
			),
		"remote_font" => array(
			"friendly_name" => "Remote Console Font Size",
			"decription" => "The Font size to use for the Console",
			"method" => "drop_array",
			"default" => "12",
			"array" => array(
				8 => "8",
				9 => "9",
				10 => "10",
				11 => "11",
				12 => "12",
				13 => "13",
				14 => "14")
		),
		"remote_rows" => array(
			"friendly_name" => "Remove Console Rows",
			"description" => "How many Remote Console rows do you wish to display on screen",
			"method" => "textbox",
			"max_length" => 5,
			"default" => 24,
			"size" => 5
		),
		"remote_cols" => array(
			"friendly_name" => "Remote Console Columns",
			"description" => "How many Remote Console columns do you wish to display on screen",
			"method" => "textbox",
			"max_length" => 5,
			"default" => 80,
			"size" => 5
		),
		"remote_font_color" => array(
			"friendly_name" => "Font Color",
			"description" => "What color should be used for the Remote Console",
			"method" => "drop_color",
			"default" => "FFFFFF"
		),
		"remote_background_color" => array(
			"friendly_name" => "Background Color",
			"description" => "Wat background color should be used for the Remote Console",
			"method" => "drop_color",
			"default" => "000000"
		)
	);

	if (isset($settings["remote"])) {
		$settings["remote"] = array_merge($settings["remote"], $temp);
	}else{
		$settings["remote"] = $temp;
	}
}

?>
