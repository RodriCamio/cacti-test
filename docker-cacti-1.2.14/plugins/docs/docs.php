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

$docs_actions = array(
	1 => "Delete"
	);

/* set default action */
if (!isset($_REQUEST["action"])) { $_REQUEST["action"] = ""; }

switch ($_REQUEST["action"]) {
	case 'save':
		form_save();

		break;
	case 'actions':
		form_actions();

		break;
	case 'edit':
		include_once("./include/top_header.php");

		document_edit();

		include_once("./include/bottom_footer.php");
		break;
	default:
		include_once("./include/top_header.php");

		documents();

		include_once("./include/bottom_footer.php");
		break;
}

function form_actions() {
	global $colors, $config, $docs_actions;

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var_post('drp_action'));
	/* ==================================================== */

	/* if we are to save this form, instead of display it */
	if (isset($_POST["selected_items"])) {
		$selected_items = unserialize(stripslashes($_POST["selected_items"]));

		if ($_POST["drp_action"] == "1") { /* delete */
			db_execute("delete from plugin_docs where " . array_to_sql_or($selected_items, "id"));
		}

		header("Location: docs.php");
		exit;
	}

	/* setup some variables */
	$docs_list = ""; $i = 0;

	while (list($var,$val) = each($_POST)) {
		if (ereg("^chk_([0-9]+)$", $var, $matches)) {
			/* ================= input validation ================= */
			input_validate_input_number($matches[1]);
			/* ==================================================== */

			$docs_list .= "<li>" . db_fetch_cell("select title from plugin_docs  where id=" . $matches[1]) . "<br>";
			$docs_array[$i] = $matches[1];
		}

		$i++;
	}

	include_once("./include/top_header.php");

	html_start_box("<strong>" . $docs_actions{$_POST["drp_action"]} . "</strong>", "60%", $colors["header_panel"], "3", "center", "");

	print "<form action='docs.php' method='post'>\n";

	if ($_POST["drp_action"] == "1") { /* delete */
		print "	<tr>
				<td class='textArea' bgcolor='#" . $colors["form_alternate1"]. "'>
					<p>Are you sure you want to delete the following Documents?</p>
					<p>$docs_list</p>
				</td>
			</tr>\n
			";
	}

	if (!isset($docs_array)) {
		print "<tr><td bgcolor='#" . $colors["form_alternate1"]. "'><span class='textError'>You must select at least one document.</span></td></tr>\n";
		$save_html = "";
	}else{
		$save_html = "<input type='image' src='" . $config['url_path'] . "images/button_yes.gif' alt='Save' align='absmiddle'>";
	}

	print "	<tr>
			<td align='right' bgcolor='#eaeaea'>
				<input type='hidden' name='action' value='actions'>
				<input type='hidden' name='selected_items' value='" . (isset($docs_array) ? serialize($docs_array) : '') . "'>
				<input type='hidden' name='drp_action' value='" . $_POST["drp_action"] . "'>
				<a href='docs.php'><img src='" . $config['url_path'] . "images/button_no.gif' alt='Cancel' align='absmiddle' border='0'></a>
				$save_html
			</td>
		</tr>
		";

	html_end_box();

	include_once("./include/bottom_footer.php");
}

function form_save() {
	global $cnn_id, $config;

	if (isset($_POST["id"])) {
		$safe      = str_replace(" ", "", trim(read_config_option("docs_safe_extensions")));
		$unsafe    = str_replace(" ", "", trim(read_config_option("docs_unsafe_extensions")));

		$allowedExtensions = explode(",", $safe . "," . $unsafe);

		$type = db_fetch_cell("SELECT type FROM plugin_docs WHERE id=" . $_POST["id"]);

		if (is_uploaded_file($_FILES['upload']['tmp_name'])) {
			if (!in_array(end(explode(".", strtolower($_FILES['upload']['name']))), $allowedExtensions)) {
				raise_message('file_type');
				header("Location: docs.php?id=" . (empty($id) ? $_POST["id"] : $id));
			}

			$save["type"]      = 1;
			$save["link"]      = basename($_FILES['upload']['name']);
			$save["data"]      = base64_encode(file_get_contents($_FILES['upload']['tmp_name']));
			$save["mimetype"] = $_FILES['upload']['type'];
		}elseif ($type == '' || $type == 0) {
			$save["type"]      = 0;
			$save["link"]      = '';
			$save["data"]      = base64_encode($_POST["data"]);
			$save["mimetype"] = '';
		}

		input_validate_input_number(get_request_var("id"));
		$save["id"] = $_POST["id"];
		$save["title"] = $cnn_id->qstr($_POST["title"]);
		$save["updated"] = time();
		if ($save["id"] == 0) {
			$save["creator"] = $_SESSION['sess_user_id'];
		}
		$save["updatedby"] = $_SESSION['sess_user_id'];

		if (!is_error_message()) {
			$id = sql_save($save, "plugin_docs");

			if ($id) {
				raise_message(1);
			}else{
				raise_message(2);
			}
		}

		if (is_error_message() || empty($_POST["id"])) {
			header("Location: docs.php?id=" . (empty($id) ? $_POST["id"] : $id));
		}else{
			header("Location: docs.php");
		}
	}
}

