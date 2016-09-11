<?php
/* Session Manager
 *
 * 2015 namonaki14
 * 
 * [insert licensing trash here]
 * 
 * Changelog:
 * 
 * '15-12-16 Created
 * 
 */
 

$extensionLoaderFunction = "sessionExtensionLoader";
$extensionName = "Session Manager";

// File: "G:\PHPTrash\N14Core\v0.1\Extensions\Session.ext.php"
// Signed: 18-12-2015 12:39:00
$moduleSignature = "EARLY_DEV!!";


function sessionExtensionLoader($manager){
	$sessionName = "N14ModularAppSession";
	$sessionId = null;
	$sessionData = extLoadSession($sessionName, $sessionId);

	$manager->bindMasterDestructor(function() use($sessionData, $sessionName, $sessionId){
		extSaveSession($sessionName, $sessionId, $sessionData);
	});
	
	$manager->bindModulePostInit(function($module,&$modData) use(&$sessionData){
		extCreateSessionForModule($module, $modData, $sessionData);
	});	
	$manager->bindModuleCleanup(function($module,&$modData) use(&$sessionData){
		extSaveSessionForModule($module, $modData, $sessionData);
	});

	$manager->bindPropertyGetterForModule("session",function&(&$modData){
		return $modData['sessionBin'];
	});	
}

function extCreateSessionForModule($module, &$modData, &$sessionData){
	$modClass = get_class($module);
	if(isset($sessionData[$modClass])){
		$modData['sessionBin'] = new SessionBin($sessionData[$modClass]);
	}else{
		$modData['sessionBin'] = new SessionBin();
	}
}

function extSaveSessionForModule($module, &$modData, &$sessionData){
	$modClass = get_class($module);
	if(count($modData['sessionBin']) > 0){
		$sessionData[$modClass] = $modData['sessionBin']->data;
	}
}



function &extLoadSession($sessionName, &$newSessionId){
	session_name($sessionName);
	session_start();
	$newSessionId = session_id();
	setcookie($sessionName,$newSessionId,time()+86400*365*2,"/");
	/*$sess = $_SESSION;
	$_COOKIE[$sessionName]=$newSessionId;
	
	session_write_close();
	return $sess;*/
	return $_SESSION;
}

function extSaveSession($sessionName, $sessionId, $sess){
	/*$previousUseCookies = ini_set("session.use_cookies", 0);
	session_name($sessionName);
	session_id($sessionId);
	session_start();
	$_SESSION = $sess;
	session_write_close();
	ini_set("session.use_cookies", $previousUseCookies);*/
}

class SessionBin implements \ArrayAccess{
	public $data;	
	public function __construct($inData=array()){
		$this->data = $inData;
	}
	
	public function offsetExists($offset){
		return isset($this->data[$offset]);
	}
	public function offsetGet($offset){
		return $this->data[$offset];
	}
	public function offsetSet($offset, $value){
		$this->data[$offset] = $value;
	}
	public function offsetUnset($offset){
		unset($this->data[$offset]);
	}
	
}
 
?>