<?php

// by "maarten at ba dot be"
// http://php.net/manual/en/function.func-get-args.php#110030
if(!function_exists("func_get_args_byref")){
	function func_get_args_byref() {
		$trace = debug_backtrace();
		return $trace[1]['args'];
	}
}

function decodeStringValue($string){
	$stringL = strtolower(trim($string));
	switch($stringL){
		case "true": return true;
		case "false": return false;
		case "null": return null;
		default: return $stringL;
	}
}

?>