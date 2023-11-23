<?php

function plugin_superlinks_version() {
	return(superlinks_version());
}

function plugin_superlinks_uninstall() {
	// doesn't really do anything. Here to remind me.

	// not sure what it should really do for this plugin...
}

function plugin_superlinks_upgrade() {
	global $config;

	$files = array('index.php', 'plugins.php');
	if (isset($_SERVER['PHP_SELF']) && !in_array(basename($_SERVER['PHP_SELF']), $files)) {
		return;
	}

	$current = plugin_superlinks_version();
	$current = $current['version'];
	$old     = db_fetch_row("SELECT * FROM plugin_config WHERE directory='superlinks'");
	if (sizeof($old) && $current != $old["version"] || ($current < 1.2) || !sizeof($old)) {
		db_execute("ALTER TABLE superlinks_pages ADD COLUMN disabled CHAR(2) NOT NULL default '' AFTER sortorder");

		/* update the plugin information */
		$info = plugin_superlinks_version();
		$id   = db_fetch_cell("SELECT id FROM plugin_config WHERE directory='superlinks'");
		db_execute("UPDATE plugin_config
			SET name='" . $info["longname"] . "',
			author='"   . $info["author"]   . "',
			webpage='"  . $info["homepage"] . "',
			version='"  . $info["version"]  . "'
			WHERE id='$id'");
	}
}

function plugin_superlinks_check_config() {
	// doesn't really do anything either because it's not implemented yet in PIA. Would be handy if it was.

	if (!function_exists("imagecreate")) return FALSE;
	if (!function_exists("preg_match")) return FALSE;
	if (!function_exists("imagettfbbox")) return FALSE;
	if (!function_exists("imagecreatefromgif")) return FALSE;
	if (!function_exists("imagegif")) return FALSE;

	return TRUE;
}


function plugin_superlinks_install () {
	api_plugin_register_hook('superlinks', 'config_insert',         'superlinks_config_insert',        'setup.php');
	api_plugin_register_hook('superlinks', 'config_arrays',         'superlinks_config_arrays',        'setup.php');
	api_plugin_register_hook('superlinks', 'top_header_tabs',       'superlinks_show_tab',             'setup.php');
	api_plugin_register_hook('superlinks', 'top_graph_header_tabs', 'superlinks_show_tab',             'setup.php');
	api_plugin_register_hook('superlinks', 'config_settings',       'superlinks_config_settings',      'setup.php');
	api_plugin_register_hook('superlinks', 'draw_navigation_text',  'superlinks_draw_navigation_text', 'setup.php');
	api_plugin_register_hook('superlinks', 'console_before',        'superlinks_console_before',       'setup.php');
	api_plugin_register_hook('superlinks', 'console_after',         'superlinks_console_after',        'setup.php');
	api_plugin_register_hook('superlinks', 'login_after',           'superlinks_login_after',          'setup.php');
	api_plugin_register_hook('superlinks', 'login_before',          'superlinks_login_before',         'setup.php');
	api_plugin_register_hook('superlinks', 'top_graph_refresh',     'superlinks_top_graph_refresh',    'setup.php');
	api_plugin_register_hook('superlinks', 'cacti_image',           'superlinks_cacti_image',          'setup.php');

	api_plugin_register_hook('superlinks', 'page_head',             'superlinks_page_head',            'setup.php');

	if (superlinks_version_check()) {
		api_plugin_register_hook('superlinks', 'page_title',            'superlinks_page_title',           'setup.php');
	}

	api_plugin_register_hook('superlinks', 'page_bottom',           'superlinks_page_bottom',          'setup.php');

	superlinks_setup_table ();
}

function superlinks_version_check() {
	$version = db_fetch_cell("SELECT cacti FROM version");

	$point = substr($version,-1);
	$major = substr($version,0,-1);

	if ($major == "0.8.7" && ord($point) <= ord("e")) {
		return true;
	}else{
		return false;
	}
}

function superlinks_page_head() {
	global $config;

	print "<script type='text/javascript' src='".$config['url_path'] ."/plugins/superlinks/jquery-latest.min.js'></script>";

	if (read_config_option("superlinks_tabstyle") >= 1) {
		?>
		<script type='text/javascript'>
		function shrinker(root, that) {
			var oldsrc = $(that).attr('src');
			var alt = $(that).attr('alt');

			oldsrc = oldsrc.replace(root,'');

			var newsrc = root + '/plugins/superlinks/tabgen.php?alt='+ alt + '&file=' + oldsrc + "&root=" + root;
			// $(that).css({'border': 'solid red 1px'});

			var w = parseInt($(that).css('width'),10);
			var h = parseInt($(that).css('height'),10);

			// some things may have shrunk themselves.
			// leave them to it.
			if (h != 25) {
				w = w * (60/88);
				h = h * (60/88);

				// this is only correct for normal tabs
				$(that).css({'height':h+'px', 'width':w+'px'});

				<?php if (read_config_option("superlinks_tabstyle") == 2) {?>
				$(that).attr('src',newsrc);
				<?php }?>
			}
		}

		$().ready( function () {
			$('#tabs').css('visibility','hidden');
			$('#gtabs').css('visibility','hidden');
			var root = '<?php echo $config['url_path'];?>';

			// if only there was an id for the tab container or something...
			$('html > body > table:first > tbody > tr > td > table:first > tbody > tr >  td >  a > img').each( function() {
				shrinker(root, this);
			}).parent().parent().parent().parent().parent().parent().parent().css({'height':'25px'});

			// in 0.8.7b this image is an image, not a background, so it needs to be shrunk too
			$("img[src$='cacti_backdrop.gif']").each(function() { shrinker(root, this); });
			$("img[src$='cacti_backdrop2.gif']").each(function() { shrinker(root, this); });
			$('#tabs').css('visibility','visible');
			$('#gtabs').css('visibility','visible');
		});

		</script>
		<?php
	}
}

function superlinks_config_insert() {
	global $superlinks_nav;

	$superlinks_nav["superlinks.php:"] = array("title" => "SuperLinks", "mapping" => "", "url" => "superlinks.php", "level" => "1");
	$superlinks_nav["superlinks-mgmt.php:"] = array("title" => "SuperLinks Admin", "mapping" => "", "url" => "superlinks-mgmt.php", "level" => "1");
	$superlinks_nav["superlinks-mgmt.php:actions"] = array("title" => "SuperLinks Admin", "mapping" => "", "url" => "superlinks-mgmt.php", "level" => "1");
	$superlinks_nav["superlinks-mgmt.php:addpage_picker"] = array("title" => "SuperLinks Admin", "mapping" => "", "url" => "superlinks-mgmt.php", "level" => "1");
	$superlinks_nav["superlinks-mgmt.php:edit"] = array("title" => "SuperLinks Admin", "mapping" => "", "url" => "superlinks-mgmt.php", "level" => "1");
	$superlinks_nav["superlinks-mgmt.php:perms_edit"] = array("title" => "SuperLinks Admin", "mapping" => "", "url" => "superlinks-mgmt.php", "level" => "1");
}

function superlinks_page_title($in) {
	global $config;

	$out = $in;
	$url = $_SERVER['REQUEST_URI'];

	if (preg_match('#/plugins/superlinks/superlinks\-mgmt\.php#', $url)) {
		$out .= " - SuperLinks - Page Management";
	}

	if (preg_match('#/plugins/superlinks/superlinks.php\?id=(\d+)#',$url ,$matches)) {
		$pagetitle = db_fetch_cell(sprintf("SELECT title FROM superlinks_pages WHERE id=%d", $matches[1]));
		if (isset($pagetitle)) {
			$out .= " - ".$pagetitle;
		}
	}

	return ($out);
}

function superlinks_cacti_image($im) {
	$hidelogo = read_config_option("superlinks_hidelogo",TRUE);

	if (intval($hidelogo)==0) {
		return $im;
	} else {
		return('');
	}
}

function superlinks_graph_refresh($current) {
	if (preg_match('/superlinks.php\?id=(\d+)/',$_SERVER['REQUEST_URI'] ,$matches)) return '';

	return $current;
}

function superlinks_config_settings () {
	global $tabs, $settings;

	$tabs["misc"] = "Misc";

	$temp = array(
		"superlinks_header" => array(
			"friendly_name" => "SuperLinks",
			"method" => "spacer",
		),
		"superlinks_tabstyle" => array(
			"friendly_name" => "Tab style",
			"description" => "Which size tabs to use?",
			"method" => "drop_array",
			"array" => array(0 => "Regular", 1 => "Smaller", 2 => "Smaller (forced)")
		),
		"superlinks_hidelogo" => array(
			"friendly_name" => "Hide login logo",
			"description" => "Hide the Cacti texture on the login screen?",
			"method" => "drop_array",
			"array" => array(0 => "No", 1 => "Yes")
		),
		"superlinks_hideconsole" => array(
			"friendly_name" => "Hide console message",
			"description" => "Try to hide the Cacti 'starting points' on the initial screen?",
			"method" => "drop_array",
			"default" => 0,
			"array" => array(0 => "No", 1 => "Yes")
		),
		"superlinks_hrs" => array(
			"friendly_name" => "Add Horizontal Rules on Console Page",
			"description" => "When adding Main Page content items, should that content be separated by Horizontal Rules?",
			"method" => "drop_array",
			"default" => 0,
			"array" => array(0 => "No", 1 => "Yes")
		)
	);

	if (isset($settings["misc"])) {
		$settings["misc"] = array_merge($settings["misc"], $temp);
	} else {
		$settings["misc"]=$temp;
	}

	plugin_superlinks_upgrade();
}

function superlinks_common_inpage($type, $add_heading=false, $add_divs=true) {
	global $config;

	static $i = 0;

	$add_hr = read_config_option("superlinks_hrs");

	$userid = 0;

	if (isset($_SESSION['sess_user_id'])) {
		$userid = $_SESSION["sess_user_id"];
	}

	$queryrows = db_fetch_assoc("SELECT DISTINCT id,title,contentfile
		FROM superlinks_pages, superlinks_auth
		WHERE superlinks_pages.id=superlinks_auth.pageid
		AND style='$type'
		AND (userid=$userid OR userid=0)
		AND disabled=''
		ORDER BY sortorder, id");

	if (sizeof($queryrows)) {
	foreach ($queryrows as $page) {
		if (preg_match('/^((((ht|f)tp(s?))\:\/\/){1}\S+)/i',$page['contentfile'])) {
			print '<iframe id="slcontent" src="' . $page['contentfile'] . '" frameborder="0"></iframe>';

			if ($add_divs) print '</div>';
		} else {
			$my_file = $config["base_path"] . "/plugins/superlinks/content/".$page['contentfile'];

			if ((!read_config_option("superlinks_hideconsole") || $i == 0) && $add_hr) print "<hr>";

			if ($add_heading && strlen($page['title'])) print "<h3>".$page['title']."</h3>";

			if ($add_divs) print '<div>';

			if (file_exists($my_file)) {
				@include_once($my_file);
			} else {
				print '<h1>The File Does not appear to exist!!</h1>';
			}

			if ($add_divs) print '</div>';

			if ($add_hr) print "<hr>";

			$i++;
		}
	}
	}
}

function superlinks_login_before() {
	superlinks_common_inpage("LOGINBEFOR",0,0);
}

function superlinks_login_after() {
	superlinks_common_inpage("LOGINAFTER",0,0);
}

function superlinks_page_bottom() {
	?>
	<script type='text/javascript'>
	$().ready(function() {
		resizeWindow();
		$(window).resize(function() {
			resizeWindow();
		});
	});

	function resizeWindow() {
		coords = $('#slcontent').position();
		height = $(window).height();
		loc = slFindPos(document.getElementById('slcontent'));
		$('#slcontent').css('height',height-loc[1]-5).css('width', '100%');
	}

	function slFindPos(obj) {
		var curleft = curtop = 0;

		if (obj && obj.offsetParent) {
			curleft = obj.offsetLeft;
			curtop  = obj.offsetTop;

			while (obj = obj.offsetParent) {
				curleft += obj.offsetLeft;
				curtop  += obj.offsetTop;
			}
		}

		return [curleft,curtop];
	}
	</script>
	<?php
}

function superlinks_console_before() {
	superlinks_common_inpage("FRONTTOP",1);
	$hidedefaultconsole = read_config_option("superlinks_hideconsole",TRUE);
	if ($hidedefaultconsole) print "<div style='display: none'>";
}

function superlinks_console_after() {
	$hidedefaultconsole = read_config_option("superlinks_hideconsole",TRUE);
	if ($hidedefaultconsole) print "</div>";
	superlinks_common_inpage("FRONT",1);
}

function superlinks_config_arrays () {
	global $user_auth_realms, $user_auth_realm_filenames, $menu;
	global $cnn_id;
	global $config, $database_default;
	global $database_hostname, $database_username, $database_password, $database_default, $database_type, $database_port;

	$realm_id2 = 0;

	if (function_exists('api_plugin_register_realm')) {
		api_plugin_register_realm('superlinks', 'superlinks.php', 'Plugin -> SuperLinks: View Pages', 1);
		api_plugin_register_realm('superlinks', 'superlinks-mgmt.php', 'Plugin -> SuperLinks: Manage Pages', 1);
	} else {
		$user_auth_realms[141]='Plugin -> SuperLinks: View Pages';
		$user_auth_realm_filenames['superlinks.php'] = 141;

		$user_auth_realms[142]='Plugin -> SuperLinks: Manage Pages';
		$user_auth_realm_filenames['superlinks-mgmt.php'] = 142;
	}

	$menu["Management"]['plugins/superlinks/superlinks-mgmt.php'] = "External Links";

	if ((isset($_SESSION['sess_user_id']))) {
		// Calling this a little earlier than normal
		db_connect_real($database_hostname, $database_username, $database_password, $database_default, $database_type, $database_port);

		if (isset($user_auth_realm_filenames['superlinks.php'])) {
			$realm_id2 = $user_auth_realm_filenames['superlinks.php'];
		}

		if ((db_fetch_assoc("select user_auth_realm.realm_id from user_auth_realm where user_auth_realm.user_id='" . $_SESSION["sess_user_id"] . "' and user_auth_realm.realm_id='$realm_id2'")) || (empty($realm_id2))) {
			$queryrows=array();
			$SQL = "SELECT DISTINCT id, title, extendedstyle
				FROM superlinks_pages, superlinks_auth
				WHERE superlinks_pages.id=superlinks_auth.pageid
				AND style='CONSOLE'
				AND disabled=''
				AND (userid=".$_SESSION["sess_user_id"]." OR userid=0)
				ORDER BY extendedstyle, sortorder,id";

			$cnn_id->SetFetchMode(ADODB_FETCH_ASSOC);
			$query = $cnn_id->Execute($SQL);
			if ($query) {
				while ((!$query->EOF) && ($query)) {
					$queryrows{sizeof($queryrows)} = $query->fields;
					$query->MoveNext();
				}
			}

			$tmp = $menu["Utilities"];
			unset( $menu["Utilities"]);

			if (sizeof($queryrows) > 0) {
				foreach ($queryrows as $page) {
					$menuname = ( isset($page['extendedstyle']) && $page['extendedstyle'] != '' ? $page['extendedstyle'] : "Extra");

					$menu[$menuname]["plugins/superlinks/superlinks.php?id=".$page['id']] = $page['title'];
				}
			}

			$menu["Utilities"] = $tmp;
		}
	}
}

function superlinks_show_tab () {
	global $config, $user_auth_realms, $user_auth_realm_filenames;

	$realm_id2 = 0;

	superlinks_setup_table();

	if (isset($user_auth_realm_filenames['superlinks.php'])) {
			$realm_id2 = $user_auth_realm_filenames['superlinks.php'];
	}

	$tabstyle = read_config_option("superlinks_tabstyle");

	if ((db_fetch_assoc("select user_auth_realm.realm_id from user_auth_realm where user_auth_realm.user_id='" . $_SESSION["sess_user_id"] . "' and user_auth_realm.realm_id='$realm_id2'")) || (empty($realm_id2))) {
		$queryrows = db_fetch_assoc("SELECT DISTINCT id, title, imagecache
			FROM superlinks_pages, superlinks_auth
			WHERE superlinks_pages.id=superlinks_auth.pageid
			AND style='TAB' AND (userid=".$_SESSION["sess_user_id"]." OR userid=0) AND disabled=''
			ORDER BY sortorder, id");

		if (sizeof($queryrows) > 0) {
			foreach ($queryrows as $page) {
				$bluetab = "tab_".$page['imagecache'];
				$redtab = "red_".$page['imagecache'];

				// use the small tab images
				if (($tabstyle == 1) || ($tabstyle == 2)) {
					$bluetab = "s_".$bluetab;
					$redtab = "s_".$redtab;
				}

				$thetab = $bluetab;

				if (preg_match('/superlinks.php\?id=(\d+)/',$_SERVER['REQUEST_URI'] ,$matches)) {
					if ($matches[1]==$page['id']) {
						$thetab = $redtab;
					}
				}

				print "<a href=\"".$config['url_path']."plugins/superlinks/superlinks.php?id=".$page['id']."\"><img align=absmiddle border=0 src=\"".$config['url_path']."plugins/superlinks/tab_images/$thetab\" alt=\"".$page['title']."\" /></a>";
			}
		}
	}
}

function superlinks_setup_table () {
	global $config, $database_default;
	include_once($config["library_path"] . DIRECTORY_SEPARATOR . "database.php");

	$dbversion = read_config_option("superlinks_db_version");

	$myversioninfo = superlinks_version();
	$myversion = $myversioninfo['version'];

	// only bother with all this if it's a new install, a new version, or we're in a development version
	// - saves a handful of db hits per request!
	if (($dbversion=="") || (($dbversion != $myversion) && !preg_match("/dev$/",$myversion))) {
		$sql = "show tables";
		$result = db_fetch_assoc($sql) or die (mysql_error());

		$tables = array();
		$sql = array();

		foreach($result as $index => $arr) {
			foreach ($arr as $t) {
				$tables[] = $t;
			}
		}

		if (!in_array('superlinks_pages', $tables)) {
			$sql[] = "CREATE TABLE superlinks_pages(
				id int(11) NOT NULL auto_increment,
				sortorder int(11) NOT NULL default 0,
				disabled char(2) NOT NULL default '',
				contentfile text NOT NULL,
				title text NOT NULL,
				style varchar(10) NOT NULL DEFAULT 'TAB',
				extendedstyle varchar(50) NOT NULL DEFAULT '',
				imagecache varchar(60) NOT NULL DEFAULT '',
				PRIMARY KEY  (id)
			) ENGINE=MyISAM;";
		} else {
			$colsql = "SHOW COLUMNS FROM superlinks_pages FROM `" . $database_default."`";
			$result = mysql_query($colsql) or die (mysql_error());
			$found_extended = false;

			while ($row = mysql_fetch_array($result, MYSQL_ASSOC)) {
				if ($row['Field'] == 'extendedstyle') $found_extended = true;
			}

			if (! $found_extended) {
				$sql[] = "alter table superlinks_pages add extendedstyle varchar(50) NOT NULL default '' after style";
				$sql[] = "update superlinks_pages set extendedstyle='Extra' where style='CONSOLE'";
			}
		}

		if (!in_array('superlinks_auth', $tables)) {
			$sql[] = "CREATE TABLE superlinks_auth (
				userid mediumint(9) NOT NULL default '0',
				pageid int(11) NOT NULL default '0'
			) ENGINE=MyISAM;";
		}

		$sql[] = "UPDATE superlinks_pages SET sortorder=id WHERE sortorder IS NULL OR sortorder=0;";

		$tabstyle = read_config_option("superlinks_tabstyle");
		if ($tabstyle == '' or $tabstyle < 0 or $tabstyle >2) {
			$sql[] = "replace into settings values('superlinks_tabstyle',0)";
		}
		$hidelogo = read_config_option("superlinks_hidelogo");
		if ($hidelogo == '' or $hidelogo < 0 or $hidelogo >2) {
			$sql[] = "replace into settings values('superlinks_hidelogo',0)";
		}

		$hideconsole = read_config_option("superlinks_hideconsole");
		if ($hideconsole == '' or $hideconsole < 0 or $hideconsole >1) {
			$sql[] = "replace into settings values('superlinks_hideconsole',0)";
		}

		$sql[] = "replace into settings values('superlinks_db_version','$myversion')";

		if (!empty($sql)) {
			for ($a = 0; $a < count($sql); $a++) {
				$result = db_execute($sql[$a]);
			}
		}
	}
}

function superlinks_draw_navigation_text ($nav) {
	global $superlinks_nav;

	if (sizeof($superlinks_nav)) {
		foreach($superlinks_nav as $key => $value) {
			$nav[$key] = $value;
		}
	}

	return $nav;
}

function superlinks_version () {
	return array(
		'name'     => 'superlinks',
		'version'  => '1.4',
		'longname' => 'SuperLinks',
		'author'   => 'Howard Jones',
		'homepage' => 'http://docs.cacti.net/plugin:superlinks',
		'webpage'  => 'http://www.cacti.net',
		'email'    => 'howie@thingy.com',
		'url'      => ''
	);
}

?>
