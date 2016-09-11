<?php
set_time_limit(2);
$scriptStart = microtime(true);

require_once "uestructures.php";
header("Content-type: text/plain");
$packageFile = findPackageDependencies("F:\\games\\UnrealTournament\\Cache\\3084318940E0831AB25746A780DFB9F0.uxx","rb");
print_r($packageFile);


echo "Script took: " . round((microtime(true)-$scriptStart)*1000,2)." ms";

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