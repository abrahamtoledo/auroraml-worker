<?php

class Tokenizer{
	private $_str;
    private $no_empty_tokens;
    public $matchedSeparator = null;
    
    public function isEmpty(){
        return strlen($this->_str) == 0;
    }
	
	public function __construct($str = "", $no_empty_tokens = TRUE){
		$this->_str = $str;
        $this->no_empty_tokens = $no_empty_tokens;
	}
	
	public function setString($name){
		$this->_str = $name;
	}
	
    public function getToken(){
        $this->matchedSeparator = null;
        
		if (strlen($this->_str) == 0) return FALSE;
		
		$separators = func_get_args();
		$min_pos = strlen($this->_str);
		$seplen = 0;
		foreach($separators as $sep){
			if (($pos = strpos($this->_str, $sep)) !== false){
                // Si el separador esta en una posicion anterior a la del separador previo
                // o es la misma posicion pero su longitud es mayor entonces se actualiza
				if ($min_pos > $pos || ($min_pos == $pos && $sep_len < strlen($sep))){
					$min_pos = $pos;
					$sep_len = strlen($sep);
				}
			}
		}
		
		$res = substr($this->_str, 0, $min_pos);
        $this->matchedSeparator = substr($this->_str, $min_pos, $sep_len);
		$this->_str = substr($this->_str, $min_pos + $sep_len);
		
		if (strlen($res) == 0 && $this->no_empty_tokens) 
			return call_user_func_array(array($this, __FUNCTION__), $separators);
			
		return $res;
	}
	public function getAll(){
		$res = substr($this->_str, 0);
		$this->_str = "";
		return $res;
	}
}