<?php
/*
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

/* get_rrdfile_names - this routine returns all of the RRDfiles know to Cacti
     so as to be processed when performin the Daily, Weekly, Monthly and Yearly
     average and peak calculations.
   @returns - (mixed) The RRDfile names */
function get_rrdfile_names() {
	return db_fetch_assoc("SELECT local_data_id, data_source_path FROM data_template_data WHERE local_data_id!=0 AND data_source_path !=''");
}

/* dsstats_debug - this simple routine echo's a standard message to the console
     when running in debug mode.
   @returns - NULL */
function dsstats_debug($message) {
	global $debug;

	if ($debug) {
		echo "DSSTATS: " . $message . "\n";
	}
}

/* dsstats_get_and_store_ds_avgpeak_values - this routine is a generic routine that takes an time interval as an
     input parameter and then, though additional function calls, reads the RRDfiles for the correct information
     and stores that information into the various database tables.
   @arg $interval - (string) either "daily", "weekly", "monthly", or "yearly"
   @returns - NULL */
function dsstats_get_and_store_ds_avgpeak_values($interval) {
	global $config;

	$rrdfiles = get_rrdfile_names();
	$stats    = array();

	$process_pipes = dsstats_rrdtool_init();
	$process = $process_pipes[0];
	$pipes   = $process_pipes[1];

	if (sizeof($rrdfiles)) {
	foreach ($rrdfiles as $file) {
		$rrdfile = str_replace("<path_rra>", $config["rra_path"], $file["data_source_path"]);

		$stats[$file["local_data_id"]] = dsstats_obtain_data_source_avgpeak_values($rrdfile, $interval, $pipes);
	}
	}

	dsstats_rrdtool_close($process);

	dsstats_write_buffer($stats, $interval);
}

/* dsstats_write_buffer - this routine provide bulk database insert services to the various tables that store
     the average and peak information for Data Sources.
   @arg $stats_array - (mixed) A multi dimensional array keyed by the local_data_id that contains both
     the average and max values for each internal RRDfile Data Source.
   @arg $interval - (string) "daily", "weekly", "monthly", and "yearly".  Used for determining the table to
     update during the dumping of the buffer.
   @returns - NULL */
function dsstats_write_buffer(&$stats_array, $interval) {
	/* initialize some variables */
	$sql_prefix = "INSERT INTO data_source_stats_$interval (local_data_id, rrd_name, average, peak) VALUES";
	$sql_suffix = " ON DUPLICATE KEY UPDATE average=VALUES(average), peak=VALUES(peak)";
	$overhead   = strlen($sql_prefix) + strlen($sql_suffix);
	$outbuf     = "";
	$out_length = 0;
	$i          = 1;
	$max_packet = "264000";

	/* don't attempt to process an empty array */
	if (sizeof($stats_array)) {
	foreach($stats_array as $local_data_id => $stats) {
		/* some additional sanity checking */
		if (sizeof($stats)) {
		foreach($stats as $rrd_name => $avgpeak_stats) {
			if ($i == 1) {
				$delim = " ";
			}else{
				$delim = ", ";
			}

			$outbuf .= $delim . "('" . $local_data_id . "','" .
				$rrd_name . "','" .
				$avgpeak_stats["AVG"] . "','" .
				$avgpeak_stats["MAX"] . "')";

			$out_length += strlen($outbuf);

			if (($out_length + $overhead) > $max_packet) {
				db_execute($sql_prefix . $outbuf . $sql_suffix);

				$outbuf     = "";
				$out_length = 0;
				$i          = 1;
			}else{
				$i++;
			}
		}
		}
	}
	}

	/* flush the buffer if it still has elements in it */
	if ($out_length > 0) {
		db_execute($sql_prefix . $outbuf . $sql_suffix);
	}
}

/* dsstats_rrdtool_init - this routine provides a bi-directional socket based connection to RRDtool.
     it provides a high speed connection to rrdfile in the case where the traditional Cacti call does
     not when performing fetch type calls.
   @returns - (mixed) An array that includes both the process resource and the pipes to communicate
     with RRDtool. */
function dsstats_rrdtool_init() {
	global $config;

	if ($config["cacti_server_os"] == "unix") {
		$fds = array(
			0 => array("pipe", "r"), // stdin
			1 => array('pipe', 'w'), // stdout
			2 => array('file', '/dev/null', 'a')  // stderr
		);
	} else {		$fds = array(
			0 => array("pipe", "r"), // stdin
			1 => array('pipe', 'w'), // stdout
			2 => array('file', 'nul', 'a')  // stderr
		);
	}

	/* set the rrdtool default font */
	if (read_config_option("path_rrdtool_default_font")) {
		putenv("RRD_DEFAULT_FONT=" . read_config_option("path_rrdtool_default_font"));
	}

	$command = read_config_option("path_rrdtool") . " - ";

	$process = proc_open($command, $fds, $pipes);

	/* make stdin/stdout/stderr non-blocking */
	stream_set_blocking($pipes[0], 0);
	stream_set_blocking($pipes[1], 0);

	return array($process, $pipes);
}

