<?php
/*******************************************************************************

Authors .........Andreas Braun
                 Reinhard Scheck
Contact ........ reportit@outlook.com
Home Site ...... http://sourceforge.net/projects/cacti-reportit/
Program ........ "Cacti-ReportIt"
Version ........ 0.8.0
Purpose ........ Create reports

*******************************************************************************/



/* register ReportIt by using PIA 2.x */
function plugin_reportit_install () {

	api_plugin_register_hook('reportit', 'page_head', 'reportit_page_head', 'setup.php');
    api_plugin_register_hook('reportit', 'top_header_tabs', 'reportit_show_tab', 'setup.php');
    api_plugin_register_hook('reportit', 'top_graph_header_tabs', 'reportit_show_tab', 'setup.php');
    api_plugin_register_hook('reportit', 'draw_navigation_text', 'reportit_draw_navigation_text', 'setup.php');
    api_plugin_register_hook('reportit', 'config_arrays', 'reportit_config_arrays', 'setup.php');
    api_plugin_register_hook('reportit', 'config_settings', 'reportit_config_settings', 'setup.php');
    api_plugin_register_hook('reportit', 'poller_bottom', 'reportit_poller_bottom', 'setup.php');

}


function plugin_reportit_uninstall () {

}


function plugin_reportit_check_config () {
    $status = reportit_check_upgrade ();
    return $status;
}


function plugin_reportit_upgrade () {
    reportit_check_upgrade ();
    return true;
}


function plugin_reportit_version () {
    return reportit_version();
}


