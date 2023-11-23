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


//----- CONSTANTS FOR: cc_reports.php -----

define("MAX_DISPLAY_PAGES", 21);

$report_actions 	= array( 1 => 'Run Report', 2 => 'Delete', 3 => 'Duplicate');
$link_array			= array('description', 'template_description', '', '', 'public', 'ds_cnt');
$link_array_admin	= array('description', 'username', 'template_description', '', '', 'public', 'scheduled', 'ds_cnt');


//$templates		- array, for dropdown menu
//			- contains all names of available templates by taking into account user's realm
$templates = db_fetch_assoc('SELECT * FROM reportit_templates WHERE locked = 0');

if(!$templates) {
    $templates['0'] = '- No template available -';
}else {
    foreach($templates as $key => $value) {
	$tmp[$templates[$key]['id']] = $templates[$key]['description'];
    }
    $templates = $tmp;
    unset($tmp);
}

$weekday = array('Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday');

// $timespans		- array, for dropdown menu
//			- contains preset values for selecting the report timespan
$timespans = array( 'Today',
					'Last 1 Day',
					'Last 2 Days',
					'Last 3 Days',
					'Last 4 Days',
					'Last 5 Days',
					'Last 6 Days',
					'Last 7 Days',
					'Last Week (Sun - Sat)',
					'Last Week (Mon - Sun)',
					'Last 14 Days',
					'Last 21 Days',
					'Last 28 Days',
					'Current Month',
					'Last Month',
					'Last 2 Months',
					'Last 3 Months',
					'Last 4 Months',
					'Last 5 Months',
					'Last 6 Months',
					'Current Year',
					'Last Year',
					'Last 2 Years'
);

//timezones
foreach ($timezones as $tmz => $value) $timezone[]=$tmz;

//Schedule frequency
$frequency = array( 'daily', 'weekly', 'monthly', 'quarterly', 'yearly');

//Maximum number of files an archive can contain
$archive[0] = 'off';
for( $i = 1; $i <= 1000; $i++) $archive[$i]= $i;

//Tabs
$tabs = array(
	"general"	=> "General",
	"presets"	=> "Data Item Presets",
	"email"		=> "Email",
	"admin"		=> "Administration"
);

// $shifttime		- array, for dropdown menu
//			- contains all possible timestamps of a day by using steps of 5 minutes
$shifttime = array();
    for($i=0; $i<24; $i++) {
	$hour=$i;
	if($hour<10) {$hour = '0' . $hour;}

	    for($j=0; $j<60; $j+=5) {
		$minutes = $j;
		if($minutes<10) {$minutes = '0' . $minutes;}
		$shifttime[]= "$hour:$minutes:00";
	    }

    }
    $shifttime2  = $shifttime;
    $shifttime2[]= "24:00:00";
unset($i);
unset($j);


$format = array('None'  => 'None',
                 'CSV'  => 'Text CSV (.csv)',
                 'SML'  => 'MS Excel 2003 XML (.xml)',
                 'XML'  => 'Raw XML (.xml)');

$form_array_email = array(
	'report_header_1'	=> array(
	'friendly_name'		=> 'General',
	'method'			=> 'spacer',
	),
	'id'				=> array(
	'method'			=> 'hidden_zero',
	'value'				=> '|arg1:id|',
	),
	'tab'				=> array(
	'method'			=> 'hidden_zero',
	'value'				=> 'email',
	),
	'report_email_subject' => array(
	'friendly_name' 	=> 'Subject',
	'size'				=> '60',
	'max_length'		=> '100',
	'method' 			=> 'textbox',
	'description' 		=> "Enter the subject of your email.<br>
							Following variables will be supported (without quotes):
							'|title|' and '|period|'",
	'default'			=> 'Scheduled report - |title| - |period|',
	'value'				=> '|arg1:email_subject|',
	),
	'report_email_body' => array(
	'friendly_name' 	=> 'Body (optional)',
	'description' 		=> 'Enter a message which will be displayed in the body of your email',
	'method' 			=> "textarea",
	'textarea_rows'		=> "3",
	'textarea_cols'		=> "45",
	'default'			=> "This is a scheduled report generated from Cacti.",
	'value'				=> '|arg1:email_body|',
	),
	'report_email_format'	=> array(
	'friendly_name'		=> 'Attachment',
	'method'			=> 'drop_array',
	'description'		=> 'Only to receive an email as a notification that a new report is available choose "None".<br>
				    Otherwise select the format the report should be attached as.',
	'value'				=> '|arg1:email_format|',
	'array'				=> $format,
	'default'           => '1',
	),
	'report_header_2'	=> array(
	'friendly_name'		=> 'Email Recipients',
	'method'			=> 'spacer',
	),
	'report_email_recipient' => array(
	'friendly_name'		=> 'New Email Recipients',
	'method'			=> 'custom',
	'default'			=> 'false',
	'description'		=> 'To add a new recipient enter a valid email address (required) and a name (optional).<br>
							For a faster setup use a list of adresses/names	where the names/addresses are separated
							with one of the following delemiters: ";" or ","',
	'value'				=> "<table border='0' cellspacing='0'>
								<tr>
									<td>
										<input type='text' id='report_email_address' name='report_email_address' size='60' maxlength='2500' value='- Email address of a recipient (or list of names) -' style='text-align: center' align='top' onfocus=start_input('report_email_address') onblur=leave_input('report_email_address')>

										<input type='submit'  name='add_recipients_x' value='add' title='Add recipients'>
									</td>
								</tr>
								<tr>
									<td>
										<input type='text' id='report_email_recipient' name='report_email_recipient' size='60' maxlength='2500' value='[OPTIONAL] - Name of a recipient (or list of names) -' style='text-align: center' align='top' onfocus=start_input('report_email_recipient') onblur=leave_input('report_email_recipient')>
									</td>
								</tr>
							</table>",

	)
);

