<?php
abstract class RequestHandler{
    const MAX_SIMULTANEOUS_REQUEST = 10;
    
    /** @var webFile */
	var $webFile = NULL;
	var $cookieFile = NULL;
    
    var $transferInfo = NULL;
    var $responseHeaders = NULL;
    
    /**
    * Factory, crea una instancia apropiada de un RequestHandler
    * @param WebFile
    * @param string
    * @return RequestHandler
    */
	public static function CreateInstance($webFile, $cookieFile){
		$logs = Logs::GetInstance();
        if (_DEBUG_){
            $logs->addEntry("[Running] RequestHandler->CreateInstance(); URL={$webFile->url}");
        }
        
		$requestHandler = new DefaultRequestHandler;
		
        // Google Search, javascript fix
        if ( stripos($webFile->url->host, ".google.") !== FALSE){
            $requestHandler = new GoogleSearchHandler();
        }
        // Dv Lottery Fix
        elseif (strcasecmp($webFile->url->host, "www.dvlottery.state.gov") == 0){
            $requestHandler = new DvLotteryHandler();
        }
        // Facebook Fix
        elseif(($webFile->url->host == "www.facebook.com") || 
                ($webFile->url->host == "facebook.com") || 
                ($webFile->url->host == "lm.facebook.com") || 
                ($webFile->url->host == "fb.com")){
            $requestHandler = new FacebookRequestHandler();
        }
        // Insertar Revolico Fix
//        elseif( (($webFile->url->host == "www.revolico.com") ||
//                ($webFile->url->host == "lok.myvnc.com")) &&
//                 (stripos($webFile->url->path, "/insertar-anuncio.html") !== FALSE) ){
//            $requestHandler = new RevolicoInsertHandler();
//        // Arreglar Revolico para movil (css)
//        }
//        elseif($webFile->url->host == "www.revolico.com" &&
//                    defined('MOBILE_REQUEST') && MOBILE_REQUEST){
//            $requestHandler = new RevolicoMobileHandler();
//        }
		
		$requestHandler->webFile = $webFile;
		$requestHandler->cookieFile = $cookieFile;
		
		return $requestHandler;
	}
    
    /**
    * @desc Realiza la(s) peticion(es) http almacenada(s) en $requestHandler
    * @param mixed (RequestHandler | RequestHandler[])
    */
    public static function PerformRequest($requestHandler){
        $logs = Logs::GetInstance();
        if (_DEBUG_){
            $logs->addEntry("[Running] RequestHandler->PerformRequest()");
        }
        
        if (is_array($requestHandler)){
            
            $lUrl = array();
            $tInfo = array();
            $cookieFile = NULL;
            $referer = NULL;
            
            foreach($requestHandler as /** @var RequestHandler */ $request){
                // DEBUG
                if (_DEBUG_){
                    $logs->addEntry("[Running] RequestHandler->onBeforeRequest()");
                }
                // ON BEFORE
                $request->onBeforeRequest($cancel);
                
                if ($cancel) 
                    continue;
                
                $lUrl[] = $request->webFile->url->Save();
                
                if (!$cookieFile){
                    $cookieFile = $request->cookieFile;
                }
                
                if (!$referer){
                    $referer = $request->webFile->referer;
                }
            }
            
            if (!count($lUrl))
                return;
            
            // DEBUG
            if (_DEBUG_){
                $logs->addEntry("[Running] EPHelper::MultiGetUrl()");
            }
            
            $contents = @EPHelper::MultiGetUrl($lUrl, WEBPAGE_TIMEOUT, $tInfo, $cookieFile, self::MAX_SIMULTANEOUS_REQUEST, $referer);
            
            $c = count($lUrl);
            for($i = 0; $i < $c; $i++){
                $request = $requestHandler[$i];
                
                $request->transferInfo = $tInfo[$i];
                $request->webFile->content = $contents[$i];
                
                // DEBUG
                if (_DEBUG_){
                    $logs->addEntry("[Running] RequestHandler->onAfterRequest()");
                }
                // ON AFTER
                $request->onAfterRequest();
            }
            
        }else{
            $request = $requestHandler;
            
            // DEBUG
            if (_DEBUG_){
                $logs->addEntry("[Running] RequestHandler->onBeforeRequest()");
            }
            $request->onBeforeRequest($cancel);
            
            if ($cancel)
                return;
            
            $request->transferInfo = array();
            if (!$request->webFile->post_vars){
                // DEBUG
                if (_DEBUG_){
                    $logs->addEntry("[Running] EPHelper::GetURL()");
                }
                $request->webFile->content = @EPHelper::GetURL($request->webFile->url->Save(), WEBPAGE_TIMEOUT, $request->transferInfo, $request->cookieFile, $request->webFile->referer, $request->responseHeaders);
            }else{
                // DEBUG
                if (_DEBUG_){
                    $logs->addEntry("[Running] EPHelper::PostURL()");
                }
                $request->webFile->content = @EPHelper::PostURL($request->webFile->url->Save(), $request->webFile->post_vars, $request->webFile->post_files, WEBPAGE_TIMEOUT, $request->transferInfo, $request->cookieFile, $request->webFile->referer, $request->webFile->urlencoded, $request->responseHeaders);
            }
            
            // DEBUG
            if (_DEBUG_){
                $logs->addEntry("[Running] RequestHandler->onAfterRequest()");
            }
            $request->onAfterRequest();
        }
    }
	
