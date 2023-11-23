<?php
/*******************************************************************************

    Author ......... Jimmy Conner
    Contact ........ jimmy@sqmail.org
    Home Site ...... http://cactiusers.org
    Program ........ ntop tab for Cacti
    Version ........ 0.2
    Purpose ........ Display Ntop inside of Cacti

*******************************************************************************/

function plugin_ntop_install() {
	api_plugin_register_hook('ntop', 'top_header_tabs',       'ntop_show_tab',             "setup.php");
	api_plugin_register_hook('ntop', 'top_graph_header_tabs', 'ntop_show_tab',             "setup.php");
	api_plugin_register_hook('ntop', 'config_arrays',         'ntop_config_arrays',        "setup.php");
	api_plugin_register_hook('ntop', 'draw_navigation_text',  'ntop_draw_navigation_text', "setup.php");
	api_plugin_register_hook('ntop', 'config_settings',       'ntop_config_settings',      "setup.php");
}

function plugin_ntop_uninstall () {
	return true;
}

function plugin_ntop_check_config () {
	return true;
}

function plugin_ntop_upgrade () {
	return false;
}

function ntop_config_settings () {
	global $tabs, $settings;
	$tabs["misc"] = "Misc";

	$temp = array(
		"ntop_header" => array(
			"friendly_name" => "NTop",
			"method" => "spacer",
			),
		"ntop_url" => array(
			"friendly_name" => "NTop URL",
			"description" => "This is full URL used to connect to NTop.  (ex: http://192.168.0.1:3000).<br>Use |SERVERIP| to use the current server's IP. (ex: http://|SERVERIP|:3000)",
			"method" => "textbox",
			"max_length" => 255,
			)
	);

	if (isset($settings["misc"]))
		$settings["misc"] = array_merge($settings["misc"], $temp);
	else
		$settings["misc"]=$temp;
}

function ntop_show_tab () {
	global $config, $user_auth_realms, $user_auth_realm_filenames;
	$realm_id2 = 0;

	if (isset($user_auth_realm_filenames{basename('ntop.php')})) {
		$realm_id2 = $user_auth_realm_filenames{basename('ntop.php')};
	}
	if ((db_fetch_assoc("select user_auth_realm.realm_id
		from user_auth_realm where user_auth_realm.user_id='" . $_SESSION["sess_user_id"] . "'
		and user_auth_realm.realm_id='$realm_id2'")) || (empty($realm_id2))) {

		if (basename($_SERVER["PHP_SELF"]) != "ntop.php") {
			print '<a href="' . $config['url_path'] . 'plugins/ntop/ntop.php"><img src="' . $config['url_path'] . 'plugins/ntop/images/tab_ntop.gif" alt="ntop" align="absmiddle" border="0"></a>';
		}else{
			print '<a href="' . $config['url_path'] . 'plugins/ntop/ntop.php"><img src="' . $config['url_path'] . 'plugins/ntop/images/tab_ntop_down.gif" alt="ntop" align="absmiddle" border="0"></a>';
		}
	}
}

function ntop_config_arrays () {
	global $user_auth_realms, $user_auth_realm_filenames;
	$user_auth_realms[36]='View NTop';
	$user_auth_realm_filenames['ntop.php'] = 36;
}


function ntop_draw_navigation_text ($nav) {
   $nav["ntop.php:"] = array("title" => "NTop", "mapping" => "", "url" => "ntop.php", "level" => "0");
   return $nav;
}

function ntop_version() {
	return plugin_ntop_version();
}

function plugin_ntop_version () {
	return array(
		'name'      => 'ntop',
		'version'   => '0.2',
		'longname'  => 'NTop Viewer',
		'author'    => 'Jimmy Conner',
		'homepage'  => 'http://cactiusers.org',
		'email'     => 'jimmy@sqmail.org',
		'url'       => 'http://cactiusers.org/cacti/versions.php'
	);
}

?>