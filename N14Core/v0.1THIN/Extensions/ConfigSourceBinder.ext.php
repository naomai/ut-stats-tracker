<?php
/* ConfigSourceBinder
 *   Exposes ConfigSource functionality to all modules 
 *   using Module->config property
 * 
 * 2015 namonaki14
 * 
 * [insert licensing trash here]
 *
 * Usage:
 *   Before loading any modules, create your ConfigSource object
 *   and pass it to ModuleMaster->configSource property
 * 
 * Changelog:
 * 
 * '15-12-03 Created
 * 
 */
 
namespace N14;

require_once __DIR__.'/../ConfigSource.class.php';

$extensionLoaderFunction = "N14\bindConfigSource";
$extensionName = "ConfigSource Wrapper Extension";

// File: "G:\PHPTrash\N14Core\v0.1\Extensions\ConfigSourceBinder.ext.php"
// Signed: 15-03-2016 15:34:10
$moduleSignature = "3b9b9e608fa8c290538e505f47e2974b702c40528cbbc5a539fe1044d87a16399e4606b2d2c6bccd86abb2afd33c86f04b66f790dd0020e7c7d8efb9807476b9";

function bindConfigSource($manager){
	$extensionData = array();
	
	/* ----- ModuleMaster->configSource getters/setters ----- */
	$manager->bindPropertySetterForMaster("configSource", function($object) use(&$extensionData){
		if($object instanceof ConfigSource){
			$extensionData['source'] = $object;
		}else{
			throw new \InvalidArgumentException("Not a valid ConfigSource");
		}
	});
	$manager->bindPropertyGetterForMaster("configSource", function() use(&$extensionData){
		return $extensionData['source'];
	});
	
	/* ----- Module->config getter ----- */
	// we're returning the object, so setter is NOT needed
	$manager->bindPropertyGetterForModule("config",function(&$modData) use(&$extensionData){
		return $modData['configSourceWrapper'];
	});
	
	/* ----- ConfigSourceWrapper creation for module ----- */
	$manager->bindModulePreInit(function($module, &$modData) use(&$extensionData){
		if(!isset($extensionData['source'])) 
			throw new \Exception("ModuleMaster has no configSource set.");
			
		$classFile = (new \ReflectionClass($module))->getFileName();
		$fname = explode(".", basename($classFile))[0];

		$configWrapper = new ConfigSourceWrapper($fname, $extensionData['source']);
		$modData['configSourceWrapper'] = $configWrapper;
		
	});
	
	
}


class ConfigSourceWrapper implements \ArrayAccess{
	protected $className;
	protected $confObj;
	
	public function __construct($className, $confObj){
		$this->confObj = $confObj;
		$this->className = $className;		
	}
	
	
	public function getProperty($prop){
		return $this->confObj->getProperty("{$this->className}.{$prop}");
	}
	public function setProperty($prop,$value){
		$this->confObj->setProperty("{$this->className}.{$prop}",$value);
	}
	public function unsetProperty($prop){
		$this->confObj->unsetProperty("{$this->className}.{$prop}");
	}
	public function isValidProperty($prop){
		return $this->confObj->isValidProperty("{$this->className}.{$prop}");
	}
	
		/* INTERFACE ArrayAccess */
							
	public function offsetExists($offset){
		return $this->isValidProperty($offset);
	}
	public function offsetGet($offset){
		return $this->getProperty($offset);
	}
	public function offsetSet($offset, $value){
		return $this->setProperty($offset,$value);
	}
	public function offsetUnset($offset){
		return $this->unsetProperty($offset);
	}
	
	public function getConfigSourceObject(){
		return $this->confObj;
	}
}
 
?>