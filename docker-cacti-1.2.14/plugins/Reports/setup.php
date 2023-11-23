<?php
/*******************************************************************************

    Modified ....... Jimmy Conner
    Contact ........ jimmy@sqmail.org
    Home Site ...... http://cactiusers.org
    Program ........ Reports for Cacti

*******************************************************************************/

function plugin_init_reports() {
	global $plugin_hooks;
	$plugin_hooks['config_arrays']['reports'] = 'reports_config_arrays';
	$plugin_hooks['draw_navigation_text']['reports'] = 'reports_draw_navigation_text';
	$plugin_hooks['poller_bottom']['reports'] = 'reports_poller_bottom';
	$plugin_hooks['console_after']['reports'] = 'reports_console_after';
	$plugin_hooks['config_settings']['reports'] = 'reports_config_settings';
}

function reports_version () {
	return array( 
		'name'     => 'reports',
		'version'  => '0.4',
		'longname' => 'Report Creator',
		'author'   => 'Jimmy Conner',
		'homepage' => 'http://cactiusers.org',
		'email'    => 'jimmy@sqmail.org',
		'url'      => 'http://cactiusers.org/cacti/versions.php'
	);
}

function reports_console_after () {
	reports_setup_table ();
}

function reports_config_arrays () {
	global $user_auth_realms, $user_auth_realm_filenames, $menu;
	$user_auth_realms[35]='Configure Reports';
	$user_auth_realm_filenames['reports.php'] = 35;
	$user_auth_realm_filenames['reports_edit.php'] = 35;
	$temp = $menu["Utilities"]['logout.php'];
	unset($menu["Utilities"]['logout.php']);
	$menu["Utilities"]['plugins/reports/reports.php'] = "Reports";
	$menu["Utilities"]['logout.php'] = $temp;
}

function reports_config_settings () {
	global $settings, $tabs;
	$temp = array(
		"reports_header" => array(
			"friendly_name" => "Reports",
			"method" => "spacer",
			),
		"path_reports_temp" => array(
			"friendly_name" => "Reports Temp Directory",
			"description" => "This is the path to a temporary directory to do work.",
			"method" => "dirpath",
			"max_length" => 255,
			'default' => '/tmp/'
		),
	);

	if (isset($settings["path"]))
		$settings["path"] = array_merge($settings["path"], $temp);
	else
		$settings["path"] = $temp;
}

function reports_draw_navigation_text ($nav) {
	$nav["reports.php:"] = array("title" => "Reports", "mapping" => "index.php:", "url" => "reports.php", "level" => "1");
	$nav["reports.php:add"] = array("title" => "Reports", "mapping" => "index.php:", "url" => "reports.php", "level" => "1");
	$nav["reports.php:delete"] = array("title" => "Reports", "mapping" => "index.php:", "url" => "reports.php", "level" => "1");
	$nav["reports.php:edit"] = array("title" => "Reports", "mapping" => "index.php:", "url" => "reports.php", "level" => "1");
	$nav["reports_edit.php:"] = array("title" => "Reports", "mapping" => "index.php:", "url" => "reports_edit.php", "level" => "1");
	$nav["reports_edit.php:add"] = array("title" => "Reports", "mapping" => "index.php:", "url" => "reports_edit.php", "level" => "1");
	$nav["reports_edit.php:delete"] = array("title" => "Reports", "mapping" => "index.php:", "url" => "reports_edit.php", "level" => "1");
	$nav["reports_edit.php:edit"] = array("title" => "Reports", "mapping" => "index.php:", "url" => "reports_edit.php", "level" => "1");
	$nav["reports_edit.php:preview"] = array("title" => "Reports", "mapping" => "index.php:", "url" => "reports_edit.php", "level" => "1");
	$nav["reports_edit.php:swap"] = array("title" => "Reports", "mapping" => "index.php:", "url" => "reports_edit.php", "level" => "1");
	return $nav;
}

