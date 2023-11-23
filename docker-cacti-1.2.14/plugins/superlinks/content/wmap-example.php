<?php
# include a single weathermap in a page.
# IMPORTANT NOTE: this goes around the back of the weathermap permissions system
# If you want to limit access to this map, then limit access to this superlinks page too!
#

   # which weathermap to include?
   $mapid = 11;

	print "<div id=\"overDiv\" style=\"position:absolute; ";
	print "visibility:hidden; z-index:1000;\"></div>\n";
        print "<script type=\"text/javascript\" src=\"../weathermap/overlib.js\">";
	print "<!-- overLIB (c) Erik Bosrup --></script> \n";

    $fd = fopen("plugins/weathermap/output/weathermap_".$mapid.".html","r");

    if($fd) {
       while(!feof($fd)) {
           $buffer = fgets($fd,4096);
           print $buffer;
       }
	fclose($fd);
    }
?>