$form_array_scheduling = array(
	'report_header_3'	=> array(
	'friendly_name'		=> 'Scheduled Reporting',
	'method'			=> 'spacer',
	),
	'report_schedule'	=> array(
	'method'			=> 'checkbox',
	'friendly_name'		=> 'Enable',
	'description'		=> 'Enable/disable scheduled reporting. Sliding time frame should be enabled.',
	'value'				=> '|arg1:scheduled|',
	'default'			=> '',
	),
	'report_schedule_frequency'	=> array(
	'friendly_name'		=> 'Frequency',
	'method'			=> 'drop_array',
	'description'		=> 'Select the frequency for processing this report. Be sure that there\'s a cronjob (or scheduled task)
							running for the choice you made. This won\'t be done automatically by ReportIt.',
	'value'				=> '|arg1:frequency|',
	'array'				=> $frequency
	)
);

$form_array_auto_tasks = array(
	'report_header_4'	=> array(
	'friendly_name'		=> 'Automatical Tasks',
	'method'			=> 'spacer',
	),
	'report_autorrdlist'=> array(
	'friendly_name'		=> 'Auto Generated Data Items',
	'method'			=> 'checkbox',
	'description'		=> 'Enable/disable automatic creation of all data items based on given filters.This will be called before report execution.
							Obsolete RRDs will be deleted and all RRDs matching the filter settings will be added.',
	'value'				=> '|arg1:autorrdlist|',
	'default'			=> '',
	),
);

if(read_config_option('reportit_email')) {
	$form_array_auto_tasks['report_email'] = array(
		'method'			=> 'checkbox',
		'friendly_name'		=> 'Auto Generated Email',
		'description'		=> 'If enabled tab "Email" will be activated and all recipients defined under that section will receive automatically an email containing this scheduled report.',
		'value'				=> '|arg1:auto_email|',
		'default'			=> ''
	);
}

if(read_config_option('reportit_archive')) {
	$form_array_auto_tasks['report_autoarchive'] = array(
		'friendly_name'	=> 'Auto Generated Archive',
		'method'		=> 'drop_array',
		'description'	=> 'Define the maximum number of instances which should be archived before the first one will be overwritten.
							Choose "off" if you want to deactivate that RoundRobbin principle (default, but not recommend).
							If you define a lower value of instances than the current archive contains then it will get shrinked automatically within the next run.<br>
							<font style="color: red;">This function is only available for scheduled reports.</font>',
		'value'			=> '|arg1:autoarchive|',
		'default'		=> '0',
		'array'			=> $archive
	);
}

if(read_config_option('reportit_auto_export')) {
	$form_array_auto_tasks['report_autoexport'] = array(
		'friendly_name'		=> 'Auto Generated Exports',
		'method'		    => 'drop_array',
		'description'		=> 'If enabled the report will be automatically exported to a separate subfolder.
								It will be placed within the export folder defined in the report template.<br>
								<font style="color: red;">This function is only available for scheduled reports.</font>',
		'value'				=> '|arg1:autoexport|',
		'array'			    => $format,
		'default'			=> '0'
	);
	$form_array_auto_tasks['report_autoexport_max_records'] = array(
		'friendly_name'	=> 'Export Limitation',
		'method'		=> 'drop_array',
		'description'	=> 'Define the maximum number of instances which should be archived before the first one will be overwritten.
							Choose "off" if you want to deactivate that RoundRobbin principle (default, but not recommend).
							If you define a lower value of instances than the current export folder contains then it will get shrinked automatically within the next run.',
		'value'			=> '|arg1:autoexport_max_records|',
		'default'		=> '0',
		'array'			=> $archive
	);
	$form_array_auto_tasks['report_autoexport_no_formatting'] = array(
		'method'			=> 'checkbox',
		'friendly_name'		=> 'Raw Data Export',
		'description'		=> 'If enabled auto generated exports will contain raw data only. The formatting of measurands will be ignored.',
		'value'				=> '|arg1:autoexport_no_formatting|',
		'default'			=> ''
	);
}

