<?php
// Unicode BOM is U+FEFF, but after encoded, it will look like this.
define ('UTF32_BIG_ENDIAN_BOM'   , chr(0x00) . chr(0x00) . chr(0xFE) . chr(0xFF));
define ('UTF32_LITTLE_ENDIAN_BOM', chr(0xFF) . chr(0xFE) . chr(0x00) . chr(0x00));
define ('UTF16_BIG_ENDIAN_BOM'   , chr(0xFE) . chr(0xFF));
define ('UTF16_LITTLE_ENDIAN_BOM', chr(0xFF) . chr(0xFE));
define ('UTF8_BOM'               , chr(0xEF) . chr(0xBB) . chr(0xBF));

define('WEBPAGE_DIR', dirname(__FILE__));

require_once WEBPAGE_DIR . DS . "RequestHandler.php";

class webFile{
	public $root = false;
    
    /**
    * @var Url
    */
	public $url = ""; 
    public $md5 = "";
	public $contentType = "";
    /**
    * @desc Nombre del archivo cuando es un adjunto
    */
    public $attFileName = null;
    public $name = ""; 
	public $depth = 0;
    public $content = "";
	
	public $post_vars = NULL;
	public $post_files = NULL;
	public $referer = "";
	public $urlencoded = false;
	
	function __construct($params = array()){
		foreach($params as $key => $val){
			$this->$key = $val;
		}
	}
}

class CssImportsCallback{
	var $webFile;
	var $webDownloadInstance;
	var $c_webFile;
	var $prefix;
	var $referer;
	
	function __construct($webFile, &$webDownloadInstance, &$c_webFile, $prefix = ""){
		$this->webFile = 				&$webFile;
		$this->webDownloadInstance =    &$webDownloadInstance;
		$this->c_webFile =              &$c_webFile;
		$this->prefix = 				$prefix;
		$this->referer = 				$webFile->url->Save();
	}
	
	function CallBack($match){
		$tWebFile = new webFile(array('url'=> $this->webFile->url->ComputeNewUrl(trim($match[1], "\"\'")), 'referer' => $this->referer));
		
		if ($t_webFile = $this->webDownloadInstance->getMapFile($tWebFile->url)){
			return "{$this->prefix}url(" . $this->webDownloadInstance->getRelativePath($this->webFile->name, $t_webFile->name) . ")";
		}else{
			$this->webDownloadInstance->tryResolveFileName($tWebFile, $this->webFile->name . "_files/");
			$this->c_webFile[] = $tWebFile;
			return "{$this->prefix}url(" . $this->webDownloadInstance->getRelativePath($this->webFile->name, $tWebFile->name) . ")";
		}
	}
}

class webDownload{

    const MOBILE_IMAGE_WIDTH = 512;
    
    const TOP_MESSAGE_TYPE_NONE = 0;
    const TOP_MESSAGE_TYPE_ALERT = 1;
    const TOP_MESSAGE_TYPE_WARN = 2;
    const TOP_MESSAGE_TYPE_INFO = 3;
    
    // Indica si se debe mostrar un mensaje al usuario en
    // la parte superior de la pagina y que tipo de mensaje,
    // el resumen y su contenido completo
    var $topMessageType = self::TOP_MESSAGE_TYPE_NONE; 
    var $topMessageDescription = "";
    var $topMessageFullContent = "";
    public function setTopMessage(
            $messageDescription, 
            $messageFullContent,
            $messageType = self::TOP_MESSAGE_TYPE_ALERT){
        $this->topMessageType = $messageType;
        $this->topMessageDescription = $messageDescription;
        $this->topMessageFullContent = $messageFullContent;
    }
    
    public function unsetTopMessage(){
        $this->topMessageType = self::TOP_MESSAGE_TYPE_NONE;
    }
    
