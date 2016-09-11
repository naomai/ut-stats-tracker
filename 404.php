<?php
	header("HTTP/1.1 404 Not found",true,404);

	require_once __DIR__."/config.php";
	require_once __DIR__."/common.php";

?>
<!DOCTYPE HTML>
<html>
<head>
	<title>OMG, THERE'S NO FOOD! - Unreal Tournament Tracker</title>
	<link rel="icon" type="image/png" href="<?=maklink(LSTATICFILE,"favicon2.ico","")?>"/>
	<meta charset="UTF-8"/>
	<link rel="stylesheet" type="text/css" href="<?=maklink(LSTATICFILE,"css/crap.css","")?>"/>
</head>
<body class='dark rage'>
<div id='body_cont'>
<h1>Unreal Tournament Tracker</h1>
<?php

echo "<div id='dsc_index'>\n<h2>".__("Error 404: File not found").": $requestPage</h2>
<p id='dir_description'>
".__("The page you're looking or desn't exist.")."<br>
<img src='".maklink(LSTATICFILE,"404bt.jpg","")."' alt=\"".__("404 Error: Milk not found")."\"/><br>
<a href='".maklink(LFILE,"","")."'>[".__("!nocp")."]</a>
</p>
$footer
</div>\n";

?>

<br><small><?=$appCredits?></small>
<?=file_get_contents("tracking.html") ?>
</div>
</body>
</html>