<?php 
require_once "config.php";
require_once "common.php";
header("HTTP/1.1 503 Service Unavailable");
?>
<!DOCTYPE HTML>
<html lang='en'>
<head>
<meta charset='utf-8'/>
<link rel="shortcut icon" type='image/png' href='<?= maklink(LSTATICFILE,"uttfav.png","") ?>' />
<link rel='stylesheet' href='<?= maklink(LSTATICFILE,"css/crap.css","") ?>'/>
<title>Temporary Offline - UT99 Tracker</title>
</head>
<body class='dark rage LOLHIDEACTIVE' id='uttrk'>	
<div id='logo_cont'>
	<h1 class='adlogo'><a href='.'>Unreal Tournament Tracker V2</a></h1>
</div>
<div id='body_cont'>
<header class='notsofreakingbig'>
<h1>Offline!!</h1>
<big>16-03-26: Fixing bugs caused by migration to PHP7. </big><s>This language is retarded</s>
<!-- <?=$_SERVER['HTTP_USER_AGENT']?>-->
</header>
<hr/>
<br><br>
<small>'13 '14 namonaki14, WaldoMG. This site is NOT HTML 2.0 compatible.</small>
<!-- Start of StatCounter Code for Default Guide -->
<script type="text/javascript">
//<![CDATA[
var sc_project=9919866; 
var sc_invisible=0; 
var sc_security="db9f78b5"; 
var scJsHost = (("https:" == document.location.protocol) ?
"https://secure." : "http://www.");
document.write("<sc"+"ript type='text/javascript' src='" +
scJsHost+
"statcounter.com/counter/counter_xhtml.js'></"+"script>");
//]]>
</script>
<noscript><div class="statcounter"><a title="site stats"
href="http://statcounter.com/" class="statcounter"><img
class="statcounter"
src="http://c.statcounter.com/9919866/0/db9f78b5/1/"
alt="site stats" /></a></div></noscript>
<!-- End of StatCounter Code for Default Guide -->
</body>
</html>
<?php exit(); ?>