<?php
/*
 ex: set tabstop=4 shiftwidth=4 autoindent:
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

chdir('../../');
include_once("./include/auth.php");
include_once("./lib/rrd.php");
include_once("./include/top_graph_header.php");

if (isset($_REQUEST["graph_id"])) {
	$id=$_REQUEST["graph_id"];
} else {
	break;
}

$graph_items = db_fetch_assoc("
	select distinct
	data_template_rrd.local_data_id
	from graph_templates_item
	inner join data_template_rrd on (graph_templates_item.task_item_id=data_template_rrd.id)
	where graph_templates_item.local_graph_id=$id");

print "<BR>";

foreach ($graph_items as $graph_item) {
	$data_source_path = get_data_source_path($graph_item["local_data_id"], true);
	print "<BR>Removing spikes for: <B>$data_source_path</b><BR>";
	$command = $config["base_path"]."/plugins/killspike/removespikes.pl $data_source_path" ;
	passthru($command);
}

print "<BR><B><a href=\"javascript:history.go(-1)\">Back</a></b>";
?>
