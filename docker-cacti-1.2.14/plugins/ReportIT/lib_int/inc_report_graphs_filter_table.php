<tr bgcolor="#<?php print $colors["panel"];?>">
		<form name="form_graph_id" method="post">
		<td>
			<table cellpadding="1" cellspacing="0" border="0">
				<tr>
					<td width="80">
						&nbsp;Data&nbsp;Source:&nbsp;
					</td>
					<td width="1">
						<select name="cbo_graph_id" style="width:120px" onChange="window.location=document.form_graph_id.cbo_graph_id.options[document.form_graph_id.cbo_graph_id.selectedIndex].value">
							<option value="cc_view.php?action=show_graphs&id=<?php print $_REQUEST['id'];?>&data_source=-1&measurand=<?php print $_REQUEST["measurand"];?>&summary=<?php print $_REQUEST["summary"];?>"<?php if ($_REQUEST["data_source"] == "-1") {?> selected<?php }?>>Any</option>
							<?php
								if (sizeof($ds_description) > 0) {
									foreach ($data_sources as $key => $value) {
										print "<option value='cc_view.php?action=show_graphs&id=" . $_REQUEST['id'] . "&data_source=$key&measurand=" . $_REQUEST['measurand'] . "&summary=" . $_REQUEST['summary'] ."'"; if ($_REQUEST["data_source"] == $key) { print " selected"; } print ">" . title_trim($value, 40) . "</option>\n";
									}
								}
									?>
						</select>
					</td>
					<td width="1">&nbsp;&nbsp;</td>
					<td width="60">
						Measurand:&nbsp;
					</td>
					<td width="1">
							<select name="cbo_graph_id_2" style="width:65px" onChange="window.location=document.form_graph_id.cbo_graph_id_2.options[document.form_graph_id.cbo_graph_id_2.selectedIndex].value">
							<option value="cc_view.php?action=show_graphs&id=<?php print $_REQUEST['id'];?>&data_source=<?php print $_REQUEST["data_source"];?>&measurand=-1"<?php if ($_REQUEST["measurand"] == "-1") {?> selected<?php }?>>Any</option>
							<?php
								if (sizeof($measurands) > 0) {
									foreach ($measurands as $key => $value) {
										print "<option value='cc_view.php?action=show_graphs&id=" . $_REQUEST['id'] . "&data_source=" . $_REQUEST['data_source'] . "&measurand=" . $key . "'"; if ($_REQUEST["measurand"] == $key) { print " selected"; } print ">" . title_trim($value, 40) . "</option>\n";
									}
								}
									?>
						</select>
					</td>
					<td width="1">&nbsp;&nbsp;</td>

					<td width="1">
						Search:&nbsp;
					</td>
					<td width="1">
						<input type="text" name="filter" style="width:170px" value="<?php print $_REQUEST["filter"];?>">
					</td>

					<td width="25%">
						&nbsp;<input type="submit" value="Go" alt="Go" border="0" align="absmiddle">
						<input type="submit" name="clear_x" value="Clear" alt="Clear" border="0" align="absmiddle">
					</td>

					<td width="100%" align="right">
						<input type="checkbox" value="cc_view.php?action=show_graphs&id=<?php print $_REQUEST['id'];?>&data_source=<?php print $_REQUEST['data_source'];?>&measurand=<?php print $_REQUEST['measurand'];?>&summary=<?php ($_REQUEST["summary"] == 1)? print "0" : print "1";?>&page=1" onclick="window.location=document.form_graph_id.summary.value" name="summary" id="summary" <?php ($_REQUEST['summary'] == 1) ? print ' checked' : print '';?>>Summary&nbsp;
					</td>

				</tr>
				<tr>
					<td width="80">
						&nbsp;Type:&nbsp;
					</td>
					<td width="1">
						<select name="cbo_graph_id_4" style="width:120px" onChange="window.location=document.form_graph_id.cbo_graph_id_4.options[document.form_graph_id.cbo_graph_id_4.selectedIndex].value">
							<?php
							foreach ($graphs as $key => $value) {
							    print "<option value='cc_view.php?action=show_graphs&id=" . $_REQUEST['id'] . "&data_source=" . $_REQUEST['data_source'] . "&measurand=" . $_REQUEST['measurand'] . "&type=" . $key . "'"; if ($_REQUEST["type"] == $key) { print " selected"; } print ">" . title_trim($value, 40) . "</option>\n";
							}
							echo "</select>";
							?>
					</td>
					<td width="1">&nbsp;&nbsp;</td>

					<td width="60">
						Rows:&nbsp;
					</td>
					<td width="1">
						<select name="cbo_graph_id_5" style="width:65px" onChange="window.location=document.form_graph_id.cbo_graph_id_5.options[document.form_graph_id.cbo_graph_id_5.selectedIndex].value">
							<?php
							foreach ($limit as $key => $value) {
							    print "<option value='cc_view.php?action=show_graphs&id=" . $_REQUEST['id'] . "&data_source=" . $_REQUEST['data_source'] . "&measurand=" . $_REQUEST['measurand'] . "&limit=" . $key . "'"; if ($_REQUEST["limit"] == $key) { print " selected"; } print ">" . title_trim($value, 40) . "</option>\n";
							}
							?>
						</select>
					</td>
					<td width="1">&nbsp;&nbsp;</td>

					<?php if ($archive != false) {?>
						<td width="1">
						Archive:&nbsp;
						</td>
						<td width="1">
							<select style="width:170px" name="cbo_graph_id_3" onChange="window.location=document.form_graph_id.cbo_graph_id_3.options[document.form_graph_id.cbo_graph_id_3.selectedIndex].value">
							<option value="cc_view.php?action=show_graphs&id=<?php print $_REQUEST['id'];?>&data_source=<?php print $_REQUEST["data_source"];?>&measurand=-1&archive=-1"<?php if ($_REQUEST["archive"] == "-1") {?> selected<?php }?>>Current</option>
							<?php
							if (sizeof($archive) > 0) {
									foreach ($archive as $key => $value) {
									    print "<option value='cc_view.php?action=show_graphs&id=" . $_REQUEST['id'] . "&data_source=-1&measurand=-1" . "&archive=" . $key . "'"; if ($_REQUEST["archive"] == $key) { print " selected"; } print ">" . title_trim($value, 40) . "</option>\n";
									}
							}
							echo "</select></td><td width='1'>&nbsp;&nbsp;</td>";
					} ?>
				</tr>
			</table>
		</td>
		<input type='hidden' name='page' value='1'>
		</form>
	</tr>