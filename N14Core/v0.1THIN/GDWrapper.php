<?php
/** GD OOP Wrapper
 * 
 * 2013 namonaki14
 * 
 * Changelog:
 * 
 * '13-01-04 cre
 * '16-01-27 added Clip, Image multiconstructor replaced with static methods createFromGD and createFromFile
 *
 * Dependencies: PHP5.3, GD2, SPL
 *
 * @version 0.1.1
 * 
 */
namespace N14\GDWrapper{
	define("GDIMAGE_SUPPORTS_AFFINE", function_exists("imageaffine"));
	 
	class Image {
		protected $layers = array(); // stack order (push-pop)
		protected $sizeX, $sizeY;
		protected $layerIdCounter = 0;
		
		/**
		 *  Creates new Image object.
		 *  
		 *  This constructor can be called in 2 ways:
		 *  - single argument: image resource, which will be added to a layer set of new object,
		 *  - two arguments: width and height of new image.
		 *  
		 *  @param int 	$width 	  or width of a new image
		 *  @param int 	$height   height of a new image
		 *  @since 0.1.0
		 */	
		public function __construct($width, $height, $createLayer = true){
			/*$args = func_get_args();
			if(count($args === 1) && is_resource($args[0]) && get_resource_type ($args[0])==="gd"){ // (resource $img)
				$this->addLayerTop(new Layer($args[0]));
				$this->setSize(imagesx($args[0]),imagesy($args[0]));
			}else if(count($args === 2) && is_numeric($args[0]) && is_numeric($args[1])){ // (int $x, int $y)
				$this->addLayerTop(new Layer($args[0],$args[1]));
				$this->setSize($args[0],$args[1]);
			}*/
			
			if( is_numeric($width) && is_numeric($height)){
				if($createLayer) {
					$bgLayer = new Layer($width,$height);
					$bgLayer->name = "Background";
					$this->addLayerTop($bgLayer);
				}
				
				$this->setSize($width,$height);
				$this->setComposer(new Composers\DefaultComposer($this));
			}
		}
		
		/**
		 *  Puts a layer object to the top of image's layer set.
		 *  
		 *  Inserted layer is drawn over the existing image.
		 *  
		 *  @param [in] $layerObj Parameter_Description
		 *  @return Unique layer ID
		 *  @since 0.1.0
		 */
		public function addLayerTop($layerObj){
			if(!($layerObj instanceof Layer)){
				throw new InvalidArgumentException("addLayerTop: Must be a Layer object");
			}
			$this->layers[$this->layerIdCounter] = $layerObj; 
			$layerObj->setParentImg($this);
			return $this->layerIdCounter++;
		}
		
		/**
		 *  Puts a layer object to the bottom of image's layer set.
		 *  
		 *  Inserted layer is drawn behind the existing image.
		 *  
		 *  @param [in] $layerObj Parameter_Description
		 *  @return Unique layer ID
		 *  @since 0.1.0
		 */
		public function addLayerBottom($layerObj){
			if(!($layerObj instanceof Layer)){
				throw new InvalidArgumentException("addLayerBottom: Must be a Layer object");
			}
			$this->layers = array(($this->layerIdCounter)=>$layerObj) + $this->layers; 
			$layerObj->setParentImg($this);
			return $this->layerIdCounter++;
		}
		
		/**
		 *  Create new layer and put it on top of layer set. 
		 *  
		 *  @return Unique layer ID for the new layer
		 *  @since 0.1.0
		 */
		public function newLayer(){
			$newLayer = new Layer($this->sizeX,$this->sizeY);
			$newLayer->clear();
			$newLayer->name = "Layer ".count($this->layers);
			return $this->addLayerTop($newLayer);
		}
		
		/**
		 *  Change the image's layer composer object.
		 *  
		 *  @since 0.1.0
		 */
		public function setComposer($composerObj){
			$this->composer = $composerObj;
		}
		
		/**
		 *  Gets the size of image object
		 *  
		 *  @return Array containing two elements: 'w': image width, 'h': image height
		 *  @since 0.1.0
		 */
		public function getSize(){
			return array(
				'w'=>$this->sizeX,
				'h'=>$this->sizeY
			);
		}
		
