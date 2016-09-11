<?php
	header("HTTP/1.1 404 Building not found",true,404);
	require_once "appConfig.php";
?>
<!DOCTYPE HTML>
<html>
<head>
	<title>Wrong URL - <?=$appName?></title>
	<link rel="icon" type="image/png" href="<?=$assetsPath?>/favicon.ico"/>
	<meta charset="UTF-8"/>
	<link rel="stylesheet" type="text/css" href="<?=$assetsPath?>/css/crap.css"/>
</head>
<body class='dark'>
<div id='body_cont'>
<h1><?=$appName?></h1>
<?php

echo "<div id='dsc_index'>\n<h2>File not found</h2>
<p id='dir_description'>
<img src='$assetsPath/img/404.jpg' alt=\"404 Error: Tape not found\"/><br/>
Apparently, the file you're looking for is not here.<br/>
<a href='$appUrl'>[Come back to village]</a>
</p>
</div>\n";

?>

<br/><small><?=$appCredits?></small>
</div>
</body>
</html>