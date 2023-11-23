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
include_once(REPORTIT_BASE_PATH . '/lib_int/funct_online.php');
include_once(REPORTIT_BASE_PATH . '/lib_int/const_runtime.php');
include_once(REPORTIT_BASE_PATH . '/lib_int/const_reports.php');
include_once(REPORTIT_BASE_PATH . '/lib_int/funct_shared.php');
include_once(REPORTIT_BASE_PATH . '/lib_int/funct_html.php');

/* ============== Validation ============== */
	safeguard_xss('', true, '<report_title>');
/* ======================================== */

if (!isset($_REQUEST['action'])) {
	$_REQUEST['action'] = '';
}

switch ($_REQUEST['action']) {
	case 'actions':
		form_actions();
		break;
	case 'report_edit':
		include_once(CACTI_INCLUDE_PATH . "/top_header.php");
		report_edit();
		include_once(CACTI_INCLUDE_PATH . "/bottom_footer.php");
		break;
	case 'report_add':
		include_once(CACTI_INCLUDE_PATH . "/top_header.php");
		report_wizard();
		include_once(CACTI_INCLUDE_PATH . "/bottom_footer.php");
		break;
	case 'save':
		form_save();
		break;
	case 'remove':
		remove_recipient();
		break;
	default:
		include_once(CACTI_INCLUDE_PATH . "/top_header.php");
		standard();
		include_once(CACTI_INCLUDE_PATH . "/bottom_footer.php");
		break;
}


function report_wizard() {
	global $colors, $config;

	$templates_list = array();
	$templates = array();

	$sql = "SELECT id, description FROM reportit_templates WHERE locked=0";
	$templates_list = db_fetch_assoc($sql);

	include_once(CACTI_INCLUDE_PATH . "/top_header.php");
	if(isset($_SESSION['reportit'])) unset($_SESSION['reportit']);

	html_start_box("<strong>New Report</strong>", "60%", $colors["header_panel"], "3", "center", "");
	print "<form action='cc_reports.php' autocomplete='off' method='post'>";

	if(sizeof($templates_list) == 0) {
		print "<tr bgcolor='#" . $colors['form_alternate1'] . "'>
				<td>
					<span class='textError'>There are no report templates available.</span>
				</td>
				</tr>";
		$save_html = "<input type='button' value='Cancel' onClick='window.history.back()'>";
	}else {
		$save_html = "<input type='button' value='Cancel' onClick='window.history.back()'>&nbsp;<input type='submit' value='Continue' title='Create a new report'>";
		foreach($templates_list as $tmp) {
			$templates[$tmp['id']] = $tmp['description'];
		}
		print "<tr bgcolor='#" . $colors['form_alternate1'] . "'>
				<td>
					<p>Choose a template this report should depend on.</p>
				</td>
				<td>";
		form_dropdown('template', $templates, '', '', '', '', '');
		print "</td>
			</tr>";
	}

	print "<tr>
		<td align='right' bgcolor='#eaeaea' colspan='2'>
			<input type='hidden' name='action' value='report_edit'>
			$save_html
		</td>
	</tr>";

	html_end_box();
	include_once("./include/bottom_footer.php");
}



