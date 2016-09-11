<?php 
require_once "config.php";
require_once "sqlengine.php";
require_once "common.php";

$dbh=sqlcreate($statdb_host,$statdb_user,$statdb_pass,$statdb_db);
sqlexec($sqlAutoexec,0,$dbh);

header("Content-type: text/plain");

$limit=(isset($_GET['p'])?(int)$_GET['p']:0)*50000;

$pstat=sqlquery("SELECT id,name FROM playerinfo LIMIT $limit,50000",null,$dbh);

if(!count($pstat)) {
	header("HTTP/1.1 404 Not Found");
	die("invalid range");
}

foreach($pstat as $sz){
	echo maklink(LPLAYER,$sz['id'],$sz['name'],null,true)."\r\n";
}

?>