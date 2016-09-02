<?php

namespace Application\Security;

/**
 * Description of SessionManager
 *
 * @author Leonan S. Carvalho
 */
class SessionManager {

    static function sessionStart($name, $limit = 0, $path = '/', $domain = null, $secure = null, $lifetime = 36000, $handler = 'Memcache') {

        if ($handler) {
            $HandlerClass = '\Application\Security\SessionHandlers\\' . $handler;
            $saveHandler = new $HandlerClass();
            session_set_save_handler(
                array(&$saveHandler, 'open'),
                array(&$saveHandler, 'close'),
                array(&$saveHandler, 'read'),
                array(&$saveHandler, 'write'),
                array(&$saveHandler, 'destroy'),
                array(&$saveHandler, 'gc')
            );
        }

        ini_set('session.auto_start', 0);
        ini_set('session.gc_probability', 1);
        ini_set('session.gc_divisor', 100);
        ini_set('session.gc_maxlifetime', $lifetime);
        ini_set('session.referer_check', "");
//        ini_set('session.entropy_file', tempnam(sys_get_temp_dir(), "JLC"));
        ini_set('session.entropy_length', 16);
        ini_set('session.use_cookies', 1);
        ini_set('session.use_only_cookies', 1);
        ini_set('session.use_trans_sid', 0);
        ini_set('session.hash_function', 1);
        ini_set('session.hash_bits_per_character', 5);

//        register_shutdown_function('session_write_close');

        // Define nivel SSL
//        if (!headers_sent()) {
            $https = isset($secure) ? $secure : isset($_SERVER['HTTPS']);
            // Define parâmetros do cookie
            session_set_cookie_params($limit, $path, $domain, $https, true);
//        }
        // Define o nome do cookie da sessão
        session_name($name . '_syss');
        session_start();
        

        //Renova a sessão por tempo de inatividade
//        if (!isset($_SESSION['CREATED'])) {
//            $_SESSION['CREATED'] = time();
//        } else if (time() - $_SESSION['CREATED'] > (3600 * 3)) { //3h de inatividade
//            // session started more than 30 minutes ago
//            session_regenerate_id(true);    // change session ID for the current session and invalidate old session ID
//            $_SESSION['CREATED'] = time();  // update creation time
//        }
        //Define o tempo que a sessão deve expirar.
//        if (array_key_exists("EXPIRES", $_SESSION)) {
////            $_SESSION['EXPIRES'] = time() + 100;
//            var_dump($_SESSION['EXPIRES'] - time());
//            exit;
//        } else {
//            $_SESSION['EXPIRES'] = time() + (3600 * 12); //12h
//        }
        // Verifica se a validade da sessão não expirou
        if (self::validateSession()) {
            // Verifica se essa sessão é nova ou se é um a tentativa "hijacking"
            // http://pt.wikipedia.org/wiki/Session_hijacking

            if (!self::preventHijacking()) {
                // Reset  nos dados da sessão e regenera o ID
                $_SESSION = array();
                $_SESSION['IPaddress'] = $_SERVER['REMOTE_ADDR'];
                $_SESSION['userAgent'] = $_SERVER['HTTP_USER_AGENT'];
                self::regenerateSession();

                // 5% de chance da sessão ser renovada em qualquer request
                // A troca de id de sessão consome muito recurso e ativá-la a 
                // todo request pode causar stress ao servidor.
            } elseif (rand(1, 100) <= 5) {
                self::regenerateSession();
            }
        } else {
            session_name($name . '_syss');
            session_destroy();
            session_start();
            
            $_SESSION = array();
//            $_SESSION['EXPIRES'] = time() + (3600 * 12); //12h
        }
    }

    static protected function preventHijacking() {
        if (!isset($_SESSION['IPaddress']) || !isset($_SESSION['userAgent']))
            return true;

        if ($_SESSION['IPaddress'] != $_SERVER['REMOTE_ADDR'])
            return false;

        if ($_SESSION['userAgent'] != $_SERVER['HTTP_USER_AGENT'])
            return false;

        return true;
    }

    static function cleanSession() {
        if (session_status() !== PHP_SESSION_NONE) {
            $_SESSION = array();
            session_unset();
            session_destroy();
        }
    }
    
    

    static function regenerateSession() {
//        TODO-> Acertar a renovação da sessão.
//         Se essa sessão é obsoleta isso significa que ela já tem um novo id.
//        if (array_key_exists('OBSOLETE', $_SESSION) && $_SESSION['OBSOLETE'] == true) {
//            return;
//        }
//        // A sessão atual tem duração de 10 segundos
//        $_SESSION['OBSOLETE'] = true;
//        $_SESSION['EXPIRES'] = time() + 10;
//         Cria uma nova sessão sem destruir os dados da sessão atual, apenas criando um novo id.
//        $old_sessionid = session_id();
//        session_regenerate_id(false);
//        $new_sessionid = session_id();
//        echo "Old Session: $old_sessionid<br />";
//        echo "New Session: $new_sessionid<br />";
//        // Pega o id atual e fecha a sessão para permitir que outros scripts use ela.
////       
//        $newSession = session_id();
//        session_write_close();
////
////        // Define o ID da sessão e inicia ela novamente.
//        session_id($newSession);
//        session_start();
//        session_regenerate_id(true);
    }

    static protected function validateSession() {
        if (isset($_SESSION['OBSOLETE']) && !isset($_SESSION['EXPIRES']))
            return false;

        if (isset($_SESSION['EXPIRES']) && $_SESSION['EXPIRES'] < time())
            return false;

        return true;
    }
    
    static function getSessionId() {
        
        if(self::validateSession()) {
            return session_id();
        } else {
            return false;
        }
        
    }
    
    public static function unserialize($session_data) {
        $method = ini_get("session.serialize_handler");
        switch ($method) {
            case "php":
                return self::unserialize_php($session_data);
            case "php_binary":
                return self::unserialize_phpbinary($session_data);
            default:
                throw new \Exception("Unsupported session.serialize_handler: " . $method . ". Supported: php, php_binary");
        }
    }

     private static function unserialize_php($session_data) {
        $return_data = array();
        $offset = 0;
        while ($offset < strlen($session_data)) {
            if (!strstr(substr($session_data, $offset), "|")) {
                throw new Exception("invalid data, remaining: " . substr($session_data, $offset));
            }
            $pos = strpos($session_data, "|", $offset);
            $num = $pos - $offset;
            $varname = substr($session_data, $offset, $num);
            $offset += $num + 1;
            $data = unserialize(substr($session_data, $offset));
            $return_data[$varname] = $data;
            $offset += strlen(serialize($data));
        }
        return $return_data;
    }

    private static function unserialize_phpbinary($session_data) {
        $return_data = array();
        $offset = 0;
        while ($offset < strlen($session_data)) {
            $num = ord($session_data[$offset]);
            $offset += 1;
            $varname = substr($session_data, $offset, $num);
            $offset += $num;
            $data = unserialize(substr($session_data, $offset));
            $return_data[$varname] = $data;
            $offset += strlen(serialize($data));
        }
        return $return_data;
    }
    
    

}