		/**
		 *  Sets the new side of image object.
		 *  
		 *  This method only manipulates on the canvas size, it doesn't resize the existing content.
		 *  
		 *  @param int $w New image width
		 *  @param int $h New image height
		 *  @since 0.1.0
		 */
		public function setSize($w,$h){
			$this->sizeX=$w;
			$this->sizeY=$h;
		}
		
		/**
		 *  Gets the Layer object from layer set using unique layer ID
		 *  
		 *  @param int $id Unique layer ID
		 *  @return Layer object matching the ID provided, or FALSE if ID is invalid.
		 *  @since 0.0.0
		 */
		public function getLayerById($id){
			return isset($this->layers[$id]) ? $this->layers[$id] : false;
		}
		
		/**
		 *  Finalize image into Layer object.
		 *  
		 *  Merges all layers in image layer set using current layer composer.
		 *  The result is a new Layer object. The original layer set is left intact.
		 *  
		 *  @return Layer object containing merged content of image
		 *  @since 0.1.0
		 */
		public function getMerged(){
			$this->composer->fillLayers($this->layers);
			$finalLayer = $this->composer->mergeAll();
			return $finalLayer;
		}
		
		/**
		 *  Finalize image into GD2 image resource.
		 *  
		 *  Merges all layers in image layer set using current layer composer.
		 *  The result is a GD2 image resource accessible by native PHP functions.
		 *  The original layer set is left intact.
		 *  
		 *  @return GD2 resource containing merged content of image
		 *  @since 0.1.0
		 */
		public function getMergedGD(){
			return $this->getMerged()->getGDHandle();
		}
		
		public function getDataUrlPNG(){
			ob_start();
			imagepng($this->getMergedGD());
			$imgd=base64_encode(ob_get_clean ());
			return "data:image/png;base64,".$imgd;
		}
		public function getDataUrlJPEG(){
			ob_start();
			imagejpeg($this->getMergedGD());
			$imgd=base64_encode(ob_get_clean ());
			return "data:image/jpeg;base64,".$imgd;
		}
		
		public static function createFromGD($gdResource){
			if(is_resource($gdResource) && get_resource_type ($gdResource)==="gd"){
				$gdImg = new Image(imagesx($gdResource),imagesy($gdResource),false);
				$gdImg->addLayerTop(new Layer($gdResource));
				return $gdImg;
			}
		}
		public static function createFromFile($fileName){
			if(is_string($fileName) && file_exists ($fileName)){
				$gdResource = imagecreatefromstring(file_get_contents($fileName));
				return self::createFromGD($gdResource);
			}
		}
	}

	class Layer {

		
		protected $gdImage = null;
		
		/**
		 *  @var int $offsetX Layer position on the destination image (X coordinate) 
		 *  @var int $offsetY Layer position on the destination image (Y coordinate) 
		 */
		public $offsetX=0, $offsetY=0;
		public $name = "";
		protected $sizeX, $sizeY;
		protected $blending = Layer::GDLAYER_BLEND_NORMAL;
		protected $opacity = 100;
		/**
		 *  @var GDLayerFilter	$filter 	Object providing image filters
		 *  @var int 			$paint 		Object providing drawing functions
		 *  @var int 			$renderer 	Image preprocessor used before merging with other layers
		 */
		public $filter,$paint,$renderer;
		protected $parentImg;
		
		const GDLAYER_BLEND_NORMAL=0;
		
