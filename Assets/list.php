<?php
	require_once("appConfig.php");
	require_once("cdnProxyCommon.php");

	if(strcmp($RQU,"/list.php")===0) {
		header("Location: $appUrl");
		exit;
	}
	header("HTTP/1.1 300 Multiple Choices");
	$RQ2=reset(explode("?",$RQU));
	//$root=$basedir.$RQ2;
	$root=$_SERVER['DOCUMENT_ROOT'].$RQ2;
	//echo "$root";
	if(!isset($_GET['dir']) || trim($_GET['dir'])=="" || preg_match("/^[a-zA-Z0-9_\-\s\/ąćęłńóśżźĄĆĘŁŃÓŚŻŹ\(\)]*$/",$_GET['dir'])!=1 || !file_exists("$root/".$_GET['dir']) || !is_dir("$root/".$_GET['dir'])){
		$dirId="$root";
		$dirIdRel="";
	}else{
		$dirId="$root/".$_GET['dir'];
		$dirIdRel=$_GET['dir']."/";
	}
	$dirnamo=end(explode("/",$dirId));
	if(!$dirnamo) $dirnamo="/";
?>
<!DOCTYPE HTML>
<html>
<head>
	<title><?=$RQ2?> - Dir index</title>
	<link rel="icon" type="image/png" href="<?=$assetsPath?>/favicon2.ico"/>
	<meta charset="UTF-8"/>
	<link rel="stylesheet" type="text/css" href="<?=$assetsPath?>/css/crap.css"/>

</head>
<body>
<div id='body_cont'>
<h1><?=$appName ?></h1>
<?php



if(isset($_GET['s']) ){
	$s = $_GET['s'];
	if(!($s=='name' || $s=='size' || $s=='time' || $s=='named' || $s=='sized' || $s=='timed')){
		$s='name';
	}
}else{
	$s='name';
}

$nd = $s=='name'?'d':'';
$sd = $s=='size'?'d':'';
$td = $s=='time'?'d':'';


//print_r($lst);
echo "<div id='dsc_index'>\n<h2>Index of: {$RQ2}</h2>
<p id='dir_description'></p>
<p><small id='dir_lastupdate'></small></p>
<div id='options' class='table'>
	<a href='$appUrl' class='go_home'><img src='$assetsPath/icons/home.gif' alt=''/> Home directory</a> 
	<a href='#' class='go_'></a>
</div>";
echo "<table id='index' class='table'>
	<thead><tr>
		<th class='dicon'></th>
		<th class='dname'><a href='//$appHost"."$RQ2?s=name$nd'>Name</a></th>
		<th class='dsize'><a href='//$appHost"."$RQ2?s=size$sd'>Size</a></th>
		<th class='ddate'><a href='//$appHost"."$RQ2?s=time$td'>Last modified</a></th>
	</tr></thead>
	<tbody>\n";
	
$total=0;

$drz=array();
$fls=array();
if ($dh = opendir( "$dirId")) {
	while (($file = readdir($dh)) !== false) {
		if ($file=="." || $file=="..") continue;
		if(filetype($dirId . "/".$file)=="dir") {
			$drz[]=array("name"=>$file,"time"=>filemtime("$dirId/$file"),"size"=>0);
		}
		else {
			$fls[]=array("name"=>$file,"time"=>filemtime("$dirId/$file"),"size"=>filesize("$dirId/$file"));
		}
	}
	closedir($dh);
}

sort($drz);

if(file_exists($dirId . "/alias.txt")){
	$aliases = parseAliases($dirId . "/alias.txt");
	foreach($aliases as $orig=>$dest){
		$destPath = $dirId . "/" . $dest;
		if(!file_exists($destPath)){
			continue;
		}
		
		if(filetype($destPath)=="dir"){
			$drs[] = array("name"=>$orig,"time"=>filemtime($destPath),"size"=>0,'isLink'=>true);
		}else{
			$fls[]=array("name"=>$orig,"time"=>filemtime($destPath),"size"=>filesize($destPath),'isLink'=>true);
		}
		
	}
}


foreach($drz as $d){
	$tagz = "";
	if(isset($d['isLink'])){
		$tagz .= "[L] ";
	}
	echo "		<tr>
			<td class='dicon'><img src=\"$assetsPath/icons/dir.gif\" alt='DIR'/></td>
			<td class='dname'>
				<a href=\"//$appHost"."$RQ2"."{$d['name']}/\" class='dir link index_link'>".strip_tags($d['name'])."</a>
				<span class='hoverhide'>$tagz</span>
			</td>
			<td class='dsize'>-</td>
			<td class='ddate'>".dateFmt($d['time'])."</td>
		</tr>\n";
	$total+=$d['size'];
}

usort($fls,"usortby$s");

