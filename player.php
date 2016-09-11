<?php
/*
 * mta tracker
 * 2009 blackmore
 *
 * player stats page
**/
	date_default_timezone_set ('GMT'); //TODO move to cfg

	require_once "sqlengine.php";
	require_once "config.php";
	require_once "common.php";
	//require_once "nemotablething.php";
	require_once N14CORE_LOCATION . "/TableThing.php";
	addRewriteParam("id");
	
	use N14\TableThing as TableThing;
	use N14\TableThingColumnInfo as TTCI;
	
	TableThing::staticInit();
	TableThing::$dataDir = $dataDir;
	
	//$statdb="tracker.s3db";
	$dbh=sqlcreate($statdb_host,$statdb_user,$statdb_pass,$statdb_db);
	sqlexec($sqlAutoexec,0,$dbh);
	
	$tmplist="";
	$na="";
	$mat=array();
    /*if ($dh = opendir($statloc)) {
        while (($file = readdir($dh)) !== false) {
			if(filetype("$statloc\\$file")=="dir") continue;
			//$file="inside.txt";
			//$na=basename($file,".txt");
			$na=substr($file,0,strlen($na)-4);
			//echo "$na ".(crc32(strtolower($na))^43548366)."<br>";
			if((crc32(strtolower($na))^43548366) == $_GET['id']){
				break;
			}
			$na="";
		}
        closedir($dh);
	}
	if($na!=""){
		$fc=iconv ("windows-1250","utf-8",file_get_contents("$statloc\\$na.txt"));
		//echo $fc;
		preg_match_all  ('/([0-9\-]+ [0-9:]+) ([^\s]+) \| ([0-9\.]+):([0-9]+) \(\-\-\[(.*)\]\-\-\) \\\\frags\\\\([^\\\\]+)\\\\ping\\\\([^\\\\]+)\\\\mesh\\\\([^\\\\]+)\\\\skin\\\\([^\\\\]+)\\\\face\\\\'."([^\r]*)\r/",$fc,$mat,PREG_SET_ORDER);


	}*/
	
	$pid=(int)$_GET['id'];
	$pi=sqlquery("SELECT * FROM playerinfo WHERE id=$pid LIMIT 1",1);	
	
	if($pi['name']=="") error404("UTT'playerid:$pid'");
	
	$currenturl=strtok($_SERVER['REQUEST_URI'],"?");
	$expurl=strtok(maklink(LPLAYER,$pi['id'],$pi['name'],isset($_GET['s'])?$_GET['s']:""),"?");
	if(strpos($expurl,$currenturl)===false) {
		permredir($expurl);
	}
	
	
	$phx = sqlquery("SELECT playerstats . * , serverinfo.name AS `sname`  , serverinfo.address AS `address` , serverinfo.rules AS `rules` , serverhistory.mapname AS `mapname` , serverhistory.date AS `lastupdate`
		FROM playerstats
		LEFT JOIN serverinfo ON serverinfo.serverid = playerstats.serverid
		LEFT JOIN serverhistory ON serverhistory.gameid = playerstats.lastgame
		WHERE playerid = $pid");
	
	// '14-07-16 rewritting the monsters
	/*$ph=sqlquery("
	SELECT *, serverinfo.name AS `sname`,serverhistory.mapname AS `mapname` FROM playerhistory 
	LEFT JOIN serverinfo ON serverinfo.serverid=playerhistory.serverid 
	LEFT JOIN serverhistory ON serverhistory.gameid=playerhistory.gameid 
	WHERE id=$pid
	");*/
	
	
	
	
	// 1\ fetch all the player's matches
	/*$pgamesq1="SELECT gameid,enterdate,lastupdate FROM playerhistory WHERE id=$pid";
	$pgames=indexaskey(sqlquery($pgamesq1),"gameid");
	$gidnums=array();
	
	// 2\ turn gameids into a comma separated list
	foreach($pgames as $g){
		$gidnums[]=$g['gameid'];
	}
	sort($gidnums,SORT_NUMERIC);
	$pgameslist=implode(",",$gidnums);*/
	
	// 3\ find all other players who played in those matches
	/*$pgamesq2="SELECT id,enterdate,lastupdate,gameid,serverid FROM playerhistory WHERE gameid IN($pgameslist) ORDER BY gameid";
	$psharedgames=sqlquerytraversable($pgamesq2); // to avoid memory bloating
	$friendslastgameid=array();
	$friendslastserverid=array();
	$friendslastgamedata=array();
	$friendscommontimes=array();
	$friendscommongames=array();
	while(($row=sqlfetch($psharedgames))!==false){
		$gid=$row['gameid'];
		$fpid=$row['id'];
		if($fpid==$pid) continue;
		$pgame=$pgames[$gid];
		if(max($pgame['enterdate'],$row['enterdate'])<min($pgame['lastupdate'],$row['lastupdate'])){
			$row['commontime']=min($pgame['lastupdate'],$row['lastupdate'])-max($pgame['enterdate'],$row['enterdate']);
			if(!isset($friendscommontimes[$fpid])) $friendscommontimes[$fpid]=0;
			$friendscommontimes[$fpid]+=$row['commontime'];
			if(!isset($friendscommongames[$fpid])) $friendscommongames[$fpid]=0;
			$friendscommongames[$fpid]++;
			
			$friendslastgameid[$fpid]=$row['gameid'];
			$friendslastserverid[$fpid]=$row['serverid'];
			$friendslastgamedata[$fpid]=$row;
		}
	}
	// 4\ sort friends' by time spend with our player (descending)
	arsort($friendscommontimes,SORT_NUMERIC);
	$friendscommontimes=array_flip($friendscommontimes);
	
	//we'll only take 10 of them
	$friendscommontimes=array_slice ($friendscommontimes,0,10,true);
	
	// 5\query for friends' names
	$pgamesq3="SELECT id,name,skindata FROM playerinfo WHERE id IN(".implode(",",$friendscommontimes).")";
	$friendsinfo=indexaskey(sqlquery($pgamesq3),"id");
	
	// 6\and last matches info
	$pgamesq4="SELECT gameid,serverid,mapname FROM serverhistory WHERE gameid IN(".implode(",",array_unique($friendslastgameid)).")";
	$gamesinfo=indexaskey(sqlquery($pgamesq4),"gameid");
	
	// 6\+ servers info
	$pgamesq5="SELECT serverid,name,address FROM serverinfo WHERE serverid IN(".implode(",",array_unique($friendslastserverid)).")";
	$servers=indexaskey(sqlquery($pgamesq5),"serverid");
	
	$pf=array(); // here's our friend
	
	foreach($friendscommontimes as $ft=>$fid){
		$fgdata=$friendslastgamedata[$fid];
		$fgid=$friendslastgameid[$fid];
		//$crec=&$pf[$fid]; // notice the ampersand
		if(isset($pf[$fid])){
			$crec=$pf[$fid];
		}else{
			$crec=array();
		}
		$crec['fid']=$fid;
		$crec['fname']=$friendsinfo[$fid]['name'];
		$crec['fskin']=$friendsinfo[$fid]['skindata'];
		$crec['commontime']=$ft;//$friendslastgamedata[$fid]['commontime'];
		$crec['commonexit']=$friendslastgamedata[$fid]['lastupdate'];
		$crec['serverid']=$gamesinfo[$fgid]['serverid'];
		$crec['sname']=$servers[$gamesinfo[$fgid]['serverid']]['name'];
		$crec['gameid']=$fgid;
		$crec['mapname']=$gamesinfo[$fgid]['mapname'];
		$pf[$fid]=$crec;
		//echo "{$friendsinfo[$fid]['name']} => $ft (".$friendslastgameid[$fid].",".$gamesinfo[$friendslastgameid[$fid]]['mapname'].")<br>";
		//print_php($crec);
		//die;
	}
*/
	//exit;
	
	
	function sortpld($a,$b){
		return -cmp($a['last'],$b['last']);
	}
	function sortct($a,$b){
		return -cmp($a['time'],$b['time']);
	}



	$title=htmlspecialchars($pi['name'])." - ".__("Player info")." - ";
	$desc = sprintf(__("%1\$s's Player Statistics on Unreal Tournament."),$pi['name']);
	printf($headerf,$title,"",htmlspecialchars($desc));


//print_r($mat);


$sez=array();

if(isset($_GET['id'])){
	if(isset($mat)){
		//if(!isset($_GET['friends'])){
			$fabs = (strcasecmp($pi['name'],"fabs")===0);
			$last=0;
			$avgping=0;
			//$skins=array();
			
			/*foreach($ph as $l){
				$sein=$l['serverid'];
				if($sein != abs(crc32($l['address']))) continue; //workaround for a weird bug, sometimes $ph contains wrong server ids
				if(!isset($sez[$sein])){
					$sez[$sein]=array("name"=>$l['sname'],"serverid"=>$l['serverid'],"time"=>0,"avgp"=>0,"tf"=>0,"last"=>0,"pings"=>array(),'d'=>0,'lg'=>0);
				}
				//$sez[$sein]['avgp'] += (int)$l['pingsum'];
				$sez[$sein]['pings'][] = round($l['pingsum']/$l['numupdates']);
				$skinname=strtolower($pi['skindata']);
				
				$sez[$sein]['tf'] += $l['scorethismatch'];
				$sez[$sein]['time']+=$l['lastupdate']-$l['enterdate'];
				$sez[$sein]['d']+=$l['deathsthismatch'];
				if($sez[$sein]['lg']<$l['gameid']){
					$sez[$sein]['lg']=$l['gameid'];
					$sez[$sein]['lastmap']=$l['mapname'];
				}

				$sez[$sein]['last']=max($sez[$sein]['last'],$l['lastupdate']);
				$last=max($last,$l['lastupdate']);
				
			}*/
			$recs=0;
			$gamemodeHours = array();
			
			foreach($phx as $l){
				$sein=$l['serverid'];
				
				$srules = json_decode($l['rules'],true);
				$tags = getServerTags($srules,SERVERTAGS_GAMEMODE);
				
				if(!isset($gamemodeHours[$tags[0]])) $gamemodeHours[$tags[0]]=0;
				$gamemodeHours[$tags[0]] += $l['time'];
								
				//if($sein != abs(crc32($l['address']))) continue; //workaround for a weird bug, sometimes $ph contains wrong server ids
				if(!isset($sez[$sein])){
					$sez[$sein]=array("name"=>$l['sname'],"serverid"=>$l['serverid'],"address"=>$l['address'],"time"=>0,"avgp"=>0,"tf"=>0,"last"=>0,"pings"=>array(),'d'=>0,'lg'=>0);
				}
				//$sez[$sein]['pings'][] = round($l['pingsum']/$l['numupdates']);
				$skinname=strtolower($pi['skindata']);
				
				$sez[$sein]['tf'] += $l['score'];
				$sez[$sein]['time']+=$l['time'];
				$sez[$sein]['d']+=$l['deaths'];
				/*if($sez[$sein]['lg']<$l['gameid']){
					$sez[$sein]['lg']=$l['gameid'];
					$sez[$sein]['lastmap']=$l['mapname'];
				}*/
				
				$sez[$sein]['last']=max($sez[$sein]['last'],$l['lastupdate']);
				$last=max($last,$l['lastupdate']);
				$recs++;
			}
			unset($gamemodeHours['']);

			uasort($sez,'sortpld');
			uasort($gamemodeHours,function($a,$b){return $a+$b;});
			
			$hasGamingInfo = $recs > 0;
			

			//print_r($skins);
			//error_reporting(E_ERROR);
			echo "<h2 class='clear'>".__("Player info")."</h2>\n";
			echo "<div class='utt-avatar'>\n";
			/*if (count($skins)>1):
				echo "<h2>{$pi['name']}</h2>\n";
				echo "It seems like there are <b>".count($skins)."</b> different players with this name. Either that, or he/she's a shapeshifter.<br>\n";
				echo "Known skins: <br>\n";
				$max=0;
				$maxid='';
				
				foreach($skins as $i=>$ct){

					list($mesh,$skin,$face)=explode("|",$i,3);
					
					echo getSkinImage($mesh,$skin,$face);
					//echo "<li>$mesh / $skin / $face</li>\n";
				}
				echo "<br>\n";
			else:*/
				//echo "Skin: ".$last[8]." / ".$last[9]." / ".$last[10]."<br>\n";
				//echo "Skin: ".key($skins)."<br>\n";
				list($mesh,$skin,$face)=explode("|",$pi['skindata']);
				if($fabs){
					echo "<img src=\"" . maklink(LSTATICFILE,"tpb.jpg") . "\" class=\"uttr_skin imgleft\" alt=\"tpb\" />";
				}else{
					echo "\n".getSkinImage($mesh,$skin,$face,true)."\n";
				}
				
				
				
				echo "<span class='playername'>".htmlspecialchars($pi['name'])."</span>\n"; 
				
				if($fabs){
					echo "<h3>A.K.A. the user of illegally downloaded UT version uploaded by <a href=\"https://thepiratebay.se/torrent/3569812/Unreal_Tournament_GOTY_%28Game_of_the_year_edition%29_FULL_ENGLISH\">LordFabius</a>.</h3>\n";
				}
			//endif;
			if($pi['country']!=null){
				echo "<b>".__("Country").":</b> ".getflag($pi['country'])." ".countryName($pi['country'])."<br>\n";
			}
			$lastX = uttdateFmt($last);
			if($last==0) $lastX = "more than 3 months ago";
			if($hasGamingInfo) echo "<b>".__('Last seen online')."</b>: <span class='lastseen' data-time='$last'>".$lastX."</span><br>\n";
			echo "</div>\n";
			
			if($hasGamingInfo) {
				
				//print_php($ph);
				$favmaps=array();
				$favservs=array();
				$alltimeonline=0;
				foreach($phx as $pm){
					//if(!isset($favmaps[$pm['mapname']])) $favmaps[$pm['mapname']]=array('games'=>array(),'time'=>0);
					if(!isset($favservs[$pm['serverid']])) $favservs[$pm['serverid']]=array('games'=>array(),'time'=>0);
					/*$favmaps[$pm['mapname']]['name']=$pm['mapname'];
					$favmaps[$pm['mapname']]['games'][$pm['gameid']]=true;
					//$favmaps[$pm['mapname']]['time'] += $pm['lastupdate']-$pm['enterdate'];
					$favmaps[$pm['mapname']]['time'] += $pm['time'];*/
					
					$favservs[$pm['serverid']]['serverid']=$pm['serverid'];
					$favservs[$pm['serverid']]['address']=$pm['address'];
					$favservs[$pm['serverid']]['name']=$pm['sname'];
					//$favservs[$pm['serverid']]['games'][$pm['gameid']]=true;
					//$favservs[$pm['serverid']]['time'] += $pm['lastupdate']-$pm['enterdate'];
					$favservs[$pm['serverid']]['time'] += $pm['time'];
					
					//$alltimeonline+=$pm['lastupdate']-$pm['enterdate'];
					$alltimeonline+=$pm['time'];
				}
				usort($favmaps,function($a,$b){return $b['time']-$a['time'];});
				usort($favservs,function($a,$b){return $b['time']-$a['time'];});
				//print_r($favmaps);

				echo "<h2 class='clear'>".__('Various stats')."</h2>\n";
				echo "<b>" . __('All time online') . "</b>: " . formattime($alltimeonline/3600)."<br>";
				//echo "<b>" . __('Favorite server') . "</b>: <a href='" .maklink(LSERVER,$favservs[0]['serverid'],$favservs[0]['name'],"pf")."'>" . htmlspecialchars(unRetardizeServerName($favservs[0]['name'])) . "</a> (" . sprintf(__('%2$s'),count($favservs[0]['games']),formattime($favservs[0]['time']/3600)).")<br>";
				echo "<b>" . __('Favorite server') . "</b>: <a href='" .maklinkHtml(LSERVER,$favservs[0],null,"pf")."'>" . htmlspecialchars(unRetardizeServerName($favservs[0]['name'])) . "</a> (" . sprintf(__('%2$s'),0,formattime($favservs[0]['time']/3600)).")<br>";
				//echo "<b>" . __('Favorite map') . "</b>: <a href='" . maklink(LMAP,null,$favmaps[0]['name'],"pf") . "'>{$favmaps[0]['name']}</a> (" . sprintf(__('%2$s'),count($favmaps[0]['games']),formattime($favmaps[0]['time']/3600)).")<br>";
				foreach($gamemodeHours as $gs=>$gh){
					if($gh<5*60*60) unset($gs);
				}
				if(count($gamemodeHours)){
					echo "<b>" . __('Favorite game types') . "</b>:";
					foreach($gamemodeHours as $gs=>$gh){
						$gfn = isset($gameTypes[$gs])?$gameTypes[$gs]:$gs;
						echo "<a href='".makLinkHTML(LFILE,"",null,array("filter"=>$gs))."'><img src='$assetsPath/bitmaps/gm-".strtolower($gs).".png' alt='[$gs]' title='$gfn'/></a> ";
					}
				}
				echo "<br>";
				echo "<h2>".__('Visited servers')."</h2>\n";
				echo "<table class='huge' id='pservers'>\n";

				echo "<thead>\n\t<tr>\n\t\t<th>".__('Server name')."</th>\n\t\t<th>".__('Last game')."</th>\n\t\t<th>".__('All time online')." (*)</th>\n\t\t<th>".__('Average ping')."</th>\n\t\t<th>".__('Total frags')." (*)</th>\n\t\t<th>".__('Deaths')." (**)</th>\n\t</tr>\n</thead>\n";
				echo "<tbody>\n";
				$idx=0;
				foreach ($sez as $sid=>$d){
				//var_dump($d);
					if(++$idx > 8 ){
						echo "\t<tr class='srow'>
						<td colspan='6'>+".(count($sez)-8)." more...</td></tr>\n";
						break;
					}
				
					echo "\t<tr class='srow'>
					<td class='sname'><a href='".maklinkHtml(LSERVER,$d,null,"pv")."'> ".htmlspecialchars(unRetardizeServerName($d['name']))."</a></td>
					<td class='slastvisit'>".uttdateFmt($d['last'])."<br></td>
					<td class='stotaltime'>". 
					formattime($d['time']/3600) .
					"</td>
					<td class='sfrags'>".($d['tf'])."</td>
					<td class='sxdeaths'>".($d['d']>0 ? $d['d']:"-")."</td>
					</tr>\n";
					
				}
				echo "</tbody>\n";
				echo "</table>\n";
				
			
				echo "(*) = ".__('tracked since Apr \'14')."<br>\n";
				echo "(**) = ".__('only for servers with <a href=\'http://www.unrealadmin.org/forums/showthread.php?p=162211#post162211\'>XServerQuery</a> installed')."<hr/>\n";

				echo "<h2>Last games</h2>\r\n";
				
				$hist=sqlquery("
					SELECT *, serverinfo.name AS `sname`,serverinfo.address AS `address`,serverinfo.rules AS `rules`,serverhistory.mapname AS `mapname` FROM playerhistory 
					LEFT JOIN serverinfo ON serverinfo.serverid=playerhistory.serverid 
					LEFT JOIN serverhistory ON serverhistory.gameid=playerhistory.gameid 
					WHERE id=$pid
				");
				
				if(count($hist)){
				
					$tt=new TableThing($hist,"lastGames$pid");
					
					
					$cx=$tt->addColumn("enterdate",__("Date"));
					$cx->contentType=TTCI::CONTENT_NUM;
					
					$cx=$tt->addColumn("mapname",__("Map"));
					$cx->contentType=TTCI::CONTENT_HTML;
					
					$cx=$tt->addColumn("sname",__("Server"));
					$cx->contentType=TTCI::CONTENT_HTML;
					
					$cx=$tt->addColumn("scorethismatch",__("Frags"));
					$cx->contentType=TTCI::CONTENT_HTML;
					
					$cx=$tt->addColumn("rules");
					$cx->contentType=TTCI::CONTENT_NUM;
					$cx->hidden=true;
					
					$tt->setRowPreprocessorCallback(function($row){
						$row['sortable_enterdate']=$row['enterdate'];
						$row['enterdate']=uttdateFmt($row['enterdate']);
						$row['mapname']="<a href='".maklinkHtml(LMAP,"",$row['mapname'])."'>".htmlspecialchars($row['mapname'])."</a>";
						$row['sname']="<a href='".maklinkHtml(LSERVER,$row,null)."'>".htmlspecialchars(unRetardizeServerName($row['sname']))."</a>";
						$srules = json_decode($row['rules'],true);
						//$row['scorethismatch'] .= $srules['fraglimit'];
						$row['scorethismatch'] .= (isset($srules['fraglimit']) && $srules['fraglimit']>0 && $row['scorethismatch'] == $srules['fraglimit'] ? " &#x2776;":""); 
						
						return $row;
					});
					
					$tt->sort("enterdate",TableThing::SORT_DESC);
					$tt->isScrollable=false;
					$tt->dontCache=true;
					echo $tt->genHtml(0,10);
				
				}else{
					if($last < time()-90*86400){
						echo __("Gaming history older than 3 months is not available.")."<br>\r\n";
					}else{
						echo __("IMPOSSIBRU ERROR.")."<br>\r\n";
					}
				}
				
				
				// timeline was here

				/*--------- FRIENDS------------ */
				
				
				//echo "<a href='player.php?id=".(int)$_GET['id']."&friends'>[Click to generate data - MIGHT TAKE A FEW MINUTES]</a><br>";
				/*$bs=array();
				
				foreach($pf as $l){
					if(stripos($l['fskin'],"spectator")===0) continue;
					$phash=$l['fid'];
					if(isset($bs[$phash])) {
						$bs[$phash]['time']+=$l['commontime'];
					}else{
						$bs[$phash]=array('name'=>$l['fname'],'skin'=>$l['fskin'],'time'=>$l['commontime'],'h'=>$phash);
					}
					$bs[$phash]["games"][$l['gameid']]=true;
					$bs[$phash]["lastt"]=$l['commonexit'];
					$bs[$phash]["lastsv"]=$l['serverid'];
					$bs[$phash]["lastsvn"]=$l['sname'];
					$bs[$phash]["lastsm"]=$l['mapname'];
					$bs[$phash]["lastsg"]=$l['gameid'];
				}
				uasort($bs,'sortct');
				foreach ($bs as $k=>$d){
					if($d['time']<1800){
						unset($bs[$k]);
					}
				}
				
			
				if(count($bs)>0){
					echo "<h2>".__('Spends most time with').":</h2>\n";
					echo "<table class='huge' id='commongames'>\n";

					echo "<thead>\n\t<tr>\n\t\t<th>".__('Player name')."</th>\n\t\t<th>".__('Total time')."</th>\n\t\t<th>".__('Nr. of games')."</th>\n\t\t<th>".__('Last game')."</th>\n\t</tr>\n</thead>\n";
					
					echo "<tbody>\n";
					$cz=0;
					foreach ($bs as $d){
						
						if($d['time']>=1800){
							echo "\t<tr class='pfrow'>
							<td class='pfname'><a href='".maklink(LPLAYER,$d['h'],$d['name'],"pf")."'>".htmlspecialchars($d['name'])."</a></td>
							<td class='pftime'>".formattime($d['time']/3600) ."</td>
							<td class='pfservers'>".$friendscommongames[$d['h']] ."</td>
							<td class='pflast'>".uttdateFmt($d['lastt']) ."<br><small><a href='".maklink(LSERVER,$d['lastsv'],$d['lastsvn'],"pf")."'>".htmlspecialchars($d['lastsvn'])."</a><br><a href='".maklink(LGAME,$d['lastsg'],$d['lastsv']."-".name2id($d['lastsvn']),"pf")."'>".htmlspecialchars($d['lastsm'])."</a></small></td>
							</tr>\n";
						}
						if(++$cz>=5) break;
					}
					echo "</tbody>\n";
					echo "</table>";
				}*/
			//}else{
				
			//}
		}else{
			echo "<p>".__("UT Tracker has no info about this player's gaming history.")." <small>(this shouldn't be happening, report bug; phx=".count($phx).")</small></p>";
		}
		// SIMILAR NAMES (NOV '14)
		// if you don't know what Levenshtein distance is,
		// DON'T TOUCH ANYTHING BELOW 'TILL THE LINE WITH 'ENDOF SIMILAR NAMES'
		
		$namechunks=explode("-",name2id($pi['name']));
		
		// build WHERE `name` LIKE query
		$likePart="`name` LIKE ";
		$chunkz=0;
		$longestChunk="";
		foreach($namechunks as $chu){
			
			// wovsx removes all characters that aren't vowels 
			// likeator puts the percent sign between characters
						
			$likeatored=likeator(wovsx($chu));
			if(strlen($likeatored)>3){
				if($chunkz) $likePart.="\" OR `name` LIKE";
				$likePart.=" \"$likeatored";
				$chunkz++;
				if(strlen($longestChunk)<strlen($chu)){
					$longestChunk=$chu;
				}
			}
		}
		
		
		if($chunkz && strlen($likePart) > 10){
			if(strlen($longestChunk)>=8){ // additional chunk for long names
				$likeatored=likeator(wovsx(substr($longestChunk,0,5)));
				if($chunkz) $likePart.="\" OR `name` LIKE";
				$likePart.=" \"$likeatored";
				$namechunks[]=name2id($longestChunk);
			}
			$likePart.="\"";
			//echo $likePart;
			$simqw="SELECT * 
				FROM playerinfo
				WHERE ".$likePart."";

				//echo $simqw;
			$simpl=sqlquery($simqw);

			if(count($simpl) > 1){
				echo "<h3>".__("Other players with similar name")."</h3>\n";
				echo "<div class='light similarItemsWithAvaters'>\n";
				$siplX=array();
				foreach($simpl as $d){
					$d['lev']=levenshtein($pi['name'],$d['name']);
					if($d['lev']==0) continue;
					foreach($namechunks as $chu){
						if(stripos($d['name'],$chu)!==false) $d['lev']-=pow(strlen($chu),1.38);
					}
					$siplX[$d['id']]=$d;
				}
				
				uasort($siplX,function($a,$b){return $a['lev']-$b['lev'];});
				$i=0;
				foreach($siplX as $d){
					if($d['lev']>strlen($d['name'])*0.75) continue;
					if($d['id']==$pi['id']) continue;
					list($ms,$sk,$fc)=explode("|",$d['skindata']);
					$flag=getflag($d['country']);
					echo "<div class='splcell'>\n<div class='splavatar'>".getSkinImage($ms,$sk,$fc)."</div>\n<div class='splinfo'>\n<a href='".maklink(LPLAYER,$d['id'],$d['name'],"ps")."' class='plist_player utt-avatar'><span class='itemname'>$flag".htmlspecialchars($d['name'])."</span>\n";
					echo "</a>\n</div>\n</div>\n";
					if(++$i>=9) break;
				}
				echo "</div>\n";
			}
		}
		// ENDOF SIMILAR NAMES

	}
}
else{

}
?><br><br>
<small><?=$appCredits?></small>
<?php 

include "tracking.php";

?>
</div>
</body>
</html>