		public function __construct(){
			$args = func_get_args();
			if(count($args === 1) && is_resource($args[0]) && get_resource_type ($args[0])==="gd"){ // (resource $img)
				$this->gdImage = $args[0];
			}else if(count($args >= 2) && is_numeric($args[0]) && is_numeric($args[1])){ // (int $w, int $h)
				$this->gdImage = imagecreatetruecolor($args[0],$args[1]);
				if(count($args) === 4 && is_numeric($args[2]) && is_numeric($args[3])){ // (int $w, int $h, int $x, int $y)
					$this->offsetX = $args[2];
					$this->offsetY = $args[3];
				}
			}else{
				throw new BadFunctionCallException("Layer::__construct requires either 1 or 2 arguments of strictly specified types.");
			}
			$this->sizeX = imagesx($this->gdImage);
			$this->sizeY = imagesy($this->gdImage);
			$this->filter = new Filters\PHPFilters($this);
			
			$this->paint = new PaintTools\DefaultTools($this);
		}
		public function getLayerDimensions(){
			return  array(
				'x'=>$this->offsetX,
				'y'=>$this->offsetY,
				'w'=>$this->sizeX,
				'h'=>$this->sizeY
			);
		}
		public function getGDHandle(){
			return $this->gdImage;
		}
		
		public function setOpacity($opacity){
			$this->opacity = $opacity;
		}
		public function getOpacity(){
			return $this->opacity;
		}
		
		/* PAINT */
		public function fill($color){
			imagealphablending ($this->gdImage,false);
			imagefilledrectangle ($this->gdImage,0,0,$this->sizeX-1,$this->sizeY-1,$color);
			imagealphablending ($this->gdImage,true);
		}

		public function clear(){
			$this->fill(0x7F000000);
		}
		/* SELECT */
		public function select(){
			$args=func_get_args();
			if(count($args) == 4){
				list($x,$y,$w,$h)=$args;
			}else if(count($args) == 0){
				$x=$this->offsetX;
				$y=$this->offsetY;
				$w=$this->sizeX;
				$h=$this->sizeY;
			}else{
				throw new BadFunctionCallException("Layer::select requires either 0 or 4 arguments.");
			}
			return new Selection($this->gdImage,$x,$y,$w,$h);
		}
		
		public function pasteClip($clip,$x=0,$y=0){
			$clipImg = $clip->getContents();
			imagecopy($this->gdImage,$clipImg,$x,$y,0,0,imagesx($clipImg),imagesy($clipImg));
		}
		
		public function transformPermanently(){
			$imgSize = $this->parentImg->getSize();
			$newLayerGD = imagecreatetruecolor($imgSize['w'],$imgSize['h']);
			imagealphablending ($newLayerGD,false);
			imagefill($newLayerGD,0,0,0x7F000000);
			imagecopy($newLayerGD, $this->gdImage, 
				$this->offsetX,$this->offsetY, 
				0, 0, 
				$this->sizeX,$this->sizeY); 
			imagedestroy($this->gdImage);
			$this->gdImage = $newLayerGD;
			$this->offsetX = $this->offsetY = 0;
			$this->sizeX = $imgSize['w'];
			$this->sizeY = $imgSize['h'];
		}
		
		public function setParentImg($parentImg){
			$this->parentImg = $parentImg;
		}
		
		
		public function setRenderer($rend){
			if($rend instanceof Renderers\ILayerRenderer){
				$rend->attachLayer($this);
				$this->renderer = $rend;
			}
		}

	}

	class Selection{
		protected $gdImage;
		protected $subImage;
		protected $offsetX, $offsetY, $sizeX, $sizeY;
		protected $offsetXorig, $offsetYorig, $sizeXorig, $sizeYorig;
		protected $filterX,$paintX;
		
		public function __construct(&$image,$x,$y,$w,$h){
			$this->gdImage = &$image;
			$this->offsetX = $x;
			$this->offsetY = $y;
			$this->sizeX   = $w;
			$this->sizeY   = $h;
			$this->copyOriginalSelectionDimensions();
			$this->filterX = new Filters\PHPFilters($this->subImage);
			$this->paintX = new PaintTools\DefaultTools($this->subImage);
		}
		
		public function __destruct(){
			if($this->subImage !== null){
				imagedestroy($this->subImage);
			}
		}
		
		public function __get($v){
			if($v=="filter") {
				$this->transformationStart();
				return $this->filterX;
			}else if($v=="paint") {
				$this->transformationStart();
				return $this->paintX;
			}
		}
		