foreach($fls as $d){
	//$res=end(explode(".",$d['name']));
	$fileName = $d['name'];
	if(isset($d['isLink'])){
		$fileFullPath = $dirId . "/" . $aliases[$fileName];
	}else{
		$fileFullPath = $dirId . "/" . $fileName;
	}
	$res=pathinfo($fileName,PATHINFO_EXTENSION);
	$baseName=pathinfo($fileName,PATHINFO_FILENAME);
	
	$icon = $res;
	
	if($res=='avi' || $res=='mp4' || $res=='wmv') $ftype='video';
	else if($res=='mp3' || $res=='wav' || $res=='flac' || $res=='ogg') $ftype='music';
	else if($res=='txt') $ftype='text';
	else if($res=='php' && strtolower($baseName) == "appconfig") $ftype="n14cfg";
	else if($res=='php' && strtolower($baseName) == "n14app") $ftype="n14app";
	else if($res=='php') $ftype="php";
	else $ftype='file';
	
	$addi = "";
	
	if($ftype=='file') { 
		$icon='file';
	}else if($ftype=='n14app') {
		$icon='n14';
	}else if($ftype=='n14cfg') {
		$icon='n14cfg';
		$appInfo = n14AppGetInfo($fileFullPath);
		$addi = $appInfo['name'];
	}else if($ftype=='php'){
		$modInfo = n14ModuleGetInfo($fileFullPath);
		if($modInfo['valid']){
			$addi = "Module: " . ($modInfo['name']!=""?$modInfo['name']:$modInfo['class']);
			$icon = 'n14m';
		}
	}
	
	$tagz = "";
	if(isset($d['isLink'])){
		$tagz .= "[L] ";
	}

	echo "		<tr>
			<td class='dicon'><img src=\"$assetsPath/icons/$icon.gif\" alt='".strtoupper($icon)."'/></td>
			<td class='dname'>
				<a href=\"//$appHost"."$RQ2"."{$d['name']}\" class='file link direct_link'>$fileName</a> $addi\n";
	if($ftype=='music'||$ftype=='video'||$tagz!="")
		echo "\t\t\t\t<span class='hoverhide'>$tagz</span>\n";

	echo "\t\t\t</td>
			<td class='dsize'>".formatFileSize($d['size'])."</td>
			<td class='ddate'>".dateFmt($d['time'])."</td>
		</tr>\n";
	$total+=$d['size'];
}
echo "\t</tbody>\n\t<tfoot><tr><td class='dicon'></td><td class='dname'>Directories: ".count($drz).", files: ".count($fls)."</td><td class='dsize'>".formatFileSize($total)."</td><td class='ddate'></td></tr></tfoot>";
echo "	
</table>
</div>\n";


function usortbytime($a,$b){
	if($a['time']>$b['time']) return -1;
	else if($a['time']<$b['time']) return 1;
	return 0;
}
function usortbytimed($a,$b){
	return -usortbytime($a,$b);
}
function usortbyname($a,$b){
	$d=strcasecmp($a['name'],$b['name']);
	return $d; //$d<0?-1:($d>0?1:0);
}
function usortbynamed($a,$b){
	return -usortbyname($a,$b);
}

function usortbysize($a,$b){
	return ($a['size']>$b['size'])?-1:($a['size']<$b['size']?1:0);
}
function usortbysized($a,$b){
	return -usortbysize($a,$b);
}

function formatFileSize($d){
	if($d>0x100000*1000) return round($d/0x40000000,2)." GB";
	else if($d>0x400*1000) return round($d/0x100000,2)." MB";
	else if($d>1000) return round($d/0x400,2)." KB";
	else return $d." B";
}

function dateFmt($d){
	if ($d<631152000) return "-";
	return date("Y-m-d",$d);
}

function n14AppGetInfo($fileName){
	$appDir = dirname($fileName);
	$configFile = $appDir."/appConfig.php";
	if(!file_exists($configFile)) $configFile = $appDir."/config.php";
	if(!file_exists($configFile)) $configFile = $appDir."/n14App.php";
		
	$configContent = file_get_contents($configFile);
	$appInfo = array();
	
	$hasAppName = preg_match("#\\\$appName\s*=\s*['\"](.*)['\"]\s*;#",$configContent,$match);
	
	if($hasAppName){
		$appInfo['name'] = $match[1];
	}
	return $appInfo;
}

function n14ModuleGetInfo($file){

	$moduleContent = file_get_contents($file);
	if(strpos($moduleContent,"\$moduleClassName")===false) return array('valid'=>false);

	$className = getVariableValueFromPHPCode('moduleClassName',$moduleContent);
	if($className===false) return array('valid'=>false);
	$moduleName=getVariableValueFromPHPCode('moduleName',$moduleContent);

	return array('valid'=>true,'class'=>$className,'name'=>$moduleName);
	
}

function getVariableValueFromPHPCode($varName, $code){
	if(!preg_match('#\$'.$varName.'\s*=\s*("|\')([^"\']*)("|\')\s*;#s',$code,$mat) || $mat[1]!=$mat[3]){
		return false;
	}
	return $mat[2];
}

?>

<br><small><?=$appCredits ?></small>
</div>
</body>
</html>