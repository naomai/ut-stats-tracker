<?php 

require_once "config.php"; 
require_once "common.php";
 
include "nemoencrypt.php"; 

if ( basename(__FILE__) == basename($_SERVER["SCRIPT_FILENAME"]) ){ // when this file is requested directly
	$retardedMessage="HA HA HA YOU'RE A FREAKING WINNER!";
}

?>
<!DOCTYPE HTML>
<html lang='en'>
<head>
	<meta charset='utf-8'/>
	<link rel='stylesheet' href='<?= maklink(LSTATICFILE,"css/crap.css","") ?>'/>
	<title>Whoops! - UT99 Tracker</title>
	<script type='text/javascript' src='<?= maklink(LSTATICFILE,"js/$jsScriptVersion.js","") ?>'></script>
</head>
<body class='dark' id='uttrk'>
	<div id='body_cont'>
<?php

	if($errstr=="sqlConErr"){
		echo "<h1>Game stats tracker had some problems connecting to db.</h1>
		<p>Check your appConfig file and firewall rules.</p>
		<img src='". maklink(LSTATICFILE,"parachute.png","") ."' alt=\"".__("there's an image here!")."\"/>";
		
	}else{
	echo"
		<h1>DSJK)OI NOPYH(UEW(HUIF HSOFDSHDJUHDSOIDU$)( $#*(!!!1</h1>
		<p><big>Congratulations, you just broke the website! As a prize, you get a free parachute with extra jump from TGES06 Airlines!</big></p>
		<img src='". maklink(LSTATICFILE,"parachute.png","") ."' alt=\"".__("there's an image here!")."\"/>
		<p>".__("Ok, now more serious: This page had some problems while loading. It's totally my fault, not yours. Try refreshing the page, maybe it'll work next time.") ."</p>
		<br>".__("Below is a detailed explaination of this problem in Cow Language") .":<br>
		<pre>";
		
	

error_reporting(0);
// all the variables come from error handler 
if(isset($retardedMessage)){
	echo $retardedMessage;
}else{
	$enc=N14\Encrypt\crypt("PHP: $errno, $errstr, $errfile, $errline\r\nCURL: $debugCurlLastUrl\r\nSQL: ".$sqlqueries,"Love, love me do, You know I love you, I'll always be true, So please, Love me do.",false); 
	echo N14\Encrypt\strMoo($enc,true);
}
	if(isset($eh_callstack)){
		echo "\r\nCS:\r\n";
		if($errno & (E_CORE_ERROR | E_ERROR | E_PARSE)){
			echo "[MF] <<< ";
		}
		echo formattedCallstack($eh_callstack);
	}
}
?>
		</pre>
		<br><br>
		<small>uttracker '13 namonaki</small>
	</div>
</body>
</html>