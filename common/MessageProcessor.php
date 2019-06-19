<?php
class MessageProcessor{
    /** @desc Contains the object of type 'PhpMailbox' to perform operations */
    var $mailBox;
    
    /** @desc The ordinal position of the message within the mailbox */
    var $msgNo;
    
    var $msgNoIsId;
    
    /** @desc This variable stores a callback to a function that creates a Service from a MailMessage */
    var $createServiceCallback;
    
    /**
    * @desc Creates a new instance of MessageProcessor
    * 
    * @param (String) The username for the mailbox containing the message
    * @param (String) The password for the mailbox containing the message
    * @param (Int) The ordinal number of the message within the mailbox
    * @param (CALLBACK) Callback function used as a Factory for the Service. Decl: ServiceAbstract callback(MailMessage $message);
    */
    function __construct($imapUserName, $imapPassword, $msgNo, $createServiceCallback, $msgNoIsId=false){
        $this->mailBox = EPHelper::getPhpMailboxFor($imapUserName, $imapPassword);
        $this->msgNo = $msgNo;
        
        $this->createServiceCallback = $createServiceCallback;
    }
    
    function ProcessMessage(){
        $logs = Logs::GetInstance();
        $logs->addEntry("Procesando un nuevo mensaje");
        
        if ($this->mailBox->Open()){
            $msg = $this->mailBox->Message($this->msgNo);
            if (!$msg){
                syslog(LOG_ERR, "Error: Mensaje no existe en: " . __FILE__ . ". line: " . __LINE__);
                return;
            }
            
            
            $addr = count($msg->reply_to) ? $msg->reply_to[0] : $msg->from[0];
            if (!EPHelper::is_email_banned($addr->address) && !EPHelper::is_email_banned($msg->to[0])) {
                $serv = call_user_func($this->createServiceCallback, $msg);

                if ($serv){
                    $serv->Run();
                    
                    $logs->AddEntry("\r\n" . $serv->GetLogInfo(1) . "\r\n------------------");
                    
                    $this->mailBox->softDelete($this->msgNo);
                }else{
                    $logs->addEntry("No se pudo crear el servicio");
                }
            }
            else{
                $logs->AddEntry(sprintf("\r\nBanned {\r\n from: %s \r\n subject: %s\r\n}", 
                            $msg->from[0], $msg->subject));
            }
            
            $this->mailBox->Close(false);
        }
        else{
            syslog(LOG_ERR, /*'['.date("d/m/Y - H:i:s").']' .*/ "\r\nIMAP OPENING FAILED ({$this->mailBox->user}): " . $this->mailBox->lastError() . "\r\n");
        }

    }
}