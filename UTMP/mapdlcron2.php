<?php
require_once __DIR__ . "/../appConfig.php";
/* CONFIG BELOW!!! */

// UCC Location - you need to have a working UT installation
define ("UCC_LOC","H:\\ut99serv\\System\\UCC.exe");
	
// paste UT game installations for file lookups
$gameInstallations=array(
	"H:\\games\\UnrealTournament",
	"H:\\games\\UnrealGold",
	"H:\\games\\utdemo",
	//"H:\\games\\quake", // lol
	"H:\\ut99serv"
);

// all downloaded content will be copied to this folder
$fileStorageDir = "H:\\ut99serv\\uttdownload";
// location of UTMP
$utmpDir = $utmpLoc;
// temporary directory used for processing current map
$tempDownloadDir = "$utmpDir/maps";

// path of PHP executable
$phpCliPath = "H:\\MICROSYF\\wamp\\bin\\php\\php5.5.11\\php";
// location of php.ini - MUST BE CONFIGURED FOR CLI
$phpCliConfigPath = "H:/GNIOTY_E/pn/php55.ini";

// path to 7z.exe
$sevenZPath = $utmpDir . "/7z.exe";
// JPEG converter
$jpgConverterPath = $utmpDir . "\\convert.exe";

// database config, change if you have separate Map Downloader user
$utmpDBUser = $statdb_user;
$utmpDBPass = $statdb_pass;

/* ENDOF CONFIG */

/* TOTAL REWRITE & MERGE WITH MAPIMG (NO MORE SUBPROCESSES!) */
exitIfNotCLI();


/* INIT */
require_once "../common.php";
echo "* Init...\r\n";
require_once "../sqlengine.php";
echo "* Loading UTMDC. If the process stays at this point for long, it means that UTMDC is redownloading websites.\r\n";
require_once "../ut_map_lookup.php";
echo "* UTMDC loaded.\r\n";
require_once "uestructures.php";

error_reporting(E_ALL);
$errhndFatalPage=false;

ob_implicit_flush(true);
ob_end_flush();
$fileStorageDir = realpath($fileStorageDir);
$tempDownloadDir = realpath($tempDownloadDir);
$utmpDir = realpath($utmpDir);
$sevenZPath = realpath($sevenZPath);
$jpgConverterPath = realpath($jpgConverterPath);

$dbh=sqlcreate($statdb_host,$utmpDBUser,$utmpDBPass,$statdb_db,false);
$localPackages = getAllLocalPackages($gameInstallations);
$localPackages = array_merge($localPackages, getPackagesForDir($fileStorageDir));
echo "* Init done. " . count($localPackages) . " local packages found.\r\n";

do{
	$quS = sqlquery("SELECT * FROM mapdownloadqueue ORDER BY recordid");
	if(count($quS)){
		$qu=$quS[0];
		if($qu['mapname']!=""){
			$jobType=(int)$qu['jobType'];
			echo "Map queue size: ".count($quS)."\r\n";
			echo "Job flags: ".dechex($jobType)."\r\n";
			doMapJob($qu['mapname'],$jobType);
		}//else{
			sqlexecnow("DELETE FROM mapdownloadqueue WHERE recordid={$qu['recordid']}");
		//}
		
		sleep(1);
	}else{
		sleep(5);
	}
	$sqlqueries=""; // to avoid filling memory with pointless query history
}while(true);

sqldestroy($dbh); // not sure if we really need this


