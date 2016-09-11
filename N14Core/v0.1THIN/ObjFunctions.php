<?php

function getCallerInfo($myClassName=null){
	$bt = debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT);
	$caller="";

	for($i=0; $i<count($bt); $i++){
		$btf = $bt[$i];
		if(!isset($btf['file'])) continue; // callback
		if($btf['file'] === __FILE__ || $btf['function'] === __FUNCTION__) continue; // this function
		
		$isMy=
			$myClassName!==null && isset($btf['class']) && 
			($btf['class']==$myClassName || is_subclass_of($btf['class'],$myClassName));

		if(!$isMy){
			
			break;

		}
		$caller=$btf;
	}
	
	return $caller;
}

function getCallerOfType($typeName){
	$bt = debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT);
	$caller="";
	for($i=0; $i<count($bt); $i++){
		$btf = $bt[$i];
		//print_r($btf);
		if(!isset($btf['file'])) continue; // callback
		if($btf['file'] === __FILE__ || $btf['function'] === __FUNCTION__) continue; // this function
		
		$isA=isset($btf['class']) && ($btf['class']==$typeName || is_subclass_of($btf['class'],$typeName));
		
		if($isA){
			//return $btf;
			break;

		}
		$caller=$btf;
	}
	
	return $caller;
}

function getCallerOfParentType($typeName){
	$bt = debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT);
	$caller="";
	for($i=0; $i<count($bt); $i++){
		$btf = $bt[$i];
		//print_r($btf);
		if(!isset($btf['file'])) continue; // callback
		if($btf['file'] === __FILE__ || $btf['function'] === __FUNCTION__) continue; // this function
		
		$isA=isset($btf['class']) && $btf['class']!=$typeName && is_subclass_of($btf['class'],$typeName);
		
		if($isA){
			break;

		}
		$caller=$btf;
	}
	
	return $caller;
}

function ble($v){
	return $v?"true":"false";
}
?>