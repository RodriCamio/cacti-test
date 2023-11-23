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


function export_to_PDF(&$data) {

}


function export_to_CSV(&$data) {
	global $config, $run_scheduled;

	$eol			= "\r\n";
	$rows			= '';
	$header			= '';
	$info_line		= '';
	$tab_head_1		= '';
	$tab_head_2		= '';
	$data_sources	= array();
	$csv_c_sep		= array(",", ";", "\t", " ");
	$csv_d_sep		= array(",", ".");
	$subhead		= array("<br>","<i>","<b>","<p>","<u>","</br>","</i>","</b>","</p>","</u>",
							"|t1| - |t2|", "|t1|-|t2|", "|t1|", "|t2|", "|tmz|",
							"|d1| - |d2|", "|d1|-|d2|", "|d1|", "|d2|");
	$measurands		= isset($_REQUEST['measurand'])? $_REQUEST['measurand'] : '-1';
	$datasources	= isset($_REQUEST['data_source']) ? $_REQUEST['data_source'] : '-1';

	$report_ds_alias	= $data['report_ds_alias'];
	$report_data		= $data['report_data'];
	$report_results		= $data['report_results'];
	$report_measurands	= $data['report_measurands'];
	$report_variables	= $data['report_variables'];

	/* load user settings */
	if($run_scheduled !== true) {
		/* request via web */
		$no_formatting = 0;
		$c_sep = $csv_c_sep[read_graph_config_option('reportit_csv_column_s')];
		$d_sep = $csv_d_sep[read_graph_config_option('reportit_csv_decimal_s')];
	}else {
		/* request via cli */
		$no_formatting = $report_data['autoexport_no_formatting'];
		$c_sep = $csv_c_sep[get_graph_config_option('reportit_csv_column_s', $report_data['user_id'])];
		$d_sep = $csv_d_sep[get_graph_config_option('reportit_csv_decimal_s', $report_data['user_id'])];
	}

	/* form the export header */
	$header = read_config_option('reportit_exp_header');
	$header = str_replace("<cacti_version>", "$eol# Cacti: " . $config['cacti_version'], $header);
	$header = str_replace("<reportit_version>", " ReportIt: " . reportit_version('version'), $header);

	/* compose additional informations */
	$report_settings = array('Report title'	=> "{$report_data['description']}",
							 'Owner'		=> "{$report_data['owner']}",
							 'Template'		=> "{$report_data['template_name']}",
							 'Start'		=> "{$report_data['start_date']}",
							 'End'			=> "{$report_data['end_date']}",
							 'Last Run'		=> "{$report_data['last_run']}");

	$ds_description = explode('|', $report_data['ds_description']);
	/* read out data sources */
	if($datasources > -1) {
		$ds_description = array($ds_description[$datasources]);
	}elseif($datasources < -1) {
		$ds_description = array('overall');
	}

	/* read out the result ids */
	list($rs_ids, $rs_cnt) = explode('-', $report_data['rs_def']);
	$rs_ids = ($rs_ids == '') ? FALSE : explode('|', $rs_ids);
	if($measurands != '-1' & $rs_ids !== FALSE) {
		$rs_ids = array($_REQUEST['measurand']);
		$rs_cnt = 1;
	}

	/* sort out all measurands which shouldn't be visible */
	if($rs_ids !== FALSE & sizeof($rs_ids)>0) {
		foreach($rs_ids as $key => $id) {
			if(!isset($data['report_measurands'][$id]['visible']) ||
				$data['report_measurands'][$id]['visible'] == 0) {
					$rs_cnt--;
					unset($rs_ids[$key]);
			}
		}
	}

	if($datasources < 0) {
		/* read out the 'spanned' ids */
		list($ov_ids, $ov_cnt)	 = explode('-', $report_data['sp_def']);
		$ov_ids = ($ov_ids == '') ? FALSE : explode('|', $ov_ids);
		if($measurands != '-1' & $ov_ids !== FALSE) {
			$ov_ids = array($_REQUEST['measurand']);
			$ov_cnt = 1;
		}

		/* sort out all measurands which shouldn't be visible */
		if($ov_ids !== FALSE & sizeof($ov_ids)>0) {
			foreach($ov_ids as $key => $id) {
				if(!isset($data['report_measurands'][$id]['visible']) ||
					$data['report_measurands'][$id]['visible'] == 0) {
						$ov_cnt--;
						unset($ov_ids[$key]);
				}
			}
		}
		if($measurands == -1 ) {
			if($ov_cnt >0 & !in_array('overall', $ds_description)) {
				$ds_description[]= 'overall';
			}
		}elseif(in_array($measurands, $ov_ids)) {
			if($ov_cnt >0 & !in_array('overall', $ds_description)) {
				$ds_description = array('overall');
			}
		}
	}

	/* create puffered CSV output */
	ob_start();

	/* report header */
	echo "$header $eol";

	/* report settings */
	echo "$eol";
	foreach($report_settings as $key => $value) echo "# $key: $value $eol";

	/* defined variables */
	echo "$eol # Variables: $eol";
	foreach($report_variables as $var) echo "# {$var['name']}: {$var['value']} $eol";

	/* build a legend to explain the abbreviations of measurands */
	echo "$eol # Legend: $eol";
	foreach($report_measurands as $id) echo "# {$id['abbreviation']}: {$id['description']} $eol";

	/* print table header */
	for($i = 1; $i < 8; $i++) $tab_head_1 .= "$c_sep";
	$tab_head_2 = $tab_head_1;
	foreach($ds_description as $datasource){
		$name = ($datasource != 'overall') ? $rs_ids : $ov_ids;

		if($name !== FALSE) {
			foreach($name as $id) {
				if(is_array($report_ds_alias) && array_key_exists($datasource, $report_ds_alias) && $report_ds_alias[$datasource] != '') {
					$tab_head_1 .= $report_ds_alias[$datasource] . $c_sep;
				}else{
					$tab_head_1 .= $datasource . $c_sep;
				}
				$var = ($datasource != 'overall') ? $datasource.'__'.$id : 'spanned__'.$id;
				$tab_head_2 .= $report_measurands[$id]['abbreviation'] . "[" . $report_measurands[$id]['unit'] . "]" . $c_sep;
			}
		}
	}
	echo "$eol $tab_head_1 $eol $tab_head_2 $eol";


	/* print results */
	foreach($report_results as $result){
		echo '"'. $result['name_cache'] .'"' . $c_sep;
		echo '"'. str_replace($subhead, '', $result['description']) .'"' . $c_sep;
		echo '"'. $result['start_day'] .'"' . $c_sep;
		echo '"'. $result['end_day'] .'"' . $c_sep;
		echo '"'. $result['start_time'] .'"' . $c_sep;
		echo '"'. $result['end_time'] .'"' . $c_sep;
		echo '"'. $result['timezone'].'"' . $c_sep;

		foreach($ds_description as $datasource) {
			$name = ($datasource != 'overall') ? $rs_ids : $ov_ids;
			if($name !== FALSE) {
				foreach($name as $id) {
					$rounding = $report_measurands[$id]['rounding'];
					$data_type = $report_measurands[$id]['data_type'];
					$data_precision = $report_measurands[$id]['data_precision'];

					$var = ($datasource != 'overall') ? $datasource.'__'.$id : 'spanned__'.$id;
					$value = ($result[$var] == NULL)? 'NA': str_replace(".", $d_sep, (($no_formatting) ? $result[$var] : get_unit($result[$var], $rounding, $data_type, $data_precision) ));
					echo '"'. $value .'"' . $c_sep;
				}
			}
		}
		echo "$eol";
	}

	return ob_get_clean();
}