function document_edit() {
	global $colors, $config;

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var("id"));
	/* ==================================================== */

	display_output_messages();

	/* control the upload size */
	ini_set("upload_max_filesize", "8M");

	if (!empty($_GET["id"])) {
		$document = db_fetch_row("select * from plugin_docs where id=" . $_GET["id"]);
		$id = $_GET['id'];
	}else{
		$_GET["id"] = 0;
		$id = 0;
		$document = array('title' => '', 'data' => '', 'type' => 0);
	}

	if ($document["type"] == 0) {
		print "<script type='text/javascript' src='jscripts/tiny_mce/tiny_mce.js'></script>\n";
		print "<script type='text/javascript' src='mceprefs.js'></script>\n";
	}

	print "<form action='" . $config['url_path'] . "plugins/docs/docs.php' method='POST' name='docs' enctype='multipart/form-data'>\n";

	html_start_box("<strong>Document Editor</strong>", "100%", $colors["header"], "3", "center", "");
	print "<tr><td><table>\n";

	print "<tr><td width='75'><b>Title:</b></td>\n";
	print "<td colspan='2'><input id='title' type='text' name='title' maxlength='255' size='100' value='" . htmlentities($document['title'], ENT_QUOTES) . "'>\n";
	print "<input type='submit' value='Save/Upload'>&nbsp;<input type='button' value='Cancel' onClick='returnTo(\"docs.php\")'></td></tr>\n";

	if ($document["type"] != 0) {
		print "<tr><td width='75'><b>File Name:</b></td>\n";
		print "<td><input size='100' type='text' readonly value='" . $document['link'] . "'></td>\n";
		print "<td width='100'><a href='" . $config["url_path"] . "plugins/docs/docs_iview.php?id=" . $document["id"] . "'><strong>Download File</strong></a></td></tr>\n";
		print "</table><table>\n";
	}

	print "<tr><td width='75'><b>New File:</b></td>\n";
	print "<td colspan='2'><input type='file' size='100' id='upload' name='upload'></td></tr>\n";

	if ($document["type"] == 0) {
		print "</table><table width='100%'><tr><td id='container' colspan='2'>\n";
		print "<textarea style='width:100%; height:100%' id='data' name='data'>" . base64_decode($document["data"]) . "</textarea>\n";
		print "</td></tr></table>\n";
	}else{
		print "</td></tr></table>\n";
	}

	print "<span><input type='hidden' name='id' value='$id'></span>\n";
	print "<span><input type='hidden' name='action' value='save'></span>\n";

	html_end_box();

	print "</form>\n";

	?>
	<script type="text/javascript">
	<!--
	function returnTo(location) {
		document.location = location;
	}
	-->
	</script>
	<?php
}

/* docs_save_button - draws a (save|create) and cancel button at the bottom of
     an html edit form
   @arg $cancel_url - the url to go to when the user clicks 'cancel'
   @arg $force_type - if specified, will force the 'action' button to be either
     'save' or 'create'. otherwise this field should be properly auto-detected */
function docs_save_button($cancel_url, $force_type = "", $key_field = "id") {
	global $config;

	if (empty($force_type)) {
		if (empty($_GET[$key_field])) {
			$value = "Create";
		}else{
			$value = "Save";
		}
	}elseif ($force_type == "save") {
		$value = "Save";
	}elseif ($force_type == "create") {
		$value = "Create";
	}
	?>
	<script type="text/javascript">
	<!--
	function returnTo(location) {
		document.location = location;
	}
	-->
	</script>
	<table align='center' width='100%' style='background-color: #ffffff; border: 1px solid #bbbbbb;'>
		<tr>
			<td bgcolor="#f5f5f5" align="right">
				<input type='hidden' name='action' value='save'>
				<input type='button' onClick='returnTo("<?php print $cancel_url;?>")' value='Cancel'>
				<input type='submit' value='<?php print $value;?>'>
			</td>
		</tr>
	</table>
	</form>
	<?php
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

	display_output_messages();

	$display_text = array(
		"title" => array("Title", "ASC"),
		"type" => array("Type", "ASC"),
		"mimetype" => array("MimeType", "ASC"),
		"creator" => array("Creator", "ASC"),
		"updated" => array("Last Updated", "ASC"),
		"updatedby" => array("Updated By", "ASC"),);

	html_start_box("<strong>Documents</strong>", "100%", $colors["header"], "3", "center", "docs.php?action=edit");

	html_header_sort_checkbox($display_text, $_REQUEST["sort_column"], $_REQUEST["sort_direction"]);

	$documents = db_fetch_assoc("SELECT * FROM plugin_docs ORDER BY " . $_REQUEST['sort_column'] . " " . $_REQUEST['sort_direction']);

	$results = db_fetch_assoc("SELECT id, username FROM user_auth");
	$users = array();
	foreach ($results as $user) {
		$users[$user['id']] = $user['username'];
	}

	if (sizeof($documents)) {
		foreach ($documents as $document) {
			form_alternate_row_color($colors["alternate"], $colors["light"], $document['id'], 'line' . $document['id']);
			form_selectable_cell("<a class='linkEditMain' href='docs.php?action=edit&id=" . $document["id"] . "'>" . $document['title'] . "</a>", $document["id"]);
			form_selectable_cell(($document["type"] == 0 ? "HTML":"Document"), $document["id"]);
			form_selectable_cell(($document["type"] == 0 ? "HTML":$document["mimetype"]), $document["id"]);
			form_selectable_cell((isset($users[$document['creator']]) ? $users[$document['creator']] : "Unknown"), $document["id"]);
			form_selectable_cell(date("F j, Y, g:i a", $document['updated']), $document["id"]);
			form_selectable_cell((isset($users[$document['updatedby']]) ? $users[$document['updatedby']] : "Unknown"), $document["id"]);
			form_checkbox_cell($document["title"], $document["id"]);
			form_end_row();
		}
	}else{
		print "<tr><td><em>No Documents</em></td></tr>\n";
	}
	html_end_box(false);

	/* draw the dropdown containing a list of available actions for this form */
	draw_actions_dropdown($docs_actions);

	print "</form>\n";
}

?>
