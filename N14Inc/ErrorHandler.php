<?php
/* made into standalone version */

set_error_handler ('handleerr_standalone');
set_exception_handler ('handleexc_standalone');
register_shutdown_function( "check_for_fatal_standalone" );
error_reporting(error_reporting()&~E_ERROR);



$gd2img=null;
$gd2posx=0;
$gd2posy=0;
$gd2sizx=0;
$gd2sizy=0;
$gd2font=imageloadfont(__DIR__ . "/../N14Core/v0.1THIN/GDWrapper/Fonts/noobtendo.gdf");
$gd2charX=imagefontwidth ($gd2font);
$gd2charY=imagefontheight($gd2font);
$errhndFatalPage=false;

//}


function handleerr_standalone($errno, $errstr, $errfile, $errline,$eh_callstack=null){
	global $gd2img,$sqlqueries,$errhndFatalPage,$errhndExecPhp,$errnames;
	//echo "EH";
	$errorReportingLast = error_reporting(0);
	if($errhndFatalPage!==false && file_exists($errhndFatalPage) && $errno & (E_ERROR|E_RECOVERABLE_ERROR)){
		$obs=ob_list_handlers();
		foreach($obs as $o){
			ob_end_clean();
		}
		header("HTTP/1.1 500 Infernal Server Error");
		if($eh_callstack==null || !isset($eh_callstack[0])) $eh_callstack=debug_backtrace();
		if(isset($eh_callstack[0]) && $eh_callstack[0]['function']=='handleerr') array_shift($eh_callstack);
		if(isset($eh_callstack[0]) && $eh_callstack[0]['function']=='check_for_fatal') array_shift($eh_callstack);
		
		if($errhndExecPhp){
			include $errhndFatalPage;
		}else{
			echo file_get_contents($errhndFatalPage);
		}
		exit;
	}
	
	if(!($errno & ($errorReportingLast|E_ERROR))) return;
	switch($errno){
		case E_NOTICE:
			$type="Notice"; break;
		case E_WARNING:
			$type="Warning"; break;
		case E_ERROR:
			$type="Fatal error"; 
			break;
		default:
			$type=$errnames[$errno];
	}
	$sqcom="";
	if(isset($sqlqueries) && $sqlqueries!="") {
		$sqcom="Sql queries:\r\n".$sqlqueries;
		$sqlqueries="";
	}
	if(php_sapi_name()=="cli") {
		eh_echo_col("$type",15);
		eh_echo_col(": ",8);
		eh_echo_col("$errstr",7);
		eh_echo_col(" in ",8);
		eh_echo_col(strencode(dirname($errfile))."/",1);
		eh_echo_col(basename($errfile),15);
		eh_echo_col(" on line ",8);
		eh_echo_col("$errline\n",15);
		eh_echo_col("$sqcom",7);
	}else if(is_resource($gd2img) && get_resource_type($gd2img)=="gd"){
		//$errinfo="\n$type: $errstr in ".strencode(dirname($errfile))."/".basename($errfile)." on line $errline\n";
		eh_img_echo_col("$type",15);
		eh_img_echo_col(": ",8);
		eh_img_echo_col("$errstr",7);
		eh_img_echo_col(" in ",8);
		eh_img_echo_col(strencode(dirname($errfile))."/",1);
		eh_img_echo_col(basename($errfile),15);
		eh_img_echo_col(" on line ",8);
		eh_img_echo_col("$errline\n",15);
		//eh_img_echo_col("$sqcom",7);		
		if($type==E_ERROR) {
			ob_end_clean();
			header("Content-type: image/jpeg");
			imagejpeg($gd2img);
			imagedestroy($gd2img);
		}
	}else{
		$errstr=nl2br($errstr,true);
		$errinfo="<br/>\n<b>$type</b>: $errstr in <b>".strencode(dirname($errfile))."/".basename($errfile)."</b> on line <b>$errline</b><br/>\n".nl2br($sqcom,true);
		echo $errinfo;
	}
	error_reporting($errorReportingLast);
	return true;
}

if(!function_exists('formattedCallstack')){
	function formattedCallstack($cs){
		$resultArr=array();
		//print_r($cs);
		foreach($cs as $frame){
			if(!is_array($frame) || !isset($frame['function'])){
				$resultArr[]="[invalid stack frame]";
				
			}else{
				
				$frameText="";
				
				if(isset($frame['class'])) $frameText .= $frame['class'] . "::"; //$frameText .= $frame['class'] . $frame['type'];
				
				$frameText .= $frame['function'];
				if(count($frame['args'])) {
					$argsArr=array();
					foreach($frame['args'] as $arg){
						if(gettype($arg)=="object")
							$argsArr[]=get_class($arg);
						else
							$argsArr[]=gettype($arg);
					}
					$frameText .= "(" . implode(", ", $argsArr) . ")";
				}
				$resultArr[] = $frameText;
			}
		}
		$resultArr[] = "main";
		return implode(" <<< ",$resultArr);
	}
}
if(!function_exists('setFatalErrorPage')){
	function setFatalErrorPage($file){
		$GLOBALS['errhndFatalPage']=$file;
	}
}
if(!function_exists('errhandlersetimage')){
	function errhandlersetimage($gdimg){
		global $gd2img;
		$gd2img=$gdimg;
		eh_set_text_bounds();
	}
}


