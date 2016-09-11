<?php
/* Nemo PHP INI Class
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
require_once "ConfigSource.class.php";

/*------------------------*\
          N14\INI
\*------------------------*/

class INI extends ConfigSource{
	protected $fileName;
    protected $iniBuffer=null;
    public $allowVariables = false;
	public $mappedPHPGlobals = array();
	public $mappedPHPConstants = array();
	public $mappedVariables = array();
    public $silentCreation = false;
	public $strict = false;
    protected $_derefCurrentSectionName=null;
	
	public function __construct($sourceFile=null){
		if(PHP_SAPI=="cli") $scriptFile = $argv[0];
		else $scriptFile = realpath($_SERVER['SCRIPT_FILENAME']);
		
		$fileDirPath = dirname($scriptFile);
		
		if($sourceFile===null){
			if(defined('N14APPNAME')){
				$appName = N14APPNAME;
			}else if(isset($GLOBALS['n14AppConfig']['Name'])){
				$appName = $GLOBALS['n14AppConfig']['Name'];
			}
			if(isset($appName)){
				$sourceFile = $fileDirPath . "/" . $appName .".ini";
				if(!file_exists($sourceFile)){
					touch($sourceFile);
				}
			}else{
				throw new INIException("No filename supplied, also N14APPNAME constant is not defined");
			}
		}
		
		$sourceFileReal = realpath($sourceFile);
		if(!file_exists(dirname($sourceFile)) || $sourceFileReal===false){
			throw new INIException("Invalid path");
		}
		
		if(!file_exists($sourceFile)){
			touch($sourceFile);
		}
		$this->fileName=$sourceFile;
		parent::__construct();
	}
	
	public function __destruct(){
		parent::__destruct();
	}
	public function getProperty($prop){
		$this->__guardRead($prop);	
		if($this->iniBuffer !== null)
            $this->iniBuffer=$this->__loadIni();
			
        if(!isset($this->iniBuffer[$prop]) && $this->silentCreation){
			$this->iniBuffer[$prop] = null;
		}
        
        if($this->allowVariables){
            $result=$this->__dereferenceVariables($this->iniBuffer[$prop],$prop);
        } else {
        	$result=$this->iniBuffer[$prop];	
        }
        $this->iniBuffer=null;
        
        return $result;
	}
	public function setProperty($prop,$value){
		$this->__guardWrite($prop);			
		$iniArray = $this->__loadIni();
		$iniArray[$prop] = $value;
		$this->__updateIni($iniArray);
	}
	public function unsetProperty($prop){
		$this->__guardWrite($prop);			
		$iniArray = $this->__loadIni();
		unset($iniArray[$prop]);
		$this->__updateIni($iniArray);
	}
	public function isValidProperty($prop){
		$this->__guardRead($prop);			
		$iniArray=$this->__loadIni();
		return isset($iniArray[$prop]);		
	}
	
	public function addVariableRef(&$var,$varName){
		if(!is_string($varName)) throw new \InvalidArgumentException("VarName is not a valid string");
		$this->mappedVariables[$varName] = $var;
	}
	
	public function addVariable($var,$varName){
		if(!is_string($varName)) throw new \InvalidArgumentException("VarName is not a valid string");
		$this->mappedVariables[$varName] = $var;
	}
	
	protected function __guardRead($prop){
		if(!$this->checkPermissions($prop,self::CONFIG_LOCK_READ)) {
			$lockInfo = $this->getLockInfo($prop);
			throw new INIException("Trying to read locked property \"$prop\", locked in {$lockInfo['lockFile']} on line {$lockInfo['lockLine']}");
			
		}
	}
	
	protected function __guardWrite($prop){
		if(!$this->checkPermissions($prop,self::CONFIG_LOCK_WRITE)) 
			throw new INIException("Trying to modify locked property \"$prop\", locked in {$lockInfo['lockFile']} on line {$lockInfo['lockLine']}");
	}
	
	protected function __loadIni(){
		$iniContent = file_get_contents($this->fileName);
		if($iniContent===false)
			throw new INIException("Filesystem error");
		
		$iniArray = self::__staticParseIni($iniContent, $this->strict);
		
		return $iniArray;		
	}
	
	protected function __updateIni($iniArray){
		file_put_contents($this->fileName, self::__staticSerializeIni($iniArray));
	}
    
        
    protected function __dereferenceVariables($content, $context){
        if(is_string($content)){
            $oldContext = $this-> __dereferenceSetCurrentContext($context);
            $result=preg_replace_callback("#{([a-z0-9_\.]*)}#i", array($this,"__dereferenceVariablesReplaceCallback"), $content);
            $this-> __dereferenceSetCurrentContext($oldContext);
        }else if(is_array($content)){
            $result=array();
			foreach($content as $key=>$val){
				$oldContext = $this-> __dereferenceSetCurrentContext($context);
				$result[$key]=$this->__dereferenceVariables($val, $context);  
				$this-> __dereferenceSetCurrentContext($oldContext);
			}
        }else{
			$result = null;
		}
        return $result;
    }
    
    private function __dereferenceSetCurrentContext($context){
        $oldCtx = $this->_derefCurrentSectionName;
        $this->_derefCurrentSectionName = substr($context, 0, strpos($context,"."));
        return $oldCtx;
    }
    
    
    
