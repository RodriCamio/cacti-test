<?php
/*
 ex: set tabstop=4 shiftwidth=4 autoindent:
 +-------------------------------------------------------------------------+
 | Copyright (C) 2008-2012 The Cacti Group                                 |
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

global $error, $bad_password;
function plugin_secpass_install () {
	api_plugin_register_hook('secpass', 'config_settings', 'plugin_secpass_config_settings', 'setup.php');
	api_plugin_register_hook('secpass', 'login_process', 'plugin_secpass_login_process', 'setup.php');
	api_plugin_register_hook('secpass', 'poller_bottom', 'plugin_secpass_check_expired', 'setup.php');
	api_plugin_register_hook('secpass', 'user_admin_edit', 'plugin_secpass_user_admin_edit', 'setup.php');
	api_plugin_register_hook('secpass', 'user_admin_setup_sql_save', 'plugin_secpass_user_admin_setup_sql_save', 'setup.php');
	plugin_secpass_setup_table ();
}

function plugin_secpass_check_expired () {
	// Expire Old Accounts
	$e = read_config_option('plugin_secpass_expireaccount');
	if ($e > 0 && is_numeric($e)) {
		$t = time();
		db_execute("UPDATE user_auth SET lastlogin = $t WHERE lastlogin = -1 AND realm = 0 AND enabled = 'on'");
		$t = $t - (intval($e) * 86400);
		db_execute("UPDATE user_auth SET enabled = '' WHERE realm = 0 AND enabled = 'on' AND lastlogin < $t AND id > 1");
	}
	$e = read_config_option('plugin_secpass_expirepass');
	if ($e > 0 && is_numeric($e)) {
		$t = time();
		db_execute("UPDATE user_auth SET lastchange = $t WHERE lastchange = -1 AND realm = 0 AND enabled = 'on'");
		$t = $t - (intval($e) * 86400);
		db_execute("UPDATE user_auth SET must_change_password = 'on' WHERE realm = 0 AND enabled = 'on' AND lastchange < $t");
	}
}

function plugin_secpass_login_process ($ret) {
	global $cnn_id;
	$users = db_fetch_assoc('SELECT username FROM user_auth WHERE realm = 0');
	$username = sanitize_search_string(get_request_var_post("login_username"));

	plugin_secpass_checkupgrade();

	# Mark failed login attempts
	if (read_config_option('plugin_secpass_lockfailed') > 0) {
		$max = intval(read_config_option('plugin_secpass_lockfailed'));
		if ($max > 0) {
			$p = get_request_var_post("login_password");
			while (list($fn, $fa) = each($users)) {
				if ($fa['username'] == $username) {

					$user = db_fetch_assoc('SELECT * FROM user_auth WHERE username = ' . $cnn_id->qstr($username) . " AND realm = 0 AND enabled = 'on'");
					if (isset($user[0]['username'])) {
						$user = $user[0];
						$unlock = intval(read_config_option('plugin_secpass_unlocktime'));
						if ($unlock > 1440) $unlock = 1440;

						if ($unlock > 0 && (time() - $user['lastfail'] > 60 * $unlock)) {
							db_execute("UPDATE user_auth SET lastfail = 0, failed_attempts = 0, locked = '' WHERE username = " . $cnn_id->qstr($username) . " AND realm = 0 AND enabled = 'on'");
							$user['failed_attempts'] = $user['lastfail'] = 0;
							$user['locked'] == '';
						}

						if ($user['password'] != md5($p)) {
							$failed = $user['failed_attempts'] + 1;
							if ($failed >= $max) {
								db_execute("UPDATE user_auth SET locked = 'on' WHERE username = " . $cnn_id->qstr($username) . " AND realm = 0 AND enabled = 'on'");
								$user['locked'] = 'on';
							}
							$user['lastfail'] = time();
							db_execute("UPDATE user_auth SET lastfail = " . $user['lastfail']  . ", failed_attempts = $failed WHERE username = " . $cnn_id->qstr($username) . " AND realm = 0 AND enabled = 'on'");

							if ($user['locked'] != '') {
								plugin_secpass_show_lockedscreen ('This account has been locked.');
							}
							return false;
						}
						if ($user['locked'] != '') {
							plugin_secpass_show_lockedscreen ('This account has been locked.');
						}
					}
				}
			}
		}
	}

	# Check if old password doesn't meet specifications and must be changed
	if (read_config_option('plugin_secpass_forceold') == 'on') {
		$p = get_request_var_post("login_password");
		$error = plugin_secpass_check_pass($p);
		if ($error != '') {
			while (list($fn, $fa) = each($users)) {
				if ($fa['username'] == $username) {
					db_execute("UPDATE user_auth SET must_change_password = 'on' WHERE username = " . $cnn_id->qstr($username) . " AND password = '" . md5(get_request_var_post("login_password")) . "' AND realm = 0 AND enabled = 'on'");
					return $ret;
				}
			}
		}
	}
	# Set the last Login time
	if (read_config_option('plugin_secpass_expireaccount') > 0) {
		$p = get_request_var_post("login_password");
		while (list($fn, $fa) = each($users)) {
			if ($fa['username'] == $username) {
				db_execute("UPDATE user_auth SET lastlogin = " . time() . " WHERE username = " . $cnn_id->qstr($username) . " AND password = '" . md5(get_request_var_post("login_password")) . "' AND realm = 0 AND enabled = 'on'");
			}
		}
	}
	return $ret;
}

function plugin_secpass_setup_table () {
	api_plugin_db_add_column ('secpass', 'user_auth', array('name' => 'lastchange', 'type' => 'int(12)', 'NULL' => false, 'default' => '-1'));
	api_plugin_db_add_column ('secpass', 'user_auth', array('name' => 'lastlogin', 'type' => 'int(12)', 'NULL' => false, 'default' => '-1'));
	api_plugin_db_add_column ('secpass', 'user_auth', array('name' => 'password_history', 'type' => 'text', 'NULL' => false, 'default' => ''));
	api_plugin_db_add_column ('secpass', 'user_auth', array('name' => 'locked', 'type' => 'varchar(3)', 'NULL' => false, 'default' => ''));
	api_plugin_db_add_column ('secpass', 'user_auth', array('name' => 'failed_attempts', 'type' => 'int(5)', 'NULL' => false, 'default' => '0'));
	api_plugin_db_add_column ('secpass', 'user_auth', array('name' => 'lastfail', 'type' => 'int(12)', 'NULL' => false, 'default' => '0'));
	$t = time();
	db_execute("UPDATE user_auth SET lastlogin = $t, lastchange = $t WHERE lastchange = -1 AND lastlogin = -1 AND realm = 0 AND enabled = 'on'");
}

function plugin_secpass_config_settings () {
	global $settings, $error, $bad_password;
	global $fields_user_user_edit_host;

	$settings['authentication']['secpass_header'] = array(
			"friendly_name" => "Complexity Requirements",
			"method" => "spacer",
			);
	$settings['authentication']['plugin_secpass_minlen'] = array(
			"friendly_name" => "Minimum Length",
			"description" => "This is minimal length of allowed passwords.",
			"method" => "textbox",
			"default" => "8",
			"max_length" => 2,
			);
	$settings['authentication']['plugin_secpass_reqmixcase'] = array(
			"friendly_name" => "Require Mix Case",
			"description" => "This will require new passwords to contains both lower and upper case characters.",
			"method" => "checkbox",
			"default" => 'on',
			);
	$settings['authentication']['plugin_secpass_reqnum'] = array(
			"friendly_name" => "Require Number",
			"description" => "This will require new passwords to contain at least 1 numberical character.",
			"method" => "checkbox",
			"default" => 'on',
			);

	$settings['authentication']['plugin_secpass_reqspec'] = array(
			"friendly_name" => "Require Special Character",
			"description" => "This will require new passwords to contain at least 1 special character.",
			"method" => "checkbox",
			"default" => 'on',
			);
	$settings['authentication']['plugin_secpass_forceold'] = array(
			"friendly_name" => "Force Complexity Upon Old Passwords",
			"description" => "This will require all old passwords to also meet the new complexity requirements upon login.  If not met, it will force a password change.",
			"method" => "checkbox",
			"default" => '',
			);
	$settings['authentication']['plugin_secpass_expireaccount'] = array(
			"friendly_name" => "Expire Inactive Accounts",
			"description" => "This is maximum number of days before inactive accounts are disabled.  The Admin account is excluded from this policy.  Set to 0 to disable.",
			"method" => "textbox",
			"default" => "0",
			"max_length" => 4,
			);
	$settings['authentication']['plugin_secpass_expirepass'] = array(
			"friendly_name" => "Expire Password",
			"description" => "This is maximum number of days before a password is set to expire.  Set to 0 to disable.",
			"method" => "textbox",
			"default" => "0",
			"max_length" => 4,
			);
	$settings['authentication']['plugin_secpass_history'] = array(
			"friendly_name" => "Password History",
			"description" => "Remember this number of old passwords and disallow re-using them.  Set to 0 to disable.",
			"method" => "textbox",
			"default" => "0",
			"max_length" => 2,
			);

	$settings['authentication']['secpass_lock_header'] = array(
			"friendly_name" => "Account Locking",
			"method" => "spacer",
			);
	$settings['authentication']['plugin_secpass_lockfailed'] = array(
			"friendly_name" => "Lock Accounts",
			"description" => "Lock an account after this many failed attempts in 1 hour.  Set to 0 to disable.",
			"method" => "textbox",
			"default" => "0",
			"max_length" => 2,
			);
	$settings['authentication']['plugin_secpass_unlocktime'] = array(
			"friendly_name" => "Auto Unlock",
			"description" => "An account will automatically be unlocked after this many minutes.  Even if the correct password is entered, the account will not unlock until this time limit has been met.  Set to 0 to disable.  Max of 1440 minutes (1 Day)",
			"method" => "textbox",
			"default" => "60",
			"max_length" => 4,
			);






	if (basename($_SERVER['PHP_SELF']) == 'auth_changepassword.php' && isset($_REQUEST['action']) && $_REQUEST['action'] == 'changepassword' && api_plugin_is_enabled('secpass')) {
		$error = '';
		$bad_password = false;

		if (($_POST['password'] == $_POST['confirm']) && ($_POST['password'] != '')) {
			$p = $_POST['password'];
			/* find out if we are logged in as a 'guest user' or not, if we are redirect away from password change */
			if (db_fetch_cell('select id from user_auth where username=\'' . read_config_option('guest_user') . '\'') == $_SESSION['sess_user_id']) {
				header('Location: index.php');
				exit;
			}
			$error = plugin_secpass_check_pass($p);
			if ($error != '') {
				$bad_password = true;
			}
			if ($bad_password) {
				plugin_secpass_show_badpassscreen ($bad_password, $error);
				exit;
			}
			if (!plugin_secpass_check_history($_SESSION['sess_user_id'], $p)) {
				$bad_password = true;
				$error = "You can not use a previously entered password!";
				plugin_secpass_show_badpassscreen ($bad_password, $error);
				exit;
			}
			// Password change is good to go
			if (read_config_option('plugin_secpass_expirepass') > 0) {
					db_execute("UPDATE user_auth SET lastchange = " . time() . " WHERE id = " . intval($_SESSION['sess_user_id']) . " AND realm = 0 AND enabled = 'on'");
			}
			$history = intval(read_config_option('plugin_secpass_history'));
			if ($history > 0) {
					$h = db_fetch_row("SELECT password, password_history FROM user_auth WHERE id = " . intval($_SESSION['sess_user_id']) . " AND realm = 0 AND enabled = 'on'");
					$op = $h['password'];
					$h = explode('|', $h['password_history']);
					while (count($h) > $history - 1) {
						array_shift($h);
					}
					$h[] = $op;
					$h = implode('|', $h);
					db_execute("UPDATE user_auth SET password_history = '" . $h . "' WHERE id = " . intval($_SESSION['sess_user_id']) . " AND realm = 0 AND enabled = 'on'");
			}
		}
	}
}

