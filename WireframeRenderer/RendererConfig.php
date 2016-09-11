<?php
require_once __DIR__ . "/../config.php";

// FONTS
$fontsPathSystem = "C:/Windows/Fonts";
$fontsPathWWW = N14CORE_LOCATION . "/GDWrapper/Fonts";

// OUTPUT DIRECTORIES
define('CACHEDIR', $utmpLoc . "/layouts"); // rendered layouts
define('JSONCACHEDIR', __DIR__ . "/jsonpolys"); // JSON version of T3D file
define('REPORTDIR', $utmpLoc . "/mapreport"); // map reports

// OPTIONS
$defaultProjectionMode = "isometric_30deg";
$defaultColorScheme = "classic";
$imageSizeNormalX = 864;
$imageSizeNormalY = 486;
$imageSizeBigX = 2560;
$imageSizeBigY = 1440;

// IMAGE OUTPUT HANDLERS
// add watermark to image
$watermarkFunction='utt_watermark';  // (resource $img)
// send image to client
$imageFinishFunction='imgfinish'; // (resource $img)

// PROJECTION MODE CALLBACKS
$renderModes=array(
	"ort"=>"orthographic",
	"iso3"=>"isometric_30deg",
	"tibia"=>"tibia"
);

// MISC
$debug_checkpoint=false;
$dateformat="d-m-Y H:i";
