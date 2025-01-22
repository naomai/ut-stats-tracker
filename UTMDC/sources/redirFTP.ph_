<?php

namespace UTMDC\RedirectFTP{
	use \DOMDocument;
	require_once(__DIR__ . "/common.php");
	
	
	function prefetch(){
		$GLOBALS['request_interval']=5000;
		prefetchForUrl("206.212.247.62","ut99","ip206");
	}
	
	function prefetchForUrl($ip,$dir,$fname){
		
		$localDbFile=__DIR__ . "/../redir_ftps_$fname.txt";
		
		$plx=prefetchArray($ip,$dir,$fname);
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
	
	function prefetchArray($ip,$dir,$fname){
		
		if(!file_exists(__DIR__ . "/../redir_ftps_$fname.txt")){
			$ht=ftpFilesList($ip,$dir);
			if(count($ht)){
				return $ht;
			}
		}
		
		
	}
	
	function findPackageUrl($pak){
		if ($dh = opendir(__DIR__ . "/..")) {
			while (($file = readdir($dh)) !== false) {
				if(stripos($file,"redir_ftps_")===0 && stripos($file,".txt")!==false){
					$fx=file(__DIR__ . "/../$file",FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES);
					foreach($fx as $fa){
						$pname=strtok($fa,"\\");
						$purl=strtok("\r");
						$pname_no_ext=substr($pname,0,strpos($pname,"."));
						if($pname_no_ext=="") continue;
						if(strtolower($pak)==strtolower($pname_no_ext)){
							return $purl;
						}
					}
				}
				
			}
			closedir($dh);
		}
		return false;
	}

	
	prefetch();
	
	function ftpFilesList($ip,$dir){
		$con = ftp_connect($ip);
		ftp_login($con,"anonymous","");
		ftp_pasv($con,true);
		$list = ftp_nlist($con,$dir);
		$newList=array();
		foreach($list as $item){
			if(strpos($item,".uz")!==false)
				$newList[]=array(0=>$item,1=>"ftp://anonymous@$ip/$dir/$item");;
		}
		return $newList;
		
	}
	
}	
?>