function plugin_secpass_user_admin_edit ($user) {
	global $fields_user_user_edit_host;
	$s['locked'] = array(
				'method' => 'checkbox',
				'value' => '|arg1:locked|',
				'friendly_name' => 'Locked',
				'form_id' => '|arg1:id|',
				'default' => '',
				);
	$fields_user_user_edit_host = array_push_after($fields_user_user_edit_host, $s, 'enabled');
	return $user;
}

function plugin_secpass_user_admin_setup_sql_save ($save) {
	if (is_error_message()) {
		return $save;
	}

	if (isset($_POST['locked'])) {
		db_execute("UPDATE user_auth set locked = 'on' WHERE id = " . $save['id']);
	} else {
		db_execute("UPDATE user_auth set locked = '', failed_attempts = 0 WHERE id = " . $save['id']);
	}

	return $save;
}

function plugin_secpass_check_pass($p) {
	$minlen = read_config_option('plugin_secpass_minlen');
	
	$reqmixcase = (read_config_option('plugin_secpass_reqmixcase') == 'on' ? true : false);
	$reqnum = (read_config_option('plugin_secpass_reqnum') == 'on' ? true : false);
	$reqspec = (read_config_option('plugin_secpass_reqspec') == 'on' ? true : false);
	if (strlen($p) < $minlen) {
		return "Password must be at least $minlen characters!";
	}
	if ($reqnum && str_replace(array('0', '1', '2', '3', '4', '5', '6', '7', '8', '9'), '', $p) == $p) {
		return 'Your password must contain at least 1 numerical character!';
	}
	if ($reqmixcase && strtolower($p) == $p) {
		return 'Your password must contain a mix of lower case and upper case characters!';
	}
	if ($reqspec && str_replace(array('~', '`', '!', '@', '#', '$', '%', '^', '&', '*', '(', ')', '-', '_', '+', '=', '[', '{', ']', '}', ';', ':', '<', ',', '.', '>', '?', '|', '/', '\\'), '', $p) == $p) {
		return 'Your password must contain at least 1 special character!';
	}
	return '';
}

