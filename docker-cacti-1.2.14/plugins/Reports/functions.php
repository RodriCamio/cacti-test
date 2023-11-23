<?php

function generate_report($report, $force = false, $output = 'S') {
	global $config;
	$from = read_config_option("settings_from_email");
	if ($from == '' && isset($_SERVER['HOSTNAME']))
		$from = "CactiReports@" . $_SERVER['HOSTNAME'];
	$subject = $report['name'];
	$type = $report['rtype'];

	$message = "<h3><center>" . $report['name'] . '</center></h3><ul>';
	$etime = time();
	$multi = array(86400, 604800, 2592000, 31536000);
	$file_array = array();
	$queryrows = db_fetch_assoc("select * from reports_data where reportid=". $report['id'] . ' order by gorder');
	if ($queryrows) {
		foreach($queryrows as $row) {
			$stime = 0;
			if ($row['type'] > 0)
				$stime = $etime - $multi[$row['type']-1];
			$rra = $row['type'];
			$graph = $row['local_graph_id'];
			$file_array[] = array(
				'graph_start'	 => $stime,
				'graph_end'	 => $etime,
				'local_graph_id' => $graph,
				'rra_id' 	 => $rra,
				'file' 	 => '',
				'mimetype' 	 => 'image/png',
				'item'		 => $row['item'],
				'data'		 => $row['data'],
				'filename'	 => $graph);
		}
		report_mail($report['email'], $from, $subject, $message, $file_array, $type, $output);
		if (!$force) {
			$temp = db_fetch_assoc("update reports set lastsent = " . time() . " where id = " . $report['id']);
		}
	}
}

function report_sendnow () {
	foreach($_POST as $t=>$v) {
		if (substr($t, 0,4) == 'chk_') {
			$id = sql_sanitize(substr($t, 4));
			$report = db_fetch_assoc("select * from reports where id = $id LIMIT 1");
			if (isset($report[0]['email'])) {
				generate_report($report[0], true);
			}
		}
	}

	Header('Location: reports.php');
	exit;
}

function report_preview ($report) {
	global $config, $colors;

	$alignment = array('L' => 'Left', 'C' => 'Center', 'R' => 'Right');

	$rep = db_fetch_assoc("select * from reports where id = $report");
	$template_list = db_fetch_assoc("select * from reports_data where reportid = $report order by gorder");
	print '<br><br>';

	html_start_box("", "80%", $colors["header"], "1", "center", "");
	print "<table bgcolor='#FFFFFF' width='100%'><tr><td align='center'>";


	print "<h3><center>" . $rep[0]['name'] . '</center></h3><br><br><ul>';
	$etime = time();
	$multi = array(86400,604800,2592000,31536000);
	foreach ($template_list as $template) {
		if ($template['item'] == 'graph' || $template['item'] == '') {
			$stime = $etime - $multi[$template['type']-1];
			print "<center><img src='../../graph_image.php?local_graph_id=" . $template['local_graph_id'] . "&rra_id=" . $template['type'] . "&graph_start=$stime&graph_end=$etime'></center><br><br><br><br>";
		} else if ($template['item'] == 'text') {
			$align = substr($template['data'], 0, 1);
			$size = substr($template['data'], 1, 2);
			$text = substr($template['data'], 3);
			print "<p align='" . $alignment[$align] . "' style='font-size: " . $size . "pt;'>$text</p>";
		}
	}

	print "</ul></td></tr></table>";
	html_end_box();
}