function reports_poller_bottom () {
	global $config;
	include_once($config["base_path"] . '/plugins/reports/functions.php');
	include_once($config["base_path"] . "/lib/poller.php");
	include_once($config["base_path"] . "/lib/data_query.php");
	include_once($config["base_path"] . "/lib/graph_export.php");
	include_once($config["base_path"] . "/lib/rrd.php");

	reports_setup_table ();

	$queryrows = db_fetch_assoc("select * from reports") or die (mysql_error("Could not connect to database") );
	foreach ($queryrows as $rep) {
		$t   = time();
		$ch  = date("G", $t);
		$dt  = date("N");  #returns day of week in number format
		$dt2 = date("j"); #returns day of month in number format i.e. 1
		$cm  = intval(date("i", $t));

		# assume daytype is "Everyday"
		if ($rep['daytype'] == '0') { 
			if ($rep['hour'] == $ch) {
				if (($cm - ($rep['minute']*15) < 5) && ($cm - ($rep['minute']*15) > -1) && ($t - 3600 > $rep['lastsent'])) {
					generate_report($rep);
				}
			}
		# assume daytype is "1st of month"
		} else if ($rep['daytype'] == '8' && $dt2 == '1') { 
			if ($rep['hour'] == $ch) {
				if (($cm - ($rep['minute']*15) < 5) && ($cm - ($rep['minute']*15) > -1) && ($t - 3600 > $rep['lastsent'])) {
					generate_report($rep);
				}
			}
		# Assume daytype is some day of the week in number representation
		} else if ($rep['daytype'] == $dt) { 
			if ($rep['hour'] == $ch) {
				if (($cm - ($rep['minute']*15) < 5) && ($cm - ($rep['minute']*15) > -1) && ($t - 3600 > $rep['lastsent'])) {
					generate_report($rep);
				}
			}
		}
	}
}


function reports_setup_table () {
	global $config, $database_default;
	include_once($config["library_path"] . "/database.php");
	$sql    = "show tables from " . $database_default;
	
	$result = db_fetch_assoc($sql) or die (mysql_error());

	$tables = array();
	$sql = array();

	foreach($result as $index => $arr) {
		foreach ($arr as $t) {
			$tables[] = $t;
		}
	}

	if (!in_array('reports', $tables)) {
		$sql[] = "CREATE TABLE IF NOT EXISTS reports (
			id int(10) NOT NULL auto_increment,
			name varchar(100) NOT NULL default '',
			hour int(2) NOT NULL default '0',
			minute int(2) NOT NULL default '0',
			daytype text NOT NULL,
			email text NOT NULL,
			rtype varchar(12) NOT NULL default 'attach',
			lastsent int(32) NOT NULL default '0',
			KEY id (id)
			) ENGINE=MyISAM;";
		$sql[] = "INSERT INTO `user_auth_realm` VALUES (35, 1);";
	} else {
		$sql2 = "show columns from reports";
		$result = db_fetch_assoc($sql2) or die (mysql_error());
		$columns = array();
		foreach($result as $index => $arr) {
			foreach ($arr as $t) {
				$columns[] = $t;
			}
		}
		if (!in_array('type', $columns)) {
			$sql .= "ALTER TABLE reports ADD rtype VARCHAR(12) NOT NULL DEFAULT 'attach' AFTER email;";
		}
	}

	if (!in_array('reports_data', $tables)) {
		$sql[] = "CREATE TABLE IF NOT EXISTS reports_data (
			id int(10) NOT NULL auto_increment,
			reportid int(10) NOT NULL default '0',
			hostid int(10) NOT NULL default '0',
			local_graph_id int(10) NOT NULL default '0',
			rra_id int(10) NOT NULL default '0',
			type int(1) NOT NULL default '0',
			item varchar(32) NOT NULL default '',
			data text NOT NULL,
			gorder int(5) NOT NULL default '0',
			KEY id (id)
			) ENGINE=MyISAM;";
	} else {
		$sql2 = "show columns from reports_data";
		$result = db_fetch_assoc($sql2) or die (mysql_error());
		$columns = array();
		foreach($result as $index => $arr) {
			foreach ($arr as $t) {
				$columns[] = $t;
			}
		}
		if (!in_array('item', $columns)) {
			$sql .= "ALTER TABLE reports_data ADD item VARCHAR(32) NOT NULL DEFAULT '' AFTER type;";
		}
	}

	if (!empty($sql)) {
		for ($a = 0; $a < count($sql); $a++) {
			$result = mysql_query($sql[$a]);
		}
	}
}