if(!function_exists('eh_img_echo_col')){ // handleerr not present
	function eh_set_text_bounds(){
		global $gd2img,$gd2sizx,$gd2sizy,$gd2charX,$gd2charY;
		$gd2sizx=floor(imagesx($gd2img)/$gd2charX);
		$gd2sizy=floor(imagesy($gd2img)/$gd2charY);
	}

	function eh_img_echo_col($str,$col){
		global $gd2posx,$gd2posy,$gd2img,$gd2sizx,$gd2sizy,$gd2charX,$gd2charY,$gd2font;
		if(!is_resource($gd2img) || get_resource_type($gd2img)!=="gd"){
			eh_echo_col($str,$col);
			return;
		}
		
		if(!is_numeric($col) && is_string($col)){
			if($col[0]=="#"){
				$color = hexdec(substr($col,1));
			}
		}else{
			$color=$GLOBALS['eh_cols'][$col];
		}
		for($i=0; $i<strlen($str); $i++){
			$char=$str[$i];
			if($char=="\n") { 
				$gd2posx=0; 
				$gd2posy++; 
			}else if($char=="\r") { 
				$gd2posx=0;
			}else if($char=="\t") { 
				$gd2posx+=4;
				$gd2posx-=$gd2posx%4;
			}else{
				imagefilledrectangle($gd2img,$gd2posx*$gd2charX,$gd2posy*$gd2charY,($gd2posx+1)*$gd2charX-1,($gd2posy+1)*$gd2charY-1,0x20000000);
				imagechar($gd2img,$gd2font,$gd2posx*$gd2charX+1,$gd2posy*$gd2charY+1,$char,0x60000000);
				imagechar($gd2img,$gd2font,$gd2posx*$gd2charX,$gd2posy*$gd2charY,$char,$color);
				$gd2posx++;
			}
			if($gd2posx>=$gd2sizx) {
				$gd2posx-=$gd2sizx; 
				$gd2posy++; 
			}
		}
	}
}


//http://www.php.net/manual/en/function.set-error-handler.php#112291
function check_for_fatal_standalone(){
    $error = error_get_last();
    if ( $error["type"] == E_ERROR )
        handleerr( $error["type"], $error["message"], $error["file"], $error["line"] );
}

function handleexc_standalone($exc){
    if ( $exc instanceof Exception ){
		//print_r($exc->getTrace());
        handleerr( E_RECOVERABLE_ERROR, "Uncaught " . get_class($exc) . ": " . $exc->getMessage(), $exc->getFile(), $exc->getLine() ,$exc->getTrace());
	}
	//return true;
}

if(!function_exists('strencode')){
	function strencode($str){
		$key="fdsijfuidsfn489jt43qt9j04u3689u20yh095jt289"; // don't need a better one
		$enstr="";
		for($i=0; $i<strlen($str);$i++){
			$enstr.= $str[$i] ^ $key[$i%strlen($key)];
			
		}
		return base64_encode($enstr);
	}
}
$errnames=array(
	E_ERROR=>"E_ERROR",
	E_WARNING=>"E_WARNING",
	E_PARSE=>"E_PARSE",
	E_NOTICE=>"E_NOTICE",
	E_CORE_ERROR=>"E_CORE_ERROR",
	E_CORE_WARNING=>"E_CORE_WARNING",
	E_CORE_ERROR=>"E_COMPILE_ERROR",
	E_CORE_WARNING=>"E_COMPILE_WARNING",
	E_USER_ERROR=>"E_USER_ERROR",
	E_USER_WARNING=>"E_USER_WARNING",
	E_USER_NOTICE=>"E_USER_NOTICE",
	E_STRICT=>"E_STRICT",
	E_RECOVERABLE_ERROR=>"E_RECOVERABLE_ERROR",
	E_DEPRECATED=>"E_DEPRECATED",
	E_USER_DEPRECATED=>"E_USER_DEPRECATED"
);

$eh_win_com=null;


if(!function_exists('eh_echo_col')){
	if(php_sapi_name()=="cli"){
		$eh_current_con_attr=7;
		$eh_previous_con_attr=7;
		$hasComDynamicWrapper = false;
		if(class_exists ("COM")){
			try{
				$eh_win_com = new COM('DynamicWrapper');
				$hasComDynamicWrapper = true;
			}catch(Exception $e){
				
			}
		}
			
		if($hasComDynamicWrapper){
			$eh_win_com->Register('kernel32.dll', 'GetStdHandle', 'i=h', 'f=s', 'r=l');
			$eh_win_com->Register('kernel32.dll', 'SetConsoleTextAttribute', 'i=hl', 'f=s', 'r=t');
			$eh_con_hnd=$eh_win_com->GetStdHandle(-11);
			function eh_con_color($c){
				global $eh_win_com,$current_con_attr,$previous_con_attr,$conhnd;
				if(is_object($eh_win_com)){
					$eh_win_com->SetConsoleTextAttribute($conhnd, $c);
				}
				
				$previous_con_attr=$current_con_attr;
				$current_con_attr=$c;
			}
			
			function eh_echo_col($txt,$col){
				global $previous_con_attr;
				eh_con_color($col);
				echo $txt;
				eh_con_color($previous_con_attr);
				

			}
		}else{
			function eh_con_color($c){
				global $eh_win_com,$current_con_attr,$previous_con_attr,$conhnd;
				
				$previous_con_attr=$current_con_attr;
				$current_con_attr=$c;
			}
			
			function eh_echo_col($txt,$col){
				echo $txt;
			}
		}
	}else{
		

		function eh_con_color($c){
			global $current_con_attr,$previous_con_attr;
			
			$previous_con_attr=$current_con_attr;
			$current_con_attr=$c;
		}
		function eh_echo_col($txt,$col){
			global $eh_cols;
			echo "<span style='color:#".(str_pad(dechex($eh_cols[$col]),6,"0"))."'>$txt</span>";
		}
	}
}
$eh_cols=array();
for($i=0; $i<16;$i++){
	$eh_cols[$i]=((($i&1))|(($i&2)<<7)|(($i&4)<<14))*(($i&8)?0xFF:0x80);
}
$eh_cols[8]=0x808080;