function doMapJob($mapName, $jobType){
	global $utmpDir,$phpCliPath,$phpCliConfigPath;
	//echo "LJ: $jobType & ".LAYOUTGEN_JOB_DOWNLOAD." = ". ($jobType & LAYOUTGEN_JOB_DOWNLOAD) . "\r\n";
	try{
		//echo "* JobType & LAYOUTGEN_JOB_DOWNLOAD = ".($jobType & LAYOUTGEN_JOB_DOWNLOAD)."\n";
		if($jobType & LAYOUTGEN_JOB_DOWNLOAD != 0){
			//echo "LEN=".strlen($mapName)."!\r\n";
			if(strlen($mapName) > 2 && strlen($mapName) < 100){
				mapDownloadStuff($mapName);
			}
		}
		if(file_exists($utmpDir . "/polys/".name2id($mapName).".t3d") || file_exists(__DIR__ . "/wireframe/jsonpolys/".name2id($mapName).".json")){
			if($jobType & LAYOUTGEN_JOB_REDNERLAYOUT != 0){ // REDNER lol
				echo("* Rednering map layouts...\r\n");
				
				// ort
				$queryStr = "?map=".urlencode($mapName)."&projmode=ort";
				system("$phpCliPath -c $phpCliConfigPath -f wireframe/renderpolywithsprites.php \"".addcslashes($queryStr,"\0..\37\\\"\177..\377")."\"");
				$queryStr = "?map=".urlencode($mapName)."&projmode=ort&fhd";
				system("$phpCliPath -c $phpCliConfigPath -f wireframe/renderpolywithsprites.php \"".addcslashes($queryStr,"\0..\37\\\"\177..\377")."\"");
				// iso
				$queryStr = "?map=".urlencode($mapName)."&projmode=iso3";
				system("$phpCliPath -c $phpCliConfigPath -f wireframe/renderpolywithsprites.php \"".addcslashes($queryStr,"\0..\37\\\"\177..\377")."\"");
				$queryStr = "?map=".urlencode($mapName)."&projmode=iso3&fhd";
				system("$phpCliPath -c $phpCliConfigPath -f wireframe/renderpolywithsprites.php \"".addcslashes($queryStr,"\0..\37\\\"\177..\377")."\"");
				// tibia
				$queryStr = "?map=".urlencode($mapName)."&projmode=tibia&fhd";
				system("$phpCliPath -c $phpCliConfigPath -f wireframe/renderpolywithsprites.php \"".addcslashes($queryStr,"\0..\37\\\"\177..\377")."\"");
				//print
				$queryStr = "?map=".urlencode($mapName)."&projmode=ort&fhd&colorScheme=print";
				system("$phpCliPath -c $phpCliConfigPath -f wireframe/renderpolywithsprites.php \"".addcslashes($queryStr,"\0..\37\\\"\177..\377")."\"");
				
			}else if(($jobType & LAYOUTGEN_JOB_GENREPORT)  != 0){ // layout renderer does that too
				echo("* Generating report...\r\n");
				system("$phpCliPath -c $phpCliConfigPath -f wireframe/genReport.php \"".addcslashes($mapName,"\0..\37!@\@\177..\377")."\"");
				
			}
		}
		echo "* AllJobsDone: $mapName\r\n";
	}catch(Exception $e){
		echo "! Couldn't complete job: " . $e->getMessage() . "\r\n";
	}
	
}

/* MAPIMG.PHP STUFF*/
function getAllLocalPackages($gameInstallationsList){
	$localPackages = array();
	foreach($gameInstallationsList as $gi){
		$localPackages = array_merge($localPackages, getPackagesForDir($gi."\\Maps"));
		$localPackages = array_merge($localPackages, getPackagesForDir($gi."\\Textures"));
		$localPackages = array_merge($localPackages, getPackagesForDir($gi."\\Music"));
		$localPackages = array_merge($localPackages, getPackagesForDir($gi."\\Sounds"));
		$localPackages = array_merge($localPackages, getPackagesForDir($gi."\\System"));
	}
	return $localPackages;
}

function getPackagesForDir($dir){ 
	$packages = array();
	echo "' getPackagesForDir($dir): ";
	if(($dr=@opendir($dir))!==false){
		while(($rd=readdir($dr))!==false) { 
			if(filetype($dir . "\\" . $rd)=='file'){
				$packages[strtolower($rd)] = $dir . "\\" . $rd; 
			}
		}
		closedir($dr);
	}
	echo count($packages)." found\r\n";
	return $packages;
}

