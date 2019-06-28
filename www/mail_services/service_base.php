<?php
define('SERVICES_DIR', dirname(__FILE__));

abstract class ServiceBase extends ServiceAbstract{
	
	private static function loadXml($str, &$errors = array()){
		$bk = libxml_use_internal_errors(true);
		$xmlDom = simplexml_load_string($str);
        if (!$xmlDom)
            $xmlDom = simplexml_load_string(mb_convert_encoding($str, "UTF-8"));
		
		if (!$xmlDom){
			foreach(libxml_get_errors() as $error){
				$errors[] = "error >> level: {$error->level}, code: {$error->code}, line: {$error->line}, column: {$error->column}, message: {$error->message}";
			}
			libxml_clear_errors();
		}
		libxml_use_internal_errors($bk);
		
		return $xmlDom;
	}
	
    // No registrar al usuario, sera necesario registrar manualmente.
    protected function RegisterIfNew(){ }
    
    var $serverDomain;
    
	var $data;
	var $files;
    var $attachments;
    
	var $msgBody;
	var $msgBodyDecoded;
    
    /**
    * @var MailMessage
    */
    var $msg;
    
    var $encrypted = FALSE;
    var $sKey = NULL;
    var $sIV = NULL;
    
    const BLOCK_SIZE = 16;
    const ENCRYPTED_KEY_SIZE = 128;
    
	public function __construct(){
	}
	
	protected function dumpFiles($indent = 0){
		$files = array();
		foreach($this->files as $fname => $file){
			$files[] = $this->Indent(
							"File $fname :\r\n" .
							"\tName: {$file['name']}\r\n" .
							"\tFile Name: {$file['tmp_name']}\r\n" .
							"\tSize: {$file['size']}\r\n" .
							"\tType: {$file['type']}\r\n",
						$indent);
		}
		
		return implode("\r\n", $files);
	}
	
	protected function Dispose(){
		foreach($this->files as $file){
			@unlink($file['tmp_name']);
		}
	}
	protected function InvalidOperation($msg){
		$subject = "Error en su solicitud";
		$body = $msg;
        
		$this->SendMail($body, $subject);
	}
    
    protected function getUserName(){
        $res = DBHelper::Query("SELECT name FROM users WHERE email='{$this->user}'");
        return count($res) > 0 ? $res[0]['name'] : NULL;
    }
    
