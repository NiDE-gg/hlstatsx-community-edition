<?php
    if ( !defined('IN_UPDATER') )
    {
        die('Do not access this file directly.');
    }

    $dbversion = 168;
    $version = "1.18.22";

    // Version bump only: the daemon stops sending a refused rcon password and backs off after an unanswered rcon auth.

    // Perform database schema update notification
    print "Updating database and version schema numbers.<br />";
    $db->query("UPDATE hlstats_Options SET `value` = '$version' WHERE `keyname` = 'version'");
    $db->query("UPDATE hlstats_Options SET `value` = '$dbversion' WHERE `keyname` = 'dbversion'");
