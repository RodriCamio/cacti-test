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

$manager_actions = array(
	1 => "Delete",
	2 => "Enable",
	3 => "Disable"
);

$manager_notification_actions = array(
	0 => "Disable",
	1 => "Enable"
);

$tabs_manager_edit = array(
	"general" => "General",
	"notifications" => "Notifications",
	"logs" => "Logs",
);

$fields_manager_edit = array(
	"host_header" => array(
		"method" => "spacer",
		"friendly_name" => "General SNMP Entity Options"
		),
	"description" => array(
		"method" => "textbox",
		"friendly_name" => "Description",
		"description" => "Give this SNMP entity a meaningful description.",
		"value" => "|arg1:description|",
		"max_length" => "250",
		),
	"hostname" => array(
		"method" => "textbox",
		"friendly_name" => "Hostname",
		"description" => "Fully qualified hostname or IP address for this device.",
		"value" => "|arg1:hostname|",
		"max_length" => "250",
		),
	"disabled" => array(
		"method" => "checkbox",
		"friendly_name" => "Disable SNMP Notification Receiver",
		"description" => "Check this box if you temporary do not want to sent SNMP notifications to this host.",
		"value" => "|arg1:disabled|",
		"default" => "",
		"form_id" => false
		),
	"max_log_size" => array(
		"method" => "drop_array",
		"friendly_name" => "Maximum Log Size",
		"description" => "Maximum number of days notification log entries for this receiver need to be stored.",
		"value" => "|arg1:max_log_size|",
		"default" => 31,
		"array" => array_combine( range(1,31), range(1,31) )
	),
	"spacer1" => array(
		"method" => "spacer",
		"friendly_name" => "SNMP Options"
		),
	"snmp_version" => array(
		"method" => "drop_array",
		"friendly_name" => "SNMP Version",
		"description" => "Choose the SNMP version for this device.",
		"on_change" => "changeHostForm()",
		"value" => "|arg1:snmp_version|",
		"default" => '1',
		"array" => array('1'=>'Version 1', '2'=>'Version 2', '3' => 'Version 3'),
		),
	"snmp_community" => array(
		"method" => "textbox",
		"friendly_name" => "SNMP Community",
		"description" => "SNMP read community for this device.",
		"value" => "|arg1:snmp_community|",
		"form_id" => "|arg1:id|",
		"default" => read_config_option("snmp_community"),
		"max_length" => "100",
		"size" => "15"
		),
	"snmp_username" => array(
		"method" => "textbox",
		"friendly_name" => "SNMP Username (v3)",
		"description" => "SNMP v3 username for this device.",
		"value" => "|arg1:snmp_username|",
		"default" => read_config_option("snmp_username"),
		"max_length" => "50",
		"size" => "15"
		),
	"snmp_auth_password" => array(
		"method" => "textbox_password",
		"friendly_name" => "SNMP Auth Password (v3)",
		"description" => "SNMP v3 user password for this device.",
		"value" => "|arg1:snmp_auth_password|",
		"default" => read_config_option("snmp_password"),
		"max_length" => "50",
		"size" => "15"
		),
	"snmp_auth_protocol" => array(
		"method" => "drop_array",
		"friendly_name" => "SNMP Auth Protocol (v3)",
		"description" => "Choose the SNMPv3 Authorization Protocol.<br>Note: SHA authentication support is only available if you have OpenSSL installed.",
		"value" => "|arg1:snmp_auth_protocol|",
		"default" => read_config_option("snmp_auth_protocol"),
		"array" => $snmp_auth_protocols,
		),
	"snmp_priv_password" => array(
		"method" => "textbox_password",
		"friendly_name" => "SNMP Privacy Password (v3)",
		"description" => "Choose the SNMPv3 Privacy Passphrase.",
		"value" => "|arg1:snmp_priv_password|",
		"default" => read_config_option("snmp_priv_passphrase"),
		"max_length" => "200",
		"size" => "40"
		),
	"snmp_priv_protocol" => array(
		"method" => "drop_array",
		"friendly_name" => "SNMP Privacy Protocol (v3)",
		"description" => "Choose the SNMPv3 Privacy Protocol.<br>Note: DES/AES encryption support is only available if you have OpenSSL installed.",
		"value" => "|arg1:snmp_priv_protocol|",
		"default" => read_config_option("snmp_priv_protocol"),
		"array" => $snmp_priv_protocols,
		),
	"snmp_context" => array(
		"method" => "textbox",
		"friendly_name" => "SNMP Context",
		"description" => "Enter the SNMP Context to use for this device.",
		"value" => "|arg1:snmp_context|",
		"default" => "",
		"max_length" => "64",
		"size" => "25"
		),
	"snmp_port" => array(
		"method" => "textbox",
		"friendly_name" => "SNMP Port",
		"description" => "The UDP port to be used for SNMP traps. Typically 162.",
		"value" => "|arg1:snmp_port|",
		"max_length" => "5",
		"default" => "162",
		"size" => "15"
		),
	"snmp_timeout" => array(
		"method" => "textbox",
		"friendly_name" => "SNMP Timeout",
		"description" => "The maximum number of milliseconds Cacti will wait for an SNMP response (does not work with php-snmp support).",
		"value" => "|arg1:snmp_timeout|",
		"max_length" => "8",
		"default" => read_config_option("snmp_timeout"),
		"size" => "15"
		),
	"snmp_message_type" => array(
		"friendly_name" => "SNMP Message Type",
		"description" => "SNMP traps are always unacknowledged. To send out acknowledged SNMP notifications, formally called \"INFORMS\", SNMPv2 or above will be required.",
		"method" => "drop_array",
		"value" => "|arg1:snmp_message_type|",
		"default" => "1",
		"array" => array(1 => "NOTICATIONS", 2 => "INFORMS")
	),
	"header4" => array(
		"method" => "spacer",
		"friendly_name" => "Additional Options"
		),
	"notes" => array(
		"method" => "textarea",
		"friendly_name" => "Notes",
		"description" => "Enter notes to this host.",
		"class" => "textAreaNotes",
		"value" => "|arg1:notes|",
		"textarea_rows" => "5",
		"textarea_cols" => "50"
		),
	"id" => array(
		"method" => "hidden_zero",
		"value" => "|arg1:id|"
		)
);

$severity_levels = array(
	EVENT_SEVERITY_LOW => 'LOW',
	EVENT_SEVERITY_MEDIUM => 'MEDIUM',
	EVENT_SEVERITY_HIGH => 'HIGH',
	EVENT_SEVERITY_CRITICAL => 'CRITICAL'
);

$severity_colors = array(
	EVENT_SEVERITY_LOW => '#00FF00',
	EVENT_SEVERITY_MEDIUM => '#FFFF00',
	EVENT_SEVERITY_HIGH => '#FF0000',
	EVENT_SEVERITY_CRITICAL => '#FF00FF'
)

?>