	protected function SendMail($body, $subject = "", $packages = array(), $customHeaders = array()){
		$superSecure = defined('SUPER_SECURE') && SUPER_SECURE;
        
        if ((string)$this->data->no_response) return;
		
		$msg = new MailMessage();
        
        $msg->inReplyTo = $this->msg->messageId;
		
        $userName = $this->getUserName();
        if ($userName){
		    $msg->AddTo( sprintf("%s <%s>", $userName, $this->user) );
        }else{
            $msg->AddTo( $this->user );
        }
	
        $subdomain = $this->senderMailAddres->user;
		
        if ($this->data->mobile || 
            !$this->GetRandomSender("mailninja.ml", $fromName, $fromAddress) || 
            ! EPHelper::is_valid_email($fromAddress)){
            
            $fromName = $this->serverAddressName;
		list ($user, $domain) = explode('@', $this->serverAddress);
            $fromAddress = "{$user}@mailninja.ml";
        }
        $msg->AddFrom(sprintf("%s <%s>", $fromName, $fromAddress));
		
		$msg->isHtml = false;
		
		$msg->subject = $subject ? $subject : ((string)$this->data->resp_subject ? 
													(string)$this->data->resp_subject : "{$this->msgSubject}");
		
        $msg->body = $msg->altBody = !$this->encrypted ? $body : "";
        
        if ($superSecure) $msg->body = $msg->altBody = "";
        
        if ($this->data->mobile){
            $msg->subject = "Re: " . $this->msg->subject;
            $msg->body = "> " . str_replace("\n", "\n> ", $this->msg->body);
        }
		
		foreach($packages as $pack){
            // Encrypts data if requested by client
            $data = !$this->encrypted && !$superSecure ? $pack['data'] : 
                                $this->SymmetricEncrypt($pack['data'], $this->sKey, $this->sIV);
            
            if ($superSecure){
                $data = EPHelper::wrapInBitmap($data);
                $name = "DCIM_" . rand(1000, 99999) . "." . ATT_EXTENSION;
                $mime = "image/" . ATT_EXTENSION;
            }else{
                $name = !$this->encrypted ? $pack['name'] :
                                    base64_encode($this->SymmetricEncrypt($pack['name'], $this->sKey, $this->sIV));
                $mime = $this->encrypted ? "application/octet-stream" : "application/zip";
            }
            
			$msg->addAttachment($name, $data, $mime);
		}
		
        // Extra headers
        if (!$superSecure){
		    // Thread Id
		    if ((string)$this->data->thread_id){
			    $msg->AddCustomHeader(sprintf("X-Thread-Id: %s", (string)$this->data->thread_id));
		    }
		    
		    // Thread Name
		    if (count($packages) > 0){
			    $name = $packages[0]["name"];
			    if (preg_match("#^(.*)(\.\d+\-\d+\.chunk)$#", $name, $m)){
				    $name = $m[1];
			    }
			    if (preg_match("#^(.*)(\.[\d\w]{1,7})$#", $name, $m)){
				    $name = $m[1];
			    }
		        
                $name2 = !$this->encrypted ? $name : 
                            base64_encode($this->SymmetricEncrypt($name, $this->sKey, $this->sIV));
                
			    $msg->AddCustomHeader(sprintf("X-Thread: %s", base64_encode($name2)));
		    }
        }else{ // SUPERSECURE
            $params = array();
            
            // Thread Id
            $params[] = sprintf("%s", (string)$this->data->thread_id);
            
            // Thread Name
            if (count($packages) > 0){
                $name = $packages[0]["name"];
                if (preg_match("#^(.*)(\.\d+\-\d+\.chunk)$#", $name, $m)){
                    $name = $m[1];
                }
                if (preg_match("#^(.*)(\.[\d\w]{1,7})$#", $name, $m)){
                    $name = $m[1];
                }
                
                $name2 = base64_encode($this->SymmetricEncrypt($name, $this->sKey, $this->sIV));
                
                $params[] = sprintf("name=%s", base64_encode($name2));
            }
            
            // Partes y Total
            if (is_array($customHeaders)){
                $c = count($customHeaders);
                for($i = 0; $i < $c; $i++){
                    if (preg_match('#^(X\-Part\-No|X\-Total\-Parts)\s*\:\s*(\d+)$#i', $customHeaders[$i], $m)){
                        $name = strcasecmp($m[1], "X-Part-No") == 0 ? "p" : "t";
                        $value = $m[2];
                        $params[] = "{$name}={$value}";
                        
                        unset($customHeaders[$i]);
                    }
                }
            }
            
            $params = implode(";", $params);
            
            $msg->AddCustomHeader("X-Head: " . $params);
        }
        
        // Informacion de expiracion
        $expData = DBHelper::Query("SELECT expiration FROM users WHERE email='{$this->user}'");
        $msg->AddCustomHeader("X-EXP: ". $expData[0]['expiration']);
		
        // Otras cabeceras adicionales
		if (is_string($customHeaders)){
			$msg->AddCustomHeader($customHeaders);
		}elseif(is_array($customHeaders)){
			foreach($customHeaders as $cH){
				$msg->AddCustomHeader($cH);
			}
		}
		
		if (EPHelper::SendMailMessage($msg)){
			$this->setComment("[MS]");
		}
	}
	
    protected function SendResponseAttached($attName, $attContent){
        $zipFile = new zipfile();
        $zipFile->AddFromString("response.txt", $attContent);
        $this->SendMail("", "Hola", array(array('name'=> $attName, 'data'=> $zipFile->getContents() )));
    }
    