function mapDownloadStuff($mapname){ // Download map with all required packages, extract polys and screenshot
	global $utmpDir, $tempDownloadDir,$fileStorageDir;
	echo "\n** $mapname **\n";
	$mapfname=name2id($mapname);
	$loc = getLocalMapLocationAndMaybeDownload($mapname);
	
	if($loc===false){
		touch($utmpDir . "\\sshots\\$mapfname.jpg");
		throw new Exception("Map file lookup failed");
	}else{
		echo "PATH: $loc\r\n";
	}
	// DEPS - PHP stage
	$deps = checkDependencies($loc);
	
	
	foreach($deps['packages'] as $depId => $import){
		echo "DEP: {$import['filename']} PRESENT: ".($import['present']?"true":"false");
		if(!$import['present']){
			echo "Missing: {$import['filename']}\r\n";
			$dlPak=downloadPackage($import['name']);
			if($dlPak===false){
				//echo "CantDownload\r\n";
				throw new Exception("Missing undownloadable dep: " . $import['name']);
			}else{
				$deps['packages'][$depId]['uttLoc'] = $dlPak;
			}
		}
			echo " NEWLOC=" . $deps['packages'][$depId]['uttLoc'] . "\r\n";
	}
	echo "* Deps OK\r\n";
	file_put_contents(__DIR__ . "/wireframe/mapdeps/$mapfname.json",json_encode($deps));
	
	try{
		uccExtract($loc);
	}catch(Exception $e){
		echo "! ERROR: {$e->getMessage()}\n";
		file_put_contents($utmpDir . "/sshots/$mapfname.jpg","");
	}
	
	//cleanup
	if($tempDownloadDir!="" && $fileStorageDir!=""){ // FIXED: deleting alle .zip files in disk root when any of those variables is not set
		shell_exec("DEL /S /F /Q \"$tempDownloadDir\\*.*\""); // WINDOWS_STUFF
		//shell_exec("DEL /S /F /Q \"$fileStorageDir\\*.zip\""); // WINDOWS_STUFF
		//shell_exec("DEL /S /F /Q \"$fileStorageDir\\*.uz\""); // WINDOWS_STUFF
	}
	//return $loc;
	
	
	
}

function getLocalMapLocationAndMaybeDownload($mapname){
	global $tempDownloadDir,$gameInstallations;
	echo "* Searching locally...\n";
	
	// LOCAL
	$loc = checkForLocalPackage($mapname.".unr", $GLOBALS['localPackages']);
	// CACHE
	if($loc===false){
		foreach($gameInstallations as $gi){
			$ori="";
			$loc=checkUnrealCache($mapname,$gi,$ori);
			//$pakName=$ori;
			if($loc!==false) break;
		}
	}
	// REDIRECT
	if($loc===false){
		$loc = downloadPackageFromRedirect($mapname);
	}
	if($loc!==false){
		if(strpos($loc, $tempDownloadDir) === false) {
			echo "* Found at: $loc, copying...\n";
			$localFileName = basename($loc);
			copy($loc,$tempDownloadDir . "/" . $localFileName);
			$origFileName = "$mapname.unr";
			if(strcasecmp($localFileName,$origFileName)!==0){
				$loc = $tempDownloadDir . "/" . $origFileName;
				rename($tempDownloadDir . "/" . $localFileName, $loc);
			}
			
		}
		//$fileName=$mapname.".unr";
	}
	// UTMDC 
	if($loc===false){
		$loc = downloadMapFromUTMDC($mapname);
	}
	return $loc;	
}

function downloadPackageFromRedirect($package){
	echo "* Checking redirect...\n";
	$packagef=name2id($package);
	
	$loc=downloadPackage($package);
	
	if($loc===false && stripos($package,"CTF-BT-")===0){
		$package=substr($package,4);
		$loc=downloadPackage($package);
		
	}
	
	return $loc;
}

