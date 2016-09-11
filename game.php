<?php
/*
 * ut tracker
 * 2013 namo
 *
 * game info page
**/
	date_default_timezone_set ('GMT'); //TODO move to cfg 

	require_once "sqlengine.php";
	require_once "config.php";
	require_once "common.php";
	require_once "ut_map_lookup.php";
	
	$dbh=sqlcreate($statdb_host,$statdb_user,$statdb_pass,$statdb_db);
	sqlexec($sqlAutoexec,0,$dbh);
	
	$tmplist="";
	$na="";
	$mat=array();

	
	$gid=(int)$_GET['gameid'];
	$pi=sqlquery("SELECT * FROM serverinfo INNER JOIN (SELECT gameid, serverid, mapname,date FROM serverhistory WHERE gameid=$gid) AS gs ON serverinfo.serverid=gs.serverid LIMIT 1",1);	

	if(count($pi)==0 || !isset($pi['mapname'])) error404();	
	
	$expurl=maklink(LGAME,$gid,$pi['serverid']."-".name2id($pi['name']));
	/*echo "CUR URL: {$_SERVER['REQUEST_URI']}<br>";
	echo "EXP URL: $expurl<br>";
	echo "MATCH: " . (strpos($_SERVER['REQUEST_URI'],$expurl)===0 ? "TRUE" : "FALSE");*/
	if(strpos($expurl,$_SERVER['REQUEST_URI'])===false) permredir($expurl);
	
	$phqw="SELECT * FROM playerhistory WHERE gameid=$gid";

	//$ph=sqlquery("SELECT *, playerinfo.name FROM playerhistory LEFT JOIN playerinfo ON playerhistory.id=playerinfo.id WHERE gameid=$gid");
	$ph=sqlquery("SELECT *, playerinfo.name FROM ($phqw) AS ph LEFT JOIN playerinfo ON ph.id=playerinfo.id WHERE gameid=$gid");

	
	
	

	function sortpld($a,$b){
		return -cmp($a['last'],$b['last']);
	}
	function sortct($a,$b){
		return -cmp($a['time'],$b['time']);
	}
	


	

	
	printf($headerf,htmlspecialchars($pi['mapname'])." (#$gid)".__("Match stats")." - ","","Stats of Match #$gid from Server ".htmlspecialchars($pi['name']).", played on ".uttdateFmt($pi['date']).".");
	

$mn=$pi['mapname'];

echo "<h2>".__('Match stats')."</h2>
<h3>played on <a href='".maklink(LSERVER,$pi['serverid'],$pi['name'])."'>{$pi['name']}</a></h3>
<p>".__('Started').": ".uttdateFmt($pi['date'])."</p>";

echo "<p>Map: <a href='".maklink(LMAP,0,$mn)."'>$mn</a></p>";
$url=findMapUrl($mn);
if($url!=""){
	echo "<p><a href='$url'>Download \"$mn\"</a> from <a href='http://".parse_url ($url, PHP_URL_HOST)."/'>".parse_url ($url, PHP_URL_HOST)."</a></p>";
}


if(count($ph)>0){
	echo "<hr/>\n";
	echo "<h3>".__('Players stats').":</h3>";
	echo "<table class='huge'>\n";

	echo "<thead>\n\t<tr>
		<th>".__('C')."</th>
		<th>".__('Player name')."</th>
		<th>".__('Time')." (*)</th>
		<th>".__('Frags')." (*)</th>
		<th>".__('Frags per hour')." (*)</th>
		<th>".__('Deaths')." (**)</th>
	</tr>\n</thead>\n";
	echo "<tbody>\n";
	echo "</tbody>\n";
	$xd=0;
	foreach ($ph as $d){
		//if($d['name']=="False") continue;
		if($xd++>=200) break;

		//$hours=($d['lastupdate']-$d['enterdate'])/3600;
		$hours=($d['lastupdate']-$d['enterdate'])/3600;
		$sd=xunserialize($d['skindata']);
		$isspec=(strtolower($sd[0])=="spectator");
		
		//if($isspec && !isset($_GET['show_spec'])) continue;
		
		$flag=getflag($d['country']);
		echo "\t<tr>
		<td>$flag</td>
		<td><a href='".maklink(LPLAYER,$d['id'],$d['name'])."'>".htmlspecialchars($d['name'])."</a>".($isspec?" (".__('Spectator').")":"")."</td>
		<td>". 
			formattime($hours) .
			"</td>
			<td>".($isspec?"-":$d['scorethismatch'])."</td>
		<td>".(!$isspec&&$hours>0?round($d['scorethismatch'] / $hours):"-")."</td>
		<td>".($d['deathsthismatch']!=""?$d['deathsthismatch']:"-")."</td>
		</tr>";
	}

	echo "</table>\n";
	echo "(*) = ".__('stats estimated, based on info received from server')."<br>\n";
	echo "(**) = ".__('only for servers with <a href=\'http://www.unrealadmin.org/forums/showthread.php?p=162211#post162211\'>XServerQuery</a> installed')."<hr/>\n";
	}else{
		echo "<h1>".__('No players playing in this match')."</h1>";
		if($pi['date']<time()-14400)
			sqlexecnow("DELETE FROM serverhistory WHERE gameid=".$pi['gameid'],$dbh);
	}

?><br><br>

<small>
	'13 '14 namonaki14, WaldoMG; amaki @ ut99.tk<br>
	UTTWEB2 &amp; UTTSS3
</small>
<?php include "tracking.php"; ?>
</div>
</body>
</html>