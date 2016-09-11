<?php
/*
 i hereby regret naming this piece of crap an "engine"
 REPLACE THIS WITH PDO ASAP!!
**/

require_once "Installer/appInstallationVerificator.php";

$sqlcount=0;

$sqbuffers=array();
$sqcons=array();


$sqlqueries="";

function sqlcreate($host,$user,$pass,$db,$isMain=true){ // 2009-04
	global $sqlconn,$sqbuffers,$sqcons;
	
	try{
		$sqlconn=new PDO("mysql:host=$host;dbname=$db;charset=utf8",$user,$pass);
		$sqlconn->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);
	}catch(Exception $e){
		sqlerr("OPEN", $e->getMessage());
	}
	if(!is_object($sqlconn) || get_class ($sqlconn) != "PDO") exit;
	//sqlite_exec($sqlconn,"pragma synchronous = off;");
	//$sqlconn->busyTimeout(5000);
	$num=-1;
	if(count($sqcons)>0){
		for ($i=0;$i<100;$i++){
			if(!isset($sqcons[$i]) || get_class($sqcons[$i]) != "PDO"){
				$num=$i;
				break;
			}
		}
	}else{
		$num=0;
	}
	
	if($num==-1) return false;
	
	$sqbuffers[$num]['b']="";
	$sqbuffers[$num]['c']=0;
	$sqbuffers[$num]['e']=true;
	$sqbuffers[$num]['t']=false;
	$sqcons[$num]=$sqlconn;
	
	if($isMain){
		checkTables($sqlconn);
	}
	
	return $num;
}

function sqlgethandle($h){
	global $sqcons;
	if($h===null) $h=key($sqcons);
	return $sqcons[$h];
}

function sqllastinsertid($h=null){
	global $sqcons;
	if($h===null) $h=key($sqcons);
	return $sqcons[$h]->lastInsertId();
}

function sqldestroy($h){
	global $sqcons;
	sqlcommit();
	//sqlite_close($sqcons[$h]);
	unset($sqcons[$h]);
}

function sqlquerytraversable($query, $handle=null) // '14-07 iterator-able or something
{
	global $sqcons,$sqlqueries;

	if($handle===null) $handle=key($sqcons);
	//$sqlcount++;

	
	if(SQLENG_DEBUG) $sqlqueries.="DB$handle: $query\n";	
	$tx=microtime(true);
	//try{
		//$qresult=sqlite_query($query, $sqcons[$handle]);
	$qw=$sqcons[$handle]->query($query);
	if($sqcons[$handle]->errorInfo()[0]!=0){
		sqlerr($query, $sqcons[$handle]->errorInfo()[2], 1, 0, "");
	}
	
	if(SQLENG_DEBUG) $sqlqueries .= "T: " . round((microtime(true)-$tx)*1000) . " ms\n";
	if($qw===false) return false;
	return $qw;
}

function sqlfetch($statement){
	return $statement->fetch (PDO::FETCH_ASSOC);
}


function sqlquery($query, $limit=null,$handle=null,$fetchType=PDO::FETCH_ASSOC) // 2008-07 From SA:MP Server Browser
{
	global $sqcons,$sqlqueries;
	
	if($handle===null) $handle=key($sqcons);
	
	$arr=null;
	
	if(SQLENG_DEBUG) $sqlqueries.="DB$handle: $query\n";	
	$tx=microtime(true);

	$qw=$sqcons[$handle]->query($query);
	if($sqcons[$handle]->errorInfo()[0]!=0){
		sqlerr($query, $sqcons[$handle]->errorInfo()[2], 1, 0, "");
	}
	if($qw===false) return false;
	$arr=$qw->fetchAll ($fetchType);

	
	
	if(SQLENG_DEBUG) $sqlqueries .= "T: " . round((microtime(true)-$tx)*1000) . " ms\n";
	if ( $limit !== 0)
	{

		if($limit==1){
			reset($arr);
			return current($arr);
		}
		
		return $arr;
	}
	
	return true;
}

function sqlexec($query,$handle=null){ // 2009-03 NEW
	global $sqcons, $sqbuffers,$sqlqueries;
	if($handle===null) $handle=key($sqcons);
	
	if($sqbuffers[$handle]['e'] && !$sqbuffers[$handle]['t']){
		$sqcons[$handle]->exec("START TRANSACTION");
		$sqbuffers[$handle]['t']=true;
	}
	
	sqlexecnow($query,$handle);
	
	/*if($sqbuffers[$handle]['e']){
		$sqbuffers[$handle]['b'].=$query.";\n";
		if(++$sqbuffers[$handle]['c'] >= 500) {
			sqlcommit($handle);
		}
	}else{
		$sqlqueries.="DB$handle: {$sqbuffers[$handle]['b']}\n";
		$tx=microtime(true);
		//sqlite_exec($sqcons[$handle],$query);
		$sqcons[$handle]->exec($query);
		$sqlqueries .= "T: " . round((microtime(true)-$tx)*1000) . " ms\n";
	}	*/
}
function sqlexecnow($query,$handle=null){ // 2009-03 NEW
	global $sqcons, $sqbuffers,$sqlqueries;
	if($handle===null) $handle=key($sqcons);
	
	if(SQLENG_DEBUG) $sqlqueries.="DB$handle: $query\n";
	$tx=microtime(true);
	//sqlite_exec($sqcons[$handle],$query);
	$rows=$sqcons[$handle]->exec($query);
	if($sqcons[$handle]->errorInfo()[0]!=0){
		sqlerr($query, $sqcons[$handle]->errorInfo()[2], 1, 0, "");
	}
	if(SQLENG_DEBUG) $sqlqueries .= "T: " . round((microtime(true)-$tx)*1000) . " ms\n";
	return $rows;
}



