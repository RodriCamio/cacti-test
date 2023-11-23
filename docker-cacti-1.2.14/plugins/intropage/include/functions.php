<?php

function humanFileSize($size)	{
    if ($size >= 1073741824) {
      $fileSize = round($size / 1024 / 1024 / 1024,1) . 'GB';
    } elseif ($size >= 1048576) {
        $fileSize = round($size / 1024 / 1024,1) . 'MB';
    } elseif($size >= 1024) {
        $fileSize = round($size / 1024,1) . 'KB';
    } else {
        $fileSize = $size . ' bytes';
    }
    return $fileSize;
}


function tail_file_2 ($file_name, $number_of_lines, $line_size = 256) {

    $file_array = array();

    if (file_exists($file_name) && is_readable($file_name)) {
	$handle = @fopen($file_name, "r");
	$linecounter = $number_of_lines;
	$pos = -2;
	$beginning = false;
	$text = array();
	
	while ($linecounter > 0) {
	    $t = " ";
	    while ($t != "\n") {
		if(fseek($handle, $pos, SEEK_END) == -1) {
		    $beginning = true;
		    break;
		}
		$t = fgetc($handle);
		$pos --;
	    }
	    $linecounter --;
	    if ($beginning) {
		rewind($handle);
	    }
	    $text[$number_of_lines-$linecounter-1] = fgets($handle);
	    if ($beginning) break;
	}
	fclose ($handle);

        $i = 0;
	$stats = 0;
	$warn = 0;
	$error = 0;

	foreach ($text as $line)	{
            if (substr_count($line, "STATS") && $stats < 10) {
            	$file_array['stats'][$stats] = $line; 
		$stats++;
	    }
            if (substr_count($line, "WARN") && $warn < 10) {
        	$file_array['warn'][$warn] = $line; 
		$warn++;
	    }
            if (substr_count($line, "ERROR") && $error < 10) {
        	$file_array['error'][$error] = $line; 
		$error++;
	    }
            if (substr_count($line, "FATAL") && $error < 10) {
        	$file_array['fatal'][$error] = $line; 
		$error++;
	    }
        }
    }
    else	{
	return (NULL);
    }
    return $file_array;
}

/**
* Retrieve time from an NTP server
*
* @param string $host The NTP server to retrieve the time from
* @return int The current unix timestamp
* @author Aidan Lister <aidan@php.net>
* @link http://aidanlister.com/2010/02/retrieve-time-from-an-ntp-server/
* My modification - timeout and suppress warning when server doesn't response
*/

function ntp_time($host) {
    $timestamp = -1;
    $sock = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);

    $timeout = array('sec'=>1,'usec'=>500000);
    socket_set_option($sock,SOL_SOCKET,SO_RCVTIMEO,$timeout);
    socket_clear_error();         
    
    socket_connect($sock, $host, 123);
    if (socket_last_error() == 0)	{  

	// Send request
	$msg = "\010" . str_repeat("\0", 47);
	socket_send($sock, $msg, strlen($msg), 0);
    	// Receive response and close socket
    	
    	if (@socket_recv($sock, $recv, 48, MSG_WAITALL))	{
	    socket_close($sock);
	    // Interpret response
	    $data = unpack('N12', $recv);
	    $timestamp = sprintf('%u', $data[9]);
	    // NTP is number of seconds since 0000 UT on 1 January 1900
	    // Unix time is seconds since 0000 UT on 1 January 1970
	    $timestamp -= 2208988800;
	}
    }
    return $timestamp;
}






