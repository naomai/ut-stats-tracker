<?php
require_once "structurereader.php";

class UEStructReader extends StructReader{
	// engine types
	const FIELD_INDEX=0x100; // compact index
	const FIELD_UESTRING=0x101;
	// built-in types
	const FIELD_GUID=0x102;
	const FIELD_VECTOR=0x103;
	const FIELD_ROTATOR=0x104;
	const FIELD_COLOR=0x105;
	
	const FIELD_BOUNDINGBOX=0x106;
	const FIELD_BOUNDINGVOLUME=0x107;
	const FIELD_COORDS=0x108;
	const FIELD_PLANE=0x109;
	const FIELD_SCALE=0x10A;
	
	protected function readChunk($type){
		if($type instanceof StructReader){
			return $type->read($this->fileHandle);
		}
		switch($type){
			case self::FIELD_INDEX:
				return $this->readCompactIndex();
			case self::FIELD_UESTRING:
				return $this->readUEString();
				
			case self::FIELD_GUID:
				return $this->readGUID();
			case self::FIELD_VECTOR:
				return $this->readVector();
			case self::FIELD_ROTATOR:
				return $this->readRotator();
			case self::FIELD_COLOR:
				return $this->readColor();
				
			case self::FIELD_BOUNDINGBOX:
				return $this->readBoundingBox();
			case self::FIELD_BOUNDINGVOLUME:
				return $this->readBoundingVolume();
			case self::FIELD_COORDS:
				return $this->readCoords();
			case self::FIELD_PLANE:
				return $this->readPlane();
			case self::FIELD_SCALE:
				return $this->readScale();
				
				
			default:
				return parent::readChunk($type);
			
		}
	}
	
	protected function readCompactIndex(){
		$rawByte=0;
		$result=0;
		
		$shift=6;
		$more=null;
		
		$rawByte = ord(fgetc($this->fileHandle));
		// first: SIGN | MORE | 6-bit VALUE 
		// next: MORE | next 7 bits
		//$result |= ($rawByte & 0x3F) | (($rawByte & 0x80)<<24); 
		
		$sign = (bool)($rawByte & 0x80);
		
		$result |= ($rawByte & 0x3F); 
		$more = ($rawByte & 0x40);

		while($more && $shift < 32) {
			$rawByte = ord(fgetc($this->fileHandle));
			$result |= ($rawByte & 0x7F) << $shift;
			$more = ($rawByte & 0x80);
			$shift += 7;
		}
		if($sign) $result=-$result;
		return $result;
	}
	
	protected function readUEString(){
		$length = $this->readCompactIndex();
		if($length==0) return "";
		return strtok($this->safeFread($length),"\0");
	}
	protected function readGUID(){
		return bin2hex($this->safeFread(16));
	}
	protected function readVector(){
		$result = array();
		$result['X'] = $this->readChunk(StructureReader::FIELD_FLOAT);
		$result['Y'] = $this->readChunk(StructureReader::FIELD_FLOAT);
		$result['Z'] = $this->readChunk(StructureReader::FIELD_FLOAT);
		return $result;
	}
	protected function readRotator(){
		$result = array();
		$result['Pitch'] = $this->readChunk(StructureReader::FIELD_SHORT);
		$result['Yaw']	 = $this->readChunk(StructureReader::FIELD_SHORT);
		$result['Roll']	 = $this->readChunk(StructureReader::FIELD_SHORT);
		return $result;
	}
	protected function readColor(){
		$result = array();
		$result['R'] = $this->readChunk(StructureReader::FIELD_BYTE);
		$result['G'] = $this->readChunk(StructureReader::FIELD_BYTE);
		$result['B'] = $this->readChunk(StructureReader::FIELD_BYTE);
		$result['A'] = $this->readChunk(StructureReader::FIELD_BYTE);
		return $result;
	}

	protected function readBoundingBox(){
		$result = array();
		$result['Min']		= $this->readChunk(self::FIELD_VECTOR);
		$result['Max']		= $this->readChunk(self::FIELD_VECTOR);
		$result['IsValid']	= $this->readChunk(StructureReader::FIELD_BYTE);
		return $result;
	}
	protected function readBoundingVolume(){
		$result = $this->readChunk(self::FIELD_BOUNDINGBOX);
		$result['Sphere'] = $this->readChunk(self::FIELD_PLANE);
		return $result;
	}
	protected function readCoords(){
		$result = array();
		$result['Origin']	= $this->readChunk(self::FIELD_VECTOR);
		$result['XAxis']	= $this->readChunk(self::FIELD_VECTOR);
		$result['YAxis']	= $this->readChunk(self::FIELD_VECTOR);
		$result['ZAxis']	= $this->readChunk(self::FIELD_VECTOR);
		return $result;
	}
	protected function readPlane(){
		$result = $this->readChunk(self::FIELD_VECTOR);
		$result['W'] = $this->readChunk(StructureReader::FIELD_FLOAT);
		return $result;
	}
	protected function readScale(){
		$result = array();
		$result['Scale'] = $this->readChunk(self::FIELD_VECTOR);
		$result['SheerRate'] = $this->readChunk(StructureReader::FIELD_FLOAT);
		$result['SheerAxis'] = $this->readChunk(StructureReader::FIELD_BYTE);
		return $result;
	}
}

class UEPackageHeaderReader extends UEStructReader{ 
	public function addField($name,$type,$size=1){
		
		throw new Exception("This class doesn't support adding fields");
	}
	
