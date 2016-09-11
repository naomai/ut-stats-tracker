<?php
/* Nemo PHP HTTPClient Class
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


$moduleClassName = "N14\HTTPClient";
$moduleName = "HTTP Client";
$moduleVersion = "0.1.001";
// File: "G:\PHPTrash\N14Core\v0.1\Modules\HTTPClient.mod.php"
// Signed: 18-12-2015 12:03:39
$moduleSignature = "451b28020c020535d432ff81138716cdc907970f094b6531ee256b2fdf94fdf4572b93c4c1b939ed698774801978e8c76215689df52ac380143e3a97adf30174";
 
/*------------------------*\
       N14\HTTPClient
\*------------------------*/

class HTTPClient extends Module{

	protected $curlContextForDomain=array();
	
	public function init(){
		$this->log("Init");
	}
	public function isRelevant(){
		return true;
	}
	public function cleanup(){
		foreach($this->curlContextForDomain as $key=>$curlHnd){
			curl_close($curlHnd);
			unset($this->curlContextForDomain[$key]);
		}
		
	}
	
	public function request($url,&$data=null,$followredir=false){
		$this->log("Requesting $url");
		$host=parse_url($url,PHP_URL_HOST);
		if(isset($this->curlContextForDomain[$host])){
			$ch=$this->curlContextForDomain[$host];
		}else{
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
			curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
			if($followredir) curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
			if($data!==null && isset($data['post'])){
				curl_setopt($ch, CURLOPT_POST, true);
				curl_setopt($ch, CURLOPT_POSTFIELDS, $data['post']);
			}
			curl_setopt($ch, CURLOPT_FORBID_REUSE, false);
			curl_setopt($ch, CURLOPT_HTTPHEADER,array(
				"Accept-language: pl" ,
				"Accept: text/html,application/xhtml+xml,application/xml;q=0.9,text/plain;q=0.4"
			));
			
			if($data['spoof']){
				$UA = "Mozilla/5.0 (Windows NT 10.0; rv:45.0) Gecko/20100101 Firefox/45.0";
			}else{
				$UA = "Mozilla/5.0 (Windows NT 10.0) Namonaki14/HTTPClientMod";
				if(isset($GLOBALS['n14AppConfig'])){
					$cfg = $GLOBALS['n14AppConfig'];
					$UA .= " [N14App: ".\prettyArray($cfg,"Name","FullName")." (+" . $cfg['Url'] . ")]";
				}
				//$this->log("UA: $UA");
			}
			
			curl_setopt($ch,CURLOPT_USERAGENT,$UA); 
			$cookfile = __DIR__ . "/../cookies/".self::getTldFromUrl($url).".txt";
			curl_setopt($ch, CURLOPT_COOKIEJAR, $cookfile);
			curl_setopt($ch, CURLOPT_COOKIEFILE, $cookfile);
			
			$this->curlContextForDomain[$host]=$ch;
		}
		curl_setopt($ch, CURLOPT_URL, $url);
		$GLOBALS['debugCurlLastUrl'] = $url;
		$tx=curl_exec($ch);
		$data=curl_getinfo($ch);
		return $tx;
	}
	
	
	public static function getTldFromUrl($url){
		$hst=parse_url ($url, PHP_URL_HOST);
		$x=explode(".",$hst);
		if(count($x)>1){
			return $x[count($x)-2].".".$x[count($x)-1];
		}
		return $hst;
	}
	
}

 
?>