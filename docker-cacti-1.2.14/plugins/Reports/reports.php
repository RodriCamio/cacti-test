<?php

chdir('../../');

include("./include/auth.php");
include_once("plugins/reports/functions.php");

$ds_actions = array(
	1 => "Send Now",
	2 => "Delete"
);

$rtypes = array('attach' => 'Inline PNG Images','pdf' => 'PDF Attachment','jpeg' => 'Inline JPEG Image');

$action = "";
if (isset($_POST['action'])) {
	$action = $_POST['action'];
} else if (isset($_GET['action'])) {
	$action = $_GET['action'];
}

$sub = "";
if (isset($_POST['sub'])) {
	$sub = $_POST['sub'];
} else if (isset($_GET['sub'])) {
	$sub = $_GET['sub'];
}

if (isset($_POST['drp_action']) && $_POST['drp_action'] == 2) {
	$action = 'delete';
}

if (isset($_POST['drp_action']) && $_POST['drp_action'] == 1) {
	$action = 'sendnow';
}


switch ($action) {
	case 'add': 
		template_add();
		break;
	case 'edit':
		template_edit();
		break;
	case 'delete':
		template_delete();
		break;
	case 'sendnow':
		include_once($config["base_path"] . '/plugins/reports/functions.php');
		include_once($config["base_path"] . "/lib/rrd.php");
		report_sendnow();
		break;
	default:
		include_once("./include/top_header.php");
		templates();
		include_once("./include/bottom_footer.php");
		break;
}

function template_add() {
	global $rtypes;
	if (!isset($_POST['name'])) {

		$rname = array('friendly_name' => 'name',
				'method' => 'textbox',
				'default' => 'New Report',
				'description' => '',
				'max_length' => 99,
				'size' => 60,
				'value' => '' );
		$rhours = array('friendly_name' => 'htime',
				'method' => 'drop_array',
				'default' => 'NULL',
				'description' => '',
				'value' => '',
				'array' => array('00','01','02','03','04','05','06','07','08','09','10','11','12','13','14','15','16','17','18','19','20','21','22','23'));
		$rdaytype = array('friendly_name' => 'mdaytype',
				'method' => 'drop_array',
				'default' => 'Everyday',
				'description' => '',
        			'value' => '',
				'array' => array('0' => 'Everyday', '1' => 'Monday','2' => 'Tuesday','3' => 'Wednesday','4' => 'Thursday', '5' =>'Friday', '6' => 'Saturday', '7' => 'Sunday', '8' => '1stofMonth'));
		$rmins = array('friendly_name' => 'mtime',
				'method' => 'drop_array',
				'default' => 'NULL',
				'description' => '',
				'value' => '',
				'array' => array('00','15','30','45'));
		$remail = array('friendly_name' => 'email',
				'method' => 'textarea',
				'default' => '',
				'description' => '',
				'max_length' => 255,
				'textarea_rows' => 4,
				'textarea_cols' => 60,
				'value' => '' );
		$rtype = array('friendly_name' => 'rtype',
				'method' => 'drop_array',
				'default' => 'NULL',
				'description' => '',
				'value' => '',
				'array' => $rtypes);


		include_once('./include/top_header.php');
		html_start_box('', '50%', $colors['header'], '1', 'center', '');
		print "<table bgcolor='#FFFFFF' width='100%'><tr><td><form action=reports.php method='post'>";
		print "<input type=hidden name='action' value='add'>";
		print "<center><h2>Report Wizard</h2><br><br>";
		print '<table><tr><td style=\'white-space:nowrap;\'>Report Name</td><td>';
		draw_edit_control('name', $rname);
		print '</td></tr>';
		print '<tr><td style=\'white-space:nowrap;\'>Send at</td><td>';
		draw_edit_control('hour', $rhours);
		print ':';
		draw_edit_control('minute', $rmins);
		print '</td></tr>';
		print '<tr><td style=\'white-space:nowrap;\'>Send on what day?</td><td>';
		draw_edit_control('daytype', $rdaytype);
		print '</td></tr>';
		print '<tr><td style=\'white-space:nowrap;\'>Report Format</td><td>';
		draw_edit_control('rtype', $rtype);
		print '</td></tr>';
		print '<tr><td style=\'white-space:nowrap;\'>Email Address</td><td>';
		draw_edit_control('email', $remail);
		print '</td></tr>';
		print "<tr><td colspan=2 align=center><br><br><input type='submit' value='Go' name='Go'>";
		print '</td></tr></table>';

		print '</form></center><br></td></tr></table>';
		html_end_box();
		include_once('./include/bottom_footer.php');
		return;

	} else {
		$save['id']       = '';
		$save['name']     = sql_sanitize($_POST['name']);
		$save['hour']     = form_input_validate($_POST['hour'], 'hour', '', false, 3);
		$save['minute']   = form_input_validate($_POST['minute'], 'minute', '', false, 3);
		$save['daytype']  = form_input_validate($_POST['daytype'], 'daytype', '', false, 3);

		if ($_POST['rtype'] != 'attach' && $_POST['rtype'] != 'pdf' && $_POST['rtype'] != 'jpeg')
			$_POST['rtype'] = 'attach';

		$save['rtype']    = sql_sanitize($_POST['rtype']);
		$save['email']    = sql_sanitize($_POST['email']);
		$save['lastsent'] = 0;
		$id = sql_save($save, 'reports');

		if ($id) {
			Header('Location: reports_edit.php?report=$id');
			exit;
		} else {
			raise_message('report_create');
			Header('Location: reports.php?action=add');
			exit;
		}
	}
}

