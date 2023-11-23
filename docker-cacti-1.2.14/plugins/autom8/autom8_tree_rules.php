<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2006-2010 Reinhard Scheck aka gandalf                     |
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
 */

chdir('../../');
include("./include/auth.php");
include_once("./lib/data_query.php");

define("MAX_DISPLAY_PAGES", 21);

$autom8_tree_rules_actions = array(
AUTOM8_ACTION_TREE_DUPLICATE => "Duplicate",
AUTOM8_ACTION_TREE_ENABLE => "Enable",
AUTOM8_ACTION_TREE_DISABLE => "Disable",
AUTOM8_ACTION_TREE_DELETE => "Delete",
);

/* set default action */
if (!isset($_REQUEST["action"])) { $_REQUEST["action"] = ""; }

switch ($_REQUEST["action"]) {
	case 'save':
		autom8_tree_rules_form_save();

		break;
	case 'actions':
		autom8_tree_rules_form_actions();

		break;
	case 'item_movedown':
		autom8_tree_rules_item_movedown();

		header("Location: autom8_tree_rules.php?action=edit&id=" . $_GET["id"]);
		break;
	case 'item_moveup':
		autom8_tree_rules_item_moveup();

		header("Location: autom8_tree_rules.php?action=edit&id=" . $_GET["id"]);
		break;
	case 'item_remove':
		autom8_tree_rules_item_remove();

		header("Location: autom8_tree_rules.php?action=edit&id=" . $_GET["id"]);
		break;
	case 'item_edit':
		include_once($config['include_path'] . "/top_header.php");

		autom8_tree_rules_item_edit();

		include_once($config['include_path'] . "/bottom_footer.php");
		break;
	case 'remove':
		autom8_tree_rules_remove();

		header ("Location: autom8_tree_rules.php");
		break;
	case 'edit':
		include_once($config['include_path'] . "/top_header.php");

		autom8_tree_rules_edit();

		include_once($config['include_path'] . "/bottom_footer.php");
		break;
	default:
		include_once($config['include_path'] . "/top_header.php");

		autom8_tree_rules();

		include_once($config['include_path'] . "/bottom_footer.php");
		break;
}

/* --------------------------
 The Save Function
 -------------------------- */

function autom8_tree_rules_form_save() {

	if (isset($_POST["save_component_autom8_tree_rule"])) {

		/* ================= input validation ================= */
		input_validate_input_number(get_request_var_post("id"));
		/* ==================================================== */

		$save["id"] = $_POST["id"];
		$save["name"] = form_input_validate($_POST["name"], "name", "", true, 3);
		$save["tree_id"] = form_input_validate($_POST["tree_id"], "tree_id", "^[0-9]+$", false, 3);
		$save["tree_item_id"] = isset($_POST["tree_item_id"]) ? form_input_validate($_POST["tree_item_id"], "tree_item_id", "^[0-9]+$", false, 3) : 0;
		$save["leaf_type"] = (isset($_POST["leaf_type"])) ? form_input_validate($_POST["leaf_type"], "leaf_type", "^[0-9]+$", false, 3) : 0;
		$save["host_grouping_type"] = isset($_POST["host_grouping_type"]) ? form_input_validate($_POST["host_grouping_type"], "host_grouping_type", "^[0-9]+$", false, 3) : 0;
		$save["rra_id"] = isset($_POST["rra_id"]) ? form_input_validate($_POST["rra_id"], "rra_id", "^[0-9]+$", false, 3) : 0;
		$save["enabled"] = (isset($_POST["enabled"]) ? "on" : "");
		if (!is_error_message()) {
			$rule_id = sql_save($save, "plugin_autom8_tree_rules");

			if ($rule_id) {
				raise_message(1);
			}else{
				raise_message(2);
			}
		}

		header("Location: autom8_tree_rules.php?action=edit&id=" . (empty($rule_id) ? $_POST["id"] : $rule_id));

	}elseif (isset($_POST["save_component_autom8_match_item"])) {
		/* ================= input validation ================= */
		input_validate_input_number(get_request_var_post("id"));
		input_validate_input_number(get_request_var_post("item_id"));
		/* ==================================================== */
		$save = array();
		$save["id"] = form_input_validate($_POST["item_id"], "item_id", "^[0-9]+$", false, 3);
		$save["rule_id"] = form_input_validate($_POST["id"], "id", "^[0-9]+$", false, 3);
		$save["rule_type"] = AUTOM8_RULE_TYPE_TREE_MATCH;
		$save["sequence"] = form_input_validate($_POST["sequence"], "sequence", "^[0-9]+$", false, 3);
		$save["operation"] = form_input_validate($_POST["operation"], "operation", "^[-0-9]+$", true, 3);
		$save["field"] = form_input_validate(((isset($_POST["field"]) && $_POST["field"] != "0") ? $_POST["field"] : ""), "field", "", true, 3);
		$save["operator"] = form_input_validate((isset($_POST["operator"]) ? $_POST["operator"] : ""), "operator", "^[0-9]+$", true, 3);
		$save["pattern"] = form_input_validate((isset($_POST["pattern"]) ? $_POST["pattern"] : ""), "pattern", "", true, 3);

		if (!is_error_message()) {
			$item_id = sql_save($save, "plugin_autom8_match_rule_items");

			if ($item_id) {
				raise_message(1);
			}else{
				raise_message(2);
			}
		}

		if (is_error_message()) {
			header("Location: autom8_tree_rules.php?action=item_edit&id=" . $_POST["id"] . "&item_id=" . (empty($item_id) ? $_POST["item_id"] : $item_id) . "&rule_type=" . AUTOM8_RULE_TYPE_TREE_MATCH);
		}else{
			header("Location: autom8_tree_rules.php?action=edit&id=" . $_POST["id"] . "&rule_type=" . AUTOM8_RULE_TYPE_TREE_MATCH);
		}
	}elseif (isset($_POST["save_component_autom8_tree_rule_item"])) {

		/* ================= input validation ================= */
		input_validate_input_number(get_request_var_post("id"));
		input_validate_input_number(get_request_var_post("item_id"));
		/* ==================================================== */
		unset($save);
		$save["id"] = form_input_validate($_POST["item_id"], "item_id", "^[0-9]+$", false, 3);
		$save["rule_id"] = form_input_validate($_POST["id"], "id", "^[0-9]+$", false, 3);
		$save["sequence"] = form_input_validate($_POST["sequence"], "sequence", "^[0-9]+$", false, 3);
		$save["field"] = form_input_validate((isset($_POST["field"]) ? $_POST["field"] : ""), "field", "", true, 3);
		$save["sort_type"] = form_input_validate($_POST["sort_type"], "sort_type", "^[0-9]+$", false, 3);
		$save["propagate_changes"] = (isset($_POST["propagate_changes"]) ? "on" : "");
		$save["search_pattern"] = isset($_POST["search_pattern"]) ? form_input_validate($_POST["search_pattern"], "search_pattern", "", false, 3) : "";
		$save["replace_pattern"] = isset($_POST["replace_pattern"]) ? form_input_validate($_POST["replace_pattern"], "replace_pattern", "", true, 3) : "";

		if (!is_error_message()) {
			$autom8_graph_rule_item_id = sql_save($save, "plugin_autom8_tree_rule_items");

			if ($autom8_graph_rule_item_id) {
				raise_message(1);
			}else{
				raise_message(2);
			}
		}

		if (is_error_message()) {
			header("Location: autom8_tree_rules.php?action=item_edit&id=" . $_POST["id"] . "&item_id=" . (empty($autom8_graph_rule_item_id) ? $_POST["item_id"] : $autom8_graph_rule_item_id) . "&rule_type=" . AUTOM8_RULE_TYPE_TREE_ACTION);
		}else{
			header("Location: autom8_tree_rules.php?action=edit&id=" . $_POST["id"] . "&rule_type=" . AUTOM8_RULE_TYPE_TREE_ACTION);
		}
	} else {
		raise_message(2);
		header("Location: autom8_tree_rules.php");
	}
}