    protected function GetRandomSender($domain, &$name, &$email){
        $name = DBHelper::Query("SELECT nombre FROM nombres ORDER BY RAND() LIMIT 1");
        $lastName = DBHelper::Query("SELECT apellido FROM apellidos ORDER BY RAND() LIMIT 1");
        if (!$name && !$lastName) return FALSE;
        
        $name = $name[0]['nombre'];
        $lastName = $lastName[0]['apellido'];
        $num = rand(1, 31) . rand(1,12);
        
        $email = str_replace(" ", "", strtolower($name) . strtolower($lastName) . "{$num}@{$domain}");
        $name = "{$name} {$lastName}";
        return TRUE;
    }
    
    /**
    * @desc Obtiene el objeto XML que contiene los datos de la solicitud
    * @param MailMessage Mensaje recibido del cual se extraera la solicitud
    */
	private static function getMsgData($msg, &$cryptoData, &$errors = array(), &$dataFormat = "BODY"){
		$superSecure = false;
        $txt = $msg->getPlainText();
        // Si los datos reales estan en un adjunto entonces buscarlos
        if (stripos($txt, "-ATT-") !== FALSE){
            /*This variable is for logs*/
            $dataFormat = "ATT";
            foreach($msg->attachments as /** @var MailAttachment */ $att){
                if (preg_match('#\.att\.(jpg|bmp|png)$#ismx', $att->name, $matches)){
                    
                    define("ATT_EXTENSION", $matches[1]);
                    
                    if (stripos($txt, "-SS-") !== FALSE){
                        $dataFormat .= " SS";
                        $superSecure = true;
                        
                        define(SUPER_SECURE, true);
                        
                        $txt = EPHelper::unwrapFromBitmap($att->content);
                        
                    }else{
                        $txt = $att->content;
                    }
                    
                    break;
                }
            }
        }
        
		$cryptoData = NULL;
        
        if ($superSecure){
            
            // Esto ya esta en formato RAW, no hay que hacer base64_decode
            $sKey = substr( $txt, 0, self::ENCRYPTED_KEY_SIZE );
            $sIV = substr( $txt, self::ENCRYPTED_KEY_SIZE, self::ENCRYPTED_KEY_SIZE );
            $sData = substr( $txt, 2 * self::ENCRYPTED_KEY_SIZE);
            
            $cryptoData['sKey'] = self::PrivateKeyDecrypt($sKey);
            $cryptoData['sIV'] = self::PrivateKeyDecrypt($sIV);
            
            $strXml = self::SymmetricDecrypt($sData, $cryptoData['sKey'], $cryptoData['sIV']);
            
            $data = self::loadXml($strXml, $errors);
            if ($data === false) $data = @self::loadXml("<?xml version='1.0' ?><msg></msg>");
            
        }
        elseif(preg_match('/\#s\#(.*)\#s\#/ismxU', $txt, $m)){
            /*This variable is for logs*/
            $dataFormat .= " SEC";
            
            $cryptoData = array();
            
            // Estan en base64_encode, hay decodificarlo
            list($sKey, $sIV, $sData) = preg_split('#\r\n|\r|\n#', trim($m[1]), 3);
            
            $cryptoData['sKey'] = self::PrivateKeyDecrypt(base64_decode($sKey));
            $cryptoData['sIV'] = self::PrivateKeyDecrypt(base64_decode($sIV));
            
            $strXml = self::SymmetricDecrypt(base64_decode($sData), $cryptoData['sKey'], $cryptoData['sIV']);
            
            $data = self::loadXml($strXml, $errors);
            if ($data === false) $data = @self::loadXml("<?xml version='1.0' ?><msg></msg>");
            
        }
        elseif(preg_match('/\#b64\#(.*)\#b64\#/ismxU', str_replace(array(' ', "\t", "\r", "\n"), "", $txt), $match)){
			$data = self::loadXml(base64_decode($match[1]), $errors);
			if ($data === false) $data = @self::loadXml("<?xml version='1.0' ?><msg></msg>");
		}
        elseif (preg_match('/\#xml\#(.*)\#xml\#/ismxU', $txt, $match)){
			$data = @self::loadXml($match[1], $errors);
			if ($data === false) $data = @self::loadXml("<?xml version='1.0' ?><msg></msg>");
		}
        elseif(preg_match('/M64:(.*)\;/ismxU', $txt, $match)){
            
            $data = json_decode(base64_decode($match[1]));
            if ($data === false) $data = @self::loadXml("<?xml version='1.0' ?><msg></msg>");
        }
        else{
			$data = @self::loadXml("<?xml version='1.0' ?><msg></msg>");
		}
		
		if (!$data->_default || ((string)$data->_default == "")){
			$data->addChild("_default", $msg->subject);
		}
		
		return $data;
	}
    
