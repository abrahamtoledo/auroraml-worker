<?php
require_once dirname(dirname(__FILE__)) . "/@webpage/service.php";

class ServiceRecarga extends ServiceWebpage{
    protected function Authorized(){ return true; }
    protected function RunService(){
        $code = preg_replace('#\s+#', '', $this->data->_default);
        
        /*$value = $this->getCodeValue($code);
        if ($value !== FALSE){
            $this->setComment("[OK]: dias={$value}");
            // Registrar al usuario si es nuevo
            $this->RegisterNewUser();
            
            // Recargar
            $new_expiration = $this->addValue($value);
            
            // Borrar el codigo de la tabla
            $this->DeletePinCode($code);
            
            $this->SendNote("Activado. Fecha de vencimiento: ". date("d/m/Y", $new_expiration));
        }else{
            $this->setComment("[WRONG]");
            $this->SendNote("No Activado. Codigo de recarga incorrecto");
        }*/
        
        $this->data->url = "http://". HTTP_HOST . "/__www/account_expired.php?submit=1&user=". urlencode($this->user) . "&pin_code=" . urlencode($code);
        
        parent::RunService();
    }
    
    protected function getCodeValue($code){
        $pin_data = DBHelper::Select('pins', "*", "code='$code'");
        return count($pin_data) ? $pin_data[0]['value'] : false;
    }
    
    protected function addValue($value){
        $val = $value * 3600 * 24;
        
        DBHelper::Query("UPDATE users SET expiration=IF(expiration > UNIX_TIMESTAMP(), expiration + {$val}, UNIX_TIMESTAMP() + {$val}) WHERE email='{$this->user}'");
        
        $data = DBHelper::Query("SELECT expiration FROM users WHERE email='{$this->user}' LIMIT 1");
        return count($data) > 0 ? $data[0]['expiration'] : 0;
    }
    
    protected function DeletePinCode($code){
        DBHelper::Query("DELETE FROM pins WHERE code='$code'");
    }
    
    protected function RegisterNewUser(){
        if (!DBHelper::is_user_registered($this->user)){
            $data = array();
            $data[0][] = $this->user;
            $data[0][] = time();
            $data[0][] = USER_CLIENT;
            $data[0][] = 0;
            
            $fields = array('email', 'expiration', 'user_type', 'last_usage');
            DBHelper::Insert('users', $data, $fields);
        }
    }
    
    private function SendNote($note){
        if ((string)$this->data->thread_id){
            
            $this->SendResponseAttached( 're_recarga.txt', $note);
        }else{
            $this->SendMail($note, 'Re: recarga');
        }
    }
    
}