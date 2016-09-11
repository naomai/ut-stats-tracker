<?php

require_once("INICache.class.php");
$branchName = "N14Core ForUTTracker";
$buildAuthor = "namo";
$versionMajor = 0;
$versionMinor = 1;

if(php_sapi_name() !== "cli"){
	die("This script can only be run in CLI.");
}

$versionIni = new N14\INICache(__DIR__ . "/Version.ini");
$revOld = $versionIni["CoreVersionInfo.Revision"];
$versionRevison = (int)$revOld + 1;

$versionFull = $versionMajor.".".$versionMinor.".".$versionRevison;
$versionDateUT = time();
$versionDate = date("Y-M-d H:i:s",$versionDateUT);
$versionHash = sha1(microtime(true));
$buildFullName = "$branchName $versionFull ($versionDate $buildAuthor) #$versionHash";

$versionIni["CoreVersionInfo.Revision"] = $versionRevison;
$versionIni["CoreVersionInfo.VersionFull"] = $versionFull;
$versionIni["CoreVersionInfo.BuildName"] = $buildFullName;
$versionIni["CoreVersionInfo.BuildDate"] = $versionDate;

echo $buildFullName;


?>