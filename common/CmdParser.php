<?php
class CmdParser{
    private $cmdline;
    private $pos;
    private $len;
    
    public function __construct($cmdline){
        $this->init($cmdline);
    }
    
    private function init($cmdline){
        $this->cmdline = $cmdline;
        $this->pos = 0;
        $this->len = strlen($cmdline);
    }
    
    private function getEnclosed(){
        $encloser = $this->cmdline[$this->pos];
        $this->pos++;
        $res = array();
        while($this->pos < $this->len){
            switch($this->cmdline[$this->pos]){
                case "\\":
                    $res[] = $this->cmdline[$this->pos + 1];
                    $this->pos += 2;
                    break;
                case $encloser:
                    return join('', $res);
                default:
                    $res[] = $this->cmdline[$this->pos];
                    $this->pos++;
                    break;
            }
        }
        
        return join('', $res);
    }
    
    public function getArgs(){
        $args = array();
        $curr = array();
        
        while($this->pos < $this->len){
            switch ($this->cmdline[$this->pos]){
                case '"':
                case "'":
                    $curr[] = $this->getEnclosed();
                    break;
                case ' ':
                    if (count($curr) > 0){
                        $args[] = join('', $curr);
                        $curr = array();
                    }
                    break;
                default:
                    $curr[] = $this->cmdline[$this->pos];
                    break;
            }
            $this->pos++;
        }
        
        if (count($curr) > 0){
            $args[] = join('', $curr);
        }
        
        return $args;
    }
    
    public static function PaseCmd($cmdline){
        $cmdParser = new CmdParser($cmdline);
        return $cmdParser->getArgs();
    }
}