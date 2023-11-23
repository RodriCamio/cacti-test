<?php
/*
   +-------------------------------------------------------------------------+
   | Copyright (C) 2004-2012 The Cacti Group                                 |
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

include_once('./include/auth.php');
include_once(REPORTIT_BASE_PATH . '/lib_int/funct_validate.php');
include_once(REPORTIT_BASE_PATH . '/lib_int/funct_html.php');
include_once(REPORTIT_BASE_PATH . '/lib_int/funct_online.php');
include_once(REPORTIT_BASE_PATH . '/lib_int/funct_calculate.php');
include_once(REPORTIT_BASE_PATH . '/lib_int/funct_shared.php');
include_once(REPORTIT_BASE_PATH . '/lib_int/const_measurands.php');
include_once(REPORTIT_BASE_PATH . '/lib_int/const_variables.php');

/* ======== Validation ======== */
	safeguard_xss();
/* ============================ */

if (!isset($_REQUEST['action'])) {
	$_REQUEST['action'] = '';
}

switch ($_REQUEST['action']) {
	case 'actions':
		form_actions();
		break;
	case 'variable_edit':
		include_once(CACTI_INCLUDE_PATH . "/top_header.php");
		variable_edit();
		include_once(CACTI_INCLUDE_PATH . "/bottom_footer.php");
		break;
	case 'save':
		form_save();
		break;
	default:
		include_once(CACTI_INCLUDE_PATH . "/top_header.php");
		standard();
		include_once(CACTI_INCLUDE_PATH . "/bottom_footer.php");
		break;
}


function standard() {

	global $colors, $variable_actions, $link_array, $list_of_modes, $var_types, $desc_array;

	$affix = '';

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var('id'));
	if(isset($_GET['sort']) && isset($_GET['mode'])) {
		if(!in_array($_GET['sort'], $link_array) || !in_array($_GET['mode'], $list_of_modes)) {
			die_html_custom_error();
		}else {
			$affix = " ORDER BY {$_GET['sort']} {$_GET['mode']}";
		}
	}
	/* ==================================================== */

	$variables_list	= db_fetch_assoc('SELECT * FROM reportit_variables WHERE template_id=' . $_GET['id'] . $affix);
	$template_name	= db_fetch_cell('SELECT description FROM reportit_templates WHERE id=' . $_GET['id']);

	$i = 0;

	//Number of variables
	$number_of_variables = count($variables_list);
	$header_label	= "<b>Variables </b>[Template:<a style='color:yellow' href='cc_templates.php?action=template_edit&id={$_GET['id']}'>$template_name</a>] [$number_of_variables]";

	html_start_box("$header_label", REPORTIT_WIDTH, $colors["header"], "2", "center", "cc_variables.php?action=variable_edit&template_id={$_GET['id']}");

	html_header_checkbox(html_sorted_with_arrows( $desc_array, $link_array, 'cc_variables.php', $_GET['id']));

	if (sizeof($variables_list) > 0) {
		foreach($variables_list as $variable) {
			form_alternate_row_color($colors["alternate"], $colors["light"], $i, $variable["id"]); $i++;
			?>
			<td>
				<a class='linkEditMain' href="cc_variables.php?action=variable_edit&id=<?php print $variable['id'];?>">
					<?php print $variable['name'];?>
				</a>
			</td>
			<td><?php print $variable['abbreviation'];?></td>
			<td><?php print $variable['max_value'];?></td>
			<td><?php print $variable['min_value'];?></td>
			<td><?php print $variable['default_value'];?></td>
			<td><?php print $var_types[$variable['input_type']];?></td>
			<td style="<?php print get_checkbox_style();?>" width='1%' align='right'>
				<input type='checkbox' style='margin: 0px;' name='chk_<?php print $variable['id'];?>' title='Select'>
			</td>
			</tr>
			<?php
		}
	}else {
		print "<tr><td><em>No variables</em></td></tr>\n";
	}

	$form_array = array('id' => array('method'	=>'hidden_zero', 'value' =>$_GET['id']));
	draw_edit_form(array( 'config' => array(),'fields' => $form_array));

	html_end_box(true);
	draw_custom_actions_dropdown($variable_actions, 'cc_templates.php');
}



