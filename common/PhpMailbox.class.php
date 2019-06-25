<?php

class phpmailbox {
	private $type_map = array(
	0 => 'text',
	1 => 'multipart',
	2 => 'message' ,
	3 => 'application',
	4 => 'audio',
	5 => 'image',
	6 => 'video',
	7 => 'unknown'

	);
	
	private $encoding_map= array(
	0 => '7bit',
	1 => '8bit',
	2 => 'binary',
	3 => 'base64',
	4 => 'quoted-printable' ,
	5 => 'unknown'
	);
	
	/**
	 * Contains the connection stream
	 * @var resource
	 */
	private $imap_conn = NULL;
	public $user = '';
	public $pass = '';
	public $host = 'localhost';
	public $port = '143';
	public $mailbox = 'INBOX';
	/**
	 * One of the values : '' for imap, 'pop3' for pop3
	 * @var string
	 */
	public $protocol = '';
	/**
	 * Array of aditional flags such as : 'ssl'
	 * @var Array
	 */
	public $flags = array('novalidate-cert');
	
	function __construct() {
		
	}
	
	function __destruct(){
		$this->Close(false);
	}
	
	private function GetConnectionStr(){
		$str_conect = $this->host.':'.$this->port;
		if ($this->protocol != ''){
			$str_conect .= '/'.$this->protocol;
		}
		
		$count = count($this->flags);
		for($i = 0; $i < $count; $i++){
			$str_conect .= '/'.$this->flags[$i];
		}
		
		$str_conect = '{' . $str_conect . '}';
		$str_conect .= $this->mailbox;
		
		return $str_conect;
	}
	
	/**
	 * Opens the Connection (<stong>imap</strong> or <stong>pop3</strong>)
	 */
	function Open(){
		$str_conn = $this->getConnectionStr();
		$this->imap_conn = @imap_open($str_conn, $this->user, $this->pass);
		
		return (bool)($this->imap_conn);
	}
	
	/**
	 * Closes the connection
	 */
	function Close($expunge = true){
		if ($this->imap_conn != NULL){
			imap_close($this->imap_conn, $expunge ? CL_EXPUNGE : 0);
			$this->imap_conn = NULL;
		}
	}
	
	function Overview($min, $max){
		// $overview = imap_fetch_overview($this->imap_conn, $sequence);
		// $result = array();
		
		// $count = count($overview);
		// for ($i = 0; $i < $count; $i++){
			// $result[$i]['subject'] = $this->str_imap_mime_header_decode($overview[$i]->subject);
			// $result[$i]['date'] = $overview[$i]->date;
			// $result[$i]['from'] = $overview[$i]->from;
			// $result[$i]['msgno'] = $overview[$i]->msgno;
			
			// $result[$i]['size'] = $overview[$i]->msgno;
			// $result[$i]['unseen'] = !$overview[$i]->seen || $overview[$i]->recent;
		// }
		$result = array();
		for($i = $min; $i <= $max; $i++){
			$result[$i] = $this->Message($i, true);
		}
		
		return $result;
	}
	
	function getUnseen(){
		$res = imap_search($this->imap_conn, "UNSEEN");
		return $res !== false ? $res : array();
	}
    
    /**
    * @desc Obtiene un resumen de los mensajes nuevos.
    *       Devuelve un objeto con las siguientes propidades
    *        
    *        subject - the messages subject 
    *        from - who sent it 
    *        to - recipient 
    *        date - when was it sent 
    *        message_id - Message-ID 
    *        references - is a reference to this message id 
    *        in_reply_to - is a reply to this message id 
    *        size - size in bytes 
    *        uid - UID the message has in the mailbox 
    *        msgno - message sequence number in the mailbox 
    *        recent - this message is flagged as recent 
    *        flagged - this message is flagged 
    *        answered - this message is flagged as answered 
    *        deleted - this message is flagged for deletion 
    *        seen - this message is flagged as already read 
    *        draft - this message is flagged as being a draft 
    * 
    * @param array Numeros de secuencia de los mensajes que se quieren abrir
    */
    function fetchOverview($NoMsgs){
        //$unseen = $this->getUnseen();
        $seq = implode(',', $NoMsgs);
        return imap_fetch_overview($this->imap_conn, $seq);
    }
	
	function CountUnseen(){
		return count($this->getUnseen());
	}
	
	function getSeen(){
		$res = imap_search($this->imap_conn, "SEEN");
		return $res !== false ? $res : array();
	}
	
	function CountSeen(){
		return count($this->getSeen());
	}
	
	function Refresh(){
		return !@imap_ping($this->imap_conn) ? $this->Open() : true;
	}
	
    function getAllHeaders($index){
        //$strHeaders = imap_fetchheader($this->imap_conn, $index);
//        
//        $currHeader = "";
//        $line = strtok($strHeaders, "\r\n");
//        while($line){
//            
//        }
    }
    