		protected function createSubImage(){
			$this->subImage = imagecreatetruecolor($this->sizeX,$this->sizeY);
			imagealphablending ($this->subImage,false);
			imagecopy($this->subImage,$this->gdImage,0,0,$this->offsetX,$this->offsetY,$this->sizeX,$this->sizeY);
			$this->filterX->updateGDSource($this->subImage);
		}
		protected function blankSourceSelectionRect(){
			imagealphablending ($this->gdImage,false);
			imagefilledrectangle ($this->gdImage,
				$this->offsetXorig, $this->offsetYorig,
				$this->offsetXorig+$this->sizeXorig-1, $this->offsetYorig+$this->sizeYorig-1,
				0x7F000000);
			imagealphablending ($this->gdImage,true);
		}
		protected function applySubImage(){
			imagecopy($this->gdImage,$this->subImage,$this->offsetX,$this->offsetY,0,0,$this->sizeX,$this->sizeY);
		}
		
		protected function transformationStart(){
			if($this->subImage === null){
				$this->createSubImage();
			}
		}
		protected function transformationEnd(){
			if($this->subImage !== null){
				$this->blankSourceSelectionRect();
				$this->applySubImage();
				imagedestroy($this->subImage);
				$this->subImage = null;
				$this->copyOriginalSelectionDimensions();
			}
		}
		protected function copyOriginalSelectionDimensions(){
			$this->offsetXorig = $this->offsetX;
			$this->offsetYorig = $this->offsetY;
			$this->sizeXorig   = $this->sizeX;
			$this->sizeYorig   = $this->sizeY;
		}
		
		public function fill($color){
			imagealphablending ($this->subImage,true);
			imagefilledrectangle ($this->subImage,0,0,$this->sizeX-1, $this->sizeY-1,$color);
		}
		public function floodFill($x,$y,$color){
			imagealphablending ($this->subImage,true);
			imagefill ($this->subImage,$x,$y,$color);
		}	

		public function fillOverwrite($color){
			imagealphablending ($this->subImage,false);
			imagefilledrectangle ($this->subImage,0,0,$this->sizeX-1, $this->sizeY-1,$color);
			imagealphablending ($this->subImage,true);
		}
		public function floodFillOverwrite($x,$y,$color){
			imagealphablending ($this->subImage,false);
			imagefill ($this->subImage,$x,$y,$color);
			imagealphablending ($this->subImage,true);
		}
		

		
		/* transformations */
		public function move($x,$y){
			$this->transformationStart();
			$this->offsetX = $x;
			$this->offsetY = $y;
		}
		public function moveOffset($ox,$oy){
			$this->transformationStart();
			$this->offsetX += $ox;
			$this->offsetY += $oy;
		}
		public function resize($w,$h){
			$this->transformationStart();
			$newSubImage = imagecreatetruecolor($w,$h);
			imagecopyresampled($newSubImage, $this->subImage,0,0,0,0,$w,$h,$this->sizeX,$this->sizeY);
			imagedestroy($this->subImage);
			$this->subImage = $newSubImage;
			$this->sizeX=$w;
			$this->sizeY=$h;
		}
		/* PHP5.5+ */
		public function rotate($degrees){
			if(!GDIMAGE_SUPPORTS_AFFINE) throw new RuntimeException("rotate function requires imageaffine support from PHP 5.5+");
			$this->transformationStart();
			$sind = sin($degrees/180*M_PI);
			$cosd = cos($degrees/180*M_PI);
			
			$newSubImage=imageaffine ($this->subImage,array($cosd,$sind,-$sind,$cosd,0,0));
			
			$this->offsetX = $this->offsetX + $this->sizeX/2 - imagesx($newSubImage)/2;
			$this->offsetY = $this->offsetY + $this->sizeY/2 - imagesy($newSubImage)/2;
			$this->sizeX = imagesx($newSubImage);
			$this->sizeY = imagesy($newSubImage);
			imagedestroy($this->subImage);
			$this->subImage = $newSubImage;
		}
		
