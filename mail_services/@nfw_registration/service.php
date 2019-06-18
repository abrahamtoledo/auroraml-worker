<?php
class ServiceNfw_registration extends ServiceBase{
    const TABLE = "nfw_registered_users";
    const TOKEN_HEADER_NAME = "X-NFW-TOKEN";
    
    protected function Authorized(){
        return true;
    }
    
    protected function RunService(){
        $this->createTable();
        
        $email = $this->user;
        list($name, $phoneNumber) = explode(":", $this->data->_default);
        $name = DBHelper::EscapeString($name);
        $phoneNumber = DBHelper::EscapeString($phoneNumber);
        
        try{
            $res = DBHelper::Select(self::TABLE, "id", "email='$email'", NULL, 1);
            if (count($res) > 0){
                DBHelper::Update(self::TABLE, 
                                    array("name"=>$name, "phone_number"=>$phoneNumber), 
                                    "id={$res[0]['id']}"
                                    );
            }else{
                DBHelper::Insert(self::TABLE, 
                            array(array(
                                    $email, $name, $phoneNumber
                                )),
                            array("email", "name", "phone_number")
                        );
            }
        }catch (DBException $ex){
            error_log($ex->__toString());
        }
        
        $this->SendMail("Confirmacion de registro:\r\n\r\n Nombre: $name\r\n Telefono: $phoneNumber", 
                            "Confirmacion de registro",
                            NULL,
                            array(
                                self::TOKEN_HEADER_NAME . ": " . base64_encode(md5($phoneNumber, true))
                            )
                        );
    }
    
    protected function createTable(){
        DBHelper::QueryOrThrow(
            "CREATE TABLE IF NOT EXISTS " . self::TABLE . "(" .
                "id INT(10) not null auto_increment, ".
                "email TEXT not null, ".
                "name TEXT not null, ".
                "phone_number TEXT not null, " .
                "PRIMARY KEY (id)" .
            ")"    
        );
    }
}
