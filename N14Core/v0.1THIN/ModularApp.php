<?php
 
if(realpath($_SERVER['SCRIPT_FILENAME']) == __FILE__) {
	die("You're doing it wrong!");
}
require_once N14CORE_LOCATION . "/Common.php";
require_once N14CORE_LOCATION . "/INICache.class.php";
require_once N14CORE_LOCATION . "/ModuleMaster.class.php";


$modMaster = new N14\ModuleMaster();
$modMaster->triggerInit = false;

$appConfig = new N14\INICache();
$appConfig->allowVariables = true;
$appConfig->silentCreation = true;
$appConfig->mappedPHPConstants[] = "N14CORE_LOCATION";
?>