<?php
/* Nemo PHP ConfigSource Class
 * 
 * 201x namonaki14
 * 
 * [insert licensing trash here]
 * 
 * Changelog:
 * 
 * 'xx-xx-xx Created
 * 
 */

namespace N14;

require_once __DIR__ . "/ObjFunctions.php";

/*------------------------*\
      N14\ConfigSource
\*------------------------*/


abstract class ConfigSource implements \ArrayAccess{
	private $ownerFile;
	private $propertyLocks=array();
	
	const CONFIG_LOCK_READ=1;
	const CONFIG_LOCK_WRITE=2;
	const CONFIG_LOCK_RW=3;
	
	abstract public function getProperty($propertyName);
	abstract public function setProperty($propertyName,$value);
	abstract public function isValidProperty($propertyName);
	abstract public function unsetProperty($propertyName);
	
	public function __construct(){
		$this->ownerFile = realpath(\getCallerInfo(get_class())['file']);
	}
	
	public function __destruct(){
		
	}
	
	public function getPropertyWithDefaultValue($propertyName,$defaultValue){
		if(!$this->isValidProperty($propertyName)){
			$this->setProperty($propertyName,$defaultValue);
		}
		return $this->getProperty($propertyName);
	}
	
	/* restrict reading of values from other scripts */
	

	protected function checkPermissions($property,$oper){
        $caller=\getCallerInfo(get_class());
        //print_r($caller);
		if($caller['file'] == $this->ownerFile)
			return true;
			
		if(!isset($this->propertyLocks[$property]))
			return true;
			
		if($this->propertyLocks[$property]['flags'] & $oper == 0) 
			return true;
			
		return false;
	}
	
	public function lockProperty($property,$oper){
		$caller=\getCallerInfo(get_class());

		if($caller['file'] != $this->ownerFile)
			throw new \Exception("Lock failed: not an owner");
		
		if(!isset($this->propertyLocks[$property])){
			$this->propertyLocks[$property]['flags'] = $oper;
		}else{
			$this->propertyLocks[$property]['flags'] |= $oper;
		}
		$this->propertyLocks[$property]['lockFile'] = $caller['file'];
		$this->propertyLocks[$property]['lockLine'] = $caller['line'];

	}
	
	public function unlockProperty($property,$oper){
        $caller=\getCallerInfo(get_class());
 
		if($caller['file'] != $this->ownerFile)
			throw new \Exception("Unlock failed: not an owner");
			
		if(!isset($this->propertyLocks[$property])){
			$this->propertyLocks[$property]['flags'] = 0;
		}else{
			$this->propertyLocks[$property]['flags'] &= (~$oper);
		}
		
		
	}
	
	public function getLockInfo($property){
		return $this->propertyLocks[$property];
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
	
	
	
	
}
?>