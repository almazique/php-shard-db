<?php
/**
* Database shard connection
* 
* @author Alex
*/
namespace Shard;

require_once "ShardException.php";

class Connection {

    /**
     * @var PDO
     */
    private $pdo;

    /**
     * @var CacheInterface
     */
    private $cache;
    /**
    * @var LogInterface
    */
    private $log;

    private $pool;
    private $shardId;
    private $side;
    private $dsn;
    private $username;
    private $password;
    
    private $fallbackDsn;    
    private $fallbackTime = 0;
    

    // array of timestamps each connection in the pool was used last
    private $lastPing = 0;
    // currect transaction name
    private $transaction = "";

    /**
     * Constructor
     * 
     * @param string $pool
     * @param int $shardId
     * @return Shard
     */
    public function __construct($poolName, $shardId, $side, $dsn, $username, $password, $fallbackDsn = false) {
        $this->pool = $poolName;
        $this->shardId = $shardId;
        $this->side = $side;
        
        $this->dsn = $dsn;
        $this->username = $username;
        $this->password = $password;
        $this->fallbackDsn = $fallbackDsn;
    }
    
    public function setCache(CacheInterface $shardCache) {
        $this->cache = $shardCache;
    }
    
    public function setLog(LogInterface $shardLog) {
        $this->log = $shardLog;
    }
    
    private function log($query, $args, $time) {
        if( !$this->log ) return;
        $side = ($this->side == DB::SIDE_A) ? "a" : "b";
        if( !empty($args) ) {
            $parts = explode("?", $query);
            $query = "";
            foreach($parts as $k=>$part) {
                $query .= $part;
                if( $k == count($parts)-1 ) break;
                elseif( !isset($args[$k]) ) $query .= "?";
                elseif( is_numeric($args[$k]) ) $query .= $args[$k];
                elseif( is_null($args[$k]) ) $query .= "NULL";
                else $query .= "'" . addslashes($args[$k]) . "'";
            }
        }
        
        $str = sprintf("[%s%d%s, %.3f] %s", $this->pool, $this->shardId, $side, $time, $query);
        $this->log->log($str);
    }

    /**
     * Get a PDO connection
     * If connection is unavailable, fallbackDsn is used. If both sides are down an exception is thrown
     * 
     * @throws Shard\Exception
     * @return PDO
     */
    private function connection() {
        if( !empty($this->pdo) ) {
            if( time() - $this->lastPing > 1 ) {
                try {
                    $this->pdo->query("SELECT 1");
                    $this->lastPing = time(); // Connection is still live
                } catch (PDOException $e) {
                    $this->pdo = null; // Connection timed out
                }
            } else {
                $this->lastPing = time();
            }
        }
        
        if( empty($this->pdo) ) {
            try {
                $dsn = $this->fallbackTime ? $this->fallbackDsn : $this->dsn;
                $time = microtime(true);
                $this->pdo = new \PDO($dsn, $this->username, $this->password, array(
                            \PDO::ATTR_ERRMODE                   => \PDO::ERRMODE_EXCEPTION,
                            \PDO::ATTR_PERSISTENT                => false,
                            \PDO::ATTR_AUTOCOMMIT                => true,
                            \PDO::ATTR_DEFAULT_FETCH_MODE        => \PDO::FETCH_OBJ,
                            \PDO::ATTR_TIMEOUT                   => 2,
                            \PDO::MYSQL_ATTR_USE_BUFFERED_QUERY  => true,
                            \PDO::MYSQL_ATTR_INIT_COMMAND        => "SET NAMES utf8",
                            ));
                $this->log("CONNECT", array(), microtime(true)-$time);
                $this->lastPing = time();
            } catch (Exception $e) {
                // if connection failed - try other side
                if( !$this->fallbackTime && $this->fallbackDsn ) {
                    $this->fallbackTime = time();
                    return $this->connection();
                // if fallback connection also failed, but not immediately - switch back
                } elseif( $this->fallbackTime && time() - $this->fallbackTime > 10 ) {
                    $this->fallbackTime = 0;
                    return $this->connection();
                }
                $error = $e->getMessage();
            }
        }

        if( isset($error) ) {
            throw new ShardException("Shard is down: {$error}");    
        }
        return $this->pdo;
        
    }

