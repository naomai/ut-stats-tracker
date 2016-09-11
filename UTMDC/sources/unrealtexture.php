<?php

namespace UTMDC\UnrealTexture{
	use \DOMDocument;
	require_once(__DIR__ . "/common.php");
	
	
	
	function prefetch(){
		$GLOBALS['request_interval']=5000;
		prefetchForUrl("http://www.unrealtexture.com/Unreal/Downloads/Maps/","u1",true);
		prefetchForUrl("http://www.uttexture.com/UT/Website/Downloads/Maps/","ut",true);

	}
	
	function prefetchForUrl($url,$fname,$recursive=false){
		//$urx="http://www.unrealtexture.com/$url";
		$urx=$url;
		$localDbFile=__DIR__ . "/../utex_$fname.txt";

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
	$scriptstart=microtime(true);
	function prefetchArray($url,$fname,$recursive=-1,$recfname=""){
		//echo $url." ";
			//if($recursive==2) echo($url);
		if($recursive!=-1){
			$urlHash=dechex(crc32($url));
			$localHtFile=__DIR__ . "/../utex_$fname"."_$recfname$urlHash.html";
		}else{
			$localHtFile=__DIR__ . "/../utex_$fname.html";
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
		$matc=preg_match_all('#<li><a href="([^"]*)"> ([^<]*)</a></li>#',$ht,$matz);
		if($matc){
			for($i=0; $i<$matc; $i++){
				$pname=trim($matz[2][$i]);
				//if($pname=="Parent Directory") continue;
				if($matz[1][$i][0]==="/") continue;
				
				$purl=getFullUrl($matz[1][$i],$url);
				
				if($pname[strlen($pname)-1]==="/") {
					if($recursive!=-1){
						$newx=prefetchArray($purl,$fname,$recursive+1,substr($pname,0,-1));
						$maxz=array_merge($maxz,$newx);
					}else{
						continue;
					}
				}else{
					$maxz[]=array(0=>$pname,1=>$purl);
				}
				
				//echo "$pname\\$purl<br/>";
			}
			
		}
		//echo count($maxz)." t:".round((microtime(true)-$GLOBALS['scriptstart'])*1000)."ms<br/>";
		return $maxz;
	}
	
	function findMapUrl($mapname){
		$gamemode=strtoupper(strtok($mapname,"-"));
		
		if ($dh = opendir(__DIR__ . "/..")) {
			while (($file = readdir($dh)) !== false) {
				if(stripos($file,"utex_")===0 && stripos($file,".txt")!==false){
					
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
			if(strtolower($mapname)==strtolower($pname_no_ext) || name2id($mapname)==name2id($pname_no_ext)){
				return $purl;
			}
		}
		return false;
	}
	
	prefetch();
}
?>