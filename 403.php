<?php
	header("HTTP/1.1 403 Forbidden",true,403);
	require_once "config.php";
	require_once "common.php";
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"
"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<title>Zły adres głąbie - wypizdowo</title>
<link rel="icon" type="image/png" href="<?=$assetsPath?>/favicon.ico"/>
<meta http-equiv="Content-Type" content="text/html;charset=UTF-8"/>
<link rel="stylesheet" type="text/css" href="<?=$assetsPath?>/css/crap.css"/>
</head>
<body class='dark'>
<div id='body_cont'>
<h1>WYPIZDOWO.TK</h1>
<?php

echo "<div id='dsc_index'>\n<h2>403 dostęp zabronony</h2>
<h3>Gratulacje, właśnie znalazłeś najlepszy filmik świata!! Niestety, dostępny jest tylko dla wybrańców.</h3>
<p id='dir_description'>
<img src='".getFURL("static/flejmnayt.jpg")."' alt=\"403 Error: poop\"/><br/>
A teraz na serio: Zbłądziłeś, kolego. Próbujesz otworzyć plik, który nie powininen być dostępny z internetu. Możesz wrócić na główną stronę. Na 100% nie znajdziesz tu kwiatków wielkanocnych.<br/>
<a href='$site_url'>[wróć]</a>
</p>
</div>\n";

?>

<br/><small><?=$appCredits?></small>
<?=file_get_contents("tracking.html") ?>
</div>
</body>
</html>