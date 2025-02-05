<?php
global $utmpIntalled;

class UTT_MapInfo{
	public $reportVersion=0;
	public $size, $worldSize;
	public $enterMessage="", $title="", $author="";
	public $idealPlayerCount;
	public $brushCountAdd=0, $brushCountSub=0, $zoneCount=0, $classCount=0, $textureCount=0;
	public $lightWattage=0;
	public $palette;
	public $rawReport;
	public $downloadUrl;
	public $mapName;
	
	public $polysLoc, $jsonPolysLoc;
	public $screenshotLoc, $layoutsLoc;
	
	public $hasPolys=false, $hasScreenshot=false, $hasLayout=false, $hasReport=false;
	public $jobsFlags;
	
	protected $mapId;
	protected $internalId = 0;
	protected $pdo;
	protected $dbMapInfo;
	
	public function __construct($mapName, $pdo){
		global $utmpLoc, $rendererLoc;
		$this->mapName = $mapName;
		$this->pdo = $pdo;
		
		$mapId=name2id($mapName);
		$this->mapId=$mapId;
		$this->internalId = abs(crc32(strtolower($mapName)));
		$reportFile = "$utmpLoc/mapreport/{$mapId}.txt";
		
		$this->hasReport = file_exists($reportFile);
		if($this->hasReport){
			$this->loadMapReport($reportFile);
		}
		$this->dbMapInfo = $this->fetchDBMapInfo($this->pdo);	
		$this->updateDBMapInfo();	

		$this->updateScreenshotInfo();		
	}
	
	protected function loadMapReport($reportLoc){
		$mapReport=json_decode(file_get_contents($reportLoc),true);
		
		if(isset($mapReport['author']) && $mapReport['author'])
			$this->author = $mapReport['author'];
		if(isset($mapReport['title']) && $mapReport['title'])
			$this->title = $mapReport['title'];
		if(isset($mapReport['ipc']) && $mapReport['ipc'])
			$this->idealPlayerCount = $mapReport['ipc'];
		if(isset($mapReport['entermsg']) && $mapReport['entermsg'])
			$this->enterMessage = $mapReport['entermsg'];
		
		$this->size = array();
		
		$this->size['x']     = round(isset($mapReport['mapsizeX'])      ? $mapReport['mapsizeX']:0);
		$this->size['y']     = round(isset($mapReport['mapsizeY'])      ? $mapReport['mapsizeY']:0);
		$this->size['z']     = round(isset($mapReport['mapsizeZ'])      ? $mapReport['mapsizeZ']:0);
		$this->brushCountAdd =  isset($mapReport['brushcsgaddcount'])   ? $mapReport['brushcsgaddcount']:0;
		$this->brushCountSub =  isset($mapReport['brushcsgsubcount'])   ? $mapReport['brushcsgsubcount']:0;
		$this->zoneCount     =  isset($mapReport['zone_count'])              ? count($mapReport['zone_count']):0;
		$this->lightWattage  = round(isset($mapReport['lightWattage'])  ? $mapReport['lightWattage']:0);
		$this->textureCount  = round(isset($mapReport['usedTextures'])  ? count($mapReport['usedTextures']):0);
		$this->classCount    = round(isset($mapReport['actorsCount'])   ? count($mapReport['actorsCount']):0);
		$this->reportVersion = round(isset($mapReport['reportVersion']) ? $mapReport['reportVersion']:0);
		$this->palette       =  isset($mapReport['levelPalette'])       ? array_unique($mapReport['levelPalette']):array();
		
		if($this->reportVersion>=54 && $mapReport['worldSizeX']>0){
			$worldSize = array();
			$this->worldSize['x'] = round($mapReport['worldSizeX']);
			$this->worldSize['y'] = round($mapReport['worldSizeY']);
			$this->worldSize['z'] = round($mapReport['worldSizeZ']);
		}else{
			$this->worldSize = $this->size;
		}
		$this->rawReport = $mapReport;
	}
	protected function fetchDBMapInfo(){
		
		$statement = $this->pdo->prepare("SELECT * FROM maps WHERE id=:mapId");
		$statement->bindParam(":mapId", $this->internalId, PDO::PARAM_INT);
		$statement->execute();
		$mapInfo = $statement->fetch(PDO::FETCH_ASSOC);
		unset($statement);
		
		

		if(isset($mapInfo['download_url']) && $mapInfo['download_url']){
			
			$this->downloadUrl=$mapInfo['download_url'];
		}else{
			$isFrontend = true;
			require_once "ut_map_lookup.php";
			$this->downloadUrl=findMapUrl($this->mapName);
		}
		if($this->downloadUrl=="") $this->downloadUrl="NN";
		
		return $mapInfo;
	}
	
