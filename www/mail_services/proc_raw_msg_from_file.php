#!/usr/bin/php70
<?php
set_time_limit(120);
ignore_user_abort(true);

define('DOCUMENT_ROOT', dirname(dirname(__FILE__)));
require_once DOCUMENT_ROOT . '/includes.php';

//if (defined('DISCARD_ALL_MAIL') &&  DISCARD_ALL_MAIL){
//    die;
//}

//$handle = fopen('php://stdin', 'r+');
//file_put_contents("/tmp/tempmsg.eml", stream_get_contents($handle));
//fclose($handle);
//
//die;


function ms_factory($msg){
    $serverAddress = $msg->to[0];
        
    // Por defecto usar los Servicios de Correo Originales
    require_once SERVICES_PATH . DS . "service_base.php";
    return ServiceBase::Factory($msg);
}


// Parse Message from File
$mailParser = new \ZBateson\MailMimeParser\MailMimeParser();
$handle = fopen($argv[1], 'r+');
$message = $mailParser->parse($handle);
fclose($handle);
unlink($argv[1]);


// Convert to MailMessage class'es object
$msg = MailMessage::createFromMailMimeMessage($message);


// Process Normally 
$addr = count($msg->reply_to) ? $msg->reply_to[0] : $msg->from[0];

if ($addr->host == 'auroraml.net' || $addr->host == 'auroraml.com'){
    syslog(LOG_ERR, "Deteniendo ejecucion para evitar bucle infinito");
    die;
}

if (!EPHelper::is_email_banned($addr->address) && !EPHelper::is_email_banned($msg->to[0])) {
    $serv = ms_factory($msg);

    if ($serv){
        $serv->Run();
    }else{
        syslog(LOG_ERR, "No se pudo crear el servicio");
    }
}else{
    syslog(LOG_ERR, "Direccion baneada {$addr->address} o {$msg->to[0]}");
}
