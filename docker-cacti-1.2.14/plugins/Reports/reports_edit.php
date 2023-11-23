<?php

include('functions.php');

chdir('../../');

include('./include/auth.php');
include_once('plugins/reports/functions.php');

$ds_actions = array(
	1 => 'Delete'
);

$action = '';
if (isset($_POST['action'])) {
	$action = $_POST['action'];
} else if (isset($_GET['action'])) {
	$action = $_GET['action'];
}


if (isset($_POST['report'])) {
	$report = $_POST['report'];
} else if (isset($_GET['report'])) {
	$report = $_GET['report'];
} else {
	Header('Location:reports.php');
	exit;
}


$sub = '';
if (isset($_POST['sub'])) {
	$sub = $_POST['sub'];
} else if (isset($_GET['sub'])) {
	$sub = $_GET['sub'];
}

if (isset($_POST['drp_action']) && $_POST['drp_action'] == 1) {
	$action = 'delete';
}

switch ($action) {
	case 'add': 
		report_template_type_add();
		break;
	case 'edit': 
		include_once('./include/top_header.php');
		template_edit();
		include_once('./include/bottom_footer.php');
		break;
	case 'swap':
		template_swap();
		break;
	case 'delete':
		template_delete();
		break;
	case 'preview':
		include_once('./include/top_header.php');
		templates();
		report_preview($report);
		include_once('./include/bottom_footer.php');
		break;
	default:
		include_once('./include/top_header.php');
		templates();
		include_once('./include/bottom_footer.php');
		break;
}

function report_template_type_add() {
	global $report;
	if (!isset($_POST['ttype'])) {
		$fields = array('friendly_name' => 'ttype',
				'method' => 'drop_array',
				'default' => 'NULL',
				'description' => '',
				'value' => '',
				'array' => array('graph' => 'Graph', 'text' => 'Text'));

		include_once('./include/top_header.php');
		html_start_box('', '50%', $colors['header'], '1', 'center', '');

		print "<table bgcolor='#FFFFFF' width='100%'><tr><td><form action=reports_edit.php method='post'>";

		print "<input type=hidden name='action' value='add'><input type=hidden name='report' value='$report'>";
		print "<center><h2>Report Addition Wizard</h2><br><br>";
		print "<table><tr><td colspan=2>Please select an Object<br><br></td></tr>";
		print "<tr><td style=\'white-space:nowrap;\'>Object</td><td>";
		draw_edit_control("ttype", $fields);
		print "</td></tr>";

		print "<tr><td colspan=2 align=center><br><br><input type='submit' name='Go' value='Go'>";
		print "</td></tr></table>";

		print '</form></center><br></td></tr></table>';

		html_end_box();
		include_once('./include/bottom_footer.php');
		return;
	}

	if ($_POST['ttype'] == 'graph') {
		template_add_graph();
		return;
	}

	if ($_POST['ttype'] == 'text') {
		template_add_text();
		return;
	}
}

