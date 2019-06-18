<?php
define('USER_ALL', 0);
define('USER_CLIENT', 1);
define('USER_ADMIN', 2);
define('USER_SADMIN', 3);

abstract class ServiceAbstract{
    var $_comments = array();
    
    var $user;
    /** @var MailAddress */
    var $senderMailAddres;
    var $msgSubject;
    var $serverAddress;
    var $serverAddressName;
    
    var $start_time;
    var $exec_time;
    
    protected function setComment($str){
        $this->_comments[] = $str;
    }
    protected function getComments($indent = 0){
        return $this->Indent(implode("\r\n", $this->_comments), $indent);
    }
    
    protected function Indent($str, $len = 0){
        $c = $len;
        $indent = "";
        for($i = 0; $i < $c; $i++) $indent .= "\t";
        
        return $indent . str_replace("\n", "\n$indent", $str);
    }
    
    public function Run(){
        $logs = Logs::GetInstance();
        $this->start_time = time();
        $this->RegisterIfNew();
        
        if ($this->Authorized()){
            if (_DEBUG_){
                $logs->addEntry("[OK] Autorizado");
            }
            
            $this->setLastUsage();
            
            if (_DEBUG_){
                $logs->addEntry("[OK] Last usage");
            }
            
            $this->RunService();
            
            if (_DEBUG_){
                $logs->addEntry("[OK] RunService()");
            }
            
            $this->Dispose();
        }else{
            if (_DEBUG_){
                $logs->addEntry("[ERROR] No Autorizado");
            }
        }
        $this->exec_time = time() - $this->start_time;
    }
    
    protected function RegisterIfNew(){
        DBHelper::createNewAccount($this->user, DIAS_PRUEBA);
    }
    
    protected function setLastUsage(){
        $time = time();
        $query = "UPDATE users SET last_usage=$time WHERE email='{$this->user}'";
        DBHelper::Query($query);
    }
    protected function getLastUsage(){
        $data = DBHelper::Select('users', 'last_usage', "email='{$this->user}'");
        return count($data) > 0 ? $data[0]['last_usage'] : 0;
    }
    
    protected function getUserType($user = ""){
        $user = empty($user) ? $this->user : $user;
        
        $data = DBHelper::Select('users', 'email, user_type', "email='$user'");
        
        return count($data) > 0 && 
            ($data[0]['user_type'] >= USER_ADMIN || DBHelper::is_user_active($data[0]['email'])) 
            ? $data[0]['user_type'] : USER_ALL;
    }
    
    // Abstract methods
    protected abstract function Authorized();
    protected abstract function RunService();
    protected abstract function Dispose();
    
    public abstract function GetLogInfo($indent = 0);
}