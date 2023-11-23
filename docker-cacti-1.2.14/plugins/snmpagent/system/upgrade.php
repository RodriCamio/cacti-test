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

function snmpagent_system_upgrade($old_version) {
	global $config, $database_default;

	if (version_compare($old_version, '0.2', '<')) {
		/* first release of the SNMPAgent Plugin created two tables, but
		   only the caching table has been used. (non static data)
		   drop both and execute the setup routine to keep that version
		   upgrade simple.
		*/
		db_execute("DROP TABLE IF EXISTS `plugin_snmpagent_cache`;");
		db_execute("DROP TABLE IF EXISTS `plugin_snmpagent_mibs`;");
		snmpagent_setup_table_new();
	}
}

?>