<?php
/* GDWRichText
 * 
 * 2014 namonaki14
 * 
 * Changelog:
 * 
 * '14-xx-xx cre
 *
 */
namespace{
	require_once __DIR__ .  "/../GDWrapper.php";
	require_once __DIR__ .  "/FontCache.php";

}

namespace N14\GDWrapper\Renderers{
	use N14\GDWrapper\Renderers\RichTextNodes as Nodes;
	class RichText implements ILayerRenderer {
		protected $layer;
		
		protected $document = array();
		protected $currentParagraph = null;
		//viewport
		public $position = array("auto"=>true);
		public $margin = array("auto"=>true);
		//document properties
		public $backgroundColor = 0xFFFFFF;
		//current paragraph properties
		public $align = RichText::GDWRT_ALIGN_LEFT;
		//next-character properties
		public $textColor       = 0x000000;
		public $highlightColor  = 0x7FFFFFFF;
		public $font = "Tahoma";
		public $fontSize = 12;
		public $fontBold      = false;
		public $fontItalic    = false;
		public $textUnderline = false;
		public $textStrike    = false;
		
		//font sources
		protected $systemFonts;
		protected $wwwFonts;
		protected $defaultFont;
		
		
		const GDWRT_ALIGN_LEFT   = 0;
		const GDWRT_ALIGN_CENTER = 1;
		const GDWRT_ALIGN_RIGHT  = 2;
		
		
		public function __construct(){
			global $fontsPathSystem, $fontsPathWWW;
			$this->systemFonts = new \FontCache();
			$this->systemFonts->fontDir = isset($fontsPathSystem) ? $fontsPathSystem : "C:\\Windows\\Fonts";
			if(file_exists($this->systemFonts->fontDir)){
				$this->systemFonts->cacheFile = __DIR__ . "\SystemFonts.dat";
				if(file_exists($this->systemFonts->cacheFile)){
					$this->systemFonts->preload();
				}else{
					$this->systemFonts->scanFonts();
				}
			}
			
			$this->wwwFonts = new \FontCache();
			$this->wwwFonts->fontDir = isset($fontsPathWWW) ? $fontsPathWWW : __DIR__ ."/Fonts";
			if(file_exists($this->wwwFonts->fontDir)){
				$this->wwwFonts->cacheFile = __DIR__ . "\WWWFonts.dat";
				if(file_exists($this->wwwFonts->cacheFile)){
					$this->wwwFonts->preload();
				}else{
					$this->wwwFonts->scanFonts();
				}
			}
			
			$this->defaultFont = $this->wwwFonts->getFontFamily("Tahoma");
		}
		
		public function getSize(){
			if(isset($this->position['auto']) && $this->position['auto']===true){
				$layerRect = $this->layer->getLayerDimensions();
				return array('x'=>0,'y'=>0,'width'=>$layerRect['w'], 'height'=>$layerRect['h'],'auto'=>true);
			}else{
				return $this->position;
			}
		}
		public function getMargin(){
			$size = $this->getSize();
			if(isset($this->margin['auto'])){
				
				$margin = array('left'=>16,'right'=>16,'top'=>16,'bottom'=>16);
			}else{
				$margin = $this->margin;
			}
			
			return $margin;
		}
		public function getInnerSize(){ // with margins
			$size = $this->getSize();
			$margin = $this->getMargin();

			$size['x']     += $margin['left'];
			$size['width'] -= $margin['left']+$margin['right'];
			$size['y']     += $margin['top'];
			if($size['height']!='auto'){
				$size['height']-= $margin['top'] +$margin['bottom'];
			}
			return $size;
		}

		
		public function write($string){
			if($this->currentParagraph == null){
				$this->newParagraph();
			}
			$newNode = new Nodes\TextNode($this->currentParagraph);
			$newNode->textContent    = $string;
			$newNode->textColor      = $this->textColor;
			$newNode->highlightColor = $this->highlightColor;
			$newNode->font           = $this->font;
			$newNode->fontSize       = $this->fontSize;
			$newNode->fontBold       = $this->fontBold;
			$newNode->fontItalic     = $this->fontItalic;
			$newNode->textUnderline  = $this->textUnderline;
			$newNode->textStrike     = $this->textStrike;
			
			$this->currentParagraph->addNode($newNode);
			return $newNode;
		}
		public function insertNodeOfType($type){
			if(is_subclass_of($type, '\N14\GDWrapper\Renderers\RichTextNodes\Node')){
				if($this->currentParagraph == null){
					$this->newParagraph();
				}
				$newNode = new $type($this->currentParagraph);
				$this->currentParagraph->addNode($newNode);
				return $newNode;
			}
		}
		
