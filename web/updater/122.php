<?php
    if ( !defined('IN_UPDATER') )
    {
        die('Do not access this file directly.');
    }

    $dbversion = 122;
    $version = "1.14.0";

    // Failed admin logins per IP and per username: logins are refused for 15 minutes
    // after too many failures (see Auth::LOCKOUT_WINDOW in includes/class_auth.php).
    print "Creating hlstats_LoginFailures table.<br />";

    $db->query("
        CREATE TABLE IF NOT EXISTS `hlstats_LoginFailures` (
          `key` varchar(80) NOT NULL,
          `failures` int(10) unsigned NOT NULL default '0',
          `last_failure` int(10) unsigned NOT NULL default '0',
          PRIMARY KEY  (`key`)
        ) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    // Perform database schema update notification
    print "Updating database and verion schema numbers.<br />";
    $db->query("UPDATE hlstats_Options SET `value` = '$version' WHERE `keyname` = 'version'");
    $db->query("UPDATE hlstats_Options SET `value` = '$dbversion' WHERE `keyname` = 'dbversion'");
?>
