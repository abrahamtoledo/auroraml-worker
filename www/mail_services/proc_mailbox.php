<?php
/**
 * Lee el buzon asignado para el servicio y va procesando los pedidos
 * desde los mas viejos a los mas recientes. El script termina cuando
 * se han procesado todos los mensajes o se acaba el tiempo de ejecucion
 */
//header("Content-type: text/plain");
define("DOCUMENT_ROOT", dirname(dirname(__FILE__)));
require_once DOCUMENT_ROOT . '/includes.php';

$mProcessor = new MailboxProcessor(IMAP_USER, IMAP_PASS, 
                                    "http://" . HTTP_HOST . "/mail_services/proc_msg.php",
                                    MAILBOX_CHECK_INTERVAL,
                                    55, 
                                    !(NO_EXPUNGE),
                                    8);

$mProcessor->ProcessMailbox();