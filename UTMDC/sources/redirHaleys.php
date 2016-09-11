<?php

namespace UTMDC\RedirectHaleysHotHouse{
	use \DOMDocument;
	require_once(__DIR__ . "/common.php");
	
	
	function prefetch(){
		$GLOBALS['request_interval']=5000;
		prefetchForUrl("","msull");
	}
	
	function prefetchForUrl($url,$fname,$recursive=false){
		$urx="http://tourian.jchost.net/$fname/";
		$localDbFile=__DIR__ . "/../redir_halyeshothouse_$fname.txt";

		$plx=prefetchArray($urx,$fname,$recursive?0:-1);
		if(!file_exists($localDbFile)){
			//$mlist="";
			$f=fopen($localDbFile,"w+b");
			for($i=0; $i<count($plx); $i++){
				$pname=$plx[$i][0];
				$purl=$plx[$i][1];
				//$mlist.="$pname\\$purl\r\n";
				fwrite($f,"$pname\\$purl\r\n");
			}
			fclose($f);
			//file_put_contents($localDbFile,$mlist);
		}else{
			
		}
	}
	
	function prefetchArray($url,$fname){
		
		$localHtFile=__DIR__ . "/../redir_halyeshothouse_$fname.html";
		if(file_exists($localHtFile)){
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
		$matc=preg_match_all('#alt="\[   \]" /> <a href="([^"]*)">([^<]*)</a>#',$ht,$matz);
		if($matc){
			for($i=0; $i<$matc; $i++){
				$pname=htmlspecialchars_decode(trim($matz[2][$i]));
				
				$purl=getFullUrl(htmlspecialchars_decode($matz[1][$i]),$url);
				$pname=rawurldecode(substr($purl,strrpos($purl,"/")+1));
				$maxz[]=array(0=>$pname,1=>$purl);
			}
			
		}
		return $maxz;
	}
	
	function findPackageUrl($pak){
		findPackageInFile($pak,"msull");
	}
	
	function findPackageInFile($pak,$file){
		$fx=file(__DIR__ . "/../redir_halyeshothouse_$file.txt",FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES);
		foreach($fx as $fa){
			$pname=strtok($fa,"\\");
			$purl=strtok("\r");
			$pname_no_ext=substr($pname,0,strpos($pname,"."));
			if($pname_no_ext=="") continue;
			if(strtolower($pak)==strtolower($pname_no_ext)){
				return $purl;
			}
		}
		return false;
	}

	
	prefetch();
	
}	
?>