	/**
    * @desc Se ejecuta antes de realizar el Request
    */
    abstract protected function onBeforeRequest(&$cancel = false);
    
    /**
    * @desc Se ejecuta despues de realizar el Request
    */
	abstract protected function onAfterRequest();
}

class DefaultRequestHandler extends RequestHandler{
	protected function onBeforeRequest(&$cancel = false){
        // Cache
        $cache = CacheManager::getManager();
        if ($cache != null && $cache->isCached($this->webFile->url)){
            $cancel = true;
            $this->webFile->content = "@md5=" . md5($this->webFile->url);
            //return;
        }
        
        $cancel = false;
    }
    
    protected function onAfterRequest(){
        // Asignar la url final y Content-Type
        $this->webFile->url = Url::Parse($this->transferInfo['url']);
        $this->webFile->contentType = $this->transferInfo['content_type'];
        
        // Cache
        $cache = CacheManager::getManager();
        if ($cache != null){
            // Comprobar de nuevo si el recurso estaba en cache. Lidia con URL redireccionadas
            if ($cache->isCached($this->webFile->url)){
                $this->webFile->content = "@md5=" . md5($this->webFile->url);
                return;
            }else{
                if ($cache->store($this->webFile->url, $this->webFile->contentType, $this->responseHeaders, $md5)){
                    $this->webFile->md5 = $md5;
                }
            }
        }
        
		// Content-Disposition para el caso en que es una descarga
        if ($this->responseHeaders != null && $this->responseHeaders->containsHeader("Content-Disposition")){
            $disposition = $this->responseHeaders->getHeader("Content-Disposition");
            $parts = explode(";", $disposition);
            if (strtoupper(trim($parts[0])) == "ATTACHMENT"){
                foreach($parts as $p){
                    list($key, $val) = explode("=", trim($p), 2);
                    if (strtoupper(trim($key)) == "FILENAME"){
                        $this->webFile->attFileName = trim($val, " \t\"");
                    }
                }
            }
        }
	}
}

class GoogleSearchHandler extends DefaultRequestHandler {
    public function onBeforeRequest(&$cancel = false){
        // Patch Url
        if ($this->webFile->url->path == "/search"){
            $this->webFile->url->AddParams(array("gbv" => 1));
        }elseif ($this->webFile->url->path == "/url"){
            $this->webFile->url = Url::Parse($this->webFile->url->params['q']);
        }elseif($this->webFile->url->path == "/imgres"){
            $this->webFile->url = Url::Parse($this->webFile->url->params['imgrefurl']);
        }
        
        parent::onBeforeRequest($cancel);
    }
}

