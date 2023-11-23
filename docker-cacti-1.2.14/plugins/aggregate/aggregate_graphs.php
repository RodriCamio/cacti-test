<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2011 The Cacti Group                                 |
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
include_once("./lib/utility.php");
include_once("./lib/api_graph.php");
include_once("./lib/api_tree.php");
include_once("./lib/api_data_source.php");
include_once("./lib/template.php");
include_once("./lib/html_tree.php");
include_once("./lib/html_form_template.php");
include_once("./lib/rrd.php");
include_once("./lib/data_query.php");
include_once("./plugins/aggregate/aggregate.php");
include_once("./plugins/aggregate/aggregate_functions.php");

define("MAX_DISPLAY_PAGES", 21);

$graph_actions = array(
	1 => "Delete",
	2 => "Migrate Aggregate to use a Template",
	3 => "Create New Aggregate from Aggregates"
);

$agg_item_actions = array(
	10 => "Associate with Aggregate",
	11 => "Disassociate with Aggregate"
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
		graph_edit();
		include_once("./include/bottom_footer.php");
		break;
	default:
		include_once("./include/top_header.php");
		graph();
		include_once("./include/bottom_footer.php");
		break;
}

function add_tree_names_to_actions_array() {
	global $graph_actions;

	/* add a list of tree names to the actions dropdown */
	$trees = db_fetch_assoc("select id,name from graph_tree order by name");

	if (sizeof($trees) > 0) {
	foreach ($trees as $tree) {
		$graph_actions{"tr_" . $tree["id"]} = "Place on a Tree (" . $tree["name"] . ")";
	}
	}
}

function form_save() {
	/* make sure we are saving aggregate graph */
	if (!isset($_POST["save_component_graph"])) {
		header("Location: aggregate_graphs.php?action=edit&id=".$_POST["id"]);
		return null;
	}

	/* remember some often used values */
	$local_graph_id        = get_request_var_post("local_graph_id", 0);
	$graph_template_id     = get_request_var_post("graph_template_id", 0);
	$aggregate_template_id = get_request_var_post("aggregate_template_id", 0);
	$graph_title           = form_input_validate($_POST["title_format"], "title_format", "", false, 3);
	if (is_error_message()) {
		raise_message(2);
		header("Location: aggregate_graphs.php?action=edit&id=$local_graph_id");
		return null;
	}

	/* get the aggregate graph id */
	$aggregate_graph_id  = db_fetch_cell("SELECT id FROM plugin_aggregate_graphs WHERE local_graph_id=" . $local_graph_id );

	/* if user disabled template propogation we need to get graph data from form */
	if (!isset($_POST['template_propogation'])) {
		$aggregate_template_id = 0;
		$new_data = aggregate_validate_graph_params($_POST, false);
	} else {
		$new_data = array();
	}
	if (is_error_message()) {
		raise_message(2);
		header("Location: aggregate_graphs.php?action=edit&id=$local_graph_id");
		return null;
	}

	/* save graph data to cacti tables */
	$graph_templates_graph_id = aggregate_graph_templates_graph_save(
		$local_graph_id,
		$graph_template_id,
		$graph_title,
		$aggregate_template_id,
		$new_data
	);

	/* update title in aggregate graphs table */
	db_execute("UPDATE plugin_aggregate_graphs " .
			"SET title_format='" . $graph_title . "' " .
			"WHERE id=$aggregate_graph_id");

	/* next lets see if any of the aggregate has changed and save as applicable
	 * if the graph is templates, we can simply ignore.  A simple check will
	 * determine if aggregation propagation is enabled
	 */
	if (!isset($_POST['template_propogation'])) {
		/* template propagation is disabled */
		/* ================= input validation ================= */
		input_validate_input_number(get_request_var("graph_type"));
		input_validate_input_number(get_request_var("total"));
		input_validate_input_number(get_request_var("total_type"));
		input_validate_input_number(get_request_var("order_type"));
		input_validate_input_number(get_request_var("item_no"));
		/* ==================================================== */

		/* prime the save array */
		$save                         = array();
		$save['id']                   = $aggregate_graph_id;
		$save['template_propogation'] = '';
		$save['gprint_prefix']        = $_POST['gprint_prefix'];
		$save['total']                = $_POST['total'];
		$save['graph_type']           = $_POST['graph_type'];
		$save['total_type']           = $_POST['total_type'];
		$save['total_prefix']         = $_POST['total_prefix'];
		$save['order_type']           = $_POST['order_type'];

		/* see if anything changed, if so, we will have to push out the aggregate */
		if (!empty($aggregate_graph_id)) {
			$old = db_fetch_row("SELECT * FROM plugin_aggregate_graphs WHERE id=$aggregate_graph_id");
			$save_me = 0;

			$save_me += ($old['template_propogation'] != $save['template_propogation']);
			$save_me += ($old['gprint_prefix']        != $save['gprint_prefix']);
			$save_me += ($old['graph_type']           != $save['graph_type']);
			$save_me += ($old['total']                != $save['total']);
			$save_me += ($old['total_type']           != $save['total_type']);
			$save_me += ($old['total_prefix']         != $save['total_prefix']);
			$save_me += ($old['order_type']           != $save['order_type']);

			if ($save_me) {
				$aggregate_graph_id = sql_save($save, "plugin_aggregate_graphs");
			}

			/* save the template items now */
			/* get existing item ids and sequences from graph template */
			$graph_templates_items = array_rekey(
				db_fetch_assoc("SELECT id, sequence FROM graph_templates_item WHERE local_graph_id=0 AND graph_template_id=" . $graph_template_id),
				"id", array("sequence")
			);
			/* get existing aggregate template items */
			$aggregate_graph_items_old = array_rekey(
				db_fetch_assoc("SELECT * FROM plugin_aggregate_graphs_graph_item WHERE aggregate_graph_id=".$aggregate_graph_id),
				"graph_templates_item_id", array('aggregate_graph_id', 'graph_templates_item_id', 'sequence', 'color_template', 't_graph_type_id', 'graph_type_id', 't_cdef_id', 'cdef_id', 'item_skip', 'item_total')
			);

			/* update graph template item values with posted values */
			aggregate_validate_graph_items($_POST, $graph_templates_items);

			$items_changed = false;
			$items_to_save = array();
			foreach($graph_templates_items as $item_id => $data) {
				$item_new = array();
				$item_new['aggregate_graph_id'] = $aggregate_graph_id;
				$item_new['graph_templates_item_id'] = $item_id;

				$item_new['color_template'] = isset($data['color_template']) ? $data['color_template']:0;
				$item_new['item_skip']      = isset($data['item_skip']) ? 'on':'';
				$item_new['item_total']     = isset($data['item_total']) ? 'on':'';
				$item_new['sequence']       = isset($data['sequence']) ? $data['sequence']:-1;

				/* compare with old item to see if we need to push out. */
				if (!isset($aggregate_graph_items_old[$item_id])) {
					/* this item does not yet exist */
					$items_changed = true;
				} else {
					/* compare data from user to data from DB */
					foreach ($item_new as $field => $new_value) {
						if ($aggregate_graph_items_old[$item_id][$field] != $new_value) {
							$items_changed = true;
						}
					}
					/* fill in missing fields with db values */
					$item_new = array_merge($aggregate_graph_items_old[$item_id], $item_new);
				}
				$items_to_save[] = $item_new;
			}

			if ($items_changed) {
				aggregate_graph_items_save($items_to_save, 'plugin_aggregate_graphs_graph_item');

			}

			if ($save_me || $items_changed) {
				push_out_aggregates(0, $local_graph_id);
			}
		}
	}

	raise_message(1);
	header("Location: aggregate_graphs.php?action=edit&id=$local_graph_id");
}

