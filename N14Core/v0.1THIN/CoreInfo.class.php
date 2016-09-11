<?php

	namespace N14;
	require_once "INICache.class.php";
	
	class CoreInfo{
		protected $pieces = array();
		protected $versionINI;
		static $instance = null;
		
		public function __construct(){
			self::$instance = $this;
			$this->versionINI = new INICache(__DIR__ . "/Version.ini");
			$this->versionINI->silentCreation = true;
		}
		
		public static function registerPuzzlePiece($name, $data){
			self::$instance->__registerP($name, $data);
		}
		public static function &getPieceData($name){
			return self::$instance->__getData($name);
		}
		public static function getPiecesList(){
			return self::$instance->__getPieces();
		}
		public static function getVersionInfoProperty($prop){
			return self::$instance->__getVersionInfoProperty($prop);
		}
		
		public function __registerP($name, $data){
			$this->pieces[$name]=$data;
		}
		public function &__getData($name){
			return $this->pieces[$name];
		}
		public function __getPieces(){
			return array_keys($this->pieces);
		}
		public function __getVersionInfoProperty($prop){
			return $this->versionINI["CoreVersionInfo." . $prop];
		}
	}
	
	$__n14internal_coreInfo=new CoreInfo;
	
?>