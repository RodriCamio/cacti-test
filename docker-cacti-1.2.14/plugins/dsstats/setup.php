<?php
/*
 ex: set tabstop=3 shiftwidth=3 autoindent:
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2009 The Cacti Group                                 |
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

/* plugin_dsstats_install - provides a generic PIA 2.x installer routine to register all plugin
     hook functions.
   @returns - null */
function plugin_dsstats_install () {
	api_plugin_register_hook('dsstats', 'config_arrays',       'dsstats_config_arrays',        'setup.php');
	api_plugin_register_hook('dsstats', 'config_settings',     'dsstats_config_settings',      'setup.php');
	api_plugin_register_hook('dsstats', 'poller_output',       'dsstats_poller_output',        'setup.php');
	api_plugin_register_hook('dsstats', 'poller_bottom',       'dsstats_poller_bottom',        'setup.php');
	api_plugin_register_hook('dsstats', 'poller_command_args', 'dsstats_poller_command_args',  'setup.php');
	api_plugin_register_hook('dsstats', 'boost_poller_bottom', 'dsstats_boost_bottom',         'setup.php');
	api_plugin_register_hook('dsstats', 'data_source_remove',  'dsstats_data_source_remove',   'setup.php');
	dsstats_setup_table_new ();
}

/* plugin_dsstats_uninstall - a generic uninstall routine.  Right now it will do nothing as I
     don't want the tables removed from the system except for forcably by the user.  This
     may change at some point.
   @returns - null */
function plugin_dsstats_uninstall () {
	/* Do any extra Uninstall stuff here */
}

/* plugin_dsstats_check_config - this routine will verify if there is any upgrade steps that
     need to be performed on the plugin.
   @returns - (bool) always returns true for some reason */
function plugin_dsstats_check_config () {
	/* Here we will check to ensure everything is configured */
	dsstats_check_upgrade();
	return TRUE;
}

/* plugin_dsstats_upgrade - this routine is similar to the config_check.  My guess is that
     the author, aka me, is doing something wrong here as a result of this discovery.
   @returns - (bool) always returns true for some reason */
function plugin_dsstats_upgrade () {
	/* Here we will upgrade to the newest version */
	dsstats_check_upgrade();
	return FALSE;
}

/* plugin_dsstats_version - obtains the current version of the plugin in a PIA 2.x
     fashion.  The legacy function is also provided for backwards compatibility, although
     it's no required.
   @returns - (string) the current plugin version */
function plugin_dsstats_version () {
	return dsstats_version();
}

/* dsstats_check_upgrade - this generic routine verifies if the plugin needs upgrading or
     not.  If it does require upgrading, then it performs that upgrade and updates
     the plugin config table with the new version.
   @returns - NULL */
