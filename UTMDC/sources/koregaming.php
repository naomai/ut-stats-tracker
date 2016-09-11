<?php

namespace UTMDC\KoreGaming{
	use \DOMDocument;
	require_once(__DIR__ . "/common.php");
	
	
	function prefetch(){
		$GLOBALS['request_interval']=5000; // we don't want to spam the site
		prefetchForUrl("Assault/","as");
		prefetchForUrl("DeathMatch/","dm");
		prefetchForUrl("CTF/","ctf");
		prefetchForUrl("CTF4/","ctf4");
		prefetchForUrl("CTFM/","ctfm");
		prefetchForUrl("BunnyTrack/BT/","bt");
		prefetchForUrl("BunnyTrack/CTF-BT/","cbt");
		prefetchForUrl("Jailbreak/","jb");
		prefetchForUrl("Monster%20Hunt/MonsterHunt/","mh");
		prefetchForUrl("SLV/","slv");
	}
	
	function prefetchForUrl($url,$fname){
		//$urx="http://medor.no-ip.org/index.php?dir=$url";
		$urx="http://koregaming.com/downloads/Unreal%20Tournament/Map%20Repository/$url";
		$localHtFile=__DIR__ . "/../koregaming_$fname.html";
		$localDbFile=__DIR__ . "/../koregaming_$fname.txt";
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
		
		if(!file_exists($localDbFile)){
			$mlist="";
			
			$matc=preg_match_all("/<td><a href=([^>]*)><img src=[^>]*> ([^<]*)<\/a><\/td>/",$ht,$matz);
			if($matc){
				for($i=0; $i<$matc; $i++){
					$mlist.="{$matz[2][$i]}\\{$matz[1][$i]}\r\n";
				}
				
			}
			
			/*while(($url=hehe($ht,"					<td><a href=","><img "))!==false){
				$name=hehe($ht,"> ","</a></td>");
				if($name!==false){
					$mlist.="$url\\$name\r\n";
					echo "$url\\$name<br>";
					
				}
			}*/
			
			file_put_contents($localDbFile,$mlist);
		}else{
			
		}
	}
	
	function findMapUrl($mapname){
		$gamemode=strtoupper(strtok($mapname,"-"));
		if ($dh = opendir(__DIR__ . "/..")) {
			while (($file = readdir($dh)) !== false) {
				if(stripos($file,"koregaming_")===0 && stripos($file,".txt")!==false){
					if(($gamemode=="DM" && stripos($file,"dm")===false && stripos($file,"af")===false) || ($gamemode=="CTF" && stripos($file,"ctf")===false && stripos($file,"bt")===false && stripos($file,"slv")===false)){
						//echo "KG:NO$file;";
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
			if(strtolower($mapname)==strtolower($pname_no_ext)){
				return $purl;
			}
		}
		return false;
	}
	
	prefetch();
}
?>