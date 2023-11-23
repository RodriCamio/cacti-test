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

$autom8_graph_rules_actions = array(
AUTOM8_ACTION_GRAPH_DUPLICATE => "Duplicate",
AUTOM8_ACTION_GRAPH_ENABLE => "Enable",
AUTOM8_ACTION_GRAPH_DISABLE => "Disable",
AUTOM8_ACTION_GRAPH_DELETE => "Delete",
);

/* set default action */
if (!isset($_REQUEST["action"])) { $_REQUEST["action"] = ""; }

switch ($_REQUEST["action"]) {
	case 'save':
		autom8_graph_rules_form_save();

		break;
	case 'actions':
		autom8_graph_rules_form_actions();

		break;
	case 'item_movedown':
		autom8_graph_rules_item_movedown();

		header("Location: autom8_graph_rules.php?action=edit&id=" . $_GET["id"]);
		break;
	case 'item_moveup':
		autom8_graph_rules_item_moveup();

		header("Location: autom8_graph_rules.php?action=edit&id=" . $_GET["id"]);
		break;
	case 'item_remove':
		autom8_graph_rules_item_remove();

		header("Location: autom8_graph_rules.php?action=edit&id=" . $_GET["id"]);
		break;
	case 'item_edit':
		include_once($config['include_path'] . "/top_header.php");

		autom8_graph_rules_item_edit();

		include_once($config['include_path'] . "/bottom_footer.php");
		break;
	case 'remove':
		autom8_graph_rules_remove();

		header ("Location: autom8_graph_rules.php");
		break;
	case 'edit':
		include_once($config['include_path'] . "/top_header.php");

		autom8_graph_rules_edit();

		include_once($config['include_path'] . "/bottom_footer.php");
		break;
	default:
		include_once($config['include_path'] . "/top_header.php");

		autom8_graph_rules();

		include_once($config['include_path'] . "/bottom_footer.php");
		break;
}

/* --------------------------
 The Save Function
 -------------------------- */

function autom8_graph_rules_form_save() {

	if (isset($_POST["save_component_autom8_graph_rule"])) {

		/* ================= input validation ================= */
		input_validate_input_number(get_request_var_post("id"));
		/* ==================================================== */

		$save["id"] = $_POST["id"];
		$save["name"] = form_input_validate($_POST["name"], "name", "", false, 3);
		$save["snmp_query_id"] = form_input_validate($_POST["snmp_query_id"], "snmp_query_id", "^[0-9]+$", false, 3);
		$save["graph_type_id"] = (isset($_POST["graph_type_id"])) ? form_input_validate($_POST["graph_type_id"], "graph_type_id", "^[0-9]+$", false, 3) : 0;
		$save["enabled"] = (isset($_POST["enabled"]) ? "on" : "");
		if (!is_error_message()) {
			$rule_id = sql_save($save, "plugin_autom8_graph_rules");

			if ($rule_id) {
				raise_message(1);
			}else{
				raise_message(2);
			}
		}

		#		if ((is_error_message()) || (empty($_POST["id"]))) {
		header("Location: autom8_graph_rules.php?action=edit&id=" . (empty($rule_id) ? $_POST["id"] : $rule_id));
		#		}else{
		#			header("Location: autom8_graph_rules.php");
		#		}
	}elseif (isset($_POST["save_component_autom8_graph_rule_item"])) {

		/* ================= input validation ================= */
		input_validate_input_number(get_request_var_post("id"));
		input_validate_input_number(get_request_var_post("item_id"));
		/* ==================================================== */
		$save = array();
		$save["id"] = form_input_validate($_POST["item_id"], "item_id", "^[0-9]+$", false, 3);
		$save["rule_id"] = form_input_validate($_POST["id"], "id", "^[0-9]+$", false, 3);
		$save["sequence"] = form_input_validate($_POST["sequence"], "sequence", "^[0-9]+$", false, 3);
		$save["operation"] = form_input_validate($_POST["operation"], "operation", "^[-0-9]+$", true, 3);
		$save["field"] = form_input_validate(((isset($_POST["field"]) && $_POST["field"] != "0") ? $_POST["field"] : ""), "field", "", true, 3);
		$save["operator"] = form_input_validate((isset($_POST["operator"]) ? $_POST["operator"] : ""), "operator", "^[0-9]+$", true, 3);
		$save["pattern"] = form_input_validate((isset($_POST["pattern"]) ? $_POST["pattern"] : ""), "pattern", "", true, 3);

		if (!is_error_message()) {
			$item_id = sql_save($save, "plugin_autom8_graph_rule_items");

			if ($item_id) {
				raise_message(1);
			}else{
				raise_message(2);
			}
		}

		if (is_error_message()) {
			header("Location: autom8_graph_rules.php?action=item_edit&id=" . $_POST["id"] . "&item_id=" . (empty($item_id) ? $_POST["item_id"] : $item_id) . "&rule_type=" . AUTOM8_RULE_TYPE_GRAPH_ACTION);
		}else{
			header("Location: autom8_graph_rules.php?action=edit&id=" . $_POST["id"] . "&rule_type=" . AUTOM8_RULE_TYPE_GRAPH_ACTION);
		}
	}elseif (isset($_POST["save_component_autom8_match_item"])) {

		/* ================= input validation ================= */
		input_validate_input_number(get_request_var_post("id"));
		input_validate_input_number(get_request_var_post("item_id"));
		/* ==================================================== */
		unset($save);
		$save["id"] = form_input_validate($_POST["item_id"], "item_id", "^[0-9]+$", false, 3);
		$save["rule_id"] = form_input_validate($_POST["id"], "id", "^[0-9]+$", false, 3);
		$save["rule_type"] = AUTOM8_RULE_TYPE_GRAPH_MATCH;
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
			header("Location: autom8_graph_rules.php?action=item_edit&id=" . $_POST["id"] . "&item_id=" . (empty($item_id) ? $_POST["item_id"] : $item_id) . "&rule_type=" . AUTOM8_RULE_TYPE_GRAPH_MATCH);
		}else{
			header("Location: autom8_graph_rules.php?action=edit&id=" . $_POST["id"] . "&rule_type=" . AUTOM8_RULE_TYPE_GRAPH_MATCH);
		}
	} else {
		raise_message(2);
		header("Location: autom8_graph_rules.php");
	}
}