function plugin_secpass_check_history($id, $p) {
	$history = intval(read_config_option('plugin_secpass_history'));
	if ($history > 0) {
		$p = md5($p);
		$user = db_fetch_row("SELECT password, password_history FROM user_auth WHERE id = " . intval($id) . " AND realm = 0 AND enabled = 'on'");
		if ($p == $user['password']) {
			return false;
		}
		$passes = explode('|', $user['password_history']);
		// Double check this incase the password history setting was changed
		while (count($passes) > $history) {
			array_shift($passes);
		}
		if (!empty($passes) && in_array($p, $passes)) {
			return false;
		}
	}
	return true;
}

function plugin_secpass_checkupgrade() {
	if (api_plugin_is_enabled ('secpass') && !db_fetch_cell("SELECT id FROM plugin_hooks WHERE name = 'secpass' AND hook = 'user_admin_setup_sql_save'")) {
		plugin_secpass_install ();
	}
}


function plugin_secpass_uninstall () {
}

function plugin_secpass_check_config () {
	return true;
}

function plugin_secpass_upgrade () {
	return false;
}

function secpass_version () {
	return plugin_secpass_version();
}

function plugin_secpass_version () {
	return array(
		'name'     => 'secpass',
		'version'  => '0.2',
		'longname' => 'Secure Passwords',
		'author'   => 'Jimmy Conner',
		'homepage' => 'http://cactiusers.org',
		'email'    => 'jimmy@sqmail.org',
		'url'      => 'http://versions.cactiusers.org/'
	);
}

