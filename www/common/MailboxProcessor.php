<?php
// Filtra de la lista los usuarios que han pagado
function isFromPayedUser($msgOverview){
    $res = DBHelper::Query("SELECT name FROM users WHERE email='{$msgOverview->from}' LIMIT 1", $mysqlErr);
    return count($res) && $res[0]['name'];
}
// Filtra de la lista los usuarios que estan probando
function isFromTestingUser($msgOverview){
    return !isFromPayedUser($msgOverview);
}

class MailboxProcessor{
    const PRIORITY_PROB = 0.62;
    
    /** @desc Contains the object of type 'PhpMailbox' to perform operations */
    var $mailBox;
    
    /** @desc The time interval between iterations */
    var $checkInterval;
    
    
    /** @desc The time limit to be running the loop*/
    var $maxExecutionTime = 55;
    
    
    /** @desc Wether to expunge or not the mailbox when closing */
    var $expungeOnClose = true;
    
    /**
    @desc The maximum number of messages to be 
    processed on each iteration 
    */
    var $maxMessagesPerLoop = 4;
    
    /**
    @desc The url of the script used to process each
    message
    */
    var $messageProcessorUrl;
    
    /**
    @desc Creates a new instance of this class.
    
    @param (String) The mailbox user_name
    @param (String) The password to this user
    @param (String) A string containing the url to the script that processes individual messages
    @param (int) time interval between two iterations (in seconds)
    @param (int) The maximum execution time (in seconds)
    @param (bool) True to expunge deleted messages from mailbox when Closing, false otherwise
    @param (int) Max number of messages to be processed on each iteration
    */
    function __construct($imapUserName, $imapPassword, $messageProcessorUrl, $checkInterval = 5, 
                         $maxExecTime, $expungeOnClose = true, $maxMessagesPerLoop = 4){
        $this->mailBox = EPHelper::getPhpMailboxFor($imapUserName, $imapPassword);
        
        $this->messageProcessorUrl = $messageProcessorUrl;
        $this->checkInterval = $checkInterval;
        $this->maxExecutionTime = $maxExecTime;
        $this->expungeOnClose = $expungeOnClose;
        $this->maxMessagesPerLoop = $maxMessagesPerLoop;
    }
    
    /**
    * @desc Runs the actual Mailbox Processing
    * @returns (void)
    */
    function ProcessMailbox(){
        $time_limit = $this->maxExecutionTime + $this->checkInterval;
        set_time_limit($time_limit);
        ignore_user_abort(TRUE);
        
        $st_time = time();
        $lastExecTime = $st_time;
        
        while(time() - $st_time < $this->maxExecutionTime){
            if ($this->mailBox->Open()){
                $unseen = $this->mailBox->getUnseen();
                if (count($unseen) > $this->maxMessagesPerLoop){
                    //shuffle($unseen);
                    $uOverview = $this->mailBox->fetchOverview($unseen);
                    
                    // Separar los grupos
                    $uPayed = array_filter($uOverview, 'isFromPayedUser');
                    $uTesting = array_filter($uOverview, 'isFromTestingUser');
                    
                    // Desorganizar
                    shuffle($uPayed); shuffle($uTesting);
                    $nPayed = min( ceil(MailboxProcessor::PRIORITY_PROB * $this->maxMessagesPerLoop), count($uPayed) );
                    $nTesting = min($this->maxMessagesPerLoop - $nPayed, count($uTesting));
                    
                    $unseen = array();
                    // Garantizar los puestos para usuarios pagados
                    for($i = 0; $i < $nPayed; $i++) {
                        $msgOver = array_pop($uPayed);
                        $unseen[] = $msgOver->msgno;
                    }
                    // Garantizar los puestos para usuarios probando
                    for($i = 0; $i < $nTesting; $i++) {
                        $msgOver = array_pop($uTesting);
                        $unseen[] = $msgOver->msgno;
                    }
                    
                    // Rellenar si quedaron puestos vacios
                    while (count($uPayed) > 0 && count($unseen) < $this->maxMessagesPerLoop){
                        $msgOver = array_pop($uPayed);
                        $unseen[] = $msgOver->msgno;
                    }
                    while (count($uTesting) > 0 && count($unseen) < $this->maxMessagesPerLoop){
                        $msgOver = array_pop($uTesting);
                        $unseen[] = $msgOver->msgno;
                    }
                    
                }
                $c = min(count($unseen), $this->maxMessagesPerLoop);
                
                $urls = array();
                for($i = 0; $i < $c; $i++){
                    // Debugin
                    // $msg = $mbox->Message($unseen[$i]);
                    // mail("@gmail.com", "De {$msg->from[0]}", serialize($msg), "From: www@catalyst-mail.info\r\n");
                    // end Debugin
                    
                    if (stripos(PHP_OS, "WIN") !== FALSE){
                        $urls[] = "{$this->messageProcessorUrl}?num={$unseen[$i]}";
                        //EPHelper::phpExec(DOCUMENT_ROOT . "/mail_services/proc_msg.php", "{$unseen[$i]}");
                    }else{
                        $cmd = "php -f " . DOCUMENT_ROOT . "/mail_services/proc_msg.php ". $unseen[$i] ." 2>1 1>/dev/null &" ;
                        //print $cmd;
                        shell_exec($cmd);
                    }
                }
                
                EPHelper::MultiGetUrl($urls, 4);
            }
            else{
                Logs::GetInstance()->addEntry('['.date("d/m/Y - H:i A").']' . "\r\nIMAP OPEN FAILED ({$this->mailBox->user}): " . $this->mailBox->lastError() . "\r\n");
                break;
            }
                
            if ($this->checkInterval - (time() - $lastExecTime) > 0)
            {
                sleep(min($this->checkInterval - (time() - $lastExecTime), $time_limit - (time() - $st_time)));
            }
            
            $this->mailBox->Close($this->expungeOnClose); // true = expunge
            $lastExecTime = time();
        }
        
        $this->mailBox->Close($this->expungeOnClose);
    }
}