/* ------------------------
    The "actions" function
   ------------------------ */

function form_actions() {
	global $colors, $graph_actions, $agg_item_actions;

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var_post('drp_action'));
	/* ==================================================== */

	/* we are performing two set's of actions here */
	$graph_actions += $agg_item_actions;

	/* if we are to save this form, instead of display it */
	if (isset($_POST["selected_items"])) {
		$selected_items = unserialize(stripslashes($_POST["selected_items"]));

		if ($_POST["drp_action"] == "1") { /* delete */
			for ($i=0;($i<count($selected_items));$i++) {
				/* ================= input validation ================= */
				input_validate_input_number($selected_items[$i]);
				/* ==================================================== */
			}

			api_aggregate_remove_multi($selected_items);
		}elseif ($_POST["drp_action"] == "2") { /* migrate to template */
			for ($i=0;($i<count($selected_items));$i++) {
				/* ================= input validation ================= */
				input_validate_input_number($selected_items[$i]);
				/* ==================================================== */
			}

			api_aggregate_convert_template($selected_items);
		}elseif ($_POST["drp_action"] == "3") { /* create aggregate from aggregate */
			for ($i=0;($i<count($selected_items));$i++) {
				/* ================= input validation ================= */
				input_validate_input_number($selected_items[$i]);
				/* ==================================================== */
			}
			$aggregate_name = $_REQUEST["aggregate_name"];

			api_aggregate_create($aggregate_name, $selected_items);
		}elseif ($_POST["drp_action"] == "10") { /* associate with aggregate */
			for ($i=0;($i<count($selected_items));$i++) {
				/* ================= input validation ================= */
				input_validate_input_number($selected_items[$i]);
				/* ==================================================== */
			}

			api_aggregate_associate($selected_items);
		}elseif ($_POST["drp_action"] == "11") { /* dis-associate with aggregate */
			for ($i=0;($i<count($selected_items));$i++) {
				/* ================= input validation ================= */
				input_validate_input_number($selected_items[$i]);
				/* ==================================================== */
			}

			api_aggregate_disassociate($selected_items);
		}elseif (ereg("^tr_([0-9]+)$", $_POST["drp_action"], $matches)) { /* place on tree */
			input_validate_input_number(get_request_var_post("tree_id"));
			input_validate_input_number(get_request_var_post("tree_item_id"));
			for ($i=0;($i<count($selected_items));$i++) {
				/* ================= input validation ================= */
				input_validate_input_number($selected_items[$i]);
				/* ==================================================== */

				api_tree_item_save(0, $_POST["tree_id"], TREE_ITEM_TYPE_GRAPH, $_POST["tree_item_id"], "", $selected_items[$i], read_graph_config_option("default_rra_id"), 0, 0, 0, false);
			}
		}

		header("Location: aggregate_graphs.php");
		exit;
	}

	/* setup some variables */
	$graph_list = ""; $i = 0;

	/* loop through each of the graphs selected on the previous page and get more info about them */
	while (list($var,$val) = each($_POST)) {
		if (ereg("^chk_([0-9]+)$", $var, $matches)) {
			/* ================= input validation ================= */
			input_validate_input_number($matches[1]);
			/* ==================================================== */

			$graph_list .= "<li>" . get_graph_title($matches[1]) . "</li>";
			$graph_array[$i] = $matches[1];

			$i++;
		}
	}

	include_once("./include/top_header.php");

	/* add a list of tree names to the actions dropdown */
	add_tree_names_to_actions_array();

	html_start_box("<strong>" . $graph_actions{$_POST["drp_action"]} . "</strong>", "60%", $colors["header_panel"], "3", "center", "");

	print "<form action='aggregate_graphs.php' method='post'>\n";

	if (isset($graph_array) && sizeof($graph_array)) {
		if ($_POST["drp_action"] == "1") { /* delete */
			print "	<tr>
					<td class='textArea' bgcolor='#" . $colors["form_alternate1"]. "'>
						<p>When you click \"Continue\", the following Aggregate Graph(s) will be deleted.</p>
						<p><ul>$graph_list</ul></p>
					</td>
				</tr>\n";
			$save_html = "<input type='button' value='Cancel' onClick='window.history.back()'>&nbsp;<input type='submit' value='Continue' title='Delete Graph(s)'>";
		}elseif ($_POST["drp_action"] == "2") { /* migrate to aggregate */
			/* determine the common graph template if any */
			reset($_POST);
			while (list($var,$val) = each($_POST)) {
				if (ereg("^chk_([0-9]+)$", $var, $matches)) {
					$local_graph_ids[] = $matches[1];
				}
			}
			$lgid = implode(",",$local_graph_ids);

			/* for whatever reason,  subquery performance in mysql is sub-obtimal.  Therefore, let's do this
			 * as a few queries instead.
			 */
			$task_items = array_rekey(db_fetch_assoc("SELECT DISTINCT task_item_id FROM graph_templates_item WHERE local_graph_id IN($lgid)"), "task_item_id", "task_item_id");
			$task_items = implode(",", $task_items);
			$graph_templates = db_fetch_assoc("SELECT DISTINCT graph_template_id FROM graph_templates_item
				WHERE task_item_id IN ($task_items) AND  graph_template_id>0");

			if (sizeof($graph_templates) > 1) {
				print "	<tr>
						<td class='textArea' bgcolor='#" . $colors["form_alternate1"]. "'>
							<p>The selected Aggregate Graphs represent elements from more than one Graph Template.</p>
							<p>In order to migrate the Aggregate Graphs below to a Template based Aggregate, they
							must only be using one Graph Template.  Please press 'Return' and then select only Aggregate
							Graph that utilize the same Graph Template.</p>
							<p><ul>$graph_list</ul></p>
						</td>
					</tr>\n";
				$save_html = "<input type='button' value='Return' onClick='window.history.back()'>";
			}else{
				$graph_template      = $graph_templates[0]['graph_template_id'];
				$aggregate_templates = db_fetch_assoc("SELECT id, name FROM plugin_aggregate_graph_templates WHERE graph_template_id=$graph_template ORDER BY name");

				if (sizeof($aggregate_templates)) {

					print "	<tr>
						<td class='textArea' colspan='2' bgcolor='#" . $colors["form_alternate1"]. "'>
							<p>When you click \"Continue\", the following Aggregate Graph(s) will be migrated to use the
							Aggregate Template that you choose below.</p>
							<p><ul>$graph_list</ul></p>
						</td>
					</tr>\n";
					print "<tr>
						<td class='textArea' width='170'><strong>Aggregate Template:</strong></td>
						<td>
							<select name='aggregate_template_id'>\n";
								html_create_list($aggregate_templates, "name", "id", $aggregate_templates[0]['id']);
					print "</select>
						</td>
					</tr>\n";

					$save_html = "<tr><td colspan='2' align='right'><input type='button' value='Cancel' onClick='window.history.back()'>&nbsp;<input type='submit' value='Continue' title='Delete Graph(s)'></td></tr>";
				}else{
					print "	<tr>
							<td class='textArea' bgcolor='#" . $colors["form_alternate1"]. "'>
								<p>There are currently no Aggregate Templates defined for the selected Legacy Aggregates.</p>
								<p>In order to migrate the Aggregate Graphs below to a Template based Aggregate, first
								create an Aggregate Template for the Graph Template '" . db_fetch_cell("SELECT name FROM graph_templates WHERE id=$graph_template") . "'.</p>
								<p>Please press 'Return' to continue.</p>
								<p><ul>$graph_list</ul></p>
							</td>
						</tr>\n";
					$save_html = "<input type='button' value='Return' onClick='window.history.back()'>";
				}
			}
		}elseif ($_POST["drp_action"] == "3") { /* create aggregate from aggregates */
			print "	<tr>
					<td colspan='2' class='textArea' bgcolor='#" . $colors["form_alternate1"]. "'>
						<p>When you click \"Continue\", the following Aggregate Graph(s) will be combined into a single Aggregate Graph.</p>
						<p><ul>$graph_list</ul></p>
					</td>
				</tr>\n";
			print "	<tr><td class='textArea' width='170'><strong>Aggregate Name:</strong></td></tr>\n";
			print "	<tr><td class='textArea'><input name='aggregate_name' size='40' value='New Aggregate'></td></tr>\n";

			$save_html = "<input type='button' value='Cancel' onClick='window.history.back()'>&nbsp;<input type='submit' value='Continue' title='Delete Graph(s)'>";
		}elseif ($_POST["drp_action"] == "10") { /* associate with aggregate */
			print "	<tr>
					<td class='textArea' bgcolor='#" . $colors["form_alternate1"]. "'>
						<p>When you click \"Continue\", the following Graph(s) will be Associated with the Aggregate Graph.</p>
						<p><ul>$graph_list</ul></p>
					</td>
				</tr>\n";
			$save_html = "<input type='button' value='Cancel' onClick='window.history.back()'>&nbsp;<input type='submit' value='Continue' title='Associate Graph(s)'>";
		}elseif ($_POST["drp_action"] == "11") { /* dis-associate with aggregate */
			print "	<tr>
					<td class='textArea' bgcolor='#" . $colors["form_alternate1"]. "'>
						<p>When you click \"Continue\", the following Graph(s) will be Removed from the Aggregate.</p>
						<p><ul>$graph_list</ul></p>
					</td>
				</tr>\n";
			$save_html = "<input type='button' value='Cancel' onClick='window.history.back()'>&nbsp;<input type='submit' value='Continue' title='Dis-Associate Graph(s)'>";
		}elseif (ereg("^tr_([0-9]+)$", $_POST["drp_action"], $matches)) { /* place on tree */
			print "	<tr>
					<td class='textArea' bgcolor='#" . $colors["form_alternate1"]. "'>
						<p>When you click \"Continue\", the following Aggregate Graph(s) will be placed under the Tree Branch selected below.</p>
						<p><ul>$graph_list</ul></p>
						<p><strong>Destination Branch:</strong><br>"; grow_dropdown_tree($matches[1], "tree_item_id", "0"); print "</p>
					</td>
				</tr>\n
				<input type='hidden' name='tree_id' value='" . $matches[1] . "'>\n";
			$save_html = "<input type='button' value='Cancel' onClick='window.history.back()'>&nbsp;<input type='submit' value='Continue' title='Place Graph(s) on Tree'>";
		}
	}else{
		print "<tr><td bgcolor='#" . $colors["form_alternate1"]. "'><span class='textError'>You must select at least one graph.</span></td></tr>\n";
		$save_html = "<input type='button' value='Return' onClick='window.history.back()'>";
	}

	print "	<tr>
			<td align='right' bgcolor='#eaeaea'>
				<input type='hidden' name='action' value='actions'>
				<input type='hidden' name='local_graph_id' value='" . (isset($_POST['local_graph_id']) ? $_POST['local_graph_id']:0) . "'>
				<input type='hidden' name='selected_items' value='" . (isset($graph_array) ? serialize($graph_array) : '') . "'>
				<input type='hidden' name='drp_action' value='" . $_POST["drp_action"] . "'>
				$save_html
			</td>
		</tr>\n";

	html_end_box(false);

	include_once("./include/bottom_footer.php");
}

