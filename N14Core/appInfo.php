<?php
	$n14AppConfig = array();
	
	$n14AppConfig['Host']='blackmore.no-ip.pl';
	$n14AppConfig['Url']='http://'.$n14AppConfig['Host']."/n14app";
	$n14AppConfig['Name']="N14Core";
	$n14AppConfig['FullName']="Unnamed application";
	$n14AppConfig['Credits']="'13 namonaki14";
	$n14AppConfig['AssetsPath']="//blackmore.no-ip.pl/static";
	$n14AppConfig['AssetsPathGeneric']="//blackmore.no-ip.pl/static";
	
	define('N14CORE_API', '0.1THIN');
	define('N14CORE_LOCATION', __DIR__ . "/v" . N14CORE_API);
	
	
?>