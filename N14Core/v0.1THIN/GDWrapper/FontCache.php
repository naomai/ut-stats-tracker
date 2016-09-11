<?php
require_once("TTFInfo.php");


class FontCache{
	public $fontDir = "C:\\Windows\\Fonts";
	protected $fontList = array();
	protected $fontFamilyList = array();
	public $cacheFile = "./fontcache.dat";
	protected $ttfInfoObject = null; 
	protected $cacheChanged = false;
	
	public function __construct(){
		$this->ttfInfoObject = new ttfInfo();
	}
	
	public function __destruct(){
		$this->ttfInfoObject = null;
		$this->saveFontCache($this->cacheFile);
	}
	
	public function preload(){
		$this->loadFontCache($this->cacheFile);
	}
	
	public function scanFonts(){
		$dirH = opendir($this->fontDir);
		if(!$dirH){
			throw new Exception("Invalid font directory");
		}
		
		while(($fontFile = readdir($dirH)) !== false) {
			$ext = pathinfo($fontFile,PATHINFO_EXTENSION);
			if(strcasecmp($ext,"ttf")===0){
				$fontPath = $this->fontDir . "/" . $fontFile;
				$base = pathinfo($fontFile,PATHINFO_BASENAME);
				$fontFileSize = filesize($fontPath);
				if(!isset($this->fontList[$base]) || $this->fontList[$base]['size'] != $fontFileSize){
					$this->cacheChanged = true;
					$fontInfo = array();
					
					$fontInfo['info'] = $this->getFileInfo($fontPath);
					
					$fontInfo['path']=$fontPath;
					$fontInfo['size']=$fontFileSize;
					
					$this->fontList[$base]=$fontInfo;
					
				}
				
			}
		}
		closedir($dirH);
		$this->rebuildFamilyList();
		
		//print_r($this->fontFamilyList);
	}
	
	protected function getFileInfo($file){
		$fontInfo=array();
		$this->ttfInfoObject->setFontFile($file); 
		$fontInfoRaw = $this->ttfInfoObject->getFontInfo();
		//echo "SCAN: $file\r\n";
		$fontInfo=array(
			"family" => $fontInfoRaw[1],
			"type" => $fontInfoRaw[2],
			"fontid" => $fontInfoRaw[3],
			"fullname" => $fontInfoRaw[4]
		);
		return $fontInfo;
	}
	
	protected function rebuildFamilyList(){
		foreach($this->fontList as $fileName=>$fontData){
			$family = $fontData['info']['family'];
			$type = $fontData['info']['type'];
			$this->fontFamilyList[strtolower($family)][strtolower($type)] = $fontData;
		}
	}
	
	protected function loadFontCache($file){
		if(file_exists($file)){
			$this->fontList = unserialize(file_get_contents($file));
			if(count($this->fontList)) 
				$this->rebuildFamilyList();
		}
	}
	
	protected function saveFontCache($file){
		if($this->cacheChanged){
			file_put_contents($file,serialize($this->fontList));
		}
	}
	
	public function getFontFamily($family){
		$familyLC = strtolower($family);
		if(isset($this->fontFamilyList[$familyLC])){
			return $this->fontFamilyList[$familyLC];
		}else{
			return array();
		}
	}

}


?>