$form_array_admin = array(
	'report_header_1'	=> array(
	'friendly_name'		=> 'General',
	'method'			=> 'spacer',
	),
	'id'				=> array(
	'method'			=> 'hidden_zero',
	'value'				=> '|arg1:id|',
	),
	'tab'				=> array(
	'method'			=> 'hidden_zero',
	'value'				=> 'admin',
	),
	'report_owner'		=> array(
	'friendly_name'		=> 'Change Report Owner',
	'method'			=> 'drop_sql',
	'description'		=> 'Change the owner of this report. Only users with a minimum of reporting rights ("View" or higher) can be selected.',
	'sql'				=> "SELECT DISTINCT a.id, a.username as name FROM user_auth AS a INNER JOIN user_auth_realm AS b
							ON a.id = b.user_id WHERE (b.realm_id = " . REPORTIT_USER_OWNER . " OR b.realm_id = " . REPORTIT_USER_VIEWER . ")
							ORDER BY username",
	'value'				=> '|arg1:user_id|',
	),
	'report_graph_permission'=> array(
	'friendly_name'		=> 'Enable Use of Graph Permissions',
	'method'			=> 'checkbox',
	'description'		=> 'If enabled (default) the list of available data items will be filtered automatically by owner\'s graph permission: "by device".',
	'value'				=> '|arg1:graph_permission|',
	'default'			=> 'on',
	),
);
if(read_config_option('reportit_operator')) $form_array_admin = array_merge($form_array_admin, $form_array_scheduling, $form_array_auto_tasks);


$form_array_presets = array(
	'report_header_1'	=> array(
	'friendly_name'		=> 'General',
	'method'			=> 'spacer',
	),
	'rrdlist_subhead'	=> array(
	'friendly_name'		=> 'Subhead (optional)',
	'method' 			=> "textarea",
	'textarea_rows'		=> "2",
	'textarea_cols'		=> "45",
	'description'		=> "Define an additional subhead that should be on display under the interface description.<br>
							Following variables will be supported (without quotes): '|t1|' '|t2|' '|tmz|' '|d1|' '|d2|'",
	'value'				=> '|arg1:description|',
	'default'			=> '',
	)
);
if(read_config_option('reportit_use_tmz')) {
	$form_array_presets['rrdlist_timezone'] = array(
	'friendly_name'		=> 'Time Zone',
	'method'			=> 'drop_array',
	'description'		=> 'Select the time zone your following shifttime informations will be based on.',
	'value'				=> '|arg1:timezone|',
	'default'			=> '17',
	'array'				=> array_keys($timezones)
	);
}
$form_array_presets_2 = array(
	'host_template_id'	=> array(
	'friendly_name'		=> 'Host Template Filter (optional)',
	'method'			=> 'drop_sql',
	'description'		=> 'Use those data items only, which belong to hosts of this host template.<br>Select \'None\' (default) to deactivate this filter setting.',
	'sql'				=> 'SELECT id,name FROM host_template ORDER BY name',
	'none_value'		=> 'None',
	'value' 			=> '|arg2:host_template_id|',
	),
	'data_source_filter'=> array(
	'friendly_name'		=> 'Data Items Filter (optional)',
	'method'			=> 'textbox',
	'max_length'		=> '100',
	'description'		=> 'Allows additional filtering on the data items descriptions.<br>
							Use SQL wildcards like % and/or _. No regular Expressions!',
	'value' 			=> '|arg2:data_source_filter|',
	),
	'report_header_2'	=> array(
	'friendly_name'		=> 'Working Time',
	'method'			=> 'spacer',
	),
	'id'				=> array(
	'method'			=> 'hidden_zero',
	'value'				=> '|arg1:id|',
	),
	'tab'				=> array(
	'method'			=> 'hidden_zero',
	'value'				=> 'presets',
	),
	'rrdlist_shifttime_start'=> array(
	'friendly_name'		=> 'From',
	'method'			=> 'drop_array',
	'default'			=> '0',
	'description'		=> 'The startpoint of duration you want to analyse',
	'value'				=> '|arg1:start_time|',
	'array'				=> $shifttime,
	),
	'rrdlist_shifttime_end'=> array(
	'friendly_name'		=> 'To',
	'method'			=> 'drop_array',
	'default'			=> '288',
	'description'		=> 'The end of analysing time.',
	'value'				=> '|arg1:end_time|',
	'array'				=> $shifttime2,
	),
	'rrdlist_header_3'	=> array(
	'friendly_name'		=> 'Working Days',
	'method'			=> 'spacer',
	),
	'rrdlist_weekday_start'=> array(
	'friendly_name'		=> 'From',
	'method'			=> 'drop_array',
	'description'		=> 'Define the band of days where shift STARTS!',
	'value'				=> '|arg1:start_day|',
	'default'			=> '0',
	'array'				=> $weekday
	),
	'rrdlist_weekday_end'=> array(
	'friendly_name'		=> 'To',
	'method'			=> 'drop_array',
	'description'		=> 'Example: For a nightshift from Mo(22:30) till Sat(06:30) define Monday to Friday',
	'value'				=> '|arg1:end_day|',
	'default'			=> '6',
	'array'				=> $weekday
	),
);
$form_array_presets = array_merge($form_array_presets, $form_array_presets_2);