function reportit_check_upgrade () {
    global $config;

    $files = array('index.php', 'plugins.php', 'runtime.php');
    if (isset($_SERVER['PHP_SELF']) && !in_array(basename($_SERVER['PHP_SELF']), $files)) {
        return;
    }

    $current    = plugin_reportit_version();
    $current    = $current['version'];
    $old        = db_fetch_row("SELECT * FROM plugin_config WHERE directory='reportit'");
    $tables     = db_fetch_assoc("SHOW TABLE STATUS WHERE `Name` LIKE 'reportit%'");
    $pia        = db_fetch_cell("SELECT value FROM settings WHERE `name` = 'reportit_pia'");


//echo "pia:$pia<br>";

    if (sizeof($old) && $current == $old["version"] && $pia == '2.x'){

//echo "up to date";
        /* ReportIt is up to date */
        return true;


    }elseif (sizeof($old) && $current != $old["version"] && $pia == '2.x') {

        /* Upgrade of an old version based on PIA 2.x */
        /* if the plugin is installed and/or active */

        if ($old["status"] == 1 || $old["status"] == 4) {
                /* install new tables */
				reportit_setup_table();
                /* perform data base upgrade */
				reportit_database_upgrade($old['version']);
        		/* re-register plugins hooks */
        		plugin_reportit_install();
        }

        $info = plugin_reportit_version();
        $id   = db_fetch_cell("SELECT id FROM plugin_config WHERE directory='reportit'");
        db_execute("UPDATE plugin_config SET name='" . $info["longname"] . "',
                    author='"   . $info["author"]   . "',
                    webpage='"  . $info["homepage"] . "',
                    version='"  . $info["version"]  . "'
                    WHERE id='$id'");
        return true;

    }elseif (sizeof($tables)>0 & $pia == false) {

//echo "upgrade from PIA 1.x";
        /* Upgrade of an old version based on PIA 1.x */
        /* Check if an old version of reportit exists */

        if(reportit_upgrade_requirements()) {

            /* allow the upgrade script to run for as long as it needs to */
            ini_set("max_execution_time", "0");
        	/* perform data base upgrade */
            reportit_database_upgrade('old_structure');
        	/* re-register plugins hooks */
        	plugin_reportit_install();

			return true;

        }else {
            return false;
        }

    }elseif (sizeof($tables)>0 & $pia == '1.x') {

//echo "post-installation";
	    /* post-installation for PIA 1.x upgrade */
	    reportit_database_upgrade('post-installation');
	    return true;
    }else {

//echo "new";
        /* New installation of ReportIt */
        /* install basic tables */

        reportit_setup_table();
        return true;
    }
}



function reportit_upgrade_requirements(){

    /* check if existing version of ReportIt is ready to upgrade */
    global $config, $database_default;

    $sql        = "show tables from `" . $database_default . "`";
    $result     = db_fetch_assoc($sql);
    $tables     = array();

    foreach($result as $index => $arr) {
	foreach($arr as $tbl) {
	    if(strpos( $tbl, 'reportit_functions') !== FALSE) {
		cacti_log("REPORTIT UPGRADE ERROR: Existing version of ReportIt is too old. Use uninstall.php to remove your old installation.");
                return false;
	    }
	}
    }

    /* check used version of MySQL */
    $sql        = "SELECT VERSION()";
    $result     = db_fetch_cell($sql) or die (mysql_error());
    if(substr($result, 0, 2) != '5.') {
	cacti_log("REPORTIT UPGRADE ERROR: MySQL 5.0 or higher not detected!");
	return false;
    }
    return true;
}



function reportit_database_upgrade($action) {

    global $config, $database_default;

    if($action == 'old_structure') {
        include_once($config["base_path"] . "/plugins/reportit/upgrade/0_4_0_to_0_7_0.php");
        upgrade_reportit_0_4_0_to_0_7_0();
    	include_once($config["base_path"] . "/plugins/reportit/upgrade/0_7_0_to_0_7_2.php");
    	upgrade_reportit_0_7_0_to_0_7_2();
    }elseif($action == 'post-installation') {
		include_once($config["base_path"] . "/plugins/reportit/upgrade/0_4_0_to_0_7_0.php");
		upgrade_pia_1x_to_pia_2x();
    }elseif($action == '0.7.0' || $action == '0.7.1') {
    	include_once($config["base_path"] . "/plugins/reportit/upgrade/0_7_0_to_0_7_2.php");
		upgrade_reportit_0_7_0_to_0_7_2();
    }elseif($action == '0.7.2' || $action == '0.7.3') {
    	include_once($config["base_path"] . "/plugins/reportit/upgrade/0_7_2_to_0_7_4.php");
    	upgrade_reportit_0_7_2_to_0_7_4();
    }
}



function reportit_version ($specifically = FALSE) {

    $info = array(  'name'      => 'reportit',
                    'version'   => '0.8.0',
                    'longname'  => 'Cacti-ReportIt',
                    'author'    => 'The Cacti Group',
                    'homepage'  => 'http://sourceforge.net/projects/cacti-reportit/',
                    'email'     => 'reportit@outlook.com',
                    'url'       => ''
                    );
    $return = ($specifically === FALSE) ? $info : $info[$specifically];

    return $return;
}



function reportit_draw_navigation_text ($nav) {

    $nav["cc_reports.php:"]                         = array("title" => "Reports", "mapping" => "index.php:", "url" => "cc_reports.php", "level" => "1");
    $nav["cc_reports.php:save"]                     = array("title" => "(Edit)", "mapping" => "index.php:,?", "url" => "cc_templates.php", "level" => "2");
    $nav["cc_reports.php:report_add"]               = array("title" => "Add", "mapping" => "index.php:,?", "url" => "cc_templates.php", "level" => "2");
    $nav["cc_reports.php:report_edit"]              = array("title" => "(Edit)", "mapping" => "index.php:,?", "url" => "cc_templates.php", "level" => "2");
    $nav["cc_reports.php:actions"]                  = array("title" => "Actions", "mapping" => "index.php:,?", "url" => "cc_templates.php", "level" => "2");

    $nav["cc_rrdlist.php:"]                         = array("title" => "Data Items", "mapping" => "index.php:,cc_reports.php:", "url" => "cc_templates.php", "level" => "2");
    $nav["cc_rrdlist.php:save"]                     = array("title" => "(Edit)", "mapping" => "index.php:,cc_reports.php:,cc_rrdlist.php:", "url" => "", "level" => "3");
    $nav["cc_rrdlist.php:rrdlist_edit"]             = array("title" => "(Edit)", "mapping" => "index.php:,cc_reports.php:,cc_rrdlist.php:", "url" => "", "level" => "3");
    $nav["cc_rrdlist.php:actions"]                  = array("title" => "Actions", "mapping" => "index.php:,cc_reports.php:,cc_rrdlist.php:", "url" => "", "level" => "3");

    $nav["cc_items.php:"]                           = array("title" => "Add", "mapping" => "index.php:,cc_reports.php:,cc_rrdlist.php:", "url"  => "cc_templates.php", "level" => "3");
    $nav["cc_items.php:save"]                        = array("title" => "(Edit)", "mapping" => "index.php:,cc_reports.php:,cc_rrdlist.php:", "url" => "", "level" => "4");

    $nav["cc_templates.php:"]                       = array("title" => "Report Templates", "mapping" => "index.php:", "url" => "cc_templates.php", "level" => "1");
    $nav["cc_templates.php:save"]                   = array("title" => "(Edit)", "mapping" => "index.php:,cc_templates.php:", "url" => "", "level" => "2");
    $nav["cc_templates.php:template_edit"]          = array("title" => "(Edit)", "mapping" => "index.php:,cc_templates.php:", "url" => "", "level" => "2");
    $nav["cc_templates.php:template_new"]           = array("title" => "Add", "mapping" => "index.php:,cc_templates.php:", "url" => "", "level" => "2");
    $nav["cc_templates.php:template_import_wizard"] = array("title" => "Import", "mapping" => "index.php:,cc_templates.php:", "url" => "", "level" => "2");
    $nav["cc_templates.php:template_upload_wizard"] = array("title" => "Import", "mapping" => "index.php:,cc_templates.php:", "url" => "", "level" => "2");
    $nav["cc_templates.php:template_import"]        = array("title" => "Export", "mapping" => "index.php:,cc_templates.php:", "url" => "", "level" => "2");
    $nav["cc_templates.php:template_export"]        = array("title" => "Export", "mapping" => "index.php:,cc_templates.php:", "url" => "", "level" => "2");
    $nav["cc_templates.php:template_export_wizard"] = array("title" => "Export", "mapping" => "index.php:,cc_templates.php:", "url" => "", "level" => "2");
    $nav["cc_templates.php:actions"]                = array("title" => "Actions", "mapping" => "index.php:,cc_templates.php:", "url" => "", "level" => "2");

    $nav["cc_measurands.php:"]                      = array("title" => "Measurands", "mapping" => "index.php:,cc_templates.php:", "url" => "cc_templates.php", "level" => "2");
    $nav["cc_measurands.php:save"]                  = array("title" => "(Edit)", "mapping" => "index.php:,cc_templates.php:,cc_measurands.php:", "url" => "", "level" => "3");
    $nav["cc_measurands.php:measurand_edit"]        = array("title" => "(Edit)", "mapping" => "index.php:,cc_templates.php:,cc_measurands.php:", "url" => "", "level" => "3");
    $nav["cc_measurands.php:actions"]               = array("title" => "Actions", "mapping" => "index.php:,cc_templates.php:,cc_measurands.php:", "url" => "", "level" => "3");

    $nav["cc_variables.php:"]                       = array("title" => "Variables", "mapping" => "index.php:,cc_templates.php:", "url" => "cc_templates.php", "level" => "2");
    $nav["cc_variables.php:save"]                   = array("title" => "(Edit)", "mapping" => "index.php:,cc_templates.php:,cc_variables.php:", "url" => "", "level" => "3");
    $nav["cc_variables.php:variable_edit"]          = array("title" => "(Edit)", "mapping" => "index.php:,cc_templates.php:,cc_variables.php:", "url" => "", "level" => "3");
    $nav["cc_variables.php:actions"]                = array("title" => "Actions", "mapping" => "index.php:,cc_templates.php:,cc_variables.php:", "url" => "", "level" => "3");

    $nav["cc_run.php:calculation"]                  = array("title" => "Report Calculation", "mapping" => "index.php:,cc_reports.php:", "url" => "", "level" => "2");

    $nav["cc_view.php:"]                            = array("title" => "Public Reports", "mapping" => "index.php:", "url" => "cc_view.php", "level" => "1");
    $nav["cc_view.php:show_report"]                 = array("title" => "Show Report", "mapping" => "index.php:,cc_view.php:", "url" => "", "level" => "2");
    $nav["cc_view.php:export"]                      = array("title" => "Export Report", "mapping" => "index.php:,cc_view.php:", "url" => "", "level" => "2");
    $nav["cc_view.php:show_graphs"]                 = array("title" => "Show Report", "mapping" => "index.php:,cc_view.php:", "url" => "", "level" => "2");

    $nav["cc_charts.php:"]                          = array("title" => "Public Report Charts", "mapping" => "index.php:", "url" => "cc_graph.php", "level" => "1");
    $nav["cc_charts.php:bar"]                       = array("title" => "Bar Chart", "mapping" => "index.php:,cc_graph.php:", "url" => "", "level" => "2");
    $nav["cc_charts.php:pie"]                       = array("title" => "Pie Chart", "mapping" => "index.php:,cc_graph.php:", "url" => "", "level" => "2");

    return $nav;
}



function reportit_config_arrays () {
    global $user_auth_realms, $user_auth_realm_filenames, $menu;

    /* register all realms of ReportIt */
    api_plugin_register_realm('reportit', 'cc_view.php,cc_charts.php', 'Plugin -> ReportIt: view', 1);
    api_plugin_register_realm('reportit', 'cc_reports.php,cc_rrdlist.php,cc_items.php,cc_run.php', 'Plugin -> ReportIt: create', 1);
    api_plugin_register_realm('reportit', 'cc_templates.php,cc_measurands.php,cc_variables.php', 'Plugin -> ReportIt: administrate', 1);

    /* show additional menu entries if plugin is enabled */
    if(api_plugin_is_enabled('reportit')) {
        $menu["Management"]['plugins/reportit/cc_reports.php']  = "Report Configurations";
        $menu["Templates"]['plugins/reportit/cc_templates.php'] = "Report Templates";
    }
}



function reportit_config_settings () {

    reportit_define_constants();

    global $tabs, $tabs_graphs, $settings, $graph_dateformats, $graph_datechar, $settings_graphs, $config;

    /* presets */
    $datetime               = array('local', 'global');
    $csv_column_separator   = array(",", ";", "Tab", "Blank");
    $csv_decimal_separator  = array(",", ".");
    $rrdtool_api            = array('PHP BINDINGS (FAST)', 'RRDTOOL CACTI (SLOW)', 'RRDTOOL SERVER (SLOW)');
    $rrdtool_quality        = array('2'=>'LOW','3'=> 'MEDIUM','4'=> 'HIGH','5'=> 'ULTIMATE');
    $operator               = array('Power User (Report Owner)', 'Super User (Report Admin)');

    $graphs                 = array('-10' => 'Bar (vertical)',
                                     '10' => 'Bar (horizontal)',
                                     '20' => 'Line',
                                     '21' => 'Area',
                                     '30' => 'Pie',
                                     '40' => 'Spider');

    $font                   = REPORTIT_BASE_PATH . '/lib_ext/fonts/DejaVuSansMono.ttf';
    $tfont                  = REPORTIT_BASE_PATH . '/lib_ext/fonts/DejaVuSansMono-Bold.ttf';

    /* setup ReportIt's global configuration area */
    $tabs["reports"]        = "Reports";

    $temp =  array(
        "reportit_header1"          => array(
            "friendly_name"         => "General",
            "method"                => "spacer",
        ),
        "reportit_met"              => array(
            "friendly_name"         => "Maximum Execution Time (in seconds)",
            "description"           => "Optional: Maximum execution time of one calculation",
            "method"                => "textbox",
            "max_length"            => "4",
            "default"               => "300",
        ),
        "reportit_maxrrdchg"        => array(
            "friendly_name"         => "Maximum Record Count Change",
            "description"           => "Optional (Auto-Generate RRD List): Do not change RRD List of any Report
                                        if Record Count Change is greater than this Number
                                        This is to avoid unwanted and disastrous changes on RRD Lists",
            "method"                => "textbox",
            "max_length"            => "4",
            "default"               => "100",
        ),
        "reportit_use_tmz"          => array(
            "friendly_name"         => "Time Zones",
            "description"           => "Enable/disable the use of time zones for data item's "
                                        ."configuration and report calculation. "
                                        ."In the former case server time has to be set up to GMT/UTC!",
            "method"                => "checkbox",
            "default"               => "",
        ),
        "reportit_show_tmz"         => array(
            "friendly_name"         => "Show Local Time Zone",
            "description"           => "Enable/disable to display server's timezone on the headlines.",
            "method"                => "checkbox",
            "default"               => "on",
        ),
        "reportit_use_IEC"          => array(
            "friendly_name"         => "SI-Prefixes",
            "description"           => "Enable/disable the use of correct SI-Prefixes for binary multiples"
                                        ." under the terms of <a href='http://www.ieee.org'>IEEE 1541</a>"
                                        ." and <a href='http://www.iec.ch/zone/si/si_bytes.htm'>IEC 60027-2</a>.",
            "method"                => "checkbox",
            "default"               => "on",
        ),
        "reportit_operator"         => array(
            "friendly_name"         => "Operator for Scheduled Reporting",
            "description"           => "Choose the level which is necessary to configure all options of scheduled reporting in a report configuration.",
            "method"                => "drop_array",
            "array"                 => $operator,
            "default"               => "1",
        ),
        "reportit_header2"          => array(
            "friendly_name"         => "RRDtool",
            "method"                => "spacer",
        ),
        "reportit_API"              => array(
            "friendly_name"         => "RRDtool Connection",
            "description"           => "Choose the way to connect to RRDtool.",
            "method"                => "drop_array",
            "array"                 => $rrdtool_api,
            "default"               => "1",
        ),
        "reportit_RRDID"            => array(
            "friendly_name"         => "RRDtool Server IP",
            "description"           => "Optional: Configured IP address of the RRDtool server.",
            "method"                => "textbox",
            "max_length"            => "15",
            "default"               => "127.0.0.1",
        ),
        "reportit_RRDPort"          => array(
            "friendly_name"         => "RRDtool Server Port",
            "description"           => "Optional: Configured port setting of RRDtool server.",
            "method"                => "textbox",
            "max_length"            => "5",
            "default"               => "13900",
        ),
        "reportit_header3"          => array(
            "friendly_name"         => "Export Settings",
            "method"                => "spacer",
        ),
        "reportit_exp_filename"     => array(
            "friendly_name"         => "Filename Format",
            "description"           => "The name format for the export files created on demand.",
            "max_length"            => "100",
            "method"                => "textbox",
            "default"               => "cacti_report_<report_id>",
        ),
        "reportit_exp_header"       => array(
            "friendly_name"         => "Export Header",
            "description"           => "The header description for export files",
            "method"                => "textarea",
            "textarea_rows"         => "3",
            "textarea_cols"         => "60",
            "default"               => "# Your report header\n# <cacti_version> <reportit_version>",
        ),
        "reportit_header4"          => array(
            "friendly_name"         => "Auto Archiving",
            "method"                => "spacer",
        ),
        "reportit_archive"          => array(
            "friendly_name"         => "Enabled",
            "description"           => "If enabled the result of every scheduled report will be archived automatically",
            "method"                => "checkbox",
            "default"               => "",
        ),
        "reportit_arc_lifecycle"    => array(
            "friendly_name"         => "Cache Life Cyle (in seconds)",
            "description"           => "Number of seconds an archived report will be cached without any hit",
            "method"                => "textbox",
            "max_length"            => "4",
            "default"               => "300",
        ),
        "reportit_arc_folder"       => array(
            "friendly_name"         => "Archive Path",
            "description"           => "Optional: The path to an archive folder for saving. If not defined subfolder \"archive\" will be used.",
            "method"                => "dirpath",
            "max_length"            => "255",
            "default"               => REPORTIT_ARC_FD,
        ),
        "reportit_header5"          => array(
            "friendly_name"         => "Auto E-Mailing",
            "method"                => "spacer",
        ),
        "reportit_email"            => array(
            "friendly_name"         => "Enable",
            "description"           => "If enabled scheduled reports can be emailed automatically to a list of recipients.<br>
                                        This feature requires a configured version of the \"Settings Plugin\".",
            "method"                => "checkbox",
            "default"               => "",
        ),
        "reportit_header6"          => array(
            "friendly_name"         => "Auto Exporting",
            "method"                => "spacer",
        ),
        "reportit_auto_export"      => array(
            "friendly_name"         => "Enabled",
            "description"           => "If enabled scheduled reports can be exported automatically to a specified folder.<br>
                                        Therefore a full structured path architecture will be used:<br>
                                        Main Folder -> Template Folder (if defined) or Template ID -> Report ID -> Report",
            "method"                => "checkbox",
            "default"               => "",
        ),
        "reportit_exp_folder"       => array(
            "friendly_name"         => "Export Path",
            "description"           => "Optional: The main path to an export folder for saving the exports. If not defined subfolder \"exports\" will be used.",
            "method"                => "dirpath",
            "max_length"            => "255",
            "default"               => REPORTIT_EXP_FD,
        ),
        "reportit_header7"          => array(
            "friendly_name"         => "Graph Settings",
            "method"                => "spacer",
        ),
        "reportit_graph"            => array(
            "friendly_name"         => "Enabled",
            "description"           => "Enable/disable graph functionality",
            "method"                => "checkbox",
            "default"               => "off",
        ),
        "reportit_g_mheight"        => array(
            "friendly_name"         => "Maximum Graph Height",
            "description"           => "The maximum height of Reportit graphs in pixels.<br>
                                        Warning! GD functions are very memory intensive. Be sure to set \"memory_limit\" high enough.",
            "method"                => "textbox",
            "max_length"            => "4",
            "default"               => "320",
        ),
        "reportit_g_mwidth"         => array(
            "friendly_name"         => "Maximum Graph Width",
            "description"           => "The maximum width of Reportit graphs in pixels.<br>
                                        Warning! GD functions are very memory intensive. Be sure to set \"memory_limit\" high enough.",
            "method"                => "textbox",
            "max_length"            => "4",
            "default"               => "480",
        ),
        "reportit_g_quality"        => array(
            "friendly_name"         => "Quality Level",
            "description"           => "Choose the level of quality.<br>
                                        Warning! A higher quality setting has a lower calculation speed and requires more memory.",
            "method"                => "drop_array",
            "array"                 => $rrdtool_quality,
            "default"               => "1",
        ),
        "reportit_g_mono"           => array(
            "friendly_name"         => "Monospace Fonts",
            "description"           => "It's recommend to use monospace fonts like Lucida, Courier, Vera or DejaVu instead of the other types.",
            "method"                => "checkbox",
            "default"               => "on",
        ),
        "reportit_g_tfont"          => array(
            "friendly_name"         => "Title Font File",
            "description"           => "Define font file to use for graph titles",
            "method"                => "filepath",
            "max_length"            => "255",
            "default"               => $tfont,
        ),
        "reportit_g_afont"          => array(
            "friendly_name"         => "Axis Font File",
            "description"           => "Define font file to use for graph axis",
            "method"                => "filepath",
            "max_length"            => "255",
            "default"               => $font,
        ),
    );

    if (isset($settings["reports"])) {
        $settings["reports"] = array_merge($settings_graphs, $temp);
    }else {
        $settings["reports"] = $temp;
        unset($temp);
    }

    //Extension of graph settings
    $tabs_graphs['reportit']= 'REPORTIT General Settings';
    $temp =  array(
        "reportit_view_filter"      => array(
            "friendly_name"         => "Separate Report View filter",
            "description"           => "Enable/disable the use of an individual filter per report",
            "method"                => "checkbox",
            "default"               => "on",
        ),
        "reportit_max_rows"         => array(
            "friendly_name"         => "Rows Per Page",
            "description"           => "The number of rows to display on a single page",
            "method"                => "textbox",
            "max_length"            => "3",
            "default"               => "25",
        ),
        "reportit_csv_header"       => array(
            "friendly_name"         => "REPORTIT Export Settings",
            "method"                => "spacer",
        ),
        "reportit_csv_column_s"     => array(
            "friendly_name"         => "CSV Column Separator",
            "description"           => "The column seperator to be used for CSV exports",
            "method"                => "drop_array",
            "array"                 => $csv_column_separator,
            "default"               => '1',
        ),
        "reportit_csv_decimal_s"    => array(
            "friendly_name"         => "CSV Decimal Separator",
            "description"           => "The symbol indicating the end of the integer part and the beginning of the fractional part",
            "method"                => "drop_array",
            "array"                 => $csv_decimal_separator,
            "default"               => '1',
        ),
        "reportit_graph_header"     => array(
            "friendly_name"         => "REPORTIT Graph Settings",
            "method"                => "spacer",
        ),
        "reportit_g_default"        => array(
            "friendly_name"         => "Default Chart",
            "description"           => "Define your default chart that should be shown first",
            "method"                => "drop_array",
            "array"                 => $graphs,
            "default"               => "-10",
        ),
        "reportit_g_height"         => array(
            "friendly_name"         => "Graph Height",
            "description"           => "The height of Reportit graphs in pixel.",
            "method"                => "textbox",
            "max_length"            => "4",
            "default"               => "320",
        ),
        "reportit_g_width"          => array(
            "friendly_name"         => "Graph Width",
            "description"           => "The width of Reportit graphs in pixel.<br>",
            "method"                => "textbox",
            "max_length"            => "4",
            "default"               => "480",
        ),
        "reportit_g_showgrid"       => array(
            "friendly_name"         => "Show Graph Grid",
            "description"           => "Enable/disable Graph Grid for Reportit Graphs",
            "method"                => "checkbox",
            "default"               => "off",
        ),
    );

    if (isset($settings_graphs["reports"]))
        $settings_graphs['reportit'] = array_merge($settings_graphs['reportit'],$temp);
    else
        $settings_graphs['reportit'] =$temp;
    unset($temp);
}



function reportit_show_tab () {

    global $config, $user_auth_realms, $user_auth_realm_filenames;
    $realm_id2 = 0;

    if (isset($user_auth_realm_filenames{basename('cc_view.php')})) {
        $realm_id2 = $user_auth_realm_filenames{basename('cc_view.php')};
    }

    $sql = "SELECT user_auth_realm.realm_id
            FROM user_auth_realm
            WHERE user_auth_realm.user_id='" . $_SESSION["sess_user_id"] . "'
            AND user_auth_realm.realm_id='$realm_id2'";

    if ((db_fetch_assoc($sql)) || (empty($realm_id2))) {
        print '<a href="' . $config['url_path'] . 'plugins/reportit/cc_view.php"><img src="' . $config['url_path'] . 'plugins/reportit/images/tab_reportit_' . (basename($_SERVER['PHP_SELF']) == 'cc_view.php' ? 'down' : 'up'). '.png" alt="reportit" align="absmiddle" border="0"></a>';
    }
}



function reportit_setup_table ($upgrade = false) {

    global $config, $database_default;
    include_once($config["library_path"] . "/database.php");

    $sql        = "show tables from `" . $database_default . "`";
    $result     = db_fetch_assoc($sql) or die (mysql_error());
    $tables     = array();
    $sql        = array();

    foreach($result as $index => $arr) {
        foreach($arr as $tbl) {
            $tables[] = $tbl;
        }
    }

    if (!in_array('reportit_reports', $tables)) {
        $sql[] = "CREATE TABLE reportit_reports (
            `id`                        int(11)             NOT NULL auto_increment,
            `description`               varchar(255)        NOT NULL default '',
            `user_id`                   int(11)             NOT NULL default '0',
            `template_id`               int(11)             NOT NULL default '0',
            `host_template_id`          mediumint(8)        UNSIGNED NOT NULL DEFAULT 0,
            `data_source_filter`        varchar(255)        NOT NULL DEFAULT '',
            `preset_timespan`           varchar(255)        NOT NULL default '',
            `last_run`                  datetime            NOT NULL default '0000-00-00 00:00:00',
            `runtime`                   int(11)             NOT NULL default '0',
            `public`                    tinyint(1)          NOT NULL default '0',
            `start_date`                date                NOT NULL default '0000-00-00',
            `end_date`                  date                NOT NULL default '0000-00-00',
            `ds_description`            varchar(5000)       NOT NULL default '',
            `rs_def`                    varchar(255)        NOT NULL default '',
            `sp_def`                    varchar(255)        NOT NULL default '',
            `sliding`                   tinyint(1)          NOT NULL default '0',
            `present`                   tinyint(1)          NOT NULL default '0',
            `scheduled`                 tinyint(1)          NOT NULL default '0',
            `autorrdlist`               tinyint(1)          NOT NULL DEFAULT '0',
            `auto_email`                tinyint(1)          NOT NULL DEFAULT '0',
            `email_subject`             varchar(255)        NOT NULL default '',
            `email_body`                varchar(1000)       NOT NULL default '',
            `email_format`              varchar(255)        NOT NULL default '',
            `subhead`                   tinyint(1)          NOT NULL default '0',
            `in_process`                tinyint(1)          NOT NULL default '0',
            `graph_permission`          tinyint(1)          NOT NULL DEFAULT '1',
            `frequency`                 varchar(255)        NOT NULL default '',
            `autoarchive`               mediumint(8)        UNSIGNED NOT NULL DEFAULT 1,
            `autoexport`                varchar(255)        NOT NULL default '',
            `autoexport_max_records`    smallint            NOT NULL DEFAULT '0',
            `autoexport_no_formatting`  tinyint(1)          NOT NULL default '0',
            PRIMARY KEY  (`id`)) ENGINE=MyISAM;";
    }

    if (!in_array('reportit_templates', $tables)) {
        $sql[] = "CREATE TABLE reportit_templates (
            `id`                        int(11)             NOT NULL auto_increment,
            `description`               varchar(255)        NOT NULL default '',
            `pre_filter`                varchar(255)        NOT NULL default '',
            `data_template_id`          int(11)             NOT NULL default '0',
            `locked`                    tinyint(1)          NOT NULL default '0',
            `export_folder`             varchar(255)        NOT NULL default '',
            PRIMARY KEY  (`id`)) ENGINE=MyISAM;";
    }

    if (!in_array('reportit_measurands', $tables)) {
        $sql[] = "CREATE TABLE reportit_measurands (
            `id`                        int(11)             NOT NULL auto_increment,
            `template_id`               int(11)             NOT NULL default '0',
            `description`               varchar(255)        NOT NULL default '',
            `abbreviation`              varchar(255)        NOT NULL default '',
            `calc_formula`              varchar(255)        NOT NULL default '',
            `unit`                      varchar(255)        NOT NULL default '',
            `visible`                   tinyint(1)          NOT NULL default '1',
            `spanned`                   tinyint(1)          NOT NULL default '0',
            `rounding`                  tinyint(1)          NOT NULL default '0',
            `cf`                        int(11)             NOT NULL default '1',
            `data_type`                 SMALLINT            NOT NULL DEFAULT '1',
            `data_precision`            SMALLINT            NOT NULL DEFAULT '2',
            PRIMARY KEY  (`id`)) ENGINE=MyISAM;";
    }

    if (!in_array('reportit_variables', $tables)) {
        $sql[] = "CREATE TABLE reportit_variables (
            `id`                        int(11)             NOT NULL auto_increment,
            `template_id`               int(11)             NOT NULL default '0',
            `abbreviation`              varchar(255)        NOT NULL default '',
            `name`                      varchar(255)        NOT NULL default '',
            `description`               varchar(255)        NOT NULL default '',
            `max_value`                 float               NOT NULL default '0',
            `min_value`                 float               NOT NULL default '0',
            `default_value`             float               NOT NULL default '0',
            `input_type`                tinyint(1)          NOT NULL default '0',
            `stepping`                  float               NOT NULL default '0',
            PRIMARY KEY  (`id`)) ENGINE=MyISAM;";
    }

    if (!in_array('reportit_rvars', $tables)) {
        $sql[] = "CREATE TABLE reportit_rvars (
            `id`                        int(11)             NOT NULL auto_increment,
            `template_id`               int(11)             NOT NULL default '0',
            `report_id`                 int(11)             NOT NULL default '0',
            `variable_id`               int(11)             NOT NULL default '0',
            `value`                     float               NOT NULL default '0',
            PRIMARY KEY  (`id`)) ENGINE=MyISAM;";
    }

    if (!in_array('reportit_presets', $tables)) {
        $sql[] = "CREATE TABLE reportit_presets (
            `id`                        int(11)             NOT NULL DEFAULT 0,
            `description`               varchar(255)        NOT NULL default '',
            `start_day`                 varchar(255)        NOT NULL default 'Monday',
            `end_day`                   varchar(255)        NOT NULL default 'Sunday',
            `start_time`                time                NOT NULL default '00:00:00',
            `end_time`                  time                NOT NULL default '24:00:00',
            `timezone`                  varchar(255)        NOT NULL default 'GMT',
            PRIMARY KEY  (`id`)) ENGINE=MyISAM;";
    }

    if (!in_array('reportit_recipients', $tables)) {
        $sql[] = "CREATE TABLE reportit_recipients (
            `id`                        int(11)             NOT NULL auto_increment,
            `report_id`                 int(11)             NOT NULL DEFAULT '0',
            `email`                     varchar(255)        NOT NULL default '',
            `name`                      varchar(255)        NOT NULL default '',
            PRIMARY KEY  (`id`)) ENGINE=MyISAM;";
    }

    if (!in_array('reportit_data_items', $tables)) {
        $sql[] = "CREATE TABLE reportit_data_items (
            `id`                        int(11)             NOT NULL default '0',
            `report_id`                 int(11)             NOT NULL default '0',
            `description`               varchar(255)        NOT NULL default '',
            `start_day`                 varchar(255)        NOT NULL default 'Monday',
            `end_day`                   varchar(255)        NOT NULL default 'Sunday',
            `start_time`                time                NOT NULL default '00:00:00',
            `end_time`                  time                NOT NULL default '24:00:00',
            `timezone`                  varchar(255)        NOT NULL default 'GMT',
            PRIMARY KEY (`id`, `report_id`), INDEX (`report_id`)) ENGINE = MYISAM;";
    }

    if (!in_array('reportit_data_source_items', $tables)) {
        $sql[] = "CREATE TABLE reportit_data_source_items (
            `id`                        int(11)             NOT NULL default '0',
            `template_id`               int(11)             NOT NULL default '0',
            `data_source_name`          varchar(255)        NOT NULL default '',
            `data_source_alias`         varchar(255)        NOT NULL default '',
            PRIMARY KEY (`id`, `template_id`), INDEX (`template_id`)) ENGINE = MYISAM;";
    }

