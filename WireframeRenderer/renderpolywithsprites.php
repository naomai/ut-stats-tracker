<?php
require_once __DIR__ . "/t3dparserVersionWhatever.php";
require_once __DIR__ . "/threedeefunctions.inc.php";
require_once __DIR__ . "/../graphcommon.php";
require_once __DIR__ . "/../config.php";
require_once __DIR__ . "/RendererConfig.php";
require_once __DIR__ . "/../N14Inc/ErrorHandler.php";
require_once N14CORE_LOCATION . "/GDWrapper.php";
require_once N14CORE_LOCATION . "/GDWrapper/RichText.php";
require_once N14CORE_LOCATION . "/GDWrapper/NonOverlappingText.php";
require_once __DIR__ . "/GDWSpriteBlitter.php";
require_once __DIR__ . "/colorschemes.php";

use N14\GDWrapper as GDW;

$errhndFatalPage=false;

ini_set('memory_limit', '512M');


define('UTT_RENDERER_REV', 100);

$UTTDEBUG_CP=0;
$UTTDEBUG_CP_START=0;




$cacheNameParams="";



$cli=PHP_SAPI == 'cli';
if($cli){
	if($argc==2 && $argv[1][0]=="?"){
		parse_str(substr($argv[1],1),$_GET);
	}else{
		$_GET['map']=$argv[1];
		$_GET['projmode']=$argv[2];
		if($argc == 4 && $argv[3]=='fhd') $_GET['fhd']=true;
	}
	echo uttdateFmt(time(),false).": Rendering with sprite powers \"{$_GET['map']}\".{$_GET['projmode']}\r\n";
	error_reporting(E_ALL);
}



if(isset($_GET['colorScheme']) && isset($colorSchemes[$_GET['colorScheme']])){
	$scheme = $colorSchemes[$_GET['colorScheme']];
}else{
	$scheme = $colorSchemes[$defaultColorScheme];
}


if(isset($_GET['projmode'])){
	if(isset($renderModes[$_GET['projmode']])){
		$pmode=$renderModes[$_GET['projmode']];
	}else{
		$img=imagecreatetruecolor(320,240);
		imagefill($img,0,0,$scheme['background']);
		imagettftextlcd($img,11,0,4,40,$scheme['fatalError'],$fontsLoc ."/kidkosmic.ttf","unknown projection mode\r\n\"{$_GET['projmode']}\"!!");
		$imageFinishFunction($img);
		imagedestroy($img);
		exit;
	}
}else{
	$pmode = $defaultProjectionMode;
}
$projectionFunction="proj_$pmode"; // must be callable!
$projectionAdjustVirtFunction="projav_$pmode";



$cacheNameParams=$pmode;


if(isset($_GET['fhd'])){
	$SCREENX = $imageSizeBigX;
	$SCREENY = $imageSizeBigY;
	$cacheNameParams.="-fhd";
}else{
	$SCREENX = $imageSizeNormalX;
	$SCREENY = $imageSizeNormalY;
}

if(isset($_GET['colorScheme']) && isset($colorSchemes[$_GET['colorScheme']])){
	$cacheNameParams.="-".$_GET['colorScheme'];
}

if(!function_exists("name2id")){
	function name2id($sx){
		$s=strtolower($sx);
		$s=str_replace(
			array('$',  /*"!",*/"@","{}v{}","(.)(.)", "(.y.)",  ")-(",")v(","|<","()","'//","'/","|_|","|_","/-]","|-|"),
			array("s" , /*"i",*/"a","m",    " boobs "," boobs ","h"  ,"m"  ,"k" ,"o" ,"w",  "y" ,"u"  ,"l" ,"a"  ,"h"),
		$s);
		$res=substr(str_replace(" ","-",trim(preg_replace("/[^a-z0-9]+/"," ",$s))),0,30);
		if(strlen($res)<2){
			$res=name2idLITERAL($sx);
		}
		return $res;
	}
	
	function name2idLITERAL($s){
		$s=str_replace(
			array('$',       '#',     '!','.....',         '.',  '+',     '~',      '}:',   '|',             '"',    "&",    "%",        "*"),
			array(' dollar ',' hash ','a',' lots of dots ','dot',' plus ',' tilde ',' cow ',' vertical bar ',"quote"," and "," percent "," star "),
		$s);

		return substr(str_replace(" ","-",trim(preg_replace("/[^a-z0-9]+/"," ",$s))),0,30);
		
	}
}
$cacheName = CACHEDIR."/".name2id($_GET['map'])."_{$cacheNameParams}.png";

if(file_exists($cacheName) && !isset($_GET['redraw'])){
	
	if(!$cli){
		header("Content-type: image/png");
	
		echo file_get_contents($cacheName);
	}else{
		echo "Already rendered.\r\n";
	}
	exit;
}
/*
if(!isset($cli)){
	die("Online rendering is not supported anymore");
}*/


$polyfile=isset($_GET['map']) ? __DIR__ . "/../utmp/polys/".name2id($_GET['map']).".t3d" : "NOTAREALFILEFINHUIDFHDFOHFIDOJFDIDNFIFDUFD";
$jsonfile=JSONCACHEDIR . "/".name2id($_GET['map']).".json";

if(!file_exists($polyfile) && !file_exists($jsonfile) && !isset($cli)){
	$img=imagecreatetruecolor(320,240);
	imagefill($img,0,0,$scheme['background']);
	if(isset($_GET['dling']) || !isset($cli)){
		imagettftextlcd($img,11,0,4,40,$scheme['fatalError'],$fontsLoc ."/kidkosmic.ttf","Generating map layout!");
		imagettftext($img,8,0,4,60,$scheme['fatalError'],$fontsLoc ."/kidkosmic.ttf","Wait a few seconds and\nrefresh the page...");
	}else{
		imagettftextlcd($img,12,0,4,40,$scheme['fatalError'],$fontsLoc ."/kidkosmic.ttf","Map layout not found");
	}
	$imageFinishFunction($img);
	imagedestroy($img);
	exit;
}


$emptyCoord=array("X"=>0,"Y"=>0,"Z"=>0);
$emptyScaleVec=array("X"=>1,"Y"=>1,"Z"=>1);
$emptyRot=array("Pitch"=>0,"Yaw"=>0,"Roll"=>0);

$image = new GDW\Image($SCREENX, $SCREENY);
//$image->setComposer(new GDW\Composers\TiledComposer($image)); // testing some new stuff from gdw

$worldLayerId = $image->newLayer();
$linesLayerId = $image->newLayer();
$spritesLayerId = $image->newLayer();
$labelsLayerId = $image->newLayer();
$importantSpritesLayerId = $image->newLayer();
$overlayLayerId = $image->newLayer();

$bgLayer = $image->getLayerById(0);
$worldLayer = $image->getLayerById($worldLayerId);
$linesLayer = $image->getLayerById($linesLayerId);
$spritesLayer = $image->getLayerById($spritesLayerId);
$labelsLayer = $image->getLayerById($labelsLayerId);
$importantSpritesLayer = $image->getLayerById($importantSpritesLayerId);
$overlayLayer = $image->getLayerById($overlayLayerId);

$worldLayer->name = "World";
$linesLayer->name = "Lines";
$spritesLayer->name = "sprites";
$importantSpritesLayer->name = "importantSpritesLayer";
$overlayLayer->name = "text";

$overlayGD = $overlayLayer->getGDHandle();
$linesGD = $linesLayer->getGDHandle();
$spritesGD = $spritesLayer->getGDHandle();
$importantSpritesGD = $importantSpritesLayer->getGDHandle();
$worldGD = $worldLayer->getGDHandle(); // it's faster to do regular imagepolygon instead of bloated gdwrapper 

$overlayLayer->paint->alphaBlend = true;
$importantSpritesLayer->paint->alphaBlend = true;
$spritesLayer->paint->alphaBlend = true;
$labelsLayer->paint->alphaBlend = true;

$textRenderer = new GDW\Renderers\NonOverlappingText();
$labelsLayer->setRenderer($textRenderer);

/*if(function_exists("errhandlersetimage")){ // n14errorhandler
	clean_obs();
	ob_start (function($x){eh_img_echo_col(strip_tags($x),7); return "";});
	errhandlersetimage($overlayGD);
}*/

eh_img_echo_col("\r\n\r\n\r\n\r\nNemoT3DWireframeRenderer +WithSprites ALPHA b".UTT_RENDERER_REV."\r\n",15);

