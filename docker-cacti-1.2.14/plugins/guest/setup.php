<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2002-2011 The Cacti Group                                 |
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

function plugin_guest_install () {
	api_plugin_register_hook('guest', 'config_settings',  'guest_config_settings', 'setup.php');
	api_plugin_register_hook('guest', 'top_header',       'guest_authorized',      'setup.php');
	api_plugin_register_hook('guest', 'top_graph_header', 'guest_authorized',      'setup.php');
	api_plugin_register_hook('guest', 'general_header',   'guest_authorized',      'setup.php');

	guest_setup_table();
}

function plugin_guest_uninstall () {
	db_execute("DROP TABLE IF EXISTS `user_auth_guests`");
}

function plugin_guest_check_config () {
    /* Here we will check to ensure everything is configured */
    return true;
}

function plugin_guest_upgrade() {
    /* Here we will upgrade to the newest version */
    return false;
}

function plugin_guest_version() {
    return guest_version();
}

function plugin_guest_check_upgrade() {
	/* only do something here if upgrading */
}

function guest_setup_table() {
	db_execute("CREATE TABLE IF NOT EXISTS `user_auth_guests` (
		`ip_address` VARCHAR(20) NOT NULL,
		`last_seen` TIMESTAMP NOT NULL,
		PRIMARY KEY (`ip_address`))
		ENGINE=MEMORY
		COMMENT = 'Keeps track of IP Addresses that are Authorized to Access Cacti';");
}

function guest_version () {
	return array( 
		'name' 		=> 'guest',
		'version' 	=> '1.0',
		'longname'	=> 'Guest Account Access Control',
		'author'	=> 'The Cacti Group',
		'homepage'	=> 'http://www.cacti.net',
		'email'		=> '',
		'url'		=> ''
	);
}

function guest_config_settings () {
	global $tabs, $settings;

	$temp1 = array(
		'guest_header' => array(
            'friendly_name' => 'Guest Account Settings',
            'method' => 'spacer',
		)
	);

	$temp2 = array(
		'guest_policy' => array(
			'friendly_name' => 'Guest Access Policy',
			'description' => 'Please select the default guest policy.  If <b>Permissive</b>, then all users who
				match the ACL below will be allowed access.  If <b>Restrictive</b> then all users that match
			the ACL below will be restricted.',
			'method' => 'drop_array',
			'array' => array(1 => 'Permissive', 2 => 'Restrictive'),
			'default' => '1'
		),
		'guest_acl' => array(
			'friendly_name' => 'Guest IP Address ACL',
			'description' => 'Please enter a comma delimited list of IP address ranges from which the guest account
				is allowed to login from. IP address range examples include <b>172.*, 10.*.*.*, 192.168.11.*</b>.
				<br><br><b>NOTE:</b> For performance reasons, all users who are authorized will be permitted to access Cacti for a
				minimum of 10 minutes prior to checking their permissions again.',
			'method' => 'textarea',
			'textarea_rows' => '2',
			'textarea_cols' => '45',
			'max_length' => 512
		)
	);

	$new_settings = array();
	foreach($settings as $key1 => $setting) {
		if ($key1 == 'authentication') {
			foreach($setting as $key2 => $value) {
				switch($key2) {
					case 'special_users_header':
						break;
					case 'guest_user':
						$new_settings[$key1] += $temp1;
						$new_settings[$key1][$key2] = $value;
						$new_settings[$key1] += $temp2;
						break;
					case 'user_template':
						$user_temp = $value;
						break;
					case 'ldap_general_header':
						$new_settings[$key1][$key2] = $value;
						$new_settings[$key1]['user_template'] = $user_temp;
						break;
					default:
						$new_settings[$key1][$key2] = $value;
						break;
				}
			}
		}else{
			$new_settings[$key1] = $setting;
		}
	}
	$settings = $new_settings;
}

