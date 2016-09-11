<?php
/* GDConsole
 * 
 * 2013 namonaki14
 * 
 * Changelog:
 * 
 * '14-08-04 cre
 *
 */
 
require_once __DIR__ . "/../Console.class.php";
require_once __DIR__ .  "/../GDWrapper.php";
 
class GDConsole extends N14\ANSIConsole implements GDLayerRenderer {
	protected $layer;
	protected $fontId = 0, $fontW,$fontH;
	protected $loadedFonts = array();
	protected $conColors = array();
	protected $conBGColors = array();
	
	public function __construct(){
		parent::__construct();
		
		for($i=0; $i<16;$i++){
			$this->conColors[$i]=((($i&1))|(($i&2)<<7)|(($i&4)<<14))*(($i&8)?0xFF:0x80);
			$this->conBGColors[$i]=(((($i&1))|(($i&2)<<7)|(($i&4)<<14))*0xFF) | (($i&8)?0x00000000:0x40000000);
		}
		$this->conColors[8]=0x808080;
		
		
	}
	
	public function attachLayer($layerObj){
		$this->layer = $layerObj;
	}
	public function apply(){
		$w = $this->width;
		$h = $this->height;
		for($y=0; $y<$h; $y++){
			for($x=0; $x<$w; $x++){
				$charR = $this->rawLines[$y][$x];
				$char = $charR & 0xFF;
				$attrib = ($charR>>8) & 0xFF;
				$this->drawChar($char,$attrib,$x,$y);
			}
		}
	}
	
	public function setFont($f){
		if(is_string($f)){
			$fontPath = realpath($f);
			if(isset($this->loadedFonts[$fontPath])){
				$f = $this->loadedFonts[$fontPath];
			}else{
				$f = $this->layer->paint->loadBMFont($fontPath);
				$this->loadedFonts[$fontPath] = $f;
			}
		}	
		
		if(is_numeric($f)){
			$this->fontId = $f;
			$this->fontW = imagefontwidth($f);
			$this->fontH = imagefontheight($f);
		}	
		return $f;
	}
	
	protected function drawChar($code, $attrib, $x, $y){
		$realX = $x * $this->fontW;
		$realY = $y * $this->fontH;
		$fgCol = $attrib & 0xF;
		$bgCol = ($attrib>>4) & 0xF;
		
		if($code!=0){
			$this->layer->paint->rectangle($realX, $realY, $realX + $this->fontW, $realY + $this->fontH,GDRECT_FILLED,0,$this->conBGColors[$bgCol]);
		}
		
		if($code>20)
			$this->layer->paint->textBM($realX, $realY, chr($code), $this->fontId, $this->conColors[$fgCol]);
	}
}

?>