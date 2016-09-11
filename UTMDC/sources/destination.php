<?php

namespace UTMDC\Destination{
	use \DOMDocument;
	require_once(__DIR__ . "/common.php");
	
	
	function prefetch(){
		$localDbFile=__DIR__ . "/../destination.txt";
		$GLOBALS['request_interval']=5000;
		$us=array();
		$us=prefetchArray("0-9",2);
		for($i=ord('A'); $i<=ord('Z'); $i++){
			$ax=prefetchArray(chr($i),$i-ord('A')+3);
			$us=array_merge($us,$ax);
		}
		if(!file_exists($localDbFile) || fileIsOld($localDbFile)){
			$mlist="";
			
			for($i=0; $i<count($us); $i++){
				$pname=$us[$i][0];
				$purl=$us[$i][1];
				$mlist.="$pname\\$purl\r\n";
			}
			file_put_contents($localDbFile,$mlist);
		}else{
			
		}
	}
	
	
	function prefetchArray($url,$fname){
		$urx="http://www.destinationunreal.com/modules.php?name=Maps&cat=$url&id=$fname";
		
		$localHtFile=__DIR__ . "/../destination_$url.html";
		//echo "$urx $localHtFile<br/>";
		if(!haveTimeForUpdate() || (file_exists($localHtFile) && !fileIsOld($localHtFile))){
			$ht=file_get_contents($localHtFile);
		}else{
			$ht=spoof_req($urx);
			if(strlen($ht)<50){
				$ht=file_get_contents($localHtFile);
			}else{
				file_put_contents($localHtFile,$ht);
			}
		}
		$maxz=array();
		$matc=preg_match_all('/><\/a><\/div><\/td><td class=row4 class=row1 valign=top><a href=\'([^"]*)\' title=\'View [^>]*>\n <div style=\'float:left; color:yellow; font-size:120%\'>([^<]*)<\/div><div style=\'float:left; vertical-align:top; color:orange; font-size:90%;\'>&nbsp;&nbsp;<font class="blocktitlesmall">([^<]*)</',$ht,$matz);
		if($matc){
			for($i=0; $i<$matc; $i++){
				$pname=str_replace(" ","_",trim($matz[2][$i]));
				$pnamelay=trim($matz[3][$i]);
				
				//$purl=getFullUrl($matz[1][$i],$urx);
				
				$purl="http://www.destinationunreal.com/files/download/Maps/$url/$pnamelay/".strtolower($pname).".zip";
				$maxz[]=array(0=>$pname,1=>$purl);
			}
			
		}
		return $maxz;
	}
	
	function findMapUrl($mapname){
		if(stripos($mapname,"MH-")!==0) return false;
		$fx=file(__DIR__ . "/../destination.txt",FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES);
		foreach($fx as $fa){
			$pname=strtok($fa,"\\");
			$purl=strtok("\r");
			if(strtolower($mapname)==strtolower($pname)){
				return $purl;
			}
		}
		return false;
	}

	
	prefetch();
	
}	
?>