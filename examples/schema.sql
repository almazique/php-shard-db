# Lookup Server
CREATE DATABASE Lookup;
CREATE TABLE Lookup.ShardLookup (
  `shardId` int(10) unsigned NOT NULL,
  `entityId` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`entityId`)
) ENGINE=InnoDB;

# Shard1
CREATE DATABASE Shard1; 
CREATE TABLE Shard1.Ex1 (
    userId BIGINT UNSIGNED NOT NULL,
    name VARCHAR(100) NOT NULL,
    birthDate DATE NOT NULL,
    PRIMARY KEY(userId)
) Engine=InnoDB;

# Shard2
CREATE DATABASE Shard2;
CREATE TABLE Shard2.Ex1 (
    userId BIGINT UNSIGNED NOT NULL,
    name VARCHAR(100) NOT NULL,
    birthDate DATE NOT NULL,
    PRIMARY KEY(userId)
) Engine=InnoDB;