function template_add_text() {
	global $report;
	if (!isset($_POST['text']) || !isset($_POST['align']) || !isset($_POST['size'])) {
		$alignment = array('friendly_name' => 'align',
				'method' => 'drop_array',
				'default' => 'NULL',
				'description' => '',
				'value' => '',
				'array' => array('L' => 'Left', 'C' => 'Center', 'R' => 'Right'));
		$sa = array();
		for ($a = 10; $a < 24; $a = $a + 2) {
			$sa[$a] = $a;
		}
		for ($a = 24; $a < 50; $a = $a + 4) {
			$sa[$a] = $a;
		}

		$size = array(
			'friendly_name' => 'size',
			'method' => 'drop_array',
			'default' => 'NULL',
			'description' => '',
			'value' => '',
			'array' => $sa
		);

		include_once('./include/top_header.php');
		html_start_box('', '50%', $colors['header'], '1', 'center', '');

		print "<table bgcolor='#FFFFFF' width='100%'><tr><td><form action=reports_edit.php method='post'>";

		print "<input type=hidden name='action' value='add'><input type=hidden name='ttype' value='text'><input type=hidden name='report' value='$report'>";
		print "<center><h2>Text Addition Wizard</h2><br><br>";
		print "<table>";
		print "<tr><td style=\'white-space:nowrap;\'>Alignment</td><td>";
		draw_edit_control("align", $alignment);
		print "</td></tr>";
		print "<tr><td style=\'white-space:nowrap;\'>Size</td><td>";
		draw_edit_control("size", $size);
		print "</td></tr>";
		print "<tr><td style=\'white-space:nowrap;\'>Text</td><td>";
		print "<input type=text name=text>";
		print "</td></tr>";

		print "<tr><td colspan=2 align=center><br><br><input type='submit' name='Go' value='Go'>";
		print "</td></tr></table>";

		print '</form></center><br></td></tr></table>';

		html_end_box();
		include_once('./include/bottom_footer.php');
		return;
	} else {
		// NEED TO ADD SECURITY CHECKS HERE!!!!
		$align = sql_sanitize($_POST['align']);
		$size = sql_sanitize($_POST['size']);
		$text = sql_sanitize($_POST['text']);


		$failed = false;
		$temp = db_fetch_assoc("select gorder from reports_data where reportid = $report order by gorder DESC LIMIT 1");
		if (isset($temp[0]['gorder']))
			$gorder = $temp[0]['gorder'] + 1;
		else
			$gorder = 1;
		$save['id'] = '';
		$save['reportid'] = $report;
		$save['hostid'] = 0;
		$save['local_graph_id'] = 0;
		$save['type'] = 0;
		$save['item'] = 'text';
		$save['data'] = $align . $size . $text;
		$save['gorder'] = $gorder;
		$gid = sql_save($save, 'reports_data');
		if (!$gid)
			$failed = true;

		if (!$failed) {
			Header("Location: reports_edit.php?report=$report");
			exit;
		} else {
			raise_message('report_graph_add');
			Header("Location: reports_edit.php?report=$report");
			exit;
		}
	}
}