function standard() {
	global $colors, $config, $report_actions, $minutes, $link_array, $link_array_admin;

    $affix              = '';
    $columns            = 0;
    $myId               = my_id();
    $myName             = my_name();
    $reportAdmin        = re_admin();
    $tmz                = (read_config_option('reportit_show_tmz') == 'on') ? '('.date('T').')' : '';
    $enable_tmz         = read_config_option('reportit_use_tmz');

	/* ================= Input validation ================= */
		input_validate_input_number(get_request_var_request("page"));
		input_validate_input_number(get_request_var_request("owner"));
		input_validate_input_number(get_request_var_request("template"));
	/* ==================================================== */

    /* ===================== checkpoint =================== */
        $session_max_rows = get_valid_max_rows();
    /* ==================================================== */

	/* clean up search string */
	if (isset($_REQUEST["filter"])) {
		$_REQUEST["filter"] = sanitize_search_string(get_request_var_request("filter"));
	}

	/* clean up sort_column */
	if (isset($_REQUEST["sort"])) {
		$_REQUEST["sort"] = sanitize_search_string(get_request_var("sort"));
	}

	/* clean up sort_direction string */
	if (isset($_REQUEST["mode"])) {
		$_REQUEST["mode"] = sanitize_search_string(get_request_var("mode"));
	}

	/* if the user pushed the 'clear' button */
	if (isset($_REQUEST["clear_x"])) {
		kill_session_var("sess_reportit_rs_filter");
		kill_session_var("sess_reportit_rs_current_page");
		kill_session_var("sess_reportit_rs_sort");
		kill_session_var("sess_reportit_rs_mode");
		kill_session_var("sess_reportit_rs_owner");
		kill_session_var("sess_reportit_rs_template");

		unset($_REQUEST["filter"]);
		unset($_REQUEST["page"]);
		unset($_REQUEST["sort"]);
		unset($_REQUEST["mode"]);
		unset($_REQUEST["owner"]);
		unset($_REQUEST["template"]);
	}

	/* remember these search fields in session vars so we don't have to keep passing them around */
	load_current_session_value("filter", "sess_reportit_rs_filter", "");
	load_current_session_value("page", "sess_reportit_rs_current_page", "1");
	load_current_session_value("sort", "sess_reportit_rs_sort", "id");
	load_current_session_value("mode", "sess_reportit_rs_mode", "ASC");
	load_current_session_value("owner", "sess_reportit_rs_owner", "-1");
	load_current_session_value("template", "sess_reportit_rs_template", "-1");

	if ($reportAdmin) {
		/* fetch user names */
		$sql = "SELECT DISTINCT a.user_id as id, c.username
				FROM reportit_reports AS a
				LEFT JOIN reportit_templates AS b
				ON b.id = a.template_id
				LEFT JOIN user_auth AS c
				ON c.id = a.user_id
				ORDER BY c.username";
		$ownerlist = db_fetch_assoc($sql);

		/* fetch template list */
		$sql = "SELECT DISTINCT b.id, b.description
				FROM reportit_reports AS a
				INNER JOIN reportit_templates AS b
				ON b.id = a.template_id";
		if ($_REQUEST["owner"] !== "-1" & !empty($_REQUEST["owner"])) {
			$sql .= " WHERE a.user_id = " . $_REQUEST['owner'] . " ORDER BY b.description";
			$templatelist = db_fetch_assoc($sql);

			if (sizeof($templatelist)>0) {
				foreach($templatelist as $template) {
					if ($template['id'] == $_REQUEST['template']) {
						$a = 1;
						break;
					}
				}
				if (!isset($a)) $_REQUEST['template'] = "-1";
			}
		}else {
			$templatelist = db_fetch_assoc($sql);
		}
	}

	/* form the 'where' clause for our main sql query */
	if (strlen($_REQUEST["filter"])) {
		$affix .= " WHERE a.description like '%%" . $_REQUEST["filter"] . "%%'";
	}else {
		/* filter nothing, but also use 'where' clause */
		$affix .= " WHERE a.description like '%'";
	}

	/* check admin's filter settings */
	if ($reportAdmin) {
		if ($_REQUEST["owner"] == "-1") {
			/* filter nothing */
		}elseif (!empty($_REQUEST["owner"])) {
			/* show only data items of selected report owner */
			$affix .= " AND a.user_id =" . $_REQUEST["owner"];
		}
		if ($_REQUEST["template"] == "-1") {
			/* filter nothing */
		}elseif (!empty($_REQUEST["template"])) {
			/* show only data items of selected template */
			$affix .= " AND a.template_id =" . $_REQUEST["template"];
		}
	}else {
		/* filter for user */
		$affix .= "AND a.user_id = $myId";
	}

	$sql = "SELECT COUNT(a.id) FROM reportit_reports AS a $affix";
	$total_rows = db_fetch_cell("$sql");

    $sql = "SELECT a.*, b.description AS template_description, c.ds_cnt, d.username, b.locked
            FROM reportit_reports AS a
            LEFT JOIN reportit_templates AS b
                ON b.id = a.template_id
            LEFT JOIN
                (SELECT report_id, count(*) as ds_cnt FROM `reportit_data_items` GROUP BY report_id) AS c
                ON c.report_id = a.id
            LEFT JOIN user_auth AS d
                ON d.id = a.user_id" . $affix .
            " ORDER BY " . $_REQUEST['sort'] . " " . $_REQUEST['mode'] .
            " LIMIT " . ($session_max_rows*($_REQUEST["page"]-1)) . "," . $session_max_rows;
    $report_list = db_fetch_assoc($sql);

	strip_slashes($report_list);

	/* generate page list */
	$url_page_select = html_custom_page_list($_REQUEST["page"], MAX_DISPLAY_PAGES, $session_max_rows, $total_rows, "cc_reports.php?");

	$columns = ($reportAdmin)? 9 : 7;

	$nav = "<tr bgcolor='#6CA6CD' >
		<td colspan='" . $columns . "'>
			<table width='100%' cellspacing='0' cellpadding='0' border='0'>
				<tr>
					<td align='left'>
						<strong>&laquo; "; if ($_REQUEST["page"] > 1) { $nav .= "<a style='color:FFFF00' href='cc_reports.php?page=" . ($_REQUEST["page"]-1) . "'>"; } $nav .= "Previous"; if ($_REQUEST["page"] > 1) { $nav .= "</a>"; } $nav .= "</strong>
					</td>\n
					<td align='center' class='textSubHeaderDark'>
						Showing Rows " . (($session_max_rows*($_REQUEST["page"]-1))+1) . " to " . ((($total_rows < $session_max_rows) || ($total_rows < ($session_max_rows*$_REQUEST["page"]))) ? $total_rows : ($session_max_rows*$_REQUEST["page"])) . " of $total_rows [$url_page_select]
					</td>\n
					<td align='right'>
						<strong>"; if (($_REQUEST["page"] * $session_max_rows) < $total_rows) { $nav .= "<a style='color:yellow' href='cc_reports.php?page=" . ($_REQUEST["page"]+1) . "'>"; } $nav .= "Next"; if (($_REQUEST["page"] * $session_max_rows) < $total_rows) { $nav .= "</a>"; } $nav .= " &raquo;</strong>
					</td>\n
				</tr>
			</table>
		</td>
		</tr>\n";

	/* start with HTML output */
	html_start_box("<b>Report Configurations</b> [$total_rows]", REPORTIT_WIDTH, $colors["header"], "2", "center", "cc_reports.php?action=report_add");
	include(REPORTIT_BASE_PATH . '/lib_int/inc_report_cfgs_filter_table.php');
	html_end_box();

	html_start_box("", REPORTIT_WIDTH, $colors["header"], "3", "center", "");
	print $nav;

	if ($reportAdmin) {
		$desc_array = array('Description', 'Owner', 'Template', "Period $tmz from - to", "Last run $tmz/ Runtime [s]", 'Public', 'Scheduled', 'Data Items');
		html_header_checkbox(html_sorted_with_arrows( $desc_array, $link_array_admin, 'cc_reports.php'));
	}else {
		$desc_array = array('Description', 'Template', "Period $tmz from - to", "Last run $tmz/ Runtime [s]", 'Public', 'Data Objects');
		html_header_checkbox(html_sorted_with_arrows( $desc_array, $link_array, 'cc_reports.php'));
	}

	/* check version of Cacti -> necessary to support 0.8.6k and lower versions as well*/
	$new_version = check_cacti_version(14);

	$i = 0;

	if (sizeof($report_list) > 0) {
		foreach($report_list as $report) {
			if ($new_version) form_alternate_row_color($colors["alternate"], $colors["light"], $i, $report["id"]);
			else form_alternate_row_color($colors["alternate"], $colors["light"], $i); $i++;
			?>
			<td>
				<a class='linkEditMain' href="cc_reports.php?action=report_edit&id=<?php print $report["id"];?>">
				<?php print $report["description"];
				if($report['in_process']) print "<b style='color: #FF0000'>&nbsp;*In process*</b>";
				?>
				</a>
			</td>
		<?php
		    if($reportAdmin) print "<td>{$report['username']}</td>";
		?>
		<td>
			<?php print $report['template_description'];
			?>
		</td>
		<td>
			<?php
			    if ($report['sliding']== true && $report['last_run'] == 0) {
				$dates = rp_get_timespan($report['preset_timespan'], $report['present'], $enable_tmz);
				print (date(config_date_format(), strtotime($dates['start_date'])) . " - " . date(config_date_format(), strtotime($dates['end_date'])));
			    }else {
				print (date(config_date_format(), strtotime($report['start_date'])) . " - " . date(config_date_format(), strtotime($report['end_date'])));
			    }
			?>
		</td>
		<td>
			<?php
			    if($report['last_run'] == '0000-00-00 00:00:00') {
			      	print "- not available -";
			    }else {
				list($date, $time) = explode(' ', $report['last_run']);
			        print (date(config_date_format(), strtotime($date)) . '&nbsp;' . $time . '&nbsp;&nbsp;/&nbsp;' . $report['runtime']);
			    }
			?>

		</td>
		<td>
			<?php html_checked_with_arrow($report['public']);?>
		</td>
			<?php
				if($reportAdmin) {
					print "<td>";
					html_checked_with_arrow($report['scheduled']);
					print "</td>";
				}

                if($report['ds_cnt'] != NULL) {
                    $link = "cc_rrdlist.php?&id={$report['id']}";
                    $msg  = "edit ({$report['ds_cnt']})";
                }else {
                    $link = "cc_items.php?&id={$report['id']}";
                    $msg  = "add";
                }

                print "<td><a class='linkEditMain' href='$link'>$msg</a></td>";

			if(!$report['locked'] && !$report['in_process']) {
		?>
			<td style="<?php print get_checkbox_style();?>" width="1%" align="right">
			<input type='checkbox' style='margin: 0px;' name='chk_<?php print $report["id"];?>' title="Select">
			</td>
			<?php
			}else {
                print "<td align='center'>";
                html_checked_with_icon(true, 'lock.gif', 'Template has been locked temporarily');
                print "</td>";
			}
		?>
		</tr>
		<?php
	}
	if ($total_rows > $session_max_rows) print $nav;
	}else {
	print "<tr><td><em>No reports</em></td></tr>\n";
	}

	html_end_box(true);
	draw_actions_dropdown($report_actions);
}