function downloadMapFromUTMDC($mapname){
	global $tempDownloadDir,$fileStorageDir,$localPackages;
	echo "* Finding map in UTMDC...\n";
	$mapfname=name2id($mapname);
	$url=findMapUrl($mapname);
	
	if($url=="") {
		echo "downloadMapFromUTMDC: Map not found\r\n";
		return false;
	}

	$extension = pathinfo(parse_url($url,PHP_URL_PATH), PATHINFO_EXTENSION);
	$fname = $mapfname . "." . $extension;
	
	
	echo "* Downloading map from '".parse_url ($url, PHP_URL_HOST)."'...\n";
	$downloadDest = $tempDownloadDir . "/$fname";
	download($url,$downloadDest);
	if(!file_exists($downloadDest)) {
		echo "downloadMapFromUTMDC: Download failed\r\n";
		return false;
	}
	
	if(stripos($fname,".uz")!==false){
		$path=unpackUZ($downloadDest);
		return $path;
	}else {
		if(stripos($fname,".unr")!==false){
			echo "* 'Somehow Unpacked'\n";
		}else{
			echo "* Unpacking UNR...\n";
			unpackUNR($downloadDest);
		}
		echo "* Searching for unpacked UNR...\n";
		$fx=getUNRFileInDir($tempDownloadDir);
		if($fx=="") {
			echo "downloadMapFromUTMDC: UNR not found\r\n";
			return false;
		}
		if(strcasecmp($mapname, pathinfo($fx,PATHINFO_FILENAME))!==0){
			echo "' PackedWithWrongName: ".basename($fx)."\r\n";
			$newFx = $mapname . ".unr"; // unr file name is different
			$fullNameOld = $tempDownloadDir . "/" . $fx;
			$fullNameNew = $tempDownloadDir . "/" . $newFx;
			rename($fullNameOld, $fullNameNew);
			$fx = $newFx;
		}
		
		shell_exec("XCOPY \"$tempDownloadDir\\*.unr\" \"$fileStorageDir\\\" /Y"); // WINDOWS_STUFF
		shell_exec("XCOPY \"$tempDownloadDir\\*.utx\" \"$fileStorageDir\\\" /Y"); // WINDOWS_STUFF
		shell_exec("XCOPY \"$tempDownloadDir\\*.uax\" \"$fileStorageDir\\\" /Y"); // WINDOWS_STUFF
		shell_exec("XCOPY \"$tempDownloadDir\\*.umx\" \"$fileStorageDir\\\" /Y"); // WINDOWS_STUFF
		shell_exec("XCOPY \"$tempDownloadDir\\*.u\" \"$fileStorageDir\\\" /Y"); // WINDOWS_STUFF
		$localPackages = array_merge($localPackages, getPackagesForDir($fileStorageDir));
	
		return realpath($tempDownloadDir . "/" . $fx);
	}

}

function checkDependencies($mapfile){
	global $gameInstallations, $localPackages;
	echo "* Checking dependencies...\r\n";
	//try{
	//echo __DIR__ . "/utmp/maps/$fx\r\n";
	$deps = findPackageDependencies($mapfile);
	foreach($deps['packages'] as $depId=>$import){
		//echo $import['name'].":";
		$depLoc = checkForLocalPackage($import['filename'],$localPackages);
		if($depLoc===false) $depLoc = checkForLocalPackage($import['name'],$localPackages);
		
		if($depLoc===false) {
			foreach($gameInstallations as $gi){
				$ori="";
				$depLoc=checkUnrealCache($import['name'],$gi,$ori);
				$pakName=$ori;
				
				if($depLoc!==false) {
					//echo "UsingCache: $pakName=".basename($locx)."\r\n";
					break;
				}
			}
		}
		$deps['packages'][$depId]['present'] = ($depLoc!==false);
		$deps['packages'][$depId]['uttLoc'] = $depLoc;
		/*if($depLoc==""){
			echo "Missing: {$import['filename']}\r\n";
			$dlPak=downloadPackage($import['name']);
			if($dlPak===false){
				echo "CantDownload\r\n";
			}else{
				$depLoc=$dlPak;
			}
		}
		*/
		
	}
	//file_put_contents(__DIR__ . "/wireframe/mapdeps/$mapfname.json",json_encode($deps));
	/*}catch(Exception $e){
		echo "Failed: {$e->getMessage()}\r\n";
		return array();
	}*/
	return $deps;
}

