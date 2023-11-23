<?php

function tabimage($text='ABC123',$source='blank-tab-blue.gif',$size=0)
{
        $orig_cwd = getcwd();
        chdir(dirname(__FILE__));
        $originalpath = getenv('GDFONTPATH');
        putenv('GDFONTPATH=' . realpath('.'));

        //88 x 37
        if($size==0)
        {
                $possibles = array(
                        array("DejaVuSans-Bold.ttf",10,false),
                        array("DejaVuSansCondensed-Bold.ttf",10,false),
                        array("DejaVuSans-Bold.ttf",9,false),
                        array("DejaVuSansCondensed-Bold.ttf",9,false),
                        array("DejaVuSans-Bold.ttf",8,false),
                        array("DejaVuSansCondensed-Bold.ttf",8,false),
                        array("DejaVuSans-Bold.ttf",7,false),
                        array("DejaVuSansCondensed-Bold.ttf",7,false),
                        // Now, try the same last few, but with two lines of text
                        //array("DejaVuSans-Bold.ttf",8,true),
                        //array("DejaVuSansCondensed-Bold.ttf",8,true),
                        array("DejaVuSans-Bold.ttf",7,true),
                        array("DejaVuSansCondensed-Bold.ttf",7,true)
                );
                $y = 30;
                $x = 44;
                $wlimit = 72;
                $wrapsize = 12;
        }

        // 60x25
        if($size==1)
        {
                $possibles = array(
                        array("DejaVuSans-Bold.ttf",7,false),
                        array("DejaVuSansCondensed-Bold.ttf",7,false),
                        // Now, try the same last few, but with two lines of text
                        //array("DejaVuSans-Bold.ttf",8,true),
                        //array("DejaVuSansCondensed-Bold.ttf",8,true),
                        // array("DejaVuSans-Bold.ttf",7,true),
                        array("DejaVuSansCondensed-Bold.ttf",7,true)
                );
                $y = 21;
                $x = 30;
                $wlimit = 52;
                $wrapsize = 9;
        }

		
                $tab_template = imagecreatefromgif( realpath(dirname(__FILE__).'/images/'.$source) );
                $w = imagesx($tab_template);
                $h = imagesy($tab_template);

                $tab = imagecreatetruecolor($w, $h);
                imagecopy($tab,$tab_template,0,0,0,0,$w,$h);
                // the top corner is the transparent colour, luckily
                $txcol = imagecolorat($tab,0,0);
                imagecolortransparent($tab,$txcol);

                $white = imagecolorallocate($tab,255,255,255);

                foreach ($possibles as $variation)
                {
                        $font = $variation[0];
                        $fontsize = $variation[1];

                        $lines = array();

                        // if no wrapping is requested, or no wrapping is possible...
                        if( (!$variation[2]) || ($variation[2] && strpos($text,' ')===FALSE) )
                        {
                                $bounds=imagettfbbox($fontsize, 0, $font, $text);
                                $w = $bounds[4] - $bounds[0];
                                $h = $bounds[1] - $bounds[5];
                                $realx = $x - $w/2 -1;
                                $lines[] = array($text,$font,$fontsize,$realx, $y);
                                $maxw = $w;
                        }
                        else
                        {
                                $texts = explode("\n",wordwrap($text,$wrapsize),2);

                                $line = 1;
                                $maxw = 0;
                                foreach ($texts as $txt)
                                {
                                        $bounds=imagettfbbox($fontsize, 0, $font, $txt);
                                        $w = $bounds[4] - $bounds[0];
                                        $h = $bounds[1] - $bounds[5];
                                        $realx = $x - $w/2 -1;
                                        $realy = $y - $h * $line + 3;
                                        $lines[] = array($txt,$font,$fontsize,$realx, $realy);
                                        if($maxw<$w) $maxw=$w;

                                        $line--;
                                }
                        }

						
                        if($maxw<$wlimit) break;
                }

                foreach ($lines as $line)
                {
                        imagettftext($tab, $line[2], 0, $line[3], $line[4], $white, $line[1], $line[0] );
                }

        putenv('GDFONTPATH=' . $originalpath);
        chdir($orig_cwd);

        imagetruecolortopalette($tab, true, 256);

        return($tab);
}

