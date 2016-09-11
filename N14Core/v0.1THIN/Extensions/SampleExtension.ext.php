<?php
/* Sample ModuleMaster Extension
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
 

$extensionLoaderFunction = "loadSampleExtension";
$extensionName = "Sample Extension";

// File: "G:\PHPTrash\N14Core\v0.1\Extensions\SampleExtension.ext.php"
// Signed: Never
$moduleSignature = "INSERT_MODULE_SIGNATURE";

function loadSampleExtension($manager){

	// create new method called "helloWorld" for ModuleMaster
	$manager->bindMethodForMaster("helloWorld","sampleHelloWorld");
	
	// usage of ModuleData
	$manager->bindModulePreInit("sampleSaveRandomNumberForModule");
	$manager->bindModulePostInit("sampleShowSavedRandomNumber");
	
	// global extension config
	$extensionConfig = array("veryImportantValue"=>42);
	
	$manager->bindMethodForModule("showVeryImportantValue", 
		function($arguments, &$modData) use(&$extensionConfig){
			echo "My very important number is: " . $extensionConfig['veryImportantValue'] . "\r\n";
		}
	);
	

}

function sampleHelloWorld($arguments){
	echo "Hello world!";
}

function sampleSaveRandomNumberForModule($module, &$modData){
	// modData is used as a data storage for each module shared by all extensions
	$randomNumber = rand(1,10);
	$modData['randomNumberToKeep'] = $randomNumber;
	
	echo "Loaded new module: " . get_class($module) . ", saving random number: " . $randomNumber . "\r\n";
}

function sampleShowSavedRandomNumber($module, &$modData){
	$randomNumber = $modData['randomNumberToKeep'];
	echo "Initalized module: " . get_class($module) . ", random number from last time was: " . $randomNumber . "\r\n";
}

 
?>