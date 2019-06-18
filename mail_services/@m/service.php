<?php

/*
	Esta clase implementa servicios para usar desde un correo
	configurado en un smartphone. Se debe implementar de forma
	tal que los mensajes sean lo mas pequenno posible, los
	resultados no deben mostrarse como adjunto sino en el propio
	cuerpo del mensaje en formato HTML
*/
class ServiceM extends ServiceBase{
	
    const NUM_RESULTS = 15;
    
	// Utilities
    // Att is an array of instances of 'MailAttachment'
	protected function CreateResponseMessage($html, $subject, $att = NULL){
		$msg = new MailMessage();
		
		$msg->AddTo($this->user);
		$msg->AddFrom(sprintf("%s <%s>", $this->serverAddressName, $this->serverAddress));
		
		$msg->isHtml = true;
		
        $msg->subject = $subject;
        $msg->body = $html;
        $msg->altBody = "Necesitas cambiar a la vista HTML para ver este mensaje";
        
        if ($att && is_array($att) && count($att)){
            foreach($att as $attach){
                if (is_a($attach, "MailAttachment"))
                    $msg->AddAttachment($attach->name, $attach->content, $attach->mimetype);
            }
        }
        
		return $msg;
	}
	
	protected function SendResponseMessage($msg){
		if (EPHelper::SendMailMessage($msg, $error)){
			$this->setComment("[MS]");
		}else{
			$this->setComment("[MS_FAIL]: {$error}");
		}
	}
	
	// Override abstract method
	protected function Authorized(){
		return $this->getUserType() >= USER_CLIENT;
	}
	
	// Override abstract method
	protected function RunService(){
		$cmd = strtok((string)$this->data->_default, ": ");
		$this->data->_default = ltrim(strtok("\0"));
		
		switch (strtolower($cmd)){
			// Buscar. La forma correcta
			case "rv":
			case "revolico":
			// Formas incorrectas, pero validas
			case "rev":
			case "rebolico":
			case "anucios":
				$this->RunSearchRevolico();
				break;
            /*-----------------------*/
            
            // Anuncio. La forma Correcta
            case "add":
            case "anuncio":
            // Formas incorrectas
            case "ad":
            case "anuncios":
            case "anun":
            case "an":
                $this->RunRevAdd();
                break;
            /*-----------------------*/    
			// Fotos.
            case "pics":
            case "pic":
            case "picture":
            case "pictures":
            case "foto":
            case "fotos":
                $this->RunRevFoto();
                break;
            // Ayuda. La forma correcta
			case "ayuda":
			case "help": 
			// Formas incorrectas
			case "?":
			case "":
			default:
				$this->RunHelp();
				break;
		}
	}
	
