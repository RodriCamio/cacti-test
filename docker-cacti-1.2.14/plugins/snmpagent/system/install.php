<?php
/*
   +-------------------------------------------------------------------------+
   | Copyright (C) 2014 The Cacti Group                                      |
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

function snmpagent_system_install() {

	/*
	* Table `plugin_snmpagent_cache`
	* - contains parsed all MIB objects, tables and data values
	*/
	$data = array();
	$data['columns'][] = array(	'name' => 'oid',				'type' => 'varchar(255)',	'NULL' => false);
	$data['columns'][] = array(	'name' => 'name',				'type' => 'varchar(255)',	'NULL' => false);
	$data['columns'][] = array(	'name' => 'mib',				'type' => 'varchar(255)',	'NULL' => false);
	$data['columns'][] = array(	'name' => 'type',				'type' => 'varchar(255)',	'NULL' => false, 'default' => '');
	$data['columns'][] = array(	'name' => 'otype',				'type' => 'varchar(255)',	'NULL' => false, 'default' => '');
	$data['columns'][] = array(	'name' => 'kind',				'type' => 'varchar(255)',	'NULL' => false, 'default' => '');
	$data['columns'][] = array(	'name' => 'max-access',			'type' => 'varchar(255)',	'NULL' => false, 'default' => 'not-accessible');
	$data['columns'][] = array(	'name' => 'value',				'type' => 'varchar(255)',	'NULL' => false, 'default' => '');
	$data['columns'][] = array(	'name' => 'description',		'type' => 'varchar(5000)',	'NULL' => false, 'default' => '');

	$data['primary'] = 'oid';
	$data['keys'][] = array('name' => 'name', 'columns' => 'name');
	$data['keys'][] = array('name' => 'mib', 'columns' => 'mib');
	$data['type'] = 'MyISAM';
	$data['comment'] = 'SNMP MIB CACHE';

	api_plugin_db_table_create ('snmpagent', 'plugin_snmpagent_cache', $data);

	/*
	* Table `plugin_snmpagent_mibs`
	* - keeps a list of all registered MIB names and locations
	*/
	$data = array();
	$data['columns'][] = array(	'name' => 'id', 				'type' => 'int(8)', 		'NULL' => false, 'auto_increment' => true);
	$data['columns'][] = array(	'name' => 'name',				'type' => 'varchar(32)',	'NULL' => false, 'default' => '');
	$data['columns'][] = array(	'name' => 'file',				'type' => 'varchar(255)',	'NULL' => false, 'default' => '');

	$data['primary'] = 'id';
	$data['type'] = 'MyISAM';
	$data['comment'] = 'registered MIB files';

	api_plugin_db_table_create ('snmpagent', 'plugin_snmpagent_mibs', $data);

	/*
	* Table `plugin_snmpagent_cache_notifications`
	* - keeps a list of all notifcations and related attributes found in registered MIB files
	*/
	$data = array();
	$data['columns'][] = array(	'name' => 'name',				'type' => 'varchar(255)',	'NULL' => false);
	$data['columns'][] = array(	'name' => 'mib',				'type' => 'varchar(255)',	'NULL' => false);
	$data['columns'][] = array(	'name' => 'attribute',			'type' => 'varchar(255)',	'NULL' => false);
	$data['columns'][] = array(	'name' => 'sequence_id',		'type' => 'smallint(6)',	'NULL' => false);

	$data['keys'][] = array('name' => 'name', 'columns' => 'name');
	$data['type'] = 'MyISAM';
	$data['comment'] = 'notifcations and related attributes';

	api_plugin_db_table_create ('snmpagent', 'plugin_snmpagent_cache_notifications', $data);

	/*
	* Table `plugin_snmpagent_cache_textual_conventions`
	* - holds a list of all textual_conventions defined within registered MIB files
	*/
	$data = array();
	$data['columns'][] = array(	'name' => 'name',				'type' => 'varchar(255)',	'NULL' => false);
	$data['columns'][] = array(	'name' => 'mib',				'type' => 'varchar(255)',	'NULL' => false);
	$data['columns'][] = array(	'name' => 'type',				'type' => 'varchar(255)',	'NULL' => false, 'default' => '');
	$data['columns'][] = array(	'name' => 'description',		'type' => 'varchar(5000)',	'NULL' => false, 'default' => '');

	$data['keys'][] = array('name' => 'name', 'columns' => 'name');
	$data['keys'][] = array('name' => 'mib', 'columns' => 'mib');
	$data['type'] = 'MyISAM';
	$data['comment'] = 'textual conventions';

	api_plugin_db_table_create ('snmpagent', 'plugin_snmpagent_cache_textual_conventions', $data);

	/*
	* Table `plugin_snmpagent_managers`
	* - keeps a list of SNMP notification receivers and settings
	*/
	$data = array();
	$data['columns'][] = array(	'name' => 'id', 				'type' => 'int(8)', 		'NULL' => false, 'auto_increment' => true);
	$data['columns'][] = array(	'name' => 'hostname',			'type' => 'varchar(255)',	'NULL' => false);
	$data['columns'][] = array(	'name' => 'description',		'type' => 'varchar(255)',	'NULL' => false);
	$data['columns'][] = array(	'name' => 'disabled',			'type' => 'char(2)',		'NULL' => true);
	$data['columns'][] = array(	'name' => 'max_log_size',		'type' => 'tinyint(1)',		'NULL' => false);
	$data['columns'][] = array(	'name' => 'snmp_version',		'type' => 'varchar(255)',	'NULL' => false);
	$data['columns'][] = array(	'name' => 'snmp_community',		'type' => 'varchar(255)',	'NULL' => false);
	$data['columns'][] = array(	'name' => 'snmp_username',		'type' => 'varchar(255)',	'NULL' => false);
	$data['columns'][] = array(	'name' => 'snmp_auth_password',	'type' => 'varchar(255)',	'NULL' => false);
	$data['columns'][] = array(	'name' => 'snmp_auth_protocol',	'type' => 'varchar(255)',	'NULL' => false);
	$data['columns'][] = array(	'name' => 'snmp_priv_password',	'type' => 'varchar(255)',	'NULL' => false);
	$data['columns'][] = array(	'name' => 'snmp_priv_protocol',	'type' => 'varchar(255)',	'NULL' => false);
	$data['columns'][] = array(	'name' => 'snmp_port',			'type' => 'varchar(255)',	'NULL' => false);
	$data['columns'][] = array(	'name' => 'snmp_message_type',	'type' => 'tinyint(1)',		'NULL' => false);
	$data['columns'][] = array(	'name' => 'notes',				'type' => 'text');

	$data['primary'] = 'id';
	$data['keys'][] = array('name' => 'hostname', 'columns' => 'hostname');
	$data['type'] = 'MyISAM';
	$data['comment'] = 'snmp notification receivers';

	api_plugin_db_table_create ('snmpagent', 'plugin_snmpagent_managers', $data);

	/*
	* Table `plugin_snmpagent_managers_notifications`
	* - returns a list of SNMP notifications in relation to the specific receivers
	*/
	$data = array();
	$data['columns'][] = array(	'name' => 'manager_id', 		'type' => 'int(8)', 		'NULL' => false);
	$data['columns'][] = array(	'name' => 'notification',		'type' => 'varchar(255)',	'NULL' => false);
	$data['columns'][] = array(	'name' => 'mib',				'type' => 'varchar(255)',	'NULL' => false);

	$data['keys'][] = array('name' => 'mib', 'columns' => 'mib');
	$data['keys'][] = array('name' => 'manager_id', 'columns' => 'manager_id');
	$data['keys'][] = array('name' => 'manager_id2', 'columns' => 'manager_id`, `notification');
	$data['type'] = 'MyISAM';
	$data['comment'] = 'snmp notifications to receivers';

	api_plugin_db_table_create ('snmpagent', 'plugin_snmpagent_managers_notifications', $data);

	/*
	   * Table `plugin_snmpagent_notifications_log`
	   * - keeps a list of snmp notifications being triggered
	*/
	$data = array();
	$data['columns'][] = array(	'name' => 'id', 				'type' => 'int(12)', 		'NULL' => false, 'auto_increment' => true);
	$data['columns'][] = array(	'name' => 'time', 				'type' => 'int(24)', 		'NULL' => false);
	$data['columns'][] = array(	'name' => 'severity',			'type' => 'tinyint(1)',		'NULL' => false);
	$data['columns'][] = array(	'name' => 'manager_id', 		'type' => 'int(8)', 		'NULL' => false);
	$data['columns'][] = array(	'name' => 'notification',		'type' => 'varchar(255)',	'NULL' => false);
	$data['columns'][] = array(	'name' => 'mib',				'type' => 'varchar(255)',	'NULL' => false);
	$data['columns'][] = array(	'name' => 'varbinds',			'type' => 'varchar(5000)',	'NULL' => false);

	$data['primary'] = 'id';
	$data['keys'][] = array('name' => 'time', 'columns' => 'time');
	$data['keys'][] = array('name' => 'severity', 'columns' => 'severity');
	$data['keys'][] = array('name' => 'manager_id', 'columns' => 'manager_id');
	$data['keys'][] = array('name' => 'manager_id2', 'columns' => 'manager_id`, `notification');
	$data['type'] = 'MyISAM';
	$data['comment'] = 'logs snmp notifications to receivers';
	api_plugin_db_table_create ('snmpagent', 'plugin_snmpagent_notifications_log', $data);

}
?>