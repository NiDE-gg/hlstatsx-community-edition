<?php
    if ( !defined('IN_UPDATER') )
    {
        die('Do not access this file directly.');
    }

    $dbversion = 101;
    $version = "1.9.2";

    // Version bump only: reworks the mobile layout into css/mobile.css, loaded
    // after the theme stylesheet (server cards, nav grid, fitted charts,
    // fewer ranking columns on small screens). No schema change.

    // Perform database schema update notification
    print "Updating database and verion schema numbers.<br />";
    $db->query("UPDATE hlstats_Options SET `value` = '$version' WHERE `keyname` = 'version'");
    $db->query("UPDATE hlstats_Options SET `value` = '$dbversion' WHERE `keyname` = 'dbversion'");
?>