function uccExtract($filename){
	global $utmpDir, $tempDownloadDir, $fileStorageDir, $gameInstallations, $jpgConverterPath, $localPackages;
	$downloadedPackages=array(); // avoid redownloading same file
	
	$mapname = pathinfo($filename, PATHINFO_FILENAME);
	$mapfname = name2id($mapname);
	
	if(!file_exists("$utmpDir\\polys\\$mapfname.t3d")){
		echo "* UCC-Extracting level...\n";
		// solve additional missing deps
		for($ret=0; $ret<50; $ret++){ // set the max limit of packages to download
			$ucc_result=shell_exec("\"".UCC_LOC."\" BatchExport \"$filename\" Level t3d \"$utmpDir\\polys\"" );
			if(preg_match("/Can't find file for package '(.*)'/",$ucc_result,$mat)===1){
				$dlPackage=$mat[1];
				if(isset($downloadedPackages[$dlPackage])){
					throw new Exception("Couldn't download required package: $dlPackage");
				}
				$downloadedPackages[$dlPackage]=true;
				
				echo "Looking for package: $dlPackage\r\n";
				
				// LOCAL
				$loc = checkForLocalPackage($dlPackage, $localPackages);
				// CACHE
				if($loc===false){
					$ori="";
					foreach($gameInstallations as $gi){
						$loc=checkUnrealCache($dlPackage,$gi,$ori);
						//$pakName=$ori;
						if($loc!==false) break;
					}
				}
				// REDIRECT
				if($loc===false){
					$loc = downloadPackageFromRedirect($dlPackage);
				}
				
				if($loc!==false){
					if(strpos($loc, $tempDownloadDir) === false) {
						echo "* Found at: $loc, copying...\n";
						copy($loc,$tempDownloadDir . "/" . basename($loc));
						$loc = $tempDownloadDir . "/" . basename($loc);
						
						$localFileName = basename($loc);
						if(isset($ori) && $ori!==""){
							$loc = $tempDownloadDir . "/" . $ori;
							echo "' rename(".$tempDownloadDir . "/" . $localFileName . ", " . $loc.")\r\n";
							rename($tempDownloadDir . "/" . $localFileName, $loc);
							
						}
						
						
					}
					
					
					
				}
			}else{
				break;
			}
		}
		if(!file_exists("$utmpDir/polys/MyLevel.t3d")) {
			throw new Exception("Level not found, ucc says: \r\n$ucc_result");
		}else {
			//echo "' rename($utmpDir\\polys\\MyLevel.t3d, $utmpDir\\polys\\$mapfname.t3d);\n";
		
			rename("$utmpDir\\polys\\MyLevel.t3d", "$utmpDir\\polys\\$mapfname.t3d");
		}
	}
	
	/// SCREENSHOT
	echo "* UCC-Extracting screenshots...\n";
	exec("\"".UCC_LOC."\" BatchExport \"$filename\" texture bmp \"$utmpDir\\sshotsbmp\"" );
	
	
	shell_exec("XCOPY \"$tempDownloadDir\\*.*\" \"$fileStorageDir\\\" /Y"); // WINDOWS_STUFF
	
	if(file_exists("$utmpDir/sshotsbmp/SkinMapBanner1.bmp")) {// for JB
		echo "* Converting JB map banners to JPG...\n";
		exec("\"$jpgConverterPath\" \"" . $utmpDir . "\\sshotsbmp\\SkinMapBanner1.bmp\" \"" . $utmpDir . "\\sshots\\$mapfname"."_jbmb1.jpg\"");
		if(file_exists($utmpDir . "/sshotsbmp/SkinMapBanner2.bmp"))
			exec("\"$jpgConverterPath\" \"" . $utmpDir . "\\sshotsbmp\\SkinMapBanner2.bmp\" \"" . $utmpDir . "\\sshots\\$mapfname"."_jbmb2.jpg\"");
	}	
	//if(!file_exists($utmpDir . "/sshotsbmp/Screenshot.bmp")) throw new Exception("Screenshot not found");
	if(file_exists($utmpDir . "/sshotsbmp/Screenshot.bmp")) {
		echo "* Converting screenshot to JPG...\n";
		exec("\"$jpgConverterPath\" \"" . $utmpDir . "\\sshotsbmp\\Screenshot.bmp\" \"" . $utmpDir . "\\sshots\\$mapfname.jpg\"");
		//if(!file_exists($utmpDir . "/sshots/$mapfname.jpg")) throw new Exception("Conversion failed");

		
	}else{
		echo "* Map has no screenshot.\n";
		//file_put_contents($utmpDir ."/sshots/$mapfname.jpg",""); // 0 bytes screenshot file = no screenshot for map
		touch($utmpDir ."/sshots/$mapfname.jpg");
	}
	array_map('unlink', glob($utmpDir . "/sshotsbmp/*.*"));
	
	$localPackages = array_merge($localPackages, getPackagesForDir($fileStorageDir));
	echo "* Reloaded packages. " . count($localPackages) . " local packages found.\r\n";

	
	echo "* uccExtract: Done.\n";
			
}

