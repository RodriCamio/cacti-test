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

include("./include/auth.php");
include_once($config["library_path"] . "/utility.php");

global $config;

/* set default action */
if (!isset($_REQUEST["action"])) { $_REQUEST["action"] = ""; }

switch ($_REQUEST["action"]) {
	case 'view':
		include_once("./plugins/docs/top_general_header.php");

		view_document();

		include_once("./include/bottom_footer.php");
		break;
	default:
		include_once("./plugins/docs/top_general_header.php");

		documents();

		include_once("./include/bottom_footer.php");
		break;
}

function view_document() {
	global $config;

	input_validate_input_number($_GET["id"]);

	print '<table width="100%" align="center"><tr><td>';
	print '<iframe id="data" align="center" src="' . $config["url_path"] . 'plugins/docs/docs_iview.php?id=' . $_GET["id"] . '" width="100%" height="100%" frameborder="0"></iframe>';
	print '</td></tr></table>';
}

function documents() {
	global $colors, $docs_actions;

	/* clean up sort_column */
	if (isset($_REQUEST["sort_column"])) {
		$_REQUEST["sort_column"] = sanitize_search_string(get_request_var("sort_column"));
	}

	/* clean up search string */
	if (isset($_REQUEST["sort_direction"])) {
		$_REQUEST["sort_direction"] = sanitize_search_string(get_request_var("sort_direction"));
	}

	/* remember these search fields in session vars so we don't have to keep passing them around */
	load_current_session_value("sort_column", "sess_docs_sort_column", "title");
	load_current_session_value("sort_direction", "sess_docs_sort_direction", "ASC");

	$display_text = array(
		"title" => array("Title", "ASC"),
		"type" => array("Type", "ASC"),
		"link" => array("Filename", "ASC"),
		"creator" => array("Creator", "ASC"),
		"updated" => array("Last Updated", "ASC"),
		"updatedby" => array("Updated By", "ASC"),);

	html_start_box("<strong>Documents</strong>", "100%", $colors["header"], "3", "center", "");

	html_header_sort($display_text, $_REQUEST["sort_column"], $_REQUEST["sort_direction"]);

	$documents = db_fetch_assoc("SELECT plugin_docs.*
		FROM plugin_docs
		ORDER BY " . $_REQUEST['sort_column'] . " " . $_REQUEST['sort_direction']);

	$results = db_fetch_assoc("SELECT id, username FROM user_auth");
	$users   = array();
	foreach ($results as $user) {
		$users[$user['id']] = $user['username'];
	}

	$i = 0;
	if (sizeof($documents)) {
		foreach ($documents as $document) {
			if ($document["type"] == 0 || substr_count($document["mimetype"], "image/")) {
				$url = "docs_view.php?action=view&id=" . $document["id"];
			}else{
				$url = "docs_iview.php?id=" . $document["id"];
			}

			form_alternate_row_color($colors["alternate"],$colors["light"],$i); $i++;
				?>
				<td>
					<a class="linkEditMain" href="<?php print $url;?>"><?php print $document['title']; ?></a>
				</td>
				<td>
					<?php print ($document['type'] == 0 ? "HTML":"Document"); ?>
				</td>
				<td>
					<?php print ($document['type'] == 0 ? "N/A":$document["link"]); ?>
				</td>
				<td>
					<?php (isset($users[$document['creator']]) ? print $users[$document['creator']] : "Unknown"); ?>
				</td>
				<td>
					<?php print date("F j, Y, g:i a", $document['updated']); ?>
				</td>
				<td>
					<?php (isset($users[$document['updatedby']]) ? print $users[$document['updatedby']] : "Unknown"); ?>
				</td>
			</tr>
		<?php
		}
	}else{
		print "<tr><td><em>No Documents</em></td></tr>\n";
	}
	html_end_box(false);
}

?>