	public function read($fileHandle = false){
		if($fileHandle!==false) $this->fileHandle = $fileHandle;
		if($this->fileHandle===false) 
			throw new Exception("Invalid file handle");
		
		$result = array();
		$tempBuffer = "";
		
		$result['magic']					= $this->readChunk(StructReader::FIELD_DWORD);
		$result['packageVersion']			= $this->readChunk(StructReader::FIELD_WORD);
		$result['licenseeVersion']			= $this->readChunk(StructReader::FIELD_WORD);
		
		$feat = getPackageFeatures($result['packageVersion']);
		
		$result['packageFlags']				= $this->readChunk(StructReader::FIELD_DWORD);
		$result['nameCount']				= $this->readChunk(StructReader::FIELD_DWORD);
		$result['nameOffset']				= $this->readChunk(StructReader::FIELD_DWORD);
		$result['exportCount']				= $this->readChunk(StructReader::FIELD_DWORD);
		$result['exportOffset']				= $this->readChunk(StructReader::FIELD_DWORD);
		$result['importCount']				= $this->readChunk(StructReader::FIELD_DWORD);
		$result['importOffset']				= $this->readChunk(StructReader::FIELD_DWORD);
		if($feat['heritage']){
			$result['heritageCount']		= $this->readChunk(StructReader::FIELD_DWORD);
			$result['heritageOffset']		= $this->readChunk(StructReader::FIELD_DWORD);
			
			$currentHeaderOffset = ftell($this->fileHandle);
			fseek($this->fileHandle,$result['heritageOffset']);
			
			$readerUEHeritageTableEntry = new UEStructReader();
			$readerUEHeritageTableEntry->addField("guid",UEStructReader::FIELD_GUID);
			$result['heritageItems']		= $this->readMultiElementField(StructReader::generateField("",$readerUEHeritageTableEntry,$result['heritageCount']));
			$result['guid'] = end($result['heritageItems'])['guid'];
			
			fseek($this->fileHandle,$currentHeaderOffset);
		}
		if($feat['guid']){
			$result['guid']					= $this->readChunk(UEStructReader::FIELD_GUID);
		}
		if($feat['generation']){
			$result['generationCount']		= $this->readChunk(StructReader::FIELD_DWORD);
			$readerUEGenerationInfoEntry = new UEStructReader();
			$readerUEGenerationInfoEntry->addField("exportCount",StructReader::FIELD_DWORD);
			$readerUEGenerationInfoEntry->addField("nameCount",StructReader::FIELD_DWORD);
			$result['generationItems']		= $this->readMultiElementField(StructReader::generateField("",$readerUEGenerationInfoEntry,$result['generationCount']));
		}
		return $result;
	}

}
/*
$readerUEPackageHeader = new UEStructReader();
$readerUEPackageHeader->addField("magic",StructReader::FIELD_DWORD);
$readerUEPackageHeader->addField("packageVersion",StructReader::FIELD_WORD);
$readerUEPackageHeader->addField("licenseeVersion",StructReader::FIELD_WORD);
$readerUEPackageHeader->addField("flags",StructReader::FIELD_DWORD);
$readerUEPackageHeader->addField("nameCount",StructReader::FIELD_DWORD);
$readerUEPackageHeader->addField("nameOffset",StructReader::FIELD_DWORD);
$readerUEPackageHeader->addField("exportCount",StructReader::FIELD_DWORD);
$readerUEPackageHeader->addField("exportOffset",StructReader::FIELD_DWORD);
$readerUEPackageHeader->addField("importCount",StructReader::FIELD_DWORD);
$readerUEPackageHeader->addField("importOffset",StructReader::FIELD_DWORD);
$readerUEPackageHeader->addField("guid",UEStructReader::FIELD_GUID);
*/

$readerUEPackageHeader = new UEPackageHeaderReader();

$readerUENames = new UEStructReader();
$readerUENames->addField("name",UEStructReader::FIELD_UESTRING);
$readerUENames->addField("nameFlags",StructReader::FIELD_DWORD);


$readerUEExport = new UEStructReader();
$readerUEExport->addField("classIdx",UEStructReader::FIELD_INDEX);
$readerUEExport->addField("super",UEStructReader::FIELD_INDEX);
$readerUEExport->addField("outer",StructReader::FIELD_DWORD);
$readerUEExport->addField("nameIdx",UEStructReader::FIELD_INDEX);
$readerUEExport->addField("eFlags",StructReader::FIELD_DWORD);
$readerUEExport->addField("eSize",UEStructReader::FIELD_INDEX);
$readerUEExport->addField("eOffset",UEStructReader::FIELD_INDEX,"ifnonzero:eSize");

	
$readerUEImport = new UEStructReader();
$readerUEImport->addField("packageIdx",UEStructReader::FIELD_INDEX);
$readerUEImport->addField("classIdx",UEStructReader::FIELD_INDEX);
$readerUEImport->addField("outer",StructReader::FIELD_DWORD);
$readerUEImport->addField("nameIdx",UEStructReader::FIELD_INDEX);


function getPackageFeatures($ver){
	$feat=array();
	$feat['names'] = true;
	$feat['export'] = true;
	$feat['import'] = true;
	$feat['heritage'] = $ver < 68;
	$feat['guid'] = $ver >= 68;
	$feat['generation'] = $ver >= 68;
	return $feat;
}

?>