$form_array_general = array(
	'id'				=> array(
	'method'			=> 'hidden_zero',
	'value'				=> '|arg1:id|',
	),
	'tab'				=> array(
	'method'			=> 'hidden_zero',
	'value'				=> 'general',
	),
	'template_id'		=> array(
	'method'			=> 'hidden_zero',
	'value'				=> '|arg1:template_id|',
	),
	'report_header_1'	=> array(
	'friendly_name'		=> 'General',
	'method'			=> 'spacer',
	),
	'report_description'=> array(
	'friendly_name' 	=> 'Name',
	'method' 			=> 'textbox',
	'max_length'		=> '100',
	'description'		=> 'The name given to this report',
	'value' 			=> '|arg1:description|',
	),
	'report_template'	=> array(
	'friendly_name'		=> 'Template',
	'method'			=> 'custom',
	'max_length'		=> '100',
	'description'		=> 'The template your configuration depends on',
	'value'				=> '|arg1:template|',
	'default'			=> '',
	),
	'report_public'		=> array(
	'friendly_name'		=> 'Public',
	'method'			=> 'checkbox',
	'description'		=> "If enabled everyone can see your report under tab 'reports'",
	'value'				=> '|arg1:public|',
	'default'			=> '',
	),
	'report_header_2'	=> array(
	'friendly_name'		=> 'Reporting Period',
	'method'			=> 'spacer',
	),
	'report_dynamic'	=> array(
	'friendly_name'		=> 'Sliding Time Frame',
	'method'			=> 'checkbox',
	'description'		=> 'If checked the reporting period will be configured automatically
							in relation to the point of time the calculation starts.',
	'value'				=> '|arg1:sliding|',
	'default'			=> 'on',
	),
	'report_timespan'	=> array(
	'friendly_name'		=> 'Time Frames',
	'method'			=> 'drop_array',
	'description'		=> 'The time frame you want to analyse in relation to the point of time the calculation starts.<br>This means calendar days, calendar months and calendar years.',
	'value'				=> '|arg1:preset_timespan|',
	'array'				=> $timespans,
	),
	'report_present'	=> array(
	'friendly_name'		=> 'Up To The Day of Calculation',
	'method'			=> 'checkbox',
	'description'		=> 'Extend the sliding time frame up to the day the calculation runs.',
	'value'				=> '|arg1:present|',
	'default'			=> '',
	),
	'report_start_date'	=> array(
	'friendly_name'		=> 'Fixed Time Frame - Start Date (From)',
	'method'			=> 'textbox',
	'max_length'		=> '10',
	'description'		=> 'To define the start date use the following format: <b>yyyy-mm-dd</b>',
	'value'				=> '|arg1:start_date|',
	),
	'report_end_date'	=> array(
	'friendly_name'		=> 'Fixed Time Frame - End Date (To)',
	'method'			=> 'textbox',
	'max_length'		=> '10',
	'description'		=> 'To define the end date use the following format: <b>yyyy-mm-dd</b>',
	'value'				=> '|arg1:end_date|',
	)
);
if(!read_config_option('reportit_operator')) $form_array_general = array_merge($form_array_general, $form_array_scheduling, $form_array_auto_tasks);

?>