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
include_once(REPORTIT_BASE_PATH . '/lib_int/funct_shared.php');
include_once(REPORTIT_BASE_PATH . '/lib_int/funct_online.php');
include_once(REPORTIT_BASE_PATH . '/lib_int/const_runtime.php');
include_once(REPORTIT_BASE_PATH . '/lib_int/const_measurands.php');
include_once(REPORTIT_BASE_PATH . '/lib_int/funct_calculate.php');
include_once(REPORTIT_BASE_PATH . '/lib_int/funct_runtime.php');
include_once(REPORTIT_BASE_PATH . '/lib_int/funct_html.php');
include_once(REPORTIT_BASE_PATH . '/runtime.php');

/* ======== Validation ======== */
		safeguard_xss();
/* ============================ */


//Set default action
if (!isset($_REQUEST['action'])) {
	$_REQUEST['action'] = "";
}


//Redirection
if (isset($_SESSION['run']) && ($_SESSION['run'] == '0')) {
	header("Location: cc_reports.php");
	exit;
}

switch ($_REQUEST['action']) {
	case 'calculation':
		include_once(CACTI_INCLUDE_PATH . '/top_header.php');
		calculation();
		include_once($config['include_path'] . '/bottom_footer.php');
		break;
	default:
		break;
}


function calculation() {
	global $colors;

	$number_of_warnings	= 0;
	$number_of_errors	= 0;
	$runtime			= '';

	locked(my_template(get_request_var('id')));

	$id = $_GET['id'];
	$_SESSION['run'] = '0';


	if(stat_process($id)) {
		html_error_box('Report is just in process.', 'cc_run.php', '', 'cc_reports.php');
		exit;
	}

	/* run the report */
	$result = runtime($id);

	/* load report informations */
	$sql = "SELECT a.description, a.last_run, a.runtime FROM reportit_reports AS a WHERE a.id=$id";
	$report_informations = db_fetch_row($sql);
	strip_slashes($report_informations);

	foreach($result as $notice) {
		if(substr_count($notice, 'WARNING')) {
			$number_of_warnings++;
			continue;
		}
		if(substr_count($notice, 'ERROR')) {
			$number_of_errors++;
		}
	}

	if(!isset($result['runtime'])) {
		html_custom_header_box($report_informations['description'], 'Report calculation failed', "cc_rrdlist.php?&id={$_GET['id']}", 'List Data Items');
		html_end_box(false);
	}else {
		$runtime = $result['runtime'];
		html_custom_header_box($report_informations['description'], 'Report statistics', "cc_reports.php", 'Report configurations');
		html_end_box(false);

	?>
	<form method="POST" action="cc_view.php?action=show_report&id=<?php print $_GET['id'];?>">
	<?php
	html_graph_start_box();
	?>
<tr>
	<td><b> Runtime:&nbsp; <font color="0000FF"> <?php print $runtime; ?>s
	</font> </b></td>
</tr>
	<?php
	html_graph_end_box();
	}


if($number_of_errors > 0) {
	html_graph_start_box();
	?>
<tr>
	<td valign="top"><b> Number of errors <font color="0000FF"> <?php print "($number_of_errors):"; ?>
	</font> </b></td>
	<td align="left"><b> <font color="FF0000"> <?php
	foreach($result as $error) {
		if(substr_count($error, 'ERROR')) echo "<li>$error";
	}
	?> </font> </b></td>
</tr>
	<?php

	html_graph_end_box();
}

if($number_of_warnings > 0) {
	html_graph_start_box();

	?>
<tr>
	<td valign="top"><b> Number of warnings <font color="0000FF"> <?php print "($number_of_warnings):"; ?>
	</font> </b></td>
	<td align="left"><b> <?php
	foreach($result as $warning) {
		if(substr_count($warning, 'WARNING')) echo "<li>$warning";
	}
	?> </b></td>
</tr>
	<?php

	html_graph_end_box();
}

if($number_of_errors == 0) {
	?>
	<table width="<?php print REPORTIT_WIDTH; ?>" align="center">
		<tr>
			<td align="left">
				<img type="image" src="../../images/arrow.gif" align="absmiddle" border="0">
			</td>
			<td align="right">
				<input type="image"	name="view"	src="../../images/button_view.gif" alt="View report" align="absmiddle">
			</td>
		</tr>
	</form>
	<?php
}
}
?>