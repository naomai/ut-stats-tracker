<?php
// THIS ONE'S CALLED "PERFORMANCE OVER READABILITY"
// regex + skip strict checks + more little optimizations
// now it takes only 13 seconds to parse 28MB file 
// tested on ctf-bt-26.t3d, php 5.5.13, pentium 4 @ 3mhz

// THIS PARSER MIGHT HAVE SOME PROBLEMS WITH MALFORMED T3D FILES
// USE ONLY WITH FILES MADE BY UCC OR UED

/* 
todo benchmarks

*/



function d_parseT3D($file){
	
	$fxRaw=file_get_contents($file);
	$fx = t3dconvertCharset($fxRaw);
	unset($fxRaw);
	
	$tree=array('Map'=>array(0=>array('Actor'=>array()))); // pretty messed up, isn't it?
	
	
	$actorsList=&$tree['Map'][0]['Actor'];
	$modelsList=&$tree['Map'][0]['__models'];
	$currentActor=null;
	
	preg_match_all("/Begin Actor Class=(.+) Name=(.+)\r?\n(.*)End Actor/sU",$fx,$mat);

	unset($mat[0],$fx); // we only need subpattern matches
	
	$actorsText=$mat[3];
	
	$tree['Map'][0]['Actor']=array(); //new SplFixedArray(count($actorsText));
	
	for($actorId=0; $actorId<count($actorsText); $actorId++){
		$actorText=$actorsText[$actorId];
		
		$currentActor=array();
		
		$currentActor['properties']['Class']=$class=$mat[1][$actorId];
		$currentActor['properties']['Name']=$name=$mat[2][$actorId];
		
		
		//parse properties
		
		preg_match_all("/([a-zA-Z0-9\(\)]+)=(.*)/",$actorText,$props,PREG_SET_ORDER);
		
		for($propId=0; $propId<count($props); $propId++){
			$itemname=$props[$propId][1];
			
			$value=trim($props[$propId][2]);
			
			if($value!="" && $value[0]=="("){ //array
				$currentActor['properties'][$itemname]=unserializeArray($value);
			}else if($value!="" && $value[0]=="\""){//string
				$currentActor['properties'][$itemname]=unserializeString($value);
			}else{
				$currentActor['properties'][$itemname]=$value;
			}
			
			
		}
		//extract brushes
		if($class=="Brush" || $class=="Mover"){
			preg_match("/Begin Brush Name=(.+)\r?\n/",$actorText,$brushesText); // here's our 'very' dirty trick (wink wink)
			if(!isset($brushesText[1])) { // brush actor has no model definition (little thing done by some mappers to save few bytes in their map files)
				$currentModel=null;
			
			}else{ // brush model definition
				$modelName=trim($brushesText[1]);
				$currentModel=&$modelsList[$modelName];
				$currentModel=t3dparsePolys($actorText);
				
				$modelsList[$modelName]=&$currentModel;
			}
			if($currentModel===null || !isset($currentActor['properties']['Brush'])){
				$currentActor['Brush'][0]=&$currentModel;
			}else{
				preg_match("/([^']+)'([^\.]+)\.([^']+)'/",$currentActor['properties']['Brush'],$modelRef);
				$currentActor['Brush'][0]=&$modelsList[$modelRef[3]];
			}

		}
		$actorsList[$actorId]=$currentActor;
	}
	
	return $tree;
}

function d_parsePolyLists($file){ // what's the point of this one? can't remember...
	$tree=array();
	
	$fxRaw=file_get_contents($file);
	
	$fx = t3dconvertCharset($fxRaw);
	
	unset($fxRaw);
	
	preg_match("/Begin PolyList\r?\n(.*)End PolyList/sU",$fx,$meshesText);
	
	for($meshId=0; $meshId<count($meshesText[1]); $meshId++){
		$tree[]=t3dparsePolys($meshesText[1][$i]);
	}
	return $tree;
}