function template_edit() {
	global $rtypes;
	if (isset($_GET['id'])) {
		$id =  sql_sanitize($_GET['id']);
		$info = db_fetch_assoc("select * from reports where id = $id");
		$rname = array('friendly_name' => 'name',
				'method' => 'textbox',
				'description' => '',
				'max_length' => 99,
				'size' => 60,
				'value' => $info[0]['name'] );
		$rhours = array('friendly_name' => 'htime',
				'method' => 'drop_array',
				'description' => '',
				'value' => $info[0]['hour'],
				'array' => array('00','01','02','03','04','05','06','07','08','09','10','11','12','13','14','15','16','17','18','19','20','21','22','23'));
		$rmins = array('friendly_name' => 'mtime',
				'method' => 'drop_array',
				'description' => '',
				'value' => $info[0]['minute'],
				'array' => array('00','15','30','45'));
		$rdaytype = array('friendly_name' => 'mdaytype',
				'method' => 'drop_array',
				'default' => 'Everyday',
				'description' => '',
				'value' => $info[0]['daytype'],
				'array' => array('0' => 'Everyday', '1' => 'Monday','2' => 'Tuesday','3' => 'Wednesday','4' => 'Thursday', '5' =>'Friday', '6' => 'Saturday', '7' => 'Sunday', '8' => '1stofMonth'));
		$remail = array('friendly_name' => 'email',
				'method' => 'textarea',
				'description' => '',
				'max_length' => 255,
				'textarea_rows' => 4,
				'textarea_cols' => 60,
				'value' => $info[0]['email'] );
		$rtype = array('friendly_name' => 'rtype',
				'method' => 'drop_array',
				'default' => 'NULL',
				'description' => '',
				'value' => $info[0]['rtype'],
				'array' => $rtypes);

		include_once('./include/top_header.php');
		html_start_box('', '50%', $colors['header'], '1', 'center', '');
		print "<table bgcolor='#FFFFFF' width='100%'><tr><td><form action=reports.php method='post'>";
		print "<input type=hidden name='action' value='edit'><input type=hidden name=id value=$id>";
		print '<center><h2>Report Wizard</h2><br><br>';
		print '<table><tr><td style=\'white-space:nowrap;\'>Report Name</td><td>';
		draw_edit_control('name', $rname);
		print '</td></tr>';
		print '<tr><td style=\'white-space:nowrap;\'>Send at</td><td>';
		draw_edit_control('hour', $rhours);
		print ':';
		draw_edit_control('minute', $rmins);
		print '</td></tr>';
		print '<tr><td style=\'white-space:nowrap;\'>Send on what day?</td><td>';
		draw_edit_control('daytype', $rdaytype);
		print '</td></tr>';
		print '<tr><td style=\'white-space:nowrap;\'>Report Format</td><td>';
		draw_edit_control('rtype', $rtype);
		print '</td></tr>';
		print '<tr><td style=\'white-space:nowrap;\'>Email Address</td><td>';
		draw_edit_control('email', $remail);
		print '</td></tr>';
		print "<tr><td colspan=2 align=center><br><br><input type='submit' value='Go' name='Go'>";
		print '</td></tr></table>';

		print '</form></center><br></td></tr></table>';
		html_end_box();
		include_once('./include/bottom_footer.php');
		return;

	} else {
		$save['id']       = sql_sanitize($_POST['id']);
		$save['name']     = sql_sanitize($_POST['name']);
		$save['hour']     = form_input_validate($_POST['hour'], 'hour', '', false, 3);
		$save['minute']   = form_input_validate($_POST['minute'], 'minute', '', false, 3);
		$save['daytype']  = form_input_validate($_POST['daytype'], 'daytype', '', false, 3);
		if ($_POST['rtype'] != 'attach' && $_POST['rtype'] != 'pdf' && $_POST['rtype'] != 'jpeg')
			$_POST['rtype'] = 'attach';
		$save['rtype']    = sql_sanitize($_POST['rtype']);
		$save['email']    = sql_sanitize($_POST['email']);
		$save['lastsent'] = 0;
		sql_save($save, 'reports');

		Header('Location: reports.php');
		exit;
	}
}

