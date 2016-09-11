<?php
/*
 * mta tracker
 * 2009 blackmore
 *
 * server list page
**/

	error_reporting(E_ALL);
	
	require_once "config.php";
	require_once "sqlengine.php";
	require_once "geoiploc.php";
	require_once "common.php";
	require_once "updater_php/updater.php";
	require_once "updater_php/julkinnet.php";	
		
	
	$globalSock=new JulkinNet();
	$globalSock->setProto(JulkinNet::jnUDP);
	
	/*if(isset($_GET['upd']) || ($update_brief && !isset($_GET['serv']) && !isset($_GET['playersearch']))){
		serverindex_gencache();
	}*/
	

	$dbh=sqlcreate($statdb_host,$statdb_user,$statdb_pass,$statdb_db);
	sqlexec($sqlAutoexec,0,$dbh);
	//$sdbh=sqlcreate($statdbbrief,SQLITE3_OPEN_READONLY);
	
	$utt_cfg=parse_ini_file($config_ini,true);
	
	$rplast=sqlquery("SELECT `data` FROM `utt_info` WHERE `key`=\"net.reaper.lastupdate\"",1)['data'];
	 
	$blacklist=explode("\r\n",file_get_contents("blacklist.txt"));
	foreach($blacklist as $k=>$be){
		$be=trim(strtok($be,"#"));//trim(explode("#",$be,2)[0]);
		$ipw=explode(":",$be,2);
		if(count($ipw)!=2 || ip2long($ipw[0])===false || !is_numeric($ipw[1])){
			
			unset($blacklist[$k]);
			}else{
			$blacklist[$k]="{$ipw[0]}:{$ipw[1]}";
			//echo "X:$be<br/>";
		}
	}

	 
	/* sorting stuff */
	$desc=isset($_GET['d']);
	$fradx=(isset($_GET['fr'])&&!$desc?"&d":"");
	$ctadx=(isset($_GET['ct'])&&!$desc?"&d":"");
	$dxadx=(isset($_GET['dx'])&&!$desc?"&d":"");
	$exadx=(isset($_GET['ex'])&&!$desc?"&d":"");
	
	$poadx=(isset($_GET['po'])&&!$desc?"&d":"");
	//$sqadx=(isset($_GET['sq'])&&!$desc?"&d":"");
	$rfadx=(isset($_GET['rf'])&&!$desc?"&d":"");
	
	$pladx=($fradx.$ctadx.$dxadx.$exadx==""?"&d":"");
	
	/* cmp
	 * Compares 2 variables
	 * If "&d" is present in request URL, the value is inverted.
	 * If     | Returns
	 * a <  b | -1
	 * a == b |  0 
	 * a >  b |  1
	 */	 
	/*function cmp($a, $b)
	{
		global $desc;
		if ($a == $b) {
			return 0;
		}
		$x = ($a < $b) ? -1 : 1;
		
		if($desc) return -$x;
		return $x;
	}*/


	/* callback functions for sorting */
	
	/* servers list */
	function sortser($a,$b){return -cmp(pow($a['rfscore'],1.6)*($a['numplayers']+1),pow($b['rfscore'],1.6)*($b['numplayers']+1));}
	
	function sortserrf($a,$b){return -cmp($a['rfscore'],$b['rfscore']);}
	//function sortsersq($a,$b){return -cmp($a['sqscore'],$b['sqscore']);}
	function sortserpl($a,$b){return -cmp($a['uplayers'],$b['uplayers']);}
	function sortseropl($a,$b){return -cmp($a['numplayers'],$b['numplayers']);}
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

$report="";

