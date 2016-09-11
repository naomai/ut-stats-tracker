<?php

namespace UTMDC\Other{
	use \DOMDocument;
	require_once(__DIR__ . "/common.php");
	
	
	function prefetch(){
		
	}
	

	

	function findMapUrl($mapname){
		$fx=file(__DIR__ . "/../other.txt",FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES);
		foreach($fx as $fa){
			$pname=strtok($fa,"\\");
			$purl=strtok("\r");
			$pname_no_ext=substr($pname,0,strrpos($pname,"."));
			if(strtolower($mapname)==strtolower($pname_no_ext)){
				return $purl;
			}
		}
		return false;
	}

	
	prefetch();
	
}	
?>