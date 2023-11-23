<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2010 The Cacti Group                                 |
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

function plugin_domains_install () {
	api_plugin_register_hook('domains', 'config_arrays',         'domains_config_arrays',         'setup.php');
	api_plugin_register_hook('domains', 'draw_navigation_text',  'domains_draw_navigation_text',  'setup.php');
	api_plugin_register_hook('domains', 'login_process',         'domains_login_process',         'setup.php');
	api_plugin_register_hook('domains', 'login_realms_exist',    'domains_login_realms_exist',    'setup.php');
	api_plugin_register_hook('domains', 'login_realms',          'domains_login_realms',          'setup.php');

	api_plugin_register_realm('domains', 'domains.php', 'Plugin -> Manage User Domains', 1);

	domains_setup_table_new();
}

function domains_version () {
	return array(
		'name'     => 'domains',
		'version'  => '0.2',
		'longname' => 'Multiple User Domains for Cacti',
		'author'   => 'The Cacti Group',
		'homepage' => 'http://www.cacti.net',
		'email'    => '',
		'url'      => 'http://www.cacti.net'
		);
}

function plugin_domains_uninstall () {
	db_execute("DROP TABLE IF EXISTS plugin_domains");
	db_execute("DROP TABLE IF EXISTS plugin_domains_ldap");
}

function plugin_domains_check_config () {
	/* Here we will check to ensure everything is configured */
	domains_check_upgrade();
	return true;
}

function plugin_domains_upgrade() {
	/* Here we will upgrade to the newest version */
	domains_check_upgrade();
	return false;
}

function plugin_domains_version() {
	return domains_version();
}

function domains_check_upgrade() {
	$files = array('plugins.php', 'domains.php');
	if (isset($_SERVER['PHP_SELF']) && !in_array(basename($_SERVER['PHP_SELF']), $files)) {
		return;
	}
}

function domains_check_dependencies() {
	return true;
}

function domains_setup_table_new() {
	db_execute("CREATE TABLE IF NOT EXISTS `plugin_domains` (
		`domain_id` int(10) unsigned NOT NULL auto_increment,
		`domain_name` varchar(20) NOT NULL,
		`type` int(10) UNSIGNED NOT NULL DEFAULT '0',
		`enabled` char(2) NOT NULL DEFAULT 'on',
		`defdomain` tinyint(3) NOT NULL DEFAULT '0',
		`user_id` int(10) unsigned NOT NULL default '0',
		PRIMARY KEY  (`domain_id`))
		ENGINE=MyISAM
		COMMENT='Table to Hold Login Domains';");

	db_execute("CREATE TABLE `plugin_domains_ldap` (
		`domain_id` int(10) unsigned NOT NULL,
		`server` varchar(128) NOT NULL,
		`port` int(10) unsigned NOT NULL,
		`port_ssl` int(10) unsigned NOT NULL,
		`proto_version` tinyint(3) unsigned NOT NULL,
		`encryption` tinyint(3) unsigned NOT NULL,
		`referrals` tinyint(3) unsigned NOT NULL,
		`mode` tinyint(3) unsigned NOT NULL,
		`dn` varchar(128) NOT NULL,
		`group_require` char(2) NOT NULL,
		`group_dn` varchar(128) NOT NULL,
		`group_attrib` varchar(128) NOT NULL,
		`group_member_type` tinyint(3) unsigned NOT NULL,
		`search_base` varchar(128) NOT NULL,
		`search_filter` varchar(128) NOT NULL,
		`specific_dn` varchar(128) NOT NULL,
		`specific_password` varchar(128) NOT NULL,
		PRIMARY KEY  (`domain_id`))
		ENGINE=MyISAM
		COMMENT='Table to Hold Login Domains for LDAP';");
}