if(isset($_GET['serv'])){
	$sid=(int)$_GET['serv'];
	$updrate=$utt_cfg['General']['IntervalMins'];
	//T: 63 ms
	$s=sqlquery("SELECT serverinfo.`name` as n,serverinfo.`serverid` as sid, serverinfo.`address` as ip, serverinfo.`rules` as rules, serverhistory.`mapname` as map, serverhistory.`gameid` as gameid FROM serverinfo
	LEFT JOIN serverhistory ON serverhistory.serverid=serverinfo.serverid WHERE serverinfo.serverid=$sid",1,$dbh);
	//echo time()-$s['datex'];
	if(isset($_GET['refresh'])){
		FetchServerInfo($s['ip']);
		$s=sqlquery("SELECT serverinfo.`name` as n,serverinfo.`serverid` as sid, serverinfo.`address` as ip, serverinfo.`rules` as rules, serverhistory.`mapname` as map, serverhistory.`gameid` as gameid FROM serverinfo
		LEFT JOIN serverhistory ON serverhistory.serverid=serverinfo.serverid WHERE serverinfo.serverid=$sid",1,$dbh);
	}
	
	if($s['n']=="") error404();
	
	$servip=$s['ip'];
	$srules=json_decode($s['rules'],true);
	if(isset($srules['queryport'])){
		$servip = strtok($servip,":").":".$srules['queryport'];
	}
	$report.=htmlspecialchars($s['n'])."<br/>\n";
	
	$report.="query address: $servip<br/>";
	$report.="detailed report:<br/>\n";
	
	try{
			
		$globalSock->connect($servip);
		$qwEcho=utServerQuery($servip,'\\echo\\Checking for fake players\\');
		if(!count($qwEcho) || reset($qwEcho) != "Checking for fake players") throw new Exception("couldn't contact the server (echo failed)");
		
		
		
		$report.="Testing server generosity: ";
		$qwInfo=utServerQuery($servip,'\\info\\xserverquery');
		if(isset($qwInfo['xserverquery'])) {
			$report.="XServerQuery {$qwInfo['xserverquery']} ";
			throw new Exception("xserverquery detected, cannot proceed.");
		}
		$report.="UdpServerQuery ";
		
		$qwGen=utServerQuery($servip,"\\game_property\\testOfGenerosity\\");
		
		if(reset($qwGen)==="*Private*") 
			throw new Exception("server does not give away enough information, do a manual check");
		
		$report.= "GameInfo ";
		$qwGen=utServerQuery($servip,'\\level_property\\TimeSeconds\\');
		if(is_numeric(reset($qwGen))){
			$report.="LevelInfo ";
		}
		$report.="<br/>";
		$qwBasic=utServerQuery($servip,'\\basic\\');
		if(isset($qwBasic['gamename'])) $report.="Gamename: {$qwBasic['gamename']}<br/>";
		if(isset($qwBasic['gamever'])) $report.="Gamever: {$qwBasic['gamever']}<br/>";
		$qwPlayers=utServerQuery($servip,'\\player_property\\Player\\');
		$qwNumplayers=utServerQuery($servip,'\\game_property\\NumPlayers\\');
		$qwSpect=utServerQuery($servip,'\\game_property\\NumSpectators\\');
		$globalSock->disconnect();
		
		$report.="server says numplayers: ".htmlspecialchars($qwInfo['numplayers'])."<br/>\n";
		$report.="GameInfo.NumPlayers says: ".reset($qwNumplayers)."<br/>\n";
		$report.="GameInfo.NumSpectators says: ".reset($qwSpect)."<br/>\n";
		$report.="connected clients:<br/>\n";
		$conCl=0;
		foreach($qwPlayers as $qp){
			if($qp!="None"){
				$qp=htmlspecialchars($qp);
				$report.="$conCl : $qp<br/>\n";
				$conCl++;
			}
		}
		$report.="Connected clients: $conCl<br/>\n";
		if($qwInfo['numplayers'] > (reset($qwNumplayers)+reset($qwSpect)) || $qwInfo['numplayers'] > $conCl){
			$report.="<b>this server has fake players</b><br/>\n";
		}else{
			$report.="<b>this server looks clean ,no fake players detected</b><br/>\n";
		}


	}catch(Exception $ex){
		$report.="<br/><b>".htmlspecialchars($ex->getMessage())."</b><br/>\n";
	}
} 


	$updrate=$utt_cfg['General']['IntervalMins'];
	$serversperpage=20;
	

		
	$currentpage=(isset($_GET['p'])?(int)$_GET['p']:1);
	$serversstart=($currentpage-1)*$serversperpage;

	$blx=array();
	
	foreach($blacklist as $bk){
		//echo "$bk => " . abs(crc32($bk)) . "<br/>";
		$blx[]=abs(crc32($bk));
	}
	
	$servstat=sqlquery("SELECT * FROM serverinfo WHERE serverid NOT IN(".implode(",",$blx).")",null,$dbh);

	
	
?>
<!DOCTYPE HTML>
<html>
<head>
<meta charset='utf-8'/>
<link rel='stylesheet' href='<?=maklink(LSTATICFILE,"css/crap.css","")?>'/>
<title>Unreal Tournament Tracker</title>
</head>
<body>
<div id='body_cont'>
<h1><a href="/">Unreal Tournament Tracker</a></h1>
<p>blah blah random letters</p>
<h3>Servers stats - servers which might have fake players</h3>
<?php
	//$rfavg=rf_avg($servstat);

	
	//echo "DEBUG: RF AVG=$rfavg<br/>";
	/*foreach($servstat as &$se){
		if(striposa($se['address'],$blacklist)!==false){
			$se['bl']=true;
		}else if(isset($se['bl'])){
			unset($se['bl']);
		}
		
	}*/
	
	if(isset($_GET['filter'])){
		$filterTypes=explode(",",$_GET['filter']);
	}else{
		$filterTypes=array();
	}
	$onlineplayers=0;
	$onlineservers=0;
	foreach ($servstat as $k=>&$s){
		if(time()-$s['lastscan']>900) {
			unset($servstat[$k]);
			continue;
		}
		$srules=json_decode($s['rules'],true);
		if($srules['gamename']!="ut" && $srules['gamename']!="unreal"){
			continue;
		}
		if(!isset($srules['numplayers'])) {
			$s['numplayers']=0;
			$s['maxplayers']=0;
			$s['humanplayers']=0;
			$s['lastupd']=0;
			$s['mapname']="";
			$s['gametype']="";
			$s['mutators']="";
			
		}else{
			$s['numplayers']=$srules['numplayers'];
			$s['maxplayers']=$srules['maxplayers'];
			$s['humanplayers']=(isset($srules['__uttrealplayers'])?$srules['__uttrealplayers']:-1);;
			$s['mapname']=(isset($srules['mapname'])?$srules['mapname']:"");
			$s['gametype']=(isset($srules['gametype'])?$srules['gametype']:"");
			$s['mutators']=(isset($srules['mutators'])?$srules['mutators']:"");
			$s['lastupd']=(isset($srules['__uttlastupdate'])?$srules['__uttlastupdate']:0);
		}
		if($rplast < $s['lastupd']-600) $scannerReboot=true;
		else if($rplast < time() - 600) $scannerOffline=true;
		/*if(time()-$s['lastupd']>900){
			if( !isset($scannerOffline)){
				unset($servstat[$k]);
			}else{
				$s['numplayers']=0;
			}
			continue;
		}	
		$onlineplayers+=$s['numplayers'];
		$onlineservers++;
		if(isset($_GET['search']) && $_GET['search']=='s' && stripos($s['name'],$_GET['name']) === false){
			unset($servstat[$k]);
			continue;
		}
		
		$s['gtypes']=getServerTags($s);
		
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
		}*/
		

	}
	if(isset($_GET['ct'])){
		usort($servstat,'sortserpl');
	}else if(isset($_GET['po'])){
		usort($servstat,'sortseropl');
	}else if(isset($_GET['rf'])){
		usort($servstat,'sortserrf');
	}else{
		usort($servstat,'sortser');
	}
	
	if(!isset($_GET['d'])) {
		array_reverse($servstat);
	}
	
	$servstatallsize=count($servstat);
	echo $report;
	if(isset($scannerReboot)) echo "<big>The server scanner is being restarted. Some servers might not be shown yet.</big><br/>\n";
	//else if(isset($scannerOffline)) echo "<big>The server scanner is not running for some reason.</big>\n";
	echo "the list below is generated using data grabbed by uttracker scanner, so some servers might be false positives. click 'query for report' to get fresh data.";
	echo "<table class='huge' id='masterlist'>\n";

	echo "<thead>\n\t<tr>
		<th>IP</th>
		<th>Server name</th>
		<th>Players online</a></th>
		<th>Unique players</a></th>
		<th>RFScore</a></th>
	</tr>\n</thead>\n";
	echo "<tbody>\n";
	$cld=0;
	//$lastupd=(int)sqlquery("SELECT `data` FROM utt_info WHERE `key`='gacke_last'",1)['data'];
	//$lastupd=(int)sqlquery("SELECT `data` FROM utt_info WHERE `key`='reaper_last'",1)['data'];
	//echo "LASTUPD=$lastupd";
	//foreach ($servstat as &$s){

	
	$serversperpageLOOP=$serversperpage;
	foreach($servstat as $i=>$s){
	//for($i=0; $i<$serversperpageLOOP;$i++){
		//if(!isset($servstat[$i+$serversstart])) break;
		//$s=$servstat[$i+$serversstart];
		if($s['humanplayers'] == -1 || $s['humanplayers'] >= $s['numplayers']) continue;
		//if(isset($s['bl'])) continue;
		//$cld=min(max($cld,$s['ld']),date("d"));	
		
		//if(striposa($s['address'],$blacklist)!==false){continue;}
		//if($cld++ == 200) break;
		
		$lastupd=$s['lastupd'];
		$rf=$s['rfscore']; //round(rf($s)/($rfavg+0.0001)*650);
		//$sq=$s['sqscore'];
		$upl=$s['uplayers'];
		if(time()-$s['lastrfupdate']>86400*2) {
			$rf="n/a";
			$upl="n/a";
		}
		//$rf="n/a";
		//$upl="n/a";
		list($ipa,$port)=explode(":",$s['address'],2);
		$ip=$ipa.":" . ((int)$port-1);

		
		
		//if($rf<10 && $sq<100) continue;
		
		//$gt=implode("]<br/>[",$gtypes);


		
		echo "\t<tr class='mlrow' id='serv_{$s['serverid']}'>
		<td>$ip</td>
		<td><b>".htmlspecialchars($s['name'])."</b><!--<a href='".maklink(LSERVER,$s['serverid'],$s['name'])."'>".htmlspecialchars($s['name'])."</a>-->";
		if($s['humanplayers'] != -1 && $s['humanplayers'] < $s['numplayers']){
			echo "<br/>server says {$s['numplayers']}, detected {$s['humanplayers']} <a href=\"?serv={$s['serverid']}\">(query for report)</a>";
		}
		echo "</td>
		<td>";
		//echo time()."-$lastupd";
		
		if(time()-$lastupd<900){
			$numpl=$s['numplayers'];
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
		
		echo "<span class='$mlcplclass'><span class='mlcpl_numplayers'>$numpl</span> / <span class='mlcpl_maxplayers'>$maxpl ({$s['humanplayers']})</span></span>";
		echo "</td>
		<td>".$s['uplayers']."</td>
		<td>".$rf."</td>\n\t</tr>\n";

	}
	echo "</tbody>\n";
	echo "</table>\n";
	
	echo "<hr/>\n";
echo "RFScore is a server popularity factor based on the data from the current week.<br/>
SQScore shows the players attachment to the server.<br/>";
echo "VB updater status: N/A, last update: ".date("d-m-Y G:i:s",0);



sqldestroy($dbh);


//echo "SQL HISTORY:<br/>\n".$sqlqueries;

?><br/>

<small>2013 namonaki14</small>
<?php 
include "tracking.php";



?>
</div>
</body>
</html>