function exitIfNotCLI(){ // AKA stopCuriousGuysFromLookingAroundUsingTheWebInterface()
	if(PHP_SAPI!="cli") {
		header("Content-type: text/plain");
		echo("Well heeeeello there!! :)\r\n");
		echo("This script is meant to be run from command line.\r\n\r\n");
		exit("As they say...\r\nThis program cannot be run in WWW mode.");
	}
}


/* DONT_REWRITE FUNCTIONS */
function checkForLocalPackage($packName, &$packList){
	//echo "' checkForLocalPackage($packName,...)\r\n";
	foreach($packList as $file=>$filePath){
		$fileWithoutExtension=strtok($file,".");
		if(strcasecmp($fileWithoutExtension,$packName)===0 || strcasecmp($file,$packName)===0){
			return realpath($filePath);
		}
	}
	return false;
}

function checkUnrealCache($packName,$installDir,&$originalName=null){
	static $cachedList=array();
	if(strpos($packName,".")===false) $packName.=".";
	
	if(!isset($cachedList[crc32($installDir)])){
		$currentCacheIdx=crc32($installDir);
		if(file_exists($installDir."/Cache/cache.ini")){
	
			$cf=file($installDir."/Cache/cache.ini",FILE_IGNORE_NEW_LINES);
			foreach($cf as $cx){
				$fileHash=strtok($cx,"=");
				if(strlen($fileHash)!=32) continue;
				$fileOriginal = strtok("\r");
				$cachedList[$currentCacheIdx][$fileOriginal]=$fileHash;
			}
		}
		
	}
	$currentCache=&$cachedList[crc32($installDir)];
	if(is_array($currentCache)){
		foreach($currentCache as $fileOriginal=>$hash){
			if(stripos($fileOriginal,$packName)===0 && file_exists($installDir."/Cache/$hash.uxx")){
				$originalName=$fileOriginal;
				
				return $installDir."/Cache/$hash.uxx";
			}
		}
	}
	return false;
}

function downloadPackage($packName){
	global $localPackages,$fileStorageDir;
	error_reporting(E_ALL);
	$url = findPackageByName($packName);
	if($url == ""){
		echo "Can't find file for package '$packName'\r\n";
		return false;
	}
	
	echo "Receiving '$packName' (F10 Cancels)\r\n";
	echo "$url\r\n";
	
	$fn = parse_url($url,PHP_URL_PATH);
	$fn = substr($fn,strrpos($fn,"/")+1);
	
	$packfile = $GLOBALS['tempDownloadDir'] . "/$fn";
	download($url, $packfile);
	
	if(($unpackedName = unpackUZ($packfile))!==false){
		/*$packfileUncomressed=dirname(UCC_LOC)."\\".str_ireplace(".uz","",basename($packfile));
		$packfileUncomressedBase=basename($packfileUncomressed);*/
		/*if(!isset($localPackages[strtolower($fnN)])){
			$fnN = pathinfo($unpackedName, PATHINFO_BASENAME);
			$localPackages[strtolower($fnN)] = $unpackedName;
			echo "' addLocalPackage(".strtolower($fnN).", $unpackedName)\r\n";
		}*/
		echo "Successfully received '$packName'\r\n";
		$localPackages = array_merge($localPackages, getPackagesForDir($fileStorageDir));
		echo "* Reloaded packages. " . count($localPackages) . " local packages found.\r\n";

		
		return $unpackedName;
		//return realpath(dirname(UCC_LOC)."\\..\\uttdownload\\$packfileUncomressedBase");
	}else{
		echo "Downloading package '$packName' failed: \r\n";
		
		return false;
	}
		
	return false;
}

