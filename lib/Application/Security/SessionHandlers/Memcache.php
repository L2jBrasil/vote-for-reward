<?php

namespace Application\Security\SessionHandlers;
/**
 * Description of Memcache
 *
 * @author Leonan
 */
class Memcache  implements \SessionHandlerInterface{

    protected $mcCon;
    private $_sessionId;
    //https://www.digitalocean.com/community/tutorials/how-to-share-php-sessions-on-multiple-memcached-servers-on-ubuntu-14-04
    
    
    public function __construct() {
        
    }

    public function open($save_path, $session_name)
    {
        try{
        $this->mcCon = new \Memcache();
        $this->mcCon->connect('127.0.0.1', 11211);
        return true;
        }  catch (\Exception $e){
            die($e->getMessage());
        }
    }

    public function close($d = "abc")
    {
        $this->mcCon->close();
        return true;
    }

    public function read($id)
    {
        $this->_sessionId = $id;
        return $this->mcCon->get($id);
    }

    public function write($id, $data)
    {
        $expiry =  (int) ini_get('session.gc_maxlifetime');
         return $this->mcCon->set($id, $data, MEMCACHE_COMPRESSED, $expiry);
    }

    public function destroy($id)
    {
       return $this->mcCon->delete($id);
    }

    public function gc($max)
    {
        
    }
}
