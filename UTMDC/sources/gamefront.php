<?php

namespace UTMDC\GameFront{
	use \DOMDocument;
	require_once(__DIR__ . "/common.php");
	
	
	function prefetch(){
		$GLOBALS['request_interval']=5000;
		prefetchForUrl("","");
	}
	
	function prefetchForUrl($url,$fname,$recursive=false){
		$urx="http://www.gamefront.com/files/listing/pub2/Unreal_Tournament/Maps_Levels_Missions/$url";
		$localDbFile=__DIR__ . "/../gamefront.txt";

		$plx=prefetchArray($urx,$fname,$recursive?0:-1);
		if(!file_exists($localDbFile) || fileIsOld($localDbFile)){
			$mlist="";
			
			for($i=0; $i<count($plx); $i++){
				$pname=$plx[$i][0];
				$purl=$plx[$i][1];
				$mlist.="$pname\\$purl\r\n";
			}
			file_put_contents($localDbFile,$mlist);
		}else{
			
		}
	}
	
	function prefetchArray($url,$fname){
		
		$localHtFile=__DIR__ . "/../gamefront.html";
		if(!haveTimeForUpdate() || (file_exists($localHtFile) && !fileIsOld($localHtFile))){
			$ht=file_get_contents($localHtFile);
		}else{
			$ht=spoof_req($url);
			if(strlen($ht)<50){
				$ht=file_get_contents($localHtFile);
			}else{
				file_put_contents($localHtFile,$ht);
			}
		}
		$maxz=array();
		$matc=preg_match_all('/<p class="file_meta"><a style="color: #000;" title="Download This File" href="([^"]*)"><span>([^<]*)<\/span>/',$ht,$matz);
		if($matc){
			for($i=0; $i<$matc; $i++){
				$pname=trim($matz[2][$i]);
				
				$purl=getFullUrl($matz[1][$i],$url);
				$maxz[]=array(0=>$pname,1=>$purl);
			}
			
		}
		return $maxz;
	}
	
	function findMapUrl($mapname){
		$fx=file(__DIR__ . "/../gamefront.txt",FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES);
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