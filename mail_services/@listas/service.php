<?php
//require_once dirname(__FILE__) . "/cathegories.php";
if (!defined('CACHE_BASE')) define('CACHE_BASE', DOCUMENT_ROOT . "/__apps/cache");

class ServiceListas extends ServiceBase{
	protected function Authorized(){
		return $this->getUserType() >= USER_CLIENT;
	}
	
	protected function RunService(){
		require_once dirname(__FILE__) . "/cathegories.php";
		
        //global $cath;
		$caths = array();
		foreach($this->data->params->param as $param){
			if ((string)$param->name == "caths[]"){
				foreach(explode(",", (string)$param->value) as $ncath)
					$caths[] = $cath[$ncath];
			}
		}
        
        // DEBUG ONLY
        if (_DEBUG_){
            $deb_str = "[OK] categorias = " . count($caths) . " :\r\n";
            foreach($caths as $item) $deb_str .= $item . "\r\n";
            Logs::GetInstance()->addEntry( $deb_str );
        }
		
		if (count($caths) == 0){
			$this->setComment("[FAILED]");
			$this->setComment("[MSG_BODY]");
			$this->setComment($this->msgBody);
			return;
		}
		
		$zipFile = new zipFile();
		foreach($caths as $cat){
			$fname = CACHE_BASE . "/" . ltrim($cat, "\\/") . "index.html";
			if (!is_file($fname)) continue;
			
			$zipFile->AddFile($fname, ltrim($cat, "\\/") . "index.html");
		}
		
        if (is_dir(CACHE_BASE . "/files")){
		    $zipFile->AddDirectory(CACHE_BASE . "/files", "files");
        }
        
        // DEBUG ONLY
        if (_DEBUG_){
            Logs::GetInstance()->addEntry( "[OK] Zip. Size = " . $zipFile->Save() );
        }
		
		$pname = "Listas-" . date("Y-m-d_H-i");
		$pack = array("name" => "{$pname}.zip" , "data" => $zipFile->Save());
		
		$this->SendMail($this->getMailFooter(), "", array($pack));
	}
}