function export_to_XML(&$data) {
	global $config, $run_scheduled;

	$eol		= "\r\n";
	$add_infos	= '';
	$output		= '';
	$header		= '';
	$subhead	= array("<br>","<i>","<b>","<p>","<u>","</br>","</i>","</b>","</p>","</u>",
						"|t1| - |t2|", "|t1|-|t2|", "|t1|", "|t2|", "|tmz|",
						"|d1| - |d2|", "|d1|-|d2|", "|d1|", "|d2|");
	$mea		= array();

	transform_htmlspecialchars($data);

	$report_data		= $data['report_data'];
	$report_results		= $data['report_results'];
	$report_measurands	= $data['report_measurands'];
	$report_variables	= $data['report_variables'];
	$ds_description		= explode('|', $report_data['ds_description']);

	$no_formatting		= ($run_scheduled !== true) ? 0 : $report_data['autoexport_no_formatting'];

	/* form the export header */
	$header = read_config_option('reportit_exp_header');
	$header = str_replace("<cacti_version>", "\r\nCacti: " . $config['cacti_version'], $header);
	$header = str_replace("<reportit_version>", " ReportIt: " . reportit_version('version'), $header);

	/* compose additional informations */
	$report_settings = array('title'	=> "{$report_data['description']}",
							 'owner'	=> "{$report_data['owner']}",
							 'template'	=> "{$report_data['template_name']}",
							 'start'	=> "{$report_data['start_date']}",
							 'end'		=> "{$report_data['end_date']}",
							 'last_run'	=> "{$report_data['last_run']}");

	/* read out the result ids */
	list($rs_ids, $rs_cnt) = explode('-', $report_data['rs_def']);
	$rs_ids = ($rs_ids == '') ? FALSE : explode('|', $rs_ids);

	/* read out the 'spanned' ids */
	list($ov_ids, $ov_cnt)	 = explode('-', $report_data['sp_def']);
	$ov_ids = ($ov_ids == '') ? FALSE : explode('|', $ov_ids);
	if($ov_cnt >0) $ds_description[]= 'overall';

	/* create puffered xml output */
	ob_start();
	echo "<?xml version='1.0' encoding=\"UTF-8\"?>$eol";
	echo "<!--{$header} -->";
	echo "<cacti>$eol<report>$eol<settings>$eol";

	foreach($report_settings as $key => $value) echo "<$key>$value</$key>$eol";
	echo "</settings>$eol<variables>$eol";

	foreach($report_variables as $variable) {
		echo "<variable>$eol";
		foreach($variable as $key => $value) echo "<$key>$value</$key>$eol";
		echo "</variable>$eol";
	}
	echo "</variables>$eol<measurands>$eol";

	foreach($report_measurands as $measurand){
		$id = $measurand['id'];
		$mea[$id]['abbreviation']	= $measurand['abbreviation'];
		$mea[$id]['visible']		= $measurand['visible'];
		$mea[$id]['unit']			= $measurand['unit'];
		$mea[$id]['rounding']		= $measurand['rounding'];
		$mea[$id]['data_type']		= $measurand['data_type'];
		$mea[$id]['data_precision']	= $measurand['data_precision'];

		echo "<measurand>$eol";
		echo "<abbreviation>{$measurand['abbreviation']}</abbreviation>$eol";
		echo "<description>{$measurand['description']}</description>$eol";
		echo "</measurand>$eol";
	}
	echo "</measurands>$eol<data_items>$eol";

	foreach($report_results as $results){
		echo "<item>$eol";
		echo "<description>{$results['name_cache']}</description>$eol";
		echo "<subhead>". str_replace($subhead, '', $results['description']) ."</subhead>$eol";
		echo "<start_day>{$results['start_day']}</start_day>$eol";
		echo "<end_day>{$results['end_day']}</end_day>$eol";
		echo "<start_time>{$results['start_time']}</start_time>$eol";
		echo "<end_time>{$results['end_time']}</end_time>$eol";
		echo "<time_zone>{$results['timezone']}</time_zone>$eol";
		echo "<results>$eol";

		foreach($ds_description as $datasource) {
			echo "<$datasource>$eol";
			$name = ($datasource != 'overall') ? $rs_ids : $ov_ids;
			if($name !== FALSE) {
				foreach($name as $id) {
					$var			= ($datasource != 'overall') ? $datasource.'__'.$id : 'spanned__'.$id;
					$abbr			= strtolower($mea[$id]['abbreviation']);
					$value			= $results[$var];
					$rounding		= $mea[$id]['rounding'];
					$data_type		= $mea[$id]['data_type'];
					$data_precision	= $mea[$id]['data_precision'];
					$value  = ($value == NULL)? 'NA' : (($no_formatting) ? $value : get_unit($value, $rounding, $data_type, $data_precision) );
					echo "<$abbr measurand=\"{$mea[$id]['abbreviation']}\" unit=\"{$mea[$id]['unit']}\">$eol";
					echo "$value";
					echo "</$abbr >$eol";
				}
			}
			echo "</$datasource>$eol";
		}

		echo"</results>$eol";
		echo "</item>$eol";
	}
	echo "</data_items>$eol";


	echo "</report>$eol</cacti>$eol";
	$output = utf8_encode(ob_get_clean());

	return $output;
}


