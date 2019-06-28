<?php
/**
 * @author Abraham Toledo <abrahamtoledo90@gmail.com>
 * @copyright 2019 Abraham Toledo
 * @
 */

/**
 * Proxy Information
 */
 class ProxyInfo {
    public $host;
    public $port;
    public $user;
    public $password;

    public function __construct($host, $port, $user, $password){
        $this->host = $host;
        $this->port = $port;
        $this->user = $user;
        $this->password = $password;
    }
}


 /**
  * Represents an HTTP Request. A pool is used to allow several downloads in paralel.
  */
class WebRequest
{
    public const DATA_URLENCODED = 0;
    public const DATA_MULTIPART = 1;
    public const DATA_JSON = 2;
    public const DATA_RAW = 3;

    /**
     * The url to fetch
     *
     * @var string
     */
    protected $url;
    public function getUrl(){
        return $this->url;
    }
    public function setUrl($val){
        $this->url = $val;
    }
    

    /**
     * HTTP Method of the request [POST, GET, PUT, DELETE, PATCH]
     *
     * @var string
     */
    protected $method;
    public function getMethod(){
        return $this->method;
    }
    public function setMethod($val){
        $this->method = $val;
    }
    

    /**
     * A Dictionary with post data. "name" => "value"
     *
     * @var array
     */
    protected $postData;
    public function getPostData(){
        return $this->postData;
    }
    public function setPostData($val){
        $this->postData = $val;
    }
    

    /**
     * An array with HTTP Headers to be sent. This will override any default Header set by CURL
     *
     * @var array
     */
    protected $headers;
    public function getHeaders(){
        return $this->headers;
    }
    public function setHeaders($val){
        $this->headers = $val;
    }
    

    /**
     * The timeout for this request
     *
     * @var int
     */
    protected $timeout;
    public function getTimeout(){
        return $this->timeout;
    }
    public function setTimeout($val){
        $this->timeout = $val;
    }

    /**
     * Specify if the remote certificate must be validated for secure connections
     *
     * @var bool
     */
    protected $verifySsl;
    public function getVerifySsl(){
        return $this->verifySsl;
    }
    public function setVerifySsl($val){
        $this->verifySsl = $val;
    }

    /**
     * A file containing a CookieJar that will be used with this WebRequest
     *
     * @var string
     */
    protected $cookieJar = NULL;
    public function getCookieJar(){
        return $this->cookieJar;
    }
    public function setCookieJar($val){
        $this->cookieJar = $val;
    }

    /**
     * Proxy Information. If null no proxy will be used
     *
     * @var ProxyInfo
     */
    protected $proxyInfo = NULL;
    public function getProxyInfo(){
        return $this->proxyInfo;
    }
    public function setProxyInfo($val){
        $this->proxyInfo = $val;
    }

    /**
     * The transfer info.
     *
     * @var array
     */
    protected $transferInfo;
    public function getTransferInfo(){
        return $this->transferInfo;
    }
    

    /**
     * The response content without headers
     *
     * @var string
     */
    protected $response;
    public function getResponse(){
        return $this->response;
    }

    /**
     * Sets the type of data to post. Valid values are DATA_URLENCODED, DATA_MULTIPART, DATA_JSON, DATA_RAW
     *
     * @var int
     */
    protected $dataType = self::DATA_URLENCODED;
    public function getDataType(){
        return $this->dataType;
    }
    public function setDataType($val){
        if (in_array($val, [
                self::DATA_URLENCODED,
                self::DATA_MULTIPART,
                self::DATA_JSON,
                self::DATA_RAW,
        ])){
            $this->dataType = $val;
        }
    }
    
    
    
    public function __construct($url = NULL, $method="GET", $postData=NULL, $headers=[], $verifySsl=true, $timeout=0){
        $this->setUrl($url);
        $this->setMethod($method);
        $this->setPostData($postData);
        $this->setHeaders($headers);
        $this->setVerifySsl($verifySsl);
        $this->setTimeout($timeout);
    }