		// creates a Clip object with content of selection
		public function copyClip(){
			$this->transformationStart();
			return new Clip($this->subImage);
			
		}
		
		public function pasteClip($clip,$x=0,$y=0){
			$clipImg = $clip->getContents();
			$this->transformationStart();
			imagecopy($this->subImage,$clipImg,$x,$y,0,0,imagesx($clipImg),imagesy($clipImg));
		}
		
		public function apply(){
			$this->transformationEnd();
		}
	}

	class Clip {
		protected $image;
		public function __construct($gdImage){
			$img = imagecreatetruecolor(imagesx($gdImage),imagesy($gdImage));
			imagecopy($img,$gdImage,0,0,0,0,imagesx($gdImage),imagesy($gdImage));
			$this->image = $img;
		}
		public function __destruct(){
			imagedestroy($this->image);	
		}
		public function getContents(){
			return $this->image;		
		}
	}
}

namespace N14\GDWrapper\Composers{
	use N14\GDWrapper as GDW;
	class DefaultComposer{
		protected $layers;
		protected $image;
		
		public function __construct($image){
			$this->image = $image;
			
		}
		
		public function fillLayers($layers){
			$this->layers = $layers;
		}
		
		public function preprocessLayer($layerObj){
			if($layerObj->renderer != null && $layerObj->renderer instanceof GDW\Renderers\ILayerRenderer){
				$layerObj->renderer->apply();
			}
		}
		public function mergeAll(){
			foreach($this->layers as $layer){
				$this->preprocessLayer($layer);
			}
			$imgSize = $this->image->getSize();
			$bgLayer = new GDW\Layer($imgSize['w'],$imgSize['h'],0,0);
			$bgLayer->clear();

			array_unshift($this->layers,$bgLayer);
			
			while(count($this->layers) > 1){
				$layerBottom = array_shift($this->layers);
				$layerTop = array_shift($this->layers);
				$newLayer = $this->mergeDown($layerTop,$layerBottom);
				array_unshift($this->layers,$newLayer);
			};
		
			return reset($this->layers);
		}

		public function mergeDown($layerTop, $layerBottom){
			$gdTop = $layerTop->getGDHandle();
			$gdBottom = $layerBottom->getGDHandle();
			
			$topDimensions = $layerTop->getLayerDimensions();
			$bottomDimensions = $layerBottom->getLayerDimensions();
			$imgSize = $this->image->getSize();
			
			$newLayerGD = imagecreatetruecolor($imgSize['w'],$imgSize['h']);
			imagefill($newLayerGD,0,0,0x7f000000);
			
			imagecopy($newLayerGD, $gdBottom, 
				$bottomDimensions['x'],$bottomDimensions['y'], 
				0, 0, 
				$bottomDimensions['w'],$bottomDimensions['h']); 
				
				
			self::mergeWithOpacity($newLayerGD,$gdTop,
				$topDimensions['x'],$topDimensions['y'],
				0,0,
				$topDimensions['w'],$topDimensions['h'],
				$layerTop->getOpacity()
				);
				
			$newLayer = new GDW\Layer($newLayerGD);
			return $newLayer;
		}
		
		// like imagecopy, but with opacity control
		// $op: 0-transparent, 100-opaque
		static function mergeWithOpacity($dst_im,$src_im,$dst_x,$dst_y,$src_x,$src_y,$src_w,$src_h,$op){
			$op=\iclamp($op,0,100);
			$dstImgW = imagesx($dst_im);
			$dstImgH = imagesy($dst_im);
			
			imagealphablending ($dst_im,true);
			if($op==100){
				imagecopy($dst_im,$src_im,$dst_x,$dst_y,$src_x,$src_y,$src_w,$src_h); // native equivalent
			}else if($op==0){
			
			}else{
				$opFracP = $op / 100;
				$opFracN = 1 - $opFracP;
				
				$startX = $dst_x < 0 ? -$dst_x : 0;
				$startY = $dst_y < 0 ? -$dst_y : 0;
				$endX = $dst_x + $src_w > $dstImgW ? $dstImgW - $dst_x : $src_w;
				$endY = $dst_y + $src_h > $dstImgH ? $dstImgH - $dst_y : $src_h;
				
				for($y = $startY; $y<$endY; $y++){
					for($x = $startX; $x<$endX; $x++){
						$srcPixX = $src_x + $x; $srcPixY = $src_y + $y;
						$dstPixX = $dst_x + $x; $dstPixY = $dst_y + $y;
						$pixSrc = imagecolorat($src_im,$srcPixX,$srcPixY);
						$pixDst = imagecolorat($dst_im,$dstPixX,$dstPixY);
						
						$srcO = (($pixSrc>>24)&0x7F);
						$srcO = (int)(127-(127-$srcO) * $opFracP);
						
						imagesetpixel($dst_im,$dstPixX,$dstPixY,($pixSrc & 0xFFFFFF) | ($srcO << 24));
					}
				}
			}
		}
	}

