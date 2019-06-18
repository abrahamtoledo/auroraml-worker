<?php
define('WEBPAGE_TIMEOUT', 30);
define('WEBPAGE_DIR', dirname(__FILE__));
define('CACHE_DIR', WEBPAGE_DIR . "/cache");

require_once WEBPAGE_DIR . DS . "UrlPatches.php";

class Cache{
	public static function getContent($url, &$content, &$type){
		switch ($url){
			case "http://s455335975.onlinehome.us/frontend.php/images/favicon.ico":
				$type = "image/x-icon";
				$content = CACHE_DIR . "/" . basename($url);
				return true;
			case "http://s455335975.onlinehome.us/frontend.php/templates/adsterix_backend_template/images/home_logo.png":
				$type = "image/png";
				$content = CACHE_DIR . "/" . basename($url);
				return true;
			case "http://s455335975.onlinehome.us/frontend.php/templates/adsterix_backend_template/css/template_css.css":
			case "http://s455335975.onlinehome.us/frontend.php/templates/adsterix_backend_template/css/css_color.css":
			case "http://s455335975.onlinehome.us/frontend.php/components/com_adsterix/css/adsterix.css":
			case "http://s455335975.onlinehome.us/frontend.php/components/com_adsterix/css/adsterix_color.css":
				$type = "text/css";
				$content = CACHE_DIR . "/" . basename($url);
				return true;
			default:
				return false;
		}
	}
}

class webFile{
	public $root = false;
	public $url = ""; 
    public $name = ""; 
	public $depth = 0;
    public $content = "";
	
	function __construct($params = array()){
		$this->root = $params['root'] ? $params['root'] : false;
		$this->url = $params['url'] ? $params['url'] : "";
		$this->name = $params['name'] ? $params['name'] : "";
		$this->depth = $params['depth'] ? $params['depth'] : 0;
		$this->content = $params['content'] ? $params['content'] : "";
	}
}

class CssImportsCallback{
	var $webFile;
	var $webDownloadInstance;
	var $c_webFile;
	var $prefix;
	
	function __construct($webFile, &$webDownloadInstance, &$c_webFile, $prefix = ""){
		$this->webFile = 				&$webFile;
		$this->webDownloadInstance =    &$webDownloadInstance;
		$this->c_webFile =              &$c_webFile;
		$this->prefix = 				$prefix;
	}
	
	function CallBack($match){
		$tWebFile = new webFile(array('url'=> $this->webFile->url->ComputeNewUrl(trim($match[1], "\"\'"))));
		
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

	var $url;
	var $post_vars = NULL;
	var $cookie_file = NULL;
	var $remove_selectors = array();
	var $depth = 0;
	var $match_only = null;
	var $is_src = false;
	
	
	var $file_map = array();
	var $files = array();
	
	var $packName = "";
	
	
	public function __construct(){
	}
	
	public static function ApplyUrlPatches($url){
		return UrlPatches::GetInstance()->ApplyPatches($url);
	}
	
	public function StartDownload(){
		$this->url = self::ApplyUrlPatches($this->url);
		$this->Download(new webFile(array(
										'url' => $this->url, 
										'depth' => $this->depth, 
										'root' => true)
									), 
									$this->post_vars,
									$this->url->RemoveParams()->Save());
	}
	
	protected function Download($webFile, $post_vars = NULL, $referer = ""){
		if (is_array($webFile)){
			$this->DownloadCollection($webFile, $referer);
		}else{
			$this->DownloadSingle($webFile, $post_vars, $referer);
		}
	}
	
	// Aclaraciones: 
	// Esta funcion solo puede descargar recursos usando metodo GET
	protected function DownloadCollection($c_webFile, $referer = ""){
		$c_strUrl = array(); 
		foreach($c_webFile as $webFile)  {
			$c_strUrl[] = $webFile->url && $webFile->depth >= 0 ? 
							self::ApplyUrlPatches($webFile->url)->Save() : "";
		}
		
		$contents = @EPHelper::MultiGetUrl($c_strUrl, WEBPAGE_TIMEOUT, $transfer_info, $this->cookie_file, 10, $referer);
		
		$c = count($c_webFile);
		for($i = 0; $i < $c; $i++){
			$c_webFile[$i]->url = Url::Parse($transfer_info[$i]['url']);
			// $lfile->name = $lfile->name == "" ? $c_url[$i]->FileName() : $lfile->name;
			// $lfile->name = $lfile->name == "" ? "document.html" : $lfile->name;
			if (!$c_webFile[$i]->url) return;
			$c_webFile[$i]->content = $contents[$i];
			$this->Digest($c_webFile[$i], $transfer_info[$i]['content_type']);
		}
	}
	
	// Aclaraciones: 
	// Esta funcion solo puede descargar recursos usando metodo GET y POST
	protected function DownloadSingle($webFile, $post_vars, $referer = ""){
		// Apply Url Changes(Patches) to Url's with no other solution
		$webFile->url = self::ApplyUrlPatches($webFile->url);
        if (!$webFile->url || $webFile->depth < 0) return;
		
		// Download the Resource
		$transfer_info = array();
		if (!$post_vars){
			$webFile->content = @EPHelper::GetURL($webFile->url->Save(), WEBPAGE_TIMEOUT, $transfer_info, $this->cookie_file, $referer);
		}else{
			$webFile->content = @EPHelper::PostURL($webFile->url->Save(), $post_vars, WEBPAGE_TIMEOUT, $transfer_info, $this->cookie_file, $referer);
		}
		
		$webFile->url = Url::Parse($transfer_info['url']);
		
        if ($webFile->url){
		    $this->Digest($webFile, $transfer_info['content_type']);
        }
	}
	
	protected function Digest($webFile, $content_type){
		$c_type = strtolower(strtok($content_type, ";"));
		
		$c_type = $c_type ? $c_type : $this->gessContentType($webFile);
		
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
			
			default:
				$this->DigestUnknown($webFile);
				break;
		}
	}
	