class FacebookRequestHandler extends DefaultRequestHandler {
    protected function onBeforeRequest(&$cancel = false){
        if ($this->webFile->url->host == "lm.facebook.com"){
            $this->webFile->url = Url::Parse( $this->webFile->url->params['u'] );
        }elseif ($this->webFile->url->path == "/" || $this->webFile->url->path == ""){
            $this->webFile->url->host = "m.facebook.com";
        }
        
        parent::onBeforeRequest($cancel);
    }
}

class DvLotteryHandler extends DefaultRequestHandler{
    // Override
    public function onAfterRequest(){
        if (strcasecmp( $this->webFile->contentType, "text/css" ) == 0){
            $this->webFile->contentType = "text/plain";
        }
        
        parent::onAfterRequest();
    }
}

class RevolicoInsertHandler extends DefaultRequestHandler {
    // Override
    protected function onAfterRequest(){
        $doc = new DOMDocument();
        $doc->preserveWhiteSpace = true;
        if (!@$doc->loadHTML($this->webFile->content)){
            return;
        }
        $xpath = new DOMXPath($doc);
        
        // Si no hay formulario entonces no haremos ninguna modificacion
        if ($xpath->query("//form")->length == 0){
            return;
        }
        
        //Logs::GetInstance()->addEntry("Se cargo el documento y tiene formulario");
        
        /** @var DOMElement Contenedor de recaptcha */
        $captchaContainer = $xpath->query('//div[noscript]')->item(0);
        //Logs::GetInstance()->addEntry("Captcha container: " . var_export($captchaContainer, true));
        
        if ($captchaContainer == NULL)
            return;
        
        // Obtenemos el challenge y creamos el widget manualmente
        $doc2 = new DOMDocument();
        if (!@$doc2->loadHTML( $this->getRecaptchaWidget( $this->getChallenge() ) )){
            Logs::GetInstance()->addEntry(libxml_get_last_error()->message);
            return;
        }
        //Logs::GetInstance()->addEntry("Se creo documento 2");
        
        try{
            $widgetNode = $doc->importNode($doc2->documentElement, true);
        }catch (DOMException $ex){
            Logs::GetInstance()->addEntry($ex->getMessage());
        }
        
        if (!$widgetNode)
            return;
        
        //Logs::GetInstance()->addEntry("Se logro copiar widget");
        
        // Reemplazamos el area recaptcha por la nuestra
        $captchaContainer->parentNode->replaceChild($widgetNode, $captchaContainer);
        
        $this->webFile->content = $doc->saveHTML();
        
        parent::onAfterRequest();
    }
    
    private function getChallenge(){
        // Chalenge API: Obtenemos el primer challenge, este es un cebo
        $key="6LdFutASAAAAAIjbHhYyry8yS8OT8KxMAP3nDsZn";
        $js = EPHelper::GetUrl("http://www.google.com/recaptcha/api/challenge?hl=es&k={$key}");

        $lines = preg_split('(\r|\n)', $js, -1, PREG_SPLIT_NO_EMPTY);
        for($i = 0; $i < count($lines); $i++){
            if ("challenge" == strtok($lines[$i], " : ")){
                $challenge = strtok(" : '");
                break;
            }
        }

        // Ahora recargamos 'reload' para obtener el segundo y verdadero chalenge.
        $p = array();
        $p[] = "lang=es";
        $p[] = "k={$key}";
        $p[] = "c={$challenge}";
        $p[] = "reason=i";
        $p[] = "type=image";

        $js = EPHelper::GetUrl("http://www.google.com/recaptcha/api/reload?" . implode('&', $p));
        strtok($js, "'");
        $challenge = strtok("'");
        return $challenge;
    }
    