		public function newParagraph(){
			
			if($this->currentParagraph != null){
				$this->currentParagraph->align = $this->align;
				$this->document[] = $this->currentParagraph;
			}
			$newPar = new Nodes\Paragraph($this);
			$this->currentParagraph = $newPar;
			return $newPar;
		}
		
		public function getFontFile($fontFamily, $type){
			$ff = $this->wwwFonts->getFontFamily($fontFamily);
			if(count($ff) == 0){
				$ff = $this->systemFonts->getFontFamily($fontFamily);
			}
			if(count($ff) == 0){
				$ff = $this->defaultFont;
			}
			if(count($ff) == 0){
				return false;
			}
			$typeLC = strtolower($type);
			
			if(isset($ff[$typeLC])) 
				$fontInfo = $ff[$typeLC];
			else if(isset($ff["regular"])) 
				$fontInfo = $ff["regular"];
			else
				$fontInfo = reset($ff);
			
			return $fontInfo['path'];
		}
		
		public function attachLayer($layerObj){
			$this->layer = $layerObj;
		}
		public function apply(){
			if($this->currentParagraph != null){
				$this->currentParagraph->align = $this->align;
				$this->document[] = $this->currentParagraph;
			}
			
			$gdItems = array();
			
			$posOuter = $this->getSize();
			$pos = $this->getInnerSize();
			
			$offsetX = $pos['x'];
			$offsetY = $pos['y'];
			$docCalculatedHeight = 0;
			$y = 0;
			$toDraw = array();
			foreach($this->document as $item){
				$newGD = $item->render();
				$docCalculatedHeight+=imagesy($newGD);
				$toDraw[] = array('item'=>$item,'gd'=>$newGD);
			}
			
			
			
			if((isset($posOuter['auto']) && $posOuter['auto']==true) || $posOuter['height']=='auto'){
				$margin = $this->getMargin();
			
				$docHeight = $docCalculatedHeight + $margin['bottom'];
				
			}else{
				$docHeight = $posOuter['height'];
			}
			//$this->layer->fill($this->backgroundColor);
			$this->layer->paint->alphaBlend = true;
			$this->layer->paint->rectangle($posOuter['x'],$posOuter['y'],$posOuter['x']+$posOuter['width'],$posOuter['y']+$docHeight,GDRECT_FILLED,null, $this->backgroundColor);
			$docGD = $this->layer->getGDHandle();
			
			foreach($toDraw as $itemDef){
				$itemDef['item']->documentPosY = $y;
				imagecopy($docGD,$itemDef['gd'],$offsetX,$y+$offsetY,0,0,imagesx($itemDef['gd']), imagesy($itemDef['gd']));
				$itemDef['item']->notifyRendered();
				$y += imagesy($itemDef['gd']);
			}
			
		}
	}
}

