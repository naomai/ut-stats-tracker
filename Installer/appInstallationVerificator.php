<?php


function checkTables($mysqlConnection=null){
	global $appTables;
	
	$tables = sqlquery("SHOW TABLES");
	$tablesList=array();
	foreach($tables as $tableInfo){
		$tablesList[] = reset($tableInfo);
	}
	colAsKey($tables,"Tables_in_".$GLOBALS['statdb_db']);
			
	foreach($appTables as $ref=>$tableName){
		if(!in_array($tableName,$tablesList)){
			
			intallationErrorHeader();
			echo "<h1>Game stats tracker is not properly installed.</h1>
			<p>Make sure you created all needed tables:</p>
			<ul>";
			//$tables = sqlquery("SHOW TABLES");
			//print_r($tables);
			foreach($GLOBALS['appTables'] as $ref=>$tableName){
				if(!isset($tables[$tableName])){
					$style = "color: #f00";
				}else{
					$style = "color: #333";
				}
				echo "<li><b style=\"$style\">$tableName</b><span style=\"font-size: 8pt; color: #999\"> (as $ref)</span></li>\r\n";
			}
			echo "</ul>\r\n";
			installationErrorFooter();
			exit;
		}
		
	}
}


function intallationErrorHeader(){
	echo "<!DOCTYPE HTML>
<html lang='en'>
<head>
	<meta charset='utf-8'/>
	<link rel='stylesheet' href='". maklink(LSTATICFILE,"css/crap.css","") ."'/>
	<title>Whoops! - UT99 Tracker</title>
</head>
<body class='dark' id='uttrk'>
	<div id='body_cont'>
";
}

function installationErrorFooter(){
	echo "		</pre>
		<br><br>
		<small>uttracker '13 namonaki</small>
	</div>
</body>
</html>";
}

?>