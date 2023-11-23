<?php
//include ("shapes.php");

// Create an image twice the size so we can resize and clean the lines
$image = imagecreatetruecolor(($_GET['x'] + (100))*2, ($_GET['y'])*2);
$white = imagecolorexact($image, 255, 255, 255);
$black = imagecolorexact($image, 0, 0, 0);
$offwht = imagecolorexact($image, 250, 250, 250);//Legend background colour

// Set the white background transparent
imagecolortransparent($image, $white);

// Set the color table
$color[0] = imagecolorallocate($image, 235, 70, 70); // red
$color[1] = imagecolorallocate($image, 70, 235, 70); // green
$color[2] = imagecolorallocate($image, 70, 70, 235); // blue
$color[3] = imagecolorallocate($image, 235, 70, 235); // magenta
$color[4] = imagecolorallocate($image, 255, 255, 70); // yellow
$color[5] = imagecolorallocate($image, 70, 235, 235); //cyan
$color[6] = imagecolorallocate($image, 168, 168, 168); // grey
$color_dark[0] = imagecolorallocate($image, 168, 70, 70); // Dark red
$color_dark[1] = imagecolorallocate($image, 70, 168, 70); // Dark green
$color_dark[2] = imagecolorallocate($image, 70, 70, 168); // Dark blue
$color_dark[3] = imagecolorallocate($image, 168, 70, 168); // magenta
$color_dark[4] = imagecolorallocate($image, 168, 168, 70); // Dark yellow
$color_dark[5] = imagecolorallocate($image, 70, 168, 168); //cyan
$color_dark[6] = imagecolorallocate($image, 0, 0, 0); //black

// Fill the background with the tramsparent color
imagefill ($image, 0, 0, $white);

// initialize the variables    
$sum = 0; $width = 0;
$c = 0 ;
$data = array();
$lbl = array();
$val = array();
$start = 0; $end=0; // clear the start and end variables

// Grab the values from the main page
$_GET['values'] = array_slice ($_GET['values'],0,6, true);

// Calculate the total
foreach ($_GET['values'] as $value)	{
  $sum+=$value;
}

// input the values into the data arrays and grab the item count 
foreach ($_GET['values'] as $key=>$value)	{

	$data[$c]=round((($value*360)/$sum));
	$lbl[$c] = $key;
	if (strlen($key) > $width) { $width = strlen($key);} 
	$val[$c] = $value;
	$c++;
}

//Create the 3D bottom effect
for ($i = 120; $i > 100; $i--) {

$start = 0; $end=0; // clear the start and end variables

	for ($count = 0; $count <> $c; $count++) {

		$end = ($data[$count] + $start);
		if ($end > 0 && $start != $end){ // Skill null or zero values
			imagefilledarc($image, 110 , $i, 200,100, $start, $end, $color_dark[$count], IMG_ARC_PIE);
		}
		$start = $end;
	} 
}

//Draw top pie chart and add the legend
/*
imagefillroundedrect($image, 235, 2, 696, 140, 15, $color[6]);//create shadow effect for Legend
imagefillroundedrect($image, 233, 0, 693, 138, 15, $offwht); //Legend Background

imagefillroundedrect($image, 10, 210, 200, 260, 15, $color[6]);//create shadow effect for Total
imagefillroundedrect($image, 3, 208, 197, 258, 15, $offwht); //Total Background
*/
$start = 0; $end=0; // clear the start and end variables

for ($count = 0; $count <> $c; $count++) {

	$end = ($data[$count] + $start);
	if ($end > 0 && $start != $end){ // Skill null or zero values
		imagefilledarc($image, 110 , 100, 200,100, $start, $end, $color[$count], IMG_ARC_PIE);
	}
	$start = $end;

  // Draw the legend
  imagefilledrectangle($image, 250,30+($count*10)+(($count+1)*10),265,30+($count*10)+($count*10)+24, $color[$count]);
  
} 

// Reduce the image to antialiase the final image
$imageOut = imagecreatetruecolor($_GET['x'], $_GET['y']);
imagecopyresampled($imageOut, $image, 0, 0, 0, 0, $_GET['x'] + (100), $_GET['y'], ($_GET['x'] + (100))*2, ($_GET['y'])*2);

// Set the white background transparent
imagecolortransparent($imageOut, $white);

// Add Labels to the Legend
for ($count = 0; $count <> $c; $count++) {
  imagestring ($imageOut, 2, 140,15+(($count*5)+(($count + .5)*5)),  "$lbl[$count]: $val[$count]",$black);
}
imagestring ($imageOut, 3, 140,85, "Total: $sum", $black);

header("Content-type: image/png");
imagepng($imageOut);
imagedestroy($imageOut);

?>
