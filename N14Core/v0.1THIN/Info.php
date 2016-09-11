<?php
/* N14 App Info Parsers
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

 
function N14AppGetInfo($fileName){
	$appDir = dirname($fileName);
	$configFile = $appDir."/appInfo.php";
	if(!file_exists($configFile)) $configFile = $appDir."/config.php";
	if(!file_exists($configFile)) $configFile = $appDir."/n14App.php";
	if(!file_exists($configFile)) $configFile = $appDir."/appConfig.php";
		
	$configContent = file_get_contents($configFile);
	$appInfo = array();
	
	$hasAppName = preg_match("#\\\$appName\s*=\s*['\"](.*)['\"]\s*;#",$configContent,$match);
	
	if($hasAppName){
		$appInfo['name'] = $match[1];
	}else{
		preg_match_all("#\\\$n14AppConfig\[['\"](.*)['\"]\]\s*=\s*['\"](.*)['\"]\s*;#",$configContent,$match,PREG_SET_ORDER);
		$appInfoN = array();
		foreach($match as $m){
			$appInfoN[$m[1]] = $m[2];
		}
		$appInfo['name']=$appInfoN['Name'];
	}
	return $appInfo;
}

function N14ModuleGetInfo($file){

	$moduleContent = file_get_contents($file);
	if(strpos($moduleContent,"\$moduleClassName")===false) return array('valid'=>false);

	$className = getVariableValueFromPHPCode('moduleClassName',$moduleContent);
	if($className===false) return array('valid'=>false);
	$moduleName=getVariableValueFromPHPCode('moduleName',$moduleContent);

	return array('valid'=>true,'class'=>$className,'name'=>$moduleName);
	
}

function N14ExtensionGetInfo($file){

	$extensionContent = file_get_contents($file);
	if(strpos($extensionContent,"\$extensionLoaderFunction")===false) return array('valid'=>false);

	$loaderFunction = getVariableValueFromPHPCode('extensionLoaderFunction',$extensionContent);
	if($loaderFunction===false) return array('valid'=>false);
	$extensionName=getVariableValueFromPHPCode('extensionName',$extensionContent);

	return array('valid'=>true,'function'=>$loaderFunction,'name'=>$extensionName);
	
}
 
?>