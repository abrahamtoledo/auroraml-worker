<?php

class UrlPatches{
	protected $c_patch = array();
	protected static $instance;
	
	public function __construct(){
	}
	
	public function AddPatchFunction($function){
		if (is_callable($function)){
			$this->c_patch[] = $function;
		}
	}
	
	public function ApplyPatches($url){
		$outUrl = clone $url;
		$this->_applyPatches($outUrl);
		return $outUrl;
	}
	
	protected function _applyPatches(&$url, $chances = 5){
		if ($chances < 0) return;
		
		foreach($this->c_patch as $patch){
			if ($tUrl = call_user_func($patch, $url)){
				$url = $tUrl;
				$this->_applyPatches($url, $chances - 1);
				return;
			}
		}
	}
	
	public static function GetInstance(){
		if (!self::$instance){
			self::$instance = new UrlPatches();
		}
		return self::$instance;
	}
	
}

$_up = UrlPatches::GetInstance();

function patch_google_url($url){
	if (strpos($url->host, "google") !== false && 
		$url->path == "/url"){
		return Url::Parse($url->params['q']);
	}elseif(strpos($url->host, "images.google") !== false &&
		$url->path == "/imgres"){
		return Url::Parse($url->params['imgrefurl']);
	}
	
	return false;
}

function patch_revolico($url){
	if (getHostByName($url->host) == getHostByName("www.revolico.com")){
		$proxUrl = Url::Parse(REV_BASE);
		$url->host = $proxUrl->host;
		$url->path = rtrim($proxUrl->path, "/") . $url->path;
		
		return $url;
	}
	
	return false;
}

function patch_facebook($url){
	if (($url->host == "www.facebook.com") || ($url->host == "facebook.com") ||
		($url->host == "fb.com") &&
		($url->path == "/" || $url->path == "")){
		
		$url->host = "m.facebook.com";
		return $url;
	}
	
	return false;
}

$_up->AddPatchFunction("patch_google_url");
//$_up->AddPatchFunction("patch_revolico");
$_up->AddPatchFunction("patch_facebook");