function sqlcommit($handle=null){
	global $sqcons, $sqbuffers,$sqlqueries;
	if($handle===null) $handle=key($sqcons);
	
	if($sqbuffers[$handle]['e'] && $sqbuffers[$handle]['t']){
		$sqcons[$handle]->exec("COMMIT");
		$sqbuffers[$handle]['t']=false;
	}
	//old code:
	/*if($sqbuffers[$handle]['e']){
		if($sqbuffers[$handle]['c'] > 0) {
		
			$sqlqueries.="DB$handle: BEGIN;{$sqbuffers[$handle]['b']}COMMIT;\n";
			$tx=microtime(true);
			
			/*sqlite_exec($sqcons[$handle],"BEGIN;");
			sqlite_exec($sqcons[$handle],$sqbuffers[$handle]['b']);
			sqlite_exec($sqcons[$handle],"COMMIT;");* /
			
			$sqcons[$handle]->beginTransaction();
			$sqcons[$handle]->exec($sqbuffers[$handle]['b']);
			$sqcons[$handle]->commit();
			
			
			
			$sqlqueries .= "T: " . round((microtime(true)-$tx)*1000) . " ms\n";

			$sqbuffers[$handle]['c']=0;
			$sqbuffers[$handle]['b']="";
		}
	}*/
	
}


function sqlsetvalue($table, $name, $value, $where, $wvalue,$handle=null)
{
	
	global $sqcons;
	if($handle===null) $handle=key($sqcons);
	$sqlcount++;

	return sqlite_exec($sqcons['handle'],"UPDATE ".$table." SET ".$name."=\"".$value."\" WHERE ". $where . "=\"" . $wvalue . "\"");
}

function sqlclose($handle)
{
	unset($handle);
}

function sqlite_escape_string($s)
{
    return SQLite3::escapeString ($s);
} 

function sqlite_escape_string_like($s)
{
    return str_replace(array("_","[","*","%"), array("\_","\[","%","\%"),SQLite3::escapeString ($s));
} 
  
function sqlerr($query="?", $func="nieznanafunkcja", $writetolog=1, $line=__LINE__, $file=__FILE__)
{
	global $user,$sqlerrors,$sqlconn;
	throw new Exception("There was a problem with generating this page. Details:<br>".$func."<br>".nl2br($query,true)."<br>");
	/*
	if($sqlerrors!==FALSE)
	{
		fb("Error: Cannot query MySQL server in $file, on line $line. Query: 
	$query\nMySQL returned error: ".mysql_error() ,FirePHP::ERROR);*/
	/*if(strpos(mysql_error(), "Too many connections") === FALSE)
		echo "Błąd bazy danych; strona może nie zostać wyświetlona prawidłowo<br /><br />";
		else
		echo "Serwer bazy danych jest przeciążony<br />Proszę spróbować ponownie.<br /><br />";

	}*/
	/*if($writetolog==1)
	{*/
		/*$flog=fopen("sqlengine_logs.txt", "a");
		fwrite($flog, date("c")." Q(($query)) E(($func)) F((".$_SERVER['SCRIPT_NAME']."))\n");
		fclose($flog);*/
	//}
}

function pathencode($str)
{
	$serch=array(" ", "!", "?", "_", ".", "(", ")", "\"", ",", ":", "ą", "ć", "ę", "ł", "ń", "ó", "ś", "ź", "ż", "Ą", "Ć", "Ę", "Ł", "Ń", "Ó", "Ś", "Ź", "Ż");
	$replc=array("-", "",  "",  "-", "",  "",  "",  "",   "",  "-", "a", "c", "e", "l", "n", "o", "s", "z", "z", "A", "C", "E", "L", "N", "O", "S", "Z", "Z");
	return substr(str_replace($serch, $replc, $str),0,20);
}
  //error_reporting($prev_err);
  
//  require_once("bandwidthlimits.php");
  
  
//'14-09-23
//http://stackoverflow.com/a/7225638

if ( basename(__FILE__) == basename($_SERVER["SCRIPT_FILENAME"]) ){
	header("HTTP/1.1 403 Forbidden");
	echo "Nice try.";
	exit;
}
  
  
 ?>