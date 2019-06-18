<?php
require_once dirname(dirname(__FILE__)) . "/service_base.php";

class ServicePrueba extends ServiceBase{
    protected function Authorized(){ return true; }

    protected function RunService(){
	if (!$this->isTested($this->user)){
		$this->setTest($this->user);

		$exp_date = date("d/m/Y", DBHelper::getUserExpirationTime($this->user));

		$subject = "Su cuenta fue activada por " . DIAS_PRUEBA . " dias.";
		$body = $subject . " Su activacion vence el {$exp_date}.";

		$this->SendMail($body, $subject);

	}else{
                $exp_date = date("d/m/Y", DBHelper::getUserExpirationTime($this->user));
                $subject = "Error: Ya usted activo sus dias de prueba. Fecha de vencimiento {$exp_date}";
                $body = $subject;

                $this->SendMail($body, $subject);
	}

    }
    
    protected function isTested($email){
        return count( DBHelper::Query("SELECT * FROM prueba WHERE email='{$email}'") ) > 0;
    }

    protected function setTest($email){
	// Inserting into prueba table
        DBHelper::Query("INSERT INTO prueba VALUES ('{$email}')");

        // Activate account for testing
        DBHelper::activateAccount($email, DIAS_PRUEBA);
    }
}
