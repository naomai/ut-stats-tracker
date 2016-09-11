<?php 
require_once "config.php";
require_once "sqlengine.php";
require_once "common.php";

$dbh=sqlcreate($statdb_host,$statdb_user,$statdb_pass,$statdb_db);
sqlexec($sqlAutoexec,0,$dbh);

header("Content-type: text/xml");


$limit=(isset($_GET['p'])?(int)$_GET['p']:0)*50000;


$servstat=sqlquery("SELECT serverid,name,address,lastscan,rfscore FROM serverinfo WHERE lastscan > ".(time()-86400*90)." ORDER BY lastscan DESC LIMIT $limit,50000",null,$dbh);

if(!count($servstat)) {
	header("HTTP/1.1 404 Not Found");
	die("invalid range");
}

$doc = new DOMDocument("1.0", "UTF-8");
$urlSet = $doc->createElement('urlset');

$urlSet->setAttribute("xmlns","http://www.sitemaps.org/schemas/sitemap/0.9");
foreach($servstat as $sz){
	$urlNode =  $doc->createElement('url');
	
	$locEl = $doc->createElement("loc");
	$link = maklink(LSERVER,$sz,null,null,true);
	$locEl->appendChild($doc->createTextNode($link));
	$urlNode->appendChild($locEl);
	
	$lastmodEl = $doc->createElement("lastmod");
	$lastModText = date("Y-m-d",$sz['lastscan']);
	$lastmodEl->appendChild($doc->createTextNode($lastModText));
	$urlNode->appendChild($lastmodEl);
	
	$changefreqEl = $doc->createElement("changefreq");
	$chgFreq = (time() - $sz['lastscan'] > 86400 * 3) ? "weekly" : "daily";
	$changefreqEl->appendChild($doc->createTextNode($chgFreq));
	$urlNode->appendChild($changefreqEl);
	
	$priorityEl = $doc->createElement("priority");
	$priority = round(1 - min((time() - $sz['lastscan']) / (86400 * 2),0.95),2);
	$priorityEl->appendChild($doc->createTextNode($priority));
	$urlNode->appendChild($priorityEl);
	
	$urlSet->appendChild($urlNode);
}
$doc->appendChild($urlSet);
$doc->formatOutput = true;
echo $doc->saveXML();
?>