function download($url,&$dest){ // this function might change $dest if Content-Disposition header was present
	global $tempDownloadDir;
	set_time_limit(0);
	
	//HEAD - get the real file name from Content-Disposition 
	$fHdr = fopen($tempDownloadDir."/hdr.txt", "w+");
	$ch = curl_init(str_replace(" ","%20",html_entity_decode($url)));
	curl_setopt($ch, CURLOPT_TIMEOUT, 300);
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
	curl_setopt($ch, CURLOPT_WRITEHEADER, $fHdr);
	curl_setopt($ch, CURLOPT_NOBODY, true);
	if(isset($GLOBALS['argc'])) {
		$launchArgs="CLI:".implode(" ",$GLOBALS['argv']);
	}else{
		$launchArgs="CGI:".$_GET['SCRIPT_FILENAME'];
	}
	$userAgent = "Mozilla/5.0 (Windows NT 10.0) UTTracker/UnrealMapArchiver (+http://tracker.ut99.tk\$projectInfo['uaRefUrl']) NemoPHPDownloaderThing (loaded from $launchArgs)";
	//echo $userAgent;
	curl_setopt($ch, CURLOPT_USERAGENT,$userAgent); 
	curl_exec($ch);
	
	// with the help of http://stackoverflow.com/a/2072920
	rewind($fHdr);
	$headers = stream_get_contents($fHdr);
	if(preg_match('/Content-Disposition: .*filename="?([^\s]+)"?/i', $headers, $matches)) {
		echo "' download(): content-disposition header renames file to: ".$matches[1]."\r\n";
		$destNew = dirname($dest) . "/" . $matches[1];
	}elseif(preg_match('/Location: ([^\s]+)/i', $headers, $matches)) {
		$newPath = urldecode($matches[1]);
		$newName = pathinfo($newPath, PATHINFO_BASENAME);
		echo "' download(): location header renames file to: ".$newName."\r\n";
		$destNew = dirname($dest) . "/" . $newName;
	}
	if(isset($destNew)){
		$dest = $destNew;
	}
	fclose($fHdr);
	
	
	//die("dest=$dest; exists=".((int)file_exists($dest)));
	if(file_exists($dest) && filesize($dest)>1024) {
		echo "* Already downloaded.\r\n";;
		return;
	}
	$fp = fopen ($dest, 'w+'); 
	//$ch = curl_init(str_replace(" ","%20",html_entity_decode($url)));
	//curl_setopt($ch, CURLOPT_TIMEOUT, 300);
	curl_setopt($ch, CURLOPT_FILE, $fp); 
	curl_setopt($ch, CURLOPT_WRITEHEADER, null);
	curl_setopt($ch, CURLOPT_NOBODY, false);
	//curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
	//curl_setopt($ch, CURLOPT_WRITEHEADER, $fHdr);
	
	
	curl_exec($ch); // get curl response
	curl_close($ch);
	fclose($fp);
	
	
	
}

function unpackUZ($packfile){
	global $fileStorageDir;
	$packfileUncomressed=dirname(UCC_LOC)."\\".str_ireplace(".uz","",basename($packfile));
	$packfileUncomressedURLDecoded=dirname(UCC_LOC)."\\".str_ireplace(".uz","",urldecode(basename($packfile)));
	
	$decomp=shell_exec("\"".UCC_LOC."\" decompress \"$packfile\"");
	echo "UnpackingUZ: $packfileUncomressed\r\n";
	if(!file_exists($packfileUncomressed) && file_exists($packfileUncomressedURLDecoded)){
		echo "OopsWasntCompressed\r\n";
		$packfileUncomressed=$packfileUncomressedURLDecoded;
	}
	$packfileUncomressedBase=basename($packfileUncomressed);
	if(file_exists($packfileUncomressed)){
		shell_exec("MOVE \"$packfileUncomressed\" \"$fileStorageDir\\$packfileUncomressedBase\""); // WINDOWS_STUFF!!
		echo("MOVE \"$packfileUncomressed\" \"$fileStorageDir\\$packfileUncomressedBase\"\r\n");
		return realpath("$fileStorageDir\\$packfileUncomressedBase");
	}
	echo "UnpackingUZFail: $decomp\r\n";
	return false;
}

function unpackUNR($file){
	$ret=0;
	//$ot="";
	//echo "' exec(\"" . $GLOBALS["sevenZPath"]. "\" e -y -r -o\"" . $GLOBALS['tempDownloadDir'] . "/\" \"$file\" *.*)\r\n";
	exec ("\"" . $GLOBALS["sevenZPath"]. "\" e -y -r -o\"" . $GLOBALS['tempDownloadDir'] . "/\" \"$file\" *.*",$ret);
	
	//echo "7Z output: \r\n".implode("\r\n",$ret)."\r\n";
	return $ret;
}