	var $url;
	var $referer = "";
	var $post_vars = NULL;
	var $post_files = NULL;
	var $cookie_file = NULL;
	var $remove_selectors = array();
	var $depth = 0;
	var $match_only = null;
	var $is_src = false;
    var $no_css_digest = false;
    
    var $fileCount = 0;
    var $_sendMetadata = false; // por defecto no se enviaran metadatos
    var $_responseMetadata = array(); // en este array se almacenaran los metadatos
    
	var $urlencode = false;
	
	var $_imageCuality = 10;

	// Values for cuality goes from 1 to 10, you must clamp it. Default is 10(For backward compatibility)
	private function setImageCuality($val){
		$this->_imageCuality = max(1, min(10, $val));
	}
	private function getImageCuality() { return $this->_imageCuality; }
	
    /**
    * @var int Cantidad maxima de KB que puede pesar una imagen en el archivo final
    */
    var $_imageSizeLimit = 100000; // Tamanno maximo de imagen en KB. Por defecto un numero grande que significa sin restriccion
    private function setImageSizeLimit($val){
        $this->_imageSizeLimit = $val;
    }
    private function getImageSizeLimit() { return $this->_imageSizeLimit; }
    
	var $file_map = array();
	var $files = array();
	
	var $packName = "";
	
	var $charset_override = array(
		//"m.facebook.com" => "utf-8",
		//"www.facebook.com" => "utf-8",
		//"facebook.com" => "utf-8"
	);
	
	public function __set($name, $val){
		if (method_exists($this, "set{$name}"))
			call_user_func(array($this, "set{$name}"), $val);
		else 
			trigger_error("Unknown, Unaccessible or Readonly property '$name' in 'WebDownload' class", E_USER_WARNING);
	}
	public function __get($name){
		if (method_exists($this, "get{$name}"))
			return call_user_func(array($this, "get{$name}"), $val);
		else 
			trigger_error("Unknown or Unaccessible property '$name' in 'WebDownload' class", E_USER_WARNING);
	}
	
	public function __construct(){
	}
	
	public function StartDownload(){
		$logs = Logs::GetInstance();
		
		$this->Download(new webFile(array(
										'url' => $this->url, 
										'depth' => $this->depth, 
										'root' => true,
										'post_vars' => $this->post_vars,
										'post_files' => $this->post_files,
										'referer' => $this->referer,
										'urlencoded' => $this->urlencode)
									));
                                    
        // Llena los campos principales de metadatos si esta opcion esta activada
        if ($this->_sendMetadata){
            $this->_responseMetadata["Files"] = $this->fileCount;
            $this->_responseMetadata["Image_Cuality"] = $this->ImageCuality;
            $this->_responseMetadata["Image_Size_Limit"] = $this->_imageSizeLimit;
            $this->_responseMetadata["Url"] = $this->url->Save();
            $this->_responseMetadata["Referer"] = $this->referer;
        }
	}
	
	protected function Download($webFile){
		if (is_array($webFile)){
			$this->DownloadCollection($webFile);
		}else{
			$this->DownloadSingle($webFile);
		}
	}
	
	// Aclaraciones: 
	// Esta funcion solo puede descargar recursos usando metodo GET
	protected function DownloadCollection($c_webFile){
		$logs = Logs::GetInstance();
		
		$lrHandlers = array();
		foreach($c_webFile as $webFile) {
			$lrHandlers[] = RequestHandler::CreateInstance($webFile, $this->cookie_file);
		}
		
		RequestHandler::PerformRequest($lrHandlers);
		
		$c = count($c_webFile);
		for($i = 0; $i < $c; $i++){
			if (!$c_webFile[$i]->url) continue;
			
            $this->Digest($c_webFile[$i], $c_webFile[$i]->contentType);
		}
	}
	