function template_add_graph() {
	global $report;
	if (!isset($_POST['hostid'])) {

		$temp = db_fetch_assoc('select id, description from host order by description');
		$hosts = array();
		foreach ($temp as $d) {
			$hosts[$d['id']] = $d['description'];
		}

		$fields = array(
			'friendly_name' => 'hostid',
			'method' => 'drop_array',
			'default' => 'NULL',
			'description' => '',
			'value' => '',
			'array' => $hosts
		);

		include_once('./include/top_header.php');
		html_start_box('', '50%', $colors['header'], '1', 'center', '');

		print "<table bgcolor='#FFFFFF' width='100%'><tr><td><form action=reports_edit.php method='post'>";

		print "<input type=hidden name='action' value='add'><input type=hidden name='ttype' value='graph'><input type=hidden name='report' value='$report'>";
		print "<center><h2>Graph Addition Wizard</h2><br><br>";
		print "<table><tr><td colspan=2>Please select a Host<br><br></td></tr>";
		print "<tr><td style=\'white-space:nowrap;\'>Host</td><td>";
		draw_edit_control("hostid", $fields);
		print "</td></tr>";

		print "<tr><td colspan=2 align=center><br><br><input type='submit' name='Go' value='Go'>";
		print "</td></tr></table>";

		print '</form></center><br></td></tr></table>';

		html_end_box();
		include_once('./include/bottom_footer.php');
		return;
	} else if (isset($_POST['hostid']) && !isset($_POST['graph'])) {
		$hostid = intval($_POST['hostid']);
		$temp   = db_fetch_assoc("select local_graph_id from reports_data where hostid = $hostid and reportid = $report");
		$exists = array();
		foreach ($temp as $e) {
			$exists[] = $e['local_graph_id'];
		}

		$temp   = db_fetch_assoc("select id, description from host where id = $hostid");
		$hosts  = array();
		foreach ($temp as $d) {
				$hosts[$d['id']] = $d['description'];
		}

		$fields = array(
			'friendly_name' => 'Data Template',
			'method' => 'drop_array',
			'default' => 'NULL',
			'description' => 'Data Template that you are using. (This can not be changed)',
			'value' => '',
			'array' => $hosts
		);

		$temp   = db_fetch_assoc("select id from graph_local where host_id = $hostid");
		$graphs = array();
		$x      = 0;
		foreach ($temp as $d) {
			if (!in_array($d['id'], $exists)) {
				$gid = $d['id'];
				if ($x == 0)
					$sgid = $gid;
				$x++;
				$t = db_fetch_assoc("select title_cache from graph_templates_graph where local_graph_id = $gid");
				$graphs[$d['id']] = $t[0]['title_cache'];
			}
		}
		$gfields = array(
			'friendly_name' => 'Data Template',
			'method' => 'drop_array',
			'default' => 'NULL',
			'description' => 'Data Template that you are using. (This can not be changed)',
			'value' => '',
			'array' => $graphs
		);
		$rras = array(
			'friendly_name' => 'type',
			'method' => 'drop_multi_rra',
			'default' => '1',
			'description' => '',
			'sql_all' => 'select rra.id from rra order by id LIMIT 1',
			'value' => ''
		);

		if (count($graphs) < 1) {
			Header("Location:reports_edit.php?action=add&report=$report");
			exit;
		}
		include_once('./include/top_header.php');
		html_start_box('', '70%', $colors['header'], '1', 'center', '');

		print "<table bgcolor='#FFFFFF' width='100%'><tr><td><form action=reports_edit.php name=graphform method='post'>";

		print "<input type=hidden name='action' value='add'><input type=hidden name='ttype' value='graph'><input type=hidden name='report' value='$report'>";
		print '<center><h2>Graph Addition Wizard</h2><br><br>';
		print '<table><tr><td colspan=2>Please select a Graph<br><br></td></tr>';
		print '<tr><td style=\'white-space:nowrap;\'>Host</td><td>';
		draw_edit_control('hostid', $fields);
		print '</td></tr>';
		print '<tr><td style=\'white-space:nowrap;\'>Graph</td><td>';
		draw_edit_control('graph', $gfields);
		print '</td></tr>';
		print '<tr><td style=\'white-space:nowrap;\'>Type</td><td>';
		draw_edit_control('type', $rras);
		print '</td></tr>';

		print "<tr><td colspan=2 align=center><br><br><input type='submit' name='Go' value='Go'>";
		print '</td></tr></table>';

		print '</form></center><br></td></tr>';
		print "<tr><td><center><img id=graphi name=graphi src='../../graph_image.php?local_graph_id=$sgid&rra_id=0'><center><br><br></td></tr>";
		print '</table>';
		html_end_box();
		include_once('./include/bottom_footer.php');
?>
	<!-- Make it look intelligent :) -->
	<script language="JavaScript">
	function GraphImage()
	{
		var _f = document.graphform;
		var id = _f.graph.options[_f.graph.selectedIndex].value;
		document.graphi.src = "../../graph_image.php?local_graph_id=" + id + "&rra_id=0";
	}

	document.graphform.graph.onchange = GraphImage;

	</script>
<?
		return;
	} else {
		// NEED TO ADD SECURITY CHECKS HERE!!!!
		$type   = $_POST['type'];
		sort($type);
		$failed = false;
		foreach ($type as $t) {
			$temp = db_fetch_assoc("select gorder from reports_data where reportid = $report order by gorder DESC LIMIT 1");
			if (isset($temp[0]['gorder']))
				$gorder = $temp[0]['gorder'] + 1;
			else
				$gorder = 1;
			$save['id'] = '';
			$save['reportid'] = $report;
			$save['hostid'] = $_POST['hostid'];
			$save['local_graph_id'] = $_POST['graph'];
			$save['type'] = $t;
			$save['item'] = 'graph';
			$save['gorder'] = $gorder;
			$gid = sql_save($save, 'reports_data');
			if (!$gid)
				$failed = true;
		}

		if (!$failed) {
			Header("Location: reports_edit.php?report=$report");
			exit;
		} else {
			raise_message('report_graph_add');
			Header("Location: reports_edit.php?report=$report");
			exit;
		}

	}
}

function template_swap() {
	global $report;
	$id = $_GET['id'];
	$move = $_GET['move'];
	$t = db_fetch_assoc("select gorder from reports_data where reportid = $report order by gorder DESC LIMIT 1");
	$b = $t[0]['gorder'];

	$t = db_fetch_assoc("select gorder from reports_data where id = $id");
	$gorder = $t[0]['gorder'];
	$gorder2 = $gorder;
	if ($move == 'up' && $gorder != 1)
		$gorder2 = $gorder - 1;
	if ($move == 'down' && $gorder != $b)
		$gorder2 = $gorder + 1;

	$t = db_fetch_assoc("update reports_data set gorder = $gorder where reportid = $report and gorder = $gorder2");
	$t = db_fetch_assoc("update reports_data set gorder = $gorder2 where id = $id");
	
	Header("Location: reports_edit.php?report=$report");
	exit;
}

