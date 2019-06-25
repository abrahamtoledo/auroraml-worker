<?php
define("LOGDB_LEVEL_INFO", 0);
define("LOGDB_LEVEL_WARNING", 1);
define("LOGDB_LEVEL_ERROR", 2);

class LogDb {
    
    private static $_session;
    private $sessId;
    private $isSessionActive;
    
    private function __construct(){
        $this->sessId = md5(uniqid(time(), true));
        $this->startSession();
    }
    
    private function startSession(){
        if ($this->isSessionActive){
            return true;
        }
        
        $this->isSessionActive = true;
        $this->i("[BEG] Session Iniciada");
        return true;
    }
    
    // Public interface
    /**
    * @desc Obtiene una instancia (Singleton) de la 
    * clase para la sesion actual
    * 
    * @returns LogDb
    */
    public function getSession(){
        if (self::$_session == null){
            self::$_session = new LogDb();
        }
        
        return self::$_session;
    }
    
    /**
    * @desc Inserta un mensaje en la sesion actual
    * 
    * @param String el mensaje a insertar
    * @param int El nivel de la alerta
    */
    public function i($message, $eLevel = LOGDB_LEVEL_INFO){
        $iTime = time();
        
        if (!$this->isSessionActive){
            DBHelper::Query(
                "INSERT INTO LogDb (sessId, time, eLevel, message) VALUES ('{$this->sessId}', {$iTime}, {$eLevel}, 'Intento de escribir un log en una sesion cerrada')");
        }
        
        DBHelper::Query(
            "INSERT INTO LogDb (sessId, time, eLevel, message) VALUES ('{$this->sessId}', {$iTime}, {$eLevel}, '{$message}')");
    }
    
    /**
    * @desc Cierra la sesion activa
    */
    public function endSession(){
        if ($this->isSessionActive){
            $this->i("[END] Session Terminada");
        }
        return true;
    }
}