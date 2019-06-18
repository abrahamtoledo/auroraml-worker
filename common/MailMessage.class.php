<?php

class MailAttachment{
    var $mimetype;
    var $name;
    var $content;
    var $disposition = "ATTACHMENT";
    var $encoding="base64";
}

class MailAddress{
    var $name;
    var $host;
    var $user;
    
    // devuelve el address
    function __get($name){
        if (strtolower($name) == 'address') return $this->user . '@' . $this->host;
    }
    
    function __construct(){
    }
    
    static function Parse($address){
        if (preg_match('#^(.*)<([^>]+)>$#is', $address, $match)){
            $res = new MailAddress;
            $res->name = trim($match[1]);
            if (preg_match('#^([-_a-z0-9\.\+]+)@(([-_a-z0-9]+\.)+[a-z]{2,5})$#is', $match[2], $_match)){
                $res->user = $_match[1];
                $res->host = $_match[2];
            }else{
                return null;
            }
            return $res;
        }elseif(preg_match('#^([-_a-z0-9\.\+]+)@(([-_a-z0-9]+\.)+[a-z]{2,5})$#is', $address, $_match)){
            $res = new MailAddress;
            $res->name = "";
            $res->user = $_match[1];
            $res->host = $_match[2];
            
            return $res;
        }else{
            return null;
        }
    }
    
    /**
    * @desc Crea una lista de Recipientes a partir de una linea de direcciones
    * @param string Linea de direcciones
    * 
    * @returns array
    */
    static function ParseList($addressLine){
        $parts = preg_split('#,|;#', $addressLine);
        $addList = array();
        foreach($parts as $strAddress){
            $addList[] = self::Parse(trim($strAddress));
        }
        
        return $addList;
    }
    
    static function FromImapAddress($imapAddress){
        $res = new MailAddress;
        $res->name = $imapAddress->personal;
        $res->user = $imapAddress->mailbox;
        $res->host = $imapAddress->host;
        return $res;
    }
    
    function Save(){
        return $this->__toString();
    }
    
    function __toString(){
        if (!empty($this->name)){
            //var_dump($this);
            return "{$this->name} <{$this->user}@{$this->host}>";
        }else{
            return "{$this->user}@{$this->host}";
        }
    }
}

class MailMessage{
    var $from;
    var $reply_to;
    var $sender;
    var $return_path;

    var $to;
    var $cc;
    var $bcc;
    
    var $subject;
    var $body;
    var $altBody;
    var $isHtml;
    
    var $attachments;
    var $customHeaders;
    
    // Only for Imap
    var $udate = 0;
    var $size = 0;
    var $unseen = false;
    
    // Other important fields
    var $messageId = NULL;
    var $inReplyTo = NULL;
    
    public function __construct(){
        $this->subject = "";
        $this->body = "";
        $this->altBody = "";
        $this->isHtml = false;
    
        $this->from = array();
        $this->reply_to = array();
        
        $this->to = array();
        $this->bcc = array();
        $this->cc = array();
        
        $this->attachments = array();
        
        $this->customHeaders = array();
    }
    
    private static function AddMailAddress(&$c_MA, $address){
        if ($address instanceof MailAddress){
            $c_MA[] = $address;
        }elseif(is_string($address)){
            $c_MA[] = MailAddress::Parse($address);
        }
    }
    public function AddFrom($strAddress){
        self::AddMailAddress($this->from, $strAddress);
    }
    
    public function AddReplyTo($strAddress){
        self::AddMailAddress($this->reply_to, $strAddress);
    }
    
    public function AddSender($strAddress){
        self::AddMailAddress($this->sender, $strAddress);
    }
    
    public function AddReturnPath($strAddress){
        self::AddMailAddress($this->return_path, $strAddress);
    }
    
    public function AddTo($strAddress){
        self::AddMailAddress($this->to, $strAddress);
    }
    
    public function AddCc($strAddress){
        self::AddMailAddress($this->cc, $strAddress);
    }
    
    public function AddBcc($strAddress){
        self::AddMailAddress($this->bcc, $strAddress);
    }
    
    public function AddAttachment($name, $content, $mimetype = 'application/octet-stream', $disposition = "ATTACHMENT", $encoding="base64"){
        $att = new MailAttachment;
        $att->name = $name;
        $att->content = $content;
        $att->mimetype = $mimetype;
        $att->disposition = $disposition;
        $att->encoding = $encoding;
        
        $this->attachments[] = $att;
    }
    
    function AddCustomHeader($header){
        $this->customHeaders[] = $header;
    }
    
    function getPlainText(){
        return $this->isHtml ? $this->altBody : $this->body;
    }
    
    /**
    * @param \ZBateson\MailMimeParser\Message Mime message
    */
    static function createFromMailMimeMessage($mimeMessage){
        $msg = new MailMessage();
        
        // Obtener las cabeceras
        $msg->subject = $mimeMessage->getHeaderValue('subject');
        $msg->messageId = $mimeMessage->getHeaderValue('message-id');
        $msg->inReplyTo = $mimeMessage->getHeaderValue('in-reply-to');
        
        // Direcciones
        foreach(
            array(
                'from' => 'from',
                'reply-to' => 'reply_to',
                'sender' => 'sender',
                'to' => 'to',
                'cc' => 'cc',
                'bcc' => 'bcc'
            ) as
            $headerName => $c_Name
        ){
            $header = $mimeMessage->getHeader($headerName);
            if ($header == NULL)
                continue;
                
            $rawValue = $header->getRawValue();
            if ($rawValue != NULL && $rawValue != ""){
                $msg->$c_Name = MailAddress::ParseList($rawValue);
            }
        }
        
        // Otros headers como custom headers
        foreach($mimeMessage->getHeaders() as $header){
            $msg->AddCustomHeader($header->getName() . ': ' . $header->getRawValue());
        }
        
        // Logica de conversion Aqui
        // Obtener el texto html y plano del mensaje
        $htmlContent = $mimeMessage->getHtmlContent();
        if ($htmlContent != NULL){
            $msg->isHtml = TRUE;
            $msg->body = $htmlContent;
            
            $nPlain = $mimeMessage->getTextPartCount();
            $plainContent = '';
            for($i = 0; $i < $nPlain; $i++) {
                $plainContent .= $mimeMessage->getTextContent($i) . "\r\n";
            }
            
            if ($plainContent){
                $msg->altBody = $plainContent;
            }
        }else{
            $msg->isHtml = FALSE;
            
            $nPlain = $mimeMessage->getTextPartCount();
            $plainContent = '';
            for($i = 0; $i < $nPlain; $i++) {
                $plainContent .= $mimeMessage->getTextContent($i) . "\r\n";
            }
            
            if ($plainContent){
                $msg->body = $plainContent;
            }
            
            $msg->altBody = "";
        }
        
        // Adjuntos
        foreach($mimeMessage->getAllAttachmentParts() as $attPart){
            $name = $attPart->getHeaderParameter('content-type', 'name', NULL);
            if ($name == NULL){
                $name = $attPart->getHeaderParameter('content-disposition', 'filename', NULL);
            }
            if ($name == NULL){
                $name = 'new_file';
            }
            
            $msg->AddAttachment(
                $name,
                $attPart->getContent(),
                $attPart->getHeaderValue('content-type'),
                $attPart->getHeaderValue('content-disposition'),
                $attPart->getHeaderValue('content-transfer-encoding')
            );
        }
        
        return $msg;
    }
}
