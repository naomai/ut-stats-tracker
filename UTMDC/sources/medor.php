<?php

namespace UTMDC\Medor{
	use \DOMDocument;
	require_once(__DIR__ . "/common.php");
	
	
	function prefetch(){
		$GLOBALS['request_interval']=5000; // "Please do not hammer this site! You must wait a few seconds before trying again."
		prefetchForUrl("BunnyTrack/","bt");
		prefetchForUrl("MonsterHunt/","mh");
		prefetchForUrl("DeathMatch/","dm");
		prefetchForUrl("Siege/","sgi");
		prefetchForUrl("Domination/","dom");
		prefetchForUrl("CTF/","ctf");
	}
	
	function prefetchForUrl($url,$fname,$recursive=false){
		//$urx="http://medor.no-ip.org/Maps/$url";
		$urx="http://medor.no-ip.org/index.php?dir=Maps/$url";
		$localDbFile=__DIR__ . "/../medor_$fname.txt";

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
			$localHtFile=__DIR__ . "/../medor_$fname"."_$recfname.html";
		}else{
			$localHtFile=__DIR__ . "/../medor_$fname.html";
		}
		$fh=false;
		try{
			if(!haveTimeForUpdate() || (file_exists($localHtFile) && !fileIsOld($localHtFile))){
				throw new \Exception("XD0047"); // todo: custom exception
			}else{
				//solve the concurrency problem
				$fh=fopen($localHtFile,"c");
				if(dibsOn($fh)){
					$ht=spoof_req($url);
					if(strlen($ht)<50){
						throw new \Exception("XD0047"); 
					}else{
						ftruncate($fh,0);
						fseek($fh,0,SEEK_SET);
						fwrite($fh,$ht);
					}
				}else{
					
					throw new \Exception("XD0047"); 
				}
			}
		}catch(\Exception $e){
			if($e->getMessage() === "XD0047") $ht=file_get_contents($localHtFile);
		}finally{
			if($fh!==false) {
				unlockFile($fh);
				fclose($fh);
			}
		}
		$maxz=array();
		//$matc=preg_match_all('/<tr><td valign="top"><img src="[^"]*" alt="\[([A-Za-z0-9\s]*)\]"><\/td><td><a href="([^"]*)">([^<]*)<\/a>/',$ht,$matz);
		//$matc=preg_match_all('/<tr class="[lightdark]*_row"><td class="default_td" align="left" valign="top"><a class="default_a" href="([^"]*)"><img[^>]*alt="\[([^\]]*)\]"[^>]*src="[^"]*" \/> ([^<]*)<\/a>/',$ht,$matz);
		$matc=preg_match_all('/<a class="autoindex_a" href="([^"]*)">\s*<img[^>]*alt="\[([^\]]*)\]"[^>]*src="[^"]*" \/>\s*([^<]*)<\/a>/',$ht,$matz);
		if($matc){
			for($i=0; $i<$matc; $i++){
				$pname=trim($matz[3][$i]);
				if($pname=="Parent Directory") continue;
				
				$purl=getFullUrl($matz[1][$i],$url);
				if(trim($matz[2][$i])=="dir") {
					if($recursive!=-1){
						$newx=prefetchArray($purl,$fname,$recursive+1,$pname);
						$maxz=array_merge($maxz,$newx);
					}else{
						continue;
					}
				}else{
					$maxz[]=array(0=>$pname,1=>$purl);
				}
				
				//echo "$pname\\$purl<br>";
			}
			
		}
		return $maxz;
	}
	
	function findMapUrl($mapname){
		$gamemode=strtoupper(strtok($mapname,"-"));
		if ($dh = opendir(__DIR__ . "/..")) {
			while (($file = readdir($dh)) !== false) {
				if(stripos($file,"medor_")===0 && stripos($file,".txt")!==false){
					if($gamemode=="DM" && stripos($file,"dm")===false) {
						//echo "ME:NO$file;";
						continue;
					}
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
		$type = strtok($mapname,"-");
		$mapTitle = strtok("\r");
		if($mapTitle===false){
			$type="";
			$mapTitle=$mapname;
		}
		
		foreach($fx as $fa){
			$pname=strtok($fa,"\\");
			$purl=strtok("\r");
			$pname_no_ext=substr($pname,0,strrpos($pname,"."));
			
			
			if(
				strcasecmp($mapname,$pname_no_ext)===0 ||
				(stripos($pname_no_ext, $type)!==false && stripos($pname_no_ext, $mapTitle)!==false)
			){
				return $purl;
			}
		}
		return false;
	}
	
	prefetch();
	//echo findMapUrlInFile("MH-nara_Beta+Fix2",'C:\Users\bun\Desktop\PHPTrash\uttracker\nightly\utmdc\medor_mh.txt');
}	
?>