<?php
define("DOCUMENT_ROOT", __DIR__);
require_once __DIR__ . "/includes.php";


function ms_factory($msg){
    $serverAddress = $msg->to[0];
        
    require_once DOCUMENT_ROOT . "/mail_services/service_base.php";
    return ServiceBase::Factory($msg);
}


syslog(LOG_INFO, "BEGIN REQUEST");
syslog(LOG_INFO, "Reading Message ...");


$mailParser = new \ZBateson\MailMimeParser\MailMimeParser();

$femail = $_FILES['email']['tmp_name'];
$email_fh = fopen($femail, 'r+');;

$message = $mailParser->parse($email_fh);

fclose($email_fh);


syslog(LOG_INFO, "COMPLETED");


$msg = MailMessage::createFromMailMimeMessage($message);

$addr = count($msg->reply_to) ? $msg->reply_to[0] : $msg->from[0];

syslog(LOG_INFO, "User: $addr, ServerAddress: {$msg->to[0]}, Subject: {$msg->subject}");

if ($addr->host == 'auroraml.net' || $addr->host == 'auroraml.com'){
    syslog(LOG_ERR, "Sender is Aurora domain, stop execution to avoid send loop");
    die("Show some information here before die");
}

if (!EPHelper::is_email_banned($addr->address) && !EPHelper::is_email_banned($msg->to[0])) {
    syslog(LOG_INFO, "Creating service to proccess request ...");
    

    $serv = ms_factory($msg);

    if ($serv){
        syslog(LOG_INFO, 
            "Service " . get_class($serv) . ", Service-Default: {$serv->data->default} " .
            "Client app version: {$serv->data->app_version}, User: {$serv->user}, " .
            "Files: " . count($serv->files) . ", Attachments: " . count($serv->attachments)
        );
        

        syslog(LOG_INFO, "Running service");
        $serv->Run();
    }else{
        syslog(LOG_ERR, "No se pudo crear el servicio");
    }
}else{
    syslog(LOG_ERR, "Direccion baneada {$addr->address} o {$msg->to[0]}");
    die;
}

syslog(LOG_INFO, "END OF EXECUTION");