/* ------------------------
 The "actions" function
 ------------------------ */

function autom8_tree_rules_form_actions() {
	global $colors, $autom8_tree_rules_actions;
	global $config;
	include_once($config["base_path"]."/plugins/autom8/autom8_utilities.php");

	/* if we are to save this form, instead of display it */
	if (isset($_POST["selected_items"])) {
		$selected_items = unserialize(stripslashes($_POST["selected_items"]));

		if ($_POST["drp_action"] == AUTOM8_ACTION_TREE_DELETE) { /* delete */
			autom8_log("form_actions delete: " . serialize($selected_items), true, "AUTOM8 TRACE", POLLER_VERBOSITY_MEDIUM);
			db_execute("delete from plugin_autom8_tree_rules where " . array_to_sql_or($selected_items, "id"));
			db_execute("delete from plugin_autom8_tree_rule_items where " . array_to_sql_or($selected_items, "rule_id"));
			db_execute("delete from plugin_autom8_match_rule_items where " . array_to_sql_or($selected_items, "rule_id"));

		}elseif ($_POST["drp_action"] == AUTOM8_ACTION_TREE_DUPLICATE) { /* duplicate */
			for ($i=0;($i<count($selected_items));$i++) {
				/* ================= input validation ================= */
				input_validate_input_number($selected_items[$i]);
				/* ==================================================== */
				autom8_log("form_actions duplicate: " . $selected_items[$i], true, "AUTOM8 TRACE", POLLER_VERBOSITY_MEDIUM);
				duplicate_autom8_tree_rules($selected_items[$i], $_POST["name_format"]);
			}
		}elseif ($_POST["drp_action"] == AUTOM8_ACTION_TREE_ENABLE) { /* enable */
			for ($i=0;($i<count($selected_items));$i++) {
				/* ================= input validation ================= */
				input_validate_input_number($selected_items[$i]);
				/* ==================================================== */
				autom8_log("form_actions enable: " . $selected_items[$i], true, "AUTOM8 TRACE", POLLER_VERBOSITY_MEDIUM);
				db_execute("UPDATE plugin_autom8_tree_rules SET enabled='on' where id=" . $selected_items[$i]);
			}
		}elseif ($_POST["drp_action"] == AUTOM8_ACTION_TREE_DISABLE) { /* disable */
			for ($i=0;($i<count($selected_items));$i++) {
				/* ================= input validation ================= */
				input_validate_input_number($selected_items[$i]);
				/* ==================================================== */
				autom8_log("form_actions disable: " . $selected_items[$i], true, "AUTOM8 TRACE", POLLER_VERBOSITY_MEDIUM);
				db_execute("UPDATE plugin_autom8_tree_rules SET enabled='' where id=" . $selected_items[$i]);
				}
		}

		header("Location: autom8_tree_rules.php");
		exit;
	}

	/* setup some variables */
	$autom8_tree_rules_list = ""; $i = 0;
	/* loop through each of the graphs selected on the previous page and get more info about them */
	while (list($var,$val) = each($_POST)) {
		if (preg_match("/^chk_([0-9]+)$/", $var, $matches)) {
			/* ================= input validation ================= */
			input_validate_input_number($matches[1]);
			/* ==================================================== */
			$autom8_tree_rules_list .= "<li>" . db_fetch_cell("select name from plugin_autom8_tree_rules where id=" . $matches[1]) . "</li>";
			$autom8_tree_rules_array[] = $matches[1];
		}
	}

	include_once("./include/top_header.php");

	print "<form name='autom8_tree_rules_action' action='autom8_tree_rules.php' method='post'>";

	html_start_box("<strong>" . $autom8_tree_rules_actions{$_POST["drp_action"]} . "</strong>", "100%", $colors["header_panel"], "3", "center", "");

	if ($_POST["drp_action"] == AUTOM8_ACTION_TREE_DELETE) { /* delete */
		print "	<tr>
			<td class='textArea' bgcolor='#" . $colors["form_alternate1"]. "'>
				<p>Are you sure you want to delete the following Rules?</p>
				<p><ul>$autom8_tree_rules_list</ul></p>
			</td>
		</tr>\n
		";
	}elseif ($_POST["drp_action"] == AUTOM8_ACTION_TREE_DUPLICATE) { /* duplicate */
		print "	<tr>
			<td class='textArea' bgcolor='#" . $colors["form_alternate1"]. "'>
				<p>When you click save, the following Rules will be duplicated. You can
				optionally change the title format for the new Rules.</p>
				<p><ul>$autom8_tree_rules_list</ul></p>
				<p><strong>Title Format:</strong><br>"; form_text_box("name_format", "<rule_name> (1)", "", "255", "30", "text"); print "</p>
			</td>
		</tr>\n
		";
	}elseif ($_POST["drp_action"] == AUTOM8_ACTION_TREE_ENABLE) { /* enable */
		print "	<tr>
			<td class='textArea' bgcolor='#" . $colors["form_alternate1"]. "'>
				<p>When you click save, the following Rules will be enabled.</p>
				<p><ul>$autom8_tree_rules_list</ul></p>
				<p><strong>Make sure, that those rules have successfully been tested!</strong></p>
			</td>
		</tr>\n
		";
	}elseif ($_POST["drp_action"] == AUTOM8_ACTION_TREE_DISABLE) { /* disable */
		print "	<tr>
			<td class='textArea' bgcolor='#" . $colors["form_alternate1"]. "'>
				<p>When you click save, the following Rules will be disabled.</p>
				<p><ul>$autom8_tree_rules_list</ul></p>
			</td>
		</tr>\n
		";
	}

	if (!isset($autom8_tree_rules_array)) {
		print "<tr><td bgcolor='#" . $colors["form_alternate1"]. "'><span class='textError'>You must select at least one Rule.</span></td></tr>\n";
		$save_html = "<input type='button' value='Return' onClick='window.history.back()'>";
	}else {
		$save_html = "<input type='button' value='Return' onClick='window.history.back()'>&nbsp;<input type='submit' value='Apply' title='Apply requested action'>";
	}

	print "	<tr>
		<td align='right' bgcolor='#eaeaea'>
			<input type='hidden' name='action' value='actions'>
			<input type='hidden' name='selected_items' value='" . (isset($autom8_tree_rules_array) ? serialize($autom8_tree_rules_array) : '') . "'>
			<input type='hidden' name='drp_action' value='" . $_POST["drp_action"] . "'>
			$save_html
		</td>
	</tr>";

	html_end_box();

	include_once("./include/bottom_footer.php");
}

