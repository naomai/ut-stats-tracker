<?php

namespace N14\GDWrapper\Filters{
	require_once __DIR__ . "/../GDWrapper.php";
	class ScrambleFilter extends FiltersAbstract{

		
		public function apply(){
			$w = imagesx($this->destGD);
			$h = imagesy($this->destGD);
			$srcCopy = imagecreatetruecolor($w,$h);
			imagecopy($srcCopy,$this->destGD,0,0,0,0,$w,$h);
			
			$rowMappings = $this->createRandomRowMappings();
			foreach($rowMappings as $rowId => $destRowId){

				$destRowId = $destRowId-round($h/9);
				if($destRowId<0) $destRowId += $h;

				
				imagecopy($this->destGD,$srcCopy,0,$destRowId,0,$rowId,$w,1);
			}
		}
		
		private function createRandomRowMappings(){
			$w = imagesx($this->destGD);
			$h = imagesy($this->destGD);
			$rowMappings = array_fill(0,$h,0);
			array_walk($rowMappings, function(&$val,$idx){ $val=$idx; });
			$shuffleRadius = round($h / 9);
			self::shuffleALittle($rowMappings, $shuffleRadius);
			return $rowMappings;
		}
		
		static function shuffleALittle(&$array,$limit=20){
			$count = count($array);
			$isShuffled = array_fill(0,$count,false);
			
			$rowsIdsToShuffle = array_fill( 0,$count-1,0);
			array_walk($rowsIdsToShuffle, function(&$val,$idx){ $val=$idx; });
			shuffle($rowsIdsToShuffle);
			$rowsIdsToShuffle = array_reverse ($rowsIdsToShuffle, true);
			
			foreach($rowsIdsToShuffle as $i){
				if($isShuffled[$i]) continue;
				
				for($attempts = 0; $attempts < $limit; $attempts++){
					$dest = ($i + mt_rand(1,$limit));
					if($dest>=$count) $dest = 2 * $count - $dest-1;
					if(!$isShuffled[$dest]) break;
					$dest = -1;
				}
				if($dest == -1) continue;
				
				$temp = $array[$i];
				$array[$i] = $array[$dest];
				$array[$dest] = $temp;
				$isShuffled[$i] = true;
				$isShuffled[$dest] = true;
			}
		}
	}
}
