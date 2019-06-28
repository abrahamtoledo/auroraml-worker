<?php
require_once DOCUMENT_ROOT . '/common/CDBMySQL.php';

class DBException extends Exception{
    public function __construct($message = NULL, $code = 0, $previous = NULL){
        if ($message == NULL){
            $message = "Error al consultar la base de datos.";
        }
        parent::__construct($message, $code, $previous);
    }
}

class DBHelper{
    /**
    * @desc Realiza una consulta a una tabla de la base de datos
    * @param string Nombre de la Tabla
    * @param string Columnas de la tabla. Lista separada por comas
    * @param string Clausula WHERE
    * @param string Clausula ORDER BY
    * @param string Clausula LIMIT
    * 
    * @returns array Lista de registros. Cada registro es un array donde las claves son los nombres de las columnas
    */
    public static function Select($table, $fields = '*', $where=NULL, $order_by = NULL, $limit = NULL){
        $query = "SELECT $fields FROM $table";
        if ($where != NULL){
            $query .= " WHERE $where";
        }
        if ($order_by != NULL){
            $query .= " ORDER BY $order_by";
        }
        if ($limit != NULL){
            $query .= " LIMIT $limit";
        }
        
        return self::QueryOrThrow($query);
    }
    
    public static function Delete($table, $where){
        $query = "DELETE FROM $table WHERE $where";
        
        return self::QueryOrThrow($query);
    }
    
    /**
     * @param $table
     * @param $data Array of rows
     * @param $fields Array
     */
    public static function Insert($table, $data, $fields = NULL){
        $query = "INSERT INTO $table ";
        if ($fields != NULL) $query .= '('.implode(',',$fields).')';
        $query .= " VALUES ";
        
        $rows = array();
        foreach ($data as $row){
            $rows[] = '(\''.implode('\',\'',$row).'\')';
        }
        $query .= implode(',',$rows);
        
        return self::QueryOrThrow($query);
    }
    
    /** Actualizar un registro en una tabla de la base de datos
     * @param $table
     * @param $data Array of rows
     * @param $fields Array
     */
    public static function Update($table, $kvData, $where=NULL){
        if (empty($table))
            throw new InvalidArgumentException("El parametro \$table no puede estar vacio");
            
        if (count($kvData) < 1)
            throw new InvalidArgumentException("El array \$kvData debe contener al menos una entrada");
        
        $query = "UPDATE $table SET ";
        $ptr = 0;
        foreach($kvData as $k => $v){
            if ($ptr > 0){
                $query .= ", ";
            }
            $query .= "$k='$v'";
            
            $ptr++;
        }
        
        if ($where != NULL){
            $query .= " WHERE $where";
        }
        
        return self::QueryOrThrow($query);
    }

    static $mysqlObject;
    public static function Query($query, &$error = ""){
        if (self::$mysqlObject == NULL){
            self::$mysqlObject = new CDBMySQL(MYSQL_HOST, MYSQL_USER, MYSQL_PASS, MYSQL_DB);
        }
        
        $results = array();
        $qres = self::$mysqlObject->Query($query);
        if ($qres === true){
            
            return true;
        }
        elseif($qres){
            while (self::$mysqlObject->NextRowNumber < self::$mysqlObject->RowCount){
                self::$mysqlObject->ReadRow();
                $results[] = self::$mysqlObject->RowData;
            }
            return $results;
        }
        else{
            $error = sprintf("Error %d : %s", self::$mysqlObject->mysqli->errno,
                self::$mysqlObject->mysqli->error);
            return false;
        }
    }

    public static function QueryOrThrow($query){
        $res = self::Query($query, $err);
        if (!empty($err)){
            throw new DBException($err);
        }
        return $res;
    }

    public static function EscapeString($str){
        if (self::$mysqlObject == NULL){
            self::$mysqlObject = new CDBMySQL(MYSQL_HOST, MYSQL_USER, MYSQL_PASS, MYSQL_DB);
        }

        return self::$mysqlObject->mysqli->escape_string($str);
    }
/* ----------------------------------------------------------------- */
    // Estados de usuario
    public static function is_user_registered($email){
         $res = self::Select('users', 'id', "email='$email'");
         return count($res) > 0;
     }
     public static function is_user_active($email){
         $res = self::Select('users', 'id', "email='$email' AND expiration>=".time());
         return count($res) > 0;
     }
    public static function is_user_name_available($email){
        $res = self::Query("SELECT name FROM users WHERE email='{$email}' LIMIT 1", $mysqlErr);
        return count($res) && $res[0]['name'];
    }
    
    // Codigos de Activacion (PIN)
    public static function pinCodeExists($pinCode){
        return count(self::Query("SELECT * FROM pins WHERE code='$pinCode'")) > 0;
    }
    public static function removePinCode($pinCode){
        self::Query("DELETE FROM pins WHERE code='$pinCode'");
    }
    public static function getPinCodeValue($code){
        $pin_data = self::Select('pins', "*", "code='$code'");
        return count($pin_data) ? $pin_data[0]['value'] : false;
    }
    public static function isPinCodeUsed($code, &$pinCodeInfo = NULL){
        $res = self::Query("SELECT id,user FROM used_pins WHERE code='$code'");
        
        if (count($res) > 0){
            $pinCodeInfo = $res[0];
            return true;
        }else{
            return false;
        }
    }
    
