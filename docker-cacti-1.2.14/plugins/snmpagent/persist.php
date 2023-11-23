#!/usr/bin/php
<?php
/*
   +-------------------------------------------------------------------------+
   | Copyright (C) 2004-2014 The Cacti Group                                 |
   |                                                                         |
   | This program is free software; you can redistribute it and/or           |
   | modify it under the terms of the GNU General Public License             |
   | as published by the Free Software Foundation; either version 2          |
   | of the License, or (at your option) any later version.                  |
   |                                                                         |
   | This program is snmpagent in the hope that it will be useful,           |
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

/* do NOT run this script through a web browser */
if (!isset($_SERVER["argv"][0]) || isset($_SERVER['REQUEST_METHOD'])  || isset($_SERVER['REMOTE_ADDR'])) {
	die("<br><strong>This script is only meant to run at the command line.</strong>");
}

/* we are not talking to the browser */
$no_http_headers = true;

/* let's report all errors */
error_reporting(E_ALL);

/* allow the script to hang around waiting for connections. */
set_time_limit(0);

chdir(dirname(__FILE__));
include_once("../../include/global.php");

/* translate well-known textual conventions and SNMP base types to net-snmp */
$smi_base_datatypes = array(
	"integer" 			=> "INTEGER",
	"integer32"			=> "Integer32",
	"unsigned32" 		=> "Unsigned32",
	"gauge" 			=> "Gauge",
	"gauge32" 			=> "Gauge32",
	"counter" 			=> "Counter",
	"counter32" 		=> "Counter32",
	"counter64" 		=> "Counter64",
	"timeticks" 		=> "TimeTicks",
	"octect string" 	=> "OCTET STRING",
	"opaque"			=> "Opaque",
	"object identifier" => "OBJECT IDENTIFIER",
	"ipaddress" 		=> "IpAddress",
	"networkaddress" 	=> "IpAddress",
	"bits" 				=> "OCTET STRING",
	"displaystring" 	=> "STRING",
	"physaddress" 		=> "OCTET STRING",
	"macaddress" 		=> "OCTET STRING",
	"truthvalue" 		=> "INTEGER",
	"testandincr" 		=> "Integer32",
	"autonomoustype" 	=> "OBJECT IDENTIFIER",
	"variablepointer" 	=> "OBJECT IDENTIFIER",
	"rowpointer" 		=> "OBJECT IDENTIFIER",
	"rowstatus" 		=> "INTEGER",
	"timestamp" 		=> "TimeTicks",
	"timeinterval" 		=> "Integer32",
	"dateandtime" 		=> "STRING",
	"storagetype" 		=> "INTEGER",
	"tdomain" 			=> "OBJECT IDENTIFIER",
	"taddress" 			=> "OCTET STRING"
);

$data				= false;
$eol				= "\n";
$cache  			= array();
$cache_keys			= array();
$cache_last_refresh = 0;

while(1) {

	cache_status();

	$input = trim(fgets(STDIN));
	switch($input) {
		case "":
			exit(0);
		case "PING":
			print "PONG"  . $eol;
			break;
		case "get":
			$oid = trim(fgets(STDIN));
			if($data = cache_read($oid)) {
				print $data["oid"] . $eol . (isset($smi_base_datatypes[$data["type"]]) ? $smi_base_datatypes[$data["type"]] : "INTEGER") . $eol . $data["value"] . $eol;
			}else {
				print "NONE" . $eol;
			}
			break;
		case "getnext":
			$oid = trim(fgets(STDIN));
			if($data = cache_read_next($oid)) {
				print $data["oid"] . $eol . (isset($smi_base_datatypes[$data["type"]]) ? $smi_base_datatypes[$data["type"]] : "INTEGER") . $eol . $data["value"] . $eol;
			}else {
				print "NONE" . $eol;
			}
			break;
		case "debug":
			print_r($cache);
			break;
		case "shutdown":
			print "BYE";
			exit(0);
	}
}