function form_save() {
	global $colors;

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var('id'));
	input_validate_input_number(get_request_var('template_id'));

	form_input_validate($_POST['variable_name'], 'variable_name', '^[a-zA-Z0-9[:space:]]+$', false, 3);
	form_input_validate($_POST['variable_description'], 'variable_description', '[a-zA-Z0-9\n\r]+', false, 3);
	form_input_validate($_POST['variable_maximum'], 'variable_maximum', '^[-]?[0-9]+[.]?[0-9]*$', false, 3);
	form_input_validate($_POST['variable_minimum'], 'variable_minimum', '^[-]?[0-9]+[.]?[0-9]*$', false, 3);
	form_input_validate($_POST['variable_default'], 'variable_default', '^[-]?[0-9]+[.]?[0-9]*$', false, 3);
	form_input_validate($_POST['variable_type'], 'variable_type', '^[1-2]$', false, 3);
	if($_POST['variable_type'] == 1) {
		form_input_validate($_POST['variable_stepping'], 'variable_stepping', '^[0-9]+[.]?[0-9]*$', false, 3);
	}
	/* ==================================================== */


	//Check defined variable
	if(!($_POST['variable_maximum'] > $_POST['variable_minimum']))
	session_custom_error_message('variable_maximum', 'Maximum has to be greater than minimum.');
	if(!($_POST['variable_minimum'] <= $_POST['variable_default'] && $_POST['variable_default']<= $_POST['variable_maximum']))
	session_custom_error_message('variable_default', 'Default value is out of values range.');
	if($_POST['variable_type'] == 1) {
		if( !($_POST['variable_stepping'] > 0) ||
		!($_POST['variable_stepping'] <= ($_POST['variable_maximum'] - $_POST['variable_minimum'])))
		session_custom_error_message('variable_stepping', 'Invalid step.');
	}

	$variable_data					= array();
	$variable_data['id']			= $_POST['id'];
	$variable_data['name']			= $_POST['variable_name'];
	$variable_data['template_id']	= $_POST['template_id'];
	$variable_data['description']	= $_POST['variable_description'];
	$variable_data['max_value']		= $_POST['variable_maximum'];
	$variable_data['min_value']		= $_POST['variable_minimum'];
	$variable_data['default_value']	= $_POST['variable_default'];
	$variable_data['input_type']	= $_POST['variable_type'];
	if(isset($_POST['variable_stepping'])) {
		$variable_data['stepping']	= $_POST['variable_stepping'];
	}

	if(is_error_message()) {
		header("Location: cc_variables.php?action=variable_edit&id={$_POST['id']}&template_id={$_POST['template_id']}");
	}else {
		//Save data
		$var_id = sql_save($variable_data, 'reportit_variables');
		if($_POST['id'] == 0) db_execute("UPDATE reportit_variables SET abbreviation = 'c". $var_id .
		"v' WHERE id = $var_id");

		//If its a new one we've to create the entries for all the reports
		//using this template.
		if($_POST['id'] == 0) create_rvars_entries($var_id, $variable_data['template_id'], $variable_data['default_value']);

		//Return to list view if it was an existing report
		header("Location: cc_variables.php?&id={$_POST['template_id']}");
		raise_message(1);
	}
}



