<?php
    if ( !defined('IN_UPDATER') )
    {
        die('Do not access this file directly.');
    }

    $dbversion = 137;
    $version = "1.16.7";

    // Version bump only: every admin write checks the CSRF token itself.

    // Perform database schema update notification
    print "Updating database and version schema numbers.<br />";
    $db->query("UPDATE hlstats_Options SET `value` = '$version' WHERE `keyname` = 'version'");
    $db->query("UPDATE hlstats_Options SET `value` = '$dbversion' WHERE `keyname` = 'dbversion'");
?>