	protected function digestHTML($webFile, $content_type){
		$this->tryResolveFileName($webFile, "", "html");
		
		if ($this->is_src) return;
		
		$dom = str_get_html($webFile->content);
		
		if ($webFile->root) $this->ResolvePackName($dom);
		
		// Add Charset && content type meta tags
		if (count($dom->find("meta[name=http-equiv]")) == 0){ 
			
		}
		
		if (preg_match('#charset\=(.*)#', $content_type, $m)){
			
		}
		
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
				$elem->outertext = "";
			
			$strDom = $dom->Save(); $dom->clear();
			$dom = str_get_html($strDom);
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
				$newUrl = (strcasecmp($key, "action") == 0 && !$elem->$key) ? $webFile->url : $base->ComputeNewUrl($elem->$key);
				if ($newUrl)
					$elem->$key = $newUrl->Save();
			}
		}
		
		// 2) Get Dependencies
		$c_webFile = array();
		
		$depend = array("*[src]", "link[rel=stylesheet]", "link[rel=shortcut icon]");
		if ($webFile->depth > 0) $depend[] = "a[href]";
		foreach($dom->find( implode(',', $depend) ) as $elem){
			
			// Download only page links with matching urls
			if (strtolower($elem->tag) == "a" && $this->match_only != null &&
				!preg_match($this->match_only, $elem->href)){
				continue;
			}
			
			$key = $elem->src ? "src" : "href";
			
			if (($url = Url::Parse($elem->$key)) !== false){
				if ($tWebFile = $this->getMapFile($url)){ // File Already Downloaded
					$elem->$key = $this->getRelativePath($webFile->name, $tWebFile->name);
				}else{                                                                       
					$depth = strtolower($elem->tag) == "a" ? $webFile->depth - 1 : $webFile->depth;
					$tWebFile = new webFile(array('url'=> $url, 'depth' => $depth));
                    
					$this->tryResolveFileName($tWebFile, $webFile->name . "_files/", false, $elem->tag);
					$elem->$key = $this->getRelativePath($webFile->name, $tWebFile->name);
					
					$c_webFile[] = $tWebFile;
				}
			}
		}
		
		// 2.1) Inline css imports
		foreach($dom->find("style") as $elem){
			if (!in_array("img", $this->remove_selectors)){
				$regexp = '#url\(([^\)]+)\)#';
				$prefix = "";
			} else{
				$regexp = '#import\s+url\(([^\)]+)\)#';
				$prefix = "import ";
			}
			
			$callback = new CssImportsCallback($webFile, $this, $c_webFile, $prefix);
			$elem->innertext = preg_replace_callback($regexp, array($callback, "Callback"), $elem->innertext);
		}
		
		// 3) Add client side transforms
		$head = $dom->find("head", 0);
		$head->innertext .= $this->getAuxHeaders();
		
		// End (TED)
		
		// Download dependencies before saving the page
		$this->Download($c_webFile, $webFile->url->RemoveParams()->Save());
		
		// Save File
		$webFile->content = $dom->Save();
		$dom->clear();
		//$this->saveToArchive($webFile);
		
		// get Opensearch plugin
		/*if ($webFile->root)*/ $this->getOpenSearch($webFile, $base);
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
		
		if (count($c_webFile) > 0) $this->Download($c_webFile);

		//$this->saveToArchive($webFile);
	}
	protected function digestJS($webFile){
		$this->tryResolveFileName($webFile, "", "js");
		//$this->saveToArchive($webFile);
	}
	protected function digestUnknown($webFile){
		$this->tryResolveFileName($webFile);
		//$this->saveToArchive($webFile);
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
	
	protected function getAuxHeaders(){
		$saddr = SERVICE_ADDRESS;
		$res = <<<HEAD
<link rel="stylesheet" href="chrome://catalyst/content/css/jquery-ui-1.7.1.custom.css" media="all" />
<link rel="stylesheet" href="chrome://catalyst/content/css/transforms.css" media="all" />

<script type="text/javascript" src="chrome://catalyst/content/js/jquery-1.3.2.min.js"></script>
<script type="text/javascript" src="chrome://catalyst/content/js/jquery-ui-1.7.1.custom.min.js"></script>
<script type="text/javascript" src="chrome://catalyst/content/js/transforms.js"></script>

<meta name="saddr" content="$saddr"/>
HEAD;
	return $res;
	}
	
	protected function getOpenSearch(&$webFile, $base){
		$dom = str_get_html($webFile->content);
		foreach($dom->find("link[rel=search]") as $tag){
			if (!empty($tag)){
				$fname = preg_replace('#[\\\/\:\*\?\"\<\>\|\s]+#', '', $tag->title) . ".xml";
				$fname = $fname != ".xml" ? $fname : "opensearch.xml";
				$url = $base->computeNewUrl($tag->href);
				
				// Search Plugin
				$swebFile = new webFile();
				$swebFile->name = "_search/$fname";
				$swebFile->content = EPHelper::GetUrl($url->Save(), 4);
				
				// Resolve Icon
				// $xml = simplexml_load_string($swebFile->content);
				// if ($url = Url::Parse((string)$xml->Image)){
					// $xml->Image = "data:{$xml->Image['type']};base64," . 
									// base64_encode(EPHelper::GetUrl($url->Save(), 1));
				// }
				// $swebFile->content = $xml->asXML();
				$this->saveToArchive($swebFile);
				
				// Installer (only one copy)
				if (!isset($binWebFile)){
					$binWebFile = new webFile();
					$binWebFile->name = "_search/AddSearchEngine";
					$binWebFile->content = file_get_contents(dirname(__FILE__) . "/tools/AddSearchEngine.bn");
					
					$this->saveToArchive($binWebFile);
				}
			}
		}
		$dom->clear();
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
	
	// Resolves the webFile file name if not yet resolved
	public function tryResolveFileName(&$webFile, $dir = "", $ext = false, $tag = false){
		if (!$webFile->name){
			$this->resolveFileName($webFile, $dir, $ext, $tag);
			$this->addToFileMap($webFile);
		}
	}
	public function resolveFileName(&$webFile, $dir = "", $ext = false, $tag = false){
		if ($dir && !preg_match('#/|\\$#', $dir)) $dir .= "/";
		
		$webFile->name = $webFile->url->FileName();
		$webFile->name = ($webFile->name != "" ? $webFile->name : "document");
		
		if ($ext) {
			$webFile->name = preg_match('#'.preg_quote(".$ext", "#").'$#', $webFile->name) ? 
							$webFile->name : "{$webFile->name}.{$ext}";
		}elseif($tag){
			// Using Euristics to attempt to determine the filetype based on tag
			if (in_array($tag->tag, array('frame', 'iframe', 'a'))){
				$webFile->name = preg_replace('#\.(php|php5|php3|phpx|asp|aspx|aspn|cgi)$#imxs', 
																		'.html', $webFile->name);
				if (strpos($webFile->name, ".") === false) $webFile->name .= ".html";
			}

			if (in_array($tag->tag, array('img')) && 
				!preg_match('#\.(bmp|dib|gif|jpg|jpeg|png|tga|svg)$#imxs', $webFile->name)){
				$webFile->name .= ".bmp";
			}

			if (strtolower($tag->tag) == "link" && 
				strtolower($tag->rel) == "stylesheet"){
				$ext = "css";
				$webFile->name = preg_match('#'.preg_quote(".$ext", "#").'$#', $webFile->name) ? 
							$webFile->name : "{$webFile->name}.{$ext}";
			}
			
			if (strtolower($tag->tag) == "link" && 
				strtolower($tag->rel) == "shortcut icon"){
				$ext = "ico";
				$webFile->name = preg_match('#'.preg_quote(".$ext", "#").'$#', $webFile->name) ? 
							$webFile->name : "{$webFile->name}.{$ext}";
			}
			
			if (strtolower($tag->tag) == "link" && 
				strtolower($tag->rel) == "search"){
				$ext = "xml";
				$webFile->name = preg_match('#'.preg_quote(".$ext", "#").'$#', $webFile->name) ? 
							$webFile->name : "{$webFile->name}.{$ext}";
			}
			
			if (in_array($tag->tag, array('script'))){
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
	}
	public function CreateZipPackage(){
		$zip = new zipFile();
		
		foreach($this->files as $webFile){
			$zip->addFromString($webFile->name, $webFile->content);
		}
		
		return $zip->Save();
	}
}