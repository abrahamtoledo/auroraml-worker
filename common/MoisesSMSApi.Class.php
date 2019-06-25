<?php
/**
 * Mediante esta clase puede realizar envíos de sms a través del API 
 * brindado por Moises SMS, de una manera simple  
 *
 */
class MoisesSMSApi {
    
    // Estos son los valores predeterminados de autenticacion. Modifiquelos
    // segun su cuenta. Estos valores se usan al inicializar la clase si
    // no se especifican otros
    const DEF_USER = "usuario predeterminado";
    const DEF_KEY = "clave predeterminada";
    
    private static $messagesByCode = array(
        "777" => "Mensaje enviado correctamente",
        "666" => "No se realizó el envío. Contacte al administrador",
        "13" =>  "Error en la base de datos Contacte al administrador",
        "111" => "No tiene saldo suficiente",   
        "12" =>  "Los números celulares en Cuba tiene 8 dígitos",
        "10" =>  "Debe programar el envío para una fecha y hora mayor a la actual", 
        "112" => "Su cuenta no tiene saldo",
        "9" =>   "Su cuenta no está activa",
        "83" =>  "Clave (KEY generado) Incorrecto",
        "82" =>  "Su cuenta no tiene Clave generada",
        "81" =>  "Su cuenta no tiene el servicio de api activado",
        "8" =>   "Usuario no registrado",
        "7" =>   "El mensaje debe tener máximo 155 caracteres",
        "6" =>   "El mensaje debe tener al menos 5 caracteres",
        "5" =>   "Mensaje vacío. Debe enviar un texto no vacío en su mensaje",
        "4" =>   "Teléfonos vacío. Debe enviar al menos un destino en su mensaje",
        "2" =>   "Clave vacía. Debe enviar su KEY generado",
        "1" =>   "Usuario vacío. Debe enviar el email de su cuenta registrada"
    );
    
    private $user;
    private $key;
    
    private static $_instance = null;
    /**
    * @desc Inicializa el singleton de esta instancia
    */
    public static function init($user = null, $key = null) {
        if (self::$_instance == null)
            self::$_instance = new MoisesSMSApi;
        
        self::$_instance->user = $user != null ? $user : self::DEF_USER;
        self::$_instance->key = $key != null ? $key : self::DEF_KEY;
        
        return self::$_instance;
    }
    
    /**
     * 
     * @param string o array $celular   N?mero o n?meros de celular.   Si es un ?nico celular es un string , si son var?os n?meros debe enviar un array de string con todo ellos. Los n?meros deben tener 8 d?gitos y comenzar con el d?gito 5 , Ejemplo:  53334455 
     * @param string $texto  Texto a enviar
     * @param string $fecha  (Opcional)   Fecha de programci?n del mensaje
     * @param string $hora   (Opcional)   Hora de programaci?n del mensaje
     * @return array    si el env?o es satisfactorio: array("enviado"=>true,"code"=>"{codigo de respuesta}", "msg"=>"{texto con el significado del c?digo}","id"=>{id del env?o});
     *                  si el env?o da error :  array("enviado"=>false,"code"=>"{codigo de respuesta}", "msg"=>"{texto con el significado del c?digo}");
     */
    public function enviarSMS($celular, $texto, $fecha=null, $hora=null){
        //$texto = str_replace("%","{et}", $texto);
        $texto = urlencode($texto);

        $cels = $celular;
        if (is_array($celular))
            $cels = implode(",", $celular);  

        $request = "http://moises-sms.com/api/sendsms?user={$this->user}&key={$this->key}&phone={$cels}&msg={$texto}";

        if ($fecha != null)
        {
            $request .= "&fecha=$fecha";
        if ($hora != null) 
            $request .= "&hora=$hora";
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $request);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $code = strtok( curl_exec($ch), ":" );

        $id = $code == 777 ? strtok("\0") : null;
        $respText = self::$messagesByCode[$code];

        return array("enviado" => $code == 777, "code" => $code, "msg" => $respText, "id" => $id);
    }
}