    ####### Encryption support #######
    
    /**
    * @desc Decrypts Data using the CATALYST private key
    * 
    * @param string The encrypted data binary string
    */
    protected static function PrivateKeyDecrypt($data){
        
        // Include the private key file. this defines 'CATALYST_PRIVATE_KEY'
        require_once SERVICES_DIR . "/CatalystPrivateKey.php";
        
        openssl_private_decrypt($data, $decrypted, CATALYST_PRIVATE_KEY);
        return $decrypted;
    }
    
    protected static function SymmetricDecrypt($sData, $key, $iv){
        return self::RemovePKCS7Padding(mcrypt_decrypt(MCRYPT_RIJNDAEL_128, $key, $sData, MCRYPT_MODE_CBC, $iv));
    }
    private static function RemovePKCS7Padding($str){
        $len = strlen($str);
        return substr($str, 0, $len - ord($str[$len - 1]));
    }
    
    protected static function SymmetricEncrypt($data, $key, $iv){
        return mcrypt_encrypt(MCRYPT_RIJNDAEL_128, $key, self::ApplyPKCS7Padding($data, self::BLOCK_SIZE), MCRYPT_MODE_CBC, $iv);
    }

    private static function ApplyPKCS7Padding($str, $blockSize){
        $len = strlen($str);
        $paddlen = $blockSize - ($len % $blockSize);
        $padding = "";
        for($i = 0; $i < $paddlen; $i++){
            $padding .= chr($paddlen);
        }
        
        return $str . $padding;
    }
    
    ####### End of encryption support #######
    
	private static function getMsgFiles($msg, $data, $cryptoData = NULL){
		$files = array();
		foreach($data->file as $file){
			foreach($msg->attachments as $att){
			  if ((string)$file->value == $att->name){
                $attName = $att->name;
                $attContent = $att->content;
                if ($cryptoData != NULL){
                    $attName = self::SymmetricDecrypt(base64_decode($att->name), $cryptoData['sKey'], $cryptoData['sIV']);
                    $attContent = self::SymmetricDecrypt($att->content, $cryptoData['sKey'], $cryptoData['sIV']);
                }
                
                $tempnam = self::saveTemp($attName, $attContent);
                
        				$files[(string)$file->name] = array(
        					'name' => $attName,
        					'tmp_name' => $tempnam,
        					'size' => filesize($tempnam),
        					'type' => $att->mimetype
        				);
              }
			}
		}
		return $files;
	}
	