function variable_edit() {

	global $colors, $template_actions, $var_types;

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var('id'));
	/* ==================================================== */

	if (!empty($_GET['id'])) {
		$variable_data		= db_fetch_row('SELECT * FROM reportit_variables WHERE id=' . $_GET['id']);
		$header_label 		= '[edit: ' . $variable_data['name'] . ']';

	}else {
		$header_label 		= '[new]';
	}

	session_custom_error_display();


	$variable_id 	= (isset($_GET['id']) ? $_GET['id'] : '0');
	$template_id	= (isset($_GET['template_id']) ? $_GET['template_id'] : $variable_data['template_id']);

	$form_array = array(
	'id'			=> array(
	'method'		=> 'hidden_zero',
	'value'			=> $variable_id
	),
	'template_id'	=> array(
	'method'		=> 'hidden_zero',
	'value'			=> $template_id
	),
	'variable_header'	=> array(
	'friendly_name'	=> 'General',
	'method'		=> 'spacer'
			    ),
	'variable_abbreviation'	=> array(
	'friendly_name' 	=> 'Internal name',
	'method' 		=> 'custom',
	'max_length'	=> '100',
	'description'	=> 'The internal name that should be used to call this variable<br> out of a formula.',
	'value' 		=> (isset($variable_data['abbreviation']) ? $variable_data['abbreviation'] : '-Available after first saving-')
	),
	'variable_name'	=> array(
	'friendly_name' => 'Name',
	'method' 		=> 'textbox',
	'max_length'	=> '100',
	'description'	=> 'The name that should be used as headline.',
	'value' 		=> (isset($variable_data['name']) ? $variable_data['name'] : '')
	),
	'variable_description'	=> array(
	'friendly_name'	=> 'Description',
	'description'	=> 'A short, pithy description that explains the sense of this variable.',
	'method'		=> 'textarea',
	'textarea_rows'	=> '2',
	'textarea_cols'	=> '50',
	'default'		=> 'Your description',
	'value'			=> (isset($variable_data['description']) ? $variable_data['description'] : '')
	),
	'variable_maximum'	=> array(
	'friendly_name' => 'Maximum Value',
	'method' 		=> 'textbox',
	'max_length'	=> '10',
	'description'	=> 'Define the maximum value the variable can get.',
	'value' 		=> (isset($variable_data['max_value']) ? $variable_data['max_value'] : '')
	),
	'variable_minimum'	=> array(
	'friendly_name' => 'Minimum Value',
	'method' 		=> 'textbox',
	'max_length'	=> '10',
	'description'	=> 'Define the minimum value the variable can get.',
	'value' 		=> (isset($variable_data['min_value']) ? $variable_data['min_value'] : '')
	),
	'variable_default'	=> array(
	'friendly_name' => 'Default Value',
	'method' 		=> 'textbox',
	'max_length'	=> '10',
	'description'	=> 'Define the default value.',
	'value' 		=> (isset($variable_data['default_value']) ? $variable_data['default_value'] : '')
	),
	'variable_type'	=> array(
	'friendly_name' => 'Type',
	'description'	=> 'The method the report owner should use to define this variable.',
	'method' 		=> 'drop_array',
	'array'			=> $var_types,
	'value' 		=> (isset($variable_data['input_type']) ? $variable_data['input_type'] : '')
	),
	'variable_stepping'	=> array(
	'friendly_name' => 'Stepping',
	'method' 		=> 'textbox',
	'max_length'	=> '10',
	'description'	=> 'Define the step (only positive) the values should increase<br>within the values range
						     that has been defined above.',
	'value' 		=> (isset($variable_data['stepping']) && $variable_data['stepping']) ? $variable_data['stepping'] : ''
	),
	);

	?>
	<script language="JavaScript">
	$(document).ready(function(){

		function change_variable_type(){
			if($("#variable_type").val() == 2) {
				$("#variable_stepping").attr("disabled", "disabled");
			}else {
				$("#variable_stepping").removeAttr("disabled");
			}
		};

		$("#variable_type").change(function(){ change_variable_type(); });

		/* initiate settings */
		change_variable_type();
	});
	</script>
	<?php

	html_start_box("<strong>Variable configuration</strong> $header_label", REPORTIT_WIDTH, $colors["header"], "2", "center", "");

	draw_edit_form(array(
	'config' => array(),
	'fields' => $form_array
	));
	html_end_box();

	form_save_button("cc_variables.php?&id=$template_id");
}


