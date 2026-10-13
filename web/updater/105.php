<?php
    if ( !defined('IN_UPDATER') )
    {
        die('Do not access this file directly.');
    }

    $dbversion = 105;
    $version = "1.9.6";

    // Version bump only: adopts upstream daemon fixes (TRCON packet_id
    // shadowing fix, player count query caching, GeoLiteCity dead script
    // cleanup). No schema change.

    // Perform database schema update notification
    print "Updating database and verion schema numbers.<br />";
    $db->query("UPDATE hlstats_Options SET `value` = '$version' WHERE `keyname` = 'version'");
    $db->query("UPDATE hlstats_Options SET `value` = '$dbversion' WHERE `keyname` = 'dbversion'");
?>
