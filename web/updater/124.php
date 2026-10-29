<?php
    if ( !defined('IN_UPDATER') )
    {
        die('Do not access this file directly.');
    }

    $dbversion = 124;
    $version = "1.14.2";

    // Version bump only: bug fixes in the CS:GO ingame plugin (hlstatsx-csgo.sp 1.6.20).
    // Update sourcemod/plugins/hlstatsx-csgo.smx on the CS:GO game servers.

    // Perform database schema update notification
    print "Updating database and verion schema numbers.<br />";
    $db->query("UPDATE hlstats_Options SET `value` = '$version' WHERE `keyname` = 'version'");
    $db->query("UPDATE hlstats_Options SET `value` = '$dbversion' WHERE `keyname` = 'dbversion'");
?>
