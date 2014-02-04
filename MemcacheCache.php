<?php
namespace Shard;

require_once "CacheInterface.php";

class MemcacheCache implements CacheInterface {
    
    private $memcache;
    
    public function __construct() {
        $this->memcache = new \Memcache();
    }
    
    public function addServer($host, $port=11211) {
        $this->memcache->addServer($host, $port);
    }
    
    public function get($key) {
        return $this->memcache->get($key);
    }
    
    public function set($key, $value) {
        return $this->memcache->set($key, $value);
    }
    
    public function delete($key) {
        return @$this->memcache->delete($key);
    }
    
}