	protected function _RunSearchRevolico(){
		
		$query = urlencode((string)$this->data->_default);
        $url = "http://www.revolico.com/search.html?q={$query}";
		//$url = "http://www.revolico.com/computadoras/laptop/";
		
        // Descargar la pagina de resultados
		$mainContent = EPHelper::GetUrl($url, 30, $tInfo);
		
        // Comprobar que todo salio bien
		if ($tInfo['http_code'] != 200){
			$this->setComment("[Failed]: Http Code {$tInfo['http_code']} received from '{$url}'");
			return;
		}
		
        // Cargar el DOM desde el texto HTML de la pagina
		$mainDom = @DOMDocument::loadHTML($mainContent);
		if (!$mainDom){
			$this->setComment("[Failed]: Loading HTML DOM failed");
			return;
		}
		
        // Crear el Objeto XPATH para la Pagina Principal
		$XPath = new DOMXPath($mainDom);
		
        // Calcular la base para las dependencias de este documento,
        // teniendo en cuenta el tag 'base' o la url original
        $baseTag = $XPath->query("//base");
        
        $base = $url;
        if ($baseTag->length > 0){
            $baseTag = $baseTag->item(0);
            if ($baseTag->hasAttribute("href")){
                $base = $baseTag->hasAttribute("href");
            }
        }                 
        
        $baseUrl = Url::Parse($base);
        if (!$baseUrl) $baseUrl = Url::Parse($url);          
        
        // Obtener todos los nodos que representan un anuncio y
        // extraer su url
        $addNodeCollection = $XPath->query("//table[@class='adsterix_set']//td[a]");
        $addsLinks = array();
		foreach ($addNodeCollection as $node){
			$linkStr = $XPath->query("a", $node)->item(0)->getAttribute("href");
            
            $addsLinks[] = $baseUrl->ComputeNewUrl($linkStr)->Save();
		}
        
        // Ir descargando los anuncios mientras sea necesario, usar un algoritmo
        // para determinar si dos anuncios son "muy parecidos"
        // Algoritmo :
        // 1) Descargar M anuncios (M = 15 o 20)
        // 2) Para Todo A en Descargados, Si A Sobrepasa el umbral con cada
        //    elemento previamente agregado, entonces agregar A a la Lista y M--
        // 3) Si M > 0, Volver a 1
        // 4) Mostrar los resultados obtenidos
        
        $count = count($addsLinks);
        $addsInfo = array();
        for ($i = 0, $M = self::NUM_RESULTS; $i < $count && $M > 0; $M = self::NUM_RESULTS - count($addsInfo)){
            
            // Obtener los siguientes M enlaces mientras queden
            $currLinks = array();
            $i_0 = $i;
            for(; $i < $M + $i_0 && $i < $count; $i++){
                $currLinks[] = $addsLinks[$i];
            }
            
            // Obtener el contenido de los Anuncios
            $addsContent = EPHelper::MultiGetUrl($currLinks, 20, $tInfo);
            
            for($k = 0; $k < $M; $k++){
                // Si esta transferencia no termino correctamente saltar a la siguiente
                if ($tInfo[$k]['http_code'] != 200)
                    continue;
                
                // Cargar el documento DOM desde el texto HTML
                $addDoc = new DOMDocument();
                $addDoc->preserveWhiteSpace = false;
                $addsContent[$k] = preg_replace('#\<br\s*(|\/)\>#i', "&lt;br /&gt;", $addsContent[$k]);
                
                if (!@$addDoc->loadHTML($addsContent[$k]))
                    continue;
                 
                // Crear el XPath    
                $XPath = new DOMXPath($addDoc);
                
                // Crear una instancia de la clase AddInfo y almacenar en el la informacion
                // del anuncio actual.
                $addInfo = new AddInfo();
                // Encabezado del Anuncio
                $addInfo->title = trim(utf8_decode($XPath->query("//*[@class='headingText']")->item(0)->nodeValue));
                
                // Precio y Fecha
                $showAddBlock = $XPath->query("//div[@id='show-ad-block']//div[@id='lineBlock']//span[@class='normalText']");
                if ($showAddBlock->length > 1){
                    $addInfo->price = trim(utf8_decode($showAddBlock->item(0)->nodeValue));
                    $addInfo->date = trim(utf8_decode($showAddBlock->item(1)->nodeValue));    
                }else{
                    $addInfo->price = null;
                    $addInfo->date = trim(utf8_decode($showAddBlock->item(0)->nodeValue));                                                           
                }
                
                // Texto del Anuncio
                $addInfo->text = trim(utf8_decode(($XPath->query("//span[@class='showAdText']")->item(0)->nodeValue)));
                
                // Informacion de contacto
                $contactNodes = $XPath->query("//div[@id='contact']//div[@id='lineBlock']");
                foreach($contactNodes as $node){
                    $fieldName = utf8_decode($XPath->query(".//span[@class='headingText2']", $node)->item(0)->nodeValue);
                    $fieldValue = trim(utf8_decode($XPath->query(".//span[@class='normalText']", $node)->item(0)->nodeValue));
                    switch(strtolower(trim($fieldName))){
                        case "email:":
                            $addInfo->email = $fieldValue;
                            break;
                        case "nombre:":
                            $addInfo->name = $fieldValue;
                            break;
                        default /*Telefono*/:
                            $addInfo->phone = $fieldValue; 
                            break;
                    }
                }
                
                // Tratar de extraer el telefono y correo desde el texto
                // Telefono
                if ($addInfo->phone == null){
                    if (preg_match_all('#[\d\s\-]+#', $addInfo->title . $addInfo->text, $matches) > 0){
                        foreach ($matches[0] as $phone_str){
                            $number = str_replace(array(" ", "\r", "\n", "\t", "-"), "", $phone_str);
                            if (strlen($number) >= 6 && strlen($number) <= 11){
                                if (!$addInfo->phone){
                                    $addInfo->phone = $number;
                                }else{
                                    if (stripos($addInfo->phone, $number) === false)
                                        $addInfo->phone .= ", {$number}";
                                }
                            }
                        }
                    }
                }
                // Correo, solo buscar en el texto si el correo esta oculto
                $mailAddress = MailAddress::Parse($addInfo->email);
                if ($mailAddress && strtolower($mailAddress->host) == "in.revolico.net"){
                    if (preg_match_all('#[\w\d\.\-_]+@[\w\d\.\-_]+#', $addInfo->title . $addInfo->text, $matches) > 0){
                        foreach ($matches[0] as $email){
                            if (stripos($addInfo->email, $email) === false)
                                $addInfo->email .= ", {$email}";    
                        }
                    }    
                }
                
                // Agregar la informacion del anuncio a la coleccion si "se considera diferente"
                // de cada uno de ellos
                if (!self::EqualToSome($addsInfo, $addInfo)) 
                    $addsInfo[] = $addInfo;
            }
        }
        
        $html = $this->FormatResults($addsInfo, (string)$this->data->_default);
        
        $msg = $this->CreateResponseMessage();
        
        $msg->subject = "Buscando {$this->data->_default} en Revolico";
        $msg->body = $html;
        $msg->altBody = "Necesitas cambiar a la vista HTML para ver este mensaje";
        
        $this->SendResponseMessage($msg);
	}
    