function t3dparsePolys($actorText){
	$currentModel=array();
	preg_match_all("/Begin Polygon(.*)\r?\n(.*)End Polygon/sU",$actorText,$polysTextArr);
	unset($polysTextArr[0]);
	
	$polysText=$polysTextArr[2];
	
	$currentModel['PolyList'][0]['Polygon']=array(); //new SplFixedArray(count($polysText));
	
	for($polyId=0; $polyId<count($polysText); $polyId++){ // this loop might take lots of ms for complex brushes
		
		$currentPoly=array();
		$polyPropsText=$polysTextArr[1][$polyId];
		preg_match_all("/([^\s]+)=(.*) /U",$polyPropsText,$polyProps,PREG_SET_ORDER);
		
		for($propId=0; $propId<count($polyProps); $propId++){
			$currentPoly['properties'][$polyProps[$propId][1]]=$polyProps[$propId][2];
		}

		$polyText=$polysText[$polyId];
		preg_match_all("/([^\s]+)\s+(.*)\r?\n/U",$polyText,$polyEntries,PREG_SET_ORDER);
		
		for($entryId=0; $entryId<count($polyEntries); $entryId++){
			$type=$polyEntries[$entryId][1];
			$value=$polyEntries[$entryId][2];
			
			if(strpos($value,"U=")!==false) // we don't need to do strict checking
				$valueX=unserializePan($value);
			else 
				$valueX=unserializeCoord($value);
			if($valueX!==false) $value=$valueX;
			
			$currentPoly[$type][]=$value;
		}
		
		$currentModel['PolyList'][0]['Polygon'][$polyId]=$currentPoly;
		
	}
	return $currentModel;
}


function unserializeCoord($data){
	preg_match("/([\+\-][0-9]{5,7}\.[0-9]{6}),([\+\-][0-9]{5,7}\.[0-9]{6}),([\+\-][0-9]{5,7}\.[0-9]{6})/",$data,$match);
	if(!count($match)) return false;
	return array("X"=>(float)$match[1],"Y"=>(float)$match[2],"Z"=>(float)$match[3]);
}
function unserializePan($data){
	preg_match("/U=(-?[0-9]+) V=(-?[0-9])+/",$data,$match);
	if(!count($match)) return false;
	return array("U"=>(int)$match[1],"V"=>(int)$match[2]);
}

function unserializeArray(&$data){
	$dt=substr($data,1);
	
	$result=array();
	while(($idx=strtokX($dt,"="))!=""){
		if($dt[0]=="("){
			
			$val=unserializeArray($dt);
		}else if($dt[0]=='"'){
			$val=unserializeString($dt);

		}else{
			$val=strtokX($dt,",");
			
			if(strpos($val,"'")!==false){ // this might not be working fine for arrays that have object reference as last element: ABC=(DEF=class'package.export')
					
			}else if(strpos($val,")")!==false){
				$data=$dt;
				
				$result[$idx]=substr($val,0,-1);
				break;
			}
		}
		
		$result[$idx]=$val;
	}
	return $result;
}
function unserializeString(&$data){
	$str=substr($data,1);
	$str=substr($str,0,strpos($str,"\""));
	$data=substr($data,strpos($data,"\"",1)+2);
	return $str;
}

function unserializeReference($str){
	preg_match("/([^']*)'([^\.]*).([^']*)'/",$str,$mat);
	return array('importType'=>$mat[1],'package'=>$mat[2],'export'=>$mat[3]);
	
}

function strtokX(&$in,$tok){
	$p=strpos($in,$tok);
	if($p===false){
		$res=substr($in,0);
		$in="";
	}else{
		$res=substr($in,0,$p);
		$in=substr($in,$p+strlen($tok));
	}
	return $res;	
}

function t3dconvertCharset($raw){
	//detect encoding
	if($raw[0]==chr(0xFE)) { // ucs2 big endian
		return mb_convert_encoding ($raw,"utf-8","ucs-2be");
	}else if($raw[1]==chr(0xFE)) { // ucs2 little endian
		return mb_convert_encoding ($raw,"utf-8","ucs-2le");
	}else{	// good old cp1252
		return mb_convert_encoding ($raw,"utf-8","windows-1252");
	}
}
?>