<?php
	namespace N14{
		error_reporting(E_ALL);
		require_once __DIR__."/appConfig.php";
		require_once N14CORE_LOCATION . "/TableThing.php";
		/*if(isset($_GET['fetchJSON'])){
			$tableid=$_GET['fetchJSON'];
			$offset=isset($_GET['offset'])?$_GET['offset']:0;
			$limit=isset($_GET['limit'])?$_GET['limit']:-1;
			TableThing::staticInit();
			if(TableThing::tableIdExists($tableid)){
				$tx=new TableThing();
				$tx->isJsonFetcher=true;
				$tx->loadDataFromCache($tableid);
				
			
				echo $tx->genJSON($offset,$limit);
			}
			
		}*/
		
	}


?>