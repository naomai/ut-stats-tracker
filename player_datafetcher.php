<?php


	date_default_timezone_set ('GMT');

	require_once "sqlengine.php";
	require_once "config.php";
	require_once "common.php";
	require_once "datafetchercommon.php";

	$dbh=sqlcreate($statdb_host,$statdb_user,$statdb_pass,$statdb_db);
	$result=array();
	
	$pid=(int)$_GET['id'];
	$ctype=$_GET['ctype'];

	$pi=sqlquery("SELECT * FROM players WHERE id=$pid LIMIT 1",1);	
	
	$tables = sqlquery("SHOW TABLES",null,null,PDO::FETCH_BOTH);
	
	$phTables = array_filter($tables, function($v){return strpos($v[0],"player_logs")===0;});
	
	$ph = array();
	
	$ps=sqlquery("SELECT * FROM player_stats WHERE player_id=$pid");	
	
	foreach($phTables as $table){
		$tableName = $table[0];
		$phX=sqlquery("SELECT * FROM `$tableName` WHERE player_id=$pid ORDER BY match_id ASC");	
		$ph = array_merge($ph,$phX);
	}
	
	
	if(!count($pi) || $pi['name']=="") {
		$result['status']=404;
		$result['error']['code']='404';
		$result['error']['message']='Playerid '.$pid.' not found';
	}else{
		$pname=$pi['name'];
		$pskin=$pi['skin_data'];
		
		$result['status']=200;			
		$result["description"]="The following is the data collected by UTTracker for player {$pname}";
		$result["explain"]['id']="abs ( crc32 ( strtolower ( \"{$pname}|\" . ( name_is_complicated ( \"{$pname}\" ) ? \"3456\" : strtok ( \"{$pskin}\", \"|\" ) ) ) ) );";
		$result["explain"]['server_id']="abs ( crc32 ( \$server_ip ) ); // \$server_ip = ip with ut query port (game port+1)";
		//$result["explain"]['recordid']="abs ( crc32 ( {$pid} ) ^ crc32 ( \$gameId ) );";
		$result["explain"]['name_is_complicated']="strlen ( \"{$pname}|\" ) >= 10 || strpbrk( \"{$pname}|\", '[](){}<>~`!@#$%^&*-=_/;:\'\",.?' ) !== false;";
		//$result["sqlhistory"]="$sqlqueries";

		
		$result['players']=$pi;
		$result['player_logs']=$ph;
		$result['player_stats']=$ps;
	}
	
	
	
	$code="";
	if($ctype=="json") {
		header("Content-type: application/json");
		$code=json_encode($result);
	}else if($ctype=="xml") {
		header("Content-type: text/xml");
		
		$code="<"."?xml version=\"1.0\" encoding=\"utf-8\"?>\n<?xml-stylesheet type=\"text/xsl\" href=\"$assetsPath/playerinfo.xsl\"?>\n";
		
		$code.="<playerdata>\n";
		$code.="<description>{$result['description']}</description>\n";
		if(isset($result['error'])){
			$code.="<error>\n";
			foreach($result['error'] as $pk=>$px){
				$code.="<$pk>".htmlspecialchars($px)."</$pk>\n";
			}
			$code.="</error>\n";
		}
		$code.="<explain>\n";
		foreach($result['explain'] as $pk=>$px){
			$code.="<$pk>".htmlspecialchars($px)."</$pk>\n";
		}
		$code.="</explain>\n";
		$code.="<players>\n";
		foreach($result['players'] as $pk=>$px){
			$code.="<$pk>".htmlspecialchars($px)."</$pk>\n";
		}
		$code.="</players>\n";
		$code.="<player_logs>\n";
		$row1=reset($result['player_logs']);
		$code.="<header>";
		foreach($row1 as $pxk=>$pxx){
			$code.="<column>".htmlspecialchars($pxk)."</column>\n";
		}
		$code.="</header>";
		
		foreach($result['player_logs'] as $pk=>$px){
			$code.="<record>\n";
			foreach($result['player_logs'][$pk] as $pxk=>$pxx){
				 
				$code.="<$pxk>".htmlspecialchars((string)$pxx)."</$pxk>\n";
			}
			$code.="</record>\n";
		}
		$code.="</player_logs>\n";
		$code.="<player_stats>\n";
		$row1=reset($result['player_stats']);
		$code.="<header>";
		foreach($row1 as $pxk=>$pxx){
			$code.="<column>".htmlspecialchars($pxk)."</column>\n";
		}
		$code.="</header>";
		
		foreach($result['player_stats'] as $pk=>$px){
			$code.="<record>\n";
			foreach($result['player_stats'][$pk] as $pxk=>$pxx){
				$code.="<$pxk>".htmlspecialchars($pxx)."</$pxk>\n";
			}
			$code.="</record>\n";
		}
		$code.="</player_stats>\n";
		$code.="</playerdata>\n";
		
		/*$xm = new XmlDomConstruct('1.0', 'utf-8');
		$xms=$xm->createProcessingInstruction('xml-stylesheet', 'type="text/css" href="rawxml.css"');
		$xm->appendChild($xms);
		$xm->fromMixed(array("playerdata"=>$result));
		$code = $xm->saveXML();*/
	}else if($ctype=="php_raw") {
		
		header("Content-type: text/plain");
		$code="<?php\n";
		$code.=sprint_php($result,0,"\$pdata_{$pid}");
		$code.="\n?>";
	}else if($ctype=="php") {
		
		header("Content-type: text/html");
		$code="<?php\n";
		$code.=sprint_php($result,0,"\$pdata_{$pid}");
		$code.="\n?>";
		$code=highlight_string($code,true);
	}
		
	sqlclose($dbh);
	
	echo $code;
?>