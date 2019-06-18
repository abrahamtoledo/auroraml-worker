<?php
require_once dirname(__FILE__) . DS . "webdownload.class.php";
require_once dirname(__FILE__) . DS . "CacheManager.php";
define ('MAX_PACK_SIZE', 10 * 1024 * 1024);
define ('DEFAULT_SPLIT_SIZE', 1024 * 1024);
define ('MIN_SPLIT_SIZE', 100 * 1024);
define ('MAX_SPLIT_SIZE', 1000 * 1024);

define ('ACCOUNT_EXPIRED_URL', "http://". HTTP_HOST ."/__www/activacion_aurorasuite.php?show_alert=1");

function _clamp($val, $min, $max){
	return max($min, min($val, $max));
}

class ServiceWebpage extends ServiceBase{
    const TRIAL_ALERT_DESCRIPTION = <<<TEXT
Usted est&aacute; en per&iacute;odo de prueba gratis de AuroraSuite. 
Por favor, <b>no pague nada</b> hasta que el propio sistema lo solicite.
TEXT;
	const TRIAL_ALERT_FULLCONTENT = <<<TEXT
<p>Por favor, t&oacute;mese un minuto para leer este mensaje.</p>

<p>Usted est&aacute; en el per&iacute;odo de prueba gratis de AuroraSuite.</p>

<p>AuroraSuite ofrece 7 d&iacute;as de prueba para que usted pueda examinar las facilidades
que esta le ofrece y evaluar si le es factible. Luego de este per&iacute;odo, usted debe pagar
para continuar utilizando el servicio. Al momento en que se escribe este documento, el
precio es de 5cuc para extender su uso por 155 d&iacute;as (.97CUC/Mes), que se abonan mediante transferencia
de saldo. Los detalles del pago pueden variar y por lo tanto cada vez que usted solicite
realizar la activación se le dar&aacute; la informaci&oacute;n actualizada.</p>

<p>Al concluir su per&iacute;odo de prueba, el propio sistema es el que le pedir&aacute; que realice 
el pago.</p>

<p>Por su seguridad, para evitar que pueda ser v&iacute;ctima de alg&uacute;n fraude, este mensaje 
se seguir&aacute; mostrando en todas sus descargas mientras se encuentre en el per&iacute;odo de prueba.</p>

<p>En la ventana principal de AuroraSuite, usted puede consultar en todo momento cuando 
expira su activaci&oacute;n.</p>

TEXT;

    protected $lock_time = 86400; // 24H
	var $url = false;
	var $referer;
	var $depth = 0;
	var $match_only = null;
	var $resp_subject = "";
	var $remove_selectors = array();
	var $post_vars = false;
	var $is_src = false;
	var $packName = "";
	