	class TiledComposer extends DefaultComposer{
		public function mergeAll(){
			foreach($this->layers as $layer){
				$this->preprocessLayer($layer);
			}
			$imgSize = $this->image->getSize();
			$bgLayer = new Layer($imgSize['w'],$imgSize['h'],0,0);
			$bgLayer->clear();
			$bgGD = $bgLayer->getGDHandle();
			//imagealphablending($bgGD,false);
			
			$layersCount = count($this->layers);
			
			$layersGridSize = ceil(sqrt($layersCount));
			$tileWidth = $imgSize['w'] / $layersGridSize;
			$tileHeight = $imgSize['h'] / $layersGridSize;
			
			$x=0; $y=0;
			
			foreach($this->layers as $layer){
				
				$layerGD = $layer->getGDHandle();
				$layerDim = $layer->getLayerDimensions();
				
				$layerGlobalX = $x * $tileWidth + $layerDim['x'] / $layersGridSize;
				$layerGlobalY = $y * $tileHeight + $layerDim['y'] / $layersGridSize;
				$layerGlobalW = $layerDim['w'] / $layersGridSize;
				$layerGlobalH = $layerDim['h'] / $layersGridSize;
				
				imagecopyresampled($bgGD, $layerGD, 
					$layerGlobalX, $layerGlobalY, 0, 0,
					$layerGlobalW, $layerGlobalH, $layerDim['w'], $layerDim['h']
				);
				
				imagestring($bgGD,5,$layerGlobalX+3,$layerGlobalY+$layerGlobalH - 16,$layer->name, 0x000000);
				imagestring($bgGD,5,$layerGlobalX+2,$layerGlobalY+$layerGlobalH - 17,$layer->name, 0xFFFFFF);
				
				$x++;
				if($x >= $layersGridSize) {
					$x=0; $y++;
				}
			}
			
			for($i = 1; $i < $layersGridSize; $i++){
				imageline($bgGD,$i*$tileWidth,0,$i*$tileWidth,$imgSize['h'], 0xFF0000);
				imageline($bgGD,0,$i*$tileHeight,$imgSize['w'],$i*$tileHeight, 0xFF0000);
			}
			
			
			imagesavealpha($bgGD,true);
			return $bgLayer;
		}
	}
}

namespace N14\GDWrapper\Filters{
	abstract class FiltersAbstract{
		protected $destLayer;
		protected $destGD;
		
		public function __construct($layerObj){
			$this->attachToLayer($layerObj);
		}
		
		public function attachToLayer($layerObj){
			$this->destLayer = $layerObj;
			$this->destGD = $layerObj->getGDHandle();
		}
	}
	
	class PHPFilters extends FiltersAbstract{


		public final function gdFilter(){
			$args = func_get_args();
			array_unshift($args,$this->destGD);
			call_user_func_array("imagefilter", $args);
		}
		
