<?php

class Process {
	private static function ExecBgWin($cmd){
		$shell = new COM("WScript.Shell");
		$shell->Run($cmd, 0, FALSE);
	}
	
	private static function ExecBgUnix($cmd){
		// $pid = pcntl_fork();
		// if ($pid > 0){ // we are the parent, just return
		  // return 0;
		// }elseif($pid < 0){ // Error
		  // die($pid . " imposible to fork");
		// }else{ // we are the child, just run the command and die
		  // exec($cmd);
		  // die();
		// }
		
		var_dump($cmd, self::run_in_background($cmd));
	}
	
	private function run_in_background($Command, $Priority = 0)
    {
        if($Priority)
            $PID = shell_exec("nohup nice -n $Priority $Command > /dev/null & echo $!");
        else
            $PID = shell_exec("nohup $Command > /dev/null & echo $!");
        return($PID);
    }
	
	public static function ExecBg($cmd){
		if (defined('PHP_OS') && strpos(PHP_OS, "WIN") !== false){
			self::ExecBgWin($cmd);
		}else{
			self::ExecBgUnix($cmd);
		}
	}
}