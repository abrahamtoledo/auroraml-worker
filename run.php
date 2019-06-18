<?php
define("DOCUMENT_ROOT", __DIR__);
require_once __DIR__ . "/includes.php";

function ms_factory($msg){
    $serverAddress = $msg->to[0];
        
    require_once SERVICES_PATH . "/service_base.php";
    return ServiceBase::Factory($msg);
}

$mailParser = new \ZBateson\MailMimeParser\MailMimeParser();

$femail = $_FILES['email']['tmp_name'];
$email_fh = fopen($femail, 'r+');;

$message = $mailParser->parse($email_fh);

fclose($email_fh);

$msg = MailMessage::createFromMailMimeMessage($message);

$addr = count($msg->reply_to) ? $msg->reply_to[0] : $msg->from[0];

_debug("Enviado desde $addr para {$msg->to[0]} con asunto {$msg->subject}");

if ($addr->host == 'auroraml.net' || $addr->host == 'auroraml.com'){
    _debug("Deteniendo ejecucion para evitar bucle infinito");
    die;
}

if (!EPHelper::is_email_banned($addr->address) && !EPHelper::is_email_banned($msg->to[0])) {
    $serv = ms_factory($msg);

    if ($serv){
        _debug("Ejecutando el servicio");
        //$logs->addEntry("Nombre del Servicio: " . get_class($serv));
        $serv->Run();
    }else{
        _debug("No se pudo crear el servicio");
    }
}else{
    error_log("Direccion baneada {$addr->address} o {$msg->to[0]}");
}