	// Aclaraciones: 
	// Esta funcion solo puede descargar recursos usando metodo GET y POST
	protected function DownloadSingle($webFile){
		$logs = Logs::GetInstance();
        
        if ($webFile->depth < 0) return;
		
		$requestHandler = RequestHandler::CreateInstance($webFile, $this->cookie_file);
		RequestHandler::PerformRequest($requestHandler);
        
        if ($webFile->url){
		    $this->Digest($webFile, $webFile->contentType);
        }
	}
	
    /**
    * @desc Procesa las respuestas
    * @param WebFile
    */
	protected function Digest($webFile, $content_type){
        $logs = Logs::GetInstance();
        
        $this->fileCount++;
        
		$c_type = strtolower(strtok($content_type, ";"));
        
		$c_type = $c_type ? $c_type : $this->gessContentType($webFile);
        
        // Patch no_css_digest
        if ($c_type == "text/css" && $this->no_css_digest){
            $c_type = "text/plain";
        }
		
		switch ($c_type){
			case "text/html":
			case "text/htm":
			case "text/xhtml":
			case "text/html5":
			case "application/xhtml+xml":
				$this->DigestHTML($webFile, $c_type);
				break;
			case "text/css":
				$this->DigestCSS($webFile);
				break;
			case "text/javascript":
			case "text/jscript":
				$this->DigestJS($webFile);
				break;
			case "image/jpeg":
			case "image/jpg":
			case "image/png":
			case "image/x-png":
			case "image/gif":
			case "image/x-gif":
			case "image/bmp":
                if ($this->ImageCuality < 10){
                    $img = @imagecreatefromstring($webFile->content);
					if ($img !== false){
					    // Para moviles reducimos tambien la resolucion de la foto
                        if (defined('MOBILE_REQUEST') && MOBILE_REQUEST){
                            $resized = $this->resizeImageForMobile($img);
                            if ($resized !== null){
                                imagedestroy($img);
                                $img = $resized;
                            }
                        }
                        
						$iCuality = $this->ImageCuality * 10;
						$tmpfile = tempnam(sys_get_temp_dir(), "img");
						
						if (imagejpeg($img, $tmpfile, $iCuality) !== false &&
							strlen($webFile->content) > filesize($tmpfile)){
							$webFile->content = file_get_contents($tmpfile);
						}
						
						unlink($tmpfile);
						imagedestroy($img);
					}
				}
                
                // Si la imagen supera el tamanno maximo debe ser eliminada. Como esto no se
                // puede saber a priori hay que procesar la imagen incluso cuando luego se descarta
                if (strlen($webFile->content) > 1024 * $this->ImageSizeLimit){
                    $webFile->content = "La imagen supero el limite maximo que usted establecio";
                }
            default:
				$this->DigestUnknown($webFile);
				break;
		}
	}
    /**
    * @desc Redimensiona la imagen al tama�o apropiado
    * para el movil
    * 
    * @param Image imagen a redimensionar
    * @return Image imagen redimensionada o null si no fue necesario redimensionar
    */
    private function resizeImageForMobile($img){
        $src_x = imagesx($img);
        $src_y = imagesy($img);
        
        if ($src_x <= self::MOBILE_IMAGE_WIDTH)
            return null;
            
        $dest_x = self::MOBILE_IMAGE_WIDTH;
        $dest_y = (int)(($dest_x / $src_x) * $src_y);
        
        $dest = imagecreatetruecolor($dest_x, $dest_y);
        if (imagecopyresampled($dest, $img, 0, 0, 0, 0, $dest_x, $dest_y, $src_x, $src_y)){
            return $dest;
        }else{
            imagedestroy($dest);
            return null;
        }
    }

	function detect_utf_encoding($text) {
		return preg_match('%(?:
        [\xC2-\xDF][\x80-\xBF]        # non-overlong 2-byte
        |\xE0[\xA0-\xBF][\x80-\xBF]               # excluding overlongs
        |[\xE1-\xEC\xEE\xEF][\x80-\xBF]{2}      # straight 3-byte
        |\xED[\x80-\x9F][\x80-\xBF]               # excluding surrogates
        |\xF0[\x90-\xBF][\x80-\xBF]{2}    # planes 1-3
        |[\xF1-\xF3][\x80-\xBF]{3}                  # planes 4-15
        |\xF4[\x80-\x8F][\x80-\xBF]{2}    # plane 16
        )+%xs', $text);
	}