/* ------------------------
 The "actions" function
 ------------------------ */

function autom8_graph_rules_form_actions() {
	global $colors, $autom8_graph_rules_actions;
	global $config;
	include_once($config["base_path"]."/plugins/autom8/autom8_utilities.php");

	/* if we are to save this form, instead of display it */
	if (isset($_POST["selected_items"])) {
		$selected_items = unserialize(stripslashes($_POST["selected_items"]));

		if ($_POST["drp_action"] == AUTOM8_ACTION_GRAPH_DELETE) { /* delete */
			db_execute("delete from plugin_autom8_graph_rules where " . array_to_sql_or($selected_items, "id"));
			db_execute("delete from plugin_autom8_graph_rule_items where " . array_to_sql_or($selected_items, "rule_id"));
			db_execute("delete from plugin_autom8_match_rule_items where " . array_to_sql_or($selected_items, "rule_id"));

		}elseif ($_POST["drp_action"] == AUTOM8_ACTION_GRAPH_DUPLICATE) { /* duplicate */
			for ($i=0;($i<count($selected_items));$i++) {
				/* ================= input validation ================= */
				input_validate_input_number($selected_items[$i]);
				/* ==================================================== */
				autom8_log("form_actions duplicate: " . $selected_items[$i] . " name: " . $_POST["name_format"], true, "AUTOM8 TRACE", POLLER_VERBOSITY_MEDIUM);
				duplicate_autom8_graph_rules($selected_items[$i], $_POST["name_format"]);
			}
		}elseif ($_POST["drp_action"] == AUTOM8_ACTION_GRAPH_ENABLE) { /* enable */
			for ($i=0;($i<count($selected_items));$i++) {
				/* ================= input validation ================= */
				input_validate_input_number($selected_items[$i]);
				/* ==================================================== */
				autom8_log("form_actions enable: " . $selected_items[$i], true, "AUTOM8 TRACE", POLLER_VERBOSITY_MEDIUM);
				db_execute("UPDATE plugin_autom8_graph_rules SET enabled='on' where id=" . $selected_items[$i]);
			}
		}elseif ($_POST["drp_action"] == AUTOM8_ACTION_GRAPH_DISABLE) { /* disable */
			for ($i=0;($i<count($selected_items));$i++) {
				/* ================= input validation ================= */
				input_validate_input_number($selected_items[$i]);
				/* ==================================================== */
				autom8_log("form_actions disable: " . $selected_items[$i], true, "AUTOM8 TRACE", POLLER_VERBOSITY_MEDIUM);
				db_execute("UPDATE plugin_autom8_graph_rules SET enabled='' where id=" . $selected_items[$i]);
				}
		}

		header("Location: autom8_graph_rules.php");
		exit;
	}

	/* setup some variables */
	$autom8_graph_rules_list = ""; $i = 0;
	/* loop through each of the graphs selected on the previous page and get more info about them */
	while (list($var,$val) = each($_POST)) {
		if (preg_match("/^chk_([0-9]+)$/", $var, $matches)) {
			/* ================= input validation ================= */
			input_validate_input_number($matches[1]);
			/* ==================================================== */
			$autom8_graph_rules_list .= "<li>" . db_fetch_cell("select name from plugin_autom8_graph_rules where id=" . $matches[1]) . "</li>";
			$autom8_graph_rules_array[] = $matches[1];
		}
	}

	include_once("./include/top_header.php");
	#print "<pre>"; print_r($_POST); print_r($_GET); print_r($_REQUEST); print "</pre>";

	print "<form name='autom8_graph_rules' action='autom8_graph_rules.php' method='post'>";

	html_start_box("<strong>" . $autom8_graph_rules_actions{$_POST["drp_action"]} . "</strong>", "100%", $colors["header_panel"], "3", "center", "");

	if ($_POST["drp_action"] == AUTOM8_ACTION_GRAPH_DELETE) { /* delete */
		print "	<tr>
			<td class='textArea' bgcolor='#" . $colors["form_alternate1"]. "'>
				<p>Are you sure you want to delete the following Rules?</p>
				<p><ul>$autom8_graph_rules_list</ul></p>
			</td>
		</tr>\n
		";
	}elseif ($_POST["drp_action"] == AUTOM8_ACTION_GRAPH_DUPLICATE) { /* duplicate */
		print "	<tr>
			<td class='textArea' bgcolor='#" . $colors["form_alternate1"]. "'>
				<p>When you click save, the following Rules will be duplicated. You can
				optionally change the title format for the new Rules.</p>
				<p><ul>$autom8_graph_rules_list</ul></p>
				<p><strong>Title Format:</strong><br>"; form_text_box("name_format", "<rule_name> (1)", "", "255", "30", "text"); print "</p>
			</td>
		</tr>\n
		";
	}elseif ($_POST["drp_action"] == AUTOM8_ACTION_GRAPH_ENABLE) { /* enable */
		print "	<tr>
			<td class='textArea' bgcolor='#" . $colors["form_alternate1"]. "'>
				<p>When you click save, the following Rules will be enabled.</p>
				<p><ul>$autom8_graph_rules_list</ul></p>
				<p><strong>Make sure, that those rules have successfully been tested!</strong></p>
			</td>
		</tr>\n
		";
	}elseif ($_POST["drp_action"] == AUTOM8_ACTION_GRAPH_DISABLE) { /* disable */
		print "	<tr>
			<td class='textArea' bgcolor='#" . $colors["form_alternate1"]. "'>
				<p>When you click save, the following Rules will be disabled.</p>
				<p><ul>$autom8_graph_rules_list</ul></p>
			</td>
		</tr>\n
		";
	}

	if (!isset($autom8_graph_rules_array)) {
		print "<tr><td bgcolor='#" . $colors["form_alternate1"]. "'><span class='textError'>You must select at least one Rule.</span></td></tr>\n";
		$save_html = "<input type='button' value='Return' onClick='window.history.back()'>";
	}else {
		$save_html = "<input type='button' value='Return' onClick='window.history.back()'>&nbsp;<input type='submit' value='Apply' title='Apply requested action'>";
	}

	print "	<tr>
		<td align='right' bgcolor='#eaeaea'>
			<input type='hidden' name='action' value='actions'>
			<input type='hidden' name='selected_items' value='" . (isset($autom8_graph_rules_array) ? serialize($autom8_graph_rules_array) : '') . "'>
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
function autom8_graph_rules_item_movedown() {
	/* ================= input validation ================= */
	input_validate_input_number(get_request_var("id"));
	input_validate_input_number(get_request_var("item_id"));
	input_validate_input_number(get_request_var("rule_type"));
	/* ==================================================== */

	if ( $_GET["rule_type"] == AUTOM8_RULE_TYPE_GRAPH_MATCH) {
		move_item_down("plugin_autom8_match_rule_items", $_GET["item_id"], "rule_id=" . $_GET["id"] . " AND rule_type=" . $_GET["rule_type"]);
	} elseif ($_GET["rule_type"] == AUTOM8_RULE_TYPE_GRAPH_ACTION) {
		move_item_down("plugin_autom8_graph_rule_items", $_GET["item_id"], "rule_id=" . $_GET["id"]);
	}
}



function autom8_graph_rules_item_moveup() {
	/* ================= input validation ================= */
	input_validate_input_number(get_request_var("id"));
	input_validate_input_number(get_request_var("item_id"));
	input_validate_input_number(get_request_var("rule_type"));
	/* ==================================================== */

	if ( $_GET["rule_type"] == AUTOM8_RULE_TYPE_GRAPH_MATCH) {
		move_item_up("plugin_autom8_match_rule_items", $_GET["item_id"], "rule_id=" . $_GET["id"] . " AND rule_type=" . $_GET["rule_type"]);
	} elseif ($_GET["rule_type"] == AUTOM8_RULE_TYPE_GRAPH_ACTION) {
		move_item_up("plugin_autom8_graph_rule_items", $_GET["item_id"], "rule_id=" . $_GET["id"]);
	}
}



function autom8_graph_rules_item_remove() {
	/* ================= input validation ================= */
	input_validate_input_number(get_request_var("item_id"));
	input_validate_input_number(get_request_var("rule_type"));
	/* ==================================================== */

	if ( $_GET["rule_type"] == AUTOM8_RULE_TYPE_GRAPH_MATCH) {
		db_execute("delete from plugin_autom8_match_rule_items where id=" . $_GET["item_id"]);
	} elseif ($_GET["rule_type"] == AUTOM8_RULE_TYPE_GRAPH_ACTION) {
		db_execute("delete from plugin_autom8_graph_rule_items where id=" . $_GET["item_id"]);
	}

}



function autom8_graph_rules_item_edit() {
	global $config;
	include_once($config["base_path"]."/plugins/autom8/autom8_utilities.php");

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var("id"));
	input_validate_input_number(get_request_var("item_id"));
	input_validate_input_number(get_request_var("rule_type"));
	/* ==================================================== */

	global_item_edit($_GET["id"], (isset($_GET["item_id"]) ? $_GET["item_id"] : ""), $_GET["rule_type"]);

	form_hidden_box("rule_type", $_GET["rule_type"], $_GET["rule_type"]);
	form_hidden_box("id", (isset($_GET["id"]) ? $_GET["id"] : "0"), "");
	form_hidden_box("item_id", (isset($_GET["item_id"]) ? $_GET["item_id"] : "0"), "");
	if($_GET["rule_type"] == AUTOM8_RULE_TYPE_GRAPH_MATCH) {
		form_hidden_box("save_component_autom8_match_item", "1", "");
	} else {
		form_hidden_box("save_component_autom8_graph_rule_item", "1", "");
	}
	form_save_button(htmlspecialchars("autom8_graph_rules.php?action=edit&id=" . $_GET["id"] . "&rule_type=". $_GET["rule_type"]));
//Now we need some javascript to make it dynamic
?>
<script type="text/javascript">

toggle_operation();
toggle_operator();

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

function autom8_graph_rules_remove() {
	/* ================= input validation ================= */
	input_validate_input_number(get_request_var("id"));
	/* ==================================================== */

	if ((read_config_option("deletion_verification") == "on") && (!isset($_GET["confirm"]))) {
		include("./include/top_header.php");
		form_confirm("Are You Sure?", "Are you sure you want to delete the Rule <strong>'" . db_fetch_cell("select name from autom8_graph_rules where id=" . $_GET["id"]) . "'</strong>?", "autom8_graph_rules.php", "autom8_graph_rules.php?action=remove&id=" . $_GET["id"]);
		include("./include/bottom_footer.php");
		exit;
	}

	if ((read_config_option("deletion_verification") == "") || (isset($_GET["confirm"]))) {
		db_execute("DELETE FROM plugin_autom8_match_rule_items " .
					"WHERE rule_id=" . $_GET["id"] .
					" AND rule_type=" . AUTOM8_RULE_TYPE_GRAPH_MATCH);
		db_execute("DELETE FROM plugin_autom8_graph_rule_items " .
					"WHERE rule_id=" . $_GET["id"]);
		db_execute("delete from plugin_autom8_graph_rules where id=" . $_GET["id"]);
	}
}


function autom8_graph_rules_edit() {
	global $colors, $config;
	global $fields_autom8_graph_rules_edit1, $fields_autom8_graph_rules_edit2, $fields_autom8_graph_rules_edit3;
	include_once($config["base_path"]."/plugins/autom8/autom8_utilities.php");

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var_request("id"));
	input_validate_input_number(get_request_var_request("snmp_query_id"));
	input_validate_input_number(get_request_var_request("graph_type_id"));
	input_validate_input_number(get_request_var_request("page"));
	/* ==================================================== */
	#print "<pre>"; print_r($_POST); print_r($_GET); print_r($_REQUEST); print "</pre>";

	/* clean up rule name */
	if (isset($_REQUEST["name"])) {
		$_REQUEST["name"] = sanitize_search_string(get_request_var("name"));
	}

	/* remember these search fields in session vars so we don't have to keep passing them around */
	load_current_session_value("page", "sess_autom8_graph_rule_current_page", "1");
	load_current_session_value("graph_rule_rows", "sess_autom8_graph_rule_rows", read_config_option("num_rows_data_query"));

	/* handle show_graphs mode */
	if (isset($_GET["show_graphs"])) {
		if ($_GET["show_graphs"] == "0") {
			kill_session_var("autom8_graph_rules_show_graphs");
		}elseif ($_GET["show_graphs"] == "1") {
			$_SESSION["autom8_graph_rules_show_graphs"] = true;
		}
	}

	/* handle show_hosts mode */
	if (isset($_GET["show_hosts"])) {
		if ($_GET["show_hosts"] == "0") {
			kill_session_var("autom8_graph_rules_show_hosts");
		}elseif ($_GET["show_hosts"] == "1") {
			$_SESSION["autom8_graph_rules_show_hosts"] = true;
		}
	}


	/*
	 * display the rule -------------------------------------------------------------------------------------
	 */
	$rule = array();
	if (!empty($_GET["id"])) {
		$rule = db_fetch_row("SELECT * FROM plugin_autom8_graph_rules where id=" . $_GET["id"]);
		if (!empty($_GET["graph_type_id"])) {
			$rule["graph_type_id"] = $_GET["graph_type_id"]; # set query_type for display
		}
		# setup header
		$header_label = "[edit: " . $rule["name"] . "]";
	}else{
		$header_label = "[new]";
	}


	/*
	 * show hosts? ------------------------------------------------------------------------------------------
	 */
	if (!empty($_GET["id"])) {
		?>
<table width="100%" align="center">
	<tr>
		<td class="textInfo" align="right" valign="top"><span
			style="color: #c16921;">*<a
			href='<?php print htmlspecialchars("autom8_graph_rules.php?action=edit&id=" . (isset($_GET["id"]) ? $_GET["id"] : 0) . "&show_hosts=") . (isset($_SESSION["autom8_graph_rules_show_hosts"]) ? "0" : "1");?>'><strong><?php print (isset($_SESSION["autom8_graph_rules_show_hosts"]) ? "Don't Show" : "Show");?></strong>
		Matching Hosts.</a></span><br>
		</td>
	</tr>
		<?php
	}

	/*
	 * show graphs? -----------------------------------------------------------------------------------------
	 */
	if (!empty($rule["graph_type_id"]) && $rule["graph_type_id"] > 0) {
		?>
	<tr>
		<td class="textInfo" align="right" valign="top"><span
			style="color: #c16921;">*<a
			href='<?php print htmlspecialchars("autom8_graph_rules.php?action=edit&id=" . (isset($_GET["id"]) ? $_GET["id"] : 0) . "&show_graphs=") . (isset($_SESSION["autom8_graph_rules_show_graphs"]) ? "0" : "1");?>'><strong><?php print (isset($_SESSION["autom8_graph_rules_show_graphs"]) ? "Don't Show" : "Show");?></strong>
		Matching Graphs.</a></span><br>
		</td>
	</tr>
</table>
<br>
		<?php
	}

	print "<form method='post' action='autom8_graph_rules.php' name='form_autom8_graph_rule_edit'>";
	html_start_box("<strong>Rule Selection</strong> $header_label", "100%", $colors["header"], "3", "center", "");
	#print "<pre>"; print_r($_POST); print_r($_GET); print_r($_REQUEST); print "</pre>";

	if (!empty($_GET["id"])) {
		/* display whole rule */
		$form_array = $fields_autom8_graph_rules_edit1 + $fields_autom8_graph_rules_edit2 + $fields_autom8_graph_rules_edit3;
	} else {
		/* display first part of rule only and request user to proceed */
		$form_array = $fields_autom8_graph_rules_edit1;
	}

	draw_edit_form(array(
		"config" => array("no_form_tag" => true),
		"fields" => inject_form_variables($form_array, (isset($rule) ? $rule : array()))
	));

	html_end_box();
	form_hidden_box("id", (isset($rule["id"]) ? $rule["id"] : "0"), "");
	form_hidden_box("item_id", (isset($rule["item_id"]) ? $rule["item_id"] : "0"), "");
	form_hidden_box("save_component_autom8_graph_rule", "1", "");

	/*
	 * display the rule items -------------------------------------------------------------------------------
	 */
	if (!empty($rule["id"])) {
		# display graph rules for host match
		display_match_rule_items("Rule Items => Eligible Hosts",
			$rule["id"],
			AUTOM8_RULE_TYPE_GRAPH_MATCH,
			basename($_SERVER["PHP_SELF"]));

		# fetch graph action rules
		display_graph_rule_items("Rule Items => Create Graph",
			$rule["id"],
			AUTOM8_RULE_TYPE_GRAPH_ACTION,
			basename($_SERVER["PHP_SELF"]));
	}

	form_save_button("autom8_graph_rules.php");
	print "<br>";

	if (!empty($rule["id"])) {
		/* display list of matching hosts */
		if (isset($_SESSION["autom8_graph_rules_show_hosts"])) {
			if ($_SESSION["autom8_graph_rules_show_hosts"]) {
				display_matching_hosts($rule, AUTOM8_RULE_TYPE_GRAPH_MATCH, basename($_SERVER["PHP_SELF"]) . "?action=edit&id=" . $_GET["id"]);
			}
		}

		/* display list of new graphs */
		if (isset($_SESSION["autom8_graph_rules_show_graphs"])) {
			if ($_SESSION["autom8_graph_rules_show_graphs"]) {
				display_new_graphs($rule);
			}
		}
	}

	?>
<script type="text/javascript">
	<!--
	function applySNMPQueryIdChange(objForm) {
		strURL = '?action=edit&id=' + objForm.id.value;
		strURL = strURL + '&snmp_query_id=' + objForm.snmp_query_id.value;
		if (typeof(name) != 'undefined') {
			strURL = strURL + '&name=' + objForm.name.value;
		}
		//strURL = strURL + '&graph_rule_rows=' + objForm.graph_rule_rows.value;
		//alert('Url: ' + strURL);
		document.location = strURL;
	}
	function applySNMPQueryTypeChange(objForm) {
		strURL = '?action=edit&id=' + objForm.id.value;
		strURL = strURL + '&snmp_query_id=' + objForm.snmp_query_id.value;
		if (typeof(name) != 'undefined') {
			strURL = strURL + '&name=' + objForm.name.value;
		}
		if (typeof(snmp_query_type) != 'undefined') {
			strURL = strURL + '&snmp_query_type' + objForm.name.value;
		}
		//strURL = strURL + '&graph_rule_rows=' + objForm.graph_rule_rows.value;
		//alert('Url: ' + strURL);
		document.location = strURL;
	}
	-->
</script>
	<?php
}

