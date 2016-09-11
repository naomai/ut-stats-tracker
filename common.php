<?php 
/*
 * mta tracker
 * 2009 blackmore
 *
 * "little main"
**/
	if(php_sapi_name()!="cli"){
		ob_start();
	}
	/* // send all clients except you to info page
	$userIp=(isset($_SERVER['HTTP_CF_CONNECTING_IP'])?$_SERVER['HTTP_CF_CONNECTING_IP']:$_SERVER['REMOTE_ADDR']);

	if($userIp != "YOUR.IP.ADDRESS") {
		require_once __DIR__ . "/offline.php";
		die;
	}
	*/

	//require_once "N14Inc/ErrorHandler.php";
	require_once __DIR__."/graphcommon.php";
	require_once __DIR__."/config.php";
	require_once __DIR__."/N14Inc/Locale.php";
	require_once __DIR__."/N14Inc/Pagi.php";

	require_once N14CORE_LOCATION."/ModularApp.php";
	//require_once "includes/dummylog.php";
	
	use \N14\GetText as GetText;
	
	list($phpMajor, $phpMinor) = explode(".",  phpversion());

	
	// BROKEN!! REWRITE USING NCORE SESS 
	/*if(php_sapi_name()!="cli"){
		session_name("N14GTLang");
		session_start();
		setcookie(session_name(),session_id(),time()+86400*365*2,"/");
	}
	
	if(!isset($_SESSION['ngt_userlang']) || isset($_GET['resetlang'])){
		$browserLocale=GetText\find_locale_by_httprequest();
		if($browserLocale){
			$_SESSION['ngt_userlang']=$browserLocale;
		}else{
			$_SESSION['ngt_userlang']=$defaultLocale;
		}
		//$firstVisit=true;
	}else if(isset($_SESSION['ngt_userlang']) && $_SESSION['ngt_userlang']!=$defaultLocale && !isset($_GET['lang'])){
		//echo "REDIR2LOC";
	}
	
	if(isset($_GET['lang']) && (
		!(isset($_SERVER['HTTP_REFERER']) && strpos($_SERVER['HTTP_REFERER'],$site_host)===false)
	)){
		if(GetText\locale_exists($_GET['lang'])){
			$_SESSION['ngt_userlang']=$_GET['lang'];
			
		}
	}
	
	GetText\setlocale($_SESSION['ngt_userlang']);*/
	GetText\setlocale("en");	
	$_SESSION['nutt_lastreq']=time();
	
	//$log = new DummyLog();
	//$modMaster = new N14\ModuleMaster($log);
	
	//$abspath="/uttracker";
	$abspath=$sub_site_url;
	
	$dateformat="d-m-Y H:i";
	
	//$rewriteParams=array();
	
	/*
	$loc="en_EN";
	putenv("LANG=$loc" ); 
	setlocale(LC_ALL, "$loc");
	bindtextdomain("messages", "./Locale"); 
	bind_textdomain_codeset("messages", 'UTF-8');
	textdomain("messages");
	*/
	
	const LPLAYER=0;
	const LSERVER=1;
	const LGAME=2;
	const LFILE=3;
	const LSTATICFILE=4;
	const LMAP=5;
	const LSEARCHPLAYER=6;
	const LSEARCHSERVER=7;
	const LMAPLAYOUT=8;
	
	//FirePHP::getInstance(true)->log("Commom.php!");
	
        /**
         * 
         * @param string $paramname Request parameter to be removed from URL when doing relative request
         */
	function addRewriteParam($paramname){
		if(!isset($GLOBALS['rewriteParams'])){
			$GLOBALS['rewriteParams']=array();
		}
		$GLOBALS['rewriteParams'][$paramname]=true;
		
	}
	
	function requestFilterRewriteParams(&$req){
		if(isset($GLOBALS['rewriteParams'])){
			foreach($GLOBALS['rewriteParams'] as $pn=>$tru){
				
				if(isset($req[$pn])) unset($req[$pn]);
			}
		}
	}
	
	function getSkinImage($mesh,$skin,$face,$floatleft=false){
		global $assetsPath,$assetsPathLocal;
		$skinn=arrLast(explode(".",$skin));
		$facen=arrLast(explode(".",$face));
		$gfxf=strtolower("$skinn"."_$facen");
		$addcl=($floatleft?" imgleft":"");
		if($skinFile=checkForAssetRemote("skins/$gfxf.jpg")) {
			return "<img src='$skinFile' alt='$mesh / $skin / $face' class='uttr_skin$addcl'/>\n";
		}else if(strtolower($mesh)=="nali cow") {
			$skinFile = checkForAssetRemote("skins/cow-face.jpg");
			return "<img src='$skinFile' alt='Nali Cow' class='uttr_skin$addcl'/>\n";
		}else if(strtolower($mesh)=="boss") {
			$skinFile = checkForAssetRemote("skins/boss_xan.jpg");
			return "<img src='$skinFile' alt='Boss' class='uttr_skin$addcl'/>\n";
		}else if(strtolower($mesh)=="spectator") {
			$skinFile = checkForAssetRemote("skins/s_camera.jpg");
			return "<img src='$skinFile' alt='Spectator' class='uttr_skin$addcl'/>\n";
		}else{
			return "<div class='uttr_skin unknown$addcl'><br><br><br>$mesh</div>\n";
		}
	}
	function getflag($c){
		global $assetsPath,$assetsPathLocal;
		if(isset($GLOBALS['isAF15']) && $GLOBALS['isAF15']) return $flag="<img src='$assetsPath/uttfavRemixed.png' class='cflag' title='".countryName($c)."' alt='{$c}'/>";
		
		if($c!="" && $flagPath=checkForAssetRemote("flags/{$c}.gif")) 
			$flag="<img src='$flagPath' class='cflag' title='".countryName($c)."' alt='{$c}'/>";
		else 
			$flag="";
		return $flag;
	}
        
	/**
         * Generates URL referring to supplied object for N14 app
         * @param int $type Type of link to create
         * @param mixed $id (string/int)Object ID or (array)object data
         * @param string $name Optional, object name
         * @param mixed $params Optional, (array)additional query parameters or (string)contents of "s" parameter
         * @param bool $noLang Optional, don't add "lang" parameter to the URL
         * @return string 
         */
	function maklink($type,$id,$name=null,$params=null,$noLang=false){
		global $abspath,$assetsPath,$site_host;
		
		
		$reqParams=array();
		if($params!==null) {
			if(is_array($params)){
				$reqParams = $reqParams + $params;
			}else{
				//$reqParams['s']=$params; // 16-04-02 NODEVEL, removing some stats features
			}
		}
		/*if(!$noLang){
			$reqParams['lang']=GetText\getlocale();
		}*/
		$q=http_build_query($reqParams);
		if($q!="") $q="?".$q;
		switch($type){
			case LPLAYER: 
				if(is_array($id)){
					$playerData = $id;
					$name = $playerData['name'];
					$id = $playerData['id'];
				}
				return "$abspath/player/$id-".name2id($name)."$q";
			case LSERVER: 
				if(is_array($id)){
					$addy="";
					$serverData = $id;
					$name = $serverData['name'];
					/*$id = $serverData['serverid'];*/
					$ip = getServerIpWithHostPort($serverData['address']);
					if(isset($reqParams['page'])){
						$addy=";{$reqParams['page']}";
						unset($reqParams['page']);
						$q=http_build_query($reqParams);
						if($q!="") $q="?".$q;
					}
					return "$abspath/server/$ip%5E".name2id($name)."$addy$q";
				}
				return "$abspath/server/$id-".name2id($name)."$q";
			case LGAME: 
				return "$abspath/server/$name/game$id.htm$q";
			case LFILE: 
				$file=ltrim(strtok(" ".$id,"?"),"/ ");
				$query=strtok("\r");
				$queryArr=null;
				parse_str($query,$queryArr);
				$reqParams=$reqParams+$queryArr;
				$q=http_build_query($reqParams);
				if($q!="") $q="?".$q;
				if(strlen($id) && $id[0]=="/") 
					return "//$site_host$id$q";
				return "$abspath/$file$q";
			case LSTATICFILE: 
				$file = ltrim($id,"/ ");
				$filePath=checkForAssetRemote($file);
				if($filePath)
					return $filePath;
				else
					return "$assetsPath/$file";
			case LMAP: 
				return "$abspath/map/".urlencode($name)."$q";
			case LSEARCHPLAYER: 
				/*$reqParams['playerSearch']=$name;
				$q=http_build_query($reqParams);
				return "$abspath/search.php?$q&$id";*/
				$q=http_build_query($reqParams);
				return "$abspath/search/player/".urlencode($name)."$q";
			case LSEARCHSERVER: 
				$reqParams['serverName']=$name;
				$q=http_build_query($reqParams);
			case LMAPLAYOUT: 
				$reqParams=$reqParams+array("map"=>$name, "projmode"=>$id);
				$q=http_build_query($reqParams);
				return "$abspath/WireframeRenderer/renderpolywithsprites.php?$q";
			return "$abspath/?$q&$id";
			
		}
	}
	
	function maklinkHtml($type,$id,$name=null,$params=null,$noLang=false){
		return htmlspecialchars(maklink($type,$id,$name,$params,$noLang));
	}
	
	function checkForAsset($file){
		global $assetsGenericPathLocal,$assetsPathLocal;
		if($file[0]!="/") $file = "/".$file;
		if(file_exists($assetsGenericPathLocal.$file)) return $assetsGenericPathLocal.$file;
		else if(file_exists($assetsPathLocal.$file)) return $assetsPathLocal.$file;
		else return false;
	}
	
	function checkForAssetRemote($file){
		global $assetsGenericPathLocal,$assetsPathLocal,$assetsGenericPath,$assetsPath;
		if($file[0]!="/") $file = "/".$file;
		if(file_exists($assetsGenericPathLocal.$file)) return $assetsGenericPath.$file;
		else if(file_exists($assetsPathLocal.$file)) return $assetsPath.$file;
		
		else return false;
	}
	
	function getServerIpWithHostPort($addr){
		list($ip,$port) = explode(":",$addr);
		return $ip . ":" . ($port-1);
		
	}
	
	// ??? no idea what was the point of this
	function serializename($nm){
		$news="";
		for ($i = 0; $i < strlen($nm); $i++):
			$cx = ord($nm{$i});
			if (($cx >= 48 && $cx <= 57) || ($cx >= 65 && $cx <= 90) || ($cx >= 97 && $cx <= 122) || $cx == 61 || $cx == 91 || $cx == 93 || $cx == 95 || $cx == 123 || $cx == 125 || $cx == 40 || $cx == 41 || ($cx >= 43 && $cx <= 46) || $cx == 32 || $cx == 33 || $cx == 36 || $cx == 38 || $cx == 39 ):
				$news .= chr($cx);
			else:
				$news .= "%" . strtoupper(dechex($cx));
			endif;
		endfor;
		return $news;
	}
	
        /**
         * Issue a 301 Moved Permanently redirect and finish the execution of script.
         * @param string $url Destination URL
         */
	function permredir($url){
		ob_end_clean();
		header("HTTP/1.1 301 Moved Permanently");
		header("Location: $url");
		header("Cache-Control: no-store, no-cache, must-revalidate");
		header("Expires: Thu, 01 Jan 1970 00:00:00 GMT");
		exit;
	}
        /**
         * Display an error page when user tries to access nonexistent resource.
         * @param type $req Optional, name of requested resource
         */
	function triggerNotFoundError($req=null){
            global $requestPage,$req,$n14app;
            ob_end_clean();
            if($req!==null) 
                $requestPage=$req;
            else if(!isset($requestPage)) 
                $requestPage=$_SERVER['REQUEST_URI'];

            if(file_exists("404.php")){
                include "404.php";
            }else{
                echo "<h1>404 Not Found</h1><p>The requested resource: $requestPage could not be found. Also, the error landing page was not created for \"".$n14app['id']."\".</p>";
            }
            exit;
	}
        
        /**
         * Alias for triggerNotFoundError
         * @deprecated since version 58
         * @param type $req Optional, name of requested resource
         */
        function error404($req=null){
            triggerNotFoundError($req);
        }
	
	function colAsKey(&$ar,$col){
		$newar=array();
		foreach($ar as $kn=>$xd){
			//echo "AAAAAAAAAAAAAAAA";
			$newar[$xd[$col]]=&$xd;
			unset($xd);
			unset($ar[$kn]);
		}
		$ar=$newar;
	}
	
	/* date & timestamp formatting, LAME!! */
	/**
         * Formats the timestamp relatively to the present date. 
         * If the referred date was more than two days before, formats it using the global $dateformat configured of N14APP
         * @param int $d Unix timestamp of date to be formatted
         * @param boolean $relative Use relative adverbs (Today, Yesterday, X time ago)
         * @global string $c 
         * @return string The formatted date
         */
	function uttdateFmt($d,$relative=true){
		global $dateformat;
                
		if($relative && $d > time() - dssm()-86400*7) 
			return niceDate($d);
		else if($d==0) 
			return "???";
		else 
			return date($dateformat,$d);
	}
	
        /**
         * Formats the timestamp relatively to the present date, using relative adverbs (Today, Yesterday, X time ago)
         * @param int $d Unix timestamp of date to be formatted
         * @return string The formatted date
         */
	function niceDate($d){
		if($d > time() - 3600) return sprintf(__("%1\$d min. ago"),floor((time()-$d)/60));
		else if($d > time() - dssm()) return sprintf(__("Today, %1\$s"),date("G:i",$d));
		else if($d > time() - dssm()-86400) return sprintf(__("Yesterday, %1\$s"),date("G:i",$d));
		else if($d == 0) return "??";
		else return sprintf(__("%1\$s ago"),formattime((time()-$d)/3600));
	}
	/**
         * Creates friendly timespan string from short intervals (with seconds-precision)
         * @param float $seconds Number of seconds
         * @return string The formatted time interval
         */
	function formattimesmall($seconds){
		//return ($hours<1 ? "&lt; 1 h":((floor($hours)>=24)?floor($hours/24)." d ":"").(floor($hours)%24)." h");
		return ((floor($seconds)>=3600)?floor($seconds/3600)." h ":"").($seconds>=60?floor(($seconds%3600)/60) ." min ":"") . (floor($seconds)%60) ." s";
	}
        
        /**
         * Creates friendly timespan string from big timespans (above minute)
         * @param float $hours Number of hours
         * @return string The formatted time interval
         */
	function formattime($hours){
		//return ($hours<1 ? "&lt; 1 h":((floor($hours)>=24)?floor($hours/24)." d ":"").(floor($hours)%24)." h");
		//return ((floor($hours)>=24)?floor($hours/24)." d ":"").($hours>=1?(floor($hours)%24)." h ":"") . (floor($hours*60)%60) ." min";
		return 	((floor($hours)>=720) ? floor($hours/720)." mo " : "").
				((floor($hours)>=24) ? (($hours/24)%30)." d " : "").
				($hours>=1&&$hours<24*5 ? (floor($hours)%24)." h " : "") . 
				($hours < 2 && $hours>=0 ? (floor($hours*60)%60) ." min" : "").
				($hours < 0  ? "[UTT_ACHTUNG!Corrupted timespan]" : ""); // < 2016-03-20 
	}
	/**
         * Creates readable time interval using formats: 
         *  MM:SS for intervals below 1 hour
         *  HH:MM:SS more than 1 hour
         * @param int $seconds Time interval in seconds
         * @return string The formatted time interval
         */
	function shortTimeInterval($seconds){
		$sign = $seconds<0 ? "-" : "";
		$secondsAbsolute = abs($seconds);
		$fullSeconds = $secondsAbsolute%60;
		$fullMinutes = floor($secondsAbsolute/60) % 60;
		$fullHours = floor($secondsAbsolute/3600);
		$result = "";
		$result .= $sign;
		if($fullHours) $result.=$fullHours . ":";
		$result.= str_pad($fullMinutes,2,"0",STR_PAD_LEFT) . ":";
		$result.= str_pad($fullSeconds,2,"0",STR_PAD_LEFT);
		return $result;
	}
	
	/* converts VB-style date to Unix timestamp */
	function strtotimeX($str){
		return date_create_from_format ("m-d-Y H:i:s",$str)->getTimestamp ();
	}
	
        /**
         * Gets number of seconds since midnight
         * @return int Seconds since midnight
         */
	function dssm(){ 
		return date("G")*3600 + date("i") * 60 + date("s");
	}
	if(!function_exists("name2id")){
		function name2id($sx){
			$s=strtolower($sx);
			$s=str_replace(
				array('$',  /*"!",*/"@","{}v{}","(.)(.)", "(.y.)",  ")-(",")v(","|<","()","'//","'/","|_|","|_","/-]","|-|"),
				array("s" , /*"i",*/"a","m",    " boobs "," boobs ","h"  ,"m"  ,"k" ,"o" ,"w",  "y" ,"u"  ,"l" ,"a"  ,"h"),
			$s);
			$res=substr(str_replace(" ","-",trim(preg_replace("/[^a-z0-9]+/"," ",$s))),0,30);
			if(strlen($res)<2){
				$res=name2idLITERAL($sx);
			}
			return $res;
		}
		
		function name2idLITERAL($s){
			$s=str_replace(
				array('$',       '#',     '!','.....',       '.',  '+',     '~',      '}:',   '|',             '"',    "&",    "%",        "*"),
				array(' dollar ',' hash ','a',' lots of dots ','dot',' plus ',' tilde ',' cow ',' vertical bar ',"quote"," and "," percent "," star "),
			$s);

			return substr(str_replace(" ","-",trim(preg_replace("/[^a-z0-9]+/"," ",$s))),0,30);
			
		}
	}
	
	
	/* detect server type ("tags") from fused serverinfo & serverrules 
	TODO prettier configurable way
	*/
	const SERVERTAGS_GAMEMODE=1;
	const SERVERTAGS_MUTATOR=2;
	
	function getServerTags($s,$type=255){
		$gtypes=array();
		$gtx=strtok($s['mapname'],"-");
		$mn=strtok("-");	
		$lcMutators = isset($s['mutators']) ? strtolower($s['mutators']) : ""; // to avoid using stripos
		$lcName = isset($s['hostname']) ? strtolower($s['hostname']) : "";
		
		$typeGM = $type & SERVERTAGS_GAMEMODE;
		$typeMUT = $type & SERVERTAGS_MUTATOR;
		
		if($typeGM){
			if(($gtx=="CTF" && $mn=="BT") || $gtx=="BT"){
				$gtypes[]="BT";
			}else{
				switch(strtolower($s['gametype'])){
					case "deathmatchplus": $gtypes[]="DM"; break;
					case "teamgameplus": $gtypes[]="TDM"; break;
					case "ctfgame": $gtypes[]="CTF"; break;
					case "domination": $gtypes[]="DOM"; break;
					case "lastmanstanding": $gtypes[]="LMS"; break;
					case "thieverydeathmatchplus": $gtypes[]="TH"; break;
					case "monsterhunt": $gtypes[]="MH"; break;
					case "idm": $gtypes[]="DM"; $gtypes[]="Insta"; break;
					case "ictf": $gtypes[]="CTF"; $gtypes[]="Insta"; break;
					case "idom": $gtypes[]="DOM"; $gtypes[]="Insta"; break;
					case "sactf": $gtypes[]="CTF"; $gtypes[]="Sniper"; break;
					case "itdm": $gtypes[]="TDM"; $gtypes[]="Insta"; break;
					case "jailbreak": $gtypes[]="JB"; break;
					case "soccermatch": $gtypes[]="SCR"; break;
					case "shgame": $gtypes[]="SH"; break;
					case "coop game":
					case "coopgame2":
					case "tvcoop": 
					case "infcoopunrealgame": 
					$gtypes[]="Coop"; break;
					case "siegegi": $gtypes[]="SGI"; break;
					default: 
					
					if($mn!=""){
						$gtypes[]=$gtx;
						}else{
						$gtypes[]=$s['gametype'];
					}
					break;
				}
			}
			if(strpos($lcName,"funnel")!==false){
				$gtypes[]="FN";
			}
		}
		
		
		if($typeGM && (
		strpos($lcMutators,"combogib")!==false ||
		strpos($lcName,"combogib")!==false ||
		strpos($lcName,"comboinstagib")!==false)){
			$gtypes[]="Combo";
		}elseif($typeGM && (
		strpos($lcMutators,"zeroping accugib")!==false ||
		strpos($lcName,"insta")!==false) &&
		!isset($gtypes['BT'])){
			$gtypes[]="Insta";
		}elseif($typeMUT && strpos($lcName,"sniper")!==false){
			$gtypes[]="Sniper";
		}elseif(strpos($lcMutators,"nali weapons 3")!==false ||
		strpos($lcName,"nw3")!==false ||
		strpos($lcName,"nali weapons 3")!==false ||
		strpos($lcName,"naliweaponsiii")!==false){
			$gtypes[]="NW3";
		}elseif($typeMUT && (
		strpos($lcName,"all weapons")!==false ||
		strpos($lcName,"allweapons")!==false ||
		strpos($lcMutators,"all weapons")!==false ||
		strpos($lcMutators,"allweapons")!==false)){
			$gtypes[]="ALLWP";
		}
		if($typeMUT && (
		strpos($lcMutators,"grapple")!==false ||
		strpos($lcMutators,"grapplinghook")!==false ||
		strpos($lcName,"grapple")!==false)){
			$gtypes[]="Grapple";
		}
		if($typeMUT && (strpos($lcMutators,"map-vote")!==false)){
			$gtypes[]="MutMV";
		}
		if($typeMUT && (strpos($lcMutators,"smartctf")!==false)){
			$gtypes[]="MutSCTF";
		}
		if($typeMUT && (
		strpos($lcMutators,"doublejumput")!==false ||
		strpos($lcMutators,"[r]^sdj")!==false)){
			$gtypes[]="MutDJ";
		}
		if($typeMUT && (strpos($lcMutators,"relic: ")!==false)){
			$gtypes[]="MutRL";
		}
		if($typeMUT && (strpos($lcMutators,"btcheckpoints")!==false)){
			$gtypes[]="MutCP";
		}
		
		if($typeMUT && strpos($lcName,"pug")!==false){
			$gtypes[]="PUG";
		}
		if(isset($s['gamever']) && $s['gamever']>250 && $s['gamever']<=348){
			$gtypes[]="DEMO";
		}
		
		return array_unique ($gtypes);
		
	}
	
	
	/* like print_r, but outputs PHP code */
	function print_php($obj){
		$bt = debug_backtrace();
		$frame=array_shift($bt);
		$lines = file($frame['file']);
		$code = implode('', array_slice($lines, $frame['line'] - 1));
		
		preg_match('/print_php\s*\(\s*(.*)\s*\)\s*;/i', $code, $matches);
		$varname=$matches[1];
		echo sprint_php($obj,0,$varname);		
	}
	function sprint_php($obj,$depth=0,$varname=null){
		$res="";

		if($depth==0) $res.="$varname=";
		if(is_string($obj)){
			$res.="\"".addcslashes ($obj,"\"")."\"";
			
		}else if(is_numeric($obj)){
			$res.="$obj";
		}else if(is_bool($obj)){
			$res.=$obj?"true":"false";
		}else if(is_array($obj)){
			$res.="array(";
			$is_first=true;
			if(count($obj)>0){
				foreach($obj as $k=>$v){
					if($is_first) $is_first=false; else $res.=",";
					$res.="\n";
					$res.=padtab($depth+1)."\"".addcslashes ($k,"\"")."\" => " . sprint_php($v,$depth+1)."";
					
				}
				$res.="\n";
			}
			$res.=padtab($depth).")";
		} else if(is_object($obj)){


			$props=get_object_vars($obj);
			$res.="new ".get_class($obj)."();";
			if(count($props)>0){
				foreach($props as $k=>$v){
					$res.="\n";
					$res.=padtab($depth+1)."$varname->{".addcslashes ($k,'{}')."} = " . sprint_php($v,$depth+1)."";
					
				}
				$res.="\n";
			}
			$res.=padtab($depth).")";
		}else{
			$res.="null";
			
		}
		if($depth==0) $res.=";";
		return $res;
	}
	
	/* https://groups.google.com/d/msg/comp.lang.php/UFUAP0SubuQ/sgRLw7T_5icJ
	function bobo_the_clown($b) {
		$bt = debug_backtrace();
		extract(array_pop($bt));
		$lines = file($file);
		$code = implode('', array_slice($lines, $line - 1));
		preg_match('/\bbobo_the_clown\s*\(\s*(\S*?)\s*\)/i', $code, $matches);
		return @$matches[1];
	}
	*/
	
	function padtab($num){
		return str_pad ("",$num,"\t");
		
	}
	
	// ???
	function xunserialize($str){
		$xd=explode("|",$str);
		$r=array();
		if(count($xd)>0){
			foreach($xd as $x){
				$w=explode("=",$x,2);
				if(!isset($w[1])){
					$r[]=$w[0];
				}else{
					$r[$w[0]]=$w[1];
				}
			}
		}
		return $r;
	}
	
	function bound($x, $min, $max){
		 return min(max($x, $min), $max);
	}
	
	function http_file_exists($url){
		stream_context_set_default(array('http' => array('method' => 'HEAD')));
		$headers = get_headers($url);
		return (substr($headers[0], 9, 3)=="200");
	}
	
	/* SERVER RANKING ALGOS */
	
	/* RF Score
	 * Server popularity based on number of records from current week 
	 * "RF" has no meaning, it's just a bunch of random letters.
	 * But if you want, you can call it "Run Forrest"
	 * Below are tons of different variations, pick one you like the most
	 */
	/*function rf($a) {
		global $cd,$proccd;
		//return log(1+$a['pwuplayers']*(pow($a['records']-$a['pwrecords'],2)))/($a['pwrecords']/200+1500)*0.003;
		return log(1+$a['pwuplayers']*(pow($a['records']-$a['pwrecords'],2))/250)*0.0003;
		//return log(1+($a['records']-$a['pwrecords'])/($a['pwuplayers']+700))*0.0018;
	}*/
	function rfVersionSome($a) {
		global $cd,$proccd;
		//return log(1+$a['pwuplayers']*(pow($a['records']-$a['pwrecords'],2)))/($a['pwrecords']/200+1500)*0.003;
		return log(1+$a['pwuplayers']*(pow($a['records'],2))/250)*0.0003;
		//return log(1+($a['records']-$a['pwrecords'])/($a['pwuplayers']+700))*0.0018;
	}
	/* Displays equation of RF for the server;
	 * use when debugging
	 */
	function rfloud($a) {
		global $cd,$proccd;
		return "";
		//return "\$RF=log(1+(pow({$a['records']}-{$a['pwrecords']},2)))/({$a['pwrecords']}/200+1500)*130";
	}
	
	/* old version of RF, used to calculate CSR */
	function oldrf($a) {global $cd;return round($a['uplayers']*(($a['records']-$a['pwrecords'])/($a['pwrecords']+2000))*10);}
	
	// Calculates average value of RF.
	function rf_avg(&$a){
		if(!is_array($a)){
			return false;
		}else{
			$avg=0;
			foreach($a as &$s){
				$rf=rf($s);
				$avg+=$rf;
				$s['rf']=$rf;
			}
			return $avg/count($a); 
		}
	}
	
	/* DEPRECATED AS OF REV52 */
	/* SQ Score 
	 * How much players like the server
	 * Based on the average number of hour spent by players 
	 * formula:
	 * SQ = A / (P + 1) * ln(P+1.1) * 10
	 * Just like in RF, let's call it the "Stoned Queen" score.
	*/
	function sq($a) {global $has; return round(($a['records'])/($a['uplayers']+1)*log($a['uplayers']+1.1)*10);}
	
	/* DEPRECATED AS OF REV14 */
	/* CSR Factor
	 * Used to detect servers messing with their players list.
	 * score above 5 might suggest that server is cheating.
	 * formula: (OLD_RF / (P + 1) - 2)^2 - 8
	 * "Cheating Seems Retarded"
	 */
	function csr($a) {return round(pow(oldrf($a)/($a['uplayers']+1)-2,2)-8);}
	//function oldrf($a) {return ($a['a']-$a['pd'])/($a['pd']+500);}
	
	
	if(!function_exists("striposa")){
		function striposa($haystack, &$needles, $offset=0) { //http://www.php.net/manual/en/function.strpos.php#107351
			$chr = array();
			foreach($needles as $needle) {
					$res = stripos($haystack, $needle, $offset);
					
					//echo "CMP: $haystack || $needle :: $res<br>\n";
					if ($res !== false) $chr[$needle] = $res;
			}
			if(empty($chr)) return false;
			return min($chr);
		}
	}
	
	/* generate data url from gd image */
	function image2url($h){
		ob_start();
		imagepng($h);
		$imgd=base64_encode(ob_get_clean ());
		//if(isset($_GET['verbose'])) echo "Rendering DC[$name]:<br>";
		return "data:image/png;base64,$imgd";
	}
	
	/* something to do with graphs drawing */
	function uttimagetxt($im,$size,$rot,$x,$y,$c,$t,$shadow=true,$defaultengine=false){
		global $shadowcolor,$font;
		if($size==1) {
			//$font="PixelEx.ttf";
			$fsize=5;
		}else if($size==2){
			//$font="PixelEx.ttf";
			$fsize=10;
		}else {
			$fsize=$size*5;
		}
		
		if($shadow) imagettftext($im,$fsize,$rot,$x+$size,$y+$size,0x60000000 | $shadowcolor,"$font",$t);
		if($defaultengine) 
			imagettftext($im,$fsize,$rot,$x,$y,$c,"$font",$t);
		else
			imagettftextlcd($im,$fsize,$rot,$x,$y,$c,"$font",$t);
	}

	
	
	function cssColToPHP($col){
		//FirePHP::getInstance(true)->log($col, "cssColToPHP");
		$xv=explode(" ",$col);
		if(count($xv)>1){
			foreach($xv as $x){
				//FirePHP::getInstance(true)->log($x, "foreach");
				if($x[0]=="#") {
					$col=$x;
					break;
				}
				
			}
		}
		$ncol=0;
		if($col[0]!="#") {
			switch(strtolower($col)){
				case "white": 	return 0xFFFFFF;
				case "silver": 	return 0xC0C0C0;
				case "gray": 	return 0x808080;
				case "black": 	return 0x000000;
				case "red": 	return 0xFF0000;
				case "maroon": 	return 0x800000;
				case "yellow": 	return 0xFFFF00;
				case "olive": 	return 0x808000;
				case "lime": 	return 0x00FF80;
				case "green": 	return 0x008000;
				case "aqua": 	return 0x00FFFF;
				case "teal": 	return 0x008080;
				case "blue": 	return 0x0000FF;
				case "navy": 	return 0x000080;
				case "fuchsia":	return 0xFF00FF;
				case "purple": 	return 0x800080;
				default:		return 0;
			}
		}
		if(strlen($col)==7){
			list($ncol)=sscanf($col, "#%6x");
		}else if(strlen($col)==4){
			list($r,$g,$b)=sscanf($col, "#%1x%1x%1x");
			$ncol=hexdec("$r$r$g$g$b$b");
		}
		//FirePHP::getInstance(true)->log($ncol, "COL FROM $col");
		return $ncol;
	}
	
	/* http://www.codingourweb.com/parsing-css-files-and-strings-using-php/ */
	/* Based on a class by Michael Ettl(michael@ettl.com) */
	class CSS {
		protected $css;
		protected $cssprops;
		protected $cssstr;
		protected $firephp;
		/**
		* Constructor function for PHP5
		*
		*/
		public function __construct()  	{
		   $this->css = array();
		   $this->cssprops = array();
		   $this->cssstr = "";
		   //$this->firephp = FirePHP::getInstance(true);
		}

		/**
		* Parses an entire CSS file
		*
		* @param mixed $filename CSS File to parse
		*/
		public function parse_file($file_name)
		{
			//$fh = fopen($file_name, "r") or die("Error opening file $file_name");
			//$css_str = fread($fh, filesize($file_name));
			//fclose($fh);
			$css_str=file_get_contents($file_name);
			
			return($this->parse_css($css_str));
		}

		/**
		* Parses a CSS string
		*
		* @param string $css_str CSS to parse
		*/
		public function parse_css($css_str)
		{
			$this->cssstr = $css_str;
			$this->css = "";
			$this->cssprops = "";
			//$this->firephp->log($css_str,"CSSFILE");
			// Strip all line endings and both single and multiline comments
			$css_str = preg_replace("/\/\*[^\*\/]*\*\//", "", $css_str);

			$css_class = explode("}", $css_str);
			//$this->firephp->log($css_class,"CSS CLASS");
			//$this->firephp->log($css_str,"CSS STR");
			while(list($key, $val) = each($css_class)){
				//$this->firephp->log("K:".$key,"V:".$val);
				$aCSSObj = explode("{", $val);
				$cSel = strtolower(trim($aCSSObj[0]));
				if($cSel){
					$this->cssprops[] = $cSel;
					$a = explode(";", $aCSSObj[1]);
					while(list($key, $val0) = each($a)){
						if(trim($val0)){
							$aCSSSub = explode(":", $val0);
							$cAtt = strtolower(trim($aCSSSub[0]));
							if(isset($aCSSSub[1])){
								$aCSSItem[$cAtt] = trim($aCSSSub[1]);
							}
						}
					}
					if((isset($this->css[$cSel])) && ($this->css[$cSel])){
						$aCSSItem = array_merge($this->css[$cSel], $aCSSItem);
					}
					$this->css[$cSel] = $aCSSItem;
					unset($aCSSItem);
				}
				if(strstr($cSel, ",")){
					$aTags = explode(",", $cSel);
					foreach($aTags as $key0 => $value0){
						$this->css[$value0] = $this->css[$cSel];
					}
					unset($this->css[$cSel]);
				}
			}
			unset($css_str, $css_class, $aCSSSub, $aCSSItem, $aCSSObj);
			return $this->css;
		}

		/**
		* Builds a CSS string out of an existing object
		*
		* @param boolean $sorted Sort the attributes alphabetically
		* @return string Resulting CSS string
		*/
		public function build_css($sorted = false)
		{
			$this->cssstr = "";
			foreach($this->css as $key0 => $value0) {
				$trimmed = trim($key0);
				$this->cssstr .= "$trimmed {n";
				if($sorted) ksort($this->css[$key0], SORT_STRING);
				foreach($this->css[$key0] as $key1 => $value1) {
					$this->cssstr .= "t$key1: $value1;n";
				}
				$this->cssstr .= "}n";
			}
			return ($this->cssstr);
		}

		/**
		* Writes an existing CSS string to file
		*
		* @param string $file_name File to save to
		* @param boolean $sorted Sort the attributes alphabetically
		*/
		public function write_file($file_name, $sorted = false)
		{
			if($this->css == "") die("There is no CSS to write!");
			if($this->cssstr == "") $this->build_css($sorted);
			$fh = fopen($file_name, "w") or die("Error opening file $file_name");
			fwrite($fh, $this->cssstr);
			fclose($fh);
		}

		/**
		* Returns the entire CSS object
		*
		* @return object or false
		*/
		public function get_css()
		{
			if (isset($this->css)) return ($this->css);
			return false;
		}

		/**
		* Returns all CSS properties
		*
		* @return array
		*/
		public function get_properties()
		{
			if (isset($this->cssprops)) return ($this->cssprops);
			return array();
		}

		/**
		* Returns a specified CSS property and all its attributes
		*
		* @param string $property
		* @return array
		*/
		public function get_property($prop)
		{
			if (isset($this->css[$prop])) return ($this->css[$prop]);
			return array();
		}

		/**
		* Gets attribute value of a specified CSS property
		*
		* @param string $prop CSS property
		* @param string $attr CSS attribute
		* @return string
		*/
		public function get_value($prop, $attr)
		{
			if (isset($this->css[$prop][$attr])) return ($this->css[$prop][$attr]);
			return "";
		}

		/**
		* Sets attribute value of a specified CSS property
		*
		* @param string $prop CSS property
		* @param string $attr CSS attribute
		* @param string $value CSS attribute value
		* @return boolean Returns true when succeeded
		*/
		public function set_value($prop, $attr, $value)
		{
			if(empty($prop)||empty($attr)) return false;
			$this->css[$prop][$attr] = $value;
			return true;
		}
	}
	
	/* NOT USED SINCE VERSION 26, BUT I'LL KEEP IT BECAUSE I WASTED SOME TIME WRITING IT */
	/* getClanTag(string)
	 * 
	 * Returns: array(
	 *    "tag" : full clan tag with opening and closing chars
	 *    "clan" : clan name only
	 *    "player" : player's name without clan tag
	 *    "format" : specifies if the clan tag is at the beginning of the name (TN), ending (NT) or is not present at all (N)
	 * )
	 */
	function getClanTag($name){
		// CASE 1: 1-3 letters surrounded by non-letters in the beginning of the name
		// random tags for testing: -=)RyF(=-   -=[*DDD*]=-   [EEF]   .:|[*SLD*]|:.   *Bs*   -=|DZ|=-  	=(V)=   -=]UM[=-   -=CoN=-
		if(preg_match("/^([^A-Za-z0-9]{1,4})([A-Za-z0-9]{1,3})[^A-Za-z0-9]/",$name,$matches)){
			// usually, the opening and closing of tags are symmetrical
			$tagOpening=$matches[1];
			$clanTag=$matches[2];
			$tagClosing=substr($name,strlen($tagOpening.$clanTag),strlen($tagOpening));
			$isProperlyClosed=preg_match("/^([^A-Za-z0-9]{".strlen($tagOpening)."})/",$tagClosing);
			if($isProperlyClosed && getSpecialCharsWeights($tagOpening)==getSpecialCharsWeights($tagClosing)) {
				
				$playerName=substr($name,strlen($tagOpening.$clanTag.$tagOpening));
				if(strlen($playerName)>=strlen($clanTag)){
					return array("tag"=>$tagOpening.$clanTag.$tagClosing,"clan"=>$clanTag,"player"=>$playerName,"format"=>"TN");
				}
			}
		}
		
		// CASE 2: 4-6 letters (+dots and others) surrounded by non-letters in the beginning of the name
		// random tags for testing: [R.I.P.]   ][c~air][   {S.o.W}   {D.M.P.}
		if(preg_match("/^([^A-Za-z0-9]{1,3})([A-Za-z0-9\.~]{4,6})[^A-Za-z0-9]/",$name,$matches)){
			$tagOpening=$matches[1];
			$clanTag=$matches[2];
			$tagClosing=substr($name,strlen($tagOpening.$clanTag),strlen($tagOpening));
			$isProperlyClosed=preg_match("/^([^A-Za-z0-9]{".strlen($tagOpening)."})/",$tagClosing);
			if($isProperlyClosed && getSpecialCharsWeights($tagOpening)==getSpecialCharsWeights($tagClosing)) {
				$playerName=substr($name,strlen($tagOpening.$clanTag.$tagOpening));
				if(strlen($playerName)>=strlen($clanTag)){
					return array("tag"=>$tagOpening.$clanTag.$tagClosing,"clan"=>$clanTag,"player"=>$playerName,"format"=>"TN");
				}
			}
		}
		// CASE 2A: Alien
		// random tags for testing: (_@_)
		if(preg_match("/^([^A-Za-z0-9]{1,3})([A-Za-z0-9@])[^A-Za-z0-9]/",$name,$matches)){
			$tagOpening=$matches[1];
			$clanTag=$matches[2];
			$tagClosing=substr($name,strlen($tagOpening.$clanTag),strlen($tagOpening));
			$isProperlyClosed=preg_match("/^([^A-Za-z0-9]{".strlen($tagOpening)."})/",$tagClosing);
			if($isProperlyClosed && getSpecialCharsWeights($tagOpening)==getSpecialCharsWeights($tagClosing)) {
				$playerName=substr($name,strlen($tagOpening.$clanTag.$tagOpening));
				return array("tag"=>$tagOpening.$clanTag.$tagClosing,"clan"=>$clanTag,"player"=>$playerName,"format"=>"TN");
			}
		}
		
		// CASE 3: 3 letters surrounded by brackets in the end of the name
		// random tags for testing: [xtc]   (ChS)   {HoF}
		
		if(preg_match("/(_?)([\[\(\{]{1,2})([A-Za-z0-9]{3})[\]\)\}]{1,2}$/",$name,$matches,PREG_OFFSET_CAPTURE)){
			$underscore=$matches[1][0]; // optional
			$tagOpening=$matches[2][0];
			$clanTag=$matches[3][0];
			$tagClosing=substr($name,strlen($underscore.$tagOpening.$clanTag)+$matches[1][1],strlen($tagOpening));
			$isProperlyClosed=preg_match("/^([\]\)\}]{1,2})/",$tagClosing);
			if($isProperlyClosed) {
				$playerName=substr($name,0,-strlen($tagOpening.$clanTag.$tagOpening)-strlen($underscore));
				if(strlen($playerName)>=strlen($clanTag)){
					return array("tag"=>$tagOpening.$clanTag.$tagClosing,"clan"=>$clanTag,"player"=>$playerName,"format"=>"NT");
				}
			}
		}
		// CASE 4: 2-3 letters on the very beginning, followed by non-letter (. - or |)
		// random tags for testing: tnt.   dU.   nM-
		
		if(preg_match("/^([A-Za-z0-9]{2,3})[\|\.\-]/",$name,$matches)){
			$tagOpening="";
			$clanTag=$matches[1];
			$tagClosing=substr($name,strlen($clanTag),1);
			$isProperlyClosed=preg_match("/^[\|\.\-]/",$tagClosing);
			if($isProperlyClosed) {
				$playerName=substr($name,1+strlen($clanTag));
				if(strlen($playerName)>=strlen($clanTag)){
					return array("tag"=>$clanTag.$tagClosing,"clan"=>$clanTag,"player"=>$playerName,"format"=>"TN");
				}
			}
		}
		// CASE 5: ()mG - i have no idea how to detect it properly, so here's the lazy way:
	
		if(preg_match("/^(\(\)mG)[\._]/",$name,$matches)){
			$tagOpening="";
			$clanTag=$matches[1];
			$tagClosing=substr($name,strlen($clanTag),1);
			$isProperlyClosed=preg_match("/^[\._]/",$tagClosing);
			if($isProperlyClosed) {
				$playerName=substr($name,1+strlen($clanTag));
				return array("tag"=>$clanTag.$tagClosing,"clan"=>$clanTag,"player"=>$playerName,"format"=>"TN");
			}
		}
		
		// CASE 6: no clan tag
		return array("tag"=>"","clan"=>"","player"=>$name,"format"=>"N");
	}
	
	/* this function is used for checking if the opening and closing sequences of clan tags
	 * are containing the same characters, or their equivalents
	 * it's ugly but kinda working solution.
	 */
	function getSpecialCharsWeights($str){
		static $charWeights=array(
			"[" => 1,"]" => 1,
			"(" => 5,")" => 5,
			"*" => 20,
			"|" => 100,
			"/" => 500,
			"{" => 2000,"}" => 2000,
			"-" => 8000,
			"=" => 20000,
			"." => 80000
		);
		$weight=0;
		for($i=0, $len=strlen($str); $i < $len; $i++){
			$char = $str[$i];
			if(isset($charWeights[$char])) 
				$weight+=$charWeights[$char];
		}
		return $weight;
	}
	
	function utt_trigger_error($msg,$type,$btrace){
		$caller = next($btrace);
		if(function_exists("handleerr")){
			handleerr($type,$msg,$caller['file'],$caller['line']);
		}else{
			trigger_error("$msg in {$caller['file']} on line {$caller['line']}",$type);
		}
	}
	
	
	function wovsx($s){
		if(($slen=strlen($s))<3) return strtolower($s);
		return $s[0].preg_replace("/[^aeiouy]/","",substr(strtolower($s),1,-1)).$s[$slen-1];
	}
	/* VERY BAD!! */
	function likeator($s){
		$s=str_replace("%","",$s);
		$s=preg_replace("/(.)/","%$1",$s);
		return $s."%";
	}
	/* creates new array with keys corresponding to values of given column */
	function indexaskey($arr,$keyname){
		if(!is_array($arr)) return $arr;
				
		$newarr=array();
		foreach($arr as $xd){
			if(!isset($newarr[$xd[$keyname]])){
				$newarr[$xd[$keyname]]=$xd;
				$newarr[$xd[$keyname]]['__iak_conflicts']=0;
			}else{
				$newarr[$xd[$keyname]]['__iak_conflicts']++;
			}
		}
		return $newarr;
	}
	
	//borrowed from ../randomquotes
	function utt_benchmark_start(){
		global $bench_start;
		$bench_start=microtime(true);
	}
	function utt_benchmark_end(){
		global $bench_start;
		$br=microtime(true);
		$br-=$bench_start;
		echo "BENCH RESULT: ".round($br*1000,2)." ms\r\n";
	}
	
	function utt_checkpoint($name=""){
		static $cp=0,$cpstart=0;
		if(!$GLOBALS['debug_checkpoint']) return;
		$timeStop=microtime(true);
		if($cp!=0){
			echo "<span class='debugcp'>Checkpoint $name: ".round(($timeStop-$cpstart)*1000)."ms (+".round(($timeStop-$cp)*1000)."ms)</span><br>\n";
		}else{
			$cpstart=microtime(true);
		}
		$cp=microtime(true);
	}
	

	// WTF ME!! just keeping it if something might want to use it
	function sqltodoexec($query){
		//file_put_contents("sqltodo.txt","$query;\r\n",FILE_APPEND);
	}
	
	
	// this function is *KINDA VERY* slow, don't use it too often.
	function updateRfForServer($serverid,$zero=false){
		//echo "URFS: $serverid:";
		if($zero){
			sqlexec("UPDATE serverinfo SET `rfscore`=0 WHERE serverid=$serverid");
		}else{
			//
			echo "	QUERY: ";
			//utt_benchmark_start();
			$uplayers=sqlquery("SELECT count(*) AS `c` FROM playerstats WHERE serverid=$serverid",1)['c'];
			
			echo $uplayers;
			//$pstat=sqlquerytraversable("SELECT id,numupdates,lastupdate FROM playerhistory FORCE INDEX (ph_sid_idx) WHERE serverid=$serverid AND lastupdate > ".(time()-86400*14));
			$pstat=sqlquerytraversable("SELECT id,sum(numupdates) as numupdates FROM playerhistory WHERE serverid=$serverid AND lastupdate > ".(time()-86400*14)." GROUP BY id");
			//utt_benchmark_end();
			$servstat=array('players'=>array(),'playerslw'=>array(),'records'=>0,'pwrecords'=>0);
			//exit;
			//for($i=0; $i<count($pstat); $px=$pstat[$i++]){
			$i=0;
			echo "	LOOP: ";
			//utt_benchmark_start();
			
			while(($px=sqlfetch($pstat))!==false){
				//if(!isset($px) || !isset($px['id'])) continue;
				
				$servstat['players'] [$px['id']]=true;
				
				$servstat['records']+=$px['numupdates'];
				/*if($px['lastupdate'] < time()-86400*14) $servstat['pwrecords']+=$px['numupdates']; 
				else $servstat['playerslw'] [$px['id']]=true; */
				//$servstat['pwrecords']+=$px['numupdates']; 
				$servstat['playerslw'][$px['id']]=true; 
				
				$i++;
				
			}
			//utt_benchmark_end();
			
			echo "	RF: ";
			//utt_benchmark_start();
			$servstat['uplayers']=$uplayers;//count($servstat['players']);
			$servstat['pwuplayers']=count($servstat['playerslw']);
			$rf=round(rfVersionSome($servstat)*500000);
			//utt_benchmark_end();
			
			echo "	UPDATE: ";
			//utt_benchmark_start();
			sqlexec("UPDATE serverinfo SET `uplayers`={$servstat['uplayers']}, `rfscore`=$rf, `lastrfupdate`=".time()." WHERE serverid=$serverid");
			//utt_benchmark_end();
			echo "	RESO: $i,$rf<br>\n";
		}
	}
	
	function pretty_file_size($fsi) { // stolen from my old cms
		if($fsi < 900) $reti="$fsi bytes";
		else if ($fsi < 800 * 1024) $reti=round($fsi/1024)." KB";
		else if ($fsi < 10 * 1024 * 1024) $reti=round($fsi/1024/1024,2)." MB";
		else if ($fsi < 800 * 1024 * 1024) $reti=round($fsi/1024/1024)." MB";
		else $reti=round($fsi/1024/1024/1024,2)." GB";
		
		return $reti;
	}
	
	/* GetDirectorySize
	 * by Janith Chinthana, edit: Alph.Dev
	 * http://stackoverflow.com/a/21409562
	 */
	function GetDirectorySize($path){
		$bytestotal = 0;
		$path = realpath($path);
		if($path!==false){
			foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS)) as $object){
				$bytestotal += $object->getSize();
			}
		}
		return $bytestotal;
	}
	
	
	/* LOCALIZED COUNTRY NAMES */
	function countryName($code){
		static $countries=null;
		if($countries===null) $countries=loadCountryNames();
		$code=strtoupper($code);
		if(!isset($countries[$code])) return "";
		$name = $countries[$code];
		
		if(strpos($name,", ")!==false){
			$chunks =  explode(", ",$name,2);
			//return $chunks[1] . " " . $chunks[0];
			return $chunks[0];
		}
		return $name;
	}
	
	function loadCountryNames(){
		$isoFile = file_exists("locale/iso-3166.".GetText\getlocale().".json") ? "locale/iso-3166.".GetText\getlocale().".json" : "iso-3166.default.json";
		$cn = json_decode(file_get_contents($isoFile),true);
		$result=array();
		foreach($cn as $c){
			$result[$c['Code']]=$c['Name'];
		}
		return $result;
	}
	
	// adds <wbr> tags ForLongCharacterSequences which End<wbr>Up<wbr>Like<wbr>This
	// couldn't come up with another name
	function wwwordOBreaker3000($str){
		$lowChars=0;
		$result="";
		for($c=0, $sLen=strlen($str); $c<$sLen; $c++){
			$char=$str[$c];
			$charCode=ord($char);
			if($charCode>=0x61 && $charCode<=0x7A){
				$result.=$char;
				$lowChars++;
			}else if($lowChars>1){
				if($char!==")" && $char!=="]" && $char!=="}")
					$result.="<wbr>".$char;
				else
					$result.= $char."<wbr>";
					
				$lowChars=0;
			}else{
				if($char==="(" || $char==="[" || $char==="{")
					$result.="<wbr>".$char;
				else if($char===")" || $char===")" || $char===")")
					$result.= $char."<wbr>";
				else
					$result.= $char;
				$lowChars=0;
			}
		}
		return $result;
	}
	
	
	// get the position of first character of map name without leading (WTF) [XY] (_69_) tags
	// for mapinfo page
	function getFirstLetterOfMapName($mapname){
		static $tagPairs = array("[]","()","{}");
		$offsetFromMapname = 0;
		if(stripos($mapname,"CTF-BT")===0){
			$mapname=substr($mapname,4);
			$offsetFromMapname += 4;
		}
		if(($dashPos=strpos($mapname,"-"))!==false && $dashPos < 4){ // with gamemode prefix (UT)
			$offsetFromMapname += strpos($mapname,"-")+1;
			$mapnameWithoutGametype = substr($mapname,$offsetFromMapname);
			
		}else{ // without prefix (Unreal)
			$mapnameWithoutGametype = $mapname;
		}
		
		// we use stack to be able to detect more complex tags like: {((BESTMAPPER][EVER))}
		$specialCharsStack=array();
		
		for($c=0,$s=strlen($mapnameWithoutGametype); $c < $s; $c++){
			$char = $mapnameWithoutGametype[$c];
			for($p=0; $p < count($tagPairs); $p++){
				if($char === $tagPairs[$p][0]){
					$pairForCurrentChar = $tagPairs[$p][1];
				}else if($char === $tagPairs[$p][1]){
					$pairForCurrentChar = $tagPairs[$p][0];
				}else{
					$pairForCurrentChar = null;
				}
				
				if($pairForCurrentChar !== null ){
					if(count($specialCharsStack) > 0 && end($specialCharsStack) === $char){ // closing tag
						array_pop($specialCharsStack);
					}else{
						array_push($specialCharsStack,$pairForCurrentChar);
					}
				}else{
					if(count($specialCharsStack)===0){
						if(strspn($char,"ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789")){
							$realMapName = substr($mapnameWithoutGametype,$c);
							if(strspn($realMapName,"ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789")>2){ // avoid short tags like: MH-TB-AlitaBattleAngelNormalGuns
								//return $realMapName[0];
								return $offsetFromMapname + $c;
							}
						}
					}
				}
			}
		}
		//return $mapnameWithoutGametype[0];
		return $offsetFromMapname;
	}
	
	
	// translates shitty server names into something better for eyes
	function unRetardizeServerName($name){
		if(strlen($name) < 30) return $name;
		$nameFixed = " " . $name . " ";
		//$nameFixedTemp=preg_replace("#^ [\w]{1,2}[^\w\s]#i","  ",$nameFixed);
		$nameFixedTemp=preg_replace("#^ (zp\||nn\|)?#i","  ",$nameFixed);
		
		if(strlen($nameFixedTemp) > 0.5 * strlen($nameFixed)){
			$nameFixed=$nameFixedTemp;
		}
				
		
		
		$serverStringPos = stripos($nameFixed,"server");
		if($serverStringPos!==false){
			$serverStringEndingPos = strpos($nameFixed," ",$serverStringPos);
			if($serverStringEndingPos!==false){
				$nameFixed = substr($nameFixed, 0, $serverStringEndingPos);
			}
		}
		
		
		//$usedTokens = array();
		
		$cb = function($m) /*use(&$usedTokens)*/{
			if(/*!isset($usedTokens[$m[1]]) && */isAlphabetic($m[1]) && strpos($m[1],"www.")===false){
				
				//if(strlen($m[1]) > 2) $usedTokens[$m[1]]=true;

				return $m[0];
			}else{
				return "";
			}
		};
		
		$nameFixed = preg_replace_callback("#\(\W*?(.*?)\W*?\)#s",$cb,$nameFixed);
		$nameFixed = preg_replace_callback("#\[\W*?(.*?)\W*?\]#s",$cb,$nameFixed);
		
		$nameFixed = preg_replace("#(\s)([^\w\s])[^\w]+(\s)#","\$1\$2\$3",$nameFixed);
		
		if(strlen($nameFixed) < 30 && strlen($nameFixed) < strlen($name) * 0.4) return $name;
		
		return $nameFixed;
	}
	
	
	// ??? NUT SURE: returns true if at least 60% of characters are alphabetic ???
	function isAlphabetic($string){
		return preg_match_all("#[a-z]#i",$string) > 0.6 * strlen($string);
	}
	
	if($phpMajor >= 7){
		// new spaceship operator
		eval('	
			function cmp($a, $b){
				return $a <=> $b;
			}
		');
	}else{
		function cmp($a, $b){
			return ($a>$b?1:($a<$b?-1:0));
		}

	}
	
	// median
	function medy($a){
		if(!is_array($a)){
			return false;
		}else{
			rsort($a);
			$asize = count($a);
			if($asize==0) return 0;
			else if($asize==1) return $a[0];
			$c=floor($asize/2);
			if($asize%2===1){
				$res=$a[$c];
			}else{
				$res=($a[$c-1]+$a[$c])/2;
			}
			return $res; 
		}
	}
	
	function cp437toentity($string){
		static $mapping = array(0x80=>0x00C7,0x81=>0x00FC,0x82=>0x00E9,0x83=>0x00E2,0x84=>0x00E4,0x85=>0x00E0,0x86=>0x00E5,0x87=>0x00E7,0x88=>0x00EA,0x89=>0x00EB,0x8A=>0x00E8,0x8B=>0x00EF,0x8C=>0x00EE,0x8D=>0x00EC,0x8E=>0x00C4,0x8F=>0x00C5,0x90=>0x00C9,0x91=>0x00E6,0x92=>0x00C6,0x93=>0x00F4,0x94=>0x00F6,0x95=>0x00F2,0x96=>0x00FB,0x97=>0x00F9,0x98=>0x00FF,0x99=>0x00D6,0x9A=>0x00DC,0x9B=>0x00A2,0x9C=>0x00A3,0x9D=>0x00A5,0x9E=>0x20A7,0x9F=>0x0192,0xA0=>0x00E1,0xA1=>0x00ED,0xA2=>0x00F3,0xA3=>0x00FA,0xA4=>0x00F1,0xA5=>0x00D1,0xA6=>0x00AA,0xA7=>0x00BA,0xA8=>0x00BF,0xA9=>0x2310,0xAA=>0x00AC,0xAB=>0x00BD,0xAC=>0x00BC,0xAD=>0x00A1,0xAE=>0x00AB,0xAF=>0x00BB,0xB0=>0x2591,0xB1=>0x2592,0xB2=>0x2593,0xB3=>0x2502,0xB4=>0x2524,0xB5=>0x2561,0xB6=>0x2562,0xB7=>0x2556,0xB8=>0x2555,0xB9=>0x2563,0xBA=>0x2551,0xBB=>0x2557,0xBC=>0x255D,0xBD=>0x255C,0xBE=>0x255B,0xBF=>0x2510,0xC0=>0x2514,0xC1=>0x2534,0xC2=>0x252C,0xC3=>0x251C,0xC4=>0x2500,0xC5=>0x253C,0xC6=>0x255E,0xC7=>0x255F,0xC8=>0x255A,0xC9=>0x2554,0xCA=>0x2569,0xCB=>0x2566,0xCC=>0x2560,0xCD=>0x2550,0xCE=>0x256C,0xCF=>0x2567,0xD0=>0x2568,0xD1=>0x2564,0xD2=>0x2565,0xD3=>0x2559,0xD4=>0x2558,0xD5=>0x2552,0xD6=>0x2553,0xD7=>0x256B,0xD8=>0x256A,0xD9=>0x2518,0xDA=>0x250C,0xDB=>0x2588,0xDC=>0x2584,0xDD=>0x258C,0xDE=>0x2590,0xDF=>0x2580,0xE0=>0x03B1,0xE1=>0x00DF,0xE2=>0x0393,0xE3=>0x03C0,0xE4=>0x03A3,0xE5=>0x03C3,0xE6=>0x00B5,0xE7=>0x03C4,0xE8=>0x03A6,0xE9=>0x0398,0xEA=>0x03A9,0xEB=>0x03B4,0xEC=>0x221E,0xED=>0x03C6,0xEE=>0x03B5,0xEF=>0x2229,0xF0=>0x2261,0xF1=>0x00B1,0xF2=>0x2265,0xF3=>0x2264,0xF4=>0x2320,0xF5=>0x2321,0xF6=>0x00F7,0xF7=>0x2248,0xF8=>0x00B0,0xF9=>0x2219,0xFA=>0x00B7,0xFB=>0x221A,0xFC=>0x207F,0xFD=>0x00B2,0xFE=>0x25A0,0xFF=>0x00A0);
		$result = "";
		for($i=0; $i<strlen($string); $i++){
			$charCode = ord($string[$i]);
			/*if($charCode >= 0x80) // uncomment to show box-drawing characters (like dos: ╒═╬╗)
				$result .= "&#".$mapping[$charCode].";";
			else */
			if($charCode < 0x20) // ASCII control chars!! choose one of the styles that suits you:
				//$result .= "&#".$charCode.";"; // wrong!! doesn't work
				//$result .= "&#xFFFD;"; // diamond with question mark
				//$result .= "&#x" . dechex(0x2400 + $charCode) . ";"; // diagonal descriptions of control chars
				$result .= "&#x25FB;"; // square, as shown in UT
			else 
				$result .= chr($charCode);
		}
		return $result;
	}

	/*
	[timeLimit] => server time limit (seconds)
	[gameStart] => beginning of the match (unixts)
	[gameTime]  => time since match beginning (seconds)
	[gameEnd]   => predicted match ending (unixts)
	[remaining] => match remaining time (seconds)
	[state]     => unknown / waiting (game hasn't started) / game (in progress) / ended / overtime
	*/
	
	function getMatchTimesFromRules($serverRules){
		$timeInfo = array();
		$timeInfo['state'] = "unknown";
		//if(isset($serverRules['xserverquery'])){
		if(isset($serverRules['remainingtime']) && $serverRules['remainingtime']!="") {
			$timeLimit = isset($serverRules['timelimit']) ? $serverRules['timelimit']*60 : 0;
			$gameEnd = $serverRules['__uttlastupdate'] + $serverRules['remainingtime'];
			//$gameStart = $gameEnd - $timeLimit;
			$gameStart = $serverRules['__uttlastupdate'] - $serverRules['elapsedtime'];
			$gameTime = time() - $gameStart;
			$remaining = $gameEnd - time();
			
			$timeInfo['timeLimit'] = $timeLimit;
			if($timeInfo['timeLimit']>0){
				$timeInfo['gameEnd'] = $gameEnd;
				$timeInfo['remaining'] = $remaining;
			}
			$timeInfo['gameStart'] = $gameStart;
			$timeInfo['gameTime'] = $gameTime;
			
			
			if($serverRules['numplayers']==0 && $serverRules['remainingtime']==0) $timeInfo['state']="waiting";
							
			if(isset($serverRules['bgameended'])) {
				if($serverRules['bgameended']=="True") $timeInfo['state']="ended";
				else if($serverRules['bovertime']=="True") $timeInfo['state']="overtime";
				else if($timeLimit > 0 && $serverRules['remainingtime']==$timeInfo['timeLimit'] || ($remaining < 0 && $serverRules['numplayers']==0)) $timeInfo['state']="waiting";
				else $timeInfo['state']="game";
			}
			if($remaining<0) $timeInfo['remaining']="0";
			
			
			//}
		}else{
			/*if(isset($serverRules['mutators']) && strpos($serverRules['mutators'],"Publish Score in Server Title")!==false){
			
			}else*/
			$timeInfo['state'] = "unknown";
			if(isset($serverRules['gametime'])){
			
				$timeInfo['timeLimit'] = $serverRules['timelimit'] * 60;
				$timeInfo['gameEnd'] = $serverRules['__uttlastupdate']+$serverRules['gametime'];
				$timeInfo['gameStart'] = $timeInfo['gameEnd']-$timeInfo['timeLimit'];
				$timeInfo['gameTime'] = time() - $timeInfo['gameStart'];
				$timeInfo['remaining'] = $timeInfo['gameEnd'] - time();
			}else if(isset($serverRules['__uttgamestart']) && isset($serverRules['__utttimetestpassed']) && $serverRules['__utttimetestpassed']==true){
				$timeInfo['timeLimit'] = $serverRules['timelimit'] * 60;
				$timeInfo['gameStart'] = $serverRules['__uttgamestart'];
				$timeInfo['gameEnd'] = $serverRules['__uttgamestart']+$timeInfo['timeLimit'];
				$timeInfo['gameTime'] = time() - $timeInfo['gameStart'];
				$timeInfo['remaining'] = $timeInfo['gameEnd'] - time();
			}
		}
		return $timeInfo;
	}
	
	function arrLast($array){
		return end($array);
	}
	
	/* LOOSE CODE BELOW */
	$user_dnt=0;
	if(isset($_SERVER['REQUEST_URI'])){
		$rqv=strtok($_SERVER['REQUEST_URI'],"?");
		/*if(function_exists("getallheaders")){
			$hdx=getallheaders();
			
			if(isset($hdx['DNT'])){
				$user_dnt=(int)$hdx['DNT'];
			}
			
		}*/
		$user_dnt = isset($_SERVER['HTTP_DNT']) ? (int)$_SERVER['HTTP_DNT'] : 0;
	}else{
		$rqv="";
	}
	
	if(isset($_GET['triggerError'])){
		handleerr(E_ERROR,"User-triggered error",__FILE__,__LINE__);
	}
	
	if(!isset($naked) && isset($_SERVER['SERVER_NAME']) && stripos($site_url,$_SERVER['SERVER_NAME'])===false){
		header("HTTP/1.1 301 Moved permanently");
		header("Location: ".$site_url.$_SERVER['REQUEST_URI']);
		exit;
	}
	
	const LAYOUTGEN_JOB_DOWNLOAD=1;
	const LAYOUTGEN_JOB_REDNERLAYOUT=2;
	const LAYOUTGEN_JOB_GENREPORT=4;
	define('LAYOUTGEN_JOB_ALL',LAYOUTGEN_JOB_DOWNLOAD|LAYOUTGEN_JOB_REDNERLAYOUT|LAYOUTGEN_JOB_GENREPORT);
	
	$allowXHR=stripos("http://{$_SERVER['HTTP_HOST']}{$_SERVER['REQUEST_URI']}",$sub_site_url)===0 ? "true" : "false";
	
	$reqSprintfSafe=str_replace("%","%%","//{$site_host}{$rqv}");
	$rqvSprintfSafe=str_replace("%","%%",$rqv);
	

	// page header - VERY UGLY WAY!!
	$headerf="<!DOCTYPE HTML>
<html lang='".GetText\getlocale()."'>
<head>
<meta charset='utf-8'/>
<meta name='description' content=\"%3\$s\" />
<link rel=\"shortcut icon\" type='image/png' href='$assetsPath/uttfavRemixed.png' />
<link rel='stylesheet' href='$assetsGenericPath/css/crap.css'/>
".(!isset($noUTTCSS)?"<link rel='stylesheet' href='$assetsPath/css/utt.css'/>":"")."
<title>%1\$sUT99 Stats Tracker</title>
<script type='text/javascript' src='$assetsPath/js/$jsScriptVersion.js'></script>
<script type='text/javascript' src='$assetsPath/js/CrapFramework130907.js'></script>
<script type='text/javascript' src='$assetsPath/js/_banner_ad.js?action=get_reklama'></script>
<script type='text/javascript'>
	TableThing.allowXHR=$allowXHR; // we don't need no CORS
	var adDetectaz0rd = true;
</script>
<!--<link rel=\"alternate\" hreflang=\"en\" href=\"$reqSprintfSafe?lang=en\" />
<link rel=\"alternate\" hreflang=\"pl\" href=\"$reqSprintfSafe?lang=pl\" />-->
</head>
<body class=\"$bodyclass %2\$s\" id='uttrk'>
	
	<div id='logo_cont'>
	<h1 class='logo'><a href='$abspath'>Unreal Tournament 99 Stats Tracker $branchname</a></h1>
	</div>
	
<div id='body_cont'>
<header class='notsofreakingbig'>
".str_replace("%","%%",file_get_contents(__DIR__."/news.html"))."
</header>
<hr/>";




if (realpath($_SERVER["SCRIPT_FILENAME"])===realpath(__FILE__)){
	header("HTTP/1.1 403 Forbidden");
	echo "Woof woof.";
	exit;
}

	
