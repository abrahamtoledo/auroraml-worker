<?php

// Descargar Apk por correo
class ServiceApk extends ServiceBase{
	
    	// No registrar al usuario para que no pierda tiempo
    	// de prueba
    	protected function RegisterIfNew(){ }
    
	protected function Authorized(){
		return true;
	}
    
	protected function RunService(){
		$fname = "";
		foreach (glob(dirname(__FILE__) . "/AuroraSuite-*.apk") as $file){
			$fname = $file;
			$name = basename($file);
		}
		
	        $zip = new zipfile();
        	$zip->AddFile($fname, $name);
        
		$this->SendMail("AuroraSuite APK" ,
						"Descargue el adjunto",
						array(array('data' => $zip->getContents(), 'name' => "AuroraSuite.zip")));
	}
}
