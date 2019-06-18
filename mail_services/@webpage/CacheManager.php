<?php
class CacheManager{
    const URL_BASED_MAX_AGE = 7776000; // 90 dias
    
    // READ ONLY
    private $CACHE_TABLE="cache";
    private $F_ID="id";
    /**
    * @desc El MD5 del url del recurso. para identificar el recurso 
    * entre cliente y servidor. sin necesidad de guardar la url 
    * (que pudiera ocupar mas espacio)
    */
    private $F_MD5="md5";
    /**
    * @desc El usuario dueño del cache
    */
    private $F_USER="user";
    /**
    * @desc La fecha de expiracion de la validez del recurso guardado
    */
    private $F_EXPIRES="expires";
    /**
    * @desc El Identificador del cache actual del usuario. Esto es 
    * para saber sincronizar servidor y cliente con un mismo cache.
    * Ademas cada cambio enviado por el servidor al cliente debe 
    * confirmarse vea {@link CacheManager::$F_TASK_UID}
    */
    private $F_CACHE_UID="cache_uid";
    /**
    * @desc El uid de la tarea en que se actualizo el elemento, 
    * si no se ha confirmado o 0 si ya se confirmo
    */
    private $F_TASK_UID="task_uid";
    /**
    * @desc Calidad de las imagenes en esta tarea. Si la calidad
    * requerida es menor que la que esta almacenada, hay que revalidar.
    * La calidad maxima es 10. Cuando un recurso no es imagen debe guardarse
    * con calidad 10 o mayor. Para que no sea revalidado innecesariamente
    */
    private $F_TASK_IMAGE_CUALITY="task_image_cuality";
    
    /**
    * @var string email del usuario
    */
    private $user;
    /**
    * @var int Id del cache valido actual
    */
    private $cacheUid;
    /**
    * @var int Id de la tarea actual
    */
    private $taskUid;
    
    /**
    * @var int calidad de las imagenes en la tarea actual.
    *  Si la calidad de una imagen guardada es menor entonces
    *  debe actualizarse
    */
    private $taskImageCuality;
    
    private $confirmedTasks = array();
    
