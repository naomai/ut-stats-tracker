<?php
require_once "../appConfig.php";

echo "<h1>UTTWEB Setup</h1>";
try{
	/* SERVER CHECK */
	echo "Checking server configuration...<br>";
	
	// PHP Version
	list($phpMajor, $phpMinor) = explode(".",  phpversion());
	if(($phpMajor == 5 && $phpMinor < 3) || $phpMajor < 5){
		throw new Exception("Required PHP version: 5.3 or later, yours: ".phpversion());
	}
	
	// PDO
	if(!class_exists("PDO")){
		throw new Exception("Missing PDO extension. Add in php.ini");
	}
	$pdoDrivers = PDO::getAvailableDrivers();
	if(!in_array("mysql", $pdoDrivers)){
		throw new Exception("Missing PDO MySQL driver. Uncomment in php.ini");
	}
	
	// Extensions
	if(!extension_loaded("mbstring")){
		throw new Exception("Missing mbstring extension. Add in php.ini");
	}
	if(!extension_loaded("json")){
		throw new Exception("Missing json extension. Add in php.ini");
	}
	
	$hasCurl = extension_loaded("curl");
	$hasGD = extension_loaded("gd");
	
	$urlFopen = ini_get('allow_url_fopen');
	if(!$urlFopen){
		echo "Warning: allow_url_fopen is not enabled, some checks will be skipped.<br>";
	}
	
	/* CONFIG FILE */
	echo "Checking config file...<br>";
	if(!$I_Modified_The_Database_Config){
		throw new Exception("You need to configure the database first in appConfig.php.");
	}
	
	if($urlFopen){
		// check if app url is correct
		$randomFileName = "verify".rand(0,999999).".tmp";
		$touchSuccess = file_put_contents(__DIR__ . "/$randomFileName","EMPTY");
		if(!$touchSuccess){
			throw new Exception("Add write permissions for Installer directory.");
		}
		if(file_get_contents($appUrl . "/Installer/$randomFileName")!="EMPTY"){
			throw new Exception("Invalid App location URL in appConfig.php");
		}
		unlink(__DIR__ . "/$randomFileName");

		// assets
		if(strpos($assetsPath, "//")===0) $assetsPath = $appUrlProto.":".$assetsPath;
		if(file_get_contents($assetsPath . "/VERY_IMPORTANT_DONT_DELETE")!="for app installer"){
			throw new Exception("Invalid AssetsPath URL in appConfig.php");
		}
		if(strpos($assetsGenericPath, "//")===0) $assetsGenericPath = $appUrlProto.":".$assetsGenericPath;
		if(file_get_contents($assetsGenericPath . "/VERY_IMPORTANT_DONT_DELETE")!="for app installer"){
			throw new Exception("Invalid Global AssetsPath URL in appConfig.php");
		}
		
	}
	
	
	/* DATABASE */
	echo "Checking database...<br>";
	try{
		$sqlconn=new PDO("mysql:host=$statdb_host;dbname=$statdb_db;charset=utf8",$statdb_user,$statdb_pass);
		$sqlconn->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);
	}catch(Exception $e){
		throw new Exception("Exception thrown by PDO: " . print_r($e));
	}
	require_once __DIR__ . "/dbTables.php";
	
	
	$tablesStat = $sqlconn->query("SHOW TABLES");
	$tables = $tablesStat->fetchAll();
	$tablesList=array();
	foreach($tables as $tableInfo){
		$tablesList[] = reset($tableInfo);
	}
	colAsKey($tables,"Tables_in_".$statdb_db);
	
	$createPerm = true;
	
	foreach($appTables as $ref=>$tableName){
		if(!in_array($tableName,$tablesList)){
			
			
			if($createPerm){
				echo "Creating missing DB table: ";
				echo "$tableName (as $ref)<br>\r\n";
				
				if(!isset($dbTables[$tableName])){
					throw new Exception("No definition for missing table $tableName. Add in Installer/dbTables.php");
				}
				$sqlconn->exec($dbTables[$tableName]);
				
				$error = $sqlconn->errorInfo();
				
				if($error[0]=="42000"){
					$createPerm = false;
					echo "ERROR: Installer has no CREATE permission granted. You need to manually execute the following statements: <br>\n";
				}else if($error[0]!=="00000") {
					throw new Exception("Database error: ({$error[0]}) ". $error[2]);
				}
			}
			if(!$createPerm){
				echo "<pre>".$dbTables[$tableName].";</pre><br>\r\n";
			}
			
		}
		
	}
	if(!$createPerm) exit;

	
}catch(Exception $e){
	echo "ERROR: ".$e->getMessage();
	exit;
}
echo "Everything seems to be OK.";

function colAsKey(&$ar,$col){
	$newar=array();
	foreach($ar as $kn=>$xd){
		$newar[$xd[$col]]=&$xd;
		unset($xd);
		unset($ar[$kn]);
	}
	$ar=$newar;
}

?>