    public static function requestAll($requests, $maxThreads = 20){
        if ($requests instanceof self){
            $requests = [$requests];
        }

        if (!is_array($requests)){
            throw new InvalidArgumentException("\$request debe ser una instancia de " 
                                                . __CLASS__ . " o una lista de instancias", 1);
        }

        $mh = curl_multi_init();
        $ch = array();
        
        // Run Loop. Dinamically adds curl resources;
        $c = count($requests);
        $running = 0;
        $i = 0;
        do{
            // While there are available threads: 
            // 		Add new handle to curl multiprocessor 
            while($i < $c && $running < $maxThreads){
                $ch[$i] = curl_init($requests[$i]->url);
                
                curl_setopt($ch[$i], CURLOPT_RETURNTRANSFER, 1);

                $headers = $requests[$i]->headers;
                if ($requests[$i]->dataType == self::DATA_JSON){
                    $headers[] = "Content-Type: application/json";
                }

                curl_setopt($ch[$i], CURLOPT_HTTPHEADER, $headers);
                curl_setopt($ch[$i], CURLOPT_HEADER, 0);
                
                if ($requests[$i]->proxyInfo){
                    $proxyInfo = $requests[$i]->proxyInfo;
                    curl_setopt($ch[$i], CURLOPT_PROXY, "{$proxyInfo->host}:{$proxyInfo->port}");
                    curl_setopt($ch[$i], CURLOPT_PROXYUSERPWD, "{$proxyInfo->user}:{$proxyInfo->password}");
                }

                if (strtoupper($requests[$i]->method) != "GET"){
                    curl_setopt($ch[$i], CURLOPT_CUSTOMREQUEST, $requests[$i]->method);
                }
                
                if ($requests[$i]->postData){
                    $postData = $requests[$i]->postData;
                    switch ($requests[$i]->dataType) {
                        case self::DATA_URLENCODED:
                            $postData = join(
                                "&", 
                                array_map(function($k, $v){ 
                                    return urlencode($k) . "=" . urlencode($v); 
                                }, array_keys($postData), $postData)
                            );
                            break;
                        case self::DATA_MULTIPART:
                            // DO nothing, the data is in the final format
                            break;
                        case self::DATA_JSON:
                            $postData = json_encode($postData);
                            break;
                        case self::DATA_RAW:
                            // Do nothing with the data
                            break;
                    }

                    curl_setopt($ch[$i], CURLOPT_POSTFIELDS, $postData);
                }
                
                curl_setopt($ch[$i], CURLOPT_TIMEOUT, $requests[$i]->timeout);
                curl_setopt($ch[$i], CURLOPT_FOLLOWLOCATION, 1);
                curl_setopt($ch[$i], CURLOPT_MAXREDIRS, 15);
                curl_setopt($ch[$i], CURLOPT_SSL_VERIFYPEER, $requests[$i]->verifySsl);
                curl_setopt($ch[$i], CURLOPT_SSL_VERIFYHOST, $requests[$i]->verifySsl);
                
                if ($requests[$i]->cookieJar && is_file($requests[$i]->cookieJar)){
                	curl_setopt($ch[$i], CURLOPT_COOKIEJAR, $requests[$i]->cookieJar);
                	curl_setopt($ch[$i], CURLOPT_COOKIEFILE, $requests[$i]->cookieJar);
                }
                
                curl_multi_add_handle($mh, $ch[$i]);
                
                $i++; $running++;
            }
            
            // Advance the downloads progress
            curl_multi_exec($mh, $running);
            usleep(100);
        } while($running > 0);
        
        // Download is completed at this point
        // Fetch results from curl
        $contents = array();
        $transfer_info = array();
        for($i=0; $i < $c; $i++){
            $cont = curl_multi_getcontent($ch[$i]);
            
            // this is to copy the string rather than assign by reference
            $requests[$i]->response = substr($cont, 0);
            $requests[$i]->transferInfo = curl_getinfo($ch[$i]);
            
            curl_multi_remove_handle($mh, $ch[$i]);
            curl_close($ch[$i]);
        }

        return $requests;
    }

    public function request(){
        return self::requestAll([$this]);
    }
}