function guest_authorized() {
	global $guest_account;

	$user_ip = $_SERVER['REMOTE_ADDR'];

	if (isset($guest_account)) {
		$guest_user_id = db_fetch_cell("SELECT id 
			FROM user_auth 
			WHERE username='" . read_config_option("guest_user") . "' 
			AND realm=0 AND enabled='on'");

		/* if there is a guest user, see if they match the ACL */
		if (!empty($guest_user_id) && isset($_SESSION["sess_user_id"]) && $_SESSION["sess_user_id"] == $guest_user_id) {
			/* check if the user is in the authorized, also refresh table */
			if (!guest_check_current_guests($user_ip)) {;
				$guest_acl  = read_config_option('guest_acl');
				$authorized = false;
				if (strlen($guest_acl)) {
					$parts = explode(',', $guest_acl);
					foreach($parts as $range) {
						$range = trim(str_replace('*', '%', $range));
						$match = db_fetch_cell("SELECT '$user_ip' LIKE '$range'");
	
						if ($match) {
							if (read_config_option('guest_policy') == 1) {
								db_execute("REPLACE INTO `user_auth_guests` (ip_address, last_seen) VALUES ('$user_ip', NOW())");
								return;
							}else{
								guest_unauthorized();
							}
						}
					}
	
					if (read_config_option('guest_policy') == 1) {
						guest_unauthorized();
					}else{
						db_execute("REPLACE INTO `user_auth_guests` (ip_address, last_seen) VALUES ('$user_ip', NOW())");
					}
				}
			}
		}
    }
}

function guest_check_current_guests($ip) {
	$authorized = db_fetch_cell("SELECT UNIX_TIMESTAMP(last_seen) FROM `user_auth_guests` WHERE ip_address='$ip'");

	if (empty($authorized) || (time() - $authorized > 600)) {
		/* remove old guest accounts */
		db_execute("DELETE FROM `user_auth_guests` WHERE UNIX_TIMESTAMP()-UNIX_TIMESTAMP(last_seen)>600");

		$user_ips = db_fetch_assoc("SELECT * FROM `user_auth_guests`");

		if (sizeof($user_ips)) {
		foreach($user_ips as $user_ip) {
			$parts = explode(',', $guest_acl);
			$valid = false;
			foreach($parts as $range) {
				$range = trim(str_replace('*', '%', $range));
				$match = db_fetch_cell("SELECT '$user_ip' LIKE '$range'");
	
				if ($match) {
					$valid = true;
					break;
				}
			}
		
			if (!$valid) {
				db_execute("DELETE FROM `user_auth_guests` WHERE ip_address='$user_ip'");
			}
		}
		}

		return false;
	}else{
		return true;
	}
}

function guest_unauthorized() {
	global $config;

	/* Clear session and return to the login page */
	setcookie(session_name(),'',time() - 3600, '/');
	session_destroy();

	if (isset($_SERVER['HTTP_REFERER'])) {
		$goBack = "<td class='textArea' colspan='2' align='center'>( <a href='" . htmlspecialchars($_SERVER['HTTP_REFERER']) . "'>Return</a> | <a href='" . $config['url_path'] . "logout.php'>Login</a> )</td>";
	}else{
		$goBack = "<td class='textArea' colspan='2' align='center'>( <a href='" . $config['url_path'] . "logout.php'>Login</a> )</td>";
	}

	$page_title = api_plugin_hook_function('page_title', draw_navigation_text("title"));

	?>
	<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
	<html>
	<head>
		<title><?php print $page_title;?></title>
		<meta http-equiv='Content-Type' content='text/html;charset=utf-8'>
		<link href='<?php echo $config['url_path']; ?>include/main.css' type='text/css' rel='stylesheet'>
	</head>
	<body>
		<br><br>
		<table width='450' align='center'>
			<tr>
				<td colspan='2'><img src='<?php echo $config['url_path']; ?>images/auth_deny.gif' border='0' alt='Access Denied'></td>
			</tr>
			<tr style='height:10px;'><td></td></tr>
			<tr>
				<td class='textArea' colspan='2'>Your IP Address is not permitted to access this section of Cacti. 
					If you feel that you need access to this particular section, please contact your Cacti Administrator.</td>
			</tr>
			<tr>
				<?php print $goBack;?>
			</tr>
		</table>
		</body>
	</html>
	<?php
	exit;
}

?>
