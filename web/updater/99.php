<?php
    if ( !defined('IN_UPDATER') )
    {
        die('Do not access this file directly.');
    }

    $dbversion = 99;
    $version = "1.9.0";

    // Version bump only: adds a viewport meta tag and baseline mobile-responsive
    // CSS (nav stacking, scrollable data tables) for the default/shared layout.
    // No schema changes.

    // Perform database schema update notification
    print "Updating database and verion schema numbers.<br />";
    $db->query("UPDATE hlstats_Options SET `value` = '$version' WHERE `keyname` = 'version'");
    $db->query("UPDATE hlstats_Options SET `value` = '$dbversion' WHERE `keyname` = 'dbversion'");
?>
