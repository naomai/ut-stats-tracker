<?php

require_once __DIR__."/less.php/Less.php";
$lp=new Less_Parser();

$fx = isset($_GET['file']) ? $_GET['file'] : $argv[1];

$fn=basename($fx);
$fn=substr($fn,0,strrpos($fn,"."));
try{
//$lp->parseFile("$fn.less","http://www.mm.pl/~namonaki/n14assets/mtatrk/css/");
$lp->parseFile("$fn.less","");
}catch(Less_Exception_Parser $e){
	echo "Exception: {$e->getMessage()}\n";
}
file_put_contents("$fn.css",$lp->getCss());
unset($lp);

?>