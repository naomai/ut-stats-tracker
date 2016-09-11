<?php

namespace UTMDC\UTFilesCom{
	use \DOMDocument;
	require_once(__DIR__ . "/common.php");
	
	
	function prefetch(){
		$GLOBALS['request_interval']=5000; // "Please do not hammer this site! You must wait a few seconds before trying again."
		prefetchForUrl("Airfight/","af");
		prefetchForUrl("Assault/","as");
		prefetchForUrl("CTF/","ctf");
		prefetchForUrl("CTF4/","ctf4");
		prefetchForUrl("CTFM/","ctfm");
		prefetchForUrl("DeathMatch/","dm",true);
		prefetchForUrl("BunnyTrack/","bt1");
		prefetchForUrl("BunnyTrack/BT/","bt2");
		prefetchForUrl("BunnyTrack/CTF-BT/","cbt");
		prefetchForUrl("Jailbreak/","jb");
		prefetchForUrl("MonsterHunt/","mh");
		prefetchForUrl("SLV/","slv");
	}
	
	function prefetchForUrl($url,$fname,$recursive=false){
		$urx="http://ut-files.com/index.php?dir=Maps/$url";
		$localDbFile=__DIR__ . "/../utfiles_$fname.txt";

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
			$localHtFile=__DIR__ . "/../utfiles_$fname"."_$recfname.html";
		}else{
			$localHtFile=__DIR__ . "/../utfiles_$fname.html";
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
		$matc=preg_match_all('/  <td class="autoindex_td">\n   <a class="autoindex_a" href="([^>]*)">\n    <img width="16" height="16" alt="\[([a-z0-9]*)\]" src="[a-z0-9_\-.\/]*" \/>([^<]*)<\/a>/',$ht,$matz);
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
				
				//echo "$pname\\$purl<br/>";
			}
			
		}
		return $maxz;
	}
	
	function findMapUrl($mapname){
		$gamemode=strtoupper(strtok($mapname,"-"));
		
		if ($dh = opendir(__DIR__ . "/..")) {
			while (($file = readdir($dh)) !== false) {
				if(stripos($file,"utfiles_")===0 && stripos($file,".txt")!==false){
					if(($gamemode=="DM" && stripos($file,"dm")===false && stripos($file,"af")===false) || ($gamemode=="CTF" && stripos($file,"ctf")===false && stripos($file,"bt")===false && stripos($file,"slv")===false)) {
						//echo "UF:NO$file;";
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