<?php
/* NemoPagination
 * Originally written for DSC CMS
 * 2009 namo
 * "drogi JA z 2013r, przepraszam za to co tu pope³ni³em" ~namo
 * (dear ME from 2013, i'm sorry for everything i've done in this file)
 * */
function create_pagination($size, $current, $format, $class="",$id="") {
	//'14-07-20 changed JQ metadata() params with HTML data-* arguments (We don't need no JQuerycation!)
	$res="";
	if($size>1){
		//$res.="<div class=\"pagination {max:$size,cur:$current,format:'$format'} $class\">\n";
		//$res.="<span class=\"pagination_data\" data=\"{viewstart:".($current<3?3:$current)."}\"></span>\n";
		$idstr=($id?" id=\"$id\"":"");
		$res.="<div class=\"pagination $class\" data-maxp=\"$size\" data-cur=\"$current\" data-format=\"$format\"$idstr>\n";
		
		$viewstart=$current-3;
		if($viewstart > $size-8) $viewstart=$size-8;
		if($viewstart < 1) $viewstart=1;
		
		$res.="<span class=\"pagination_data\" data-viewstart=\"".($viewstart)."\"></span>\n";
	
		if($viewstart > 1){
			$flnk=htmlspecialchars(sprintf($format,1));
			$res.="<a href='$flnk' class='pagnavi' data-scrolldelta=\"-1\">&#x00AB;</a>\n";

		}else {
			$res.="<strong class='pagnavi'>&#x00AB;</strong>\n";
		}
		
		if($current==1){
			$res.="<strong class='pagnavi'>&#x2039; Prev.</strong>\n";
		}else{
			$flnk=htmlspecialchars(sprintf($format,$current-1));
			$res.="<a href='$flnk' class='pagnavi'>&#x2039; Prev.</a>\n";
		}
		
		/*if($viewstart >= 5)
		{
			//if($current==0) $res.="<strong>1</strong> \n";
			//else $res.=sprintf("<a href='$format'>1</a> \n",1);
			//$res.="<strong>...</strong>\n";
		}*/
		for($ii=$viewstart; $ii < $viewstart+8; $ii++)
		{
			if($ii >= 1 && $ii <= $size) 
			{
				if($ii==$current){
					$res.="<strong class='pagenum'>" . ($current) . "</strong>\n";
				}else{
					$flnk=htmlspecialchars(sprintf($format,$ii));
					$res.="<a href='$flnk' class='pagenum'>". ($ii) ."</a>\n";
				}
			}
		}
		if($current < $size-1){
			$flnk=htmlspecialchars(sprintf($format,$current+1));
			$res.="<a href='$flnk' class='pagnavi'>Next &#x203A;</a>\n";
		}else{
			$res.="<strong class='pagnavi'>Next &#x203A;</strong>\n";
		}
		
		if($viewstart < $size-8){
			$flnk=htmlspecialchars(sprintf($format,$size));
			$res.="<a href='$flnk' class='pagnavi' data-scrolldelta=\"1\">&#x00BB;</a>\n";
		}else{
			$res.="<strong class='pagnavi'>&#x00BB;</strong>\n";
		}
		
		
		$res.="</div><!--/PAGINACJA-->";
	}
	return $res;
}


?>