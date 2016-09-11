<?php
/* Nemo PHP LameLog Class
 * 
 * 2014 namonaki14
 * 
 * [insert licensing trash here]
 * 
 * Changelog:
 * 
 * '14-xx-xx 001 Created
 * 
 */
 
namespace N14;

require_once __DIR__.'/../ILog.class.php';
$moduleClassName = "N14\LameLog";
$moduleName = "Nemo PHP LameLog Class";
$moduleVersion = "0.1.001";

// File: "G:\PHPTrash\N14Core\v0.1\Modules\LameLog.mod.php"
// Signed: 18-12-2015 12:06:13
$moduleSignature = "376272f0922dc57aace4de81e956414b86e8c9519f561319041961e131b6e67c64d5fbc843cc2ebeadf76cf9289acea6c5dfdb46533c4315090c74798320b2b5";

/*------------------------*\
        N14\LameLog
\*------------------------*/

class LameLog extends Module implements ILog{
	public function init(){
		if(php_sapi_name() != "cli"){
			header("Content-type: text/plain; charset=utf-8");
		}
		$master = $this->__getMaster();
		$master->registerEventHandler("LogWrite", array($this,'writeln'));
	}
	public function isRelevant(){
		return true;
	}
	public function cleanup(){
	
	}
	
	public function write($string, $stream=ILog::LOG_OUT){
		echo $string;
	}
	public function newLine(){
		echo "\r\n";
	}
	public function writeln($string, $stream=ILog::LOG_OUT){
		$this->write($string, $stream);
		$this->newLine();
	}
}

 
?>