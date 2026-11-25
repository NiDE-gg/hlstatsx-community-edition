<?php
    if ( !defined('IN_UPDATER') )
    {
        die('Do not access this file directly.');
    }

    $dbversion = 173;
    $version = "1.19.1";

    // Admin sign-in history: every sign-in attempt and password confirmation (time,
    // account, address, method, result), kept 90 days, shown in Tools > Sign-in History.

    print "Creating table hlstats_LoginLog.<br />";
    $db->query("
        CREATE TABLE IF NOT EXISTS `hlstats_LoginLog` (
          `id` int(10) unsigned NOT NULL auto_increment,
          `time` int(10) unsigned NOT NULL default '0',
          `username` varchar(64) NOT NULL default '',
          `address` varchar(45) NOT NULL default '',
          `method` varchar(16) NOT NULL default '',
          `result` varchar(16) NOT NULL default '',
          PRIMARY KEY (`id`),
          KEY `time` (`time`),
          KEY `username` (`username`, `time`),
          KEY `address` (`address`, `time`)
        ) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    // Perform database schema update notification
    print "Updating database and version schema numbers.<br />";
    $db->query("UPDATE hlstats_Options SET `value` = '$version' WHERE `keyname` = 'version'");
    $db->query("UPDATE hlstats_Options SET `value` = '$dbversion' WHERE `keyname` = 'dbversion'");
?>
