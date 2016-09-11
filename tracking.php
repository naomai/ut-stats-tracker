<?php 
if($user_dnt==1){
	echo '<!--Tracking code removed due to DNT header -->';
}else{
	echo '<!-- Start of StatCounter Code for Default Guide -->
<script type="text/javascript">
var sc_project=9919866; 
var sc_invisible=0; 
var sc_security="db9f78b5"; 
var scJsHost = (("https:" == document.location.protocol) ?
"https://secure." : "http://www.");
document.write("<sc"+"ript type=\'text/javascript\' src=\'" +
scJsHost+
"statcounter.com/counter/counter.js\'></"+"script>");
</script>
<noscript><div class="statcounter"><a title="hit counter"
href="http://statcounter.com/free-hit-counter/"
target="_blank"><img class="statcounter"
src="http://c.statcounter.com/9919866/0/db9f78b5/0/"
alt="hit counter"></a></div></noscript>
<!-- End of StatCounter Code for Default Guide -->
';
	// :P
	echo '<p id="appapa"></p>
<script>
if(typeof adDetectaz0rd!=="undefined" && (typeof adBurockTrappuHai==="undefined" || adBurockTrappuHai!="mojebule")){
	document.addEventListener("DOMContentLoaded",function(e){
		document.getElementById("appapa").innerHTML="AD BLOCK DETECTED!! That\'s a shame, because... there are no ads on this website! <img src=\"'.maklinkHtml(LSTATICFILE,"smiles/594.gif","").'\" alt=\";)\" />";
	});
}
</script>
';

}

