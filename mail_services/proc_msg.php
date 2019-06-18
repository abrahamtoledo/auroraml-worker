<?php
//header("Content-Type: text/plain");
set_time_limit(60);
ignore_user_abort(true);

define('DOCUMENT_ROOT', dirname(dirname(__FILE__)));
require_once DOCUMENT_ROOT . '/includes.php';

_debug("Start debug session.");

if (!isset($_GET['num']) && count($argv) <= 1) die("No se especifico un mensaje");

$num = $_GET['num'] ? (int)$_GET['num'] : (int)$argv[1];
if ($num < 1) die("Numero de mensaje incorrecto: $num");

/**
* @desc Esta es la funcion que sera llamada para crear el
* servicio correspondiente. Para agregar nuevos servicios
* debe ser suficiente modificar solamente esta funcion,
* preservando de esta manera el resto de la logica, que debe
* permanecer invariante.
* 
* @param MailMessage Mensaje recibido, a partir del cual debe crearse el servicio
* @returns ServiceAbstract
*/
function ms_factory_callback($msg){
    /** @var MailAddress */ 
    $serverAddress = $msg->to[0];
    
    // Por defecto usar los Servicios de Correo Originales
    require_once SERVICES_PATH . DS . "service_base.php";
    return ServiceBase::Factory($msg);
}

$msgProcessor = new MessageProcessor(IMAP_USER, IMAP_PASS, $num, 'ms_factory_callback');

$msgProcessor->ProcessMessage();