	/**
	 * @param $webFile
	 * @param $content_type
	 * @return mixed|null
	 */
	protected function tryGetCharset($webFile, $content_type)
	{
		$contentFrag = substr($webFile->content, 0, 400);

		// From http-equiv="Content-type"
		if (preg_match('#<meta\s+[^>]*http-equiv\=\"content\-type\"\s+[^>]*content\=\"([^"]*)\"[^>]*>#is',
			$contentFrag, $m)){
			strtok($m[1], ";");
			while($tok = strtok(";")){
				list($key, $val) = explode("=", $tok);
				if ($key == "charset"){
					return $val;
				}
			}
		}

		// From meta charset
		if (preg_match('#<meta\s+[^>]*charset\=\"([^"]*)"[^>]*>#is',
			$contentFrag, $m)){
			return $m[1];
		}

		// From xml encoding attribute
		if (preg_match('#<\?xml\s+[^?>]*encoding\=\"([^"]*)"[^?>]*\?>#is',
			$contentFrag, $m)){
			return $m[1];
		}

		// From HTTP Headers
		if (preg_match("#charset=(.*)(?:;|$)#", $content_type, $m)) {
			$charset = $m[1];
			return $charset;
		}

		// From charset overrides
		if (isset($this->charset_override[$webFile->url->host])) {
			$charset = $this->charset_override[$webFile->url->host];
			return $charset;
		}

		return NULL;
	}

