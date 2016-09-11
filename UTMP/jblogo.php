<?php
require_once "../../common.php";
$mapname = $_GET['map'];

$mapId = name2id($_GET['map']);

$jbBanner1 = __DIR__ . "/sshots/$mapId"."_jbmb1.jpg";
$jbBanner2 = __DIR__ . "/sshots/$mapId"."_jbmb2.jpg";

$banner1exists = file_exists($jbBanner1);
$banner2exists = file_exists($jbBanner2);

if(!$banner1exists) exit;

if($banner2exists){
	$left = imagecreatefromjpeg($jbBanner1);
	$right = imagecreatefromjpeg($jbBanner2);
	$merged = imagecreatetruecolor(imagesx($left) + imagesx($right), imagesy($left));
	imagecopy($merged,$left,0,0,0,0,imagesx($left),imagesy($left));
	imagecopy($merged,$right,imagesx($left),0,0,0,imagesx($right),imagesy($right));
	imagedestroy($left);
	imagedestroy($right);
	header("Content-type: image/jpeg");
	imagejpeg($merged);
	imagedestroy($merged);
}else{
	header("Content-type: image/jpeg");
	return file_get_contents($jbBanner1);
}

?>