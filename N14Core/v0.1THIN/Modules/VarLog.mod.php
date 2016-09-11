<?php
/* Nemo PHP VarLog Class
 * 
 * 2014 namonaki14
 * 
 * [insert licensing trash here]
 * 
 * Changelog:
 * 
 * '15-xx-xx 001 Created
 * 
 */
 
namespace N14;

require_once __DIR__.'/../ILog.class.php';

$moduleClassName = "N14\VarLog";
$moduleName = "Log to variable";
$moduleVersion = "0.1.001";

// File: "G:\PHPTrash\N14Core\v0.1\Modules\VarLog.mod.php"
// Signed: 18-12-2015 12:06:58
$moduleSignature = "109f4bc64328029352442f4f979c344d694d983cac0971c20fc06a918a3acf632ac2fdd9f1315e49fde4d96ae2f9ff33787ce98cf9c193c10f2e59f3af059bfd";

/*------------------------*\
        N14\VarLog
\*------------------------*/

class VarLog extends Module implements ILog{
	public $logVar = null;
	public $doEcho;
	protected $start;
	public function init(){
		$this->logVar = &$GLOBALS['VarLogContent'];
		$master = $this->__getMaster();
		$master->registerEventHandler("LogWrite", array($this,'writeEventHandler'));
		if($this->logVar == null) $this->logVar = "";
		$this->doEcho = php_sapi_name() == "cli";
		$this->start = microtime(true);
	}
	public function isRelevant(){
		return true;
	}
	public function cleanup(){
	
	}
	
	public function write($string, $stream=ILog::LOG_OUT){
		$this->logVar .= $string;
		if($this->doEcho) echo $string;
	}
	public function newLine(){
		$this->logVar .= "\r\n";
		if($this->doEcho) echo "\r\n";
	}
	public function writeln($string, $stream=ILog::LOG_OUT){
		$this->write($string, $stream);
		$this->newLine();
	}
	
	protected function color($col){ // only for errorhandler.php
		if(function_exists('con_color')){
			con_color($col);
		}
	}
	
	public function writeEventHandler($string, $stream=ILog::LOG_OUT, $origin = null){
		if($origin==null){
			$bt = debug_backtrace();
			$cur = reset($bt);
			
			while(key($bt) !== null){
				if(isset($cur['function']) && $cur['function']=="raiseEvent") break;
				$cur = next($bt);			
			}
			
			$callerFrame = next($bt);
			if(key($bt)!==null && isset($callerFrame['class'])){
				$sourceName = $callerFrame['class'];
			}else{
				$sourceName = $callerFrame['file'].":".$callerFrame['line'];
			}
		}else{
			$sourceName = $origin;
		}
		$this->color(0xF);
		$this->write("[".sprintf("%08.5f",microtime(true)-$this->start)."] ");
		$this->color(0x5);
		$this->write($sourceName.": ");
		$this->color(0x7);
		$this->write($string);
		$this->newLine();
		
	}
}

 
?>