function domains_config_arrays() {
	global $menu, $domain_types, $auth_realms, $auth_methods;

	domains_check_upgrade();

	$menu2 = array ();
	foreach ($menu as $temp => $temp2 ) {
		if ($temp == 'Utilities') {
			$newtmp2 = array();
			foreach($temp2 as $uri => $name) {
				$newtmp2[$uri] = $name;
				if ($uri == 'user_admin.php') {
					$newtmp2['plugins/domains/domains.php'] = 'User Domains';
				}
			}
			$menu2[$temp] = $newtmp2;
		}else{
			$menu2[$temp] = $temp2;
		}
	}
	$menu = $menu2;

	$domain_types = array("1" => "LDAP", "2" => "Active Directory");

	$new_realms = db_fetch_assoc("SELECT * FROM plugin_domains");
	$realms     = array();
	if (sizeof($new_realms)) {
	foreach($new_realms as $realm) {
		$realms[$realm["domain_id"] + 1000] = $realm["domain_name"];
	}
	}

	if (sizeof($realms)) {
		$auth_realms = $auth_realms + $realms;
	}

	$auth_methods = array_merge($auth_methods, array("4" => "Multiple LDAP/AD Domains"));
}

function domains_login_realms($auth_realms) {
	if (read_config_option('auth_method') > "2") {
		$realms = db_fetch_assoc("SELECT * FROM plugin_domains WHERE enabled='on' ORDER BY domain_name");
		$default_realm = db_fetch_cell("SELECT domain_id FROM plugin_domains WHERE defdomain=1 AND enabled='on'");
		if (sizeof($realms)) {
			$new_realms["local"] = array("name" => "Local", "selected" => false);
			foreach($realms as $realm) {
				$new_realms[1000+$realm["domain_id"]] = array("name" => $realm["domain_name"], "selected" => false);
			}

			if (!empty($default_realm)) {
				$new_realms[1000+$default_realm]["selected"] = true;
			}else{
				$new_realms["local"]["selected"] = true;
			}

			return $new_realms;
		}else{
			return $auth_realms;
		}
	}else{
		return $auth_realms;
	}
}

function domains_login_realms_exist() {
	if ((read_config_option("auth_method") == "4") &&
		(sizeof(db_fetch_cell("SELECT count(*) FROM plugin_domains WHERE enabled='on'")))) {
		return true;
	}else{
		return false;
	}
}

function domains_login_process() {
	global $user, $realm, $username, $user_auth, $ldap_error, $ldap_error_message;
	if (is_numeric(get_request_var_post("realm")) && (strlen(get_request_var_post("login_password")) > 0)) {
		/* include LDAP lib */
		include_once("./lib/ldap.php");

		/* get user DN */
		$ldap_dn_search_response = domains_ldap_search_dn($username, get_request_var_post("realm"));
		if ($ldap_dn_search_response["error_num"] == "0") {
			$ldap_dn = $ldap_dn_search_response["dn"];
		}else{
			/* Error searching */
			cacti_log("LOGIN: LDAP Error: " . $ldap_dn_search_response["error_text"], false, "AUTH");
			$ldap_error = true;
			$ldap_error_message = "LDAP Search Error: " . $ldap_dn_search_response["error_text"];
			$user_auth = false;
			$user = array();
		}

		if (!$ldap_error) {
			/* auth user with LDAP */
			$ldap_auth_response = domains_ldap_auth($username, stripslashes(get_request_var_post("login_password")), $ldap_dn, get_request_var_post("realm"));

			if ($ldap_auth_response["error_num"] == "0") {
				/* User ok */
				$user_auth = true;
				$copy_user = true;
				$realm = get_request_var_post("realm");
				/* Locate user in database */
				cacti_log("LOGIN: LDAP User '" . $username . "' Authenticated from Domain '" . db_fetch_cell("SELECT domain_name FROM plugin_domains WHERE domain_id=" . ($realm-1000)) . "'", false, "AUTH");
				$user = db_fetch_row("SELECT * FROM user_auth WHERE username='" . $username . "' AND realm=$realm");

				/* Create user from template if requested */
				$template_user = db_fetch_cell("SELECT user_id FROM plugin_domains WHERE domain_id=" . (get_request_var_post("realm")-1000));
				$template_username = db_fetch_cell("SELECT username FROM user_auth WHERE id=$template_user");
				if ((!sizeof($user)) && ($copy_user) && ($template_user != "0") && (strlen($username) > 0)) {
					cacti_log("WARN: User '" . $username . "' does not exist, copying template user", false, "AUTH");
					/* check that template user exists */
					if (db_fetch_row("SELECT id FROM user_auth WHERE id=" . $template_user . " AND realm = 0")) {
						/* template user found */
						user_copy($template_username, $username, 0, $realm);
						/* requery newly created user */
						$user = db_fetch_row("SELECT * FROM user_auth WHERE username='" . $username . "' AND realm=" . $realm);
					}else{
						/* error */
						cacti_log("LOGIN: Template user '" . $template_username . "' does not exist.", false, "AUTH");
						auth_display_custom_error_message("Template user '" . $template_username . "' does not exist.");
						exit;
					}
				}
			}else{
				/* error */
				cacti_log("LOGIN: LDAP Error: " . $ldap_auth_response["error_text"], false, "AUTH");
				$ldap_error = true;
				$ldap_error_message = "LDAP Error: " . $ldap_auth_response["error_text"];
				$user_auth = false;
				$user = array();
			}
		}

	}
}