	protected function updateDBMapInfo(){
		if(!$this->hasReport)
			return;
		
		if($this->dbMapInfo==false){
			$statement = $this->getStatementForMapInfoInsert();
		}else if(!$this->isDBMapInfoValid()){
			$statement = $this->getStatementForMapInfoUpdate();
		}
		
		if(isset($statement)){
			$this->bindParamsForMapInfoUpdate($statement);
			$statement->execute();
		}

		
	}
	
	protected function updateScreenshotInfo(){
		global $utmpLoc,$rendererLoc;
		
		$sshotLoc = "$utmpLoc/sshots/{$this->mapId}.jpg";
		if(file_exists($sshotLoc)){
			$this->hasScreenshot = filesize($sshotLoc) > 9;
			$this->screenshotLoc = $sshotLoc;
		}
		
		$polysLoc = "$utmpLoc/polys/{$this->mapId}.t3d";
		if(file_exists($polysLoc)){
			$this->hasPolys = true;
			$this->polysLoc = $polysLoc;
		}
		
		$jsonPolysLoc = "$rendererLoc/jsonpolys/{$this->mapId}.json";
		if(file_exists($jsonPolysLoc)){
			$this->hasPolys = true;
			$this->jsonPolysLoc = $jsonPolysLoc;
		}
		
		$layoutTypes = array("isometric_30deg","orthographic");
		
		foreach($layoutTypes as $type){
			$layoutFile = "$rendererLoc/cache3/{$this->mapId}_{$type}.png";
			if(file_exists($layoutFile)){
				$this->layoutsLoc[$type] = $layoutFile;
				$this->hasLayout = true;
			}
		}
		// schedule jobs
		$this->jobsFlags=0;
		if($this->hasPolys && !$this->hasLayout){
			$this->jobsFlags |= LAYOUTGEN_JOB_REDNERLAYOUT;
		}
		
		if($this->hasPolys && $this->hasReport && $this->reportVersion < UTT_MAPREPORT_VER){
			$this->jobsFlags |= LAYOUTGEN_JOB_GENREPORT;
		}
		
		if($this->screenshotLoc == null){
			$this->jobsFlags |= LAYOUTGEN_JOB_ALL;
		}
		if($this->jobsFlags){
			$this->scheduleMapJob($this->jobsFlags);
		}
		
	}
	
	protected function scheduleMapJob($jobType){
		global $utmpInstalled;
			if($utmpInstalled){
			$statement = $this->pdo->prepare("INSERT INTO mapdownloadqueue SET `mapname`=:mapName,`jobType`=:jobType");
			echo "ScheduleMapJob?mapName=".urlencode($this->mapName)."&jobType=$jobType";
			$statement->bindParam(":mapName", $this->mapName);
			$statement->bindParam(":jobType", $jobType);
			$statement->execute();
		}
	}
	
