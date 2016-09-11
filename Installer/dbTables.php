<?php
	$dbTables = array();
	$dbTables['playerinfo']="CREATE TABLE `playerinfo` (
		 `id` int(11) NOT NULL,
		 `name` text,
		 `skindata` text,
		 `country` varchar(3) DEFAULT NULL,
		 PRIMARY KEY (`id`)
		) ENGINE=InnoDB DEFAULT CHARSET=latin1";

	$dbTables['playerhistory']="CREATE TABLE `playerhistory` (
		 `recordid` int(11) NOT NULL,
		 `id` int(11) DEFAULT NULL,
		 `serverid` int(11) DEFAULT NULL,
		 `gameid` int(11) DEFAULT NULL,
		 `numupdates` smallint(6) DEFAULT NULL,
		 `lastupdate` int(11) DEFAULT NULL,
		 `enterdate` int(11) DEFAULT NULL,
		 `scorethismatch` int(11) DEFAULT NULL,
		 `pingsum` int(11) DEFAULT NULL,
		 `deathsthismatch` smallint(6) DEFAULT NULL,
		 `team` tinyint(4) DEFAULT NULL,
		 `flags` int(11) NOT NULL DEFAULT '0',
		 PRIMARY KEY (`recordid`),
		 KEY `ph_gid_idx` (`gameid`) USING HASH,
		 KEY `ph_pid_idx` (`id`) USING HASH,
		 KEY `ph_sid_idx` (`serverid`) USING HASH,
		 KEY `ph_lup_idx` (`lastupdate`) USING BTREE,
		 KEY `ph_pidsid_idx` (`serverid`,`id`)
		) ENGINE=InnoDB DEFAULT CHARSET=latin1";

	$dbTables['playerhistorythin']="CREATE TABLE `playerhistorythin` (
		 `recordid` int(11) NOT NULL,
		 `id` int(11) DEFAULT NULL,
		 `serverid` int(11) DEFAULT NULL,
		 `gameid` int(11) DEFAULT NULL,
		 `numupdates` smallint(6) DEFAULT NULL,
		 `lastupdate` int(11) DEFAULT NULL,
		 `enterdate` int(11) DEFAULT NULL,
		 `scorethismatch` int(11) DEFAULT NULL,
		 `pingsum` int(11) DEFAULT NULL,
		 `deathsthismatch` smallint(6) DEFAULT NULL,
		 `team` tinyint(4) DEFAULT NULL,
		 `flags` int(11) NOT NULL DEFAULT '0',
		 PRIMARY KEY (`recordid`),
		 KEY `phthin_gid_idx` (`gameid`) USING HASH,
		 KEY `phthin_pid_idx` (`id`) USING HASH,
		 KEY `phthin_sid_idx` (`serverid`) USING HASH,
		 KEY `phthin_lup_idx` (`lastupdate`) USING BTREE
		) ENGINE=InnoDB DEFAULT CHARSET=latin1";

	$dbTables['serverinfo']="CREATE TABLE `serverinfo` (
		 `serverid` int(11) NOT NULL,
		 `address` text,
		 `name` text,
		 `rules` text,
		 `lastscan` int(11) NOT NULL,
		 `lastrfupdate` int(11) NOT NULL,
		 `rfscore` int(11) NOT NULL,
		 `uplayers` int(11) NOT NULL,
		 `country` varchar(3) NOT NULL,
		 `gamename` varchar(20) NOT NULL,
		 PRIMARY KEY (`serverid`)
		) ENGINE=InnoDB DEFAULT CHARSET=latin1";

	$dbTables['serverhistory']="CREATE TABLE `serverhistory` (
		 `gameid` int(11) NOT NULL AUTO_INCREMENT,
		 `serverid` int(11) DEFAULT NULL,
		 `date` int(11) DEFAULT NULL,
		 `mapname` text,
		 PRIMARY KEY (`gameid`),
		 KEY `sh_sid_idx` (`serverid`) USING HASH,
		 KEY `sh_dat_idx` (`date`) USING BTREE,
		 KEY `sh_map_idx` (`mapname`(40))
		) ENGINE=InnoDB DEFAULT CHARSET=latin1";

	$dbTables['playerstats']="CREATE TABLE `playerstats` (
		 `playerid` int(11) NOT NULL,
		 `serverid` int(11) NOT NULL,
		 `time` int(11) NOT NULL,
		 `numupdates` int(11) NOT NULL,
		 `deaths` int(11) NOT NULL,
		 `score` bigint(20) NOT NULL,
		 `lastgame` int(11) NOT NULL,
		 PRIMARY KEY (`serverid`,`playerid`),
		 KEY `ps_sid_idx` (`serverid`),
		 KEY `ps_pid_idx` (`playerid`)
		) ENGINE=InnoDB DEFAULT CHARSET=latin1";
	$dbTables['mapinfo']="CREATE TABLE `mapinfo` (
		 `mapid` int(11) NOT NULL,
		 `mapname` text CHARACTER SET latin1 NOT NULL,
		 `author` text CHARACTER SET latin1 NOT NULL,
		 `downloadurl` text CHARACTER SET latin1 NOT NULL,
		 `reportVersion` int(11) NOT NULL,
		 `sizeX` int(11) NOT NULL,
		 `sizeY` int(11) NOT NULL,
		 `sizeZ` int(11) NOT NULL,
		 `brushCSGADD` int(11) NOT NULL,
		 `brushCSGSUB` int(11) NOT NULL,
		 `zones` int(11) NOT NULL,
		 `lightwattage` int(11) NOT NULL,
		 `numTextures` int(11) NOT NULL,
		 `numClasses` int(11) NOT NULL,
		 UNIQUE KEY `mapid` (`mapid`)
		) ENGINE=InnoDB DEFAULT CHARSET=latin1";
		
	$dbTables['mapdownloadqueue']="CREATE TABLE `mapdownloadqueue` (
		 `recordid` int(11) NOT NULL AUTO_INCREMENT,
		 `mapname` text CHARACTER SET latin1 NOT NULL,
		 `jobType` int(11) NOT NULL DEFAULT '2147483647',
		 PRIMARY KEY (`recordid`)
		) ENGINE=InnoDB AUTO_INCREMENT=251 DEFAULT CHARSET=latin1";
		
	$dbTables['utt_info']="CREATE TABLE `utt_info` (
		 `key` varchar(48) NOT NULL,
		 `data` text,
		 `private` boolean,
		 PRIMARY KEY (`key`),
		 UNIQUE KEY `key` (`key`)
		) ENGINE=InnoDB DEFAULT CHARSET=latin1";
		
	$dbTables['btrecords']="CREATE TABLE `btrecords` (
		 `mapname` varchar(64) CHARACTER SET latin1 NOT NULL,
		 `source` int(11) NOT NULL,
		 `player` text CHARACTER SET latin1 NOT NULL,
		 `record` double NOT NULL,
		 PRIMARY KEY (`mapname`,`source`)
		) ENGINE=InnoDB DEFAULT CHARSET=latin1";
			
	$dbTables['tinyscanschedule']="CREATE TABLE `tinyscanschedule` (
		 `address` varchar(21) CHARACTER SET latin1 NOT NULL,
		 `time` int(11) NOT NULL,
		 `status` int(11) NOT NULL,
		 `scannedtime` int(11) NOT NULL DEFAULT '0',
		 PRIMARY KEY (`address`)
		) ENGINE=InnoDB DEFAULT CHARSET=latin1";

	$dbTables['serverqueue']="CREATE TABLE `serverqueue` (
		 `address` text CHARACTER SET latin1 NOT NULL,
		 `flags` int(11) NOT NULL DEFAULT '0'
		) ENGINE=InnoDB DEFAULT CHARSET=latin1";
		