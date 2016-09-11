<?php

// namo's encryption thing
// IT'S PROBABLY A VERY WEAK ENCRYPTION SO DON'T USE IT
// FOR SENSITIVE DATA

namespace N14\Encrypt{

	function crypt($str,$key,$prettyPrint=false){

		$pk=prepareKey($key);
		$sl=strlen($str);
		$kl=strlen($key);
		for($i=0; $i<$sl;$i++){
			$str[$i] = chr(ord($str[$i]) ^ ord($pk[$i%$kl]));
		}
		return ($prettyPrint?strHex($str,true):$str);
	}

	function prepareKey($key){
		$kseq=array();
		$klen=strlen($key);
		
		for($i=0; $i<$klen; $i++){
			$kseq[$i] = $i & 255;
		}
		
		$prevkc=0;
		$prevmp=0;
		for($i=0; $i<$klen; $i++){
			$kc=ord($key[$i]) ^ $prevkc;
			$mischpos = abs(crc32($kc)^$prevmp) % $klen;
			$kseq[$mischpos] ^= (crc32($kc) >> 8) & 0xFF;
			if($mischpos<$klen-1)
			$kseq[$mischpos+1]^=crc32($kc) & 0xFF;
			else
			$kseq[0]^=crc32($kc) & 0xFF;
			$prevkc=$kc;
			$prevmp=$mischpos;
		}
		
		
		/*2*/
		$kfinal=array();
		$kflen=max(hibit($klen-1) << 1,8);
		$kops = $kflen << 2;
		$pk=0;
		for($i=0; $i<$kops; $i++){
			if(isset($kfinal[$i])){
				$orgpos = ($kfinal[$i%$kflen]&0xFF) % $klen;
				$pk = $kfinal[$i%$kflen] ^= $kseq[$orgpos] ^ (crc32($pk+$i)&0xFF);
				
			}else{
				$orgpos = ($kseq[$i%$klen]&0xFF) % $klen;
				$pk = $kfinal[$i%$kflen] = $kseq[$orgpos] ^ (crc32($pk+$i)&0xFF);
			}
		}
		
		$ok="";
		for($i=0; $i<$kflen; $i++){
			$ok.=chr($kfinal[$i]);
		}
		
		return $ok;
	}
	
	function hibit($n) { // http://stackoverflow.com/a/53184
		$n |= ($n >>  1);
		$n |= ($n >>  2);
		$n |= ($n >>  4);
		$n |= ($n >>  8);
		$n |= ($n >> 16);
		return $n - ($n >> 1);
	}
	
	function strHex($str,$linebreaks=false){
		$l=strlen($str)	;
		$o="";
		for($i=0; $i<$l;$i++){
			if($linebreaks && $i && $i % 35 == 0) $o.="\r\n";
			$o.=sprintf("%02X ",ord($str[$i]));
		}
		return trim($o);
	}
	
	function hexStr($str){
		$hx=preg_replace("/([^0-9A-F]*)/i","",$str);
		return pack("H*",$hx);
	}
	
	function strMoo($str,$linebreaks=false){
		static $dict = array("Moo!","Moo.","Moo?","Moo,");
		
		$l=strlen($str)	;
		$o="Moo! ";
		$mooCount=1;
		for($i=0; $i<$l;$i++,$mooCount+=4){
			$c=ord($str[$i]);
			if($linebreaks && $mooCount && $mooCount % 5 == 0) $o.="\r\n";
			$o.= $dict[($c>>6)&0x3] . " " . $dict[($c>>4)&0x3] . " " . $dict[($c>>2)&0x3] . " " . $dict[($c)&0x3] . " ";
		}
		return trim($o) . " Moo, Moo. Moo moo.";
	}
	
	function mooStr($str){
		static $dict = array("Moo!"=>0,"Moo."=>1,"Moo?"=>2,"Moo,"=>3,"Moo"=>256);
		$toks = explode(" ", $str);
		$c=0;
		$i=0;
		$o="";
		
		if(array_shift($toks)!=="Moo!"){
			throw new Exception("Invalid Moo Sequence");
		}
		
		foreach($toks as $t){
			if(isset($dict[trim($t)])){
				$c <<= 2;
				$c |= $dict[trim($t)];
				
				if($c>256) break;
				
				if(($i++) % 4 === 3){
					$o.=chr($c);
					$i = 0;
					$c = 0;
				}
			}
		}
		return $o;
	}

}

namespace{
	if ( basename(__FILE__) == basename($_SERVER["SCRIPT_FILENAME"]) ){
		echo "<link rel='stylesheet' href='http://www.mm.pl/~namonaki/n14assets/css/crap.css'><h1>n14enc crappy gui</h1>evil tool for evil people. if you're not namonaki14, go being not-evil somewhere else.<br>";
		if(isset($_POST['n14encCT']) && isset($_POST['n14encK'])){
			if(strpos($_POST['n14encCT'],"Moo! ")===0){
				$str=N14\Encrypt\mooStr($_POST['n14encCT']);
			}else{
				$str=N14\Encrypt\hexStr($_POST['n14encCT']);
			}
			$key=$_POST['n14encK'];
			echo "result:<br><pre>".N14\Encrypt\crypt($str,$key)."</pre>";
		} else{
			echo "<form action='' method='POST'>Input hex: <textarea name='n14encCT' rows='10' cols='50'></textarea><br>Key: <input type='text' name='n14encK' size='70'/><br><input type='submit' value='dec'/></form><br>2013 namonaki14";
		}
		echo "<br><q>Love, love me do, You know I love you, I'll always be true, So please, Love me do.</q> -The Beatles.";
	}
}



?>