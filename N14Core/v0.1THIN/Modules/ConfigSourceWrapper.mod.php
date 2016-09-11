<?php
/* ConfigSource-ModMaster wrapper
 * 
 * 2015 namonaki14
 * 
 * [insert licensing trash here]
 * 
 * Changelog:
 * 
 * '15-12-03 Created
 * 
 * !! THIS MODULE IS DEPRECATED, INSTEAD USE ConfigSourceBinder EXTENSION
 */
 
namespace N14;

require_once __DIR__.'/../ConfigSource.class.php';

$moduleClassName = "N14\ConfigSourceWrapperCreator";
// File: "G:\PHPTrash\N14Core\v0.1\Modules\ConfigSourceWrapper.mod.php"
// Signed: NOT
$moduleSignature = "DEPRECATED";

/*------------------------------*\
  N14\ConfigSourceWrapperCreator
\*------------------------------*/

class ConfigSourceWrapperCreator extends Module{
	public $cnfObject;
	
	public function init(){
		$this->master->registerEventHandler("GetConfigWrapper", array($this,"getCnfWrapperEv"));
		if(isset($this->arguments['cnfObject'])) {
			$this->setConfigSource($this->arguments['cnfObject']);
		}
	}

	public function cleanup(){
	
	}
	
	public function setConfigSource($object){
		if($object instanceof ConfigSource){
			$this->cnfObject = $object;
		}else{
			throw new \InvalidArgumentException("Not a valid ConfigSource");
		}
	}
	
	protected function getCnfWrapperEv(&$evArray){
		$caller = \getCallerOfParentType("N14\\Module");
		$fname = explode(".", basename($caller['file']))[0];
		$evArray[0] =  new ConfigSourceWrapper($fname, $this->cnfObject);
	}
		
	protected function getCnfWrapper(){
		$caller = \getCallerOfParentType("N14\\Module");
		$fname = explode(".", basename($caller['file']))[0];
		return new ConfigSourceWrapper($fname, $this->cnfObject);
	}
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