function autom8_graph_rules() {
	global $colors, $autom8_graph_rules_actions, $config, $item_rows;
	#print "<pre>"; print_r($_POST); print_r($_GET); print_r($_REQUEST); print "</pre>";

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var_request("page"));
	input_validate_input_number(get_request_var_request("rule_status"));
	input_validate_input_number(get_request_var_request("rule_rows"));
	input_validate_input_number(get_request_var_request("snmp_query_id"));
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
		kill_session_var("sess_autom8_graph_rules_current_page");
		kill_session_var("sess_autom8_graph_rules_filter");
		kill_session_var("sess_autom8_graph_rules_sort_column");
		kill_session_var("sess_autom8_graph_rules_sort_direction");
		kill_session_var("sess_autom8_graph_rules_status");
		kill_session_var("sess_autom8_graph_rules_rows");
		kill_session_var("sess_autom8_graph_rules_snmp_query_id");

		unset($_REQUEST["page"]);
		unset($_REQUEST["filter"]);
		unset($_REQUEST["sort_column"]);
		unset($_REQUEST["sort_direction"]);
		unset($_REQUEST["rule_status"]);
		unset($_REQUEST["rule_rows"]);
		unset($_REQUEST["snmp_query_id"]);

	}

	if ((!empty($_SESSION["sess_autom8_graph_rules_status"])) && (!empty($_REQUEST["rule_status"]))) {
		if ($_SESSION["sess_autom8_graph_rules_status"] != $_REQUEST["rule_status"]) {
			$_REQUEST["page"] = 1;
		}
	}

	/* remember these search fields in session vars so we don't have to keep passing them around */
	load_current_session_value("page", "sess_autom8_graph_rules_current_page", "1");
	load_current_session_value("filter", "sess_autom8_graph_rules_filter", "");
	load_current_session_value("sort_column", "sess_autom8_graph_rules_sort_column", "name");
	load_current_session_value("sort_direction", "sess_autom8_graph_rules_sort_direction", "ASC");
	load_current_session_value("rule_status", "sess_autom8_graph_rules_status", "-1");
	load_current_session_value("rule_rows", "sess_autom8_graph_rules_rows", read_config_option("num_rows_device"));
	load_current_session_value("snmp_query_id", "sess_autom8_graph_rules_snmp_query_id", "");

	/* if the number of rows is -1, set it to the default */
	if ($_REQUEST["rule_rows"] == -1) {
		$_REQUEST["rule_rows"] = read_config_option("num_rows_device");
	}

	print ('<form name="form_autom8_graph_rules" method="post" action="autom8_graph_rules.php">');

	html_start_box("<strong>Graph Rules</strong>", "100%", $colors["header"], "3", "center", "autom8_graph_rules.php?action=edit");

	$filter_html = '<tr bgcolor=' . $colors["panel"] . '>
					<td>
					<table width="100%" cellpadding="0" cellspacing="0">
						<tr>
							<td nowrap style="white-space: nowrap;" width="50">
								Search:&nbsp;
							</td>
							<td width="1"><input type="text" name="filter" size="40" onChange="applyViewRuleFilterChange(document.form_autom8_graph_rules)" value="' . get_request_var_request("filter") . '">
							</td>
							<td nowrap style="white-space: nowrap;" width="50">
								&nbsp;Status:&nbsp;
							</td>
							<td width="1">
								<select name="rule_status" onChange="applyViewRuleFilterChange(document.form_autom8_graph_rules)">
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
								name"go" value="Go"><input type="button"
								name="clear_x" value="Clear"></td>
						</tr>
						<tr>
							<td nowrap style="white-space: nowrap;" width="50">
								Data Query:&nbsp;
							</td>
							<td width="1">
								<select name="snmp_query_id" onChange="applyViewRuleFilterChange(document.form_autom8_graph_rules)">
									<option value="-1" ';
	if (get_request_var_request("snmp_query_id") == "-1") {
		$filter_html .= 'selected';
	}
	$filter_html .= '>Any</option>';
	$available_data_queries = db_fetch_assoc("SELECT DISTINCT " .
		"plugin_autom8_graph_rules.snmp_query_id, " .
		"snmp_query.name " .
		"FROM plugin_autom8_graph_rules " .
		"LEFT JOIN snmp_query ON (plugin_autom8_graph_rules.snmp_query_id=snmp_query.id) " .
		"order by snmp_query.name");

	if (sizeof($available_data_queries) > 0) {
		foreach ($available_data_queries as $data_query) {
			$filter_html .= "<option value='" . $data_query["snmp_query_id"] . "'";
			if (get_request_var_request("snmp_query_id") == $data_query["snmp_query_id"]) {
				$filter_html .= " selected";
			}
			$filter_html .= ">" . $data_query["name"] . "</option>\n";
		}
	}
	$filter_html .= '							</select>
							</td>
						</tr>
					</table>
					</td>
					<td><input type="hidden" name="page" value="1"></td>
				</tr>';

	print $filter_html;

	html_end_box();

	print "</form>\n";

	/* form the 'where' clause for our main sql query */
	if (strlen(get_request_var_request("filter"))) {
		$sql_where = "WHERE (plugin_autom8_graph_rules.name LIKE '%%" . get_request_var_request("filter") . "%%')";
	}else{
		$sql_where = "";
	}

	if (get_request_var_request("rule_status") == "-1") {
		/* Show all items */
	}elseif (get_request_var_request("rule_status") == "-2") {
		$sql_where .= (strlen($sql_where) ? " and plugin_autom8_graph_rules.enabled='on'" : "where .plugin_autom8_graph_rules.enabled='on'");
	}elseif (get_request_var_request("rule_status") == "-3") {
		$sql_where .= (strlen($sql_where) ? " and plugin_autom8_graph_rules.enabled=''" : "where plugin_autom8_graph_rules.enabled=''");
	}

	if (get_request_var_request("snmp_query_id") == "-1") {
		/* show all items */
	} elseif (!empty($_REQUEST["snmp_query_id"])) {
		$sql_where .= (strlen($sql_where) ? " AND " : " WHERE ");
		$sql_where .= "plugin_autom8_graph_rules.snmp_query_id=" . get_request_var_request("snmp_query_id");
	}

	print "<form name='chk' method='post' action='autom8_graph_rules.php'>\n";
	html_start_box("", "100%", $colors["header"], "3", "center", "");

	$total_rows = db_fetch_cell("SELECT " .
		"COUNT(plugin_autom8_graph_rules.id)" .
		"FROM plugin_autom8_graph_rules " .
		"LEFT JOIN snmp_query ON (plugin_autom8_graph_rules.snmp_query_id=snmp_query.id) " .
		$sql_where);

	$autom8_graph_rules_list = db_fetch_assoc("SELECT " .
		"plugin_autom8_graph_rules.id, ".
		"plugin_autom8_graph_rules.name, " .
		"plugin_autom8_graph_rules.snmp_query_id, " .
		"plugin_autom8_graph_rules.graph_type_id, " .
		"plugin_autom8_graph_rules.enabled, " .
		"snmp_query.name AS snmp_query_name, " .
		"snmp_query_graph.name AS graph_type_name " .
		"FROM plugin_autom8_graph_rules " .
		"LEFT JOIN snmp_query 			ON (plugin_autom8_graph_rules.snmp_query_id=snmp_query.id) " .
		"LEFT JOIN snmp_query_graph 	ON (plugin_autom8_graph_rules.graph_type_id=snmp_query_graph.id) " .
		$sql_where .
		" ORDER BY " . get_request_var_request("sort_column") . " " . get_request_var_request("sort_direction") .
		" LIMIT " . (get_request_var_request("rule_rows")*(get_request_var_request("page")-1)) . "," . get_request_var_request("rule_rows"));

	/* generate page list */
	$url_page_select = get_page_list(get_request_var_request("page"), MAX_DISPLAY_PAGES, get_request_var_request("rule_rows"), $total_rows, "autom8_graph_rules.php?filter=" . get_request_var_request("filter"));

	$nav = "<tr bgcolor='#" . $colors["header"] . "'>
		<td colspan='9'>
			<table width='100%' cellspacing='0' cellpadding='0' border='0'>
				<tr>
					<td align='left' class='textHeaderDark'>
						<strong>&lt;&lt; "; if (get_request_var_request("page") > 1) { $nav .= "<a class='linkOverDark' href='" . htmlspecialchars("autom8_graph_rules.php?filter=" . get_request_var_request("filter") . "&rule_status=" . get_request_var_request("rule_status") . "&page=" . (get_request_var_request("page")-1)) . "'>"; } $nav .= "Previous"; if (get_request_var_request("page") > 1) { $nav .= "</a>"; } $nav .= "</strong>
					</td>\n
					<td align='center' class='textHeaderDark'>
						Showing Rows " . ((get_request_var_request("rule_rows")*(get_request_var_request("page")-1))+1) . " to " . ((($total_rows < read_config_option("num_rows_device")) || ($total_rows < (get_request_var_request("rule_rows")*get_request_var_request("page")))) ? $total_rows : (get_request_var_request("rule_rows")*get_request_var_request("page"))) . " of $total_rows [$url_page_select]
					</td>\n
					<td align='right' class='textHeaderDark'>
						<strong>"; if ((get_request_var_request("page") * get_request_var_request("rule_rows")) < $total_rows) { $nav .= "<a class='linkOverDark' href='" . htmlspecialchars("autom8_graph_rules.php?filter=" . get_request_var_request("filter") . "&rule_status=" . get_request_var_request("rule_status") . "&page=" . (get_request_var_request("page")+1)) . "'>"; } $nav .= "Next"; if ((get_request_var_request("page") * get_request_var_request("rule_rows")) < $total_rows) { $nav .= "</a>"; } $nav .= " &gt;&gt;</strong>
					</td>\n
				</tr>
			</table>
		</td>
	</tr>\n";

	print $nav;

	$display_text = array(
		"name" 					=> array("Rule Title", "ASC"),
		"id" 					=> array("Rule Id", "ASC"),
		"snmp_query_name" 		=> array("Data Query", "ASC"),
		"graph_type_name"		=> array("Graph Type", "ASC"),
		"enabled" 				=> array("Enabled", "ASC"),
	);

	html_header_sort_checkbox($display_text, get_request_var_request("sort_column"), get_request_var_request("sort_direction"), false);

	$i = 0;
	if (sizeof($autom8_graph_rules_list) > 0) {
		foreach ($autom8_graph_rules_list as $autom8_graph_rules) {
			$snmp_query_name 		= ((empty($autom8_graph_rules["snmp_query_name"])) 	 ? "<em>None</em>" : $autom8_graph_rules["snmp_query_name"]);
			$graph_type_name 		= ((empty($autom8_graph_rules["graph_type_name"])) 	 ? "<em>None</em>" : $autom8_graph_rules["graph_type_name"]);

			form_alternate_row_color($colors["alternate"], $colors["light"], $i, 'line' . $autom8_graph_rules["id"]); $i++;

			form_selectable_cell("<a class='linkEditMain' href='" . htmlspecialchars("autom8_graph_rules.php?action=edit&id=" . $autom8_graph_rules["id"] . "&page=1 ' title='" . $autom8_graph_rules["name"]) . "'>" . ((get_request_var_request("filter") != "") ? eregi_replace("(" . preg_quote(get_request_var_request("filter")) . ")", "<span style='background-color: #F8D93D;'>\\1</span>", title_trim($autom8_graph_rules["name"], read_config_option("max_title_graph"))) : title_trim($autom8_graph_rules["name"], read_config_option("max_title_graph"))) . "</a>", $autom8_graph_rules["id"]);
			form_selectable_cell($autom8_graph_rules["id"], $autom8_graph_rules["id"]);
			form_selectable_cell(((get_request_var_request("filter") != "") ? eregi_replace("(" . preg_quote(get_request_var_request("filter")) . ")", "<span style='background-color: #F8D93D;'>\\1</span>", $snmp_query_name) : $snmp_query_name), $autom8_graph_rules["id"]);
			form_selectable_cell(((get_request_var_request("filter") != "") ? eregi_replace("(" . preg_quote(get_request_var_request("filter")) . ")", "<span style='background-color: #F8D93D;'>\\1</span>", $graph_type_name) : $graph_type_name), $autom8_graph_rules["id"]);
			form_selectable_cell($autom8_graph_rules["enabled"] ? "Enabled" : "Disabled", $autom8_graph_rules["id"]);
			form_checkbox_cell($autom8_graph_rules["name"], $autom8_graph_rules["id"]);

			form_end_row();
		}
		print $nav;
	}else{
		print "<tr><td><em>No Graph Rules</em></td></tr>\n";
	}
	html_end_box(false);

	/* draw the dropdown containing a list of available actions for this form */
	draw_actions_dropdown($autom8_graph_rules_actions);

	print "</form>\n";
	?>
	<script type="text/javascript">
	<!--

	function applyViewRuleFilterChange(objForm) {
		strURL = 'autom8_graph_rules.php?rule_status=' + objForm.rule_status.value;
		strURL = strURL + '&rule_rows=' + objForm.rule_rows.value;
		strURL = strURL + '&snmp_query_id=' + objForm.snmp_query_id.value;
		strURL = strURL + '&filter=' + objForm.filter.value;
		document.location = strURL;
	}

	-->
	</script>
	<?php
}

?>
