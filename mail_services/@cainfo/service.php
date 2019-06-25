<?php

class ServiceCainfo extends ServiceBase{
	
	protected function Authorized(){
		return true;
	}
	protected function RunService(){
        $reply = new MailMessage();
        
        $reply->subject = "Obtener un Codigo de Activacion";
		$reply->body = file_get_contents(__DIR__ . "/cainfo.html");
        $reply->altBody = file_get_contents(__DIR__ . "/cainfo.txt");
        
        $reply->isHtml = true;
        
        $reply->AddTo(count($this->msg->reply_to) ? $this->msg->reply_to[0] : $this->msg->from[0]);
        $reply->AddFrom($this->msg->to[0]);
        
        if (EPHelper::SendMailMessage($reply, $error)){
            syslog(LOG_INFO, "Mail Sent to {$reply->to[0]}");
            
        }else{
            syslog(LOG_ERR, "Error al enviar correo: $error");
        }
	}
}