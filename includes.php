<?php
date_default_timezone_set("America/Havana");
//********************************
// This file can not be executed *
//********************************
if (!defined("DOCUMENT_ROOT")) die("You can not access this file");
define ('DS', DIRECTORY_SEPARATOR);

// Load Configuration
require_once DOCUMENT_ROOT . '/config/config.php';

// Include all files under "common" directory
foreach(glob(DOCUMENT_ROOT . '/common/*.php') as $common){
	require_once ($common);
}

// Syslog Preparation
openlog("auroraml-worker", LOG_ODELAY, LOG_LOCAL0);
