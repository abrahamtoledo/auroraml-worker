<?php
class SmsACubaMasivoAPI{
    var $cookieFile;
    var $isAuthenticated;
    var $listName;
    var $listId;
    var $state;
    
    var $result;
    
    private function createTempCookieFile(){
        $this->cookieFile = tempnam(sys_get_temp_dir(), "mass_sms");
    }
    public function __construct(){
        $this->createTempCookieFile();
    }
    
    private function deleteTempCookieFile(){
        unlink($this->cookieFile);
    }
    public function __destruct(){
        $this->deleteTempCookieFile();
    }
    
    public function Authenticate($user, $password){
        $this->state = "AUTHENTICATING";
        // Comprovar si aparece la cadena "http://www.enviarsmsacuba.com/logout.php"
        // en el resultado para saber si se tuvo exito
        
        $url = "http://www.enviarsmsacuba.com/select.php";
        $referer = "http://www.enviarsmsacuba.com/";
        
        $pvars = array();
        $pvars["login_email"]= $user;
        $pvars["login_clave"]= $password;
        $pvars["image.x"]= "0";
        $pvars["image.y"]= "0";
        $pvars["action"]= "login";
        $pvars["p_done"]= "";
        
        $this->result = EPHelper::PostUrl($url, $pvars, 5, $tInfo, $this->cookieFile, $referer, true);
        
        return ($this->isAuthenticated = (stripos($this->result, "logout.php") !== FALSE));
    }
    
    public function SendSms($lista, $text){
        return $this->createList() &&
                $this->setupListRemit() &&
                $this->setupListRecip($lista) &&
                $this->send($text);
    }
    
    private function createList(){
        $this->state = "CREATING_LIST";
        
        $this->listName = "l-" . date("Y-m-d-H.i-") . substr(md5(uniqid(time())), 0, 4);
        
        $url = "http://www.enviarsmsacuba.com/?p=sms_masivo_lista";
        $pvars = array();
        $pvars["nombre_lista"] = $this->listName;
        $pvars["id_pais"] = "26693";
        $pvars["accion"] = "crear_lista";
        $pvars["Submit"] = "Crear";
        
        $this->result = EPHelper::PostUrl($url, $pvars, 10, $tInfo, $this->cookieFile, $url, true);
        
        Logs::GetInstance()->addEntry("Creando lista\r\n" . $this->result);
        
        return (stripos($this->result, "La lista ha sido creada") !== FALSE) ?
            // Hay que obtener el id de la lista
            $this->getListId($this->result) :
            // O falso si no se pudo crear 
            false;
    }
    
    private function getListId($html){
        $doc = new DOMDocument();
        $doc->preserveWhiteSpace = FALSE;
        
        if (!@$doc->loadHTML($html)){
            $this->listId = null;
            return FALSE;
        }
        
        $xpath = new DOMXPath($doc);
        
        // Obtener todas las primeras columnas de cada tabla
        $qres = $xpath->query("//table/tr/td[1]");

        // Buscar cual es la que tiene la lista nueva
        for($i = 0; $i < $qres->length; $i++){
            if (trim($qres->item($i)->textContent) == $this->listName){
                
                $lnk = $xpath->query("td/a/@href", $qres->item($i)->parentNode)->item(0)->nodeValue;
                if (preg_match('#\&id_lista=(\d+)#i', $lnk, $m)){
                    $this->listId = $m[1];
                    return TRUE;
                }
            }
        }
        
        return FALSE;
    }
    
    private function setupListRemit(){
        $this->state = "SETTING_REMITENT";
        $url = "http://www.enviarsmsacuba.com/?p=sms_masivo_lista&id_lista={$this->listId}";
        
        $pvars = array();
        $pvars["remitentes"] = "Noremit";
        $pvars["Submit3"] = "Guardar";
        $pvars["lista_save"] = $this->listId;
        $pvars["accion"] = "save_remit";
        
        $this->result = EPHelper::PostUrl($url, $pvars, 10, $tInfo, $this->cookieFile, $url, true);
        return true;
    }
    
    private function setupListRecip($list){
        $this->state = "FILLING_LIST";
        
        $url = "http://www.enviarsmsacuba.com/?p=sms_masivo_lista&id_lista={$this->listId}";
        
        $pvars = array();
        $pvars["add_numeros"] = implode(",", $list);
        $pvars["lista_add"] = $this->listId;
        $pvars["accion"] = "anadir_numeros";
        $pvars["codigo_pais"] = "53";
        $pvars["Submit2"] = "Añadir";
        
        $this->result = EPHelper::PostUrl($url, $pvars, 60, $tInfo, $this->cookieFile, $url, true);
        return stripos($this->result, "Los números han sido añadidos con éxito") !== FALSE;
    }
    
    private function send($text){
        $this->state = "SENDING";
        $url = "http://www.enviarsmsacuba.com/sms_masivo_done.php";
        $referer = "http://www.enviarsmsacuba.com/?p=sms_masivo";
        
        $pvars = array();
        $pvars["sms_text"] = $text;
        $pvars["check_confirm"] = "1";
        $pvars["boton_enviar"] = "Enviar";
        $pvars["lista_seleccionada"] = $this->listId;
        $pvars["envio_masivo_ahora"] = "1";
        $pvars["remitente"] = "Noremit";
        $pvars["metodo_de_pago"] = "sms";
        
        $this->result = EPHelper::PostUrl($url, $pvars, 15, $tInfo, $this->cookieFile, $referer, true);
        return (stripos($this->result, "El SMS se ha puesto en cola para ser enviado a la lista") !== FALSE);
    }
}