<?php

	date_default_timezone_set ('GMT');
	
	
	require_once "config.php";
	require_once "sqlengine.php";
	require_once "geoiploc.php";
	require_once "common.php";
	require_once N14CORE_LOCATION . "/TableThing.php";
		
	use N14\TableThing as TableThing;
	use N14\TableThingColumnInfo as TTCI;

	$dbh=sqlcreate($statdb_host,$statdb_user,$statdb_pass,$statdb_db);
	
	printf($headerf,"",\N14\GetText\getlocale(),"");
		
	if(isset($_GET['playerSearch'])){
		$currenturl=strtok($_SERVER['REQUEST_URI'],"?");
		$expurl=strtok(maklink(LSEARCHPLAYER,null,$_GET['playerSearch']),"?");
		if(strpos($expurl,$currenturl)===false) {
			permredir($expurl);
		}
		
		
		//$px=sqlquery("SELECT *, playerhistory.lastupdate as lastupdate FROM playerinfo RIGHT JOIN playerhistory ON playerinfo.id=playerhistory.id WHERE LCASE(name)='".sqlite_escape_string(strtolower($_GET['playerSearch']))."' ORDER BY lastupdate DESC LIMIT 1",1);
		
		
		echo "<h2>".__("Search result")."</h2>";
		
		$pn=trim($_GET['playerSearch']);
		if(strlen($pn)<3) die(__("Your query is too short."));
		$title=__("Search").": ".htmlspecialchars($pn)." - ";
		
		$conds[]="playerinfo.id=playerhistory.id";
		if(isset($_GET['server']) && is_numeric($_GET['server'])){
			$conds[]="playerhistory.serverid=".(int)$_GET['server'];
			
		}
		$whereCond="LOWER(playerinfo.name) LIKE '%".sqlite_escape_string_like(strtolower($pn))."%'";
		
		$px=sqlquery("SELECT max(playerhistory.lastupdate) as lastupdate, playerinfo.id, name, skindata,country FROM playerinfo LEFT JOIN playerhistory ON ".implode(" AND ",$conds)." WHERE $whereCond GROUP BY playerinfo.id ORDER BY lastupdate DESC",null,$dbh);
		if(count($px)>1){
			foreach($px as $k=>$d){
				if(stripos($d['skindata'],"Spectator|")===0){
					unset($px[$k]);
					unset($d);
				}
			}
		}
		reset($px);
		if(count($px)==1 && strtolower($pn) == strtolower(current($px)['name'])) {
			
			header("Location: player.php?id=".current($px)['id']);
			exit;
		}else if(count($px)==0){
			printf(__("Player \"%1\$s\" was not found in the database. Keep in mind that this search engine is the LAMEST implementation possible, so you need to type the the exact name (or part of it).<br>"),htmlspecialchars($pn));
		}else{
			if(strtolower($pn)=="fabs"){
				header("Location: http://thepiratebay.org");
				exit;
			}
			//echo "<h2>".__('Here\'s a list of known software pirates').":</h2><small>".__('There\'s a pirated version of UT on torrents that has default player\'s name set to \'Fabs\'. Those guys below have probably downloaded it without changing the name').".</small>\n";
			
			echo '<h3>';
			printf(__("Found %1\$d players with \"%2\$s\" in name."),count($px),htmlspecialchars($pn));
			echo "</h3>\n";
			echo "<table class='light'><tbody>\n";
			foreach($px as $d){
				if(!$d['lastupdate']) continue;
				list($ms,$sk,$fc)=explode("|",$d['skindata']);
				$flag=getflag($d['country']);
				echo "<tr>\n<td>".getSkinImage($ms,$sk,$fc)."</td>\n<td>\n<a href='".maklink(LPLAYER,$d['id'],$d['name'])."' class='plist_player utt-avatar'><span class='playername'>$flag".htmlspecialchars($d['name'])."</span>\n";
				if($d['lastupdate']) echo __("Last seen online").": <span class='lastseen' data-time='{$d['lastupdate']}'>".uttdateFmt($d['lastupdate'])."</span></a>\n";
				echo "</td>\n</tr>\n";
			}
			echo "</tbody></table>\n";
		}
		
		//var_dump($px);
	} 
	

	sqldestroy($dbh);



?><br>


<small>'11, '13 namonaki14, Wolver M.G.</small>
<!-- Start of StatCounter Code for Default Guide -->
<script type="text/javascript">
//<![CDATA[
var sc_project=9919866; 
var sc_invisible=0; 
var sc_security="db9f78b5"; 
var scJsHost = (("https:" == document.location.protocol) ?
"https://secure." : "http://www.");
document.write("<sc"+"ript type='text/javascript' src='" +
scJsHost+
"statcounter.com/counter/counter_xhtml.js'></"+"script>");
//]]>
</script>
<noscript><div class="statcounter"><a title="site stats"
href="http://statcounter.com/" class="statcounter"><img
class="statcounter"
src="http://c.statcounter.com/9919866/0/db9f78b5/1/"
alt="site stats" /></a></div></noscript>
<!-- End of StatCounter Code for Default Guide -->
</div>
</body>
</html>