    // Acciones sobre cuentas de usuario
    /**
    * @desc Crea una cuenta de usuario nueva
    * @param string Email de la nueva cuenta
    * @param int Dias de prueba
    */
    public static function createNewAccount($email, $days){
        if (!self::is_user_registered($email)){
            $data = array();
            $data[0][] = $email;
            $data[0][] = time() + 3600 * 24 * $days;
            $data[0][] = USER_CLIENT;
            $data[0][] = 0; // last_usage (TIMESTAMP)
            $data[0][] = 1; // on_trial (1: true, 0: false)
            
            $fields = array('email', 'expiration', 'user_type', 'last_usage', 'on_trial');
            self::Insert('users', $data, $fields);
        }
    }
    public static function activateAccount($email, $days){
        if (!self::is_user_registered($email)){
            self::createNewAccount($email, 0);
        }
        
        self::addActivation($email, $days);
    }
    public static function addActivation($user, $value){
        $val = $value * 3600 * 24;
        
        self::Query("UPDATE users SET on_trial=0, expiration=IF(expiration > UNIX_TIMESTAMP(), expiration + {$val}, UNIX_TIMESTAMP() + {$val}) WHERE email='{$user}'");
        
        $data = self::Query("SELECT expiration FROM users WHERE email='{$user}' LIMIT 1");
        return count($data) > 0 ? $data[0]['expiration'] : 0;
    }
    public static function activateAuroraWithPinCode($user, $pincode){
        $pinCodeValue = self::getPinCodeValue($pincode);
        if (!$pinCodeValue){
            // Si el codigo de activacion ya fue usado por este mismo
            // usuario, se debe indicar que la activacion fue exitosa,
            // pero sin activar realmente
            if (self::isPinCodeUsed($pincode, $pinCodeInfo) &&
                    $pinCodeInfo['user'] == $user){
                return TRUE;
            }
            
            return FALSE;
        }
        
        self::activateAccount($user, $pinCodeValue);
        self::removePinCode($pincode);
        self::Query("INSERT INTO used_pins (code, value, user) VALUES ('$pincode', '$pinCodeValue', '$user')");
        return TRUE;
    }
    public static function getUserExpirationTime($email){
        $data = self::Query("SELECT expiration FROM users WHERE email='$email'");
        if (count($data) > 0){
            $max = 0;
            foreach($data as $rec){
                $max = max($rec['expiration'], $max);
            }
            return $max;
        }
        return 0;
    }
    public static function userOnTrial($email){
        try{
            $res = self::Select('users',
                'on_trial', 
                "email='{$email}'"
                );
                
            return count($res) > 0 && $res[0]['on_trial'];
        }catch (DBException $ex){
            error_log($ex->__toString());
            return false;
        }
    }
    
    // Guardar una direccion email cuando no se este aceptando nuevos 
    // usuarios.
    public static function storeMailAddress($email){
        if (!count(self::Select("stored_mail_addresses", "email", "email='$email'", NULL, "1"))){
            $rec["store_time"]=time();
            $rec["email"]=$email;
            self::Insert("stored_mail_addresses", array($rec), array("store_time", "email"));
        }
    }
    
    // Acciones de Firewall
    public static function hasUserRequestedLicense($email){
        return count( self::Query("SELECT id FROM fw_licenses WHERE email='$email' LIMIT 1") ) > 0;
    }
    public static function isFwLicensed($imei){
        return count( self::Query("SELECT id FROM fw_licenses WHERE imei='$imei'") ) > 0;
    }
    public function registerLicensedUser($email, $imei){
        $time = time();
        
        DBHelper::Query("INSERT INTO fw_licenses (managerId, activationDate, imei, email) ". 
                "VALUES (0, {$time}, '{$imei}', '{$email}')", $error);
                
        if ($error){
            error_log("[SQL_ERROR] : $error");
        }
    }

    public static function escape_string($str){
        if (self::$mysqlObject == NULL){
            self::$mysqlObject = new CDBMySQL(MYSQL_HOST, MYSQL_USER, MYSQL_PASS, MYSQL_DB);
        }

        return self::$mysqlObject->mysqli->escape_string($str);
    }
    public static function mysql_scape_array($array){
         $results = array();
         foreach ($array as $key => $val){
             $results[self::escape_string($key)] = self::escape_string($val);
         }
         return $results;
    }

    
    /**
    * Almacena metadatos sobre la solicitud de una pagina web para poder hacer analitica
    * 
    * @param String $userEmail
    * @param Url $url
    * @param int $time
    */
    public static function storeConnectionMetadata($userEmail, $url, $start_time, $exec_time){
        $res = DBHelper::Query("SELECT id FROM users WHERE email='$userEmail'");
        if (count($res) >= 1){
            $userId = $res[0]['id'];
        }else{
            error_log("No se puede encontrar el usuario $userEmail", E_ERROR);
            trigger_error("No se puede encontrar el usuario $userEmail", E_USER_ERROR);
        }
        
        $fields = array('user_id', 'host', 'path', 'start_time', 'exec_time');
        $data = array($userId, $url->host, $url->path, $start_time, $exec_time);
        
        DBHelper::Insert('connections_metadata', 
                            array($data),
                            $fields);
    }
}
