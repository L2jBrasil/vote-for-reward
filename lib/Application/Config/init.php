<?php
namespace Application\Config;

/**
 * Description of init
 *
 * @author Leonan Carvalho
 */
final class init {

    //Standalone
    public static $initialized = false;
    //Global Config
    private $_config;
    
    private $_config_env;
    
    private $_config_global;

    /**
     * Classe geradora de configuração do sistema
     * @param string $environment define o ambiente do sistema
     * @param array $config Adiciona ou Sobrescreve alguma configuração
     * @param boolean $defineall Registra todas as configs em super globais
     * @param string $definekey Chave única para não haver duplicidade nas configs
     * @author leonan.carvalho
     */
    function __construct($environment = false, $config = array(), $defineall = true, $definekey = "CFG") {
        
        if(!$environment){
            $environment = getenv('APPLICATION_ENVIRONMENT');
            if(!$environment) {
                $environment = "dev";
                $rev = 0;
            }
        }
        
        $rev = (int) getenv('APPLICATION_REV');
        
        
        
        
        $env = strtolower($environment);
        
        $this->_config = array('sys.rev' => $rev,'sys.env' => $env);
        $this->_config_global = include('config_global.php');
        $this->_config_env = include("config_{$env}.php");

        //Obtém configurações do arquivo de ambiente, a configuração env sobrescreve qualquer valor global da config de env.
        $this->_config = $this->_config + $this->_config_env + $this->_config_global;

        //Oque for definido na variável $config irá sobrescrever  o valor da variável $_config;
        $this->_config = $config + $this->_config;

        if ($defineall) {
            self::initVars($definekey);
        }

        self::$initialized = true;
    }

    /**
     * Inicializa as variáveis do sistema como super globais:
     * EX:
     * $definekey = "CFG";
     * $key = "db.name";
     * Resultará : 
     *   - constante global CFG_db_name
     *   - variavel $GLOBAL[$definekey]['db']['name'];
     * Que seria o mesmo que:
     * $config = \Application\Main::getConfig();
     * $config->get('db.name');
     *
     * @param string $definekey Prefixo da lista de configuração
     */
    private function initVars($definekey) {
        $GLOBAL[$definekey] = array();
        if (!self::$initialized) {
            foreach ($this->_config as $key => $value) {
                $constantKey = $definekey . "_" . str_replace(".", "_", $key);
                define($constantKey, $value);
                $group = explode(".", $key);
                if (!isset($group[0])) {
                    $GLOBAL[$definekey][$key] = $value;
                } else {
                    $groupname = $group[0];
                    unset($group[0]);
                    if (!array_key_exists($groupname, $GLOBAL[$definekey])) {
                        $GLOBAL[$definekey][$groupname] = array();
                    }
                    $newKey = implode('.', $group);
                    $GLOBAL[$definekey][$groupname][$newKey] = $value;
                }
            }
        }
    }

    /**
     * Retorna o valor de uma configuração, caso encontrada.
     * 
     * @param string $key Chave de configuração
     * @param mixed $default  Valor padrão caso a config não exista
     * @return type
     */
    public function get($key, $default = null) {
        if (array_key_exists($key, $this->_config)) {
            return $this->_config[$key];
        } else {
            return $default;
        }
    }
    /**
     * Retorna todas as variáveis de um grupo específico 
     * Ex:
     * 
     * $this->getGroup('db'); // retorna todas as variáveis encontradas que comecem com "db.";
     * 
     * Seria o mesmo que fazer: 
     * 
     *  $GLOBAL[$definekey][$prefix];
     * 
     * 
     * @param type $prefix
     * @return type
     */
    public function getGroup($prefix) {
        if($prefix == "all"){
            return $this->_config;
        }
        $found = array();
        foreach ($this->_config as $key => $value) {
            $frag = explode(".", $key);
            if ($frag[0] == $prefix) {
                $found[$key] = $value;
            }
        }

        return $found;
    }

    /**
     * Retorna todo o array de configuração
     * 
     * @return array
     */
    public function getAll() {
        return $this->_config;
    }

}