/* dsstats_rrdtool_execute - this routine passes commands to RRDtool and returns the information
     back to DSStats.  It is important to note here that RRDtool needs to provide an either "OK"
     or "ERROR" response accross the pipe as it does not provide EOF characters to key upon.
     This may not be the best method and may be changed after I have a conversation with a few
     developers.
   @arg $command - (string) The rrdtool command to execute
   @arg $pipes - (array) An array of stdin and stdout pipes to read and write data from
   @returns - (string) The output from RRDtool */
function dsstats_rrdtool_execute($command, $pipes) {
	$stdout = '';

	if ($command == "") return;

	$command .= "\r\n";

	$return_code = fwrite($pipes[0], $command);

	while (!feof($pipes[1])) {
		$stdout .= fgets($pipes[1], 4096);
		if (substr_count($stdout, "OK")) {
			break;
		}
		if (substr_count($stdout, "ERROR")) {
			break;
		}
	}

	if (strlen($stdout)) return $stdout;
}

/* dsstats_rrdtool_close - this routine closes the RRDtool process thus also
     closing the pipes.
   @returns - NULL */
function dsstats_rrdtool_close($process) {
	proc_close($process);
}

/* dsstats_obtain_data_source_avgpeak_values - this routine, given the rrdfile name, interval and RRDtool process
     pipes, will obtain the average a peak values from the RRDfile.  It does this in two steps:

     1) It first reads the RRDfile's information header to obtain all of the internal data source names,
     poller interval and consolidation functions.
     2) Based upon the available consolidation functions, it then grabs either AVERAGE, and MAX, or just AVERAGE
        in the case where the MAX consolidation function is not included in the RRDfile, and then proceeds to
        gather data from the RRDfile for the time period in question.  It allows RRDtool to select the RRA to
        use by simply limiting the number of rows to be returned to the default.

     Once it has all of the information from the RRDfile.  It then decomposes the resulting XML file to it's
     components and then calculates the AVERAGE and MAX values from that data and returns an array to the calling
     function for storage into the respective database table.
   @returns - (mixed) An array of AVERAGE, and MAX values in an RRDfile by Data Source name */
