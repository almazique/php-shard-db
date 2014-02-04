<?php
namespace Shard;

require_once "LookupInterface.php";
require_once "DB.php";

class TableLookup implements LookupInterface {

    /**
    * @var DB
    */
    private $db;

    /**
    * @var Cache
    */
    private $cache;
    
    private $writableIds;
    
    private function cacheKey($entityId) {
        return "ShardLookup_{$entityId}";
    }
    
    private function getWritableShard() {
        return $this->writableIds[mt_rand(0, count($this->writableIds)-1)];
    }
    
    public function __construct(DB $db) {
        $this->db = $db;
    }
        
    public function setWritableShards($shardIds) {
        $this->writableIds = $shardIds;
    }
        
    public function getShardId($entityId, $assign = true) {
        $row = $this->db->shard(1)->selectRow("SELECT shardId FROM ShardLookup WHERE entityId=?", array($entityId), $this->cacheKey($entityId));
        if( !$row ) {
            if( $assign ) {
                $shardId = $this->getWritableShard();
                
                $this->db->shard(1)->update("INSERT INTO ShardLookup(entityId, shardId) VALUES(?,?)", array($entityId, $shardId), $this->cacheKey($entityId));
            } else {
                $shardId = 0;
            }
        } else {
            $shardId = $row->shardId;
        }
        return $shardId;
    }
}