/* -----------------------
    item - Graph Items
   ----------------------- */

function item() {
	global $colors, $consolidation_functions, $graph_item_types, $struct_graph_item;

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var("id"));
	/* ==================================================== */

	if (empty($_REQUEST["id"])) {
		$template_item_list = array();

		$header_label = "[new]";
	}else{
		$template_item_list = db_fetch_assoc("SELECT
			gti.id, gti.text_format, gti.value, gti.hard_return, gti.graph_type_id,
			gti.consolidation_function_id, dtr.data_source_name,
			cdef.name AS cdef_name, colors.hex
			FROM graph_templates_item AS gti
			LEFT JOIN data_template_rrd AS dtr ON (gti.task_item_id=dtr.id)
			LEFT JOIN data_local AS dl ON (dtr.local_data_id=dl.id)
			LEFT JOIN data_template_data AS dtd ON (dl.id=dtd.local_data_id)
			LEFT JOIN cdef ON (gti.cdef_id=cdef.id)
			LEFT JOIN colors ON (gti.color_id=colors.id)
			WHERE gti.local_graph_id=" . $_REQUEST["id"] . "
			ORDER BY gti.sequence");

		$header_label = "[edit: " . htmlspecialchars(get_graph_title($_REQUEST["id"])) . "]";
	}

	$graph_template_id = db_fetch_cell("SELECT graph_template_id FROM graph_local WHERE id=" . $_REQUEST["id"]);

	if (empty($graph_template_id)) {
		$add_text = "aggregate_items.php?action=item_edit&local_graph_id=" . $_REQUEST["id"];
	}else{
		$add_text = "";
	}

	html_start_box("<strong>Graph Items</strong> $header_label", "100%", $colors["header"], "3", "center", $add_text);
	draw_graph_items_list($template_item_list, "aggregate_items.php", "local_graph_id=" . $_REQUEST["id"], (empty($graph_template_id) ? false : true));
	html_end_box(false);
}

/* ------------------------------------
    graph - Graphs
   ------------------------------------ */

