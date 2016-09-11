<?php
/*
 * mta tracker
 * 2009 blackmore
 *
 * server list page
**/

	date_default_timezone_set ('GMT'); //TODO move to cfg 
	
	
	require_once "config.php";
	require_once "sqlengine.php";
	
	require_once "common.php";

	try{

		$dbh=sqlcreate($statdb_host,$statdb_user,$statdb_pass,$statdb_db);

		sqlexec($sqlAutoexec,0,$dbh);
	}catch(Exception $e){
		$errstr = "sqlConErr";
		include $GLOBALS['errhndFatalPage'];
		exit;
	}
	
	try{
		$utt_cfg = new N14\INICache($config_ini);
	}catch(N14\INIException $exc){
		$utt_cfg = null;
	}
	
	$rplast=sqlquery("SELECT `data` FROM `utt_info` WHERE `key`=\"net.reaper.lastupdate\"",1)['data'];
	 
	$blacklist=explode("\r\n",file_get_contents("blacklist.txt")); // TODO func/class?
	foreach($blacklist as $k=>$be){
		$be=trim(strtok($be,"#"));
		$ipw=explode(":",$be,2);
		if(count($ipw)!=2 || ip2long($ipw[0])===false || !is_numeric($ipw[1])){
			
			unset($blacklist[$k]);
			}else{
			$blacklist[$k]="{$ipw[0]}:{$ipw[1]}";

		}
	}

	if(isset($_GET['error'])) throw new Exception("Moo");
	 
	/* sorting stuff */

	$fradx=(isset($_GET['fr'])&&!$desc?"&d":"");
	$ctadx=(isset($_GET['ct'])&&!$desc?"&d":"");
	$dxadx=(isset($_GET['dx'])&&!$desc?"&d":"");
	$exadx=(isset($_GET['ex'])&&!$desc?"&d":"");
	
	$poadx=(isset($_GET['po'])&&!$desc?"&d":"");
	//$sqadx=(isset($_GET['sq'])&&!$desc?"&d":"");
	$rfadx=(isset($_GET['rf'])&&!$desc?"&d":"");
	
	$pladx=($fradx.$ctadx.$dxadx.$exadx==""?"&d":"");


	
	/* callback functions for sorting */
	
	/* servers list */
	function sortser($a,$b){return -cmp($a['rfcombo'],$b['rfcombo']);}
	
	function sortserrf($a,$b){return -cmp($a['rfscore'],$b['rfscore']);}
	//function sortsersq($a,$b){return -cmp($a['sqscore'],$b['sqscore']);}
	function sortserpl($a,$b){return -cmp($a['uplayers'],$b['uplayers']);}
	function sortseropl($a,$b){return -cmp($a['realnum'],$b['realnum']);}
	/* server info */
	function sortpl($a,$b){return -cmp($a['lastupdate'],$b['lastupdate']);}
	function sortfr($a,$b){return -cmp($a['scorethismatch'],$b['scorethismatch']);}
	function sortct($a,$b){return -cmp($a['time'],$b['time']);}
	function sortdx($a,$b){return -cmp(dxc($a),dxc($b));}
	function sortex($a,$b){return -cmp($a['deathsthismatch'],$b['deathsthismatch']);}
	function dxc($a){$hours=($a['time'])/3600; return ($hours>0.5?round($a['scorethismatch'] / $hours):"-");}
	

	
	//function phpsux($a,$k){return $a[$k];} // temporary workaround, no longer needed

/*
if(isset($_GET['ct'])){
	uasort($servstat,'sortserpl');
}elseif(isset($_GET['sq'])){
	uasort($servstat,'sortsersq');
}else{
	uasort($servstat,'sortser');
}*/

if(isset($_GET['search'])){
	$rurl="";
	switch($_GET['search']){
		case 'p':
			$rurl=maklink(LSEARCHPLAYER,"",$_GET['name']);
		break;
		
		case 's':
		break;
		
		default:
			$rurl=maklink(LFILE,"","");
		break;
	}
	if($rurl) {	
		header("Location: $rurl");
		exit;
	}
}
if(isset($_GET['retarded']) && is_string($_GET['retarded'])){
	$hooksExploded = explode(",",$_GET['retarded']);
	$hooks = array_flip($hooksExploded);
        unset($hooksExploded);
}else{
	$hooks = array();
}