	protected function digestHTML($webFile, $content_type){
		syslog(LOG_INFO, "START DIGEST HTML: url={$webFile->url}");
		
		$this->tryResolveFileName($webFile, "", "html");
		
		if ($this->is_src) return;
		
		$dom = str_get_html($webFile->content);
		
		if ($webFile->root) {
            // Este es el archivo principal
            $this->ResolvePackName($dom);
        }
        
        // Comprobar si se debe forzar javascript o imagenes, o cualquier otro tag
		$meta = $dom->find("meta[name=auroraml:force_tags]", 0);
        $force_tags = explode(",", str_replace(" ", "", $meta->content));
        $this->remove_selectors = array_diff($this->remove_selectors, $force_tags);

		$charset = $this->tryGetCharset($webFile, $content_type);

		// Add charset && x-location
		$head = $dom->find("head", 0);
		if ($head) {
			if ($charset !== null && count($dom->find("meta[http-equiv*=ontent]")) == 0) {
				$head->AddChild($dom->create_self_closed_element('meta', array(
					'http-equiv' => "Content-Type",
					'content' => "text/html; charset={$charset}"
				)));
			}

			$head->AddChild($dom->create_self_closed_element('meta', array(
				'name' => "x-location",
				'content' => $webFile->url
			)));
		}

        if ($webFile->root){
            if ($this->topMessageType){
                // Existe un mensaje para mostrar en la parte superior
                switch($this->topMessageType){
                    case self::TOP_MESSAGE_TYPE_INFO:
                        $topMessageColor = "#00aa00";
                        break;
                    case self::TOP_MESSAGE_TYPE_WARN:
                        $topMessageColor = "#99aa00";
                        break;
                    case self::TOP_MESSAGE_TYPE_ALERT:
                    default:
                        $topMessageColor = "#aa0000";
                        break;
                    
                }
                
                // convert charset
                
                $messageTag = <<<HTML
<style type="text/css">
        .aurora-top-message{
            background: {$topMessageColor};
            color: #ffffff;
            margin-top: 0px; margin-left:0px; margin-right: 0px;
            padding: 4px 8px;
            font-family: Verdana, Geneva, sans-serif;
            z-index: 100000;
            /*position: absolute;*/
            top: 0px; left: 0px;
            width: 100%;
        }
        .aurora-top-message a{
            text-decoration: underline;
            color: #fff !important;
        }
        .aurora-modal{
            position: fixed;
            font-family: Arial, Helvetica, sans-serif;
            top: 0; right: 0; bottom: 0; left: 0;
            background: rgba(0,0,0,0.4);
            z-index: 100001;
            opacity: 0;
            -webkit-transition: opacity 400ms ease-in;
            -moz-transition: opacity 400ms ease-in;
            transition: opacity 400ms ease-in;
            pointer-events: none;
            display: none;
        }
        .aurora-modal:target{
            opacity: 1;
            pointer-events: auto;
            display: block;
        }
        .aurora-modal > div{
            position: absolute;
            top: 20px; right: 20px; bottom: 20px; left: 20px;
            margin: 10% auto;
            padding: 5px 20px 13px 20px;
            background: #fff;
        }
        .aurora-modal-header{
            position: absolute;
            top: 20px; left: 20px; right: 20px;
            height: 40px;
            clear: both;
        }
        .aurora-modal-content{
            overflow-y: scroll;
            position: absolute;
            bottom: 20px; left: 20px; right: 20px; top: 60px;
            font-size: 11pt;
        }
        .aurora-close-btn-bk{
            background: #606061;
            color: #fff;
            line-height: 25px;
            position: absolute;
            right: -12px;
            text-align:center;
            top: -10px;
            width: 24px;
            text-decoration: none;
            font-weight: bold;
            -moz-border-radius: 50%;
            border-radius: 50%;
            -moz-box-shadow: 1px 1px 3px #000;
            box-shadow: 1px 1px 3px #000;
        }
        .aurora-close-btn{
            display: block;
            float: right;
            text-decoration: none;
            font-weight: bold;
            color: #77aa00 !important;
            line-height: 18pt;
            
        }
        .aurora-modal-header > h1{
            float: left;
            display: block;
            margin: 0px;
            font-size: 16pt;
            color: #77aa00;
        }
    </style>
    <div class="aurora-top-message" id="closeAuroraModal">
    {$this->topMessageDescription} <a href="#openAuroraModal">M&aacute;s Informaci&oacute;n...</a>
    </div>
    <div id="openAuroraModal" class="aurora-modal">
      <div class="aurora-modal-container">
        <div class="aurora-modal-header">
            <a href="#closeAuroraModal" title="Close" class="aurora-close-btn">X</a>
            <h1>Informaci�n</h1>
        </div>
        <div class="aurora-modal-content">
            {$this->topMessageFullContent}
        </div>
        
      </div>
    </div>
HTML;

                if ($charset != null){
                    $messageTag = mb_convert_encoding($messageTag, 
                                    strtoupper($charset), 
                                    "ISO-8859-1");
                }
                
                $body = $dom->find("body", 0);
                if ($body){
					$body->PrependChild($dom->create_element('div', $messageTag));
				}
            }
        }
        
		// End Add charset
		
		// Base File Computation
		$base = $webFile->url;
		$baseTag = $dom->find("base", 0);
		if ($baseTag){
			$base = $base->ComputeNewUrl($baseTag->href);
			$baseTag->outertext = "";
		}
		
		// remove_selectors
		if (count($this->remove_selectors)){
			foreach($dom->find( implode(',', $this->remove_selectors) ) as $elem) 
				$elem->remove();
		}
		
		// remove refresh meta tag
		foreach($dom->find("meta") as $elem){
			if ($elem->hasAttribute("http-equiv") && strcasecmp($elem->getAttribute("http-equiv"), "Refresh") == 0 ){
				$elem->remove();
			}
		}
		
		if (in_array("script", $this->remove_selectors)){
			foreach($dom->find("noscript") as $elem){
				$elem->tag = "div";
			}
		}
		
		// (TED) Transforms and Extract Dependencies
		// 1) Normalize all Urls
		foreach ($dom->find("*[src], *[href], *[action]") as $elem){
			$key = isset($elem->src) ? "src" : (isset($elem->href) ? "href" : "action");
			if (!preg_match('#^(\#|mailto:|javascript:)#is', $elem->$key)){
				$newUrl = (strcasecmp($key, "action") == 0 && (is_bool($elem->$key) || !$elem->$key )) ? $webFile->url : $base->ComputeNewUrl($elem->$key);
				if ($newUrl)
					$elem->$key = $newUrl->Save();
			}
		}
		
		// Patch, A FORM WITHOUT ACTION attribute
		foreach($dom->find("form") as $elem){
			if (!$elem->action)
				$elem->action = $webFile->url;
		}
		// End Patch
		
		// 2) Get Dependencies
		$c_webFile = array();
		$referer = $webFile->url->Save();
		
		// src elements, styles, icon, search plugins
		$depend = array("*[src]", "link[rel=Stylesheet]", "link[rel=stylesheet]", "link[rel=shortcut icon]", "link[rel=search]");
		if ($webFile->depth > 0) $depend[] = "a[href]";
		foreach($dom->find( implode(',', $depend) ) as $elem){
			
			// Download only page links with matching urls
			if (strtolower($elem->tag) == "a" && $this->match_only != null &&
				!preg_match("#{$this->match_only}#is", $elem->href)){
				continue;
			}
            
			$key = $elem->src ? "src" : "href";
			
			if (($url = Url::Parse($elem->$key)) !== false){
				if ($tWebFile = $this->getMapFile($url)){ // File Already Downloaded
					$elem->$key = $this->urlencode_keep_slashes(
                                $this->getRelativePath($webFile->name, $tWebFile->name)
                            );
				}else{                                                                       
					$depth = strtolower($elem->tag) == "a" ? $webFile->depth - 1 : $webFile->depth;
					$tWebFile = new webFile(array('url'=> $url, 'depth' => $depth, 'referer' => $referer));
                    
					$this->tryResolveFileName($tWebFile, $webFile->name . "_files/", false, $elem);
					$elem->$key = $this->urlencode_keep_slashes(
                                $this->getRelativePath($webFile->name, $tWebFile->name)
                            );
					
					$c_webFile[] = $tWebFile;
				}
			}
		}
		
		// 2.1) Inline css imports
        if (!$this->no_css_digest) {
            foreach ($dom->find("style") as $elem) {
                if (!in_array("img", $this->remove_selectors)) {
                    $regexp = '#url\(([^\)]+)\)#';
                    $prefix = "";
                } else {
                    $regexp = '#import\s+url\(([^\)]+)\)#';
                    $prefix = "import ";
                }

                $callback = new CssImportsCallback($webFile, $this, $c_webFile, $prefix);
                $elem->innertext = preg_replace_callback($regexp, array($callback, "Callback"), $elem->innertext);
            }
        }
		
		// End (TED)
        
        // Download dependencies before saving the page
		$this->Download($c_webFile);
		
		// Save File
		$webFile->content = $dom->Save();
		$dom->clear();

		syslog(LOG_INFO, "END DIGEST.");
	}