function domains_ldap_auth($username, $password = "", $dn = "", $realm) {
	$ldap = new Ldap;

	if (!empty($username)) $ldap->username = $username;
	if (!empty($password)) $ldap->password = $password;
	if (!empty($dn))       $ldap->dn       = $dn;

	$ld = db_fetch_row("SELECT * FROM plugin_domains_ldap WHERE domain_id=" . ($realm-1000));

	if (sizeof($ld)) {
		if (!empty($ld["dn"]))                $ldap->dn                = $ld["dn"];
		if (!empty($ld["server"]))            $ldap->host              = $ld["server"];
		if (!empty($ld["port"]))              $ldap->port              = $ld["port"];
		if (!empty($ld["port_ssl"]))          $ldap->port_ssl          = $ld["port_ssl"];
		if (!empty($ld["proto_version"]))     $ldap->version           = $ld["proto_version"];
		if (!empty($ld["encryption"]))        $ldap->encryption        = $ld["encryption"];
		if (!empty($ld["referrals"]))         $ldap->referrals         = $ld["referrals"];
		if (!empty($ld["group_require"]))     $ldap->group_require     = $ld["group_require"];
		if (!empty($ld["group_dn"]))          $ldap->group_dn          = $ld["group_dn"];
		if (!empty($ld["group_attrib"]))      $ldap->group_attrib      = $ld["group_attrib"];
		if (!empty($ld["group_member_type"])) $ldap->group_member_type = $ld["group_member_type"];

		return $ldap->Authenticate();
	}else{
		return false;
	}
}

function domains_ldap_search_dn($username, $realm) {
	$ldap = new Ldap;

	if (!empty($username)) $ldap->username = $username;

	$ld = db_fetch_row("SELECT * FROM plugin_domains_ldap WHERE domain_id=" . ($realm-1000));

	if (sizeof($ld)) {
		if (!empty($ld["dn"]))                $ldap->dn                = $ld["dn"];
		if (!empty($ld["server"]))            $ldap->host              = $ld["server"];
		if (!empty($ld["port"]))              $ldap->port              = $ld["port"];
		if (!empty($ld["port_ssl"]))          $ldap->port_ssl          = $ld["port_ssl"];
		if (!empty($ld["proto_version"]))     $ldap->version           = $ld["proto_version"];
		if (!empty($ld["encryption"]))        $ldap->encryption        = $ld["encryption"];
		if (!empty($ld["referrals"]))         $ldap->referrals         = $ld["referrals"];
		if (!empty($ld["mode"]))              $ldap->group_require     = $ld["mode"];
		if (!empty($ld["search_base"]))       $ldap->group_dn          = $ld["search_base"];
		if (!empty($ld["search_filter"]))     $ldap->group_attrib      = $ld["search_filter"];
		if (!empty($ld["specific_dn"]))       $ldap->group_member_type = $ld["specific_dn"];
		if (!empty($ld["specific_password"])) $ldap->group_member_type = $ld["specific_password"];

		return $ldap->Search();
	}else{
		return false;
	}
}

function domains_draw_navigation_text ($nav) {
	$nav["domains.php:"] = array("title" => "User Domains", "mapping" => "index.php:", "url" => "domains.php", "level" => "1");
	$nav["domains.php:edit"] = array("title" => "(Edit)", "mapping" => "domains.php:,index.php:", "url" => "domains.php:edit", "level" => "2");

	return $nav;
}

?>