function template_delete() {
	global $report;
	foreach($_POST as $t=>$v) {
		if (substr($t, 0,4) == "chk_") {
			$id = substr($t, 4);
			$t = db_fetch_assoc("select gorder from reports_data where id = $id");
			$temp = db_fetch_assoc("delete from reports_data where id = $id");
			order_remove ($t[0]['gorder']);
		}
	}
	Header("Location: reports_edit.php?report=$report");
	exit;
}

function order_remove ($order) {
	global $report;
	$temp = db_fetch_assoc("select id, gorder from reports_data where reportid = $report and gorder > $order order by gorder");
	if (isset($temp[0]['gorder'])) {
		foreach ($temp as $t) {
			$j = db_fetch_assoc("update reports_data set gorder = $order where id = " . $t['id']);
			$order++;
		}
	}
}


function templates() {
	global $colors, $ds_actions, $id, $report, $action;

	html_start_box("<strong>Graphs</strong>", "100%", $colors["header"], "5", "center", "reports_edit.php?action=add&report=$report");

	html_header_checkbox(array('Host', 'Graph', 'Type', 'Object', ''));

	$temp = db_fetch_assoc('select id, description from host order by id');
	$hosts = array();
	foreach ($temp as $h) {
		$hosts[$h['id']] = $h['description'];
	}

	$temp = db_fetch_assoc('select local_graph_id, title_cache from graph_templates_graph');
	$graphs = array();
	foreach ($temp as $h) {
		$graphs[$h['local_graph_id']] = $h['title_cache'];
	}

	$template_list = db_fetch_assoc("select * from reports_data where reportid = $report order by gorder");

	$type = array('Daily', 'Weekly', 'Monthly', 'Yearly');
	$items = array('' => 'Graph', 'graph' => 'Graph', 'text' => 'Text', 'line' => 'Line');
	$alignment = array('L' => 'Left', 'C' => 'Center', 'R' => 'Right');
	$i = 0;
	print "<input type=hidden name=report value=$report>";
	if (sizeof($template_list) > 0) {
		foreach ($template_list as $template) {
			form_alternate_row_color($colors["alternate"],$colors["light"],$i);
			if ($template['item'] == 'text') {
				$align = substr($template['data'], 0, 1);
				$size = substr($template['data'], 1, 2);
				$text = substr($template['data'], 3);
			}
				?>
				<td>
					<?php 
						if ($template['item'] == '' || $template['item'] == 'graph')
							print ($template["hostid"] == 0 ? '' : $hosts[$template["hostid"]]); 
						if ($template['item'] == 'text')
							print $alignment[$align];

					?>
				</td>
				<td>
					<?php 
						if ($template['item'] == '' || $template['item'] == 'graph')
							print $graphs[$template["local_graph_id"]];
						if ($template['item'] == 'text')
							print $text;

					 ?>
				</td>
				<td>
					<?php 
						if ($template['item'] == '' || $template['item'] == 'graph')
							print ($template['type'] == 0 ? '' : $type[$template['type']-1]);
						if ($template['item'] == 'text')
							print $size;
					?>
				</td>
				<td>
					<?php print $items[$template['item']]; ?>
				</td>
				<td>
					<a href=reports_edit.php?action=swap&report=<?php print $report;?>&id=<?php print $template["id"];?>&move=down><img src=../../images/move_down.gif border=0></a>
					<a href=reports_edit.php?action=swap&report=<?php print $report;?>&id=<?php print $template["id"];?>&move=up><img src=../../images/move_up.gif border=0></a>
				</td>
				<td style="<?php print get_checkbox_style();?>" width="1%" align="right">
					<input type='checkbox' style='margin: 0px;' name='chk_<?php print $template["id"];?>' title="<?php print $template["id"];?>">
				</td>
			</tr>
			<?php
			$i++;
		}
	}else{
		print "<tr><td><em>No Graphs</em></td></tr>\n";
	}
	html_end_box(false);

	rdraw_actions_dropdown($ds_actions);

	print "</form>\n";
	if ($action != 'preview') {
		print "<br><br><br><center><a href='reports_edit.php?action=preview&report=$report'>Preview Report</a></center>";
	}
	print "<br><center><a href='reports.php'>All Reports</a></center>";
}

?>
