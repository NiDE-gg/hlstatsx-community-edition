<?php
    if ( !defined('IN_UPDATER') )
    {
        die('Do not access this file directly.');
    }

    $dbversion = 123;
    $version = "1.14.1";

    // Version bump only: the SourceMod ingame plugin (hlstatsx.sp 1.7.0) was rewritten in the
    // new syntax for SourceMod 1.12/1.13. Update sourcemod/plugins/hlstatsx.smx on the game servers.

    // Perform database schema update notification
    print "Updating database and verion schema numbers.<br />";
    $db->query("UPDATE hlstats_Options SET `value` = '$version' WHERE `keyname` = 'version'");
    $db->query("UPDATE hlstats_Options SET `value` = '$dbversion' WHERE `keyname` = 'dbversion'");
?>
