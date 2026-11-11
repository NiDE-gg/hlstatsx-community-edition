<?php
    if ( !defined('IN_UPDATER') )
    {
        die('Do not access this file directly.');
    }

    $dbversion = 130;
    $version = "1.16.0";

    // Version bump only: second security pass and PHP 8.5 clean-up of the web pages,
    // daemon and plugin fixes, removal of the TeamSpeak 2 / Ventrilo live viewers.

    // Perform database schema update notification
    print "Updating database and verion schema numbers.<br />";
    $db->query("UPDATE hlstats_Options SET `value` = '$version' WHERE `keyname` = 'version'");
    $db->query("UPDATE hlstats_Options SET `value` = '$dbversion' WHERE `keyname` = 'dbversion'");
?>
