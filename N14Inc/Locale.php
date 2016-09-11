<?php
/*
 * NamoGetText
 * Yet another crappy component of mine
**/
	namespace N14\GetText{
		$n14gt_default_locale="en";
		$n14gt_locale=$n14gt_default_locale;
		$n14gt_strings=array();
		$n14gt_strings_attributes=array();
		$n14gt_refs = array();
		$n14gt_refsLoc = "Locale/refs.txt";
		
		const GENERATE_REFS = true;
		const ADD_REFS_TO_LOCALE = false; // Annoying
			
		function setlocale($locale){
			global $n14gt_strings,$n14gt_strings_attributes;
			$locFile = get_locale_file();
			if(!locale_exists($locale)) {
				touch($locFile);
			}
			$GLOBALS['n14gt_locale']=$locale;
			$strf=file(__DIR__ . "/../Locale/$locale.txt",FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES);
			if(!count($strf)) return;
			foreach($strf as $line){
				
				$mx=preg_match("/^\s*\"(.*)\"\s*=\s*\"(.*)\"\s*(!?)\s*$/",$line,$matc);
				if(!$mx) {
					$n14gt_strings['^^emptyline'.count($n14gt_strings)]=$line;
				}else{
					$orig_str=$matc[1];
					$trans_str=$matc[2];
					$n14gt_strings[$orig_str]=$trans_str;
					$n14gt_strings_attributes[$orig_str]['markedAsTranslated'] = ($matc[3]=="!");
				}
			}
			
			if(!isset($n14gt_strings['^^localename'])){
				$n14gt_strings = array_merge(array('^^localename'=>$locale), $n14gt_strings);
				update_locale();
			}
			loadRefs();
		}
		
		function loadRefs(){
			global $n14gt_refsLoc,$n14gt_refs;
			if(!file_exists($n14gt_refsLoc)) {
				touch($n14gt_refsLoc);
			}
			$strf=file($n14gt_refsLoc,FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES);
			foreach($strf as $line){
				$match = preg_match("#^([0-9a-fA-F]*)=([^>]*)>([0-9]*);([^@]*)@(.*)$#",$line,$mat);
				$md5 = $mat[1];
				$file = $mat[2];
				$line = $mat[3];
				$exampleUrl = $mat[4];
				$code = $mat[5];
				/*$md5 = strtok($line,"=");
				$file = strtok(">");
				$line = strtok(";");
				$exampleUrl = strtok("@");
				$code = strtok("\r");*/
				$n14gt_refs[$md5]=array('file'=>$file,'line'=>$line,'exampleUrl'=>$exampleUrl,'code'=>$code);
			}	
		}
		
		function getCurrentUrl(){
			$proto=(isset($_SERVER['HTTP_X_FORWARDED_PROTO']) 
				   ? $_SERVER['HTTP_X_FORWARDED_PROTO'] 
				   : (isset($_SERVER['HTTPS']) || $_SERVER['SERVER_PORT'] == 443 ? "https" : "http")
				   );
			$host = $_SERVER['HTTP_HOST'];
			$port = $_SERVER['SERVER_PORT'];
			$portSuffix = ($port==80 || $port==443 ? "" : ":$port");
			$req = $_SERVER['REQUEST_URI'];
			return "$proto://$host$portSuffix$req";
		}
		
		function createRef($string,$trace){
			global $n14gt_refs;
			if(!GENERATE_REFS) return;
			$md5=md5($string);
			$url = getCurrentUrl();
			if(isset($n14gt_refs[$md5])) return;
			$max = count($trace)-1;
			$i=$max;
			for(; $i >= 0; $i--){
				if(isset($trace[$i]['file']) && $trace[$i]['file']!=__FILE__) {
					
					$file=$trace[$i]['file'];
					$line=$trace[$i]['line'];
					if($file=="") continue;
					$file=realpath($file);
					//if(!isset($n14gt_refs[$md5]) /*|| $n14gt_refs[$md5]['file']!=$file || $n14gt_refs[$md5]['line']!=$line*/){
					$code = file($file,FILE_IGNORE_NEW_LINES)[$line-1];
					if(strpos($code,"__")==false) continue;
					
					$n14gt_refs[$md5]=array('file'=>$file,'line'=>$line,'exampleUrl'=>$url,'code'=>$code);
					//}
					break;
				}
			}
			$n14gt_refs[$md5]['exampleUrl']=$url;
			updateRefs();
		}
		function updateRefs(){
			global $n14gt_refs,$n14gt_refsLoc;
			if(!GENERATE_REFS) return;
			$textBuf="";
			foreach($n14gt_refs as $md5=>$ref){
				$textBuf.="$md5={$ref['file']}>{$ref['line']};{$ref['exampleUrl']}@{$ref['code']}\r\n";
			}	
			file_put_contents($n14gt_refsLoc,$textBuf);
		}
		
		function update_locale(){
			global $n14gt_locale,$n14gt_strings,$n14gt_strings_attributes;
			$resultfile="";
			foreach($n14gt_strings as $orig=>$trans){
				if(strpos($orig,"^^emptyline")===0){
					$resultfile.=$trans."\r\n";
				}else{
					$addi="";
					if(isset($n14gt_strings_attributes[$orig]) && $n14gt_strings_attributes[$orig]['markedAsTranslated']) $addi.="!";
					$resultfile.="\"$orig\" = \"$trans\"$addi\r\n";
				}
			}
			file_put_contents(get_locale_file(),$resultfile);
		}
		
		/*function __($string){
			return gettext($string);
		}*/
		
		function gettext($string){
			global $n14gt_strings,$n14gt_refs;
			if(isset($n14gt_strings[$string])){
				$newString = $n14gt_strings[$string];
				//return "n14gt";
			}else{
				if(GENERATE_REFS){
					$bt=debug_backtrace();
					createRef($string,$bt);
					//$n14gt_refs[md5($string)]=array("file"=>basename($bt[1]['file']),"line"=>$bt[1]['line']);
					if(ADD_REFS_TO_LOCALE){
						$n14gt_strings['emptyline'.count($n14gt_strings)]="# ".basename($bt[1]['file']).":".$bt[1]['line'];
					}
				}
				$n14gt_strings[$string]=$string;
				update_locale();
				$newString = $string;
				//return "n14gt";
			}
			if(isset($_GET['n14gtHighlight']) && md5($string)==$_GET['n14gtHighlight']){
				$newString = "<span style=\"background: #0FF !important; color: #000 !important;\">$newString</span>";
			}
			return $newString;
		}
		
		function stats(){
			$stats = array();
			$stats['locale']=$GLOBALS['n14gt_locale'];
			$stats['localeName']=$stats['locale'];
			$isDefaultLocale = $stats['locale']==$GLOBALS['n14gt_default_locale'];
			$strings = $GLOBALS['n14gt_strings'];
			$stats['linesTotal']=0;
			$stats['linesTranslated']=0;
			$stats['linesEmpty']=0;
			foreach($strings as $strOrig=>$str){
				$attributes = $GLOBALS['n14gt_strings_attributes'][$strOrig];
				if($strOrig=="^^localename"){
					$stats['localeName']=$str;
				}else if(strpos($strOrig,"^^emptyline")===0){
					$stats['linesEmpty']++;
				}else{
					if($attributes['markedAsTranslated'] || $strOrig != $str || $isDefaultLocale){
						$stats['linesTranslated']++;
					}
					$stats['linesTotal']++;
				}
				
			}
			return $stats;
		}
		
		function locale_exists($locale){
			if(!preg_match("/[a-zA-Z]{2,3}/",$locale)) return false;
			return file_exists("Locale/$locale.txt");
		}
		
		function get_locale_file($lang=null){
			if($lang==null) $lang=getlocale();
			$locFile = "Locale/$lang.txt";
			if(file_exists($locFile))
				return realpath($locFile);
			else
				return $loc;
		}

		function getlocale(){
			return $GLOBALS['n14gt_locale'];
		}
		
		function find_locale_by_httprequest(){
			//todo: extract the weights, do a sort-reverse,flip etc
			if(!isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) return false;
			$locs=explode(",",$_SERVER['HTTP_ACCEPT_LANGUAGE']);
			foreach($locs as $lx){
				$lxt=trim(strtok($lx,";")); // dirty; we're skipping the weights
				if(locale_exists(strtolower($lxt))) return $lxt;
			}
			return false;
		}
	}
	namespace {
		function __($string){
			$bt=debug_backtrace();
			$newText = N14\GetText\gettext($string);
			N14\GetText\createRef($string,$bt);
			if(func_num_args() > 1){
				$args = func_get_args();
				array_shift($args);
				/*array_unshift($args,$newText); // LAME!
				$newText = call_user_func_array("sprintf",$args); */
				$newText = vsprintf($newText,$args);
			}
			return $newText;
		}
		
		/*function __($string){ // bypass version
			if(func_num_args() > 1){ 
				$args = func_get_args();
				array_shift($args);
				$newText = vsprintf($string,$args);
			}else{
				$newText = $string;
			}
			return $newText;
		}*/
	}

?>