function form_actions() {
	global $colors, $variable_actions, $config;
	$error = FALSE;

	// ================= input validation =================
	input_validate_input_number(get_request_var('id'));
	input_validate_input_number(get_request_var_post('drp_action'));
	// ====================================================


	if (isset($_POST['selected_items'])) {
		$selected_items = unserialize(stripslashes($_POST['selected_items']));

		if ($_POST['drp_action'] == '1') { // delete variables
			$sql = 'DELETE FROM reportit_variables WHERE ' . array_to_sql_or($selected_items, 'id');
			db_execute($sql);
			$sql = 'DELETE FROM reportit_rvars WHERE ' . array_to_sql_or($selected_items, 'variable_id');
			db_execute($sql);
		}
		header("Location: cc_variables.php?id={$_POST['id']}");
		exit;
	}


	//Set preconditions
	$ds_list = ''; $i = 0;

	foreach($_POST as $key => $value) {
		if(strstr($key, 'chk_')) {
			//Fetch report id
			$id = substr($key, 4);
			$variable_ids[] = $id;
			// ================= input validation =================
			input_validate_input_number($id);
			// ====================================================

			//Fetch report description
			$variable_description 	= db_fetch_cell('SELECT name FROM reportit_variables WHERE id=' . $id);
			$ds_list[$variable_description] = '';
		}
	}
	include_once(CACTI_INCLUDE_PATH . '/top_header.php');
	html_start_box("<strong>" . $variable_actions{$_POST["drp_action"]} . "</strong>", "60%", $colors["header_panel"], "2", "center", "");
	print "<form action='cc_variables.php' method='post'>\n";


	if ($_POST['drp_action'] == '1') { //DELETE REPORT
		print "<tr>
					<td bgcolor='#" . $colors["form_alternate1"]. "'>
					<p>Are you sure you want to delete the following variables?</p>";
		if(is_array($ds_list)) {

			//Check possible dependences for each variable
			foreach($variable_ids as $id) {
				$sql 	= "SELECT abbreviation from reportit_variables WHERE id=$id";
				$name 	= db_fetch_cell($sql);
				$sql 	= "SELECT COUNT(*) from reportit_measurands WHERE template_id={$_POST['id']}
					   AND calc_formula LIKE '%$name%'";
				$count	= db_fetch_cell($sql);
				if($count != 0) {
					$error = TRUE;
					break;
				}
			}
			if(!$error){
				print	"<p>List of selected variables:<br>";
				foreach($ds_list as $key => $value) {
					print	"&#160 |_Variable : $key<br>";
				}
			}
		}
		print "		</td>
				</tr>";

		if (!is_array($ds_list) || $error == TRUE) {
			if($error) {
				print "<tr><td bgcolor='#" . $colors['form_alternate1']. "'><span class='textError'>There are one or more variables in use.</span></td></tr>\n";
			}else {
				print "<tr><td bgcolor='#" . $colors['form_alternate1']. "'><span class='textError'>You must select at least one variable.</span></td></tr>\n";
			}
			$save_html = "<input type='button' value='Cancel' onClick='window.history.back()'>";
		}else {
			$save_html = "<input type='button' value='Cancel' onClick='window.history.back()'>&nbsp;<input type='submit' value='Continue' title='Delete Template Variables'>";
		}

	}

	print "<tr>
				<td align='right' bgcolor='#eaeaea'>
					<input type='hidden' name='id' value='{$_POST['id']}'>
					<input type='hidden' name='action' value='actions'>
					<input type='hidden' name='selected_items' value='" . (isset($variable_ids) ? serialize($variable_ids) : '') . "'>
					<input type='hidden' name='drp_action' value='" . $_POST["drp_action"] . "'>
					$save_html
				</td>
			</tr>";

	html_end_box();
	include_once(CACTI_BASE_PATH . '/include/bottom_footer.php');
}