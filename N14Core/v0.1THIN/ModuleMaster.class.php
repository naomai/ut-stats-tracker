<?php
/* ModuleMaster
 * 
 * 2013-2014 namonaki14
 */

namespace N14;

abstract class Module{
	public abstract function init();
	public abstract function cleanup();
}

class ModuleMaster{
	
}

class ModMasterExtensionManager{

}

if(!function_exists("func_get_args_byref")){
	function func_get_args_byref() {
		$trace = debug_backtrace();
		return $trace[1]['args'];
	}
}

class InvalidSignatureException extends \Exception{

}
class NoSignatureException extends \Exception{

}
 
?>