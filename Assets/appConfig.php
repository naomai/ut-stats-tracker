<?php
	//$appHost='amaki.cf';
	$appHost='ut99.tk';
	$appUrl="//$appHost/static";
	$appName="N14CDN";
	$appCredits="2015 Namonaki14";
	//$assetsPath="//amaki.cf";
	$assetsPath="//ut99.tk/static";
	
	$cdnAllowedOrigins = array(
		"amaki.no-ip.eu",
		"blackmore.no-ip.pl",
		"ut99.tk",
		"webcache.googleusercontent.com",
		"localhost"
	);

	$basedir=dirname($_SERVER['SCRIPT_FILENAME']);
	$includes="./includes";
	/*	echo "<!-- ";
	print_r($_GET);
	echo "	-->";*/	
	$RQU=$_SERVER['REQUEST_URI'];
	
?>