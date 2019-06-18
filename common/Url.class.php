<?php

class Url{
	private static $urlRegexp = '#^(|([a-z]+)://|//)(|([^:@]+)(|\:([^/][^@]+))@)(([-_a-zA-Z0-9]+\.)+([a-zA-Z]{2,3}|com|net|info|org|name|biz|gov|edu)|localhost|[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3})(|\:([0-9]+))(|((/[^\/\\\?]*)*)(|\?([^\#]*))(|\#(.*)))$#is';
	private static $urlRegexpForceScheme = '#^(([a-z]+)://)(|([^:@]+)(|\:([^/][^@]+))@)(([-_a-zA-Z0-9]+\.)+([a-zA-Z]{2,3}|com|net|info|org|name|biz|gov|edu)|localhost|[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3})(|\:([0-9]+))(|((/[^\/\\\?]*)*)(|\?([^\#]*))(|\#(.*)))$#is';
	
	public $scheme,
		$host,
		$port,
		$user,
		$pwd,
		$path,
		$params,
        $hashParam;
		
	function __construct(){
		$scheme = 
		$host =
		$port =
		$user =
		$pwd =
		$path =
		$params = "";
	}
	
	function __clone(){}
	
	public static function __dirname($str){
		$pos = strrpos($str, "/");
		$pos = $pos === false ? 0 : $pos;
		
		return substr($str, 0, $pos);
	}
	
	public static function Parse($strUrl, $forceScheme = false){
		$c = __CLASS__;
		$res = new $c;
		
		$pattern = "";
		if ($forceScheme){
			$pattern = self::$urlRegexpForceScheme;
		}else{
			$pattern = self::$urlRegexp;
		}
		
		if (!preg_match('#^mailto\:#', $strUrl) && 
			 preg_match($pattern, $strUrl, $match)){
			 
			$res->scheme = $match[2] ? $match[2] : "http";
			$res->user = $match[4];
			$res->pwd = $match[6];
			$res->host = $match[7];
			$res->port = $match[11];
			$res->path = empty($match[13]) ? '/' : $match[13];
			$res->path = self::ComputeRealPath($res->path);
			
			self::ParseQueryString(empty($match[16]) ? "" : $match[16], $res->params);
            
            if (!empty($match[18])){
                $res->hashParam = $match[18];
            }else{
                $res->hashParam = NULL;
            }
		}else{
			$res = false;
		}
		return $res;
	}
	private static function ComputeRealPath($path){
		$res = $path;
		while(preg_match('#(/[^/]+/\.\.)(?=/|$)#', $res))
			$res = preg_replace('#(/[^/]+/\.\.)(?=/|$)#', "", $res);
		
        $res = preg_replace('#(^/\.\.|/\.)(?=/|$)#', "", $res);
        
		return $res;
	}
	
    /**
    * @desc Computa una nueva url partiendo de esta.
    * @param string La url como string del nuevo recurso. puede ser una una url relativa o absoluta
    * 
    * @return Url
    */
	public function ComputeNewUrl($strPath){
		$newUrl = false;
		if($strPath == ""){ // The same Uri
			return clone $this;
		}else if(preg_match('#^(\w+:|)//#is', $strPath)){ // Independent Url
			$newUrl = self::Parse($strPath);
		}else if(preg_match('/^(#|\?)/', $strPath)){ // Same Page Part
			$newUrl = self::Parse($this->RemoveParams() . $strPath);
		}else if (preg_match('#^/#', $strPath)){ // Absolute Path
			$newUrl = self::Parse($this->ServerRoot() . $strPath);
		}else{ // Relative Path
			$newUrl = self::Parse($this->Dirname() . "/" . $strPath);
		}
		return $newUrl;
	}
	
	public function ServerRoot(){
		$res = clone $this;
		$res->path = "";
        $res->params = array();
        $res->hashParam = NULL;
		return $res;
	}
	
	public function Dirname(){
		$res = $this->serverRoot();
		$res->path = Url::__dirname($this->path);
		//var_dump($this->path, $res->path);
		return $res;
	}
	
	public function RemoveParams(){
		$res = $this->serverRoot();
		$res->path = $this->path;
		return $res;
	}
	
	public function FileName(){
		return preg_match('#/$#', $this->path) ? "" : basename($this->path);
	}
	
	function __toString(){
				// scheme 
		return $this->scheme . "://" . 
				// user:pwd
				(empty($this->user) ? "" : ( $this->user . (empty($this->pwd) ? "" : ":{$this->pwd}") . "@" )) .
				// host
				$this->host .
				// port
				(empty($this->port) ? "" : ":{$this->port}") .
				// path
				$this->path .
				// query
                ((count($this->params) > 0) ? ("?" . $this->BuildQueryString()) : "") .
                // hash part
				(($this->hashParam != NULL) ? ("#" . $this->hashParam) : "");
	}
	
	// Alias of __toString
	function Save(){
		return $this->__toString();
	}
	
    /**
    * @desc Agrega un grupo de parametros a esta Url
    * @param array Array asociativo con los parametros
    * 
    * @return void
    */
	function AddParams($arrParams){
		$this->params = $this->Union($this->params, $arrParams);
	}
	
	private function BuildQueryString(){
		$res = array();
		foreach($this->params as $key => $val){
			$res[] = urlencode($key) . "=" . urlencode($val);
		}
		return implode("&", $res);
	}
	
    /**
    * @desc Parsea un query string, y lo almacena en $vars
    * @param string La query string
    * @param array Array asociativo con las variables, pasado por referencia
    * 
    * @return void
    */
	private static function ParseQueryString($str, &$vars){
		$parts = preg_split('#\&(?!\w+\;)|\&amp\;#isx', $str, -1, PREG_SPLIT_NO_EMPTY);
		$vars = array();
		foreach($parts as $p){
			list($name, $val) = explode("=", $p, 2);
			$vars[urldecode($name)] = urldecode($val);
		}
	}
	
	private function Union($A, $B){
		$C = $A + array();
		foreach($B as $bb => $val){
			if (!isset($A[$bb])){
				$C[$bb] = $B[$bb];
			}elseif(is_array($A[$bb]) && is_array($B[$bb])){
				$C[$bb] = $this->Union($A[$bb], $B[$bb]);
			}
		}
		return $C;
	}
}