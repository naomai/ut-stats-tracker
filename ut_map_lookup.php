<?php
if(isset($utmdcInstalled) && $utmdcInstalled){
	// regular download
	require_once __DIR__."/utmdc/sources/utfiles.php";
	require_once __DIR__."/utmdc/sources/medor.php";
	require_once __DIR__."/utmdc/sources/dawn.php";
	require_once __DIR__."/utmdc/sources/destination.php";
	require_once __DIR__."/utmdc/sources/gamefront.php";
	require_once __DIR__."/utmdc/sources/celticwarriors.php";
	require_once __DIR__."/utmdc/sources/other.php";
	require_once __DIR__."/utmdc/sources/unrealtexture.php";
	require_once __DIR__."/utmdc/sources/filesize.php";

	// redirect servers
	if(!isset($isFrontend)){
		require_once __DIR__."/utmdc/sources/redirHaleys.php";
		require_once __DIR__."/utmdc/sources/redirUM.php";
		require_once __DIR__."/utmdc/sources/redirFuzzeh.php";
		require_once __DIR__."/utmdc/sources/redirSnipersParadise.php";
		require_once __DIR__."/utmdc/sources/redirFTP.php";
	}



	function findMapUrl($mapname){
		if(($ma=UTMDC\Dawn\findMapUrl($mapname))!==false) return $ma;
		if(($ma=UTMDC\Destination\findMapUrl($mapname))!==false) return $ma;
		if(($ma=UTMDC\CelticWarriors\findMapUrl($mapname))!==false) return $ma;
		if(($ma=UTMDC\UTFilesCom\findMapUrl($mapname))!==false) return $ma;
		if(($ma=UTMDC\Medor\findMapUrl($mapname))!==false) return $ma;
		if(($ma=UTMDC\Other\findMapUrl($mapname))!==false) return $ma;
		if(($ma=UTMDC\UnrealTexture\findMapUrl($mapname))!==false) return $ma;
	}

	function findPackageByName($pak){
		if(($url=UTMDC\RedirectFuzzeh\findPackageUrl($pak))!==false) return $url;
		if(($url=UTMDC\RedirectSnipersParadise\findPackageUrl($pak))!==false) return $url;
		if(($url=UTMDC\RedirectHaleysHotHouse\findPackageUrl($pak))!==false) return $url;
		if(($url=UTMDC\RedirectFTP\findPackageUrl($pak))!==false) return $url;
		return "";
	}
}else{
	function findMapUrl($mapname){
		return false;
	}
	function findPackageByName($pak){
		return "";
	}
}

?>