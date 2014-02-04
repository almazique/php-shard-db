<?php
namespace Shard;

interface LookupInterface {
    
    public function getShardId($entityId, $assign = true);
    
}