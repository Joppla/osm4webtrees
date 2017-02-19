<?php
######################################################################
# Script for making a colored marker 
# written by Jop Planje
# februari 2017
######################################################################

// get base for marker
	$im = imagecreatefrompng('marker/marker.png');
	
// get color for marker
	$color="008000"; //basic color for marker
	if(isset($_GET['color'])&&!empty($_GET['color'])&&strlen($_GET['color'])===6) {
		$color=$_GET['color'];
	} 
	$aColor=str_split($color,2);
	foreach ($aColor as $i => $dec){
		$aColor[$i]=hexdec($dec);
	}

// get icon for marker 
	$icon = 'STAR';
	if(isset($_GET['icon'])&&!empty($_GET['icon'])&&strlen($_GET['icon'])===4) {
		$icon=$_GET['icon'];
	} 
	if(!file_exists('marker/'.$icon.'.png')) {
		$icon='STAR';		
	} 
//get icon image
	$im2 = imagecreatefrompng('marker/'.$icon.'.png');
	
// get sizes of marker
   $w = imagesx ($im);
   $h = imagesy ($im);

// make new picture   
   $resImage = imagecreatetruecolor ($w, $h);

// add color to the new picture   
   imagefill ($resImage, 0, 0, imagecolorallocate ($resImage, $aColor[0], $aColor[1], $aColor[2]));

// copy the marker on the new color   
   imagecopy ($resImage, $im, 0, 0, 0, 0, $w, $h);

// make the 'allmost' black transparant   
	$black=imagecolorallocate($resImage, 1, 1, 1);
	imagecolortransparent($resImage, $black);

// copy the icon on the 'new marker'
	imagecopy($resImage, $im2, 0, 0, 0, 0, 350, 566);

	header('Content-Type: image/png');
	imagepng($resImage);
	imagedestroy($resImage);
	imagedestroy($im);
	imagedestroy($im2);
?>
