<?php
/* PHP Binary structure reader
 * 
 * 2015 namonaki14
 * 
 * [insert licensing trash here]
 * 
 * Changelog:
 * 
 * '15-02-26 Created
 * 
 */

	class StructReader{
		protected $fields=array();
		protected $fileHandle=false;
		const FIELD_INT8=1;
		const FIELD_INT16=2;
		const FIELD_INT32=3;
		
		const FIELD_UINT8=129;
		const FIELD_UINT16=130;
		const FIELD_UINT32=131;
		
		const FIELD_FLOAT=4;
		const FIELD_DOUBLE=5;
		
		const FIELD_STRING=6;
		
		const FIELD_CHAR=1;
		const FIELD_SHORT=2;
		const FIELD_LONG=3;	
		const FIELD_WORD=2;
		const FIELD_DWORD=3;	
		
		public function addField($name,$type,$size=1){
			$this->fields[] = self::generateField($name,$type,$size);
		}
		
		public function __construct($fileHandle=false){
			$this->fileHandle = $fileHandle;
		}
		
		/* for more complex structures (dynamic) 
		 * you might override this method with your own list of
		 * readField and readMultiElementField calls
		 */
		public function read($fileHandle = false){
			if($fileHandle!==false) $this->fileHandle = $fileHandle;
			if($this->fileHandle===false) 
				throw new Exception("Invalid file handle");
				
			$result = array();
			$tempBuffer = "";
			foreach($this->fields as $structField){
				if(is_numeric($structField['type']) && $structField['type'] == self::FIELD_STRING){
					$buffer = "";
					$startPos = ftell($this->fileHandle);
					do{
						$buffer .= $this->safeFread(256);
					}while(($nullcharPos = strpos($buffer,"\0")) === false);
					fseek($this->fileHandle, $startPos+$nullcharPos+1, SEEK_SET);
				}else{
					if(is_string($structField['size']) && strpos($structField['size'],"field:")===0){
						$refFieldName = substr($structField['size'],6);
						$fieldCopy=$structField;
						$fieldCopy['size'] = $result[$refFieldName];
						$result[$structField['name']] = $this->readMultiElementField($fieldCopy);
					}else if(is_string($structField['size']) && strpos($structField['size'],"ifnonzero:")===0){
						$refFieldName = substr($structField['size'],10);
						if($result[$refFieldName] != 0) {
							$result[$structField['name']] = $this->readField($structField);
						}
					}else if($structField['size']!=1){
						$result[$structField['name']] = $this->readMultiElementField($structField);
					}else{
						$result[$structField['name']] = $this->readField($structField);
					}
					
				}
			}
			return $result;
		}
		
		public function readMulti($length, $fileHandle = false){
			$result = array();
			for($i=0; $i<$length; $i++){
				$result[$i] = $this->read($fileHandle);
			}
			return $result;
		}
		
		protected function readField($field){
			try{
				return $this->readChunk($field['type']);
			}catch(Exception $e){
				throw new Exception("'{$e->getMessage()}' while reading '{$field['name']}'",0,$e);
			}
			
		}
		
		protected function readMultiElementField($field){
			$result = array();
			for($i=0; $i<$field['size']; $i++){
				$result[$i] = $this->readField($field);
			}
			return $result;
		}
		
		protected function readChunk($type){
			if($type instanceof StructReader){
				return $type->read($this->fileHandle);
			}else{
				$packType = self::getEquivalentPackFormat($type);
				$elementSize = self::getSizeOf($type);
				$binBuf=$this->safeFread($elementSize);
				if(strlen($binBuf) < $elementSize){
					throw new Exception("failed reading $elementSize bytes: got ".strlen($binBuf));
				}
				return unpack($packType,$binBuf)[1];
			}
		}
		
		protected function safeFread($size){
			$result=@fread($this->fileHandle,$size);
			if($result===false){
				if(feof($this->fileHandle)){
					throw new Exception("read beyond eof: ".ftell($this->fileHandle)." readsize:".$size."");
				}else{
					throw new Exception("fread native error: ".error_get_last()['message']);
				}
			}
			return $result;
		}
		
		protected static function getEquivalentPackFormat($type){
			$packType="x";
			switch($type){
				case self::FIELD_INT8:
				$packType = "c";
				break;
				case self::FIELD_INT16:
				$packType = "s";
				break;
				case self::FIELD_INT32:
				$packType = "l";
				break;
				case self::FIELD_UINT8:
				$packType = "C";
				break;
				case self::FIELD_UINT16:
				$packType = "v";
				break;
				case self::FIELD_UINT32:
				$packType = "V";
				break;
				case self::FIELD_FLOAT:
				$packType = "f";
				break;
				case self::FIELD_DOUBLE:
				$packType = "d";
				break;
				case self::FIELD_STRING:
				$phpVer = explode(".",phpversion());
				if(($phpVer[0] == 5 && $phpVer[1] >= 5) || $phpVer[0] > 5){ // http://php.net/manual/en/function.unpack.php#refsect1-function.unpack-changelog
					$packType = "Z";
					}else{ // before 5.5.0
					$packType = "a";
				}
				break;
				
			}
			return $packType;
		}
		
		protected static function getSizeOf($type){
			switch($type){
				case self::FIELD_INT8:
				case self::FIELD_UINT8:
					return 1;
				case self::FIELD_INT16:
				case self::FIELD_UINT16:
					return 2;
				case self::FIELD_INT32:
				case self::FIELD_UINT32:
				case self::FIELD_FLOAT:
					return 4;
				case self::FIELD_DOUBLE:
					return 8;
				default:
			
			}
		}
		
		protected static function generateField($name,$type,$size=1){
			return array("name"=>$name,"type"=>$type,"size"=>$size);
		}
		
	}
?>