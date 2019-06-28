<?php

class ServiceClient extends ServiceBase{
	
	protected function Authorized(){
		return true;
	}
	protected function RunService(){
		if (preg_match('#^\:([\w\d\_\-]+)(.*)#ms', $this->data->_default, $match)){
			$action = $match[1];
			$this->data->_default = trim($match[2]);
			if (method_exists($this, $action)){
				$this->$action();
			}else{
				return;
			}
		}
	}
	
    protected function RegisterNewUser(){
        if (!DBHelper::is_user_registered($this->user)){
            $data = array();
            $data[0][] = $this->user;
            $data[0][] = time() + 24 * 3600;
            $data[0][] = USER_CLIENT;
            $data[0][] = 0;
            
            $fields = array('email', 'expiration', 'user_type', 'last_usage');
            DBHelper::Insert('users', $data, $fields);
        }
    }
    
	// Add client methods below
	
	private function Recharge(){
		$this->data->_default = preg_replace('#\D+#', '', $this->data->_default);
		$pin_code = @mysql_real_escape_string($this->data->_default);
		
		$pin_data = DBHelper::Select('pins', "*", "code='$pin_code'");
		$pin_data = count($pin_data) ? $pin_data[0] : false;
		if ($pin_data){
			
            $this->RegisterNewUser();
            
            $pin_data['value'] *= 3600 * 24;
		    
		    $user_data = DBHelper::Select('users', "*", "email='{$this->user}'");
		    $user_data = $user_data[0];
		    
		    $user_data['expiration'] = max(time(), $user_data['expiration']) + $pin_data['value'];
		    
		    DBHelper::Query("UPDATE users SET expiration={$user_data['expiration']} WHERE email='{$this->user}'");
		    
            DBHelper::Query("DELETE FROM pins WHERE code='$pin_code'");
			
			$this->Info();
		}else{
			//$this->SendResponse("Codigo no Valido", "El codigo de recarga pin($pin_code) no es valido. Por favor Compruebe el Numero e intentelo nuevamente");
		}
	}
	
	private function Info(){
		$user_data = DBHelper::Select('users', "*", "email='{$this->user}'");
		
		$subject = "Informacion de Usuario";
		$body = "A continuacion le brindamos la informacion referente a su cuenta: \r\n";
		$body .= "Usuario : {$this->user}\r\n";
		$body .= "Fecha de expiracion de saldo : " . EPHelper::format_date($user_data[0]['expiration'], true);
		$body .= "\r\n\r\n\r\n" . $this->getMailFooter();
		
		$to = $this->user;
		
		$this->SendResponse($subject, $body);
	}

	private function Recarga(){
		$this->Recharge();
	}
	private function Activar(){
		$this->Recharge();
	}
    
    private function SendResponse($subject, $body){
        if ((string)$this->data->thread_id){
            $att = $this->CreateResponse($body);
            $this->SendMail("", "Hola", array(array('name'=> $subject, 'data'=>$att)));
        }else{
            $this->SendMail($body, $subject);
        }
    }
    private function CreateResponse($txt){
        $zipFile = new zipfile();
        $zipFile->AddFromString("response.txt", $txt);
        return $zipFile->getContents();
    }
}