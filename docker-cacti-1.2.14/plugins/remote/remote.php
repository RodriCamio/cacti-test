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

chdir('../../');
include("./include/auth.php");

global $config;

$host      = $_REQUEST["host"];
$transport = $_REQUEST["transport"];
$user      = $_REQUEST["user"];
if ($transport == "telnet") {
	$port = 23;
	$plugins = "Status,Socket,Telnet,Terminal";
}else{
	$port = 22;
	$plugins = "Status,Socket,SSH,Telnet,Terminal";}

$width_height = explode("x", read_config_option("remote_window"));
$width        = $width_height[0]-18;
$height       = $width_height[1]-18;

if (isset($_REQUEST["host"])) {
	if ($transport == 'ssh') {
		if (0 == 1) {
			print '
			<applet CODEBASE="."  ARCHIVE="jta26.jar" CODE="de.mud.jta.Applet" WIDTH="' . $width . '" HEIGHT="' . $height . '">
				<param name="config" value="./applet.conf">
				<param name="plugins" value="' . $plugins . '">
				<param name="Socket.host" value="' . $host . '">
				<param name="Socket.port" value="' . $port. '">
				<param name="Terminal.fontSize" value="' . read_config_option("remote_font") . '">
				<param name="Terminal.size" value="[' . read_config_option("remote_cols") . ', ' . read_config_option("remote_rows") . ']">
			</applet>';
		}else{
			print '
			<applet
				CODE="com.sshtools.sshterm.SshTermApplet"
				WIDTH="' . $width . '"
				HEIGHT="' . $height . '"
				CODEBASE="."
				ARCHIVE="files/SSHTerm-0.2.3.jar, files/libbrowser.jar, files/SSHVnc.jar, files/SecureTunneling.jar, files/ShiFT.jar, files/j2ssh-common-0.2.7.jar, files/j2ssh-core-0.2.7.jar, files/commons-logging.jar, files/filedrop.jar, files/jce-jdk13-135.jar, files/log4j-1.2.6.jar, files/openssh-pk-1.1.0.jar, files/putty-pk-1.1.0.jar, files/jlirc-unix-soc.jar"
				style="border-style: solid; border-width: 1px; padding-left: 1px; padding-right: 1px; padding-top: 1px; padding-bottom: 1px">
				<param name="sshapps.connection.host" value="' . $host . '">
				<param name="sshapps.connection.connectImmediately"   value="true">
				<param name="sshapps.connection.authenticationMethod" value="password">
				<param name="sshapps.connection.showConnectionDialog" value="false">
				<param name="sshapps.connection.disableHostKeyVerification" value="true">
				<param name="sshapps.connection.userName"  value="' . $user . '">
			</applet>';
		}
	}else{
		print '
		<applet CODEBASE="."  ARCHIVE="jta26.jar" CODE="de.mud.jta.Applet" WIDTH="' . $width . '" HEIGHT="' . $height . '">
			<param name="config" value="./applet.conf">
			<param name="plugins" value="' . $plugins . '">
			<param name="Socket.host" value="' . $host . '">
			<param name="Socket.port" value="' . $port. '">
			<param name="Terminal.fontSize" value="' . read_config_option("remote_font") . '">
			<param name="Terminal.size" value="[' . read_config_option("remote_cols") . ', ' . read_config_option("remote_rows") . ']">
		</applet>';
	}
}
?>
