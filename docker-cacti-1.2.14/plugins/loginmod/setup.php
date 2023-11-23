<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2008 The Cacti Group                                      |
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

function plugin_init_loginmod() {
	global $plugin_hooks;
	$plugin_hooks['login_before']['loginmod'] = 'plugin_loginmod_loginbefore';
	$plugin_hooks['login_after']['loginmod']  = 'plugin_loginmod_loginafter';
	$plugin_hooks['cacti_image']['loginmod']  = 'plugin_loginmod_cacti_image';
}

function loginmod_version () {
	return array( 'name' 	=> 'loginmod',
			'version' 	=> '1.0',
			'longname'	=> 'Login Page Mod',
			'author'		=> 'Jimmy Conner',
			'homepage'	=> 'http://cactiusers.org',
			'email'		=> 'jimmy@sqmail.org',
			'url'		=> 'http://versions.cactiusers.org/'
			);
}

function plugin_loginmod_loginbefore() {
	print "<style>
		body {
			margin: 0 0;
			padding: 0;
			background-color:  #508040;
			font-weight: bold;
		}
		input {
			border: 1px solid #508040;
			background-color:  #eee;
			font-size: 110%;
			color: #508040;
			font-weight: bold;
			text-align: center;
		}
		#loginwrapper {
			position: relative;
			background-color:  #fff;
			width: 500;
			margin: 15% auto 0 auto;
			padding: 10px;
			border: 1px solid #000;
		}
	</style>";
	print "<center><div id='loginwrapper'>";
}

function plugin_loginmod_loginafter() {
	print "</div><script>document.login.submit.value;</script>";
}

function plugin_loginmod_cacti_image ($image) {
	return $image;
}