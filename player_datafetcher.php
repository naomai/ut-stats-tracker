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

	$pi=sqlquery("SELECT * FROM playerinfo WHERE id=$pid LIMIT 1",1);	
	
	$tables = sqlquery("SHOW TABLES",null,null,PDO::FETCH_BOTH);
	
	$phTables = array_filter($tables, function($v){return strpos($v[0],"playerhistory")===0;});
	
	$ph = array();
	
	$ps=sqlquery("SELECT * FROM playerstats WHERE playerid=$pid");	
	
	foreach($phTables as $table){
		$tableName = $table[0];
		$phX=sqlquery("SELECT * FROM `$tableName` WHERE id=$pid ORDER BY gameid ASC");	
		$ph = array_merge($ph,$phX);
	}
	
	
	if(!count($pi) || $pi['name']=="") {
		$result['status']=404;
		$result['error']['code']='404';
		$result['error']['message']='Playerid '.$pid.' not found';
	}else{
		$pname=$pi['name'];
		$pskin=$pi['skindata'];
		
		$result['status']=200;			
		$result["description"]="The following is the data collected by UTTracker for player {$pname}";
		$result["explain"]['id']="abs ( crc32 ( strtolower ( \"{$pname}|\" . ( name_is_complicated ( \"{$pname}\" ) ? \"3456\" : strtok ( \"{$pskin}\", \"|\" ) ) ) ) );";
		$result["explain"]['serverid']="abs ( crc32 ( \$server_ip ) ); // \$server_ip = ip with ut query port (game port+1)";
		$result["explain"]['recordid']="abs ( crc32 ( {$pid} ) ^ crc32 ( \$gameId ) );";
		$result["explain"]['name_is_complicated']="strlen ( \"{$pname}|\" ) >= 10 || strpbrk( \"{$pname}|\", '[](){}<>~`!@#$%^&*-=_/;:\'\",.?' ) !== false;";
		//$result["sqlhistory"]="$sqlqueries";

		
		$result['playerinfo']=$pi;
		$result['playerhistory']=$ph;
		$result['playerstats']=$ps;
	}
	
	
	
	$code="";
	if($ctype=="json") {
		header("Content-type: text/plain");
		$code=json_encode($result,JSON_PRETTY_PRINT);
	}else if($ctype=="xml") {
		header("Content-type: text/xml");
		
		$code="<"."?xml version=\"1.0\" encoding=\"utf-8\"?>\n<?xml-stylesheet type=\"text/css\" href=\"$assetsPath/rawxml.css\"?>\n";
		
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
		$code.="<playerinfo>\n";
		foreach($result['playerinfo'] as $pk=>$px){
			$code.="<$pk>".htmlspecialchars($px)."</$pk>\n";
		}
		$code.="</playerinfo>\n";
		$code.="<playerhistory>\n";
		$row1=reset($result['playerhistory']);
		$code.="<header>";
		foreach($row1 as $pxk=>$pxx){
			$code.="<column>".htmlspecialchars($pxk)."</column>\n";
		}
		$code.="</header>";
		
		foreach($result['playerhistory'] as $pk=>$px){
			$code.="<record>\n";
			foreach($result['playerhistory'][$pk] as $pxk=>$pxx){
				$code.="<$pxk>".htmlspecialchars($pxx)."</$pxk>\n";
			}
			$code.="</record>\n";
		}
		$code.="</playerhistory>\n";
		$code.="<playerstats>\n";
		$row1=reset($result['playerstats']);
		$code.="<header>";
		foreach($row1 as $pxk=>$pxx){
			$code.="<column>".htmlspecialchars($pxk)."</column>\n";
		}
		$code.="</header>";
		
		foreach($result['playerstats'] as $pk=>$px){
			$code.="<record>\n";
			foreach($result['playerstats'][$pk] as $pxk=>$pxx){
				$code.="<$pxk>".htmlspecialchars($pxx)."</$pxk>\n";
			}
			$code.="</record>\n";
		}
		$code.="</playerstats>\n";
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