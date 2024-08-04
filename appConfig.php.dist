<?php

	// App Info
	$appName="UTTracker2WEB";
	$appFullName="Unreal Tournament Stats Tracker";
	$appCredits="UTTWEB2 '13 '14 namonaki14, WaldoMG; contact: namonaki [at] mm.pl";
	
	// App location (URL)
	$appHost = "blackmore.no-ip.pl";
	$appPath = "/UTTrackerPub";
	$appUrlProto = "http";
	$appUrl = $appUrlProto.'://'.$appHost.$appPath;
	
	// Host for cookies
	$site_cookie_host='blackmore.no-ip.pl';
	
	// URL paths to static cloudflare stuff
	$assetsPath = "//blackmore.no-ip.pl/UTTrackerPub/Assets/Site"; // /Assets/Site/
	$assetsGenericPath = "//blackmore.no-ip.pl/UTTrackerPub/Assets/Global";    // /Assets/Global/

	// Local path for static files
	$assetsPathLocal=__DIR__."/Assets/Site"; // /Assets/Site/
	$assetsGenericPathLocal=__DIR__."/Assets/Global";    // /Assets/Global/
	
	
	
	/* DATABASE CONFIG */
	$statdb_host="localhost";
	$statdb_user="uttWeb";
	$statdb_pass="HelloMyNameIsJason";
	$statdb_db="utt";
	
	/*$statdb_host="localhost";
	$statdb_user="uttTestDb";
	$statdb_pass="orbitwhite";
	$statdb_db="utt_test";*/
	$I_Modified_The_Database_Config = false; // set to true if values above are correct
	
	/* DATA FOLDER */
	$dataDir = __DIR__ . "/N14Data";
	
	/* NCORE Location */
	define('N14CORE_API', '0.1THIN');
	define('N14CORE_LOCATION', __DIR__. "/N14Core/v" . N14CORE_API);

	
	/* OPTIONAL COMPONENTS */
	
	// UT Map Page content (screenshots, polys and downloaded map files)
	$utmpInstalled = true;
	$utmpLoc = realpath(__DIR__ . "/UTMP");          // /Optional/UTMP/
	// !! if you also wish to use Map Downloader Tool, edit the configs in UTMP/mapdlcron2.php
	
	// Wireframe renderer for map page
	$rendererInstalled = true;
	$rendererLoc = realpath(__DIR__ . "/WireframeRenderer"); // /Optional/WireframeRenderer/
	
	// UT Map Download Database
	$utmdcInstalled = true;
	$utmdcLoc = realpath(__DIR__ . "/UTMDC"); // /Optional/UTMDC/
		
	// Fonts
	$fontsLoc = realpath(N14CORE_LOCATION . "/GDWrapper/Fonts"); // /N14Core/vX.Y/GDWrapper/Fonts
	
	
	// Location of server scanner config ini (optional)
	$config_ini=__DIR__ . "\\VB10\\updater_v3\\ConsoleApplication1\\bin\\Debug\\utt_updater3.ini";
	
	// display benchmarking checkpoints
	$debug_checkpoint=false;
	
	
	$update_brief=true;    // ?? UNUSED
	$enable_updater=false; // ?? UNUSED
	
	/* APPEARANCE */
	$bodyclass="dark rage";
	// used for graph drawing
	$bgcolor=0x000000;
	$textcolor=0xffffff;
	
	$branchname="";
	
	$defaultLocale="en";
	
	$jsScriptVersion="utt150121-b5";
	
	
	/* N14 Error Handler */
	$errhndFatalPage=__DIR__ . "/errorpage.php"; // for n14phperrorhandler
	$errhndExecPhp=true;

	$sqlAutoexec="SET SESSION TRANSACTION ISOLATION LEVEL READ COMMITTED";


	const SQLENG_DEBUG=false;
	$showSqlHistory=false;
	
		
	const UTT_BUILD=58;
	const UTT_MAPREPORT_VER=57;
	
	error_reporting(E_ALL);
	
	$appTables = array(
		'trk.player.history'=>"playerhistory",
		'trk.player.history.temporary'=>"playerhistorythin",
		'trk.player.info'=>"playerinfo",
		'trk.player.stats'=>"playerstats",
		'trk.server.history'=>"serverhistory",
		'trk.server.info'=>"serverinfo",
		'trk.config'=>"utt_info",
		'trk.mapdl.queue'=>"mapdownloadqueue",
		'trk.utmap.info'=>"mapinfo",
		'trk.bunnytrackaddon.records'=>"btrecords",
		'trk.scanner3.gameendcatcher.schedule'=>"tinyscanschedule",
		'trk.scanner3.serverrescan.queue'=>"serverqueue",
		//'trk.utmdc.data'=>"utmdc_data",
		//'trk.utmdc.sources'=>"utmdc_sources"
		);
	
	
	include_once "contentcfg.php";
	
	
	/* N14Core */

	$n14AppConfig['Host']=$appHost;
	$n14AppConfig['Url']=$appUrl;
	$n14AppConfig['Name']=$appName;
	$n14AppConfig['FullName']=$appFullName;
	$n14AppConfig['Credits']=$appCredits;
	$n14AppConfig['AssetsPath']=$assetsPath;
	$n14AppConfig['AssetsPathGeneric']=$assetsGenericPath;
	$n14AppConfig['Logo']="$assetsPath/logos/mtatrlogo.png";
	$n14AppConfig['Version']="UTTWEB 0.2.".UTT_BUILD;
	
	/* N14Core Legacy (v0.-1) */
	define("N14APPNAME",$appName);
	define("N14APPFULLNAME",$appFullName);
	define("N14APPURL",$appUrl);	
	
	/* N14 Crappy Includes stuff (get rid of it ASAP) */
	$site_host=$appHost;
	$site_url=$appUrl;
	$sub_site_url=$site_url;
	
	if(php_sapi_name()=="cli"){
		$RQU="";
		$basedir=__DIR__;
	}else{
		$RQU=$_SERVER['REQUEST_URI'];
		
		$basedir=dirname($_SERVER['SCRIPT_FILENAME']);
	}
	
	
?>