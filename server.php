<?php
/*
 * mta tracker
 * 2009 blackmore
 *
 * player list for server
**/
	
	date_default_timezone_set ('GMT'); //TODO move to cfg
	
	
	require_once "config.php";
	require_once "sqlengine.php";
	require_once "geoiploc.php";
	
	$rewriteParams['serv']=true;
	$rewriteParams['ip']=true;
	$rewriteParams['page']=true;
	$bodyclass="dark";
	
	require_once "common.php";
	//require_once "updater_php/updater.php";

	require_once N14CORE_LOCATION . "/TableThing.php";

	
	use N14\TableThing as TableThing;
	use N14\TableThingColumnInfo as TTCI;
	
	TableThing::staticInit();
	TableThing::$dataDir = $dataDir;

	$dbh=sqlcreate($statdb_host,$statdb_user,$statdb_pass,$statdb_db);
	$pdoHnd=sqlgethandle($dbh);
	
	/*try{
		$utt_cfg = new N14\INICache($config_ini);
	}catch(N14\INIException $exc){
		$utt_cfg = null;
	}*/
	 
	$blacklist=explode("\r\n",file_get_contents("blacklist.txt")); // TODO func/class?
	$blacklistReasons=array();
	foreach($blacklist as $k=>$be){
		$be=trim(strtok($be,"#"));
		$blDescr=trim(strtok("\r"));
		$ipw=explode(":",$be,2);
		if(count($ipw)!=2 || ip2long($ipw[0])===false || !is_numeric($ipw[1])){
			
			unset($blacklist[$k]);
			}else{
			$blacklist[$k]="{$ipw[0]}:{$ipw[1]}";
			$blacklistReasons[$k]=$blDescr;
		}
	}



$pageParam=isset($_GET['page']);
$showRules = $pageParam && $_GET['page']=='rules';
$showPlayers = !$showRules;
$requestingRescan = $pageParam && $_GET['page']=='refresh';

if(isset($_GET['ip'])){
	$ipAddr=strtok($_GET['ip'],":");
	$port=strtok("\r");
	$sid=abs(crc32($ipAddr . ":" . ($port+1)));
	$_GET['serv'] = $sid;
}

