<?php
namespace Shard;

interface CacheInterface {
    
    public function get($key);
    public function set($key, $value);
    public function delete($key);
    
}