<?php

class ServiceSms extends ServiceBase{
	
    var $warnings;
    
	protected function Authorized(){
		return true;
	}
	protected function RunService(){
		strtok($this->msg->subject, " ");
        $num = preg_replace('#\D#', "", strtok(" "));
        $msg = trim(strtok("\0"));
        
        if (!$num || !preg_match('#5\d{7}#', $num)){
            $this->SendMail("El numero debe ser de la forma 5#######", "Error sms: Numero no valido");
            return;
        }
        
        if (!$msg){
            $this->SendMail("El mensaje no puede estar vacio", "Error sms {$num}");
            return;
        }
        
        if (strlen($msg) > 160){
            $msg = substr($msg, 0, 160);
            $this->warnings[] = "-La longitud maxima del sms es 160 caracteres. Se recorto a: {$msg}";
        }
        
        $this->sendSms($num, $msg);
	}
    
    private function sendSms($num, $msg){
        $cookie = tempnam("", "sms_cookie");
        
        $url = "http://moises-sms.com/login.html";
        
        $pvars = array(
            "login_userName" => "abrahamtoledo90@gmail.com",
            "login_pass" => "lidialira25",
            "login_btnEnviar" => "Enviar",
            "login_secu" => "wfobt"
        );
        
        $result = EPHelper::PostUrl($url, $pvars, 15, $tInfo, $cookie, "", true);
        
        if (stripos($tInfo['url'], "perfil.html") === FALSE){
            $this->SendMail("Error al iniciar sesion\r\n{$result}", "Error sms {$num} {$msg}");
            return;
        }
        
        $url = "http://moises-sms.com/enviar-sms.html";
        
        $pvars = array(
            "r_sms_celular" => $num,
            "r_sms_msg" => '',
            "r_sms_sms" => $msg,
            "r_sms_btnEnviar" => "Enviar",
            "r_sms_secu" => "PBtaC"
        );
        
        $result = EPHelper::PostUrl($url, $pvars, 15, $tInfo, $cookie, "", true);
        
        if (stripos($result, "enviado satisfactoriamente") === FALSE){
            $this->SendMail("Error al enviar\r\n{$result}", "Error sms {$num} {$msg}");
            return;
        }
        
        $body = "";
        if (count($this->warnings))
            $body = "Warnings:\r\n" . implode("\r\n", $this->warnings);
        
        $this->SendMail($body, "Enviado con exito al {$num}");
        $this->saveRecord($this->user, $num, $msg, time());
    }
    
    private function saveRecord($sender, $num, $msg, $time){
        DBHelper::Query("INSERT INTO sms_logs (sender, receiver, text, time) VALUES ('{$sender}', '{$num}', {$msg}, '{$time}')");
    }
}