<?php
    if ( !defined('IN_UPDATER') )
    {
        die('Do not access this file directly.');
    }

    $dbversion = 121;
    $version = "1.13.1";

    // Version bump only: daemon and Docker fixes (proxied lines containing "PROXY",
    // queued events lost on KILL / SIGTERM, DB credentials with special characters
    // in the Docker entrypoints) and a default search type in the search pages.

    // Perform database schema update notification
    print "Updating database and verion schema numbers.<br />";
    $db->query("UPDATE hlstats_Options SET `value` = '$version' WHERE `keyname` = 'version'");
    $db->query("UPDATE hlstats_Options SET `value` = '$dbversion' WHERE `keyname` = 'dbversion'");
?>
