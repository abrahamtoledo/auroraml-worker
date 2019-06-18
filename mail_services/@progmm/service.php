<?php

// Descargar software version 2
class ServiceProgmm extends ServiceBase{
	
    // No registrar al usuario para que no pierda tiempo
    // de prueba
    protected function RegisterIfNew(){ }
    
	protected function Authorized(){
		return DBHelper::is_user_registered($this->user);
	}
    
	protected function RunService(){
		$name = "MagicMail.rar";
		$fname = dirname(__FILE__) . "/$name";
		
        $zip = new zipfile();
        $zip->AddFile($fname, "MagicMail.rar");
        
		$this->SendMail("Prog MM" ,
						"Prog MM",
						array(array('data' => $zip->getContents(), 'name' => "ProgMM.zip")));
	}
}