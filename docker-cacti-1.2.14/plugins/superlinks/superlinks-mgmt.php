<?php

chdir('../../');
include_once("./include/auth.php");
include_once(dirname(__FILE__) . DIRECTORY_SEPARATOR."/tablib.php");
$cacti_base = dirname(__FILE__) . ".." . DIRECTORY_SEPARATOR . "..";

include_once($config["library_path"] . "/database.php");
include_once($config["library_path"] . "/html_form.php");
include_once($config["library_path"] . "/html_form_template.php");

$superlinks_contentdir = realpath(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'content');
$superlinks_tabdir = realpath(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'tab_images');

/* ================= input validation ================= */
input_validate_input_number(get_request_var_request("id"));
input_validate_input_number(get_request_var_request("order"));
input_validate_input_number(get_request_var_request("pageid"));
input_validate_input_number(get_request_var_request("userid"));
/* ==================================================== */

$sl_actions = array(1 => 'Delete', 3 => 'Enable', 2 => 'Disable');

$action = "";
if (isset($_POST['action'])) {
	$action = $_POST['action'];
} else if (isset($_GET['action'])) {
	$action = $_GET['action'];
}

switch ($action) {
	case 'actions':
		form_actions();

		break;
	case 'delete_page':
		if (isset($_REQUEST['id']) && is_numeric($_REQUEST['id']) ) page_delete($_REQUEST['id']);

		header("Location: superlinks-mgmt.php");

		break;
	case 'move_page_up':
		if (isset($_REQUEST['id']) && is_numeric($_REQUEST['id']) && isset($_REQUEST['order']) && is_numeric($_REQUEST['order'])) {
			page_move($_REQUEST['id'],$_REQUEST['order'],-1);
		}

		header("Location: superlinks-mgmt.php");

		break;
	case 'move_page_down':
		if (isset($_REQUEST['id']) && is_numeric($_REQUEST['id']) && isset($_REQUEST['order']) && is_numeric($_REQUEST['order'])) {
			page_move($_REQUEST['id'],$_REQUEST['order'],+1);
		}

		header("Location: superlinks-mgmt.php");

		break;
	case 'perms_add_user':
		if (isset($_REQUEST['pageid']) && is_numeric($_REQUEST['pageid']) && isset($_REQUEST['userid']) && is_numeric($_REQUEST['userid'])) {
			perms_add_user($_REQUEST['pageid'],$_REQUEST['userid']);
			header("Location: superlinks-mgmt.php?action=perms_edit&id=".$_REQUEST['pageid']);
		}

		break;
	case 'perms_delete_user':
		if (isset($_REQUEST['pageid']) && is_numeric($_REQUEST['pageid']) && isset($_REQUEST['userid']) && is_numeric($_REQUEST['userid'])) {
			perms_delete_user($_REQUEST['pageid'],$_REQUEST['userid']);
			header("Location: superlinks-mgmt.php?action=perms_edit&id=".$_REQUEST['pageid']);
		}

		break;
	case 'perms_edit':
		if (isset($_REQUEST['id']) && is_numeric($_REQUEST['id'])) {
			include_once($config["base_path"]."/include/top_header.php");
			perms_list($_REQUEST['id']);
			include_once($config["base_path"]."/include/bottom_footer.php");
		} else {
			print "Something got lost back there.";
		}

		break;
	case 'save':
		if (isset($_REQUEST['id']) && is_numeric($_REQUEST['id'])) {
			$id = $_REQUEST['id'];
			$title = mysql_real_escape_string(form_input_validate($_REQUEST["title"], "title", "", false, 3));
			$style = mysql_real_escape_string($_REQUEST['style']);

			$extendedstyle     = "";
			$disabled          = (strlen($_REQUEST['disabled']) ? 'on':'');
			$consolesection    = mysql_real_escape_string($_REQUEST['consolesection']);
			$consolenewsection = mysql_real_escape_string($_REQUEST['consolenewsection']);
			if ($style=='CONSOLE') {
				$extendedstyle = ($consolesection == '__NEW__' ? $consolenewsection : $consolesection);
				if ($extendedstyle == '') $extendedstyle = "Extra";
			}
			$extendedstyle = mysql_escape_string($extendedstyle);

			$filename = $_REQUEST['filename'];

			// if the filename is a valid URL, don't sanitize it
			if (preg_match('/^((((ht|f)tp(s?))\:\/\/){1}\S+)/i',$filename)) {
				$file = mysql_real_escape_string($filename);
			} else {
				$file = preg_replace("/[^A-Za-z0-9_\.-]/","_",$filename);
				$file = mysql_real_escape_string($file);
			}

			$SQL = "UPDATE superlinks_pages
				SET title='$title',
				style='$style',
				extendedstyle='$extendedstyle',
				disabled='$disabled',
				contentfile='$file'
				WHERE id=$id";

			if (!is_error_message()){
				db_execute($SQL);
				update_tabimage($id,$title);
				raise_message(1);
				header("Location: superlinks-mgmt.php");
				exit;
			}else{
				header("Location: superlinks-mgmt.php?action=edit&id=" . $_REQUEST['id']);
				exit;
			}
		}

		break;
	case 'edit':
		include_once($config["base_path"]."/include/top_header.php");
		if ( isset($_REQUEST['id']) && is_numeric($_REQUEST['id']) ) {
			editpage($_REQUEST['id']);
		}
		include_once($config["base_path"]."/include/bottom_footer.php");

		break;
	case 'addpage_picker':
		include_once($config["base_path"]."/include/top_header.php");
		addpage_picker();
		include_once($config["base_path"]."/include/bottom_footer.php");

		break;
	case 'addpage':
		if (isset($_REQUEST['file'])) {
			$id=add_page($_REQUEST['file']);
			header("Location: superlinks-mgmt.php?action=edit&id=$id");
		} else {
			print "No such file.";
		}

		break;
	// by default, just list the page setup
	default:
		include_once($config["base_path"]."/include/top_header.php");
		pagelist();
		include_once($config["base_path"]."/include/bottom_footer.php");

		break;
}

