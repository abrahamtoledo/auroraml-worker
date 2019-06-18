<?php
  define('DOCUMENT_ROOT', dirname(__DIR__));
  require_once DOCUMENT_ROOT . '/includes.php';
  
  $cookies = glob(DOCUMENT_ROOT . '/mail_services/@webpage/cookies/*');
  print count($cookies) . " cookies encontradas\r\n";
  
  $cDeleted = 0;
  foreach ($cookies as $cookie){
    $name = basename($cookie);
	$exptime = DBHelper::getUserExpirationTime($name);
	if ($exptime + 3600*24*7 < time()){
		unlink($cookie);
		$cDeleted++;
	}
  }
  
  print "$cDeleted cookies eliminadas\r\n";