	protected function digestCSS($webFile){
		$this->tryResolveFileName($webFile, "", "css");
		
		if ($this->is_src) return;
		
		if (!in_array("img", $this->remove_selectors)){
			$regexp = '#url\(([^\)]+)\)#';
			$prefix = "";
		} else{
			$regexp = '#import\s+url\(([^\)]+)\)#';
			$prefix = "import ";
		}
		$c_webFile = array();
		$callback = new CssImportsCallback($webFile, $this, $c_webFile, $prefix);
		$webFile->content = preg_replace_callback($regexp, array($callback, "Callback"), $webFile->content);
		
		// Get dependecies
		if (count($c_webFile) > 0) $this->Download($c_webFile);
	}

	protected function digestJS($webFile){
		$this->tryResolveFileName($webFile, "", "js");
	}
	/**
    * @desc procesa un archivo con un formato sin tratamiento especial
    */
    protected function digestUnknown($webFile){
        $this->tryResolveFileName($webFile);
	}

	/******************/
	/* Helper Methods */
	/******************/
	protected function gessContentType($webFile){
		$sample = substr($webFile->content, 0, 4096);
		
		if (preg_match('#\<html[^\>]*\>#', $sample))
		{
			return "text/html";
		}elseif (preg_match('#url\(([^\)]+)\)#', $sample)){ // Css (only matters if it have url dependencies)
			return "text/css";
		}
		
		return "";
	}
	