    /**
    * @desc Crea una instancia de esta clase. Una sesion cache para el usuario especificado.
    * @param String un id unico para cada usuario. puede usarse su direccion de correo.
    */
    protected function __construct($user, $task_image_cuality, $cache_uid = -1, $confirmed_tasks = array()){
        // Debug
        _debug("CacheManager: Inicializando la tabla");
        $this->initCacheTable();
        
        $this->user = $user;
        $this->cacheUid = (int)$cache_uid;
        $this->taskUid = mt_rand();
        
        $this->taskImageCuality = (int)$task_image_cuality;
        
        if (!$confirmed_tasks)
            $confirmed_tasks = array();
            
        $this->confirmedTasks = $confirmed_tasks;
        
        // Debug
        _debug("CacheManager: Realizando Mantenimiento");
        $this->maintain($confirmed_tasks);
    }
    private function initCacheTable(){
        DBHelper::Query(
                "CREATE TABLE IF NOT EXISTS {$this->CACHE_TABLE} (
                    {$this->F_ID} INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY ,
                    {$this->F_MD5} VARCHAR(32) NOT NULL,
                    {$this->F_USER} TEXT NOT NULL ,
                    {$this->F_EXPIRES} INT NOT NULL ,
                    {$this->F_CACHE_UID} INT NOT NULL ,
                    {$this->F_TASK_UID} INT NOT NULL,
                    {$this->F_TASK_IMAGE_CUALITY} INT NOT NULL
                ) AUTO_INCREMENT=1"
            );
    }
    
    private static $_instance = NULL;
    /**
    * @desc Obtiene el singleton del esta clase. Devuelve NULL hasta que se llame con parametros.
    * Luego devuelve el singleton
    * @param string El correo del usuario
    * @param int Calidad de las imagenes de esta sesion
    * @param int El uid del Cache del cliente
    * @param array Lista de uids de tareas confirmadas
    * 
    * @return CacheManager El singleton de esta instancia o null si no se ha llamado aun con parametros
    */
    public static function getManager($user = NULL, $task_image_cuality = 10, $cache_uid = -1, $confirmed_tasks = array()){
        if (self::$_instance == NULL && $user != NULL)
            self::$_instance = new CacheManager($user, $task_image_cuality, $cache_uid, $confirmed_tasks);
        
        return self::$_instance;
    }
    
    private function maintain($confirmed_tasks){
        // Debug
        _debug("CacheManager: Confirmando ". count($confirmed_tasks) ." tareas.");
        // Confirmacion de tareas
        // NOTA : Para optimizar, poner las tareas no confirmadas en una tabla aparte
        // Otra forma de optimizar seria tener varias tablas de cache. O mejor, una para
        // cada usuario, ya que no se necesitan hacer consultas cruzadas.
        if (count($confirmed_tasks)){
            $confirmed_tasks = implode(',', $confirmed_tasks);
            $res = DBHelper::Query(
                "UPDATE {$this->CACHE_TABLE} 
                 SET {$this->F_TASK_UID}=0
                 WHERE 
                   {$this->F_USER}='{$this->user}' AND
                   {$this->F_TASK_UID} > 0 AND
                   ({$this->F_TASK_UID} IN ({$confirmed_tasks}))
                 ");
        }
        
        // Debug
        _debug("CacheManager: Eliminando elementos obsoletos.");
        // Eliminacion de elementos obsoletos para el usuario actual
        DBHelper::Query(
            "DELETE FROM {$this->CACHE_TABLE} 
             WHERE {$this->F_USER} = '{$this->user}' AND 
                    ( {$this->F_CACHE_UID} <> {$this->cacheUid} OR
                      {$this->F_EXPIRES} < UNIX_TIMESTAMP() )
             ");
    }
    
    // Interfaz
    /**
    * @desc Comprueba si un recurso esta guardado en cache y confirmado
    * @param String url
    * @return bool TRUE si esta guardado y confirmado. FALSE en otro caso
    */
    public function isCached($url){
        return $this->getId($url, true) != NULL;
    }
    
    private function getId($url, $mustBeConfirmed = false){
        $confirmedCheck = $mustBeConfirmed ? "AND {$this->F_TASK_UID}=0" : "";
        
        $md5 = md5($url);
        $res = DBHelper::Query(
                "SELECT 
                    {$this->F_ID} FROM {$this->CACHE_TABLE}
                WHERE 
                    {$this->F_MD5}='{$md5}' AND
                    {$this->F_USER}='{$this->user}' AND
                    {$this->F_EXPIRES}>UNIX_TIMESTAMP() AND
                    {$this->F_TASK_IMAGE_CUALITY} >= {$this->taskImageCuality}
                    {$confirmedCheck}
                "
                , $error);
                
        return ($res && count($res) > 0) ? $res[0][$this->F_ID] : NULL;
    }
    
    /**
    * @desc Almacena un contenido en cache, si los headers permiten esta accion. 
    * OJO REALMENTE LA CACHE LA ALMACENA EL CLIENTE
    * @param String la url del recurso
    * @param HttpHeaders los headers de la respuesta http del recurso
    * 
    * @return bool TRUE si se guardo en cache, FALSE en otro caso
    */
    public function store($url, $contentType, $httpHeaders, &$md5 = NULL){
        // TODO: Implementar
        // Puede guardar una entrada nueva; o actualizar una vieja si la url coincide
        
        switch($contentType){
            case "image/jpeg":
            case "image/jpg":
            case "image/png":
            case "image/x-png":
            case "image/gif":
            case "image/x-gif":
            case "image/bmp":
                $cuality = $this->taskImageCuality;
                break;
            default:
                $cuality = 10;
                break;
        }
        
        $md5 = md5($url);
        if ($this->shouldCache($url, $httpHeaders, $exp)){
            if ( ($id = $this->getId($url)) ){
                return DBHelper::Query(
                    "UPDATE 
                        {$this->CACHE_TABLE} 
                     SET
                        {$this->F_EXPIRES}={$exp}, 
                        {$this->F_CACHE_UID}={$this->cacheUid}, 
                        {$this->F_TASK_UID}={$this->taskUid},
                        {$this->F_TASK_IMAGE_CUALITY}={$cuality}
                     WHERE
                        {$this->F_ID}={$id}",
                        
                    $error);
            }else{
                return DBHelper::Query(
                    "INSERT INTO
                        {$this->CACHE_TABLE} 
                     (
                        {$this->F_MD5},
                        {$this->F_USER},
                        {$this->F_EXPIRES}, 
                        {$this->F_CACHE_UID}, 
                        {$this->F_TASK_UID},
                        {$this->F_TASK_IMAGE_CUALITY}
                     )
                     VALUES
                     (
                        '{$md5}',
                        '{$this->user}',
                        {$exp},
                        {$this->cacheUid},
                        {$this->taskUid},
                        {$cuality}
                     )",
                        
                    $error);
            }
        }
        
        return false;
    }
    
    /**
    * @desc Determina si una respuesta debe ser almacenada en cache. NO SOPORTA VALIDACION POR EL MOMENTO
    * @param Url la url del recurso
    * @param HttpHeaders cabeceras de la respuesta http
    * @param int _OUT_ el momento en que expira el recurso
    * 
    * @return bool TRUE si se puede guardar en cache, FALSE en caso contrario
    */
    private function shouldCache($url, $httpHeaders, &$expiration = 0){
        
        if ($httpHeaders != null){
            // Si esta cache-control::max-age
            if ($httpHeaders->containsHeader("cache-control")){
                $parts = preg_split("#,|;#", $httpHeaders->getHeader("cache-control"));
                foreach($parts as $part){
                    $key = trim(strtok($part, "="));
                    $val = trim(strtok("\0")); // resto
                    
                    //if (strcasecmp($key, "no-cache") == 0){
    //                    return false;
    //                }
    //                
    //                if (strcasecmp($key, "no-store") == 0){
    //                    return false;
    //                }
                    
                    if (strcasecmp($key, "max-age") == 0){
                        $val = trim($val, "'\"");
                        if ($val == 0){
                            return false;
                        }
                        else{
                            $expiration = time() + trim($val, "'\"");
                            return true;
                        }
                    }
                }
            }
            
            if ($httpHeaders->containsHeader("expires")){ // Compatibilidad con HTTP/1.0
                $expiration = strtotime( $httpHeaders->getHeader("expires") );
                return $expiration > time();
            }
            return false;
        }else{
            // Basado en la extension de archivo
            if (preg_match('#\.(css|js|ico|gif|png|jpeg|jpg)$#i', $url->path)){
                $expiration = time() + self::URL_BASED_MAX_AGE; // 90 dias
                return true;
            }
            
            return false;
        }
    }
    
    public function getCacheUid(){
        return $this->cacheUid;
    }
    
    public function getTaskUid(){
        return $this->taskUid;
    }
    
    public function getConfirmedTasks(){
        return $this->confirmedTasks;
    }
}