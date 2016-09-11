<?php
/* GDWNonOverlappingText
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
}

namespace N14\GDWrapper\Renderers{
	class NonOverlappingText implements ILayerRenderer {
		public $color = 0xFF0000;
		public $spacing = 1;
		protected $layer;
		protected $labelsList = array();
		
		public function write($x,$y,$text,$params=array(),$color=null){
			$newLabel = array('x'=>$x, 'y'=>$y, 'text'=>$text, 'params'=>$params, 'color'=>$color!==null?$color:$this->color);
			$this->labelsList[]=$newLabel;
		}
		
		public function attachLayer($layerObj){
			$this->layer = $layerObj;
		}
		public function apply(){
			$layerPainter = $this->layer->paint;
			foreach($this->labelsList as $labelId=>$label){
				$rect = $layerPainter->textGetBox($label['x'],$label['y'],$label['text'],$label['params']);
				$this->labelsList[$labelId]['w'] = $rect['w']+$this->spacing;
				$this->labelsList[$labelId]['h'] = $rect['h']+$this->spacing;
			}
			
			$this->spaceOutLabels();
			$layerPainter->borderColor = 0xFFFFFF;
			
			foreach($this->labelsList as $labelId=>$label){
				$layerPainter->text($label['x'],$label['y'],$label['text'],$label['params'],$label['color']);
				//$layerPainter->rectangle($label['x'],$label['y'],$label['x']+$label['w'],$label['y']+$label['h']);
			}
		}
		
		//based on: http://stackoverflow.com/a/3279877
		protected function spaceOutLabels(){
			$layerPainter = $this->layer->paint;
			
			while($this->areRectanglesIntersecting()){

				$intersections = $this->getSortedIntersectingRects();

				$label = $this->labelsList[$intersections[0]['labelid']];
				$labelOrig = $label;
				unset($this->labelsList[$intersections[0]['labelid']]);
			

				$rectsInt = $this->getIntersectionsForRectangle($label);


				$grpMinX = 0xFFFFFFFF;
				$grpMinY = 0xFFFFFFFF;
				$grpMaxX = 0;
				$grpMaxY = 0;
				
				array_push($rectsInt, $label);
				foreach($rectsInt as $rect){
					$grpMinX = min($grpMinX,$rect['x']);
					$grpMaxX = max($grpMaxX,$rect['x']/*+$rect['w']*/);
					$grpMinY = min($grpMinY,$rect['y']);
					$grpMaxY = max($grpMaxY,$rect['y']/*+$rect['h']*/);
				}
				array_pop($rectsInt);
				

				$grpCenterX = ($grpMinX + $grpMaxX) / 2;
				$grpCenterY = ($grpMinY + $grpMaxY) / 2;
				
				$rectCenterX = $label['x'] /* +$label['w']/2 */;
				$rectCenterY = $label['y'] /* +$label['h']/2 */;
				
				$vecX = $rectCenterX - $grpCenterX;
				$vecY = $rectCenterY - $grpCenterY;
				
				if($vecX==0 && $vecY==0){
					$vecY=1;
				}
				$div = max(abs($vecX), abs($vecY));
				

				
				$moveX = $vecX / $div;
				$moveY = $vecY / $div;
				
				while($this->checkIntersectionsForRectangle($label)){
					$label['x'] += $moveX;
					$label['y'] += $moveY;
					
				}
				$label['x'] = round($label['x']);
				$label['y'] = round($label['y']);
				
				$grpNewMinX = min($grpMinX,$label['x']);
				$grpNewMaxX = max($grpMaxX,$label['x']/*+$label['w']*/);
				$grpNewMinY = min($grpMinY,$label['y']);
				$grpNewMaxY = max($grpMaxY,$label['y']/*+$label['h']*/);
				
				$grpNewCenterX = ($grpNewMinX + $grpNewMaxX) / 2;
				$grpNewCenterY = ($grpNewMinY + $grpNewMaxY) / 2;
				
				$grpOffsetX = $grpNewCenterX - $grpCenterX;
				$grpOffsetY = $grpNewCenterY - $grpCenterY;
				
				foreach($rectsInt as $rectId=>$rect){
					$this->labelsList[$rectId]['x']-=$grpOffsetX;
					$this->labelsList[$rectId]['y']-=$grpOffsetY;
				}
				$label['x']-=$grpOffsetX;
				$label['y']-=$grpOffsetY;

				array_push($this->labelsList, $label);
				
			}

		}
		
		protected function areRectanglesIntersecting(){
			foreach($this->labelsList as $labelId=>$label){
				if($this->checkIntersectionsForRectangle($label)){
					return true;
				}
			}
			return false;
		}
		
		protected function getIntersectionsForRectangle($rect){
			$intersections = array();
			foreach($this->labelsList as $labelId=>$label){
				if($rect==$label) continue;
				if(self::checkIntersection($label, $rect)){
					$intersections[$labelId] = $label;
				}
			}
			return $intersections;
		}
		
		protected function checkIntersectionsForRectangle($rect){
			foreach($this->labelsList as $labelId=>$label){
				if($rect==$label) continue;
				if(self::checkIntersection($label, $rect)){
					return true;
				}
			}
			return false;
		}
		
		protected function getSortedIntersectingRects(){
			$intersections = array();
			foreach($this->labelsList as $labelId=>$label){
				$labelInt = $this->getIntersectionsForRectangle($label);
				if(!count($labelInt)) 
					continue;
				$intersections[] = array('labelid'=>$labelId,'intersections'=>count($labelInt));
			}
			usort($intersections, function($a, $b){
				if($a['intersections']>$b['intersections']) return -1;
				else if($a['intersections']<$b['intersections']) return 1;
				else return 0;
			});
			return $intersections;
		}
		
		protected static function checkIntersection($r1, $r2){
			return 
				max($r1['x'],$r2['x'])<min($r1['x']+$r1['w'],$r2['x']+$r2['w']) &&
				max($r1['y'],$r2['y'])<min($r1['y']+$r1['h'],$r2['y']+$r2['h']);
		}
	}
}