function dsstats_check_upgrade () {
	global $config;

	$files = array('index.php', 'plugins.php');
	if (isset($_SERVER['PHP_SELF']) && !in_array(basename($_SERVER['PHP_SELF']), $files)) {
		return;
	}

	$current = plugin_dsstats_version();
	$current = $current['version'];
	$old     = db_fetch_row("SELECT * FROM plugin_config WHERE directory='dsstats'");
	if (sizeof($old) && $current != $old["version"]) {
		/* if the plugin is installed and/or active */
		if ($old["status"] == 1 || $old["status"] == 4) {
			/* re-register the hooks */
			plugin_dsstats_install();

			/* perform a database upgrade */
			dsstats_database_upgrade();
		}

		/* update the plugin information */
		$info = plugin_dsstats_version();
		$id   = db_fetch_cell("SELECT id FROM plugin_config WHERE directory='dsstats'");
		db_execute("UPDATE plugin_config
			SET name='" . $info["longname"] . "',
			author='"   . $info["author"]   . "',
			webpage='"  . $info["homepage"] . "',
			version='"  . $info["version"]  . "'
			WHERE id='$id'");
	}
}

/* dsstats_database_upgrade - this routine is where I "should" be performing the upgrade.
     I guess I will have to change that at some point from the previous function.
   @returns - (bool) always returns true for some reason */
function dsstats_database_upgrade() {
	global $plugins, $config;

	if (!sizeof(db_fetch_row("SHOW COLUMNS FROM data_source_stats_hourly_last WHERE Field='calculated'"))) {
		db_execute("ALTER TABLE data_source_stats_hourly_last ADD calculated DOUBLE NOT NULL AFTER value");
	};

	db_execute("ALTER TABLE `data_source_stats_daily` MODIFY COLUMN `local_data_id` MEDIUMINT(8) UNSIGNED NOT NULL");
	db_execute("ALTER TABLE `data_source_stats_hourly` MODIFY COLUMN `local_data_id` MEDIUMINT(8) UNSIGNED NOT NULL");
	db_execute("ALTER TABLE `data_source_stats_hourly_cache` MODIFY COLUMN `local_data_id` MEDIUMINT(8) UNSIGNED NOT NULL");
	db_execute("ALTER TABLE `data_source_stats_hourly_last` MODIFY COLUMN `local_data_id` MEDIUMINT(8) UNSIGNED NOT NULL");
	db_execute("ALTER TABLE `data_source_stats_monthly` MODIFY COLUMN `local_data_id` MEDIUMINT(8) UNSIGNED NOT NULL");
	db_execute("ALTER TABLE `data_source_stats_weekly` MODIFY COLUMN `local_data_id` MEDIUMINT(8) UNSIGNED NOT NULL");
	db_execute("ALTER TABLE `data_source_stats_yearly` MODIFY COLUMN `local_data_id` MEDIUMINT(8) UNSIGNED NOT NULL");

	return TRUE;
}

/* dsstats_check_dependencies - this routine is where I would check for other plugin
     dependencies.  There only plugin dependency at this moment is the PIA itself.
     So, I will always return true at the moment.
   @returns - (bool) always returns true since there are not dependencies */
function dsstats_check_dependencies() {
	global $plugins, $config;
	return TRUE;
}

/* dsstats_setup_table_new - this routine creates all DSStats table if they don't
     already exist.  At some point, they would work better with the uninstall routine
     but not for now.
   @returns - NULL */
function dsstats_setup_table_new () {
	if (!sizeof(db_fetch_row("SHOW TABLES LIKE 'data_source_stats_daily'"))) {
		db_execute("CREATE TABLE `data_source_stats_daily` (
			`local_data_id` mediumint(8) unsigned NOT NULL,
			`rrd_name` varchar(19) NOT NULL,
			`average` DOUBLE NOT NULL,
			`peak` DOUBLE NOT NULL,
			PRIMARY KEY  (`local_data_id`,`rrd_name`)
			) ENGINE=MyISAM DEFAULT CHARSET=UTF8;"
		);
	}

	if (!sizeof(db_fetch_row("SHOW TABLES LIKE 'data_source_stats_hourly'"))) {
		db_execute("CREATE TABLE `data_source_stats_hourly` (
			`local_data_id` mediumint(8) unsigned NOT NULL,
			`rrd_name` varchar(19) NOT NULL,
			`average` DOUBLE NOT NULL,
			`peak` DOUBLE NOT NULL,
			PRIMARY KEY  (`local_data_id`,`rrd_name`)
			) ENGINE=MyISAM DEFAULT CHARSET=UTF8;"
		);
	}

	if (!sizeof(db_fetch_row("SHOW TABLES LIKE 'data_source_stats_hourly_cache'"))) {
		db_execute("CREATE TABLE `data_source_stats_hourly_cache` (
			`local_data_id` mediumint(8) unsigned NOT NULL,
			`rrd_name` varchar(19) NOT NULL,
			`time` timestamp NOT NULL default '0000-00-00 00:00:00',
			`value` DOUBLE NOT NULL,
			PRIMARY KEY  (`local_data_id`,`time`,`rrd_name`),
			KEY `time` USING BTREE (`time`)
			) ENGINE=MEMORY DEFAULT CHARSET=UTF8;"
		);
	}

	if (!sizeof(db_fetch_row("SHOW TABLES LIKE 'data_source_stats_hourly_last'"))) {
		db_execute("CREATE TABLE `data_source_stats_hourly_last` (
			`local_data_id` mediumint(8) unsigned NOT NULL,
			`rrd_name` varchar(19) NOT NULL,
			`value` varchar(30) NOT NULL,
			`calculated` DOUBLE NOT NULL,
			PRIMARY KEY  (`local_data_id`,`rrd_name`)
			) ENGINE=MEMORY DEFAULT CHARSET=UTF8;"
		);
	}

	if (!sizeof(db_fetch_row("SHOW COLUMNS from data_source_stats_hourly_last where Field='calculated'"))) {
		db_execute("ALTER TABLE data_source_stats_hourly_last ADD calculated double not null after value");
	};

	if (!sizeof(db_fetch_row("SHOW TABLES LIKE 'data_source_stats_monthly'"))) {
		db_execute("CREATE TABLE `data_source_stats_monthly` (
			`local_data_id` mediumint(8) unsigned NOT NULL,
			`rrd_name` varchar(19) NOT NULL,
			`average` DOUBLE NOT NULL,
			`peak` DOUBLE NOT NULL,
			PRIMARY KEY  (`local_data_id`,`rrd_name`)
			) ENGINE=MyISAM DEFAULT CHARSET=UTF8;"
		);
	}

	if (!sizeof(db_fetch_row("SHOW TABLES LIKE 'data_source_stats_weekly'"))) {
		db_execute("CREATE TABLE `data_source_stats_weekly` (
			`local_data_id` mediumint(8) unsigned NOT NULL,
			`rrd_name` varchar(19) NOT NULL,
			`average` DOUBLE NOT NULL,
			`peak` DOUBLE NOT NULL,
			PRIMARY KEY  (`local_data_id`,`rrd_name`)
			) ENGINE=MyISAM DEFAULT CHARSET=UTF8;"
		);
	}

	if (!sizeof(db_fetch_row("SHOW TABLES LIKE 'data_source_stats_yearly'"))) {
		db_execute("CREATE TABLE `data_source_stats_yearly` (
			`local_data_id` mediumint(8) unsigned NOT NULL,
			`rrd_name` varchar(19) NOT NULL,
			`average` DOUBLE NOT NULL,
			`peak` DOUBLE NOT NULL,
			PRIMARY KEY  (`local_data_id`,`rrd_name`)
			) ENGINE=MyISAM AUTO_INCREMENT=3 DEFAULT CHARSET=UTF8;"
		);
	}
}