	protected function getStatementForMapInfoInsert(){
		return $this->pdo->prepare(
			"INSERT INTO mapinfo SET 
			   mapID=:mid,mapname=:mapname,
			   reportVersion=:reportVer,author=:authr,download_url=:url,
			   bound_size_x=:sizex,bound_size_y=:sizey,bound_size_z=:sizez,
			   brush_count_additive=:bca,brush_count_subtractive=:bcs,
			   zone_count=:zones,light_wattage=:lw,texture_count=:textures,class_count=:classes ");
	}
	protected function getStatementForMapInfoUpdate(){
		return $this->pdo->prepare("
			UPDATE mapinfo SET 
			   reportVersion=:reportVer,author=:authr,download_url=:url,
			   bound_size_x=:sizex,bound_size_y=:sizey,bound_size_z=:sizez,
			   brush_count_additive=:bca,brush_count_subtractive=:bcs,
			   zone_count=:zones,light_wattage=:lw,texture_count=:textures,class_count=:classes 
			WHERE mapid=:mid; --:mapname");
	}
	
	protected function bindParamsForMapInfoUpdate($statement){
		$statement->bindParam(":mid",           $this->internalId,     PDO::PARAM_INT);
		$statement->bindParam(":mapname",       $this->mapName         );
		$statement->bindParam(":authr",         $this->author          );
		$statement->bindParam(":url",           $this->downloadUrl     );
		$statement->bindParam(":reportVer",     $this->reportVersion,  PDO::PARAM_INT);
		$statement->bindParam(":sizex",         $this->size['x'],      PDO::PARAM_INT);
		$statement->bindParam(":sizey",         $this->size['y'],      PDO::PARAM_INT);
		$statement->bindParam(":sizez",         $this->size['z'],      PDO::PARAM_INT);
		$statement->bindParam(":bca",           $this->brushCountAdd,  PDO::PARAM_INT);
		$statement->bindParam(":bcs",           $this->brushCountSub,  PDO::PARAM_INT);
		$statement->bindParam(":zones",         $this->zoneCount,      PDO::PARAM_INT);
		$statement->bindParam(":lw",            $this->lightWattage,   PDO::PARAM_INT);
		$statement->bindParam(":textures",      $this->textureCount,   PDO::PARAM_INT);
		$statement->bindParam(":classes",       $this->classCount,     PDO::PARAM_INT);
	}
	
	protected function isDBMapInfoValid(){
		return 	$this->dbMapInfo['download_url'] == $this->downloadUrl && 
				$this->dbMapInfo['reportVersion'] == $this->reportVersion && 
				$this->dbMapInfo['bound_size_x'] == $this->size['x'] && 
				$this->dbMapInfo['brush_count_additive'] == $this->brushCountAdd && 
				$this->dbMapInfo['author'] == $this->author && 
				$this->dbMapInfo['zone_count'] == $this->zoneCount;
	}
	
	public function findSimilarMaps(){
		$sql = "SELECT * FROM mapinfo WHERE 
			bound_size_x <> 0 AND (
			bound_size_x BETWEEN :sizeXF AND :sizeXC OR
			bound_size_y BETWEEN :sizeYF AND :sizeYC OR
			bound_size_z BETWEEN :sizeZF AND :sizeZC OR
			brush_count_additive BETWEEN :brushCSGADDF AND :brushCSGADDC OR
			brush_count_subtractive BETWEEN :brushCSGSUBF AND :brushCSGSUBC OR
			light_wattage BETWEEN :lightwattageF AND :lightwattageC";
		if($this->reportVersion>=53){
			$sql .= " OR
				texture_count BETWEEN :numTexturesF AND :numTexturesC OR
				class_count BETWEEN :numClassesF AND :numClassesC";
		}
		$sql .= ") AND mapid <> :mapId";
		$statement = $this->pdo->prepare($sql);
		$statement->bindValue(":sizeXF",floor($this->size['x']*0.95),PDO::PARAM_INT);
		$statement->bindValue(":sizeYF",floor($this->size['y']*0.95),PDO::PARAM_INT);
		$statement->bindValue(":sizeZF",floor($this->size['z']*0.95),PDO::PARAM_INT);
		$statement->bindValue(":brushCSGADDF",floor($this->brushCountAdd*0.95),PDO::PARAM_INT);
		$statement->bindValue(":brushCSGSUBF",floor($this->brushCountSub*0.95),PDO::PARAM_INT);
		$statement->bindValue(":lightwattageF",floor($this->lightWattage*0.95),PDO::PARAM_INT);
		
		$statement->bindValue(":sizeXC",ceil($this->size['x']*1.1),PDO::PARAM_INT);
		$statement->bindValue(":sizeYC",ceil($this->size['y']*1.1),PDO::PARAM_INT);
		$statement->bindValue(":sizeZC",ceil($this->size['z']*1.1),PDO::PARAM_INT);
		$statement->bindValue(":brushCSGADDC",ceil($this->brushCountAdd*1.1),PDO::PARAM_INT);
		$statement->bindValue(":brushCSGSUBC",ceil($this->brushCountSub*1.1),PDO::PARAM_INT);
		$statement->bindValue(":lightwattageC",ceil($this->lightWattage*1.1),PDO::PARAM_INT);
		
		$statement->bindParam(":mapId",$this->internalId,PDO::PARAM_INT);
		
		if($this->reportVersion>=53){
			$statement->bindValue(":numTexturesF",floor($this->textureCount*0.95),PDO::PARAM_INT);
			$statement->bindValue(":numClassesF",floor($this->classCount*0.95),PDO::PARAM_INT);
			$statement->bindValue(":numTexturesC",ceil($this->textureCount*1.1),PDO::PARAM_INT);
			$statement->bindValue(":numClassesC",ceil($this->classCount*1.1),PDO::PARAM_INT);
		}
		$statement->execute();
		$simMaps = $statement->fetchAll(PDO::FETCH_ASSOC);
		$mapSize = $this->size;
		$result = array();
		foreach($simMaps as $mapX){
			$similarity=0; // [0-8]
			$similaritySecond=0; // [0-3]
			
			// '14-12-17 it's symmetrical now!
			if($mapSize['x']    !=0 && ($diff=(abs($mapX['bound_size_x'] - $mapSize['x'])         /($mapX['bound_size_x']+$mapSize['x'])*2))  	       < 0.5) {$diff=1-pow($diff-1,2);$similarity+=(1-$diff)*2.5;              }
			if($mapSize['y']    !=0 && ($diff=(abs($mapX['bound_size_y'] - $mapSize['y'])         /($mapX['bound_size_y']+$mapSize['y'])*2)) 	       < 0.5) {$diff=1-pow($diff-1,2);$similarity+=(1-$diff)*2.5;              }
			if($mapSize['z']    !=0 && ($diff=(abs($mapX['bound_size_z'] - $mapSize['z'])         /($mapX['bound_size_z']+$mapSize['z'])*2))          < 0.5) {$diff=1-pow($diff-1,2);$similarity+=(1-$diff)*1.8;              }
			if($this->brushCountAdd      > 0 && ($diff=(abs($mapX['brush_count_additive']  - $this->brushCountAdd) / ($mapX['brush_count_additive']  + $this->brushCountAdd)*2)) < 0.5) {$diff=1-pow($diff-1,2);$similaritySecond+=(1-$diff)*1.0;        }
			if($this->brushCountSub      > 0 && ($diff=(abs($mapX['brush_count_subtractive']  - $this->brushCountSub) / ($mapX['brush_count_subtractive']  + $this->brushCountSub)*2)) < 0.5) {$diff=1-pow($diff-1,4);$similaritySecond+=(1-$diff)*1.3;        }
			if($this->zoneCount          > 0 && ($diff=(abs($mapX['zone_count']        - $this->zoneCount)     / ($mapX['zone_count']        + $this->zoneCount    )*2)) < 0.5) {$diff=1-pow($diff-1,2);$similaritySecond+=(1-$diff)*0.7;        }
			if($this->lightWattage       > 0 && ($diff=(abs($mapX['light_wattage'] - $this->lightWattage)  / ($mapX['light_wattage'] + $this->lightWattage )*2)) < 0.5) {$diff=1-pow($diff-1,2);$similarity+=(1-$diff)*1.2;              }
			if($this->textureCount       > 0 && ($diff=(abs($mapX['texture_count']  - $this->textureCount)  / ($mapX['texture_count']  + $this->textureCount )*2)) < 0.5) {$diff=1-pow($diff-1,4);$similaritySecond+=(1-$diff)*1.7;        }
			if($this->classCount         > 0 && ($diff=(abs($mapX['class_count']   - $this->classCount)    / ($mapX['class_count']   + $this->classCount   )*2)) < 0.5) {$diff=1-pow($diff-1,2);$similaritySecond+=(1-$diff)*1.3;        }
			
			$similarityPercent=($similarity+$similaritySecond)*0.07142857142857143; // VERY ADVANCED MAGIC, DON'T!! ask.
			
			$similarityPercent=1-pow($similarityPercent-1,2);
			
			if(/*$mapX['reportVersion']>=$reportVer && */$similarityPercent>0.94 /*&& (($similarity>6 && $similaritySecond>3) || ($similarity>5 && $similaritySecond>4))*/) {
				$mapX['similarityPercent'] = $similarityPercent;
				$result[] = $mapX;
			}
		}
		return $result;
	}
}

