<?php
/*
 ex: set tabstop=4 shiftwidth=4 autoindent:
 +-------------------------------------------------------------------------+
 | Copyright (C) 2008-2010 The Cacti Group                                 |
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

function plugin_watermark_install () {
	api_plugin_register_hook('watermark', 'rrd_graph_graph_options', 'watermark_rrd_graph_graph_options', 'setup.php');
	api_plugin_register_hook('watermark', 'config_settings', 'watermark_config_settings', 'setup.php');
}

function plugin_watermark_uninstall () {
}

function plugin_watermark_check_config () {
	return true;
}

function plugin_watermark_upgrade () {
	return false;
}

function watermark_version () {
	return plugin_watermark_version();
}

function plugin_watermark_version () {
	return array(
		'name'     => 'watermark',
		'version'  => '0.2',
		'longname' => 'Watermark',
		'author'   => 'Jimmy Conner',
		'homepage' => 'http://cactiusers.org',
		'email'    => 'jimmy@sqmail.org',
		'url'      => 'http://versions.cactiusers.org/'
	);
}

function watermark_rrd_graph_graph_options ($g) {
	$text = trim(read_config_option('plugin_watermark_text'));
	$text = trim(str_replace(array('|', "\\", '"'), '',  $text));
	if ($text != '') {
		$g['graph_defs'] .= '--watermark "' . $text . '" \\' . "\n";
	}

	$canvas = trim(read_config_option('plugin_watermark_canvas'));
	if ($canvas > 0) {
		$canvas = db_fetch_cell("SELECT hex FROM colors WHERE id=$canvas");
		$g['graph_defs'] .= '--color CANVAS#' . $canvas . RRD_NL;
	}

	$font   = trim(read_config_option('plugin_watermark_font'));
	if ($font > 0) {
		$font = db_fetch_cell("SELECT hex FROM colors WHERE id=$font");
		$g['graph_defs'] .= '--color FONT#' . $font . RRD_NL;
	}

	$back   = trim(read_config_option('plugin_watermark_background'));
	if ($back > 0) {
		$back = db_fetch_cell("SELECT hex FROM colors WHERE id=$back");
		$g['graph_defs'] .= '--color BACK#' . $back . RRD_NL;
	}

	$arrow  = trim(read_config_option('plugin_watermark_arrow'));
	if ($arrow > 0) {
		$arrow = db_fetch_cell("SELECT hex FROM colors WHERE id=$arrow");
		$g['graph_defs'] .= '--color ARROW#' . $arrow . RRD_NL;
	}

	if (read_config_option("plugin_watermark_gridfit")) {
		$g['graph_defs'] .= '--no-gridfit' . RRD_NL;
	}

	return $g;
}

function watermark_config_settings () {
	global $settings;
	$settings['visual']['watermark_header'] = array(
			"friendly_name" => "Watermark",
			"method" => "spacer",
			);
	$settings['visual']['plugin_watermark_gridfit'] = array(
			"friendly_name" => "No Grid Fit",
			"description" => "Allow for Crisper Graphs.",
			"method" => "checkbox",
			"default" => '',
			);
	$settings['visual']['plugin_watermark_text'] = array(
			"friendly_name" => "Watermark",
			"description" => "This is visual text to place at the bottom of each graph.",
			"method" => "textbox",
			"default" => "",
			"max_length" => 255,
			);
	$settings['visual']['plugin_watermark_canvas'] = array(
			"friendly_name" => "Canvas Color",
			"description" => "Graph Canvas Color.",
			"method" => "drop_color",
			"default" => 0,
			);
	$settings['visual']['plugin_watermark_font'] = array(
			"friendly_name" => "Font Color",
			"description" => "Graph Font Color.",
			"method" => "drop_color",
			"default" => 0,
			);
	$settings['visual']['plugin_watermark_background'] = array(
			"friendly_name" => "Back Color",
			"description" => "Graph Background Color.",
			"method" => "drop_color",
			"default" => 0,
			);
	$settings['visual']['plugin_watermark_arrow'] = array(
			"friendly_name" => "Arrow Color",
			"description" => "Graph Arrow Color.",
			"method" => "drop_color",
			"default" => 0,
			);
}