function export_to_SML(&$data){

	$eol = "\r\n";

	$sml_workbook	= "<Workbook xmlns=\"urn:schemas-microsoft-com:office:spreadsheet\"$eol
							xmlns:o=\"urn:schemas-microsoft-com:office:office\"$eol
							xmlns:x=\"urn:schemas-microsoft-com:office:excel\"$eol
							xmlns:ss=\"urn:schemas-microsoft-com:office:spreadsheet\"$eol
							xmlns:html=\"http://www.w3.org/TR/REC-html40\">$eol";

	$sml_properties =  "<DocumentProperties xmlns=\"urn:schemas-microsoft-com:office:office\">$eol
							<Created>2007-04-17T09:28:01Z</Created>$eol
						</DocumentProperties>$eol";

	$sml_styles	 = " <Styles>$eol
								<Style ss:ID='theme_1'>$eol
									<Interior/>$eol
									<Font/>$eol
									<Borders/>$eol
								</Style>$eol
								<Style ss:ID='theme_2'>$eol
									<Interior ss:Color='#00356f' ss:Pattern='Solid'/>$eol
								</Style>$eol
						</Styles>$eol";

	$footer = "</Workbook>";

	/* create puffered xml output */
	ob_start();
	echo "<?xml version='1.0' encoding='UTF-8'?>";
	echo "<?mso-application progid='Excel.Sheet'?>";
	echo $sml_workbook;
	echo $sml_properties;
	echo $sml_styles;
	echo new_worksheet($data, $sml_styles);
	echo $footer;
	$output = utf8_encode(ob_get_clean());

	return $output;
}