/* Sends a group of graphs to a user, also used for thresholds */
function report_mail($to, $from, $subject, $message, $filename, $type = 'attach', $output = 'S') {
	global $config;

	$alignment = array('L' => 'Left', 'C' => 'Center', 'R' => 'Right');

	include_once($config["base_path"] . "/plugins/reports/class.phpmailer.php");
	$mail = new PHPMailer();

	$how = read_config_option("settings_how");
	# gandalf: $how was erraneously too high by 1
	if ($how < 0 && $how > 2)
		$how = 0;
	if ($how == 0) {
		$mail->IsMail();									  // set mailer to use PHPs Mailer Class
	} else if ($how == 1) {
		$mail->IsSendmail();								  // set mailer to use Sendmail
		$sendmail = read_config_option("settings_sendmail_path");
		if ($sendmail != '')
			$mail->Sendmail = $sendmail;
	} else if ($how == 2) {
		$mail->IsSMTP();									  // set mailer to use SMTP
		$smtp_host = read_config_option("settings_smtp_host");
		$smtp_port = read_config_option("settings_smtp_port");
		$smtp_username = read_config_option("settings_smtp_username");
		$smtp_password = read_config_option("settings_smtp_password");
		if ($smtp_username != '' && $smtp_password != '') {
			$mail->SMTPAuth = true;
			$mail->Username = $smtp_username;
			$mail->Password = $smtp_password;
		} else {
			# fosstob: $mail was erroneously set to true.
			$mail->SMTPAuth = false;
		}
		$mail->Host = $smtp_host;
		$mail->Port = $smtp_port;
	}

	if ($from == '') {
		$from = read_config_option("settings_from_email");
		$fromname = read_config_option("settings_from_name");
		if ($from == "" && isset($_SERVER['HOSTNAME']))
			$from = "Cacti@" . $_SERVER['HOSTNAME'];
		if ($fromname == "")
			$fromname = "Cacti";
		$mail->From = $from;
		$mail->FromName = $fromname;
	} else {
		$mail->From = $from;
		$mail->FromName = 'Cacti Reports';
	}
	$to = explode(',',$to);

	foreach($to as $t)
		$mail->AddAddress($t);

	$mail->WordWrap = 50;								 // set word wrap to 50 characters
	$mail->IsHTML(true);								  // set email format to HTML
	$mail->Subject = $subject;
	$mail->CreateHeader();

	if ($type == 'attach' || $type == '') {
		$mail->Body	= $message . '<br>';
		$mail->AltBody = strip_tags($message);
		if (is_array($filename)) {
			foreach($filename as $val) {
				if ($val['item'] == '' || $val['item'] == 'graph') {
					$graph_data_array = array("output_flag" => RRDTOOL_OUTPUT_STDOUT, 'graph_start' => $val['graph_start'], 'graph_end' => $val['graph_end']);
					$data = @rrdtool_function_graph($val['local_graph_id'], $val['rra_id'], $graph_data_array);
					if ($data != "") {
						$cid = md5(uniqid(rand()));
						$mail->AddStringEmbedAttachment($data, $val['filename'].'.png', $cid, 'base64', $val['mimetype']);	// optional name
						$mail->Body .= "<br><br><center><img src='cid:$cid'></center>";
					} else {
						$mail->Body .= "<br><center><img src='" . $val['file'] . "'></center>";
						$mail->Body .= "<br><center>Could not open!</center><br>" . $val['file'];
					}
				} else if ($val['item'] == 'text') {
					$align = substr($val['data'], 0, 1);
					$size = substr($val['data'], 1, 2);
					$text = substr($val['data'], 3);
					$mail->Body .= "<p align='" . $alignment[$align] . "' style='font-size: " . $size . "pt;'>$text</p>";
					$mail->AltBody .= "$text\n";
				}
			}
			$mail->AttachAll();
		}
	} else if ($type == 'pdf') {
		$mail->Body = "Report : $subject<br>";
		$mail->Body .= 'Date : ' . date("F j, Y, g:i a", time()) . '<br>';
		$mail->AltBody = "Report : $subject\n";
		$mail->AltBody .= 'Date : ' . date("F j, Y, g:i a", time()) . "\n";
		reports_generate_pdf ($subject, $filename, $mail, $output);
	} else if ($type == 'jpeg') {
		$mail->Body	= $message . '<br>';
		$mail->AltBody = strip_tags($message);
		if (is_array($filename)) {
			foreach($filename as $val) {
				if ($val['item'] == '' || $val['item'] == 'graph') {
					$graph_data_array = array("output_flag" => RRDTOOL_OUTPUT_STDOUT, 'graph_start' => $val['graph_start'], 'graph_end' => $val['graph_end']);
					$data = @rrdtool_function_graph($val['local_graph_id'], $val['rra_id'], $graph_data_array);
					if ($data != "") {
						$cid = md5(uniqid(rand()));
						$jdata = png2jpeg($data, $val);	/* convert png image to jpeg using php-gd */
						$mail->AddStringEmbedAttachment($jdata, $val['filename'].'.jpg', $cid, 'base64', 'image/jpg');	// optional name
						$mail->Body .= "<br><br><center><img src='cid:$cid'></center>";
					} else {
						$mail->Body .= "<br><center><img src='" . $val['file'] . "'></center>";
						$mail->Body .= "<br><center>Could not open!</center><br>" . $val['file'];
					}
				} else if ($val['item'] == 'text') {
					$align = substr($val['data'], 0, 1);
					$size = substr($val['data'], 1, 2);
					$text = substr($val['data'], 3);
					$mail->Body .= "<p align='" . $alignment[$align] . "' style='font-size: " . $size . "pt;'>$text</p>";
					$mail->AltBody .= "$text\n";
				}
			}
			$mail->AttachAll();
		}
	}

	if (!$mail->Send()) {
		cacti_log("Email could not be sent - '" . $mail->ErrorInfo . "'", true, "Reports");
		return;
	}
}