function form_actions() {
	global $colors, $sl_actions;

	/* if we are to save this form, instead of display it */
	if (isset($_POST["selected_items"])) {
		$selected_items = unserialize(stripslashes($_POST["selected_items"]));

		if ($_POST["drp_action"] == "3") { /* Enable Page */
			for ($i=0;($i<count($selected_items));$i++) {
				/* ================= input validation ================= */
				input_validate_input_number($selected_items[$i]);
				/* ==================================================== */

				db_execute("UPDATE superlinks_pages SET disabled='' WHERE id='" . $selected_items[$i] . "'");
			}
		}elseif ($_POST["drp_action"] == "2") { /* Disable Page */
			for ($i=0;($i<count($selected_items));$i++) {
				/* ================= input validation ================= */
				input_validate_input_number($selected_items[$i]);
				/* ==================================================== */

				db_execute("UPDATE superlinks_pages SET disabled='on' WHERE id='" . $selected_items[$i] . "'");
			}
		}elseif ($_POST["drp_action"] == "1") { /* Delete Page */
			for ($i=0;($i<count($selected_items));$i++) {
				/* ================= input validation ================= */
				input_validate_input_number($selected_items[$i]);
				/* ==================================================== */

				db_execute("DELETE FROM superlinks_pages WHERE id='" . $selected_items[$i] . "'");
			}
		}

		header("Location: superlinks-mgmt.php");
		exit;
	}

	/* setup some variables */
	$page_list = ""; $i = 0;

	/* loop through each of the pages selected on the previous page and get more info about them */
	while (list($var,$val) = each($_POST)) {
		if (preg_match("/^chk_([0-9]+)$/", $var, $matches)) {
			/* ================= input validation ================= */
			input_validate_input_number($matches[1]);
			/* ==================================================== */

			$page_list .= "<li>" . htmlspecialchars(db_fetch_cell("SELECT title FROM superlinks_pages WHERE id=" . $matches[1])) . "</li>";
			$pages[$i] = $matches[1];

			$i++;
		}
	}

	include_once("./include/top_header.php");

	html_start_box("<strong>" . $sl_actions[get_request_var_post("drp_action")] . "</strong>", "60%", $colors["header_panel"], "3", "center", "");

	print "<form action='superlinks-mgmt.php' autocomplete='off' method='post'>\n";

	if (isset($pages) && sizeof($pages)) {
		if ($_POST["drp_action"] == "3") { /* Enable Pages */
			print " <tr>
				<td colspan='2' class='textArea' bgcolor='#" . $colors["form_alternate1"]. "'>
					<p>To Enable the following Page(s), click \"Continue\".</p>
					<ul>" . $page_list . "</ul>
				</td>
			</tr>";

			$save_html = "<input type='button' value='Cancel' onClick='window.history.back()'>&nbsp;<input type='submit' value='Continue' title='Enable Device(s)'>";
		}elseif ($_POST["drp_action"] == "2") { /* Disable Pages */
			print " <tr>
				<td colspan='2' class='textArea' bgcolor='#" . $colors["form_alternate1"]. "'>
					<p>To Disable the following Page(s), click \"Continue\".</p>
					<ul>" . $page_list . "</ul>
				</td>
			</tr>";

			$save_html = "<input type='button' value='Cancel' onClick='window.history.back()'>&nbsp;<input type='submit' value='Continue' title='Disable Device(s)'>";
		}elseif ($_POST["drp_action"] == "1") { /* Delete Pages*/
			print " <tr>
				<td colspan='2' class='textArea' bgcolor='#" . $colors["form_alternate1"]. "'>
					<p>To Delete the following Page(s), click \"Continue\".</p>
					<ul>" . $page_list . "</ul>
				</td>
			</tr>";

			$save_html = "<input type='button' value='Cancel' onClick='window.history.back()'>&nbsp;<input type='submit' value='Continue' title='Change Device(s) SNMP Options'>";
		}
	}else{
		print "<tr><td bgcolor='#" . $colors["form_alternate1"]. "'><span class='textError'>You must select at least one page.</span></td></tr>\n";
		$save_html = "<input type='button' value='Return' onClick='window.history.back()'>";
	}

	print "<tr>
		<td colspan='2' align='right' bgcolor='#eaeaea'>
			<input type='hidden' name='action' value='actions'>
			<input type='hidden' name='selected_items' value='" . (isset($pages) ? serialize($pages) : '') . "'>
			<input type='hidden' name='drp_action' value='" . $_POST["drp_action"] . "'>
			$save_html
		</td>
	</tr>\n";

	html_end_box();

	include_once("./include/bottom_footer.php");
}

