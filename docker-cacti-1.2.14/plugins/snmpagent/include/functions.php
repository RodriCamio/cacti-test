<?php
/*
   +-------------------------------------------------------------------------+
   | Copyright (C) 2004-2014 The Cacti Group                                 |
   |                                                                         |
   | This program is free software; you can redistribute it and/or           |
   | modify it under the terms of the GNU General Public License             |
   | as published by the Free Software Foundation; either version 2          |
   | of the License, or (at your option) any later version.                  |
   |                                                                         |
   | This program is snmpagent in the hope that it will be useful,           |
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

function snmpagent_xss_shield($allowable_tags='') {
	global $config;
	$params = $_REQUEST;
	foreach($params as $key => $value) {
		if($value != strip_tags($value, $allowable_tags) || $key != strip_tags($key, $allowable_tags)) {
			include_once(CACTI_INCLUDE_PATH . "/top_header.php");
			die_html_input_error();
		}
	}
}

?>