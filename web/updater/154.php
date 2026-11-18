<?php
    if ( !defined('IN_UPDATER') )
    {
        die('Do not access this file directly.');
    }

    $dbversion = 154;
    $version = "1.18.8";

    // Version bump only: plugins for CS:GO, CSPromod and Insurgency 2007 removed.

    // Perform database schema update notification
    print "Updating database and version schema numbers.<br />";
    $db->query("UPDATE hlstats_Options SET `value` = '$version' WHERE `keyname` = 'version'");
    $db->query("UPDATE hlstats_Options SET `value` = '$dbversion' WHERE `keyname` = 'dbversion'");
?>
