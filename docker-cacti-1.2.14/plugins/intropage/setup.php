<?php


function plugin_intropage_install ()	{
    // only for hint (intropage as default page)
    api_plugin_register_hook('intropage', 'console_after', 'intropage_display_hint', 'setup.php');

    api_plugin_register_hook('intropage', 'config_settings', 'intropage_config_settings', 'setup.php');
    // pouzivam pro rozsireni formu, kde pak je polozka, ze vychozi je intropage
    api_plugin_register_hook('intropage', 'config_form','intropage_config_form', 'setup.php');

    api_plugin_register_hook('intropage', 'top_header_tabs', 'intropage_show_tab', 'setup.php');
    api_plugin_register_hook('intropage', 'top_graph_header_tabs', 'intropage_show_tab', 'setup.php');

    // kdyz je login_opts == 4, tak ho presmeruju na intropage tab
    api_plugin_register_hook('intropage', 'login_options_navigate', 'intropage_redirect', 'setup.php');

    // muze zapinat intropage jednotlivym uzivatelum
    api_plugin_register_realm('intropage', 'intropage.php', 'Plugin Intropage - view', 1);

}

function intropage_config_form ()	{
    global $fields_user_user_edit_host;
    
    array_push ($fields_user_user_edit_host['login_opts']['items'],array("radio_value"=>"4","radio_caption"=>"Show the Intropage plugin screen in separated tab"));
    array_push ($fields_user_user_edit_host['login_opts']['items'],array("radio_value"=>"5","radio_caption"=>"Show the Intropage plugin screen in console screen"));
}

function intropage_redirect ()	{ // souvisi s login_options_navigate
    global $config;
    
    $lopts = db_fetch_cell('SELECT login_opts FROM user_auth WHERE id=' . $_SESSION['sess_user_id']);


    
    if ($lopts == 3)
	header("Location: " . $config['url_path'] . "plugins/intropage/intropage.php");


    
    if ($lopts == 4)
	header("Location: " . $config['url_path'] . "plugins/intropage/intropage.php");

    if ($lopts == 5)
	header("Location: " . $config['url_path']);

}

function plugin_intropage_uninstall ()	{
    // set default login page
    db_execute ("UPDATE user_auth set login_opts=1 WHERE login_opts in (4,5)");
}

function plugin_intropage_version()	{
    return intropage_version();
}


function intropage_version () {
    return array( 	'name'		=> 'intropage',
			'version' 	=> '0.7',
			'longname'	=> 'Intropage',
			'author'	=> 'Petr Macek',
			'homepage'	=> 'http://www.kostax.cz/cacti',
			'email'		=> 'petr.macek@kostax.cz',
			'url'		=> 'http://www.kostax.cz/cacti/checkforupdates/'
		);
}

function plugin_intropage_check_config () {
	return true;
}


function intropage_config_settings()	{
        global $tabs, $settings, $page_refresh_interval, $graph_timespans;


        if (isset($_SERVER['PHP_SELF']) && basename($_SERVER['PHP_SELF']) != 'settings.php')
                return;

         $tabs["intropage"] = "Intropage";
         $treeList = array_rekey(get_graph_tree_array(null, true), 'id', 'name');

	  /* YOU can CREATE here your settings */
        $defaultallowguest="on";
        $temp = array(
        
                "intropage_ntp_header" => array(
                        "friendly_name" => "NTP settings",
                        "method" => "spacer",
                        ),
                "intropage_ntp_enable" => array(
                        "friendly_name" => "Allow NTP",
                        "description" => "if checked this plugin is allowed to check time against NTP server",
                        "method" => "checkbox",
                        "default" => "on",
                        ),
                "intropage_ntp_server" => array(
                        "friendly_name" => "IP or DNS name of NTP server",
                        "description" => "Insert IP or DNS name of NTP server",
                        "method" => "textbox",
                        "max_length" => 50,
                        "default" => "tak.cesnet.cz",
                        ),
                "intropage_log_analyse_header" => array(
                    "friendly_name" => "Log analyse settings",
	            "method" => "spacer",
                    ),
                "intropage_log_enable" => array(
                        "friendly_name" => "Allow cacti log analyse",
                        "description" => "if checked this plugin is allowed to analyse cacti log file",
                        "method" => "checkbox",
                        "default" => "on",
                        ),
                "intropage_log_rows" => array(
                        "friendly_name" => "Number of lines",
                        "description" => "How many lines of log will be analysed. Big number may causes slow page load.",
                        "method" => "textbox",
                        "max_length" => 5,
                        "default" => "1000",
			),
                "intropage_login_analyse_header" => array(
                    "friendly_name" => "Login analyse settings",
	            "method" => "spacer",
                    ),
                "intropage_login_enable" => array(
                        "friendly_name" => "Allow logins analyse",
                        "description" => "if checked this plugin is allowed to analyse logins file",
                        "method" => "checkbox",
                        "default" => "on",
                        ),
                "intropage_login_analyse_db" => array(
                    "friendly_name" => "Analyze MySQL database",
	            "method" => "spacer",
                    ),
                "intropage_db_check" => array(
                        "friendly_name" => "Allow MySQL database check",
                        "description" => "if checked this plugin is allowed to analyse MySQL database",
                        "method" => "checkbox",
                        "default" => "on",
                        ),
                "intropage_display_header" => array(
                    "friendly_name" => "Display settings",
	            "method" => "spacer",
                    ),
                "intropage_display_layout" => array(
                        "friendly_name" => "Layout",
                        "description" => "Left to right or up to down",
                        "method" => "drop_array",
                        "array" => array("horizontal" => "Horizontal", "vertical" => "Vertical", "bestfit" => "Best fit"),
                        "default" => "1",
                    ),

                "intropage_display_level" => array(
                        "friendly_name" => "Display",
                        "description" => "What will you see",
                        "method" => "drop_array",
                        "array" => array("0" => "Only errors", "1" => "Errors and warnings", "2" => "All",),
                        "default" => "2",
                    ),
                "intropage_display_os_info" => array(
                        "friendly_name" => "Display type of OS and poller type",
                        "description" => "if checked this plugin is displays information about OS and poller",
                        "method" => "checkbox",
                        "default" => "on",
                        ),
                "intropage_display_pie_host" => array(
                        "friendly_name" => "Display pie graph for hosts (up/down/recovering/..)",
                        "description" => "if checked this plugin displays pie graph for hosts. It needs GD library.",
                        "method" => "checkbox",
                        "default" => "on",
                        ),
                "intropage_display_pie_threshold" => array(
                        "friendly_name" => "Display pie graph for thresholds (ok/trigerred/..)",
                        "description" => "if checked this plugin  displays pie graph for thresholds. It needs GD library.",
                        "method" => "checkbox",
                        "default" => "on",
                        ),
                "intropage_display_pie_datasource" => array(
                        "friendly_name" => "Display pie graph for datasources (SNMP/script/ ..)",
                        "description" => "if checked this plugin displays pie graph for data sources. It needs GD library.",
                        "method" => "checkbox",
                        "default" => "on",
                        ),
                "intropage_display_pie_template" => array(
                        "friendly_name" => "Display pie graph for templates (generic/win/printer/..)",
                        "description" => "if checked this plugin displays pie graph for templates. It needs GD library.",
                        "method" => "checkbox",
                        "default" => "on",
                        ),
                "intropage_display_pie_mactrack" => array(
                        "friendly_name" => "Display pie graph for Mactrack plugin",
                        "description" => "if checked this plugin displays pie graph for Mactrack. It needs GD library.",
                        "method" => "checkbox",
                        "default" => "on",
                        ),

                "intropage_display_topx" => array(
                        "friendly_name" => "Display top 5 devices with the worst ping and availability",
                        "description" => "if checked this plugin displays table of hosts with the worse ping and availability",
                        "method" => "checkbox",
                        "default" => "on",
                        ),



        );

        if (isset($settings["intropage"])) {
              $settings["intropage"] = array_merge($settings["intropage"], $temp);
        }else {
              $settings["intropage"]=$temp;
        }
} 


