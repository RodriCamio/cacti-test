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

chdir('../../');
include("./include/global.php");
include("./lib/utility.php");

function get_extension($file) {
	$parts = explode(".",$file);
	if (sizeof($parts)) {
		return $parts[sizeof($parts)-1];
	}else{
		return "";
	}
}

if (docs_authorized()) {
	/* ================= input validation ================= */
	input_validate_input_number(get_request_var("id"));
	/* ==================================================== */

	$document = db_fetch_row("SELECT * FROM plugin_docs WHERE id=" . $_GET["id"]);

	if ($document["type"] != 0) {
		$extension = get_extension($document['link']);
		$safe      = explode(",", str_replace(" ", "", trim(read_config_option("docs_safe_extensions"))));
		$unsafe    = explode(",", str_replace(" ", "", trim(read_config_option("docs_unsafe_extensions"))));
		$data      = base64_decode($document["data"]);

		header("Content-type: " . $document["mimetype"]);
		if (in_array($extension, $unsafe) !== false) {
			header("Content-Disposition: attachment; filename=" . str_replace(" ", "_", $document["link"]));
		}
		print $data;
	}else{
		print htmlspecialchars_decode(base64_decode($document["data"]));
	}

	/* flush the headers now */
	session_write_close();
}

?>