function remove_recipient(){

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var('id'));
	input_validate_input_number(get_request_var('rec'));
	/* ==================================================== */

	/* ==================== Checkpoint ==================== */
	my_report(get_request_var('id'));
	/* ==================================================== */

	$sql = "DELETE FROM reportit_recipients WHERE id = {$_GET['rec']} AND report_id = {$_GET['id']}";
	db_execute($sql);
	header("Location: cc_reports.php?action=report_edit&id=" . $_GET['id'] . "&tab=email");
}



function form_save() {
	global 	$templates, $timespans, $frequency,
			$timezone, $shifttime, $shifttime2, $weekday, $format;

	$owner 	= array();

	$sql = "SELECT DISTINCT a.id, a.username as name FROM user_auth AS a
			INNER JOIN user_auth_realm AS b
			ON a.id = b.user_id WHERE (b.realm_id = " . REPORTIT_USER_OWNER . " OR b.realm_id = " . REPORTIT_USER_VIEWER . ")
			ORDER BY username";
	$owner = db_custom_fetch_assoc($sql, 'id', false);

	/* ================= Input Validation ================= */
	input_validate_input_whitelist(get_request_var_post('tab'),array('general', 'presets', 'admin', 'email'));
	input_validate_input_number(get_request_var_post('id'));

	/* stop if user is not authorised to save a report config */
	if($_POST['id']!=0) my_report($_POST['id']);
	if(!re_owner()) die_html_custom_error('Not authorised', true);	//this should normally done by Cacti itself

	/* check for the type of saving if it was sent through the email tab */
	$add_recipients = (array_key_exists('add_recipients_x', $_REQUEST)) ? true : false;

	switch($_POST['tab']){
		case 'presets':
		 	input_validate_input_blacklist($_POST['id'],array(0));
			input_validate_input_key(get_request_var_post('rrdlist_timezone'), $timezone, true);
			input_validate_input_key(get_request_var_post('rrdlist_shifttime_start'), $shifttime);
			input_validate_input_key(get_request_var_post('rrdlist_shifttime_end'), $shifttime2);
			input_validate_input_key(get_request_var_post('rrdlist_weekday_start'), $weekday);
			input_validate_input_key(get_request_var_post('rrdlist_weekday_end'), $weekday);

			form_input_validate($_POST['rrdlist_subhead'], 'rrdlist_subhead', '' ,true,3);

			input_validate_input_number(get_request_var_post('host_template_id'));
			form_input_validate($_POST['data_source_filter'], 'data_source_filter'	, '', true, 3);
			break;

		case 'admin':

			input_validate_input_blacklist($_POST['id'],array(0));
			input_validate_input_key(get_request_var_post('report_owner'), $owner);

			if(read_config_option('reportit_operator')) {

				input_validate_input_key(get_request_var_post('report_schedule_frequency'), $frequency, true);
				input_validate_input_limits(get_request_var_post('report_autoarchive'),0,1000);

				if(read_config_option('reportit_auto_export')) {
					input_validate_input_limits(get_request_var_post('report_autoexport_max_records'),0,1000);
					input_validate_input_key(get_request_var_post('report_autoexport'), $format, true);
				}
			}

			break;

		case 'email':
			if(!$add_recipients) {
				form_input_validate($_POST['report_email_subject'], 'report_email_subject', '' ,false,3);
				form_input_validate($_POST['report_email_body'], 'report_email_body', '', false, 3);
				input_validate_input_key(get_request_var_post('report_email_format'), $format);
			}else {
				/* if javascript is disabled */
				form_input_validate($_POST['report_email_address'], 'report_email_address', '', false, 3);
			}
			break;

		default:
			input_validate_input_number(get_request_var_post('template_id'));
			input_validate_input_key(get_request_var_post('preset_timespan'), $timespans, true);

			/* if template is locked we don't know if the variables have been changed */
			locked($_POST['template_id']);

			form_input_validate($_POST['report_description'], 'report_description', '' ,false,3);

			/* validate start- and end date if sliding time should not be used */
			if (!isset($_POST['report_dynamic'])) {

				if(!preg_match('/^\d{4}\-\d{2}\-\d{2}$/', $_POST['report_start_date'])) {
					session_custom_error_message('report_start_date', 'Invalid date');
				}

				if(!preg_match('/^\d{4}\-\d{2}\-\d{2}$/', $_POST['report_end_date'])) {
					session_custom_error_message('report_end_date', 'Invalid date');
				}

				if(!is_error_message()) {
					list($ys, $ms, $ds) = explode("-", $_POST['report_start_date']);
					list($ye, $me, $de) = explode("-", $_POST['report_end_date']);

					if(!checkdate($ms, $ds, $ys)) session_custom_error_message('report_start_date', 'Invalid date');
					if(!checkdate($me, $de, $ye)) session_custom_error_message('report_end_date', 'Invalid date');

					if (($start_date = mktime(0,0,0,$ms,$ds,$ys)) > ($end_date = mktime(0,0,0,$me,$de,$ye)) || $ys > $ye || $ys > date("Y")) {
						session_custom_error_message('report_start_date', 'Start date lies ahead');
					}
					if (($end_date = mktime(0,0,0,$me,$de,$ye)) > mktime() || $ye > date("Y")) {
						session_custom_error_message('report_start_date', 'End date lies ahead');
					}
				}
			}

			if(!read_config_option('reportit_operator')) {
				input_validate_input_key(get_request_var_post('report_schedule_frequency'), $frequency, true);
				input_validate_input_limits(get_request_var_post('report_autoarchive'),0,1000);

				if(read_config_option('reportit_auto_export')) {
					input_validate_input_limits(get_request_var_post('report_autoexport_max_records'),0,1000);
					input_validate_input_key(get_request_var_post('report_autoexport'), $format, true);
				}
			}
	}
	/* ==================================================== */

	/* return if validation failed */
	if(is_error_message()) header("Location: cc_reports.php?action=report_edit&id={$_POST['id']}&tab={$_POST['tab']}" );


	switch($_POST['tab']){
		case 'presets':
			$rrdlist_data['id']					= $_POST['id'];
			$rrdlist_data['start_day']			= $weekday[$_POST['rrdlist_weekday_start']];
			$rrdlist_data['end_day']			= $weekday[$_POST['rrdlist_weekday_end']];
			$rrdlist_data['start_time']			= $shifttime[$_POST['rrdlist_shifttime_start']];
			$rrdlist_data['end_time']			= $shifttime2[$_POST['rrdlist_shifttime_end']];
			if(isset($_POST['rrdlist_timezone']))
				$rrdlist_data['timezone']		= $timezone[$_POST['rrdlist_timezone']];
			if(isset($_POST['rrdlist_subhead']))
				$rrdlist_data['description']	= mysql_real_escape_string($_POST['rrdlist_subhead']);

			$report_data['id']					= $_POST['id'];
			$report_data['host_template_id']	= $_POST['host_template_id'];
			$report_data['data_source_filter']	= mysql_real_escape_string($_POST['data_source_filter']);

			/* save settings */
			sql_save($report_data, "reportit_reports");
			sql_save($rrdlist_data, "reportit_presets", 'id', false);
			break;

		case 'admin':
			$report_data['id']					= $_POST['id'];
			$report_data['user_id']				= $_POST['report_owner'];
			$report_data['graph_permission']	= isset($_POST['report_graph_permission']) ? 1 : 0;

			/* save the settings for scheduled reporting if the admin is configured to do this job */
			if(read_config_option('reportit_operator')) {
				$report_data['scheduled'] = isset($_POST['report_schedule']) ? 1 : 0;
				if( isset($_POST['report_autorrdlist']) ) {
					$report_data['autorrdlist'] = 1;
				}
				if( isset($_POST['report_schedule_frequency']) ) {
					$report_data['frequency'] = $frequency[$_POST['report_schedule_frequency']];
				}
				if( isset($_POST['report_autoarchive']) ) {
					$report_data['autoarchive'] = $_POST['report_autoarchive'];
				}
				if( isset($_POST['report_email']) ) {
					$report_data['auto_email'] = 1;
				}
				if( isset($_POST['report_autoexport']) ) {
					$report_data['autoexport'] = $_POST['report_autoexport'];
				}
				if( isset($_POST['report_autoexport_max_records']) ) {
					$report_data['autoexport_max_records'] = $_POST['report_autoexport_max_records'];
				}
				if( isset($_POST['report_autoexport_no_formatting']) ) {
					$report_data['autoexport_no_formatting'] = 1;
				}
			}

			/* save settings */
			sql_save($report_data, "reportit_reports");
			break;

		case 'email':

			if(!$add_recipients) {

				$report_data['id']					= $_POST['id'];
				$report_data['email_subject']		= mysql_real_escape_string($_POST['report_email_subject']);
				$report_data['email_body']			= mysql_real_escape_string($_POST['report_email_body']);
				$report_data['email_format']		= $_POST['report_email_format'];

				/* save settings */
				sql_save($report_data, "reportit_reports");
			}else {

				$id			= $_POST['id'];
				$columns	= '(report_id, email, name)';
				$values		= '';

				if(strpos($_REQUEST['report_email_address'],';')) {
					$addresses = explode(';',$_REQUEST['report_email_address'] );
				}elseif (strpos($_REQUEST['report_email_address'],',')){
					$addresses = explode(',',$_REQUEST['report_email_address'] );
				}else {
					$addresses[] = $_REQUEST['report_email_address'];
				}

				if(strpos($_REQUEST['report_email_recipient'],';')) {
					$recipients = explode(';',$_REQUEST['report_email_recipient'] );
				}elseif (strpos($_REQUEST['report_email_recipient'],',')){
					$recipients = explode(',',$_REQUEST['report_email_recipient'] );
				}else {
					$recipients[] = $_REQUEST['report_email_recipient'];
				}

				if(sizeof($addresses)>0) {
					foreach($addresses as $key => $value) {
						$value = trim($value);
						if(!preg_match("/(^[0-9a-zA-Z]([-_.]?[0-9a-zA-Z])*@[0-9a-zA-Z]([-_.]?[0-9a-zA-Z])*\\.[a-zA-Z]{2,3}$)/", $value)) {
							session_custom_error_message('report_email_address', 'Invalid email address');
						}
						if(array_key_exists($key, $recipients) && $recipients[$key] != '[OPTIONAL] - Name of a recipient (or list of names) -') {
							$name = mysql_real_escape_string($recipients[$key]);
						}else {
							$name = '';
						}
						$values .= "('$id', '$value', '$name'),";
					}
					$values = substr($values, 0, strlen($values)-1);
					if(!is_error_message()) {
						$sql = "INSERT INTO reportit_recipients $columns VALUES $values";
						db_execute($sql);
					}
				}
			}
			break;

		default:
			$report_data['id']					= $_POST['id'];
			$report_data['description']			= mysql_real_escape_string($_POST['report_description']);
			$report_data['template_id']			= $_POST['template_id'];
			$report_data['public']				= isset($_POST['report_public']) ? 1 : 0;

			$report_data['preset_timespan']		= isset($_POST['report_timespan']) ? $timespans[$_POST['report_timespan']] : '';
			$report_data['last_run']			= '0000-00-00 00:00:00';

			$report_data['start_date']			= isset($_POST['report_start_date']) ? $_POST['report_start_date'] : '0000-00-0';
			$report_data['end_date']			= isset($_POST['report_end_date']) ? $_POST['report_end_date'] : '0000-00-0';

			$report_data['sliding']				= isset($_POST['report_dynamic']) ? 1 : 0;
			$report_data['present']				= isset($_POST['report_present']) ? 1 : 0;

			/* define the owner if it's a new configuration */
			if($_POST['id']==0) $report_data['user_id'] = my_id();

			/* save the settings for scheduled reporting if owner has the rights to do this */
			if(!read_config_option('reportit_operator')) {
				$report_data['scheduled'] = isset($_POST['report_schedule']) ? 1 : 0;
				if( isset($_POST['report_autorrdlist']) ) {
					$report_data['autorrdlist'] = 1;
				}
				if( isset($_POST['report_schedule_frequency']) ) {
					$report_data['frequency'] = $frequency[$_POST['report_schedule_frequency']];
				}
				if( isset($_POST['report_autoarchive']) ) {
					$report_data['autoarchive'] = $_POST['report_autoarchive'];
				}
				if( isset($_POST['report_email']) ) {
					$report_data['auto_email'] = 1;
				}
				if( isset($_POST['report_autoexport']) ) {
					$report_data['autoexport'] = $_POST['report_autoexport'];
				}
				if( isset($_POST['report_autoexport_max_records']) ) {
					$report_data['autoexport_max_records'] = $_POST['report_autoexport_max_records'];
				}
				if( isset($_POST['report_autoexport_no_formatting']) ) {
					$report_data['autoexport_no_formatting'] = 1;
				}
			}

			//Now we've to keep our variables
			$vars		= array();
			$rvars		= array();
			$var_data	= array();

			foreach($_POST as $key => $value) {
				if(strstr($key, 'var_')) {
					$id = substr($key, 4);
					$vars[$id] = $value;
				}
			}

			$sql = "SELECT a.*, b.id as b_id, b.value FROM reportit_variables AS a
				 LEFT JOIN reportit_rvars as b
				 on a.id = b.variable_id AND report_id = {$_POST['id']}
				 WHERE a.template_id = {$_POST['template_id']}";

			$rvars	= db_fetch_assoc($sql);

			foreach($rvars as $key => $v) {
				$value = $vars[$v['id']];

				if( $v['input_type'] == 1) {
					$i = 0;
					$array = array();
					$a = $v['min_value'];
					$b = $v['max_value'];
					$c = $v['stepping'];

					for($i=$a; $i <= $b; $i+=$c) {
						$array[] = $i;
					}

					$value = $array[$value];

					if( $value > $v['max_value'] || $value < $v['min_value']) die_html_custom_error('', true);
				}else {
					if( $value > $v['max_value'] || $value < $v['min_value']) {
						session_custom_error_message($v['name'], "{$v['name']} is out of range");
						break;
					}
				}

				//If there's no error we can go on
				$var_data[] = array('id' => (($v['b_id'] != NULL) ? $v['b_id'] : 0),
									'template_id'	=> $_POST['template_id'],
									'report_id'		=> $_POST['id'],
									'variable_id'	=> $v['id'],
									'value' 		=> $value);
			}

			/* start saving process or return */
			if(is_error_message()) {
				header('Location: cc_reports.php?action=report_edit&id=' . $_POST['id'] . "&tab={$_POST['tab']}");
			}else {
				/* save report config */
				$report_id = sql_save($report_data, 'reportit_reports');

				/* save addtional report variables */
				foreach($var_data as $data) {
					if($_POST['id'] == 0) $data['report_id'] = $report_id;
					sql_save($data, 'reportit_rvars');
				}

			}

	}
	header("Location: cc_reports.php?action=report_edit&id=" . (isset($report_id)? $report_id : $_POST['id']) . "&tab={$_POST['tab']}") ;
	raise_message(1);
}