function intropage_show_tab () {
        global $config;
                    
        if (api_user_realm_auth('intropage.php')) {

	    $lopts = db_fetch_cell('SELECT login_opts FROM user_auth WHERE id=' . $_SESSION['sess_user_id']);  		
	    if ($lopts == 1 || $lopts == 2 || $lopts == 3 || $lopts == 4)	{

        
                $cp = false;
                if (basename($_SERVER['PHP_SELF']) == 'intropage.php')
                $cp = true;
                print '<a href="' . $config['url_path'] . 'plugins/intropage/intropage.php"><img src="' . $config['url_path'] . 'plugins/intropage/images/tab_intropage' . ($cp ? '_down': '') . '.gif" alt="intropage"  align="absmiddle" border="0"></a>';
    	    }
        
        }
}

function intropage_display_hint ()       {
    global $config;


    $lopts = db_fetch_cell('SELECT login_opts FROM user_auth WHERE id=' . $_SESSION['sess_user_id']);

    switch ($lopts)	{ // 3=graphs, 4=tab, 5=console
	case "1" : 
	case "2" : 
            if (db_fetch_assoc("select realm_id from user_auth_realm where user_id='" . $_SESSION["sess_user_id"] . "' and user_auth_realm.realm_id=8"))	{
		echo "<b>Hint: </b>If you want to see Intropage plugin as default page (in console or separated tab), you can set it up in Console -> User Management -> User -> Login Options <br/>";
	    }
	    else	
        	echo "<b>Hint: </b>If you want to see Intropage plugin as default page, <a href=\"" . $config['url_path'] . "plugins/intropage/intropage.php?default=true&how=4\">click here </a><BR/>\n";
	break;

	case "3": 
            if (!db_fetch_assoc("select realm_id from user_auth_realm where user_id='" . $_SESSION["sess_user_id"] . "' and user_auth_realm.realm_id=8"))
        	echo "<b>Hint: </b>If you want to see Intropage as default page <a href=\"" . $config['url_path'] . "plugins/intropage/intropage.php?default=true&how=4\">click here </a><br/>\n";
	break;

	case "4": 
            if (db_fetch_assoc("select realm_id from user_auth_realm where user_id='" . $_SESSION["sess_user_id"] . "' and user_auth_realm.realm_id=8"))
		echo "<b>Hint: </b>If you want to see Intropage plugin as default page (in console or separated tab), you can set it up in Console -> User Management -> User -> Login Options <br/>";
    	    else
        	echo "<b>Hint: </b>If you want to see Graphs as default page <a href=\"" . $config['url_path'] . "plugins/intropage/intropage.php?default=true&how=3\">click here </a><br/>\n";
	break;

	case "5" :
	    include_once("./plugins/intropage/include/functions.php");
	    display_information();

            if (db_fetch_assoc("select realm_id from user_auth_realm where user_id='" . $_SESSION["sess_user_id"] . "' and user_auth_realm.realm_id=8"))
		echo "<b>Hint: </b>If you want to see Intropage plugin as default page (in console or separated tab), you can set it up in Console -> User Management -> User -> Login Options <br/>";
    	    else
        	echo "<b>Hint: </b>If you want to see Graphs as deafult page <a href=\"" . $config['url_path'] . "plugins/intropage/intropage.php?default=true&how=5\">click here </a><br/>\n";

	break;    
    }
}

?>