#!/usr/bin/php
<?php
set_time_limit(120);
ignore_user_abort(true);

define('_DEBUG_', 1);
define('REPORT_ERRORS', 1);

define('DOCUMENT_ROOT', dirname(dirname(__FILE__)));
require_once DOCUMENT_ROOT . '/includes.php';

_debug("Procesando un nuevo mensaje");

function ms_factory($msg){
    $serverAddress = $msg->to[0];
        
    // Por defecto usar los Servicios de Correo Originales
    require_once SERVICES_PATH . DS . "service_base.php";
    return ServiceBase::Factory($msg);
}

_debug("Parseando el mensaje");

// Parse Message from STDIN
$mailParser = new \ZBateson\MailMimeParser\MailMimeParser();
//$handle = fopen('php://stdin', 'r+');
$handle = fopen('/tmp/tempmail.eml', 'r+');
$message = $mailParser->parse($handle);
fclose($handle);

_debug("Convirtiendo a MailMessage");

// Convert to MailMessage class'es object
$msg = MailMessage::createFromMailMimeMessage($message);

_debug("Procesar la solicitud");

// Process Normally 
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
}
