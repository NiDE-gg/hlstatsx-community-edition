<?php
    if ( !defined('IN_UPDATER') )
    {
        die('Do not access this file directly.');
    }

    $dbversion = 125;
    $version = "1.14.3";

    // Version bump only: bug fixes in the SourceMod superlogs-* plugins (cvars applied on
    // reload, KTRAJ off by default, weapon stats hitgroups). Update sourcemod/plugins/superlogs-*.smx
    // on the game servers.

    // Perform database schema update notification
    print "Updating database and verion schema numbers.<br />";
    $db->query("UPDATE hlstats_Options SET `value` = '$version' WHERE `keyname` = 'version'");
    $db->query("UPDATE hlstats_Options SET `value` = '$dbversion' WHERE `keyname` = 'dbversion'");
?>
