<?php
require_once DOCUMENT_ROOT . "/mail_services/CatalystPrivateKey.php";

// Gestiona las licencias de VpnNautaWall.
class ServiceFwLicense extends ServiceBase{
    const SEND_LICENSE_SUBJECT = "Nauta Firewall : Su licencia";
    const SEND_LICENSE_TEXT = "Este correo contiene su licencia en un archivo adjunto. Guárdela en la memoria de su dispositivo.";
    
    const ERR_LICENSEGEN_SUBJECT = "Nauta Firewall : Error al obtener licencia";
    const ERR_LICENSEGEN_TEXT = "Ocurrió un error al obtener su licencia. El código de activación sigue siendo válido para otra operación.";
    
    
    // No registrar al usuario
    protected function RegisterIfNew(){ }
    
    protected function Authorized(){ return true; }
    
    protected function oldRunService(){
        strtok($this->msgSubject, " ");
        
        $strFirstToken = strtok(" ");
        
        // Determinar mediante una euristica muy sencilla el formato del asunto, ie:
        // Si todos los caracteres son digitos es muy improbable que se trate de una
        // cadena codificada con base64 y muy probable sea un imei.
        if (preg_match('#^\d+$#', $strFirstToken)){
            $imei = $strFirstToken;
            $pinCode = strtok(" ");
        }else{
            list($imei, $pinCode) = explode(" ", base64_decode($strFirstToken));
        }
        
        if (strlen($imei) != 15) // No es un IMEI valido
            return;
            
        //$pinCode = @mysql_real_escape_string($pinCode);
            
        if (self::isFwLicensed($imei)){
            // Recuperar licencia. Notificar que el pin sigue siendo valido
            // para otra operacion
            $licenseContent = $this->generateLicense($imei);
            if ($licenseContent == null){
                // Hubo un error, enviar un mensaje alertando
                $this->SendMail("Ocurrio un error al recuperar su licencia", 
                    "Nauta Firewall : Error al recuperar licencia");
                return;
            }
            
            $this->SendMail("Este correo contiene su licencia en un archivo adjunto. Guárdela en la memoria de su dispositivo." . 
                    (DBHelper::pinCodeExists($pinCode) ? " El código de activación que envió no fue necesario. Puede utilizarlo para otra operación." : ""), 
                "Nauta Firewall : Su licencia",
                array(
                    array("name" => "license-{$imei}.lic", "data" => base64_encode($licenseContent))
                ));
        }elseif (DBHelper::pinCodeExists($pinCode)){
            
            // Es un nuevo usuario y el pin es valido.
            $licenseContent = $this->generateLicense($imei);
            if ($licenseContent == null){
                // Hubo un error, enviar un mensaje alertando
                $this->SendMail("Ocurrió un error al obtener su licencia. El código de activación sigue siendo válido para otra operación.", 
                    "Nauta Firewall : Error al obtener licencia");
                return;
            }
            
            // Enviar el archivo de licencia
            $this->SendMail("Este correo contiene su licencia en un archivo adjunto. Guárdela en la memoria de su dispositivo.", 
                "Nauta Firewall : Su licencia",
                array(
                    array("name" => "license-{$imei}.lic", "data" => base64_encode($licenseContent))
                ));
            
            $this->registerLicensedUser($imei);
            
            // Eliminar el codigo pin de la base de datos
            DBHelper::removePinCode($pinCode);
        }else{
            // El cliente no esta registrado y el pin es incorrecto. Mostrar error
            $this->SendMail("El código de activación no es válido.", 
                "NFW: Codigo no valido");
        }
    }
    
    protected function RunService(){
        strtok($this->msgSubject, " ");
        
        $strFirstToken = strtok(" ");
        
        // Determinar mediante una euristica muy sencilla el formato del asunto, ie:
        // Si todos los caracteres son digitos es muy improbable que se trate de una
        // cadena codificada con base64 y muy probable sea un imei.
        if (preg_match('#^\d+$#', $strFirstToken)){
            $imei = $strFirstToken;
            $pinCode = strtok(" ");
        }else{
            list($imei, $pinCode) = explode(" ", base64_decode($strFirstToken));
        }
        
        if (strlen($imei) != 15) // No es un IMEI valido
            return;
            
        $pinCodeExists = DBHelper::pinCodeExists($pinCode);
        if ($pinCodeExists){ 
            // Existe el CA
            $licenseContent = $this->generateLicense($imei);
            if ($licenseContent == null){
                // Hubo un error, enviar un mensaje alertando
                $this->SendMail(self::ERR_LICENSEGEN_TEXT, self::ERR_LICENSEGEN_SUBJECT);
                return;
            }
            
            // Activar un mes de Aurora (BONIFICACION)
            DBHelper::activateAuroraWithPinCode($this->user, $pinCode);
            
            // Enviar el archivo de licencia
            $this->SendMail(
             self::SEND_LICENSE_TEXT . 
            ($pinCodeExists ? "\r\n\r\nComo bonificacion, se adicionaron 30 dias a su activacion de AuroraSuite" : ""), 
                self::SEND_LICENSE_SUBJECT,
                array(
                    array("name" => "license-{$imei}.lic", "data" => base64_encode($licenseContent))
                ));
            
            if (!DBHelper::isFwLicensed($imei)){
                $this->setComment("Nuevo Cliente");
                $this->setComment("Insertando : {$time}, {$imei}, {$email}");
        
                DBHelper::registerLicensedUser($this->user, $imei);
            }
        
        }elseif (DBHelper::isFwLicensed($imei)){
            // No existe el CA pero se esta recuperando la licencia.
            $licenseContent = $this->generateLicense($imei);
            if ($licenseContent == null){
                // Hubo un error, enviar un mensaje alertando
                $this->SendMail(self::ERR_LICENSEGEN_TEXT, self::ERR_LICENSEGEN_SUBJECT);
                return;
            }
            
            // Enviar el archivo de licencia
            $this->SendMail(
                    self::SEND_LICENSE_TEXT, 
                    self::SEND_LICENSE_SUBJECT,
                array(
                    array("name" => "license-{$imei}.lic", "data" => base64_encode($licenseContent))
                ));
            
        }elseif ( DBHelper::is_user_name_available($this->user) && !DBHelper::hasUserRequestedLicense($this->user) ){
            // No es valido el CA, ni esta licenciado el IMEI, pero el usuario puede 
            // obtener una licencia por ser usuario pago de AuroraSuite
            $licenseContent = $this->generateLicense($imei);
            if ($licenseContent == null){
                // Hubo un error, enviar un mensaje alertando
                $this->SendMail(self::ERR_LICENSEGEN_TEXT, self::ERR_LICENSEGEN_SUBJECT);
                return;
            }
            
            
            // Enviar el archivo de licencia
            $this->SendMail(
             self::SEND_LICENSE_TEXT,
                self::SEND_LICENSE_SUBJECT,
                array(
                    array("name" => "license-{$imei}.lic", "data" => base64_encode($licenseContent))
                ));
            
            $this->setComment("Nuevo Cliente");
            $this->setComment("Insertando : {$time}, {$imei}, {$email}");
        
            DBHelper::registerLicensedUser($this->user, $imei);
        }else{
            // El cliente no esta autorizado a obtener la licencia
            $this->SendMail("El código de activación no es válido.", 
                "NFW: Codigo no valido");
        }
    }
    
    private function generateLicense($imei){
        if (openssl_private_encrypt($imei, $crypted, CATALYST_PRIVATE_KEY)){
            return $crypted;
        }else{
            return null;
        }
    }
    
}