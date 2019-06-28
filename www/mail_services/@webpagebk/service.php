<?php
require_once dirname(__FILE__) . DS . "webdownload.class.php";
define ('MAX_PACK_SIZE', 10 * 1024 * 1024);
define ('DEFAULT_SPLIT_SIZE', 1024 * 1024);
define ('MIN_SPLIT_SIZE', 100 * 1024);
define ('MAX_SPLIT_SIZE', 1000 * 1024);

function _clamp($val, $min, $max){
	return max($min, min($val, $max));
}

class ServiceWebpageBK extends ServiceBase{
	protected $lock_time = 3600;
	var $url = false;
	var $depth = 0;
	var $resp_subject = "";
	var $remove_selectors = array();
	var $post_vars = false;
	var $is_src = false;
	var $packName = "";
	
	var $split = false;
	var $splitSize = DEFAULT_SPLIT_SIZE;
	
	private function getSuccessText(){
		return file_get_contents(dirname(__FILE__) . DS . "text" . DS . "webpage_success.txt") . 
			"\r\n\r\n\r\n" . $this->getMailFooter();
	}
	private function getFailureText(){
		return $this->sprintFromFile(dirname(__FILE__) . DS . "text" . DS . "webpage_failure.txt",
						(string)$this->data->_default, (string)$this->data->url, $this->depth, $this->resp_subject, 
						implode(", ", $this->remove_selectors)) . 
			"\r\n\r\n\r\n" . $this->getMailFooter();
	}
	private function getUrlFromData(){
		$url = Url::Parse(trim((string)$this->data->_default));
		return $url ? $url : Url::Parse(trim((string)$this->data->url));
	}
	
	// First argument is the path of a file
	// Rest of the arguments are the parameters
	// to the format
	private function sprintFromFile(){
		$count = 0;
		if (($count = func_num_args()) < 0) 
			trigger_error("Error calling function ServiceWebpage::sprintFromFile." . 
								" you must pass at least one parameter", E_USER_ERROR);
		
		$fargs = func_get_args();
		$format = file_get_contents($fargs[0]);
		$args = array();
		for($i = 1; $i < $count; $i++){
			$args[] = $fargs[$i];
		}
		return vsprintf($format, $args);
	}
	
	protected function Authorized(){
		$uType = $this->getUserType();
		
		if ($uType >= USER_CLIENT){
			return true;
		}else{
			//$this->data->depth = 0;
			return $this->getLastUsage() + $this->lock_time < time();
		}
	}
	
