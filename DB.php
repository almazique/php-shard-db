<?php
/**
* Sharded Database
* 
* @author Alex 
*/
namespace Shard;

require_once "Connection.php";
require_once "ShardException.php";
require_once "LookupInterface.php";
require_once "CacheInterface.php";

class DB {
    
    const SIDE_A = 1;
    const SIDE_B = 2;
          
    /**
    * @var LookupInterface
    */
    private $lookup;
    
    /**
    * @var CacheInterface
    */
    private $cache;
    
    /**
    * @var LogInterface
    */
    private $log;
    
    private $poolName;
    private $username;
    private $password;
    
    // lock side. 0 -> no lock, 1 -> A, 2 -> B
    private $sideLock = 0;

    private $shardCount;
    private $dsn = array();
    private $connections = array();   
    
    /**
    * put your comment there...
    * 
    * @param mixed $shardCount
    * @param ShardLookup $shardLookup
    * @param ShardCache $shardCache
    * @return ShardDB
    */
    public function __construct($poolName = "Default") {
        $this->poolName = $poolName;
    }

    public function addShard($shardId, $dsnSideA, $dsnSideB) {
        if( isset($this->dsn[$shardId]) ) throw new ShardException("Shard {$shardId} already defined");
        $this->dsn[$shardId] = array();
        if( $dsnSideA ) $this->dsn[$shardId][self::SIDE_A] = $dsnSideA;
        if( $dsnSideB ) $this->dsn[$shardId][self::SIDE_B] = $dsnSideB;
    }
    
    public function setAuth($username, $password) {
        $this->username = $username;
        $this->password = $password;
    }
    
    public function setLookup(LookupInterface $shardLookup) {
        $this->lookup = $shardLookup;
    }
    
    public function setCache(CacheInterface $shardCache) {
        $this->cache = $shardCache;
    }
    
    public function setLog(LogInterface $shardLog) {
        $this->log = $shardLog;
    }
    
    /**
    * Get Shard connection where an entity is located.
    * 
    * @param int $entityId
    * @return ShardConnection
    */
    public function shard($entityId) {
        $shardId = (count($this->dsn) > 1) ? $this->lookup->getShardId($entityId) : 1;
        $side = $this->sideLock ? $this->sideLock : (($entityId % 2) ? self::SIDE_A : self::SIDE_B);
        $otherSide = ($side == self::SIDE_A) ? self::SIDE_B : self::SIDE_A;
        if( empty($this->connections[$shardId]) ) {
            $dsn = $this->dsn[$shardId];
            $this->connections[$shardId] = array();
            if( !empty($dsn[self::SIDE_A]) ) { 
                $c = new Connection($this->poolName, $shardId, $side, $dsn[self::SIDE_A], $this->username, $this->password, empty($dsn[self::SIDE_B]) ? false : $dsn[self::SIDE_B]);
                $c->setCache($this->cache);
                $c->setLog($this->log);
                $this->connections[$shardId][self::SIDE_A] = $c;
                
            }
            if( !empty($dsn[self::SIDE_B]) ) {
                $c = new Connection($this->poolName, $shardId, $side, $dsn[self::SIDE_B], $this->username, $this->password, empty($dsn[self::SIDE_A]) ? false : $dsn[self::SIDE_A]);
                $c->setCache($this->cache);
                $c->setLog($this->log);
                $this->connections[$shardId][self::SIDE_B] = $c;
            }
        }
        $connection = empty($this->connections[$shardId][$side]) ? $this->connections[$shardId][$otherSide] : $this->connections[$shardId][$side];
        return $connection;
    }
        
}