		public function updateGDSource($gdResource){
			$this->destGD = &$gdResource;
		}
		
		
		/* filter defs */
		public function invert(){
			$this->gdFilter(IMG_FILTER_NEGATE);
		}
		public function grayscale(){
			$this->gdFilter(IMG_FILTER_GRAYSCALE);
		}
		public function brightness($level){
			$level = \iclamp($level, -255, 255);
			$this->gdFilter(IMG_FILTER_BRIGHTNESS,$level);
		}
		public function contrast($level){
			$level = \iclamp($level, -100, 100);
			$this->gdFilter(IMG_FILTER_CONTRAST,-$level);
		}
		// don't trust the php docs on this one - no IMG_FILTER_GRAYSCALE involved
		public function colorize($addR=0,$addG=0,$addB=0,$addA=0){
			$addR = \iclamp($addR, -255, 255);
			$addG = \iclamp($addG, -255, 255);
			$addB = \iclamp($addB, -255, 255);
			$addA = \iclamp($addA, 0, 127);
			$this->gdFilter(IMG_FILTER_COLORIZE,$addR,$addG,$addB,$addA);
		}
		public function edge(){
			$this->gdFilter(IMG_FILTER_EDGEDETECT);
		}
		public function emboss(){
			$this->gdFilter(IMG_FILTER_EMBOSS);
		}
		public function blur(){
			$this->gdFilter(IMG_FILTER_GAUSSIAN_BLUR);
		}
		public function blurSelective(){
			$this->gdFilter(IMG_FILTER_SELECTIVE_BLUR);
		}
		public function sketch(){
			$this->gdFilter(IMG_FILTER_MEAN_REMOVAL);
		}
		public function smooth($weight=1.0){
			$this->gdFilter(IMG_FILTER_SMOOTH,$weight);
		}
		public function pixelate($size=2,$advanced=false){
			$this->gdFilter(IMG_FILTER_PIXELATE,$size,$advanced);
		}
	}
}

namespace N14\GDWrapper\PaintTools{
	define('GDRECT_BORDER', 1);
	define('GDRECT_FILLED', 2);
	define('GDRECT_FILLEDBORDER', GDRECT_BORDER|GDRECT_FILLED);
	define('GDALIGN_LEFT', 0);
	define('GDALIGN_CENTER', 1);
	define('GDALIGN_RIGHT', 2);

	abstract class ToolsAbstract{
		protected $destLayer;
		protected $destGD;
		
		public function __construct($layerObj){
			$this->attachToLayer($layerObj);
		}
		
		public function attachToLayer($layerObj){
			$this->destLayer = $layerObj;
			$this->destGD = $layerObj->getGDHandle();
		}
	}

	class DefaultTools extends ToolsAbstract{
		
		public $alphaBlend = false;
		public $antiAlias = false;
		public $lineColor = 0xFFFFFF;
		public $borderColor = 0xFF0000;
		public $lineSize = 1;
		
		
			
		// PAINT FUNCTIONS
		public function pixel($x, $y, $color=null){
			$this->setDrawingConfig();
			imagesetpixel($this->destGD, $x, $y, $this->c($color));
		}
		public function line($x1, $y1, $x2, $y2, $color=null){
			$this->setDrawingConfig();
			
			imageline ($this->destGD, $x1, $y1, $x2, $y2, $this->c($color));
		}
		
		public function rectangle($x1, $y1, $x2, $y2, $type=GDRECT_BORDER, $colorBorder=null, $colorFill=null){
			$this->setDrawingConfig();
			if($type & GDRECT_FILLED){
				$crop = 0; //ceil($this->lineSize/2);
				
				imagefilledrectangle ($this->destGD, $x1+$crop, $y1+$crop, $x2-$crop-1, $y2-$crop-1, $this->c($colorFill));
			}
			if($type & GDRECT_BORDER){
				imagerectangle ($this->destGD, $x1, $y1, $x2, $y2, $this->b($colorBorder));
			}
		}
		
		public function rectangleBox($box, $type=GDRECT_BORDER, $colorBorder=null, $colorFill=null){
			$this->rectangle($box['x'], $box['y'], $box['x']+$box['w'], $box['y']+$box['h'], $type, $colorBorder, $colorFill);
		}
		