function pagelist() {
	global $title, $colors, $item_rows, $config, $reset_multi;
	global $superlinks_tabdir, $sl_actions;

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var_request('rows'));
	input_validate_input_number(get_request_var_request('page'));
	/* ==================================================== */

	/* clean up filter */
	if (isset($_REQUEST['filter'])) {
		$_REQUEST['filter'] = sanitize_search_string(get_request_var_request('filter'));
	}

	/* clean up sort solumn */
	if (isset($_REQUEST['sort_column'])) {
		$_REQUEST['sort_column'] = sanitize_search_string(get_request_var_request('sort_column'));
	}

	/* clean up sort direction */
	if (isset($_REQUEST['sort_direction'])) {
		$_REQUEST['sort_direction'] = sanitize_search_string(get_request_var_request('sort_direction'));
	}

	/* if the user pushed the 'clear' button */
	if (isset($_REQUEST['clear'])) {
		kill_session_var('sess_sl_rows');
		kill_session_var('sess_sl_page');
		kill_session_var('sess_sl_filter');
		kill_session_var('sess_sl_sort_column');
		kill_session_var('sess_sl_sort_direction');

		$_REQUEST['page'] = 1;
		unset($_REQUEST['rows']);
		unset($_REQUEST['filter']);
		unset($_REQUEST['sort_column']);
		unset($_REQUEST['sort_direction']);
	}else{
		/* if any of the settings changed, reset the page number */
		$changed = 0;
		$changed += sl_request_check_changed('rows', 'sess_sl_rows');
		$changed += sl_request_check_changed('filter', 'sess_sl_filter');
		$changed += sl_request_check_changed('sort_column', 'sess_sl_sort_column');
		$changed += sl_request_check_changed('sort_direction', 'sess_sl_sort_direction');
		if ($changed) {
			$_REQUEST['page'] = '1';
		}

		$reset_multi = false;
	}

	/* remember search fields in session vars */
	load_current_session_value('rows', 'sess_sl_rows', '-1');
	load_current_session_value('page', 'sess_sl_current_page', '1');
	load_current_session_value('filter', 'sess_sl_filter', '');
	load_current_session_value('sort_column', 'sess_sl_sort_column', 'sortorder');
	load_current_session_value('sort_direction', 'sess_sl_sort_direction', 'ASC');

	if (!is_writable($superlinks_tabdir)) {
		print "<div align=center style='margin: 10px;width: 60%; border:2px red solid; padding: 5px;'>You need to make sure that the tab_images/ directory is writable by the user that your webserver runs as. SuperLinks cannot create the tab graphics without this change.</div>";
	}

	if (!function_exists('imagepng')) {
		print "<div align=center style='margin: 10px;width: 60%; border:2px red solid; padding: 5px;'>Your mod_php should have the GD module enabled, with PNG support AND Freetype support. SuperLinks cannot create the tab graphics without this change.</div>";
	} elseif (!function_exists('imagettftext')) {
		print "<div align=center style='margin: 10px;width: 60%; border:2px red solid; padding: 5px;'>Your GD php module (and GD library/dll) need to be built with Freetype support. SuperLinks cannot create the tab graphics without this change.</div>";
	}

	?>
	<script type="text/javascript">
	<!--
	function filterChange(objForm) {
		strURL = '?rows=' + objForm.rows.value;
		strURL = strURL + '&filter=' + objForm.filter.value;
		document.location = strURL;
	}
	-->
	</script>
	<?php

	html_start_box("<strong>SuperLink Pages</strong>", "100%", $colors["header"], "3", "center", "superlinks-mgmt.php?action=addpage_picker");
	?>
	<tr bgcolor='<?php print $colors["panel"];?>' class='noprint'>
		<td class='noprint'>
			<form name='sl' action='superlinks-mgmt.php' method='post'>
			<table cellpadding='0' cellspacing='0'>
				<tr class='noprint'>
					<td width='1'>
						&nbsp;Search:&nbsp;
					</td>
					<td width='40'>
						<input type='textbox' name='filter' value='<?php print $_REQUEST['filter'];?>' size='40'>
					</td>
					<td width='1'>
						&nbsp;Rows:&nbsp;
					</td>
					<td width='1'>
						<select name='rows' onChange='filterChange(document.sl)'>
							<option value=-1>All</option>
							<?php
							foreach ($item_rows as $key => $row) {
								echo "<option value='" . $key . "'" . (isset($_REQUEST['rows']) && $key == $_REQUEST['rows'] ? ' selected' : '') . '>' . $row . '</option>';
							}
							?>
						</select>
					</td>
					<td width='1' style='white-space:nowrap;'>
						&nbsp;<input type='submit' name='go' value='Go' title='Apply Filter'>
					</td>
					<td width='1' style='white-space:nowrap;'>
						&nbsp;<input type='submit' name='clear' value='Clear' title='Reset filters'>
					</td>
				</tr>
			</table>
			</form>
		</td>
	</tr>
	<?php

	define('MAX_DISPLAY_PAGES', 21);

	html_end_box();

	$query = db_fetch_assoc("SELECT id, username FROM user_auth ORDER BY username");
	$users[0] = 'Anyone';

	foreach ($query as $user) {
		$users[$user['id']] = $user['username'];
	}

	$style_translate = array(
		"CONSOLE" => "Console Menu",
		"TAB" => "Top Tab",
		"FRONT" => "Bottom of Front Page",
		"FRONTTOP" => "Top of Front Page",
		"LOGINBEFOR" => "Before Login Box",
		"LOGINAFTER" => "After Login Box"
	);

	if ($_REQUEST['rows'] == -1) {
		$num_rows = 20;
	}else{
		$num_rows = $_REQUEST['rows'];
	}

	if (strlen($_REQUEST['filter'])) {
		$sql_where = " WHERE title LIKE '%" . $_REQUEST['filter'] . "%' OR contentfile LIKE '%" . $_REQUEST['filter'] . "%'";
	}else{
		$sql_where = "";
	}

	$limit = ' LIMIT ' . ($num_rows*($_REQUEST['page']-1)) . ", $num_rows";
	$sort  = ' ORDER BY ' . $_REQUEST['sort_column'] . ' ' . ($_REQUEST['sort_column'] == 'sortorder' ? 'ASC':$_REQUEST['sort_direction']);

	$queryrows   = db_fetch_assoc("SELECT *
		FROM superlinks_pages
		$sql_where
		$sort
		$limit");

	$total_rows = db_fetch_cell("SELECT COUNT(*)
		FROM superlinks_pages");

	$url_page_select = get_page_list($_REQUEST['page'], MAX_DISPLAY_PAGES, $num_rows, $total_rows, 'superlinks-mgmt.php?');

    html_start_box('', '100%', $colors['header'], '4', 'center', '');
	if ($total_rows) {
		$nav = "<tr bgcolor='#" . $colors['header'] . "'>
			<td colspan='12'>
				<table width='100%' cellspacing='0' cellpadding='0' border='0'>
					<tr>
						<td align='left' class='textHeaderDark'>
							<strong>&lt;&lt; "; if ($_REQUEST["page"] > 1) { $nav .= "<a class='linkOverDark' href='" . htmlspecialchars("superlinks-mgmt.php?page=" . ($_REQUEST["page"]-1)) . "'>"; } $nav .= "Previous"; if ($_REQUEST["page"] > 1) { $nav .= "</a>"; } $nav .= "</strong>
						</td>
						<td align='center' class='textHeaderDark'>
							Showing Rows " . (($num_rows*($_REQUEST["page"]-1))+1) . " to " . ((($total_rows < $num_rows) || ($total_rows < ($num_rows*$_REQUEST["page"]))) ? $total_rows : ($num_rows*$_REQUEST["page"])) . " of $total_rows [$url_page_select]
						</td>
						<td align='right' class='textHeaderDark'>
							<strong>"; if (($_REQUEST["page"] * $num_rows) < $total_rows) { $nav .= "<a class='linkOverDark' href='" . htmlspecialchars("superlinks-mgmt.php?page=" . ($_REQUEST["page"]+1)) . "'>"; } $nav .= "Next"; if (($_REQUEST["page"] * $num_rows) < $total_rows) { $nav .= "</a>"; } $nav .= " &gt;&gt;</strong>
						</td>
					</tr>
				</table>
			</td>
		</tr>\n";
	}else{
		$nav = "<tr bgcolor='#" . $colors['header'] . "'>
			<td colspan='12'>
				<table width='100%' cellspacing='0' cellpadding='0' border='0'>
					<tr>
						<td align='center' class='textHeaderDark'>
							No Rows Found
						</td>
					</tr>
				</table>
			</td>
		</tr>\n";
	}

	print $nav;

	$display_text = array(
		"nosort0"     => array('Actions', ''),
		"contentfile" => array('Page', 'ASC'),
		"title"       => array('Title', 'ASC'),
		"style"       => array('Style', 'ASC'),
		"disabled"    => array('Enabled', 'ASC'),
		"sortorder"   => array('Sort Order', 'ASC'),
		"nosort2"     => array('Accessible By', ''));

	html_header_sort_checkbox($display_text, $_REQUEST['sort_column'], $_REQUEST['sort_direction']);

	$i = 0;
	$previous_id = -2;
	if (sizeof($queryrows)) {
		foreach ($queryrows as $map) {
			form_alternate_row_color($colors["alternate"], $colors["light"], $i, 'line' . $map["id"]);

			$actions = '<a href="' . htmlspecialchars('superlinks-mgmt.php?action=edit&id='.$map['id']) . '" title="Edit Page"><img border="0" src="images/application_edit.png"></a>';

			if ($map['disabled'] == '') {
				$actions .= '<a href="' . htmlspecialchars('superlinks.php?id='.$map['id']) . '" title="View Page"><img border="0" src="images/view_page.png"></a>';
			}

			form_selectable_cell($actions, $map["id"], '50');
			form_selectable_cell(htmlspecialchars($map['contentfile']), $map["id"]);
			form_selectable_cell(htmlspecialchars($map['title']), $map["id"]);
			form_selectable_cell(htmlspecialchars($style_translate[$map['style']]) . ($map['style'] == 'CONSOLE' ? ' (' . $map['extendedstyle'].')':''), $map["id"]);
			form_selectable_cell(($map['disabled'] == '' ? 'Yes':'No'), $map["id"]);

			if ($_REQUEST["sort_column"] == 'sortorder') {
				if ($i != 0) {
					$sort = '<a href="' . htmlspecialchars('superlinks-mgmt.php?action=move_page_up&order='.$map['sortorder'].'&id='.$map['id']) . '"><img src="../../images/move_up.gif" width="14" height="10" border="0" alt="Move Page Up"></a>';
				}else{
					$sort = '<img src="../../images/view_none.gif" alt="" width="14" height="10" border="0">';
				}

				if ($i == sizeof($queryrows)-1) {
					$sort .= '<img src="../../images/view_none.gif" alt="" width="14" height="10" border="0">';
				}else{
					$sort .= '<a href="' . htmlspecialchars('superlinks-mgmt.php?action=move_page_down&order='.$map['sortorder'].'&id='.$map['id']) . '"><img src="../../images/move_down.gif" width="14" height="10" border="0" alt="Move Page Down"></a>';
				}

				form_selectable_cell($sort, $map['id']);
			}else{
				form_selectable_cell('<img src="../../images/view_none.gif" alt="" width="14" height="10" border="0"><img src="../../images/view_none.gif" alt="" width="14" height="10" border="0">', $map['id']);
			}

			$UserSQL  = 'SELECT * FROM superlinks_auth WHERE pageid=' . $map['id'] . ' ORDER BY userid';
			$userlist = db_fetch_assoc($UserSQL);

			$mapusers = array();
			if (sizeof($userlist)) {
			foreach ($userlist as $user) {
				if (array_key_exists($user['userid'], $users)) {
					$mapusers[] = $users[$user['userid']];
				}else{
					/* remove deleted users */
					db_execute('DELETE FROM superlinks_auth WHERE pageid=' . $map['id'] . ' AND userid=' . $user['userid']);
				}
			}
			}

			if (!sizeof($mapusers)) {
				$pusers = "(no users)";
			} else {
				$pusers = implode(", ", $mapusers);
			}

			form_selectable_cell('<a class="linkEditMain" href="' . htmlspecialchars('superlinks-mgmt.php?action=perms_edit&id='.$map['id']) . '">' .  $pusers . '</a>', $map['id']);
			form_checkbox_cell($map['title'], $map['id']);
			form_end_row();

			$i++;
		}

		print $nav;
	}else{
		print "<tr><td><em>No Pages Configured</em></td></tr>\n";
	}

	html_end_box(false);

	draw_actions_dropdown($sl_actions);
}

function sl_request_check_changed($request, $session) {
	if ((isset($_REQUEST[$request])) && (isset($_SESSION[$session]))) {
		if ($_REQUEST[$request] != $_SESSION[$session]) {
			return 1;
		}
	}
}

function addpage_picker() {
	global $colors;
	global $superlinks_contentdir;

	$loaded=array();
	// find out what maps are already in the database, so we can skip those
	$queryrows = db_fetch_assoc("SELECT * FROM superlinks_pages");
	if ( is_array($queryrows) ) {
		foreach ($queryrows as $map) {
			$loaded[]=$map['contentfile'];
		}
	}
	$loaded[]='index.php';

	html_start_box("<strong>Available SuperLinks Content Files</strong>", "78%", $colors["header"], "2", "center", "");

	if (is_dir($superlinks_contentdir)) {
		$n=0;
		$dh = opendir($superlinks_contentdir);
		if ($dh) {
			$i = 0; $skipped = 0;
			html_header(array("Content File", ""),2);

			while($file = readdir($dh)) {
				$realfile = $superlinks_contentdir.'/'.$file;
				if (is_file($realfile) && ! in_array($file,$loaded) ) {
					if (in_array($file,$loaded)) {
						$skipped++;
					} else {
						$titles[$file] = 'xx';
						$i++;
					}
				}
			}
			closedir($dh);

			/* add generic http content first */
			form_alternate_row_color($colors["alternate"],$colors["light"],$i);
			print '<td>Generic Hypertext Document URL</td>';
			print '<td><a href="' . htmlspecialchars('superlinks-mgmt.php?action=addpage&file=generic') . '" title="Add the content file">Add</a></td>';
			print '</tr>';
			$i++;

			if ($i>0) {
				ksort($titles);

				$i=0;

				foreach ($titles as $file=>$title) {
					$title = $titles[$file];
					form_alternate_row_color($colors["alternate"],$colors["light"],$i);
					print '<td>'.htmlspecialchars($file).'</td>';
					print '<td><a href="' . htmlspecialchars('superlinks-mgmt.php?action=addpage&file=' . $file) . '" title="Add the content file">Add</a></td>';
					print '</tr>';
					$i++;
				}
			}

			if (($i + $skipped) == 0) {
				print "<tr><td>No unused files were found in the content directory.</td></tr>";
			}

			if (($i == 0) && $skipped>0) {
				print "<tr><td>($skipped files weren't shown because they are already in the database)</td></tr>";
			}
		} else {
			print "<tr><td>Can't open $superlinks_contentdir to read - you should set it to be readable by the webserver.</td></tr>";
		}
	} else {
		print "<tr><td>There is no directory named $superlinks_contentdir - you will need to create it, and set it to be readable by the webserver. If you want to upload configuration files from inside Cacti, then it should be <i>writable</i> by the webserver too.</td></tr>";
	}
	html_end_box();
}

function page_delete($id) {
	$SQL = "DELETE FROM superlinks_pages WHERE id=".$id;
	db_execute($SQL);

	$SQL = "DELETE FROM superlinks_auth WHERE pageid=".$id;
	db_execute($SQL);

	page_resort();
}

function perms_add_user($pageid,$userid) {
	$SQL = "INSERT INTO superlinks_auth (pageid,userid) VALUES ($pageid,$userid)";
	db_execute($SQL);
}

function perms_delete_user($pageid,$userid) {
	$SQL = "DELETE FROM superlinks_auth WHERE pageid=$pageid AND userid=$userid";
	db_execute($SQL);
}

function perms_list($id) {
	global $colors;

	$title_sql = "SELECT title FROM superlinks_pages WHERE id=$id";
	$results = db_fetch_assoc($title_sql);
	$title = $results[0]['title'];

	$auth_sql = "SELECT * FROM superlinks_auth WHERE pageid=$id ORDER BY userid";

	$users = array_rekey(db_fetch_assoc("SELECT
		id,username,full_name,enabled
		FROM user_auth
		ORDER BY username"), "id", array("username", "full_name", "enabled"));

	$users[0]     = array("username" => "anyone", "full_name" => "All Users", "enabled" => "on");
	$auth_results = db_fetch_assoc($auth_sql);
	$mapusers     = array();
	$mapuserids   = array();
	foreach ($auth_results as $user) {
		if (isset($users[$user['userid']])) {
			$mapusers[] = $users[$user['userid']];
			$mapuserids[] = $user['userid'];
		}
	}

	$userselect="";
	foreach ($users as $uid => $attribs) {
		if (!in_array($uid,$mapuserids)) {
			$userselect .= "<option value=\"$uid\">" . $attribs["username"] . "</option>\n";
		}
	}

	html_start_box("<strong>Edit permissions for Page $id: $title</strong>", "100%", $colors["header"], "2", "center", "");
	html_header(array("Username", "Full Name", "Enabled", ""));

	$n = 0;
	foreach($mapuserids as $user) {
		form_alternate_row_color($colors["alternate"],$colors["light"],$n);
		print "<td width='20%'>".$users[$user]["username"]."</td>";
		print "<td width='50%'>".$users[$user]["full_name"]."</td>";
		print "<td width='20%'>".($users[$user]["enabled"] == "on" ? "Yes":"No")."</td>";
		print '<td align="right"><a href="' . htmlspecialchars('superlinks-mgmt.php?action=perms_delete_user&pageid='.$id.'&userid='.$user) . '"><img src="../../images/delete_icon.gif" width="10" height="10" border="0" alt="Remove permissions for this user to see this Page"></a></td>';
		print "</tr>";
		$n++;
	}

	if ($n==0) {
		print "<tr><td><em><strong>nobody</strong> can see this map</em></td></tr>";
	}
	html_end_box();

	html_start_box("", "100%", $colors["header"], "3", "center", "");
	print "<tr>";
	if ($userselect == '') {
		print "<td><em>There aren't any users left to add!</em></td></tr>";
	} else {
		print "<td><form action=\"\">Allow <input type=\"hidden\" name=\"action\" value=\"perms_add_user\"><input type=\"hidden\" name=\"pageid\" value=\"$id\"><select name=\"userid\">";
		print $userselect;
		print "</select> to see this Page <input type=\"submit\" value=\"Update\"></form></td>";
		print "</tr>";
	}
	html_end_box();
}

function page_resort() {
	$list = db_fetch_assoc("SELECT * FROM superlinks_pages ORDER BY sortorder;");
	$i = 1;
	foreach ($list as $map) {
		$sql[] = "UPDATE superlinks_pages SET sortorder=$i WHERE id = ".$map['id'];
		$i++;
	}

	if (!empty($sql)) {
		for ($a = 0; $a < count($sql); $a++) {
			$result = db_execute($sql[$a]);
		}
	}
}

function page_move($pageid,$junk,$direction) {
	$source = db_fetch_assoc("SELECT * FROM superlinks_pages WHERE id=$pageid");
	$oldorder = $source[0]['sortorder'];

	$neworder = $oldorder + $direction;
	$target = db_fetch_assoc("SELECT * FROM superlinks_pages WHERE sortorder = $neworder");

	if (!empty($target[0]['id'])) {
		$otherid = $target[0]['id'];
		// move $pageid in direction $direction
		$sql[] = "UPDATE superlinks_pages SET sortorder=$neworder WHERE id=$pageid";
		// then find the other one with the same sortorder and move that in the opposite direction
		$sql[] = "UPDATE superlinks_pages SET sortorder=$oldorder WHERE id=$otherid";
	}

	if (!empty($sql)) {
		for ($a = 0; $a < count($sql); $a++) {
			$result = db_execute($sql[$a]);
		}
	}
}

function add_page($file, $title='NewTab', $style='TAB') {
	global $superlinks_contentdir;
	global $superlinks_tabdir;
	global $colors;

	chdir($superlinks_contentdir);

	$path_parts = pathinfo($file);
	$file_dir   = realpath($path_parts['dirname']);
	if (($file_dir != $superlinks_contentdir) && ($file != "generic")) {
		// someone is trying to read arbitrary files?
		print "<h3>Path mismatch</h3>";
	} else {
		if ($file == 'generic') {
			$file = "http://www.cacti.net/";
		} else {
			$realfile = $superlinks_contentdir.DIRECTORY_SEPARATOR.$file;
		}

		$file   = mysql_real_escape_string($file);
		$etitle = mysql_real_escape_string($title);
		$SQL    = "INSERT INTO superlinks_pages (contentfile, title, style, disabled) VALUES ('$file', '$etitle', '$style', 'on')";
		db_execute($SQL);

		// add auth for 'admin'
		$last_id = mysql_insert_id();
		$myuid   = (int)$_SESSION["sess_user_id"];
		$SQL     = "INSERT INTO superlinks_auth (pageid, userid) VALUES ($last_id, $myuid)";
		db_execute($SQL);

		update_tabimage($last_id, $title);

		page_resort();

		return $last_id;
	}

	return(-1);
}

function update_tabimage($id, $title) {
	global $superlinks_tabdir;

	if (is_writable($superlinks_tabdir)) {
		$oldsuffix = "";
		$current = db_fetch_assoc("SELECT * FROM superlinks_pages WHERE id=$id");
		$old1 = $superlinks_tabdir.DIRECTORY_SEPARATOR."tab_".$current[0]['imagecache'];
		$old2 = $superlinks_tabdir.DIRECTORY_SEPARATOR."red_".$current[0]['imagecache'];
		$old3 = $superlinks_tabdir.DIRECTORY_SEPARATOR."s_tab_".$current[0]['imagecache'];
		$old4 = $superlinks_tabdir.DIRECTORY_SEPARATOR."s_red_".$current[0]['imagecache'];
		if (file_exists($old1)) { unlink($old1); }
		if (file_exists($old2)) { unlink($old2); }
		if (file_exists($old3)) { unlink($old3); }
		if (file_exists($old4)) { unlink($old4); }

		$tabfilesuffix = $id."_".crc32($title)."_".session_id().".gif";

		$tab=tabimage($title,"blank-tab-blue.gif");
		$tabfile = "tab_".$tabfilesuffix;
		$tabfilefull = $superlinks_tabdir.DIRECTORY_SEPARATOR.$tabfile;
		imagegif ($tab,$tabfilefull);
		imagedestroy($tab);

		$tab=tabimage($title,"blank-tab-red.gif");
		$tabfile = "red_".$tabfilesuffix;
		$tabfilefull = $superlinks_tabdir.DIRECTORY_SEPARATOR.$tabfile;
		imagegif ($tab,$tabfilefull);
		imagedestroy($tab);

		$tab=tabimage($title,"blank-tab-red-small.gif",1);
		$tabfile = "s_red_".$tabfilesuffix;
		$tabfilefull = $superlinks_tabdir.DIRECTORY_SEPARATOR.$tabfile;
		imagegif ($tab,$tabfilefull);
		imagedestroy($tab);

		$tab=tabimage($title,"blank-tab-blue-small.gif",1);
		$tabfile = "s_tab_".$tabfilesuffix;
		$tabfilefull = $superlinks_tabdir.DIRECTORY_SEPARATOR.$tabfile;
		imagegif ($tab,$tabfilefull);
		imagedestroy($tab);


		$SQL = "UPDATE superlinks_pages SET imagecache='$tabfilesuffix' WHERE id=$id";
		db_execute($SQL);
	}

}

function editpage($id) {
	global $colors;

	$SQL_sections = "SELECT extendedstyle
		FROM superlinks_pages
		WHERE style='CONSOLE'
		GROUP BY extendedstyle
		ORDER BY extendedstyle";

	$sections = db_fetch_assoc($SQL_sections);

	$sec_ar = array();

	$sec_ar['Extra'] = "Extra (the default)";
	foreach ($sections as $sec) {
		if ($sec['extendedstyle'] !='') {
			$sec_ar[$sec['extendedstyle']] = $sec['extendedstyle'];
		}
	}
	$sec_ar['__NEW__'] = "--> New Name (enter below)";

	$SQL = 'SELECT * FROM superlinks_pages WHERE id='.$id;
	$data = db_fetch_assoc($SQL);
	$values_ar = array();

	$field_ar = array(
		"id" => array(
			"friendly_name" => "Style",
			"method" => "hidden",
			"value" => $id
		),
		"style" => array(
			"friendly_name" => "Style",
			"method" => "drop_array",
			"array" => array(
				"TAB" => "Top Tab",
				"CONSOLE" => "Console Menu",
				"FRONT" => "Front Page (below '3 steps' intro)",
				"FRONTTOP" => "Front Page (above '3 steps' intro)",
				"LOGINBEFOR" => "Login Screen (above)",
				"LOGINAFTER" => "Login Screen (below)"
			),
			"description" => "Where should this page appear?",
			"value" => $data[0]['style']
		),
		"consolesection" => array(
			"friendly_name" => "Console Menu Section",
			"method" => "drop_array",
			"array" => $sec_ar,
			"description" => "Under which Console heading should this item appear?
				(All SuperLinks menus will appear between Configuration and Utilities)",
			"value" => $data[0]['extendedstyle']
		),
		"consolenewsection" => array(
			"friendly_name" => "--> New Console Section",
			"method" => "textbox",
			"max_length" => 20,
			"description" => "If you don't like any of the choices above, type a new title in here.",
			"value" => $data[0]['extendedstyle']
		),
		"title" => array(
			"friendly_name" => "Tab/Menu Name",
			"method" => "textbox",
			"max_length" => 20,
			"description" => "The text that will appear in the tab or menu.",
			"value" => $data[0]['title']
		),
		"filename" => array(
			"friendly_name" => "Content File/URL",
			"method" => "textbox",
			"max_length" => 512,
			"description" => "The file that contains the content for this page. This
				can be a file in the content/ directory or a valid URL.",
			"value" => $data[0]['contentfile']
		),
		"disabled" => array(
			"friendly_name" => "Do You Want this Page Enabled",
			"method" => "drop_array",
			"array" => array('' => 'Yes', 'on' => 'No'),
			"description" => "If you wish this page to be viewable immediately, select 'Yes', otherwise select 'No'",
			"value" => $data[0]['disabled']
		),
	);

	html_start_box("<strong>SuperLinks</strong> [edit: ".$data[0]['title']."]", "100%", $colors["header"], "3", "center", "");
	draw_edit_form(array("config"=>$values_ar, "fields"=>$field_ar) );
	html_end_box();

	form_save_button("superlinks-mgmt.php", "save");

    ?>
	<script type="text/javascript">
	$().ready(function() {
		// hide and show the extra console fields when necessary
		$('#style').change(function() {
			if ($('#style').val() != 'CONSOLE') {
				$('#row_consolesection').hide();
				$('#row_consolenewsection').hide();
			} else {
				$('#row_consolesection').show();
				$('#row_consolenewsection').show();
			}
		}).change();

		// if you change the section, make the 'new' textbox reflect it
		// if you change it to 'new', then clear the textbox, and jump to it
		$('#consolesection').change( function() {
			if ($(this).val() == '__NEW__') {
				$('#consolenewsection').val("").focus();
			} else {
				$('#consolenewsection').val($(this).val());
			}
		});

		// if you just start typing in there, then change the combo to "New"
		$('#consolenewsection').change( function() {
			$('#consolesection').val("__NEW__");
		});
	});
	</script>
	<?php
}

// vim:ts=4:sw=4:
?>