namespace N14\GDWrapper\Renderers\RichTextNodes{
	use \N14\GDWrapper as GDW;
	use \N14\GDWrapper\Renderers\RichText as GDWRT;
	abstract class Node{
		protected $parentNode, $document;
		/* render() returns an array with following structure:
		( 
			'gd'=>GD handle with rendered element,
			'rect'=>array of rects around each symbol
		)*/
		public function __construct($parent){
			$this->parentNode = $parent;
			$this->document = $this->getDocument();
		}
		protected function getDocument(){
			if($this->parentNode instanceof GDWRT){
				return $this->parentNode;
			}else{
				return $this->parentNode->getDocument();
			}
		}
		
		public abstract function render();
		public function notifyRenderResult($rect){
			
		}
		
	}

	class TextNode extends Node{
		public $textContent = "";
		public $textColor;
		public $highlightColor;
		public $font, $fontSize;
		public $fontBold;
		public $fontItalic;
		public $textUnderline;
		public $textStrike;
		
		public function render(){
			$fontFile = $this->document->getFontFile($this->font, $this->getFontType());
			
			
			// prepare boxes
			$rect = array();
			$w = 0;
			$h = 0;
			
			$offsetX = 0;
			$offsetY = 0;
			
			$minX=0; $minY=0; $maxX=0; $maxY=0;
			
			$fallbackFontW = imagefontwidth(2)-1;
			$fallbackFontH = imagefontheight(2);
			
			for($i=0; $i<mb_strlen($this->textContent); $i++){
				$charRect = array();
				$char = mb_substr($this->textContent, $i, 1);
				if($char == " "){
					$charRect['white'] = true;
				}else if($char == "\r"){
					
				}else if($char == "\n"){
					$charRect['linefeed'] = true;
				}else if($char == "\t"){
					$charRect['tab'] = true;
				}else{
					
				}
				$charRect['_char'] = $char;
				
				if($fontFile){
					$gdBox = imagettfbbox($this->fontSize, 0, $fontFile, $char);
				}else{ // fallback (imagestring)
					$gdBox = array(0, $fallbackFontH, $fallbackFontW, $fallbackFontH, $fallbackFontW, 0, 0, 0);
				}
				
				
				$charRect['x'] = $w;
				$charRect['y'] = $gdBox[7];
				$charRect['width'] = max($gdBox[0],$gdBox[2],$gdBox[4],$gdBox[6]) - min($gdBox[0],$gdBox[2],$gdBox[4],$gdBox[6])+1 ;
				$charRect['height'] = $gdBox[1] - $gdBox[7];
				
				/*$offsetX = max($offsetX, $gdBox[0]-$gdBox[6]);
				$offsetY = max($offsetY, $gdBox[3]-$gdBox[7]);*/
				$minX=min($minX,$gdBox[0],$gdBox[2],$gdBox[4],$gdBox[6]); // X of point closest to the left edge
				$maxX=max($maxX,$gdBox[0],$gdBox[2],$gdBox[4],$gdBox[6]); // X ----\\---- right edge
				$minY=min($minY,$gdBox[1],$gdBox[3],$gdBox[5],$gdBox[7]); // Y ----\\---- top edge
				$maxY=max($maxY,$gdBox[1],$gdBox[3],$gdBox[5],$gdBox[7]); // Y ----\\---- bottom edge
				
				$w += $charRect['width'];
				//$h = max($h,$charRect['width']);

				$rect[] = $charRect;
			}
		
			$offsetX = 0;
			$offsetY = -$minY;
			$h = $maxY - $minY;
			
			// draw
			$elGD = imagecreatetruecolor($w,$h);
			imagealphablending($elGD,false);
			imagefilledrectangle($elGD,0,0,$w,$h,$this->highlightColor);
			imagealphablending($elGD,true);
			
			foreach($rect as $rectId=>$current){
				$current['y'] += $offsetY;
				$rect[$rectId]['y'] += $offsetY;
				//imagerectangle($elGD,$current['x'],$current['y'], $current['x']+$current['width'],$current['y']+$current['height'],0xff0000);
				if($fontFile){
					imagettftext($elGD,$this->fontSize,0,$current['x']+$offsetX,$offsetY-1,$this->textColor,$fontFile,$current['_char']);
				}else{ // fallback
					imagestring($elGD, 2, $current['x'], $offsetY, $current['_char'], $this->textColor);
				}
				
				
				unset($rect[$rectId]['_char']);
			}
			imagesavealpha($elGD,true);
			return array('gd'=>$elGD, 'rect'=>$rect);
		}
		