	protected function saveToArchive($webFile){
		$this->files[$webFile->name] = &$webFile;
	}
	
	public function getMapFile($url){
		$name = $this->file_map[$url->Save()];
		
		if ($name){
			return new webFile(array('url'=> $url, 'name' => $name));
		}else{
			return false;
		}
	}
	public function addToFileMap($webFile){
		$this->file_map[$webFile->url->Save()] = $webFile->name;
	}
	public function getRelativePath($src, $dest){
		if (preg_match('#^(\w\:)#', $dest, $match)){
			return "file:///$dest";
		}
		
		$dname = $src;
		$rpath = "";
		while($dname = Url::__dirname($dname)) $rpath .= "../";
		
		$rpath .= ltrim($dest, "/\\");
		return $rpath;
	}
	
	/**
    * @desc 
    * @param WebFile
    * @param string
    * @param boolean
    * @param boolean
    */
	public function tryResolveFileName(&$webFile, $dir = "", $ext = false, $tag = false){
		if (!$webFile->name){
			$this->resolveFileName($webFile, $dir, $ext, $tag);
            $webFile->name = preg_replace('#[\:\+\;\?\*\"\<\>\|]#', '_', $webFile->name);
			$this->addToFileMap($webFile);
		}
	}
    
	public function resolveFileName(&$webFile, $dir = "", $ext = false, $tag = false){
		if ($dir && !preg_match('#/|\\$#', $dir)) $dir .= "/";
		
        if ($webFile->attFileName){
            $pathInfo = pathinfo($webFile->attFileName);
            $this->packName = $pathInfo['filename']; 
        }
        
		$webFile->name = $webFile->attFileName ? $webFile->attFileName : urldecode($webFile->url->FileName());
		$webFile->name = ($webFile->name != "" ? $webFile->name : "document");
		
		if ($ext) {
			$webFile->name = preg_match('#'.preg_quote(".$ext", "#").'$#', $webFile->name) ? 
							$webFile->name : "{$webFile->name}.{$ext}";
		}elseif(is_a($tag, 'DOMElement')){
			// Using Euristics to attempt to determine the filetype based on tag
			if (in_array($tag->tagName, array('frame', 'iframe', 'a'))){
                // Remplazar cualquier extension de script a html
				$webFile->name = preg_replace('#\.(php|php5|php3|phpx|asp|aspx|aspn|cgi)$#imxs', 
																		'.html', $webFile->name);
                
                // Si el nombre de archivo no tiene extension. hacerlo html por defecto
				if (strpos($webFile->name, ".") === false) $webFile->name .= ".html";
			}

			if (in_array($tag->tagName, array('img')) && 
				!preg_match('#\.(bmp|dib|gif|jpg|jpeg|png|tga|svg)$#imxs', $webFile->name)){
				$webFile->name .= ".bmp";
			}

			if (strtolower($tag->tagName) == "link" &&
				strtolower($tag->relName) == "stylesheet"){
				$ext = "css";
				$webFile->name = preg_match('#'.preg_quote(".$ext", "#").'$#', $webFile->name) ? 
							$webFile->name : "{$webFile->name}.{$ext}";
			}
			
			if (strtolower($tag->tagName) == "link" &&
				strtolower($tag->relName) == "shortcut icon"){
				$ext = "ico";
				$webFile->name = preg_match('#'.preg_quote(".$ext", "#").'$#', $webFile->name) ? 
							$webFile->name : "{$webFile->name}.{$ext}";
			}
			
			if (strtolower($tag->tagName) == "link" &&
				strtolower($tag->relName) == "search"){
				$ext = "xml";
				$webFile->name = preg_match('#'.preg_quote(".$ext", "#").'$#', $webFile->name) ? 
							$webFile->name : "{$webFile->name}.{$ext}";
			}
			
			if (in_array($tag->tagName, array('script'))){
				$ext = "js";
				$webFile->name = preg_match('#'.preg_quote(".$ext", "#").'$#', $webFile->name) ? 
							$webFile->name : "{$webFile->name}.{$ext}";
			}
		}
		
		if (isset($this->files["{$dir}{$webFile->name}"])){
			$k = 1;
			while (isset($this->files["{$dir}{$k}_{$webFile->name}"])) $k++;
			$webFile->name = "{$dir}{$k}_{$webFile->name}";
		}else{
			$webFile->name = "{$dir}{$webFile->name}";
		}
        
        $this->saveToArchive($webFile);
	}
	
	
	private function ResolvePackName($dom){
		$title = $dom->find("title", 0);
		if ($title){
			$this->packName = $title->innertext;
		}else{
			$this->packName = basename($this->url->RemoveParams()->Save());
		}
        
        $this->packName = preg_replace('#[\:\+\;\?\*\"\<\>\|]#', '_', $this->packName);
	}