utt_checkpoint("Begin");
//imagefill($img,0,0,0x000000);
$bgLayer->fill($scheme['background']);


utt_checkpoint("BeginParse");



if(file_exists($jsonfile)){
	$actors=json_decode(file_get_contents($jsonfile),true);
	if(!count($actors)){
		eh_img_echo_col("!! Empty JSON poly file: " . realpath($jsonfile),12);
		//imagepng($img,CACHEDIR."/".name2id($_GET['map'])."_{$cacheNameParams}.png",9);
		$merged = $image->getMergedGD();
		$imageFinishFunction($merged);
		//header("content-type: image/png");
		//imagepng($merged);
		imagedestroy($merged);
		exit;
	}
}else if(file_exists($polyfile)){
	$problemLoading = false;
	if(!file_exists($polyfile)){
		$problemLoading = true;
	}else{
		$actorsCT=d_parseT3D($polyfile);
		$problemLoading = !isset($actorsCT['Map']);
	}
	
	if($problemLoading){
		eh_img_echo_col("!! There was a problem with loading the map...", 12);
		//imagepng($img,CACHEDIR."/".name2id($_GET['map'])."_{$cacheNameParams}.png",9);
		$merged = $image->getMergedGD();
		$imageFinishFunction($merged);
		//imagepng($merged);
		//imagedestroy($merged);
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
}else{
	eh_img_echo_col("!! Couldn't find polys for map. Open Mappage and run mapdlcron.", 12);
	//imagepng($img,CACHEDIR."/".name2id($_GET['map'])."_{$cacheNameParams}.png",9);
	$merged = $image->getMergedGD();
	$imageFinishFunction($merged);
	//imagepng($merged);
	//imagedestroy($merged);
	exit;
}
utt_checkpoint("EndParse");

$brsh=getActorsByPropertyValue($actors, "CsgOper","CSG_Subtract");
//$brshNullCsg=getActorsByPropertyValue($actors, "CsgOper",null);
if(!count($brsh)){ // all the brushes has been removed from map to protect it from editing
	eh_img_echo_col("!! Map layout was restricted by the author. World geometry won't be shown.\r\n",14);
	/*$imageFinishFunction($img);
	imagepng($img,CACHEDIR."/".name2id($_GET['map'])."_{$cacheNameParams}.png",9);
	imagedestroy($img);
	exit;*/
}
$restricted=!count($brsh);
$bounds=array('tl'=>array("X"=>32768.0,"Y"=>32768.0,"Z"=>32768.0),'br'=>array("X"=>-32768.0,"Y"=>-32768.0,"Z"=>-32768.0));
$worldBounds=$bounds;

utt_checkpoint("BeginBoundaries");
$skyboxIsPartOfMap=false;
foreach($actors as $act){ // HOLY SHIT!
	// we're finding the level boundaries by iterating through all the actors' positions;
	$closeActs=null;
	$class=isset($act['properties']['Class'])?$act['properties']['Class']:"None";
	
	if($class=="PathNode" || $class=="PlayerStart" || $class=="JBPlayerStart" || isset($act['properties']['markedItem']) || 
		$class=="InventorySpot" || $class=="Mover" || isset($act['properties']['FootRegion']) || isset($act['properties']['Paths(0)']) ||
		$class=="Brush" ) {
			
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
		//eh_img_echo_col("stupid actor:".$act['properties']['Name']."\n",7);
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

$dispOffset=vec3Diff($bounds['tl'],$bounds['br']);

$projectionAdjustVirtFunction();

$scaleX=$SCREENX / $virtW;
$scaleY=$SCREENY / $virtH;
$scale=abs(min($scaleX,$scaleY)*0.8);
$offsetX = 0;//(SCREENX - ($virtW+$virtOffX)*$scale) / 2;
$offsetY = 0;//(SCREENY - ($virtH+$virtOffY)*$scale) / 2;


$bound1=$projectionFunction($bounds['br']);
$bound2=$projectionFunction($bounds['tl']);


$offsetX=(  (-$bound2['X'])) + ($SCREENX - $bound1['X']+$bound2['X'])/2;
$offsetY=(  (-$bound2['Y'])) + ($SCREENY - $bound1['Y']+$bound2['Y'])/2;
//imagettftext($img,8,0,4,$SCREENY-100,0xFFFFFF,$fontsLoc ."/seguisb.ttf","MAPX:$mapSizeX MAPY:$mapSizeY MAPZ:$mapSizeZ\nSX:$scaleX SY:$scaleY\nVW:$virtW VH: $virtH\nVOX:$virtOffX VOY:$virtOffY\nOX: $offsetX OY: $offsetY");

utt_checkpoint("EndBoundaries");

$bound1=$projectionFunction($bounds['br']);
$bound2=$projectionFunction($bounds['tl']);
/*
imagettftext($img,12,0,$bound2['X'],$bound2['Y'],0xFFFFFF,$fontsLoc ."/tahomabd.ttf","B2");
imagettftext($img,12,0,$bound1['X'],$bound1['Y'],0xFFFFFF,$fontsLoc ."/tahomabd.ttf","B1");


imagerectangle($img,$bound2['X'],$bound2['Y'],$bound1['X'],$bound1['Y'],0xffffff);
*/
utt_checkpoint("BeginDrawWorld");


$brushct=0;
$polyct=0;
$vertct=0;


$report=array('reportVersion'=>UTT_MAPREPORT_VER,'monstercount'=>0,'medboxcount'=>0,'mapsizeX'=>$mapSizeX,'mapsizeY'=>$mapSizeY,'mapsizeZ'=>$mapSizeZ,'brushcsgaddcount'=>0,'brushcsgsubcount'=>0,'moverscount'=>0,'lightWattage'=>0,'usedTextures'=>array());

foreach($actors as $act){
	//break;
	
	if($act['properties']['Class']=="Brush" || $act['properties']['Class']=="Mover"){
		if(!isset($act['Brush'])) continue;
		if(!isset($act['Brush'][0]['PolyList'][0]['Polygon'])) continue;
		$polyz=$act['Brush'][0]['PolyList'][0]['Polygon'];
		
		if(isset($act['properties']['Region']['Zone']) && strpos($act['properties']['Region']['Zone'],"SkyZoneInfo")!==false && !$skyboxIsPartOfMap) continue;
		
		$brushOffs=isset($act['properties']['Location'])?$act['properties']['Location']+$emptyCoord:$emptyCoord;
		
		
		
		if(isset($act['properties']['CsgOper'])){
			$brushOp=$act['properties']['CsgOper'];
			
			if($brushOp=="CSG_Subtract"){
				$col=$scheme['brushSubtract'];
				$brushType=0;
				$report['brushcsgsubcount']++;
			}else if($brushOp=="CSG_Add"){
				$col=$scheme['brushAdd'];
				$brushType=1;
				$report['brushcsgaddcount']++;
			}else if($brushOp=="CSG_Active"){
				$col=$scheme['brushActive'];
				$brushType=2;
				continue; // we're not drawing the red brush anymore
			}else{
				continue;
				$col=$scheme['brushGray'];
				$brushType=3;
			}
		}else{
			if(isset($act['properties']['Brush']) && unserializeReference($act['properties']['Brush'])['export']=="Brush"){ // usually, the builder brush is linked to a model called 'Brush', and not 'ModelXXX'
				//echo "BuilderBrushFoound";
				$col=$scheme['brushActive'];
				$brushType=2;
				continue;
			}else{
				//continue;
				$col=$scheme['brushGray'];
				$brushType=4;
			}
		}
		
		if(isset($act['properties']['PolyFlags'])){
			$flags=$act['properties']['PolyFlags'];
			if($flags & 0x00000001){ // invisible
				continue;
			}else if($flags & 0x00000008){ //non-solid
				$col = $scheme['brushNonSolid'];
			}else if($flags & 0x00000020){ //semi-solid
				$col = $scheme['brushSemiSolid'];
			}else if($flags & 0x04000000){ //zone portal
				//$col = $scheme['brushZonePortal'];
				continue;
			}
		}
		if($act['properties']['Class']=="Mover"){
			$col=$scheme['brushMover'];
			$brushType=4;
			$report['moverscount']++;
		}/*else if($brushType>2) {
			continue;
		}*/
		if($col==$scheme['brushGray']){
			continue;
		}
		
		
		//$col = ($col >> 1) & 0x7F7F7F;
		$pivot=isset($act['properties']['PrePivot'])?$act['properties']['PrePivot']+$emptyCoord:$emptyCoord;

		
		$rotation=isset($act['properties']['Rotation'])?$act['properties']['Rotation']+$emptyRot:$emptyRot;
		
		$bprops=$act['properties'];
		$mainScaleVec=vec3ScaleMatrixToQBVec3(isset($bprops['MainScale']['Scale'])?$bprops['MainScale']['Scale']+$emptyScaleVec:$emptyScaleVec);
		$postScaleVec=vec3ScaleMatrixToQBVec3(isset($bprops['PostScale']['Scale'])?$bprops['PostScale']['Scale']+$emptyScaleVec:$emptyScaleVec);
		$tempScaleVec=vec3ScaleMatrixToQBVec3(isset($bprops['TempScale']['Scale'])?$bprops['TempScale']['Scale']+$emptyScaleVec:$emptyScaleVec);
		$rot=vec3rotToQBVec3($rotation);
		$piv=vec3ToQBVec3($pivot);
		$coffset=vec3ToQBVec3($brushOffs);
			
		/*$p=$projectionFunction(vec3Sum($pivot,$brushOffs));
		
		imagefilledrectangle($img,$p['X']-1,$p['Y']-1,$p['X']+1,$p['Y']+1,0xFF0000);
		*/

		
		foreach($polyz as $polyNum=>$pol){
			

			$vertList=array(); // imagefilledpolygon-compatible list
			
			$transformedVerts=array();
			$verts=array();

			
			
			if(isset($pol['properties']['Texture'])){
				if(isset($report['usedTextures'][$pol['properties']['Texture']])) $report['usedTextures'][$pol['properties']['Texture']]++;
				else $report['usedTextures'][$pol['properties']['Texture']]=1;
			}
			
			foreach($pol['Vertex'] as $vertNum=>$vert){
				if(is_array($vert)){
					$verts[]=vec3ToQBVec3($vert+$emptyCoord);
				}else{
					echo "Warn: act#{$act['properties']['Name']}/pol$polyNum/vert#$vertNum !is_array\r\n";
				}
			}

			UEVertTransform($verts,$mainScaleVec,$postScaleVec,$tempScaleVec,$rot,$piv,$coffset);

			foreach($verts as $v){
				$utVert=QBVec3Tovec3($v);
				$transformedVerts[]=$utVert;
				
				$v2=$projectionFunction($utVert);
				$vertList[]=round($v2['X']);
				$vertList[]=round($v2['Y']);
				
				if($brushType==0){
					$actVert=$utVert;
					$actVert['Z']=-$actVert['Z'];
					$worldBounds['tl']=vec3Op($actVert,$worldBounds['tl'],'min');
					$worldBounds['br']=vec3Op($actVert,$worldBounds['br'],'max');
				}
			}
			
			// Dirty code to check if polygon is facing our way. If not, we're skipping the drawing
			
			if(isPolyOffscreen($vertList)) continue;
			
			$polyCenter=$emptyCoord;
			
			foreach($transformedVerts as $vert){
				$polyCenter=vec3Sum($polyCenter,$vert);
			}
			$vertCt=count($transformedVerts );
			$polyCenter=vec3Op($polyCenter,$emptyCoord,function($a,$b)use($vertCt){return $a / ($vertCt);});
		
			
			$centerTransformed=$polyCenter;
			
			$centerCoords=$projectionFunction($centerTransformed);
			$firstVertCoords=$projectionFunction($transformedVerts[0]);
			$secondVertCoords=$projectionFunction($transformedVerts[1]);
			
			$ang=vec2Angle($centerCoords,$firstVertCoords)/M_PI;
			$ang2=vec2Angle($centerCoords,$secondVertCoords)/M_PI;
			
			$angX=$ang2-$ang;
			// if you don't have a clue what's going on here, those might help a little:
			//imageline($img,$centerCoords['X'],$centerCoords['Y'],$firstVertCoords['X'],$firstVertCoords['Y'],0xFF0000);
			//imageline($img,$centerCoords['X'],$centerCoords['Y'],$secondVertCoords['X'],$secondVertCoords['Y'],0xFFFF00);
			
			$angR=vec3Angle($centerTransformed,$transformedVerts[0]);
			$angRX=($angR['Roll']+$angR['Pitch'])/(M_PI);
			
			// visible range: (-1, 0) , (1, 2)
			
			if($brushType==0){ // subtractive brush
				if($angX > 0 && $angX < 1 || $angX < -1){
					continue;
				}
			}else{ // additive
				if(-$angX > 0 && -$angX < 1 || -$angX < -1){
					continue;
				}
			}
			
			$pcol = round(128 * fmod($angRX-0.5, 1));
			$pcol *= 0x010101;
			$pcol += 0x3f3f3f;
			$pcol |= 0x60000000;
			
			$polyColor = $col|0x70000000;
			/*if($act['properties']['Class']!="Mover") {
				$polyColor = mixColors($polyColor,$pcol,0.3);
				$polyColor |= 0x68000000;
			}else{
				$polyColor |= 0x70000000;
			}*/
			
			imagefilledpolygon($worldGD,$vertList,count($vertList)/2,$polyColor);
			imagepolygon($worldGD,$vertList,count($vertList)/2,$col|0x70000000);
			
			
			
			if(count($vertList)<3 || $act['properties']['Class']=="Mover") 
				//echo "Invalid vert count: ".$act['properties']['Name']."->poly$polynum\r\n";
				continue;
			else
				imagefilledpolygon($worldGD,$vertList,count($vertList)/2,$pcol);
			
			$polyct++;
			$vertct+=count($vertList)/2;
			
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
/* gametype detection */

$mhendList = getActorsByClass($actors, "MonsterEnd");

$isMH = (bool)count($mhendList);
$flagList = getActorsByClass($actors, "FlagBase");
$isCTF = !$isMH && (bool)count($flagList);



utt_checkpoint("BeginDrawObjects");


$displayedTags=array(); // to avoid displaying the same thing multiple times
$levelPalette=array(); 

$actorsGroupsNum=0;
$actorsGroupsFirstAct=array();

$blitter    = new GDW\PaintTools\SpriteBlitter($spritesLayer);
$blitterImp = new GDW\PaintTools\SpriteBlitter($importantSpritesLayer); // blitter for objective related objects (e.g FlagBase or MonsterEnd)
$sprites    = array();
$spritesImp = array();
$spriteLoc = __DIR__ . "/sprites";
$spriteDir = opendir($spriteLoc);
while(($spriteFile=readdir($spriteDir))!==false){
	$spritePath = "$spriteLoc/$spriteFile";
	if(filetype($spritePath)=='file'){
		$spriteId = pathinfo($spritePath,PATHINFO_FILENAME);
		$sprites[$spriteId]    = $blitter->addSprite($spritePath);
		$spritesImp[$spriteId] = $blitterImp->addSprite($spritePath);
	}
	
}


foreach($actors as $act){
	$loc2d=null;
	$class=strtolower($act['properties']['Class']);
	$classNC=$act['properties']['Class'];
	//if($class!="levelinfo" && isset($act['properties']['Region']['iLeaf']) && $act['properties']['Region']['iLeaf']==-1) continue;
	
	/*if($class=="brush"){
		$loc=isset($act['properties']['Location'])?$act['properties']['Location']+$emptyCoord:$emptyCoord;
		$loc2d=$projectionFunction($loc);
		imagefilledrectangle($spritesGD,$loc2d['X'],$loc2d['Y'],$loc2d['X']+2,$loc2d['Y']+2,0xffffff);
		
		$tip = "$classNC:{$act['properties']['Name']}";
		if(isset($act['properties']['Region']['Zone'])) 
			$tip.= " zone=".$act['properties']['Region']['Zone'];
		gdw_textWithShadow($labelsLayer, $loc2d['X']+2,$loc2d['Y']-2, $tip, array("size"=>7,"font"=>$fontsLoc ."/tahoma.ttf"),0xFFFFFF);
	}*/

	if(isset($report['actorsCount'][$class])) $report['actorsCount'][$class]++;
	else $report['actorsCount'][$class]=1;
	
	if(isset($act['properties']['Region']['Zone'])) $report['zones'][crc32($act['properties']['Region']['Zone'])]=$act['properties']['Region']['Zone'];
	
	if($class=="levelinfo"){
		$report['title']=isset($act['properties']['Title'])?$act['properties']['Title']:"";
		$report['author']=isset($act['properties']['Author'])?$act['properties']['Author']:"";
		
		$report['ipc']=isset($act['properties']['IdealPlayerCount'])?$act['properties']['IdealPlayerCount']:"";
		$report['entermsg']=isset($act['properties']['LevelEnterText'])?$act['properties']['LevelEnterText']:"";
	}else if($class=="playerstart" || $class=="jbplayerstart" ){
		if($isMH && isset($act['properties']['TeamNumber']) && $act['properties']['TeamNumber']!=0) continue;
		$secondaryMHSpawn = $isMH && isset($act['properties']['bEnabled']) && $act['properties']['bEnabled']=="False";
		$loc=isset($act['properties']['Location'])?$act['properties']['Location']+$emptyCoord:$emptyCoord;
		$loc2d=$projectionFunction($loc);
		//imagefilledrectangle($img,$loc2d['X']-1,$loc2d['Y']-1,$loc2d['X']+1,$loc2d['Y']+1,0x00FFFF);
		if($isCTF){
			$team = isset($act['properties']['TeamNumber']) ? $act['properties']['TeamNumber'] : 0;
			if($team < 0 || $team > 3) $team = 0;
			$blitter->blit($sprites['teamplayerstart_'.$team],$loc2d['X']-5,$loc2d['Y']-4);
		}
		
		
		if($secondaryMHSpawn){
			$blitter->blitWithShadow($sprites['playerstart_2'],$loc2d['X']-4,$loc2d['Y']-7);
		}else{
			$blitter->blitWithShadow($sprites['playerstart'],$loc2d['X']-4,$loc2d['Y']-7);
		}
		
	}else if($class=="kicker"){
		$loc=isset($act['properties']['Location'])?$act['properties']['Location']+$emptyCoord:$emptyCoord;
		$kickVel=isset($act['properties']['KickVelocity'])?$act['properties']['KickVelocity']+$emptyCoord:$emptyCoord;
		

		$kickDest=vec3Sum($loc,$kickVel);
		$loc2d=$projectionFunction($loc);
		$dest2d=$projectionFunction($kickDest);
		
		
		
		$kickAngle=-vec2Angle($dest2d,$loc2d)-M_PI/2;
		/*$ox=round(sin($kickAngle)*12)+$loc2d['X'];
		$oy=round(cos($kickAngle)*12)+$loc2d['Y'];*/
		
		$arrow1X=round(sin(M_PI+$kickAngle-0.4)*7)+$dest2d['X'];
		$arrow1Y=round(cos(M_PI+$kickAngle-0.4)*7)+$dest2d['Y'];
		$arrow2X=round(sin(M_PI+$kickAngle+0.4)*7)+$dest2d['X'];
		$arrow2Y=round(cos(M_PI+$kickAngle+0.4)*7)+$dest2d['Y'];
		
		imagefilledrectangle($linesGD,$loc2d['X']-1,$loc2d['Y']-1,$loc2d['X']+1,$loc2d['Y']+1,$scheme['objKicker']);
		
		if($arrow1X!=$arrow2X && $arrow1Y!=$arrow2Y){
			
			imageLine($linesGD,$loc2d['X'],$loc2d['Y'],$dest2d['X'],$dest2d['Y'],$scheme['objKickerArrow']);
			//imageLine($linesGD,$loc2d['X'],$loc2d['Y'],$ox,$oy,$scheme['objKickerArrow']);
			imageLine($linesGD,$dest2d['X'],$dest2d['Y'],$arrow1X,$arrow1Y,$scheme['objKickerArrow']);
			imageLine($linesGD,$dest2d['X'],$dest2d['Y'],$arrow2X,$arrow2Y,$scheme['objKickerArrow']);
		}
		//imagettftext($img,6,0,$loc2d['X']+5,$loc2d['Y'],0,$fontsLoc ."/seguisb.ttf",$kickAngle);
		
		
		$kickerPresent=true;
	}else if($class=="teleporter" || $class=="favoritesteleporter" || $class=="visibleteleporter"){
		$loc=isset($act['properties']['Location'])?$act['properties']['Location']+$emptyCoord:$emptyCoord;
		$loc2d=$projectionFunction($loc);

		$telepText="";

		if(isset($act['properties']['URL'])){
			$tdestNum=getSingleActorByTag($actors,$act['properties']['URL']);
			if($tdestNum!=null){
				$tdest=$actors[$tdestNum];
				if(!isset($tdest['properties']['Region']['iLeaf']) || $tdest['properties']['Region']['iLeaf']!=-1){
					$teleLineColor = $scheme['lineTeleportConnection'];
					imagesetstyle($linesGD,array($teleLineColor,$teleLineColor,$teleLineColor,$teleLineColor,$teleLineColor, IMG_COLOR_TRANSPARENT , IMG_COLOR_TRANSPARENT , IMG_COLOR_TRANSPARENT , IMG_COLOR_TRANSPARENT , IMG_COLOR_TRANSPARENT ));
			
					$destLoc=isset($tdest['properties']['Location'])?$tdest['properties']['Location']+$emptyCoord:$emptyCoord;
					
					$dest2d=$projectionFunction($destLoc);
					
					$linesLayer->paint->line($loc2d['X'],$loc2d['Y'],$dest2d['X'],$dest2d['Y'],IMG_COLOR_STYLED);
				}
				
				
			}
			//ImageShadowatedjfdsglifgdlsljhgklsTTFText($img,9,0,$loc2d['X'],$loc2d['Y']+4,0xffffff,$fontsLoc ."/tahomabd.ttf","T");
			$blitter->blitWithShadow($sprites['teleporter'],$loc2d['X']-4,$loc2d['Y']-6);
		}else{
			//ImageShadowatedjfdsglifgdlsljhgklsTTFText($img,7,0,$loc2d['X'],$loc2d['Y']+3,0x30ffffff,$fontsLoc ."/tahomabd.ttf","D");
			$blitter->blitWithShadow($sprites['teleporter_dest'],$loc2d['X']-4,$loc2d['Y']-6);
		}
		
		
		
		$teleporterPresent=true;
	}else if($class=="warpzoneinfo"){
		$loc=isset($act['properties']['Location'])?$act['properties']['Location']+$emptyCoord:$emptyCoord;
		$loc2d=$projectionFunction($loc);

		$telepText="";

		if(isset($act['properties']['OtherSideURL'])){
			$tdestNums=getActorsByPropertyValue($actors,"ThisTag",$act['properties']['OtherSideURL']);
			if(isset($tdestNums[0]) && $tdestNums[0]!=null){
				$tdest=$actors[$tdestNums[0]];
				if(!isset($tdest['properties']['Region']['iLeaf']) || $tdest['properties']['Region']['iLeaf']!=-1){
					$teleLineColor = $scheme['lineTeleportConnection'];
					imagesetstyle($linesGD,array($teleLineColor,$teleLineColor,$teleLineColor,$teleLineColor,$teleLineColor, IMG_COLOR_TRANSPARENT , IMG_COLOR_TRANSPARENT , IMG_COLOR_TRANSPARENT , IMG_COLOR_TRANSPARENT , IMG_COLOR_TRANSPARENT ));
			
					$destLoc=isset($tdest['properties']['Location'])?$tdest['properties']['Location']+$emptyCoord:$emptyCoord;
					
					$dest2d=$projectionFunction($destLoc);
					
					$linesLayer->paint->line($loc2d['X'],$loc2d['Y'],$dest2d['X'],$dest2d['Y'],IMG_COLOR_STYLED);
				}
				
				
			}
			//ImageShadowatedjfdsglifgdlsljhgklsTTFText($img,9,0,$loc2d['X'],$loc2d['Y']+4,0xffffff,$fontsLoc ."/tahomabd.ttf","T");
			$blitter->blitWithShadow($sprites['teleporter'],$loc2d['X']-4,$loc2d['Y']-6);
		}else{
			//ImageShadowatedjfdsglifgdlsljhgklsTTFText($img,7,0,$loc2d['X'],$loc2d['Y']+3,0x30ffffff,$fontsLoc ."/tahomabd.ttf","D");
			$blitter->blitWithShadow($sprites['teleporter_dest'],$loc2d['X']-4,$loc2d['Y']-6);
		}
		
		
		
		$teleporterPresent=true;
	}else if($class=="flagbase" && !$isMH){ // CTF
		$loc=isset($act['properties']['Location'])?$act['properties']['Location']+$emptyCoord:$emptyCoord;
		$loc2d=$projectionFunction($loc);
		
		$team=isset($act['properties']['Team'])?$act['properties']['Team']:0;
		
		if($team < 0 || $team > 3) $team = 0;
		
		switch($team){
			case 0:
				$ds="Red";
				$report['redflag']=$loc;
			break;
			case 1:
				$ds="Blue";
				$report['blueflag']=$loc;
			break;
			case 2:
				$ds="Green";
				$report['greenflag']=$loc;
			break;
			case 3:
				$ds="Gold";
				$report['goldflag']=$loc;
			break;
		}
		
		$flagsPresent=true;
		//imagefilledrectangle($img,$loc2d['X']-1,$loc2d['Y']-1,$loc2d['X']+1,$loc2d['Y']+1,$colx);
		//ImageShadowatedjfdsglifgdlsljhgklsTTFText($img,11,0,$loc2d['X'],$loc2d['Y'],$colx,$fontsLoc ."/tahomabd.ttf","F");
		
		//ImageShadowatedjfdsglifgdlsljhgklsTTFText($img,7,0,$loc2d['X']+6,$loc2d['Y'],0xFFFFFF,$fontsLoc ."/tahoma.ttf",$ds);
		$blitterImp->blitWithShadow($sprites['flagbase_'.$team],$loc2d['X'],$loc2d['Y']-7);
	}else if($class=="monsterend"){ // MH
		$loc=isset($act['properties']['Location'])?$act['properties']['Location']+$emptyCoord:$emptyCoord;
		$loc2d=$projectionFunction($loc);
		//ImageShadowatedjfdsglifgdlsljhgklsTTFText($img,7,0,$loc2d['X']+3,$loc2d['Y']+2,0x00FF00,$fontsLoc ."/tahoma.ttf","MHEnd");
		//imagefilledrectangle($img,$loc2d['X']-2,$loc2d['Y']-2,$loc2d['X']+1,$loc2d['Y']+1,0xFFFFFF);
		$blitterImp->blitWithShadow($sprites['monsterend'],$loc2d['X']-4,$loc2d['Y']-7);
		$monsterEndPresent=true;
	}else if($class=="warheadlauncher"){

		$loc=isset($act['properties']['Location'])?$act['properties']['Location']+$emptyCoord:$emptyCoord;
		$loc2d=$projectionFunction($loc);
		//ImageShadowatedjfdsglifgdlsljhgklsTTFText($img,7,0,$loc2d['X']-3,$loc2d['Y']+3,0xFFCF00,$fontsLoc ."/tahomabd.ttf","R");
		$blitter->blitWithShadow($sprites['warheadlauncher'],$loc2d['X']-4,$loc2d['Y']-8);
		
		$redeemerPresent=true;
	}else if($class=="udamage"){

		$loc=isset($act['properties']['Location'])?$act['properties']['Location']+$emptyCoord:$emptyCoord;
		$loc2d=$projectionFunction($loc);
		//ImageShadowatedjfdsglifgdlsljhgklsTTFText($img,7,0,$loc2d['X']-3,$loc2d['Y']+3,0xAF2FFF,$fontsLoc ."/tahomabd.ttf","U");
		$blitter->blitWithShadow($sprites['udamage'],$loc2d['X']-4,$loc2d['Y']-8);
		
		$udamagePresent=true;

	}else if($class=="jailzone" || $class=="pressurejailzone"){ // JB
		$loc=isset($act['properties']['Location'])?$act['properties']['Location']+$emptyCoord:$emptyCoord;
		$loc2d=$projectionFunction($loc);
		
		$team=isset($act['properties']['JailedTeam'])?$act['properties']['JailedTeam']:0;
		if($team < 0 || $team > 3) $team = 0;
		
		switch($team){
			case 0:
				$ds="Red";
			break;
			case 1:
				$ds="Blue";
			break;
			case 2:
				$ds="Green";
			break;
			case 3:
				$ds="Gold";
			break;
		}
		$jailPresent=true;
		//imagefilledrectangle($img,$loc2d['X']-1,$loc2d['Y']-1,$loc2d['X']+1,$loc2d['Y']+1,$colx);
		//ImageShadowatedjfdsglifgdlsljhgklsTTFText($img,11,0,$loc2d['X'],$loc2d['Y'],$colx,$fontsLoc ."/tahomabd.ttf","J");
		
		//ImageShadowatedjfdsglifgdlsljhgklsTTFText($img,7,0,$loc2d['X']+8,$loc2d['Y'],0xFFFFFF,$fontsLoc ."/tahoma.ttf",$ds);
		$blitterImp->blitWithShadow($sprites['jailzone_'.$team],$loc2d['X']-4,$loc2d['Y']-7);
		
	}else if($class=="teamactivatedtrigger" || $class=="teamactivateddamageatonce" || $class=="teamactivateddamagebuildup"){
		$loc=isset($act['properties']['Location'])?$act['properties']['Location']+$emptyCoord:$emptyCoord;
		$loc2d=$projectionFunction($loc);
		
		$team=isset($act['properties']['Team'])?$act['properties']['Team']:0;
		if($team < 0 || $team > 3) $team = 0;
		
		switch($team){
			case 0:
				$ds="Red";
			break;
			case 1:
				$ds="Blue";
			break;
			case 2:
				$ds="Green";
			break;
			case 3:
				$ds="Gold";
			break;
		}
		$jailSwitchPresent=true;
		//imagefilledrectangle($img,$loc2d['X']-1,$loc2d['Y']-1,$loc2d['X']+1,$loc2d['Y']+1,$colx);
		/*ImageShadowatedjfdsglifgdlsljhgklsTTFText($img,11,0,$loc2d['X'],$loc2d['Y'],$colx,$fontsLoc ."/tahomabd.ttf","R");
		
		ImageShadowatedjfdsglifgdlsljhgklsTTFText($img,7,0,$loc2d['X']+9,$loc2d['Y'],0xFFFFFF,$fontsLoc ."/tahoma.ttf",$ds);*/
		$blitterImp->blitWithShadow($sprites['jailtrigger_'.$team],$loc2d['X']-4,$loc2d['Y']-7);
	}else if(isset($act['properties']['FootRegion'])){
		
		if($class=="cow" || $class=="babycow" || $class=="nali" || $class=="nalipriest" // scriptedpawn
			|| $class=="horseflyswarm" || $class=="biterfishschool" || $class=="parentblob" // flockmasterpawn
			|| $class=="bird1" || $class=="biterfish" || $class=="bloblet" || $class=="horsefly" || $class=="nalirabbit" // flockpawn
			|| $class=="teamcannon" || $class=="miniguncannon" || $class=="fortstandard" // StationaryPawn
			) continue;
		
		$loc=isset($act['properties']['Location'])?$act['properties']['Location']+$emptyCoord:$emptyCoord;
		$drawScale=(isset($act['properties']['DrawScale'])?$act['properties']['DrawScale']:1)*2;
		$loc2d=$projectionFunction($loc);
		$drawScaleX=max(2,$drawScale/2);
		
		$report['monstercount']++;
		
		$monName=(isset($act['properties']['MenuName'])?$act['properties']['MenuName']:$class);
		
		$mcx=$classNC;
		if(isset($report['monsterTypesCount'][$mcx][$monName])) $report['monsterTypesCount'][$mcx][$monName]++;
		else $report['monsterTypesCount'][$mcx][$monName]=1;
		
		imagefilledellipse($spritesGD,$loc2d['X']-$drawScaleX/2,$loc2d['Y']-$drawScaleX/2,$drawScaleX,$drawScaleX,$scheme['objMonster']);
			
		if(!isset($act['uttpr_props']['partOfGroup'])){
			$otherActz=getActorsByClass($actors,$classNC);
			$closeActzRad=getActorsInRadius($otherActz,$act['properties']['Location'],40/$scale);
			$closeActzReg=getActorsInTheSameRegion($otherActz,$act,12);
			$closeActz=array_merge($closeActzRad,$closeActzReg);
			if(count($closeActz) > 2){
				
				
				
				$currentGroupNr=-1;
				foreach($closeActz as $acxId){
					
					if(isset($actors[$acxId]['uttpr_props']['partOfGroup'])) $currentGroupNr=max($actors[$acxId]['uttpr_props']['partOfGroup'],$currentGroupNr);
				}
				
				if($currentGroupNr==-1) {
					$currentGroupNr=$actorsGroupsNum++;
					
					$act['uttpr_props']['groupSize']=0;
					$act['uttpr_props']['partOfGroup']=$currentGroupNr;
					$actorsGroupsFirstAct[$currentGroupNr]=$act;
					
					//echo "CGN=$currentGroupNr {$actorsGroupsFirstAct[$currentGroupNr]['uttpr_props']['groupSize']}\r\n";
				}
				
				//imagettftext($img,9,0,$loc2d['X']+3,$loc2d['Y']-1,0xFFFFFF,$fontsLoc ."/tahoma.ttf",$currentGroupNr);
				
				foreach($closeActz as $acxId){
					if(!isset($actors[$acxId]['uttpr_props']['partOfGroup'])){
						$actors[$acxId]['uttpr_props']['partOfGroup']=$currentGroupNr;
						/*if(!isset($actorsGroupsFirstAct[$currentGroupNr]['uttpr_props']['groupSize'])) {
							//echo "ORP=$currentGroupNr\r\n";
							$actorsGroupsFirstAct[$currentGroupNr]['uttpr_props']['groupSize']=0;
						}*/
						$actorsGroupsFirstAct[$currentGroupNr]['uttpr_props']['groupSize']++;
					}
				}
			}else{
				//ImageShadowatedjfdsglifgdlsljhgklsTTFText($img,7,0,$loc2d['X']+2,$loc2d['Y']-2,0xFF7F00,$fontsLoc ."/tahoma.ttf",$classNC);
				
				if($SCREENX >= 1024) {
					$hp = isset($act['properties']['Health']) ? " (".$act['properties']['Health'].")" : "";
					gdw_textWithShadow($labelsLayer, $loc2d['X']+2,$loc2d['Y']-2, $classNC.$hp, array("size"=>7,"font"=>$fontsLoc ."/tahoma.ttf"),$scheme['objMonsterText']);
				}
	
			}
		}
		
		 
		
		$monsterPresent=true;
	}else if($class=="thingfactory" || $class=="creaturefactory"){
		if(!isset($act['properties']['prototype'])) continue;
		$tag=$act['properties']['Tag'];
		$spawns=getActorsByTag($actors,$tag);
		$cap=(isset($act['properties']['capacity'])?$act['properties']['capacity']:1);
		
		if(!count($spawns)){ // no spawnpoints, so the factory is useless
			eh_img_echo_col("'' stupid useless factory: ".$act['properties']['Name']."\n", 7);
			continue;
		}
		$spawnsAvgCt = 0;
		
		foreach($spawns as $spId){
			$sp=$actors[$spId];
			if(strcasecmp($sp['properties']['Class'],"SpawnPoint")!==0) continue;
			//if($sp==$act) continue;
			
			$locSp=isset($sp['properties']['Location'])?$sp['properties']['Location']+$emptyCoord:$emptyCoord;
			$locSp2d=$projectionFunction($locSp);
			
			if(!isset($spawnsLocAvg)){
				$spawnsLocAvg = $locSp;
			}else{
				$spawnsLocAvg['X'] += $locSp['X'];
				$spawnsLocAvg['Y'] += $locSp['Y'];
				$spawnsLocAvg['Z'] += $locSp['Z'];
			}
			$spawnsAvgCt++;
			imagefilledrectangle($spritesGD,$locSp2d['X']-1,$locSp2d['Y']-1,$locSp2d['X']+1,$locSp2d['Y']+1,$scheme['objThingFactory']);
		}
		if($spawnsAvgCt==0) continue;
		$spawnsLocAvg['X'] /= $spawnsAvgCt;
		$spawnsLocAvg['Y'] /= $spawnsAvgCt;
		$spawnsLocAvg['Z'] /= $spawnsAvgCt;
		
		
		$thingRef=unserializeReference($act['properties']['prototype']);
		$package=$thingRef['package'];
		//if($package=="UnrealShare") continue;
		$thingFactPresent=true;
		//$loc=isset($act['properties']['Location'])?$act['properties']['Location']+$emptyCoord:$emptyCoord;
		//$loc2d=$projectionFunction($loc);
		$loc2d=$projectionFunction($spawnsLocAvg);
		
		if($cap <= 1000){
			$thingType=$thingRef['export'] . ' ×'.$cap;
			$report['monstercount']+=$cap;
			$mcx=$thingRef['export'];
			if(isset($report['monsterTypesCount'][$mcx]["(factory)"])) $report['monsterTypesCount'][$mcx]["(factory)"]+=$cap;
			else $report['monsterTypesCount'][$mcx]["(factory)"]=$cap;
		}else{
			$thingType=$thingRef['export'] . " ×∞"; // infinity
		}
		
		//ImageShadowatedjfdsglifgdlsljhgklsTTFText($img,7,0,$loc2d['X'],$loc2d['Y'],0xFFFF00,$fontsLoc ."/tahoma.ttf",$thingType);
		if($SCREENX >= 1024) 
			gdw_textWithShadow($labelsLayer, $loc2d['X'],$loc2d['Y'], $thingType, array("size"=>7,"font"=>$fontsLoc ."/tahoma.ttf"),$scheme['objThingFactoryText']);
	
		unset($spawnsLocAvg);
	}else if($class=="b_monsterspawner" || $class=="b_monsterloopspawner" || $class=="b_monsterwavespawner"){ // BBoyShare
	
		$tag=$act['properties']['Tag'];
		
		if(isset($displayedTags[$tag])) continue;
		
		$loc=isset($act['properties']['Location'])?$act['properties']['Location']+$emptyCoord:$emptyCoord;
		$loc2d=$projectionFunction($loc);
		imagefilledrectangle($spritesGD,$loc2d['X']-1,$loc2d['Y']-1,$loc2d['X']+1,$loc2d['Y']+1,$scheme['objThingFactory']);

		if($class=="b_monsterwavespawner"){
			$thingType="<monster wave>";
		}else{
			if(isset($act['properties']['CreatureType'])){
				$factRef=unserializeReference($act['properties']['CreatureType']);
				$thingType=$factRef['export'];
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
		$thingFactPresent=true;
		$loc=isset($act['properties']['Location'])?$act['properties']['Location']+$emptyCoord:$emptyCoord;
		$loc2d=$projectionFunction($loc);
		
		
		if($capacity <= 1000){
			$thingType.=' ×'.$capacity;
			$report['monstercount']+=$capacity;
			$mcx=$monType;
			if(isset($report['monsterTypesCount'][$mcx]["(factory)"])) $report['monsterTypesCount'][$mcx]["(factory)"]+=$capacity;
			else $report['monsterTypesCount'][$mcx]["(factory)"]=$capacity;
		}else{
			$thingType=$thingRef['export'] . " ×∞"; // infinity
		}
			
			
		//ImageShadowatedjfdsglifgdlsljhgklsTTFText($img,7,0,$loc2d['X'],$loc2d['Y'],0xFFFF00,$fontsLoc ."/tahoma.ttf",$thingType);
		
		if($SCREENX >= 1024) 
			gdw_textWithShadow($labelsLayer, $loc2d['X'],$loc2d['Y'], $thingType, array("size"=>7,"font"=>$fontsLoc ."/tahoma.ttf"),$scheme['objThingFactoryText']);
	
		$displayedTags[$tag]=true;

	}else if($class=="zoneinfo"){
		if(!isset($act['properties']['ZoneName'])) continue;
		$desc=$act['properties']['ZoneName'];

		$loc=isset($act['properties']['Location'])?$act['properties']['Location']+$emptyCoord:$emptyCoord;
		$loc2d=$projectionFunction($loc);
		//ImageShadowatedjfdsglifgdlsljhgklsTTFText($img,14,0,$loc2d['X'],$loc2d['Y']-11,0x50FFFF00,$fontsLoc ."/segoeuib.ttf",$desc);

	}else if($class=="healthvial" || $class=="medbox" || $class=="healthpack"){
				
		switch($class){
			case "healthvial": $hp=5; break;
			case "medbox": $hp=20; break;
			case "healthpack": $hp=100; break;
		}

		$loc=isset($act['properties']['Location'])?$act['properties']['Location']+$emptyCoord:$emptyCoord;
		$loc2d=$projectionFunction($loc);
		//ImageShadowatedjfdsglifgdlsljhgklsTTFText($img,6,0,$loc2d['X']-3,$loc2d['Y']+2,0x00CFFF,$fontsLoc ."/tahomabd.ttf","+");
		
		$report['medboxcount']++;
		

	}else if($class=="light"||$class=="triggerlight"){ // default settings ~= 100W light bulb

		$brightness=isset($act['properties']['LightBrightness'])?$act['properties']['LightBrightness']:64;
		$radius=isset($act['properties']['LightRadius'])?$act['properties']['LightRadius']:64;
		
		$watts=round($brightness*1.5625 * log($radius,64));
		
		$h=isset($act['properties']['LightHue'])?$act['properties']['LightHue']:0;
		$s=255-(isset($act['properties']['LightSaturation'])?$act['properties']['LightSaturation']:255);
		$v=isset($act['properties']['LightBrightness'])?$act['properties']['LightBrightness']:64;
		
		
		$rgb=ColorHSLToRGB($h/255,$s/255,$v/255 * (255-$s/2)/255); // unreal engine uses HSV
		$rgbV=clamp($rgb['r'],0,255) << 16 | clamp($rgb['g'],0,255) << 8 | clamp($rgb['b'],0,255);
		//echo "LIGHT H$h S$s V$v ".dechex($rgbV)."\r\n";
		$levelPalette[]=$rgbV;
		//imagefilledrectangle($img,count($levelPalette)*3,0,count($levelPalette)*3+2,2,$rgbV);
		$report['lightWattage']+=$watts;

	}
	
	
}
if($SCREENX >= 1024) {
	foreach($actorsGroupsFirstAct as $gn=>$ax){
		
		if(isset($ax['uttpr_props'])){
			$ct=$ax['uttpr_props']['groupSize'];
			$loc=isset($ax['properties']['Location'])?$ax['properties']['Location']+$emptyCoord:$emptyCoord;
			$loc2d=$projectionFunction($loc);
			
			$acxDesc=$ax['properties']['Class']." ×".$ct;
			//ImageShadowatedjfdsglifgdlsljhgklsTTFText($img,7,0,$loc2d['X']+2,$loc2d['Y']-2,0xFF7F00,$fontsLoc ."/tahoma.ttf",$acxDesc);
			if($SCREENX >= 1024) 
				gdw_textWithShadow($labelsLayer, $loc2d['X']+2,$loc2d['Y']-2, $acxDesc, array("size"=>7,"font"=>$fontsLoc ."/tahoma.ttf"),$scheme['objMonsterGroupText']);
		}
	}
}

// map scale 


$scaledDistFT=pow(10,round(log(0.0625/$scale*50,10)));
$scaledDistM =pow(10,round(log(0.01905/$scale*50,10)));

$scaledDistFTToUU=$scaledDistFT/0.0625;
$scaledDistMToUU =$scaledDistM /0.01905;
if($scaledDistFTToUU*2<$scaledDistMToUU ) {$scaledDistMToUU /=2;$scaledDistM /=2;}
else if($scaledDistMToUU*2<$scaledDistFTToUU ) {$scaledDistFTToUU /=2;$scaledDistFT /=2;}

$pt1=$projectionFunction(array('X'=>0,'Y'=>0,'Z'=>0));
$pt2=$projectionFunction(array('X'=>$scaledDistFTToUU,'Y'=>0,'Z'=>0));
$pt3=$projectionFunction(array('X'=>$scaledDistMToUU,'Y'=>0,'Z'=>0));


$ptDiffFT=vec2Diff($pt2,$pt1);
$ptDiffM=vec2Diff($pt3,$pt1);

//imageline($img,30,$SCREENY-48,30+$ptDiffFT['X'],$SCREENY-48+$ptDiffFT['Y'],0xFFFFFF);
//imageline($img,30,$SCREENY-33,30+$ptDiffM['X'],$SCREENY-33+$ptDiffM['Y'],0xFFFFFF);
$overlayLayer->paint->line(30,$SCREENY-48,30+$ptDiffFT['X'],$SCREENY-48+$ptDiffFT['Y'],$scheme['scaleLine']);
$overlayLayer->paint->line(30,$SCREENY-33,30+$ptDiffM['X'],$SCREENY-33+$ptDiffM['Y'],$scheme['scaleLine']);


//ImageShadowatedjfdsglifgdlsljhgklsTTFText($img,7,0,30,$SCREENY-65,0xFFFFFF,$fontsLoc ."/tahoma.ttf","Scale:");
//ImageShadowatedjfdsglifgdlsljhgklsTTFText($img,7,0,30,$SCREENY-50,0xFFFFFF,$fontsLoc ."/tahoma.ttf","$scaledDistFT ft");
//ImageShadowatedjfdsglifgdlsljhgklsTTFText($img,7,0,30,$SCREENY-35,0xFFFFFF,$fontsLoc ."/tahoma.ttf","$scaledDistM m");

gdw_textWithShadow($overlayLayer, 29, $SCREENY-74, "Scale:",           array("size"=>7,"font"=>$fontsLoc ."/tahoma.ttf"),$scheme['scaleText']);
gdw_textWithShadow($overlayLayer, 29, $SCREENY-59, "$scaledDistFT ft", array("size"=>7,"font"=>$fontsLoc ."/tahoma.ttf"),$scheme['scaleText']);
gdw_textWithShadow($overlayLayer, 29, $SCREENY-44, "$scaledDistM m",   array("size"=>7,"font"=>$fontsLoc ."/tahoma.ttf"),$scheme['scaleText']);



$report['levelPalette']=array_unique ($levelPalette);
utt_checkpoint("EndDrawObjects");

$legendLayerId = $image->newLayer();
$legendLayer = $image->getLayerById($legendLayerId);
$legendLayer->name = "LegendLayer";
$blitter->attachToLayer($legendLayer);
$legend = new GDW\Renderers\RichText();
$legendLayer->setRenderer($legend);
$legend->position=array("x"=>$SCREENX-250,"y"=>0,"width"=>250,"height"=>'auto');
$legend->margin=array('left'=>8, 'right'=>8,'top'=>8,'bottom'=>8);
$legend->backgroundColor = $scheme['legendBackground'];
$legend->textColor = $scheme['legendTitle'];
$legend->fontSize = 10;
$legend->font = "Tahoma";
$legend->fontBold = true;

$par = $legend->newParagraph();
$par->lineHeight = 20;
$legend->write("Legend:");

$legend->newParagraph();
$legend->fontBold = false;
$legend->textColor = $scheme['legendText'];
$legend->fontSize = 7;
$blitter->blitIntoRichText($legend,$sprites['playerstart'],1,1,true);
$legend->write(" player spawns");
if($isMH){
	$legend->newParagraph();
	$blitter->blitIntoRichText($legend,$sprites['playerstart_2'],1,1,true);
	$legend->write(" additional respawn points");
}
if(isset($flagsPresent)){
	$legend->newParagraph();
	if(isset($report['redflag'])){
		//$legend->newParagraph();
		$blitter->blitIntoRichText($legend,$sprites['flagbase_0'],1,1,true);
		//$legend->write(" red flag");
	}
	if(isset($report['blueflag'])){
		//$legend->newParagraph();
		$blitter->blitIntoRichText($legend,$sprites['flagbase_1'],1,1,true);
		//$legend->write(" blue flag");
	}
	if(isset($report['greenflag'])){
		//$legend->newParagraph();
		$blitter->blitIntoRichText($legend,$sprites['flagbase_2'],1,1,true);
		//$legend->write(" green flag");
	}
	if(isset($report['goldflag'])){
		//$legend->newParagraph();
		$blitter->blitIntoRichText($legend,$sprites['flagbase_3'],1,1,true);
		//$legend->write(" gold flag");
	}
	$legend->write(" team flags");
}

if(isset($jailPresent)){
	$legend->newParagraph();
	$blitter->blitIntoRichText($legend,$sprites['jailzone_255'],1,1,true);
	$legend->write(" team jails - color of jailed team");
}
if(isset($jailSwitchPresent)){
	$legend->newParagraph();
	$blitter->blitIntoRichText($legend,$sprites['jailtrigger_255'],1,1,true);
	$legend->write(" release switch - opens jail of the same color");
}
if(isset($monsterEndPresent)){
	$legend->newParagraph();
	$blitter->blitIntoRichText($legend,$sprites['monsterend'],1,1,true);
	$legend->write(" end of the map");
}
if(isset($teleporterPresent)){
	$legend->newParagraph();
	$blitter->blitIntoRichText($legend,$sprites['teleporter'],1,1,true);
	$legend->write(" teleporter connected with ");
	$blitter->blitIntoRichText($legend,$sprites['teleporter_dest'],1,1,true);
	$legend->write(" destination by dashed line");
}if(isset($kickerPresent)){
	$legend->newParagraph();
	$blitter->blitIntoRichText($legend,$sprites['kicker'],1,1,true);
	$legend->write(" kicker");
}
if(isset($monsterPresent)){
	$legend->newParagraph();
	$blitter->blitIntoRichText($legend,$sprites['monster'],1,1,true);
	$legend->write(" monster (different sizes)");
}
if(isset($thingFactPresent)){
	$legend->newParagraph();
	$blitter->blitIntoRichText($legend,$sprites['thingfactory'],1,1,true);
	$legend->write(" monsters respawn point (\"factory\")");
}
if(isset($redeemerPresent)){
	$legend->newParagraph();
	$blitter->blitIntoRichText($legend,$sprites['warheadlauncher'],1,1,true);
	$legend->write(" redeemer");
}
if(isset($udamagePresent)){
	$legend->newParagraph();
	$blitter->blitIntoRichText($legend,$sprites['udamage'],1,1,true);
	$legend->write(" damage amp");
}


$merged = $image->getMergedGD();
$imageFinishFunction($merged);
/*if(!$cli){
	header("content-type: image/png");
	imagepng($merged);
}else{
	//echo ": CacheFile: $cacheName\r\n";
}*/
imagepng($merged,$cacheName,9);


imagedestroy($merged);

file_put_contents(REPORTDIR."/".name2id($_GET['map']).".txt",json_encode($report));
















// NEW BEHAVIOR: RETURNS IDX NRS INSTEAD OF ACTOR REFERENCES!!
function getActorsByPropertyValue(&$actors,$propName,$propVal){
	$actres=array();
	foreach($actors as $actNum=>$act){ // we can't use &act, iterators don't support references
		if(is_numeric($act)) { 
			$actNum=$act; 
			$act=$GLOBALS['actors'][$act]; 
		}

		
		if(
			(isset($act['properties'][$propName]) && strcasecmp($act['properties'][$propName],$propVal)===0) ||
			(!isset($act['properties'][$propName]) && $propVal==null)
		){
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
		if(isset($act['properties']['Tag']) && strcasecmp($act['properties']['Tag'],$tag)===0){
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

function gdw_textWithShadow($layer, $x,$y,$text,$params=array(),$color=null){
	global $textRenderer;
	$alpha=($color==null ? 0x00000000 : $color&0x7F000000);
	
	$isDark = (($color & 0xFF0000) < 0x170000) && (($color & 0xFF00) < 0x1000) && (($color & 0xFF) < 0x20);
	
	if(!$isDark){
		//$layer->paint->text( $x+1,$y+1,$text,$params,$alpha);
		$params['shadow']=true;
	}
	//$layer->paint->text( $x,$y,$text,$params,$color);
	$textRenderer->write( $x,$y,$text,$params,$color);
	
}

function ImageShadowatedjfdsglifgdlsljhgklsTTFText($img,$size,$rot,$x,$y,$col,$font,$text){
	$alpha=$col&0x7F000000;
	imagettftext($img,$size,$rot,$x+1,$y+1,$alpha,$font,$text);
	imagettftext($img,$size,$rot,$x,$y,$col,$font,$text);
	
}

/* PROJECTION FUNCTIONS

projav is used to calculate the proper offset for map to be in center of viewport
TODO description
*/

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

function proj_tibia($vert){
	global $scale,$offsetX,$offsetY;
	$res['X']=($vert['X']-$vert['Z']/2)*$scale+$offsetX;
	$res['Y']=($vert['Y']-$vert['Z']/2)*$scale+$offsetY;
	
	return $res;
}
function projav_tibia(){
	global $virtW,$virtH,$mapSizeX,$mapSizeY,$mapSizeZ,$virtOffX,$virtOffY,$dispOffset;
	$virtW=($mapSizeX+$mapSizeZ/2);
	$virtH=($mapSizeY+$mapSizeZ/2);

	$virtOffX=($dispOffset['X']-$dispOffset['Z']/2);
	$virtOffY=($dispOffset['Y']-$dispOffset['Z']/2);
}



function imgfinish($img){
	global $report, $scheme,$fontsLoc;
	$GLOBALS['watermarkFunction']($img);
	$mapnx=(isset($report['title']) && $report['title']?$report['title']:$_GET['map']);

	imagettftextlcd($img,12,0,4,17,$scheme['mapName'],$fontsLoc ."/segoeuib.ttf",$mapnx);
	if(isset($report['author'])) imagettftextlcd($img,9,0,4,30,$scheme['mapName'],$fontsLoc ."/segoeuib.ttf"," by {$report['author']}");

	imagesavealpha($img,true);
	if($GLOBALS['cli']){
		
	}else{
		
		header("Content-type: image/png");
		flush_obs();
		imagepng($img);

		
	}
}

function utt_watermark($img){
	$wmimg=imagecreatefrompng(__DIR__."/uttwatermarkBIGA.png");
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
		eh_img_echo_col( "Checkpoint $name: ".round(($timeStop-$GLOBALS['UTTDEBUG_CP_START'])*1000)."ms (+".round(($timeStop-$GLOBALS['UTTDEBUG_CP'])*1000)."ms)\n",7);
	}else{
		$GLOBALS['UTTDEBUG_CP_START']=microtime(true);
	}
	$GLOBALS['UTTDEBUG_CP']=microtime(true);
}
function uttdateFmt($d,$c=true){
	global $dateformat;
	if($c){
		/*if($d > time() - 600) return "Now";
		else*/ if($d > time() - 3600) return floor((time()-$d)/60) ." min. ago";
		else if($d > time() - dssm()) return "Today, ".date("G:i",$d);
		else if($d > time() - dssm()-86400) return "Yesterday, ".date("G:i",$d);
		else return date($dateformat,$d);
	}else{
		return date($dateformat,$d);
	}
}
function dssm(){ // seconds since midnight
	return date("G")*3600 + date("i") * 60 + date("s");
}

//http://stackoverflow.com/a/3642787
function ColorHSLToRGB($h, $s, $l){
	
	$r = $l;
	$g = $l;
	$b = $l;
	$v = ($l <= 0.5) ? ($l * (1.0 + $s)) : ($l + $s - $l * $s);
	if ($v > 0){
		
		$m = $l + $l - $v;
		$sv = ($v - $m ) / $v;
		$h *= 6.0;
		$sextant = floor($h);
		$fract = $h - $sextant;
		$vsf = $v * $sv * $fract;
		$mid1 = $m + $vsf;
		$mid2 = $v - $vsf;
		
		switch ($sextant){
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

function clean_obs(){
	$obs=ob_list_handlers();
	foreach($obs as $o){
		ob_end_clean();
	}
}

function flush_obs(){
	$obs=ob_list_handlers();
	foreach($obs as $o){
		ob_end_flush();
	}
}

function mixColors($col1, $col2, $amount=0.5){
	$amountN = 1 - $amount;
	//$a = clamp((($col1>>24) & 0x7F) * $amountN + (($col2>>24) & 0x7F) * $amount,0,127);
	/*$a1 = (($col1>>24) & 0x7F)/127;
	$a2 = (($col2>>24) & 0x7F)/127;
	$a = ($a1 + (1-$a1)*$a2)*255;*/
	$r = clamp((($col1>>16) & 0xFF) * $amountN + (($col2>>16) & 0xFF) * $amount,0,255);
	$g = clamp((($col1>> 8) & 0xFF) * $amountN + (($col2>> 8) & 0xFF) * $amount,0,255);
	$b = clamp((($col1>> 4) & 0xFF) * $amountN + (($col2>> 4) & 0xFF) * $amount,0,255);
	return /*($a << 24) | */($r<<16) | ($g<<8) | $b;
}

/*
function getBoxSizeForItemCount($count){
	$div = hdivisor($count);
	
}
function hdivisor($n){
	for($i=ceil(sqrt($n)); $i>=1; $i--){
		if($n % $i == 0){
			return $i;
		}
	}
}*/