if(isset($_GET['serv'])){
	$sid=(int)$_GET['serv'];
	unset($_GET['serv']);
	$updrate=2;//$utt_cfg['General']['IntervalMins'];
	//T: 63 ms
	/*$s=sqlquery("SELECT serverinfo.`name` as n,serverinfo.`serverid` as sid, serverinfo.`address` as ip, serverinfo.`rules` as rules, serverhistory.`mapname` as map, 
	serverhistory.`gameid` as gameid,`uplayers`,
	FIND_IN_SET( rfscore, (	SELECT GROUP_CONCAT( rfscore ORDER BY rfscore DESC ) FROM serverinfo WHERE lastscan > ".(time()-86400*7)." ) ) AS rank FROM serverinfo
	LEFT JOIN serverhistory ON serverhistory.serverid=serverinfo.serverid WHERE serverinfo.serverid=$sid",1,$dbh);
	
	*/
	
	//$si=sqlquery("SELECT `name` as n,`address` as ip,`serverid` as sid,`uplayers`,`rules`,`lastscan`, FIND_IN_SET( rfscore, ( SELECT GROUP_CONCAT( rfscore ORDER BY rfscore DESC ) FROM serverinfo WHERE lastscan > ".(time()-86400*7)." ) ) AS rank FROM serverinfo WHERE serverid=$sid",1,$dbh);
	$si=sqlquery("SELECT `name`,`address`,`name` as n,`address` as ip,`serverid` as sid,`uplayers`,`rules`,`lastscan`,`gamename` FROM serverinfo WHERE serverid=$sid",1,$dbh);
	$sh=sqlquery("SELECT `mapname` as map, `gameid` FROM serverhistory WHERE serverid=$sid ORDER BY gameid DESC limit 1",1,$dbh);

	
	if($sh!=null){
		$s=($si+$sh);
	}else{
		$s=$si;
	}
	if($s['rules']!=""){
		$srules=json_decode($s['rules'],true);
		$noData = $srules == null || !isset($srules['hostname']);
		ksort($srules);
		if(!isset($s['gamename']) && $srules['gamename']!=null) $s['gamename'] = $srules['gamename'];
		$notUnr = $s['gamename']!=null && $s['gamename'] != "unreal" && $s['gamename'] != "ut" && $s['gamename'] != "ut2" && $s['gamename'] != "ut2004";
		
	}else{
		$noData = true;
		//print_r($s);
	}
	
	$ipCh = explode(":",$s['ip']);
	if(isset($srules['hostport']) && ($ipCh[1]-1) != $srules['hostport']){
		//echo "WR";
		$s['address']=$ipCh[0] . ":" . ($srules['hostport']+1);
		permredir(maklink(LSERVER,$s));
	}
	
	//echo time()-$s['datex'];
	/*if(isset($_GET['refresh'])){
		FetchServerInfo($s['ip']);
		$s=sqlquery("SELECT serverinfo.`name` as n,serverinfo.`serverid` as sid, serverinfo.`address` as ip, serverinfo.`rules` as rules, serverhistory.`mapname` as map, serverhistory.`gameid` as gameid FROM serverinfo
		LEFT JOIN serverhistory ON serverhistory.serverid=serverinfo.serverid WHERE serverinfo.serverid=$sid",1,$dbh);
	}*/
	//utt_checkpoint("uglyquerymadeintotwoqueries");
	
	$banned=false;
	$bannedReason="";
	foreach($blacklist as $k=>$bk){
		if($bk==$s['ip']){
			$banned=true;
			$bannedReason=$blacklistReasons[$k];
			break;
		}
	}
	
	if($s['n']==""){
		error404();
	}
	$expurl=maklink(LSERVER,$s);
	$expUrlParset = parse_url($expurl);
	
	$expUrlGoofified = $expUrlParset['host'].urldecode($expUrlParset['path']);
	
	$curUrl = "http://{$_SERVER['HTTP_HOST']}{$_SERVER['REQUEST_URI']}";
	$curUrlParset = parse_url($curUrl);
	$curUrlGoofified = $curUrlParset['host'].urldecode($curUrlParset['path']);
	
	/*echo "CUR URL: $curUrlGoofified<br>";
	echo "EXP URL: $expUrlGoofified<br>";
	echo "MATCH: " . ((strpos($curUrlGoofified,$expUrlGoofified))!==false ? "TRUE" : "FALSE");*/
	if(strpos($curUrlGoofified,$expUrlGoofified)===false) permredir($expurl);

	/*
	$pd=sqlquery("SELECT *,playerinfo.`name` as `name` FROM playerhistory LEFT JOIN playerinfo ON playerhistory.`id`=playerinfo.`id` WHERE `serverid`=$sid ORDER BY `date` DESC");
	$po=sqlquery("SELECT *,playerinfo.`name` as `name` FROM playersonline LEFT JOIN playerinfo ON playersonline.`id`=playerinfo.`id` WHERE `serverid`=$sid ORDER BY `lastupdate` DESC");
	*/
	
	// MONSTER BELOW - DON'T TOUCH!
	
	$si['mutators']=isset($srules['mutators'])?$srules['mutators']:"";
	$si['gametype']=isset($srules['gametype'])?$srules['gametype']:"";
	$si['mapname']=isset($srules['mapname'])?$srules['mapname']:"";
	$si['gamever']=isset($srules['gamever'])?$srules['gamever']:"";
	$si['name']=$si['n'];
	$st=getServerTags($si);
	$stInv = array_flip($st);
	$isCompetitiveGame = isset($stInv['DM']) || isset($stInv['MH']);
	$isSiege = isset($stInv['SGI']);
	$isMH = isset($stInv['MH']);
	$isTDM = isset($stInv['TDM']);
	$isCTF = isset($stInv['CTF']);
	
	
	$bclass=" gm-".strtolower($st[0])."";
	for($i=1; $i<count($st);$i++){
		$bclass.=" gma-".strtolower($st[$i])."";
		
	}
	if($notUnr){
		$bclass .= " nonUnr calm";
		$noUTTCSS = true;
	}else{
		$bclass .= " rage";

	}
	
	$title=cp437toentity(htmlspecialchars(unRetardizeServerName($s['n'])))." - ";
	printf($headerf,$title,$bclass,"View Server Stats, Player Ranks and more for server ".htmlspecialchars($s['n']).".");
	
	//T: 288 ms (lowest) - ~4000 ms (highest) <- rewrite!!
	/*$pdxt=sqlquery("SELECT id,sum(numupdates) as `numupdates`,sum(deathsthismatch) as `deathsthismatch`,sum(scorethismatch) as `scorethismatch`,sum(time) as `time`,name,skin,country,lastupdate,team FROM (
		SELECT ph.id as `id`,sum(ph.numupdates) as `numupdates`, 
		sum(ph.deathsthismatch) as `deathsthismatch`, sum(ph.scorethismatch) as `scorethismatch`, max(ph.lastupdate) as `lastupdate`, 
		sum(ph.lastupdate-ph.enterdate) as `time`,team,
		pi.name as `name`, pi.skindata as `skin`,pi.country as `country` FROM playerhistory AS ph
		LEFT JOIN playerinfo AS pi ON ph.`id`=pi.`id` WHERE `serverid`=$sid  GROUP BY ph.`id`
		UNION ALL
		SELECT ph.id as `id`,sum(ph.numupdates) as `numupdates`, 
		sum(ph.deathsthismatch) as `deathsthismatch`, sum(ph.scorethismatch) as `scorethismatch`, max(ph.lastupdate) as `lastupdate`, 
		sum(ph.lastupdate-ph.enterdate) as `time`,team,
		pi.name as `name`, pi.skindata as `skin`,pi.country as `country` FROM playerhistorythin AS ph
		LEFT JOIN playerinfo AS pi ON ph.`id`=pi.`id` WHERE `serverid`=$sid GROUP BY ph.`id`) GROUP BY `id`",null,$dbh);*/
	
	// '14-07-17 action turtle->flash!!
	// before: T: 3435 ms
	// after: BENCH RESULT: 912.11 ms
	// note: UNION removes duplicated values (slow), use UNION ALL
	// ^ everything above, for sqlite, it is.
	// mysql: Checkpoint playerhistory: 37282ms (+37136ms)
	
	//$qs1="SELECT * FROM playerhistory WHERE serverid=$sid UNION ALL SELECT * FROM playerhistorythin WHERE serverid=$sid ORDER BY gameid DESC";
	/*$qs1a="SELECT * FROM playerhistory WHERE serverid=$sid";
	// DO YOUR INDEXING GOOD F*CKER!
	//$qs1a="";
	$qs1b="SELECT * FROM playerhistorythin WHERE serverid=$sid";
	//$qr1=sqlquerytraversable($qs1,$dbh);*/
	
	$qs1="SELECT * FROM playerstats WHERE serverid=$sid";

	
	$pdxt=array();

	/*$qr1func=function($phgame,&$pdxt){
		if(!isset($pdxt[$phgame['id']])) {
			//if(count($pdxt)>=200) continue;
			$pdxt[$phgame['id']]=array('id'=>$phgame['id'],'time'=>0,'lastupdate'=>0,'enterdate'=>0xFFFFFFFF,'numupdates'=>0,'pingsum'=>0,'deathsthismatch'=>0,'scorethismatch'=>0,'games'=>0,'team'=>$phgame['team']);
		}
		//notice ,-reference!
		$playz = &$pdxt[$phgame['id']];
		$playz['time']+=$phgame['lastupdate']-$phgame['enterdate'];
		$playz['enterdate']=min($playz['enterdate'],$phgame['enterdate']);
		$playz['lastupdate']=max($playz['lastupdate'],$phgame['lastupdate']);
		$playz['pingsum']+=$phgame['pingsum'];
		$playz['deathsthismatch']+=(int)$phgame['deathsthismatch'];
		$playz['scorethismatch']+=(int)$phgame['scorethismatch'];
		$playz['numupdates']+=(int)$phgame['numupdates'];
		$playz['games']++;
	};*/
	$refreshData=isset($_GET['ttrefresh'])?true:(TableThing::getCachedTableAge('servplayers'.$sid)>86400);
	
	//echo "AGE:".TableThing::getCachedTableAge('servplayers'.$sid)." FILE: ".(TableThing::$dataDir."/table_".TableThing::genStaticUniqId('servplayers'.$sid).".json")."";
	
	if($refreshData && !$banned && $showPlayers){
		utt_checkpoint("lecimy_kurka_tutaj");
		$qr1a=sqlquerytraversable($qs1,null,$dbh); 
		utt_checkpoint("playerhistoryQwery");
		while(($phgame=sqlfetch($qr1a))!==false){

			if(!isset($pdxt[$phgame['playerid']])) {
				//if(count($pdxt)>=200) continue;
				$pdxt[$phgame['playerid']]=array(
					'id'=>$phgame['playerid'],
					'time'=>0,
					'lastupdate'=>0,
					'enterdate'=>0xFFFFFFFF,
					'numupdates'=>0,
					'pingsum'=>0,
					'deathsthismatch'=>null,
					'scorethismatch'=>0,
					'games'=>0,
					'team'=>isset($phgame['team']) ? $phgame['team'] : 0
				);
			}
			$playz = &$pdxt[$phgame['playerid']];
			$playz['time']=$phgame['time'];
			//$playz['pingsum']=$phgame['pingsum'];
			$playz['deathsthismatch']=$phgame['deaths'];
			$playz['scorethismatch']=$phgame['score'];
			$playz['numupdates']=$phgame['numupdates'];
			if($playz['time']<0) $playz['time']=$playz['numupdates']*120;
			
			//$playz['games']++;
		}
		
		
		utt_checkpoint("playerhistorythinLoop");
		$playz=null; //get rid of reference
		
		$playerids=array_keys($pdxt);
		if(count($playerids)){
			$qs2="SELECT * FROM playerinfo WHERE id IN(".implode(",",$playerids).")";
			$qr2=sqlquerytraversable($qs2,$dbh); // T: 21 ms
			
			while(($pinfo=sqlfetch($qr2))!==false){
				if($pdxt[$pinfo['id']]['id']!=$pinfo['id']) continue;
				$ply = &$pdxt[$pinfo['id']];
				$ply['name']=$pinfo['name'];
				$ply['skin']=$pinfo['skindata'];
				$ply['country']=$pinfo['country'];

			}
			$ply=null;
			$pdxt=array_values($pdxt);
		}else{
			$pdxt=array();
		}
	}

	$sq1="SELECT * FROM serverhistory WHERE serverid=$sid ORDER BY date DESC LIMIT 10";
	$qr1=sqlquery($sq1,null,$dbh);
	$gids=array();
	$smh=array();
	foreach($qr1 as $v){
		$gids[]=$v['gameid'];
		$smh[$v['gameid']]=$v;
		$smh[$v['gameid']]['players']=0;
		$smh[$v['gameid']]['score']=0;
		$smh[$v['gameid']]['deaths']=0;
		$smh[$v['gameid']]['time']=0;
		$smh[$v['gameid']]['lastplayerupdate']=0;
		$smh[$v['gameid']]['topplayer']=null;
		$smh[$v['gameid']]['topplayerscore']=0;
	}
	$sq2="SELECT playerhistory.*, playerinfo.* FROM playerhistory LEFT JOIN playerinfo ON playerhistory.id = playerinfo.id WHERE gameid IN(".implode(",",$gids).")";
	$qr2=sqlquery($sq2,null,$dbh);
	foreach($qr2 as $pv){
		$gid=$pv['gameid'];
		$smh[$gid]['players']++;
		$smh[$gid]['score']+=$pv['scorethismatch'];
		$smh[$gid]['deaths']+=(int)$pv['deathsthismatch'];
		$smh[$gid]['lastplayerupdate']=max($pv['lastupdate'],$smh[$gid]['lastplayerupdate']);
		$smh[$gid]['time']=$smh[$gid]['lastplayerupdate']-$smh[$gid]['date'];
		if($smh[$gid]['topplayerscore']<$pv['scorethismatch']){
			$smh[$gid]['topplayer']=$pv;
			$smh[$gid]['topplayerscore']=$pv['scorethismatch'];
		}
	}
	$smh=array_values($smh);

	
	
	
	if($s){

		echo "<h2>".cp437toentity(htmlspecialchars($s['n']))."</h2>";
		$lastupdx=0;
		if($s['lastscan']) $lastupdx=$s['lastscan'];
		if(isset($srules['__uttlastupdate'])) $lastupdx=max($lastupdx,$srules['__uttlastupdate']);
		if($lastupdx==0) $lastupdx=$smh[0]['date'];
		$rplast=sqlquery("SELECT `data` FROM `utt_info` WHERE `key`=\"net.reaper.lastupdate\"",1)['data'];
		$offline= (abs($rplast - $lastupdx) > 600) && $lastupdx < time() - 600;
		$unknown = time()-$rplast > 600 && time()-$lastupdx > 600;
		$oldUTT = $lastupdx == 0 && count($srules)==0;
		$lastUpdateSeconds=time()-$lastupdx;
		echo __("Status").": ";
		if($unknown){
			echo __("Unknown")." <small>(";
			printf(__('server scanner might not be working, last successful scan was %1$s ago'),($lastUpdateSeconds>86400?formattime($lastUpdateSeconds/3600):formattimesmall($lastUpdateSeconds)));
			echo ")</small>";
		}else if($offline){
			echo __("Offline")."  <small>(";
			
			printf(__('last seen online %1$s ago'),($lastUpdateSeconds>86400?formattime($lastUpdateSeconds/3600):formattimesmall($lastUpdateSeconds)));
			//echo "; <a href='".maklinkHtml(LSERVER,$s,null,array('page'=>'refresh'))."'>".__("click here to check it again")."</a>";
			echo ") </small>";
			//echo $s['address'];
			if($requestingRescan){
				list($ipa,$portq)=explode(":",$s['ip'],2);
				
				if(isset($srules['queryport'])) $portq=$srules['queryport'];
				//sqlexecnow("INSERT INTO `serverqueue` (`address`,`flags`) VALUES (\"" . mysql_real_escape_string($ipa.":".$portq) . "\", 1)");
				$statement=$pdoHnd->prepare("INSERT INTO `serverqueue` (`address`,`flags`) VALUES (:address,:flags)");
				$statement->bindValue(":address",$ipa.":".$portq);
				$statement->bindValue(":flags",1);
				$statement->execute();
				unset($statement);
				
				
				echo "\r\n<br><span class=\"inYourFace\">".__("The server will be checked during the next scan.")."</span>\r\n";
			}
		}else{
			echo "<b>".__("Online")."</b> <small>(";
			printf(__('updated %1$s ago'),($lastUpdateSeconds>86400?formattime($lastUpdateSeconds/3600):formattimesmall($lastUpdateSeconds)));
			echo ")</small>";
		}
		//$en = sqlquery("SELECT * FROM `tinyscanschedule` where `address`='{$s['address']}'",1);
		//print_r($en);
		//if(isset($en['time'])) echo (time() - $en['time']);
		
		/*echo "<br>SCANNERLAST=$rplast"; 
		if(isset($srules['__uttlastupdate'])) echo "<br>RULES_LASTUPD=".$srules['__uttlastupdate'];
		
		echo "<br>SMH_LASTUPD=".$smh[0]['date'];
		echo "<br>LASTUPDX=$lastupdx";*/
		if($notUnr) {
			echo "<p><b>".__("This server is not an Unreal/UT related server, and I have absolutely no idea how did it get here. Some informations on this page might not be displayed properly, additional info might be found on: %1\$s page.","<a href=\"".maklinkHtml(LFILE,"{$rqv}",null,array('page'=>'rules'))."\">".__("Server variables")."</a>")."</b></p>\r\n";
		}
		if($offline){
			echo "<p><b>".__("This server is currently unavailable, or could not be scanned by UT Tracker. The information below was archived on %1\$s, when the server was still online.",uttdateFmt($lastupdx))." <a href='".maklinkHtml(LSERVER,$s,null,array('page'=>'refresh'))."'>".__("Click here to check if the server is back online.")."</a></b></p>\r\n";
		}

		list($ipa,$port)=explode(":",$s['ip'],2);
		
		$portx=((int)$port-1);
		if(isset($srules['hostport'])) $portx=$srules['hostport'];
		
		$ip=$ipa.":" . $portx;
		
		$addit = $notUnr?"Kitty":"C2";
		
		
		
		if(!$noData){
			if(isset($srules['mapname']) && !$offline){
				$mapid=name2id($srules['mapname']);
				echo "<div class='mappreview'>";
				
				if($utmpInstalled){
					if(file_exists("$utmpLoc/sshots/{$mapid}.jpg") && filesize("$utmpLoc/sshots/{$mapid}.jpg")>9){
						$mapimg=maklink(LSTATICFILE,"mapsshot/".urlencode($mapid).".jpg",null);
					}else{
						if(!file_exists("$utmpLoc/sshots/{$mapid}.jpg")){
							// '16-03-26 Operation "Great, PHP finally got rid of MySQL extension, it's time to rewrite that old code"
							// The following commented code is left here as my punishment for using deprectated functions for long time. Learn from my mistakes.
							//sqlexec("INSERT INTO mapdownloadqueue (`mapname`) VALUES (\"".@mysql_real_escape_string($srules['mapname'])."\")");
							
							
							$mapDQStat=$pdoHnd->prepare("INSERT INTO mapdownloadqueue (`mapname`) VALUES (:map)");
							$mapDQStat->bindParam(":map",$srules['mapname']);
							$mapDQStat->execute();
							unset($mapDQStat);
							
							
							$mapimg=maklink(LSTATICFILE,"mapnoimgyet$addit.png",null);
							$downloadingMap=true;
						}else{
							$mapimg=maklink(LSTATICFILE,"mapnoimgyet$addit.png",null);
						}
					}
				}else{
					$mapimg=maklink(LSTATICFILE,"mapnoimgyet$addit.png",null);
				}
				echo "<img src=\"$mapimg\" alt='mapimage' class='mapimage' />";
				
				echo "<br><a href=\"".maklinkHtml(LMAP,0,$srules['mapname'],"smp")."\" class='maplink'>{$srules['mapname']}</a>\n";
				
				echo "</div>";
			}
		}
		
		echo "<div class='mapinfo'><p>";
		
		if($s['gamename'] == 'ut' || $s['gamename']==null) 
			$proto="unreal";
		else
			$proto=$s['gamename'];
		
	
		echo __('Server address').": <a href=\"$proto://$ip\">$proto://$ip</a>";
		echo "</p>\n";
			
		if(!$noData){
			$playerNum = (isset($srules['__uttrealplayers'])?$srules['__uttrealplayers']:$srules['numplayers']);
			
			if( !$offline)
				echo "<p>".__("Players online").": ".$playerNum." / {$srules['maxplayers']}</p>";
				
			$uniqplayers=$s['uplayers'];
			if(!$banned){
				
				$scount=sqlquery("SELECT count(*) as `ct` FROM serverinfo WHERE lastscan>".(time()-3600),1)['ct'];
				//if($s['rank']) $rank=$s['rank']; else $rank="-";
				//echo "<p>".__("Rank").": $rank of $scount</p>\n";
				//echo "<p>".__("Unique players").": {$s['uplayers']}</p>\n";
				
				
				
				
			}
		}else{
			echo "<p><b>The server scanner could not get any information about this server.</b></p>";
		}
		
		
		
		if(isset($srules) && !$offline && !$noData){
			
			if(isset($srules['adminname']) && isset($srules['adminemail'])) echo "<p>".__('Admin').": {$srules['adminname']}</p>\n";
			if(isset($srules['homepage']) && strpos($srules['homepage'],".")!==false && strpos($srules['homepage']," ")===false) {
				$fullurl=$srules['homepage'];
				if(stripos($srules['homepage'],"http://")!==0 && stripos($srules['homepage'],"https://")!==0){
					$fullurl="http://$fullurl";
				}
				echo "<p>".__('Website').": <a href=\"".htmlspecialchars($fullurl)."\">".htmlspecialchars($srules['homepage'])."</a></p>\n";
			}
			$xsq=(isset($srules['__uttxserverquery']) && $srules['__uttxserverquery']==="true");
		
			/*if(!isset($srules['maptitle']) || $srules['maptitle']=="Untitled") $mapn=$smh[0]['mapname'];
			else $mapn=$srules['maptitle']." ({$smh[0]['mapname']})";
			echo "<p>".__('Map name').": <a href=\"".maklink(LMAP,0,$srules['mapname'])."\">$mapn</a></p>\n";*/
			$gameTimes = getMatchTimesFromRules($srules);
			//print_r($gameTimes);
			if(isset($gameTimes['remaining']) && $gameTimes['timeLimit'] > 0) {
				//$rtm=$lastupdx+$srules['remainingtime']-time();
				
				
				$rtm = $gameTimes['remaining'];
				$timebarClass="remainingTime";
				
				//xserverquery doesn't handle overtime well
				if($xsq && $rtm > $gameTimes['timeLimit']){
					$gameTimes['state'] = "unknown";
					$rtm = -$rtm;
				}			
				
				if($gameTimes['state']=="unknown"){
					if($rtm>0) {
						$rtm=shortTimeInterval($rtm);
					}else if($rtm > -240){
						$rtm=__("overtime or map vote");
						$timebarClass.=" overtime";
					}else{
						$rtm=__("overtime");
						$timebarClass.=" overtime";
					}
				}else{
					switch($gameTimes['state']){
						case "waiting": 
							$rtm=__("waiting for start...");
							$timebarClass.=" overtime";
							break;
						case "game": 
							$rtm=shortTimeInterval($rtm);
							break;
						case "ended": 
							$rtm=__("ended");
							$timebarClass.=" overtime";
							break;
						case "overtime": 
							$rtm=__("overtime");
							$timebarClass.=" overtime";
							break;
					}
				}
				
				$remainingBarWidth = round($gameTimes['remaining'] / $gameTimes['timeLimit'] * 100, 1) . "%";
				
				echo "<p>".__('Remaining time').": <span class='$timebarClass'><span class='remainingTimeBar' style='width: $remainingBarWidth'></span><span class='remainingTimeString'>$rtm</span></span></p>\n";
			}else if(isset($gameTimes['gameTime'])){
				$etm = shortTimeInterval($gameTimes['gameTime']);
				echo "<p>".__('Game elapsed time').": <span class='elapsedTime'><span class='elapsedTimeString'>$etm</span></span></p>\n";
			}
			/*if(isset($srules['monsterstotal'])){
				echo "<p>".__("Monsters left").": ".((int)$srules['monsterstotal'])."</p>\n";
			}*/
			
		}
		
		$tgz=array();
		foreach($st as $tn){
			if(!isset($gameTypes[$tn])) continue;
			$tagFullName = $gameTypes[$tn];
			if($tagFullName[0]==="~") $tagFullName=substr($tagFullName,1);
			if(file_exists("$assetsPathLocal/bitmaps/gm-".strtolower($tn).".png")){
				$tgz[]="<a href='".maklinkHtml(LFILE,"?filter=$tn&s=st",null)."'><img src='$assetsPath/bitmaps/gm-".strtolower($tn).".png' alt=\"[$tn]\" /> {$tagFullName}</a>";
			}else{
				$tgz[]="<a href='".maklinkHtml(LFILE,"?filter=$tn&s=st",null)."'>[$tn] {$tagFullName}</a>";
			
			}
		}
		//print_r($st);
		if(count($tgz)){
			echo "<p>".__("Server tags")."<br>";
			echo implode("<br>\n",$tgz)."</p>\n";
		}
		
		$goals = array();
		

		if($isCTF) $teamPointsName = "captures";
		else if($isTDM) $teamPointsName = "team frags";
		else $teamPointsName = "team points";
		
		if($isMH){
			$newGoal = "finish the map";
			if(isset($srules['timelimit']) && $srules['timelimit'] != 0)
				$newGoal .= " before the time limit (".__("%1\$s minutes", (int)$srules['timelimit']).")";
			$goals[] = $newGoal;
			
		}else{
			if(isset($srules['goalteamscore']) && $srules['goalteamscore'] != 0)
				$goals[] = __("%1\$s $teamPointsName", (int)$srules['goalteamscore']);
			if(isset($srules['fraglimit']) && $srules['fraglimit'] != 0)
				$goals[] = __("%1\$s frags", (int)$srules['fraglimit']);
			if(isset($srules['timelimit']) && $srules['timelimit'] != 0)
				$goals[] = __("time limit %1\$s minutes", (int)$srules['timelimit']);
				
		}	
		if(count($goals)){
			echo "<p>".__("Game goals") . ": ";
			
			$ptr=0;
			if(count($goals)==3){
				echo $goals[$ptr++].", ";
			}
			if(count($goals)>=2){
				echo $goals[$ptr++]." or ";
			}
			if(count($goals)>=1){
				echo $goals[$ptr++].".";
			}
			echo "</p>\r\n";
			
		}
		
		
		if(isset($srules['protection']) && $srules['protection']!=="False" && $srules['protection']!=="Hidden"){
			echo "<p>".__("Protection").": ";
			$prot = $srules['protection'];
			if(stripos($prot,"ACE")===0){
				$acName="AntiCheatEngine (ACE)";
				$acIcon = "ace.png";
				$acVersion = "{$prot[4]}.{$prot[5]}{$prot[6]}";
			}else if(stripos($prot,"UTPure")===0){
				$acName="UTPure";
				$acIcon = "utpure.gif";
				$acVersion = substr($prot,6);
			}else if(stripos($prot,"ESE")===0){
				$acName="Enhanced Secure Environment (ESE)";
				$acIcon = "ese.ico";
				$acVersion = explode(" ",$prot)[2];
			}
			if(isset($acName)){
				if(isset($acIcon)){
					echo "<img src='".maklinkHtml(LSTATICFILE,"ext_favicons/".$acIcon,"")."' class=\"icon\" alt=\"".htmlspecialchars($acName)."\" title=\"".htmlspecialchars($acName)."\"/> ";
				}else{
					echo htmlspecialchars($acName);
				}
				echo " ".htmlspecialchars($acVersion)."";
			}else{
				echo htmlspecialchars($prot);
			}
			echo "</p>\r\n";
		}
		
		
		if(isset($srules['newnet'])){
			echo "<p>".__("NewNet version").": ".htmlspecialchars($srules['newnet'])."</p>\n";
		}
		
		echo "<p>".__("Other stats websites").": ";
		
		if(file_exists($dataDir . "/utstats.txt")){
			$utstatsList = json_decode(file_get_contents($dataDir . "/utstats.txt"),true);
		}else{
			$utstatsList = array();
		}
		
		if(isset($utstatsList[$ip])){
			echo "<a href='{$utstatsList[$ip]}?p=sinfo&amp;serverip=$ip'><img src=\"".maklink(LSTATICFILE,"ext_favicons/utstats.ico","")."\" class=\"icon\" alt=\"[UTStats]\" title=\"UTStats\"/></a>\n";
		}
		
		// not ready yet
		/*$utstatsUrl=searchForUTStats($s);
			if($utstatsUrl!=""){
			echo "<a href='{$utstatsUrl}?p=sinfo&serverip={$ip}'>[UTStats]</a>\n";
		}*/
		
		echo "<a href='http://www.gametracker.com/server_info/$ip/'><img src=\"".maklink(LSTATICFILE,"ext_favicons/gt.png","")."\" class=\"icon\" alt=\"[GameTracker]\" title=\"GameTracker\"/></a>\n";
		if(!$notUnr) {
			echo "<a href='http://333networks.com/ut/{$s['ip']}'><img src=\"".maklink(LSTATICFILE,"ext_favicons/333net.png","")."\" class=\"icon\" alt=\"[333networks]\" title=\"333networks\"/></a>\n";
		}
		//echo "<a href='http://www.games.pervii.com/search.php?lan=en&amp;q=".urlencode(trim(unRetardizeServerName($s['n'])))."'><img src=\"".maklink(LSTATICFILE,"ext_favicons/pervii.ico","")."\" class=\"icon\" alt=\"[pervii.com]\" title=\"pervii.com\"/></a>\n";
		echo "<a href='http://www.game-state.com/$ip/'><img src=\"".maklink(LSTATICFILE,"ext_favicons/gamestate.ico","")."\" class=\"icon\" alt=\"[Game-State.com]\" title=\"Game-State.com\"/></a>\n";
		
		if(!$banned && isset($srules['__uttrealplayers']) && $srules['__uttrealplayers']<$srules['numplayers']-2){
			echo "<p>".__("This server is reporting incorrect number of players, and should be checked for fake players.")."</p>\r\n";
		}
		
		echo "</div><!--/mapinfo-->\n";
		//$pxva=indexaskey(sqlquery("SELECT id,team,scorethismatch,enterdate,lastupdate,pingsum,numupdates FROM playerhistorythin WHERE serverid=$sid AND abs(lastupdate-$lastupdx) < 10",null,$dbh),'id');
		
		
		
		
		if($banned){
			$match="";
			preg_match("/(('[0-9]{2}-[0-9]{2}-[0-9]{2})?) (.*)/",$bannedReason,$match);
			//print_r($match);
			
			echo "<br><span class=\"inYourFace\">".sprintf(__("This server has been added to UTT's <a href='%1\$s'>exclusion list</a>."),maklink(LSTATICFILE,"blacklist.txt"))."</span><br>";
			if($bannedReason!="") {
				echo "<p>".__("Additional info").":</p><pre>$bannedReason</pre>";
				if(strpos($bannedReason,"fake players")!==false){
					echo "<!--<p><a href='".maklink(LFILE,"nightly/fakeplayers.php?serv=$sid","")."'>".__("check fakeplayers.php")."</a></p>-->";
				}				
			}
			
			if(!$offline && !$unknown && isset($srules['__uttrealplayers']) && $srules['__uttrealplayers']<$srules['numplayers']){
				echo "<p>".__("Currently there are <b>%1\$s bots</b> and <b>%2\$s human players</b> on this server.",($srules['numplayers']-$srules['__uttrealplayers']),($srules['__uttrealplayers']))."</p>";
				$fakers = ($srules['numplayers']-$srules['__uttrealplayers']);
			}
			
			
		}
		
		if(!$offline && !$unknown && !$noData && $showPlayers && (!isset($fakers) || $fakers===0)){
			echo "<h3>".__("Players online").":</h3>";
			
			$scoreInTitle = (isset($srules['mutators']) && strpos($srules['mutators'],"Publish Score in Server Title")!==false) || strpos($s['name'],"minutes remaining")!==false;
			$hasTeamScores = $xsq || $scoreInTitle || $isTDM;
			
			$ponline=sqlquery("SELECT ph.*,pi.*,ps.* FROM playerinfo AS pi 
							   LEFT JOIN playerhistorythin AS ph ON pi.id=ph.id
							   LEFT JOIN playerstats AS ps ON ps.serverid=$sid AND pi.id=ps.playerid WHERE ph.serverid=$sid AND ph.lastupdate >= ".($lastupdx-10));
							   /*echo "SELECT ph.*,pi.*,ps.* FROM playerinfo AS pi 
							   LEFT JOIN playerhistorythin AS ph ON pi.id=ph.id
							   LEFT JOIN playerstats AS ps ON ps.serverid=$sid AND pi.id=ps.id WHERE ph.serverid=$sid AND ph.lastupdate >= ".($lastupdx-10);*/
			if(count($ponline)){
				$teams=array();
				foreach($ponline as &$pxzz){
					$pid=$pxzz['id'];
					//echo "A={$pxzz['id']} {$pxzz['lastupdate']} - $lastupdx<br>";
					//if(abs($pxzz['lastupdate']-$lastupdx)<10 && isset($pxva[$pxzz['id']])){
						
						/*$pxzz['fragz']=$pxva[$pxzz['id']]['scorethismatch'];
						$pxzz['enterdatex']=$pxva[$pxzz['id']]['enterdate'];
						$pxzz['lastupdatex']=$pxva[$pxzz['id']]['lastupdate'];
						$pxzz['pingsum']=$pxva[$pxzz['id']]['pingsum'];
						$pxzz['numupdates']=$pxva[$pxzz['id']]['numupdates'];*/
						$pxzz['fragz']=$pxzz['scorethismatch'];
						$pxzz['enterdatex']=$pxzz['enterdate'];
						$pxzz['lastupdatex']=$pxzz['lastupdate'];
						$pxzz['skin']=$pxzz['skindata'];
						//$pxzz['estimatedfragz'] = ($pxzz['score'] / $pxzz['time'])*($pxzz['lastupdate'] - $pxzz['enterdate']);
						$teams[$pxzz['team']]['p'][]=$pxzz;
						if($isTDM){
							$teams[$pxzz['team']]['f']+=$pxzz['scorethismatch'];
						}
					//}
				}
				
			
				$teams['maxct']=0;
				for($i=0; $i<255; $i++){
					
					if(isset($srules['teamname_'.$i])){
						$teams[$i]['n']=$srules['teamname_'.$i];
						$teams[$i]['s']=$srules['teamsize_'.$i];
						$teams[$i]['f']=$srules['teamscore_'.$i];
						$teams['maxct']=max($teams['maxct'],$teams[$i]['s']);
						
					}

				}
				if($srules['gametype']=="CTFGame" || $srules['gametype']=="TeamGamePlus" || $srules['gametype']=="SiegeGI" || (isset($srules['maxteams']) && $srules['maxteams']>=2)){

					echo "<table class='flexisemihuge' id='servonline'>\n<thead>\n<tr>";
					
					if($scoreInTitle){
						preg_match("#\[([0-9]+)-([0-9]+)\]#",$s['name'],$mat);
						if(is_numeric($mat[1]) && is_numeric($mat[2])){
							$teams[0]['f'] = $mat[1];
							$teams[1]['f'] = $mat[2];
						}
					}
					foreach($teams as $tn=>&$tm){
						if(!is_numeric($tn)) continue;
						if($tn==127 || $tn==255) continue;
						
						
						if(!isset($tm['p']) || count($tm['p'])==0) continue;
						$tnx="team_$tn";
						if(!isset($tm['n'])) {
							switch($tn){
								case 0: $tnx="Red"; break;
								case 1: $tnx="Blue"; break;
								case 2: $tnx="Green"; break;
								case 3: $tnx="Gold"; break;
							}
						
							$tm['n']=$tnx;
							$tm['s']=count($tm['p']);
							//$tm['f']=0;
							$teams['maxct']=max($teams['maxct'],$tm['s']);
						}
						if($tm['s']){
							$tz=&$teams[$tn]['p'];
							usort($tz,function($a,$b){return $b['fragz']-$a['fragz'];});
						}
						$cls = "tcheader";
						$siegeSuff="";
						if($isSiege && $hasTeamScores){						
							$cls.=" tcsiege".strtolower($tm['n']);
							$addiCont = "<div class=\"tcsiegewunderbar tc".strtolower($tm['n'])."\" style=\"width: ".((int)$tm['f'])."%\"></div>";
							$siegeSuff = "<div class=\"tcsiegecorelabel\">Core HP:</div>";
						}else{
							$cls.=" tc".strtolower($tm['n']);
							$addiCont = "";
						}
						echo "<th class=\"$cls\" colspan=\"2\">$addiCont<span class=\"tcteamname\">{$tm['n']}</span>$siegeSuff</th><th class=\"$cls\"><span class=\"tcteamscore\">".($hasTeamScores?$tm['f']:"")."</span></th>";
					}
					echo "</tr>\n</thead>\n<tbody>\n";

					for($i=0; $i<$teams['maxct'];$i++){
						
						echo "<tr>";
						
						foreach($teams as $tn=>&$tm){
							if(!isset($tm['s']) || $tm['s']==0) continue;
							$cls= "tb".strtolower($tm['n']);
							if(isset($tm['p'][$i])){
								if(!is_numeric($tn)) continue;
								$pw=$tm['p'][$i];
								$sk=explode("|",$pw['skin']);
								if(($tn==127 || $tn==255) && $sk[0]=="Spectator") continue;
								//$pwx=$pxva[$pw['id']];
								$tim=round(($pw['lastupdatex']-$pw['enterdatex'])/60);
								$pi=round($pw['pingsum']/$pw['numupdates']);
								
								$skinCode = (!$notUnr) ? getSkinImage($sk[0],$sk[1],$sk[2],false) : "";
								
								echo "<td class=\"soskin $cls\">$skinCode</td><td class=\"soname $cls\" title=\"TM: $tim PI: $pi\"><a href='".maklinkHtml(LPLAYER,$pw['id'],$pw['name'],"so")."'>".htmlspecialchars($pw['name'])."</a><!--<a href=\"#{$pw['id']}\" class='fragm'>&#8595;</a>--></td><td class=\"soscore $cls\">{$pw['fragz']}</td>";
							}else{
								
								echo "<td class=\"$cls\" colspan=\"3\"></td>";
							}
							
						}
						echo "</tr>\n";
					}
					
					
					
					echo "</tbody>\n</table>\n";
				}else{
					$fraglimit = isset($srules['fraglimit']) ? $srules['fraglimit'] : 0;
					$scAddz = "";
					if($fraglimit > 0) $scAddz = " /".(int)$fraglimit;
					echo "<table class='semihuge' id='servonline'>\n<thead>\n<tr>";
					if($isMH && isset($srules['monsterstotal'])){
						echo "<th class='soskin'></th><th class='soname'>".__("Player name")."</th><th class='soscore' colspan='2' title=\"".__("Monsters left")."\">ML:".((int)$srules['monsterstotal'])."</th></th>";
					}else{
						echo "<th class='soskin'></th><th class='soname'>".__("Player name")."</th><th class='soscore'>".__("Score")."<span class='scAddz'>$scAddz</span></th>";
					}
					echo "</tr>\n</thead>\n<tbody>\n";
					
					//$team=reset($teams);
					
					//print_r($teams);
					
					usort($ponline,function($a,$b){return $b['scorethismatch']-$a['scorethismatch'];});
					$ponline=indexaskey($ponline,'id');
					/*$minEndTime = 86400;
					$predictedWinner = 0;*/
					foreach($ponline as $pn=>&$pw){
						if(!is_numeric($pn)) continue;
						$pdz=$ponline[$pw['id']];
						if(strtok($pdz['skin'],"|")=="Spectator") continue;
						switch($pw['team']){
							case 0: $tnx="Red"; break;
							case 1: $tnx="Blue"; break;
							case 2: $tnx="Green"; break;
							case 3: $tnx="Gold"; break;
							default: $tnx="Gray"; break;
						}
						
						$sk=explode("|",$pdz['skin']);
						
						$tm=round(($pw['lastupdate']-$pw['enterdate'])/60);
						$pi=round($pw['pingsum']/$pw['numupdates']);
						$fphstate="";
						/*if($isCompetitiveGame && $pw['scorethismatch']>1 && $pw['estimatedfragz'] > 1){
							$satw = round($pw['scorethismatch'] / $pw['estimatedfragz'],2);
							if($satw > 1.1) $fphstate="(".__("better than average").")";
							else if($satw < 0.9) $fphstate="(".__("worse than average").")";
							
							$comboFactor = $pw['scorethismatch'] / $fraglimit;
							
							$fraglimitTime = (1-($pw['scorethismatch']/$fraglimit))*$tm*60;
							$fraglimitTimeAvg = (1-($pw['estimatedfragz']/$fraglimit))*$tm*60;
							$fraglimitTimeCombo = ($fraglimitTime * $comboFactor + $fraglimitTimeAvg * (1-$comboFactor)) / 2;
							$pw['endtime']=$fraglimitTimeCombo;
							$fphstate.=" satw=$satw est=".round($pw['estimatedfragz'])."";
							if($fraglimit>0) $fphstate.=" cfac=".round($comboFactor*100)."%";
							if($fraglimit>0) $fphstate.=" wint=".round($fraglimitTimeCombo)."s";
							if($minEndTime > $fraglimitTimeCombo && $comboFactor > 0.2){
								$minEndTime = $fraglimitTimeCombo;
								$predictedWinner=$pw;
							}
						}				
						*/						
						$skinCode = (!$notUnr) ? getSkinImage($sk[0],$sk[1],$sk[2],false) : "";
						$teamClass = "tb".strtolower($tnx);
						if($notUnr) $teamClass = "";
						echo "<tr class=\"$teamClass\"><td class='soskin'>$skinCode<br>TM: $tm<br>PI: $pi</td><td class='soname'><a href='".maklinkHtml(LPLAYER,$pw['id'],$pdz['name'],"so")."' class='player'>".htmlspecialchars($pdz['name'])."</a> <!--<a href=\"#{$pw['id']}\" class='fragm'>&#8595;</a>--></td><td class='soscore'>{$pw['scorethismatch']} <!--<span class='sofphState'>$fphstate</span>--></td></tr>\n";
					}
					unset($ponline);
					echo "</tbody>\n</table>\n";
					/*
					echo "<!--testing: outcome prediction..";
					$endtimes = array_column($ponline,"endtime");
					$endtimesMedian = medy($endtimes);
					if($predictedWinner!==0){
						//$prob = 1 - ($minEndTime/$gameTimes['gameTime']);
						$prob = $minEndTime / $endtimesMedian;
						echo round($prob*100)."%: " . $predictedWinner['name'] . " at: ".date("H:i:s",$predictedWinner['lastupdate']+$minEndTime) . " (in $minEndTime seconds)";
					}
					echo "-->";*/
				}
				if(isset($teams[127])) $tmpspec=$teams[127];
				else if(isset($teams[255])) $tmpspec=$teams[255];
					
				
				if(isset($tmpspec)) {
					$spclist="";
					
					$xdf=true;
					foreach($tmpspec['p'] as $tx){
						if(strtok($tx['skin'],"|")!=="Spectator") continue;
						if(!$xdf) $spclist.=", ";
						$xdf=false;
						$spclist.="<a href='".maklinkHtml(LPLAYER,$tx['id'],$tx['name'],"so")."'>{$tx['name']}</a>";
					}
					if($spclist!="") echo "<div class='spec-list'>".__("Spectators").": $spclist</div>";
				}
			}else{
				echo "<p>".__("No players are currently playing on this server.")."</p>";
			}
			
		}
		/*if(isset($_GET['rules'])){
			echo "<pre>";
			echo "Rules:\n";
			print_php($srules);
			if($srules===null){
				echo $s['rules'];
			}

			echo "</pre>\n";
		}
		//*/
		
		
		
		if((!$banned || $offline || $unknown) && $showPlayers && (count($pdxt) || !$refreshData)){
			
			//echo "<h3>".__('All-time players stats')."</h3>\n";
			//echo __("the list is refreshed every 24 hours")."<br>\n";
			//if(!isset($_GET['show_spec'])) echo __("Spectator players are hidden from the list.")." <a href='{$_SERVER['REDIRECT_URL']}?show_spec&{$_SERVER['REDIRECT_QUERY_STRING']}'>".__("Click here to show them.")."</a><br><br>\n";
			/*echo "<form action=''>
			<input type='hidden' name='serv' value='$sid' />
			<input type='text' placeholder='".__("Search for player (this server)")."' name='playersearch' size='40' />
			<input type='submit' value='".__("Search")."' />
			</form>\n";*/
			
			$pdxth=array(); //sqlquery("SELECT id, games,lastupdate,score,deaths,time FROM playerstats WHERE serverid=$sid",null,$sdbh);
			
			if(!$refreshData){
				$pt=new TableThing(null,'servplayers'.$sid);
				$pt->usingCached=true;
				$pt->loadDataFromCache('servplayers'.$sid);
			}else{
				
	
				if(count($pdxt)>0 || count($pdxth)>0){
					
					for($i=0; $i<count($pdxt); $px=$pdxt[$i++]){
						if(!isset($px['id'])) continue;
						$pdx[$px['id']]=$px;
					}
					for($i=0; $i<count($pdxth); $px=$pdxth[$i++]){
						if(!isset($px['games'])) continue;
						if(!isset($pdx[$px['id']])) $pdx[$px['id']]=array('numupdates'=>0,'deathsthismatch'=>0,'scorethismatch'=>0,'time'=>0,'lastupdate'=>0);
						$pv=&$pdx[$px['id']];
						
						$pv['numupdates'] += $px['games'];
						$pv['deathsthismatch'] += $px['deaths'];
						$pv['scorethismatch'] += $px['score'];
						$pv['time'] += $px['time'];
						$pv['id'] = $px['id'];
						$pv['lastupdate'] = max($pv['lastupdate'],$px['lastupdate']);
						
					}

					unset($pv,$pdxth,$pdxt);
					//utt_benchmark_start();
					
					$pt=new TableThing($pdx,'servplayers'.$sid);
					
					$pt->dataLastUpdated=$lastupdx;
				}
			}
			
			$pt->caption = __('All-time players stats');
			
			$pt->htmlClass="huge";
			$pt->htmlId="servplayers_$sid";
			
			
			
			$cx=$pt->addColumn('country',__('C'));
			$cx->contentType=TTCI::CONTENT_HTML;
			$cx->sortOrder=TableThing::SORT_ASC;

			
			
			//$cx=$pt->addSortableColumn(function($r){
			//	if(isset($r['name'])) 
			//		return "<a href='".maklink(LPLAYER,$r['id'],$r['name'],"sp")."' class='player'>".htmlspecialchars($r['name'])."</a>";
			//	},'name',__('Player name'));
			
			$cx=$pt->addColumn('name',__('Player name'));
			$cx->contentType=TTCI::CONTENT_HTML;
			$cx->sortOrder=TableThing::SORT_ASC;

			
			$cx=$pt->addColumn('scorethismatch',__('Total frags'));
			$cx->contentType=TTCI::CONTENT_NUM;
			$cx->sortOrder=TableThing::SORT_DESC;
			
			$cx=$pt->addColumn('fph',__('FPH'));
			$cx->contentType=TTCI::CONTENT_NUM;
			$cx->sortOrder=TableThing::SORT_DESC;
			
			$cx=$pt->addColumn('deathsthismatch',__('Deaths')." (*)");
			$cx->contentType=TTCI::CONTENT_NUM;
			$cx->sortOrder=TableThing::SORT_DESC;
				
			$cx->hidden=!(isset($srules['__uttxserverquery']) && $srules['__uttxserverquery']===true);
			
			$cx=$pt->addColumn('time',__('All time online'));
			$cx->contentType=TTCI::CONTENT_NUM;
			$cx->sortOrder=TableThing::SORT_DESC;
			
			$cx=$pt->addColumn('id',"");
			$cx->contentType=TTCI::CONTENT_NUM;
			$cx->hidden=true;
			
			$cx=$pt->addColumn('skin',"");
			$cx->contentType=TTCI::CONTENT_TEXT;
			$cx->hidden=true;
			
			
			$pt->setRowPreprocessorCallback(function($r){
				$r['sortable_country'] = $r['country'];
				$r['sortable_name'] = $r['name'];
				if($r['time']>600){
					$r['sortable_fph'] = $r['scorethismatch'] * 3600 / ($r['time']);
				}else{
					$r['sortable_fph'] = 0;
				}
				$r['fph'] = $r['sortable_fph'];
				$r['sortable_time'] = $r['time'];
				
				
				return $r;
			});
			// TODO: fix over 9000000000 bug
			$pt->setRowFormatterCallback(function($r)use(&$over9000count){
				$r['country'] = getflag($r['sortable_country']);
				
				$displayName = $r['name'];
				
				$r['name'] = (isset($r['sortable_name'])) ? "<a href='".maklinkHtml(LPLAYER,$r['id'],$r['sortable_name'],"sp")."' class='player'>".htmlspecialchars($displayName)."</a>" : "";
				$r['fph'] = ($r['sortable_time']>600?round($r['sortable_fph']):"-");
				$r['time'] = formattime($r['sortable_time']/3600);
				
				if($r['scorethismatch']>=2147483647) {
					$over9000count++;
					$r['fph'] = __("lots");
					$r['scorethismatch'] = __("> 2 billion");
				}
				
				return $r;
			});
			
			//$pt->sort("lastupdate",TableThing::SORT_DESC);
			$pt->sort("scorethismatch",TableThing::SORT_ASC);
			
			$pt->htmlIdColumn="id";
			
			$over9000count = 0;
			
			$tableHt=$pt->genHTML(0,20);
			
			if($over9000count>0){
				echo "\n<p><b>".__("IT'S OVER 9000000000!!")."</b><br>".__("The frag counters of some players are so ridiculously high, that UTTracker cannot display them properly. ")."<small>(".__("this will be fixed soon").")</small></p>";
			}
			
			echo $tableHt;
			/*$currentpage=isset($_GET['p'])?$_GET['p']:1;
			//echo "range: ".(($currentpage-1)*20);
			
			unset($_GET['p']);
			echo create_pagination(ceil($uniqplayers/20)+1,$currentpage,"?p=%1\$d&".http_build_query($_GET)."#{$pt->htmlId}");*/
			
			//echo "TABLETHING ";
			//utt_benchmark_end();

		
		
			echo "<h3>".__('Last matches:')."</h3>";
			//utt_benchmark_start();
			$fst=true;
			foreach($smh as $k=>$map){
				$gid=$map['gameid'];
				//if(!$fst && time()-($map['date']+$map['time']) < 300) {
					
				//if($map['date']<time()-14400){ //match has ended
				if(!$fst && $srules['mapname']!==$map['mapname']){
					if($map['players']==0){
						sqlexecnow("DELETE FROM serverhistory WHERE gameid=$gid",$dbh);
						//echo "DEL: $gid ";
						unset($smh[$k]);
						continue;
					}
					if($map['time'] < 60){
						sqlexecnow("DELETE FROM serverhistory WHERE gameid=$gid;DELETE FROM playerhistory WHERE gameid=$gid");
						//echo "DEL: $gid ";
						unset($smh[$k]);
						continue;
					}
				}
				if($map['time'] < 0){
					sqlexecnow("DELETE FROM serverhistory WHERE gameid=$gid;DELETE FROM playerhistory WHERE gameid=$gid");
					//echo "DEL: $gid ";
					unset($smh[$k]);
					continue;
				}
				
				$fst=false;
			}
			$fst=true;
			$pt=new TableThing($smh,'servmaps'.$sid);
			$pt->htmlClass="huge";
			$pt->htmlId="servmaps_$sid";
			$pt->dataLastUpdated=$lastupdx;
			$pt->allowSorting=false;
			
			$cx=$pt->addColumn('date',__('Date'));
			$cx->contentType=TTCI::CONTENT_HTML;
			$cx->sortOrder=TableThing::SORT_DESC;
			
			$cx=$pt->addColumn('mapname',__('Map'));
			$cx->contentType=TTCI::CONTENT_HTML;
			$cx->sortOrder=TableThing::SORT_ASC;
			
			$cx=$pt->addColumn("players",__("Nr. of players"));
			$cx->contentType=TTCI::CONTENT_NUM;
			$cx->sortOrder=TableThing::SORT_DESC;
			
			$cx=$pt->addColumn('time',__('Game time'));
			$cx->contentType=TTCI::CONTENT_NUM;
			$cx->sortOrder=TableThing::SORT_DESC;
			
			$cx=$pt->addColumn("score",__("Frags"));
			$cx->contentType=TTCI::CONTENT_NUM;
			$cx->sortOrder=TableThing::SORT_DESC;
			
			if(isset($stInv['DM'])) $topPlayerFieldName = __("Winner");
			else if(isset($stInv['MH'])) $topPlayerFieldName = __("Highest score");
			else $topPlayerFieldName = __("Highest score");
			
			$cx=$pt->addColumn("topplayer",$topPlayerFieldName);
			$cx->hidden=!$isCompetitiveGame;
			$cx->contentType=TTCI::CONTENT_HTML;
			$cx->sortOrder=TableThing::SORT_DESC;
			/*$pt->setRowPreprocessorCallback(function($r){
				
			});*/
			$pt->setRowFormatterCallback(function($r)use(&$fst,$s,$srules,$offline,$unknown,$playerNum,$isCompetitiveGame,$gameTimes){
				/*if(!isset($s['n'])) {
					$pt->skipRow();
					return;
				}*/
				$mn="<a href='".maklinkHtml(LMAP,0,$r['mapname'],"sr")."'>".$r['mapname']."</a>";
				if($fst && $srules['mapname']==$r['mapname']&&!$offline&&!$unknown){
					$sx=__("Now");
					$r['mapname']="$mn <small>(".__('in progress').")</small>";
					$r['time'] = formattime($gameTimes['gameTime']/3600); //formattime((time()-$r['date'])/3600);
					$r['players'] = $playerNum;
					$r['score'] = "-";
					$r['topplayer'] = "???";
				}else{
					$sx="<a href=\"".maklinkHtml(LGAME,$r['gameid'],$s['sid']."-".name2id($s['n']),"sr")."\">".uttdateFmt($r['date'])."</a>";
					$r['mapname']=$mn;
					$r['time']=formattime(($r['time'])/3600);
					//print_r($r['topplayer'] );
					$r['sortable_topplayer'] = $r['topplayer']['name'];
					$topPlayerData = $r['topplayer'];
					$r['topplayer'] = "<a href=\"".maklinkHtml(LPLAYER,$topPlayerData,null)."\">".htmlspecialchars($topPlayerData['name'])."</a>";
					if(!isset($srules['fraglimit']) || $srules['fraglimit']==0){
						$r['topplayer'] .= " (".($topPlayerData['scorethismatch']).")";
					}
					
				}
				$fst=false;
				$r['date']=$sx;
						
						
				return $r;
			});
			
			echo $pt->genHTML(0,10);
		}else if($showRules){
			$rulesIndexed = array();
			foreach($srules as $key=>$val){
				$rulesIndexed[]=array("key"=>$key, "value"=>$val);
			}
			
			$pt=new TableThing($rulesIndexed,'servrules'.$sid);
			$pt->dontCache=true;
			$pt->caption=__("Server variables");
			$pt->htmlClass="huge";
			$pt->htmlId="servrules_$sid";
			$pt->allowSorting=false;
			
			$cx=$pt->addColumn('key',__('Raw name'));
			$cx->contentType=TTCI::CONTENT_TEXT;
			$cx->sortOrder=TableThing::SORT_DESC;
			$cx->hidden=true;
			
			$cx=$pt->addColumn('descr',__('Variable name'));
			$cx->contentType=TTCI::CONTENT_TEXT;
			$cx->sortOrder=TableThing::SORT_DESC;
			
			$cx=$pt->addColumn('value',__('Value'));
			$cx->contentType=TTCI::CONTENT_TEXT;
			$cx->sortOrder=TableThing::SORT_ASC;
			
			
			$pt->setRowPreprocessorCallback(function($r)use($rulesDescriptions,$pt){
				if(strpos($r['key'],"__")!==false) $pt->skipRow();
				$numkey = null;
				$key = preg_replace_callback("#([0-9]+)\$#",function($m)use(&$numkey){
					if($m[1]!="") $numkey = $m[1];
					return "^";
				},$r['key']);
				$keyLC = strtolower($key);
				if(isset($rulesDescriptions[$keyLC])){
					$r['descr']=$rulesDescriptions[$keyLC];
				}else{
					$r['descr']=$key;
				}
				if($numkey!==null){
					$r['descr']=str_replace("#","#".$numkey,$r['descr']);
				}
				$r['descr'] = strtok($r['descr'],";");
				$r['sortable_descr'] = $r['key'];
				if(is_bool($r['value'])) $r['value'] = ($r['value']?"true":"false");
				return $r;
			});
			echo $pt->genHTML();
			
			$pt=new TableThing($rulesIndexed,'servuttdiag'.$sid);
			$pt->dontCache=true;
			$pt->caption=__("Additional scanner variables");
			$pt->htmlClass="huge";
			$pt->htmlId="servuttdiag_$sid";
			$pt->allowSorting=false;
			
			$cx=$pt->addColumn('key',__('Variable'));
			$cx->contentType=TTCI::CONTENT_TEXT;
			$cx->sortOrder=TableThing::SORT_DESC;
			
			
			$cx=$pt->addColumn('descr',__('ServerScanner Equivalent'));
			$cx->contentType=TTCI::CONTENT_TEXT;
			$cx->sortOrder=TableThing::SORT_DESC;
			$cx->hidden=true;
			
			$cx=$pt->addColumn('value',__('Value'));
			$cx->contentType=TTCI::CONTENT_TEXT;
			$cx->sortOrder=TableThing::SORT_ASC;
			
			
			$pt->setRowPreprocessorCallback(function($r)use($rulesDescriptions,$pt){
				if(strpos($r['key'],"__")===false) $pt->skipRow();
				$numkey = null;
				$key = preg_replace_callback("#([0-9]+)\$#",function($m)use(&$numkey){
					if($m[1]!="") $numkey = $m[1];
					return "^";
				},$r['key']);
				$keyLC = strtolower($key);
				if(isset($rulesDescriptions[$keyLC])){
					$r['coltitle_key']=$rulesDescriptions[$keyLC];
				}else{
					$r['coltitle_key']=$key;
				}
				if($numkey!==null){
					$r['coltitle_key']=str_replace("#","#".$numkey,$r['coltitle_key']);
				}
				$r['coltitle_key'] = strtok($r['coltitle_key'],";");
				if(is_bool($r['value'])) $r['value'] = ($r['value']?"true":"false");
				return $r;
			});
			echo $pt->genHTML();
		}
		
		
		echo "<hr/>\n";
		if($xsq) {
			
			echo "(*) = ".__('extended stats provided by <a href=\'http://www.unrealadmin.org/forums/showthread.php?p=162211#post162211\'>XServerQuery</a>')."<hr/>\n";
		}
	
	}
}

printf(__("Stats on this page are updated every %1\$s minutes."),$updrate);
echo "<br>\n";

function searchForUTStats($s){
	$utstCache=__DIR__."/n14data/utstats_urls.txt";
	if(file_exists($utstCache)){
		$utsc=file($utstCache,FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES);
		foreach($utsc as $l){
			$sid=strtok($l,"\\");
			$url=strtok("\r");
			if($s['serverid']==$sid) return $url;
		}
	}
	
	$qres=GoogleGetURLs("\"{$s['n']}\" intitle:\"Powered by UTStats\"");
	if(!isset($urlz['items'])) {
		echo "NOITEMS";
		if(isset($urlz['error'])){
			echo $urlz['error'];
			return "";
		}
	}else{
		foreach($urlz['items'] as $v){
			$link=$v['link'];
			if(strpos($link,"?p=") !==false){
				$link=strtok($link,"?");
				
				file_put_contents($utstCache,"{$s['serverid']}\\$link\r\n");
				return $link;
			}
		}
		return "";
	}
}

sqldestroy($dbh);

//echo "SQL HISTORY:<br>\n".$sqlqueries;

?><br>

<small><?=$appCredits?></small>
<?php 

include "tracking.php";

?>
</div>
</body>
</html>

<?php
utt_checkpoint("END");
?>