    protected function RunSearchRevolico(){
        $query = urlencode((string)$this->data->_default);
        if (strtoupper(substr(PHP_OS, 0, 3) != "WIN")) 
            $url = "http://www.revolico.com/search.html?q={$query}";
        else
            $url = "http://www.revolico.com/computadoras/laptop/";
        
        // Descargar la pagina de resultados
        $mainContent = EPHelper::GetUrl($url, 30, $tInfo);
        
        // Comprobar que todo salio bien
        if ($tInfo['http_code'] != 200){
            $this->setComment("[Failed]: Http Code {$tInfo['http_code']} received from '{$url}'");
            return;
        }
        
        // Cargar el DOM desde el texto HTML de la pagina
        $mainDom = @DOMDocument::loadHTML($mainContent);
        if (!$mainDom){
            $this->setComment("[Failed]: Loading HTML DOM failed");
            return;
        }
        
        // Crear el Objeto XPATH para la Pagina Principal
        $XPath = new DOMXPath($mainDom);
        
        // Calcular la base para las dependencias de este documento,
        // teniendo en cuenta el tag 'base' o la url original
        $baseTag = $XPath->query("//base");
        
        $base = $url;
        if ($baseTag->length > 0){
            $baseTag = $baseTag->item(0);
            if ($baseTag->hasAttribute("href")){
                $base = $baseTag->hasAttribute("href");
            }
        }                 
        
        $baseUrl = Url::Parse($base);
        if (!$baseUrl) $baseUrl = Url::Parse($url);          
        
        // Obtener todos los nodos que representan un anuncio y
        // extraer su url
        $addNodeCollection = $XPath->query("//table[@class='adsterix_set']//td[a]");
        $addsLinks = array();
        foreach ($addNodeCollection as $node){
            $linkStr = $XPath->query("a", $node)->item(0)->getAttribute("href");
            
            $addsLinks[] = $baseUrl->ComputeNewUrl($linkStr)->Save();
        }
        
        // Ir descargando los anuncios mientras sea necesario, usar un algoritmo
        // para determinar si dos anuncios son "muy parecidos"
        // Algoritmo :
        // 1) Descargar M anuncios (M = 15 o 20)
        // 2) Para Todo A en Descargados, Si A Sobrepasa el umbral con cada
        //    elemento previamente agregado, entonces agregar A a la Lista y M--
        // 3) Si M > 0, Volver a 1
        // 4) Mostrar los resultados obtenidos
        
        $count = count($addsLinks);
        $addsInfo = array();
        for ($i = 0, $M = self::NUM_RESULTS; $i < $count && $M > 0; $M = self::NUM_RESULTS - count($addsInfo)){
            
            // Obtener los siguientes M enlaces mientras queden
            $currLinks = array();
            $i_0 = $i;
            for(; $i < $M + $i_0 && $i < $count; $i++){
                $currLinks[] = $addsLinks[$i];
            }
            
            // Obtener el contenido de los Anuncios
            $addsContent = EPHelper::MultiGetUrl($currLinks, 20, $tInfo);
            
            for($k = 0; $k < $M; $k++){
                // Si esta transferencia no termino correctamente saltar a la siguiente
                if ($tInfo[$k]['http_code'] != 200)
                    continue;
                
                $addInfo = $this->GetAddInfoFromHtml($addsContent[$k]);
                if (!$addInfo)
                    continue;
                    
                // Enlace al anuncio(A traves del propio servicio)
                $path = Url::Parse($currLinks[$k])->path;
                $addInfo->link = "mailto:". $this->serverAddress . "?subject=@m:add:$path";
                
                // Agregar la informacion del anuncio a la coleccion si "se considera diferente"
                // de cada uno de ellos
                if (!self::EqualToSome($addsInfo, $addInfo)) 
                    $addsInfo[] = $addInfo;
            }
        }
        
        $html = $this->FormatResults($addsInfo, (string)$this->data->_default);
        
        $msg = $this->CreateResponseMessage($html, "Buscando {$this->data->_default} en Revolico");
        
        $this->SendResponseMessage($msg);
    }
    