/* dsstats_error_handler - this routine logs all PHP error transactions
     to make sure they are properly logged.
   @arg $errno - (int) The errornum reported by the system
   @arg $errmsg - (string) The error message provides by the error
   @arg $filename - (string) The filename that encountered the error
   @arg $linenum - (int) The line number where the error occured
   @arg $vars - (mixed) The current state of PHP variables.
   @returns - (bool) always returns true for some reason */
function dsstats_error_handler($errno, $errmsg, $filename, $linenum, $vars) {
	if (read_config_option("log_verbosity") >= POLLER_VERBOSITY_DEBUG) {
		/* define all error types */
		$errortype = array(
			E_ERROR             => 'Error',
			E_WARNING           => 'Warning',
			E_PARSE             => 'Parsing Error',
			E_NOTICE            => 'Notice',
			E_CORE_ERROR        => 'Core Error',
			E_CORE_WARNING      => 'Core Warning',
			E_COMPILE_ERROR     => 'Compile Error',
			E_COMPILE_WARNING   => 'Compile Warning',
			E_USER_ERROR        => 'User Error',
			E_USER_WARNING      => 'User Warning',
			E_USER_NOTICE       => 'User Notice',
			E_STRICT            => 'Runtime Notice'
		);

		if (defined("E_RECOVERABLE_ERROR")) {
			$errortype[E_RECOVERABLE_ERROR] = 'Catchable Fatal Error';
		}

		/* create an error string for the log */
		$err = "ERRNO:'"  . $errno   . "' TYPE:'"    . $errortype[$errno] .
			"' MESSAGE:'" . $errmsg  . "' IN FILE:'" . $filename .
			"' LINE NO:'" . $linenum . "'";

		/* let's ignore some lesser issues */
		if (substr_count($errmsg, "date_default_timezone")) return;
		if (substr_count($errmsg, "Only variables")) return;

		/* log the error to the Cacti log */
		cacti_log("PROGERR: " . $err, FALSE, "DSSTATS");
	}

	return;
}

