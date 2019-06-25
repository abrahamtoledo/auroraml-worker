<?php
class ConnectProxyStreamWrapper{
    public $context;
    
    protected $fh;
    protected $proxyHost;
    protected $proxyPort;
    protected $targetHost;
    protected $targetPort;
    protected $useSSL = FALSE;
    protected $useTLS = FALSE;
    
    public function stream_open($path, $mode, $options, &$openedPath)
    {
        
    }
    public function stream_close(){}
    
    public function stream_eof() {return FALSE;}
    
    public function stream_read($count){  }
    public function stream_write($data){  }
    public function stream_set_option( int $option , int $arg1 , int $arg2){
        
    }
}