    protected function RunRevAdd(){
        $url = "http://www.revolico.com{$this->data->_default}";
        
        $addContent = EPHelper::GetUrl($url, 20, $tInfo);
        // Comprobar que todo salio bien
        if ($tInfo['http_code'] != 200){
            $this->setComment("[Failed]: Http Code {$tInfo['http_code']} received from '{$url}'");
            return;
        }
        
        $addInfo = $this->GetAddInfoFromHtml($addContent);
        if (!$addInfo){
            $this->setComment("[Failed]: Imposible to Parse add at '$url'");
            return;
        }
        
        $addInfo->link = "mailto:{$this->serverAddress}?subject=@m:add:{$this->data->_default}";
        
        $html = $this->FormatResults(array($addInfo), NULL, TRUE);
        $msg = $this->CreateResponseMessage($html, $addInfo->title);
        
        $this->SendResponseMessage($msg);
    } 
    
    protected function RunRevFoto(){
        $url = "http://www.revolico.com{$this->data->_default}";
        
        $addContent = EPHelper::GetUrl($url, 20, $tInfo);
        // Comprobar que todo salio bien
        if ($tInfo['http_code'] != 200){
            $this->setComment("[Failed]: Http Code {$tInfo['http_code']} received from '{$url}'");
            return;
        }
        
        $addInfo = $this->GetAddInfoFromHtml($addContent);
        if (!$addInfo){
            $this->setComment("[Failed]: Imposible to Parse add at '$url'");
            return;
        }
        
        $addInfo->link = $url;
        $baseUrl = $addInfo->base != NULL ? Url::Parse("{$addInfo->base}") : Url::Parse($addInfo->link);
        
        $pic_uris = array();
        foreach($addInfo->pictures as $pic){
            $picUrl = $baseUrl->ComputeNewUrl($pic);
            $pic_uris[] = "$picUrl";
        }
        
        $contents = EPHelper::MultiGetUrl($pic_uris);
        
        $msg = $this->CreateResponseMessage("", "++Fotos : {$addInfo->title}");
        $count = count($pic_uris);
        
        for($i = 0; $i < $count; $i++){
            $name = basename($pic_uris[$i], ".jpg") . ".jpg";
            $msg->AddAttachment($name, $contents[$i], "image/jpeg", "INLINE");
        }
        
        $this->SendResponseMessage($msg);
    }
    
    protected function InsertRevolico(){
        
    }
    
    protected function RunHelp(){
        
    }
    
