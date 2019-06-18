<?php
class HttpHeaders {
    private $_c;
    
    public function containsHeader($name){
        return array_key_exists(strtolower($name), $this->_c);
    }
    public function removeHeader($name){
        unset($this->_c[strtolower($name)]);
    }
    public function getHeader($name){
        return $this->_c[strtolower($name)];
    }
    public function setHeader($name, $val){
        $this->_c[strtolower($name)] = $val;
    }
    public function setHeaderFromString($str){
        list($name, $val) = explode(":", $str, 2);
        $this->setHeader($name, trim($val));
    }
    public function addToHeader($name, $val){
        if ($this->containsHeader($name)){
            $curr = $this->getHeader($name);
            $this->setHeader($name, "{$curr}; {$val}");
        }else{
            $this->setHeader($name, $val);
        }
    }
    
    public function getAll(){
        $keys = array_map(array($this, "toUpperHeaderName"), array_keys($this->_c));
        return array_combine($keys, array_values($this->_c));
    }
    protected function toUpperHeaderName($v){
        $parts = explode("-", $v);
        $c = count($parts);
        for($i = 0; $i < $c; $i++){
            $parts[$i][0] = strtoupper($parts[$i][0]);
        }
        return implode("-", $parts);
    }
    
    public function __construct(){
        $this->_c = array();
    }
    
    public static function fromString($str){
        $result = new HttpHeaders();
        $lines = preg_split('#\r|\n#', $str, -1, PREG_SPLIT_NO_EMPTY);
        foreach($lines as $line){
            $result->setHeaderFromString($line);
        }
        return $result;
    }
}