function display_information ()	{
    global $input_types, $poller_options, $config, $colors;

    //$realm_console = 8; // include/global_arrays.php
    $realm_id_mactrack = 2120;
    $console_access = false;


    if (api_user_realm_auth('intropage.php'))	{

	if (db_fetch_assoc("select realm_id from user_auth_realm where user_id='" . $_SESSION["sess_user_id"] . "' and user_auth_realm.realm_id=8"))
	    $console_access = true; 
/*
	if (read_config_option("intropage_display_layout") == "horizontal")	
	    $horizontal = true;
	else
	    $horizontal = false;
*/
	$display_layout = read_config_option("intropage_display_layout");

	$gd = function_exists ("imagecreatetruecolor") ? true:false;
	

	$logfile = read_config_option("path_cactilog");
	$lines   = read_config_option("intropage_log_rows");
	if (!is_int($lines)) $lines = 1000;
	$ntp_enable = read_config_option('intropage_ntp_enable');
	$display_level = read_config_option('intropage_display_level');

	$current_user = db_fetch_row('SELECT * FROM user_auth WHERE id=' . $_SESSION['sess_user_id']);
	$sql_where = get_graph_permissions_sql($current_user['policy_graphs'], $current_user['policy_hosts'], $current_user['policy_graph_templates']);

	$realm_id_mactrack = 2120;

	// list of allowed hosts - works only with host with graphs
	$allowed_hosts = "";
	$query = "SELECT distinct host.id as id FROM host LEFT JOIN graph_local ON ( host.id = graph_local.host_id )
            LEFT JOIN graph_templates_graph ON ( graph_templates_graph.local_graph_id = graph_local.id )
            LEFT JOIN graph_templates ON (graph_templates.id=graph_local.graph_template_id)
            LEFT JOIN user_auth_perms ON ((graph_templates_graph.local_graph_id=user_auth_perms.item_id
                and user_auth_perms.type=1 and user_auth_perms.user_id=" .
        	$_SESSION["sess_user_id"] . ") OR (host.id=user_auth_perms.item_id
                and user_auth_perms.type=3 and user_auth_perms.user_id=" .
                $_SESSION["sess_user_id"] . ") OR (graph_templates.id=user_auth_perms.item_id
                and user_auth_perms.type=4 and user_auth_perms.user_id=" .
                $_SESSION["sess_user_id"] . "))
    	    WHERE graph_templates_graph.local_graph_id=graph_local.id and  $sql_where" ;

	    $result = db_fetch_assoc ($query);
	
	    if ($result)	{
		foreach ($result as $item) {
		    $allowed_hosts = $allowed_hosts . $item['id'] . ",";
		}
	    
		$allowed_hosts = substr ($allowed_hosts,0,-1);
	    }


	include_once($config['base_path'] . "/plugins/intropage/include/js.php");




	// ----------------- preparing data
	$values = array();
	$values_pie = array();
        
	// ---------- Hosts
	$values['hosts']['name'] = "Hosts";
        $values['hosts']['alarm'] = "green"; 

	$h_all  = db_fetch_cell ("SELECT count(id) from host where id in ($allowed_hosts)");
	$h_up   = db_fetch_cell ("SELECT count(id) from host where id in ($allowed_hosts) and status='3' and disabled=''");
	$h_down = db_fetch_cell ("SELECT count(id) from host where id in ($allowed_hosts) and status='1' and disabled=''");
	$h_reco = db_fetch_cell ("SELECT count(id) from host where id in ($allowed_hosts) and status='2' and disabled=''");
	$h_disa = db_fetch_cell ("SELECT count(id) from host where id in ($allowed_hosts) and disabled='on'");

	if ($h_down > 0)	
    	    $values['hosts']['alarm'] = "red";
	else if ($h_reco > 0 || $h_disa > 0)	
    	    $values['hosts']['alarm'] = "yellow"; 
    
	if ($console_access)	{
    	    $values['hosts']['data']  = "<a href=\"" . htmlspecialchars($config['url_path']) . "host.php?host_status=-1\">All: $h_all</a> | \n";
	    $values['hosts']['data'] .= "<a href=\"" . htmlspecialchars($config['url_path']) . "host.php?host_status=3\">Up: $h_up</a> | \n";
	    $values['hosts']['data'] .= "<a href=\"" . htmlspecialchars($config['url_path']) . "host.php?host_status=1\">Down: $h_down</a> | \n";
	    $values['hosts']['data'] .= "<a href=\"" . htmlspecialchars($config['url_path']) . "host.php?host_status=-2\">Disabled: $h_disa</a> | \n";
	    $values['hosts']['data'] .= "<a href=\"" . htmlspecialchars($config['url_path']) . "host.php?host_status=2\">Recovering: $h_reco</a>\n";
	}
	else	{
    	    $values['hosts']['data']  = "All: $h_all | \n";
	    $values['hosts']['data'] .= "Up: $h_up | \n";
	    $values['hosts']['data'] .= "Down: $h_down | \n";
	    $values['hosts']['data'] .= "Disabled: $h_disa | \n";
	    $values['hosts']['data'] .= "Recovering: $h_reco\n";
	}

	if (read_config_option('intropage_display_pie_host') == "on")	{
	    $values_pie['host']['title'] = "Hosts:";
	    $values_pie['host']['values'] = "values[Down]=$h_down&amp;values[Up]=$h_up&amp;values[Recovering]=$h_reco&amp;values[Disabled]=$h_disa";
	}
    
	// ---------- Thresholds
	$values['thresholds']['name'] = "Thresholds";
	$values['thresholds']['alarm'] = "green"; 

	if (db_fetch_cell("SELECT directory FROM plugin_config where directory='thold' and status=1"))	{

	    //thold_graph.php, listthold.php
	    $sql = "select id from plugin_realms where file like '%thold_graph.php%' or file like '%listthold.php%'";
	    $result = db_fetch_assoc ($sql);
	
	    if ($result)	{
		$ids = "";
		foreach ($result as $item) {
		    $ids = $ids . ($item['id']+100) . ",";
		}
	    
		$ids = substr ($ids,0,-1);
	    }
	    $sql = "select * from user_auth_realm where user_id = " . $_SESSION["sess_user_id"] . " and realm_id in ($ids)";

	    if (db_fetch_cell($sql))	{ // permission?
	
		$sql  = "SELECT count(*) FROM thold_data ";
		$sql .= "LEFT JOIN user_auth_perms ON ";
		$sql .= "((thold_data.graph_id=user_auth_perms.item_id AND user_auth_perms.type=1 AND user_auth_perms.user_id= " . $_SESSION["sess_user_id"] . ") OR ";
		$sql .= "(thold_data.host_id=user_auth_perms.item_id AND user_auth_perms.type=3 AND user_auth_perms.user_id= " . $_SESSION["sess_user_id"] . ") OR ";
    		$sql .= "(thold_data.graph_template=user_auth_perms.item_id AND user_auth_perms.type=4 AND user_auth_perms.user_id= " . $_SESSION["sess_user_id"] . ")) ";
		$sql .= "where $sql_where";

		$t_all = db_fetch_cell ($sql);

		$sql  = "SELECT count(*) FROM thold_data ";
    		$sql .= "LEFT JOIN user_auth_perms ON ";
		$sql .= "((thold_data.graph_id=user_auth_perms.item_id AND user_auth_perms.type=1 AND user_auth_perms.user_id=" . $_SESSION["sess_user_id"] . ") OR ";
		$sql .= "(thold_data.host_id=user_auth_perms.item_id AND user_auth_perms.type=3 AND user_auth_perms.user_id=" . $_SESSION["sess_user_id"] . ") OR ";
		$sql .= "(thold_data.graph_template=user_auth_perms.item_id AND user_auth_perms.type=4 AND user_auth_perms.user_id=" . $_SESSION["sess_user_id"] . ")) ";
		$sql .= "WHERE (thold_data.thold_alert!=0 OR thold_data.bl_alert>0) AND $sql_where";

		$t_brea = db_fetch_cell ($sql);

		$sql  = " SELECT count(*) FROM thold_data ";
		$sql .= "LEFT JOIN user_auth_perms ON ";
    		$sql .= "((thold_data.graph_id=user_auth_perms.item_id AND user_auth_perms.type=1 AND user_auth_perms.user_id=1) OR ";
		$sql .= "(thold_data.host_id=user_auth_perms.item_id AND user_auth_perms.type=3 AND user_auth_perms.user_id=1) OR ";
		$sql .= "(thold_data.graph_template=user_auth_perms.item_id AND user_auth_perms.type=4 AND user_auth_perms.user_id=1)) ";
		$sql .= "WHERE ((thold_data.thold_alert!=0 AND ";
		$sql .= "thold_data.thold_fail_count >= thold_data.thold_fail_trigger) OR ";
		$sql .= "(thold_data.bl_alert>0 AND thold_data.bl_fail_count >= thold_data.bl_fail_trigger)) AND $sql_where";
		$t_trig = db_fetch_cell ($sql);

		$sql  = "SELECT count(*) FROM thold_data ";
		$sql .= "LEFT JOIN user_auth_perms ON ((thold_data.graph_id=user_auth_perms.item_id AND user_auth_perms.type=1 AND user_auth_perms.user_id=1) OR ";
		$sql .= "(thold_data.host_id=user_auth_perms.item_id AND user_auth_perms.type=3 AND user_auth_perms.user_id=1) OR ";
		$sql .= "(thold_data.graph_template=user_auth_perms.item_id AND user_auth_perms.type=4 AND user_auth_perms.user_id=1)) ";
		$sql .= "WHERE thold_data.thold_enabled='off' AND $sql_where ";
		$t_disa = db_fetch_cell ($sql);

		if ($t_brea > 0 || $t_trig > 0)	
		    $values['thresholds']['alarm'] = "red";
		elseif ($t_disa > 0)	
		    $values['thresholds']['alarm'] = "yellow"; 

		if ($console_access)	{
		    $values['thresholds']['data']  = "<a href=\"" . htmlspecialchars($config['url_path']) . "plugins/thold/thold_graph.php?tab=thold&amp;triggered=-1\">All: $t_all</a> | \n";
		    $values['thresholds']['data'] .= "<a href=\"" . htmlspecialchars($config['url_path']) . "plugins/thold/thold_graph.php?tab=thold&amp;triggered=1\">Breached: $t_brea</a> | \n";
		    $values['thresholds']['data'] .= "<a href=\"" . htmlspecialchars($config['url_path']) . "plugins/thold/thold_graph.php?tab=thold&amp;triggered=3\">Trigged: $t_trig</a> | \n";
		    $values['thresholds']['data'] .= "<a href=\"" . htmlspecialchars($config['url_path']) . "plugins/thold/thold_graph.php?tab=thold&amp;triggered=0\">Disabled: $t_disa</a>\n";
		}
		else	{
		    $values['thresholds']['data']  = "All: $t_all\n | ";
		    $values['thresholds']['data'] .= "Breached: $t_brea\n | ";
		    $values['thresholds']['data'] .= "Trigged: $t_trig\n | ";
		    $values['thresholds']['data'] .= "Disabled: $t_disa\n";
		}


		if (read_config_option('intropage_display_pie_threshold') == "on")	{
		    $values_pie['threshold']['title'] = "Thresholds:";
		    $values_pie['threshold']['values'] = "values[Breached]=$t_brea&amp;values[OK]=" .($t_all-$t_brea-$t_trig-$t_disa) . "&amp;values[Trigerred]=$t_trig&amp;values[Disabled]=$t_disa";
		}
	    }
	    else	{ // no permission
		$values['thresholds']['data'] = "You don't have permission\n";
	    }
	}
	else	{
	    $values['thresholds']['data'] = "Thold plugin not installed/running\n";
	}


	// db check

	if ($console_access && read_config_option('intropage_db_check') == "on")	{

	    $values['dbcheck']['name'] = "Database check";
	    $values['dbcheck']['alarm'] = "green"; 
	    $damaged = 0;
	    $memtables = 0;

	    $result = db_fetch_assoc ("SHOW TABLES");

	    foreach($result as $key=>$val)	{
    		$row = db_fetch_row ("check table " . current($val) . " MEDIUM");
    		if (strtolower ($row["Msg_type"]) == "note" && strpos (strtolower ($row["Msg_text"]), "doesn't support" ) )
    		    $memtables++;
    		elseif (strtolower ($row["Msg_text"]) != "ok")	{
		    $values['dbcheck']['detail'] .= "Table " . $row["Table"] . " status " . $row["Msg_text"] . "<br/>\n";
		    $damaged++;
		}
	    }

	    if ($damaged > 0)	{	
    		$values['dbcheck']['alarm'] = "red";
	    }

    	    $values['dbcheck']['data'] = "Damaged tables: $damaged, Memory tables: $memtables";
	}

	// ---------- Mactrack

	$values['mactrack']['name'] = "Mactrack";
	$values['mactrack']['alarm'] = "green"; 
    
	if (db_fetch_cell("SELECT directory FROM plugin_config where directory='mactrack' and status=1"))      {
    
	    if (db_fetch_cell("select * from user_auth_realm where user_id = " .  $_SESSION["sess_user_id"] . " and realm_id=$realm_id_mactrack"))	{ // permission?
    
		$query = "select id , description , hostname FROM host WHERE ID NOT IN (SELEct distinct host_id from mac_track_devices ) AND snmp_version!=0";
    		$result = db_fetch_assoc ($query);

		$m_count = sizeof($result);
    		if ($m_count > 0) {
    		    $values['mactrack']['detail'] = "Hosts without mactrack<br/>";

    		    foreach ($result as $item) {
        		$values['mactrack']['detail'] .= "<a href=\"" . htmlspecialchars($config['url_path']) . "host.php?action=edit&amp;id=" . $item['id'] . "\">" . $item['description'] . "-" . $item['hostname'] . "</a><br/>\n";
    		    }
		}

		$m_all  = db_fetch_cell ("select count(host_id) from mac_track_devices");
		$m_up = db_fetch_cell ("select count(host_id) from mac_track_devices where snmp_status='3'");
		$m_down = db_fetch_cell ("select count(host_id) from mac_track_devices where snmp_status='1'");
		$m_disa = db_fetch_cell ("select count(host_id) from mac_track_devices where snmp_status='-2'");
		$m_err  = db_fetch_cell ("select count(host_id) from mac_track_devices where snmp_status='4'");
		$m_unkn = db_fetch_cell ("select count(host_id) from mac_track_devices where snmp_status='0'");

		if ($m_down > 0 || $m_err > 0 || $m_unkn > 0)
    		    $values['mactrack']['alarm'] = "red";
    		elseif ($m_disa > 0)
    		    $values['mactrack']['alarm'] = "yellow";
    	    
		$values['mactrack']['data']  = "<a href=\"" . htmlspecialchars($config['url_path']) . "plugins/mactrack/mactrack_devices.php?site_id=-1&amp;status=-1&amp;type_id=-1&amp;device_type_id=-1&amp;filter=&amp;rows=-1\">All: $m_all</a> | \n";
		$values['mactrack']['data'] .= "<a href=\"" . htmlspecialchars($config['url_path']) . "plugins/mactrack/mactrack_devices.php?site_id=-1&amp;status=3&amp;type_id=-1&amp;device_type_id=-1&amp;filter=&amp;rows=-1\">Up: $m_up</a> | \n";
		$values['mactrack']['data'] .= "<a href=\"" . htmlspecialchars($config['url_path']) . "plugins/mactrack/mactrack_devices.php?site_id=-1&amp;status=1&amp;type_id=-1&amp;device_type_id=-1&amp;filter=&amp;rows=-1\">Down: $m_down</a> | \n";
		$values['mactrack']['data'] .= "<a href=\"" . htmlspecialchars($config['url_path']) . "plugins/mactrack/mactrack_devices.php?site_id=-1&amp;status=4&amp;type_id=-1&amp;device_type_id=-1&amp;filter=&amp;rows=-1\">Erros: $m_err</a> | \n";
		$values['mactrack']['data'] .= "<a href=\"" . htmlspecialchars($config['url_path']) . "plugins/mactrack/mactrack_devices.php?site_id=-1&amp;status=0&amp;type_id=-1&amp;device_type_id=-1&amp;filter=&amp;rows=-1\">Unknown: $m_unkn</a> | \n";
		$values['mactrack']['data'] .= "<a href=\"" . htmlspecialchars($config['url_path']) . "plugins/mactrack_devices.php?site_id=-1&amp;status=-2&amp;type_id=-1&amp;device_type_id=-1&amp;filter=&amp;rows=-1\">Disabled: $m_disa</a>\n";

		if (read_config_option('intropage_display_pie_mactrack') == "on")	{
		    $values_pie['mactrack']['title'] = "Mactrack:";
		    $values_pie['mactrack']['values'] = "values[Down]=$m_down&amp;values[Up]=$m_up&amp;values[Error]=$m_err&amp;values[Unknown]=$m_unkn&amp;values[Disabled]=$m_disa";
		}
	    }
	    else	{ // no permission
		$values['mactrack']['data'] = "You don't have permission\n";
	    }
	}
	else	{
	    $values['mactrack']['data'] = "Mactrack plugin not installed/running\n";
	}

	// ----------- time

	if ($console_access && $ntp_enable == "on")	{

	    $values['time']['name'] = "Time synchronization";
	    $values['time']['alarm'] = "green"; 

    	    $ntp_server = read_config_option('intropage_ntp_server');

    	    if (preg_match("/^(([0-9]|[1-9][0-9]|1[0-9]{2}|2[0-4][0-9]|25[0-5])\.){3}([0-9]|[1-9][0-9]|1[0-9]{2}|2[0-4][0-9]|25[0-5])$/", $ntp_server) ||
        	preg_match("/^(([a-zA-Z0-9]|[a-zA-Z0-9][a-zA-Z0-9\-]*[a-zA-Z0-9])\.)*([A-Za-z0-9]|[A-Za-z0-9][A-Za-z0-9\-]*[A-Za-z])$/", $ntp_server))	{

		    $time = ntp_time ($ntp_server);
		    if ($time > 0)	{
			$time_local = date("U");

		    $diff = $time_local - $time;
		    
		    if ($diff < -600 || $diff > 600)	{
			$values['time']['alarm'] = "red";
			$values['time']['data']  = date("Y-m-d H:i:s") . " (Please check time. It is different (more than 10 minutes) from NTP server $ntp_server)";
		    }
		    elseif ($diff < -120 || $diff > 120)	{
			$values['time']['alarm'] = "yellow";
			$values['time']['data'] = date("Y-m-d H:i:s") . " (Please check time. It is different (more than 2 minutes) from NTP server $ntp_server)";
		    }
		    else	{
			$values['time']['data'] = date("Y-m-d H:i:s") . " (Localtime is equal to NTP server $ntp_server)";
		    }
		}
		else	{
		    $values['time']['alarm'] = "red";
    		    $values['time']['data'] = "(I can't check time from $ntp_server)</td>";
		}
	    }
	    else	{
		$values['time']['alarm'] = "red";
		$values['time']['data'] = "Incorrect ntp server address, please insert IP or DNS name</td>";
    	    }
	}
    
        // ---------- log
	if ($console_access && read_config_option('intropage_log_enable') == "on")	{
	    $values['log_size']['name'] = "Log file size";
	    $values['log_size']['alarm'] = "green"; 

	    $size = filesize ($logfile);
	    $values['log_size']['data'] = "<a href=\"" . htmlspecialchars($config['url_path']) . "utilities.php?action=view_logfile\">";
	
	    if (!$size)	{
		$values['log_size']['alarm'] = "red"; 
		$values['log_size']['data'] .= "Log file not accessible";
	    }
	    else	{
		if ($size < 0)	{ 
		    $values['log_size']['alarm'] = "red";
		    $values['log_size']['data'] .= "more then 2GB";
		}
		elseif ($size < 255999999)	{
		    $values['log_size']['alarm'] = "green";
		    $values['log_size']['data'] .= humanFileSize($size); 
		}
		else	{
		    $values['log_size']['alarm'] = "yellow";
		    $values['log_size']['data'] .= humanFileSize($size) . " (Logfile is quite large)";
		}
	    }
	    $values['log_size']['data'] .= "</a>\n";

	    // poller stats
	    $values['poller']['name'] = "Poller stats (interval " . read_config_option("poller_interval") . "s)";
	    $values['poller']['alarm'] = "green"; 

	    unset ($time);
	    $avg_time = 0;
	    $count = 0;

	    $logcontents = tail_file_2 ($logfile, $lines);

	    if (!$size)	{
		$values['poller']['alarm'] = "red"; 
		$values['poller']['data'] = "Log file not accessible";
	    }
	    else	{
		if (isset ($logcontents['stats']))	{
		    $values['poller']['detail'] = "";
		    foreach ($logcontents['stats'] as $item)	{
			$values['poller']['detail'] .= "$item<br/>";
			if (strpos ($item, "SYSTEM STATS") === false) 
			    continue;
			
			$start = strpos ($item, "Time:")+5;
			$end   = strpos ($item, " ", $start)-1;
			$time  = substr ($item, $start, $end-$start);
			$count++;
			$avg_time += $time;
		    }
		    $avg_time = round (($avg_time/$count),1);
		    $values['poller']['detail'] .= "<a href=\"" . htmlspecialchars($config['url_path']) . "utilities.php?action=view_logfile&amp;tail_lines=$lines&amp;message_type=1&amp;reverse=1\">Full log - last $lines lines</a><br/>\n";
		}
	    }
	
	    if (!isset ($time))	{
		$values['poller']['alarm'] = "yellow";
		$values['poller']['data'] = "Don't see any Stats, purged log?";
	    }
	    else {
		$nasobek = read_config_option("poller_interval")/$time;
		if ($nasobek < 1.2)	{
		    $values['poller']['alarm'] = "red";
		    $values['poller']['data'] = "average " . $avg_time ."s (Polling is almost reaching the limit)";
		}
		else if ($nasobek < 1.5)	{
		    $values['poller']['alarm'] = "yellow"; 
		    $values['poller']['data'] = "average " .$avg_time . "s (Polling is close to the limit)";
		}
		else	{
		    $values['poller']['alarm'] = "green"; 
		    $values['poller']['data']  = "average " .$avg_time . "s (Polling is finished in time)";
		}
	    }

	    // warning and error    
    	    $values['log_err']['name'] = "Warning and error (in last $lines lines)";
	    $values['log_err']['alarm'] = "green"; 
    
	    if (!$size)	{
		$values['log_err']['alarm'] = "red";
		$values['log_err']['data'] = "Log file not accessible";
	    }
	    else	{
    		$values['log_err']['detail'] = "";
	    
		if (isset ($logcontents['warn']))	{

		    $values['log_err']['alarm'] = "yellow"; 
		    $values['log_err']['data'] = "Warnings in log (in last $lines lines)";
		    foreach ($logcontents['warn'] as $item)	{
			$values['log_err']['detail'] .= "$item<br/>\n";
	    	    }
		}
		if (isset ($logcontents['error']))	{
		    $values['log_err']['alarm'] = "red";
		    $values['log_err']['data'] = "Errors in log (in last $lines lines)";
		    foreach ( $logcontents['error'] as $item)	{
	    		$values['log_err']['detail'] .=  "$item<br/>\n";
    		    }
		}

		if (isset ($logcontents['fatal']))	{
		    $values['log_err']['alarm'] = "red";
		    $values['log_err']['data'] = "Fatal Errors in log (in last $lines lines)";
		    foreach ( $logcontents['fatal'] as $item)	{
	    		$values['log_err']['detail'] .=  "$item<br/>\n";
    		    }
		}

	    
		$values['log_err']['detail'] .= "<a href=\"" . htmlspecialchars($config['url_path']) . "utilities.php?tail_lines=$lines&amp;message_type=3&amp;reverse=1&amp;action=view_logfile\">Full log - last $lines lines</a><br/>\n";
	    }	
	}


	// ---------- logins
	if ($console_access && read_config_option('intropage_login_enable') == "on")	{

	    $values['login']['name'] = "Last 10 logins";
	    $values['login']['alarm'] = "green"; 
	    $values['login']['data']  = "Without Failed logins";
	    $values['login']['detail'] = "";

	    $user_log_sql = "SELECT user_log.username, user_auth.full_name, user_log.time, user_log.result, user_log.ip FROM user_auth RIGHT JOIN user_log ON user_auth.username = user_log.username ORDER  BY user_log.time desc LIMIT 10";
	    $result = db_fetch_assoc ($user_log_sql);
    
	    foreach($result as $row)	{
		$values['login']['detail'] .= $row['time'] . " - " . $row['username'] . "(" . $row['full_name'] .") result: ";
		$values['login']['detail'] .= $row['result'] == 0 ? "Failed" : "Success";
		$values['login']['detail'] .= ", IP: " . $row['ip'] . "<br/>\n";
		if ($row['result'] == 0)	{
		    $values['login']['alarm'] = "red"; 
		    $values['login']['data'] = "Failed logins";
		}
	    }
	    $values['login']['detail'] .= "<a href=\"" . htmlspecialchars($config['url_path']) . "utilities.php?action=view_user_log\">Full log</a><br/>\n";
	}


	// ---------------------- Christopher's values :-) 

	// --- same description
	$values['description']['name'] = "Devices with the same description";
	$values['description']['alarm'] = "green"; 

	$query = "SELECT id,description, count(*) as count FROM host where id in ($allowed_hosts) GROUP BY description HAVING count(*)>1";
	
	$result = db_fetch_assoc ($query);

	$count = sizeof($result);
	if ($count > 0) {
	    $values['description']['alarm'] = "red";
	    $values['description']['detail'] = "";


	    foreach ($result as $item) {
		$query = "SELECT id FROM host where description = '" . $item['description'] . "'";
		$result2 = db_fetch_assoc ($query);
		
		foreach ($result2 as $item2) {
		    $values['description']['detail'] .= "<a href=\"" . htmlspecialchars($config['url_path']) . "host.php?action=edit&amp;id=" . $item2['id'] . "\">" . $item['description'] . "</a> (ID " . $item2['id'] . ")<br/>\n";
		}
	    }
	}
	$values['description']['data'] = $count;


	// device in more then one tree
	$values['more_tree']['name'] = "Devices in more then one tree";
	$values['more_tree']['alarm'] = "green"; 

	$query = "SELECT description, name, gt.id as idtree, gt.name as treename FROM graph_tree gt, graph_tree_items gti, host WHERE host.id in ($allowed_hosts) and gt.id=gti.graph_tree_id AND host.id=gti.host_id AND gti.host_id IN (SELECT host_id FROM graph_tree_items GROUP BY host_id HAVING count(*)>1 )";
	$result = db_fetch_assoc ($query);

	$count = sizeof($result);
	if ($count > 0) {
	    $values['more_tree']['alarm'] = "red";
	    $values['more_tree']['detail'] = "";
	
	    foreach ($result as $item) {
		$values['more_tree']['detail'] .= "<a href=\"" . htmlspecialchars($config['url_path']) . "tree.php?action=edit&amp;id=" . $item['idtree'] . "\">" . $item['description'] .  " (Tree " . $item['treename'] . ") </a><br/>\n";
	    }
	}
	$values['more_tree']['data'] = $count;


	// host without graphs
	$values['without_graph']['name'] = "Hosts without graphs";
	$values['without_graph']['alarm'] = "green";

	$query = "select id , description FROM host WHERE id in ($allowed_hosts) and ID NOT IN (SELECT distinct host_id from graph_local ) AND snmp_version!=0";

	$result = db_fetch_assoc ($query);

	$count = sizeof($result);
	if ($count > 0) {
	    $values['without_graph']['alarm'] = "red";
	    $values['without_graph']['detail'] = "";
	    foreach ($result as $item) {
		$values['without_graph']['detail'] .= "<a href=\"" . htmlspecialchars($config['url_path']) . "host.php?action=edit&amp;id=" . $item['id'] . "\">" . $item['description'] . "</a><br/>\n";
	    }
	}
	$values['without_graph']['data'] = $count;


	// host without tree
	$values['without_tree']['name'] = "Hosts without tree";
	$values['without_tree']['alarm'] = "green";

	$query = "SELECT description, id FROM host WHERE id in ($allowed_hosts) and id NOT IN (SELECT host_id AS id FROM graph_tree_items)";

	$result = db_fetch_assoc ($query);

	$count = sizeof($result);
	if ($count > 0) {
	    $values['without_tree']['alarm'] = "red";
 	    $values['without_tree']['detail'] = "";
	
	    foreach ($result as $item) {
		$values['without_tree']['detail'] .= "<a href=\"" . htmlspecialchars($config['url_path']) . "host.php?action=edit&amp;id=" . $item['id'] . "\">" . $item['description'] . "</a><br/>\n";
	    }
	}
	$values['without_tree']['data'] = $count;

    
	// not monitored hosts
	$values['monitor']['name'] = "Not monitored hosts";
	$values['monitor']['alarm'] = "green";

	if (db_fetch_cell("SHOW COLUMNS FROM `host` LIKE 'monitor'"))	{
    	    $query = "SELECT description, id FROM host WHERE id in ($allowed_hosts) and monitor != 'on'";

	    $result = db_fetch_assoc ($query);

	    $count = sizeof($result);
	    if ($count > 0) {
		$values['monitor']['alarm'] = "red";
		$values['monitor']['detail'] = "";
	    
		foreach ($result as $item) {
		    $values['monitor']['detail'] .= "<a href=\"" . htmlspecialchars($config['url_path']) . "host.php?action=edit&amp;id=" . $item['id'] . "\">" . $item['description'] . "</a><br/>\n";
		}
	    }
	    $values['monitor']['data'] = $count;
	}
	else	{
	    $values['monitor']['data'] = "Plugin monitor not installed/running";
	}


	// ---------- Datasources
	if (read_config_option("intropage_display_pie_datasource") == "on")	{
	    $get_val_ds = "";
//		$data_count = db_fetch_assoc("SELECT i.type_id, COUNT(i.type_id) AS total FROM data_template_data AS d, data_input AS i WHERE d.data_input_id = i.id AND local_data_id <> 0 GROUP BY i.type_id");
	    $data_count = db_fetch_assoc("SELECT data_input.type_id, count(data_input.type_id) as total FROM (data_local,data_template_data) LEFT JOIN data_input ON (data_input.id=data_template_data.data_input_id) LEFT JOIN data_template ON (data_local.data_template_id=data_template.id) WHERE data_local.id=data_template_data.local_data_id and local_data_id<>0 and host_id in ($allowed_hosts) group by type_id");
	    if (sizeof($data_count)) {
		foreach ($data_count as $item) {
		    $input_types[$item["type_id"]] = stri_replace ("script server", "SS",$input_types[$item["type_id"]]);
		    $get_val_ds .= "&amp;values[" . $input_types[$item["type_id"]] . "]=$item[total]";
		}
	    }
	    $values_pie['datasource']['title'] = "Datasources:";
    	    $values_pie['datasource']['values'] = $get_val_ds;
	}

	// ---------- Hosts by templates
	if (read_config_option("intropage_display_pie_template") == "on")	{
	    $sql = "SELECT host_template.id as id, name, count(host.host_template_id) AS pocet FROM host_template LEFT JOIN host ON host_template.id = host.host_template_id and host.id in ($allowed_hosts) GROUP by host_template_id ORDER BY pocet desc";
	    $result = db_fetch_assoc ($sql);

	    $get_val_templ = "";
	    foreach($result as $row)	{
		$get_val_templ .= "&amp;values[" . $row['name'] . "]=" . $row['pocet'];
	    }
	    $values_pie['template']['title'] = "Templates:";
    	    $values_pie['template']['values'] = $get_val_templ;
	}


	// ---------- end of preparing data



	// ---------- display data

	echo "<table><tr style=\"vertical-align: top; \"><td>\n";
    
	$i = 0;
	html_start_box("<strong>Alerts</strong>", "650", $colors["header"], "3", "left", "");
	echo  "<tr bgcolor=\"#" . $colors["header_panel"] . "\">";
	DrawMatrixHeaderItem("&nbsp;",$colors["header_text"],1);
	DrawMatrixHeaderItem("name",$colors["header_text"],1);
	DrawMatrixHeaderItem("value",$colors["header_text"],1);
	echo "</tr>";
    
	foreach ($values as $val)	{
	    if ( ($display_level == 0 && $val['alarm'] == "red") || ($display_level == 1 && ($val['alarm'] == "yellow" || $val['alarm'] == "red" )) || $display_level == 2)	{
		form_alternate_row_color($colors["alternate"],$colors["light"], $i);
		echo "<td><img src=\"" . $config['url_path'] . "plugins/intropage/images/alert_" . $val['alarm'] . ".png\" /></td>\n";
		echo "<td style=\"vertical-align: top;\"><strong>" . $val['name'] . "</strong></td>\n";
		echo "<td>" . $val['data']; 
		if (isset($val['detail']))	{
    		    echo "<span style=\"float: right\"><a href=\"#\" onclick=\"hide_display('block_$i');\">View/hide details</a></span></td></tr>\n";
    		    form_alternate_row_color($colors["alternate"],$colors["light"], $i);
		    echo "<td colspan=\"3\">\n";	
		    echo "<div id=\"block_$i\" style=\"display: none\">";
		    echo $val['detail'];
		    echo "</div></td>";
		    echo "</tr>\n";
		}
	        else	{
		    echo "</td>\n";
		    echo "</tr>\n";
		}
	    }
	    $i++;
	}
	html_end_box();


	echo "</td>";

    
	if (read_config_option("intropage_display_topx") == "on")	{
	    if ($display_layout == "horizontal" || $display_layout == "bestfit")	
		echo "<td>\n";
	    else	// inner table 
		echo "</tr><tr><td><table><tr><td>\n";	    

	    $i = 0;
	    
	    // --- Top 5 host with bad ping
    	    html_start_box("<strong>Top 5 hosts with the worst ping response</strong>", "315", $colors["header"], "3", "left", "");
	    echo "<tr bgcolor=\"#" . $colors["header_panel"] . "\">";
	    DrawMatrixHeaderItem("Host",$colors["header_text"],1);
	    DrawMatrixHeaderItem("avg.",$colors["header_text"],1);
	    DrawMatrixHeaderItem("cur",$colors["header_text"],1);
	    echo "</tr>";

	    $query = "SELECT description, id , avg_time, cur_time FROM host where host.id in ($allowed_hosts) order by avg_time desc limit 5";
	    $result = db_fetch_assoc ($query);
	    foreach ($result as $item) {
		form_alternate_row_color($colors["alternate"],$colors["light"], $i++);
		if ($console_access)
		    echo "<td style=\"padding-right: 2em;\"><a href=\"" . htmlspecialchars($config['url_path']) . "host.php?action=edit&amp;id=" . $item['id'] . "\">" . $item['description'] . "</a></td>\n";
		else			
		    echo "<td style=\"padding-right: 2em;\">" . $item['description'] . "</td>\n";			

		echo "<td style=\"padding-right: 2em; text-align: right;\">" . round ($item['avg_time'],2) . "</td>\n";
		echo "<td style=\"padding-right: 2em; text-align: right;\">" . round ($item['cur_time'],2) . "</td></tr>\n";
	    }

	    html_end_box(false);


//	    if ($horizontal)	
	    if ($display_layout == "horizontal" || $display_layout == "bestfit")	
		echo "<br style=\"clear: both;\"/><br/>\n";
	    else
		echo "</td><td width=\"10\">&nbsp;</td><td>\n";	    

	
	    $i=0;
	    // --- Top 5 host with lowest availability
	    html_start_box("<strong>Top 5 hosts with the lowest availability</strong>", "315", $colors["header"], "3", "left", "");
	    echo  "<tr bgcolor=\"#" . $colors["header_panel"] . "\">";
	    DrawMatrixHeaderItem("Host",$colors["header_text"],1);
	    DrawMatrixHeaderItem("Availability.",$colors["header_text"],1);
	    echo "</tr>";

	    $query = "SELECT description, id , availability FROM host where  host.id in ($allowed_hosts) order by availability  limit 5;";
	    $result = db_fetch_assoc ($query);

	    foreach ($result as $item) {
		form_alternate_row_color($colors["alternate"],$colors["light"], $i++);
		if ($console_access)
		    echo "<td style=\"padding-right: 2em;\"><a href=\"" . htmlspecialchars($config['url_path']) . "host.php?action=edit&amp;id=" . $item['id'] . "\">" . $item['description'] . "</a></td>\n";
		else
    		    echo "<td style=\"padding-right: 2em;\">" . $item['description'] . "</td>\n";
    		    
		echo "<td style=\"padding-right: 2em; text-align: right;\">" . round (trim($item['availability']),2) . "%</td></tr>\n";
	    }
	    html_end_box();
	    

//	    if ($horizontal)	
	    if ($display_layout == "horizontal" || $display_layout == "bestfit")	
		echo "";
	    else	
		echo "</td></tr></table>\n"; // end of inner table

	}

	$i = 0;
    
	if ($gd)	{
//	    if ($horizontal)	
	    if ($display_layout == "horizontal")
		echo "<td>\n";
	    else
		echo "</tr><tr><td>\n";

	    echo "<table width=\"600\"><tr>";	// inner table

	    foreach ($values_pie as $val)	{
    		echo "<td><strong>" . $val['title'] . "</strong><br/>\n";
//		echo "<img src=\"" . $config['url_path'] . "plugins/intropage/include/graph_pie.php?x=215&amp;y=150&amp;" . $val['values'] . "\" /></td>\n";
		echo "<img src=\"" . $config['url_path'] . "plugins/intropage/include/graph_pie.php?x=330&amp;y=150&amp;" . $val['values'] . "\" /></td>\n";
		$i++;
		if ($i == 2)	echo "</tr><tr>\n";
	    }
	    echo "</tr></table>\n"; //  end of inner table
	    
	    echo "<td></tr>\n";
	}
	else	{
	    if ($horizontal)	
    		echo "<td>\n";
	    else
    		echo "</tr><tr><td>\n";

	    echo "For pie graphs, please install PHP GD library";

	    echo "<td></tr>\n";
	}

	echo "</table>\n"; // end of table withh data, topx and graphs
    
	// ----------------- OS, poller, ...

	if ($console_access && read_config_option("intropage_display_os_info") == "on")	{

	    echo "<br style=\"clear: both;\" />";

	    if ((file_exists(read_config_option("path_spine"))) && ((function_exists('is_executable')) && (is_executable(read_config_option("path_spine"))))) {
		$out_array = array();
		exec(read_config_option("path_spine") . " --version", $out_array);
		if (sizeof($out_array) > 0) {
		    $spine_version = $out_array[0];
		}
	    }

	    if (file_exists(read_config_option("path_spine")) && $poller_options[read_config_option("poller_type")] == 'spine') 
		$type = $spine_version;
	    else 
		$type = $poller_options[read_config_option("poller_type")];
	
	    echo "<strong>Poller type: </strong><a href=\"" . htmlspecialchars($config['url_path']) .  "settings.php?tab=poller\">$type</a>\n";

	    echo "<br/><strong>Running on:</strong> ";
	    if (function_exists("php_uname")) {
    		print php_uname();
	    }
	    else	{
    		print PHP_OS;
	    }
	}
	
    }	// he has permission 
    else	{
	echo "Intropage - permission denied";
    }
    echo "<br/><br/>\n";
}


?>