		protected function getFontType(){
			if($this->fontBold && $this->fontItalic){
				$type = "Bold Italic";
			}else if($this->fontBold){
				$type = "Bold";
			}else if($this->fontItalic){
				$type = "Italic";
			}else{
				$type = "Regular";
			}
			return $type;
		}
	}

	class Paragraph extends Node{
		public $align = GDW\Renderers\RichText::GDWRT_ALIGN_LEFT;
		public $lineHeight = 16;
		public $documentPosY = 0;
		protected $nodes = array();
		protected $resultRects = array();
		
		public function addNode($node){
			if($node instanceof Node && !($node instanceof Paragraph)){
				$this->nodes[] = $node;
			}
		}
		
		public function render(){
			$docSize = $this->document->getInnerSize();
			//$marginSize = $this->document->getMargin();
			
			// throw all rects into one array
			$flatRects = array();
			foreach($this->nodes as $nodeId=>$node){
				$nodeRendered = $node->render();
				$gd = $nodeRendered['gd'];
				$rects = $nodeRendered['rect'];
				foreach($rects as $rectId=>$rect){
					$rect['gd'] = $gd;
					$rect['nodeId'] = $nodeId;
					$rect['rectId'] = $rectId;
					$flatRects[] = $rect;
				}
			}
			/*print_r($flatRects);
			die;*/
			//echo "R=".count($flatRects);
			
			// calculate text flow
			$width = $docSize['width'];
			$height = 0;
			
			$lineInfo = array();
			$charsForCurrentLine = 0;
			$charsForCurrentWord = 0;
			$currentLineHeight = $this->lineHeight;
			$wordBeginning = 0;
			
			$x = 0; //$y = 0;
			$xSafe = 0;
			$justWrapped = false;
			
			
			
			for($i=0; $i<count($flatRects); $i++){
				$rect = $flatRects[$i];
				//eCHO "(i=$i,W=$x,H=$height)   ";
						
				if($x + $rect['width'] > $width || isset($rect['linefeed'])){ // line overflow or newline 
					if(isset($rect['resizable']) && $rect['width']>$width){ // image too big
						$flatRects[$i]['resizeTo'] = array('width'=>$width, 'height'=>$rect['height'] * ($width/$rect['width']));
					}else if(!isset($rect['linefeed']) && $justWrapped){ // break long word
						$charsForCurrentLine = $charsForCurrentWord;
						$charsForCurrentWord = 0;
						//ECHO "CFCW=$charsForCurrentLine;";
						$xSafe = $x;
						$i-=1;
					}else if(!isset($rect['linefeed'])){ // go back to last 'safe' position (first character of current word)
						//eCHO "WB=$wordBeginning;";
						$i = $wordBeginning-1;
					}
					
					if(isset($rect['linefeed']) || $xSafe>0){ // line feed or overflow with whitespace character
						if(isset($rect['linefeed'])){
							$charsForCurrentLine += $charsForCurrentWord+1;
							$xSafe = $x;
						}
						//$charsForCurrentLine+=1;
						//eCHO "LF=$charsForCurrentLine;";
						$lineInfo[] = array("chars"=>$charsForCurrentLine,"width"=>$xSafe,"height"=>$currentLineHeight);
						$height += $currentLineHeight;
						$currentLineHeight = $this->lineHeight;
						$wordBeginning = $i;
					}
					
					$charsForCurrentWord = 0;
					$charsForCurrentLine = 0;
					$x = 0;
					$xSafe = 0;
					//$y += $this->lineHeight;
					$justWrapped = true;
				}else if(isset($rect['white'])){
					$xSafe = $x;
					$wordBeginning = $i+1;
					$justWrapped = false;
					$charsForCurrentLine += $charsForCurrentWord+1;
					$charsForCurrentWord = 0;
					$x += $rect['width'];
				}else{
					$charsForCurrentWord++;
					$x += $rect['width'];
					$currentLineHeight = max($currentLineHeight,$rect['height']);
				}
				/*if(count($lineInfo)>20){
					print_r($lineInfo);
					die;
				}*/
			}

			$charsForCurrentLine += $charsForCurrentWord;
			$lineInfo[] = array("chars"=>$charsForCurrentLine,"width"=>$x,"height"=>$currentLineHeight);
			$height += $currentLineHeight;
			/*print_r($lineInfo);
					die;*/
			//$height = count($lineInfo) * $this->lineHeight;
			$gd = imagecreatetruecolor($width, $height);
			imagealphablending($gd,false);
			imagefilledrectangle($gd,0,0,$width,$height,0x7f000000);
			imagealphablending($gd,true);
			
			
			//$resultRects = array(); // one for each childNodes
			
			$lineOffset = 0;
			$lineOffsetY = 0;
			foreach($lineInfo as $lineNum=>$line){
				//echo "LIN$lineNum=".$line['chars']."!";
				$lineAlignOffset = round(($width - $line['width']) * ($this->align / 2));
				//$lineOffsetY = $lineNum * $this->lineHeight;
				
				$x = $lineAlignOffset;
				for($c=0; $c<$line['chars']; $c++){
					$rect = $flatRects[$lineOffset + $c];
					if(isset($rect['linefeed'])) continue;
					//die("imagecopy(,,$x,$lineOffsetY,{$rect['x']},{$rect['y']},{$rect['width']},{$rect['height']});");
					
					if($rect['gd'] !== null){
						imagecopy($gd,$rect['gd'],$x,$lineOffsetY+$rect['y'],$rect['x'],$rect['y'],$rect['width'],$rect['height']);
					}
					if(!isset($this->resultRects[$rect['nodeId']])) 
						$this->resultRects[$rect['nodeId']]=array('gdResult'=>$gd,'rect'=>array());
					
					//echo "NID={$rect['nodeId']} LOF=$lineOffsetY; ";
					$this->resultRects[$rect['nodeId']]['rect'][$rect['rectId']] = array(
						'x'=>$x,'y'=>$lineOffsetY+$rect['y'],
						'layerX'=>$x+$docSize['x'],'layerY'=>$lineOffsetY+$rect['y']+$docSize['y'],
						'width'=>$rect['width'],'height'=>$rect['height']
						);
					$x += $rect['width'];
				}
				$lineOffset += $line['chars'];
				$lineOffsetY += $line['height'];
			}
			
			
			
			return $gd;
		}
		
		public function notifyRendered(){
			//notify
			foreach($this->resultRects as $nodeId=>$rectInfo){
				foreach($rectInfo['rect'] as $rectId=>$rect){
					$rectInfo['rect'][$rectId]['y']+=$this->documentPosY;
					$rectInfo['rect'][$rectId]['layerY']+=$this->documentPosY;
				}
				$this->nodes[$nodeId]->notifyRenderResult($rectInfo);
			}
		}
	}

	class ImageNode extends Node{
		public $gdContainer;
		public function render(){
			if(is_resource($this->gdContainer) && get_resource_type($this->gdContainer)=="gd"){
				$rect = array(
					'gd'=>$this->gdContainer, 
					'rect'=>array(
						array('x'=>0,'y'=>0,'width'=>imagesx($this->gdContainer),'height'=>imagesy($this->gdContainer))
					)
				);
				return $rect;
			}else{
				return array('gd'=>null,'rect'=>array());
			}
		}
	}
}
