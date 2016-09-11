<?php
/*
 * ut tracker
 * 2013 namo
 *
 * map info
**/
	date_default_timezone_set ('GMT'); //TODO move to cfg
	
	
	require_once "config.php";
	require_once "sqlengine.php";
	require_once "geoiploc.php";
	require_once "common.php";
	require_once N14CORE_LOCATION . "/TableThing.php";
	require_once "mapPageBackend.php";
	
	use N14\TableThing as TableThing;
	use N14\TableThingColumnInfo as TTCI;

	try{
		
		addRewriteParam("name");

		$mapname=str_replace(" ","+",$_GET['name']);
		$mapid=name2id($mapname);
		if(stripos($mapname,"BT-")===0){
			permredir(maklink(LMAP,null,"CTF-".$mapname));
			exit;
		}
		//echo "!"; // travelling debugger
		
		$isBT = stripos($mapname,"CTF-BT")===0;
		
		$dbh=sqlcreate($statdb_host,$statdb_user,$statdb_pass,$statdb_db);
		
		$lastscan=sqlquery("SELECT data FROM utt_info WHERE `key`=\"net.reaper.lastupdate\"",1)['data'];
		
		if(isset($_GET['redl']) && file_exists("$utmpLoc/sshots/{$mapid}.jpg")) {
			unlink("$utmpLoc/sshots/{$mapid}.jpg");
			unlink("$rendererLoc/jsonpolys/{$mapid}.json");
		}
		
		$pdoHandle=sqlgethandle($dbh);
		$sq=$pdoHandle->prepare("SELECT sh.*, serverinfo.name, serverinfo.address, serverinfo.country FROM (SELECT * FROM serverhistory WHERE mapname=:map) AS sh LEFT JOIN serverinfo ON sh.serverid=serverinfo.serverid WHERE sh.date > ".(time()-86400*60)." AND serverinfo.lastscan > ".(time()-86400*14)." ORDER BY date DESC");
		$sq->bindParam(":map",$mapname);
		$sq->execute();

		$msh=$sq->fetchAll(PDO::FETCH_ASSOC);

		unset($sq);
		$mid=abs(crc32(strtolower($mapname)));
		
		
		$mapInfoObj = new UTT_MapInfo($mapname,$pdoHandle);
		//print_r($mapInfoObj);
		$hasReport = $mapInfoObj->hasReport;
		$reportVer = $mapInfoObj->reportVersion;
		$mi = $mapInfoObj->rawReport;
		$mapSize = $mapInfoObj->size;
		
		if(!count($msh) && !$hasReport && !isset($_GET['ignore404'])){
			die("404");
		}
		
		
		
		$knownPackagesLookup=array();
		foreach($knownPackages as $pakGroup=>$pakList){
			foreach($pakList as $pakName){
				$knownPackagesLookup[strtolower($pakName)]=$pakGroup;
			}
		}

		$title=$mapname." - Map info - ";
		
		
		
		$url = $mapInfoObj->downloadUrl;
		
		$mapBriefDesc="";
		if($hasReport){
			if(isset($mapInfoObj->author) && $mapInfoObj->author)
				$mapBriefDesc.=__("Map created by %1\$s",htmlspecialchars($mapInfoObj->author))."; ";
			if(isset($mapInfoObj->enterMessage) && $mapInfoObj->enterMessage)
				$mapBriefDesc.="''".htmlspecialchars($mapInfoObj->enterMessage)."''; ";
		}
		
			
		if($url!="" && $url!="NN") {
			printf($headerf,$title," gm-default","$mapBriefDesc".__("Download Map, View map details, Find Servers with Map '%1\$s'.",htmlspecialchars($mapname).""));
		}else{
			printf($headerf,$title," gm-default","$mapBriefDesc".__("View map details, Find Servers with Map '%1\$s'.",htmlspecialchars($mapname))."");
		}
		$jbsshot=false;
		if(file_exists("$utmpLoc/sshots/{$mapid}_jbmb1.jpg") && filesize("$utmpLoc/sshots/{$mapid}_jbmb1.jpg")>9){ // jb map banner
			echo "<img src=\"".maklinkHtml(LSTATICFILE,"jbbanner/".urlencode($mapid).".jpg",null)."\" alt=\"".__("JB Map Banner")."\" />";
			
			$jbsshot=true;
		}

		
		$simMaps = $mapInfoObj->findSimilarMaps();

		$layoutgenJobs=0;
			
		
		$polysExist = $mapInfoObj->hasPolys;
		$hasLayoutIso = $polysExist; //file_exists($layoutLocIso);
		$hasLayoutOrt = $polysExist; //file_exists($layoutLocOrt);
		$hasLayoutTibia = $polysExist; //file_exists($layoutLocTibia);

		//$downloadingMap = ($mapInfoObj->screenshotLoc === false) && !$polysExist;
		
		if($mapInfoObj->hasScreenshot){
			if($mapInfoObj->screenshotLoc !== false){
				$mapimg=maklinkHtml(LSTATICFILE,"mapsshot/".urlencode($mapid).".jpg",null);
			}else{
				$mapimg=maklinkHtml(LSTATICFILE,"mapnoimgyetC2.png",null);
			}
			
			echo "<div class='mappreview'>";
			echo "<img src=\"$mapimg\" alt='mapimage' class='mapimage' /><br>";
			if(file_exists("$utmpLoc/sshots/{$mapid}.txt")){
				echo "<small>".__("Screenshot from").": <a href=\"".htmlspecialchars($srcUrl=file_get_contents("$utmpLoc/sshots/{$mapid}.txt"))."\">".parse_url ($srcUrl, PHP_URL_HOST)."</a></small>";
			}
			echo "</div>";
		}
		echo "<div class='mapinfo'>";
		if(!$jbsshot) echo "<h1>$mapname</h1>";
		if(isset($_GET['redl'])){
			echo "MapRedlScheduled";
		}
		
		if($hasReport){
			
			if($mapInfoObj->title && $mapInfoObj->title!=$mapname)
				echo "<h2>".htmlspecialchars($mapInfoObj->title)."</h2>\n";
			if($mapInfoObj->enterMessage)
				echo "<p><q>".htmlspecialchars($mapInfoObj->enterMessage)."</q></p>\n";
			
			if($mapInfoObj->author)
				echo "<p><b>".__("Author")."</b>: ".htmlspecialchars($mapInfoObj->author)."</p>\n";
			
			if($mapInfoObj->idealPlayerCount)
				echo "<p><b>".__("Ideal player count")."</b>: ".htmlspecialchars($mapInfoObj->idealPlayerCount)."</p>\n";
			
			
		}
		
		if($isBT){
			$statement = $pdoHandle->prepare("SELECT * FROM `btrecords` WHERE `mapname` = :mapName");
			$statement->bindValue(":mapName",$mapname);
			$statement->execute();
			$btrecords = $statement->fetchAll(PDO::FETCH_ASSOC);
			if(count($btrecords)){
				echo "<p id='records'><b>".("World records").":</b></p>\r\n";
			foreach($btrecords as $rec){
				$recSrc=$btRecordSources[$rec['source']];
				$recMinutes = floor($rec['record']/60);
				$recSeconds = fmod($rec['record'],60);
				$recFullSeconds = str_pad(floor($recSeconds),2,"0",STR_PAD_LEFT);
				$recMiliSeconds = str_pad(round(fmod($recSeconds,1)*1000),3,"0",STR_PAD_LEFT);
								
				$recTimeFormatted = "<b>";
				//if($recMinutes) 
					$recTimeFormatted .= "$recMinutes:";
				
				$recTimeFormatted .= "$recFullSeconds</b>.$recMiliSeconds";
				echo "<p class='record'>";
				echo "<span class='recordTime' data-value='{$rec['record']}'>$recTimeFormatted</span> ";
				
				if(isset($recSrc['icon'])) 
					$srcString="<img src=\"".maklinkHtml(LSTATICFILE,"ext_favicons/".$recSrc['icon'],"")."\" alt=\"".htmlspecialchars($recSrc['name'])."\" title=\"".htmlspecialchars($recSrc['name'])."\" />";
				else
					$srcString=htmlspecialchars($recSrc['name']);
				echo "<a href=\"".maklinkHtml(LFILE,"search.php?playerSearch=" . urlencode($rec['player']),"") . "\" class=\"recordBy\">" . htmlspecialchars($rec['player']) . "</a> <span class='recordSrc recordSrcNum{$rec['source']}'>(<a href=\"{$recSrc['url']}\">$srcString</a>)</span></p>";
			}
			}
		}
		
		
		echo "</div>";
		if($utmdcInstalled && $url!="" && $url!="NN"){

			echo "<div class='mapdownload'>";
			require_once "ut_map_lookup.php";
			$msize=UTMDC\FileSize\getFileSize(html_entity_decode($url));
			echo " <a href='$url' class='mapdl'>".__('Download').($msize>0?" (".pretty_file_size($msize).")":"")."</a><br><small>".__('Source').": <a href='http://".parse_url ($url, PHP_URL_HOST)."/'>".parse_url ($url, PHP_URL_HOST)."</a></small>\n";
			echo "</div>";
		}
		
		
		
		echo "<div class='maplayout'>";
		

		if(!$mapInfoObj->hasLayout && !$mapInfoObj->hasPolys){
			echo "<h2>".__("Map layout")."</h2>\n";
			echo "<img src=\"".maklink(LSTATICFILE,"layoutdownloading.png",null)."\" alt=\"Looking for the map\layout!!\\\\\\UT Tracker is searching \for informations about\this map.\\Try refreshing the page \in a minute.\"/>";
		}else{
			echo "<h2>".__("Map layout")."</h2>\n";
			
			$m1 = pow($mapSize['x'],2) + pow($mapSize['y'],2);
			$m2 = 2* pow($mapSize['x'],2);
			
			if(($m1 > $m2 && $hasLayoutOrt) || !$hasLayoutIso) 
				$layoutImg = maklinkHtml(LMAPLAYOUT,"ort",$mapname);
			else 
				$layoutImg = maklinkHtml(LMAPLAYOUT,"iso3",$mapname);
			
			echo "<img src=\"".$layoutImg."\" alt=\"map layout is being generated... few more seconds...\"/><br>\r\n";
			//echo "<img src=\"".maklink(LSTATICFILE,"layout/".urlencode($mapname),null).".png\" alt=\"map layout is being generated... few more seconds...\"/><br>\r\n";
			echo "<b>".__("High res versions")." (1440p)</b><br>\r\n";
			if($hasLayoutOrt) echo "<a href=\"".maklinkHtml(LMAPLAYOUT,"ort",$mapname,array("fhd"=>""))."\">".__("Orthographic view (from top)")."</a><br>\r\n";
			if($hasLayoutIso) echo "<a href=\"".maklinkHtml(LMAPLAYOUT,"iso3",$mapname,array("fhd"=>""))."\">".__("Fake perspective (2.5D)")."</a><br>\r\n";
			if($hasLayoutTibia) echo "<a href=\"".maklinkHtml(LMAPLAYOUT,"tibia",$mapname,array("fhd"=>""))."\">".__("\"Tibia\" projection")."</a><br>\r\n";
			echo "<br>\r\n";
		}	
		if($hasReport){
			if(count($simMaps)){
				$simMapsHtml="";
				foreach($simMaps as $mapX){
					$simMapsHtml.="<span class=\"inYourFace\"><a href=\"".maklink(LMAP,null,$mapX['mapname'])."\">{$mapX['mapname']}</a></span> Similarity: ".round($mapX['similarityPercent']*100)."%<br>\r\n"; // "Power=(A=".round($similarity,2).",B=".round($similaritySecond,2)."),MapParams=(SX={$mapX['sizeX']},SY={$mapX['sizeY']},SZ={$mapX['sizeZ']},BCA={$mapX['brushCSGADD']},BCS={$mapX['brushCSGSUB']},ZON={$mapX['zones']},LW={$mapX['lightwattage']},TX={$mapX['numTextures']},CL={$mapX['numClasses']}),ReportVersion={$mapX['reportVersion']}<br>\n";

				}
				echo "<h3>".__("Similar map layouts")." (".__("not really accurate, but will be fixed in the future").")</h3>\r\n$simMapsHtml";

			}
			
		}else{
			echo "<b>".__("UTTracker has no detailed info about this map.")."</b><br/>\r\n";
		}
		
		echo "</div>";
		
		if($hasReport){
			echo "<h2>".__("Detailed info")."</h2>\n";
			echo "\n";

			if(isset($mi['flagdist'])) echo "<p id='mapFlagDist'>".("Distance between flags")." (*): ".round($mi['flagdist']*0.01905)." m / ".round($mi['flagdist']*0.0625)." ft</p>";
			// 16 unreal unit ~= 1 ft (0.3048 m)
			// 1 unreal unit ~= 0.0625 ft (0.01905 m)
					
			
			echo "<h3>".__("Inventory")."</h3>\n";
			
			/* ACTORS */
			foreach($inventoryTypes as $mainClass=>$subClasses){
				$inv=array();
				$invCT=array();
				foreach($subClasses as $utClass=>$displayClass){
					foreach($mi['actorsCount'] as $class=>$count){
						if(strtolower($utClass)==strtolower($class) && $count){
							if(isset($invCT[$displayClass])) $invCT[$displayClass]+=$count;
							else $invCT[$displayClass]=$count;
							
							if($displayClass=="NO") $disX="";
							else $disX=$displayClass;
							
							if(file_exists($assetsPathLocal . "/weapons/".strtolower($utClass).".png")){
								$inv[$displayClass]="<div class='uttr_skin' style=\"background: url(".maklink(LSTATICFILE,"weapons/".strtolower($utClass).".png","").");background-size: contain;\">{$invCT[$displayClass]}<br><br><br><br>$disX</div>";
							}else{
								$inv[$displayClass]="<div class='uttr_skin'>{$invCT[$displayClass]}<br><br><br><br>$disX</div>";							
							}
						}
					}
				}
				if(count($inv)) echo "<b>$mainClass</b>:<br>".implode("",$inv)."<br>\n";
			}
			if($mi['monstercount']) {
				echo "<h3>".__("Monsters").": {$mi['monstercount']}</h3>\n";
				
				if(isset($mi['monsterTypesCount']))
				foreach($mi['monsterTypesCount'] as $mt=>$mx){
					echo "<b>".htmlspecialchars($mt)."</b>: ";
					$mtx=array();
					
					foreach($mx as $mn=>$mc){
						$mtx[]="".htmlspecialchars($mn)." x$mc";
					}
					echo implode(", ",$mtx)."<br>\n";
					
				}
				
			}
			
			echo "<h3>".__("Other")."</h3>\n";
			echo "MapParams: 
			Size: ({$mapSize['x']},{$mapSize['y']},{$mapSize['z']}), 
			Brushes(A:{$mapInfoObj->brushCountAdd}, S:{$mapInfoObj->brushCountSub}), 
			Zones: {$mapInfoObj->zoneCount}, 
			Light Wattage: {$mapInfoObj->lightWattage}, 
			Textures: {$mapInfoObj->textureCount}, 
			Classes: {$mapInfoObj->classCount}<br>";
			echo __("Lighting palette").": ";
			
			$paletteHSV = array_map(function($col){
				$hsv = RGBToHSV($col);
				$hsv['col']=$col;
				return $hsv;
			}, $mapInfoObj->palette);
			
			usort($paletteHSV,function($a,$b){
				$hueA=$a[0];
				$hueB=$b[0];
				return cmp($hueA,$hueB);
			});
			$grayPalette=array();
			foreach($paletteHSV as $hsv){
				//$hsv=RGBToHSV($color);
				$color = $hsv['col'];
				if($hsv[1] < 0.3 || $hsv[2] < 20) {
					$grayPalette[]=$hsv;
					continue;
				}
				$multiplyV = 510/(255+$hsv[2]);
				$colorMul = ((($color & 0xFF0000) * $multiplyV) & 0xFF0000) | ((($color & 0xFF00) * $multiplyV) & 0xFF00) | ((($color & 0xFF) * $multiplyV) & 0xFF);
				echo "<div class=\"lightPaletteColor\" style=\"background: #".str_pad(dechex($colorMul),6,"0",STR_PAD_LEFT)."\"></div>";
			}
			usort($grayPalette,function($a,$b){
				$valA=$a[2];
				$valB=$b[2];
				return cmp($valA,$valB);
			});
			foreach($grayPalette as $hsv){
				$color = $hsv['col'];
				echo "<div class=\"lightPaletteColor\" style=\"background: #".str_pad(dechex($color),6,"0",STR_PAD_LEFT)."\"></div>";
			}
			echo "<br>";
			if(file_exists("$rendererLoc/mapdeps/{$mapid}.json")){
				echo __("Packages used").":<br/>";
				$paks=json_decode(file_get_contents("$rendererLoc/mapdeps/{$mapid}.json"),true);
				foreach($paks['packages'] as $pak){
					if(isset($knownPackagesLookup[strtolower($pak['name'])])){
						$usedPaks[]=$knownPackagesLookup[strtolower($pak['name'])];
					}else{
						$usedPaks[]=$pak['filename'];
					}
				}
				echo implode(", ",array_unique($usedPaks));
			}
		}
		
		$installedOn = indexaskey($msh,'serverid');
		if(count($installedOn)){
			echo "<h3>".__("Available on %1\$s servers",count($installedOn)).":</h3>\n";
			
			$pt=new TableThing($installedOn,"map-".$mapid."-installedOn");
			$pt->htmlClass="mapInstalledOn semihuge";
			$pt->htmlId="map-".$mapid."-installedOn";
			$pt->htmlIdColumn="serverid";
			$pt->dataLastUpdated=$lastscan;
			$pt->dontCache=true;
			
			$pt->setRowPreprocessorCallback(function($r)use($pt){
				if($r['address']=="") {
					$pt->skipRow();
					return null;
				}
				if(($country=$r['country']) != ""){
					//$cif="<img src='".maklink(LSTATICFILE,"flags/".strtolower($country).".gif")."' alt='$country' title='$country'/> ";
					$cif=getflag($country);
				}else{
					$cif="";
				}
				$r['serverNameH']="<a href=\"".maklink(LSERVER,$r['serverid'],$r['name'])."\">$cif".htmlspecialchars($r['name'])."</a>";
				$addressChunks = explode(":",$r['address']);
				$r['addressH']=$addressChunks[0] . ":" . ((int)$addressChunks[1]-1);
				$r['dateH']=niceDate($r['date']);
				
				return $r;
			});


			$cx=$pt->addColumn('serverNameH',"Server");
			$cx->contentType=TTCI::CONTENT_HTML;
			$cx->htmlClass="servName verylongtext";
			/*$cx=$pt->addColumn('addressH',"IP");
			$cx->contentType=TTCI::CONTENT_HTML;*/
			$cx=$pt->addColumn('dateH',"Last game");
			$cx->htmlClass="lastGame";
			$cx->contentType=TTCI::CONTENT_TEXT;
			$cx=$pt->addColumn('__iak_conflicts',"__iak_conflicts");
			$cx->contentType=TTCI::CONTENT_NUM;
			$cx->hidden=true;
			
			$pt->allowSorting=false;
			
			$pt->sort("__iak_conflicts",TableThing::SORT_DESC);

			echo $pt->genHTML(0,15);
		}
		

		if(isset($mi['author']) && $mi['author']!=null){
			$mappers = file($utmpLoc . "/mappers.txt");
			$mappersA=array();
			$mappersAliases=array();
			foreach($mappers as $mapperInfo){
				$mapperInfoA=trim(strtok($mapperInfo,"#"));
				if($mapperInfoA=="") continue;
				$mapperInfoB=explode("=",$mapperInfoA,2);
				if(count($mapperInfoB)<2) {
					$mapperAlias=$mapperInfoB[0];
					$mapperName=$mapperInfoB[0];
				}else{
					$mapperAlias=$mapperInfoB[0];
					$mapperName=$mapperInfoB[1];
					if(!isset($mappersAliases[$mapperName])){
						$mappersAliases[$mapperName][]=trim($mapperName,"|");
					}
				}
				$mappersAliases[$mapperName][]=trim($mapperAlias,"|");
				
				if(
					stripos("||".trim($mi['author'])."||",$mapperAlias)!==false ||
					stripos("||".trim($mi['author'])."||",$mapperName)!==false
				) 
				$mappersA[$mapperName]=true;
			}
			
			if(!count($mappersA)){
				$mappersA[$mi['author']]=true;
				$mappersAliases[$mi['author']]=array($mi['author']);
			}
			
			$noMapImgFile = maklink(LSTATICFILE,"fuckyouwaldo.png","");
			foreach($mappersA as $mapperName=>$nullx){

				
				$mapperBindings = array();
				$sql = "SELECT * FROM mapinfo WHERE (";
				
				$first = true;
				foreach($mappersAliases[$mapperName] as $mapperAlias){
					$mapperBindings[] = strtolower(trim($mapperAlias));
					if($first){
						$first = false;
					}else{
						$sql .= " OR ";
					}
					$sql .= "LOWER(author) LIKE :mapperAlias".(count($mapperBindings)-1);
					
				}
				$sql .= ") AND mapid <> :mapId";
				$statement = $pdoHandle->prepare($sql);
				
				$statement->bindValue(":mapId", $mid);
				foreach($mapperBindings as $idx=>$mapperAlias){
					$statement->bindValue(":mapperAlias$idx", $mapperAlias);
				}
				$statement->execute();
				
				$otherMapsBy = $statement->fetchAll(PDO::FETCH_ASSOC);
				
				if(count($otherMapsBy)){
				
					echo "<h3>".__("Other maps by %1\$s",htmlspecialchars(trim($mapperName,"|")))."</h3>\r\n";
					echo "<!-- sweet-ass over the top html5 map list -jutsu! -->\n";
					echo "<div class=\"light similarMaps\">\n";
					
					usort($otherMapsBy,function($a,$b){
						$posa = getFirstLetterOfMapName($a['mapname']);
						$posb = getFirstLetterOfMapName($b['mapname']);
						$cmp=strcasecmp($a['mapname'][$posa],$b['mapname'][$posb]);
						if($cmp==0) 
							$cmp=strcasecmp(substr($a['mapname'],$posa),substr($b['mapname'],$posb));
						return $cmp;
					});
					
					foreach($otherMapsBy as $mapInfo){
						//echo "<big><a href=\"".maklink(LMAP,$mapInfo['mapid'],$mapInfo['mapname'])."\">{$mapInfo['mapname']}</a></big><br>";
						$mapImgLocal = $utmpLoc . "/sshots/".name2id($mapInfo['mapname']).".jpg";
						$mapImgRemote = maklink(LSTATICFILE,"mapsshot/".name2id($mapInfo['mapname']).".jpg","");
						if(file_exists($mapImgLocal) && filesize($mapImgLocal)>0){
							$mapImg = $mapImgRemote;
						}else{
							$mapImg = $noMapImgFile;
						}
						
						$mapName = $mapInfo['mapname'];
						if(stripos($mapName,"CTF-BT-")===0) 
							$mapPrefix="CTF-BT";
						else
							$mapPrefix = strtok($mapName,"-");
						
						
						if($mapPrefix !== "" && $mapPrefix !== $mapName && strlen($mapPrefix) < 8){
							$mapPrefixSeparator = "-";
							$mapWithoutPrefix = substr($mapName,strlen($mapPrefix)+1);
						}else{
							if(stripos($mapName,"Dm")===0) {
								$mapPrefix=substr($mapName,0,2);
								$mapWithoutPrefix = substr($mapName,2);
								$mapPrefixSeparator="";
							}else{
								$mapWithoutPrefix=$mapName;
								$mapPrefix="";
								$mapPrefixSeparator="";
							}
						}
						/*$formattedMapName=str_replace(array('-','[',']','(',')',"_"),
													  array('-<wbr>','<wbr>[',']<wbr>','<wbr>(',')<wbr>','_<wbr>'),
													  htmlspecialchars($mapWithoutPrefix));*/
						$formattedMapName=wwwordOBreaker3000(htmlspecialchars($mapWithoutPrefix));
						
						$mapnameFirstLetter=$mapName[getFirstLetterOfMapName($mapName)];
						
						echo "<div class='smcell' style=\"background-image: url($mapImg)\">\n<a href='".maklink(LMAP,$mapInfo['mapid'],$mapInfo['mapname'],"ps")."'><span class=\"mapPrefx\">$mapPrefix</span><span class=\"mapPrefxSeparator\">$mapPrefixSeparator</span>".$formattedMapName."\n";
						echo "<div class='mapFirstLteerefdfdsgfds'>$mapnameFirstLetter</div></a>\n</div>\n";
						
					}
					echo "</div>\n";
					
				}
			}
			
			
		}
		
		
		
		sqldestroy($dbh);
	}catch(Exception $e){
		echo "EXC: ".$e->getMessage();
	}
	
	function RGBToHSV($col){
		$r=(($col >> 16) & 0xFF);
		$g=(($col >> 8) & 0xFF);
		$b=(($col) & 0xFF);
		
		$v = max($r,$g,$b);
		
		$d = $v - min($r,$g,$b);
		
		if($v != 0){
			$s = $d / $v;
			if($r == $v)
				$h = @(($g - $b) / $d);
			else if($g == $v)
				$h = 2 + ($b - $r) / $d;
			else
				$h = 4 + ( $r - $g) / $d;
			
			$h *= 60;
			if($h < 0)
				$h += 360;
		}else {
			$s = 0;
			$h = 0;
		}
		if(is_nan($h)) // '16-03-06: in PHP7 NaN breaks the sorting (/r/lolphp/comments/40se6f/)
			$h = 0; 
		
		return array($h,$s,$v);
		
	}
	
	
?><br>

(*) - according to <a href='http://wiki.beyondunreal.com/Legacy:Unreal_Unit'>Unreal Wiki</a>, 1 Unreal Unit = 0.75 inches.<br>


<small><?=$appCredits?></small>
<?php include "tracking.php"; ?>
</div>
</body>
</html>