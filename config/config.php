<?php

// MYSQL
define("MYSQL_HOST", "auroraml.com");
define("MYSQL_USER", "root");
define("MYSQL_PASS", "isaburuma1002");
define("MYSQL_DB", "admin_main");
define("MYSQL_PORT", 3306);

// SMTP
define("MAIL_IS_SMTP", 1);
define("SMTP_HOST", "aurora-mail-sender");
define("SMTP_PORT", 25);
define("SMTP_AUTH", 0);
//define("SMTP_USER", "w2m@auroraml.com");
//define("SMTP_PASS", "isaburuma1002");
define("SMTP_SSL", 0);

define("SMTP_DEBUG", 0);


// Mail
define("MAIL_DOMAIN", "auroraml.com");
define("MAIL_USER", "w2m");
define("MAIL_NAME", "Aurora Mail");
define("SERVICE_ADDRESS", "w2m@auroraml.com");
define("SUPPORT_ADDRESS", "nauta.fw@gmail.com");

// Misc
define("_DEBUG_", 0);
define("REPORT_ERRORS", 0);
//define("ERROR_REPORTING", 0);

define("DIAS_PRUEBA", 7);

// Version Minima de AuroraSuite para nuevos usuarios
define("AU_NEW_USER_MIN_VERSION_CODE ",  13);
define("AU_NEW_USER_MIN_VERSION_NAME ",  "6.0.1");

// Hack para cuando hay una cola enorme de entrada
// porque el sistema estuvo caido
define("DROP_ALL", 0);

// Other configurations
define('REV_BASE', "http://www.revolico.com");

define('SERVICES_PATH', DOCUMENT_ROOT . DS . "mail_services");
define('MOBILE_SERVICES_PATH', DOCUMENT_ROOT . DS ."mobile_services");
define('SMS_SERVICES_PATH', DOCUMENT_ROOT . DS ."sms_service");

