<?php
/**
* @desc Guardar y leer logs.
* 
* @last_update 30/09/2015
*/

  function mapResults($v){
    $date = date("Y-m-d H:i", $v['time']);  
	return "{$date}\t{$v['tag']} :\t{$v['text']}";
  }
 /**
  * Provee un mecanismo para crear logs para monitorear el comportamiento de los
  * scripts
  * @author Abraham Toledo Sanchez <a.sanchez@lab.matcom.uh.cu>
  */
  class Logs {
    private $TABLE_NAME = "logs2";
      
	private static $_instance;
	
	public static function GetInstance(){
		if (self::$_instance == null){
			self::$_instance = new Logs();
		}
		
		return self::$_instance;
	}
	
	function __construct(){
        DBHelper::Query(
<<<SQL
        CREATE TABLE IF NOT EXISTS {$this->TABLE_NAME} (
            time INT NOT NULL, 
            tag TEXT, 
            text TEXT
        )
SQL
        , $error);
    }
	
	function __destruct(){ }
	
    /**
    * @desc Agrega una entrada en el log del sistema
    */
	public function addEntry($message, $tag = ""){
		if (@$_REQUEST['verbose']) 
            print $message;
        
        $time = time();
        $tag = DBHelper::escape_string($tag);
		$message = DBHelper::escape_string($message);
		DBHelper::Query("INSERT INTO {$this->TABLE_NAME} (time, tag, text) VALUES ('$time', '$tag', '$message')");
	}
	
	public function getLogs($key = NULL){
		if ($key !== NULL){		
			return array_map("mapResults", DBHelper::Select($this->TABLE_NAME, '*'));
		}else{
			return array_map("mapResults", DBHelper::Select($this->TABLE_NAME, '*', "tag LIKE '%$key%'"));
		}
	}
	
	public function deleteLogs(){
		DBHelper::Query("DELETE FROM {$this->TABLE_NAME}");
	}
	
	// Deprecated
	private function Save(){ }
	// Deprecated
	public function Clear(){ }
 }

?>