function dsstats_obtain_data_source_avgpeak_values($rrdfile, $interval, $pipes) {
	global $config;

	/* don't attempt to get information if the file does not exist */
	if (file_exists($rrdfile)) {
		/* high speed or snail speed */
		if (read_config_option("dsstats_rrdtool_pipe") == "on") {
			$info = dsstats_rrdtool_execute("info $rrdfile", $pipes);
		} else {
			$info = rrdtool_execute("info $rrdfile", false, RRDTOOL_OUTPUT_STDOUT);
		}

		/* don't do anything if RRDfile did not return data */
		if ($info != "") {
			$info_array = explode("\n", $info);

			$average = FALSE;
			$max     = FALSE;
			$dsnames = array();

			/* figure out whatis in this RRDfile.  Assume CF Uniformity as Cacti does not allow async rrdfiles.
			 * also verify the consolidation functions in the RRDfile for average and max calculations.
			 */
			if (sizeof($info_array)) {
				foreach ($info_array as $line) {
					if (substr_count($line, "ds[")) {
						$parts = explode("]", $line);
						$parts2 = explode("[", $parts[0]);
						$dsnames[trim($parts2[1])] = 1;
					} else if (substr_count($line, ".cf")) {
						$parts = explode("=", $line);
						if (substr_count($parts[1], "AVERAGE")) {
							$average = TRUE;
						} elseif (substr_count($parts[1], "MAX")) {
							$max = TRUE;
						}
					} else if (substr_count($line, "step")) {
						$parts = explode("=", $line);
						$poller_interval = trim($parts[1]);
					}
				}
			}

			/* create the command syntax to get data */
			/* assume that an RRDfile has not more than 62 data sources */
			$defs     = "abcdefghijklmnopqrstuvwzyz012345789ABCDEFGHIJKLMNOPQRSTUVWXYZ";
			$i        = 0;
			$def      = "";
			$xport    = "";
			$dsvalues = array();


			/* escape the file name if on Windows */
			if ($config["cacti_server_os"] != "unix") {
				$rrdfile = str_replace(":", "\\:", $rrdfile);
			}

			/* setup the export command by parsing throught the internal data source names */
			if (sizeof($dsnames)) {
				foreach ($dsnames as $dsname => $present) {
					if ($average) {
						$def .= "DEF:" . $defs[$i] . "=\"" . $rrdfile . "\":" . $dsname . ":AVERAGE ";
						$xport .= " XPORT:" . $defs[$i];
						$i++;
					}

					if ($max) {
						$def .= "DEF:" . $defs[$i] . "=\"" . $rrdfile . "\":" . $dsname . ":MAX ";
						$xport .= " XPORT:" . $defs[$i];
						$i++;
					}
				}
			}

			/* change the interval to something RRDtool understands */
			switch($interval) {
				case "daily":
					$interval = "day";
					break;
				case "weekly":
					$interval = "week";
					break;
				case "monthly":
					$interval = "month";
					break;
				case "yearly":
					$interval = "year";
					break;
			}

			/* now execute the xport command */
			$xport_cmd = "xport --start now-1" . $interval . " --end now " . trim($def) . " " . trim($xport) . " --maxrows 10";
			if (read_config_option("dsstats_rrdtool_pipe") == "on") {
				$xport_data = dsstats_rrdtool_execute($xport_cmd, $pipes);
			} else {
				$xport_data = rrdtool_execute($xport_cmd, false, RRDTOOL_OUTPUT_STDOUT);
			}

			/* initialize the array of return values */
			foreach($dsnames as $dsname => $present) {
				$dsvalues[$dsname]["AVG"]    = 0;
				$dsvalues[$dsname]["AVGCNT"] = 0;
				$dsvalues[$dsname]["MAX"]    = 0;
			}

			/* process the xport array and return average and peak values */
			if ($xport_data != "") {
				$xport_array = explode("\n", $xport_data);

				if (sizeof($xport_array)) {
					foreach($xport_array as $line) {
						/* we've found an output value, let's cut it to pieces */
						if (substr_count($line, "<v>")) {
							$line = str_replace("<row><t>", "", $line);
							$line = str_replace("</t>",     "", $line);
							$line = str_replace("</v>",     "", $line);
							$line = str_replace("</row>",   "", $line);

							$values = explode("<v>", $line);
							array_shift($values);

							$i = 0;
							/* sum and/or store values for later processing */
							foreach($dsnames as $dsname => $present) {
								if ($average) {
									/* ignore "NaN" values */
									if (strtolower($values[$i]) != "nan") {
										$dsvalues[$dsname]["AVG"] += $values[$i];
										$dsvalues[$dsname]["AVGCNT"] += 1;

										if (!$max) {
											if ($values[$i] > $dsvalues[$dsname]["MAX"]) {
												$dsvalues[$dsname]["MAX"] = $values[$i];
											}
										}
										$i++;
									}
								}

								if ($max) {
									/* ignore "NaN" values */
									if (strtolower($values[$i]) != "nan") {
										if ($values[$i] > $dsvalues[$dsname]["MAX"]) {
											$dsvalues[$dsname]["MAX"] = $values[$i];
										}
										$i++;
									}
								}
							}
						}
					}

					/* calculate the average */
					foreach($dsnames as $dsname => $present) {
						if ($dsvalues[$dsname]["AVGCNT"] > 0) {
							$dsvalues[$dsname]["AVG"] = $dsvalues[$dsname]["AVG"] / $dsvalues[$dsname]["AVGCNT"];
						}
					}

					return $dsvalues;
				}
			}
		}
	} else {
		/* only alarm if performing the "daily" averages */
		if (($interval == "daily") || ($interval == "day")) {
			cacti_log("WARNING: File '" . $rrdfile . "' Does not exist", false, "DSSTATS");
		}
	}
}

/* log_dsstats_statistics - provides generic timing message to both the Cacti log and the settings
     table so that the statistcs can be graphed as well.
   @arg $type - (string) the type of statistics to log, either "HOURLY", "DAILY" or "MAJOR".
   @returns - null */
function log_dsstats_statistics($type) {
	global $start;

	/* take time and log performance data */
	list($micro,$seconds) = split(" ", microtime());
	$end = $seconds + $micro;

	$cacti_stats = sprintf("Time:%01.4f ", round($end-$start,4));
	/* take time and log performance data */
	list($micro,$seconds) = split(" ", microtime());
	$start = $seconds + $micro;

	/* log to the database */
	db_execute("REPLACE INTO settings (name,value) VALUES ('stats_dsstats_$type', '" . $cacti_stats . "')");

	/* log to the logfile */
	cacti_log("DSSTATS STATS: Type:" . $type . ", " . $cacti_stats , TRUE, "SYSTEM");
}

?>
