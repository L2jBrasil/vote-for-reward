<?php

if (isset($_SERVER['HTTP_ORIGIN'])) {
    header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Max-Age: 86400');    // cache for 1 day
}
// Access-Control headers are received during OPTIONS requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {

    if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD']))
        header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");

    if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']))
        header("Access-Control-Allow-Headers: {$_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']}");
}



//date_default_timezone_set('America/Sao_Paulo');

set_time_limit(0);
session_cache_limiter('private');
session_cache_expire(160);

//set_include_path(dirname(__FILE__) . '/../_lib' . PATH_SEPARATOR .
//        dirname(__FILE__) . '/../_modules' . PATH_SEPARATOR .
//        get_include_path());


include_once '../vendor/autoload.php';


//RestServer usando SlimFW
Slim\Slim::registerAutoloader();

$app = new \Slim\Slim(array(
    'mode' => 'production',
    'debug' => false
        ));


//$app->add(new Application\CsrfProtection($config->get('sys.csfrkey'), $config->get('sys.salt'))); 
//Captura todas as exceptions
$app->error(function(\Exception $e = null) use ($app) {
    $erro = array('status' => 'error',
        'text' => $e->getMessage(),
        "code" => $e->getCode(),
        "message" => $e->getPrevious());
    \Application\Util::PrintJson($erro);
});


$app->group('/api', function () use($app) {

    $params = json_decode($app->request()->getBody());

    $app->any('/debug', function($id, $type = false) use($params) {
        \Application\Util::PrintJson($params);
    });
});


$app->run();