    private function __dereferenceVariablesReplaceCallback($matches){
        $varName = $matches[1];
		$varNameSploded = explode(".", $varName);

		if($varNameSploded[0] == "PHPGlobal"){
			if(in_array($varNameSploded[1], $this->mappedPHPGlobals)){
				return $GLOBALS[$varNameSploded[1]];
			}
		
		}else if($varNameSploded[0] == "PHPConstant"){
			if(in_array($varNameSploded[1], $this->mappedPHPConstants)){
				return constant($varNameSploded[1]);
			}
		
		}else if($varNameSploded[0] == "Vars"){
			if(isset($this->mappedVariables[$varNameSploded[1]])){
				return $this->mappedVariables[$varNameSploded[1]];
			}
		
		}else{
		
			if(strpos($varName,".")===false){
				$varName = $this->_derefCurrentSectionName . "." . $varName;
			}
			return $this->getProperty($varName);
		}
    }
	
	protected static function __staticParseIni($content, $strict = false){
		$commentsNum = 0; 
		
		$fileLines = self::__staticSafeLineSplit($content);
		
		$result=array();
		$currentSection="__top";
		$prefixCurrentSection="__top.";
		
		for($lineNum = 0, $lineCount = count($fileLines); $lineNum < $lineCount; $lineNum++){
			$line = trim($fileLines[$lineNum]);
			
			if(preg_match("/^([^\s\[\]#;=]+)\s*(\[([0-9]*)\])?\s*=\s*(.*)$/",$line,$propertyRegex)){ // property
				$propertyName = $propertyRegex[1];
				if(!preg_match("#^[a-zA-Z0-9_\-]+$#",$propertyName) && $strict)
					throw new INIParseException("Invalid property name: $propertyName",null,$lineNum+1);
					
				$propertyIndexDef = $propertyRegex[2];
				$propertyIndex = $propertyRegex[3];
				$propertyValue = $propertyRegex[4];
				
				$propertyId = $prefixCurrentSection.$propertyName;
				
				if($propertyIndexDef!==""){
					if(is_numeric($propertyIndex)) {
						$result[$propertyId][$propertyIndex+0]=$propertyValue;
					}else if($propertyIndex=="" || !$strict) {
						$result[$propertyId][]=$propertyValue;
					}else{
						throw new INIParseException("Invalid property index: $propertyIndex",null,$lineNum+1);
					}
				}else{
					$result[$propertyId]=$propertyValue;
				}
				
			} else if(preg_match("#^\[([^\]]*)\]$#",$line,$sectionNameRegex)){ // section name
				$currentSection = $sectionNameRegex[1];
				if($currentSection=="__Spaghetti") 
					$prefixCurrentSection="";
				else
					$prefixCurrentSection=$currentSection.".";
					
			} else if(strlen($line)===0 || strpos($line,";") === 0 || strpos($line,"#") === 0 || !$strict){ // comment / blank line / invalid line
				$propertyId = $prefixCurrentSection."__comment" . ($commentsNum++);
				$result[$propertyId]=$line;
			} else {
				throw new INIParseException("Unexpected \"$line\"",null,$lineNum+1);
			}
			
			
		}
		return $result;
	}
	
	protected static function __staticSerializeIni($iniArray){
		$arrayBySections=array();
		foreach($iniArray as $propertyId => $propertyValue){
			if(preg_match("#^(.+)\.(.+)$#",$propertyId,$propertyIdRegex)){
				$sectionName = $propertyIdRegex[1];
				$propertyName = $propertyIdRegex[2];
			}else{
				$sectionName = "__Spaghetti";
				$propertyName = $propertyId;
			}
			if(!isset($arrayBySections[$sectionName])){
				if($sectionName==="__top"){
					$arrayBySections[$sectionName]="";
				}else{
					$arrayBySections[$sectionName]="[$sectionName]\r\n";
				}
			}
			
			if(strpos($propertyName,"__comment")===0){
				$arrayBySections[$sectionName].=$propertyValue."\r\n";;
			}else{
				if(is_array($propertyValue)){
					foreach($propertyValue as $index=>$value){
						$arrayBySections[$sectionName].="$propertyName"."[$index]=$value\r\n";
					}
				}else{
					$arrayBySections[$sectionName].="$propertyName=$propertyValue\r\n";
				}
			}
		}
		return trim(implode($arrayBySections));
	}
	
	protected static function __staticSafeLineSplit($content){
		return explode("\n", str_replace("\r\n","\n",$content));
	}
	
	public function coreInfoStats(){
		return array("name"=>"INI","source"=>__FILE__,"class"=>__CLASS__,"stats"=>array());
	}
}

class INIException extends \Exception{
	
}

class INIParseException extends INIException{
	protected $iniFile;
	protected $iniLine;
	public function __construct($message, $file=null, $line=null){
		$this->iniFile=$file;
		$this->iniLine=$line;
		$messageNew = "Parse error: $message";
		if($file!==null) $messageNew .= " in $file";
		if($line!==null) $messageNew .= " on line $line";
		parent::__construct($messageNew);
	}
}


 
?>