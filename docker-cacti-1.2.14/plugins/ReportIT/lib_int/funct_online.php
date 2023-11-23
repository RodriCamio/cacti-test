<?php
/*
   +-------------------------------------------------------------------------+
   | Copyright (C) 2004-2012 The Cacti Group                                 |
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

function my_id() {
    $whoiam = $_SESSION["sess_user_id"];
    return $whoiam;
}


function my_name() {
    $id     = my_id();
    $myname = db_fetch_cell("SELECT username FROM user_auth WHERE id = $id");
    return $myname;
}


function my_report($report_id, $public = FALSE){
    if(is_numeric($report_id) && $report_id != 0) {
        $user_id  = my_id();

        $sql = "SELECT user_id, public FROM reportit_reports WHERE id=$report_id ";
        $user = db_fetch_row($sql);

        if($user == FALSE) {
	    if(!re_admin()) die_html_custom_error('Permission denied');
	    die_html_custom_error('Not existing');
	}

        if($user_id !== $user['user_id']) {
	    if(re_admin()) return;
	    if($public && $user['public'] == 1) return;
	    die_html_custom_error('Permission denied');
        }
    }
}


function my_template($report_id) {
    $sql 	= "SELECT template_id FROM reportit_reports WHERE id=$report_id";
    $mytemplate = db_fetch_cell($sql);
    return $mytemplate;
}


function locked($template_id, $header=true) {
    $sql	= "SELECT locked FROM reportit_templates WHERE id=$template_id";
    $status	= db_fetch_cell($sql);
    if($status) die_html_custom_error('Template has been locked', true);
}


function other_name($userid) {
    $other_name = db_fetch_cell("SELECT username FROM user_auth WHERE id = $userid");
    return $other_name;
}


function only_viewer() {
    $id		 = my_id();
    $report_viewer = db_fetch_cell("SELECT * FROM user_auth_realm WHERE user_id = $id AND (realm_id = " . REPORTIT_USER_ADMIN . " OR realm_id = " . REPORTIT_USER_OWNER . ")");
    if ($report_viewer == Null || substr_count($_SERVER['REQUEST_URI'], "cc_view.php")) return true;
    return false;
}

function re_owner(){
	$id 	  = my_id();
    $report_owner = db_fetch_cell("SELECT * FROM user_auth_realm WHERE realm_id = " . REPORTIT_USER_OWNER . " AND user_id = $id");
    if ($report_owner == REPORTIT_USER_OWNER) return true;
    return false;
}

function re_admin() {
    $id 	  = my_id();
    $report_admin = db_fetch_cell("SELECT * FROM user_auth_realm WHERE realm_id = " . REPORTIT_USER_ADMIN . " AND user_id = $id");
    if ($report_admin == REPORTIT_USER_ADMIN) return 1;
    return 0;
}


function session_custom_error_message($field, $custom_message, $toplevel_message=2) {
    $_SESSION['sess_error_fields'][$field] = $field;
    //Do not overwrite the first message.
    if(!isset($_SESSION['sess_custom_error'])) 	$_SESSION['sess_custom_error'] = $custom_message;
    if(!isset($_SESSION['sess_messages']) & $toplevel_message !== false) raise_message($toplevel_message);
}


function session_custom_error_display() {
	if(isset($_SESSION['sess_custom_error'])) {
		display_custom_error_message($_SESSION['sess_custom_error']);
		kill_session_var('sess_custom_error');
	}
}

function is_error_message_field($field) {
    if(isset($_SESSION['sess_error_fields'][$field])) return TRUE;
    return FALSE;
}


function stat_autolock_template($template_id) {
    $sql 	= "SELECT COUNT(*) FROM reportit_measurands WHERE template_id=$template_id";
    $count 	= db_fetch_cell($sql);
    if($count != 0) return FALSE;
    return TRUE;
}


function set_autolock_template($template_id) {
    $sql 	= "UPDATE reportit_templates SET locked=1 WHERE id=$template_id";
    db_execute($sql);
}


function update_formulas(& $array) {
    foreach($array as $key => $value) {
	$sql = "UPDATE reportit_measurands SET calc_formula='{$value['calc_formula']}' WHERE id={$value['id']}";
	db_execute($sql);
    }
}


function try_autolock_template($template_id) {
    $sql	= "SELECT COUNT(*) FROM reportit_reports WHERE template_id=$template_id and in_process=1";
    $status	= db_fetch_cell($sql);
    if($status == 0) {
	set_autolock_template($template_id);
	return TRUE;
    }else {
	return FALSE;
    }
}

function check_cacti_version($hash){
	global $config, $hash_version_codes;
	if ($hash_version_codes[$config['cacti_version']] < $hash) return FALSE;
	return TRUE;
}

function check_graph_support(){
	/* Check required PHP extensions: GD Library and Freetype support */
	$loaded_extensions = get_loaded_extensions();
	if(!in_array('gd', $loaded_extensions)) die_html_custom_error("GD library not available - Check your systems configuration", true);
	$gd_info = gd_info();
	if(!$gd_info["FreeType Support"]) die_html_custom_error("GD Freetype Support not available - Check your systems configuration", true);
}

function get_valid_max_rows(){
    /* return the default if a user defined an invalid value for maximum number of rows */
    $session_max_rows = read_graph_config_option("reportit_max_rows");
    if(is_numeric($session_max_rows) & $session_max_rows > 0) {
        return $session_max_rows;
    }else {
        return read_default_graph_config_option("reportit_max_rows");
    }
}

function inc_top_header(){
	global $config;
	if(only_viewer()) {
		include_once(CACTI_BASE_PATH . "/plugins/reportit/header.php");
	}else {
		include_once(CACTI_INCLUDE_PATH . "/top_header.php");
	}
}

?>