		public function polygon($verts, $type=GDRECT_BORDER, $colorBorder=null, $colorFill=null){
			$this->setDrawingConfig();
			$gdVerts=array();
			foreach($verts as $v){
				$gdVerts[]=$v[0];
				$gdVerts[]=$v[1];
			}
			$gdVertsCount = count($verts);
			
			if($type & GDRECT_FILLED){
				imagefilledpolygon ($this->destGD, $gdVerts, $gdVertsCount, $this->c($colorFill));
			}
			if($type & GDRECT_BORDER){
				imagepolygon ($this->destGD, $gdVerts, $gdVertsCount, $this->b($colorBorder));
			}
		}
		
		
		public function textBM($x,$y,$text,$font=3,$color=null){
			$this->setDrawingConfig();
			imagestring($this->destGD,$font,$x,$y,$text,$this->c($color));
		}
		
		public function loadBMFont($fontFile){
			return imageloadfont($fontFile);
		}
		
		public function textGetBox($x,$y,$text,$params=array()){
			$angle = isset($params['angle']) ? $params['angle'] : 0;
			$font = isset($params['font']) ? $params['font'] : __DIR__."/GDWrapper/Fonts/tahoma.ttf";
			$align = isset($params['align']) ? $params['align'] : GDALIGN_LEFT;
			$size = isset($params['size']) ? $params['size'] : 12;
			if(file_exists($font)){
				$box = imagettfbbox($size, $angle, $font, $text);
			}else{
				$fallbackFontW = imagefontwidth(2);
				$fallbackFontH = imagefontheight(2);
				$box = array(0, $fallbackFontH, $fallbackFontW, $fallbackFontH, $fallbackFontW, 0, 0, 0);
			}
			$w = $box[2] - $box[0];
			$h = $box[1] - $box[7];
			return array(
				'x'=>$x - $w*$align/2,
				'y'=>$y,
				'w'=>$w,
				'h'=>$h
			);
		}
		
		public function text($x,$y,$text,$params=array(),$color=null){
			$this->setDrawingConfig();
			$angle = isset($params['angle']) ? $params['angle'] : 0;
			$font = isset($params['font']) ? $params['font'] : __DIR__."/GDWrapper/Fonts/tahoma.ttf";
			$align = isset($params['align']) ? $params['align'] : GDALIGN_LEFT;
			$size = isset($params['size']) ? $params['size'] : 12;
			if(file_exists($font)){
				$box = imagettfbbox($size, $angle, $font, $text);
			}else{
				$fallbackFontW = imagefontwidth(2);
				$fallbackFontH = imagefontheight(2);
				$box = array(0, $fallbackFontH, $fallbackFontW, $fallbackFontH, $fallbackFontW, 0, 0, 0);
			}
			$w = $box[2] - $box[0];
			$newX = $x - $box[6] - $w * $align / 2;
			$newY = $y - $box[7];
			$this->setDrawingConfig();
			
			if(file_exists($font)){
				if(isset($params['shadow']) && $params['shadow']==true){
					imagettftext($this->destGD,$size,$angle,$newX+1,$newY+1,0x000000,$font,$text);
				}
				imagettftext($this->destGD,$size,$angle,$newX,$newY,$this->c($color),$font,$text);
			}else{
				$this->textBM($x,$y,$text,2,$this->c($color));
			}
		}
		
		// MISC
		protected function setDrawingConfig(){
			imagealphablending ($this->destGD,$this->alphaBlend);
			imageantialias ($this->destGD, $this->lineSize > 1 ? false : $this->antiAlias);
			imagesetthickness ($this->destGD,$this->lineSize);
		}
		protected function c($color){
			return $color===null?$this->lineColor:$color;
		}
		protected function b($color){
			return $color===null?$this->borderColor:$color;
		}
	}
}

namespace N14\GDWrapper\Renderers{
	interface ILayerRenderer{
		public function attachLayer($layerObj);
		public function apply();

	}
}


namespace{

	function bclamp($v){
		return min(max((int)$v,0),255);
	}
	function iclamp($v,$min,$max){
		return min(max((int)$v,$min),$max);
	}
}
 