    /**
     * Run a parameterized SQL query
     * 
     * @param string $query
     * @param array $args
     * @return PDOStatement
     */
    public function query($query, $args = array()) {
        try {
            $time = microtime(true);
            if( empty($args) ) {
                $sth = $this->connection()->query($query);
            } else {
                $sth = $this->connection()->prepare($query);
                $sth->execute($args);
            }
            $this->log($query, $args, microtime(true) - $time);
        } catch (PDOException $e) {
            throw new ShardException($e->getMessage());
        }

        return $sth;
    }

    /**
     * Run a Select query and get the first row
     * If cacheKey is specified will try to get it from Cache first.
     * 
     * @param string $query
     * @param array $args
     * @param string $cacheKey
     * @return array
     */
    public function selectRow($query, $args = array(), $cacheKey = null) {
        $data = $this->cache ? $this->cache->get($cacheKey) : false;
        if( $data === false ) {
            $data = $this->query($query, $args)->fetch();
            if( $this->cache ) $this->cache->set($cacheKey, $data);
        }
        return $data;
    }

    /**
     * Run a Select query and get the array of first column values
     * If cacheKey is specified will try to get it from Cache first.
     * 
     * @param string $query
     * @param array $args
     * @param string $cacheKey
     * @return array
     */
    public function selectColumn($query, $args = array(), $cacheKey = null) {
        $data = $this->cache ? $this->cache->get($cacheKey) : false;
        if( $data === false ) {
            $data = $this->query($query, $args)->fetchAll(PDO::FETCH_COLUMN);
            if( $this->cache ) $this->cache->set($cacheKey, $data);
        }
        return $data;
    }


    /**
     * Run a Select query
     * If cacheKey is specified will try to get it from Cache first.
     * 
     * @param string $query
     * @param array $args
     * @param string $cacheKey
     * @return array
     */
    public function select($query, $args = array(), $cacheKey = null) {
        $data = $this->cache ? $this->cache->get($cacheKey) : false;
        if( $data === false ) {
            $data = $this->query($query, $args)->fetchAll();
            if( $this->cache ) $this->cache->set($cacheKey, $data);
        }
        return $data;
    }

    /**
     * Run a Update-type query (update, insert, delete)
     * Returns number of affected rows
     * 
     * @param string $query
     * @param array $args
     * @param string $cacheKey
     * @return int
     */
    public function update($query, $args = array(), $cacheKey = null) {
        $sth = $this->query($query, $args);
        if( $this->cache ) $this->cache->delete($cacheKey);
        return $sth->rowCount();
    }

    /**
     * Begin a transaction
     * Only top level transaction's commit's and rollbacks will be executed
     * 
     * @param string $name
     * @return bool
     */
    public function beginTransaction($name = 'default') {
        if( empty($this->transaction) ) {
            $this->connection()->beginTransaction();
            $this->transaction = $name;
            return true;
        } elseif ($this->transaction == $name) {
            throw new ShardException("Transaction '{$name}' already started");
        } else {
            // nested transaction
        }
        return false;
    }

    /**
     * Commit a transaction. Will only work if name is of the top-level transaction
     * 
     * @param string $name
     * @return bool
     */
    public function commit($name = 'default') {
        if( $name == $this->transaction ) {
            $this->connection()->commit();
            $this->transaction = null;
            return true;
        } else {
            // nested transaction
        }
        return false;
    }

    /**
     * Rollback a transaction. Will only work if name is for the top-level transaction.
     * 
     * @param string $name
     * @return bool
     */
    public function rollback($name = 'default') {
        if( $name == $this->transaction ) {
            $this->connection()->rollBack();            
            $this->transaction = null;
            return true;
        } else {
            // nested transaction
        }
        return false;
    }

    public function disconnect() {
        if( $this->pdo ) {
            unset($this->pdo);
        }
    }

    /**
     * Get last insert ID
     * 
     */
    public function lastInsertId() {
        return $this->connection()->lastInsertId();
    }
}