	protected function RunWebpage(){
		if ($this->url !== false){
			$wd = new WebDownload();
			$wd->url = $this->url;
			
			$files = array();
			foreach($this->files as $key => $file){
				$files[$key] = "@{$file['tmp_name']};type={$file['type']}";
			}
			// Debug purpose
			if (count($files) > 0){
				$this->setComment("[FILES_TO_SEND]");
				foreach($files as $key => $val){
					$this->setComment("$key => $val");
				}
			}
			
			$wd->post_vars = $files + (is_array($this->post_vars) ? $this->post_vars : array());
			if (!count($wd->post_vars)) $wd->post_vars = false;
			
			$wd->remove_selectors = $this->remove_selectors;
			$wd->depth = $this->depth;
			$wd->cookie_file = $this->getCookieFile();
			$wd->is_src = $this->is_src;
			
			$wd->StartDownload();
			$this->url = $wd->url;
			
			$this->packName = $this->cleanFileName($wd->packName ? $wd->packName : 
									basename($this->url->RemoveParams()->Save()));
			
			return $wd->CreateZipPackage();
		}else {
			return false;
		}
	}
	protected function RunService(){
		$this->url = $this->getUrlFromData();
		
		// Se queda en 0 pero puede ser sobrescrito por una clase
		// hija.
		//$this->depth = $this->data->depth ? $this->data->depth : 0;
		$this->remove_selectors = $this->data->remove_selectors ? 
				explode(',', $this->data->remove_selectors) : array();
		if ((string)$this->data->no_images){
			$this->remove_selectors[] = "img";
		}
		if ((string)$this->data->no_scripts){
			$this->remove_selectors[] = "script";
		}
		
		$this->split = (string)$this->data->split ? (string)$this->data->split : $this->split;
		$this->splitSize = floor((string)$this->data->splitSize ? (string)$this->data->splitSize * 1024 : 
																		$this->splitSize);
					
		$this->splitSize = _clamp($this->splitSize, MIN_SPLIT_SIZE, MAX_SPLIT_SIZE);
		
		$this->resp_subject = $this->data->resp_subject ? 
					$this->data->resp_subject : "Re: {$this->msgSubject}";
		
		$charset = (string)$this->data->charset ? (string)$this->data->charset : "ISO-8859-1";
		
		// Parameters
		foreach ($this->data->params as $params){
			foreach($params->children() as $param){
				$val = mb_convert_encoding((string)$param->value, $charset, "UTF-8");
				if (preg_match('#^base64\:(.*)$#isxm', $val, $m)){
					$val = base64_decode($m[1]);
				}
				$val = substr($val, 0, 1) == "@" ? " $val" : $val;
				
				if (preg_match('#^([\w\_][\w\_\d]*)\s*\[(|\'|\")(.*)\2\]$#is', (string)$param->name, $match)){
					if (!isset($this->post_vars[$match[1]]))
						$this->post_vars[$match[1]] = array();
						
					if ($match[3]) $this->post_vars[$match[1]][$match[3]] = $val;
					else $this->post_vars[$match[1]][] = $val;
				}else{
					$this->post_vars[(string)$param->name] = $val;
				}
			}
		}
		
		if (is_array($this->post_vars) && 
            strtolower($this->data->method ? $this->data->method : "get") != "post"){
			$this->url->AddParams($this->post_vars);
			$this->post_vars = FALSE;
		}
		
		$this->addInfo();
		
		$this->is_src = strpos((string)$this->data->_default, ':src') !== false;
		
		$to = $this->user;
		$from = $this->serverAddress;
		$fromName = $this->serverAddressName ? $this->serverAddressName : "WWW";
		if ( ($pack = $this->RunWebpage()) && (strlen($pack) <= MAX_PACK_SIZE) ){ // Exito
			$subject = $this->resp_subject;
			$body = "url: {$this->url}\r\n\r\n" . $this->getSuccessText();
			
			$packages = $this->buildPackage($pack);
		}else{ // Fallo
			$subject = "Fallo la descarga";
			$body = $this->getFailureText() 
											. "Tamaño de archivo final: ". strlen($pack) ."\r\n"
											. "Tamaño de archivo maximo: ". MAX_PACK_SIZE ."\r\n"
											. "\r\n\r\nXML:\r\n"
											. "\r\n" . $this->data->asXML();
			
			$this->setComment("[FAILED]");
			
			// Debugin purposes
			$this->setComment("[XML_DATA]");
			$this->setComment($this->data->asXML());
			$this->setComment("[MSG_BODY]");
			$this->setComment($this->msgBody);
			$this->setComment("[MSG_BODY_DECODED]");
			$this->setComment($this->msgBodyDecoded);
			
			// ----------------
			
			$packages = array();
		}
		
		$c = count($packages);
		if ($c > 0){
			$k = 1;
			foreach($packages as $pk){
				$part = $c == 1 ? "" : " (Parte $k de $c)"; $k++;
				$rsubject = (string)$this->data->resp_subject ? 
						(string)$this->data->resp_subject : "{$this->packName}{$part}";
				
				$this->SendMail($body, $rsubject, array($pk));
			}
		}else{
			$this->SendMail($body, $subject, array());
		}
	}
	
	function buildPackage(&$pack){
		$name = $this->packName . date(" (Y-m-d H.i)") . ".zip";
		$N = ceil(strlen($pack) / $this->splitSize);
		
		$packages = array();
		if ($N == 1 || !$this->split){
			$packages[] = array('name' => $name, 'data' => $pack);
		}else{
			for($i = 0; $i < $N; $i++){
				$packages[] = array('name' => "{$name}.{$i}-{$N}.chunk", 
								'data' => substr($pack, $i * $this->splitSize, $this->splitSize));
			}
		}
		
		return $packages;
	}
	
	function addInfo(){
		$this->setComment("url: {$this->url}");
		$this->setComment("method: " . (string)$this->data->method);
		$this->setComment("depth: {$this->depth}");
		$this->setComment("resp_subject: {$this->resp_subject}");
	}
	
	/******************/
	/* Helper Methods */
	/******************/
	function cleanFileName($fname){
		return preg_replace(array('#[\\\/\:\*\?\"\<\>\|]+(?=\w|\d|\s)#',
									'#[\\\/\:\*\?\"\<\>\|]+(?!\w|\d|\s)#'),
									array("-", ""), $fname);
	}
	protected function getCookieFile(){
		if ($this->getUserType() >= USER_CLIENT){
			$fname = dirname(__FILE__) . DS . "cookies" . DS . $this->cleanFileName($this->user);
			if (!is_file($fname)) // Create File
				file_put_contents($fname, "");
			
			return $fname;
		}else{
			return NULL;
		}
	}
}