function template_delete() {
	foreach($_POST as $t=>$v) {
		if (substr($t, 0,4) == 'chk_') {
			$id = substr($t, 4);
			$temp = db_fetch_assoc("delete from reports where id = $id LIMIT 1");
			$temp = db_fetch_assoc("delete from reports_data where reportid = $id");
		}
	}

	Header('Location: reports.php');
	exit;
}

function templates() {
	global $colors, $ds_actions, $rtypes;

	html_start_box('<strong>Reports</strong>', '100%', $colors['header'], '5', 'center', 'reports.php?action=add');

	html_header_checkbox(array('','Name', 'Time', 'Day', 'Type', 'Email'));

	$template_list = db_fetch_assoc('select * from reports order by name');

	$i = 0;
	$m = array('00','15','30','45');
      $d = array('Everyday','Mondays','Tuesdays','Wednesdays','Thursdays','Fridays','Saturdays','Sundays','1st of Each Month');

	if (sizeof($template_list) > 0) {
	foreach ($template_list as $template) {
		form_alternate_row_color($colors['alternate'],$colors['light'],$i);
			?>
			<td width=15>
				<a class="linkEditMain" href="reports.php?action=edit&id=<?php print $template["id"];?>"><img src='images/edit.gif' border=0></a>
			</td>
			<td>
				<a class="linkEditMain" href="reports_edit.php?report=<?php print $template["id"];?>"><?php print $template["name"];?></a>
			</td>
			<td>
				<?php print $template['hour'] . ':' . $m[$template['minute']]; ?>
			</td>
			<td>
				<?php print $d[$template['daytype']]; ?>
			</td>
			<td>
				<?php print $rtypes[$template['rtype']]; ?>
			</td>
			<td>
				<?php print $template['email'];?>
			</td>
			<td style="<?php print get_checkbox_style();?>" width="1%" align="right">
				<input type='checkbox' style='margin: 0px;' name='chk_<?php print $template["id"];?>' title="<?php print $template["name"];?>">
			</td>
		</tr>
		<?php
		$i++;
	}
	}else{
		print "<tr><td><em>No Reports</em></td></tr>\n";
	}
	html_end_box(false);

	/* draw the dropdown containing a list of available actions for this form */
	rdraw_actions_dropdown($ds_actions);

	print "</form>\n";
}
