<?php

	
	$noUTTCSS=true;
	require_once "config.php";
	require_once "common.php";
		
	
	printf($headerf,"LocaleThing","calm","");
		
	
	
	
?>
<h2>N14Localize * LocaleViewer</h2>
<p>todo : editor</p>
<?php
	$stats = N14\GetText\stats();
	
	echo "<h3>Translation: {$stats['localeName']}</h3>\r\n";
	echo "<p>path: ".N14\GetText\get_locale_file()."</p>";
	if($stats['locale']==$n14gt_default_locale){
		echo "Default locale detected";
	}
	echo "<table class='huge'><thead><tr><th>Original</th><th>Translated</th><th>CodeRef</th></tr></thead>\r\n";
	echo "<tbody>\r\n";
	foreach($n14gt_strings as $orig=>$trans){
		$attributes=$n14gt_strings_attributes[$orig];
		
		if(!$attributes['markedAsTranslated'] && $orig==$trans && $n14gt_locale !== $n14gt_default_locale ){
			$trAttrib=" style=\"background: #243\"";
		}else{
			$trAttrib="";
		}
		echo "<tr$trAttrib><td>".htmlspecialchars($orig)."</td><td>".htmlspecialchars($trans)."</td>";
		echo "<td>";
		if(isset($n14gt_refs[md5($orig)])){
			$ref = $n14gt_refs[md5($orig)];
			$exampleUrl = $ref['exampleUrl'];
			$noQueryArgs = strtok($exampleUrl,"?");
			$queryArgs = parse_url($exampleUrl, PHP_URL_QUERY);
			$argsArray = null;
			parse_str($queryArgs,$argsArray);
			$argsArray['n14gtHighlight']=md5($orig);
			$queryArgs = http_build_query($argsArray);
			$exampleUrl = "$noQueryArgs?$queryArgs";
			
			echo "<a href=\"".htmlspecialchars($exampleUrl)."\">".htmlspecialchars($ref['file']) . " : " . (int)$ref['line'] . "</a><br>\r\n";
			echo "<code>".htmlspecialchars($ref['code'])."</code>";
		}
		echo "</td></tr>\r\n";
	}
	echo "</tbody></table>";

?>

<hr>
<small>'11 NaMONaKi14</small>
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
</div>
</body>
</html>
<?php

	
?>