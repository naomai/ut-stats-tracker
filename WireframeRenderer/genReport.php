<?php

// requires php-qb
require_once __DIR__ . "/t3dparserVersionWhatever.php";
require_once __DIR__ . "/threedeefunctions.inc.php";
require_once __DIR__ . "/../graphcommon.php";
require_once __DIR__ . "/../config.php";

$errhndFatalPage=false;

ini_set('memory_limit', '512M');

define('CACHEDIR', __DIR__ . "/cache2");
define('JSONCACHEDIR', __DIR__ . "/jsonpolys");
define('REPORTDIR', __DIR__ . "/mapreport");

$UTTDEBUG_CP=0;
$UTTDEBUG_CP_START=0;
$debug_checkpoint=false;

$cacheNameParams="";

if(isset($argc)){
	echo "Generating report for \"{$argv[1]}\"\r\n";
	$_GET['map']=$argv[1];
	if($argc == 4 && $argv[3]=='fhd') $_GET['fhd']=true;
	$cli=true;
}

if(!isset($cli)){
	die("Online rendering is not supported anymore");
}


if(!function_exists("name2id")){
	function name2id($sx){
		$s=strtolower($sx);
		$s=str_replace(
		array('$',  "!","@","{}v{}","(.)(.)", "(.y.)",  ")-(",")v(","|<","()","'//","'/","|_","/-]","|-|"),
		array("s" , "i","a","m",    " boobs "," boobs ","h"  ,"m"  ,"k" ,"o" ,"w",  "y" ,"l" ,"a"  ,"h"),
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



$polyfile=isset($_GET['map']) ? __DIR__ . "/../utmp/polys/".name2id($_GET['map']).".t3d" : "NOTAREALFILEFINHUIDFHDFOHFIDOJFDIDNFIFDUFD";
$jsonfile=JSONCACHEDIR . "/".name2id($_GET['map']).".json";
if(!file_exists($polyfile) && !file_exists($jsonfile)){
	if(isset($_GET['dling'])){
		echo("Downloading map layout!");
		echo("Wait a few seconds and\nrefresh the page...");
	}else{
		echo("Map layout not found");
	}
	
	exit;
}


$emptyCoord=array("X"=>0,"Y"=>0,"Z"=>0);
$emptyScaleVec=array("X"=>1,"Y"=>1,"Z"=>1);
$emptyRot=array("Pitch"=>0,"Yaw"=>0,"Roll"=>0);

utt_checkpoint("BeginParse");

if(file_exists($jsonfile)){
	$actors=json_decode(file_get_contents($jsonfile),true);
}else{
	$actors=d_parseT3D($polyfile)['Map'][0]['Actor'];
}
/*
if(file_exists($jsonfile)){
	$actors=json_decode(file_get_contents($jsonfile),true);
}else{
	$actorsCT=d_parseT3D($polyfile);
	if(!isset($actorsCT['Map'])){
		echo "There was a problem with loading the map...";
		exit;
	}
	
	$actors=&$actorsCT['Map'][0]['Actor'];
	
	unset($actorsCT);

	
	//$actors=parseT3D($polyfile)['Map'][0]['Actor'];
	
	$jsenc=json_encode($actors);
	if(!json_last_error()){ 
		file_put_contents($jsonfile,$jsenc);
	}else{
		eh_img_echo_col("JSONERROR: ".json_last_error_msg()."\r\n",15);
	}
}*/
utt_checkpoint("EndParse");

$brsh=getActorsByPropertyValue($actors, "CsgOper","CSG_Subtract");
if(!count($brsh)){ // all the brushes has been removed from map to protect it from editing
	echo "The map layout couldn't be displayed, because the level is protected from editing.";
	exit;
}
$bounds=array('tl'=>array("X"=>32768.0,"Y"=>32768.0,"Z"=>32768.0),'br'=>array("X"=>-32768.0,"Y"=>-32768.0,"Z"=>-32768.0));
$worldBounds=$bounds;

utt_checkpoint("BeginBoundaries");
$skyboxIsPartOfMap=false;
foreach($actors as $act){ // HOLY SHIT!
	// we're finding the level boundaries by iterating through all the actors' positions;
	$closeActs=null;
	$class=isset($act['properties']['Class'])?$act['properties']['Class']:"None";
	
	if($class=="PathNode" || $class=="PlayerStart" || $class=="JBPlayerStart" || isset($act['properties']['markedItem']) || 
		$class=="InventorySpot" || $class=="Mover" || isset($act['properties']['FootRegion']) || isset($act['properties']['Paths(0)'])) {
			
		if(!isset($act['properties']['Region']['iLeaf']) || $act['properties']['Region']['iLeaf']=="-1") continue; 
		// actors with iLeaf == -1 are outside the level (including the subtractive brushes)
	}else{
		continue;
	}
	
	if(isset($act['properties']['Region']['Zone']) && strpos($act['properties']['Region']['Zone'],"SkyZoneInfo")!==false){
		//eh_img_echo_col("skybox actor:".$act['properties']['Name']."\n",7);
		// if there are pathnode-like actors in skyzone, it indicates the skybox is also a playable part of map
		if(isset($act['properties']['Paths(0)'])) $skyboxIsPartOfMap=true; 
		else continue;
	}
	if(isset($act['properties']['CsgOper']) && $act['properties']['CsgOper']=="CSG_Add") continue;

	if(!isset($act['properties']['Location'])){
		eh_img_echo_col("stupid actor:".$act['properties']['Name']."\n",7);
	} else{
		$actVect=$act['properties']['Location']+$emptyCoord;
		$actVect['Z']=-$actVect['Z'];
		$bounds['tl']=vec3Op($actVect,$bounds['tl'],'min');
		$bounds['br']=vec3Op($actVect,$bounds['br'],'max');
	}
}
$bounds['tl']['Z']=-$bounds['tl']['Z'];
$bounds['br']['Z']=-$bounds['br']['Z'];

$mapSizeX=$bounds['br']['X']-$bounds['tl']['X'];
$mapSizeY=$bounds['br']['Y']-$bounds['tl']['Y'];
$mapSizeZ=$bounds['tl']['Z']-$bounds['br']['Z'];


utt_checkpoint("EndBoundaries");

utt_checkpoint("BeginDrawWorld");


$brushct=0;
$polyct=0;
$vertct=0;



$report=array('reportVersion'=>UTT_MAPREPORT_VER,'monstercount'=>0,'medboxcount'=>0,'mapsizeX'=>$mapSizeX,'mapsizeY'=>$mapSizeY,'mapsizeZ'=>$mapSizeZ,'brushcsgaddcount'=>0,'brushcsgsubcount'=>0,'moverscount'=>0,'lightWattage'=>0,'usedTextures'=>array());


foreach($actors as $act){
	
	if($act['properties']['Class']=="Brush" || $act['properties']['Class']=="Mover"){
		if(!isset($act['Brush'])) continue;
		if(!isset($act['Brush'][0]['PolyList'][0]['Polygon'])) continue;
		$polyz=$act['Brush'][0]['PolyList'][0]['Polygon'];
		
		if(isset($act['properties']['Region']['Zone']) && strpos($act['properties']['Region']['Zone'],"SkyZoneInfo")!==false && !$skyboxIsPartOfMap) continue;
		
		$brushOffs=isset($act['properties']['Location'])?$act['properties']['Location']+$emptyCoord:$emptyCoord;
		
		
		
		if(isset($act['properties']['CsgOper'])){
			$brushOp=$act['properties']['CsgOper'];
			if($brushOp=="CSG_Subtract"){
				$brushType=0;
				$report['brushcsgsubcount']++;
			}else if($brushOp=="CSG_Add"){
				$brushType=1;
				$report['brushcsgaddcount']++;
			}else if($brushOp=="CSG_Active"){
				$brushType=2;
				$report['brushcsgaddcount']++;
			}else{
				$col=0xA3A3A3;
				$brushType=3;
			}
		}else{
			$brushType=4;
		}
		
		if($act['properties']['Class']=="Mover"){
			$report['moverscount']++;
		}
		
		
		
		//$col = ($col >> 1) & 0x7F7F7F;
		$pivot=isset($act['properties']['PrePivot'])?$act['properties']['PrePivot']+$emptyCoord:$emptyCoord;

		
		$rotation=isset($act['properties']['Rotation'])?$act['properties']['Rotation']+$emptyRot:$emptyRot;
		
		/*$p=$projectionFunction(vec3Sum($pivot,$brushOffs));
		
		imagefilledrectangle($img,$p['X']-1,$p['Y']-1,$p['X']+1,$p['Y']+1,0xFF0000);
		*/

		
		foreach($polyz as $polyNum=>$pol){
			
			$normal=$pol['Normal'][0];
			
			


			$transformedVerts=array();
			$verts=array();
			$bprops=$act['properties'];
			
			
			

			if($brushType==0){
				$mainScaleVec=vec3ScaleMatrixToQBVec3(isset($bprops['MainScale']['Scale'])?$bprops['MainScale']['Scale']+$emptyScaleVec:$emptyScaleVec);
				$postScaleVec=vec3ScaleMatrixToQBVec3(isset($bprops['PostScale']['Scale'])?$bprops['PostScale']['Scale']+$emptyScaleVec:$emptyScaleVec);
				$tempScaleVec=vec3ScaleMatrixToQBVec3(isset($bprops['TempScale']['Scale'])?$bprops['TempScale']['Scale']+$emptyScaleVec:$emptyScaleVec);
				$rot=vec3rotToQBVec3($rotation);
				$piv=vec3ToQBVec3($pivot);
				$coffset=vec3ToQBVec3($brushOffs);
				foreach($pol['Vertex'] as $vertNum=>$vert){
					$verts[]=vec3ToQBVec3($vert+$emptyCoord);
				}
				UEVertTransform($verts,$mainScaleVec,$postScaleVec,$tempScaleVec,$rot,$piv,$coffset);
				
				foreach($verts as $v){
					$utVert=QBVec3Tovec3($v);
					$actVert=$utVert;
					$actVert['Z']=-$actVert['Z'];
					$worldBounds['tl']=vec3Op($actVert,$worldBounds['tl'],'min');
					$worldBounds['br']=vec3Op($actVert,$worldBounds['br'],'max');
			
				}
			}	
			if(isset($pol['properties']['Texture'])){
				if(isset($report['usedTextures'][$pol['properties']['Texture']])) $report['usedTextures'][$pol['properties']['Texture']]++;
				else $report['usedTextures'][$pol['properties']['Texture']]=1;
			}
			
			
			$polyct++;
			$vertct+=count($pol['Vertex']);
			
		}
		//$pivotCoords=$projectionFunction(vec3Sum($brushOffs,$pivot));
		//imagesetpixel($img,$pivotCoords['X'],$pivotCoords['Y'],0xFF0000);
		
		$brushct++;
	} 
}

$report['worldBounds']=$worldBounds;
$report['worldSizeX']=$worldBounds['br']['X']-$worldBounds['tl']['X'];
$report['worldSizeY']=$worldBounds['br']['Y']-$worldBounds['tl']['Y'];
$report['worldSizeZ']=$worldBounds['br']['Z']-$worldBounds['tl']['Z'];

/*
echo "Done ; $brushct brushes, $polyct polys, $vertct vertices\r\n";*/
utt_checkpoint("EndDrawWorld");
utt_checkpoint("BeginDrawObjects");


$displayedTags=array(); // to avoid displaying the same thing multiple times

$actorsGroupsNum=0;
$actorsGroupsFirstAct=array();


foreach($actors as $act){
	$class=strtolower($act['properties']['Class']);
	$classNC=$act['properties']['Class'];
	//if($class!="levelinfo" && isset($act['properties']['Region']['iLeaf']) && $act['properties']['Region']['iLeaf']==-1) continue;

	if(isset($report['actorsCount'][$class])) $report['actorsCount'][$class]++;
	else $report['actorsCount'][$class]=1;
	
	if(isset($act['properties']['Region']['Zone'])) $report['zones'][crc32($act['properties']['Region']['Zone'])]=$act['properties']['Region']['Zone'];
	

	if($class=="levelinfo"){
		$report['title']=isset($act['properties']['Title'])?$act['properties']['Title']:"";
		$report['author']=isset($act['properties']['Author'])?$act['properties']['Author']:"";
		
		$report['ipc']=isset($act['properties']['IdealPlayerCount'])?$act['properties']['IdealPlayerCount']:"";
		$report['entermsg']=isset($act['properties']['LevelEnterText'])?$act['properties']['LevelEnterText']:"";
	}else if($class=="flagbase"){ // CTF
		$loc=isset($act['properties']['Location'])?$act['properties']['Location']+$emptyCoord:$emptyCoord;
		
		$team=isset($act['properties']['Team'])?$act['properties']['Team']:0;
		if($team==1){
			$report['blueflag']=$loc;
		}else{
			$report['redflag']=$loc;
		}

	}else if(isset($act['properties']['FootRegion'])){
		
		if($class=="cow" || $class=="babycow" || $class=="nali" || $class=="nalipriest" // scriptedpawn
			|| $class=="horseflyswarm" || $class=="biterfishschool" || $class=="parentblob" // flockmasterpawn
			|| $class=="bird1" || $class=="biterfish" || $class=="bloblet" || $class=="horsefly" || $class=="nalirabbit" // flockpawn
			|| $class=="teamcannon" || $class=="miniguncannon" || $class=="fortstandard" // StationaryPawn
			) continue;
		
		
		$report['monstercount']++;
		
		$monName=(isset($act['properties']['MenuName'])?$act['properties']['MenuName']:$class);
		
		$mcx=$classNC;
		if(isset($report['monsterTypesCount'][$mcx][$monName])) $report['monsterTypesCount'][$mcx][$monName]++;
		else $report['monsterTypesCount'][$mcx][$monName]=1;
		
	}else if($class=="thingfactory" || $class=="creaturefactory"){
		if(!isset($act['properties']['prototype'])) continue;
		$cap=(isset($act['properties']['capacity'])?$act['properties']['capacity']:1);
		
		preg_match("/([^']*)'([^\.]*).([^']*)'/",$act['properties']['prototype'],$mat);
		$package=$mat[2];
		if($package=="UnrealShare") continue;

		
		if($cap <= 5000){
			$report['monstercount']+=$cap;
			$mcx=$mat[3];
			if(isset($report['monsterTypesCount'][$mcx]["(factory)"])) $report['monsterTypesCount'][$mcx]["(factory)"]+=$cap;
			else $report['monsterTypesCount'][$mcx]["(factory)"]=$cap;
		}
		
	}else if($class=="b_monsterspawner" || $class=="b_monsterloopspawner" || $class=="b_monsterwavespawner"){ // BBoyShare
	
		$tag=$act['properties']['Tag'];
		
		if(isset($displayedTags[$tag])) continue;
		
		if($class=="b_monsterwavespawner"){
			$thingType="<monster wave>";
		}else{
			if(isset($act['properties']['CreatureType'])){
				preg_match("/[^']*'[^\.]*.([^']*)'/",$act['properties']['CreatureType'],$mat);
				$thingType=$mat[1];
			}else{
				$thingType="Brute";
			}
		}
		$tagActz=getActorsByTag($actors,$tag);
		$capacity=0;
		foreach($tagActz as $acId){
			$capacity+=isset($act['properties']['SpawnNum'])?$act['properties']['SpawnNum']:10;
		}
		$monType=$thingType;
		
		if($capacity <= 5000){
			$report['monstercount']+=$capacity;
			$mcx=$monType;
			if(isset($report['monsterTypesCount'][$mcx]["(factory)"])) $report['monsterTypesCount'][$mcx]["(factory)"]+=$capacity;
			else $report['monsterTypesCount'][$mcx]["(factory)"]=$capacity;
		}
			


	}else if($class=="healthvial" || $class=="medbox" || $class=="healthpack"){
				
		$report['medboxcount']++;
		

	}else if($class=="light"||$class=="triggerlight"){ // default settings ~= 100W light bulb

		$brightness=isset($act['properties']['LightBrightness'])?$act['properties']['LightBrightness']:64;
		$radius=isset($act['properties']['LightRadius'])?$act['properties']['LightRadius']:64;
		
		$watts=round($brightness*1.5625 * log($radius,64));
		
		$h=isset($act['properties']['LightHue'])?$act['properties']['LightHue']:0;
		$s=255-(isset($act['properties']['LightSaturation'])?$act['properties']['LightSaturation']:255);
		$v=isset($act['properties']['LightBrightness'])?$act['properties']['LightBrightness']:64;
		
		
		$rgb=ColorHSLToRGB($h/255,$s/255,$v/255 * (255-$s/2)/255);
		$rgbV=clamp($rgb['r'],0,255)<<16 | clamp($rgb['g'],0,255)<<8 | clamp($rgb['b'],0,255);
		//echo "LIGHT H$h S$s V$v ".dechex($rgbV)."\r\n";
		$levelPalette[]=$rgbV;
		//imagefilledrectangle($img,count($levelPalette)*3,0,count($levelPalette)*3+2,2,$rgbV);
		$report['lightWattage']+=$watts;

	}


}
$report['levelPalette']=array_unique($levelPalette);
utt_checkpoint("EndDrawObjects");


file_put_contents(REPORTDIR."/".name2id($_GET['map']).".txt",json_encode($report));

/*
function imageCurvedLine($img,$x1,$y1,$x2,$y2,$curv,$color){
	$curvFactor=360/$curv;
	
	
	$lineCenterX=($x2+$x1) / 2;
	$lineCenterY=($y2+$y1) / 2;

	$aW=abs($x2-$x1);
	$aH=abs($y2-$y1);	
	
	$aL=pow(pow($aW,2)+pow($aH,2),0.5);
	
	
	$pointsangle=vec2Angle(array("X"=>$x1,"Y"=>$y1),array("X"=>$x2,"Y"=>$y2));
	
	$arcR=cos($pointsangle)*$aL;
	$arcCenterX=$lineCenterX-sin($pointsangle)*$arcR;
	$arcCenterY=$lineCenterY+cos($pointsangle)*$arcR;
	
	$width=$aW*$arcR;
	$height=$aH*$arcR;
	
	imageline($img,$lineCenterX,$lineCenterY,$arcCenterX,$arcCenterY,0xFFFF00);
	
	imageellipse($img,$arcCenterX,$arcCenterY,$width,$height,0xFFFF00);
	
	//imagearc($img,$arcCenterX,$arcCenterY,$aW,$aH,0,359,$color);
	
}*/

// NEW BEHAVIOR: RETURNS IDX NRS INSTEAD OF ACTOR REFERENCES!!
function getActorsByPropertyValue(&$actors,$propName,$propVal){
	$actres=array();
	foreach($actors as $actNum=>$act){ // we can't use &act, iterators don't support references
		if(is_numeric($act)) { $actNum=$act; $act=$GLOBALS['actors'][$act]; }
		if(isset($act['properties'][$propName]) && $act['properties'][$propName]==$propVal){
			//$actres[]=&$actors[$actNum];
			$actres[]=$actNum;
		}
	}
	$arr=SplFixedArray::fromArray($actres);
	return $arr;
}
function getActorsByTag(&$actors,$tag){
	return getActorsByPropertyValue($actors,"Tag",$tag);
}

function getSingleActorByTag(&$actors,$tag){
	$actres=array();
	foreach($actors as $actNum=>$act){
		if(is_numeric($act)) { $actNum=$act; $act=$GLOBALS['actors'][$act]; }
		if(isset($act['properties']['Tag']) && $act['properties']['Tag']==$tag){
			//$actres[]=&$act;
			return $actNum;
		}
	}
	return $act=null; // we can't return the null directly (reference!)
	//return $actres;
}

function getActorsByClass(&$actors,$class){
	return getActorsByPropertyValue($actors,"Class",$class);
}

function getActorsInRadius(&$actors,$coords,$rad){
	$actres=array();
	foreach($actors as $actNum=>$act){
		if(is_numeric($act)) { $actNum=$act; $act=$GLOBALS['actors'][$act]; }
		if(!isset($act['properties']['Location'])) continue;
		$aloc=$act['properties']['Location']+$GLOBALS['emptyCoord'];
		if(vec3Distance($aloc,$coords+$GLOBALS['emptyCoord'])<=$rad){
			$actres[]=$actNum;
		}
	}
	return $actres;
}

function getActorsInTheSameRegion(&$actors,&$refActor,$maxLeafDistance=0){
	$actres=array();
	$ileafRef=(isset($refActor['properties']['Region']['iLeaf'])?$refActor['properties']['Region']['iLeaf']:0);
	foreach($actors as $actNum=>$act){
		if(is_numeric($act)) { $actNum=$act; $act=$GLOBALS['actors'][$act]; }
		$ileafAct=(isset($act['properties']['Region']['iLeaf'])?$act['properties']['Region']['iLeaf']:0);
		if(abs($ileafRef-$ileafAct) <= $maxLeafDistance){
			$actres[]=$actNum;
		}
	}
	return $actres;
}

	

function isPolyOffscreen($vert){
	for($i=0;$i<count($vert); $i+=2){
		if($vert[$i] >=0 && $vert[$i]<$GLOBALS['SCREENX'] && $vert[$i+1] >=0 && $vert[$i+1]<$GLOBALS['SCREENY']) return false;
		//echo "VOFF:{$vert[$i]},{$vert[$i+1]}\n";
	}
	return true;
}

function ImageShadowatedjfdsglifgdlsljhgklsTTFText($img,$size,$rot,$x,$y,$col,$font,$text){
	$alpha=$col&0x7F000000;
	imagettftext($img,$size,$rot,$x+1,$y+1,$alpha,$font,$text);
	imagettftext($img,$size,$rot,$x,$y,$col,$font,$text);
	
}


function proj_isometric_30deg($vert){
	global $scale,$offsetX,$offsetY;
	$res['X']=($vert['X'] * 2 - $vert['Y'])*$scale+$offsetX;
	$res['Y']=($vert['Y']+ $vert['X'] /2 - $vert['Z']*2)*$scale+$offsetY;
	
	return $res;
}
function projav_isometric_30deg(){
	global $virtW,$virtH,$mapSizeX,$mapSizeY,$mapSizeZ,$virtOffX,$virtOffY,$dispOffset;
	$virtW=($mapSizeX*2+$mapSizeY);
	$virtH=($mapSizeY+$mapSizeX/2+$mapSizeZ);

	$virtOffX=($dispOffset['X']+$dispOffset['Y']/2);
	$virtOffY=($dispOffset['Y']+$dispOffset['X']/2+$dispOffset['Z']*2)/2;
}

function proj_orthographic($vert){
	global $scale,$offsetX,$offsetY;
	$res['X']=($vert['X'])*$scale+$offsetX;
	$res['Y']=($vert['Y'])*$scale+$offsetY;
	
	return $res;
}
function projav_orthographic(){
	global $virtW,$virtH,$mapSizeX,$mapSizeY,$mapSizeZ,$virtOffX,$virtOffY,$dispOffset;
	$virtW=($mapSizeX);
	$virtH=($mapSizeY);

	$virtOffX=($dispOffset['X']);
	$virtOffY=($dispOffset['Y']);
}

/*
function proj_perspective($vert){
	global $scale,$offsetX,$offsetY,$dispOffset;
	
	$zScale=$dispOffset['Z'];
	
	$res['X']=($vert['X'] * 2 - $vert['Y'])*$scale+$offsetX;
	$res['Y']=($vert['Y']+ $vert['X'] /2 - $vert['Z']*2)*$scale+$offsetY;
	
	return $res;
}
function projav_perspective(){
	global $virtW,$virtH,$mapSizeX,$mapSizeY,$mapSizeZ,$virtOffX,$virtOffY,$dispOffset;
	$virtW=($mapSizeX*2+$mapSizeY);
	$virtH=($mapSizeY+$mapSizeX/2+$mapSizeZ);

	$virtOffX=($dispOffset['X']+$dispOffset['Y']/2);
	$virtOffY=($dispOffset['Y']+$dispOffset['X']/2+$dispOffset['Z']*2)/2;
}*/


function imgfinish($img){
	global $report;
	$GLOBALS['watermarkFunction']($img);
	$mapnx=(isset($report['title']) && $report['title']?$report['title']:$_GET['map']);

	imagettftextlcd($img,12,0,4,17,0xFFFFFF,__DIR__ . "/../../segoeuib.ttf",$mapnx);
	if($report['author']) imagettftextlcd($img,9,0,4,30,0xFFFFFF,__DIR__ . "/../../segoeuib.ttf"," by {$report['author']}");

	ob_end_clean();
	header("Content-type: image/png");
	imagepng($img);
}

function utt_watermark($img){
	$wmimg=imagecreatefrompng(__DIR__."/../uttwatermarkBIGA.png");
	$iw=imagesx($img);
	$ih=imagesy($img);
	$ww=imagesx($wmimg);
	$wh=imagesy($wmimg);
	imagecopy ($img,$wmimg,$iw-$ww+20,$ih-$wh,0,0,$ww,$wh);
	
}

function inlineimage($dc,$name=""){
	echo "<img src='".image2url($dc)."'>";
}

function image2url($dc){
	ob_start();
	imagepng($dc);
	$imgd=base64_encode(ob_get_clean ());
	return "data:image/png;base64,$imgd";
}

function utt_checkpoint($name=""){
	if(!$GLOBALS['debug_checkpoint']) return;
	$timeStop=microtime(true);
	if($GLOBALS['UTTDEBUG_CP']!=0){
		echo "<span class='debugcp'16eckpoint $name: ".round(($timeStop-$GLOBALS['UTTDEBUG_CP_START'])*1000)."ms (+".round(($timeStop-$GLOBALS['UTTDEBUG_CP'])*1000)."ms)</span8";
	}else{
		$GLOBALS['UTTDEBUG_CP_START']=microtime(true);
	}
	$GLOBALS['UTTDEBUG_CP']=microtime(true);
}

//http://stackoverflow.com/a/3642787
function ColorHSLToRGB($h, $s, $l){
	
	$r = $l;
	$g = $l;
	$b = $l;
	$v = ($l <= 0.5) ? ($l * (1.0 + $s)) : ($l + $s - $l * $s);
	if ($v > 0){
		$m;
		$sv;
		$sextant;
		$fract;
		$vsf;
		$mid1;
		$mid2;
		
		$m = $l + $l - $v;
		$sv = ($v - $m ) / $v;
		$h *= 6.0;
		$sextant = floor($h);
		$fract = $h - $sextant;
		$vsf = $v * $sv * $fract;
		$mid1 = $m + $vsf;
		$mid2 = $v - $vsf;
		
		switch ($sextant)
		{
			case 0:
			$r = $v;
			$g = $mid1;
			$b = $m;
			break;
			case 1:
			$r = $mid2;
			$g = $v;
			$b = $m;
			break;
			case 2:
			$r = $m;
			$g = $v;
			$b = $mid1;
			break;
			case 3:
			$r = $m;
			$g = $mid2;
			$b = $v;
			break;
			case 4:
			$r = $mid1;
			$g = $m;
			$b = $v;
			break;
			case 5:
			$r = $v;
			$g = $m;
			$b = $mid2;
			break;
		}
	}
	return array('r' => round($r * 255.0), 'g' => round($g * 255.0), 'b' => round($b * 255.0));
}

function clamp($v,$min,$max){
	return max($min,min($max,$v));	
}

?>