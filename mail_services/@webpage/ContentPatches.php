<?php
class ContentPatches{
    protected $c_patch = array();
    protected static $instance;
    
    public function __construct(){
    }
    
    public function AddPatchFunction($function){
        if (is_callable($function)){
            $this->c_patch[] = $function;
        }
    }
    
    public function ApplyPatches($url, &$content){
        $this->_applyPatches($url, $content);
    }
    
    protected function _applyPatches($url, &$content){
        foreach($this->c_patch as $patch){
            //if (!preg_match($urlMatch, $url)) continue;
            $content = call_user_func($patch, $url, $content);
        }
    }
    
    /**
    * @desc Obtiene un singleton de esta clase
    * @param ContentPatches
    */
    public static function GetInstance(){
        if (!self::$instance){
            self::$instance = new ContentPatches();
        }
        return self::$instance;
    }
}

$_cp = ContentPatches::GetInstance();

// No definir parches hasta que no se implemente 
// la applicacion del cliente
function m_patch_google($url, $content){
    // TODO: 
    // 1) Comprobar que se trata de una peticion movil
    // 2) Comprobar que la version de la app soporta este parche
    // 3) Comprobar que la url coincide
    // 4) Aplicar el parche
}
$_cp->AddPatchFunction("m_patch_google");