/* --------------------------
 Rule Item Functions
 -------------------------- */

function autom8_tree_rules_item_movedown() {
	/* ================= input validation ================= */
	input_validate_input_number(get_request_var("id"));
	input_validate_input_number(get_request_var("item_id"));
	input_validate_input_number(get_request_var("rule_type"));
	/* ==================================================== */

	if ($_GET["rule_type"] == AUTOM8_RULE_TYPE_TREE_MATCH) {
		move_item_down("plugin_autom8_match_rule_items", $_GET["item_id"], "rule_id=" . $_GET["id"] . " AND rule_type=" . $_GET["rule_type"]);
	} elseif ($_GET["rule_type"] == AUTOM8_RULE_TYPE_TREE_ACTION) {
		move_item_down("plugin_autom8_tree_rule_items", $_GET["item_id"], "rule_id=" . $_GET["id"]);
	}
}

function autom8_tree_rules_item_moveup() {
	/* ================= input validation ================= */
	input_validate_input_number(get_request_var("id"));
	input_validate_input_number(get_request_var("item_id"));
	input_validate_input_number(get_request_var("rule_type"));
	/* ==================================================== */

	if ($_GET["rule_type"] == AUTOM8_RULE_TYPE_TREE_MATCH) {
		move_item_up("plugin_autom8_match_rule_items", $_GET["item_id"], "rule_id=" . $_GET["id"] . " AND rule_type=" . $_GET["rule_type"]);
	} elseif ($_GET["rule_type"] == AUTOM8_RULE_TYPE_TREE_ACTION) {
		move_item_up("plugin_autom8_tree_rule_items", $_GET["item_id"], "rule_id=" . $_GET["id"]);
	}
}

function autom8_tree_rules_item_remove() {
	/* ================= input validation ================= */
	input_validate_input_number(get_request_var("item_id"));
	input_validate_input_number(get_request_var("rule_type"));
	/* ==================================================== */

	if ($_GET["rule_type"] == AUTOM8_RULE_TYPE_TREE_MATCH) {
		db_execute("delete from plugin_autom8_match_rule_items where id=" . $_GET["item_id"]);
	} elseif ($_GET["rule_type"] == AUTOM8_RULE_TYPE_TREE_ACTION) {
		db_execute("delete from plugin_autom8_tree_rule_items where id=" . $_GET["item_id"]);
	}


}