function getUNRFileInDir($dir){
	$d=opendir($dir);
	$dx="";
	do{
		$dx=readdir($d);
	}while($dx!==false && stripos($dx,".unr")===false);
	closedir($d);
	return $dx;
	
}

/* SPAGHETTI ALERT! */
	
function findPackageDependencies($file){
	global $readerUEPackageHeader,$readerUENames,$readerUEExport,$readerUEImport;
	$importedPackages = array();
	$packageHeader = array();
	try{
		$packageFile = fopen($file,"rb");

		$packageHeader = $readerUEPackageHeader->read($packageFile);
		
		if($packageHeader['magic']!=-1641380927 || $packageHeader['packageVersion']>69){
			throw new Exception("Unsupported package format {$packageHeader['magic']} {$packageHeader['packageVersion']}");
		}
		
		
		fseek($packageFile,$packageHeader['nameOffset'],SEEK_SET);
		$names = $readerUENames->readMulti($packageHeader['nameCount'],$packageFile);
		
		fseek($packageFile,$packageHeader['exportOffset'],SEEK_SET);
		$exports = $readerUEExport->readMulti($packageHeader['exportCount'],$packageFile);
		
		fseek($packageFile,$packageHeader['importOffset'],SEEK_SET);
		$imports = $readerUEImport->readMulti($packageHeader['importCount'],$packageFile);
		

		
		
		foreach($imports as $imp){
			$importType = $names[$imp['classIdx']]['name'];
			$importName = $names[$imp['nameIdx']]['name'];
			//echo "".getUnrealFriendlyReference($names,$imp);
			if($importType=="Package"){
				if($imp['outer']!=0) continue;
				$importedPackages[$importName]["name"]=$importName;
				$importedPackages[$importName]["referenceCount"]=0;
				}else{
				$root=getObjectRoot($imp,$exports,$imports);
				//echo "ROOT:" . getUnrealFriendlyReference($names,$root);
				$imPak = &$importedPackages[$names[$root['nameIdx']]['name']];
				$imPak['objects'][$importType][]=$importName;
				$imPak['referenceCount']=(isset($imPak['referenceCount'])?$imPak['referenceCount']+1:1);
			}
			
			//echo "<br>";
		}
		//print_r($importedPackages);
		
		// type detection
		
		foreach($importedPackages as $pakName=>$pak){
			//echo $pak['name'] .".";
			if(!isset($pak['objects'])){
				$type="???";
				}else if(count($pak['objects'])==1){
				reset($pak['objects']);
				$obx=key($pak['objects']);
				switch($obx){
					case "Sound": 
					$type = "uax"; break;
					case "Texture": 
					case "FractalTexture":
					case "FireTexture":
					case "IceTexture":
					case "WaterTexture":
					case "WaveTexture":
					case "WetTexture":
					case "ScriptedTexture":
					$type = "utx"; break;
					case "Music": 
					$type = "umx"; break;
					default: 
					$type = "u"; break;
				}
				}else{
				$type="u";
			}
			
			$importedPackages[$pakName]['filename']="$pakName.$type";

		}
		

	}catch(Exception $e){
		echo "UEDeps Exception: {$e->getMessage()}\r\n";
		echo "Callstack:\r\n".formattedCallstack($e->getTrace())."\r\n";
	}finally{
		if(isset($packageFile) && $packageFile!==false) fclose($packageFile);
		return array('packages'=>$importedPackages,'packageHeader'=>$packageHeader);
	}
}

function getObjectRoot($obj,&$exports,&$imports){
	if($obj['outer']){
		return getObjectRoot(getObjectByReference($obj['outer'],$exports,$imports),$exports,$imports);
	}else{
		return $obj;
	}
}

function getObjectByReference($value, &$exports, &$imports){
	if($value==0) return null;
	else if($value > 0) return $exports[$value-1];
	else return $imports[-$value-1];
}

function getUnrealFriendlyReference(&$names,$object){
	return $names[$object['classIdx']]['name']."'".$names[$object['packageIdx']]['name'] . ".".$names[$object['nameIdx']]['name'] . "'";
}

?>