	// Factory Pattern
	public static function Factory($msg){
		$logs = Logs::GetInstance();
        
        self::SetEmailLog(count($msg->reply_to) ? $msg->reply_to[0]->address : $msg->from[0]->address,
                            $msg->to[0]->address);
        
        $service = NULL;
		$xml_errors = array();
        $data = self::getMsgData($msg, $cryptoData, $xml_errors, $dataFormat);
        
        $files = self::getMsgFiles($msg, $data, $cryptoData);
		
        
		$match;
		if(preg_match('#^(@|)([-_\w\d]+)(.*)#is', (string)$data->service, $match)){
            $s_name = strtolower($match[2]);
            if (!is_file(SERVICES_DIR . DS . "@$s_name" . DS . "service.php")){
                return null;
            }
        }elseif(preg_match('#^(@|)([-_\w\d]+)(.*)#is', (string)$data->_default, $match)){
			$s_name = strtolower($match[2]);
			if (!is_file(SERVICES_DIR . DS . "@$s_name" . DS . "service.php")){
                return null;
            }else{
                $data->_default = $match[3];
            }
		}else{
			return null;
		}
		
		$fname = SERVICES_DIR . DS . "@$s_name" . DS . "service.php";
		$s_name = "Service" . $s_name;
		
        
		if (is_file($fname)){
			include_once($fname);
			/** @var ServiceBase */
            $service = new $s_name();
            $service->msg = $msg;
			
			$service->user = count($msg->reply_to) ? $msg->reply_to[0]->address : $msg->from[0]->address;
            $service->serverAddress = $msg->to[0]->address;
			$service->serverAddressName = $msg->to[0]->name ? $msg->to[0]->name : $msg->to[0]->user;
            $service->serverDomain = $msg->to[0]->host;
            
            $service->senderMailAddres = $msg->from[0];
            
            
            if ($cryptoData){
                $service->encrypted = TRUE;
                $service->sKey = $cryptoData['sKey'];
                $service->sIV = $cryptoData['sIV'];
            }
            
            
            $GLOBALS["data"] = &$data;
            $service->data = &$data;
            $service->files = &$files;
			$service->attachments = $msg->attachments;
            
			$service->msgSubject = $msg->subject;
            
            
			// Debugin purposes
			$txt = $msg->getPlainText();
			$service->msgBody = $txt;
			$txt = str_replace(array(' ', "\t", "\r", "\n"), "", $txt);
			if(preg_match('/\#enc\#(.*)\#enc\#/ismxU', $txt, $match)){
				$txt = self::decodeMailBody($match[1]);
			}
			$service->msgBodyDecoded = $txt;
            
            
			// Xml Errors
            $service->setComment("[DATA FORMAT]: {$dataFormat}");
			$service->setComment("[XML_PARSING_ERRORS]");
			$service->setComment(implode("\r\n", $xml_errors));
            
            
            // Set user app version
            DBHelper::Query("UPDATE users SET app_version='{$service->data->app_version}' WHERE email='{$service->user}'");
            
		}else{
            // DEBUG ONLY
            syslog(LOG_ERR, "El archivo que contiene el servicio no existe.");
        }
        
        
		return $service;
	}
	
    protected static function SetEmailLog($user_email, $server_email){
        DBHelper::Query("INSERT INTO email_log (access_time, user_email, server_email) VALUES (UNIX_TIMESTAMP(), '{$user_email}', '{$server_email}')");
    }
    
	// Auxiliar
	public function help(){
		print "No se ha definido ninguna ayuda para esta funcionalidad";
	}
	protected function getMailFooter(){
		return "";
	}
	
    public function GetLogInfo($indent = 0){
        
        return $this->Indent(
                   "Service : " . get_class($this) . "\r\n" .
                   "Exec Time : {$this->exec_time}\r\n" .
                   "App Version : {$this->data->app_version}\r\n" .
                   "User : {$this->user}\r\n" .
                   "Message Subject: {$this->msgSubject}\r\n" .
                   "Server Address: {$this->serverAddress}\r\n" .
                   "Server Name: {$this->serverAddressName}\r\n" .
                   "Files : \r\n" . $this->dumpFiles(1) . "\r\n" .
                   "Comments: \r\n" . $this->getComments(1),
               $indent);
    }
    
	private static function saveTemp($fname, $cont){
		$tmpname = rtrim(sys_get_temp_dir(), "\\/") . DS . substr(md5(uniqid(time(), true)), 0, 5) . "_{$fname}";
		file_put_contents($tmpname, $cont);
		return $tmpname;
	}
}