function autom8_tree_rules_item_edit() {
	global $config;
	include_once($config["base_path"]."/plugins/autom8/autom8_utilities.php");

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var("id"));
	input_validate_input_number(get_request_var("item_id"));
	input_validate_input_number(get_request_var("rule_type"));
	/* ==================================================== */


	/* handle show_trees mode */
	if (isset($_GET["show_trees"])) {
		if ($_GET["show_trees"] == "0") {
			kill_session_var("autom8_tree_rules_show_trees");
		}elseif ($_GET["show_trees"] == "1") {
			$_SESSION["autom8_tree_rules_show_trees"] = true;
		}
	}

	if (!empty($_GET["rule_type"]) && !empty($_GET["item_id"])) {
		if ($_GET["rule_type"] == AUTOM8_RULE_TYPE_TREE_ACTION) {
		$item = db_fetch_row("SELECT * " .
						"FROM plugin_autom8_tree_rule_items " .
						"WHERE id=" . $_GET["item_id"]);
			if ($item["field"] != AUTOM8_TREE_ITEM_TYPE_STRING) {
				?>
<table width="100%" align="center">
	<tr>
		<td class="textInfo" align="right" valign="top"><span
			style="color: #c16921;">*<a
			href='<?php print htmlspecialchars("autom8_tree_rules.php?action=item_edit&id=" . (isset($_GET["id"]) ? $_GET["id"] : 0) . "&item_id=" . (isset($_GET["item_id"]) ? $_GET["item_id"] : 0) . "&rule_type=" . (isset($_GET["rule_type"]) ? $_GET["rule_type"] : 0) ."&show_trees=") . (isset($_SESSION["autom8_tree_rules_show_trees"]) ? "0" : "1");?>'><strong><?php print (isset($_SESSION["autom8_tree_rules_show_trees"]) ? "Don't Show" : "Show");?></strong>
		Created Trees.</a></span><br>
		</td>
	</tr>
</table>
<br>
				<?php
			}
		}
	}

	global_item_edit($_GET["id"], (isset($_GET["item_id"]) ? $_GET["item_id"] : ""), $_GET["rule_type"]);

	form_hidden_box("rule_type", $_GET["rule_type"], $_GET["rule_type"]);
	form_hidden_box("id", (isset($_GET["id"]) ? $_GET["id"] : "0"), "");
	form_hidden_box("item_id", (isset($_GET["item_id"]) ? $_GET["item_id"] : "0"), "");
	if($_GET["rule_type"] == AUTOM8_RULE_TYPE_TREE_MATCH) {
		form_hidden_box("save_component_autom8_match_item", "1", "");
	} else {
		form_hidden_box("save_component_autom8_tree_rule_item", "1", "");
	}
	form_save_button(htmlspecialchars("autom8_tree_rules.php?action=edit&id=" . $_GET["id"] . "&rule_type=". $_GET["rule_type"]));
	print "<br>";

	/* display list of matching trees */
	if (!empty($_GET["rule_type"]) && !empty($_GET["item_id"])) {
		if ($_GET["rule_type"] == AUTOM8_RULE_TYPE_TREE_ACTION) {
			if (isset($_SESSION["autom8_tree_rules_show_trees"]) && ($item["field"] != AUTOM8_TREE_ITEM_TYPE_STRING)) {
				if ($_SESSION["autom8_tree_rules_show_trees"]) {
					display_matching_trees($_GET["id"], AUTOM8_RULE_TYPE_TREE_ACTION, $item, basename($_SERVER["PHP_SELF"]) . "?action=item_edit&id=" . $_GET["id"]. "&item_id=" . $_GET["item_id"] . "&rule_type=" .$_GET["rule_type"]);
				}
			}
		}
	}

	//Now we need some javascript to make it dynamic
?>
<script type="text/javascript">

applyHeaderChange();
toggle_operation();
toggle_operator();

function applyHeaderChange() {
	if (document.getElementById('rule_type').value == '<?php print AUTOM8_RULE_TYPE_TREE_ACTION;?>') {
		if (document.getElementById('field').value == '<?php print AUTOM8_TREE_ITEM_TYPE_STRING;?>') {
			document.getElementById('replace_pattern').value = '';
			document.getElementById('replace_pattern').disabled='disabled';
		} else {
			document.getElementById('replace_pattern').disabled='';
		}
	}
}

function toggle_operation() {
	// right bracket ")" does not come with a field
	if (document.getElementById('operation').value == '<?php print AUTOM8_OPER_RIGHT_BRACKET;?>') {
		//alert("Sequence is '" + document.getElementById('sequence').value + "'");
		document.getElementById('field').value = '';
		document.getElementById('field').disabled='disabled';
		document.getElementById('operator').value = 0;
		document.getElementById('operator').disabled='disabled';
		document.getElementById('pattern').value = '';
		document.getElementById('pattern').disabled='disabled';
	} else {
		document.getElementById('field').disabled='';
		document.getElementById('operator').disabled='';
		document.getElementById('pattern').disabled='';
	}
}

function toggle_operator() {
	// if operator is not "binary", disable the "field" for matching strings
	if (document.getElementById('operator').value == '<?php print AUTOM8_OPER_RIGHT_BRACKET;?>') {
		//alert("Sequence is '" + document.getElementById('sequence').value + "'");
	} else {
	}
}
</script>
<?php
}

/* ---------------------
 Rule Functions
 --------------------- */

