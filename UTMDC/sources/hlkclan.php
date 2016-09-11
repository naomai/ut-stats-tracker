<?php

namespace UTMDC\HLKClan{
	use \DOMDocument;
	require_once(__DIR__ . "/common.php");
	
	
	function prefetch(){
		$GLOBALS['request_interval']=5000; // we don't want to spam the site
		prefetchForUrl("","");

	}
	
	function prefetchForUrl($url,$fname){
		
		$urx="http://www.hlkclan.net/unreal1/maps/";
		
		$localHtFile=__DIR__ . "/../hlkclan.html";
		$localDbFile=__DIR__ . "/../hlkclan.txt";
		if((file_exists($localHtFile) && !fileIsOld($localHtFile))){
			$ht=file_get_contents($localHtFile);
		}else{
			$nullz=null;
			$ht=spoof_req($urx,$nullz,false,true);
			
			if(strlen($ht)<50){
				$ht=file_get_contents($localHtFile);
			}else{
				file_put_contents($localHtFile,$ht);
			}
		}
		
		if(!file_exists($localDbFile)){
			$mlist="";
			
			$matc=preg_match_all('#" alt="\[   \]"></td><td><a href="([^"]*)">([^<]*)</a>#',$ht,$matz);
			if($matc){
				for($i=0; $i<$matc; $i++){
					
					$pname=htmlspecialchars_decode(trim($matz[2][$i]));
					
					$purl=getFullUrl(htmlspecialchars_decode($matz[1][$i]),$urx);
					$pname=rawurldecode(substr($purl,strrpos($purl,"/")+1));
					
					$mlist.="{$pname}\\{$purl}\r\n";
				}
				
			}
			
			file_put_contents($localDbFile,$mlist);
		}else{
			
		}
	}
	
	function findMapUrl($mapname){
		$fx=file(__DIR__ . "/../hlkclan.txt",FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES);
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