	private function _ResolvePackName(DOMXPath $xp){
		$title = $xp->evaluate("string(//head//title)");

		if (strlen($title) > 0){
			$this->packName = $title;
		}else{
			$this->packName = basename($this->url->RemoveParams()->Save());
		}

        $this->packName = preg_replace('#[\:\+\;\?\*\"\<\>\|]#', '_', $this->packName);
	}

	public function CreateZipPackage(){
		$zip = new zipFile();
		
        // Soporte para cache
        $cache = CacheManager::getManager();
        if ($cache != NULL)
            $jsonData = array(
                "cache_uid" => $cache->getCacheUid(),
                "task_uid" => $cache->getTaskUid(),
                "confirmed_tasks" => $cache->getConfirmedTasks(),
                "store" => array(),
                "inflate" => array()
            );
        
		foreach($this->files as /** @var WebFile */ $webFile){
            if ($cache != NULL && preg_match('#^@md5=([0-9a-fA-F]{32})$#', $webFile->content, $m)){
                // Este recurso ya esta guardado en cache del cliente
                $jsonData["inflate"][] = array( "md5" => $m[1], "path" => $webFile->name );
            }else{
                // No esta guardado en cache
                $zip->addFromString($webFile->name, $webFile->content);
                
                // Si esta definido $webFile->md5 es que se decidio 
                // almacenar el archivo en cache
                if ($webFile->md5 && ($cache != NULL)){
                    $jsonData["store"][] = array( "md5" => $webFile->md5, "path" => $webFile->name );
                }
            }
		}
        
        // Agregar informacion de cache
        if ($cache != null){
            $zip->AddFromString("cache.json", json_encode($jsonData));
        }
		
        if ($this->_sendMetadata){
            // Request data
            $zip->AddFromString(".metadata/metadata.json", json_encode($this->_responseMetadata));
            
            // User data
            if (SEND_USER_METADATA){
                $userData = array();
                if (defined('META_USER_EXPIRATION')){
                    $userData['expiration'] = META_USER_EXPIRATION;
                }
                
                $zip->AddFromString(".metadata/user.json", json_encode($userData));
            }
        }
        
		return $zip->Save();
	}
    
    private function urlencode_keep_slashes($path){
        return str_replace("%2F", "/", urlencode($path));
    }
}