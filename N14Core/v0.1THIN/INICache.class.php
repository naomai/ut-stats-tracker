<?php
/* Nemo PHP INICache Class
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
require_once "INI.class.php";

/*------------------------*\
        N14\INICache
\*------------------------*/
// Buffered version of the N14\INI class

class INICache extends INI{
	public $saveImmediately = true;
	protected $wasModified = false;
	protected $updatePending = false;
	
	public function __construct($sourceFile=null){
		parent::__construct($sourceFile);
	}
	
	public function __destruct(){
		if($this->updatePending)
			$this->__updateIni($this->iniBuffer);
			
		parent::__destruct();
	}

	public function getProperty($prop){
		$this->__guardRead($prop);				
		$this->__maybeLoad();
		
		if(!isset($this->iniBuffer[$prop]) && $this->silentCreation){
			$this->iniBuffer[$prop] = null;
			$this->__maybeUpdate();
		}
		
        if($this->allowVariables){
            $result=$this->__dereferenceVariables($this->iniBuffer[$prop],$prop);
            
		} else {
        	$result=$this->iniBuffer[$prop];	
        }
        return $result;
	}
	public function setProperty($prop,$value){
		$this->__guardWrite($prop);				
		$this->__maybeLoad();
		$this->iniBuffer[$prop] = $value;
		$this->__maybeUpdate();
		$this->wasModified=true;
	}
	public function unsetProperty($prop){
		$this->__guardWrite($prop);	
		$this->__maybeLoad();
		unset($this->iniBuffer[$prop]);
		$this->__maybeUpdate();
		$this->wasModified=true;
	}
	public function isValidProperty($prop){
		$this->__guardRead($prop);				
		$this->__maybeLoad();
		return isset($this->iniBuffer[$prop]);		
	}
	
	
	
	private function __maybeLoad(){
		if($this->iniBuffer===null){
			$this->iniBuffer = $this->__loadIni();
		}
	}
	
	private function __maybeUpdate(){
		if($this->saveImmediately){
			$this->__updateIni($this->iniBuffer);
		}else{
			$this->updatePending = true;
		}
	}
	
	public function coreInfoStats(){
		return array("name"=>"INICache","source"=>__FILE__,"class"=>__CLASS__,"stats"=>array());
	}
}

 
?>