    // Auxiliar Methods
    protected function GetAddInfoFromHtml($addContent){
        // Cargar el documento DOM desde el texto HTML
        $addDoc = new DOMDocument();
        $addDoc->preserveWhiteSpace = false;
        $addContent = preg_replace('#\<br\s*(|\/)\>#i', "&lt;br /&gt;", $addContent);
        
        if (!@$addDoc->loadHTML($addContent))
            continue;
         
        // Crear el XPath    
        $XPath = new DOMXPath($addDoc);
        
        // Crear una instancia de la clase AddInfo y almacenar en el la informacion
        // del anuncio actual.
        $addInfo = new AddInfo();
        
        // Encabezado del Anuncio
        $addInfo->title = @trim(utf8_decode($XPath->query("//*[@class='headingText']")->item(0)->nodeValue));
        if (!$addInfo->title)
            return FALSE;
        
        // Precio y Fecha
        $showAddBlock = $XPath->query("//div[@id='show-ad-block']//div[@id='lineBlock']//span[@class='normalText']");
        if ($showAddBlock->length > 1){
            $addInfo->price = trim(utf8_decode($showAddBlock->item(0)->nodeValue));
            $addInfo->date = trim(utf8_decode($showAddBlock->item(1)->nodeValue));    
        }else{
            $addInfo->price = null;
            $addInfo->date = trim(utf8_decode($showAddBlock->item(0)->nodeValue));                                                           
        }
        
        // Texto del Anuncio
        $addInfo->text = trim(utf8_decode(($XPath->query("//span[@class='showAdText']")->item(0)->nodeValue)));
        
        // Informacion de contacto
        $contactNodes = $XPath->query("//div[@id='contact']//div[@id='lineBlock']");
        foreach($contactNodes as $node){
            $fieldName = utf8_decode($XPath->query(".//span[@class='headingText2']", $node)->item(0)->nodeValue);
            $fieldValue = trim(utf8_decode($XPath->query(".//span[@class='normalText']", $node)->item(0)->nodeValue));
            switch(strtolower(trim($fieldName))){
                case "email:":
                    $addInfo->email = $fieldValue;
                    break;
                case "nombre:":
                    $addInfo->name = $fieldValue;
                    break;
                default /*Telefono*/:
                    $addInfo->phone = $fieldValue; 
                    break;
            }
        }
        
        // Tratar de extraer el telefono y correo desde el texto
        // Telefono
        if ($addInfo->phone == null){
            if (preg_match_all('#[\d\s\-]+#', $addInfo->title . $addInfo->text, $matches) > 0){
                foreach ($matches[0] as $phone_str){
                    $number = str_replace(array(" ", "\r", "\n", "\t", "-"), "", $phone_str);
                    if (strlen($number) >= 6 && strlen($number) <= 11){
                        if (!$addInfo->phone){
                            $addInfo->phone = $number;
                        }else{
                            if (stripos($addInfo->phone, $number) === false)
                                $addInfo->phone .= ", {$number}";
                        }
                    }
                }
            }
        }
        // Correo, solo buscar en el texto si el correo esta oculto
        $mailAddress = MailAddress::Parse($addInfo->email);
        if ($mailAddress && strtolower($mailAddress->host) == "in.revolico.net"){
            if (preg_match_all('#[\w\d\.\-_]+@[\w\d\.\-_]+#', $addInfo->title . $addInfo->text, $matches) > 0){
                foreach ($matches[0] as $email){
                    if (stripos($addInfo->email, $email) === false)
                        $addInfo->email .= ", {$email}";    
                }
            }    
        }
        
        // Pictures
        $baseNode = $XPath->query("//base");
        $addInfo->base = $baseNode->length > 0 ? $baseNode->item(0)->attributes->getNamedItem("href")->nodeValue : NULL;
        $addInfo->base = $addInfo->base ? $addInfo->base : NULL;
        
        $picNodes = $XPath->query("//*[@class='photo-frame']//img");
        for($i = 0; $i < $picNodes->length; $i++){
            $addInfo->pictures[] = $picNodes->item($i)->attributes->getNamedItem("src")->nodeValue;
        }
        
        return $addInfo;
    }
    