function cache_read($object) {
	global $cache;
	if(isset($cache[$object])) {
		if( $cache[$object]["otype"] == "DATA" ) {
			if( $cache[$object]["max-access"] == "read-only" || $cache[$object]["max-access"] == "read-write" ) {
				return $cache[$object];
			}
			return $cache[$object];
		}else {
			return false;
		}
	}
}

function cache_read_next($object) {
	global $cache, $cache_keys;
	if(isset($cache[$object])) {
		$rest = array_slice($cache_keys, array_search($object, $cache_keys)+1, 10);
		foreach($rest as $option) {
			if($cache[$option]["otype"] == "DATA") {
				if( $cache[$option]["max-access"] == "read-only" || $cache[$option]["max-access"] == "read-write" ) {
				return $cache[$option];
			}
		}
		}
		return false;
	}else {
		return cache_read($object . ".0");
	}
}

function cache_status() {
	global $cache_last_refresh;
	if(time()-$cache_last_refresh >= 60 ) {
		cache_refresh();
	}
}

function cache_refresh() {
	global $cache, $cache_keys, $cache_last_refresh;

	if(!cache_db_connection()){
		/* unable to read data */
		if( !cache_db_reconnect() ) {
			/* unabled to establish a new connection to the Cacti DB */
			$cache = array();
			$cache_keys = array();
			$cache_last_refresh = time();
			return;
		}
	}

	if(cache_snmpagent_enabled() == TRUE){
		$data = db_fetch_assoc("SELECT `oid`, LOWER(type) as type, `otype`, `max-access`, `value` FROM plugin_snmpagent_cache");
		if($data && sizeof($data)>0) {
			$oids = array();
			$objects = array();
			foreach($data as $object) {
				$oids[] = $object["oid"];
				$object_data[] = $object;
			}
			natsort($oids);

			foreach($oids as $key => $oid) {
				$objects[$oid]= $object_data[$key];
			}

			$cache = $objects;
			$cache_keys = array_keys($cache);
		}else {
			$cache = array();
			$cache_keys = array();
		}
	}else {
		$cache = array();
		$cache_keys = array();
	}
	$cache_last_refresh = time();
}

function cache_snmpagent_enabled(){
	/* SNMPAgent should not return a result anymore if the plugin has been disabled */
	$snmpagent = db_fetch_cell("SELECT status FROM plugin_config WHERE directory = 'snmpagent'", false);
	if($snmpagent == 1) {
		return TRUE;
	}elseif(is_null($snmpagent)) {
		return NULL;
	}else {
		return FALSE;
	}
}

function cache_db_connection(){
	global $cnn_id;
	if($cnn_id) {
		$cacti_version = db_fetch_cell("SELECT cacti FROM version");
		return is_null($cacti_version) ? FALSE : TRUE;
	}
	return FALSE;
}

function cache_db_reconnect(){

	chdir(dirname(__FILE__));
	include_once("../../include/config.php");
	global $cnn_id, $database_type, $database_default, $database_hostname, $database_username, $database_password, $database_port, $database_ssl;

	if(is_object($cnn_id)){
		$cnn_id->Close();
	}
	$dsn = "$database_type://" . rawurlencode($database_username) . ":" . rawurlencode($database_password) . "@" . rawurlencode($database_hostname) . "/" . rawurlencode($database_default) . "?persist";

	if ($database_ssl && $database_type == "mysql") {
		$dsn .= "&clientflags=" . MYSQL_CLIENT_SSL;
	}elseif ($database_ssl && $database_type == "mysqli") {
		$dsn .= "&clientflags=" . MYSQLI_CLIENT_SSL;
	}

	if ($database_port != "3306") {
		$dsn .= "&port=" . $database_port;
	}

	$cnn_id = ADONewConnection($dsn);
	return ($cnn_id) ? TRUE : FALSE;
}
?>