function reports_generate_pdf ($subject, $filename, &$mail, $output = 'S') {
	global $config;
	include_once ("class.fpdf_modified.php");
	$pdf = new PDF_ImageAlpha();

	$pdf -> AddPage();
	$pdf -> SetFont('Arial', 'B', 16);
	$pdf -> SetTitle($subject);
	$pdf -> Cell(0, 0, $subject, 0, 1, 'C');
	$ypos = 25;

	$tempdir = read_config_option('path_reports_temp');
	if (!isset($tempdir)) {
		$tempdir = '/tmp/';
	} else if ($tempdir == '') {
		$tempdir = '/tmp/';
	}
	if (substr($tempdir, -1, 1) != '/' && substr($tempdir, -1, 1) != "\\") {
		$tempdir .= '/';
	}

	if (is_array($filename)) {
		foreach($filename as $val) {
			if ($val['item'] == '' || $val['item'] == 'graph') {
				$graph_data_array = array("output_flag" => RRDTOOL_OUTPUT_STDOUT, 'graph_start' => $val['graph_start'], 'graph_end' => $val['graph_end']);
				$graph_data_array["graph_height"] = 100;
				$graph_data_array["graph_width"] = 492;
				$data = @rrdtool_function_graph($val['local_graph_id'], $val['rra_id'], $graph_data_array);
				if ($data != "") {
					$fn =  $tempdir . $val['local_graph_id'] . '-' . $val['rra_id'] . '.png';
					# $fn = '\\tmp\\380-2.png';
					$f = fopen($fn, 'wb');
					fwrite($f, $data);
					fclose($f);

					$h = getimagesize($fn);
					$h = ($h[1] / 2.75);
					if ($ypos >= 220) {
						$ypos = 25;
						$pdf->AddPage();
					}
					$pdf->SetY($ypos);
					$pdf->Image($fn, 1, $ypos, 0, 0, 'PNG');
					unlink($fn);
					$ypos += $h;
				}
			} else if ($val['item'] == 'text') {
				if ($ypos >= 220) {
					$ypos = 25;
					$pdf->AddPage();
				}
				$align = substr($val['data'], 0, 1);
				$size = substr($val['data'], 1, 2);
				$text = substr($val['data'], 3);

				$pdf -> SetFontSize($size);
				$ypos += 5;
				$pdf -> SetY($ypos);
				$pdf -> Cell(0, 0, $text, 0, 1, $align);
				$pdf -> SetFontSize(16);
				$ypos += 5;
				$pdf -> SetY($ypos);

			}
		}
	}

	$s = str_replace(array('"', "'", '.', ',', '`', '!', '@', '#', '$', '%', '^', '&', '*', "\\", '/', '<', '>'), '', $subject) . '.pdf';

	$pdf -> Close();
	if ($output == 'S')
		$p = $pdf -> Output($s, $output);
	else if ($output == '')
		$pdf -> Output();


	if (is_array($filename)) {
		$mail -> AddStringAttachment($p, $s);
		$mail -> AttachAll();
		$mail->AltBody .= "Note : PDF Report Attached\n";
		$mail->Body .= 'Note : PDF Report Attached<br>';
	} else {
		$mail->AltBody .= "Error : No Graphs in Report\n";
		$mail->Body .= 'Error : No Graphs in Report<br>';
	}
}

function rdraw_actions_dropdown($actions_array) {
	global $config;

	?>
	<table align='center' width='100%'>
		<tr>
			<td width='1' valign='top'>
				<img src='<?php echo $config['url_path']; ?>images/arrow.gif' alt='' align='absmiddle'>&nbsp;
			</td>
			<td align='right'>
				Choose an action:
				<?php form_dropdown("drp_action",$actions_array,"","","1","","");?>
			</td>
			<td width='1' align='right'>
				<input type='submit' name='Go' value='Go'>
			</td>
		</tr>
	</table>

	<input type='hidden' name='action' value='actions'>
	<?php
}

/**
 * convert png images stream to jpeg using php-gd
 *
 * @param unknown_type $png_data	the png image as a stream
 * @param unknown_type $val			some data used for the image
 * @return unknown					the jpeg image as a stream
 */
function png2jpeg ($png_data, $val) {
	global $config;

	/* need scratch dir for php-gd file operations */
	$tempdir = read_config_option('path_reports_temp');
	if (!isset($tempdir)) {
		$tempdir = '/tmp/';
	} else if ($tempdir == '') {
		$tempdir = '/tmp/';
	}
	if (substr($tempdir, -1, 1) != '/' && substr($tempdir, -1, 1) != "\\") {
		$tempdir .= '/';
	}

	if ($png_data != "") {
		$fn = $tempdir . $val['local_graph_id'] . '-' . $val['rra_id'] . '.png';

		/* write rrdtool's png file to scratch dir */
		$f = fopen($fn, 'wb');
		fwrite($f, $png_data);
		fclose($f);

		/* create php-gd image object from file */
		$im = imagecreatefrompng($fn);
		if (!$im) {								/* check for errors */
			$im = ImageCreate (150, 30);		/* create an empty image */
			$bgc = ImageColorAllocate ($im, 255, 255, 255);
			$tc  = ImageColorAllocate ($im, 0, 0, 0);
			ImageFilledRectangle ($im, 0, 0, 150, 30, $bgc);
			/* print error message */
			ImageString($im, 1, 5, 5, "Error while opening: $imgname $fn", $tc);
		}

		ob_start(); // start a new output buffer to capture jpeg image stream
		imagejpeg($im);	// output to buffer
		$ImageData = ob_get_contents(); // fetch image from buffer
		$ImageDataLength = ob_get_length();
		ob_end_clean(); // stop this output buffer
		imagedestroy($im); //clean up

		unlink($fn); // delete scratch file
	}
	return $ImageData;
}
