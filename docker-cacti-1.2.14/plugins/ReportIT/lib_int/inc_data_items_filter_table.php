<tr bgcolor="#<?php print $colors["panel"];?>">
		<form name="form_graph_id" method="post">
		<td>
			<table width="100%" cellpadding="0" cellspacing="0">
				<tr>
					<td width="100">
						&nbsp;Filter by host:&nbsp;
					</td>
					<td width="1">
						<select name="cbo_graph_id" onChange="window.location=document.form_graph_id.cbo_graph_id.options[document.form_graph_id.cbo_graph_id.selectedIndex].value">
							<option value="cc_items.php?id=<?php print $_REQUEST["id"];?>&host=-1&filter=<?php print $_REQUEST["filter"];?>"<?php if ($_REQUEST["host"] == "-1") {?> selected<?php }?>>Any</option>

							<?php
							/* fetch host names */
							if (read_config_option("auth_method") != 0 & $report_data['graph_permission'] == 1) {
								$sql = "SELECT DISTINCT host.id,CONCAT_WS('',host.description,' (',host.hostname,')') AS name
										FROM (graph_templates_graph,graph_local)
										LEFT JOIN host ON (host.id=graph_local.host_id)
										LEFT JOIN graph_templates ON (graph_templates.id=graph_local.graph_template_id)
										LEFT JOIN user_auth_perms ON ((graph_templates_graph.local_graph_id=user_auth_perms.item_id and user_auth_perms.type=1 and user_auth_perms.user_id=" . $report_data["user_id"] . ") OR (host.id=user_auth_perms.item_id and user_auth_perms.type=3 and user_auth_perms.user_id=" . $report_data["user_id"] . ") OR (graph_templates.id=user_auth_perms.item_id and user_auth_perms.type=4 and user_auth_perms.user_id=" . $report_data["user_id"] . "))
										WHERE graph_templates_graph.local_graph_id=graph_local.id";

								$sql .= ($report_data['host_template_id'] != 0) ? " AND host.host_template_id = {$report_data['host_template_id']}" : "";
								$sql .= (empty($sql_where)) ? "" : " AND $sql_where";
								$sql .= " ORDER BY description,hostname";


							}else {
								$sql = "SELECT host.id,CONCAT_WS('',host.description,' (',host.hostname,')') AS name from host";
								if ($report_data['host_template_id'] != 0) $sql .= " WHERE host_template_id = {$report_data['host_template_id']}";
								$sql .= " ORDER BY description,hostname";
							}
							$hosts = db_fetch_assoc($sql);

							if (sizeof($hosts) > 0) {
								foreach ($hosts as $host) {
										print "<option value='cc_items.php?id=" . $_REQUEST['id'] . "&host=" . $host["id"] . "&page=1'"; if ($_REQUEST["host"] == $host["id"]) { print " selected"; } print ">" . title_trim($host["name"], 40) . "</option>\n";
								}
							}
							?>

						</select>
					</td>
					<td width="30"></td>
					<td width="60">
						Search:&nbsp;
					</td>
					<td width="1">
						<input type="text" name="filter" size="20" value="<?php print $_REQUEST["filter"];?>">
					</td>
					<td>
						&nbsp;<input type="submit" value="Go" alt="Go" border="0" align="absmiddle">
						<input type="submit" name="clear_x" value="Clear" alt="Clear" border="0" align="absmiddle">
					</td>
					<td align="right">
						Host Template Filter:&nbsp;<br>
						Data Source Filter:&nbsp;
					</td>
					<td align="left" style="color:darkblue">
						[<?php print $ht_desc;?>]<br>
						 [<?php print $ds_desc;?>]
					</td>
				</tr>
			</table>
		</td>
		<input type='hidden' name='page' value='1'>
		</form>
	</tr>