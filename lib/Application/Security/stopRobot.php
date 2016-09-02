<?php

namespace Application\Security;

/**
 * A class to handle secure against Robots
 *
 *
 * @author  Leonan S. Carvalho
 */
class stopRobot {

    private $_token;
    private $_secret = ""; //TODO implementar captcha
    private $_remoteIp;
    private $_certFile;

    public function __construct($token) {
        //http://stackoverflow.com/questions/316099/cant-connect-to-https-site-using-curl-returns-0-length-content-instead-what-c/316732#316732
        $pemFile = dirname(__FILE__) . DIRECTORY_SEPARATOR . "stopRobotcurlcert.pem";
        
        if(!file_exists($pemFile)){
            $pemFileContent = file_get_contents('http://curl.haxx.se/ca/cacert.pem');
            $pemHandle = fopen($pemFile, "w");
            fwrite($pemHandle, $pemFileContent);
            fclose($pemHandle);
        }
        $this->_certFile = $pemFile;
        
        $this->_remoteIp = $_SERVER['REMOTE_ADDR'];
        if (isset($_SERVER["HTTP_X_FORWARDED_FOR"])) {
            $this->_remoteIp = $_SERVER["HTTP_X_FORWARDED_FOR"];
        }

        if (isset($_SERVER["HTTP_CLIENT_IP"])) {
            $this->_remoteIp = $_SERVER["HTTP_CLIENT_IP"];
        }


        if (isset($_SERVER["HTTP_CF_CONNECTING_IP"])) {
            $this->_remoteIp = $_SERVER["HTTP_CF_CONNECTING_IP"];
        }

        
        if($this->_remoteIp == "::1"){
            $this->_remoteIp = "127.0.0.1";
        }
        $this->_token = $token;
    }

    public function validade() {

        $postBody = array(
            "secret" => $this->_secret,
            "response" => $this->_token,
            "remoteip" => $this->_remoteIp,
        );

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, "https://www.google.com/recaptcha/api/siteverify");
        curl_setopt($ch, CURLOPT_POST, 1);
        if(array_key_exists('HTTP_USER_AGENT', $_SERVER)){
            curl_setopt($ch, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT']);
        }
//        if($this->_remoteIp == "127.0.0.1"){
//            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
//            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2); 
//        }else{
            curl_setopt ($ch, CURLOPT_CAINFO, $this->_certFile);
//        }
        
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postBody));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $server_output = curl_exec($ch);
        curl_close($ch);
//        var_dump($server_output,$postBody,http_build_query($postBody));
        $response = json_decode($server_output);
        if (null != $response && isset($response->success)) {
            return $response->success;
        } else {
            return false;
        }
    }

}