    private function getRecaptchaWidget($challenge){
return <<<WID
<!-- Recaptcha widget -->
<div class="captcha_container captcha ">
<div class=" recaptcha_nothad_incorrect_sol recaptcha_isnot_showing_audio" id="recaptcha_widget_div" style="">
    <div id="recaptcha_area">
        <table id="recaptcha_table" class="recaptchatable recaptcha_theme_clean">
            <tbody>
                <tr height="73">
                    <td class="recaptcha_image_cell" width="302">
                        <center>
                            <div style="width: 300px; height: 57px;" id="recaptcha_image">
                                <img id="recaptcha_challenge_image" alt="Pista de imagen reCAPTCHA" src="http://www.google.com/recaptcha/api/image?c=$challenge" height="57" width="300">
                            </div>
                        </center>
                    </td>
                    <td style="padding: 10px 7px 7px 7px;">
                        <a title="Obtener una pista nueva" id="recaptcha_reload_btn"><img src="http://www.google.com/recaptcha/api/img/clean/refresh.png" id="recaptcha_reload" alt="Obtener una pista nueva" height="18" width="25"></a><a title="Obtener una pista sonora" id="recaptcha_switch_audio_btn" class="recaptcha_only_if_image"><img src="http://www.google.com/recaptcha/api/img/clean/audio.png" id="recaptcha_switch_audio" alt="Obtener una pista sonora" height="15" width="25"></a><a title="Obtener una pista visual" id="recaptcha_switch_img_btn" class="recaptcha_only_if_audio"><img src="http://www.google.com/recaptcha/api/img/clean/text.png" id="recaptcha_switch_img" alt="Obtener una pista visual" height="15" width="25"></a><a title="Ayuda" id="recaptcha_whatsthis_btn"><img alt="Ayuda" src="http://www.google.com/recaptcha/api/img/clean/help.png" id="recaptcha_whatsthis" height="16" width="25"></a>
                    </td>
                    <td style="padding: 18px 7px 18px 7px;">
                        <img src="http://www.google.com/recaptcha/api/img/clean/logo.png" id="recaptcha_logo" alt="" height="36" width="71">
                    </td>
                </tr>
                <tr>
                    <td style="padding-left: 7px;">
                        <div class="recaptcha_input_area" style="padding-top: 2px; padding-bottom: 7px;">
                            <span style="display: none;" id="recaptcha_challenge_field_holder"><input name="recaptcha_challenge_field" id="recaptcha_challenge_field" value="$challenge" type="hidden"></span><input autocomplete="off" placeholder="Introduzca el texto" style="border: 1px solid #3c3c3c; width: 302px;" name="recaptcha_response_field" id="recaptcha_response_field" type="text">
                        </div>
                    </td>
                    <td colspan="2">
                        <span id="recaptcha_privacy" class="recaptcha_only_if_privacy"></span>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<!-- end Recaptcha widget -->
</div>
WID;
    }
}

class RevolicoMobileHandler extends DefaultRequestHandler{
    protected function onAfterRequest(){
        parent::onAfterRequest();
        
        //Logs::GetInstance()->addEntry("Fix Revolico CSS: {$this->webFile->url->path} - {$this->webFile->contentType}");
        
        if (stripos($this->webFile->url->path, "insertar-anuncio.html") === FALSE &&
            stripos($this->webFile->url->path, "modificar-anuncio.html") === FALSE &&
            stripos($this->webFile->contentType, "text/html") !== FALSE){
            
            //Logs::GetInstance()->addEntry("Creando en DOM");
                
            $doc = new DOMDocument();
            $doc->preserveWhiteSpace = true;
            if (!@$doc->loadHTML($this->webFile->content)){
                return;
            }
            
            //Logs::GetInstance()->addEntry("Buscando head");
            
            $head = $doc->getElementsByTagName("head");
            if ($head->length == 0)
                return;
            
            $head = $head->item(0);
            
            //Logs::GetInstance()->addEntry("Agregando Viewport meta tag");
            
            $viewport = $doc->createElement("meta");
            $viewport->setAttribute("name", "viewport");
            $viewport->setAttribute("content", "width=device-width");
            $head->appendChild($viewport);
            
            //Logs::GetInstance()->addEntry("Agregando Estilo");
            
            $mobileCss = $doc->createElement("link");
            $mobileCss->setAttribute("rel", "stylesheet");
            $mobileCss->setAttribute("href", "https://". HTTP_HOST ."/__www/rev/mobile.css");
            $mobileCss->setAttribute("media", "all");
            
            $head->appendChild($mobileCss);
            
            //Logs::GetInstance()->addEntry("Guardando el contenido");
            
            $this->webFile->content = $doc->saveHTML();
        }
    }
}