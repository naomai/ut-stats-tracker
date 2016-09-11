<?php
/*
 * N14TableThing
 * Copysphright (:P) 2014 namonaki14
 * 
 * This program is free software - whatever that means.
 * You may redistribute it, modify, steal it under the conditions
 * of Wolver's General License CR@P, version 666,
 * issued by Greenpiss Foundation, you may as well put it 
 * up in your <censored>, if you like it.
 * 
 * This program is distributed in a hope that some idiot will download it.
 * The author does not take any responsibility for what you're going to do
 * with this software. In conclusion - if you do not know how to use
 * this program, delete it immediately. 
 * 
 * You should have received a paper copy of this license in the amount of
 * half a million pieces. If the license has not been delivered or 
 * you don't like it, visit the following web site:
 * http://www.google.com/search?q=software+license
 * and find yourself another one that suits you.
 * 
 * The full text of this license is available at:
 * http://mm.pl/~namonaki/wpl/sraq23_lic_en.txt
 * 
 * 
**/

	namespace N14{
		
		use \Iterator,\ArrayAccess;

		
		class TableThing{
			private $data;
			private $isGenerator = false;
			private $columns;
			private $uniqId;
			private $isCached=false;
			private $tempArray=array();
			private $sortColumn=null;
			private $sortOrder=SORT_ASC;
			private $sortOrderSet = false;
			private $skipCurrentRow=false;
			private $_rowCallback=null;
			private $_rowFormatterCallback=null;
			private $_rowsProcessed=null;
			
			public $dataLastUpdated=0;
			public $htmlClass="";
			public $htmlId=null;
			public $allowSorting=true;
			public $isScrollable=true;
			public $isJsonFetcher=false;
			public $usingCached=false;
			public $dontCache=false;
			public $caption=null;
			
			public $htmlIdColumn=null;
			
			public static $uniqVal=0;
			
			public static $dataDir = null;
			
			const SORT_ASC=\SORT_ASC; // legacy code
			const SORT_DESC=\SORT_DESC;
			
			public function __construct($data=null,$uniqId=null){
				self::staticInit();
				if($data!==null){
					$this->fillData($data);
				}
				if($uniqId!==null){
					$this->setUniqId($uniqId);
				}else{
					$this->uniqId = TableThing::genUniqId();
				}
				$this->columns = array();
				
			}
			
			public function fillData($data){
				if($data instanceof \Generator){
					$this->data=$data;
					$this->isGenerator = true;
				}if(is_array($data)){
					$this->data=array_values($data);
				}else{
					throw new TableThingException("Supplied data is not an array.",debug_backtrace());
				}
			}
			
			public function loadDataFromCache($id){
				if(!file_exists(self::$dataDir."/table_$id.json")) {
					$id=TableThing::genStaticUniqId($id);
					if(!file_exists(self::$dataDir."/table_$id.json")) 
						throw new TableThingException("Table ID doesn't exist",debug_backtrace());
				}
				if(!preg_match("/[a-zA-Z0-9]*/",$id)) throw new TableThingException("Invalid table ID",debug_backtrace());
				
				//echo strlen(file_get_contents(self::$dataDir."/table_$id.json"));
				$fx=json_decode(file_get_contents(self::$dataDir."/table_$id.json"),true);
				if($jserr=json_last_error()){
					throw new TableThingException("Cache error: ".json_last_error_msg()."",debug_backtrace());
				}
				
				//echo "JSONERR:".json_last_error ();
				$this->tempArray=$fx['data'];
				//echo "LOADED:".count($this->tempArray);
				
				foreach($fx['columninfo'] as $c){
					$cx=$this->addColumn($c['displayKey'],$c['displayName']);
					$cx->contentType=$c['contentType'];
					$cx->sortOrder=$c['sortOrder'];
					$cx->hidden=(bool)$c['hidden'];
					$cx->htmlClass=$c['htmlClass'];
				}
			}
			public function getCacheAge(){
				$id=$this->uniqId;
				if(!file_exists(self::$dataDir."/table_$id.json")) return 0;
				return filemtime(self::$dataDir."/table_$id.json");
			}
			
			public function saveDataToCache(){
				if(!$this->dontCache){
					$json=$this->genJSON();
					TableThing::flushOldCache();
					file_put_contents(self::$dataDir."/table_{$this->uniqId}.json",$json);
				}
			}
			
			/*public function fillRow($row){
				$this->data[]=$row;
			}*/
			
			
			public function sort($columnName,$order=SORT_ASC){
				$this->sortColumn=$columnName;
				$this->sortOrder=$order;
				
			}
			public function addColumn($columnRealName,$columnDisplayName=null){
				if($columnDisplayName===null) $columnDisplayName = $columnRealName;
				$col=new TableThingColumnInfo($columnRealName,$columnDisplayName);
				return $this->columns[$col->displayKey]=$col;
			}
			
			public function addSortableColumn($columnRealName,$columnSortKey,$columnDisplayName){
				$c=new TableThingColumnInfo($columnRealName,$columnDisplayName);
				$c->sortKey=$columnSortKey;
				//return $c;
				return $this->columns[$c->displayKey]=$c;
			}
			
			public function setRowPreprocessorCallback($callback){
				if(is_callable($callback)) $this->_rowCallback = $callback;
			}
			
			public function setRowFormatterCallback($callback){
				if(is_callable($callback)) $this->_rowFormatterCallback = $callback;
			}
			
			public function isValidColumn($column){
				foreach($this->columns as $c){
					if($c->displayKey==$column) return true;
				}
				return false;
			}
			
			public function skipRow(){
				$this->skipCurrentRow=true;
			}
			
			public function genTempArray($offset,$limit){
				$this->tempArray=array();
				$i=$offset;
				if($this->isGenerator){
					$gen = $this->data($offset,$limit);
					$inData = array();
					foreach($gen as $i=>$row){
						$inData[$i]=$row;
					}
				}else{
					$inData = &$this->data;
				}
				
				do{
				
					$this->skipCurrentRow=false;
					$rd=$inData[$i];
					$ri=$rd;
					
					if($this->_rowCallback!=null) {
						$ri = call_user_func($this->_rowCallback,$rd);
					}
					
					foreach($this->columns as $cd){
						//$ri[$cd->displayKey]=$cd->getCellValueByRow($rd);
						
						$ri[$cd->displayKey]=$cd->getCellValueByRow($ri);
						if(!isset($ri['sortable_'.$cd->displayKey])){
							$ri['sortable_'.$cd->displayKey]=$cd->getCellSortableValueByRow($rd);
						}
						if($this->skipCurrentRow){
							unset($ri[$cd->displayKey],$ri['sortable_'.$cd->displayKey]);
							break;
						}
					}
					
					
					if(!$this->skipCurrentRow){
						$this->tempArray[$i]=$ri;
					}
				
				}while(isset($inData[++$i]) && ($limit==-1 || ($limit>0 && $i < $offset+$limit)));
				
				$this->sortTempArray();
			}
			
			public function sortTempArray(){
				if($this->allowSorting && isset($_GET['sort']) && $this->isValidColumn($_GET['sort'])) {
					$sortColumn=$_GET['sort'];
					$sortord=(isset($_GET['order']) && $_GET['order']=='d'?SORT_DESC:SORT_ASC);
					$this->sortOrderSet = isset($_GET['order']);
					$this->sort($sortColumn,$sortord);
				}
				if($this->sortColumn!==null){
					
					if(!isset($this->columns[$this->sortColumn])){
						foreach($this->columns as $ci=>$cx){
							if(
							(is_string($cx->sortKey) && $cx->sortKey == $this->sortColumn) ||
							(is_string($cx->displayKey) && $cx->displayKey == $this->sortColumn)
							){
								$sco=$cx;
								$sci=$ci;
							}
						}
						if(!isset($sco)) return;
					}else{
						$sco=$this->columns[$this->sortColumn];
						$sci=$this->sortColumn;
					}
					$sc="sortable_".$sci;
					//echo "SC=$sc;";
					
					if($sco->contentType==TableThingColumnInfo::CONTENT_NUM || $sco->contentType==TableThingColumnInfo::CONTENT_NUMFLOAT || $sco->contentType==TableThingColumnInfo::CONTENT_FMTNUM) {
						
						$uf=function($a,$b) use($sc){
							return ($a[$sc]>$b[$sc]?1:($a[$sc]<$b[$sc]?-1:0));
						};
					}else {
						
						$uf=function($a,$b) use($sc){
							//echo "SC";
							return strcasecmp($a[$sc],$b[$sc]);
						};
					}
					
					usort($this->tempArray,$uf);
					//echo "ThisSO=".((int)$this->sortOrder)." xor ColSO=".((int)$sco->sortOrder);
					if($this->getRealColumnSortState($sco)==SORT_DESC) {
						$this->tempArray=array_reverse($this->tempArray,false);
					}
					//$this->tempArray=array_values($this->tempArray);
				}
			}
			
			public function genHTML($offset=0,$limit=-1){
				if(!is_numeric($offset) || !is_numeric($limit)) throw new TableThingException("Invalid range",debug_backtrace());
				
				if($limit != -1 && $this->isScrollable && isset($_GET['p'.$this->uniqId])) $offset=($_GET['p'.$this->uniqId]-1)*$limit;
				if(!$this->isJsonFetcher && !$this->usingCached){
					/*$this->genTempArray($offset,$limit);*/
					$this->genTempArray(0,-1);
				}else{
					$this->sortTempArray();
				}
				if(!$this->isJsonFetcher && !$this->usingCached && ($this->dataLastUpdated > $this->getCacheAge() || $this->dataLastUpdated==0 )){
					$this->saveDataToCache();
				}
				$html="";
				$tid="";
				$this->_rowsProcessed=0;
				if($this->htmlId!==null){
					$tid="id=\"{$this->htmlId}\" ";
				}
				$addiParams="";
				if($this->usingCached){
					$addiParams.=" data-cache-age=\"".(time()-$this->getCacheAge())."\"";
				}
				
				if($this->dontCache){
					$addiParams.=" data-no-xhr=\"1\"";
				}
				
				$html.="<table class=\"".trim("n14table {$this->htmlClass}")."\" $tid"."data-tableid='".$this->uniqId."' data-siteurl=\"".$GLOBALS['sub_site_url']."\" data-view-offset='$offset' data-view-limit='$limit' data-total-items='".count($this->tempArray)."' data-html-id-column='{$this->htmlIdColumn}'$addiParams>\n";
				if($this->caption!==null){
					$html.="\t<caption>".$this->caption."</caption>\n";
				}
				// HEADER
				$html.="\t<thead>\n";
				$html.="\t\t<tr data-rid='-1'>\n";
				if($this->allowSorting || $this->isJsonFetcher ||  $this->usingCached ){
					foreach($this->columns as $cd){
						if($cd->hidden) continue;
						if(function_exists('requestFilterRewriteParams')) 
							$ax=\requestFilterRewriteParams($_GET);
						else 
							$ax=$_GET;
							
						
						$ax['sort']=$cd->displayKey;
						unset($ax['order']);
						$columnSortState = 0; // 0=n/a, 1=asc, 2=desc
						if($this->sortColumn==$ax['sort']){
							//$columnSortState = (!isset($_GET['order']) || $_GET['order']!='d') ? 1 : 2; 
							$columnSortState = (($this->getRealColumnSortState($cd)==SORT_ASC)) ? 1 : 2;
							
						}
						
						if($columnSortState == 1){
							$ax['order']='d';
						}else if($columnSortState == 2){
							$ax['order']='a';
						}
						if($cd->htmlClass!="") 
							$cl=" class=\"{$cd->htmlClass}\"";
						else
							$cl="";
						$html.="\t\t\t<th data-cid=\"".$cd->displayKey."\"$cl>";
						$html.="<a href='?".htmlspecialchars(http_build_query($ax))."'>".$cd->displayName."</a>";
						//16-04-03 arrows
						$ax['order']='d';
						if($columnSortState!=2){
							$html.="<a href='?".htmlspecialchars(http_build_query($ax))."'>▼</a>";
						}else{
							$html.="▼";
						}
						$ax['order']='a';
						if($columnSortState!=1){
							$html.="<a href='?".htmlspecialchars(http_build_query($ax))."'>▲</a>";
						}else{
							$html.="▲";
						}
						
						$html.="</th>\n";
					}
				}else{
					foreach($this->columns as $cd){
						if($cd->hidden) continue;
						$html.="\t\t\t<th>".$cd->displayName."</th>\n";
					}
				}
				$html.="\t\t</tr>\n";
				$html.="\t</thead>\n";
				
				$sby="";
				$sod=$this->sortOrder;
				if($this->sortColumn!==null){
					$sby=$this->sortColumn;
				}
				
				$html.="\t<tbody data-sort-by='$sby' data-sort-order='$sod'>\n";
				
				$i=-1;
				
				
				$lastItem = ($limit==-1 ? count($this->tempArray) : $offset+$limit);
				
				foreach($this->tempArray as $rn=>$rd){
					$i++;
					if($i<$offset) continue;
					if($limit != -1 && $i-$offset >= $limit) break;
					
					if($this->_rowFormatterCallback!=null) {
						$rd = call_user_func($this->_rowFormatterCallback,$rd);
					}

					if($this->htmlIdColumn!==null && isset($rd[$this->htmlIdColumn])){
						$idc=" id=\"".$rd[$this->htmlIdColumn]."\"";
					}else{
						$idc="";
					}
					$html.="\t\t<tr data-rid='$rn'$idc>\n";
					foreach($this->columns as $cd){
						if($cd->hidden || !isset($rd["sortable_".$cd->displayKey])) continue;
						$title = isset($rd["coltitle_".$cd->displayKey])?" title=\"".htmlspecialchars($rd["coltitle_".$cd->displayKey])."\"":"";
						$sv=$rd["sortable_".$cd->displayKey];//$cd->getCellSortableValueByRow($this->data[$rn]);
						$cv=$rd[$cd->displayKey];//$cd->getCellValueByRow($this->data[$rn])
						if($cd->contentType!=TableThingColumnInfo::CONTENT_HTML && $cd->contentType!=TableThingColumnInfo::CONTENT_FMTNUM){
							$cv=htmlspecialchars($cv);
						}
						//$html.="\t\t\t<td data-colid=\"{$cd->displayKey}\" data-sv=\"".htmlspecialchars($sv)."\"> rowid=$rn i=$i offset=$offset limit=$limit ".$cv."</td>\n";
						if($cd->htmlClass!="") 
							$cl=" class=\"{$cd->htmlClass}\"";
						else
							$cl="";
						$html.="\t\t\t<td data-cid=\"{$cd->displayKey}\" data-sv=\"".htmlspecialchars($sv)."\"$cl$title>".$cv."</td>\n";
					}
					$html.="\t\t</tr>\n";
					$this->_rowsProcessed++;
				}

				$html.="\t</tbody>\n";
				$html.="</table>\n";
				
				if($this->isScrollable){
					$currentpage=isset($_GET['p'.$this->uniqId])?$_GET['p'.$this->uniqId]:1;
					//echo "range: ".(($currentpage-1)*20);
					unset($_GET['p'.$this->uniqId]);
					$qs=http_build_query($_GET);
					if($qs!="") $qs="&".$qs;
					$html .= create_pagination(ceil(count($this->tempArray)/$limit),$currentpage,"?p{$this->uniqId}=%1\$d$qs#{$this->htmlId}","","ttpag".$this->uniqId);
				}
				return $html;
			}
			public function genJSON($offset=0,$limit=-1){
				$json="";
				$this->_rowsProcessed=0;
				
				$json.="{";
				
				// HEADER
				$json.="\"columninfo\":[";
				$cols=array();
				foreach($this->columns as $cd){
					$cols[]=json_encode(array("displayKey"=>$cd->displayKey,"displayName"=>$cd->displayName,"contentType"=>$cd->contentType,"sortOrder"=>$cd->sortOrder,"hidden"=>(($cd->hidden)?1:0),"htmlClass"=>$cd->htmlClass));
				}
				$json.=implode(",",$cols);
				$json.="],";
				$json.="\"data\":[";
				
				$rows=array();
				
				if(!$this->isJsonFetcher && !$this->usingCached ){
					$this->genTempArray($offset,$limit);
				}else{
					$this->sortTempArray();
				}
				//foreach($this->data as $rn=>$rd){
				/*$i=$offset;
				do{
					$rd=$this->tempArray[$i];
					$row=array();
					foreach($this->columns as $cd){
						$row[$cd->displayKey]=$rd[$cd->displayKey];//$cd->getCellValueByRow($rd);
						$row["sortable_".$cd->displayKey]=$rd["sortable_".$cd->displayKey];//$cd->getCellSortableValueByRow($rd);
					}
					$rows[]=json_encode($row);
				}while(isset($this->tempArray[++$i]) && ($limit==-1 || ($limit>0 && $i < $offset+$limit)));*/
				
				$rn=$offset;
				
				//foreach($this->tempArray as $rn=>$rd){
				if($rn<sizeof($this->tempArray)) {
					do{
						if(!isset($this->tempArray[$rn])) continue;
						$rd=$this->tempArray[$rn];
						$row=array();
						foreach($this->columns as $cd){

							$row[$cd->displayKey]=$rd[$cd->displayKey];//$cd->getCellValueByRow($rd);
							$row["sortable_".$cd->displayKey]=$rd["sortable_".$cd->displayKey];//$cd->getCellSortableValueByRow($rd);
						}
						$rowEnc=json_encode($row);
						if($rowEnc!="") {
							$rows[]=$rowEnc;
							$this->_rowsProcessed++;
						}
						
					}while(isset($this->tempArray[++$rn]) && ($limit==-1 || ($limit>0 && $rn < $offset+$limit)));
					$json.=implode(",",$rows);
				}
				$json.="]";
				$json.="}";
				return $json;
			}
			
			protected function getRealColumnSortState($columnInfo){
				if($this->sortOrderSet) {
					return $this->sortOrder;
				}else{
					return (($this->sortOrder==SORT_ASC ) xor ($columnInfo->sortOrder==SORT_DESC)) ? SORT_ASC : SORT_DESC;
				}
			}
			
			public function setUniqId($id){
				$this->uniqId=strtr(base64_encode(crc32($id)),'/+=',"000");
			}
			
			public function getItemsCountInCurrentView(){
				return $this->_rowsProcessed;
			}
			
			public static function staticInit(){
				if(self::$dataDir==null) self::$dataDir =  "./n14data";
			}
			
			public static function genUniqId(){
				do{
					$newId=strtr(base64_encode(mt_rand(0,mt_getrandmax())),'/+=',"000");
				}while(file_exists(self::$dataDir."/table_$newId.json"));
				return $newId;
			}
			

			
			public static function tableIdExists($id){
				
				return file_exists(self::$dataDir."/table_".TableThing::genStaticUniqId($id).".json") || file_exists(self::$dataDir."/table_".($id).".json");
			}
			public static function genStaticUniqId($str){
				return strtr(base64_encode(crc32($str)),'/+=',"000");
			}
			
			public static function flushOldCache($age=86400){
				$dir=opendir(self::$dataDir."");
				while(($df=readdir($dir))!==false){
					if(strpos($df,"table_")===0 && strpos($df,".json")!==false && filemtime(self::$dataDir."/$df")<time()-$age){
						unlink(self::$dataDir."/$df");
					}
				}
				closedir($dir);
			}
			
			public static function getCachedTableAge($id){
				if(file_exists(self::$dataDir."/table_".TableThing::genStaticUniqId($id).".json"))
					return time()-filemtime(self::$dataDir."/table_".TableThing::genStaticUniqId($id).".json");
				else if(file_exists(self::$dataDir."/table_".$id.".json"))
					return time()-filemtime(self::$dataDir."/table_".$id.".json");
				else
					return time();
			}
			
		}
		
		
		
		class TableThingColumnInfo{
			public $displayName;
			public $displayKey=null;
			public $sortKey=null;
			public $sortOrder=SORT_ASC;
			public $callback=null;
			public $hidden=false;
			public $contentType=false;
			public $htmlClass="";
			
			private static $uniqVal=0;
			
			const CONTENT_TEXT=0;
			const CONTENT_NUM=1;
			const CONTENT_HTML=2;
			const CONTENT_NUMFLOAT=3;
			const CONTENT_FMTNUM=4;
			
			public function __construct($realname,$displayname=null){
				$this->sortKey=$realname;
				if(is_callable($realname) && !is_string($realname)) { // not sure 'bout the security here
					$this->callback = $realname;
					$this->displayKey="col".self::getUniqVal();
				}else{
					$this->displayKey=$realname;
				}
				$this->displayName=($displayname==null?$realname:$displayname);
			}
			
			public function getCellValueByRow($row){
				if($this->callback!==null && is_callable($this->callback) && !is_string($this->callback)){
					return call_user_func($this->callback,$row);
				}else{
					if($this->contentType===$this::CONTENT_TEXT) return htmlspecialchars($row[$this->displayKey]);
					return $row[$this->displayKey];
					/*switch($this->contentType){
						case $this::CONTENT_TEXT:
							return htmlspecialchars($row[$this->displayKey]);
						case $this::CONTENT_NUM:
						case $this::CONTENT_NUMFLOAT:
						case $this::CONTENT_FMTNUM:
						case $this::CONTENT_HTML:
							return $row[$this->displayKey];
					}*/
				} 
			}
			public function getCellSortableValueByRow($row){
				if(is_callable($this->sortKey) && !is_string($this->sortKey)){
					$result=call_user_func($this->sortKey,$row);
				}else if(isset($row[$this->sortKey])){
					$result=$row[$this->sortKey];//."|".$this->sortKey;
				} else{
					$result="";
				}
				if($this->contentType==TableThingColumnInfo::CONTENT_NUM || $this->contentType==TableThingColumnInfo::CONTENT_FMTNUM){
					$result=intval($result);
				}else if($this->contentType==TableThingColumnInfo::CONTENT_NUMFLOAT){
					$result=floatval($result);
				}
				return $result;
			}
			public function setDefaultSortOrder($sorder){
				$this->sortOrder=$sorder;
			}
			
			public static function getUniqVal(){
				return self::$uniqVal++;
			}
		}
		
		
		
		class TableThingException extends \Exception{
			public function __construct($message, $backtrace) {
				$caller = next($backtrace);
				if(count($backtrace)==1) $caller=reset($backtrace);

				parent::__construct($message);
				
				$this->file=$caller['file'];
				$this->line=$caller['line'];
				
			}
		}
		
		
		
		if(isset($_GET['fetchJSON'])){
			$tableid=$_GET['fetchJSON'];
			$offset=isset($_GET['offset'])?$_GET['offset']:0;
			$limit=isset($_GET['limit'])?$_GET['limit']:-1;
			TableThing::staticInit();
			if(TableThing::tableIdExists($tableid)){
				$tx=new TableThing();
				$tx->isJsonFetcher=true;
				$tx->loadDataFromCache($tableid);
				
			
				echo $tx->genJSON($offset,$limit);
			}
			
		}
		/* NemoPagination
		 * Originally written for DSC CMS
		 * 2009 namo
		 * "drogi JA z 2013r, przepraszam za to co tu popełniłem" ~namo
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
	}
	
	namespace {
		//require_once "sqlengine.php";
	}

?>