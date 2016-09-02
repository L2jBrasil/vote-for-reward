<?php

namespace Application\Security;
use Application\Util;

/**
 * Description of CsrfProtection
 *
 * @author Leonan Carvalho
 */
class CsrfProtection extends \Slim\Middleware {

    /**
     * CSRF token key name.
     *
     * @var string
     */
    protected $key;

    /**
     * Secret random workd to be salted in requests.
     *
     * @var string
     */
    protected $salt;

    /**
     * Kind of requests do be checked
     *
     * @var array
     */
    protected $requests;

    /**
     * Constructor.
     *
     * @param string    $key        The CSRF token key name.
     * @return void
     */
    public function __construct($key = 'APP-X', $salt = "", $requests = array('POST', 'PUT', 'DELETE','GET','HEAD'), $checkPostData = false, $ignore = array()) {
        if (session_status() == PHP_SESSION_NONE) {
//            session_start();
            $config = \Application\Main::getConfig();
            SessionManager::sessionStart($config->get('sys.codename'));
        }

        if (!is_string($key) || empty($key) || preg_match('/[^a-zA-Z0-9\-\_]/', $key)) {
            throw new \OutOfBoundsException('Invalid CSRF token key "' . $key . '"');
        }

        $this->key = $key;
        $this->salt = $salt;
        $this->requests = array_diff($requests, $ignore); //Remove da lista de requests checados os REQUESTS que devem ser ignorados.
        $this->ignore = $ignore; //Requests a serem ignorados
        //OBS: O request que nao existir nem no ignorado nem no requests será descartado como um request não autorizado
        
        
        //Creating KEy
        if (!isset($_SESSION[$this->key]) || (isset($_SESSION[$this->key]['renew']) && $_SESSION[$this->key]['renew']) ) {
//            $secret = hash("sha256", substr(md5(session_id() . $this->salt), 5, 10));
//            $secret = md5(uniqid(session_id(), true) . $this->salt);
//            $secret = hash_hmac('sha1', rand(), $this->salt); 

            $secret = md5(uniqid(rand(0, 0xffffffff) . $this->salt, true));
//            echo $secret;
            $_SESSION[$this->key] = array(
                'checkPostData' => $checkPostData,
                'private' => $secret,
                'public' => Util::Criptografar($secret),
                'agent' => (array_key_exists("HTTP_USER_AGENT", $_SERVER))? $_SERVER['HTTP_USER_AGENT'] : "unknow",
                'ip' => (array_key_exists("REMOTE_ADDR", $_SERVER))? $_SERVER['REMOTE_ADDR'] : "unknow",
                'renew' => false //Permite que a plicação controle a renovação do token
            );
        }else{
            //Atualiza os dados do usuário requisitante:
            $_SESSION[$this->key]['agent'] = (array_key_exists("HTTP_USER_AGENT", $_SERVER))? $_SERVER['HTTP_USER_AGENT'] : "unknow";
            $_SESSION[$this->key]['ip'] = (array_key_exists("REMOTE_ADDR", $_SERVER))? $_SERVER['REMOTE_ADDR'] : "unknow";
            $_SESSION[$this->key]['checkPostData'] = $checkPostData;
        }
    }

    /**
     * Call middleware.
     *
     * @return void
     */
    public function call() {
        // Attach as hook.
        $this->app->hook('slim.before', array($this, 'check'));

        // Call next middleware.
        $this->next->call();
    }

    /**
     * Check CSRF token is valid.
     * Note: Also checks POST data to see if a Moneris RVAR CSRF token exists.
     *
     * @return void
     */
    public function check() {
        
        // Check sessions are enabled.
        if (session_id() === '') {
            throw new \Exception('Sessions are required to use the CSRF Protection middleware.');
        }
        $csfr = $this->getProtection();
        $user_request = $this->app->request()->getMethod();
        
        // Validate the CSRF token.
        if (in_array($user_request, $this->requests)) {
            
            $token = $csfr['private'];
            
            if($csfr['checkPostData']){
                $headerToken = $this->app->request()->headers->get($this->key);
                $userToken = ($headerToken !== null)? $headerToken : ((array_key_exists($this->key, $_REQUEST))? $_REQUEST[$this->key] : null);
            }else{
                $userToken = $this->app->request()->headers->get($this->key);
            }
            
            if (null == $userToken) {
                $this->app->halt(401, 'Invalid or missing Request token.');
            } else {

                $userCSFR = array(
                    'private' => Util::Decriptografar($userToken),
                    'public' => $csfr['public'], //O novo mecanismo de criptografia ele nunca gera igual mesmo que seja a mesma string, então para comparação esse valor é estático.
                    'agent' => (array_key_exists("HTTP_USER_AGENT", $_SERVER))? $_SERVER['HTTP_USER_AGENT'] : "unknow",
                    'ip' => (array_key_exists("REMOTE_ADDR", $_SERVER))? $_SERVER['REMOTE_ADDR'] : "unknow",
                );

                
                
                $error = ['status'=>'Acesso negado','text'=>'Não foi possível completar essa requisição por motivos de segurança.','code'=>401];
                
                //Verifico se há alguam diferença no array original com o fornecido
                //Com essa validação eu pego a troca de IP, de Agente e tudo mais.
                //Decisões podem ser tomadas de acordo com o que ocorrer, como por exemplo renovar o ID da requisição
                foreach (array_diff($csfr, $userCSFR) as $key => $value) {
                    switch ($key) {
                        case 'private':
                            $this->app->halt(401, json_encode((object) (['message'=>'Invalid Request token.']+$error)));
                            break;
                        case 'public':
                            $this->app->halt(401, json_encode((object) (['message'=>'Unauthorized request']+$error)));
                            break;
                        case 'agent':
                            SessionManager::cleanSession();
                            $this->app->halt(406, json_encode((object) (['message'=>'Invalid or changed user agent','code'=>406]+$error))); //Pode ser que o 
                            break;
                        case 'ip': //Se o Ip foi alterado dar erro e relogar o usuário
                            SessionManager::cleanSession();
                            $this->app->halt(406, json_encode((object) (['message'=>'Invalid or changed user address','code'=>406]+$error)));//Retornar erro de sessão nmão ativa
                            break;
                        case 'checkPostData':
                        case 'renew':
                            //Essa variável de controle serve para ativar o catilho de verificação de postData, nada deve ocorrer quando ela for mudada.
                            break;
                        default:
//                            var_dump($csfr, $userCSFR,$key);die(); //Para debugar erro 401
                            $this->app->halt(401, json_encode((object) (['message'=>'Invalid or missing Default token.']+$error)));
                            break;
                    }
                }
            }
        }
        else if(in_array($user_request, $this->ignore)){
            //Nothing!
        }
        else{
             $this->app->halt(401, 'Unauthorized request');
        }

        // Assign CSRF token key and value to view.
//        $this->app->view()->appendData(array(
//            'csrf_key' => $this->key,
//            'csrf_token' => $csfr,
//        ));
    }

    public function getProtection() {
        return $_SESSION[$this->key];
    }

}