    protected static function EqualToSome(array $addCollection, AddInfo $add){
        
        $cEmail = 0; $cPhone = 0;
        foreach($addCollection as $singleAdd){
            // Comprobar coincidencias en los telefonos
            if ($singleAdd->phone && $add->phone){
                $phoneCollectionA = explode(", ", $singleAdd->phone);
                $phoneCollectionB = explode(", ", $add->phone);
                
                $cPhone += count(array_intersect($phoneCollectionA, $phoneCollectionB)) > 0 ? 1 : 0;
                if ($cPhone >= 3)
                    return true;
            }
            // Comprobar coincidencias en los correos
            if ($singleAdd->email && $add->email){
                $emailCollectionA = explode(", ", $singleAdd->email);
                $emailCollectionB = explode(", ", $add->email);
                
                $cEmail += count(array_intersect($emailCollectionA, $emailCollectionB)) > 0 ? 1 : 0;
                if ($cEmail >= 3)
                    return true;
            }    
            // Comprobar la similitud entre los titulos (Usa la distancia levenshtein)
            $titleA = substr($singleAdd->title, 0, 50);
            $titleB = substr($add->title, 0, 50);
            $delta = levenshtein($titleA, $titleB);
            
            $max_len = max(strlen($titleA), strlen($titleB));
            $r = ($max_len - $delta) / $max_len;
            
            if ($r > 0.9)
                return true;
        }
        
        return false;
    }
    
    protected function FormatResults($addsInfo, $query = NULL, $showAll = FALSE){
        $html = <<<HTML
<html><head><style type="text/css">body{background:#eff5fb;color:#333;margin:4pt;font-family:Segoe UI,Verdana,sans serif;}hr{border:2px solid red}h3,h4{color:darkblue;}.date,.contact-info{font-size:90%}.date span,.contact-info span{color:darkred;}.add-wrapper{margin:5pt}p,.nav{padding:4pt;border:1px solid #333;-webkit-border-radius:4pt;width:100%}p{background:white}.add-wrapper p{width:96%}.nav{background:#ffffcc}.nav a{color:darkred;text-decoration:none}.nav td{width:33%;text-align:center}.nav td:first-child{text-align:left}.nav td:last-child{text-align:right}</style></head></body>
HTML;
 
   if ($query != NULL) 
    $html .= "<h3>Buscando \"$query\" en Revolico</h3>";
   else
    $html .= "<h3>Detalles del Anuncio</h3>";
        
        $k = 0;
        foreach($addsInfo as $add){
            $sample_length = 256;
            
            $text_sample = strlen($add->text) > $sample_length ? substr($add->text, 0, $sample_length) : NULL;
            
            $ant = $k - 1;
            $sig = $k + 1;
            $html .= "<hr/>";

            if (count($addsInfo) > 1){
                
                $html .= <<<HTML
<table id="add-$k" cellspacing=0 cellpadding=0 class="nav"><tr><td><a href="#add-$ant">Anterior</a></td><td><a href="#">Inicio</a></td><td><a href="#add-$sig">Siguiente</a></td></tr></table>
HTML;
            }
            
            $html .= <<<HTML
<div class="add-wrapper"><h4>{$add->price} - {$add->title}</h4><div class="date"><span>{$add->date}</span></div>
HTML;
  
  if (!$showAll && $text_sample != NULL) {
    $html.= "<p>{$text_sample} ...<br/><br/><a href=\"{$add->link}\">Leer Todo el Anuncio ...</a></p>";
  }else{
    $html.= "<p>{$add->text}</p>";
  }
  
  if ($nPics = count($add->pictures)){
    $s = $nPics > 1 ? "s" : "";
    $picsLink = str_replace(":add:", ":pics:", $add->link);
    $html .= "<a href=\"{$picsLink}\">Tiene $nPics foto{$s}. Toca aqui para verla{$s} ...</a><br/><br/>";
  }

  $html .= <<<HTML
  <div class="contact-info"><div><span>Email: </span>{$add->email}</div><div><span>Nombre: </span>{$add->name}</div><div><span>Telefono: </span>{$add->phone}</div></div></div>
HTML;
            $k++;
        }
        
        $html .= <<<HTML
</body>        
</html>        
HTML;
 
        return $html;
    }
    // End Auxiliar
}

class AddInfo{
    var $link;
    var $base;
    var $price = null;
    var $date;
    var $title;
    var $text;
    var $email;
    var $phone = null;
    var $name = null;
    var $pictures = null;
}