function graph_edit() {
	global $config, $colors, $struct_graph, $struct_aggregate_graph, $image_types, $consolidation_functions, $graph_item_types, $struct_graph_item;

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var("id"));
	/* ==================================================== */

	if (isset($_REQUEST["reset"])) {
		$_SESSION["aggregate_referer"] = "aggregate_graphs.php";
	}elseif (!substr_count($_SERVER["HTTP_REFERER"], "aggregate_graphs.php")) {
		$_SESSION["aggregate_referer"] = $_SERVER["HTTP_REFERER"];
	}elseif (!isset($_SESSION["aggregate_referer"])) {
		$_SESSION["aggregate_referer"] = $_SERVER["HTTP_REFERER"];
	}
	$referer = $_SESSION["aggregate_referer"];

	$use_graph_template = false;
	$aginfo = array();
	$graphs = array();
	if (!empty($_REQUEST["id"])) {
		$graphs = db_fetch_row("SELECT * FROM graph_templates_graph WHERE local_graph_id=" . $_REQUEST["id"]);
		$aginfo = db_fetch_row("SELECT * FROM plugin_aggregate_graphs WHERE local_graph_id=" . $graphs["local_graph_id"]);
		$header_label = "[edit: " . htmlspecialchars(get_graph_title($_REQUEST["id"])) . "]";
	}

	if (sizeof($aginfo)) {
		if ($aginfo['aggregate_template_id'] > 0) {
			$template = db_fetch_row("SELECT * FROM plugin_aggregate_graph_templates WHERE id=" . $aginfo["aggregate_template_id"]);
		}else{
			$template = $aginfo;
		}

		$aggregate_tabs = array('details' => 'Details', 'items' => 'Items', 'preview' => 'Preview');
	}else{
		$template = array();
		$aggregate_tabs = array('details' => 'Details', 'preview' => 'Preview');
	}

	/* set the default settings category */
	load_current_session_value("tab", "sess_aggregate_tab", "details");
	$current_tab = $_REQUEST["tab"];

	/* draw the categories tabs on the top of the page */
	print "<table class='tabs' width='100%' cellspacing='0' cellpadding='3' border='0' align='center'><tr>\n";

	if (sizeof($aggregate_tabs) > 0) {
	foreach (array_keys($aggregate_tabs) as $tab_short_name) {
		if ($tab_short_name == 'details' || (!empty($_REQUEST["id"]))) {
			print "<td " . (($tab_short_name == $current_tab) ? "bgcolor='silver'" : "bgcolor='#DFDFDF'") . " nowrap='nowrap' width='" . (strlen($aggregate_tabs[$tab_short_name]) * 9) . "' align='center' class='tab'>
				<span class='textHeader'><a href='" . htmlspecialchars($config["url_path"] . "plugins/aggregate/aggregate_graphs.php?action=edit&id=" . (isset($_REQUEST["id"]) ? $_REQUEST["id"]:"") . "&tab=$tab_short_name") . "'>" . $aggregate_tabs[$tab_short_name] . "</a></span>
				</td>\n
				<td width='1'></td>\n";
		}
	}
	}

	/* handle debug mode */
	if (isset($_REQUEST["debug"])) {
		if ($_REQUEST["debug"] == "0") {
			kill_session_var("graph_debug_mode");
		}elseif ($_REQUEST["debug"] == "1") {
			$_SESSION["graph_debug_mode"] = true;
		}
	}

	if (!empty($_REQUEST["id"]) && $current_tab == 'preview') {
		print "<td align='right'><a class='textHeader' href='" . htmlspecialchars("aggregate_graphs.php?action=edit&id=" . $_REQUEST["id"] . "&tab=" . $_REQUEST["tab"] .  "&debug=" . (isset($_SESSION["graph_debug_mode"]) ? "0" : "1")) . "'>Turn <strong>" . (isset($_SESSION["graph_debug_mode"]) ? "Off" : "On") . " Graph Debug Mode</a></td>\n</tr></table>\n";
	}elseif (!empty($_REQUEST["id"]) && $current_tab == 'details' && (!sizeof($template))) {
		print "<td align='right'><a id='toggle_items' class='textHeader' href='#'>Show Item Details</a></td>\n</tr></table>\n";
	}else{
		print "<td align='right'></td>\n</tr></table>\n";
	}

	if (!empty($_REQUEST["id"]) && $current_tab == 'preview') {
		html_start_box("<strong>Aggregate Preview</strong> $header_label", "100%", $colors["header"], "3", "center", "");
		?>
		<tr bgcolor='#FFFFFF'>
			<td align="center" class="textInfo" colspan="2">
				<img src="<?php print htmlspecialchars($config['url_path'] . "graph_image.php?action=edit&local_graph_id=" . $_REQUEST["id"] . "&rra_id=" . read_graph_config_option("default_rra_id"));?>" alt="">
			</td>
			<?php
			if ((isset($_SESSION["graph_debug_mode"])) && (isset($_REQUEST["id"]))) {
				$graph_data_array["output_flag"] = RRDTOOL_OUTPUT_STDERR;
				$graph_data_array["print_source"] = 1;
				?>
				<td>
					<span class="textInfo">RRDTool Command:</span><br>
					<pre><?php print @rrdtool_function_graph($_REQUEST["id"], 1, $graph_data_array);?></pre>
					<span class="textInfo">RRDTool Says:</span><br>
					<?php unset($graph_data_array["print_source"]);?>
					<pre><?php print @rrdtool_function_graph($_REQUEST["id"], 1, $graph_data_array);?></pre>
				</td>
				<?php
			}
			?>
		</tr>
		<?php
		html_end_box(false);
	}

	if (!empty($_REQUEST['id']) && $current_tab == 'items') {
		aggregate_items();
		exit;
	}

	print ('<form name="template_edit" action="aggregate_graphs.php" method="post">');

	/* we will show the templated representation only when when there is a template and propogation is enabled */
	if (!empty($_REQUEST['id']) && $current_tab == 'details') {
		if (sizeof($template)) {
			print "<div id='templated'>";

			html_start_box("<strong>Aggregate Graph</strong> $header_label", "100%", $colors["header"], "3", "center", "");

			/* add template propogation to the structure */
			draw_edit_form(array(
				"config" => array("no_form_tag" => true),
				"fields" => inject_form_variables($struct_aggregate_graph, (isset($aginfo) ? $aginfo : array()))
			));

			html_end_box(false);

			if (isset($template)) {
				draw_aggregate_graph_items_list(0, $template['graph_template_id'], $aginfo);
			}

			form_hidden_box("id", (isset($template["id"]) ? $template["id"] : "0"), "");
			form_hidden_box("save_component_template", "1", "");

			?>
			<script type='text/javascript'>

				var templated_selectors = [
					'#gprint_prefix',
					'#graph_type',
					'#total',
					'#total_type',
					'#total_prefix',
					'#order_type',

					'select[name^="agg_color"]',
					'input[name^="agg_total"]',
					'input[name^="agg_skip"]',

					'#image_format_id',
					'#height',
					'#width',
					'#slope_mode',
					'#auto_scale',
					'#auto_scale_opts',
					'#auto_scale_log',
					'#scale_log_units',
					'#auto_scale_rigid',
					'#auto_padding',
					'#export',
					'#upper_limit',
					'#lower_limit',
					'#base_value',
					'#unit_value',
					'#unit_exponent_value',
					'#vertical_label'
				];

			$().ready(function() {
				if ($('#template_propogation').is(':checked')) {
					for (var i = 0; i < templated_selectors.length; i++) {
						$( templated_selectors[i] ).attr('disabled', 'disabled');
					}
				}else{
					$('#row_template_propogation').hide();
					$('#row_spacer0').hide();
				}
			});

			$('#template_propogation').change(function() {
				if (!$('#template_propogation').is(':checked')) {
					for (var i = 0; i < templated_selectors.length; i++) {
						$( templated_selectors[i] ).removeAttr('disabled');
					}
				}else{
					for (var i = 0; i < templated_selectors.length; i++) {
						$( templated_selectors[i] ).attr('disabled', 'disabled');
					}
				}
			});
			</script>
			<?php
			print "</div>";
		}

		/* we will show the classic representation only when we are not templating */
		print "<div id='classic'>";

		?>
		<input type="hidden" id="graph_template_graph_id" name="graph_template_graph_id" value="<?php print (isset($graphs) ? $graphs["id"] : "0");?>">
		<input type="hidden" id="local_graph_template_graph_id" name="local_graph_template_graph_id" value="<?php print (isset($graphs) ? $graphs["local_graph_template_graph_id"] : "0");?>">
		<?php

		/* graph item list goes here */
		if (empty($graphs["graph_template_id"]) && sizeof($template) == 0) {
			item();
		}

		if (empty($graphs["graph_template_id"])) {
			html_start_box("<strong>Graph Configuration</strong>", "100%", $colors["header"], "3", "center", "");

			$form_array = array();

			while (list($field_name, $field_array) = each($struct_graph)) {
				if ($field_name != "title") {
					$form_array += array($field_name => $struct_graph[$field_name]);

					$form_array[$field_name]["value"]   = (isset($graphs) ? $graphs[$field_name] : "");
					$form_array[$field_name]["form_id"] = (isset($graphs) ? $graphs["id"] : "0");
	
					if (!(($use_graph_template == false) || ($graphs_template{"t_" . $field_name} == "on"))) {
						$form_array[$field_name]["method"]      = "template_" . $form_array[$field_name]["method"];
						$form_array[$field_name]["description"] = "";
					}
				}
			}

			draw_edit_form(
				array(
					"config" => array("no_form_tag" => true),
					"fields" => $form_array
					)
				);

			html_end_box(false);
		}

		form_hidden_box("save_component_graph","1","");
		form_hidden_box("save_component_input","1","");
		form_hidden_box("rrdtool_version", read_config_option("rrdtool_version"), "");
		aggregate_save_button($referer, "return", "id");

		echo "</div>";

		?>
		<script language="JavaScript">

		$().ready(function() {
			dynamic();
			if (!$('#templated')) {
				$('#local_graph_template_graph_id').next('table').css('display', 'none');
			}
		});

		$('#toggle_items').click(function() {
			if ($('#toggle_items').is(":contains('Show')")) {
				$('#local_graph_template_graph_id').next('table').css('display', '');
				$('#toggle_items').text('Hide Item Details');
			}else{
				$('#local_graph_template_graph_id').next('table').css('display', 'none');
				$('#toggle_items').text('Show Item Details');
			}
		});

		function dynamic() {
			if ($('#scale_log_units')) {
				$('#scale_log_units').attr('disabled', 'disabled');
				if (($('#rrdtool_version').val() != 'rrd-1.0.x') &&
					($('#auto_scale_log').is(':checked'))) {
					$('#scale_log_units').attr('disabled', 'disabled');
				}
			}
		}

		function changeScaleLog() {
			if ($('#scale_log_units')) {
				$('#scale_log_units').attr('disabled', 'disabled');
				if (($('#rrdtool_version').val() != 'rrd-1.0.x') &&
					($('#auto_scale_log').is(':checked'))) {
					$('#scale_log_units').removeAttr('disabled');
				}
			}
		}
		</script>
		<?php
	}
}

