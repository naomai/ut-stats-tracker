<?php

namespace UTMDC\CelticWarriors{
	use \DOMDocument;
	require_once(__DIR__ . "/common.php");
	
	
	function prefetch(){
		$GLOBALS['request_interval']=5000; // "Please do not hammer this site! You must wait a few seconds before trying again."
		prefetchForUrl("COOPMaps/","coop");

	}
	
	function prefetchForUrl($url,$fname,$recursive=false){
		//$urx="http://medor.no-ip.org/Maps/$url";
		$urx="http://www.celticwarriors.net/Downloads/Maps/$url";
		$localDbFile=__DIR__ . "/../celticwarriors_$fname.txt";

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
	
	function prefetchArray($url,$fname,$recursive=-1,$recfname=""){
		
		if($recursive!=-1){
			$localHtFile=__DIR__ . "/../celticwarriors_$fname"."_$recfname.html";
		}else{
			$localHtFile=__DIR__ . "/../celticwarriors_$fname.html";
		}
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
		//$matc=preg_match_all('/<tr><td valign="top"><img src="[^"]*" alt="\[([A-Za-z0-9\s]*)\]"><\/td><td><a href="([^"]*)">([^<]*)<\/a>/',$ht,$matz);
		$matc=preg_match_all('/<li><a href="([^"]*)"> ([^<]*)<\/a>/',$ht,$matz);
		if($matc){
			for($i=0; $i<$matc; $i++){
				$pname=trim($matz[2][$i]);
				if($pname=="Parent Directory") continue;
				
				$purl=getFullUrl($matz[1][$i],$url);
				/*if(trim($matz[2][$i])=="dir") {
					if($recursive!=-1){
						$newx=prefetchArray($purl,$fname,$recursive+1,$pname);
						$maxz=array_merge($maxz,$newx);
					}else{
						continue;
					}
				}else{*/
					$maxz[]=array(0=>$pname,1=>$purl);
				//}
				
				//echo "$pname\\$purl<br/>";
			}
			
		}
		return $maxz;
	}
	
	function findMapUrl($mapname){
		if ($dh = opendir(__DIR__ . "/..")) {
			while (($file = readdir($dh)) !== false) {
				if(stripos($file,"celticwarriors_")===0 && stripos($file,".txt")!==false){
					$mn=findMapUrlInFile($mapname,__DIR__ . "/../$file");
					if($mn!==false) return $mn;
				}
			}
			closedir($dh);
		}
		return false;
	}
	
	function findMapUrlInFile($mapname,$filename){
		$fx=file($filename,FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES);
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
	//echo findMapUrlInFile("MH-nara_Beta+Fix2",'C:\Users\bun\Desktop\PHPTrash\uttracker\nightly\utmdc\medor_mh.txt');
}	
?>