	function Message($index, $headerOnly = false){
		if ($index > $this->Count()){
			return false;
		}
		
		$msg = new MailMessage;
		$header = imap_header($this->imap_conn, $index);
		
		$msg->subject = $this->str_imap_mime_header_decode($header->subject);
		if ($header->to) foreach ($header->to as $to){
			$msg->addTo(MailAddress::FromImapAddress($to));
		}
		if ($header->reply_to) foreach($header->reply_to as $reply_to){
			$msg->addReplyTo(MailAddress::FromImapAddress($reply_to));
		}
		if ($header->from) foreach ($header->from as $from){
			$msg->addFrom(MailAddress::FromImapAddress($from));
		}
		if ($header->cc) foreach ($header->cc as $cc){
			$msg->addcc(MailAddress::FromImapAddress($cc));
		}
		if ($header->bcc) foreach($header->bcc as $bcc){
			$msg->addBcc(MailAddress::FromImapAddress($bcc));
		}
		if ($header->sender) foreach ($header->sender as $sender){
			$msg->addSender(MailAddress::FromImapAddress($sender));
		}
		if ($header->return_path) foreach ($header->return_path as $return_path){
			$msg->addSender(MailAddress::FromImapAddress($return_path));
		}
		
		$msg->udate = $header->udate;
		$msg->size = $header->Size;
		$msg->unseen = $header->Unseen == 'U' || $header->Recent == 'N';
        $msg->messageId = $header->message_id;
        $msg->inReplyTo = $header->in_reply_to;
		
		if ($headerOnly) return $msg;
		
		$struct = imap_fetchstructure($this->imap_conn, $index);
		
		$msg->isHtml = false;
		$this->AddPart($index, '', $struct, $msg);
		
		return $msg;
	}
	
	function RawMessage($index, $headerOnly = false){
		if ($index > $this->Count()){
			return false;
		}
	
		$str = imap_fetchheader($this->imap_conn, $index) . "\r\n\r\n";
		if ($headerOnly) return $str;
		
		$str .= imap_body($this->imap_conn, $index);
		return $str;
	}
	
	function AddPart($index, $prefix, $struct, &$msg){
		if (!empty($struct->parts)){
			$count = count($struct->parts);
			for($i = 1; $i < $count + 1; $i++){
				if ($prefix != ''){
					$this->AddPart($index, $prefix . ".$i", $struct->parts[$i-1], $msg);
				}else{
					$this->AddPart($index, "$i", $struct->parts[$i-1], $msg);
				}
			}
		}
		else{
			if ($prefix != ""){
				$pinfo['content'] = imap_fetchbody($this->imap_conn, $index, $prefix);
			}
			else{
				$pinfo['content'] = imap_body($this->imap_conn, $index);
			}
			
			$pinfo['content'] = $this->decodeData($pinfo['content'], $struct->encoding);
			
			$pinfo['type'] = $this->type_map[$struct->type];
			$pinfo['subtype'] = strtolower($struct->subtype);
			$pinfo['mime-type'] = $pinfo['type'] .'/'.$pinfo['subtype'];
			
			// Add Part To Message
            if($struct->ifdisposition){
                $dparams = array();
                if ($struct->ifdparameters){
                    foreach ($struct->dparameters as $param){
                        $dparams[strtolower($param->attribute)] = $param->value;
                    }
                }
                
                $name = $dparams["filename"] ? $dparams["filename"] : 
                                        ($dparams['name'] ? $dparams['name'] : "new_file");
                $msg->AddAttachment($name, $pinfo['content'], $pinfo['mime-type'], $struct->disposition);
                
            }elseif ($pinfo['mime-type'] == "text/html"){
				$msg->isHtml = true;
				$msg->altBody = $msg->body;
				$msg->body = $pinfo['content'];
			}else/*if($pinfo['mime-type'] == "text/plain")*/{
				if ($msg->isHtml){
					$msg->altBody = $pinfo['content'];
				}else{
					$msg->body = $pinfo['content'];
				}
			}
		}
	}
	
	/**
	 * Fetches the number of messages in the current MAILBOX
	 */
	function Count(){
		return imap_num_msg($this->imap_conn);
	}
	
	function softDelete($index){
		imap_delete($this->imap_conn, $index);
	}
	
	/**
	 * Delete the messages specified in the current MAILBOX
	 * @param int
	 */
	function Delete($index){
		$this->SoftDelete($index);
		$this->Expunge();
	}
	
	/**
	 * Deletes a range of messages. <strong>$range</strong> is in the following
	 * format : X,Y ; X:Y or Combination of both where X,Y means delete X and Y;
	 * and X:Y means delete all items in [X,Y] interval.
	 *
	 * ie: $range = '1,4:6' which means delete messages 1,4,5,6
	 * @param string $range
	 */
	function softDeleteRange($range){
		$range = str_replace(' ', '', $range);
		$parts = explode(',', $range);
		foreach($parts as $part){
			$matches = array();
			if (preg_match('/([0-9]+):([0-9]+)/', $part, $matches)){
				for($i = $matches[1]; $i <= $matches[2]; $i++){
					imap_delete($this->imap_conn, $i);
				}
			}elseif(preg_match('/^[0-9]+$/', $part)){
				imap_delete($this->imap_conn, $part);
			}
		}
	}
	
	function DeleteRange($range){
		$this->SoftDeleteRange($range);
		$this->Expunge();
	}
	
	function Expunge(){
		imap_expunge($this->imap_conn);
	}
	
	function LastError(){
		return imap_last_error();
	}
	
	// Helpers
	function str_imap_mime_header_decode($text){
		$data = imap_mime_header_decode($text);
		
		$str = "";	foreach ($data as $p) $str .= $p->text;
		
		return $str;
	}
	
	function decodeData($data, $encoding){
		switch ($encoding){
			case 0:
			case 1:
			case 2:
				return $data;
			case 3:
				return base64_decode($data);
			case 4:
				return imap_qprint($data);
			default:
				return $data;
		}
	}
	
}
