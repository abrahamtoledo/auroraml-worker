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

// Syslog Preparation
openlog("auroraml-worker", LOG_ODELAY, LOG_LOCAL0);

// Include all files under "common" directory
foreach(glob(DOCUMENT_ROOT . '/common/*.php') as $common){
	require_once ($common);
}

