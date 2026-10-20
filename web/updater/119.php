<?php
    if ( !defined('IN_UPDATER') )
    {
        die('Do not access this file directly.');
    }

    $dbversion = 119;
    $version = "1.12.0";

    // The daemon records its build (commit, build date) and start time here when it
    // starts; Admin > Daemon Control shows them. opttype 2: read by the web only.
    $db->query("INSERT IGNORE INTO hlstats_Options (`keyname`, `value`, `opttype`) VALUES ('daemon_build', '', 2), ('daemon_started', '0', 2)");

    // Perform database schema update notification
    print "Updating database and verion schema numbers.<br />";
    $db->query("UPDATE hlstats_Options SET `value` = '$version' WHERE `keyname` = 'version'");
    $db->query("UPDATE hlstats_Options SET `value` = '$dbversion' WHERE `keyname` = 'dbversion'");
?>
