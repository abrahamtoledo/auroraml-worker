<?php
require_once dirname(dirname(__FILE__)) . "/@webpage/service.php";

class ServiceSetInfo extends ServiceWebpage{
	protected function RunService(){
		$param = $this->data->params->addChild("param");
		$param->addChild("name", "email");
		$param->addChild("value", $this->user);
		
		parent::RunService();
	}
}