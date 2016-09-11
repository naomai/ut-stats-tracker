<?php
namespace N14;

function getModuleSignatureInfo($file){

	$moduleContent = file_get_contents($file);
	$valid = preg_match('#(.+)[\r\n\s]*// Signed: [0-9]+-[0-9]+-[0-9]+ [0-9]+:[0-9]+:[0-9]+\r?\n\s*\$moduleSignature\s*=\s*("|\')?([^"\']*)("|\')?\s*;[\r\n\s]*(.+)#s',$moduleContent,$mat); // with comment
	if(!$valid){
		$valid = preg_match('#(.+)[\r\n\s]*\$moduleSignature\s*=\s*("|\')?([^"\']*)("|\')?\s*;[\r\n\s]*(.+)#s',$moduleContent,$mat); // without
	}
	if(!$valid || $mat[2]!=$mat[4]) {
		return array('valid'=>false,'code'=>$moduleContent);
	}
	$oldSignature = $mat[3];
	$script = $mat[1].$mat[5]; // script with signature removed
	return array('valid'=>true,'code'=>$script,'signature'=>$oldSignature);
	
}


function validateModule($code,$signature){
	static $pubKey = null;
	if($pubKey===null){
		$pubKey=openssl_get_publickey(
"-----BEGIN PUBLIC KEY-----
MFwwDQYJKoZIhvcNAQEBBQADSwAwSAJBAIHxYLuZuzSJftiHENJuSJUGJhZwLi8h
/+Y1Wg4i0vYTuEr74ijZfEefP2lTio91BTFpLwthA7Xjv7el9gxnXZECAwEAAQ==
-----END PUBLIC KEY-----");
	}
	if(!ctype_xdigit ($signature)) return false;
	$signatureRaw = pack('H*', $signature);
	$sigStatus = openssl_verify($code, $signatureRaw, $pubKey,'sha256');
	return $sigStatus === 1;
}


?>