/* dsstats_poller_output - this routine runs in parallel with the cacti poller and
     populates the last and cache tables.  On larger systems, it should be noted that
     the memory overhead for the global arrays, $ds_types, $ds_last, $ds_steps, $ds_multi
     could be serval hundred megabytes.  So, this should be kept in mind when running the
     sizing your system.

     The routine basically loads those 4 structures into memory, and then uses them to
     determine what should be stored in both the Cache and the Last tables.  The 4 structures
     contain the following information:

     $ds_types - The type of data source, keyed by the local_data_id and the rrd_name stored inside
                 of the RRDfile.
     $ds_last  - For the COUNTER, and DERIVE DS types, the last measured and stored value.
     $ds_steps - Records the poller interval for every Data Source so that rates can be stored.
     $ds_multi - For Multi Part responses, stores the mapping of the Data Input Fields to the
                 Internal RRDfile DS names.

     The routine loops through all poller output items and makes decisions relative to the output
     that should be stored into the two tables, and then bulk inserts that information once
     all poller items have been processed.

     The pupose for loading then entire structures into memory at one time is to reduce the latency
     related to multiple database calls.  The author believed that PHP's array hashing algorythms
     would be as fast, if not faster, than MySQL, when considering the transaction overhead and therefore
     chose this method.

   @arg $rrd_update_array - (mixed) The output from the poller output table to be processed by the plugin
   @returns - (mixed) The untouched $rrd_update_array array so it can be used by other plugins. */