function autom8_tree_rules_remove() {
	/* ================= input validation ================= */
	input_validate_input_number(get_request_var("id"));
	/* ==================================================== */

	if ((read_config_option("deletion_verification") == "on") && (!isset($_GET["confirm"]))) {
		include("./include/top_header.php");
		form_confirm("Are You Sure?", "Are you sure you want to delete the Rule <strong>'" . db_fetch_cell("select name from autom8_tree_rules where id=" . $_GET["id"]) . "'</strong>?", "autom8_tree_rules.php", "autom8_tree_rules.php?action=remove&id=" . $_GET["id"]);
		include("./include/bottom_footer.php");
		exit;
	}

	if ((read_config_option("deletion_verification") == "") || (isset($_GET["confirm"]))) {
		db_execute("DELETE FROM plugin_autom8_match_rule_items " .
					"WHERE rule_id=" . $_GET["id"] .
					" AND rule_type=" . AUTOM8_RULE_TYPE_TREE_MATCH);
		db_execute("DELETE FROM plugin_autom8_tree_rule_items " .
					"WHERE rule_id=" . $_GET["id"]);
		db_execute("delete from plugin_autom8_tree_rules where id=" . $_GET["id"]);
	}
}

function autom8_tree_rules_edit() {
	global $colors, $config;
	global $fields_autom8_tree_rules_edit1, $fields_autom8_tree_rules_edit2, $fields_autom8_tree_rules_edit3;
	include_once($config["base_path"]."/plugins/autom8/autom8_utilities.php");
	include_once($config["base_path"]."/lib/html_tree.php");

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var_request("id"));
	input_validate_input_number(get_request_var_request("tree_id"));
	input_validate_input_number(get_request_var_request("leaf_type"));
	input_validate_input_number(get_request_var_request("host_grouping_type"));
	input_validate_input_number(get_request_var_request("rra_id"));
	input_validate_input_number(get_request_var_request("tree_item_id"));
	/* ==================================================== */

	/* clean up rule name */
	if (isset($_REQUEST["name"])) {
		$_REQUEST["name"] = sanitize_search_string(get_request_var_request("name"));
	}

	/* remember these search fields in session vars so we don't have to keep passing them around */
	load_current_session_value("tree_rule_rows", "sess_autom8_tree_rule_rows", read_config_option("num_rows_data_query"));


	/* handle show_hosts mode */
	if (isset($_GET["show_hosts"])) {
		if ($_GET["show_hosts"] == "0") {
			kill_session_var("autom8_tree_rules_show_objects");
		}elseif ($_GET["show_hosts"] == "1") {
			$_SESSION["autom8_tree_rules_show_objects"] = true;
		}
	}

	if (!empty($_GET["id"])) {
		?>
<table width="100%" align="center">
	<tr>
		<td class="textInfo" align="right" valign="top"><span
			style="color: #c16921;">*<a
			href='<?php print htmlspecialchars("autom8_tree_rules.php?action=edit&id=" . (isset($_GET["id"]) ? $_GET["id"] : 0) . "&show_hosts=") . (isset($_SESSION["autom8_tree_rules_show_objects"]) ? "0" : "1");?>'><strong><?php print (isset($_SESSION["autom8_tree_rules_show_objects"]) ? "Don't Show" : "Show");?></strong>
		Eligible Objects.</a></span><br>
		</td>
	</tr>
</table>
<br>
		<?php
	}

	/*
	 * display the rule -------------------------------------------------------------------------------------
	 */
	$rule = array();
	if (!empty($_GET["id"])) {
		$rule = db_fetch_row("SELECT * FROM plugin_autom8_tree_rules where id=" . $_GET["id"]);
		$header_label = "[edit: " . $rule["name"] . "]";
	}else{
		$header_label = "[new]";
	}
	/* if creating a new rule, use all fields that have already been entered on page reload */
	if (isset($_REQUEST["name"])) {$rule["name"] = $_REQUEST["name"];}
	if (isset($_REQUEST["tree_id"])) {$rule["tree_id"] = $_REQUEST["tree_id"];}
	if (isset($_REQUEST["leaf_type"])) {$rule["leaf_type"] = $_REQUEST["leaf_type"];}
	if (isset($_REQUEST["host_grouping_type"])) {$rule["host_grouping_type"] = $_REQUEST["host_grouping_type"];}
	if (isset($_REQUEST["rra_id"])) {$rule["rra_id"] = $_REQUEST["rra_id"];}
	if (isset($_REQUEST["tree_item_id"])) {$rule["tree_item_id"] = $_REQUEST["tree_item_id"];}

	print "<form method='post' action='autom8_tree_rules.php' name='form_autom8_tree_rule_edit'>";
	html_start_box("<strong>Tree Rule Selection</strong> $header_label", "100%", $colors["header"], "3", "center", "");
	#print "<pre>"; print_r($_POST); print_r($_GET); print_r($_REQUEST); print "</pre>";

	if (!empty($_GET["id"])) {
		/* display whole rule */
		$form_array = $fields_autom8_tree_rules_edit1 + $fields_autom8_tree_rules_edit2 + $fields_autom8_tree_rules_edit3;
	} else {
		/* display first part of rule only and request user to proceed */
		$form_array = $fields_autom8_tree_rules_edit1;
	}

	draw_edit_form(array(
		"config" => array("no_form_tag" => true),
		"fields" => inject_form_variables($form_array, (isset($rule) ? $rule : array()))
	));

	html_end_box();
	form_hidden_box("id", (isset($rule["id"]) ? $rule["id"] : "0"), "");
	form_hidden_box("item_id", (isset($rule["item_id"]) ? $rule["item_id"] : "0"), "");
	form_hidden_box("save_component_autom8_tree_rule", "1", "");

	/*
	 * display the rule items -------------------------------------------------------------------------------
	 */
	if (!empty($rule["id"])) {
		# display tree rules for host match
		display_match_rule_items("Rule Items => Eligible Objects",
			$rule["id"],
			AUTOM8_RULE_TYPE_TREE_MATCH,
			basename($_SERVER["PHP_SELF"]));

		# fetch tree action rules
		display_tree_rule_items("Rule Items => Create Tree",
			$rule["id"],
			$rule["leaf_type"],
			AUTOM8_RULE_TYPE_TREE_ACTION,
			basename($_SERVER["PHP_SELF"]));
	}

	form_save_button("autom8_tree_rules.php");
	print "<br>";

	if (!empty($rule["id"])) {
		/* display list of matching hosts */
		if (isset($_SESSION["autom8_tree_rules_show_objects"])) {
			if ($_SESSION["autom8_tree_rules_show_objects"]) {
				if ($rule["leaf_type"] == TREE_ITEM_TYPE_HOST) {
					display_matching_hosts($rule, AUTOM8_RULE_TYPE_TREE_MATCH, basename($_SERVER["PHP_SELF"]) . "?action=edit&id=" . $_GET["id"]);
				} elseif ($rule["leaf_type"] == TREE_ITEM_TYPE_GRAPH) {
					display_matching_graphs($rule, AUTOM8_RULE_TYPE_TREE_MATCH, basename($_SERVER["PHP_SELF"]) . "?action=edit&id=" . $_GET["id"]);
				}
			}
		}
	}

	?>