function aggregate_items() {
	global $colors, $agg_item_actions, $item_rows;

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var_request("rows"));
	input_validate_input_number(get_request_var_request("template_id"));
	input_validate_input_number(get_request_var_request("page"));
	/* ==================================================== */

	/* clean up search string */
	if (isset($_REQUEST["filter"])) {
		$_REQUEST["filter"] = sanitize_search_string(get_request_var("filter"));
	}

	/* clean up matching string */
	if (isset($_REQUEST["matching"])) {
		$_REQUEST["matching"] = sanitize_search_string(get_request_var("matching"));
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
	if (isset($_REQUEST["clear"])) {
		kill_session_var("sess_agraph_items_current_page");
		kill_session_var("sess_agraph_items_filter");
		kill_session_var("sess_agraph_items_sort_column");
		kill_session_var("sess_agraph_items_sort_direction");
		kill_session_var("sess_agraph_items_rows");
		kill_session_var("sess_agraph_items_matching");

		unset($_REQUEST["page"]);
		unset($_REQUEST["filter"]);
		unset($_REQUEST["sort_column"]);
		unset($_REQUEST["sort_direction"]);
		unset($_REQUEST["rows"]);
		unset($_REQUEST["matching"]);
	}

	/* remember these search fields in session vars so we don't have to keep passing them around */
	load_current_session_value("page",           "sess_agraph_items_current_page", "1");
	load_current_session_value("filter",         "sess_agraph_items_filter", "");
	load_current_session_value("sort_column",    "sess_agraph_items_sort_column", "title_cache");
	load_current_session_value("sort_direction", "sess_agraph_items_sort_direction", "ASC");
	load_current_session_value("rows",           "sess_agraph_items_rows", read_config_option("num_rows_graph"));
	load_current_session_value("matching",       "sess_agraph_items_matching", "on");

	/* if the number of rows is -1, set it to the default */
	if (get_request_var_request("rows") == -1) {
		$_REQUEST["rows"] = read_config_option("num_rows_graph");
	}

	?>
	<script type="text/javascript">
	<!--
	function applyFilterChange(objForm) {
		strURL = '?action=edit&tab=items&id=<?php print $_REQUEST['id'];?>&rows=' + objForm.rows.value;
		strURL = strURL + '&filter=' + objForm.filter.value;
		strURL = strURL + '&matching=' + objForm.matching.checked;
		document.location = strURL;
	}

	function clearFilter(objForm) {
		strURL = '?action=edit&tab=items&id=<?php print $_REQUEST['id'];?>&rows=-1&filter=&matching=true'
		document.location = strURL;
	}
	-->
	</script>
	<?php

	html_start_box("<strong>Matching Graphs</strong>", "100%", $colors["header"], "3", "center", "");

	?>
	<tr bgcolor="#<?php print $colors["panel"];?>">
		<td>
			<form name="form_graph_id" action="aggregate_graphs.php">
			<table cellpadding='1' cellspacing="0">
				<tr>
					<td width='1'>
						&nbsp;Search:&nbsp;
					</td>
					<td>
						<input type='text' name='filter' size='70' onChange='applyFilterChange(document.form_graph_id)' value='<?php print htmlspecialchars(get_request_var_request("filter"));?>'>
					</td>
					<td nowrap style='white-space: nowrap;' width='50'>
						&nbsp;Rows:&nbsp;
					</td>
					<td width='1'>
						<select name='rows' onChange='applyFilterChange(document.form_graph_id)'>
							<option value='-1'<?php if (get_request_var_request('rows') == '-1') {?> selected<?php }?>>Default</option>
							<?php
							if (sizeof($item_rows) > 0) {
								foreach ($item_rows as $key => $value) {
									print "<option value='" . $key . "'"; if (get_request_var_request("rows") == $key) { print " selected"; } print ">" . htmlspecialchars($value) . "</option>\n";
								}
							}
							?>
						</select>
					</td>
					<td width='1'>
						<input type='checkbox' name='matching' id='matching' onChange='applyFilterChange(document.form_graph_id)' <?php print ($_REQUEST['matching'] == 'on' || $_REQUEST['matching'] == 'true' ? ' checked':'');?>>
					</td>
					<td width='100'>
						&nbsp;<label style='white-space:nowrap;' for='matching'>Part of Aggregate</label>&nbsp;
					</td>
					<td width='100' nowrap style='white-space: nowrap;'>
						&nbsp;<input type='submit' value='Go' onClick='applyFilterChange(document.form_graph_id)' title='Set/Refresh Filters'>
						<input type='button' onClick='clearFilter(document.form_graph_id)' name='clear' value='Clear' title='Clear Filters'>
					</td>
				</tr>
			</table>
			<input type='hidden' name='page' value='1'>
			<input type='hidden' name='action' value='edit'>
			<input type='hidden' name='tab' value='items'>
			<input type='hidden' name='id' value='<?php print $_REQUEST['id'];?>'>
			</form>
		</td>
	</tr>
	<?php

	html_end_box(false);

	/* form the 'where' clause for our main sql query */
	if (strlen(get_request_var_request("filter"))) {
		$filters = explode(" ", get_request_var_request("filter"));
		$sql_where = "";
		$sql_where = aggregate_make_sql_where($sql_where, $filters, 'gtg.title_cache');

		//$sql_where = "WHERE (gtg.title_cache LIKE '%%" . get_request_var_request("filter") . "%%')";
	}else{
		$sql_where = "";
	}

	if (get_request_var_request('matching') != 'false') {
		$sql_where .= (strlen($sql_where) ? " AND":"WHERE") . " (agi.local_graph_id IS NOT NULL)";
	}

	$graph_template = db_fetch_cell("SELECT graph_template_id
		FROM plugin_aggregate_graphs AS ag
		WHERE ag.local_graph_id=" . $_REQUEST['id']);

	$aggregate_id   = db_fetch_cell("SELECT id FROM plugin_aggregate_graphs WHERE local_graph_id=" . $_REQUEST["id"]);

	if (!empty($graph_template)) {
		$sql_where .= (strlen($sql_where) ? " AND":"WHERE") . "(gtg.graph_template_id=$graph_template)";
	}

	/* print checkbox form for validation */
	print "<form name='chk' method='post' action='aggregate_graphs.php'>\n";

	html_start_box("", "100%", $colors["header"], "3", "center", "");

	$total_rows = db_fetch_cell("SELECT COUNT(gtg.id) AS total
		FROM graph_templates_graph AS gtg
		INNER JOIN graph_local AS gl
		ON gtg.local_graph_id=gl.id
		LEFT JOIN (SELECT DISTINCT local_graph_id FROM plugin_aggregate_graphs_items) AS agi
		ON gtg.local_graph_id=agi.local_graph_id
		$sql_where");

	$graph_list = db_fetch_assoc("SELECT
		gtg.id, gtg.local_graph_id, gtg.height, gtg.width, gtg.title_cache, agi.local_graph_id AS agg_graph_id
		FROM graph_templates_graph AS gtg
		INNER JOIN graph_local AS gl
		ON gtg.local_graph_id=gl.id
		LEFT JOIN (
			SELECT DISTINCT local_graph_id
			FROM plugin_aggregate_graphs_items
			WHERE aggregate_graph_id=$aggregate_id) AS agi
		ON gtg.local_graph_id=agi.local_graph_id
		$sql_where
		ORDER BY " . $_REQUEST["sort_column"] . " " . get_request_var_request("sort_direction") .
		" LIMIT " . (get_request_var_request("rows")*(get_request_var_request("page")-1)) . "," . get_request_var_request("rows"));

	/* generate page list */
	$url_page_select = get_page_list(get_request_var_request("page"), MAX_DISPLAY_PAGES, get_request_var_request("rows"), $total_rows, "aggregate_graphs.php?action=edit&tab=items&id=" . get_request_var_request("id") . "&filter=" . get_request_var_request("filter"));

	if ($total_rows > 0) {
		$nav = "<tr bgcolor='#" . $colors["header"] . "'>
				<td colspan='5'>
					<table width='100%' cellspacing='0' cellpadding='0' border='0'>
						<tr>
							<td align='left' class='textHeaderDark'>
								<strong>&lt;&lt; "; if (get_request_var_request("page") > 1) { $nav .= "<a class='linkOverDark' href='" . htmlspecialchars("aggregate_graphs.php?action=edit&tab=items&id=" . get_request_var_request("id") . "&filter=" . get_request_var_request("filter") . "&page=" . (get_request_var_request("page")-1)) . "'>"; } $nav .= "Previous"; if (get_request_var_request("page") > 1) { $nav .= "</a>"; } $nav .= "</strong>
							</td>\n
							<td align='center' class='textHeaderDark'>
								Showing Rows " . ((get_request_var_request("rows")*(get_request_var_request("page")-1))+1) . " to " . ((($total_rows < get_request_var_request("rows")) || ($total_rows < (get_request_var_request("rows")*get_request_var_request("page")))) ? $total_rows : (get_request_var_request("rows")*get_request_var_request("page"))) . " of $total_rows [$url_page_select]
							</td>\n
							<td align='right' class='textHeaderDark'>
								<strong>"; if ((get_request_var_request("page") * get_request_var_request("rows")) < $total_rows) { $nav .= "<a class='linkOverDark' href='" . htmlspecialchars("aggregate_graphs.php?action=edit&tab=items&id=" . get_request_var_request("id") . "&filter=" . get_request_var_request("filter") . "&page=" . (get_request_var_request("page")+1)) . "'>"; } $nav .= "Next"; if ((get_request_var_request("page") * get_request_var_request("rows")) < $total_rows) { $nav .= "</a>"; } $nav .= " &gt;&gt;</strong>
							</td>\n
						</tr>
					</table>
				</td>
			</tr>\n";
	}else{
		$nav = "<tr bgcolor='#" . $colors["header"] . "'>
				<td colspan='5'>
					<table width='100%' cellspacing='0' cellpadding='0' border='0'>
						<tr>
							<td align='center' class='textHeaderDark'>
								No Rows Found
							</td>\n
						</tr>
					</table>
				</td>
			</tr>\n";
	}

	print $nav;

	$display_text = array(
		"title_cache" => array("Graph Title", "ASC"),
		"local_graph_id" => array("ID", "ASC"),
		"agg_graph_id" => array("Included in Aggregate", "ASC"),
		"height" => array("Size", "ASC"));

	aggregate_header_sort_checkbox($display_text, get_request_var_request("sort_column"), get_request_var_request("sort_direction"), "action=edit&tab=items&id=" . get_request_var_request("id"), false);

	$i = 0;
	if (sizeof($graph_list) > 0) {
		foreach ($graph_list as $graph) {
			/* we're escaping strings here, so no need to escape them on form_selectable_cell */
			$template_name = ((empty($graph["name"])) ? "<em>None</em>" : htmlspecialchars($graph["name"]));
			form_alternate_row_color($colors["alternate"], $colors["light"], $i, 'line' . $graph["local_graph_id"]); $i++;
			form_selectable_cell(((get_request_var_request("filter") != "") ? aggregate_format_text(title_trim(htmlspecialchars($graph["title_cache"]), read_config_option("max_title_graph")),get_request_var_request("filter")) : title_trim(htmlspecialchars($graph["title_cache"]), read_config_option("max_title_graph"))), $graph["local_graph_id"]);
			form_selectable_cell($graph["local_graph_id"], $graph["local_graph_id"]);
			form_selectable_cell(($graph['agg_graph_id'] != '' ? "<span style='color:green;'><strong>Yes</strong></span>":"<span style='color:red;'><strong>No</strong></span>"), $graph["local_graph_id"]);
			form_selectable_cell($graph["height"] . "x" . $graph["width"], $graph["local_graph_id"]);
			form_checkbox_cell($graph["title_cache"], $graph["local_graph_id"]);
			form_end_row();
		}

		/* put the nav bar on the bottom as well */
		print $nav;
	}else{
		print "<tr><td><em>No Graphs Found</em></td></tr>";
	}

	html_end_box(false);

	/* add a list of tree names to the actions dropdown */
	add_tree_names_to_actions_array();

	/* draw the dropdown containing a list of available actions for this form */
	form_hidden_box("local_graph_id", $_REQUEST["id"], "");
	aggregate_actions_dropdown($agg_item_actions);

	print "</form>\n";
}

function aggregate_make_sql_where($sql_where, $items, $field) {
	if (strlen($sql_where)) {
		$sql_where .= " AND (";
	}else{
		$sql_where  = "WHERE (";
	}

	$indentation = 0;

	if (sizeof($items)) {
	foreach($items as $i) {
		$i = trim($i);
		while (substr($i,0,1) == "(") {
			$indentation++;
			$sql_where .= "(";
			$i = substr($i,1);
		}

		$split = strpos($i, ")");
		if ($split !== false) {
			$end = trim(substr($i,$split));
			$i   = substr($i,0,$split);
		}else{
			$end = '';
		}

		if (strlen($i)) {
			if (strtolower($i) == 'and') {
				$sql_where .= " AND ";
			}elseif (strtolower($i) == 'or') {
				$sql_where .= " OR ";
			}else{
				$sql_where .= $field . " LIKE '%%" . trim($i) . "%%'";
			}
		}

		if ($end != '') {
			while (substr($end,0,1) == ")") {
				$indentation--;
				$sql_where .= ")";
				$end = trim(substr($end,1));
			}
		}
	}
	}
	$sql_where .= ")";

	return trim($sql_where);
}

function aggregate_format_text($text, $filter) {
	$items = explode(" ", $filter);
	$tags  = array();
	foreach($items as $i) {
		$i = trim($i);
		$i = str_replace("(","",$i);
		$i = str_replace(")","",$i);
		if (strtolower($i) == "and" || strtolower($i) == "or") {
			continue;
		}

		if (substr_count($text, $i) !== false) {
			$tagno = rand();
			$tags[$tagno] = $i;
			$text = str_replace($i, "<<$tagno>>", $text);
		}
	}

	if (sizeof($tags)) {
	foreach($tags as $k => $t) {
		$text = str_replace("<<$k>>", "<span style='background-color: #F8D93D;'>" . $t . "</span>", $text);
	}
	}

	return $text;
}

function graph() {
	global $colors, $graph_actions, $item_rows;

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var_request("rows"));
	input_validate_input_number(get_request_var_request("template_id"));
	input_validate_input_number(get_request_var_request("page"));
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
	if (isset($_REQUEST["clear"])) {
		kill_session_var("sess_agraph_current_page");
		kill_session_var("sess_agraph_filter");
		kill_session_var("sess_agraph_sort_column");
		kill_session_var("sess_agraph_sort_direction");
		kill_session_var("sess_agraph_rows");
		kill_session_var("sess_agraph_template_id");

		unset($_REQUEST["page"]);
		unset($_REQUEST["filter"]);
		unset($_REQUEST["sort_column"]);
		unset($_REQUEST["sort_direction"]);
		unset($_REQUEST["rows"]);
		unset($_REQUEST["template_id"]);
	}

	/* remember these search fields in session vars so we don't have to keep passing them around */
	load_current_session_value("page",           "sess_agraph_current_page", "1");
	load_current_session_value("filter",         "sess_agraph_filter", "");
	load_current_session_value("sort_column",    "sess_agraph_sort_column", "title_cache");
	load_current_session_value("sort_direction", "sess_graph_sort_direction", "ASC");
	load_current_session_value("rows",           "sess_agraph_rows", read_config_option("num_rows_graph"));
	load_current_session_value("template_id",    "sess_agraph_template_id", "-1");

	/* if the number of rows is -1, set it to the default */
	if (get_request_var_request("rows") == -1) {
		$_REQUEST["rows"] = read_config_option("num_rows_graph");
	}

	?>
	<script type="text/javascript">
	<!--
	function applyGraphsFilterChange(objForm) {
		strURL = '?rows=' + objForm.rows.value;
		strURL = strURL + '&filter=' + objForm.filter.value;
		strURL = strURL + '&template_id=' + objForm.template_id.value;
		document.location = strURL;
	}
	-->
	</script>
	<?php

	html_start_box("<strong>Aggregate Graphs</strong>", "100%", $colors["header"], "3", "center", "");

	?>
	<tr bgcolor="#<?php print $colors["panel"];?>">
		<td>
			<form name="form_graph_id" action="aggregate_graphs.php">
			<table cellpadding="1" cellspacing="0">
				<tr>
					<td width="70">
						&nbsp;Template:&nbsp;
					</td>
					<td width="1">
						<select name="template_id" onChange="applyGraphsFilterChange(document.form_graph_id)">
							<option value="-1"<?php if (get_request_var_request("template_id") == "-1") {?> selected<?php }?>>Any</option>
							<option value="0"<?php if (get_request_var_request("template_id") == "0") {?> selected<?php }?>>None</option>
							<?php
							$templates = db_fetch_assoc("SELECT DISTINCT at.id, at.name
								FROM plugin_aggregate_graph_templates AS at
								INNER JOIN plugin_aggregate_graphs AS ag
								ON ag.aggregate_template_id=at.id
								ORDER BY name");

							if (sizeof($templates) > 0) {
								foreach ($templates as $template) {
									print "<option value='" . $template["id"] . "'"; if (get_request_var_request("template_id") == $template["id"]) { print " selected"; } print ">" . title_trim(htmlspecialchars($template["name"]), 40) . "</option>\n";
								}
							}
							?>
						</select>
					</td>
					<td width="120" nowrap style='white-space: nowrap;'>
						&nbsp;<input type="submit" value="Go" title="Set/Refresh Filters">
						<input type="submit" name="clear" value="Clear" title="Clear Filters">
					</td>
				</tr>
			</table>
			<table cellpadding="1" cellspacing="0">
				<tr>
					<td width="70">
						&nbsp;Search:&nbsp;
					</td>
					<td>
						<input type="text" name="filter" size="70" value="<?php print htmlspecialchars(get_request_var_request("filter"));?>">
					</td>
					<td nowrap style='white-space: nowrap;' width="50">
						&nbsp;Rows:&nbsp;
					</td>
					<td width="1">
						<select name="rows" onChange="applyGraphsFilterChange(document.form_graph_id)">
							<option value="-1"<?php if (get_request_var_request("rows") == "-1") {?> selected<?php }?>>Default</option>
							<?php
							if (sizeof($item_rows) > 0) {
								foreach ($item_rows as $key => $value) {
									print "<option value='" . $key . "'"; if (get_request_var_request("rows") == $key) { print " selected"; } print ">" . htmlspecialchars($value) . "</option>\n";
								}
							}
							?>
						</select>
					</td>
				</tr>
			</table>
			<input type='hidden' name='page' value='1'>
			</form>
		</td>
	</tr>
	<?php

	html_end_box(false);

	$sql_where = "WHERE (gtg.graph_template_id=0 AND gl.host_id=0)";
	/* form the 'where' clause for our main sql query */
	if (strlen(get_request_var_request("filter"))) {
		$sql_where .= " AND (gtg.title_cache LIKE '%%" . get_request_var_request("filter") . "%%'" .
			" OR ag.title_format LIKE '%%" . get_request_var_request("filter") . "%%')";
	}

	if (get_request_var_request("template_id") == "-1") {
		/* Show all items */
	}elseif (get_request_var_request("template_id") == "0") {
		$sql_where .= " AND (ag.aggregate_template_id=0 OR ag.aggregate_template_id IS NULL)";
	}elseif (!empty($_REQUEST["template_id"])) {
		$sql_where .= " AND ag.aggregate_template_id=" . get_request_var_request("template_id");
	}

	/* print checkbox form for validation */
	print "<form name='chk' method='post' action='aggregate_graphs.php'>\n";

	html_start_box("", "100%", $colors["header"], "3", "center", "");

	$total_rows = db_fetch_cell("SELECT COUNT(gtg.id) AS total
		FROM graph_templates_graph AS gtg
		INNER JOIN graph_local AS gl
		ON gtg.local_graph_id=gl.id
		LEFT JOIN plugin_aggregate_graphs AS ag
		ON gtg.local_graph_id=ag.local_graph_id
		LEFT JOIN plugin_aggregate_graph_templates AS agt
		ON agt.id=ag.aggregate_template_id
		$sql_where");

	$graph_list = db_fetch_assoc("SELECT
		gtg.id, gtg.local_graph_id, gtg.height, gtg.width, gtg.title_cache, agt.name
		FROM graph_templates_graph AS gtg
		INNER JOIN graph_local AS gl
		ON gtg.local_graph_id=gl.id
		LEFT JOIN plugin_aggregate_graphs AS ag
		ON gtg.local_graph_id=ag.local_graph_id
		LEFT JOIN plugin_aggregate_graph_templates AS agt
		ON agt.id=ag.aggregate_template_id
		$sql_where
		AND ag.id IS NOT NULL
		ORDER BY " . $_REQUEST["sort_column"] . " " . get_request_var_request("sort_direction") .
		" LIMIT " . (get_request_var_request("rows")*(get_request_var_request("page")-1)) . "," . get_request_var_request("rows"));

	/* generate page list */
	$url_page_select = get_page_list(get_request_var_request("page"), MAX_DISPLAY_PAGES, get_request_var_request("rows"), $total_rows, "aggregate_graphs.php?filter=" . get_request_var_request("filter"));

	if ($total_rows > 0) {
		$nav = "<tr bgcolor='#" . $colors["header"] . "'>
				<td colspan='5'>
					<table width='100%' cellspacing='0' cellpadding='0' border='0'>
						<tr>
							<td align='left' class='textHeaderDark'>
								<strong>&lt;&lt; "; if (get_request_var_request("page") > 1) { $nav .= "<a class='linkOverDark' href='" . htmlspecialchars("aggregate_graphs.php?filter=" . get_request_var_request("filter") . "&page=" . (get_request_var_request("page")-1)) . "'>"; } $nav .= "Previous"; if (get_request_var_request("page") > 1) { $nav .= "</a>"; } $nav .= "</strong>
							</td>\n
							<td align='center' class='textHeaderDark'>
								Showing Rows " . ((get_request_var_request("rows")*(get_request_var_request("page")-1))+1) . " to " . ((($total_rows < get_request_var_request("rows")) || ($total_rows < (get_request_var_request("rows")*get_request_var_request("page")))) ? $total_rows : (get_request_var_request("rows")*get_request_var_request("page"))) . " of $total_rows [$url_page_select]
							</td>\n
							<td align='right' class='textHeaderDark'>
								<strong>"; if ((get_request_var_request("page") * get_request_var_request("rows")) < $total_rows) { $nav .= "<a class='linkOverDark' href='" . htmlspecialchars("aggregate_graphs.php?filter=" . get_request_var_request("filter") . "&page=" . (get_request_var_request("page")+1)) . "'>"; } $nav .= "Next"; if ((get_request_var_request("page") * get_request_var_request("rows")) < $total_rows) { $nav .= "</a>"; } $nav .= " &gt;&gt;</strong>
							</td>\n
						</tr>
					</table>
				</td>
			</tr>\n";
	}else{
		$nav = "<tr bgcolor='#" . $colors["header"] . "'>
				<td colspan='5'>
					<table width='100%' cellspacing='0' cellpadding='0' border='0'>
						<tr>
							<td align='center' class='textHeaderDark'>
								No Rows Found
							</td>\n
						</tr>
					</table>
				</td>
			</tr>\n";
	}

	print $nav;

	$display_text = array(
		"title_cache" => array("Graph Title", "ASC"),
		"local_graph_id" => array("ID", "ASC"),
		"name" => array("Aggregate Template", "ASC"),
		"height" => array("Size", "ASC"));

	aggregate_header_sort_checkbox($display_text, get_request_var_request("sort_column"), get_request_var_request("sort_direction"), "filter=" . get_request_var_request("filter"), false);

	$i = 0;
	if (sizeof($graph_list) > 0) {
		foreach ($graph_list as $graph) {
			/* we're escaping strings here, so no need to escape them on form_selectable_cell */
			$template_name = ((empty($graph["name"])) ? "<em>None</em>" : htmlspecialchars($graph["name"]));
			form_alternate_row_color($colors["alternate"], $colors["light"], $i, 'line' . $graph["local_graph_id"]); $i++;
			form_selectable_cell("<a class='linkEditMain' href='" . htmlspecialchars("aggregate_graphs.php?action=edit&tab=details&reset=1&id=" . $graph["local_graph_id"]) . "' title='" . htmlspecialchars($graph["title_cache"]) . "'>" . ((get_request_var_request("filter") != "") ? aggregate_format_text(title_trim(htmlspecialchars($graph["title_cache"]), read_config_option("max_title_graph")),get_request_var_request("filter")) : title_trim(htmlspecialchars($graph["title_cache"]), read_config_option("max_title_graph"))) . "</a>", $graph["local_graph_id"]);
			form_selectable_cell($graph["local_graph_id"], $graph["local_graph_id"]);
			form_selectable_cell(((get_request_var_request("filter") != "") ? eregi_replace("(" . preg_quote(get_request_var_request("filter")) . ")", "<span style='background-color: #F8D93D;'>\\1</span>", $template_name) : $template_name), $graph["local_graph_id"]);
			form_selectable_cell($graph["height"] . "x" . $graph["width"], $graph["local_graph_id"]);
			form_checkbox_cell($graph["title_cache"], $graph["local_graph_id"]);
			form_end_row();
		}

		/* put the nav bar on the bottom as well */
		print $nav;
	}else{
		print "<tr><td><em>No Aggregate Graphs Found</em></td></tr>";
	}

	html_end_box(false);

	/* add a list of tree names to the actions dropdown */
	add_tree_names_to_actions_array();

	/* draw the dropdown containing a list of available actions for this form */
	aggregate_actions_dropdown($graph_actions);

	/* remove old graphs */
	purge_old_graphs();

	print "</form>\n";
}

function purge_old_graphs() {
	/* workaround to handle purged graphs */
	$old_graphs = array_rekey(db_fetch_assoc("SELECT DISTINCT local_graph_id
		FROM plugin_aggregate_graphs_items AS pagi
		LEFT JOIN graph_local AS gl ON pagi.local_graph_id=gl.id
		WHERE gl.id IS NULL AND local_graph_id>0"), "local_graph_id",  "local_graph_id");

	if (sizeof($old_graphs)) {
		db_execute("DELETE FROM plugin_aggregate_graphs_items
			WHERE local_graph_id IN (" . implode(",", $old_graphs) . ")");
	}

	$old_aggregates = array_rekey(db_fetch_assoc("SELECT DISTINCT local_graph_id
		FROM plugin_aggregate_graphs AS pag
		LEFT JOIN graph_local AS gl
		ON pag.local_graph_id=gl.id
		WHERE gl.id IS NULL AND local_graph_id>0"), "local_graph_id", "local_graph_id");

	$old_agg_ids = array_rekey(db_fetch_assoc("SELECT DISTINCT pag.id
		FROM plugin_aggregate_graphs AS pag
		LEFT JOIN graph_local AS gl
		ON pag.local_graph_id=gl.id
		WHERE gl.id IS NULL"), "id", "id");

	if (sizeof($old_aggregates)) {
		db_execute("DELETE FROM graph_templates_item 
			WHERE local_graph_id IN (" . implode(",", $old_aggregates) . ")");

		db_execute("DELETE FROM graph_templates_graph 
			WHERE local_graph_id IN (" . implode(",", $old_aggregates) . ")");

		db_execute("DELETE FROM plugin_aggregate_graphs 
			WHERE local_graph_id IN (" . implode(",", $old_aggregates) . ")");
	}

	if (sizeof($old_agg_ids)) {
		db_execute("DELETE FROM plugin_aggregate_graphs_items
			WHERE aggregate_graph_id IN (" . implode(",", $old_agg_ids) . ")");
	}
}

?>