/* tables for caching the archive informations */

    if (!in_array('reportit_cache_reports', $tables)) {
        $sql[] = "CREATE TABLE reportit_cache_reports (
            `cache_id`                  varchar(255)        NOT NULL default '',
            `id`                        int(11)             NOT NULL default '0',
            `description`               varchar(255)        NOT NULL default '',
            `user_id`                   int(11)             NOT NULL default '0',
            `template_id`               int(11)             NOT NULL default '0',
            `host_template_id`          mediumint(8)        UNSIGNED NOT NULL DEFAULT 0,
            `data_source_filter`        varchar(255)        NOT NULL DEFAULT '',
            `preset_timespan`           varchar(255)        NOT NULL default '',
            `last_run`                  datetime            NOT NULL default '0000-00-00 00:00:00',
            `runtime`                   float               NOT NULL default '0',
            `public`                    tinyint(1)          NOT NULL default '0',
            `start_date`                date                NOT NULL default '0000-00-00',
            `end_date`                  date                NOT NULL default '0000-00-00',
            `ds_description`            varchar(5000)       NOT NULL default '',
            `rs_def`                    varchar(255)        NOT NULL default '',
            `sp_def`                    varchar(255)        NOT NULL default '',
            `sliding`                   tinyint(1)          NOT NULL default '0',
            `present`                   tinyint(1)          NOT NULL default '0',
            `scheduled`                 tinyint(1)          NOT NULL default '0',
            `autorrdlist`               tinyint(1)          NOT NULL DEFAULT '0',
            `auto_email`                tinyint(1)          NOT NULL DEFAULT '0',
            `email_subject`             varchar(255)        NOT NULL default '',
            `email_body`                varchar(1000)       NOT NULL default '',
            `email_format`              varchar(255)        NOT NULL default '',
            `subhead`                   tinyint(1)          NOT NULL default '0',
            `in_process`                tinyint(1)          NOT NULL default '0',
            `graph_permission`          tinyint(1)          NOT NULL DEFAULT '1',
            `frequency`                 varchar(255)        NOT NULL default '',
            `autoarchive`               mediumint(8)        UNSIGNED NOT NULL DEFAULT 0,
            `template_name`             varchar(255)        NOT NULL default '',
            `data_template_alias`       varchar(10000)      NOT NULL default '',
            `owner`                     varchar(255)        NOT NULL default '',
            `autoexport`                varchar(255)        NOT NULL default '',
            `autoexport_max_records`    smallint            NOT NULL DEFAULT '0',
            `autoexport_no_formatting`  tinyint(1)          NOT NULL default '0',
            PRIMARY KEY  (`cache_id`)) ENGINE=MyISAM;";
    }

    if (!in_array('reportit_cache_measurands', $tables)) {
        $sql[] = "CREATE TABLE reportit_cache_measurands (
            `cache_id`                  varchar(255)        NOT NULL default '',
            `id`                        int(11)             NOT NULL default '0',
            `template_id`               int(11)             NOT NULL default '0',
            `description`               varchar(255)        NOT NULL default '',
            `abbreviation`              varchar(255)        NOT NULL default '',
            `calc_formula`              varchar(255)        NOT NULL default '',
            `unit`                      varchar(255)        NOT NULL default '',
            `visible`                   tinyint(1)          NOT NULL default '1',
            `spanned`                   tinyint(1)          NOT NULL default '0',
            `rounding`                  tinyint(1)          NOT NULL default '0',
            `cf`                        int(11)             NOT NULL default '1',
            `data_type`                 SMALLINT            NOT NULL DEFAULT '1',
            `data_precision`            SMALLINT            NOT NULL DEFAULT '2',
            INDEX (`cache_id`), UNIQUE(`cache_id`, `id`)) ENGINE=MyISAM;";
    }

    if (!in_array('reportit_cache_variables', $tables)) {
        $sql[] = "CREATE TABLE reportit_cache_variables (
            `cache_id`                  varchar(255)        NOT NULL default '',
            `id`                        int(11)             NOT NULL default '0',
            `name`                      varchar(255)        NOT NULL default '',
            `description`               varchar(255)        NOT NULL default '',
            `value`                     float               NOT NULL default '0',
            `max_value`                 float               NOT NULL default '0',
            `min_value`                 float               NOT NULL default '0',
            INDEX (`cache_id`), UNIQUE(`cache_id`, `id`)) ENGINE=MyISAM;";
    }

    if (!empty($sql)) {
        for ($a = 0; $a < count($sql); $a++) {
            $result = mysql_query($sql[$a]);
        }
    }

    if(!$upgrade) db_execute("REPLACE INTO settings (`name` , `value`) VALUES ('reportit_pia', '2.x')");

}