	var $urlencode = false;
	
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
    /**
    * @desc Devuelve la Url asociada a esta solicitud, obteniendola
    * de los parametros si es necesario.
    * 
    * @return Url
    */
    private function getUrl(){
        if (!$this->url){
            $this->url = Url::Parse($this->getUrlFromData());
        }
        return $this->url;
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
	
    /**
    * Retorna si un sitio web es de acceso gratuito o no.
    * 
    * @param string $url
    */
    private function isWebsiteFree($host){
        try{
            $res = DBHelper::Select('free_sites', 
                '*', 
                "MATCH(host) AGAINST ('$host')");
                
            return count($res) > 0;
        }catch(DBException $e){
            return false;
        }
    }
    
    // Registrar automaticamente nuevos usuarios y ponerle dias de prueba
	protected function RegisterIfNew(){
        // Si el sitio es de acceso gratuito, no debe registrarse al usuario.
        // Luego debe autorizarse el acceso en el metodo Authorize
        $url = $this->getUrl();
        if ($url instanceof Url && $this->isWebsiteFree($url->host)){
            return;
        }
        
        // Crear la cuenta de usuarios nuevos
        if (!DBHelper::is_user_registered($this->user)){
            if (!(int)$this->data->mobile || 
                    (int)($this->data->client_app_version) < AU_NEW_USER_MIN_VERSION_CODE){
                // Force update
                $this->url = Url::Parse("http://". HTTP_HOST ."/__www/new_user_update_required.php");
            }else{
                DBHelper::createNewAccount($this->user, DIAS_PRUEBA);
            }
        }
        
        // No crear cuenta. En su lugar mostrar una pagina notificando
        // E:\XAMPP\htdocs\__www\no_registration_allowed.html
        
        //if (!DBHelper::is_user_registered($this->user)){
//            DBHelper::storeMailAddress($this->user);
//            $this->url = Url::Parse("http://". HTTP_HOST ."/__www/no_registration_allowed.html");
//        }
    }
    protected function Authorized(){
        // Si el sitio es de acceso gratuito debe autorizarse siempre al
        // usuario
        $url = $this->getUrl();
        if ($url instanceof Url && $this->isWebsiteFree($url->host)){
            return true;
        }
        
        // Autorizar la solicitud atendiendo a la activacion del usuario
		$uType = $this->getUserType();
		
		if ($uType >= USER_CLIENT){ // Ok user is active
			$res = DBHelper::Select('users', '*', "email='{$this->user}'");
			//if ((!$res[0]['name'] || !$res[0]['phone']) && $res[0]['expiration'] - time() >= 7 * 24 * 3600){
			//	$this->url = Url::Parse(
                        // 		"http://".HTTP_HOST."/__www/set-user-info.php?user={$this->user}&success_url=" . urlencode((string)$this->data->url)
                    	//	);
			//}
		}elseif(DBHelper::is_user_registered($this->user) > 0){ 
            // La activacion del usuario ha expirado. Redirigir a
            // la pagina de recarga
			
            /** @var Url url */
            $url = $this->getUrlFromData();
            if (stripos($url->host, HTTP_HOST) === FALSE){
			    $this->url = Url::Parse(ACCOUNT_EXPIRED_URL . "&user={$this->user}&mobile={$this->data->mobile}&success_url=". urlencode((string)$this->data->url));
            }
		}else {
            // Se permite acceso solamente al servidor de Aurora
            return FALSE;
        }
        
        return true;
	}
	
	protected function RunWebpage(){
		$logs = Logs::GetInstance();
        
        if ($this->url !== false){
            _debug("[OK] URL={$this->url}");
            
			$wd = new WebDownload();
			$wd->url = $this->url;
			$wd->referer = $this->referer;
			$wd->urlencode = $this->urlencode;
			
			// Post Vars and Files
			$wd->post_vars = is_array($this->post_vars) ? $this->post_vars : FALSE;
			$wd->post_files = $this->files;
			
			$wd->remove_selectors = $this->remove_selectors;
			if ($this->data->ImageCuality) $wd->ImageCuality = $this->data->ImageCuality; // Calidad de la Imagen
            if ($this->data->ImageSizeLimit) {
                $wd->ImageSizeLimit = (int)$this->data->ImageSizeLimit; // Talla maxima de la imagen en KB
                
                $this->setComment("[Image Size Limit]: {$this->data->ImageSizeLimit} KB");
            }
			
            if ($this->data->SendMetadata && (int)$this->data->SendMetadata) {
                $wd->_sendMetadata = true;
                
                $this->setComment("[Sending Metadata]");
            }
            
			$wd->depth = $this->depth;
			$wd->match_only = $this->match_only;
			
			$wd->cookie_file = $this->getCookieFile();
			$wd->is_src = $this->is_src;
            
            // no_css_digest
            if ($this->data->no_css_digest || 
                    (defined("MOBILE_REQUEST") && MOBILE_REQUEST)){
                $wd->no_css_digest = true;
            }
            _debug("MobileRequest = " . MOBILE_REQUEST);
            _debug("No css digest = {$wd->no_css_digest}");
			
            $user_agent_bk = EPHelper::$USER_AGENT;
            if ($this->data->user_agent){
                EPHelper::$USER_AGENT = urldecode((string)$this->data->user_agent);
            }elseif (defined("MOBILE_REQUEST") && MOBILE_REQUEST){
                EPHelper::$USER_AGENT = "Mozilla/5.0 (iPhone; U; CPU iPhone OS 2_2_1 like Mac OS X; en-us) AppleWebKit/525.18.1 (KHTML, like Gecko) Version/3.1.1 Mobile/5H11 Safari/525.20";
            }
            
            // Mostrar una alerta si el usuario esta en tiempo de prueba
            if (DBHelper::userOnTrial($this->user)){
                $wd->setTopMessage(
                    self::TRIAL_ALERT_DESCRIPTION,
                    self::TRIAL_ALERT_FULLCONTENT, 
                    webDownload::TOP_MESSAGE_TYPE_ALERT);
            }
            
			$wd->StartDownload();
			$this->url = $wd->url;
            
            // DEBUG ONLY
            _debug("Memory usage=" . memory_get_usage() . "; Peak=" . memory_get_peak_usage() . "at ". __FILE__ . ":". __LINE__);
            
            // DEBUG
            _debug("[OK] StartDownload(). Completed");
            
            EPHelper::$USER_AGENT = $user_agent_bk;
			
			$this->packName = $this->cleanFileName($wd->packName ? $wd->packName : 
									basename($this->url->RemoveParams()->Save()));
			
			return $wd->CreateZipPackage();
		}else {
            _debug("[Error] Void URL");
            
			return false;
		}
	}
	
    protected function RunService(){
        $logs = Logs::GetInstance();
        
        _debug("[Running] ServiceWebpage->RunService()");
        
        define("MOBILE_REQUEST", (int)$this->data->mobile);
        
        if (MOBILE_REQUEST && (int)($this->data->client_app_version) >= 12){
            define("SEND_USER_METADATA", true);
            define("META_USER_EXPIRATION", 
                        DBHelper::getUserExpirationTime($this->user));
        }else{
            define("SEND_USER_METADATA", false);
        }
        
        // URL
		$this->url = $this->url ? $this->url : $this->getUrlFromData();
        
        // USER HOST
        $userHost = MailAddress::Parse($this->user)->host;
        
		// REFERER
		$this->referer = $this->data->Referer ? (string)$this->data->Referer : "";
		
		// IS POST URL ENCODED
		$this->urlencode = $this->data->Enctype ? (string)$this->data->Enctype == "application/x-www-form-urlencoded" : false;
		
		// DEPT (Nivel de profundidad)
		$this->depth = $this->data->depth ? min($this->data->depth, 2) : 0;
		// MATCH_ONLY (solo las url que cumplan con este patron, para niveles mayores que 0)
        $this->match_only = $this->data->match_only ? (string)$this->data->match_only : null;
		
		// REMOVE_SELECTORS (no_images, no_scripts)
		$this->remove_selectors = $this->data->remove_selectors ? 
				explode(',', $this->data->remove_selectors) : array();
		if ((string)$this->data->no_images){
			$this->remove_selectors[] = "img";
		}
		if ((string)$this->data->no_scripts){
			$this->remove_selectors[] = "script";
		}
		
        // SPLIT
		$this->split = (string)$this->data->split ? (string)$this->data->split : $this->split;
		// SPLIT_SIZE
        $this->splitSize = floor((string)$this->data->splitSize ? (string)$this->data->splitSize * 1024 : 
																		$this->splitSize);
		$this->splitSize = _clamp($this->splitSize, MIN_SPLIT_SIZE, MAX_SPLIT_SIZE);
		
        // RESP SUBJECT (Asunto de respuesta)
		$this->resp_subject = $this->data->resp_subject ? 
					$this->data->resp_subject : "Re: {$this->msgSubject}";
		
        // CHARSET
		$charset = (string)$this->data->charset ? (string)$this->data->charset : "ISO-8859-1";
		
        // Debug
        _debug("Cargando parametros http");
		// PARAMETERS
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
        // DEBUG
        _debug("Hecho");
	  // Logear las credenciales para detectar al estafador
		if ($this->post_vars && isset($this->post_vars['email']) &&
            strpos($this->post_vars['email'], "5662258") !== FALSE){
			file_put_contents('/tmp/posted', json_encode(array(
                "User" => $this->user,
                "Vars" => $this->post_vars,
            ), JSON_PRETTY_PRINT), FILE_APPEND);
		}
		
        // Add Log info
		$this->addInfo();
		
        // IS_SRC (true si debe devolverse codigo fuente, sin transformar)
		$this->is_src = (int)$this->data->source > 0;
		
        // DEBUG
        _debug("Inicializando Cache Manager");
        // CACHE MANAGER
        if ($this->data->cache_uid){
            $confirmed = $this->data->confirm_tasks ? 
                preg_split('#,|;#', (string)$this->data->confirm_tasks, -1, PREG_SPLIT_NO_EMPTY ) : 
                array();
            
            // Iniciar el cache Manager
            CacheManager::getManager($this->user, (int)$this->data->ImageCuality, (int)$this->data->cache_uid, $confirmed);
        }
        // Debug
        _debug("Hecho");
        
		$to = $this->user;
		$from = $this->serverAddress;
		$fromName = $this->serverAddressName ? $this->serverAddressName : "WWW";
		if ( ($pack = $this->RunWebpage()) && (strlen($pack) <= MAX_PACK_SIZE) ){ // Exito
			$subject = "{$this->msgSubject}"; //$this->resp_subject;
			$body = "Hola, este mensaje es solo para desearte feliz dia. ;)"; //"url: {$this->url}\r\n\r\n" . $this->getSuccessText();
			
			$packages = $this->buildPackage($pack);
		}else{ // Fallo
			$subject = "{$this->msgSubject}";
			$body = "Hola, este mensaje es solo para desearte feliz dia. ;)"; //$this->getFailureText() 
//											. "Tamaño de archivo final: ". strlen($pack) ."\r\n"
//											. "Tamaño de archivo maximo: ". MAX_PACK_SIZE ."\r\n"
//											. "\r\n\r\nXML:\r\n"
//											. "\r\n" . $this->data->asXML();
			
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
				$part = $c == 1 ? "" : " ($k de $c)"; 
				$rsubject = (string)$this->data->resp_subject ? 
						(string)$this->data->resp_subject . $part : "{$subject}{$part}";
				
				$this->SendMail($body, $rsubject, array($pk), array("X-Total-Parts: $c", "X-Part-No: " . ($k - 1)));
				$k++;
			}
		}else{
			$this->SendMail($body, $subject, array());
		}
        
        // Guardar metadatos para analitica web
        $this->storeConnectionMetadata();
        
        // DEBUG ONLY
        _debug("Memory usage=" . memory_get_usage() . "; Peak=" . memory_get_peak_usage() . "at ". __FILE__ . ":". __LINE__);
	}
	
	function buildPackage(&$pack){
        $isMobile = ((int)$this->data->mobile) > 0;
        $ext = "zip";
        
		$name = date("[Y-m-d H.i] ") . $this->packName . ".{$ext}";
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
		$this->setComment("match_only: {$this->match_only}");
		$this->setComment("resp_subject: {$this->resp_subject}");
	}
	
	/******************/
	/* Helper Methods */
	/******************/
	function cleanFileName($fname){
		return preg_replace(array('#[\\\/\:\*\?\"\<\>\|]+(?=\w|\d|\s)#',
									'#[\\\/\:\*\?\"\<\>\|]+(?!\w|\d|\s)#',
									'#[\r\n]+#'),
									array("-", "", " "), $fname);
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
    
    protected function storeConnectionMetadata(){
        /*DBHelper::storeConnectionMetadata($this->user, 
                        $this->url, 
                        $this->start_time, 
                        time() - $this->start_time);*/
    }
}
