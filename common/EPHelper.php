<?php
 class EPHelper{
    public static $USER_AGENT = "Mozilla/5.0 (Windows; U; Windows NT 5.1; es-ES; rv:1.9.2) Gecko/20100115 Firefox/3.6";
     
	public static $banned_mails = null;
	
	private static $prox_list;
	public static function getRevProxy($i = -1){
		if (stripos(PHP_OS, "win") !== false){
			$fname = DOCUMENT_ROOT . "/config/p-list.win.txt";
		}else{
			$fname = DOCUMENT_ROOT . "/config/p-list.lin.txt";
		}
		if (!self::$prox_list) self::$prox_list = file($fname);
		$c = count(self::$prox_list);
		$i = ($i == -1) ? mt_rand(0, $c-1) : $i;
		return trim(self::$prox_list[$i]);
	}
	
 	public static function is_valid_email($email){
 		return preg_match('/^[-_a-z0-9.]+@([-_a-z0-9]+\.)+[a-z]{2,4}$/i', $email);
 	}
 	
	public static function is_email_banned($email){
		if (!isset(self::$banned_mails)){
			self::$banned_mails = parse_ini_file(DOCUMENT_ROOT . DS . "config" . DS . "banned.ini", false);
			self::$banned_mails = self::$banned_mails['banned'];
		}
		
		foreach (self::$banned_mails as $patt){
			if (preg_match('/^#([^#]+)#[a-z]*$/is', $patt)){ // then it is a regular expression
				if (preg_match($patt, $email)){
					return true;
				}
			}else{ // it is a normal string
				if ($patt == $email){
					return true;
				}
			}
		}
	}
	
 	public static function format_date($time, $showHours = false){
 		$date = date("Y/m/j/w/g/i/A" ,$time);
 		list($y, $m, $d, $w, $h, $i, $A) = explode('/', $date);
 		
 		$months = array(
 			'01' => 'Enero',
 			'02' => 'Febrero',
 			'03' => 'Marzo',
 			'04' => 'Abril',
 			'05' => 'Mayo',
 			'06' => 'Junio',
 			'07' => 'Julio',
 			'08' => 'Agosto',
 			'09' => 'Septiembre',
 			'10' => 'Octubre',
 			'11' => 'Noviembre',
 			'12' => 'Diciembre'
 		);
 		$weekd = array(
 			'0' => 'Domingo',
 			'1' => 'Lunes',
 			'2' => 'Martes',
 			'3' => 'Miercoles',
 			'4' => 'Jueves',
 			'5' => 'Viernes',
 			'6' => 'Sabado'
 		);
 		
 		return "{$weekd[$w]}, $d de {$months[$m]} de $y" .
 				($showHours ? ", $h:$i $A" : '');
 	}
 	
 	public static function safe_explode($separator, $str){
		$result = array();
 		$tok = strtok($str, $separator);
		while($tok){
			$result[] = $tok;
			unset($tok);
			$tok = strtok($separator);
		}
		
		return $result;
	}
 	
	public static function format_list($results){
		$list = file_get_contents(TEMPLATES_ROOT . '/list.html');
		
		$rows = array();
		$last_date = "";
		$k = 0;
		foreach($results as $res){
			if ($last_date != EPHelper::format_date($res['date'])){
				$last_date = EPHelper::format_date($res['date']);
				
				$rows[] =
				'<tr><td class="space"></td></tr>
				<tr><td class="adsterix_head">'.$last_date.'</td></tr>
				<tr><td class="space"></td></tr>';
			}
			$rows[] = '<tr><td class="'.($k % 2 ? 'light' : 'dark').'"><a href="details/'.$res['hash'].'.html">'. ($res['price'] ? '<span>'.$res['price'].'&nbsp;cuc&nbsp;-&nbsp;</span>' : '') . $res['title'].'</a>&nbsp;&nbsp;<span class="textGray">'.
			substr($res['text'], 0, 140).'...</span></td></tr>';
			$k++;
		}
		
		$match = array();
		$replace = array();
		
		include DOCUMENT_ROOT . '/common/cathegories.php';
		global $cath;
		$match[] = '${page-title}'; $replace[] = str_replace('/', '  ', $cath[$res['cathegory']]);
		$match[] = '${num-cath}'; $replace[] = $res['cathegory'];
		$match[] = '${rows}'; $replace[] = implode("\r\n", $rows);
		
		return str_replace($match, $replace, $list);
	}
	
	public static function format_details($results, $show_images=false){
		$template = file_get_contents(TEMPLATES_ROOT . '/details.html');
		$details = array();

		$k = 0;
		foreach ($results as $res){
			$mat = array(); $rep = array();
			$mat[] = '${page-title}'; $rep[] = $res['title'];
			$mat[] = '${numcath}'; $rep[] = $res['cathegory'];
			
			$mat[] = '${add-title}'; $rep[] = $res['title'];
			$mat[] = '${add-price}'; $rep[] = $res['price'];
			$mat[] = '${add-date}'; $rep[] = EPHelper::format_date($res['date'], true);
			
			$text = str_replace("\r\n", "<br />\r\n", $res['text']);
			$text = str_replace("\n", "<br />\n", $text);
			$mat[] = '${add-text}'; $rep[] = $text;
			$mat[] = '${add-email}'; $rep[] = $res['email'];
			$mat[] = '${add-name}'; $rep[] = $res['name'];
			$mat[] = '${add-phone}'; $rep[] = $res['phone'];
			
			$images_html = "";
			if ($show_images && strlen($res['photos']) > 1){
				$images = explode(';', $res['photos']);
				$images_url = array();
				foreach ($images as $_url){
					$url = substr($_url, 0);
					
					$url = str_replace(array("thumbnail", "_t"), array("photo", ""), $url);
					
					if (!preg_match("#^http://#" ,$url)){
						$url = BASE_REVOLICO . "/" . ltrim($url, " /");
					}
					
					$images_url[]=substr($url, 0);
				}
				
				$raw_images = EPHelper::MultiGetUrl($images_url);
				
				foreach ($raw_images as $image_content){
					if ($img = @imagecreatefromstring($image_content)){
						$sx = imagesx($img);
						$sy = imagesy($img);
						
						$t_size = 200;
						$t_cuality = 85;
						
						$nsx = 0; $nsy = 0;
						if ($sx <= $t_size && $sy <= $t_size){
							$nsx = $sx;
							$nsy = $sy;
						}else{
							$max = max($sx, $sy);
							
							$r = $t_size / $max;
							
							$nsx = $r * $sx;
							$nsy = $r * $sy;
						}
						
						$dest = imagecreatetruecolor($nsx, $nsy);
						
						imagecopyresampled($dest, $img, 0,0,0,0, $nsx, $nsy, $sx, $sy);
						
						$tempnam = tempnam(TEMP_ROOT, 'photo');
						imagejpeg($dest, $tempnam, $t_cuality);
						
						$details["images/photos_$k.jpg"] = file_get_contents($tempnam);
						$images_html .= "<img style=\"border: 4px solid white;\" src=\"../images/photos_$k.jpg\" />&nbsp;&nbsp;";
						
						unlink($tempnam);
						imagedestroy($img);
						imagedestroy($dest);
						$k++;
					}
				}
			}
			elseif (strlen($res['photos']) == 0){
				$images_html = "<a href=\"mailto:services@elpuent.com?subject=details {$res['hash']}\">Ver con fotos</a>";
			}
			
			$mat[] = '${photos}'; $rep[] = $images_html;
			$details['details/'.$res['hash'] . '.html'] = str_replace($mat, $rep, $template);
		}
		
		return $details;
	}
	
	/**
	 * @param string $body
	 * @param bool $quoted_printable
	*/
 	public static function parse_form($_body) {
		$body = substr($_body, 0);
		
		if (preg_match('#<root>.*</root>#ismx', $body, $match)){
			$match[0] = "<?xml version='1.0' ?>" . $match[0];
			return simplexml_load_string($match[0]);
		}else{
			return simplexml_load_string("<?xml version='1.0'?><root></root>");
		}
 	}
 	
 	public static function get_form_data($msg) {
 		$body = '';
		//$quoted_print = false;
 		foreach ($msg['body']['parts'] as $part) {
 			if ($part['mime-type'] == 'text/plain') {
 				$body = $part['content'];
				//$quoted_print = ($part['encoding'] == "quoted-printable");
 				break;
 			}
 		}
 		return EPHelper::parse_form($body);
 	}
 	
 	public static function &packData($data){
 		$zip = new zipfile;
 		foreach ($data as $local_name => $contents){
 			$zip->addFromString($local_name, $contents);
 		}
 		
 		return $zip->getContents();
 	}
 	
 	public static function &packFiles($files){
 		
 		$zip = new zipfile;
 		foreach ($files as $file){
 			$zip->addFile($file['filename'], $file['localname']);
 		}
 		
 		return $zip->getContents();
 	}
 
    /**
    * @desc Envia un mensaje de correo
    * @param MailMessage El mensaje a enviar
    */
	public static function SendMailMessage($mailMessage, &$error = "", $config = NULL){
		$credentials = EPHelper::getRandSmtpAccount();
		
		_debug('Enviando mensahje');

        if ($config == NULL){
            $config = array(
                'MAIL_IS_SMTP' => MAIL_IS_SMTP,
                'SMTP_HOST' => SMTP_HOST,
                'SMTP_PORT' => SMTP_PORT,
                'SMTP_AUTH' => SMTP_AUTH,
                'SMTP_SSL' => SMTP_SSL,
                'SMTP_USER' => $credentials->user,
                'SMTP_PASS' => $credentials->pass
            );
        }
        
		$mail = new phpmailer;
		$mail->XMailer = ' ';
		$mail->AddCustomHeader("User-Agent: Mozilla/5.0 (Windows NT 10.0; WOW64; rv:45.0) Gecko/20100101 Thunderbird/45.3.0");
        
 		$mail->Helo = "auroraml.net";
        $mail->Hostname = "auroraml.net";
        $mail->AllowEmpty = TRUE;
        
		$mail->SMTPDEBUG = 0;
		if (defined('SMTP_DEBUG'))
			$mail->SMTPDEBUG = SMTP_DEBUG;
		
		
 		// Configure Mail Connection
 		if ($config['MAIL_IS_SMTP']){
			$mail->IsSMTP();
			$mail->Host = $config['SMTP_HOST'];
			$mail->Port = $config['SMTP_PORT'];
			
			if ($config['SMTP_AUTH']){
				$mail->SMTPAuth   = true;
				$mail->Username = $config['SMTP_USER'];
				$mail->Password = $config['SMTP_PASS'];
			}else{
				$mail->SMTPAuth   = false;
			}
			
			if ($config['SMTP_SSL']){
				$mail->Host = "ssl://{$mail->Host}";
			}
		}
		else{
			$mail->IsMail();
		}
 		
		// Message-Id (RFC 2822 dictates that this field is mandatory)
		$mail->MessageID = $mailMessage->messageId ? $mailMessage->messageId : 
                                    "<" . md5(uniqid(time(), true)) . "@mail.{$mailMessage->from[0]->host}>";
		
        if ($mailMessage->inReplyTo){
            $mailMessage->AddCustomHeader( "In-Reply-To: {$mailMessage->inReplyTo}" );
            $mailMessage->AddCustomHeader( "References: {$mailMessage->inReplyTo}" );
        }
		
 		// Senders
		if (count($mailMessage->from) > 0){
			$mail->From = $mailMessage->from[0]->address;
			$mail->FromName = $mailMessage->from[0]->name;

			$mail->Sender = $mailMessage->from[0]->address;
		}

		// Recipients
 		foreach($mailMessage->to as $to){
			$mail->AddAddress($to->address, $to->name);
		}
		
		foreach($mailMessage->cc as $cc){
			$mail->AddCC($cc->address, $cc->name);
		}
		
		foreach($mailMessage->bcc as $bcc){
			$mail->AddBCC($bcc->address, $bcc->name);
		}
		
		foreach($mailMessage->reply_to as $rt){
			$mail->AddReplyTo($rt->address, $rt->name);
		}
		
 		$mail->Subject = $mailMessage->subject;
 		
		foreach($mailMessage->customHeaders as $customHeader){
			$mail->AddCustomHeader($customHeader);
		}
		
		$mail->IsHTML($mailMessage->isHtml);
		// Mail Contents
		if ($mailMessage->isHtml){
			$mail->Body = $mailMessage->body;
			$mail->AltBody = $mailMessage->altBody;
		}else{
			$mail->Body = $mailMessage->body;
		}
		
		foreach($mailMessage->attachments as $att){
			$mail->AddStringAttachment($att->content, $att->name/*, $att->encoding, $att->mimetype, $att->disposition*/);
		}
		
		if ( $mail->Send() ){
			_debug("Mail Sent");
			return true;
		}else{
			$error = $mail->ErrorInfo;
			_debug($error);
			return false;
		}
	}
 
 	public static function SendMail($to, $subject, $body, $packages = array(), $from = "", $fromName = ""){
 		$credentials = EPHelper::getRandSmtpAccount();
		
 		//require_once DOCUMENT_ROOT . '/common/class.phpmailer.php';
 		$mail = new phpmailer;
 		
		$mail->SMTPDEBUG = 0;
		
 		// Configure Mail Connection
 		if (MAIL_IS_SMTP){
			$mail->IsSMTP();
			$mail->Host = SMTP_HOST;
			$mail->Port = SMTP_PORT;
			
			if (SMTP_AUTH){
				$mail->SMTPAuth = true; 
				$mail->Username = $credentials->user;
				$mail->Password = $credentials->pass;
			}
			
			if (SMTP_SSL){
				$mail->SMTPSecure = "ssl";
			}
		}
		else{
			$mail->IsMail();
		}
 		
 		// Mail Headers
		$mail->From = $from != "" ? $from : MAIL_USER . '@' . MAIL_DOMAIN;
		$mail->FromName = $fromName != "" ? $fromName : MAIL_NAME;
 		
 		$mail->AddAddress($to);
 		$mail->Subject = $subject;
 		
 		$mail->IsHTML(true);
 		// Mail Contents
 		$mail->AltBody = $body;
 		
 		$mail->Body ="
<html>
<head>
  <title>$subject</title>
  <style type=\"text/css\">
  	body {
  	  font-family:Segoe UI, Verdana, Arial;
  	  color:#333;
  	  font-size:10pt;
 	}
 	
 	p {
 	  text-align:justify;
 	}
  </style>
</head>
<body>
  <p>" . preg_replace(array('#\${([^}]+)}#', '#{([^}]+)}\$#', '#\$\*{([^}]+)}#', '#\n#'), 
					array('<${1}>', '</${1}>', '<${1}/>', '<br>'), htmlspecialchars($body)).
 "</p>
</body>
</html>
";
 		
 		// Mail Attachments
 		foreach ($packages as $att){
 			$mail->AddStringAttachment($att['data'], $att['name'], 'base64');
 		}
 		
 		if ($mail->Send()){
 			return true;
 		}else{
 			return false;
 		}
 	}
	
	public static function SendMailHtml($to, $subject, $body, $packages = array(), $from = "", $fromName = ""){
 		$credentials = EPHelper::getRandSmtpAccount();
		
 		require_once DOCUMENT_ROOT . '/common/class.phpmailer.php';
 		$mail = new phpmailer;
 		
 		// Configure Mail Connection
 		if (MAIL_IS_SMTP){
			$mail->IsSMTP();
			$mail->Host = SMTP_HOST;
			$mail->Port = SMTP_PORT;
			
			if (SMTP_AUTH){
				$mail->SMTPAuth   = true; 
				$mail->Username = $credentials->user;
				$mail->Password = $credentials->pass;
			}
			
			if (SMTP_SSL){
				$mail->SMTPSecure = "ssl";
			}
		}
		else{
			$mail->IsMail();
		}
 		
 		// Mail Headers
 		$mail->From = "rev@elpuent.com";
 		$mail->FromName = 'El Puente - Services';
		
		if ($from != "") $mail->From = $from;
		if ($fromName != "") $mail->FromName = $fromName;
 		
 		$mail->AddAddress($to);
 		$mail->Subject = $subject;
 		
 		$mail->IsHTML(true);
 		// Mail Contents
 		$mail->AltBody = "Por favor, abra la version HTML de este mensaje";
 		
 		$mail->Body = $body;
 		
 		// Mail Attachments
 		foreach ($packages as $att){
 			$mail->AddStringAttachment($att['data'], $att['name'], 'base64');
 		}
 		
 		if ($mail->Send()){
 			return true;
 		}else{
 			return false;
 		}
 	}
 
 	public static function SendFormatedMail($to, $subject, $template, $params=array(), $packdata = '', $packname = ''){
 		if (!file_exists(HTML_ROOT . '/tmpl_' . $template . '.txt')){
 			$keys = array();
 			foreach ($params as $key => $value) $keys[] = '${' .$key. '}';
 			file_put_contents(HTML_ROOT . '/tmpl_' . $template . '.txt',
 									"New Template tmpl_{$template}.txt\r\n Implements : ". implode(', ',$keys));
 		}
 		$body = file_get_contents(HTML_ROOT . '/tmpl_' . $template . '.txt');
 		
 		if (count($params) > 0){
 			$search = array(); $replaces = array();
 			foreach ($params as $key => $value){
 				$search[] = '${' . $key . '}';
 				$replaces[] = $value;
 			}
 			
 			$body = str_replace($search, $replaces, $body);
 		}
 		
 		$packages = array();
 		if ($packdata != ''){
 			$packages[0]['data'] = $packdata;
 		
 			if ($packname != ''){
 				$packages[0]['name'] = $packname;
 			}
 			else{
 				$packages[0]['name'] = 'archive.zip';
 			}
 		}
 		
 		return EPHelper::SendMail($to, $subject, $body, $packages);
 	}
 	
    /**
    * @desc Obtiene el recurso http en una determinada url
    * @param string La url del recurso
    * @param int Timeout para esta solicitud (en segundos)
    * @param array Cuando retorna, contiene informacion sobre la transferencia
    * @param string La ruta al archivo de cookies a utilizar
    * @param string La url que se usara en la cabecera Referer
    * @param HttpHeaders Las cabeceras de Respuesta
    */
 	public static function GetUrl($url, $timeout = 3, &$transfer_info = NULL, $cookie_file = "", $referer="", &$responseHttpHeaders=null){
 		// DEBUG
        $logs = Logs::GetInstance();
        /*if (_DEBUG_){
            $logs->addEntry("[Running] EPHelper::GetURL($url, $timeout, $transfer_info, $cookie_file, $referer, $responseHttpHeaders)");
        }*/
		
		if (strpos($url, "s455335975.onlinehome.us") !== false){
			$timeout = min($timeout, 3);
		}
   
   _debug("GetUrl: {$url}");
		
		$headers = array();
		$headers[] = "Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8";
		$headers[] = "Referer: " . ( $referer ? $referer : ( (Url::Parse($url)) ? Url::Parse($url)->RemoveParams()->Save() : "http://www.example.com/" ) );
		$headers[] = "Cache-Control: max-age=0";
		$headers[] = "Connection: close";
		//$headers[] = "Keep-Alive: 300";
		$headers[] = "Accept-Charset: ISO-8859-1,utf-8;q=0.7,*;q=0.7";
		$headers[] = "Accept-Language: es-es,es;q=0.8,en-us;q=0.5,en;q=0.3";
		//$headers[] = "Accept-Encoding:";
		
		$ch = curl_init($url);
 		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		
		if (strpos($url, "m.facebook.com") === false){
			curl_setopt($ch, CURLOPT_USERAGENT, self::$USER_AGENT);
		}else{	
			curl_setopt($ch, CURLOPT_USERAGENT, "Apple-iPad2C1/1001.523");
		}
 		
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_HEADER, TRUE);
		curl_setopt($ch, CURLOPT_ENCODING, "");
 		curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($ch, CURLOPT_MAXREDIRS, 15);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
		//curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_0);
		
		if (is_file($cookie_file)){
			curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie_file);
			curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie_file);
		}
 		
        // DEBUG
        /*if (_DEBUG_){
            $logs->addEntry("[Running] curl_exec()");
        }*/
        
		$res = curl_exec($ch);
		if (strlen($res) > 0){
            // DEBUG
            /*if (_DEBUG_){
                $logs->addEntry("[Running] EPHelper::getHeadersAndContent(); RAW={$res}");
            }*/
            list($header, $contents) = self::getHeadersAndContent($res);
            
            // DEBUG
            /*if (_DEBUG_){
                $logs->addEntry("[Running] HttpHeaders::fromString()");
            }*/
            $responseHttpHeaders = HttpHeaders::fromString($header);
        }else{
            $contents = "CURL ERROR: " . curl_error($ch);
        }
        
		// DEBUG
        /*if (_DEBUG_){
            $logs->addEntry("[Running] curl_getinfo()");
        }*/
        $transfer_info = curl_getinfo($ch);
 		
		curl_close($ch);
		
		return $contents;
 	}
    protected static function getHeadersAndContent($str){
        $content = $str;
        do{
            list($header, $content) = preg_split('#\r\n\r\n|\r\r|\n\n#', $content, 2, PREG_SPLIT_NO_EMPTY);
            list($resp_line, $header) = preg_split('#\r|\n#', $header, 2, PREG_SPLIT_NO_EMPTY);
            list($foo, $state, $comment) = preg_split('#\s+#', $resp_line, 3, PREG_SPLIT_NO_EMPTY);
            $state += 0;
        }while(($state < 200 || (300 <= $state && $state < 400)) && $content);
        
        return array($header, $content);
    }
 
	public static function PostUrl($url, $pdata, $pfiles, $timeout = 5, &$transfer_info = NULL, $cookie_file = "", $referer="", $urlencoded=false, &$responseHttpHeaders=null){
 		
		if (strpos($url, "s455335975.onlinehome.us") !== false){
			$timeout = min($timeout, 5);
		}
   
    _debug(json_encode($pfiles));
		
		$headers = array();
		$headers[] = "Referer: " . ( $referer ? $referer : ( (Url::Parse($url)) ? Url::Parse($url)->RemoveParams()->Save() : "http://www.example.com/" ) );
		$headers[] = "Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8";
		$headers[] = "Cache-Control: max-age=0";
		$headers[] = "Connection: close";
		//$headers[] = "Keep-Alive: 300";
		$headers[] = "Accept-Charset: ISO-8859-1,utf-8;q=0.7,*;q=0.7";
		$headers[] = "Accept-Language: es-es,es;q=0.8,en-us;q=0.5,en;q=0.3";
		//$headers[] = "Accept-Encoding:";
		
    $pvars = array();
    foreach ($pdata as $key => $val){
        self::squashPostField($key, $val, $pvars);
    }
    
    foreach ($pfiles as $key => $val){
			$pvars[$key] = new CURLFile($val['tmp_name'], $val['type'], $val['name']);
		}
        
		if ($urlencoded){
			$pvars_ue = "";
			foreach ($pvars as $key => $val){
				if ($pvars_ue)
					$pvars_ue .= "&";
				
				$pvars_ue .= "$key=" . urlencode($val);
			}
			
			$pvars = $pvars_ue;
		}
		
		$ch = curl_init($url);
 		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		
		if (strpos($url, "m.facebook.com") === false){
			curl_setopt($ch, CURLOPT_USERAGENT, self::$USER_AGENT);
		}else{	
			curl_setopt($ch, CURLOPT_USERAGENT, "Apple-iPad2C1/1001.523");
		}
 		
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		//curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_HEADER, 1);
		curl_setopt($ch, CURLOPT_ENCODING, "");
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $pvars);
 		curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
		curl_setopt($ch, CURLOPT_MAXREDIRS, 15);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
		
		//curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_0);
 		
		if (is_file($cookie_file)){
			curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie_file);
			curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie_file);
		}
		
		$res = curl_exec($ch);
        if (strlen($res) > 0){
            list($header, $contents) = self::getHeadersAndContent($res);
            $responseHttpHeaders = HttpHeaders::fromString($header);
        }else{
            $contents = "CURL ERROR: " . curl_error($ch);
        }
        
		$transfer_info = curl_getinfo($ch);
 		
		curl_close($ch);
		
		return $contents;
 	}
	private static function squashPostField($key, $val, &$pvars){
        if (is_array($val)){
            foreach($val as $k => $v){
                if (is_string($k)) $k = "$k";
                self::squashPostField($key . "[$k]", $v, $pvars);
            }
        }else{
            $pvars[$key] = $val;
        }
    }
    
 	public static function _MultiGetUrl($lurl, $timeout = 5, &$transfer_info = NULL, $cookie_file = ""){
 		$mh = curl_multi_init();
 		$ch = array();
 		
		$headers = array();
		$headers[] = "Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8";
		//$headers[] = "Referer: http://www.google.com/search";
		$headers[] = "Cache-Control: max-age=0";
		$headers[] = "Connection: keep-alive";
		$headers[] = "Keep-Alive: 300";
		$headers[] = "Accept-Charset: ISO-8859-1,utf-8;q=0.7,*;q=0.7";
		$headers[] = "Accept-Language: es-es,es;q=0.8,en-us;q=0.5,en;q=0.3";
		$headers[] = "Accept-Encoding:";
		
		if (strpos($url, "m.facebook.com") === false){
			$user_agent = "Mozilla/5.0 (Windows; U; Windows NT 5.1; es-ES; rv:1.9.2) Gecko/20100115 Firefox/3.6";
		}else{	
			$user_agent = "Apple-iPad2C1/1001.523";
		}
		
		$cookie_file_exists = is_file($cookie_file);
 		$c = count($lurl);
 		for($i = 0; $i < $c; $i++){
 			$ch[$i] = curl_init($lurl[$i]);
 			curl_setopt($ch[$i], CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($ch[$i], CURLOPT_USERAGENT, $user_agent);
			curl_setopt($ch[$i], CURLOPT_HTTPHEADER, $headers);
 			curl_setopt($ch[$i], CURLOPT_HEADER, 0);
 			curl_setopt($ch[$i], CURLOPT_TIMEOUT, $timeout);
			curl_setopt($ch[$i], CURLOPT_FOLLOWLOCATION, 1);
			curl_setopt($ch[$i], CURLOPT_MAXREDIRS, 5);
			
			if ($cookie_file_exists){
				curl_setopt($ch[$i], CURLOPT_COOKIEJAR, $cookie_file);
				curl_setopt($ch[$i], CURLOPT_COOKIEFILE, $cookie_file);
			}
 			
 			curl_multi_add_handle($mh, $ch[$i]);
 		}
 		
 		$running = 0;
 		do{
 			curl_multi_exec($mh, $running);
 			usleep(100);
 		} while($running > 0);
 		
 		$contents = array();
		$transfer_info = array();
 		for($i=0; $i < $c; $i++){
 			$contents[] = substr(curl_multi_getcontent($ch[$i]), 0);
			$transfer_info[] = curl_getinfo($ch[$i]);
			
 			curl_multi_remove_handle($mh, $ch[$i]);
 			curl_close($ch[$i]);
 		}
 		
 		return $contents;
 	}
 
	public static function MultiGetUrlSecuential($lurl, &$transfer_info, $cookie_file, $referer){
		$c_res = array();
		$transfer_info = array();
		
		$c = count($lurl);
		
		for($i = 0; $i < $c; $i++){
			$transfer_info[$i] = array();
			$c_res[$i] = EPHelper::GetUrl($lurl[$i], 1, $transfer_info[$i], $cookie_file, $referer);
		}
		
		return $c_res;
	}
 
	public static function MultiGetUrl($lurl, $timeout = 5, &$transfer_info = NULL, $cookie_file = "", $max = 20, $referer=""){
 		
		// if (strpos($lurl[0], "s455335975.onlinehome.us") !== false){
			// return EPHelper::MultiGetUrlSecuential($lurl, $transfer_info, $cookie_file, $referer);
		// }
		
		$mh = curl_multi_init();
 		$ch = array();
 		
		$headers = array();
		$headers[] = "Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8";
		$headers[] = "Referer: " . ( $referer ? $referer : ( Url::Parse($lurl[0]) ? Url::Parse($lurl[0])->RemoveParams()->Save() : "http://www.example.com/" ) );
		$headers[] = "Cache-Control: max-age=0";
		$headers[] = "Connection: close";
		//$headers[] = "Keep-Alive: 300";
		$headers[] = "Accept-Charset: ISO-8859-1,utf-8;q=0.7,*;q=0.7";
		$headers[] = "Accept-Language: es-es,es;q=0.8,en-us;q=0.5,en;q=0.3";
		//$headers[] = "Accept-Encoding:";
		
		if (strpos($lurl[0], "m.facebook.com") === false){
			$user_agent = self::$USER_AGENT;
		}else{	
			$user_agent = "Apple-iPad2C1/1001.523";
		}
		
		$cookie_file_exists = is_file($cookie_file);
 		$c = count($lurl);
 		
		// Run Loop. Dinamically adds curl resources;
		
 		$running = 0;
		$i = 0;
 		do{
			while($i < $c && $running < $max){
				$ch[$i] = curl_init($lurl[$i]);
				curl_setopt($ch[$i], CURLOPT_RETURNTRANSFER, 1);
				//curl_setopt($ch[$i], CURLOPT_USERAGENT, "Mozilla/5.0 (Windows; U; Windows NT 5.1; es-ES; rv:1.9.2) Gecko/20100115 Firefox/3.6");
				curl_setopt($ch[$i], CURLOPT_USERAGENT, $user_agent);
				curl_setopt($ch[$i], CURLOPT_HTTPHEADER, $headers);
                curl_setopt($ch[$i], CURLOPT_HEADER, 0);
				curl_setopt($ch[$i], CURLOPT_ENCODING, "");
				curl_setopt($ch[$i], CURLOPT_TIMEOUT, $timeout);
				curl_setopt($ch[$i], CURLOPT_FOLLOWLOCATION, 1);
				curl_setopt($ch[$i], CURLOPT_MAXREDIRS, 15);
				curl_setopt($ch[$i], CURLOPT_SSL_VERIFYPEER, false);
				curl_setopt($ch[$i], CURLOPT_SSL_VERIFYHOST, false);
				
				//curl_setopt($ch[$i], CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_0);
				
				if ($cookie_file_exists){
					curl_setopt($ch[$i], CURLOPT_COOKIEJAR, $cookie_file);
					curl_setopt($ch[$i], CURLOPT_COOKIEFILE, $cookie_file);
				}
				
				curl_multi_add_handle($mh, $ch[$i]);
				
				$i++; $running++;
			}
			
 			curl_multi_exec($mh, $running);
 			usleep(100);
 		} while($running > 0);
 		
 		$contents = array();
		$transfer_info = array();
 		for($i=0; $i < $c; $i++){
			$cont = curl_multi_getcontent($ch[$i]);
			$cont = $cont ? $cont : "CURL Error: " . curl_error($ch[$i]);
			
 			$contents[] = substr($cont, 0);
			$transfer_info[] = curl_getinfo($ch[$i]);
			
 			curl_multi_remove_handle($mh, $ch[$i]);
 			curl_close($ch[$i]);
 		}
 		
 		return $contents;
 	}
	
 	private static function parse_date($strDate){
		$months = array(
			'Enero' => 1,
			'Febrero' => 2,
			'Marzo' => 3,
			'Abril' => 4,
			'Mayo' => 5,
			'Junio' => 6,
			'Julio' => 7,
			'Agosto' => 8,
			'Septiembre' => 9,
			'Octubre' => 10,
			'Noviembre' => 11,
			'Diciembre' => 12
		);
		
		list($foo, $d, $foo2, $m, $foo3, $y, $hi, $A) = explode(' ', $strDate, 8);
		list($h, $i) = explode(':', $hi);
		$y = str_replace(',', '', $y);

		$h %= 12;
		if (strpos($A, 'PM') !== false){
			$h += 12;
		}
		
		return mktime($h, $i, 0, $months[$m], $d, $y);
	}
 	
	private static function map_contact_key($k){
		if (strpos($k->plaintext, 'Email') !== false){
			return 'email';
		}
		if (strpos($k->plaintext, 'Nombre') !== false){
			return 'name';
		}
		if (strpos($k->plaintext, 'Tel') !== false){
			return 'phone';
		}
		return NULL;
	}
	
 	public static function parseAnounce(&$html){
 		$dom = str_get_html($html);
		$base = $dom->find("base", 0)->href;
		
 		// Removes all Scripts (javascript, vbscript, etc), links, base
 		foreach ($dom->find('script, link, base') as $script){
 			$script->outertext = '';
 		}
 		
 		// Change logo src
 		$dom->find('img', 0)->src = 'css/logo.gif';
 		
 		$head = $dom->find('head', 0);
 		$head->innertext .='
 		<link href="css/template_css.css" rel="stylesheet" type="text/css" media="screen"/>
 		<link href="css/adsterix.css" rel="stylesheet" type="text/css" media="screen"/>
 		<link href="css/adsterix_color.css" rel="stylesheet" type="text/css" media="screen"/>
 		<link href="css/css_color.css" rel="stylesheet" type="text/css" media="screen"/>
 		';
		
		$lurl = array();
		$k = 0;
		$lnames = array();
		foreach($dom->find(".photo-frame img") as $photo){
			$lurl[] = $base . $photo->src;
			$lnames[] = $photo->src = "images/{$k}.jpg";
			$k++;
		}
		
		$photos = EPHelper::MultiGetUrl($lurl, 7);
		
		$data = array_combine($lnames, $photos);
		$data['detalles.html'] = substr($dom->save(), 0);
		
		$dom->clear();
		unset($dom);
		
		return $data;
 	}
 
 	public static function parseList(&$html){
 		$dom = str_get_html($html);
		if (!$dom->find('.table_wrapper', 0)) return false;
		
 		// Removes all Scripts (javascript, vbscript, etc), links, base
 		foreach ($dom->find('script, link, base') as $script){
 			$script->outertext = '';
 		}
 		
 		// Change logo src
 		$dom->find('img', 0)->src = '../../css/logo.gif';
 		
 		$head = $dom->find('head', 0);
 		$head->innertext .='
 		<link href="../../css/template_css.css" rel="stylesheet" type="text/css" media="screen"/>
 		<link href="../../css/adsterix.css" rel="stylesheet" type="text/css" media="screen"/>
 		<link href="../../css/adsterix_color.css" rel="stylesheet" type="text/css" media="screen"/>
 		<link href="../../css/css_color.css" rel="stylesheet" type="text/css" media="screen"/>
 		';
 		
 		// Change href for anchors
 		foreach ($dom->find('.table_wrapper a') as $link){
 			$link->href = '"mailto:www@elpuent.com?subject=web-im: base64:'. base64_encode(EPHelper::getProx() . $link->href) .'"';
 		}
 		
 		$save = substr($dom->save(), 0);
		$dom->clear();
		unset($dom);
		return $save;
 	}
	
	public static function phpExec($script, $args = ""){
		$php = escapeshellarg(SERVER_PHP_PATH);
		$cmd = "-f '" . escapeshellarg($script) . "'";
		Process::ExecBg("$php $cmd -- $args");
	}
 
	public static function getRandSmtpAccount(){
		$users = explode(";", SMTP_USER_LIST);
		$pwds = explode(";", SMTP_PASS_LIST);
		
		$c = min(count($users), count($pwds));
		$i = mt_rand(0, $c - 1);
		
		$res = new stdClass;
		$res->user = $users[$i];
		$res->pass = $pwds[$i];
		
		return $res;
	}
    
    public static function getPhpMailboxFor($userName, $password){
        // Mailbox Setup
        $mbox = new phpmailbox();
        $mbox->host = IMAP_HOST;
        $mbox->port = IMAP_PORT;
        $mbox->protocol = IMAP_PROT;

        if (IMAP_SSL) {
            $mbox->flags[] = 'ssl';
        }

        $mbox->user = $userName;
        $mbox->pass = $password;
        $mbox->mailbox = "INBOX";
        // -- End Mailbox Setup
        
        return $mbox;
    }
    
    /**
    * @desc Oculta una cadena de bytes en un bitmap 4:3
    * @param string La cadena de bytes a ocultar
    * @returns string El bitmap con la cadena oculta
    */
    public static function wrapInBitmap($byteString){
        // Usa resolucion de 32 bits para no tener problemas con el padding del formato 'BMP'
        // Calcular el tamaño del bitmap y rellenar el espacio sobrante
        $byteString = pack( "V", strlen($byteString) ) . $byteString;
        
        $len = strlen($byteString);
        // Buscanos el menor entero b mayor que sqrt( $len/4 )
        $b = floor(sqrt($len / 4));
        while(4 * $b * $b < $len) $b++;
        
        $len = 4 * $b * $b; // La nueva longitud
        
        // Rellenar la cadena(bytes) con ceros
        $byteString = str_pad($byteString, $len, "\0");
        
        // Crear el header primero
        // Headers de archivo
        $bitmapData = pack("v", "BM");
        $bitmapData .= pack("V", 54 + $len); // El tamaño del archivo, header + data. len(header)=54
        $bitmapData .= pack("v", 0); //Reservado 
        $bitmapData .= pack("v", 0); // Reservado
        $bitmapData .= pack("V", 54); // Offset of pixel data (54)
        
        //headers de Imagen
        $bitmapData .= pack("V", 40); // Header size
        $bitmapData .= pack("V", $b); // width (px)
        $bitmapData .= pack("V", $b); // height (px)
        $bitmapData .= pack("v", 1); // planes=1
        $bitmapData .= pack("v", 32); // bbp=32bit (resolucion de color)
        $bitmapData .= pack("V", 0); // compresion=0
        $bitmapData .= pack("V", 0); // ImageSize=0 (Uncompressed)
        $bitmapData .= pack("V", 0); // PrefferedXResoution=0 
        $bitmapData .= pack("V", 0); // PrefferedYResoution=0 
        $bitmapData .= pack("V", 0); // nColorMaps=0 
        $bitmapData .= pack("V", 0); // nSignificantColor=0
        
        // Agragar los datos y retornar
        return $bitmapData .= $byteString;
    }
    
    /**
    * @desc Desoculta una cadena de bytes de un bitmap 4:3
    * @param string El bitmap, en una cadena
    * @return string La cadena oculta
    */
    public static function unwrapFromBitmap($bitmapBytes){
        $len = array_values(unpack("V", substr($bitmapBytes, 54, 4)));
        $len = $len[0];
        
        return substr($bitmapBytes, 54+4, $len);
    }
    
    /**
    * @desc Compara dos versiones de la forma 'a.b.c.d'. Aunque pueden
    * tener menos componentes, pero no mas.
    * 
    * @return int Devuelve: -k si: $v1 < $v2, 0 si: $v1 = $v2, k si $v1 > $v2
    */
    public static function compareVersions($v1, $v2){
        $v1Parts = explode('.', $v1);
        $v2Parts = explode('.', $v2);
        
        for($i = 0; $i < 4; $i++){
            $diff = $v1Parts[$i] - $v2Parts[$i];
            if ($diff == 0) continue;
            
            return $diff;
        }
        
        return 0;
    }
    
 }
