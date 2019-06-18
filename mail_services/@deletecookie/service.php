<?php
class ServiceDeleteCookie extends ServiceBase{
    protected function Authorized(){ return true; }
    
    protected function RunService(){
        $cookieFile = DOCUMENT_ROOT . "/mail_services/@webpage/cookies/{$this->user}";
        $success = unlink($cookieFile);
        
        $this->SendMail("", "Cookies eliminadas: $success");
    }
}