function report_edit() {
	global $colors, $templates, $timespans, $graph_timespans, $frequency, $archive, $tabs ,
			$weekday, $timezone, $shifttime, $shifttime2, $format,
			$form_array_admin, $form_array_presets, $form_array_general, $form_array_email;

	if (!isset($_REQUEST["tab"])) $_REQUEST["tab"] = "general";

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var_request('id'));
	input_validate_input_number(get_request_var_post('template'));
	input_validate_input_whitelist(get_request_var_request('tab'), array_keys($tabs));
	/* ==================================================== */

	/* ==================== Checkpoint ==================== */
	my_report(get_request_var('id'));
	if($_REQUEST['tab']=='admin' & !re_admin()) die_html_custom_error('Permission denied', true);
	if($_REQUEST['tab']=='email' & !read_config_option('reportit_email')) die_html_custom_error();
	if(!isset($_REQUEST['id']) & empty($_POST['template'])) die_html_custom_error();
	if($_REQUEST['tab']=='email' & isset($_REQUEST['id']) && !get_report_setting($_REQUEST['id'], 'auto_email')) die_html_custom_error();
	session_custom_error_display();
	/* ==================================================== */


	/* load config settings if it's not a new one */
	if (!empty($_REQUEST['id'])) {

		$report_data		= db_fetch_row('SELECT * FROM reportit_reports WHERE id=' . $_REQUEST['id']);
		strip_slashes($report_data);
		$rrdlist_data		= db_fetch_row('SELECT * FROM reportit_presets WHERE id=' . $_REQUEST['id']);
		strip_slashes($rrdlist_data);
		$report_recipients	= db_fetch_assoc('SELECT * FROM reportit_recipients WHERE report_id='. $_REQUEST['id']);
		strip_slashes($report_recipients);

		$header_label 		= '[edit: ' . $report_data['description'] . ']';

		/* update rrdlist_data */
		if ($rrdlist_data) {
			$rrdlist_data['timezone']	= array_search($rrdlist_data['timezone'],$timezone);
			$rrdlist_data['start_time']	= array_search($rrdlist_data['start_time'],$shifttime);
			$rrdlist_data['end_time']	= array_search($rrdlist_data['end_time'],$shifttime2);
			$rrdlist_data['start_day']	= array_search($rrdlist_data['start_day'],$weekday);
			$rrdlist_data['end_day']	= array_search($rrdlist_data['end_day'],$weekday);
		}

		/* update report_data array for getting compatible to Cacti's drawing functions */
		$report_data['preset_timespan'] = array_search($report_data['preset_timespan'], $timespans);
		$report_data['frequency'] 		= array_search($report_data['frequency'], $frequency);

		/* replace all binary settings to get compatible with Cacti's draw functions */
		$rpm = array('public', 'sliding', 'present', 'scheduled', 'autorrdlist', 'subhead', 'graph_permission', 'auto_email', 'email_compression', 'autoexport_no_formatting');
		foreach($report_data as $key => $value) if (in_array($key,$rpm)) if ($value == 1) $report_data[$key] = 'on';

		/* setup blue link */
		$href 	= 'cc_items.php?id=' . $_REQUEST['id'];
		$text 	= 'Add data items';
		$link[] = array('href' =>$href, 'text' =>$text);

		/* load values for host_template_filter */
		$sql 	= "SELECT pre_filter FROM reportit_templates WHERE id={$report_data['template_id']}";
		$filter = db_fetch_cell($sql);
		$tmp 	= db_fetch_assoc("SELECT id, description FROM reportit_templates WHERE pre_filter='$filter'");

	}else {
		$header_label	= '[new]';
		$report_data = array();
	}

	$id	= (isset($_REQUEST['id']) ? $_REQUEST['id'] : '0');
	$rrdlist_data['id']= $id;

	if(isset($_REQUEST['template'])) {
		if(!isset($_SESSION['reportit']['template'])) $_SESSION['reportit']['template'] = $_REQUEST['template'];
	}
	$template_id = (isset($report_data['template_id']) ? $report_data['template_id'] : $_SESSION['reportit']['template']);

	/* leave if base template is locked */
	locked($template_id);
	$report_data['template_id'] = $template_id;
	$report_data['template'] = db_fetch_cell("SELECT description FROM reportit_templates WHERE id=$template_id");
	if(!array_key_exists('auto_email',$report_data)) $report_data['auto_email'] = false;

	/* start with HTML output */
	if($id != 0) html_blue_link( $link, false);

	/* draw the categories tabs on the top of the page */
	print "<table class='tabs' width='" . REPORTIT_WIDTH ."' cellspacing='0' cellpadding='3' align='center'><tr>\n";
	$current_tab = $_REQUEST["tab"];

	/* unset the administration tab if user isn't a report admin */
	if (!re_admin()) unset($tabs['admin']);

	/* remove the email tab if emailing is deactivated globally */
	if(read_config_option('reportit_email') != 'on') unset($tabs['email']);

	if (sizeof($tabs) > 0) {
		foreach (array_keys($tabs) as $tab_short_name) {
			print "<td " . (($tab_short_name == $current_tab) ? "bgcolor='#c0c0c0'" : "bgcolor='#DFDFDF'") . " nowrap='nowrap' width='" . (strlen($tabs[$tab_short_name]) * 9) . "' align='center' class='tab'>";
			if (($id == 0 & $tab_short_name != 'general') | ($tab_short_name == 'admin' && !re_admin()) | ($tab_short_name == 'email' & $report_data['auto_email'] != true)){
				echo "<span class='textHeader'><font style='color:#A8A8A8;'>$tabs[$tab_short_name]</font></span>";
			}elseif ($id == 0 & $tab_short_name == 'general'){
				echo "<span class='textHeader'><font style='color:#0000FF;'>$tabs[$tab_short_name]</font></span>";
			}else {
				$link = "cc_reports.php?action=report_edit&id=$id&tab=$tab_short_name";
				echo "<span class='textHeader'><a href='$link'>$tabs[$tab_short_name]</a></span>";
			}
			echo "</td>\n<td width='1'></td>\n";
		}
	}
	print "<td></td>\n</tr></table>\n";

	html_start_box("<strong>Report Configuration ($tabs[$current_tab])</strong> $header_label", REPORTIT_WIDTH, $colors["header"], "2", "center", "");
	switch($_REQUEST['tab']){
		case 'presets':
						draw_edit_form(array(
							'config' => array(),
							'fields' => inject_form_variables($form_array_presets, $rrdlist_data, $report_data)
						));
			break;

		case 'admin':
						draw_edit_form(array(
							'config' => array(),
							'fields' => inject_form_variables($form_array_admin, $report_data)
						));
			break;

		case 'email':
						draw_edit_form(array(
							'config' => array(),
							'fields' => inject_form_variables($form_array_email, $report_data)
						));
						html_end_box();
						html_start_box("", REPORTIT_WIDTH, $colors["header"], "2", "center", "");
						html_header(array('Name','Email',''));
						$i = 0;
						if (sizeof($report_recipients) > 0) {
							foreach ($report_recipients as $recipient) {
								form_alternate_row_color($colors["alternate"],$colors["light"],$i); $i++;
								echo '<td>' . $recipient['name'] . '</td>';
								echo '<td>' . $recipient['email'] . '</td>';
								echo '<td align="right">'
									."<a href=\"cc_reports.php?action=remove&id={$_REQUEST['id']}&rec={$recipient["id"]}\">"
									.'<img src="../../images/delete_icon.gif" width="10" height="10" border="0" alt="Delete"></a></td>';
							}
						}else{
							print "<tr><td><em>No recipients found</em></td></tr>\n";
						}
			break;
		default:
						draw_edit_form(array(
							'config' => array(),
							'fields' => inject_form_variables($form_array_general, $report_data)
						));
						$template_variables = html_report_variables($id, $template_id );

						/* draw input fields for variables */
						if($template_variables !== false) {
							draw_edit_form(array(
								'config' => array(),
								'fields' => $template_variables
							));
						}
	}
	html_end_box();
	form_save_button('cc_reports.php');

	?>
	<script language="JavaScript">

	if (document.getElementById('report_dynamic')) {
		dyn_general_tab();
		document.getElementById('report_dynamic').onclick = dyn_general_tab;
	}

	if (document.getElementById('report_schedule')) {
		dyn_admin_tab();
		document.getElementById('report_schedule').onclick = dyn_admin_tab;
	}

	function start_input(name){
		if (name == 'report_email_address') {
			text = '- Email address of a recipient (or list of names) -';
		}else {
			text = '[OPTIONAL] - Name of a recipient (or list of names) -';
		}
		if (document.getElementById(name).value == text) {
			document.getElementById(name).value = '';
			document.getElementById(name).style.textAlign ='left';
		}
	}

	function leave_input(name){
		if (name == 'report_email_address') {
			text = '- Email address of a recipient (or list of names) -';
		}else {
			text = '[OPTIONAL] - Name of a recipient (or list of names) -';
		}
		if (document.getElementById(name).value == '') {
			document.getElementById(name).value = text;
			document.getElementById(name).style.textAlign ='center';
		}
	}

	function dyn_general_tab() {
		if (document.getElementById('report_dynamic').checked){
			document.getElementById('report_start_date').value='yyyy-mm-dd';
			document.getElementById('report_start_date').disabled=true;
			document.getElementById('report_end_date').value='yyyy-mm-dd';
			document.getElementById('report_end_date').disabled=true;
			document.getElementById('report_present').disabled=false;
			document.getElementById('report_timespan').disabled=false;
		}else {
			document.getElementById('report_start_date').disabled=false;
			document.getElementById('report_end_date').disabled=false;
			document.getElementById('report_present').disabled=true;
			document.getElementById('report_timespan').disabled=true;
		}
	}

	function dyn_admin_tab(){
		if (document.getElementById('report_schedule').checked) {
			document.getElementById('report_schedule_frequency').disabled=false;
			if (document.getElementById('report_autoarchive')) {
				document.getElementById('report_autoarchive').disabled=false;
			}
			if (document.getElementById('report_autoexport')) {
				document.getElementById('report_autoexport').disabled=false;
			}
		}else {
			document.getElementById('report_schedule_frequency').disabled=true;
			if (document.getElementById('report_autoarchive')) {
				document.getElementById('report_autoarchive').disabled=true;
			}
			if (document.getElementById('report_autoexport')) {
				document.getElementById('report_autoexport').disabled=true;
			}
		}
	}
	</script>
	<?php
}


