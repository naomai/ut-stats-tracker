<?php
	namespace UTMDC\FileSize{
		require_once __DIR__."/common.php";
		
		function getFileSize($url){
			if(file_exists(__DIR__ . "/../fsizes.txt")){
				$fx=file(__DIR__ . "/../fsizes.txt",FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES);
				foreach($fx as $fa){
					$purl=strtok($fa,"\\");
					$psize=strtok("\r");
					if($purl==crc32($url)){
						return $psize;
					}
				}
			}
			stream_context_set_default(array('http'=>array('method'=>'HEAD','user_agent'=>'Mozilla/5.0 (Windows NT 10.0) UTTracker/MapIndexer (+http://tracker.ut99.tk/static/utmapfetcher.html)')));
			$hd=get_headers($url,true);
			if(isset($hd['Content-Length'])){
				$psize=$hd['Content-Length'];
				if(!is_numeric($psize))
					$psize=0;
			}else{
				$psize=0;
			}
			file_put_contents(__DIR__ . "/../fsizes.txt",crc32($url)."\\$psize\r\n",FILE_APPEND);
			echo "'";
			return $psize;
		}
	}

?>