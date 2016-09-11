<?php


// stolen from my other project (tlyrandomquotes)
$spoof_ctx=array();

$request_interval=3000;
$last_request_time=0;

ini_set ("user_agent","Mozilla/5.0 (Windows NT 10.0) UTTracker/MapIndexer (+http://tracker.ut99.tk/static/utmapfetcher.html)");

$debugCurlLastUrl="";

function spoof_req($url,&$data=null,$followredir=false,$ignore_limits=false){
	global $spoof_ctx,$last_request_time,$request_interval;
	

	
	$host=parse_url($url,PHP_URL_HOST);
	if(isset($spoof_ctx[$host])){
		$ch=$spoof_ctx[$host];
	}else{
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
		if($followredir) curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($ch, CURLOPT_FORBID_REUSE, false);

		curl_setopt($ch, CURLOPT_HTTPHEADER,array(
		"Accept-language: pl,en-us;q=0.7,en;q=0.3" ,
		"Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8"
		));
		curl_setopt($ch,CURLOPT_USERAGENT,"Mozilla/5.0 (Windows NT 10.0) UTTracker/MapIndexer (+http://tracker.ut99.tk/static/utmapfetcher.html)"); 
		//curl_setopt($ch,CURLOPT_USERAGENT,"Mozilla/5.0 (Windows NT 6.1; rv:33.0) Gecko/20100101 Firefox/33.0");  
		//$cookfile = __DIR__ . "/../cookies/".get_tld_from_url($url).".txt";
		//curl_setopt($ch, CURLOPT_COOKIEJAR, $cookfile);
		//curl_setopt($ch, CURLOPT_COOKIEFILE, $cookfile);
		$spoof_ctx[$host]=$ch;
	}
	if(isset($data['referer'])){
		curl_setopt($ch, CURLOPT_REFERER, $data['referer']);
	}
	curl_setopt($ch, CURLOPT_URL, $url);
	$request_time=microtime(true);
	$sleeptime=($request_interval-($request_time-$last_request_time))/1000;
	if($sleeptime>1.0 /*&& !$ignore_limits*/){
		echo "SLEEPX=".round($sleeptime)."<br>";
		sleep(round($sleeptime));
		//sleep(3);
	}
	$GLOBALS['debugCurlLastUrl']=$url;
	$tx=curl_exec($ch);
	//echo strlen($tx);
	$data=curl_getinfo($ch);
	$last_request_time=microtime(true);
	//curl_close($ch);
	return $tx;
}

function hehe_notbrutal($tex,$st,$end=NULL){
	return hehe($tex,$st,$end);
}

function hehe(&$tex, $st, $end=NULL){
	$tokpoz=strpos($tex, $st);
	$poz=$tokpoz+strlen($st);
	$tex2=$tex;
	if($end==NULL){
		$endi2=NULL;
	}else{
		$texz=substr($tex,$poz);
		$endi2=strpos($texz,$end);
		if($endi2){
			$tex=substr($texz, $endi2);
		}
		/*$endi=strpos($tex, $end, $poz);
		$tex2=$tex;
		if($endi !== false) $tex=substr($tex, $endi+strlen($end));
		$endi2=$endi-$poz;*/
	}
	return substr($tex2, $poz, $endi2);
}
function getFullUrl($newurl, $refurl){
	
	$xd=explode("/",$newurl);
	$nxd=array();
	foreach($xd as $kx=>$vc){
		if($vc=="."){
			continue;
		}else if($vc==".."){
			array_pop($nxd);
			continue;
		}else{
			array_push($nxd,$vc);
		}		
	}
	
	$newurl=implode("/",$nxd);
	$purl=parse_url ($newurl);
	$poldurl=parse_url ($refurl);
	

	
	if(strpos($newurl,"//")===0) $purl['scheme']=$poldurl['scheme']; // for addresses like: "//www.example.com/fdsnfgh"
	if(isset($purl['scheme']) && $purl['scheme']!='http' && $purl['scheme']!='https') return "";
	if(!isset($purl['host'])){
		$purl_query=(isset($purl['query'])?"?".$purl['query']:"");
		$poldurl_query=(isset($poldurl['query'])?"?".$poldurl['query']:"");
		
		if(!isset($purl['path']) || $purl['path']==""){
			$poldurl_frag=(isset($poldurl['fragment'])?"#".$poldurl['fragment']:"");
			//$newurl="{$poldurl['scheme']}://{$poldurl['host']}{$poldurl['path']}$poldurl_query$poldurl_frag";
			if($poldurl_frag!="") return "";
		}else{
			if($purl['path'][0]!="/"){ //relative url
				$newurl="{$poldurl['scheme']}://{$poldurl['host']}".substr($poldurl['path'],0,strrpos($poldurl['path'],"/"))."/{$purl['path']}$purl_query";
			}else{
				$newurl="{$poldurl['scheme']}://{$poldurl['host']}{$purl['path']}$purl_query";
			}
		}
	}
	return $newurl;
}

function fileIsOld($file){
	//return false;
	return !isset($GLOBALS['isFrontend']) && (time()-filemtime($file) > 86400*30);
}

function haveTimeForUpdate(){
	return PHP_SAPI==='cli';
	//return (microtime(true)-$GLOBALS['starttime']) < 7;
}

function dibsOn($fh){
	return flock($fh, LOCK_EX|LOCK_NB);
}

function unlockFile($fh){
	return flock($fh, LOCK_UN);
}

if(!function_exists("name2id")){
	function name2id($sx){
		$s=strtolower($sx);
		
		$res=substr(str_replace(" ","-",trim(preg_replace("/[^a-z0-9]+/"," ",$s))),0,30);
		
		return $res;
	}
}

$starttime=microtime(true);

?>