function new_worksheet(&$data, &$styles){
	global $config, $run_scheduled;

	$eol			= "\r\n";
	$rows			= '';
	$header			= '';
	$info_line		= '';
	$tab_head_1		= '';
	$tab_head_2		= '';
	$data_sources	= array();
	$csv_c_sep		= array(",", ";", "\t", " ");
	$csv_d_sep		= array(",", ".");
	$subhead		= array("<br>","<i>","<b>","<p>","<u>","</br>","</i>","</b>","</p>","</u>",
							"|t1| - |t2|", "|t1|-|t2|", "|t1|", "|t2|", "|tmz|",
							"|d1| - |d2|", "|d1|-|d2|", "|d1|", "|d2|");
	$measurands		= isset($_REQUEST['measurand'])? $_REQUEST['measurand'] : '-1';
	$datasources	= isset($_REQUEST['data_source']) ? $_REQUEST['data_source'] : '-1';

	/* except serialized data */

	$report_ds_alias	= $data['report_ds_alias'];
	$report_data		= $data['report_data'];
	$report_results		= $data['report_results'];
	$report_measurands	= $data['report_measurands'];
	$report_variables	= $data['report_variables'];

	$no_formatting		= ($run_scheduled !== true) ? 0 : $report_data['autoexport_no_formatting'];

	/* form the export header */
	$header = read_config_option('reportit_exp_header');
	$header = str_replace("<cacti_version>", " Cacti: " . $config['cacti_version'], $header);
	$header = str_replace("<reportit_version>", " ReportIt: " . reportit_version('version'), $header);

	/* compose additional informations */
	$report_settings = array('Report title' => "{$report_data['description']}",
							 'Owner'		=> "{$report_data['owner']}",
							 'Template'	 => "{$report_data['template_name']}",
							 'Start'		=> "{$report_data['start_date']}",
							 'End'		  => "{$report_data['end_date']}",
							 'Last Run'	 => "{$report_data['last_run']}");

	$ds_description = explode('|', $report_data['ds_description']);
	/* read out data sources */
	if($datasources > -1) {
		$ds_description = array($ds_description[$datasources]);
	}elseif($datasources < -1) {
		$ds_description = array('overall');
	}

	/* read out the result ids */
	list($rs_ids, $rs_cnt) = explode('-', $report_data['rs_def']);
	$rs_ids = ($rs_ids == '') ? FALSE : explode('|', $rs_ids);
	if($measurands != '-1' & $rs_ids !== FALSE) {
		$rs_ids = array($_REQUEST['measurand']);
		$rs_cnt = 1;
	}

	/* sort out all measurands which shouldn't be visible */
	if($rs_ids !== FALSE & sizeof($rs_ids)>0) {
		foreach($rs_ids as $key => $id) {
			if(!isset($data['report_measurands'][$id]['visible']) ||
				$data['report_measurands'][$id]['visible'] == 0) {
					$rs_cnt--;
					unset($rs_ids[$key]);
			}
		}
	}

	if($datasources < 0) {
		/* read out the 'spanned' ids */
		list($ov_ids, $ov_cnt)	 = explode('-', $report_data['sp_def']);
		$ov_ids = ($ov_ids == '') ? FALSE : explode('|', $ov_ids);
		if($measurands != '-1' & $ov_ids !== FALSE) {
			$ov_ids = array($_REQUEST['measurand']);
			$ov_cnt = 1;
		}

		/* sort out all measurands which shouldn't be visible */
		if($ov_ids !== FALSE & sizeof($ov_ids)>0) {
			foreach($ov_ids as $key => $id) {
				if(!isset($data['report_measurands'][$id]['visible']) ||
					$data['report_measurands'][$id]['visible'] == 0) {
						$ov_cnt--;
						unset($ov_ids[$key]);
				}
			}
		}
		if($measurands == -1 ) {
			if($ov_cnt >0 & !in_array('overall', $ds_description)) {
				$ds_description[]= 'overall';
			}
		}elseif(in_array($measurands, $ov_ids)) {
			if($ov_cnt >0 & !in_array('overall', $ds_description)) {
				$ds_description = array('overall');
			}
		}
}

	/* create puffered CSV output */
	ob_start();

	/* worksheet header */
	echo "\t<Worksheet ss:Name='{$report_data['description']}'>$eol";
	echo "\t\t<Table ss:StyleID='theme_1'>$eol";

	/* report header */
	echo sml_cell($header, true);

	/* report settings */
	echo sml_cell('', true);
	foreach($report_settings as $key => $value) {
		echo sml_cell("# $key: $value", true);
	}

	/* defined variables */
	echo sml_cell('', true);
	echo sml_cell('# Variables:', true);
	foreach($report_variables as $var) {
		echo sml_cell("# {$var['name']}: {$var['value']}", true);
	}

	/* build a legend to explain the abbreviations of measurands */
	echo sml_cell('# Legend:', true);
	foreach($report_measurands as $id) {
		echo sml_cell ("# {$id['abbreviation']}: {$id['description']}", true);
	}

	/* print table header */
	echo sml_cell('', true);

	echo "\t\t\t<Row>$eol";
	for($i = 1; $i < 8; $i++) $tab_head_1 .= sml_cell('');
	$tab_head_2 = $tab_head_1;
	foreach($ds_description as $datasource){
		$name = ($datasource != 'overall') ? $rs_ids : $ov_ids;

		if($name !== FALSE) {
			foreach($name as $id) {
				if(is_array($report_ds_alias) && array_key_exists($datasource, $report_ds_alias) && $report_ds_alias[$datasource] != '') {
					$tab_head_1 .= sml_cell($report_ds_alias[$datasource]);
				}else{
					$tab_head_1 .= sml_cell($datasource);
				}
				$var = ($datasource != 'overall') ? $datasource.'__'.$id : 'spanned__'.$id;
				$tab_head_2 .= sml_cell($report_measurands[$id]['abbreviation'] . "[" . $report_measurands[$id]['unit'] . "]");
			}
		}
	}
	echo "$eol $tab_head_1\t\t\t</Row>$eol\t\t\t<Row>$tab_head_2 $eol";
	echo "\t\t\t</Row>$eol";

	/* print results */
	foreach($report_results as $result){
		echo "\t\t\t<Row>$eol";
		echo sml_cell($result['name_cache']);
		echo sml_cell(str_replace($subhead, '', $result['description']));
		echo sml_cell($result['start_day']);
		echo sml_cell($result['end_day']);
		echo sml_cell($result['start_time']);
		echo sml_cell($result['end_time']);
		echo sml_cell($result['timezone']);

		foreach($ds_description as $datasource) {
			$name = ($datasource != 'overall') ? $rs_ids : $ov_ids;
			if($name !== FALSE) {
				foreach($name as $id) {
					$var = ($datasource != 'overall') ? $datasource.'__'.$id : 'spanned__'.$id;
					$rounding = $report_measurands[$id]['rounding'];
					$data_type = $report_measurands[$id]['data_type'];
					$data_precision = $report_measurands[$id]['data_precision'];

					$value = $result[$var];
					$value = ($value == NULL)? 'NA' : (($no_formatting) ? $value : get_unit($value, $rounding, $data_type, $data_precision) );
					echo sml_cell($value);
				}
			}
		}
		echo "\t\t\t</Row>$eol";
	}

	/* print worksheet footer */
	echo "\t\t</Table>$eol";
	echo "\t</Worksheet>$eol";

	return ob_get_clean();
}


function sml_cell($data, $row=false, $styleID=false){

	$eol = "\r\n";

	$data_style = is_numeric($data) ? " ss:Type='Number'"
									: " ss:Type='String'";

	$cell_style = ($styleID == false)	? ''
										: " ss:StyleID='$styleID'";

	/* form cell output */
	$cell = "\t\t\t\t<Cell $cell_style>$eol\t\t\t\t\t<Data $data_style>$data</Data>$eol\t\t\t\t</Cell>$eol";

	/* add row tags if required */
	if ($row !==false) $cell = "\t\t\t<Row>$eol" . "$cell" . "\t\t\t</Row>$eol";

	return $cell;
}