<script type="text/javascript">
	<!--
	applyItemTypeChange(document.form_autom8_tree_rule_edit);

	function applyTreeChange(objForm) {
		strURL = '?action=edit&id=' + objForm.id.value;
		if (typeof(objForm.name) 				!= 'undefined') {strURL = strURL + '&name=' + objForm.name.value;}
		if (typeof(objForm.tree_id) 			!= 'undefined') {strURL = strURL + '&tree_id=' + objForm.tree_id.value;}
		if (typeof(objForm.tree_item_id) 		!= 'undefined') {strURL = strURL + '&tree_item_id=' + objForm.tree_item_id.value;}
		if (typeof(objForm.leaf_type) 			!= 'undefined') {strURL = strURL + '&leaf_type=' + objForm.leaf_type.value;}
		if (typeof(objForm.host_grouping_type) 	!= 'undefined') {strURL = strURL + '&host_grouping_type=' + objForm.host_grouping_type.value;}
		if (typeof(objForm.rra_id) 				!= 'undefined') {strURL = strURL + '&rra_id=' + objForm.rra_id.value;}
		//strURL = strURL + '&graph_rule_rows=' + objForm.graph_rule_rows.value;
		//alert('Url: ' + strURL);
		document.location = strURL;
	}

	function applyItemTypeChange(objForm) {
		if (document.getElementById('leaf_type').value == '<?php print TREE_ITEM_TYPE_HOST;?>') {
			document.getElementById('host_grouping_type').value = '';
			document.getElementById('host_grouping_type').disabled='';
			document.getElementById('rra_id').value = '';
			document.getElementById('rra_id').disabled='disabled';
		} else if (document.getElementById('leaf_type').value == '<?php print TREE_ITEM_TYPE_GRAPH;?>') {
			document.getElementById('host_grouping_type').value = '';
			document.getElementById('host_grouping_type').disabled='disabled';
			document.getElementById('rra_id').value = '';
			document.getElementById('rra_id').disabled='';
		}

	}
	-->
</script>
	<?php
}