function reportit_define_constants(){
    global $config;

    /* realm IDs which have been defined dynamically by PIA 2.x */
    $ids = db_fetch_assoc("SELECT id FROM plugin_realms WHERE plugin='reportit' ORDER BY id ASC");

    @define("REPORTIT_USER_ADMIN", 100 + $ids[0]['id']);
    @define("REPORTIT_USER_OWNER", 100 + $ids[1]['id']);
    @define("REPORTIT_USER_VIEWER", 100 + $ids[2]['id']);

    /* default width of tables */
    @define("REPORTIT_WIDTH", '100%');

    /* define ReportIt's base paths */
    @define("REPORTIT_BASE_PATH", strtr(ereg_replace("(.*)[\\\/]include", "\\1", dirname(__FILE__)), "\\", "/"));

    /* with regard to Cacti 0.8.8 it becomes necessarily to replace the old path settings */
    @define("CACTI_BASE_PATH", $config['base_path']);
    @define("CACTI_INCLUDE_PATH", CACTI_BASE_PATH . '/include/');

    /* path where PCLZIP will save temporary files */
    @define('REPORTIT_TMP_FD', REPORTIT_BASE_PATH . '/tmp/');
    /* path where archives will be saved per default */
    @define('REPORTIT_ARC_FD', REPORTIT_BASE_PATH . '/archive/');
    /* path where exports will be saved per default */
    @define('REPORTIT_EXP_FD', REPORTIT_BASE_PATH . '/exports/');
}