function form_actions() {
	global $colors, $report_actions, $config;

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var_post('drp_action'));
	/* ==================================================== */

	if (isset($_POST['selected_items'])) {
		$selected_items = unserialize(stripslashes($_POST['selected_items']));

		if ($_POST['drp_action'] == '2') { // DELETE REPORT
			$report_datas = db_fetch_assoc('SELECT id FROM reportit_reports WHERE ' . array_to_sql_or($selected_items, 'id'));

			if (sizeof($report_datas) > 0) {
				$counter_data_items = 0;
				foreach ($report_datas as $report_data) {
					$counter_data_items += db_fetch_cell("SELECT COUNT(*) FROM reportit_data_items WHERE report_id = {$report_data['id']}");
					db_execute('DELETE FROM reportit_reports WHERE id=' . $report_data['id']);
					db_execute('DELETE FROM reportit_presets WHERE id=' . $report_data['id']);
					db_execute('DELETE FROM reportit_rvars WHERE report_id=' . $report_data['id']);
					db_execute('DELETE FROM reportit_recipients WHERE report_id=' . $report_data['id']);
					db_execute('DELETE FROM reportit_data_items WHERE report_id=' . $report_data['id']);
					db_execute('DROP TABLE IF EXISTS reportit_results_' . $report_data['id']);
				}
				if($counter_data_items > 200) {
					db_execute('OPTIMIZE TABLE `reportit_data_items`');
				}
			}
		}elseif ($_POST['drp_action'] == '3') { //DUPLICATE REPORT CONFIGURATION

			for ($i=0;($i<count($selected_items));$i++) {
				/* ================= input validation ================= */
				input_validate_input_number($selected_items[$i]);
				/* ==================================================== */

				$report_data = db_fetch_row('SELECT * FROM reportit_reports WHERE id = ' . $selected_items[$i]);
				$report_data['id'] = 0;
				$report_data['description'] = str_replace("<report_title>", $report_data['description'], $_POST['report_addition']);
				$new_id = sql_save($report_data, 'reportit_reports');

				//Copy original rrdlist table  to new rrdlist table
				$sql = "SELECT * FROM reportit_data_items WHERE report_id = {$selected_items[$i]}";
				$data_items = db_fetch_assoc($sql);

				if(sizeof($data_items)>0) {
					foreach($data_items as $data_item) {
						$data_item['report_id']=$new_id;
						sql_save($data_item, 'reportit_data_items', array('id', 'report_id'), false);
					}
				}

				/* duplicate the presets settings */
				$report_presets = db_fetch_row('SELECT * FROM reportit_presets WHERE id = ' . $selected_items[$i]);
				$report_presets['id'] = $new_id;
				sql_save($report_presets, 'reportit_presets', 'id', false);

				/* duplicate list of recipients */
				$report_recipients = db_fetch_assoc('SELECT * FROM reportit_recipients WHERE report_id = ' . $selected_items[$i]);
				if(sizeof($report_recipients)>0) {
					foreach($report_recipients as $recipient){
						$recipient['id'] = 0;
						$recipient['report_id']=$new_id;
						sql_save($recipient, 'reportit_recipients');
					}
				}

				/* reset the new report configuration */
				reset_report($new_id);
			}
		}
		header('Location: cc_reports.php');
		exit;
	}

	//Set preconditions
	$ds_list = ''; $i = 0;

	foreach($_POST as $key => $value) {

		if(strstr($key, 'chk_')) {
			//Fetch report id
			$id = substr($key, 4);
			$report_ids[] = $id;
			// ================= input validation =================
			input_validate_input_number($id);
			// ====================================================

			//Fetch report description
			$report_description 	= db_fetch_cell('SELECT description FROM reportit_reports WHERE id=' . $id);
			$ds_list[] = $report_description;
		}
	}

	//For running report jump to cc_run.php!
	if ($_POST['drp_action'] == '1') { // RUNNING REPORT
		//Only one report is allowed to run at the same time, so select the first one:
		if(isset($report_ids)) {
			$report_id = $report_ids[0];

			//Update $_SESSION
			$_SESSION['run'] = '1';

			//Jump to cc_run.php
			header('Location: cc_run.php?action=calculation&id=' . $report_id);
			exit;
		}
	}

	include_once(CACTI_INCLUDE_PATH . '/top_header.php');
	html_start_box("<strong>" . $report_actions{$_POST["drp_action"]} . "</strong>", "60%", $colors["header_panel"], "2", "center", "");
	print "<form action='cc_reports.php' method='post'>\n";

	if ($_POST['drp_action'] == '2') { //DELETE REPORT
		print " <tr>
					<td bgcolor='#" . $colors["form_alternate1"]. "'>
						<p>Are you sure you want to delete the following reports?</p>";
		if(is_array($ds_list)) {
			print	"<p>List of selected reports:<br>";
			foreach($ds_list as $key => $value) {
				print "&#160 |_Report: $value<br>";
			}
		}
		print "		</td>
				</tr>";

	}elseif ($_POST['drp_action'] == '3') { // DUPLICATE REPORT
		print " <tr>
					<td bgcolor='#" . $colors['form_alternate1']. "'>
					<p>When you click \"yes\", the following report configuration will be duplicated. You can optionally change the title format for the new report.</p>";

		if(is_array($ds_list)) {
			print	"<p>List of selected reports:<br>";
			foreach($ds_list as $key => $value) {
				print "&#160 |_Report: $value<br>";
			}
		}
		print "<p><strong>Title Format:</strong><br>";
		form_text_box("report_addition", "<report_title> (1)", "", "255", "30", "text");
		print "		</p>
		    </td>
		</tr>\n";
	}


	if (!is_array($ds_list)) {
		print "<tr><td bgcolor='#" . $colors['form_alternate1']. "'><span class='textError'>You must select at least one report.</span></td></tr>\n";
		$save_html = "<input type='button' value='Cancel' onClick='window.history.back()'>";
	}else {
		$save_html = "<input type='button' value='Cancel' onClick='window.history.back()'>&nbsp;<input type='submit' value='Continue'>";
	}

	print " <tr>
				<td align='right' bgcolor='#eaeaea'>
					<input type='hidden' name='action' value='actions'>
					<input type='hidden' name='selected_items' value='" . (isset($report_ids) ? serialize($report_ids) : '') . "'>
					<input type='hidden' name='drp_action' value='" . $_POST["drp_action"] . "'>
					$save_html
				</td>
			</tr>";

	html_end_box();
	include_once(CACTI_BASE_PATH . '/include/bottom_footer.php');}
	?>