function array_push_after($src, $in, $pos){
	$R = array();
	if (is_int($pos)) {
		$R = array_merge(array_slice($src, 0, $pos+1), $in, array_slice($src, $pos+1));
	} else {
		foreach($src as $k=>$v){
			$R[$k] = $v;
			if($k == $pos){
				$R = array_merge($R, $in);
			}
		}
	}
	return $R;
}

function plugin_secpass_show_badpassscreen ($bad_password, $error) {
	global $error, $bad_password;
	if (api_plugin_hook_function('custom_password', OPER_MODE_NATIVE) == OPER_MODE_RESKIN) {
		exit;
	}
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html>
<head>
	<title>Login to cacti</title>
	<meta http-equiv="Content-Type" content="text/html;charset=utf-8">
	<STYLE TYPE="text/css">
	<!--
		BODY, TABLE, TR, TD {font-family: Verdana, Arial, Helvetica, sans-serif; font-size: 12px;}
		A {text-decoration: none;}
		A:active { text-decoration: none;}
		A:hover {text-decoration: underline; color: #333333;}
		A:visited {color: Blue;}
	-->
	</style>
</head>

<body onload="document.login.password.focus()">

<form name="login" method="post" action="<?php print basename($_SERVER["PHP_SELF"]);?>">

<table align="center">
	<tr>
		<td colspan="2"><img src="images/auth_login.gif" border="0" alt=""></td>
	</tr>
	<?php if ($bad_password == true) {?>
	<tr style="height:10px;"><td></td></tr>
	<tr>
		<td colspan="2"><font color="#FF0000"><strong><?php echo $error; ?></strong></font></td>
	</tr>
	<?php }?>
	<tr style="height:10px;"><td></td></tr>
	<tr>
		<td colspan="2">
			<strong><font color="#FF0000">*** Forced Password Change ***</font></strong><br><br>
			Please enter a new password for cacti:
		</td>
	</tr>
	<tr style="height:10px;"><td></td></tr>
	<tr>
		<td>Password:</td>
		<td><input type="password" name="password" size="40"></td>
	</tr>
	<tr>
		<td>Confirm:</td>
		<td><input type="password" name="confirm" size="40"></td>
	</tr>
	<tr style="height:10px;"><td></td></tr>
	<tr>
		<td><input type="submit" value="Save"></td>
	</tr>
</table>

<input type="hidden" name="action" value="changepassword">
<input type="hidden" name="ref" value="<?php print (isset($_REQUEST["ref"]) ? sanitize_uri($_REQUEST["ref"]) : '');?>">

</form>

</body>
</html>
<?php
}

function plugin_secpass_show_lockedscreen ($error) {
	if (api_plugin_hook_function('custom_password', OPER_MODE_NATIVE) == OPER_MODE_RESKIN) {
		exit;
	}
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html>
<head>
	<title>Login to cacti</title>
	<meta http-equiv="Content-Type" content="text/html;charset=utf-8">
	<STYLE TYPE="text/css">
	<!--
		BODY, TABLE, TR, TD {font-family: Verdana, Arial, Helvetica, sans-serif; font-size: 12px;}
		A {text-decoration: none;}
		A:active { text-decoration: none;}
		A:hover {text-decoration: underline; color: #333333;}
		A:visited {color: Blue;}
	-->
	</style>
</head>

<body>

<table align="center">
	<tr>
		<td colspan="2"><img src="images/auth_login.gif" border="0" alt=""></td>
	</tr>
	<tr style="height:10px;"><td></td></tr>
	<tr>
		<td colspan="2"><font color="#FF0000"><strong><center><?php echo $error; ?></center></strong></font></td>
	</tr>
	<tr style="height:10px;"><td></td></tr>
	<tr>

	</tr>
</table>

</body>
</html>
<?php
	exit;
}