function autom8_tree_rules() {
	global $colors, $autom8_tree_rules_actions, $config, $item_rows;
	global $autom8_tree_item_types, $host_group_types;

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var_request("page"));
	input_validate_input_number(get_request_var_request("rule_status"));
	input_validate_input_number(get_request_var_request("rule_rows"));
	/* ==================================================== */

	/* clean up search string */
	if (isset($_REQUEST["filter"])) {
		$_REQUEST["filter"] = sanitize_search_string(get_request_var("filter"));
	}

	/* clean up sort_column string */
	if (isset($_REQUEST["sort_column"])) {
		$_REQUEST["sort_column"] = sanitize_search_string(get_request_var("sort_column"));
	}

	/* clean up sort_direction string */
	if (isset($_REQUEST["sort_direction"])) {
		$_REQUEST["sort_direction"] = sanitize_search_string(get_request_var("sort_direction"));
	}

	/* if the user pushed the 'clear' button */
	if (isset($_REQUEST["clear_x"])) {
		kill_session_var("sess_autom8_tree_rules_current_page");
		kill_session_var("sess_autom8_tree_rules_filter");
		kill_session_var("sess_autom8_tree_rules_sort_column");
		kill_session_var("sess_autom8_tree_rules_sort_direction");
		kill_session_var("sess_autom8_tree_rules_status");
		kill_session_var("sess_autom8_tree_rules_rows");

		unset($_REQUEST["page"]);
		unset($_REQUEST["filter"]);
		unset($_REQUEST["sort_column"]);
		unset($_REQUEST["sort_direction"]);
		unset($_REQUEST["rule_status"]);
		unset($_REQUEST["rule_rows"]);

	}

	if ((!empty($_SESSION["sess_autom8_tree_rules_status"])) && (!empty($_REQUEST["rule_status"]))) {
		if ($_SESSION["sess_autom8_tree_rules_status"] != $_REQUEST["rule_status"]) {
			$_REQUEST["page"] = 1;
		}
	}

	/* remember these search fields in session vars so we don't have to keep passing them around */
	load_current_session_value("page", "sess_autom8_tree_rules_current_page", "1");
	load_current_session_value("filter", "sess_autom8_tree_rules_filter", "");
	load_current_session_value("sort_column", "sess_autom8_tree_rules_sort_column", "name");
	load_current_session_value("sort_direction", "sess_autom8_tree_rules_sort_direction", "ASC");
	load_current_session_value("rule_status", "sess_autom8_tree_rules_status", "-1");
	load_current_session_value("rule_rows", "sess_autom8_tree_rules_rows", read_config_option("num_rows_device"));

	/* if the number of rows is -1, set it to the default */
	if ($_REQUEST["rule_rows"] == -1) {
		$_REQUEST["rule_rows"] = read_config_option("num_rows_device");
	}

	print ('<form name="form_autom8_tree_rules" method="post" action="autom8_tree_rules.php">');

	html_start_box("<strong>Tree Rules</strong>", "100%", $colors["header"], "3", "center", "autom8_tree_rules.php?action=edit");
	#print "<pre>"; print_r($_POST); print_r($_GET); print_r($_REQUEST); print "</pre>";

	$filter_html = '<tr bgcolor=' . $colors["panel"] . '>
						<td>
						<table width="100%" cellpadding="0" cellspacing="0">
							<tr>
								<td nowrap style="white-space: nowrap;" width="50">Search:&nbsp;</td>
								<td width="1"><input type="text" name="filter" size="40"
									value=' . get_request_var_request("filter") . '></td>
							<td nowrap style="white-space: nowrap;" width="50">
								&nbsp;Status:&nbsp;
							</td>
							<td width="1">
								<select name="rule_status" onChange="applyViewRuleFilterChange(document.form_autom8_tree_rules)">
									<option value="-1"';
	if (get_request_var_request("rule_status") == "-1") {
		$filter_html .= 'selected';
	}
	$filter_html .= '>Any</option>					<option value="-2"';
	if (get_request_var_request("rule_status") == "-2") {
		$filter_html .= 'selected';
	}
	$filter_html .= '>Enabled</option>				<option value="-3"';
	if (get_request_var_request("rule_status") == "-3") {
		$filter_html .= 'selected';
	}
	$filter_html .= '>Disabled</option>';
	$filter_html .= '					</select>
							</td>
							<td nowrap style="white-space: nowrap;" width="50">
								&nbsp;Rows per Page:&nbsp;
							</td>
							<td width="1">
								<select name="rule_rows" onChange="applyViewRuleFilterChange(document.form_autom8_graph_rules)">
								<option value="-1"';
	if (get_request_var_request("rule_rows") == "-1") {
		$filter_html .= 'selected';
	}
	$filter_html .= '>Default</option>';
	if (sizeof($item_rows) > 0) {
		foreach ($item_rows as $key => $value) {
			$filter_html .= "<option value='" . $key . "'";
			if (get_request_var_request("rule_rows") == $key) {
				$filter_html .= " selected";
			}
			$filter_html .= ">" . $value . "</option>\n";
		}
	}
	$filter_html .= '					</select>
							</td>
							<td nowrap style="white-space: nowrap;">&nbsp;<input type="submit"
								value="Go"> <input type="submit"
								name="clear_x" value="Clear"></td>
						</tr>
					</table>
					</td>
					<td><input type="hidden" name="page" value="1"></td>
				</tr>';
	print $filter_html;

	?>
	<script type="text/javascript">
	<!--

	function applyViewRuleFilterChange(objForm) {
		strURL = 'autom8_tree_rules.php?rule_status=' + objForm.rule_status.value;
		strURL = strURL + '&rule_rows=' + objForm.rule_rows.value;
		strURL = strURL + '&filter=' + objForm.filter.value;
		document.location = strURL;
	}

	-->
	</script>
	<?php

	html_end_box();

	print "</form>\n";

	/* form the 'where' clause for our main sql query */
	if (strlen(get_request_var_request("filter"))) {
		$sql_where = "WHERE (plugin_autom8_tree_rules.name LIKE '%%" . get_request_var_request("filter") . "%%')";
	}else{
		$sql_where = "";
	}

	if (get_request_var_request("rule_status") == "-1") {
		/* Show all items */
	}elseif (get_request_var_request("rule_status") == "-2") {
		$sql_where .= (strlen($sql_where) ? " and plugin_autom8_tree_rules.enabled='on'" : "where .plugin_autom8_tree_rules.enabled='on'");
	}elseif (get_request_var_request("rule_status") == "-3") {
		$sql_where .= (strlen($sql_where) ? " and plugin_autom8_tree_rules.enabled=''" : "where plugin_autom8_tree_rules.enabled=''");
	}

	print "<form name='chk' method='post' action='autom8_tree_rules.php'>\n";
	html_start_box("", "100%", $colors["header"], "3", "center", "");

	$total_rows = db_fetch_cell("SELECT " .
		"COUNT(plugin_autom8_tree_rules.id)" .
		"FROM plugin_autom8_tree_rules " .
		"LEFT JOIN graph_tree ON (plugin_autom8_tree_rules.id = graph_tree.id) " .
		$sql_where);

	$autom8_tree_rules = db_fetch_assoc("SELECT " .
		"plugin_autom8_tree_rules.id, ".
		"plugin_autom8_tree_rules.name, " .
		"plugin_autom8_tree_rules.tree_id, " .
		"plugin_autom8_tree_rules.tree_item_id, " .
		"plugin_autom8_tree_rules.leaf_type, " .
		"plugin_autom8_tree_rules.host_grouping_type, " .
		"plugin_autom8_tree_rules.rra_id, " .
		"plugin_autom8_tree_rules.enabled, " .
		"graph_tree.name AS tree_name, " .
		"graph_tree_items.title AS subtree_name, " .
		"rra.name AS rra_name " .
		"FROM plugin_autom8_tree_rules " .
		"LEFT JOIN graph_tree ON (plugin_autom8_tree_rules.tree_id = graph_tree.id) " .
		"LEFT JOIN graph_tree_items ON (plugin_autom8_tree_rules.tree_item_id = graph_tree_items.id) " .
		"LEFT JOIN rra ON (plugin_autom8_tree_rules.rra_id = rra.id) " .
		$sql_where .
		" ORDER BY " . get_request_var_request("sort_column") . " " . get_request_var_request("sort_direction") .
		" LIMIT " . (get_request_var_request("rule_rows")*(get_request_var_request("page")-1)) . "," . get_request_var_request("rule_rows"));
	#print "<pre>"; print_r($autom8_tree_rules); print "</pre>";

	/* generate page list */
	$url_page_select = get_page_list(get_request_var_request("page"), MAX_DISPLAY_PAGES, get_request_var_request("rule_rows"), $total_rows, "autom8_tree_rules.php?filter=" . get_request_var_request("filter"));

	$nav = "<tr bgcolor='#" . $colors["header"] . "'>
			<td colspan='9'>
			<table width='100%' cellspacing='0' cellpadding='0' border='0'>
				<tr>
					<td align='left' class='textHeaderDark'>
						<strong>&lt;&lt; "; if (get_request_var_request("page") > 1) { $nav .= "<a class='linkOverDark' href='" . htmlspecialchars("autom8_tree_rules.php?filter=" . get_request_var_request("filter") . "&rule_status=" . get_request_var_request("rule_status") . "&page=" . (get_request_var_request("page")-1)) . "'>"; } $nav .= "Previous"; if (get_request_var_request("page") > 1) { $nav .= "</a>"; } $nav .= "</strong>
					</td>\n
					<td align='center' class='textHeaderDark'>
						Showing Rows " . ((get_request_var_request("rule_rows")*(get_request_var_request("page")-1))+1) . " to " . ((($total_rows < read_config_option("num_rows_device")) || ($total_rows < (get_request_var_request("rule_rows")*get_request_var_request("page")))) ? $total_rows : (get_request_var_request("rule_rows")*get_request_var_request("page"))) . " of $total_rows [$url_page_select]
					</td>\n
					<td align='right' class='textHeaderDark'>
						<strong>"; if ((get_request_var_request("page") * get_request_var_request("rule_rows")) < $total_rows) { $nav .= "<a class='linkOverDark' href='" . htmlspecialchars("autom8_tree_rules.php?filter=" . get_request_var_request("filter") . "&rule_status=" . get_request_var_request("rule_status") . "&page=" . (get_request_var_request("page")+1)) . "'>"; } $nav .= "Next"; if ((get_request_var_request("page") * get_request_var_request("rule_rows")) < $total_rows) { $nav .= "</a>"; } $nav .= " &gt;&gt;</strong>
					</td>\n
				</tr>
			</table>
			</td>
		</tr>\n";

	print $nav;

	$display_text = array(
		"name" 					=> array("Rule Title", "ASC"),
		"id" 					=> array("Id", "ASC"),
		"tree_name" 			=> array("Hook into Tree", "ASC"),
		"subtree_name"			=> array("at Subtree", "ASC"),
		"leaf_type"				=> array("this Type", "ASC"),
		"host_grouping_type"	=> array("using Grouping", "ASC"),
		"rra_id"				=> array("using Round Robin Archive", "ASC"),
		"enabled" 				=> array("Enabled", "ASC"));

	html_header_sort_checkbox($display_text, get_request_var_request("sort_column"), get_request_var_request("sort_direction"), false);

	$i = 0;
	if (sizeof($autom8_tree_rules) > 0) {
		foreach ($autom8_tree_rules as 	$autom8_tree_rule) {
			$tree_item_type_name	= ((empty($autom8_tree_rule["leaf_type"])) ? "<em>None</em>" : $autom8_tree_item_types{$autom8_tree_rule["leaf_type"]});
			$subtree_name	= ((empty($autom8_tree_rule["subtree_name"])) ? "<em>ROOT</em>" : $autom8_tree_rule["subtree_name"]);
			$tree_host_grouping_type = ((empty($host_group_types{$autom8_tree_rule["host_grouping_type"]})) ? "" : $host_group_types{$autom8_tree_rule["host_grouping_type"]});
			form_alternate_row_color($colors["alternate"], $colors["light"], $i, 'line' . $autom8_tree_rule["id"]); $i++;

			form_selectable_cell("<a class='linkEditMain' href='" . htmlspecialchars("autom8_tree_rules.php?action=edit&id=" . $autom8_tree_rule["id"] . "&page=1") . "' title='" . htmlspecialchars($autom8_tree_rule["name"]) . "'>" . ((get_request_var_request("filter") != "") ? eregi_replace("(" . preg_quote(get_request_var_request("filter")) . ")", "<span style='background-color: #F8D93D;'>\\1</span>", title_trim($autom8_tree_rule["name"], read_config_option("max_title_graph"))) : title_trim($autom8_tree_rule["name"], read_config_option("max_title_graph"))) . "</a>", $autom8_tree_rule["id"]);
			form_selectable_cell($autom8_tree_rule["id"], $autom8_tree_rule["id"]);
			form_selectable_cell($autom8_tree_rule["tree_name"], $autom8_tree_rule["id"]);
			form_selectable_cell($subtree_name, $autom8_tree_rule["id"]);
			form_selectable_cell($tree_item_type_name, $autom8_tree_rule["id"]);
			form_selectable_cell($tree_host_grouping_type, $autom8_tree_rule["id"]);
			form_selectable_cell($autom8_tree_rule["rra_name"], $autom8_tree_rule["id"]);
			form_selectable_cell($autom8_tree_rule["enabled"] ? "Enabled" : "Disabled", $autom8_tree_rule["id"]);
			form_checkbox_cell($autom8_tree_rule["name"], $autom8_tree_rule["id"]);

			form_end_row();
		}
		print $nav;
	}else{
		print "<tr><td><em>No Tree Rules</em></td></tr>\n";
	}
	html_end_box(false);

	/* draw the dropdown containing a list of available actions for this form */
	draw_actions_dropdown($autom8_tree_rules_actions);

	print "</form>\n";
}
?>