function dsstats_poller_output(&$rrd_update_array) {
	global $config, $ds_types, $ds_last, $ds_steps, $ds_multi;

	/* suppress warnings */
	if (defined("E_DEPRECATED")) {
		error_reporting(E_ALL ^ E_DEPRECATED);
	}else{
		error_reporting(E_ALL);
	}

	/* install the dsstats error handler */
	set_error_handler("dsstats_error_handler");

	/* do not make any calculations unlessed enabled */
	if (read_config_option("dsstats_enable") == "on") {
		if (sizeof($rrd_update_array) > 0) {
			/* we will assume a smaller than the max packet size.  This would appear to be around the sweat spot. */
			$max_packet       = "264000";

			/* initialize some variables related to the DB inserts */
			$outbuf           = "";
			$sql_cache_prefix = "INSERT INTO data_source_stats_hourly_cache (local_data_id, rrd_name, time, `value`) VALUES";
			$sql_last_prefix  = "INSERT INTO data_source_stats_hourly_last (local_data_id, rrd_name, `value`, calculated) VALUES";
			$sql_suffix       = " ON DUPLICATE KEY UPDATE `value`=VALUES(`value`)";
			$sql_last_suffix  = " ON DUPLICATE KEY UPDATE `value`=VALUES(`value`), `calculated`=VALUES(`calculated`)";
			$overhead         = strlen($sql_cache_prefix) + strlen($sql_suffix);
			$overhead_last    = strlen($sql_last_prefix) + strlen($sql_last_suffix);

			/* determine the keyvalue pair's to decide on how to store data */
			$ds_types = array_rekey(db_fetch_assoc("SELECT DISTINCT data_source_name, data_source_type_id, rrd_step
				FROM data_template_rrd
				INNER JOIN data_template_data
				ON data_template_data.local_data_id=data_template_rrd.local_data_id
				WHERE data_template_data.local_data_id>0"), "data_source_name", array("data_source_type_id", "rrd_step"));

			/* make the association between the multi-part name value pairs and the RRDfile internal
			 * data source names.
			 */
			$ds_multi = array_rekey(db_fetch_assoc("SELECT DISTINCT data_name, data_source_name
				FROM data_template_rrd
				INNER JOIN data_input_fields
				ON data_input_fields.id=data_template_rrd.data_input_field_id
				WHERE data_template_rrd.data_input_field_id!=0"), "data_name", "data_source_name");

			/* required for updating tables */
			$cache_i      = 1;
			$last_i       = 1;
			$out_length   = 0;
			$last_length  = 0;
			$lastbuf      = "";
			$cachebuf     = "";

			/* process each array */
			$n = 1;
			foreach($rrd_update_array as $data_source) {
				if (isset($data_source["times"])) {
				foreach($data_source["times"] as $time => $sample) {
					foreach($sample as $ds => $value) {
						$result["local_data_id"] = $data_source["local_data_id"];
						$result["rrd_name"]      = $ds;
						$result["time"]          = date("Y-m-d H:i:s", $time);
						$result["output"]        = $value;
						$lastval                 = "";

						if (!isset($ds_types[$result["rrd_name"]]["data_source_type_id"])) {
							$polling_interval = db_fetch_cell("SELECT rrd_step FROM data_template_data WHERE local_data_id=" . $data_source["local_data_id"]);
							$ds_type          = db_fetch_cell("SELECT data_source_type_id FROM data_template_rrd WHERE local_data_id=" . $data_source["local_data_id"]);
						}else{
							$polling_interval = $ds_types[$result["rrd_name"]]["rrd_step"];
							$ds_type          = $ds_types[$result["rrd_name"]]["data_source_type_id"];
						}

						switch ($ds_type) {
							case 2:	// COUNTER
								/* get the last values from the database for COUNTER and DERIVE data sources */
								$ds_last = db_fetch_cell("SELECT SQL_NO_CACHE `value`
									FROM data_source_stats_hourly_last
									WHERE local_data_id=" . $result["local_data_id"] . "
									AND rrd_name='" . $result["rrd_name"] . "'");

								if ($ds_last == '') {
									$currentval = "U";
								} elseif ($result["output"] >= $ds_last) {
									/* everything is normal */
									$currentval = $result["output"] - $ds_last;
								} else {
									/* possible overflow, see if its 32bit or 64bit */
									if ($ds_last > 4294967295) {
										$currentval = (18446744073709551615 - $ds_last) + $result["output"];
									} else {
										$currentval = (4294967295 - $ds_last) + $result["output"];
									}
								}
								$currentval = $currentval / $polling_interval;
								$lastval    = $result["output"];

								break;
							case 3:	// DERIVE
								/* get the last values from the database for COUNTER and DERIVE data sources */
								$ds_last = db_fetch_cell("SELECT SQL_NO_CACHE `value`
									FROM data_source_stats_hourly_last
									WHERE local_data_id=" . $result["local_data_id"] . "
									AND rrd_name='" . $result["rrd_name"] . "'");

								if ($ds_last == '') {
									$currentval = "U";
								} else {
									$currentval = ($result["output"] - $ds_last) / $polling_interval;
								}
								$lastval = $result["output"];

								break;
							case 4:	// ABSOLUTE
								$currentval = $result["output"] / $polling_interval;
								$lastval = $result["output"];

								break;
							case 1:	// GAUGE
								$currentval = $result["output"];
								$lastval = $result["output"];

								break;
							default:
								cacti_log("WARNING: Unknown RRDtool Data Type '" . $ds_types[$result["rrd_name"]]["data_source_type_id"] . "', For '" . $result["rrd_name"] . "'", false, "DSSTATS");

								break;
						}

						/* when doing bulk inserts, the second record is different */
						if ($cache_i == 1) {
							$cache_delim = " ";
						} else {
							$cache_delim = ", ";
						}

						if ($last_i == 1) {
							$last_delim = " ";
						} else {
							$last_delim = ", ";
						}

						/* setupt the output buffer for the cache first */
						$cachebuf .=
							$cache_delim . "('" .
							$result["local_data_id"] . "','" .
							$result["rrd_name"] . "','" .
							$result["time"] . "','" .
							($currentval !== "U" ? $currentval:"-90909090909") . "')";

						$out_length += strlen($cachebuf);

						/* now do the the last value, if applicable */
						if ($lastval != "") {
							$lastbuf .=
								$last_delim . "('" .
								$result["local_data_id"] . "','" .
								$result["rrd_name"] . "','" .
								$lastval . "','" .
								($currentval !== "U" ? $currentval:"-90909090909") . "')";
							$last_i++;
							$last_length += strlen($lastbuf);
						}

						/* if we exceed our output buffer, it's time to write */
						if ((($out_length + $overhead) > $max_packet) ||
							(($last_length + $overhead_last) > $max_packet )) {
							db_execute($sql_cache_prefix . $cachebuf . $sql_suffix);

							if ($last_i > 1) {
								db_execute($sql_last_prefix . $lastbuf . $sql_last_suffix);
							}

							$cachebuf     = "";
							$lastbuf      = "";
							$out_length   = 0;
							$last_length  = 0;
							$cache_i      = 1;
							$last_i       = 1;
						} else {
							$cache_i++;
						}

						$n++;

						if (($n % 1000) == 0) echo ".";
					}
				}
				}
			}

			if ($cache_i > 1) {
				db_execute($sql_cache_prefix . $cachebuf . $sql_suffix);
			}

			if ($last_i > 1) {
				db_execute($sql_last_prefix . $lastbuf . $sql_last_suffix);
			}
		}
	}

	/* restore original error handler */
	restore_error_handler();

	return $rrd_update_array;
}

/* dsstats_boost_bottom - this routine accomodates mass updates after the boost process
     has completed.  The use of boost will require boost version 2.5 or above.  The idea
     if that daily averages will be updated on the boost cycle.
   @returns - NULL */
function dsstats_boost_bottom() {
	global $config;

	include_once($config["base_path"] . "/lib/rrd.php");
	include_once($config["base_path"] . "/plugins/dsstats/functions.php");

	/* run the daily stats. log to database to prevent secondary runs */
	db_execute("REPLACE INTO settings (name, value) VALUES ('dsstats_last_daily_run_time', '" . date("Y-m-d G:i:s", time()) . "')");
	dsstats_get_and_store_ds_avgpeak_values("daily");
	log_dsstats_statistics("DAILY");
}

/* dsstats_poller_command_args - this routine allows DSStats to increase the memory of the
     running script.  This is important for very large sites.
   @arg $args - (string) The are the extra args that are passed to cmd.php or spine.
   @returns - (string) The untouched $args variable as Cacti and other plugins may need/change them */
function dsstats_poller_command_args ($args) {
	dsstats_memory_limit();

	return $args;
}

/* dsstats_memory_limit - this routine increases/decreases the memory available for the script
     It is divided into two functions as the main dsstats poller calls this function directly
     as opposed to the call during the processing of poller output in the main cacti poller.
   @returns - NULL */
function dsstats_memory_limit() {
	ini_set("memory_limit", read_config_option("dsstats_poller_mem_limit") . "M");
}

/* dsstats_poller_bottom - this routine launches the main dsstats poller so that it might
     calculate the Hourly, Daily, Weekly, Monthly, and Yearly averages.  It is forked independently
     to the Cacti poller after all polling has finished.
   @arg $output - (mixed) This is information passed to this plugin and returned pristine for other plugins
   @returns - (mixed) The untouched $output variable for other plugins to use. */
function dsstats_poller_bottom ($output) {
	global $config;
	include_once($config["base_path"] . "/lib/poller.php");

	$command_string = read_config_option("path_php_binary");

	if (read_config_option("path_dsstats_log") != "") {
		if ($config["cacti_server_os"] == "unix") {
			$extra_args = "-q " . $config["base_path"] . "/plugins/dsstats/poller_dsstats.php >> " . read_config_option("path_dsstats_log") . " 2>&1";
		} else {
			$extra_args = "-q " . $config["base_path"] . "/plugins/dsstats/poller_dsstats.php >> " . read_config_option("path_dsstats_log");
		}
	} else {
		$extra_args = "-q " . $config["base_path"] . "/plugins/dsstats/poller_dsstats.php";
	}

	exec_background($command_string, "$extra_args");
}

/* dsstats_version - this routine returns the version information for the plugin in a generic
     fashion.
   @returns - (mixed) The version information from the plugin author */
function dsstats_version () {
	return array(
		'name'      => 'DSStats',
		'version'   => '1.4',
		'longname'  => 'Data Sources Statistics',
		'author'    => 'The Cacti Group',
		'homepage'  => 'http://www.cacti.net',
		'email'	    => 'forums@cacti.net',
		'url'       => 'http://www.cacti.net'
	);
}

/* dsstats_config_settings - this routine set's up the Settings page in Cacti.  It allows the
     user/administrator of the plugin to change the behavior of the plugin.  Since the modification
     of variables can be so complex, we have choosen to implement global variables to make generic
     moficiation of the Cacti UI simpler.
   @returns - NULL */
function dsstats_config_settings () {
	global $tabs, $settings, $dsstats_refresh_interval, $dsstats_max_memory, $dsstats_hourly_avg;

	/* check for an upgrade */
	plugin_dsstats_check_config();

	$tabs["dsstats"] = "DS Stats";

	$settings["dsstats"] = array(
		"dsstats_hq_header" => array(
			"friendly_name" => "Data Sources Statistics",
			"method" => "spacer",
		),
		"dsstats_enable" => array(
			"friendly_name" => "Enable Data Source Statistics",
			"description" => "Should Data Source Statistics be collected for this Cacti system?",
			"method" => "checkbox",
			"default" => ""
		),
		"dsstats_daily_interval" => array(
			"friendly_name" => "Daily Update Frequency",
			"description" => "How frequent should Daily Stats be updated?",
			"default" => "60",
			"method" => "drop_array",
			"array" => $dsstats_refresh_interval
		),
		"dsstats_hourly_duration" => array(
			"friendly_name" => "Hourly Average Window",
			"description" => "The number of consecutive hours that represent the hourly
			average.  Keep in mind that a setting too high can result in very large memory tables",
			"default" => "60",
			"method" => "drop_array",
			"array" => $dsstats_hourly_avg
		),
		"dsstats_major_update_time" => array(
			"friendly_name" => "Maintenance Time",
			"description" => "What time of day should Weekly, Monthly, and Yearly Data be updated?  Format is HH:MM [am/pm]",
			"method" => "textbox",
			"default" => "12:00am",
			"max_length" => "20"
		),
		"dsstats_poller_mem_limit" => array(
			"friendly_name" => "Memory Limit for dsstats and Poller",
			"description" => "The maximum amount of memory for the Cacti Poller and dsstats's Poller",
			"method" => "drop_array",
			"default" => "1024",
			"array" => $dsstats_max_memory
		),
		"dsstats_debug_header" => array(
			"friendly_name" => "Debugging",
			"method" => "spacer",
		),
		"dsstats_rrdtool_pipe" => array(
			"friendly_name" => "Enable Single RRDtool Pipe",
			"description" => "Using a single pipe will speed the RRDtool process by 10x.  However, RRDtool crashes
			problems can occur.  Disable this setting if you need to find a bad RRDfile.",
			"method" => "checkbox",
			"default" => "on"
		),
		"dsstats_partial_retrieve" => array(
			"friendly_name" => "Enable Partial Reference Data Retrieve",
			"description" => "If using a large system, it may be beneficial for you to only gather data as needed
			during Cacti poller passes.  If you check this box, you DSStats will gather data this way.",
			"method" => "checkbox",
			"default" => ""
		)
	);
}

/* dsstats_config_array - this routine provides a set of generic arrays for use by DSStats mainly
     for within the Cacti settings page.  Again, these are implemented through global variables.
   @returns - NULL */
function dsstats_config_arrays () {
	global $dsstats_refresh_interval, $dsstats_max_memory, $dsstats_hourly_avg;

	if (!sizeof(db_fetch_cell("SELECT directory FROM plugin_config WHERE directory='boost' and status='1'"))) {
		$dsstats_refresh_interval = array(
			"60"  => "1 Hour",
			"120" => "2 Hours",
			"180" => "3 Hours",
			"240" => "4 Hours",
			"300" => "5 Hours",
			"360" => "6 Hours");
	} else {
		$dsstats_refresh_interval = array(
			"boost"  => "When Boost Updates RRD's");
	}

	$dsstats_max_memory = array(
		"32" => "32 MBytes",
		"64" => "64 MBytes",
		"128" => "128 MBytes",
		"256" => "256 MBytes",
		"512" => "512 MBytes",
		"1024" => "1 GBytes",
		"1536" => "1.5 GBytes",
		"2048" => "2 GBytes",
		"3072" => "3 GBytes");

	$dsstats_hourly_avg = array(
		"60"  => "1 Hour",
		"120" => "2 Hours",
		"180" => "3 Hours",
		"240" => "4 Hours",
		"300" => "5 Hours",
		"360" => "6 Hours");
}

/* dsstats_data_source_remove - this routine is required to keep DSSTATS tables consistent.
   @returns - NULL */
function dsstats_data_source_remove($data_sources_to_act_on){
	if(is_array($data_sources_to_act_on) && sizeof($data_sources_to_act_on)>0) {
		$data_sources_to_act_on = array_chunk( $data_sources_to_act_on, 100);
		foreach($data_sources_to_act_on as $local_data_ids) {
			$ids = implode(',', $local_data_ids);
			db_execute("DELETE FROM data_source_stats_hourly WHERE local_data_id IN (" . $ids . ")");
			db_execute("DELETE FROM data_source_stats_hourly_cache WHERE local_data_id IN (" . $ids . ")");
			db_execute("DELETE FROM data_source_stats_hourly_last WHERE local_data_id IN (" . $ids . ")");
			db_execute("DELETE FROM data_source_stats_daily WHERE local_data_id IN (" . $ids . ")");
			db_execute("DELETE FROM data_source_stats_weekly WHERE local_data_id IN (" . $ids . ")");
			db_execute("DELETE FROM data_source_stats_monthly WHERE local_data_id IN (" . $ids . ")");
			db_execute("DELETE FROM data_source_stats_yearly WHERE local_data_id IN (" . $ids . ")");
		}
	}
}

?>
