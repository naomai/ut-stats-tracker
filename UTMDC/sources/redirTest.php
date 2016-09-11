<?php

require_once "redirUM.php";
require_once "redirFuzzeh.php";
require_once "redirSnipersParadise.php";
require_once "redirURH.php";
require_once "redirHaleys.php";

echo findPackageByName("record12");
function findPackageByName($pak){
	if(($url=UTMDC\RedirectUM\findPackageUrl($pak))!==false) return $url;
	if(($url=UTMDC\RedirectFuzzeh\findPackageUrl($pak))!==false) return $url;
	if(($url=UTMDC\RedirectSnipersParadise\findPackageUrl($pak))!==false) return $url;
	if(($url=UTMDC\RedirectURH\findPackageUrl($pak))!==false) return $url;
	if(($url=UTMDC\RedirectHaleysHotHouse\findPackageUrl($pak))!==false) return $url;

}
?>