if(isset($_GET['serv'])){
	/*$sid=(int)$_GET['serv'];
	$updrate=$utt_cfg['General']['IntervalMins'];
	//T: 63 ms
	$s=sqlquery("SELECT serverinfo.`name` as n,serverinfo.`serverid` as sid, serverinfo.`address` as ip, serverinfo.`rules` as rules, serverhistory.`mapname` as map, serverhistory.`gameid` as gameid FROM serverinfo
	LEFT JOIN serverhistory ON serverhistory.serverid=serverinfo.serverid WHERE serverinfo.serverid=$sid",1,$dbh);
	//echo time()-$s['datex'];
	if(isset($_GET['refresh'])){
		FetchServerInfo($s['ip']);
		$s=sqlquery("SELECT serverinfo.`name` as n,serverinfo.`serverid` as sid, serverinfo.`address` as ip, serverinfo.`rules` as rules, serverhistory.`mapname` as map, serverhistory.`gameid` as gameid FROM serverinfo
		LEFT JOIN serverhistory ON serverhistory.serverid=serverinfo.serverid WHERE serverinfo.serverid=$sid",1,$dbh);
	}*/
	
	if($s['n']=="") error404();
	/*$expurl=maklink(LSERVER,$s['sid'],$s['n']);

	if(strpos($expurl,strtok($_SERVER['REQUEST_URI'],"?"))===false || strpos($_SERVER['REQUEST_URI'],".htm")===false) permredir($expurl);	*/
	/*echo "CUR URL: {$_SERVER['REQUEST_URI']}<br>";
	echo "EXP URL: $expurl<br>";
	echo "MATCH: " . (strpos($expurl,strtok($_SERVER['REQUEST_URI'],"?"))!==false && strpos($_SERVER['REQUEST_URI'],".htm")!==false ? "TRUE" : "FALSE");*/	
} else{
	/*$servstat=sqlquery("SELECT serverinfo.name AS name,serverinfo.address AS address,serverinfo.serverid AS serverid,ph.uplayers AS uplayers,ph.records AS records,pd.pwrecords AS pwrecords FROM serverinfo 
	INNER JOIN ( SELECT COUNT(DISTINCT id) AS uplayers, SUM(numupdates) AS records, playerhistory.serverid as serverid FROM playerhistory GROUP BY playerhistory.serverid) as ph ON ph.serverid = serverinfo.serverid
	LEFT JOIN ( SELECT SUM(numupdates) AS pwrecords, playerhistory.serverid as serverid FROM playerhistory WHERE `lastupdate` <= ".(time()-86400)." GROUP BY playerhistory.serverid) as pd ON pd.serverid = serverinfo.serverid
	ORDER BY uplayers DESC",null,$dbh);*/
	
	if($utt_cfg!==null){
		$updrate=$utt_cfg['General.IntervalMins'];
	}
	
	$serversperpage=30;
	
	/*if(isset($_GET['ct'])){
		$orderby="uplayers";
	}elseif(isset($_GET['rf'])){
		$orderby="rfscore";
	}elseif(isset($_GET['po'])){
		$orderby="numplayers";
	}else{
		$orderby="numplayers*(rfscore/400)";
	}*/
	
	//$ord=(isset($_GET['d'])?"ASC":"DESC");
		
	$currentpage=(isset($_GET['p'])?(int)$_GET['p']:1);
	$serversstart=($currentpage-1)*$serversperpage;
	
	//$servstat=sqlquery("SELECT * FROM `cache_serverlist` ORDER BY $orderby $ord LIMIT $serversstart,$serversperpage",null,$dbh);
	
	$blx=array();
	/*foreach($blacklist as $bk){
		//echo "$bk => " . abs(crc32($bk)) . "<br>";
		$blx[]=abs(crc32($bk));
	}*/
	
	foreach($blacklist as $bk){
		$blx[$bk]=true;
	}
	
	//$servstat=sqlquery("SELECT * FROM serverinfo WHERE serverid NOT IN(".implode(",",$blx).")",null,$dbh);
	$servstat=sqlquery("SELECT * FROM serverinfo WHERE `gamename` = 'ut'",null,$dbh);
	//$servstatallsize=sqlquery("SELECT count(*) as c FROM `cache_serverlist`",1,$dbh)['c'];
	
	
	
	printf($headerf,"","gm-default","Player and Server Stats for Unreal Tournament 99");
	//$rfavg=rf_avg($servstat);
	
	
	if(isset($_GET['filter'])){
		$filterTypes=explode(",",$_GET['filter']);
	}else{
		$filterTypes=array();
	}
	$filterTypesFlipped=array_flip($filterTypes);
	foreach($filterTypesFlipped as $gameType=>$i){
		if(!isset($gameTypes[$gameType])) {
			$filterTypesFlipped[$gameType]=$gameType;
		}else{
			$filterTypesFlipped[$gameType]=$gameTypes[$gameType];
		}
	}

	echo "Filter servers: <span id='mlServerFilter'>";
	foreach($gameTypes as $gs=>$gfn){
		if($gfn[0]==="~" || $gfn[0]===":") continue;
		if(!count($filterTypes)) {
			$optionClass="selectable";
		}else if(isset($filterTypesFlipped[$gs])) {
			$optionClass="selected";
		}else{
			$optionClass="notselected";
		}
		echo "<a href='?filter={$gs}' class=\"$optionClass\"><img src='$assetsPath/bitmaps/gm-".strtolower($gs).".png' alt='[$gs]' title='$gfn'/></a> ";
	}
	echo "</span><br><!--<form action='' method='get'>\n";
	echo "(WORK IN PROGRESS) Search for \n";
	echo "<select name='search'>\n";
	$opts=array('p'=>'Player','s'=>'Server');
	
	foreach($opts as $sh=>$nm){
		echo "<option value='$sh'".(isset($_GET['search']) && $sh==$_GET['search'] ? " selected": "").">$nm</option>\n";
	}
	echo "</select>\n";
	echo " with name: <input type='text' name='name' value='".(isset($_GET['name'])?htmlspecialchars($_GET['name']):"")."' />\n";
	echo "<input type='submit' value='Search'/>";
	echo "</form>-->\n";
	//echo "DEBUG: RF AVG=$rfavg<br>";
	/*foreach($servstat as &$se){
		if(striposa($se['address'],$blacklist)!==false){
			$se['bl']=true;
		}else if(isset($se['bl'])){
			unset($se['bl']);
		}
		
	}*/
	
	
	$onlineplayers=0;
	$playerslots=0;
	$onlineservers=0;
	$currentViewPlayers = 0;
	$currentViewPlayerSlots = 0;
	foreach ($servstat as $k=>&$s){
		
		$blacklisted=isset($blx[$s['address']]);
		
		$srules=json_decode($s['rules'],true);
		
		if(isset($s['gamename']) && $s['gamename']!='ut') {
			unset($servstat[$k]);
			continue;
		}
		
		if(!isset($srules['numplayers'])) {
			$s['numplayers']=0;
			$s['maxplayers']=0;
			$s['humanplayers']=0;
			$s['realnum']=0;
			$s['lastupd']=0;
			$s['mapname']="";
			$s['gametype']="";
			$s['mutators']="";
			$s['gamever']="";
			
		}else{
			$s['numplayers']=$srules['numplayers'];
			$s['maxplayers']=$srules['maxplayers'];
			$s['humanplayers']=(isset($srules['__uttrealplayers'])?$srules['__uttrealplayers']:-1);
			
			if($s['humanplayers'] != -1 && $s['humanplayers'] < $s['numplayers']){
				//$s['rfscore']=1684;
				$s['realnum']=$s['humanplayers'];
			}else{
				$s['realnum']=$s['numplayers'];
			}
			$s['mapname']=(isset($srules['mapname'])?$srules['mapname']:"");
			$s['gametype']=(isset($srules['gametype'])?$srules['gametype']:"");
			$s['mutators']=(isset($srules['mutators'])?$srules['mutators']:"");
			$s['gamever']=(isset($srules['gamever'])?$srules['gamever']:"");
			$s['lastupd']=(isset($srules['__uttlastupdate'])?$srules['__uttlastupdate']:$s['lastscan']);
			$s['rulesArr']=$srules;
		}
		
		if($blacklisted) {
			$s['rfscore']=round($s['rfscore']*0.65);
			$s['black']=true;
		}
		
		$s['rfcombo']=round(pow($s['rfscore'],1.6)*($s['realnum']+1));
		
		$s['gtypes']=getServerTags($s);
		if($rplast < $s['lastupd']-600) $scannerReboot=true;
		else if($rplast < time() - 600) $scannerOffline=true;
		if(time()-$s['lastupd']>900){
			if( !isset($scannerOffline)){
				unset($servstat[$k]);
			}else{
				if(time()-$s['lastupd']>86400*60){
					unset($servstat[$k]);
				}else{
					//$s['realnum']=0;
				}
			}
			continue;
		}	
		$onlineplayers+=$s['realnum'];
		$playerslots+=$s['maxplayers'];
		$onlineservers++;
		if(isset($_GET['search']) && $_GET['search']=='s' && stripos($s['name'],$_GET['name']) === false){
			unset($servstat[$k]);
			continue;
		}
		
		
		$match=true;
		for($iv=0;$iv<count($filterTypes);$iv++){
			$match=false;
			foreach($s['gtypes'] as $gx){
				if(strcasecmp($gx,$filterTypes[$iv])===0){
					$match=true;
					break;
				}
			}
			if(!$match){
				break;
			}
		}
		if(!$match){
			unset($servstat[$k]);
			continue;
		}
		$currentViewPlayers+=$s['realnum'];
		$currentViewPlayerSlots+=$s['maxplayers'];
	

	}
	if(isset($_GET['ct'])){
		usort($servstat,'sortserpl');
	}else if(isset($_GET['po'])){
		usort($servstat,'sortseropl');
	}else if(isset($_GET['rf'])){
		usort($servstat,'sortserrf');
	}else{
		usort($servstat,'sortseropl');
	}
	
	if(!isset($_GET['d'])) {
		array_reverse($servstat);
	}
	
	$servstatallsize=count($servstat);
	
	if(isset($scannerReboot)) echo "<span class=\"inYourFace\">".__("The server scanner is being restarted. Some servers might not be shown yet.")."</span>\n";
	else if(isset($scannerOffline)) echo "<span class=\"inYourFace\">".__("The server scanner is not running for some reason. SPAM MY MAILBOX ABOUT IT!!")."</span>\n";
		
	/*echo "BETA: \r\n";
	$withRetarded=$_GET;
	$withRetarded['retarded']="readableServerNames2";
	echo "<a href=\"?".http_build_query($withRetarded)."\">[human-readable server names]</a>\r\n";
	$withRetarded['retarded']="extendedServerInfo";
	echo "<a href=\"?".http_build_query($withRetarded)."\">[extended server info]</a>\r\n";*/
		
	if(count($filterTypesFlipped)) echo "<p>Showing only <b>".implode("</b> AndAlso <b>",$filterTypesFlipped)."</b> servers:</p>\r\n";
	echo "<table class='huge' id='masterlist'>\n";
	echo "<colgroup>\n";
	echo "\t<col class='mlcoltype'/>\n";
	echo "\t<col class='mlcolname'/>\n";
	echo "\t<col class='mlcolpl'/>\n";
	echo "\t<col class='mlcolcm'/>\n";
	echo "\t<!--<col class='mlcolrf'/>-->\n";
	echo "\t<!--<col class='mlcolup'/>-->\n";
	echo "\t<!--<col class='mlcollu'/>-->\n";
	echo "\t<col class='mlcolip'/>\n";
	echo "</colgroup>\n";

	echo "<thead>\n\t<tr>
		<th class='mltype'>".__('Type')."</th>
		<th class=\"mlname verylongtext\">".__('Server name')."</th>
		<th class='mlpl'><a href='?po$poadx'>".__('Players')."</a></th>
		<th class='mlcm'><a href=''>".__('Map')."</a></th>
		<!--<th class='mlrf'><a href='?rf$rfadx'>".__('RFScore')."</a></th>-->
		<!--<th class='mlup'><a href='?ct$ctadx'>".__('Unique players')."</a></th>-->
		<!--<th class='mllu'><a href=''>".__('Last update')."</a></th>-->
		<th class='mlip'>".__('IP')."</th>
	</tr>\n</thead>\n";
	echo "<tbody>\n";
	$cld=0;
	//$lastupd=(int)sqlquery("SELECT `data` FROM utt_info WHERE `key`='gacke_last'",1)['data'];
	//$lastupd=(int)sqlquery("SELECT `data` FROM utt_info WHERE `key`='reaper_last'",1)['data'];
	//echo "LASTUPD=$lastupd";
	//foreach ($servstat as &$s){

	
	$serversperpageLOOP=$serversperpage;
	$rowsDisplayed=0;
	$lastRowId=0;
	for($i=0; $i<$serversperpageLOOP;$i++){
		if(!isset($servstat[$i+$serversstart])) break;
		$s=$servstat[$i+$serversstart];
		//if(isset($s['bl'])) continue;
		//$cld=min(max($cld,$s['ld']),date("d"));	
		
		//if(striposa($s['address'],$blacklist)!==false){continue;}
		//if($cld++ == 200) break;
		
		$lastupd=$s['lastupd'];
		$rf=$s['rfscore']; //round(rf($s)/($rfavg+0.0001)*650);
		//$sq=$s['sqscore'];
		$upl=$s['uplayers'];
		$srules=$s['rulesArr'];
		/*if(time()-$s['lastrfupdate']>86400*2 ) {
			$rf="n/a";
			$upl="n/a";
		}*/
		//$rf="n/a";
		//$upl="n/a";
		list($ipa,$port)=explode(":",$s['address'],2);
		$portx=((int)$port-1);
		if(isset($s['hostport'])) $portx=$s['hostport'];
		$ip=$ipa.":" . $portx;
		
		$dispIpChunks=explode(".",$ipa);
		$dispIp="<span class='mlic'>{$dispIpChunks[0]}</span>.<span class='mlic'>{$dispIpChunks[1]}</span>.<span class='mlic'>{$dispIpChunks[2]}</span>.<span class='mlic'>{$dispIpChunks[3]}</span>";
		
		$dispIp.=":".$portx;

		
		if($s['country']==""){
			require_once "geoiploc.php"; // we don't need to include this at every request!
			$s['country']=getCountryFromIP(explode(":",$s['address'])[0], "code");
			if($s['country']!=""){
				sqlexec("UPDATE serverinfo SET `country`=\"{$s['country']}\" WHERE serverid={$s['serverid']}");
			}
			
		}
		
		
		if(($country=$s['country']) != ""){
			//$cif="<img src='$assetsPath/flags/".strtolower($country).".gif' alt='$country' title='$country'/> ";
			$cif=getFlag($country);
		}else{
			$cif="";
		}
		
		$gtypes=$s['gtypes'];
		
		//if($rf<10 && $sq<100) continue;
		
		//$gt=implode("]<br>[",$gtypes);
		$gt="";
		foreach($gtypes as $gx){
			if($gameTypes[$gx][0]===":") continue;
			if(file_exists("$assetsPathLocal/bitmaps/gm-".strtolower($gx).".png")){
				$gt.="<img src='$assetsPath/bitmaps/gm-".strtolower($gx).".png' alt='[$gx]' /><br>";
			}else{
				$gt.="[$gx]<br>";
			}
		}
		
		$sname = $s['name'];
		
		if(isset($hooks['readableServerNames2']))
			$sname = unRetardizeServerName($s['name']); 
		
		$pwlock="";
		if(isset($srules['password']) && strcasecmp($srules['password'],"true")===0){
			$pwlock = "<img src=\"".maklinkHtml(LSTATICFILE,"pwdlock.png",null)."\" alt=\"[PWD]\" />";
		}
		
		$addiRowStyle="";
		//if($s['black']) $addiRowStyle.=" gayBlacklist";
		echo "\t<tr class=\"mlrow$addiRowStyle\" id='serv_{$s['serverid']}'>
		<td class='mltype'>$gt</td>
		<td class=\"mlname verylongtext\"><a href='".maklinkHtml(LSERVER,$s,null)."'>$cif$pwlock ".cp437toentity(htmlentities($sname))."</a>";
		
		
		if(isset($hooks['extendedServerInfo'])){
			$extendedInfo = array();
			echo "<span class=\"mlextended\">";
			
			$mutators = isset($srules['mutators']) ? explode(", ",$srules['mutators']) : array();
						
			//if($srules['numplayers']>0){
				$times = getMatchTimesFromRules($srules);
				if($times['state']=="overtime") 
					$rt="overtime";
				else if($times['state']=="waiting") 
					$rt="waiting";
				else if($times['state']=="ended" || ($times['state']=="unknown" && $times['remaining'] < 0)) 
					$rt="ended";
				else
					$rt=shortTimeInterval($times['remaining']);
				
				if($times['timeLimit'] > 0) $extendedInfo['RT'] = array('desc'=>"remaining time",'value'=>$rt);
			//}
			
			if(isset($srules['monsterstotal'])){
				$extendedInfo['ML']=array('desc'=>"Monsters left",'value'=>$srules['monsterstotal']);
			}
			$serverCaps = array();
			$acProt=false;
			array_walk($mutators, function($val) use(&$serverCaps,&$acProt){
				if(stripos($val, "Map-Vote")===0) $serverCaps['hasMapVote']=true;
				if(stripos($val, "ZeroPing")===0) $serverCaps['hasZeroPing']=true;
				if(stripos($val, "SmartCTF")===0) $serverCaps['hasSCTF']=true;
				if(stripos($val, "BT++")===0) $serverCaps['hasBTPP']=true;
				if(stripos($val, "BTCheckPoints")===0) $serverCaps['hasBTCheckPoints']=true;
				if(stripos($val, "Auto Team Balance")===0) $serverCaps['hasATB']=true;
				if(stripos($val, "Relic: ")===0) $serverCaps['hasRelics']=true;
				if(stripos($val, "[R]^sdj")!==false || 
				   stripos($val, "DoubleJumpUT")!==false) $serverCaps['hasDJ']=true;
				if(stripos($val, "AntiTweak")===0) $acProt="AntiTweak";
			});
			
			if(isset($srules['protection']) && stripos($srules['protection'], "ACE")===0) $acProt = "ACE";
			
			
			$extendedInfo['MV']=array('desc'=>"Has map vote",'value'=>isset($serverCaps['hasMapVote']));
			$extendedInfo['SCTF']=array('desc'=>"SmartCTF installed",'value'=>isset($serverCaps['hasSCTF']));
			$extendedInfo['BT++']=array('desc'=>"BT++ installed",'value'=>isset($serverCaps['hasBTPP']));
			$extendedInfo['CP']=array('desc'=>"BT Checkpoints enabled",'value'=>isset($serverCaps['hasBTCheckPoints']));
			$extendedInfo['DJ']=array('desc'=>"DoubleJump mutator",'value'=>isset($serverCaps['hasDJ']));
			$extendedInfo['RE']=array('desc'=>"Relics enabled",'value'=>isset($serverCaps['hasRelics']));
			$extendedInfo['ATB']=array('desc'=>"Auto Team Balance",'value'=>isset($serverCaps['hasATB']));
			$extendedInfo['ZP']=array('desc'=>"Uses ZeroPing",'value'=>isset($serverCaps['hasZeroPing']));
			$extendedInfo['AC']=array('desc'=>"Anticheat protection",'value'=>$acProt);
			$extendedInfo['R']=array('desc'=>"Show server variables",'value'=>true,'url'=>maklink(LSERVER,$s,null,array('page'=>"rules")));
			
			
			foreach($extendedInfo as $exId=>$exVal){
				if($exVal['value']===false) continue;
				else if($exVal['value']===true) $displayVal = $exId;
				else $displayVal = "$exId={$exVal['value']}";

				$exInfoContent = "<span class=\"mle_".strtolower($exId)."\" title=\"".htmlspecialchars($exVal['desc'])."\">[".htmlspecialchars($displayVal)."]</span> ";
				if(isset($exVal['url'])) $exInfoContent = "<a href=\"".htmlspecialchars($exVal['url'])."\">$exInfoContent</a>";
				
				echo $exInfoContent;
			}
			if(isset($srules['maxteams']) && $srules['maxteams']>1) {
				
			}
			//if(isset($srules['xserverquery'])) echo "[XSQ] ";
			echo "</span>";
		}
		
		echo "</td>
		<td class='mlcpl'>";
		//echo time()."-$lastupd";
		$servScannedLongTimeAgo = time()-$lastupd>900;
		if(!$servScannedLongTimeAgo){
			$numpl=$s['realnum'];
			if(isset($srules['__uttspectators'])){
				$calculatedMaxNumPlayers = $s['realnum'] + $srules['__uttspectators'];
			}else{
				$calculatedMaxNumPlayers = $s['realnum'];
			}
			$maxpl=$s['maxplayers'];
			if($numpl>=$maxpl || 
				(($s['serverid']==522350518 || $s['serverid']==992738732) && $numpl>=12) || // |uk| instagib (max 12 players)
				(($s['serverid']==1891472973) && $numpl>=16) || // |uk| bunnytrack the sam house (max 16)
				(($s['serverid']==2115296102) && $numpl>=25) // |uk| siege (max 25)
			){
				if($numpl==0){
					$mlcplclass='mlcpl_openslots';
					$numpl='?';
					$maxpl='?';
				}else{
					$mlcplclass='mlcpl_full';
				}
			/*}else if($numpl>=$maxpl-2){
				$mlcplclass='mlcpl_almostfull';*/
			}else{
				$mlcplclass='mlcpl_openslots';
			}
		}else{
			$mlcplclass='mlcpl_full';
			$numpl='?';
			$maxpl='?';
		}
		if($numpl!="?" && $numpl > $maxpl){
			$numplayersText="lol";
		}else{
			$numplayersText="<span class='mlcpl_numplayers'>$numpl</span> / <span class='mlcpl_maxplayers'>$maxpl</span>";
		}
		
		//$bts=($numpl!="?" && $s['realnum']!=$s['numplayers'])?"<br>(+".($s['numplayers']-$s['realnum'])." bots)":"";
		echo "<span class='$mlcplclass'>$numplayersText</span>";
		if($numpl!="?" && $s['numplayers']>$calculatedMaxNumPlayers){
			echo " <span class='mlcpl_excl' title=\"Server scanner has detected fake players (server says ".((int)$s['numplayers']).")\">[!]</span>";
		}
		
		if(!$servScannedLongTimeAgo){
			$mapCell = "<a href=\"".maklink(LMAP,0,$s['mapname'])."\">".htmlspecialchars($s['mapname'])."</a>";
		}else{
			$mapCell = "???";
		}
		echo "</td>
		<td class='mlcm verylongtext'>$mapCell</td>
		<!--<td class='mlrf'>".$rf."</td>-->
		<!--<td class='mlup'>".$upl."</td>-->
		<!--<td class='mllu'>".uttdateFmt($s['lastscan'])."</td>-->
		<td class='mlip'>$dispIp</td>\n\t</tr>\n";
		$rowsDisplayed++;
		$lastRowId=$i+$serversstart;
		
		
		
	}
	
	
	echo "</tbody>\n";
	echo "<tfoot>\n";
	if(!$rowsDisplayed){
		echo "<tr><td colspan=\"5\"><b>Empty search result</b></td></tr>\r\n";
	}else{
		echo "<tr><td></td><td>Servers: ".($serversstart+1)."-".($lastRowId+1)." of ".(count($servstat))."</td><td>$currentViewPlayers / $currentViewPlayerSlots</td><td></td><td></td></tr>\r\n";
	}
	echo "</tfoot>\n";
	echo "</table>\n";
	unset($_GET['p']);
	echo create_pagination(ceil($servstatallsize/$serversperpage),$currentpage,"?p=%1\$d&".http_build_query($_GET));
	echo "<form action='search.php'>
	<input type='text' placeholder=\"".__('Search for player (globally)')."\" name='playerSearch' size='40' />
	<input type='submit' value='".__('Search')."' />
	</form><br>\n";
	
	/*
	echo "<p>Servers suspected of sending spoofed info (fake players):</p>\n<table class='huge'>\n";

	echo "<thead>\n\t<tr>\n\t\t<th>IP</th>\n\t\t<th>Name</th>\n\t\t<th><s>Unique players</s></th>\n\t</tr>\n</tread>\n";
	echo "<tbody>\n";

	foreach ($servstat as $i => &$s){
		if(!isset($s['bl'])) continue;
		list($ip,$port)=explode(":",$s['address'],2);
		$ip.=":".((int)$port-1);
		echo "\t<tr>\n\t\t<td>$ip</td>\n\t\t<td>".htmlspecialchars($s['name'])."</td>\n\t\t<td>{$s['uplayers']}</td>\n\t</tr>\n";
	}
	echo "</tbody>\n";
	echo "</table>";*/
	echo "<hr/>\n";
	//echo __('RFScore is a server popularity factor based on the data from the last 2 weeks.')."<br>";
	//if(count($blacklist)>0) printf(__("Since version 53 the servers from blacklist aren't excluded from the listing. However, their RF Score is lowered, also the 'players online' row contains number of real players. Blacklisted servers: %1\$s"),count($blacklist));
	//echo " <a href='blacklist.txt'>(".__('List').")</a><br>";
	echo " <a href='blacklist.txt'>(".__('Servers blacklist').")</a><br>";
	//echo "<hr/>";
echo "<!--
<h3 id='graphsplc'>".__('Graphs').":</h3>
<img src=\"graphstatsworker.php\" alt=\"".__('Number of UT players per hour; data from last 24 hours (%1$s - %2$s); GMT timezone')."\" /><br>
<hr/>
<h3 id='graphsfrg'>".__('Number of frags (by game type)').":</h3>
<img src=\"graphfragsworker.php?gtype=dm\" alt=\"".__('%1$s - Number of frags (%2$s - %3$s)')."\" /><hr/>
<img src=\"graphfragsworker.php?gtype=ctf\" alt=\"".__('%1$s - Number of frags (%2$s - %3$s)')."\" /><hr/>
<img src=\"graphfragsworker.php?gtype=mh\" alt=\"".__('%1$s - Number of frags (%2$s - %3$s)')."\" /><hr/>
<img src=\"graphfragsworker.php?gtype=bt\" alt=\"".__('%1$s - Number of frags (%2$s - %3$s)')."\" /><hr/>
no data = my computer is turned off.
-->
<br>";
}
/*
echo "VBNET reaper status: ";

$task_list=shell_exec  ("tasklist 2>NUL");

if(stripos($task_list,"UTTReaperV2.exe")!==false){
	echo "RUNNING";
} else {
	echo "<b>NOT RUNNING</b>";
}echo ". ";*/
if(isset($updrate)){
	printf(__("Stats on this page are updated every %1\$i minutes."),$updrate);
}
echo "<br>\n";
echo "Players online: $onlineplayers, servers: $onlineservers.<br>";

/*$cp = sqlquery("SELECT COUNT(playerid) as `ct` FROM playerstats WHERE `time` > 7200",1)['ct'];
echo "Total UT players: $cp<br>";*/

//$l24hp = sqlquery("SELECT id,lastupdate FROM playerhistory WHERE `lastupdate` > ".(time()-86400)." GROUP BY id");
//echo "UT players seen in last 24h hours: ".count($l24hp)."<br>";
/*
$lsupd=sqlquery("SELECT `data` FROM `utt_info` WHERE `key`=\"www.stats.libsize.lastupdate\"",1)['data'];
if(time()-$lsupd>86400){
	//sqlexec("Replace into `utt_info` VALUES('www.stats.libsize.lastupdate','".time()."'),('www.stats.libsize.value','".(GetDirectorySize(__DIR__."/nightly/utmp/zip")+GetDirectorySize("F:\\ut99serv\\uttdownload"))."');",$dbh);
}
$lsdirsize=sqlquery("SELECT `data` FROM `utt_info` WHERE `key`=\"www.stats.libsize.value\"",1)['data'];
echo "UTTracker's map library size: ".pretty_file_size($lsdirsize)." (I might put a torrent with all of these in the future)<br>";
*/
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