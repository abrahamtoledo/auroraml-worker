<?php

class SmsACubaApi{
    const PrecioSMSACuba = 3;
    
    var $user; 
    var $password;
    
    var $lastError;
    
    function VerificarCredenciales(){
        // Borramos cualquier error anterior
        $this->lastError = null;
        
        // Operamos con la API
        $res = EPHelper::GetUrl("http://api.smsacuba.com/apilogin.php?login=". urlencode($this->user) . 
                                                              "&password=" . urlencode($this->password));
        
        // Comprobamos si hubo algun error en la operacion
        if (strlen($res) == 0){
            $this->lastError = "Error de Acceso a la API";
            return FALSE;
        }
        
        // De acuerdo con la API si es 
        // correcto el primer caracter es 1
        return substr($res, 0, 1) == 1;
    }
    
    /**
    * @desc Obtiene el saldo en centavos
    */
    function Saldo(){
        // Borramos cualquier error anterior
        $this->lastError = null;
        
        // Operamos con la API
        $res = EPHelper::GetUrl("http://api.smsacuba.com/saldo.php?login=". urlencode($this->user) . 
                                                              "&password=" . urlencode($this->password));
        
        // Comprobamos si no hubo error en la operacion
        if (strlen($res) == 0){
            $this->lastError = "Error de Acceso a la API";
            return FALSE;
        }
        
        
        // De acuerdo con la api si es correcto se muestra un 
        // numero de la forma a.b que representa el saldo. Si
        // no se muestra un mensaje de error.
        if (preg_match('#^(\d+)\.(\d+)$#', $res, $m)){
            return $m[1] * 100 + substr($m[2], 0, 2);
        }else{
            $this->lastError = $res;
            return 0;
        }
    }
    
    /**
    * @desc Envia un mensaje a una lista de numeros (ARRAY). LOS NUMEROS DEBEN TENER EL FORMTO 5xxxyyyy
    */
    function Enviar($numeros, $mensaje, $sender){
        $this->lastError = "";
        $failNums = array();
        
        $post = array();
        $post['login'] = $this->user;
        $post['password'] = $this->password;
        $post['prefix'] = 53;
        // Dejamos number para ultimo ya que lo vamos a ir cambiando
        $post['sender'] = $sender;
        $post['msg'] = $mensaje;
        
        $k = 0;
        $N = count($numeros);
        while($k < $N){
            $lista = array();
            $i = 0;
            while($k < $N && $i < 9){
                $lista[] = $numeros[$k];
                $k++; $i++;
            }
            
            $listNum = implode(',', $lista);
            $post['number'] = $listNum;
            
            $res = EPHelper::PostUrl("http://api.smsacuba.com/api10allcountries.php", $post, 10);
            
            // Comprobar posibles errores
            if (strlen($res) == 0){
                $failNums += $lista;
                $this->lastError .= "Los siguientes recipientes fallaron: {$listNum}. Error de conexion\r\n";
                continue;
            }
            
            if ($res != "SMS ENVIADO"){
                $failNums += $lista;
                $this->lastError .= "Los siguientes recipientes fallaron: {$listNum}. Error: {$res}\r\n";
                continue;
            }
        }
        
        // Si hubo algun error entonces debe devolverse FALSE
        if (count($failNums) > 0){
            //$this->lastError = "Los siguientes recipientes fallaron: " . implode(',', $failNums);
            return FALSE;
        }
        
        return TRUE;
    }
}