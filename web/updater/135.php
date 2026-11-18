<?php
    if ( !defined('IN_UPDATER') )
    {
        die('Do not access this file directly.');
    }

    $dbversion = 135;
    $version = "1.16.5";

    // Version bump only: Daemon Control only sends commands to this machine and the Proxy_Daemons.

    // Perform database schema update notification
    print "Updating database and version schema numbers.<br />";
    $db->query("UPDATE hlstats_Options SET `value` = '$version' WHERE `keyname` = 'version'");
    $db->query("UPDATE hlstats_Options SET `value` = '$dbversion' WHERE `keyname` = 'dbversion'");
?>
