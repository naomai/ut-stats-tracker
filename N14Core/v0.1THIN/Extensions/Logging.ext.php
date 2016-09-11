<?php
/* Logging
 *   Adds Module->log() method
 *
 * 2015 namonaki14
 * 
 * [insert licensing trash here]
 * 
 * Changelog:
 * 
 * '15-12-09 Created
 * 
 */
 
namespace N14;

require_once __DIR__.'/../ILog.class.php';

$extensionLoaderFunction = "N14\bindLogger";
$extensionName = "Log Binder Extension";

// File: "G:\PHPTrash\N14Core\v0.1\Extensions\Logging.ext.php"
// Signed: 15-03-2016 16:46:19
$moduleSignature = "09078e4031d0fc0252529c6004283542924e8979f45d613a87f33f3a974676427283dbd7750b55e1d3c673f6360ebe62a9ce3ef323ca96ab3adea3dcdb0a155e";
 
function bindLogger($manager){
	$modMaster = $manager->getModuleMaster();
	$manager->bindMethodForModule("log",function($args, &$modData) use($modMaster){
		$modMaster->raiseEvent("LogWrite",$args[0],(isset($args[1])?$args[1]:ILog::LOG_OUT),$modData['modName']);
	});
	
	$manager->bindModulePreInit(function($module, &$modData) use($manager){
		$modData['modName'] = $module->moduleName;
	});

}


 
?>