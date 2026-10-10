<?php
    if ( !defined('IN_UPDATER') )
    {
        die('Do not access this file directly.');
    }

    $dbversion = 81;
    $version = "1.6.20";

    // Cache of Steam community avatar / online state, so player pages stop calling steamcommunity.com on every view.
    print "Creating hlstats_SteamProfiles cache table.<br />";

    $db->query("
        CREATE TABLE IF NOT EXISTS `hlstats_SteamProfiles` (
          `communityId` bigint(20) unsigned NOT NULL,
          `status` varchar(32) NOT NULL default '',
          `avatar` varchar(255) NOT NULL default '',
          `fetched` int(10) unsigned NOT NULL default '0',
          PRIMARY KEY  (`communityId`)
        ) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    // Perform database schema update notification
    print "Updating database and verion schema numbers.<br />";
    $db->query("UPDATE hlstats_Options SET `value` = '$version' WHERE `keyname` = 'version'");
    $db->query("UPDATE hlstats_Options SET `value` = '$dbversion' WHERE `keyname` = 'dbversion'");
?>
