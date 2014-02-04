<?php
require_once "../DB.php";
require_once "../MemcacheCache.php";
require_once "../TableLookup.php";
require_once "../GenericLog.php";

$dbuser = "root";
$dbpass = "";

$log = new Shard\GenericLog();


$cache = new Shard\MemcacheCache();
$cache->addServer("localhost");


$db = new Shard\DB("main");
$db->setAuth($dbuser, $dbpass);
$db->addShard(1, "mysql:host=localhost;dbname=Shard1", false);
$db->addShard(2, false, "mysql:host=localhost;dbname=Shard2");


$db->setCache($cache);
$db->setLog($log);

$lookupDb = new Shard\DB("lookup");
$lookupDb->setAuth($dbuser, $dbpass);
$lookupDb->addShard(1, "mysql:host=localhost;dbname=Lookup", false);
$lookupDb->setCache($cache);
$lookupDb->setLog($log);

$lookup = new Shard\TableLookup($lookupDb);
$lookup->setWritableShards(array(1,2));

$db->setLookup($lookup);


$id = mt_rand(1, 1000);
$db->shard($id)->update("INSERT INTO Ex1(userId, name, birthDate) VALUES(?,?,?)", array($id, "Joe Branson", "1977-09-08"));