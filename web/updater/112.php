<?php
    if ( !defined('IN_UPDATER') )
    {
        die('Do not access this file directly.');
    }

    $dbversion = 112;
    $version = "1.10.6";

    // Version bump only: the daemon assigns hlstats_Options values directly instead
    // of through a string eval, and its helper scripts quote or avoid the shell for
    // external input (GitHub issue #81). No schema change.

    // Perform database schema update notification
    print "Updating database and verion schema numbers.<br />";
    $db->query("UPDATE hlstats_Options SET `value` = '$version' WHERE `keyname` = 'version'");
    $db->query("UPDATE hlstats_Options SET `value` = '$dbversion' WHERE `keyname` = 'dbversion'");
?>
