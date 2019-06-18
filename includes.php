<?php
date_default_timezone_set("America/Havana");
//********************************
// This file can not be executed *
//********************************
if (!defined("DOCUMENT_ROOT")) die("You can not access this file");
define ('DS', DIRECTORY_SEPARATOR);

// Load Configuration
require_once DOCUMENT_ROOT . '/config/config.php';

// DO NOT REPORT ERRORS BY DEFAULT
ini_set('display_errors', 'Off');

// Pero si en php_errors.log
error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED & ~E_STRICT);

// ERROR_REPORTING
if (REPORT_ERRORS || $_REQUEST['report_errors']){
    ini_set('display_errors', 'On');
    error_reporting(E_ALL ^ E_STRICT);
}

// DEBUG
$GLOBALS["debug_started"] = false;
function _debug($val){
    if (_DEBUG_ || @$_REQUEST['debug']){
        
        $fname = '/var/log/aurora_debug.log';
        if (!$GLOBALS["debug_started"]){
            
        }
        
        file_put_contents($fname, date("Y-m-d -- H:i:s.u") . " : $val\n", FILE_APPEND);
    }
}

// Include all files under "common" directory
foreach(glob(DOCUMENT_ROOT . '/common/*.php') as $common){
	require_once ($common);
}