function reportit_poller_bottom() {

    $str = '';
    $ids = '';
    $cnt = 0;

    $lifecycle = read_config_option("reportit_arc_lifecycle", TRUE);
    $logging_level = read_config_option("log_verbosity", TRUE);

    /* fetch all tables whose life cycle has been expired */
    $sql =  "SHOW TABLE STATUS WHERE `Name` LIKE 'reportit_tmp_%'
             AND (UNIX_TIMESTAMP(`Update_time`) + $lifecycle) <= UNIX_TIMESTAMP()";
    $tables = db_fetch_assoc($sql);

    if(count($tables)>0) {
        foreach($tables as $table) {
            /* take care that we really do NOT delete others tables */
            if (strpos($table['Name'], 'reportit_tmp_') !== FALSE) {
                $str .= $table['Name'] . ', ';
            $ids .= ",'" . substr($table['Name'], 13) . "'";
                $cnt++;
            }
        }
        if($cnt == 0) exit;

        $ids = substr($ids, 1);
        $str = substr($str, 0, -2);
        if(db_execute("DROP TABLE IF EXISTS $str") == 1) {
            db_execute("DELETE FROM reportit_cache_reports WHERE `cache_id` IN ($ids);");
            db_execute("DELETE FROM reportit_cache_variables WHERE `cache_id` IN ($ids);");
            db_execute("DELETE FROM reportit_cache_measurands WHERE `cache_id` IN ($ids);");
            if($cnt >= 5) {
                db_execute("OPTIMIZE TABLE `reportit_cache_reports`;");
                db_execute("OPTIMIZE TABLE `reportit_cache_variables`;");
                db_execute("OPTIMIZE TABLE `reportit_cache_measurands`;");
            }
            if($logging_level != 'POLLER_VERBOSITY_NONE' and $logging_level != 'POLLER_VERBOSITY_LOW') {
                cacti_log("REPORTIT STATS: Cache Life Cycle:$lifecycle"."s &nbsp;&nbsp;Number of drops:$cnt", false, 'PLUGIN');
            }
        }else {
            if($logging_level != 'POLLER_VERBOSITY_LOW') {
                cacti_log("REPORTIT WARNING: Unable to clean up report cache", false, 'PLUGIN');
            }
        }
    }
}

function reportit_page_head(){
?>
   <script type="text/javascript" src="<?php echo URL_PATH; ?>plugins/reportit/lib_ext/jquery/jquery-1.4.2.min.js"></script>
<?php
}
?>